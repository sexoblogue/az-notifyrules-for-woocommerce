<?php

defined( 'ABSPATH' ) || exit;

final class AZ_Woo_Alerts_Pushover_Provider implements AZ_Woo_Alerts_Channel_Provider {
	private const API_URL      = 'https://api.pushover.net/1/messages.json';
	private const VALIDATE_URL = 'https://api.pushover.net/1/users/validate.json';

	public function get_type(): string {
		return 'pushover';
	}

	public function get_label(): string {
		return __( 'Pushover', 'az-notifyrules-for-woocommerce' );
	}

	public function is_email_router(): bool {
		return false;
	}

	public function sanitize( array $raw, array $existing = array() ) {
		$channel = array(
			'id'               => sanitize_key( $raw['id'] ?? '' ),
			'name'             => sanitize_text_field( $raw['name'] ?? '' ),
			'type'             => $this->get_type(),
			'enabled'          => ! empty( $raw['enabled'] ),
			'device'           => sanitize_text_field( $raw['device'] ?? '' ),
			'priority'         => in_array( (string) ( $raw['priority'] ?? '0' ), array( '-1', '0', '1' ), true ) ? (int) $raw['priority'] : 0,
			'sound'            => sanitize_key( $raw['sound'] ?? '' ),
			'title'            => sanitize_text_field( $raw['title'] ?? __( 'New WooCommerce order', 'az-notifyrules-for-woocommerce' ) ),
			'include_products' => ! empty( $raw['include_products'] ),
			'include_amount'   => ! empty( $raw['include_amount'] ),
			'include_customer' => ! empty( $raw['include_customer'] ),
		);

		if ( '' === $channel['id'] ) {
			$channel['id'] = 'pushover_' . wp_generate_uuid4();
		}

		if ( '' === $channel['name'] ) {
			return new WP_Error( 'az_woo_alerts_missing_name', __( 'Each connection must have a name.', 'az-notifyrules-for-woocommerce' ) );
		}

		foreach ( array( 'app_token', 'user_key' ) as $secret_key ) {
			$plain = trim( (string) ( $raw[ $secret_key ] ?? '' ) );
			if ( AZ_Woo_Alerts_Crypto::STORED_MASK === $plain ) {
				$plain = '';
			}
			if ( '' === $plain ) {
				$channel[ $secret_key ] = (string) ( $existing[ $secret_key ] ?? '' );
				continue;
			}

			if ( ! preg_match( '/^[A-Za-z0-9]{20,80}$/', $plain ) ) {
				return new WP_Error( 'az_woo_alerts_invalid_pushover_secret', __( 'A Pushover credential does not have the expected format.', 'az-notifyrules-for-woocommerce' ) );
			}

			$encrypted = AZ_Woo_Alerts_Crypto::encrypt( $plain );
			if ( is_wp_error( $encrypted ) ) {
				return $encrypted;
			}
			$channel[ $secret_key ] = $encrypted;
		}

		if ( $channel['enabled'] && ( empty( $channel['app_token'] ) || empty( $channel['user_key'] ) ) ) {
			return new WP_Error( 'az_woo_alerts_missing_pushover_secret', __( 'An active Pushover connection must contain an application token and a user or group key.', 'az-notifyrules-for-woocommerce' ) );
		}

		return $channel;
	}

	/**
	 * Returns active devices associated with the connection user key.
	 *
	 * @return array<int,string>|WP_Error
	 */
	public function get_devices( array $channel ) {
		$app_token = AZ_Woo_Alerts_Crypto::decrypt( (string) ( $channel['app_token'] ?? '' ) );
		$user_key  = AZ_Woo_Alerts_Crypto::decrypt( (string) ( $channel['user_key'] ?? '' ) );

		if ( '' === $app_token || '' === $user_key ) {
			return new WP_Error( 'az_woo_alerts_missing_pushover_secret', __( 'Save the Pushover token and key first.', 'az-notifyrules-for-woocommerce' ) );
		}

		$cache_key = 'az_woo_alerts_devices_' . md5(
			(string) ( $channel['id'] ?? '' ) . '|' .
			(string) ( $channel['app_token'] ?? '' ) . '|' .
			(string) ( $channel['user_key'] ?? '' )
		);
		$cached = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$response = wp_remote_post(
			self::VALIDATE_URL,
			array(
				'timeout' => 12,
				'body'    => array(
					'token' => $app_token,
					'user'  => $user_key,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'az_woo_alerts_pushover_devices_http', $response->get_error_message() );
		}

		$status = wp_remote_retrieve_response_code( $response );
		$data   = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $status < 200 || $status >= 300 || 1 !== (int) ( $data['status'] ?? 0 ) ) {
			return new WP_Error( 'az_woo_alerts_pushover_devices_rejected', __( 'Pushover could not provide the device list.', 'az-notifyrules-for-woocommerce' ) );
		}

		$devices = array();
		foreach ( (array) ( $data['devices'] ?? array() ) as $device ) {
			$device = sanitize_text_field( (string) $device );
			if ( preg_match( '/^[A-Za-z0-9_-]{1,25}$/', $device ) ) {
				$devices[] = $device;
			}
		}
		$devices = array_values( array_unique( $devices ) );
		set_transient( $cache_key, $devices, 15 * MINUTE_IN_SECONDS );

		return $devices;
	}

