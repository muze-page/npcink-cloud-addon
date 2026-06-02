<?php
/**
 * Optional local observability collection for Magick AI plugins.
 *
 * @package MagickAICloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Magick_AI_Cloud_Observability_Collector' ) ) {
	/**
	 * Captures metadata-only plugin events after explicit Cloud Addon opt-in.
	 */
	final class Magick_AI_Cloud_Observability_Collector {
		public const BUFFER_OPTION = 'magick_ai_cloud_addon_observability_buffer';
		public const STATUS_OPTION = 'magick_ai_cloud_addon_observability_status';
		public const SUMMARY_OPTION = 'magick_ai_cloud_addon_observability_summary';
		public const CRON_HOOK = 'magick_ai_cloud_addon_flush_observability';
		private const MAX_BUFFER_ITEMS = 200;
		private const MAX_BATCH_ITEMS = 50;

		/**
		 * Registers collection hooks.
		 *
		 * @return void
		 */
		public static function register(): void {
			add_action( 'magick_ai_observability_event', array( __CLASS__, 'capture_event' ), 10, 1 );
			add_action( self::CRON_HOOK, array( __CLASS__, 'flush_buffer' ) );

			self::sync_schedule();
		}

		/**
		 * Keeps the upload cron aligned with the verified monitoring setting.
		 *
		 * @return void
		 */
		public static function sync_schedule(): void {
			if ( Magick_AI_Cloud_Addon_Settings::is_monitoring_enabled() ) {
				self::maybe_schedule_flush();
				return;
			}

			if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
				wp_clear_scheduled_hook( self::CRON_HOOK );
			}
		}

		/**
		 * Captures a local event into the bounded addon-owned observability buffer.
		 *
		 * @param mixed $event Event payload.
		 * @return void
		 */
		public static function capture_event( $event ): void {
			if ( ! is_array( $event ) || ! Magick_AI_Cloud_Addon_Settings::is_monitoring_enabled() ) {
				return;
			}

			$normalized = self::normalize_event( $event );
			if ( '' === (string) $normalized['plugin_slug'] || '' === (string) $normalized['event_kind'] ) {
				return;
			}

			$buffer = get_option( self::BUFFER_OPTION, array() );
			$buffer = is_array( $buffer ) ? array_values( $buffer ) : array();
			$buffer[] = $normalized;
			if ( count( $buffer ) > self::MAX_BUFFER_ITEMS ) {
				$buffer = array_slice( $buffer, -1 * self::MAX_BUFFER_ITEMS );
			}

			update_option( self::BUFFER_OPTION, $buffer, false );
			update_option(
				self::STATUS_OPTION,
				array_merge(
					self::get_raw_status(),
					array(
						'last_captured_at' => gmdate( 'c' ),
						'last_event_kind'  => (string) $normalized['event_kind'],
						'last_plugin_slug' => (string) $normalized['plugin_slug'],
						'buffer_count'     => count( $buffer ),
					)
				),
				false
			);
		}

		/**
		 * Uploads a bounded batch of buffered events to Cloud.
		 *
		 * @return array<string,mixed>
		 */
		public static function flush_buffer(): array {
			if ( ! Magick_AI_Cloud_Addon_Settings::is_monitoring_enabled() ) {
				return self::record_flush_result( false, 0, __( 'Monitoring is disabled or Cloud is not verified.', 'magick-ai-cloud-addon' ) );
			}

			$buffer = get_option( self::BUFFER_OPTION, array() );
			$buffer = is_array( $buffer ) ? array_values( $buffer ) : array();
			if ( empty( $buffer ) ) {
				return self::record_flush_result( true, 0, '' );
			}

			$batch = array_slice( $buffer, 0, self::MAX_BATCH_ITEMS );
			$client = new Magick_AI_Cloud_Runtime_Client();
			$result = $client->send_observability_events(
				$batch,
				'trace_cloud_observability_' . wp_generate_uuid4(),
				'obs_' . wp_generate_uuid4()
			);

			if ( is_wp_error( $result ) ) {
				return self::record_flush_result( false, 0, $result->get_error_message() );
			}

			$data = is_array( $result['data'] ?? null ) ? $result['data'] : array();
			$accepted = min( count( $batch ), max( 0, absint( $data['accepted_count'] ?? count( $batch ) ) ) );
			if ( $accepted > 0 ) {
				$buffer = array_slice( $buffer, $accepted );
				update_option( self::BUFFER_OPTION, $buffer, false );
			}

			return self::record_flush_result( true, $accepted, '' );
		}

		/**
		 * Returns local monitoring status for the settings UI.
		 *
		 * @return array<string,mixed>
		 */
		public static function get_status(): array {
			$buffer = get_option( self::BUFFER_OPTION, array() );
			$buffer = is_array( $buffer ) ? $buffer : array();
			$status = get_option( self::STATUS_OPTION, array() );
			$status = is_array( $status ) ? $status : array();

			return array(
				'enabled'          => Magick_AI_Cloud_Addon_Settings::is_monitoring_enabled(),
				'configured'       => Magick_AI_Cloud_Addon_Settings::is_configured(),
				'verified'         => Magick_AI_Cloud_Addon_Settings::is_verified(),
				'buffer_count'     => count( $buffer ),
				'last_captured_at' => sanitize_text_field( (string) ( $status['last_captured_at'] ?? '' ) ),
				'last_event_kind'  => sanitize_text_field( (string) ( $status['last_event_kind'] ?? '' ) ),
				'last_plugin_slug' => sanitize_text_field( (string) ( $status['last_plugin_slug'] ?? '' ) ),
				'last_uploaded_at' => sanitize_text_field( (string) ( $status['last_uploaded_at'] ?? '' ) ),
				'last_upload_error' => sanitize_text_field( (string) ( $status['last_upload_error'] ?? '' ) ),
				'total_uploaded'   => absint( $status['total_uploaded'] ?? 0 ),
				'remote_summary'   => self::get_summary_cache(),
				'plugins'          => self::plugin_snapshot(),
			);
		}

		/**
		 * Refreshes the Cloud-side observability summary cache.
		 *
		 * @return array<string,mixed>
		 */
		public static function refresh_summary(): array {
			if ( ! Magick_AI_Cloud_Addon_Settings::is_monitoring_enabled() ) {
				return self::record_summary_result( false, array(), __( 'Monitoring is disabled or Cloud is not verified.', 'magick-ai-cloud-addon' ) );
			}

			$client = new Magick_AI_Cloud_Runtime_Client();
			$result = $client->get_observability_summary( 24, 'trace_cloud_observability_summary_' . wp_generate_uuid4() );
			if ( is_wp_error( $result ) ) {
				return self::record_summary_result( false, array(), $result->get_error_message() );
			}

			$data = is_array( $result['data'] ?? null ) ? $result['data'] : array();

			return self::record_summary_result( true, $data, '' );
		}

		/**
		 * Deletes addon-owned observability options.
		 *
		 * @return void
		 */
		public static function delete_data(): void {
				delete_option( self::BUFFER_OPTION );
			delete_option( self::STATUS_OPTION );
			delete_option( self::SUMMARY_OPTION );
			wp_clear_scheduled_hook( self::CRON_HOOK );
		}

		/**
		 * Normalizes a safe event payload.
		 *
		 * @param array<string,mixed> $event Raw event.
		 * @return array<string,mixed>
		 */
		private static function normalize_event( array $event ): array {
			$allowed = array(
				'schema_version',
				'plugin_slug',
				'plugin_version',
				'source',
				'event_kind',
				'event_id',
				'emitted_at',
				'captured_at',
				'status',
				'status_detail',
				'error_code',
				'latency_ms',
				'ability_id',
				'proposal_id',
				'correlation_id',
				'adapter_request_id',
				'method',
				'route',
				'status_code',
				'mode',
				'deduplicated',
				'proposal_count',
				'blocked_count',
				'executed_count',
				'failed_count',
			);
			$normalized = array();

			foreach ( $allowed as $key ) {
				if ( ! array_key_exists( $key, $event ) ) {
					continue;
				}

				$normalized[ $key ] = self::sanitize_event_value( $event[ $key ] );
			}

			$normalized['captured_at'] = gmdate( 'c' );
			if ( empty( $normalized['event_id'] ) ) {
				$normalized['event_id'] = 'evt_' . wp_generate_uuid4();
			}

			return $normalized;
		}

		/**
		 * Schedules the bounded observability buffer flush when needed.
		 *
		 * @return void
		 */
		private static function maybe_schedule_flush(): void {
			if ( ! function_exists( 'wp_next_scheduled' ) || ! function_exists( 'wp_schedule_event' ) ) {
				return;
			}

			if ( false === wp_next_scheduled( self::CRON_HOOK ) ) {
				wp_schedule_event( time() + 300, 'hourly', self::CRON_HOOK );
			}
		}

		/**
		 * Stores the latest upload outcome.
		 *
		 * @param bool   $ok Whether upload passed.
		 * @param int    $uploaded Uploaded count.
		 * @param string $error Error message.
		 * @return array<string,mixed>
		 */
		private static function record_flush_result( bool $ok, int $uploaded, string $error ): array {
			$buffer = get_option( self::BUFFER_OPTION, array() );
			$buffer = is_array( $buffer ) ? $buffer : array();
			$status = self::get_raw_status();
			$total_uploaded = absint( $status['total_uploaded'] ?? 0 ) + max( 0, $uploaded );
			$status = array_merge(
				$status,
				array(
					'last_upload_ok'    => $ok,
					'last_uploaded_at'  => $ok ? gmdate( 'c' ) : (string) ( $status['last_uploaded_at'] ?? '' ),
					'last_upload_error' => $ok ? '' : sanitize_text_field( $error ),
					'total_uploaded'    => $total_uploaded,
					'buffer_count'      => count( $buffer ),
				)
			);
			update_option( self::STATUS_OPTION, $status, false );

			return $status;
		}

		/**
		 * Stores the latest Cloud summary refresh result.
		 *
		 * @param bool                $ok Whether refresh passed.
		 * @param array<string,mixed> $summary Summary payload.
		 * @param string              $error Error message.
		 * @return array<string,mixed>
		 */
		private static function record_summary_result( bool $ok, array $summary, string $error ): array {
			$cache = array(
				'last_refresh_ok'    => $ok,
				'last_refreshed_at'  => $ok ? gmdate( 'c' ) : '',
				'last_refresh_error' => $ok ? '' : sanitize_text_field( $error ),
				'summary'            => $ok ? self::sanitize_summary_payload( $summary ) : self::get_summary_cache()['summary'],
			);
			update_option( self::SUMMARY_OPTION, $cache, false );

			return $cache;
		}

		/**
		 * Returns cached Cloud summary.
		 *
		 * @return array<string,mixed>
		 */
		private static function get_summary_cache(): array {
			$cache = get_option( self::SUMMARY_OPTION, array() );
			$cache = is_array( $cache ) ? $cache : array();

			return array(
				'last_refresh_ok'    => ! empty( $cache['last_refresh_ok'] ),
				'last_refreshed_at'  => sanitize_text_field( (string) ( $cache['last_refreshed_at'] ?? '' ) ),
				'last_refresh_error' => sanitize_text_field( (string) ( $cache['last_refresh_error'] ?? '' ) ),
				'summary'            => is_array( $cache['summary'] ?? null ) ? $cache['summary'] : array(),
			);
		}

		/**
		 * Sanitizes a nested summary payload for local option storage.
		 *
		 * @param mixed $value Raw payload.
		 * @return mixed
		 */
		private static function sanitize_summary_payload( $value ) {
			if ( is_array( $value ) ) {
				$clean = array();
				foreach ( $value as $key => $item ) {
					$clean[ is_int( $key ) ? $key : sanitize_key( (string) $key ) ] = self::sanitize_summary_payload( $item );
				}
				return $clean;
			}

			if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
				return $value;
			}

			return sanitize_text_field( wp_unslash( (string) $value ) );
		}

		/**
		 * Returns raw status option.
		 *
		 * @return array<string,mixed>
		 */
		private static function get_raw_status(): array {
			$status = get_option( self::STATUS_OPTION, array() );

			return is_array( $status ) ? $status : array();
		}

		/**
		 * Sanitizes an event scalar.
		 *
		 * @param mixed $value Raw value.
		 * @return bool|int|float|string
		 */
		private static function sanitize_event_value( $value ) {
			if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
				return $value;
			}

			if ( is_array( $value ) || is_object( $value ) ) {
				return '';
			}

			return sanitize_text_field( wp_unslash( (string) $value ) );
		}

		/**
		 * Returns active/installed state for known Magick AI plugins.
		 *
		 * @return array<int,array<string,mixed>>
		 */
		private static function plugin_snapshot(): array {
			$known = array(
				'magick-ai-core'        => array(
					'label'    => __( 'Core', 'magick-ai-cloud-addon' ),
					'file'     => 'magick-ai-core/magick-ai-core.php',
					'constant' => 'MAGICK_AI_CORE_VERSION',
				),
				'magick-ai-abilities'   => array(
					'label'    => __( 'Abilities', 'magick-ai-cloud-addon' ),
					'file'     => 'magick-ai-abilities/magick-ai-abilities.php',
					'constant' => 'MAGICK_AI_ABILITIES_VERSION',
				),
				'magick-ai-adapter'     => array(
					'label'    => __( 'Adapter', 'magick-ai-cloud-addon' ),
					'file'     => 'magick-ai-adapter/magick-ai-adapter.php',
					'constant' => 'MAGICK_AI_ADAPTER_VERSION',
				),
				'magick-ai-cloud-addon' => array(
					'label'    => __( 'Cloud Addon', 'magick-ai-cloud-addon' ),
					'file'     => 'magick-ai-cloud-addon/magick-ai-cloud-addon.php',
					'constant' => 'MAGICK_AI_CLOUD_ADDON_VERSION',
				),
			);

			if ( ! function_exists( 'get_plugins' ) && is_readable( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$installed = function_exists( 'get_plugins' ) ? get_plugins() : array();
			$snapshot = array();

			foreach ( $known as $slug => $meta ) {
				$file = (string) $meta['file'];
				$constant = (string) $meta['constant'];
				$plugin_data = is_array( $installed[ $file ] ?? null ) ? $installed[ $file ] : array();
				$active = function_exists( 'is_plugin_active' ) ? is_plugin_active( $file ) : defined( $constant );
				$version = defined( $constant ) ? (string) constant( $constant ) : (string) ( $plugin_data['Version'] ?? '' );

				$snapshot[] = array(
					'slug'      => $slug,
					'label'     => (string) $meta['label'],
					'installed' => ! empty( $plugin_data ) || defined( $constant ),
					'active'    => (bool) $active,
					'version'   => sanitize_text_field( $version ),
				);
			}

			return $snapshot;
		}
	}
}
