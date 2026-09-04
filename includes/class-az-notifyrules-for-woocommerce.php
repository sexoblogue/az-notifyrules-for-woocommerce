<?php

defined( 'ABSPATH' ) || exit;

final class AZ_Woo_Alerts {
	public const OPTION_CHANNELS = 'az_woo_alerts_channels';
	public const OPTION_RULES    = 'az_woo_alerts_rules';
	private const SENT_META      = '_az_woo_alerts_sent_channels';

	private static ?self $instance = null;

	/** @var array<string,AZ_Woo_Alerts_Channel_Provider>|null */
	private ?array $providers = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		foreach ( $this->new_order_notification_hooks() as $hook ) {
			add_action( $hook, array( $this, 'dispatch_order_alerts' ), 20, 2 );
		}

		add_filter( 'woocommerce_email_recipient_new_order', array( $this, 'route_to_recipients' ), 20, 3 );
		add_filter( 'woocommerce_email_cc_recipient_new_order', array( $this, 'route_cc_recipients' ), 20, 3 );
		add_filter( 'woocommerce_email_bcc_recipient_new_order', array( $this, 'route_bcc_recipients' ), 20, 3 );
		add_filter( 'woocommerce_order_actions', array( $this, 'register_retry_order_action' ) );
		add_action( 'woocommerce_order_action_az_woo_alerts_retry', array( $this, 'retry_order_alerts' ) );