	public function dispatch( WC_Order $order, array $channel, bool $test = false ) {
		$app_token = AZ_Woo_Alerts_Crypto::decrypt( (string) ( $channel['app_token'] ?? '' ) );
		$user_key  = AZ_Woo_Alerts_Crypto::decrypt( (string) ( $channel['user_key'] ?? '' ) );

		if ( '' === $app_token || '' === $user_key ) {
			return new WP_Error( 'az_woo_alerts_missing_pushover_secret', __( 'The Pushover application token or user key is missing.', 'az-notifyrules-for-woocommerce' ) );
		}

		$title = $test
				/* translators: %s: connection name. */
				? sprintf( __( 'Test %s', 'az-notifyrules-for-woocommerce' ), (string) ( $channel['name'] ?? 'Pushover' ) )
			: (string) ( $channel['title'] ?? __( 'New WooCommerce order', 'az-notifyrules-for-woocommerce' ) );

		$body = $test
				/* translators: %s: order number. */
				? sprintf( __( 'The Pushover connection works. Reference order: #%s.', 'az-notifyrules-for-woocommerce' ), $order->get_order_number() )
			: $this->build_message( $order, $channel );

		$payload = array(
			'token'     => $app_token,
			'user'      => $user_key,
			'title'     => $this->truncate( $title, 250 ),
			'message'   => $this->truncate( $body, 1024 ),
			'priority'  => (int) ( $channel['priority'] ?? 0 ),
			'timestamp' => $order->get_date_created() ? $order->get_date_created()->getTimestamp() : time(),
			'url'       => $order->get_edit_order_url(),
				/* translators: %s: order number. */
				'url_title' => sprintf( __( 'Open order #%s', 'az-notifyrules-for-woocommerce' ), $order->get_order_number() ),
		);

		if ( ! empty( $channel['device'] ) ) {
			$payload['device'] = (string) $channel['device'];
		}
		if ( ! empty( $channel['sound'] ) ) {
			$payload['sound'] = (string) $channel['sound'];
		}

		/**
			 * Allows the payload to be adapted without exposing secrets in logs.
		 *
		 * @param array<string,mixed> $payload
		 * @param WC_Order            $order
		 * @param array<string,mixed> $channel
		 * @param bool                 $test
		 */
		$payload = apply_filters( 'az_woo_alerts_pushover_payload', $payload, $order, $channel, $test );

		$response = wp_remote_post(
			self::API_URL,
			array(
				'timeout' => 12,
				'body'    => $payload,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'az_woo_alerts_pushover_http', $response->get_error_message() );
		}

		$status = wp_remote_retrieve_response_code( $response );
		$data   = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $status < 200 || $status >= 300 || 1 !== (int) ( $data['status'] ?? 0 ) ) {
			$errors = isset( $data['errors'] ) && is_array( $data['errors'] ) ? implode( ' ', array_map( 'sanitize_text_field', $data['errors'] ) ) : '';
				/* translators: %d: HTTP response status code. */
				return new WP_Error( 'az_woo_alerts_pushover_rejected', $errors ?: sprintf( __( 'Pushover returned HTTP status %d.', 'az-notifyrules-for-woocommerce' ), $status ) );
		}

		return true;
	}

	private function build_message( WC_Order $order, array $channel ): string {
			/* translators: %s: order number. */
			$lines = array( sprintf( __( 'Order #%s', 'az-notifyrules-for-woocommerce' ), $order->get_order_number() ) );

		if ( ! empty( $channel['include_products'] ) ) {
			$products = array();
			foreach ( $order->get_items( 'line_item' ) as $item ) {
				$products[] = sprintf( '%s × %d', wp_strip_all_tags( $item->get_name() ), $item->get_quantity() );
			}
			if ( $products ) {
				$lines[] = implode( ', ', $products );
			}
		}

		if ( ! empty( $channel['include_amount'] ) ) {
				/* translators: %s: formatted order total. */
				$lines[] = sprintf( __( 'Amount: %s', 'az-notifyrules-for-woocommerce' ), wp_strip_all_tags( $order->get_formatted_order_total() ) );
		}

		if ( ! empty( $channel['include_customer'] ) ) {
			$name = trim( $order->get_formatted_billing_full_name() );
			if ( $name ) {
					/* translators: %s: customer name. */
					$lines[] = sprintf( __( 'Customer: %s', 'az-notifyrules-for-woocommerce' ), $name );
			}
		}

		return implode( "\n", $lines );
	}

	private function truncate( string $value, int $length ): string {
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $length ) : substr( $value, 0, $length );
	}
}
