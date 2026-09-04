<?php

defined( 'ABSPATH' ) || exit;

final class AZ_Woo_Alerts_Email_Provider implements AZ_Woo_Alerts_Channel_Provider {
	public function get_type(): string {
		return 'email';
	}

	public function get_label(): string {
		return __( 'WooCommerce email', 'az-notifyrules-for-woocommerce' );
	}

	public function is_email_router(): bool {
		return true;
	}

	public function sanitize( array $raw, array $existing = array() ) {
		$emails = preg_split( '/[\s,;]+/', (string) ( $raw['emails'] ?? '' ), -1, PREG_SPLIT_NO_EMPTY );
		$emails = array_values( array_unique( array_filter( array_map( 'sanitize_email', $emails ?: array() ), 'is_email' ) ) );
		$mode   = in_array( (string) ( $raw['mode'] ?? 'bcc' ), array( 'to', 'cc', 'bcc' ), true ) ? (string) $raw['mode'] : 'bcc';
		$name   = sanitize_text_field( $raw['name'] ?? '' );

		if ( '' === $name ) {
			return new WP_Error( 'az_woo_alerts_missing_name', __( 'Each connection must have a name.', 'az-notifyrules-for-woocommerce' ) );
		}
		if ( ! $emails ) {
			return new WP_Error( 'az_woo_alerts_missing_email', __( 'The email connection must contain at least one valid address.', 'az-notifyrules-for-woocommerce' ) );
		}

		return array(
			'id'      => sanitize_key( $raw['id'] ?? '' ) ?: 'email_' . wp_generate_uuid4(),
			'name'    => $name,
			'type'    => $this->get_type(),
			'enabled' => ! empty( $raw['enabled'] ),
			'emails'  => $emails,
			'mode'    => $mode,
		);
	}

	public function dispatch( WC_Order $order, array $channel, bool $test = false ) {
		if ( ! $test ) {
			return new WP_Error( 'az_woo_alerts_email_routed', __( 'This connection is injected into the WooCommerce New order email.', 'az-notifyrules-for-woocommerce' ) );
		}

		$recipients = implode( ',', (array) ( $channel['emails'] ?? array() ) );
		/* translators: %s: order number. */
		$subject = sprintf( __( '[TEST] WooCommerce alert #%s', 'az-notifyrules-for-woocommerce' ), $order->get_order_number() );
		/* translators: 1: connection name, 2: order number. */
		$message = sprintf( __( 'The email connection “%1$s” works. Reference order: #%2$s.', 'az-notifyrules-for-woocommerce' ), (string) ( $channel['name'] ?? '' ), $order->get_order_number() );

		return wp_mail( $recipients, $subject, $message )
			? true
			: new WP_Error( 'az_woo_alerts_email_failed', __( 'WordPress did not accept the test email.', 'az-notifyrules-for-woocommerce' ) );
	}
}
