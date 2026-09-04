<?php
/**
 * Native updates for copies distributed outside WordPress.org.
 *
 * @package AZ_Woo_Alerts
 */

defined( 'ABSPATH' ) || exit;

final class AZ_NotifyRules_Updater {
	private const SLUG = 'az-notifyrules-for-woocommerce';
	private const UPDATE_URI = 'https://www.zeler.fr/plugins/az-notifyrules-for-woocommerce/';
	private const METADATA_URL = 'https://www.zeler.fr/plugins/az-notifyrules-for-woocommerce/update.json';
	private const CACHE_KEY = 'az_notifyrules_update_metadata';

	/**
	 * Registers the update hooks.
	 */
	public static function register(): void {
		add_filter( 'update_plugins_www.zeler.fr', array( self::class, 'filter_update' ), 10, 4 );
		add_filter( 'plugins_api', array( self::class, 'filter_plugin_information' ), 10, 3 );
		add_filter( 'upgrader_pre_download', array( self::class, 'verify_package' ), 10, 4 );
		add_action( 'upgrader_process_complete', array( self::class, 'clear_cache_after_update' ), 10, 2 );
	}

	/**
	 * Supplies update data for WordPress' Update URI mechanism.
	 *
	 * @param array|false $update      Existing update data.
	 * @param array       $plugin_data Installed plugin headers.
	 * @param string      $plugin_file Installed plugin basename.
	 * @param string[]    $locales     Installed locales.
	 * @return array|false
	 */
	public static function filter_update( $update, array $plugin_data, string $plugin_file, array $locales ) {
		unset( $plugin_data, $locales );

		if ( plugin_basename( AZ_WOO_ALERTS_FILE ) !== $plugin_file ) {
			return $update;
		}

		$metadata = self::get_metadata();
		if ( is_wp_error( $metadata ) || version_compare( $metadata['version'], AZ_WOO_ALERTS_VERSION, '<=' ) ) {
			return false;
		}

		return array(
			'id'           => self::UPDATE_URI,
			'slug'         => self::SLUG,
			'version'      => $metadata['version'],
			'url'          => self::UPDATE_URI,
			'package'      => $metadata['package'],
			'tested'       => $metadata['tested'],
			'requires_php' => $metadata['requires_php'],
			'autoupdate'   => false,
		);
	}

	/**
	 * Supplies the version-details modal for this external plugin.
	 *
	 * @param false|object|array $result Existing result.
	 * @param string             $action Requested API action.
	 * @param object             $args   API request arguments.
	 * @return false|object|array
	 */
	public static function filter_plugin_information( $result, string $action, object $args ) {
		if ( 'plugin_information' !== $action || self::SLUG !== ( $args->slug ?? '' ) ) {
			return $result;
		}

		$metadata = self::get_metadata();
		if ( is_wp_error( $metadata ) ) {
			return $result;
		}

		return (object) array(
			'name'          => 'AZ NotifyRules for WooCommerce',
			'slug'          => self::SLUG,
			'version'       => $metadata['version'],
			'author'        => 'AZ',
			'homepage'      => self::UPDATE_URI,
			'requires'      => $metadata['requires'],
			'tested'        => $metadata['tested'],
			'requires_php'  => $metadata['requires_php'],
			'download_link' => $metadata['package'],
			'external'      => true,
			'sections'      => array(
				'description' => wp_kses_post( $metadata['description'] ),
				'changelog'   => wp_kses_post( $metadata['changelog'] ),
			),
		);
	}

