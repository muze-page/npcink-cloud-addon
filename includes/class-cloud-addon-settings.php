<?php
/**
 * Cloud addon settings registry.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Npcink_Cloud_Addon_Settings' ) ) {
	/**
	 * Owns the addon-local Cloud credential settings.
	 */
	final class Npcink_Cloud_Addon_Settings {
		private const DEFAULT_TIMEOUT = 8;
		private const MIN_TIMEOUT = 5;
		private const MAX_TIMEOUT = 60;
		private const LOCAL_DEFAULT_BASE_URL = 'http://localhost:8010/';
		private const PRODUCTION_DEFAULT_BASE_URL = 'https://cloud.npc.ink/';

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
				'npcink_cloud_addon',
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
			$option_name = defined( 'NPCINK_CLOUD_ADDON_OPTION_NAME' )
				? (string) NPCINK_CLOUD_ADDON_OPTION_NAME
				: 'npcink_cloud_addon_settings';

			return '' !== $option_name ? $option_name : 'npcink_cloud_addon_settings';
		}

		/**
		 * Returns normalized settings.
		 *
		 * @return array<string,mixed>
		 */
		public static function get_settings(): array {
			$stored = get_option( self::option_name(), false );
			$stored = is_array( $stored ) ? $stored : array();

			return self::normalize_settings( $stored );
		}

		/**
		 * Returns the default Cloud base URL for the current environment.
		 *
		 * @return string
		 */
		public static function get_default_base_url(): string {
			$default = self::PRODUCTION_DEFAULT_BASE_URL;
			if ( self::is_local_wordpress_environment() ) {
				$default = self::LOCAL_DEFAULT_BASE_URL;
			}
			if ( defined( 'NPCINK_CLOUD_ADDON_DEFAULT_BASE_URL' ) && '' !== trim( (string) NPCINK_CLOUD_ADDON_DEFAULT_BASE_URL ) ) {
				$default = (string) NPCINK_CLOUD_ADDON_DEFAULT_BASE_URL;
			}

			/**
			 * Filters the default Npcink Cloud base URL used by the authorization entry.
			 *
			 * @param string $default Default Cloud base URL.
			 */
			$filtered = apply_filters( 'npcink_cloud_addon_default_base_url', $default );
			$normalized = self::normalize_base_url( is_string( $filtered ) ? $filtered : $default );

			return '' !== $normalized ? $normalized : self::PRODUCTION_DEFAULT_BASE_URL;
		}

		/**
		 * Returns whether the current WordPress site looks like local development.
		 *
		 * @return bool
		 */
		private static function is_local_wordpress_environment(): bool {
			if ( function_exists( 'wp_get_environment_type' ) && 'local' === wp_get_environment_type() ) {
				return true;
			}

			$host = function_exists( 'home_url' )
				? strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) )
				: '';
			if ( in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true ) ) {
				return true;
			}

			return '' !== $host && str_ends_with( $host, '.local' );
		}

		/**
		 * Returns the stored base URL or the environment default.
		 *
		 * @param array<string,mixed> $settings Stored settings.
		 * @return string
		 */
		public static function get_effective_base_url( array $settings = array() ): string {
			$settings = empty( $settings ) ? self::get_settings() : self::normalize_settings( $settings );
			$stored = (string) ( $settings['base_url'] ?? '' );

			return '' !== $stored ? $stored : self::get_default_base_url();
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
			 * Returns whether Site Knowledge public content delivery may run.
			 *
			 * @return bool
			 */
			public static function is_site_knowledge_delivery_enabled(): bool {
				$settings = self::get_settings();

				return self::is_verified() && ! empty( $settings['site_knowledge_delivery_enabled'] );
			}

			/**
			 * Returns whether verified Cloud settings may be exposed to the WordPress AI plugin.
			 *
			 * @return bool
			 */
			public static function is_wordpress_ai_connector_enabled(): bool {
				$settings = self::get_settings();

				return self::is_verified() && ! empty( $settings['wordpress_ai_connector_enabled'] );
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
					'label' => __( 'Not configured', 'npcink-cloud-addon' ),
					'message' => $has_any_values
						? __( 'Cloud settings are incomplete. Reconnect this site in Npcink Cloud to issue a new connection key.', 'npcink-cloud-addon' )
						: __( 'Authorize this site in Npcink Cloud to create the connection.', 'npcink-cloud-addon' ),
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
					'label' => __( 'Verified', 'npcink-cloud-addon' ),
					'message' => __( 'Cloud settings are saved and verified.', 'npcink-cloud-addon' ),
					'configured' => true,
					'verified' => true,
					'verified_at' => sanitize_text_field( (string) $settings['verified_at'] ),
					'last_verification_error' => '',
					'severity' => 'ok',
				);
			}

			return array(
				'code' => '' !== $last_error ? 'configured_unavailable' : 'configured_unverified',
				'label' => '' !== $last_error ? __( 'Unavailable', 'npcink-cloud-addon' ) : __( 'Pending verification', 'npcink-cloud-addon' ),
				'message' => '' !== $last_error ? $last_error : __( 'Cloud settings are saved but have not passed verification.', 'npcink-cloud-addon' ),
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
				if ( '' !== trim( (string) $payload['base_url'] ) && '' === $base_url ) {
					return new WP_Error(
						'invalid_cloud_base_url',
						__( 'Cloud Base URL must use HTTPS unless it points to localhost or 127.0.0.1.', 'npcink-cloud-addon' )
					);
				}
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

			if ( array_key_exists( 'site_knowledge_delivery_enabled', $payload ) ) {
				$next['site_knowledge_delivery_enabled'] = ! empty( $payload['site_knowledge_delivery_enabled'] );
			}

			if ( array_key_exists( 'wordpress_ai_connector_enabled', $payload ) ) {
				$next['wordpress_ai_connector_enabled'] = ! empty( $payload['wordpress_ai_connector_enabled'] );
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
				'site_knowledge_delivery_enabled' => array_key_exists( 'site_knowledge_delivery_enabled', $settings )
					? ! empty( $settings['site_knowledge_delivery_enabled'] )
					: true,
				'wordpress_ai_connector_enabled' => array_key_exists( 'wordpress_ai_connector_enabled', $settings )
					? ! empty( $settings['wordpress_ai_connector_enabled'] )
					: true,
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
				'site_knowledge_delivery_enabled' => true,
				'wordpress_ai_connector_enabled' => true,
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
		 * Supported format:
		 * - mak1_{base64url(json)}
		 *
		 * @param string $api_key Raw Cloud API Key.
		 * @return array<string,string>|WP_Error
		 */
		private static function parse_api_key( string $api_key ) {
			$api_key = trim( $api_key );
			if ( '' === $api_key ) {
				return new WP_Error(
					'invalid_cloud_api_key',
					__( 'Cloud API Key cannot be empty.', 'npcink-cloud-addon' )
				);
			}

			if ( 0 !== strpos( $api_key, 'mak1_' ) ) {
				return new WP_Error(
					'invalid_cloud_api_key',
					__( 'Manual Cloud API Key recovery must use a Cloud-issued mak1_ wrapper. Reconnect this site in Npcink Cloud to issue a new key.', 'npcink-cloud-addon' )
				);
			}

			$decoded = self::base64url_decode( substr( $api_key, 5 ) );
			if ( false === $decoded || '' === $decoded ) {
				return new WP_Error(
					'invalid_cloud_api_key',
					__( 'Cloud API Key could not be decoded.', 'npcink-cloud-addon' )
				);
			}

			$decoded_payload = json_decode( $decoded, true );
			if ( ! is_array( $decoded_payload ) ) {
				return new WP_Error(
					'invalid_cloud_api_key',
					__( 'Cloud API Key format is invalid. Use a key issued by Npcink Cloud.', 'npcink-cloud-addon' )
				);
			}

			$site_id = self::normalize_identifier( (string) ( $decoded_payload['site_id'] ?? '' ) );
			$key_id = self::normalize_identifier( (string) ( $decoded_payload['key_id'] ?? '' ) );
			$secret = self::normalize_secret( (string) ( $decoded_payload['secret'] ?? '' ) );

			if ( '' === $site_id || '' === $key_id || '' === $secret ) {
				return new WP_Error(
					'invalid_cloud_api_key',
					__( 'Cloud API Key wrapper is missing required signing data.', 'npcink-cloud-addon' )
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
			$base_url = untrailingslashit( esc_url_raw( trim( $base_url ) ) );
			if ( '' === $base_url ) {
				return '';
			}

			$scheme = strtolower( (string) wp_parse_url( $base_url, PHP_URL_SCHEME ) );
			if ( 'https' === $scheme ) {
				return $base_url;
			}
			if ( 'http' === $scheme && self::is_local_http_base_url( $base_url ) ) {
				return $base_url;
			}

			return '';
		}

		/**
		 * Returns whether an HTTP URL is limited to a local development host.
		 *
		 * @param string $base_url Normalized base URL.
		 * @return bool
		 */
		private static function is_local_http_base_url( string $base_url ): bool {
			$host = strtolower( (string) wp_parse_url( $base_url, PHP_URL_HOST ) );

			return in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true );
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
