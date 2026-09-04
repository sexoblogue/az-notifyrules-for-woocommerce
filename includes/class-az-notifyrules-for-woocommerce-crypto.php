<?php

defined( 'ABSPATH' ) || exit;

final class AZ_Woo_Alerts_Crypto {
	public const STORED_MASK = '******************************';

	private const PREFIX_SODIUM  = 'sodium:';
	private const PREFIX_OPENSSL = 'openssl:';

	/**
	 * @return string|WP_Error
	 */
	public static function encrypt( string $plaintext ) {
		if ( '' === $plaintext ) {
			return '';
		}

		$key = self::key();

		if ( function_exists( 'sodium_crypto_secretbox' ) ) {
			$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$cipher = sodium_crypto_secretbox( $plaintext, $nonce, $key );
			return self::PREFIX_SODIUM . base64_encode( $nonce . $cipher );
		}

		if ( function_exists( 'openssl_encrypt' ) ) {
			$iv     = random_bytes( 12 );
			$tag    = '';
			$cipher = openssl_encrypt( $plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
			if ( false !== $cipher ) {
				return self::PREFIX_OPENSSL . base64_encode( $iv . $tag . $cipher );
			}
		}

		return new WP_Error( 'az_woo_alerts_no_crypto', __( 'No secure encryption engine is available on this server.', 'az-notifyrules-for-woocommerce' ) );
	}

	public static function decrypt( string $encoded ): string {
		if ( '' === $encoded ) {
			return '';
		}

		$key = self::key();

		if ( str_starts_with( $encoded, self::PREFIX_SODIUM ) && function_exists( 'sodium_crypto_secretbox_open' ) ) {
			$raw = base64_decode( substr( $encoded, strlen( self::PREFIX_SODIUM ) ), true );
			if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
				return '';
			}
			$nonce = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$plain = sodium_crypto_secretbox_open( substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), $nonce, $key );
			return false === $plain ? '' : $plain;
		}

		if ( str_starts_with( $encoded, self::PREFIX_OPENSSL ) && function_exists( 'openssl_decrypt' ) ) {
			$raw = base64_decode( substr( $encoded, strlen( self::PREFIX_OPENSSL ) ), true );
			if ( false === $raw || strlen( $raw ) <= 28 ) {
				return '';
			}
			$iv     = substr( $raw, 0, 12 );
			$tag    = substr( $raw, 12, 16 );
			$cipher = substr( $raw, 28 );
			$plain  = openssl_decrypt( $cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
			return false === $plain ? '' : $plain;
		}

		return '';
	}

	public static function is_encrypted( string $value ): bool {
		return str_starts_with( $value, self::PREFIX_SODIUM ) || str_starts_with( $value, self::PREFIX_OPENSSL );
	}

	private static function key(): string {
		$material = defined( 'AZ_WOO_ALERTS_ENCRYPTION_KEY' ) && AZ_WOO_ALERTS_ENCRYPTION_KEY
			? (string) AZ_WOO_ALERTS_ENCRYPTION_KEY
			: wp_salt( 'auth' ) . wp_salt( 'secure_auth' );

		return hash( 'sha256', 'az-notifyrules-for-woocommerce|' . $material, true );
	}
}