		if ( is_admin() ) {
			new AZ_Woo_Alerts_Admin( $this );
		}
	}

	/**
	 * @return array<string,AZ_Woo_Alerts_Channel_Provider>
	 */
	public function providers(): array {
		if ( null !== $this->providers ) {
			return $this->providers;
		}

		$providers = array(
			'pushover' => new AZ_Woo_Alerts_Pushover_Provider(),
			'email'    => new AZ_Woo_Alerts_Email_Provider(),
		);

		/**
		 * Registers additional connection providers.
		 *
		 * @param array<string,AZ_Woo_Alerts_Channel_Provider> $providers
		 */
		$providers = apply_filters( 'az_woo_alerts_channel_providers', $providers );

		$this->providers = array_filter(
			$providers,
			static fn( $provider ) => $provider instanceof AZ_Woo_Alerts_Channel_Provider
		);

		return $this->providers;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function channels(): array {
		$value = get_option( self::OPTION_CHANNELS, array() );
		return is_array( $value ) ? array_values( $value ) : array();
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function rules(): array {
		$value = get_option( self::OPTION_RULES, array() );
		return is_array( $value ) ? array_values( $value ) : array();
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	public function channels_by_id(): array {
		$indexed = array();
		foreach ( $this->channels() as $channel ) {
			if ( ! empty( $channel['id'] ) ) {
				$indexed[ (string) $channel['id'] ] = $channel;
			}
		}
		return $indexed;
	}

	public function dispatch_order_alerts( int $order_id, $order = false ): void {
		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$channels = $this->matching_channels( $order );
		$sent     = $order->get_meta( self::SENT_META, true );
		$sent     = is_array( $sent ) ? $sent : array();
		do_action( 'az_woo_alerts_order_matched', $order, $channels );

		foreach ( $channels as $channel ) {
			$id       = (string) ( $channel['id'] ?? '' );
			$type     = (string) ( $channel['type'] ?? '' );
			$provider = $this->providers()[ $type ] ?? null;

			if ( '' === $id || ! $provider || $provider->is_email_router() || isset( $sent[ $id ] ) ) {
				continue;
			}

			/** @param WC_Order $order @param array<string,mixed> $channel */
			do_action( 'az_woo_alerts_before_dispatch', $order, $channel );
			$result = $provider->dispatch( $order, $channel, false );

			if ( true === $result ) {
				$sent[ $id ] = gmdate( 'c' );
				$order->update_meta_data( self::SENT_META, $sent );
				$order->save_meta_data();
				$this->log( 'info', $order, $channel, 'accepted' );
				$order->add_order_note(
					sprintf(
						/* translators: %s: channel name */
						__( 'AZ NotifyRules: request accepted by the “%s” connection.', 'az-notifyrules-for-woocommerce' ),
						(string) ( $channel['name'] ?? $type )
					)
				);
			} else {
				$error = is_wp_error( $result ) ? $result->get_error_message() : __( 'Unknown error.', 'az-notifyrules-for-woocommerce' );
				$this->log( 'error', $order, $channel, $error );
				$order->add_order_note(
					sprintf(
						/* translators: 1: channel name, 2: error */
						__( 'AZ NotifyRules: the “%1$s” connection failed: %2$s', 'az-notifyrules-for-woocommerce' ),
						(string) ( $channel['name'] ?? $type ),
						wp_strip_all_tags( $error )
					)
				);
			}

			/** @param true|WP_Error $result */
			do_action( 'az_woo_alerts_after_dispatch', $order, $channel, $result );
		}
	}

	public function route_to_recipients( string $recipient, $object, $email ): string {
		return $this->route_email_recipients( 'to', $recipient, $object );
	}

	public function route_cc_recipients( string $recipient, $object, $email ): string {
		return $this->route_email_recipients( 'cc', $recipient, $object );
	}

	public function route_bcc_recipients( string $recipient, $object, $email ): string {
		return $this->route_email_recipients( 'bcc', $recipient, $object );
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function matching_channels( WC_Order $order ): array {
		$ids      = array();
		$channels = $this->channels_by_id();

		foreach ( $this->rules() as $rule ) {
			if ( empty( $rule['enabled'] ) || ! $this->rule_matches( $rule, $order ) ) {
				continue;
			}
			foreach ( (array) ( $rule['channel_ids'] ?? array() ) as $channel_id ) {
				$channel_id = (string) $channel_id;
				if ( isset( $channels[ $channel_id ] ) && ! empty( $channels[ $channel_id ]['enabled'] ) ) {
					$ids[ $channel_id ] = true;
				}
			}
		}

		/**
		 * @param array<int,string>                    $ids
		 * @param WC_Order                            $order
		 * @param array<string,array<string,mixed>>   $channels
		 */
		$ids = apply_filters( 'az_woo_alerts_matching_channel_ids', array_keys( $ids ), $order, $channels );

		$matched = array();
		foreach ( array_unique( array_map( 'strval', (array) $ids ) ) as $id ) {
			if ( isset( $channels[ $id ] ) && ! empty( $channels[ $id ]['enabled'] ) ) {
				$matched[] = $channels[ $id ];
			}
		}

		return $matched;
	}

	/**
	 * @param array<string,string> $actions
	 * @return array<string,string>
	 */
	public function register_retry_order_action( array $actions ): array {
		$actions['az_woo_alerts_retry'] = __( 'AZ NotifyRules: retry unsent alerts', 'az-notifyrules-for-woocommerce' );
		return $actions;
	}

	public function retry_order_alerts( WC_Order $order ): void {
		$this->dispatch_order_alerts( $order->get_id(), $order );
	}

	/**
	 * @param array<string,mixed> $rule
	 */
	public function rule_matches( array $rule, WC_Order $order ): bool {
		$type   = (string) ( $rule['match_type'] ?? 'all' );
		$values = array_values( array_filter( array_map( 'absint', (array) ( $rule['match_values'] ?? array() ) ) ) );
		$items  = $this->order_product_context( $order );

		switch ( $type ) {
			case 'all':
				$matches = true;
				break;
			case 'categories':
				$matches = (bool) array_intersect( $values, $items['category_ids'] );
				break;
			case 'products':
				$matches = (bool) array_intersect( $values, $items['product_ids'] );
				break;
			default:
				$matches = false;
		}

		return (bool) apply_filters( 'az_woo_alerts_rule_matches', $matches, $rule, $order, $items );
	}

	/**
	 * @return array{product_ids:array<int,int>,category_ids:array<int,int>}
	 */
	private function order_product_context( WC_Order $order ): array {
		$product_ids  = array();
		$category_ids = array();

		foreach ( $order->get_items( 'line_item' ) as $item ) {
			$product_id   = absint( $item->get_product_id() );
			$variation_id = absint( $item->get_variation_id() );
			$bundle_id    = absint( $item->get_meta( '_woosb_parent_id', true ) );

			foreach ( array( $product_id, $variation_id, $bundle_id ) as $id ) {
				if ( $id ) {
					$product_ids[ $id ] = $id;
				}
			}

			$category_product_id = $product_id;
			if ( $variation_id ) {
				$variation = wc_get_product( $variation_id );
				if ( $variation && $variation->get_parent_id() ) {
					$category_product_id = $variation->get_parent_id();
				}
			}

			foreach ( wc_get_product_term_ids( $category_product_id, 'product_cat' ) as $category_id ) {
				$category_ids[ $category_id ] = $category_id;
			}
		}

		return array(
			'product_ids'  => array_values( $product_ids ),
			'category_ids' => array_values( $category_ids ),
		);
	}

	private function route_email_recipients( string $mode, string $recipient, $object ): string {
		if ( ! $object instanceof WC_Order ) {
			return $recipient;
		}

		$emails = preg_split( '/[\s,;]+/', $recipient, -1, PREG_SPLIT_NO_EMPTY ) ?: array();
		foreach ( $this->matching_channels( $object ) as $channel ) {
			if ( 'email' !== ( $channel['type'] ?? '' ) || $mode !== ( $channel['mode'] ?? 'bcc' ) ) {
				continue;
			}
			$emails = array_merge( $emails, (array) ( $channel['emails'] ?? array() ) );
		}

		$emails = array_values( array_unique( array_filter( array_map( 'sanitize_email', $emails ), 'is_email' ) ) );
		return implode( ', ', $emails );
	}

	private function log( string $level, WC_Order $order, array $channel, string $message ): void {
		if ( ! function_exists( 'wc_get_logger' ) ) {
			return;
		}
		wc_get_logger()->log(
			$level,
			sprintf(
				'order=%d channel=%s type=%s result=%s',
				$order->get_id(),
				sanitize_key( (string) ( $channel['id'] ?? '' ) ),
				sanitize_key( (string) ( $channel['type'] ?? '' ) ),
				wp_strip_all_tags( $message )
			),
			array( 'source' => 'az-notifyrules-for-woocommerce' )
		);
	}

	/**
	 * @return array<int,string>
	 */
	private function new_order_notification_hooks(): array {
		return array(
			'woocommerce_order_status_pending_to_processing_notification',
			'woocommerce_order_status_pending_to_completed_notification',
			'woocommerce_order_status_pending_to_on-hold_notification',
			'woocommerce_order_status_failed_to_processing_notification',
			'woocommerce_order_status_failed_to_completed_notification',
			'woocommerce_order_status_failed_to_on-hold_notification',
			'woocommerce_order_status_cancelled_to_processing_notification',
			'woocommerce_order_status_cancelled_to_completed_notification',
			'woocommerce_order_status_cancelled_to_on-hold_notification',
		);
	}
}
