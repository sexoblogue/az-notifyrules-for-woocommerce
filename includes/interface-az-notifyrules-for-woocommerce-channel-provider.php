<?php

defined( 'ABSPATH' ) || exit;

interface AZ_Woo_Alerts_Channel_Provider {
	public function get_type(): string;

	public function get_label(): string;

	/**
	 * Returns true for connections injected into the WooCommerce email.
	 */
	public function is_email_router(): bool;

	/**
	 * @param array<string,mixed> $raw
	 * @param array<string,mixed> $existing
	 * @return array<string,mixed>|WP_Error
	 */
	public function sanitize( array $raw, array $existing = array() );

	/**
	 * @param WC_Order            $order
	 * @param array<string,mixed> $channel
	 * @param bool                 $test
	 * @return true|WP_Error
	 */
	public function dispatch( WC_Order $order, array $channel, bool $test = false );
}
