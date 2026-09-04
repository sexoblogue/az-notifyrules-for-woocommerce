<?php
/**
 * Removes plugin settings when the plugin is deleted from WordPress.
 *
 * Order notes and delivery metadata are retained as part of the store's order
 * history. They contain connection identifiers and delivery results, not API
 * credentials.
 *
 * @package AZ_Woo_Alerts
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'az_woo_alerts_channels' );
delete_option( 'az_woo_alerts_rules' );

