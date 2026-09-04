<?php
/**
 * Plugin Name: AZ NotifyRules for WooCommerce
 * Plugin URI: https://www.zeler.fr/plugins/az-notifyrules-for-woocommerce/
 * Description: Route new WooCommerce order alerts by product or category to Pushover and additional email recipients.
 * Version: 1.0.0
 * Author: AZ
 * Author URI: https://www.zeler.fr/
 * Requires at least: 6.6
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * WC requires at least: 9.8
 * WC tested up to: 11.0
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: az-notifyrules-for-woocommerce
 * Update URI: https://www.zeler.fr/plugins/az-notifyrules-for-woocommerce/
 */

defined( 'ABSPATH' ) || exit;

define( 'AZ_WOO_ALERTS_VERSION', '1.0.0' );
define( 'AZ_WOO_ALERTS_FILE', __FILE__ );
define( 'AZ_WOO_ALERTS_DIR', plugin_dir_path( __FILE__ ) );
define( 'AZ_WOO_ALERTS_URL', plugin_dir_url( __FILE__ ) );

add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	static function ( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=az-notifyrules-for-woocommerce' ) ),
			esc_html__( 'Settings', 'az-notifyrules-for-woocommerce' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}
);

add_action(
	'admin_init',
	static function () {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = sprintf(
			/* translators: 1: Pushover privacy policy URL, 2: Pushover terms URL. */
			__( 'When a Pushover connection is configured and a matching order is received, AZ NotifyRules sends data to Pushover, LLC over HTTPS. This data includes the configured application token and user or group key, the order number, message title, order creation time, an administrative order URL, and optionally product names and quantities, the order amount, and the customer billing name, according to the connection settings. Pushover processes this data under its <a href="%1$s">privacy policy</a> and <a href="%2$s">terms of use</a>. The plugin does not send telemetry.', 'az-notifyrules-for-woocommerce' ),
			esc_url( 'https://pushover.net/privacy' ),
			esc_url( 'https://pushover.net/terms' )
		);

		wp_add_privacy_policy_content(
			__( 'AZ NotifyRules for WooCommerce', 'az-notifyrules-for-woocommerce' ),
			wp_kses_post( wpautop( $content, false ) )
		);
	}
);

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

require_once AZ_WOO_ALERTS_DIR . 'includes/class-az-notifyrules-for-woocommerce-crypto.php';
require_once AZ_WOO_ALERTS_DIR . 'includes/class-az-notifyrules-for-woocommerce-updater.php';
require_once AZ_WOO_ALERTS_DIR . 'includes/interface-az-notifyrules-for-woocommerce-channel-provider.php';
require_once AZ_WOO_ALERTS_DIR . 'includes/class-az-notifyrules-for-woocommerce-pushover-provider.php';
require_once AZ_WOO_ALERTS_DIR . 'includes/class-az-notifyrules-for-woocommerce-email-provider.php';
require_once AZ_WOO_ALERTS_DIR . 'includes/class-az-notifyrules-for-woocommerce-admin.php';
require_once AZ_WOO_ALERTS_DIR . 'includes/class-az-notifyrules-for-woocommerce.php';

AZ_NotifyRules_Updater::register();

add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function () {
					if ( current_user_can( 'activate_plugins' ) ) {
						echo '<div class="notice notice-error"><p>' . esc_html__( 'AZ NotifyRules requires WooCommerce.', 'az-notifyrules-for-woocommerce' ) . '</p></div>';
					}
				}
			);
			return;
		}

		AZ_Woo_Alerts::instance();
	},
	20
);
