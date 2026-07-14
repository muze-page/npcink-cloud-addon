<?php
/**
 * Authenticated storage for addon-owned Cloud signing credentials.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Npcink_Cloud_Credential_Store' ) ) {
	/**
	 * Encrypts and authenticates the credential envelope stored in wp_options.
	 */
	final class Npcink_Cloud_Credential_Store {
		private const VERSION = 1;
		private const ALGORITHM_SODIUM = 'sodium_secretbox';
		private const ALGORITHM_OPENSSL = 'aes-256-gcm';
		private const KEY_CONTEXT = 'npcink-cloud-addon/credential-store/v1';
		private const GCM_AAD = 'npcink-cloud-addon-credentials:v1:aes-256-gcm';

		/**
		 * Encrypts one credential payload.
		 *
		 * @param array<string,string> $credentials Signing credentials.
		 * @return array<string,mixed>|WP_Error
		 */
		public static function encrypt( array $credentials ) {
			$key = self::derive_key();
			if ( is_wp_error( $key ) ) {
				return $key;
			}

			$plaintext = wp_json_encode(
				array(
					'site_id' => (string) ( $credentials['site_id'] ?? '' ),
					'key_id'  => (string) ( $credentials['key_id'] ?? '' ),
					'secret'  => (string) ( $credentials['secret'] ?? '' ),
				)
			);
			if ( ! is_string( $plaintext ) ) {
				return self::error( 'cloud_credential_encoding_failed' );
			}

			try {
				if ( function_exists( 'sodium_crypto_secretbox' ) ) {
					$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
					$ciphertext = sodium_crypto_secretbox( $plaintext, $nonce, $key );

					return array(
						'version'    => self::VERSION,
						'algorithm'  => self::ALGORITHM_SODIUM,
						'nonce'      => base64_encode( $nonce ),
						'ciphertext' => base64_encode( $ciphertext ),
					);
				}

				if ( function_exists( 'openssl_encrypt' ) && function_exists( 'openssl_decrypt' ) ) {
					$nonce = random_bytes( 12 );
					$tag = '';
					$ciphertext = openssl_encrypt(
						$plaintext,
						self::ALGORITHM_OPENSSL,
						$key,
						OPENSSL_RAW_DATA,
						$nonce,
						$tag,
						self::GCM_AAD,
						16
					);
					if ( ! is_string( $ciphertext ) || 16 !== strlen( $tag ) ) {
						return self::error( 'cloud_credential_encryption_failed' );
					}

					return array(
						'version'    => self::VERSION,
						'algorithm'  => self::ALGORITHM_OPENSSL,
						'nonce'      => base64_encode( $nonce ),
						'ciphertext' => base64_encode( $ciphertext ),
						'tag'        => base64_encode( $tag ),
					);
				}
			} catch ( Throwable $throwable ) {
				return self::error( 'cloud_credential_encryption_failed' );
			}

			return self::error( 'cloud_credential_encryption_unavailable' );
		}

		/**
		 * Decrypts and authenticates one credential envelope.
		 *
		 * @param mixed $envelope Stored envelope.
		 * @return array<string,string>|WP_Error
		 */
		public static function decrypt( $envelope ) {
			if ( ! is_array( $envelope ) || self::VERSION !== (int) ( $envelope['version'] ?? 0 ) ) {
				return self::error( 'cloud_credential_envelope_invalid' );
			}

			$key = self::derive_key();
			if ( is_wp_error( $key ) ) {
				return $key;
			}

			$algorithm = (string) ( $envelope['algorithm'] ?? '' );
			$nonce = self::decode( $envelope['nonce'] ?? null );
			$ciphertext = self::decode( $envelope['ciphertext'] ?? null );
			if ( false === $nonce || false === $ciphertext ) {
				return self::error( 'cloud_credential_envelope_invalid' );
			}

			$plaintext = false;
			try {
				if ( self::ALGORITHM_SODIUM === $algorithm ) {
					if ( ! function_exists( 'sodium_crypto_secretbox_open' ) || SODIUM_CRYPTO_SECRETBOX_NONCEBYTES !== strlen( $nonce ) ) {
						return self::error( 'cloud_credential_decryption_unavailable' );
					}
					$plaintext = sodium_crypto_secretbox_open( $ciphertext, $nonce, $key );
				} elseif ( self::ALGORITHM_OPENSSL === $algorithm ) {
					$tag = self::decode( $envelope['tag'] ?? null );
					if ( ! function_exists( 'openssl_decrypt' ) || 12 !== strlen( $nonce ) || false === $tag || 16 !== strlen( $tag ) ) {
						return self::error( 'cloud_credential_decryption_unavailable' );
					}
					$plaintext = openssl_decrypt(
						$ciphertext,
						self::ALGORITHM_OPENSSL,
						$key,
						OPENSSL_RAW_DATA,
						$nonce,
						$tag,
						self::GCM_AAD
					);
				} else {
					return self::error( 'cloud_credential_algorithm_unsupported' );
				}
			} catch ( Throwable $throwable ) {
				return self::error( 'cloud_credential_authentication_failed' );
			}

			if ( ! is_string( $plaintext ) ) {
				return self::error( 'cloud_credential_authentication_failed' );
			}

			$credentials = json_decode( $plaintext, true );
			if ( ! is_array( $credentials ) ) {
				return self::error( 'cloud_credential_payload_invalid' );
			}

			return array(
				'site_id' => is_string( $credentials['site_id'] ?? null ) ? $credentials['site_id'] : '',
				'key_id'  => is_string( $credentials['key_id'] ?? null ) ? $credentials['key_id'] : '',
				'secret'  => is_string( $credentials['secret'] ?? null ) ? $credentials['secret'] : '',
			);
		}

		/**
		 * Derives a site-bound key from WordPress authentication salt.
		 *
		 * @return string|WP_Error
		 */
		private static function derive_key() {
			if ( ! function_exists( 'wp_salt' ) ) {
				return self::error( 'cloud_credential_key_unavailable' );
			}

			$salt = (string) wp_salt( 'auth' );
			if ( '' === $salt ) {
				return self::error( 'cloud_credential_key_unavailable' );
			}

			return hash_hmac( 'sha256', self::KEY_CONTEXT, $salt, true );
		}

		/**
		 * Strictly decodes one base64 field.
		 *
		 * @param mixed $value Encoded value.
		 * @return string|false
		 */
		private static function decode( $value ) {
			return is_string( $value ) && '' !== $value ? base64_decode( $value, true ) : false;
		}

		/**
		 * Returns a non-secret storage error.
		 *
		 * @param string $code Error code.
		 * @return WP_Error
		 */
		private static function error( string $code ): WP_Error {
			return new WP_Error(
				$code,
				__( 'Cloud credentials could not be stored or read securely. Reconnect this site after checking the WordPress security salts.', 'npcink-cloud-addon' )
			);
		}
	}
}