	/**
	 * Downloads and verifies this plugin's package before WordPress installs it.
	 *
	 * @param false|string|WP_Error $reply      Existing short-circuit value.
	 * @param string                $package    Package URL.
	 * @param WP_Upgrader           $upgrader   Upgrader instance.
	 * @param array                 $hook_extra Upgrader context.
	 * @return false|string|WP_Error
	 */
	public static function verify_package( $reply, string $package, $upgrader, array $hook_extra ) {
		unset( $upgrader, $hook_extra );

		if ( false !== $reply ) {
			return $reply;
		}

		if ( ! str_starts_with( $package, self::UPDATE_URI ) ) {
			return false;
		}

		$metadata = self::get_metadata();
		if ( is_wp_error( $metadata ) ) {
			return $metadata;
		}

		if ( $metadata['package'] !== $package ) {
			return new WP_Error(
				'az_notifyrules_unexpected_package',
				__( 'The AZ NotifyRules update package URL is invalid.', 'az-notifyrules-for-woocommerce' )
			);
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		$download = download_url( $package, 300 );
		if ( is_wp_error( $download ) ) {
			return $download;
		}

		$actual_checksum = hash_file( 'sha256', $download );
		if ( ! is_string( $actual_checksum ) || ! hash_equals( $metadata['sha256'], strtolower( $actual_checksum ) ) ) {
			wp_delete_file( $download );
			return new WP_Error(
				'az_notifyrules_checksum_mismatch',
				__( 'The AZ NotifyRules update package failed its integrity check.', 'az-notifyrules-for-woocommerce' )
			);
		}

		return $download;
	}

	/**
	 * Clears cached metadata after this plugin is updated.
	 *
	 * @param WP_Upgrader $upgrader Upgrader instance.
	 * @param array       $options  Update context.
	 */
	public static function clear_cache_after_update( $upgrader, array $options ): void {
		unset( $upgrader );

		if ( 'plugin' !== ( $options['type'] ?? '' ) || 'update' !== ( $options['action'] ?? '' ) ) {
			return;
		}

		$plugins = $options['plugins'] ?? array( $options['plugin'] ?? '' );
		if ( in_array( plugin_basename( AZ_WOO_ALERTS_FILE ), $plugins, true ) ) {
			delete_site_transient( self::CACHE_KEY );
		}
	}

	/**
	 * Fetches and validates release metadata.
	 *
	 * @return array|WP_Error
	 */
	private static function get_metadata() {
		$cached = get_site_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$response = wp_remote_get(
			self::METADATA_URL,
			array(
				'timeout'     => 10,
				'redirection' => 3,
				'user-agent'  => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url( '/' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'az_notifyrules_update_http_error', __( 'The AZ NotifyRules update server is unavailable.', 'az-notifyrules-for-woocommerce' ) );
		}

		$metadata = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $metadata ) || ! self::is_valid_metadata( $metadata ) ) {
			return new WP_Error( 'az_notifyrules_update_invalid_metadata', __( 'The AZ NotifyRules update information is invalid.', 'az-notifyrules-for-woocommerce' ) );
		}

		$metadata['package'] = esc_url_raw( $metadata['package'] );
		$metadata['sha256']  = strtolower( $metadata['sha256'] );
		set_site_transient( self::CACHE_KEY, $metadata, 6 * HOUR_IN_SECONDS );

		return $metadata;
	}

	/**
	 * Checks the required metadata fields and restricts downloads to this host.
	 *
	 * @param array $metadata Decoded release metadata.
	 */
	private static function is_valid_metadata( array $metadata ): bool {
		$required = array( 'version', 'package', 'sha256', 'requires', 'tested', 'requires_php', 'description', 'changelog' );
		foreach ( $required as $key ) {
			if ( ! isset( $metadata[ $key ] ) || ! is_string( $metadata[ $key ] ) || '' === trim( $metadata[ $key ] ) ) {
				return false;
			}
		}

		$package_host   = wp_parse_url( $metadata['package'], PHP_URL_HOST );
		$package_scheme = wp_parse_url( $metadata['package'], PHP_URL_SCHEME );
		$package_path   = wp_parse_url( $metadata['package'], PHP_URL_PATH );

		return 'https' === $package_scheme
			&& 'www.zeler.fr' === $package_host
			&& is_string( $package_path )
			&& str_starts_with( $package_path, '/plugins/az-notifyrules-for-woocommerce/' )
			&& 1 === preg_match( '/^[0-9a-fA-F]{64}$/', $metadata['sha256'] );
	}
}
