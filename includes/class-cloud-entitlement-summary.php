<?php
/**
 * Cloud entitlement summary.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Npcink_Cloud_Entitlement_Summary' ) ) {
	/**
	 * Reads a compact, read-only entitlement projection from Cloud.
	 */
	final class Npcink_Cloud_Entitlement_Summary {
		private const CACHE_TTL_SECONDS = 300;

		/**
		 * Returns the entitlement summary.
		 *
		 * @param bool $force_refresh Whether to bypass cache.
		 * @return array<string,mixed>
		 */
		public static function get_summary( bool $force_refresh = false ): array {
			$state = Npcink_Cloud_Addon_Settings::get_credential_state();
			if ( empty( $state['configured'] ) ) {
				return self::unavailable_summary(
					'not_configured',
					__( 'Configure Cloud settings before reading entitlement.', 'npcink-cloud-addon' )
				);
			}
			if ( empty( $state['verified'] ) ) {
				return self::unavailable_summary(
					'unverified',
					__( 'Entitlement is unavailable until Cloud settings verify successfully.', 'npcink-cloud-addon' )
				);
			}

			$settings = Npcink_Cloud_Addon_Settings::get_settings();
			$cache_key = self::cache_key( $settings );
			if ( ! $force_refresh ) {
				$cached = get_transient( $cache_key );
				if ( is_array( $cached ) ) {
					$cached['state'] = 'cached';
					$cached['available'] = true;
					$cached['stale'] = false;

					return $cached;
				}
			}

			$client = new Npcink_Cloud_Runtime_Client( $settings );
			$result = $client->get_current_entitlement( 'trace_cloud_entitlement_' . wp_generate_uuid4() );
			if ( is_wp_error( $result ) ) {
				return self::unavailable_summary( 'unavailable', $result->get_error_message() );
			}

			$data = self::extract_data( $result );
			$summary = self::normalize_cloud_entitlement( $data, $settings );
			set_transient( $cache_key, $summary, self::CACHE_TTL_SECONDS );

			return $summary;
		}

		/**
		 * Forces entitlement refresh.
		 *
		 * @return array<string,mixed>
		 */
		public static function refresh(): array {
			return self::get_summary( true );
		}

		/**
		 * Extracts data from common Cloud envelopes.
		 *
		 * @param array<string,mixed> $response Cloud response.
		 * @return array<string,mixed>
		 */
		private static function extract_data( array $response ): array {
			if ( is_array( $response['data'] ?? null ) ) {
				return $response['data'];
			}

			return $response;
		}

		/**
		 * Builds a transient key without including secrets.
		 *
		 * @param array<string,mixed> $settings Settings.
		 * @return string
		 */
		private static function cache_key( array $settings ): string {
			$seed = implode(
				'|',
				array(
					(string) ( $settings['base_url'] ?? '' ),
					(string) ( $settings['site_id'] ?? '' ),
					(string) ( $settings['key_id'] ?? '' ),
				)
			);

			return 'magick_ai_cloud_entitlement_' . md5( $seed );
		}

		/**
		 * Normalizes Cloud entitlement for local display.
		 *
		 * @param array<string,mixed> $data Cloud response data.
		 * @param array<string,mixed> $settings Addon settings.
		 * @return array<string,mixed>
		 */
		private static function normalize_cloud_entitlement( array $data, array $settings ): array {
			$entitlement = is_array( $data['entitlement'] ?? null ) ? $data['entitlement'] : $data;
			$period = is_array( $data['period'] ?? null ) ? $data['period'] : array();
			$quota = is_array( $entitlement['hosted_runtime_quota'] ?? null ) ? $entitlement['hosted_runtime_quota'] : array();
			$usage_limits = is_array( $entitlement['usage_limits'] ?? null ) ? $entitlement['usage_limits'] : array();

			return array(
				'state' => 'fresh',
				'available' => true,
				'stale' => false,
				'message' => __( 'Entitlement summary synced.', 'npcink-cloud-addon' ),
				'contract_version' => sanitize_text_field( (string) ( $data['contract_version'] ?? '' ) ),
				'package_label' => sanitize_text_field( (string) ( $data['package'] ?? $data['package_label'] ?? '' ) ),
				'package_tier' => sanitize_key( (string) ( $data['package_tier'] ?? $entitlement['package_tier'] ?? '' ) ),
				'entitlement_status' => sanitize_key( (string) ( $data['status'] ?? $entitlement['status'] ?? '' ) ),
				'renews_at' => sanitize_text_field( (string) ( $period['end_at'] ?? $entitlement['renews_at'] ?? '' ) ),
				'usage_limits' => self::normalize_usage_limits( $usage_limits ),
				'hosted_runtime_quota' => array(
					'max_active_runs' => absint( $quota['max_active_runs'] ?? 0 ),
					'max_batch_items' => absint( $quota['max_batch_items'] ?? 0 ),
					'execution_tiers' => self::sanitize_string_list( $quota['execution_tiers'] ?? array() ),
				),
				'links' => self::build_portal_links( $settings ),
				'synced_at' => gmdate( 'Y-m-d H:i:s' ) . ' UTC',
				'fresh_until' => gmdate( 'Y-m-d H:i:s', time() + self::CACHE_TTL_SECONDS ) . ' UTC',
			);
		}

		/**
		 * Normalizes usage limits.
		 *
		 * @param mixed $usage_limits Raw usage limits.
		 * @return array<string,mixed>
		 */
		private static function normalize_usage_limits( $usage_limits ): array {
			$usage_limits = is_array( $usage_limits ) ? $usage_limits : array();

			return array(
				'period' => sanitize_key( (string) ( $usage_limits['period'] ?? '' ) ),
				'max_runs' => (float) ( $usage_limits['max_runs'] ?? 0 ),
				'max_tokens' => (float) ( $usage_limits['max_tokens'] ?? 0 ),
				'max_cost_usd' => (float) ( $usage_limits['max_cost_usd'] ?? 0 ),
				'max_sites' => absint( $usage_limits['max_sites'] ?? 0 ),
			);
		}

		/**
		 * Builds bounded Cloud detail links.
		 *
		 * @param array<string,mixed> $settings Addon settings.
		 * @return array<string,string>
		 */
		private static function build_portal_links( array $settings ): array {
			$base_url = untrailingslashit( esc_url_raw( (string) ( $settings['base_url'] ?? '' ) ) );
			if ( '' === $base_url ) {
				return array();
			}

			return array(
				'usage_url' => esc_url_raw( $base_url . '/portal/usage' ),
				'billing_url' => esc_url_raw( $base_url . '/portal/billing' ),
			);
		}

		/**
		 * Sanitizes a string list.
		 *
		 * @param mixed $items Raw list.
		 * @return array<int,string>
		 */
		private static function sanitize_string_list( $items ): array {
			$items = is_array( $items ) ? $items : array();
			$normalized = array();
			foreach ( $items as $item ) {
				$value = sanitize_text_field( (string) $item );
				if ( '' !== $value ) {
					$normalized[] = $value;
				}
			}

			return array_values( array_unique( $normalized ) );
		}

		/**
		 * Builds an unavailable summary.
		 *
		 * @param string $state State code.
		 * @param string $message Operator message.
		 * @return array<string,mixed>
		 */
		private static function unavailable_summary( string $state, string $message ): array {
			return array(
				'state' => sanitize_key( $state ),
				'available' => false,
				'stale' => false,
				'message' => sanitize_text_field( $message ),
				'contract_version' => '',
				'package_label' => '',
				'package_tier' => '',
				'entitlement_status' => '',
				'renews_at' => '',
				'usage_limits' => array(),
				'hosted_runtime_quota' => array(),
				'links' => array(),
				'synced_at' => '',
				'fresh_until' => '',
			);
		}
	}
}
