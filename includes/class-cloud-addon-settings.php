<?php
/**
 * Cloud addon settings registry.
 *
 * @package MagickAICloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Magick_AI_Cloud_Addon_Settings' ) ) {
	/**
	 * Owns the addon-local Cloud credential settings.
	 */
	final class Magick_AI_Cloud_Addon_Settings {
		private const DEFAULT_TIMEOUT = 8;
		private const MIN_TIMEOUT = 5;
		private const MAX_TIMEOUT = 60;

		/**
		 * Registers WordPress settings metadata hook.
		 *
		 * @return void
		 */
		public static function register(): void {
			add_action( 'admin_init', array( __CLASS__, 'register_setting' ) );
		}

		/**
		 * Registers the option schema with WordPress.
		 *
		 * @return void
		 */
		public static function register_setting(): void {
			register_setting(
				'magick_ai_cloud_addon',
				self::option_name(),
				array(
					'type' => 'array',
					'sanitize_callback' => array( __CLASS__, 'normalize_settings' ),
					'default' => self::defaults(),
					'show_in_rest' => false,
				)
			);
		}

		/**
		 * Returns the option name.
		 *
		 * @return string
		 */
		public static function option_name(): string {
			$option_name = defined( 'MAGICK_AI_CLOUD_ADDON_OPTION_NAME' )
				? (string) MAGICK_AI_CLOUD_ADDON_OPTION_NAME
				: 'magick_ai_cloud_addon_settings';

			return '' !== $option_name ? $option_name : 'magick_ai_cloud_addon_settings';
		}

		/**
		 * Returns normalized settings.
		 *
		 * @return array<string,mixed>
		 */
		public static function get_settings(): array {
			$stored = get_option( self::option_name(), array() );
			$stored = is_array( $stored ) ? $stored : array();

			return self::normalize_settings( $stored );
		}

		/**
		 * Returns whether credentials are complete.
		 *
		 * @return bool
		 */
		public static function is_configured(): bool {
			$settings = self::get_settings();

			return '' !== (string) $settings['base_url']
				&& '' !== (string) $settings['site_id']
				&& '' !== (string) $settings['key_id']
				&& '' !== (string) $settings['secret'];
		}

		/**
		 * Returns whether the last save-and-verify passed.
		 *
		 * @return bool
		 */
		public static function is_verified(): bool {
			$settings = self::get_settings();

			return self::is_configured() && ! empty( $settings['verified'] );
		}

		/**
		 * Returns whether monitoring collection may run.
		 *
		 * @return bool
		 */
		public static function is_monitoring_enabled(): bool {
			$settings = self::get_settings();

			return self::is_verified() && ! empty( $settings['monitoring_enabled'] );
		}

		/**
		 * Returns a compact credential state for local surfaces.
		 *
		 * @return array<string,mixed>
		 */
		public static function get_credential_state(): array {
			$settings = self::get_settings();
			$configured = self::is_configured();
			$verified = $configured && ! empty( $settings['verified'] );
			$has_any_values = self::has_any_values( $settings );
			$last_error = sanitize_text_field( (string) $settings['last_verification_error'] );

			if ( ! $configured ) {
				return array(
					'code' => 'not_configured',
					'label' => __( 'Not configured', 'magick-ai-cloud-addon' ),
					'message' => $has_any_values
						? __( 'Cloud settings are incomplete. Save a Cloud Base URL and Cloud API Key.', 'magick-ai-cloud-addon' )
						: __( 'Add a Cloud Base URL and Cloud API Key to connect this site.', 'magick-ai-cloud-addon' ),
					'configured' => false,
					'verified' => false,
					'verified_at' => '',
					'last_verification_error' => '',
					'severity' => 'inactive',
				);
			}

			if ( $verified ) {
				return array(
					'code' => 'configured_valid',
					'label' => __( 'Verified', 'magick-ai-cloud-addon' ),
					'message' => __( 'Cloud settings are saved and verified.', 'magick-ai-cloud-addon' ),
					'configured' => true,
					'verified' => true,
					'verified_at' => sanitize_text_field( (string) $settings['verified_at'] ),
					'last_verification_error' => '',
					'severity' => 'ok',
				);
			}

			return array(
				'code' => '' !== $last_error ? 'configured_unavailable' : 'configured_unverified',
				'label' => '' !== $last_error ? __( 'Unavailable', 'magick-ai-cloud-addon' ) : __( 'Pending verification', 'magick-ai-cloud-addon' ),
				'message' => '' !== $last_error ? $last_error : __( 'Cloud settings are saved but have not passed verification.', 'magick-ai-cloud-addon' ),
				'configured' => true,
				'verified' => false,
				'verified_at' => '',
				'last_verification_error' => $last_error,
				'severity' => '' !== $last_error ? 'error' : 'pending',
			);
		}

		/**
		 * Builds settings from admin POST payload without persisting.
		 *
		 * @param array<string,mixed> $payload Raw admin payload.
		 * @return array<string,mixed>|WP_Error
		 */
		public static function build_settings_from_admin_payload( array $payload ) {
			$existing = self::get_settings();
			$next = $existing;

			if ( array_key_exists( 'base_url', $payload ) ) {
				$base_url = self::normalize_base_url( (string) $payload['base_url'] );
				if ( $base_url !== (string) $next['base_url'] ) {
					$next['verified'] = false;
					$next['verified_at'] = '';
					$next['last_verification_error'] = '';
				}
				$next['base_url'] = $base_url;
			}

			if ( array_key_exists( 'timeout', $payload ) ) {
				$next['timeout'] = self::normalize_timeout( $payload['timeout'] );
			}

			if ( array_key_exists( 'monitoring_enabled', $payload ) ) {
				$next['monitoring_enabled'] = ! empty( $payload['monitoring_enabled'] );
			}

			$api_key = array_key_exists( 'api_key', $payload ) ? trim( (string) $payload['api_key'] ) : '';
			if ( '' !== $api_key ) {
				$parsed = self::parse_api_key( $api_key );
				if ( is_wp_error( $parsed ) ) {
					return $parsed;
				}

				$next['site_id'] = (string) $parsed['site_id'];
				$next['key_id'] = (string) $parsed['key_id'];
				$next['secret'] = (string) $parsed['secret'];
				$next['verified'] = false;
				$next['verified_at'] = '';
				$next['last_verification_error'] = '';
			}

			return self::normalize_settings( $next );
		}

		/**
		 * Saves normalized settings.
		 *
		 * @param array<string,mixed> $settings Settings payload.
		 * @return bool
		 */
		public static function write_settings( array $settings ): bool {
			$normalized = self::normalize_settings( $settings );
			$current = self::get_settings();
			if ( $current === $normalized ) {
				return true;
			}

			return false !== update_option( self::option_name(), $normalized, false );
		}

		/**
		 * Marks the latest verification result.
		 *
		 * @param bool   $verified Whether verification passed.
		 * @param string $message Verification error message.
		 * @return array<string,mixed>
		 */
		public static function mark_verification_result( bool $verified, string $message = '' ): array {
			$settings = self::get_settings();
			$settings['verified'] = $verified;
			$settings['verified_at'] = $verified ? gmdate( 'Y-m-d H:i:s' ) . ' UTC' : '';
			$settings['last_verification_error'] = $verified ? '' : sanitize_text_field( $message );
			self::write_settings( $settings );

			return self::get_settings();
		}

		/**
		 * Normalizes settings payload.
		 *
		 * @param mixed $settings Raw settings.
		 * @return array<string,mixed>
		 */
		public static function normalize_settings( $settings ): array {
			$settings = is_array( $settings ) ? $settings : array();

			return array(
				'base_url' => self::normalize_base_url( (string) ( $settings['base_url'] ?? '' ) ),
				'site_id' => self::normalize_identifier( (string) ( $settings['site_id'] ?? '' ) ),
				'key_id' => self::normalize_identifier( (string) ( $settings['key_id'] ?? '' ) ),
				'secret' => self::normalize_secret( (string) ( $settings['secret'] ?? '' ) ),
				'timeout' => self::normalize_timeout( $settings['timeout'] ?? self::DEFAULT_TIMEOUT ),
				'verified' => ! empty( $settings['verified'] ),
				'verified_at' => sanitize_text_field( (string) ( $settings['verified_at'] ?? '' ) ),
				'last_verification_error' => sanitize_text_field( (string) ( $settings['last_verification_error'] ?? '' ) ),
				'monitoring_enabled' => ! empty( $settings['monitoring_enabled'] ),
			);
		}

		/**
		 * Removes all addon-owned settings.
		 *
		 * @return void
		 */
		public static function delete_settings(): void {
			delete_option( self::option_name() );
		}

		/**
		 * Returns default settings.
		 *
		 * @return array<string,mixed>
		 */
		private static function defaults(): array {
			return array(
				'base_url' => '',
				'site_id' => '',
				'key_id' => '',
				'secret' => '',
				'timeout' => self::DEFAULT_TIMEOUT,
				'verified' => false,
				'verified_at' => '',
				'last_verification_error' => '',
				'monitoring_enabled' => false,
			);
		}

		/**
		 * Returns whether settings contain any meaningful Cloud value.
		 *
		 * @param array<string,mixed> $settings Settings payload.
		 * @return bool
		 */
		private static function has_any_values( array $settings ): bool {
			return '' !== (string) ( $settings['base_url'] ?? '' )
				|| '' !== (string) ( $settings['site_id'] ?? '' )
				|| '' !== (string) ( $settings['key_id'] ?? '' )
				|| '' !== (string) ( $settings['secret'] ?? '' );
		}

		/**
		 * Parses a customer-facing API key into signing credentials.
		 *
		 * Supported formats:
		 * - mak1_{base64url(json)}
		 * - raw JSON object with site_id, key_id, and secret
		 *
		 * @param string $api_key Raw Cloud API Key.
		 * @return array<string,string>|WP_Error
		 */
		private static function parse_api_key( string $api_key ) {
			$api_key = trim( $api_key );
			if ( '' === $api_key ) {
				return new WP_Error(
					'invalid_cloud_api_key',
					__( 'Cloud API Key cannot be empty.', 'magick-ai-cloud-addon' )
				);
			}

			$json_candidate = $api_key;
			if ( 0 === strpos( $api_key, 'mak1_' ) ) {
				$decoded = self::base64url_decode( substr( $api_key, 5 ) );
				if ( false === $decoded || '' === $decoded ) {
					return new WP_Error(
						'invalid_cloud_api_key',
						__( 'Cloud API Key could not be decoded.', 'magick-ai-cloud-addon' )
					);
				}
				$json_candidate = $decoded;
			}

			$decoded_payload = json_decode( $json_candidate, true );
			if ( ! is_array( $decoded_payload ) ) {
				return new WP_Error(
					'invalid_cloud_api_key',
					__( 'Cloud API Key format is invalid. Use a key issued by Magick AI Cloud.', 'magick-ai-cloud-addon' )
				);
			}

			$site_id = self::normalize_identifier( (string) ( $decoded_payload['site_id'] ?? '' ) );
			$key_id = self::normalize_identifier( (string) ( $decoded_payload['key_id'] ?? '' ) );
			$secret = self::normalize_secret( (string) ( $decoded_payload['secret'] ?? '' ) );

			if ( '' === $site_id || '' === $key_id || '' === $secret ) {
				return new WP_Error(
					'invalid_cloud_api_key',
					__( 'Cloud API Key is missing site_id, key_id, or secret.', 'magick-ai-cloud-addon' )
				);
			}

			return array(
				'site_id' => $site_id,
				'key_id' => $key_id,
				'secret' => $secret,
			);
		}

		/**
		 * Normalizes Cloud base URL.
		 *
		 * @param string $base_url Raw base URL.
		 * @return string
		 */
		private static function normalize_base_url( string $base_url ): string {
			return untrailingslashit( esc_url_raw( trim( $base_url ) ) );
		}

		/**
		 * Normalizes Cloud identifiers.
		 *
		 * @param string $value Raw identifier.
		 * @return string
		 */
		private static function normalize_identifier( string $value ): string {
			$value = sanitize_text_field( $value );
			$value = preg_replace( '/[^A-Za-z0-9._:-]/', '', $value );

			return is_string( $value ) ? trim( $value ) : '';
		}

		/**
		 * Normalizes stored secret.
		 *
		 * @param string $secret Raw secret.
		 * @return string
		 */
		private static function normalize_secret( string $secret ): string {
			return trim( $secret );
		}

		/**
		 * Normalizes timeout seconds.
		 *
		 * @param mixed $timeout Raw timeout.
		 * @return int
		 */
		private static function normalize_timeout( $timeout ): int {
			$timeout = absint( $timeout );
			if ( $timeout < self::MIN_TIMEOUT ) {
				return self::MIN_TIMEOUT;
			}
			if ( $timeout > self::MAX_TIMEOUT ) {
				return self::MAX_TIMEOUT;
			}

			return $timeout;
		}

		/**
		 * Decodes a base64url payload.
		 *
		 * @param string $encoded Encoded value.
		 * @return string|false
		 */
		private static function base64url_decode( string $encoded ) {
			$encoded = strtr( $encoded, '-_', '+/' );
			$padding = strlen( $encoded ) % 4;
			if ( 0 !== $padding ) {
				$encoded .= str_repeat( '=', 4 - $padding );
			}

			return base64_decode( $encoded, true );
		}
	}
}
