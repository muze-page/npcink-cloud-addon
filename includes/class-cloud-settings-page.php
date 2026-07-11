<?php
/**
 * Cloud addon settings page.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Npcink_Cloud_Settings_Page' ) ) {
	/**
	 * Renders Npcink > Cloud Addon and handles save-and-verify.
	 */
	final class Npcink_Cloud_Settings_Page {
		private const PARENT_MENU_SLUG = 'npcink-ai';
		private const PAGE_SLUG = 'npcink-cloud-addon';
		private const MENU_CAPABILITY = 'manage_options';
		private const ACTION_SAVE = 'npcink_cloud_addon_save';
		private const ACTION_COMPLETE_AUTH = 'npcink_cloud_addon_complete_auth';
		private const ACTION_START_CUSTOM_AUTH = 'npcink_cloud_addon_start_custom_auth';
		private const ACTION_DISCONNECT = 'npcink_cloud_addon_disconnect';
		private const ACTION_UPDATE_LOCAL_PERMISSION = 'npcink_cloud_addon_update_local_permission';
		private const ACTION_REFRESH_MONITORING = 'npcink_cloud_addon_refresh_monitoring';
		private const ACTION_REFRESH_SITE_KNOWLEDGE = 'npcink_cloud_addon_refresh_site_knowledge';
		private const ACTION_UPDATE_SITE_KNOWLEDGE_DELIVERY = 'npcink_cloud_addon_update_site_knowledge_delivery';
		private const ACTION_MANAGE_SITE_KNOWLEDGE_INDEX = 'npcink_cloud_addon_manage_site_knowledge_index';
		private const ACTION_RETRY_RUNTIME_RUN = 'npcink_cloud_addon_retry_runtime_run';
		private const ACTION_RUN_MANUAL_READINESS_TEST = 'npcink_cloud_addon_run_manual_readiness_test';
		private const DATETIME_DISPLAY_FORMAT = 'Y-m-d H:i:s';
		private const AUTH_STATE_TTL_SECONDS = 600;

		/**
		 * Registers admin hooks.
		 *
		 * @return void
		 */
		public static function register(): void {
			add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ), 50 );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
			add_action( 'admin_post_' . self::ACTION_SAVE, array( __CLASS__, 'handle_save' ) );
			add_action( 'admin_post_' . self::ACTION_COMPLETE_AUTH, array( __CLASS__, 'handle_complete_auth' ) );
			add_action( 'admin_post_' . self::ACTION_START_CUSTOM_AUTH, array( __CLASS__, 'handle_start_custom_auth' ) );
			add_action( 'admin_post_' . self::ACTION_DISCONNECT, array( __CLASS__, 'handle_disconnect' ) );
			add_action( 'admin_post_' . self::ACTION_UPDATE_LOCAL_PERMISSION, array( __CLASS__, 'handle_update_local_permission' ) );
			add_action( 'admin_post_' . self::ACTION_REFRESH_MONITORING, array( __CLASS__, 'handle_refresh_monitoring' ) );
			add_action( 'admin_post_' . self::ACTION_REFRESH_SITE_KNOWLEDGE, array( __CLASS__, 'handle_refresh_site_knowledge' ) );
			add_action( 'admin_post_' . self::ACTION_UPDATE_SITE_KNOWLEDGE_DELIVERY, array( __CLASS__, 'handle_update_site_knowledge_delivery' ) );
			add_action( 'admin_post_' . self::ACTION_MANAGE_SITE_KNOWLEDGE_INDEX, array( __CLASS__, 'handle_manage_site_knowledge_index' ) );
			add_action( 'admin_post_' . self::ACTION_RETRY_RUNTIME_RUN, array( __CLASS__, 'handle_retry_runtime_run' ) );
			add_action( 'admin_post_' . self::ACTION_RUN_MANUAL_READINESS_TEST, array( __CLASS__, 'handle_run_manual_readiness_test' ) );
		}

		/**
		 * Enqueues admin assets for the Cloud Addon pages.
		 *
		 * @param string $hook_suffix Admin hook suffix.
		 * @return void
		 */
		public static function enqueue_admin_assets( string $hook_suffix ): void {
			$is_cloud_page = false !== strpos( $hook_suffix, self::PAGE_SLUG ) || false !== strpos( $hook_suffix, self::PARENT_MENU_SLUG );
			if ( ! $is_cloud_page ) {
				return;
			}

			wp_enqueue_style(
				'npcink-cloud-addon-admin',
				plugins_url( 'assets/admin.css', NPCINK_CLOUD_ADDON_FILE ),
				array(),
				NPCINK_CLOUD_ADDON_VERSION
			);
		}

		/**
		 * Adds the settings page.
		 *
		 * @return void
		 */
		public static function add_menu_page(): void {
			if ( self::has_parent_menu() ) {
				add_submenu_page(
					self::PARENT_MENU_SLUG,
					__( 'Npcink Cloud Addon', 'npcink-cloud-addon' ),
					__( 'Cloud Addon', 'npcink-cloud-addon' ),
					self::MENU_CAPABILITY,
					self::PAGE_SLUG,
					array( __CLASS__, 'render' ),
					50
				);
				return;
			}

			add_options_page(
				__( 'Npcink Cloud Addon', 'npcink-cloud-addon' ),
				__( 'Npcink Cloud Addon', 'npcink-cloud-addon' ),
				self::MENU_CAPABILITY,
				self::PAGE_SLUG,
				array( __CLASS__, 'render' )
			);
		}

		/**
		 * Returns whether another Npcink plugin already created the parent menu.
		 *
		 * @return bool
		 */
		private static function has_parent_menu(): bool {
			global $menu;

			foreach ( (array) $menu as $item ) {
				if ( isset( $item[2] ) && self::PARENT_MENU_SLUG === $item[2] ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Handles save-and-verify.
		 *
		 * @return void
		 */
		public static function handle_save(): void {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to manage Npcink Cloud settings.', 'npcink-cloud-addon' ) );
			}

			check_admin_referer( self::ACTION_SAVE );

			$base_url = isset( $_POST['base_url'] ) ? sanitize_text_field( wp_unslash( $_POST['base_url'] ) ) : '';
			$api_key  = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
			$timeout  = isset( $_POST['timeout'] ) ? absint( wp_unslash( $_POST['timeout'] ) ) : 8;
			$monitoring_enabled = ! empty( $_POST['monitoring_enabled'] );
			$site_knowledge_delivery_enabled = ! empty( $_POST['site_knowledge_delivery_enabled'] );
			$site_knowledge_generation_reference_enabled = ! empty( $_POST['site_knowledge_generation_reference_enabled'] );
			$wordpress_ai_connector_enabled = ! empty( $_POST['wordpress_ai_connector_enabled'] );

			$payload = array(
				'base_url'           => $base_url,
				'api_key'            => $api_key,
				'timeout'            => $timeout,
				'monitoring_enabled' => $monitoring_enabled,
				'site_knowledge_delivery_enabled' => $site_knowledge_delivery_enabled,
				'site_knowledge_generation_reference_enabled' => $site_knowledge_generation_reference_enabled,
				'wordpress_ai_connector_enabled' => $wordpress_ai_connector_enabled,
			);

			$settings = Npcink_Cloud_Addon_Settings::build_settings_from_admin_payload( $payload );
			if ( is_wp_error( $settings ) ) {
				self::set_admin_notice( 'error', $settings->get_error_message() );
				self::redirect_to_page();
			}

			self::persist_and_verify_settings( $settings, __( 'Cloud settings saved and verified.', 'npcink-cloud-addon' ) );
			self::redirect_to_page();
		}

		/**
		 * Handles the Cloud Portal authorization callback.
		 *
		 * @return void
		 */
		public static function handle_complete_auth(): void {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to manage Npcink Cloud settings.', 'npcink-cloud-addon' ) );
			}

			$raw_state = filter_input( INPUT_GET, 'state', FILTER_UNSAFE_RAW );
			$raw_code = filter_input( INPUT_GET, 'code', FILTER_UNSAFE_RAW );
			$state = is_string( $raw_state ) ? sanitize_text_field( wp_unslash( $raw_state ) ) : '';
			$code  = is_string( $raw_code ) ? sanitize_text_field( wp_unslash( $raw_code ) ) : '';
			$auth_state = self::consume_authorization_state( $state );
			if ( empty( $auth_state ) || '' === $code ) {
				self::set_admin_notice( 'error', __( 'Cloud authorization expired or is invalid. Start the connection again.', 'npcink-cloud-addon' ) );
				self::redirect_to_page( 'status' );
			}

			$base_url = (string) ( $auth_state['base_url'] ?? '' );
			$exchange = self::exchange_authorization_code( $base_url, $code, $state );
			if ( is_wp_error( $exchange ) ) {
				self::set_admin_notice( 'error', $exchange->get_error_message() );
				self::redirect_to_page( 'status' );
			}

			$settings = Npcink_Cloud_Addon_Settings::build_settings_from_admin_payload(
				array(
					'base_url' => $base_url,
					'api_key'  => (string) ( $exchange['cloud_api_key'] ?? '' ),
					'timeout'  => (int) ( Npcink_Cloud_Addon_Settings::get_settings()['timeout'] ?? 8 ),
				)
			);
			if ( is_wp_error( $settings ) ) {
				self::set_admin_notice( 'error', $settings->get_error_message() );
				self::redirect_to_page( 'status' );
			}

			self::persist_and_verify_settings( $settings, __( 'Cloud connection completed and verified.', 'npcink-cloud-addon' ) );

			self::redirect_to_page( 'permissions' );
		}

		/**
		 * Starts authorization against an administrator-supplied Cloud endpoint.
		 *
		 * @return void
		 */
		public static function handle_start_custom_auth(): void {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to manage Npcink Cloud settings.', 'npcink-cloud-addon' ) );
			}

			check_admin_referer( self::ACTION_START_CUSTOM_AUTH );

			$base_url = isset( $_POST['self_hosted_base_url'] )
				? sanitize_text_field( wp_unslash( $_POST['self_hosted_base_url'] ) )
				: '';
			if ( '' === trim( $base_url ) ) {
				self::set_admin_notice( 'error', __( 'Enter a Cloud Base URL before starting self-hosted authorization.', 'npcink-cloud-addon' ) );
				self::redirect_to_page( 'connect' );
			}

			$settings = Npcink_Cloud_Addon_Settings::build_settings_from_admin_payload(
				array(
					'base_url' => $base_url,
				)
			);
			if ( is_wp_error( $settings ) ) {
				self::set_admin_notice( 'error', $settings->get_error_message() );
				self::redirect_to_page( 'connect' );
			}

			$normalized_base_url = (string) ( $settings['base_url'] ?? '' );
			if ( '' === $normalized_base_url ) {
				self::set_admin_notice( 'error', __( 'Cloud Base URL must use HTTPS unless it points to localhost or 127.0.0.1.', 'npcink-cloud-addon' ) );
				self::redirect_to_page( 'connect' );
			}

			wp_redirect( esc_url_raw( self::build_authorization_url_for_base_url( $normalized_base_url ) ) );
			exit;
		}

		/**
		 * Persists settings and immediately updates the verified state.
		 *
		 * @param array<string,mixed> $settings Settings payload.
		 * @param string              $success_message Success notice.
		 * @return void
		 */
		private static function persist_and_verify_settings( array $settings, string $success_message ): void {
			Npcink_Cloud_Addon_Settings::write_settings( $settings );

			$client = new Npcink_Cloud_Runtime_Client( $settings );
			$probe = $client->probe_connectivity();
			if ( ! empty( $probe['ok'] ) ) {
				Npcink_Cloud_Addon_Settings::mark_verification_result( true, '' );
				Npcink_Cloud_Observability_Collector::sync_schedule();
				$summary = is_array( $probe['entitlement_response'] ?? null ) && ! empty( $probe['entitlement_response'] )
					? Npcink_Cloud_Entitlement_Summary::cache_summary_from_response( $probe['entitlement_response'], $settings )
					: Npcink_Cloud_Entitlement_Summary::refresh();
				if ( empty( $summary['available'] ) ) {
					self::set_admin_notice(
						'warning',
						sprintf(
							/* translators: %s: entitlement refresh message. */
							__( 'Cloud settings verified, but entitlement summary could not refresh: %s', 'npcink-cloud-addon' ),
							(string) ( $summary['message'] ?? __( 'Unknown entitlement refresh result.', 'npcink-cloud-addon' ) )
						)
					);
					return;
				}

				self::set_admin_notice( 'success', $success_message );
				return;
			}

			$message = self::format_probe_failure_message( $probe );
			Npcink_Cloud_Addon_Settings::mark_verification_result( false, $message );
			Npcink_Cloud_Observability_Collector::sync_schedule();
			self::set_admin_notice( 'error', $message );
		}

		/**
		 * Handles local Cloud connection disconnect.
		 *
		 * @return void
		 */
		public static function handle_disconnect(): void {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to manage Npcink Cloud settings.', 'npcink-cloud-addon' ) );
			}

			check_admin_referer( self::ACTION_DISCONNECT );

			$settings = Npcink_Cloud_Addon_Settings::get_settings();
			Npcink_Cloud_Entitlement_Summary::delete_cached_summary( $settings );
			Npcink_Cloud_Addon_Settings::delete_settings();
			Npcink_Cloud_Observability_Collector::delete_data();
			Npcink_Cloud_Site_Knowledge_Change_Bridge::delete_data();

			self::set_admin_notice(
				'success',
				__( 'Cloud connection disconnected locally. Stored credentials and addon-owned buffers were cleared.', 'npcink-cloud-addon' )
			);
			self::redirect_to_page( 'status' );
		}

		/**
		 * Handles one local permission switch.
		 *
		 * @return void
		 */
		public static function handle_update_local_permission(): void {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to manage Npcink Cloud settings.', 'npcink-cloud-addon' ) );
			}

			check_admin_referer( self::ACTION_UPDATE_LOCAL_PERMISSION );

			if ( ! Npcink_Cloud_Addon_Settings::is_verified() ) {
				self::set_admin_notice( 'error', __( 'Cloud Addon settings are not verified.', 'npcink-cloud-addon' ) );
				self::redirect_to_page( 'status' );
			}

			$permission = isset( $_POST['permission'] ) ? sanitize_key( wp_unslash( $_POST['permission'] ) ) : '';
			$definitions = self::get_local_permission_definitions();
			if ( ! isset( $definitions[ $permission ] ) ) {
				self::set_admin_notice( 'error', __( 'The requested local permission is not supported.', 'npcink-cloud-addon' ) );
				self::redirect_to_page( 'status' );
			}

			$enabled = ! empty( $_POST['enabled'] );
			$settings = Npcink_Cloud_Addon_Settings::get_settings();
			$settings[ $permission ] = $enabled;
			Npcink_Cloud_Addon_Settings::write_settings( $settings );
			self::sync_local_permission_effects( $permission );

			self::set_admin_notice(
				'success',
				sprintf(
					/* translators: 1: local permission label, 2: enabled or disabled state. */
					__( '%1$s %2$s.', 'npcink-cloud-addon' ),
					(string) $definitions[ $permission ]['label'],
					$enabled ? __( 'enabled', 'npcink-cloud-addon' ) : __( 'disabled', 'npcink-cloud-addon' )
				)
			);
			self::redirect_to_page( 'permissions' );
		}

		/**
		 * Handles manual monitoring upload and read-only quality summary refresh.
		 *
		 * @return void
		 */
		public static function handle_refresh_monitoring(): void {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to manage Npcink Cloud settings.', 'npcink-cloud-addon' ) );
			}

			check_admin_referer( self::ACTION_REFRESH_MONITORING );

			if ( ! Npcink_Cloud_Addon_Settings::is_verified() ) {
				self::set_admin_notice( 'error', __( 'Cloud Addon settings are not verified.', 'npcink-cloud-addon' ) );
				self::redirect_to_page( 'status' );
			}

			$upload = array( 'buffer_count' => Npcink_Cloud_Observability_Collector::get_status()['buffer_count'] ?? 0 );
			if ( Npcink_Cloud_Addon_Settings::is_monitoring_enabled() ) {
				$upload = Npcink_Cloud_Observability_Collector::flush_buffer();
				if ( empty( $upload['last_upload_ok'] ) ) {
					$message = sanitize_text_field( (string) ( $upload['last_upload_error'] ?? '' ) );
					self::set_admin_notice( 'error', '' !== $message ? $message : __( 'Monitoring upload failed.', 'npcink-cloud-addon' ) );
					self::redirect_to_page( 'status' );
				}

				$summary = Npcink_Cloud_Observability_Collector::refresh_summary();
				if ( empty( $summary['last_refresh_ok'] ) ) {
					$message = sanitize_text_field( (string) ( $summary['last_refresh_error'] ?? '' ) );
					self::set_admin_notice( 'error', '' !== $message ? $message : __( 'Monitoring summary refresh failed.', 'npcink-cloud-addon' ) );
					self::redirect_to_page( 'status' );
				}
			}

			$quality = Npcink_Cloud_Observability_Collector::refresh_agent_feedback_summary();
			if ( empty( $quality['last_refresh_ok'] ) ) {
				$message = sanitize_text_field( (string) ( $quality['last_refresh_error'] ?? '' ) );
				self::set_admin_notice( 'error', '' !== $message ? $message : __( 'Agent quality summary refresh failed.', 'npcink-cloud-addon' ) );
				self::redirect_to_page( 'status' );
			}

			$remaining = absint( $upload['buffer_count'] ?? 0 );
			if ( Npcink_Cloud_Addon_Settings::is_monitoring_enabled() ) {
				self::set_admin_notice(
					'success',
					sprintf(
						/* translators: %d: remaining buffered event count. */
						__( 'Monitoring and quality refreshed. Remaining buffered events: %d.', 'npcink-cloud-addon' ),
						$remaining
					)
				);
			} else {
				self::set_admin_notice( 'success', __( 'Agent quality summary refreshed. Monitoring collection is disabled.', 'npcink-cloud-addon' ) );
			}
			self::redirect_to_page( 'status' );
		}

		/**
		 * Handles a manual bounded Site Knowledge public content refresh request.
		 *
		 * @return void
		 */
			public static function handle_refresh_site_knowledge(): void {
				if ( ! current_user_can( 'manage_options' ) ) {
					wp_die( esc_html__( 'You do not have permission to manage Npcink Cloud settings.', 'npcink-cloud-addon' ) );
				}

				check_admin_referer( self::ACTION_REFRESH_SITE_KNOWLEDGE );

				if ( ! Npcink_Cloud_Addon_Settings::is_verified() ) {
					self::set_admin_notice( 'error', __( 'Cloud Addon settings are not verified.', 'npcink-cloud-addon' ) );
					self::redirect_to_page( 'site_knowledge' );
				}

				Npcink_Cloud_Site_Knowledge_Change_Bridge::buffer_recent_public_content();
				$status = Npcink_Cloud_Site_Knowledge_Change_Bridge::flush_buffer();
				if ( empty( $status['last_delivery_ok'] ) ) {
					$message = sanitize_text_field( (string) ( $status['last_delivery_error'] ?? '' ) );
					self::set_admin_notice( 'error', '' !== $message ? $message : __( 'Site Knowledge refresh request failed.', 'npcink-cloud-addon' ) );
					self::redirect_to_page( 'site_knowledge' );
				}

				self::set_admin_notice(
					'success',
					sprintf(
						/* translators: %d: sent public content count. */
						__( 'Site Knowledge refresh requested. Public content items sent: %d.', 'npcink-cloud-addon' ),
						absint( $status['last_sent_count'] ?? 0 )
					)
				);
				self::redirect_to_page( 'site_knowledge' );
			}

			/**
			 * Handles local Site Knowledge delivery consent changes.
			 *
			 * @return void
			 */
			public static function handle_update_site_knowledge_delivery(): void {
				if ( ! current_user_can( 'manage_options' ) ) {
					wp_die( esc_html__( 'You do not have permission to manage Npcink Cloud settings.', 'npcink-cloud-addon' ) );
				}

				check_admin_referer( self::ACTION_UPDATE_SITE_KNOWLEDGE_DELIVERY );

				if ( ! Npcink_Cloud_Addon_Settings::is_verified() ) {
					self::set_admin_notice( 'error', __( 'Cloud Addon settings are not verified.', 'npcink-cloud-addon' ) );
					self::redirect_to_page( 'site_knowledge' );
				}

				$settings = Npcink_Cloud_Addon_Settings::get_settings();
				$settings['site_knowledge_delivery_enabled'] = ! empty( $_POST['site_knowledge_delivery_enabled'] );
				Npcink_Cloud_Addon_Settings::write_settings( $settings );
				Npcink_Cloud_Site_Knowledge_Change_Bridge::sync_schedule();

				if ( ! empty( $settings['site_knowledge_delivery_enabled'] ) ) {
					self::set_admin_notice( 'success', __( 'Site Knowledge delivery enabled for public WordPress content.', 'npcink-cloud-addon' ) );
				} else {
					self::set_admin_notice( 'success', __( 'Site Knowledge delivery disabled locally. Existing Cloud index data was not deleted.', 'npcink-cloud-addon' ) );
				}
				self::redirect_to_page( 'site_knowledge' );
			}

			/**
			 * Handles an administrator-requested Site Knowledge index operation.
			 *
			 * @return void
			 */
			public static function handle_manage_site_knowledge_index(): void {
				if ( ! current_user_can( 'manage_options' ) ) {
					wp_die( esc_html__( 'You do not have permission to manage Npcink Cloud settings.', 'npcink-cloud-addon' ) );
				}

				check_admin_referer( self::ACTION_MANAGE_SITE_KNOWLEDGE_INDEX );

				if ( ! Npcink_Cloud_Addon_Settings::is_verified() ) {
					self::set_admin_notice( 'error', __( 'Cloud Addon settings are not verified.', 'npcink-cloud-addon' ) );
					self::redirect_to_page( 'site_knowledge' );
				}

				$operation = isset( $_POST['site_knowledge_index_action'] ) ? sanitize_key( wp_unslash( $_POST['site_knowledge_index_action'] ) ) : '';
				if ( ! in_array( $operation, array( 'start', 'rebuild', 'delete' ), true ) ) {
					self::set_admin_notice( 'error', __( 'The requested Site Knowledge index action is not supported.', 'npcink-cloud-addon' ) );
					self::redirect_to_page( 'site_knowledge' );
				}

				$confirmation = isset( $_POST['site_knowledge_confirmation'] ) ? sanitize_text_field( wp_unslash( $_POST['site_knowledge_confirmation'] ) ) : '';
				if ( in_array( $operation, array( 'rebuild', 'delete' ), true ) && strtoupper( $confirmation ) !== strtoupper( $operation ) ) {
					self::set_admin_notice( 'error', __( 'Type the confirmation word before running this Site Knowledge index action.', 'npcink-cloud-addon' ) );
					self::redirect_to_page( 'site_knowledge' );
				}

				$status = Npcink_Cloud_Site_Knowledge_Change_Bridge::request_manual_index_operation( $operation );
				if ( is_wp_error( $status ) ) {
					self::set_admin_notice( 'error', $status->get_error_message() );
					self::redirect_to_page( 'site_knowledge' );
				}

				$sent = is_array( $status ) ? absint( $status['last_index_action_sent_count'] ?? $status['last_sent_count'] ?? 0 ) : 0;
				switch ( $operation ) {
					case 'start':
						$message = sprintf(
							/* translators: %d: public content item count. */
							__( 'Site Knowledge indexing started. Public content items sent: %d.', 'npcink-cloud-addon' ),
							$sent
						);
						break;
					case 'rebuild':
						$message = sprintf(
							/* translators: %d: public content item count. */
							__( 'Site Knowledge rebuild requested. Public content items sent: %d.', 'npcink-cloud-addon' ),
							$sent
						);
						break;
					case 'delete':
						$message = __( 'Site Knowledge index deletion requested. WordPress content was not changed.', 'npcink-cloud-addon' );
						break;
					default:
						$message = __( 'Site Knowledge index action requested.', 'npcink-cloud-addon' );
				}

				self::set_admin_notice( 'success', $message );
				self::redirect_to_page( 'site_knowledge' );
			}

			/**
			 * Handles an explicit administrator-triggered connector readiness test.
			 *
			 * @return void
			 */
			public static function handle_run_manual_readiness_test(): void {
				if ( ! current_user_can( 'manage_options' ) ) {
					wp_die( esc_html__( 'You do not have permission to manage Npcink Cloud settings.', 'npcink-cloud-addon' ) );
				}

				check_admin_referer( self::ACTION_RUN_MANUAL_READINESS_TEST );

				$settings = Npcink_Cloud_Addon_Settings::get_settings();
				$result = ( new Npcink_Cloud_Runtime_Client( $settings ) )->manual_readiness_test();
				self::set_manual_readiness_result( $result );

				$status = sanitize_key( (string) ( $result['bounded_status'] ?? $result['status'] ?? '' ) );
				if ( 'ready' === $status ) {
					self::set_admin_notice( 'success', __( 'Manual readiness test completed. Connector is ready.', 'npcink-cloud-addon' ) );
				} else {
					self::set_admin_notice( 'warning', self::format_readiness_detail( $result ) );
				}

				self::redirect_to_page( 'diagnostics' );
			}

			/**
			 * Handles a bounded Cloud-owned retry request for a known runtime run.
			 *
			 * @return void
			 */
			public static function handle_retry_runtime_run(): void {
				if ( ! current_user_can( 'manage_options' ) ) {
					wp_die( esc_html__( 'You do not have permission to manage Npcink Cloud settings.', 'npcink-cloud-addon' ) );
				}

				check_admin_referer( self::ACTION_RETRY_RUNTIME_RUN );

				if ( ! Npcink_Cloud_Addon_Settings::is_verified() ) {
					self::set_admin_notice( 'error', __( 'Cloud Addon settings are not verified.', 'npcink-cloud-addon' ) );
					self::redirect_to_page( 'diagnostics' );
				}

				$run_id = isset( $_POST['runtime_run_id'] ) ? self::normalize_run_id( sanitize_text_field( wp_unslash( $_POST['runtime_run_id'] ) ) ) : '';
				if ( '' === $run_id ) {
					self::set_admin_notice( 'error', __( 'Enter a Cloud run ID before requesting retry.', 'npcink-cloud-addon' ) );
					self::redirect_to_page( 'diagnostics' );
				}

				$settings = Npcink_Cloud_Addon_Settings::get_settings();
				$client = new Npcink_Cloud_Runtime_Client( $settings );
				$result = $client->retry_run(
					$run_id,
					array(
						'retry_context' => array(
							'source'                  => 'cloud_addon_diagnostics_tab',
							'operator_requested'      => true,
							'direct_wordpress_write'  => false,
							'local_queue_created'     => false,
							'local_scheduler_created' => false,
						),
					),
					'trace_cloud_addon_runtime_retry_' . wp_generate_uuid4(),
					'cloud-addon-runtime-retry-' . substr( md5( $run_id . '|' . microtime( true ) ), 0, 24 )
				);
				if ( is_wp_error( $result ) ) {
					self::set_admin_notice( 'error', $result->get_error_message() );
					self::redirect_to_page( 'diagnostics' );
				}

				$data = is_array( $result['data'] ?? null ) ? $result['data'] : ( is_array( $result ) ? $result : array() );
				$retry_run = is_array( $data['retry_run'] ?? null ) ? $data['retry_run'] : array();
				$new_run_id = self::normalize_run_id( (string) ( $retry_run['run_id'] ?? $data['run_id'] ?? '' ) );
				self::set_admin_notice(
					'success',
					'' !== $new_run_id
						? sprintf(
							/* translators: %s: Cloud retry run id. */
							__( 'Cloud retry requested. New run ID: %s. Cloud remains the run-state owner.', 'npcink-cloud-addon' ),
							$new_run_id
						)
						: __( 'Cloud retry requested. Cloud remains the run-state owner.', 'npcink-cloud-addon' )
				);

				$url = add_query_arg(
					array_filter(
						array(
							'tab'              => 'diagnostics',
							'runtime_view'     => '' !== $new_run_id ? 'status' : '',
							'runtime_run_id'   => $new_run_id,
						)
					),
					self::page_url()
				);
				wp_safe_redirect( $url );
				exit;
			}

		/**
		 * Renders the settings page.
		 *
		 * @return void
		 */
		public static function render(): void {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			$settings = Npcink_Cloud_Addon_Settings::get_settings();
			$state = Npcink_Cloud_Addon_Settings::get_credential_state();
			$entitlement = Npcink_Cloud_Entitlement_Summary::get_cached_summary();
			$monitoring = Npcink_Cloud_Observability_Collector::get_status();
			$site_knowledge = Npcink_Cloud_Site_Knowledge_Change_Bridge::health_snapshot();
			$is_verified = ! empty( $state['verified'] );
			$active_tab = self::get_active_tab( $is_verified, $state );
			?>
			<div class="wrap npcink-cloud-addon">
				<h1><?php esc_html_e( 'Npcink Cloud Addon', 'npcink-cloud-addon' ); ?></h1>
				<p><?php esc_html_e( 'Cloud connector status and access settings for this WordPress site.', 'npcink-cloud-addon' ); ?></p>
				<?php self::render_admin_notice(); ?>

				<?php self::render_connection_summary( $settings, $state, $entitlement ); ?>
				<?php self::render_tab_navigation( $active_tab, $is_verified, $state ); ?>

				<?php if ( 'connect' === $active_tab ) : ?>
					<section class="npcink-cloud-section npcink-cloud-tab-panel">
						<h2><?php esc_html_e( 'Connect this site', 'npcink-cloud-addon' ); ?></h2>
						<?php self::render_cloud_authorization_panel( $settings, $state ); ?>
					</section>
				<?php elseif ( 'permissions' === $active_tab ) : ?>
					<?php self::render_local_permissions( $settings, $is_verified ); ?>
				<?php elseif ( 'status' === $active_tab ) : ?>
					<section class="npcink-cloud-section npcink-cloud-tab-panel">
						<h2><?php esc_html_e( 'Status', 'npcink-cloud-addon' ); ?></h2>
						<?php self::render_status_overview( $settings, $state, $entitlement, $monitoring, $site_knowledge, $is_verified ); ?>
					</section>
				<?php elseif ( 'diagnostics' === $active_tab ) : ?>
					<section class="npcink-cloud-section npcink-cloud-tab-panel">
						<h2><?php esc_html_e( 'Troubleshooting', 'npcink-cloud-addon' ); ?></h2>
						<?php self::render_diagnostics( $settings, $state, $entitlement, $monitoring, $site_knowledge, $is_verified ); ?>
					</section>
				<?php elseif ( 'site_knowledge' === $active_tab ) : ?>
					<section class="npcink-cloud-section npcink-cloud-tab-panel">
						<h2 class="screen-reader-text"><?php esc_html_e( 'Site Knowledge', 'npcink-cloud-addon' ); ?></h2>
						<?php self::render_site_knowledge_summary( $site_knowledge, $settings, $is_verified ); ?>
					</section>
				<?php elseif ( 'advanced' === $active_tab ) : ?>
					<section class="npcink-cloud-section npcink-cloud-tab-panel">
						<h2><?php esc_html_e( 'Connection management', 'npcink-cloud-addon' ); ?></h2>
						<?php self::render_connection_management_page( $settings, $state, $entitlement ); ?>
					</section>
				<?php endif; ?>
			</div>
			<?php
		}

		/**
		 * Returns the active settings tab.
		 *
		 * @param bool                $is_verified Whether the connector has verified credentials.
		 * @param array<string,mixed> $state Credential state.
		 * @return string
		 */
		private static function get_active_tab( bool $is_verified, array $state ): string {
			$tabs = self::get_tab_labels( $is_verified, $state );
			$default = $is_verified ? 'permissions' : 'connect';
			$raw_tab = filter_input( INPUT_GET, 'tab', FILTER_UNSAFE_RAW );
			$requested = is_string( $raw_tab ) ? sanitize_key( wp_unslash( $raw_tab ) ) : '';
			if ( $is_verified && 'details' === $requested ) {
				$requested = 'status';
			}
			if ( $is_verified && 'runtime_runs' === $requested ) {
				$requested = 'diagnostics';
			}

			return isset( $tabs[ $requested ] ) ? $requested : $default;
		}

		/**
		 * Returns available tab labels.
		 *
		 * @param bool                $is_verified Whether the connector has verified credentials.
		 * @param array<string,mixed> $state Credential state.
		 * @return array<string,string>
		 */
		private static function get_tab_labels( bool $is_verified, array $state = array() ): array {
			if ( $is_verified ) {
				return array(
					'permissions'    => __( 'Local permissions', 'npcink-cloud-addon' ),
					'status'         => __( 'Status', 'npcink-cloud-addon' ),
					'site_knowledge' => __( 'Site Knowledge', 'npcink-cloud-addon' ),
					'diagnostics'    => __( 'Troubleshooting', 'npcink-cloud-addon' ),
					'advanced'       => __( 'Connection management', 'npcink-cloud-addon' ),
				);
			}

			$tabs = array(
				'connect'  => __( 'Connect', 'npcink-cloud-addon' ),
			);
			if ( self::should_show_unverified_advanced_tab( $state ) ) {
				$tabs['advanced'] = __( 'Connection management', 'npcink-cloud-addon' );
			}

			return $tabs;
		}

		/**
		 * Determines whether unverified local troubleshooting should be visible.
		 *
		 * @param array<string,mixed> $state Credential state.
		 * @return bool
		 */
		private static function should_show_unverified_advanced_tab( array $state ): bool {
			return ! empty( $state['configured'] )
				|| '' !== (string) ( $state['last_verification_error'] ?? '' )
				|| in_array( (string) ( $state['code'] ?? '' ), array( 'configured_unverified', 'verification_failed' ), true );
		}

		/**
		 * Renders settings tab navigation.
		 *
		 * @param string $active_tab Active tab slug.
		 * @param bool                $is_verified Whether the connector has verified credentials.
		 * @param array<string,mixed> $state Credential state.
		 * @return void
		 */
		private static function render_tab_navigation( string $active_tab, bool $is_verified, array $state ): void {
			$tabs = self::get_tab_labels( $is_verified, $state );
			?>
			<nav class="npcink-ai-tabs npcink-cloud-tabs" aria-label="<?php esc_attr_e( 'Cloud addon sections', 'npcink-cloud-addon' ); ?>">
				<?php foreach ( $tabs as $slug => $label ) : ?>
					<?php
					$url = add_query_arg(
						array(
							'page' => self::PAGE_SLUG,
							'tab'  => $slug,
						),
						admin_url( 'admin.php' )
					);
					$is_active = $active_tab === $slug;
					?>
					<a
						class="npcink-ai-tab<?php echo $is_active ? ' npcink-ai-tab-active' : ''; ?>"
						href="<?php echo esc_url( $url ); ?>"
						<?php echo $is_active ? 'aria-current="page"' : ''; ?>
					>
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>
			<?php
		}

		/**
		 * Renders page-local secondary navigation.
		 *
		 * @param string               $active_view Active view slug.
		 * @param array<string,string> $views View labels.
		 * @param string               $parent_tab Parent tab slug.
		 * @param string               $label Navigation label.
		 * @return void
		 */
		private static function render_secondary_tab_navigation( string $active_view, array $views, string $parent_tab, string $label ): void {
			?>
			<nav class="npcink-ai-tabs npcink-cloud-secondary-tabs" aria-label="<?php echo esc_attr( $label ); ?>">
				<?php foreach ( $views as $slug => $view_label ) : ?>
					<?php
					$url = add_query_arg(
						array(
							'page' => self::PAGE_SLUG,
							'tab'  => sanitize_key( $parent_tab ),
							'view' => sanitize_key( $slug ),
						),
						admin_url( 'admin.php' )
					);
					$is_active = $active_view === $slug;
					?>
					<a
						class="npcink-ai-tab<?php echo $is_active ? ' npcink-ai-tab-active' : ''; ?>"
						href="<?php echo esc_url( $url ); ?>"
						<?php echo $is_active ? 'aria-current="page"' : ''; ?>
					>
						<?php echo esc_html( $view_label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>
			<?php
		}

		/**
		 * Builds an admin URL for a settings tab.
		 *
		 * @param string $tab Tab slug.
		 * @return string
		 */
		private static function tab_url( string $tab ): string {
			return add_query_arg(
				array(
					'page' => self::PAGE_SLUG,
					'tab'  => sanitize_key( $tab ),
				),
				admin_url( 'admin.php' )
			);
		}

		/**
		 * Builds a Runtime runs troubleshooting URL.
		 *
		 * @param string $view Runtime view.
		 * @param string $run_id Optional run id.
		 * @return string
		 */
		private static function runtime_tab_url( string $view, string $run_id ): string {
			$args = array(
				'page'         => self::PAGE_SLUG,
				'tab'          => 'diagnostics',
				'runtime_view' => sanitize_key( $view ),
			);
			if ( '' !== $run_id ) {
				$args['runtime_run_id'] = self::normalize_run_id( $run_id );
			}

			return add_query_arg( $args, admin_url( 'admin.php' ) );
		}

		/**
		 * Returns the requested runtime view.
		 *
		 * @return string
		 */
		private static function runtime_view_from_request(): string {
			$raw = filter_input( INPUT_GET, 'runtime_view', FILTER_UNSAFE_RAW );
			$view = is_string( $raw ) ? sanitize_key( wp_unslash( $raw ) ) : '';

			return in_array( $view, array( 'recent', 'status', 'result' ), true ) ? $view : '';
		}

		/**
		 * Returns the requested Site Knowledge subview.
		 *
		 * @return string
		 */
		private static function site_knowledge_view_from_request(): string {
			$raw = filter_input( INPUT_GET, 'view', FILTER_UNSAFE_RAW );
			$view = is_string( $raw ) ? sanitize_key( wp_unslash( $raw ) ) : '';

			return in_array( $view, array( 'overview', 'index' ), true ) ? $view : 'overview';
		}

		/**
		 * Returns the requested diagnostics subview.
		 *
		 * @return string
		 */
		private static function diagnostics_view_from_request(): string {
			if ( '' !== self::runtime_view_from_request() ) {
				return 'runs';
			}

			$raw = filter_input( INPUT_GET, 'view', FILTER_UNSAFE_RAW );
			$view = is_string( $raw ) ? sanitize_key( wp_unslash( $raw ) ) : '';

			return in_array( $view, array( 'checks', 'runs', 'capabilities' ), true ) ? $view : 'checks';
		}

		/**
		 * Returns the requested Status subview.
		 *
		 * @return string
		 */
		private static function status_view_from_request(): string {
			$raw = filter_input( INPUT_GET, 'view', FILTER_UNSAFE_RAW );
			$view = is_string( $raw ) ? sanitize_key( wp_unslash( $raw ) ) : '';

			return in_array( $view, array( 'overview', 'account', 'monitoring', 'monitoring_diagnostics' ), true ) ? $view : 'overview';
		}

		/**
		 * Returns the requested Connection management subview.
		 *
		 * @return string
		 */
		private static function connection_view_from_request(): string {
			$raw = filter_input( INPUT_GET, 'view', FILTER_UNSAFE_RAW );
			$view = is_string( $raw ) ? sanitize_key( wp_unslash( $raw ) ) : '';

			return in_array( $view, array( 'status', 'actions', 'manual' ), true ) ? $view : 'status';
		}

		/**
		 * Returns the requested Cloud run id.
		 *
		 * @return string
		 */
		private static function runtime_run_id_from_request(): string {
			$raw = filter_input( INPUT_GET, 'runtime_run_id', FILTER_UNSAFE_RAW );

			return is_string( $raw ) ? self::normalize_run_id( wp_unslash( $raw ) ) : '';
		}

		/**
		 * Normalizes a Cloud run id for display and signed reads.
		 *
		 * @param mixed $value Raw run id.
		 * @return string
		 */
		private static function normalize_run_id( $value ): string {
			return (string) preg_replace( '/[^A-Za-z0-9._:-]/', '', sanitize_text_field( (string) $value ) );
		}

		/**
		 * Extracts recent run cards from common Cloud response envelopes.
		 *
		 * @param array<string,mixed> $response Cloud response.
		 * @return array<int,array<string,mixed>>
		 */
		private static function runtime_runs_from_response( array $response ): array {
			$candidates = array(
				$response['data']['runs'] ?? null,
				$response['data']['items'] ?? null,
				$response['runs'] ?? null,
				$response['items'] ?? null,
			);
			foreach ( $candidates as $candidate ) {
				if ( is_array( $candidate ) ) {
					return array_values(
						array_filter(
							$candidate,
							static function ( $item ): bool {
								return is_array( $item );
							}
						)
					);
				}
			}

			return array();
		}

		/**
		 * Reads the first scalar value from one shallow record.
		 *
		 * @param array<string,mixed> $source Source record.
		 * @param array<int,string>   $keys Candidate keys.
		 * @return string
		 */
		private static function runtime_scalar( array $source, array $keys ): string {
			foreach ( $keys as $key ) {
				if ( ! array_key_exists( $key, $source ) ) {
					continue;
				}
				$value = $source[ $key ];
				if ( is_bool( $value ) ) {
					return $value ? __( 'yes', 'npcink-cloud-addon' ) : __( 'no', 'npcink-cloud-addon' );
				}
				if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
					return sanitize_text_field( (string) $value );
				}
			}

			return '';
		}

		/**
		 * Reads the first scalar value from nested Cloud response paths.
		 *
		 * @param array<string,mixed> $source Source record.
		 * @param array<int,string>   $paths Dot-separated paths.
		 * @return string
		 */
		private static function runtime_pick( array $source, array $paths ): string {
			foreach ( $paths as $path ) {
				$value = $source;
				foreach ( explode( '.', $path ) as $segment ) {
					if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
						$value = null;
						break;
					}
					$value = $value[ $segment ];
				}
				if ( is_bool( $value ) ) {
					return $value ? __( 'yes', 'npcink-cloud-addon' ) : __( 'no', 'npcink-cloud-addon' );
				}
				if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
					return sanitize_text_field( (string) $value );
				}
			}

			return '';
		}

		/**
		 * Formats the runtime quota projection only when all fields are present.
		 *
		 * @param array<string,mixed> $runtime Runtime entitlement projection.
		 * @return string
		 */
		private static function format_runtime_quota_projection( array $runtime ): string {
			$keys = array(
				'used_nightly_inspection_runs',
				'max_nightly_inspection_runs_per_period',
				'remaining_nightly_inspection_runs',
			);
			foreach ( $keys as $key ) {
				if ( ! array_key_exists( $key, $runtime ) || ! is_numeric( $runtime[ $key ] ) ) {
					return self::format_empty( '' );
				}
			}

			return sprintf(
				/* translators: 1: used runs, 2: max runs, 3: remaining runs. */
				__( '%1$d used / %2$d limit / %3$d remaining', 'npcink-cloud-addon' ),
				absint( $runtime['used_nightly_inspection_runs'] ),
				absint( $runtime['max_nightly_inspection_runs_per_period'] ),
				absint( $runtime['remaining_nightly_inspection_runs'] )
			);
		}

		/**
		 * Formats an optional runtime integer projection.
		 *
		 * @param array<string,mixed> $runtime Runtime entitlement projection.
		 * @param string              $key Projection key.
		 * @return string
		 */
		private static function format_runtime_integer_projection( array $runtime, string $key ): string {
			if ( ! array_key_exists( $key, $runtime ) || ! is_numeric( $runtime[ $key ] ) ) {
				return self::format_empty( '' );
			}

			return (string) absint( $runtime[ $key ] );
		}

		/**
		 * Formats an optional runtime retention-day projection.
		 *
		 * @param array<string,mixed> $runtime Runtime entitlement projection.
		 * @param string              $key Projection key.
		 * @return string
		 */
		private static function format_runtime_days_projection( array $runtime, string $key ): string {
			if ( ! array_key_exists( $key, $runtime ) || ! is_numeric( $runtime[ $key ] ) ) {
				return self::format_empty( '' );
			}

			return sprintf(
				/* translators: %d: retention days. */
				__( '%d days', 'npcink-cloud-addon' ),
				absint( $runtime[ $key ] )
			);
		}

		/**
		 * Formats an optional runtime boolean projection.
		 *
		 * @param array<string,mixed> $runtime Runtime entitlement projection.
		 * @param string              $key Projection key.
		 * @return string
		 */
		private static function format_runtime_boolean_projection( array $runtime, string $key ): string {
			if ( ! array_key_exists( $key, $runtime ) || ! is_bool( $runtime[ $key ] ) ) {
				return self::format_empty( '' );
			}

			return $runtime[ $key ] ? __( 'yes', 'npcink-cloud-addon' ) : __( 'no', 'npcink-cloud-addon' );
		}

		/**
		 * Builds a Cloud Portal URL for authorizing this WordPress site.
		 *
		 * @param array<string,mixed> $settings Stored settings.
		 * @return string
		 */
		private static function build_authorization_url( array $settings ): string {
			$base_url = Npcink_Cloud_Addon_Settings::get_effective_base_url( $settings );
			return self::build_authorization_url_for_base_url( $base_url );
		}

		/**
		 * Builds a Cloud Portal URL for one normalized Cloud base URL.
		 *
		 * @param string $base_url Normalized Cloud base URL.
		 * @return string
		 */
		private static function build_authorization_url_for_base_url( string $base_url ): string {
			$state = self::create_authorization_state( $base_url );
			$return_url = add_query_arg(
				array(
					'action' => self::ACTION_COMPLETE_AUTH,
					'state'  => $state,
				),
				admin_url( 'admin-post.php' )
			);

			return add_query_arg(
				array(
					'connect'    => 'wordpress-addon',
					'site_url'   => home_url( '/' ),
					'site_name'  => get_bloginfo( 'name' ),
					'return_url' => $return_url,
					'state'      => $state,
				),
				untrailingslashit( $base_url ) . '/portal/sites'
			);
		}

		/**
		 * Creates a short-lived local authorization state.
		 *
		 * @param string $base_url Cloud base URL.
		 * @return string
		 */
		private static function create_authorization_state( string $base_url ): string {
			$state = wp_generate_password( 32, false, false );
			set_transient(
				self::authorization_state_transient_name( $state ),
				array(
					'base_url' => $base_url,
					'created'  => time(),
				),
				self::AUTH_STATE_TTL_SECONDS
			);

			return $state;
		}

		/**
		 * Consumes a short-lived local authorization state.
		 *
		 * @param string $state Authorization state.
		 * @return array<string,mixed>
		 */
		private static function consume_authorization_state( string $state ): array {
			$state = trim( $state );
			if ( '' === $state ) {
				return array();
			}

			$name = self::authorization_state_transient_name( $state );
			$value = get_transient( $name );
			delete_transient( $name );

			return is_array( $value ) ? $value : array();
		}

		/**
		 * Returns the transient name for an authorization state.
		 *
		 * @param string $state Authorization state.
		 * @return string
		 */
		private static function authorization_state_transient_name( string $state ): string {
			return 'npcink_cloud_auth_' . hash( 'sha256', $state );
		}

		/**
		 * Exchanges a Cloud one-time authorization code for a customer API key.
		 *
		 * @param string $base_url Cloud base URL.
		 * @param string $code     One-time authorization code.
		 * @param string $state    Local authorization state.
		 * @return array<string,mixed>|WP_Error
		 */
		private static function exchange_authorization_code( string $base_url, string $code, string $state ) {
			$response = wp_remote_post(
				untrailingslashit( $base_url ) . '/portal/v1/addon-connections/exchange',
				array(
					'timeout' => 12,
					'headers' => array(
						'Content-Type' => 'application/json',
					),
					'body'    => wp_json_encode(
						array(
							'code'  => $code,
							'state' => $state,
						)
					),
				)
			);
			if ( is_wp_error( $response ) ) {
				return new WP_Error(
					'cloud_authorization_exchange_failed',
					sprintf(
						/* translators: %s: request error message. */
						__( 'Cloud authorization exchange failed: %s', 'npcink-cloud-addon' ),
						$response->get_error_message()
					)
				);
			}

			$status = (int) wp_remote_retrieve_response_code( $response );
			$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			$data = is_array( $body ) && is_array( $body['data'] ?? null ) ? $body['data'] : array();
			$cloud_api_key = (string) ( $data['cloud_api_key'] ?? '' );
			if ( $status < 200 || $status >= 300 || '' === $cloud_api_key ) {
				return new WP_Error(
					'cloud_authorization_exchange_failed',
					__( 'Cloud authorization exchange did not return a valid connection key.', 'npcink-cloud-addon' )
				);
			}

			return array(
				'cloud_api_key' => $cloud_api_key,
			);
		}

		/**
		 * Renders the default connector summary.
		 *
		 * @param array<string,mixed> $settings Stored settings.
		 * @param array<string,mixed> $state Credential state.
		 * @param array<string,mixed> $entitlement Entitlement summary.
		 * @return void
		 */
		private static function render_connection_summary( array $settings, array $state, array $entitlement ): void {
			$severity = sanitize_html_class( (string) ( $state['severity'] ?? 'inactive' ) );
			$is_verified = ! empty( $state['verified'] );
			$is_configured = ! empty( $state['configured'] );
			$display_base_url = $is_configured
				? (string) $settings['base_url']
				: Npcink_Cloud_Addon_Settings::get_effective_base_url( $settings );
			?>
			<section class="npcink-cloud-summary">
				<div class="npcink-cloud-summary__header">
					<div>
						<p class="npcink-cloud-summary__state">
							<span class="npcink-cloud-badge npcink-cloud-badge--<?php echo esc_attr( $severity ); ?>"><?php echo esc_html( (string) $state['label'] ); ?></span>
						</p>
						<p class="npcink-cloud-summary__message"><?php echo esc_html( (string) $state['message'] ); ?></p>
					</div>
					<?php if ( $is_configured ) : ?>
						<?php self::render_connection_actions( $settings, $is_verified ); ?>
					<?php endif; ?>
				</div>
				<?php if ( $is_configured ) : ?>
					<div class="npcink-cloud-summary__grid">
						<div class="npcink-cloud-summary__item">
							<span class="npcink-cloud-summary__label"><?php esc_html_e( 'Cloud Base URL', 'npcink-cloud-addon' ); ?></span>
							<span class="npcink-cloud-summary__value"><?php echo esc_html( self::format_setting_value( $display_base_url, __( 'Not set', 'npcink-cloud-addon' ) ) ); ?></span>
						</div>
						<div class="npcink-cloud-summary__item">
							<span class="npcink-cloud-summary__label"><?php esc_html_e( 'Last verified', 'npcink-cloud-addon' ); ?></span>
							<span class="npcink-cloud-summary__value"><?php echo esc_html( self::format_datetime_value( (string) $settings['verified_at'], __( 'Never', 'npcink-cloud-addon' ) ) ); ?></span>
						</div>
					</div>
				<?php endif; ?>
			</section>
			<?php
		}

		/**
		 * Returns local permission switch definitions.
		 *
		 * @return array<string,array{label:string,description:string}>
		 */
		private static function get_local_permission_definitions(): array {
			return array(
				'wordpress_ai_connector_enabled' => array(
					'label'       => __( 'WordPress AI connector', 'npcink-cloud-addon' ),
					'description' => __( 'Allow the WordPress AI plugin to select Npcink Cloud as an AI connector.', 'npcink-cloud-addon' ),
				),
				'site_knowledge_delivery_enabled' => array(
					'label'       => __( 'Site Knowledge delivery', 'npcink-cloud-addon' ),
					'description' => __( 'Allow public content-change delivery and explicit administrator delivery intents for Cloud-owned Site Knowledge indexing.', 'npcink-cloud-addon' ),
				),
				'site_knowledge_generation_reference_enabled' => array(
					'label'       => __( 'Reference site content during generation', 'npcink-cloud-addon' ),
					'description' => __( 'Allow Npcink Cloud to reference indexed public articles during supported WordPress AI generation tasks so suggestions better match this site\'s writing style and taxonomy. WordPress content is not changed.', 'npcink-cloud-addon' ),
				),
				'monitoring_enabled' => array(
					'label'       => __( 'Monitoring', 'npcink-cloud-addon' ),
					'description' => __( 'Upload metadata-only plugin monitoring events. Prompts, content, results, secrets, and raw request payloads are not collected.', 'npcink-cloud-addon' ),
				),
			);
		}

		/**
		 * Synchronizes local side effects after a permission change.
		 *
		 * @param string $permission Permission key.
		 * @return void
		 */
		private static function sync_local_permission_effects( string $permission ): void {
			if ( 'wordpress_ai_connector_enabled' === $permission ) {
				Npcink_Cloud_WordPress_AI_Connector::sync_connected_marker();
				return;
			}

			if ( 'site_knowledge_delivery_enabled' === $permission ) {
				Npcink_Cloud_Site_Knowledge_Change_Bridge::sync_schedule();
				return;
			}

			if ( 'monitoring_enabled' === $permission ) {
				Npcink_Cloud_Observability_Collector::sync_schedule();
			}
		}

		/**
		 * Renders top-level local permission switches.
		 *
		 * @param array<string,mixed> $settings Stored settings.
		 * @param bool                $is_verified Whether the connector has verified credentials.
		 * @return void
		 */
		private static function render_local_permissions( array $settings, bool $is_verified ): void {
			if ( ! $is_verified ) {
				return;
			}

			?>
			<section class="npcink-cloud-local-permissions" aria-labelledby="npcink-cloud-local-permissions-title">
				<div class="npcink-cloud-local-permissions__header">
					<h2 id="npcink-cloud-local-permissions-title"><?php esc_html_e( 'Local permissions', 'npcink-cloud-addon' ); ?></h2>
					<p><?php esc_html_e( 'Choose which verified Cloud connector services this WordPress site may expose locally. Changes save immediately.', 'npcink-cloud-addon' ); ?></p>
				</div>
				<div class="npcink-cloud-local-permissions__list">
					<?php foreach ( self::get_local_permission_definitions() as $permission => $definition ) : ?>
						<?php self::render_local_permission_switch( $permission, $definition, ! empty( $settings[ $permission ] ) ); ?>
					<?php endforeach; ?>
				</div>
			</section>
			<?php
		}

		/**
		 * Renders one local permission switch.
		 *
		 * @param string                             $permission Permission key.
		 * @param array{label:string,description:string} $definition Permission copy.
		 * @param bool                               $enabled Whether the switch is enabled.
		 * @return void
		 */
		private static function render_local_permission_switch( string $permission, array $definition, bool $enabled ): void {
			$input_id = 'npcink-cloud-local-permission-' . sanitize_html_class( str_replace( '_', '-', $permission ) );
			?>
			<form class="npcink-cloud-local-permission" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( self::ACTION_UPDATE_LOCAL_PERMISSION ) ); ?>" />
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_UPDATE_LOCAL_PERMISSION ); ?>" />
				<input type="hidden" name="permission" value="<?php echo esc_attr( $permission ); ?>" />
				<input type="hidden" name="enabled" value="0" />
				<label class="npcink-cloud-local-permission__control" for="<?php echo esc_attr( $input_id ); ?>">
					<span class="npcink-ai-switch">
						<input
							type="checkbox"
							class="npcink-ai-switch__input"
							id="<?php echo esc_attr( $input_id ); ?>"
							name="enabled"
							value="1"
							onchange="this.form.submit();"
							<?php checked( $enabled ); ?>
						/>
						<span class="npcink-ai-switch__track" aria-hidden="true">
							<span class="npcink-ai-switch__thumb"></span>
						</span>
					</span>
					<span class="npcink-cloud-local-permission__copy">
						<span class="npcink-cloud-local-permission__title"><?php echo esc_html( $definition['label'] ); ?></span>
						<span class="npcink-cloud-local-permission__description"><?php echo esc_html( $definition['description'] ); ?></span>
					</span>
					<span class="npcink-cloud-local-permission__state"><?php echo $enabled ? esc_html__( 'enabled', 'npcink-cloud-addon' ) : esc_html__( 'disabled', 'npcink-cloud-addon' ); ?></span>
				</label>
				<noscript>
					<button type="submit" class="button button-secondary"><?php esc_html_e( 'Save', 'npcink-cloud-addon' ); ?></button>
				</noscript>
			</form>
			<?php
		}

		/**
		 * Renders connection-level actions.
		 *
		 * @param array<string,mixed> $settings Stored settings.
		 * @param bool                $is_verified Whether the connector has verified credentials.
		 * @return void
		 */
		private static function render_connection_actions( array $settings, bool $is_verified ): void {
			?>
			<div class="npcink-cloud-summary__actions">
				<?php self::render_reverify_form( $settings ); ?>
				<?php if ( $is_verified ) : ?>
					<a class="button button-secondary" href="<?php echo esc_url( untrailingslashit( Npcink_Cloud_Addon_Settings::get_effective_base_url( $settings ) ) . '/portal/sites' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open Cloud sites', 'npcink-cloud-addon' ); ?></a>
				<?php endif; ?>
			</div>
			<?php
		}

		/**
		 * Renders connection management secondary sections.
		 *
		 * @param array<string,mixed> $settings Stored settings.
		 * @param array<string,mixed> $state Credential state.
		 * @param array<string,mixed> $entitlement Entitlement summary.
		 * @return void
		 */
		private static function render_connection_management_page( array $settings, array $state, array $entitlement ): void {
			$active_view = self::connection_view_from_request();
			self::render_secondary_tab_navigation(
				$active_view,
				array(
					'status'  => __( 'Connection status', 'npcink-cloud-addon' ),
					'actions' => __( 'Connection actions', 'npcink-cloud-addon' ),
					'manual'  => __( 'Manual fallback', 'npcink-cloud-addon' ),
				),
				'advanced',
				__( 'Connection management sections', 'npcink-cloud-addon' )
			);

			if ( 'actions' === $active_view ) {
				self::render_connection_management( $settings );
				return;
			}

			if ( 'manual' === $active_view ) {
				self::render_manual_connection_fallback( $settings );
				return;
			}

			self::render_advanced_information( $settings, $state, $entitlement );
		}

		/**
		 * Renders low-frequency connection management actions.
		 *
		 * @param array<string,mixed> $settings Stored settings.
		 * @return void
		 */
		private static function render_connection_management( array $settings ): void {
			if ( empty( $settings['base_url'] ) ) {
				return;
			}
			?>
			<h3><?php esc_html_e( 'Cloud connection actions', 'npcink-cloud-addon' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Use Cloud for account-level connection changes. Local disconnect only clears this WordPress site.', 'npcink-cloud-addon' ); ?></p>
			<div class="npcink-cloud-summary__actions npcink-cloud-summary__actions--start">
				<a class="button button-secondary" href="<?php echo esc_url( self::build_authorization_url( $settings ) ); ?>"><?php esc_html_e( 'Change connection in Cloud', 'npcink-cloud-addon' ); ?></a>
				<?php self::render_disconnect_form(); ?>
			</div>
			<?php
		}

		/**
		 * Renders the manual recovery connection form.
		 *
		 * @param array<string,mixed> $settings Stored settings.
		 * @return void
		 */
		private static function render_manual_connection_fallback( array $settings ): void {
			?>
			<h3><?php esc_html_e( 'Manual connection fallback', 'npcink-cloud-addon' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Use only for recovery or local debugging when Cloud authorization is unavailable.', 'npcink-cloud-addon' ); ?></p>
			<?php self::render_settings_form( $settings ); ?>
			<?php
		}

		/**
		 * Renders a compact re-verification action.
		 *
		 * @param array<string,mixed> $settings Stored settings.
		 * @return void
		 */
		private static function render_reverify_form( array $settings ): void {
			?>
			<form class="npcink-cloud-verify-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( self::ACTION_SAVE ) ); ?>" />
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_SAVE ); ?>" />
				<input type="hidden" name="base_url" value="<?php echo esc_attr( (string) $settings['base_url'] ); ?>" />
				<input type="hidden" name="api_key" value="" />
				<input type="hidden" name="timeout" value="<?php echo esc_attr( (string) $settings['timeout'] ); ?>" />
				<input type="hidden" name="monitoring_enabled" value="<?php echo esc_attr( ! empty( $settings['monitoring_enabled'] ) ? '1' : '0' ); ?>" />
				<input type="hidden" name="site_knowledge_delivery_enabled" value="<?php echo esc_attr( ! empty( $settings['site_knowledge_delivery_enabled'] ) ? '1' : '0' ); ?>" />
				<input type="hidden" name="site_knowledge_generation_reference_enabled" value="<?php echo esc_attr( ! empty( $settings['site_knowledge_generation_reference_enabled'] ) ? '1' : '0' ); ?>" />
				<input type="hidden" name="wordpress_ai_connector_enabled" value="<?php echo esc_attr( ! empty( $settings['wordpress_ai_connector_enabled'] ) ? '1' : '0' ); ?>" />
				<button type="submit" class="button button-secondary"><?php esc_html_e( 'Re-verify and refresh', 'npcink-cloud-addon' ); ?></button>
			</form>
			<?php
		}

		/**
		 * Renders a local disconnect action.
		 *
		 * @return void
		 */
		private static function render_disconnect_form(): void {
			?>
			<form class="npcink-cloud-disconnect-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( self::ACTION_DISCONNECT ) ); ?>" />
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_DISCONNECT ); ?>" />
				<button
					type="submit"
					class="button button-secondary npcink-cloud-button-danger"
					onclick="return confirm('<?php echo esc_js( __( 'Disconnect this site locally? Stored Cloud credentials and addon-owned buffers will be cleared from this WordPress site only. Manage the site connection in the Cloud portal.', 'npcink-cloud-addon' ) ); ?>');"
				>
					<?php esc_html_e( 'Disconnect locally', 'npcink-cloud-addon' ); ?>
				</button>
			</form>
			<?php
		}

		/**
		 * Renders connector settings.
		 *
		 * @param array<string,mixed> $settings Stored settings.
		 * @return void
		 */
		private static function render_settings_form( array $settings ): void {
			$base_url = Npcink_Cloud_Addon_Settings::get_effective_base_url( $settings );
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width: 860px;">
				<?php wp_nonce_field( self::ACTION_SAVE ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_SAVE ); ?>" />
				<input type="hidden" name="monitoring_enabled" value="<?php echo esc_attr( ! empty( $settings['monitoring_enabled'] ) ? '1' : '0' ); ?>" />
				<input type="hidden" name="site_knowledge_delivery_enabled" value="<?php echo esc_attr( ! empty( $settings['site_knowledge_delivery_enabled'] ) ? '1' : '0' ); ?>" />
				<input type="hidden" name="site_knowledge_generation_reference_enabled" value="<?php echo esc_attr( ! empty( $settings['site_knowledge_generation_reference_enabled'] ) ? '1' : '0' ); ?>" />
				<input type="hidden" name="wordpress_ai_connector_enabled" value="<?php echo esc_attr( ! empty( $settings['wordpress_ai_connector_enabled'] ) ? '1' : '0' ); ?>" />
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="npcink-cloud-base-url"><?php esc_html_e( 'Cloud Base URL', 'npcink-cloud-addon' ); ?></label>
							</th>
							<td>
								<input
									type="url"
									class="regular-text code"
									id="npcink-cloud-base-url"
									name="base_url"
									value="<?php echo esc_attr( $base_url ); ?>"
									placeholder="<?php echo esc_attr( Npcink_Cloud_Addon_Settings::get_default_base_url() ); ?>"
									required
								/>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="npcink-cloud-api-key"><?php esc_html_e( 'Recovery Cloud API Key', 'npcink-cloud-addon' ); ?></label>
							</th>
							<td>
								<input
									type="password"
									class="regular-text code"
									id="npcink-cloud-api-key"
									name="api_key"
									value=""
									autocomplete="new-password"
									placeholder="<?php echo esc_attr__( 'Paste a Cloud-issued mak1_ recovery key', 'npcink-cloud-addon' ); ?>"
								/>
								<p class="description"><?php esc_html_e( 'Leave blank to keep the stored connection key. Use Change connection in Cloud for normal account changes.', 'npcink-cloud-addon' ); ?></p>
								<p class="description"><?php esc_html_e( 'This fallback accepts only a Cloud-issued mak1_ wrapper and never displays the stored value.', 'npcink-cloud-addon' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="npcink-cloud-timeout"><?php esc_html_e( 'Timeout', 'npcink-cloud-addon' ); ?></label>
							</th>
							<td>
								<input
									type="number"
									id="npcink-cloud-timeout"
									name="timeout"
									min="5"
									max="60"
									step="1"
									value="<?php echo esc_attr( (string) $settings['timeout'] ); ?>"
								/>
								<span><?php esc_html_e( 'seconds', 'npcink-cloud-addon' ); ?></span>
							</td>
						</tr>
					</tbody>
				</table>
				<?php submit_button( __( 'Save and Verify', 'npcink-cloud-addon' ) ); ?>
			</form>
			<?php
		}

		/**
		 * Renders the compact default status panel.
		 *
		 * @param array<string,mixed> $settings Stored settings.
		 * @param array<string,mixed> $state Credential state.
		 * @param array<string,mixed> $entitlement Entitlement summary.
		 * @param array<string,mixed> $monitoring Monitoring status.
		 * @param array<string,mixed> $site_knowledge Site Knowledge bridge status.
		 * @param bool                $is_verified Whether the connector has verified credentials.
		 * @return void
		 */
		private static function render_status_overview( array $settings, array $state, array $entitlement, array $monitoring, array $site_knowledge, bool $is_verified ): void {
			if ( ! $is_verified ) {
				self::render_cloud_authorization_panel( $settings, $state );
				return;
			}

			$active_view = self::status_view_from_request();
			self::render_secondary_tab_navigation(
				$active_view,
				array(
					'overview'               => __( 'Overview', 'npcink-cloud-addon' ),
					'account'                => __( 'Account and usage', 'npcink-cloud-addon' ),
					'monitoring'             => __( 'Monitoring quality', 'npcink-cloud-addon' ),
					'monitoring_diagnostics' => __( 'Monitoring diagnostics', 'npcink-cloud-addon' ),
				),
				'status',
				__( 'Status sections', 'npcink-cloud-addon' )
			);

			if ( 'account' === $active_view ) {
				self::render_status_account_usage( $entitlement, $is_verified );
				return;
			}

			if ( 'monitoring' === $active_view ) {
				self::render_status_monitoring_quality( $monitoring );
				return;
			}

			if ( 'monitoring_diagnostics' === $active_view ) {
				self::render_status_monitoring_diagnostics( $monitoring );
				return;
			}
			?>
			<table class="widefat striped" style="max-width: 860px;">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Connection', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( (string) ( $state['label'] ?? '' ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Entitlement', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_entitlement_availability( $entitlement, $is_verified ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Entitlement summary fresh until', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_datetime_value( (string) ( $entitlement['fresh_until'] ?? '' ) ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Package', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_package_label( $entitlement, $is_verified ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Monitoring', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_monitoring_overview( $monitoring ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Site Knowledge', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_site_knowledge_overview( $site_knowledge ) ); ?></td>
					</tr>
				</tbody>
			</table>
			<p class="description"><?php esc_html_e( 'Entitlement, monitoring, and Site Knowledge are local connector summaries. Cloud remains the service owner for indexing, runtime detail, and diagnostics.', 'npcink-cloud-addon' ); ?></p>
			<?php
		}

		/**
		 * Renders bounded Cloud service diagnostics.
		 *
		 * @param array<string,mixed> $settings Stored settings.
		 * @param array<string,mixed> $state Credential state.
		 * @param array<string,mixed> $entitlement Entitlement summary.
		 * @param array<string,mixed> $monitoring Monitoring status.
		 * @param array<string,mixed> $site_knowledge Site Knowledge bridge status.
		 * @param bool                $is_verified Whether the connector has verified credentials.
		 * @return void
		 */
		private static function render_diagnostics( array $settings, array $state, array $entitlement, array $monitoring, array $site_knowledge, bool $is_verified ): void {
			if ( ! $is_verified ) {
				self::render_cloud_authorization_panel( $settings, $state );
				return;
			}

			$active_view = self::diagnostics_view_from_request();
			self::render_secondary_tab_navigation(
				$active_view,
				array(
					'checks'       => __( 'Checks', 'npcink-cloud-addon' ),
					'runs'         => __( 'Runtime runs', 'npcink-cloud-addon' ),
					'capabilities' => __( 'Capability notes', 'npcink-cloud-addon' ),
				),
				'diagnostics',
				__( 'Troubleshooting sections', 'npcink-cloud-addon' )
			);

			if ( 'runs' === $active_view ) {
				self::render_runtime_runs( $settings, $state, $entitlement, $is_verified );
				return;
			}

			if ( 'capabilities' === $active_view ) {
				self::render_diagnostic_capability_notes();
				return;
			}

			self::render_diagnostic_checks( $settings, $state, $entitlement, $monitoring, $site_knowledge, $is_verified );
		}

		/**
		 * Renders bounded Cloud service checks.
		 *
		 * @param array<string,mixed> $settings Stored settings.
		 * @param array<string,mixed> $state Credential state.
		 * @param array<string,mixed> $entitlement Entitlement summary.
		 * @param array<string,mixed> $monitoring Monitoring status.
		 * @param array<string,mixed> $site_knowledge Site Knowledge bridge status.
		 * @param bool                $is_verified Whether the connector has verified credentials.
		 * @return void
		 */
		private static function render_diagnostic_checks( array $settings, array $state, array $entitlement, array $monitoring, array $site_knowledge, bool $is_verified ): void {
			$runtime = ! empty( $entitlement['available'] ) && is_array( $entitlement['pro_cloud_runtime'] ?? null ) ? $entitlement['pro_cloud_runtime'] : array();
			$credit_detail = is_array( $entitlement['credit_usage_detail'] ?? null ) ? $entitlement['credit_usage_detail'] : array();
			$links = is_array( $entitlement['links'] ?? null ) ? $entitlement['links'] : array();
			$usage_url = esc_url( (string) ( $links['usage_url'] ?? '' ) );
			$readiness = self::get_manual_readiness_result();
			?>
			<div class="npcink-cloud-section-heading">
				<h3><?php esc_html_e( 'Checks', 'npcink-cloud-addon' ); ?></h3>
				<div class="npcink-cloud-summary__actions">
					<?php self::render_manual_readiness_test_form(); ?>
					<a class="button button-secondary" href="<?php echo esc_url( untrailingslashit( Npcink_Cloud_Addon_Settings::get_effective_base_url( $settings ) ) . '/portal/sites' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open Cloud status detail', 'npcink-cloud-addon' ); ?></a>
				</div>
			</div>
			<p class="description"><?php esc_html_e( 'Read-only connection and service status. Product actions, approvals, and WordPress writes stay outside this addon.', 'npcink-cloud-addon' ); ?></p>
			<table class="widefat striped" style="max-width: 980px;">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Check', 'npcink-cloud-addon' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'npcink-cloud-addon' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Detail', 'npcink-cloud-addon' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php self::render_diagnostic_group_heading( 'local_configuration', __( 'Connection Management', 'npcink-cloud-addon' ) ); ?>
					<?php self::render_diagnostic_row( __( 'Cloud Base URL', 'npcink-cloud-addon' ), self::diagnostic_status( '' !== (string) ( $settings['base_url'] ?? '' ), __( 'saved', 'npcink-cloud-addon' ), __( 'missing', 'npcink-cloud-addon' ) ), self::format_setting_value( (string) ( $settings['base_url'] ?? '' ), __( 'Not set', 'npcink-cloud-addon' ) ) ); ?>
					<?php self::render_diagnostic_row( __( 'Cloud API Key', 'npcink-cloud-addon' ), self::diagnostic_status( ! empty( $state['configured'] ), __( 'saved', 'npcink-cloud-addon' ), __( 'missing', 'npcink-cloud-addon' ) ), __( 'Stored server-side only. Split signing credentials are not displayed.', 'npcink-cloud-addon' ) ); ?>
					<?php self::render_diagnostic_group_heading( 'cloud_connectivity', __( 'Cloud status', 'npcink-cloud-addon' ) ); ?>
					<?php self::render_diagnostic_row( __( 'Cloud liveness', 'npcink-cloud-addon' ), self::diagnostic_status( ! empty( $state['verified'] ), __( 'verified', 'npcink-cloud-addon' ), __( 'not verified', 'npcink-cloud-addon' ) ), sprintf( /* translators: %s: last verification time. */ __( 'Last checked: %s', 'npcink-cloud-addon' ), self::format_datetime_value( (string) ( $settings['verified_at'] ?? '' ), __( 'Never', 'npcink-cloud-addon' ) ) ) ); ?>
					<?php self::render_diagnostic_row( __( 'Manual readiness test', 'npcink-cloud-addon' ), self::format_readiness_status( $readiness ), self::format_readiness_detail( $readiness ) ); ?>
					<?php self::render_diagnostic_group_heading( 'signed_transport', __( 'Signed Cloud read', 'npcink-cloud-addon' ) ); ?>
					<?php self::render_diagnostic_row( __( 'Signed Cloud read', 'npcink-cloud-addon' ), self::format_entitlement_availability( $entitlement, $is_verified ), self::format_empty( (string) ( $entitlement['message'] ?? '' ) ) ); ?>
					<?php self::render_diagnostic_group_heading( 'entitlement_readiness', __( 'Entitlement and quota', 'npcink-cloud-addon' ) ); ?>
					<?php self::render_diagnostic_row( __( 'Entitlement and quota', 'npcink-cloud-addon' ), self::diagnostic_status( ! empty( $entitlement['available'] ), __( 'available', 'npcink-cloud-addon' ), __( 'not refreshed', 'npcink-cloud-addon' ) ), self::format_package_label( $entitlement, $is_verified ) ); ?>
					<?php self::render_diagnostic_row( __( 'Hosted Runtime', 'npcink-cloud-addon' ), self::diagnostic_status( ! empty( $runtime['feature_id'] ), __( 'reported', 'npcink-cloud-addon' ), __( 'not returned', 'npcink-cloud-addon' ) ), self::format_hosted_runtime_diagnostic_detail( $runtime ) ); ?>
					<?php self::render_diagnostic_group_heading( 'support_facts', __( 'Readiness support facts', 'npcink-cloud-addon' ) ); ?>
					<?php self::render_diagnostic_row( __( 'Readiness support facts', 'npcink-cloud-addon' ), (string) ( $readiness['write_posture'] ?? 'read_only' ), self::format_readiness_support_facts( $readiness ) ); ?>
					<?php self::render_diagnostic_row( __( 'Site Knowledge bridge', 'npcink-cloud-addon' ), self::format_site_knowledge_overview( $site_knowledge ), self::format_site_knowledge_diagnostic_detail( $site_knowledge ) ); ?>
					<?php self::render_diagnostic_row( __( 'Monitoring detail', 'npcink-cloud-addon' ), self::format_monitoring_overview( $monitoring ), __( 'Metadata-only upload and read-only aggregate summaries; not Core audit or approval truth.', 'npcink-cloud-addon' ) ); ?>
				</tbody>
			</table>
			<?php if ( '' !== $usage_url || ! empty( $credit_detail['available'] ) ) : ?>
				<p class="npcink-cloud-section-action">
					<?php if ( '' !== $usage_url ) : ?>
						<a class="button button-secondary" href="<?php echo esc_url( $usage_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View usage in Cloud', 'npcink-cloud-addon' ); ?></a>
					<?php endif; ?>
				</p>
			<?php endif; ?>
			<?php
		}

		/**
		 * Renders Cloud-owned capability notes.
		 *
		 * @return void
		 */
		private static function render_diagnostic_capability_notes(): void {
			?>
			<h3><?php esc_html_e( 'Cloud-owned capability notes', 'npcink-cloud-addon' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Provider readiness and product tools stay outside this local connector.', 'npcink-cloud-addon' ); ?></p>
			<div class="npcink-cloud-capability-list">
				<div class="npcink-cloud-capability-header" role="row">
					<span><?php esc_html_e( 'Check', 'npcink-cloud-addon' ); ?></span>
					<span><?php esc_html_e( 'Status', 'npcink-cloud-addon' ); ?></span>
					<span><?php esc_html_e( 'Detail', 'npcink-cloud-addon' ); ?></span>
				</div>
				<?php self::render_capability_note( __( 'Platform Models and provider readiness', 'npcink-cloud-addon' ), __( 'Cloud-owned', 'npcink-cloud-addon' ), __( 'No addon read contract is currently connected. Use Cloud status detail for provider-level readiness.', 'npcink-cloud-addon' ) ); ?>
				<?php self::render_capability_note( __( 'Cloud web search', 'npcink-cloud-addon' ), __( 'Not connected', 'npcink-cloud-addon' ), __( 'No Cloud Addon status API is currently contracted for web search capability.', 'npcink-cloud-addon' ) ); ?>
				<?php self::render_capability_note( __( 'Cloud image generation', 'npcink-cloud-addon' ), __( 'Scene runtime only', 'npcink-cloud-addon' ), __( 'Available through the WordPress AI image scene after verification; provider and source detail stay Cloud-owned.', 'npcink-cloud-addon' ) ); ?>
				<?php self::render_capability_note( __( 'Image source search', 'npcink-cloud-addon' ), __( 'Not connected', 'npcink-cloud-addon' ), __( 'Tavily, Unsplash, and other product search tools are not Cloud Addon admin actions.', 'npcink-cloud-addon' ) ); ?>
			</div>
			<?php
		}

		/**
		 * Renders one diagnostics row.
		 *
		 * @param string $label Row label.
		 * @param string $status Row status.
		 * @param string $detail Row detail.
		 * @return void
		 */
		private static function render_diagnostic_row( string $label, string $status, string $detail ): void {
			?>
			<tr>
				<th scope="row"><?php echo esc_html( $label ); ?></th>
				<td><?php echo esc_html( self::format_empty( $status ) ); ?></td>
				<td><?php echo esc_html( self::format_empty( $detail ) ); ?></td>
			</tr>
			<?php
		}

		/**
		 * Renders one table-native diagnostic group heading.
		 *
		 * @param string $group Group identifier.
		 * @param string $label Operator-facing group label.
		 * @return void
		 */
		private static function render_diagnostic_group_heading( string $group, string $label ): void {
			?>
			<tr class="npcink-cloud-diagnostic-group" data-diagnostic-panel-group="<?php echo esc_attr( $group ); ?>">
				<th colspan="3" scope="colgroup"><?php echo esc_html( $label ); ?></th>
			</tr>
			<?php
		}

		/**
		 * Renders the explicit manual readiness test action.
		 *
		 * @return void
		 */
		private static function render_manual_readiness_test_form(): void {
			?>
			<form class="npcink-cloud-verify-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( self::ACTION_RUN_MANUAL_READINESS_TEST ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_RUN_MANUAL_READINESS_TEST ); ?>" />
				<button type="submit" class="button button-secondary"><?php esc_html_e( 'Run readiness test', 'npcink-cloud-addon' ); ?></button>
			</form>
			<?php
		}

		/**
		 * Renders one lightweight capability note.
		 *
		 * @param string $label Capability label.
		 * @param string $status Capability status.
		 * @param string $detail Capability detail.
		 * @return void
		 */
		private static function render_capability_note( string $label, string $status, string $detail ): void {
			$detail_id = 'npcink-cloud-capability-detail-' . substr( md5( $label . '|' . $status . '|' . $detail ), 0, 10 );
			?>
			<div class="npcink-cloud-capability-item">
				<strong class="npcink-cloud-capability-name"><?php echo esc_html( $label ); ?></strong>
				<span class="npcink-cloud-capability-state"><?php echo esc_html( self::format_empty( $status ) ); ?></span>
				<span class="npcink-cloud-capability-detail" tabindex="0" aria-describedby="<?php echo esc_attr( $detail_id ); ?>" title="<?php echo esc_attr( self::format_empty( $detail ) ); ?>">
					<span class="npcink-cloud-capability-icon" aria-hidden="true">!</span>
					<span class="screen-reader-text"><?php esc_html_e( 'Detail', 'npcink-cloud-addon' ); ?></span>
					<span id="<?php echo esc_attr( $detail_id ); ?>" class="npcink-cloud-capability-popover" role="tooltip"><?php echo esc_html( self::format_empty( $detail ) ); ?></span>
				</span>
			</div>
			<?php
		}

		/**
		 * Formats a boolean diagnostics status.
		 *
		 * @param bool   $ok Positive status.
		 * @param string $ok_label Positive label.
		 * @param string $fail_label Negative label.
		 * @return string
		 */
		private static function diagnostic_status( bool $ok, string $ok_label, string $fail_label ): string {
			return $ok ? $ok_label : $fail_label;
		}

		/**
		 * Formats the bounded readiness status.
		 *
		 * @param array<string,mixed> $readiness Readiness result.
		 * @return string
		 */
		private static function format_readiness_status( array $readiness ): string {
			if ( empty( $readiness ) ) {
				return __( 'not run', 'npcink-cloud-addon' );
			}

			return sanitize_key( (string) ( $readiness['bounded_status'] ?? $readiness['status'] ?? 'unavailable' ) );
		}

		/**
		 * Formats the bounded readiness owner and next action.
		 *
		 * @param array<string,mixed> $readiness Readiness result.
		 * @return string
		 */
		private static function format_readiness_detail( array $readiness ): string {
			if ( empty( $readiness ) ) {
				return __( 'Use Run readiness test to execute the liveness and signed-read checks.', 'npcink-cloud-addon' );
			}

			$owner = sanitize_key( (string) ( $readiness['owner_label'] ?? 'cloud_addon' ) );
			$next_action = sanitize_key( (string) ( $readiness['next_safe_action'] ?? $readiness['next_action'] ?? 'retry_test' ) );
			$blocked = sanitize_text_field( (string) ( $readiness['blocked_reason'] ?? '' ) );

			if ( '' !== $blocked ) {
				return sprintf(
					/* translators: 1: owner label, 2: next action, 3: blocked reason. */
					__( 'Owner: %1$s. Next safe action: %2$s. Blocked reason: %3$s', 'npcink-cloud-addon' ),
					$owner,
					$next_action,
					$blocked
				);
			}

			return sprintf(
				/* translators: 1: owner label, 2: next action. */
				__( 'Owner: %1$s. Next safe action: %2$s.', 'npcink-cloud-addon' ),
				$owner,
				$next_action
			);
		}

		/**
		 * Formats copyable non-secret readiness support facts.
		 *
		 * @param array<string,mixed> $readiness Readiness result.
		 * @return string
		 */
		private static function format_readiness_support_facts( array $readiness ): string {
			if ( empty( $readiness ) ) {
				return __( 'No manual readiness result has been captured for this admin session.', 'npcink-cloud-addon' );
			}

			$facts = is_array( $readiness['copyable_support_facts'] ?? null ) ? $readiness['copyable_support_facts'] : array();
			$parts = array();
			foreach ( $facts as $key => $value ) {
				if ( ! is_scalar( $value ) ) {
					continue;
				}
				$parts[] = sanitize_key( (string) $key ) . '=' . sanitize_text_field( (string) $value );
			}

			return self::format_empty( implode( '; ', $parts ) );
		}

		/**
		 * Formats hosted runtime diagnostic detail.
		 *
		 * @param array<string,mixed> $runtime Pro Cloud Runtime summary.
		 * @return string
		 */
		private static function format_hosted_runtime_diagnostic_detail( array $runtime ): string {
			$feature = sanitize_key( (string) ( $runtime['feature_id'] ?? '' ) );
			if ( '' === $feature ) {
				return __( 'Cloud entitlement did not return hosted runtime detail. Re-verify or open Cloud status detail.', 'npcink-cloud-addon' );
			}

			return sprintf(
				/* translators: 1: feature id, 2: remaining runs. */
				__( '%1$s, %2$d remaining runtime runs.', 'npcink-cloud-addon' ),
				$feature,
				absint( $runtime['remaining_nightly_inspection_runs'] ?? 0 )
			);
		}

		/**
		 * Renders Cloud-owned runtime run detail and recovery entry.
		 *
		 * @param array<string,mixed> $settings Stored settings.
		 * @param array<string,mixed> $state Credential state.
		 * @param array<string,mixed> $entitlement Entitlement summary.
		 * @param bool                $is_verified Whether the connector has verified credentials.
		 * @return void
		 */
		private static function render_runtime_runs( array $settings, array $state, array $entitlement, bool $is_verified ): void {
			if ( ! $is_verified ) {
				self::render_cloud_authorization_panel( $settings, $state );
				return;
			}

			$runtime = ! empty( $entitlement['available'] ) && is_array( $entitlement['pro_cloud_runtime'] ?? null ) ? $entitlement['pro_cloud_runtime'] : array();
			$run_detail_url = untrailingslashit( Npcink_Cloud_Addon_Settings::get_effective_base_url( $settings ) ) . '/portal/runs?feature=nightly_site_inspection';
			?>
			<div class="npcink-cloud-section-heading">
				<h3><?php esc_html_e( 'Cloud runtime runs', 'npcink-cloud-addon' ); ?></h3>
				<div class="npcink-cloud-summary__actions">
					<?php if ( ! empty( $runtime['feature_id'] ) ) : ?>
						<a class="button button-secondary" href="<?php echo esc_url( self::runtime_tab_url( 'recent', '' ) ); ?>"><?php esc_html_e( 'Load recent runs', 'npcink-cloud-addon' ); ?></a>
					<?php endif; ?>
					<a class="button button-secondary" href="<?php echo esc_url( $run_detail_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open Cloud run detail', 'npcink-cloud-addon' ); ?></a>
				</div>
			</div>
			<?php
			if ( empty( $runtime['feature_id'] ) ) {
				?>
				<p class="npcink-cloud-empty"><?php esc_html_e( 'Cloud did not return Runtime Runs entitlement for this site yet.', 'npcink-cloud-addon' ); ?></p>
				<p class="description"><?php esc_html_e( 'Run status, result reads, and retry controls appear after Cloud reports the runtime entitlement.', 'npcink-cloud-addon' ); ?></p>
				<?php
				return;
			}

			$view = self::runtime_view_from_request();
			$run_id = self::runtime_run_id_from_request();
			$client = new Npcink_Cloud_Runtime_Client( $settings );
			?>
			<p class="description"><?php esc_html_e( 'Cloud-owned Nightly Inspection run status, result reads, and bounded retry requests. This troubleshooting section creates no local queue, scheduler, proposal, approval record, or WordPress write.', 'npcink-cloud-addon' ); ?></p>
			<?php self::render_pro_cloud_runtime_summary( $entitlement ); ?>

			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="max-width: 860px; margin: 16px 0;">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
				<input type="hidden" name="tab" value="diagnostics" />
				<label for="npcink-cloud-runtime-run-id"><strong><?php esc_html_e( 'Cloud run ID', 'npcink-cloud-addon' ); ?></strong></label>
				<input id="npcink-cloud-runtime-run-id" class="regular-text code" type="text" name="runtime_run_id" value="<?php echo esc_attr( $run_id ); ?>" placeholder="run_..." />
				<button class="button" type="submit" name="runtime_view" value="status"><?php esc_html_e( 'Read status', 'npcink-cloud-addon' ); ?></button>
				<button class="button" type="submit" name="runtime_view" value="result"><?php esc_html_e( 'Read result', 'npcink-cloud-addon' ); ?></button>
				<p class="description"><?php esc_html_e( 'Status and result reads are signed Cloud reads. Result detail stays review-only and does not create a Core proposal.', 'npcink-cloud-addon' ); ?></p>
			</form>

			<?php
			if ( 'recent' === $view ) {
				self::render_runtime_recent_runs( $client );
			}
			if ( in_array( $view, array( 'status', 'result' ), true ) && '' !== $run_id ) {
				self::render_runtime_run_detail( $client, $run_id, $view );
			}
		}

		/**
		 * Renders recent Nightly Inspection runs from Cloud.
		 *
		 * @param Npcink_Cloud_Runtime_Client $client Runtime client.
		 * @return void
		 */
		private static function render_runtime_recent_runs( Npcink_Cloud_Runtime_Client $client ): void {
			$response = $client->get_recent_nightly_inspection_runs( 5, 'trace_cloud_addon_runtime_recent_' . wp_generate_uuid4() );
			if ( is_wp_error( $response ) ) {
				?>
				<p class="npcink-cloud-empty"><?php echo esc_html( $response->get_error_message() ); ?></p>
				<?php
				return;
			}

			$runs = self::runtime_runs_from_response( is_array( $response ) ? $response : array() );
			?>
			<h3><?php esc_html_e( 'Recent runs', 'npcink-cloud-addon' ); ?></h3>
			<?php if ( empty( $runs ) ) : ?>
				<p class="npcink-cloud-empty"><?php esc_html_e( 'Cloud returned no recent Nightly Inspection runs for this site.', 'npcink-cloud-addon' ); ?></p>
				<?php return; ?>
			<?php endif; ?>
			<table class="widefat striped" style="max-width: 980px;">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Run', 'npcink-cloud-addon' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'npcink-cloud-addon' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Result', 'npcink-cloud-addon' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Updated', 'npcink-cloud-addon' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Action', 'npcink-cloud-addon' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( array_slice( $runs, 0, 5 ) as $run ) : ?>
						<?php $run_id = self::normalize_run_id( (string) ( $run['run_id'] ?? $run['id'] ?? '' ) ); ?>
						<tr>
							<th scope="row"><code><?php echo esc_html( self::format_empty( $run_id ) ); ?></code></th>
							<td><?php echo esc_html( self::format_empty( self::runtime_scalar( $run, array( 'status', 'state' ) ) ) ); ?></td>
							<td><?php echo esc_html( self::format_empty( self::runtime_scalar( $run, array( 'result_status', 'result' ) ) ) ); ?></td>
							<td><?php echo esc_html( self::format_datetime_value( self::runtime_scalar( $run, array( 'updated_at', 'created_at', 'finished_at' ) ) ) ); ?></td>
							<td>
								<?php if ( '' !== $run_id ) : ?>
									<a href="<?php echo esc_url( self::runtime_tab_url( 'status', $run_id ) ); ?>"><?php esc_html_e( 'Inspect', 'npcink-cloud-addon' ); ?></a>
								<?php else : ?>
									<?php echo esc_html( self::format_empty( '' ) ); ?>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php
		}

		/**
		 * Renders status or result detail for one Cloud run.
		 *
		 * @param Npcink_Cloud_Runtime_Client $client Runtime client.
		 * @param string                      $run_id Cloud run id.
		 * @param string                      $view Requested view.
		 * @return void
		 */
		private static function render_runtime_run_detail( Npcink_Cloud_Runtime_Client $client, string $run_id, string $view ): void {
			$response = 'result' === $view
				? $client->get_run_result( $run_id, 'trace_cloud_addon_runtime_result_' . wp_generate_uuid4() )
				: $client->get_run( $run_id, 'trace_cloud_addon_runtime_status_' . wp_generate_uuid4() );
			?>
			<h3><?php echo esc_html( 'result' === $view ? __( 'Run result', 'npcink-cloud-addon' ) : __( 'Run status', 'npcink-cloud-addon' ) ); ?></h3>
			<?php
			if ( is_wp_error( $response ) ) {
				?>
				<p class="npcink-cloud-empty"><?php echo esc_html( $response->get_error_message() ); ?></p>
				<?php
				return;
			}

			$payload = is_array( $response ) ? $response : array();
			?>
			<table class="widefat striped" style="max-width: 980px;">
				<tbody>
					<?php self::render_diagnostic_row( __( 'Run ID', 'npcink-cloud-addon' ), self::runtime_pick( $payload, array( 'data.run_id', 'run.run_id', 'run_id' ) ), __( 'Cloud run identifier.', 'npcink-cloud-addon' ) ); ?>
					<?php self::render_diagnostic_row( __( 'Run status', 'npcink-cloud-addon' ), self::runtime_pick( $payload, array( 'data.status', 'run.status', 'status' ) ), __( 'Cloud-owned run state.', 'npcink-cloud-addon' ) ); ?>
					<?php self::render_diagnostic_row( __( 'Result status', 'npcink-cloud-addon' ), self::runtime_pick( $payload, array( 'data.result_status', 'result.status', 'result_status' ) ), __( 'Result availability from Cloud.', 'npcink-cloud-addon' ) ); ?>
					<?php self::render_diagnostic_row( __( 'Worker phase', 'npcink-cloud-addon' ), self::runtime_pick( $payload, array( 'data.run_lifecycle.phase', 'data.cloud_run.run_lifecycle.phase', 'cloud_run.run_lifecycle.phase' ) ), __( 'Queue and worker detail remain Cloud-owned.', 'npcink-cloud-addon' ) ); ?>
					<?php self::render_diagnostic_row( __( 'Retryable', 'npcink-cloud-addon' ), self::runtime_pick( $payload, array( 'data.retry_guidance.retryable', 'retry_guidance.retryable', 'data.retryable', 'retryable' ) ), __( 'Retry is a Cloud runtime request, not a local queue.', 'npcink-cloud-addon' ) ); ?>
					<?php self::render_diagnostic_row( __( 'Error code', 'npcink-cloud-addon' ), self::runtime_pick( $payload, array( 'data.error_code', 'error_code', 'data.run_lifecycle.error_code' ) ), __( 'Cloud error classification, if present.', 'npcink-cloud-addon' ) ); ?>
					<?php self::render_diagnostic_row( __( 'Started', 'npcink-cloud-addon' ), self::format_datetime_value( self::runtime_pick( $payload, array( 'data.run_lifecycle.processing_started_at', 'data.started_at', 'started_at' ) ) ), __( 'Displayed in the WordPress site timezone.', 'npcink-cloud-addon' ) ); ?>
					<?php self::render_diagnostic_row( __( 'Finished', 'npcink-cloud-addon' ), self::format_datetime_value( self::runtime_pick( $payload, array( 'data.run_lifecycle.processing_finished_at', 'data.completed_at', 'completed_at' ) ) ), __( 'Displayed in the WordPress site timezone.', 'npcink-cloud-addon' ) ); ?>
				</tbody>
			</table>
			<div class="npcink-cloud-summary__actions npcink-cloud-summary__actions--start npcink-cloud-run-detail-actions">
				<a class="button button-secondary" href="<?php echo esc_url( self::runtime_tab_url( 'result', $run_id ) ); ?>"><?php esc_html_e( 'Read result', 'npcink-cloud-addon' ); ?></a>
				<?php self::render_runtime_retry_form( $run_id ); ?>
			</div>
			<p class="description"><?php esc_html_e( 'Retry requests are sent to Cloud for a known run. This addon does not reconstruct a Toolbox snapshot, create local jobs, or create Core proposals.', 'npcink-cloud-addon' ); ?></p>
			<?php
		}

		/**
		 * Renders a nonce-protected Cloud retry request form.
		 *
		 * @param string $run_id Cloud run id.
		 * @return void
		 */
		private static function render_runtime_retry_form( string $run_id ): void {
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( self::ACTION_RETRY_RUNTIME_RUN ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_RETRY_RUNTIME_RUN ); ?>" />
				<input type="hidden" name="runtime_run_id" value="<?php echo esc_attr( $run_id ); ?>" />
				<button type="submit" class="button button-secondary"><?php esc_html_e( 'Request Cloud retry', 'npcink-cloud-addon' ); ?></button>
			</form>
			<?php
		}

		/**
		 * Renders the default Cloud authorization entry.
		 *
		 * @param array<string,mixed> $settings Stored settings.
		 * @param array<string,mixed> $state Credential state.
		 * @return void
		 */
		private static function render_cloud_authorization_panel( array $settings, array $state ): void {
			$base_url = Npcink_Cloud_Addon_Settings::get_effective_base_url( $settings );
			?>
			<div class="npcink-cloud-connect-context" aria-label="<?php esc_attr_e( 'Connection context', 'npcink-cloud-addon' ); ?>">
				<div class="npcink-cloud-connect-context__item">
					<span class="npcink-cloud-connect-context__label"><?php esc_html_e( 'Connection', 'npcink-cloud-addon' ); ?></span>
					<strong class="npcink-cloud-connect-context__value"><?php echo esc_html( (string) ( $state['label'] ?? '' ) ); ?></strong>
				</div>
				<div class="npcink-cloud-connect-context__item">
					<span class="npcink-cloud-connect-context__label"><?php esc_html_e( 'Cloud', 'npcink-cloud-addon' ); ?></span>
					<code class="npcink-cloud-connect-context__value"><?php echo esc_html( $base_url ); ?></code>
				</div>
				<div class="npcink-cloud-connect-context__item">
					<span class="npcink-cloud-connect-context__label"><?php esc_html_e( 'Current site', 'npcink-cloud-addon' ); ?></span>
					<span class="npcink-cloud-connect-context__value"><?php echo esc_html( home_url( '/' ) ); ?></span>
				</div>
			</div>
			<div class="npcink-cloud-connect-actions">
				<a class="button button-primary button-hero" href="<?php echo esc_url( self::build_authorization_url( $settings ) ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Add this site in Npcink Cloud', 'npcink-cloud-addon' ); ?>
				</a>
				<p class="description"><?php esc_html_e( 'Cloud will create or activate this site connection and return here with a one-time authorization code.', 'npcink-cloud-addon' ); ?></p>
			</div>
			<details class="npcink-cloud-endpoint-advanced">
				<summary>
					<span><?php esc_html_e( 'Advanced connection', 'npcink-cloud-addon' ); ?></span>
					<small><?php esc_html_e( 'Self-hosted Cloud endpoint', 'npcink-cloud-addon' ); ?></small>
				</summary>
				<div class="npcink-cloud-endpoint-advanced__body">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( self::ACTION_START_CUSTOM_AUTH ); ?>
						<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_START_CUSTOM_AUTH ); ?>" />
						<label for="npcink-cloud-self-hosted-base-url"><?php esc_html_e( 'Cloud Base URL', 'npcink-cloud-addon' ); ?></label>
						<div class="npcink-cloud-endpoint-advanced__controls">
							<input
								type="url"
								class="regular-text code"
								id="npcink-cloud-self-hosted-base-url"
								name="self_hosted_base_url"
								value="<?php echo esc_attr( $base_url ); ?>"
								placeholder="<?php echo esc_attr( Npcink_Cloud_Addon_Settings::get_default_base_url() ); ?>"
								required
							/>
							<button type="submit" class="button button-secondary" formtarget="_blank"><?php esc_html_e( 'Authorize with this endpoint', 'npcink-cloud-addon' ); ?></button>
						</div>
					</form>
					<p class="description"><?php esc_html_e( 'For compatible Npcink Cloud deployments only. Cloud still owns site activation and key issuance.', 'npcink-cloud-addon' ); ?></p>
					<p class="description"><?php esc_html_e( 'This does not manage Cloud sites, keys, billing, models, router, workflows, or runtime policy.', 'npcink-cloud-addon' ); ?></p>
				</div>
			</details>
			<?php if ( '' !== (string) ( $state['last_verification_error'] ?? '' ) ) : ?>
				<p class="npcink-cloud-empty"><?php echo esc_html( (string) $state['last_verification_error'] ); ?></p>
			<?php endif; ?>
			<?php
		}

		/**
		 * Renders Site Knowledge connector status and manual refresh transport.
		 *
		 * @param array<string,mixed> $site_knowledge Site Knowledge bridge status.
		 * @param array<string,mixed> $settings Stored settings.
		 * @param bool                $is_verified Whether the connector has verified credentials.
		 * @return void
		 */
		private static function render_site_knowledge_summary( array $site_knowledge, array $settings, bool $is_verified ): void {
			if ( ! $is_verified ) {
				?>
				<p class="npcink-cloud-empty"><?php esc_html_e( 'Site Knowledge delivery starts after the connector verifies successfully.', 'npcink-cloud-addon' ); ?></p>
				<?php
				return;
			}

			$base_url = untrailingslashit( Npcink_Cloud_Addon_Settings::get_effective_base_url( $settings ) );
			$delivery_enabled = ! empty( $site_knowledge['delivery_enabled'] );
			$generation_reference_enabled = ! empty( $settings['site_knowledge_generation_reference_enabled'] );
			$active_view = self::site_knowledge_view_from_request();
			?>
					<div class="npcink-cloud-site-knowledge-consent npcink-cloud-site-knowledge-consent--readonly">
						<div class="npcink-cloud-site-knowledge-consent__control" aria-describedby="npcink-cloud-site-knowledge-delivery-summary npcink-cloud-site-knowledge-delivery-status">
							<span class="npcink-cloud-site-knowledge-consent__copy">
								<span class="npcink-cloud-site-knowledge-consent__title"><?php esc_html_e( 'Site Knowledge delivery', 'npcink-cloud-addon' ); ?></span>
								<span id="npcink-cloud-site-knowledge-delivery-summary" class="npcink-cloud-site-knowledge-consent__description"><?php esc_html_e( 'Allow public content-change delivery and explicit administrator delivery intent. WordPress content is not changed.', 'npcink-cloud-addon' ); ?></span>
							</span>
						</div>
						<details class="npcink-cloud-inline-info npcink-cloud-site-knowledge-consent__info">
							<summary aria-label="<?php esc_attr_e( 'Site Knowledge delivery details', 'npcink-cloud-addon' ); ?>">
								<span aria-hidden="true">i</span>
							</summary>
							<div class="npcink-cloud-inline-info__content">
								<p><?php esc_html_e( 'Cloud owns indexing, rebuild, deletion, freshness policy, and diagnostics.', 'npcink-cloud-addon' ); ?></p>
								<p><?php esc_html_e( 'Turning delivery off does not delete existing Cloud index data.', 'npcink-cloud-addon' ); ?></p>
								<p><?php esc_html_e( 'Routine refresh, start, and rebuild controls are hidden or disabled while delivery is off.', 'npcink-cloud-addon' ); ?></p>
								<p><?php esc_html_e( 'Cloud index cleanup remains a separate explicit action.', 'npcink-cloud-addon' ); ?></p>
							</div>
						</details>
						<span id="npcink-cloud-site-knowledge-delivery-status" class="npcink-cloud-site-knowledge-consent__state">
							<?php echo $delivery_enabled ? esc_html__( 'enabled', 'npcink-cloud-addon' ) : esc_html__( 'disabled', 'npcink-cloud-addon' ); ?>
						</span>
						<a class="button button-secondary" href="<?php echo esc_url( self::tab_url( 'permissions' ) ); ?>"><?php esc_html_e( 'Change in Local permissions', 'npcink-cloud-addon' ); ?></a>
					</div>
					<?php
					self::render_secondary_tab_navigation(
						$active_view,
						array(
							'overview' => __( 'Overview', 'npcink-cloud-addon' ),
							'index'    => __( 'Index operations', 'npcink-cloud-addon' ),
						),
						'site_knowledge',
						__( 'Site Knowledge sections', 'npcink-cloud-addon' )
					);
					if ( 'index' === $active_view ) {
						self::render_site_knowledge_index_operations( $delivery_enabled );
						return;
					}
					?>
					<div class="npcink-cloud-section-heading">
						<h3><?php esc_html_e( 'Overview', 'npcink-cloud-addon' ); ?></h3>
						<div class="npcink-cloud-summary__actions">
							<?php if ( $delivery_enabled ) : ?>
								<form class="npcink-cloud-verify-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<?php wp_nonce_field( self::ACTION_REFRESH_SITE_KNOWLEDGE ); ?>
									<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_REFRESH_SITE_KNOWLEDGE ); ?>" />
									<button type="submit" class="button button-primary"><?php esc_html_e( 'Request public content refresh', 'npcink-cloud-addon' ); ?></button>
								</form>
							<?php endif; ?>
							<a class="button button-secondary" href="<?php echo esc_url( $base_url . '/portal/site-knowledge' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open Cloud Site Knowledge', 'npcink-cloud-addon' ); ?></a>
						</div>
					</div>
					<?php if ( ! $delivery_enabled ) : ?>
						<p class="description npcink-cloud-site-knowledge-disabled-note"><?php esc_html_e( 'Delivery is off; refresh controls and routine delivery rows are hidden.', 'npcink-cloud-addon' ); ?></p>
						<?php self::render_site_knowledge_bridge_health_detail( $site_knowledge ); ?>
						<?php
						return;
					endif;
					?>
					<table class="widefat striped npcink-cloud-site-knowledge-status">
						<tbody>
							<tr>
								<th scope="row"><?php esc_html_e( 'Delivery', 'npcink-cloud-addon' ); ?></th>
								<td><?php echo $delivery_enabled ? esc_html__( 'enabled', 'npcink-cloud-addon' ) : esc_html__( 'disabled locally', 'npcink-cloud-addon' ); ?></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'AI generation reference', 'npcink-cloud-addon' ); ?></th>
								<td><?php echo $generation_reference_enabled ? esc_html__( 'enabled for supported editor tasks', 'npcink-cloud-addon' ) : esc_html__( 'disabled', 'npcink-cloud-addon' ); ?></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Last index action', 'npcink-cloud-addon' ); ?></th>
								<td><?php echo esc_html( self::format_empty( (string) ( $site_knowledge['last_index_action'] ?? '' ) ) ); ?></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Connector state', 'npcink-cloud-addon' ); ?></th>
								<td><?php echo esc_html( self::format_site_knowledge_overview( $site_knowledge ) ); ?></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Buffered public changes', 'npcink-cloud-addon' ); ?></th>
								<td><?php echo esc_html( (string) absint( $site_knowledge['buffer_count'] ?? 0 ) ); ?></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Last delivery', 'npcink-cloud-addon' ); ?></th>
								<td><?php echo esc_html( self::format_datetime_value( (string) ( $site_knowledge['last_delivery_at'] ?? '' ) ) ); ?></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Last sent', 'npcink-cloud-addon' ); ?></th>
								<td><?php echo esc_html( (string) absint( $site_knowledge['last_sent_count'] ?? 0 ) ); ?></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Last error', 'npcink-cloud-addon' ); ?></th>
								<td><?php self::render_site_knowledge_error_cell( (string) ( $site_knowledge['last_delivery_error'] ?? '' ) ); ?></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Next flush', 'npcink-cloud-addon' ); ?></th>
								<td><?php echo esc_html( self::format_datetime_value( (string) ( $site_knowledge['next_flush_at'] ?? '' ) ) ); ?></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Boundary', 'npcink-cloud-addon' ); ?></th>
								<td>
									<details class="npcink-cloud-inline-note">
										<summary>
											<span aria-hidden="true" class="npcink-cloud-inline-note__icon">!</span>
											<?php esc_html_e( 'Transport only; Cloud owns indexing detail.', 'npcink-cloud-addon' ); ?>
										</summary>
										<p><?php esc_html_e( 'This addon sends public change hints and explicit administrator delivery intents through signed runtime requests. Cloud owns index execution, rebuild/delete handling, freshness policy, collection lifecycle, and diagnostics detail.', 'npcink-cloud-addon' ); ?></p>
									</details>
								</td>
							</tr>
						</tbody>
					</table>
					<?php self::render_site_knowledge_bridge_health_detail( $site_knowledge ); ?>
			<p class="description"><?php esc_html_e( 'Toolbox uses Site Knowledge results in best-practice buttons. Provider settings, collection lifecycle, and deep troubleshooting remain Cloud-owned.', 'npcink-cloud-addon' ); ?></p>
			<?php
		}

		/**
		 * Renders read-only Site Knowledge bridge health detail.
		 *
		 * @param array<string,mixed> $site_knowledge Site Knowledge bridge status.
		 * @return void
		 */
		private static function render_site_knowledge_bridge_health_detail( array $site_knowledge ): void {
			$wp_cron_disabled = ! empty( $site_knowledge['wp_cron_disabled'] );
			$health_contract  = (string) ( $site_knowledge['health_detail_version'] ?? 'site_knowledge_bridge_health.v1' );
			?>
			<h4><?php esc_html_e( 'Bridge health detail', 'npcink-cloud-addon' ); ?></h4>
			<table class="widefat striped npcink-cloud-site-knowledge-status npcink-cloud-site-knowledge-health-detail">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Health contract', 'npcink-cloud-addon' ); ?></th>
						<td><code><?php echo esc_html( self::format_empty( $health_contract ) ); ?></code></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Last success', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_datetime_value( (string) ( $site_knowledge['last_success_at'] ?? '' ) ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Last error code', 'npcink-cloud-addon' ); ?></th>
						<td><code><?php echo esc_html( self::format_empty( (string) ( $site_knowledge['last_error_code'] ?? '' ) ) ); ?></code></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Last error time', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_datetime_value( (string) ( $site_knowledge['last_error_at'] ?? '' ) ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Delivery attempts', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( sprintf( '%1$d / %2$d', absint( $site_knowledge['delivery_attempts'] ?? 0 ), absint( $site_knowledge['max_delivery_attempts'] ?? 0 ) ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Batch limit', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( sprintf( '%1$d / %2$d', absint( $site_knowledge['batch_size'] ?? 0 ), absint( $site_knowledge['max_buffer_items'] ?? 0 ) ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Total sent', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( (string) absint( $site_knowledge['total_sent'] ?? 0 ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Last index action time', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_datetime_value( (string) ( $site_knowledge['last_index_action_at'] ?? '' ) ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Last index action sent', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( (string) absint( $site_knowledge['last_index_action_sent_count'] ?? 0 ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Next reconcile', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_datetime_value( (string) ( $site_knowledge['next_reconcile_at'] ?? '' ) ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'WP-Cron disabled', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo $wp_cron_disabled ? esc_html__( 'yes', 'npcink-cloud-addon' ) : esc_html__( 'no', 'npcink-cloud-addon' ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Manual flush command', 'npcink-cloud-addon' ); ?></th>
						<td><code><?php echo esc_html( self::format_empty( (string) ( $site_knowledge['wp_cli_command'] ?? $site_knowledge['cron_command'] ?? '' ) ) ); ?></code></td>
					</tr>
				</tbody>
			</table>
			<?php self::render_site_knowledge_boundary_truth_detail( $site_knowledge ); ?>
			<p class="description"><?php esc_html_e( 'This detail is local connector health only; Cloud remains the owner of indexing, freshness policy, collection lifecycle, and diagnostics.', 'npcink-cloud-addon' ); ?></p>
			<?php
		}

		/**
		 * Renders Site Knowledge owner/truth detail without adding local controls.
		 *
		 * @param array<string,mixed> $site_knowledge Site Knowledge bridge status.
		 * @return void
		 */
		private static function render_site_knowledge_boundary_truth_detail( array $site_knowledge ): void {
			$ownership = is_array( $site_knowledge['ownership'] ?? null ) ? $site_knowledge['ownership'] : array();
			$truth_boundaries = is_array( $site_knowledge['truth_boundaries'] ?? null ) ? $site_knowledge['truth_boundaries'] : array();
			if ( array() === $ownership && array() === $truth_boundaries ) {
				return;
			}

			?>
			<h4><?php esc_html_e( 'Cloud boundary truth', 'npcink-cloud-addon' ); ?></h4>
			<table class="widefat striped npcink-cloud-site-knowledge-status npcink-cloud-site-knowledge-boundary-detail">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Source content owner', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_site_knowledge_owner_label( (string) ( $ownership['source_content_owner'] ?? '' ) ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Delivery bridge owner', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_site_knowledge_owner_label( (string) ( $ownership['delivery_bridge_owner'] ?? '' ) ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Index and freshness owner', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_site_knowledge_owner_label( (string) ( $ownership['index_execution_owner'] ?? $ownership['freshness_policy_owner'] ?? '' ) ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Vector and embedding owner', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_site_knowledge_owner_label( (string) ( $ownership['vector_storage_owner'] ?? $ownership['embedding_execution_owner'] ?? '' ) ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Approval and final write owner', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_site_knowledge_owner_label( (string) ( $ownership['final_write_owner'] ?? $ownership['approval_owner'] ?? '' ) ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Cloud WordPress control plane', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_site_knowledge_bool_label( (bool) ( $truth_boundaries['cloud_is_wordpress_control_plane'] ?? false ) ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Cloud creates WordPress writes', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_site_knowledge_bool_label( (bool) ( $truth_boundaries['cloud_creates_wordpress_writes'] ?? false ) ) ); ?></td>
					</tr>
				</tbody>
			</table>
			<?php
		}

		/**
		 * Formats a Site Knowledge owner token for admin display.
		 *
		 * @param string $owner Owner token.
		 * @return string
		 */
		private static function format_site_knowledge_owner_label( string $owner ): string {
			$labels = array(
				'cloud_addon' => __( 'Cloud Addon', 'npcink-cloud-addon' ),
				'cloud_service' => __( 'Cloud service', 'npcink-cloud-addon' ),
				'local_wordpress_host' => __( 'Local WordPress host', 'npcink-cloud-addon' ),
			);
			$owner = sanitize_key( $owner );

			return $labels[ $owner ] ?? self::format_empty( $owner );
		}

		/**
		 * Formats a Site Knowledge boolean boundary value.
		 *
		 * @param bool $value Boundary value.
		 * @return string
		 */
		private static function format_site_knowledge_bool_label( bool $value ): string {
			return $value ? __( 'yes', 'npcink-cloud-addon' ) : __( 'no', 'npcink-cloud-addon' );
		}

		/**
		 * Renders administrator Site Knowledge index operations.
		 *
		 * @param bool $delivery_enabled Whether local delivery is enabled.
		 * @return void
		 */
		private static function render_site_knowledge_index_operations( bool $delivery_enabled ): void {
			?>
			<section class="npcink-cloud-site-knowledge-index-panel" aria-labelledby="npcink-cloud-site-knowledge-index-title">
				<h3 id="npcink-cloud-site-knowledge-index-title"><?php esc_html_e( 'Index operations', 'npcink-cloud-addon' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Use only for initial indexing, rebuilds, or explicit Cloud index cleanup.', 'npcink-cloud-addon' ); ?></p>
				<?php if ( ! $delivery_enabled ) : ?>
					<p class="description npcink-cloud-site-knowledge-disabled-note"><?php esc_html_e( 'Site Knowledge delivery is disabled locally. Enable delivery before starting or rebuilding the index.', 'npcink-cloud-addon' ); ?></p>
				<?php endif; ?>
				<div class="npcink-cloud-index-actions">
					<details class="npcink-cloud-inline-note npcink-cloud-index-actions__note">
						<summary>
							<span aria-hidden="true" class="npcink-cloud-inline-note__icon">!</span>
							<?php esc_html_e( 'These actions send intent only; WordPress content is not changed.', 'npcink-cloud-addon' ); ?>
						</summary>
						<p><?php esc_html_e( 'These actions send local administrator delivery intent and bounded public WordPress content for Cloud-owned Site Knowledge operations. Cloud performs indexing, rebuild, deletion, and diagnostics; WordPress content is not changed.', 'npcink-cloud-addon' ); ?></p>
					</details>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( self::ACTION_MANAGE_SITE_KNOWLEDGE_INDEX ); ?>
						<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_MANAGE_SITE_KNOWLEDGE_INDEX ); ?>" />
						<input type="hidden" name="site_knowledge_index_action" value="start" />
						<p><strong><?php esc_html_e( 'Start indexing', 'npcink-cloud-addon' ); ?></strong></p>
						<div class="npcink-cloud-index-action__controls">
							<button type="submit" class="button button-secondary" <?php disabled( ! $delivery_enabled ); ?>><?php esc_html_e( 'Start indexing', 'npcink-cloud-addon' ); ?></button>
						</div>
						<p class="description"><?php esc_html_e( 'Send a public post and page manifest.', 'npcink-cloud-addon' ); ?></p>
					</form>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( self::ACTION_MANAGE_SITE_KNOWLEDGE_INDEX ); ?>
						<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_MANAGE_SITE_KNOWLEDGE_INDEX ); ?>" />
						<input type="hidden" name="site_knowledge_index_action" value="rebuild" />
						<p><strong><?php esc_html_e( 'Rebuild index', 'npcink-cloud-addon' ); ?></strong></p>
						<div class="npcink-cloud-index-action__controls">
							<input type="text" name="site_knowledge_confirmation" placeholder="<?php esc_attr_e( 'Type REBUILD', 'npcink-cloud-addon' ); ?>" />
							<button type="submit" class="button button-secondary" <?php disabled( ! $delivery_enabled ); ?>><?php esc_html_e( 'Rebuild index', 'npcink-cloud-addon' ); ?></button>
						</div>
						<p class="description"><?php esc_html_e( 'Ask Cloud to clear and rebuild the site index.', 'npcink-cloud-addon' ); ?></p>
					</form>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( self::ACTION_MANAGE_SITE_KNOWLEDGE_INDEX ); ?>
						<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_MANAGE_SITE_KNOWLEDGE_INDEX ); ?>" />
						<input type="hidden" name="site_knowledge_index_action" value="delete" />
						<p><strong><?php esc_html_e( 'Delete site index', 'npcink-cloud-addon' ); ?></strong></p>
						<div class="npcink-cloud-index-action__controls">
							<input type="text" name="site_knowledge_confirmation" placeholder="<?php esc_attr_e( 'Type DELETE', 'npcink-cloud-addon' ); ?>" />
							<button type="submit" class="button button-secondary npcink-cloud-button-danger"><?php esc_html_e( 'Delete site index', 'npcink-cloud-addon' ); ?></button>
						</div>
						<p class="description"><?php esc_html_e( 'Ask Cloud to delete the site index only.', 'npcink-cloud-addon' ); ?></p>
					</form>
				</div>
			</section>
			<?php
		}

		/**
		 * Renders read-only account and usage projections for the Status tab.
		 *
		 * @param array<string,mixed> $entitlement Entitlement summary.
		 * @param bool                $is_verified Whether the connector has verified credentials.
		 * @return void
		 */
		private static function render_status_account_usage( array $entitlement, bool $is_verified ): void {
			$credit_detail = is_array( $entitlement['credit_usage_detail'] ?? null ) ? $entitlement['credit_usage_detail'] : array();
			$links = is_array( $entitlement['links'] ?? null ) ? $entitlement['links'] : array();
			$has_entitlement_detail = self::has_entitlement_detail( $entitlement );
			$has_credit_detail = ! empty( $credit_detail['available'] )
				|| '' !== (string) ( $links['credit_ledger_url'] ?? '' )
				|| '' !== (string) ( $links['usage_url'] ?? '' );

			if ( ! $has_entitlement_detail && ! $has_credit_detail ) {
				?>
				<p class="npcink-cloud-empty"><?php esc_html_e( 'Cloud account and usage projections are not available yet. Re-verify the connection or open Cloud for service detail.', 'npcink-cloud-addon' ); ?></p>
				<?php
				return;
			}

			if ( $has_entitlement_detail ) {
				?>
				<h3><?php esc_html_e( 'Entitlement Summary', 'npcink-cloud-addon' ); ?></h3>
				<?php
				self::render_entitlement_summary( $entitlement, $is_verified );
			}

			if ( $has_credit_detail ) {
				self::render_credit_usage_summary( $entitlement );
			}
		}

		/**
		 * Renders read-only monitoring and quality projections for the Status tab.
		 *
		 * @param array<string,mixed> $monitoring Monitoring status.
		 * @return void
		 */
		private static function render_status_monitoring_quality( array $monitoring ): void {
			if ( ! self::has_monitoring_detail( $monitoring ) ) {
				?>
				<p class="npcink-cloud-empty"><?php esc_html_e( 'Monitoring and quality projections are not available yet.', 'npcink-cloud-addon' ); ?></p>
				<?php
				return;
			}

			?>
			<div class="npcink-cloud-section-heading">
				<h3><?php esc_html_e( 'Monitoring & Quality', 'npcink-cloud-addon' ); ?></h3>
				<?php if ( ! empty( $monitoring['enabled'] ) ) : ?>
					<form class="npcink-cloud-verify-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( self::ACTION_REFRESH_MONITORING ); ?>
						<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_REFRESH_MONITORING ); ?>" />
						<button type="submit" class="button button-secondary"><?php esc_html_e( 'Refresh monitoring and quality', 'npcink-cloud-addon' ); ?></button>
					</form>
				<?php endif; ?>
			</div>
			<?php
			self::render_monitoring_summary( $monitoring );
		}

		/**
		 * Renders monitoring diagnostics as a dedicated Status subview.
		 *
		 * @param array<string,mixed> $monitoring Monitoring status.
		 * @return void
		 */
		private static function render_status_monitoring_diagnostics( array $monitoring ): void {
			if ( ! self::has_monitoring_detail( $monitoring ) ) {
				?>
				<p class="npcink-cloud-empty"><?php esc_html_e( 'Monitoring diagnostics are not available yet.', 'npcink-cloud-addon' ); ?></p>
				<?php
				return;
			}

			?>
			<h3><?php esc_html_e( 'Monitoring diagnostics', 'npcink-cloud-addon' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Local buffers, Cloud aggregate metrics, plugin signals, and recent error codes.', 'npcink-cloud-addon' ); ?></p>
			<?php
			self::render_monitoring_advanced_diagnostics( $monitoring );
		}

		/**
		 * Checks whether local entitlement detail has useful content.
		 *
		 * @param array<string,mixed> $summary Entitlement summary.
		 * @return bool
		 */
		private static function has_entitlement_detail( array $summary ): bool {
			return ! empty( $summary['available'] )
				|| '' !== (string) ( $summary['message'] ?? '' )
				|| '' !== (string) ( $summary['state'] ?? '' )
				|| '' !== (string) ( $summary['entitlement_status'] ?? '' )
				|| '' !== (string) ( $summary['synced_at'] ?? '' );
		}

		/**
		 * Checks whether monitoring detail should be visible.
		 *
		 * @param array<string,mixed> $monitoring Monitoring status.
		 * @return bool
		 */
		private static function has_monitoring_detail( array $monitoring ): bool {
			if ( ! empty( $monitoring['enabled'] ) ) {
				return true;
			}

			$remote = is_array( $monitoring['remote_summary'] ?? null ) ? $monitoring['remote_summary'] : array();
			$summary = is_array( $remote['summary'] ?? null ) ? $remote['summary'] : array();
			$totals = is_array( $summary['totals'] ?? null ) ? $summary['totals'] : array();
			$agent_feedback = is_array( $monitoring['agent_feedback_summary'] ?? null ) ? $monitoring['agent_feedback_summary'] : array();
			$agent_summary = is_array( $agent_feedback['summary'] ?? null ) ? $agent_feedback['summary'] : array();

			return absint( $monitoring['buffer_count'] ?? 0 ) > 0
				|| '' !== (string) ( $monitoring['last_uploaded_at'] ?? '' )
				|| '' !== (string) ( $monitoring['last_upload_error'] ?? '' )
				|| '' !== (string) ( $monitoring['last_captured_at'] ?? '' )
				|| '' !== (string) ( $remote['last_refreshed_at'] ?? '' )
				|| '' !== (string) ( $remote['last_refresh_error'] ?? '' )
				|| '' !== (string) ( $agent_feedback['last_refreshed_at'] ?? '' )
				|| '' !== (string) ( $agent_feedback['last_refresh_error'] ?? '' )
				|| absint( $totals['events_total'] ?? 0 ) > 0
				|| absint( $totals['error_total'] ?? 0 ) > 0
				|| absint( $agent_summary['events_total'] ?? 0 ) > 0;
		}

		/**
		 * Renders monitoring status.
		 *
		 * @param array<string,mixed> $monitoring Monitoring status.
		 * @return void
		 */
		private static function render_monitoring_summary( array $monitoring ): void {
			$is_enabled = ! empty( $monitoring['enabled'] );
			if ( ! $is_enabled && ! self::has_monitoring_detail( $monitoring ) ) {
				?>
				<p class="npcink-cloud-empty"><?php esc_html_e( 'Monitoring collection is disabled and no local monitoring history is available.', 'npcink-cloud-addon' ); ?></p>
				<?php
				return;
			}

			$remote = is_array( $monitoring['remote_summary'] ?? null ) ? $monitoring['remote_summary'] : array();
			$summary = is_array( $remote['summary'] ?? null ) ? $remote['summary'] : array();
			$agent_feedback = is_array( $monitoring['agent_feedback_summary'] ?? null ) ? $monitoring['agent_feedback_summary'] : array();
			$agent_summary = is_array( $agent_feedback['summary'] ?? null ) ? $agent_feedback['summary'] : array();
			$totals = is_array( $summary['totals'] ?? null ) ? $summary['totals'] : array();
			?>
				<?php if ( ! $is_enabled ) : ?>
					<p class="description"><?php esc_html_e( 'Monitoring collection is disabled. Historical local and Cloud summaries are shown read-only.', 'npcink-cloud-addon' ); ?></p>
				<?php endif; ?>
				<table class="widefat striped" style="max-width: 860px;">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Collection', 'npcink-cloud-addon' ); ?></th>
							<td><?php echo ! empty( $monitoring['enabled'] ) ? esc_html__( 'enabled', 'npcink-cloud-addon' ) : esc_html__( 'disabled', 'npcink-cloud-addon' ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Buffered events', 'npcink-cloud-addon' ); ?></th>
							<td><?php echo esc_html( (string) absint( $monitoring['buffer_count'] ?? 0 ) ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Last upload status', 'npcink-cloud-addon' ); ?></th>
							<td>
								<?php
								$has_upload_state = '' !== (string) ( $monitoring['last_uploaded_at'] ?? '' )
									|| '' !== (string) ( $monitoring['last_upload_error'] ?? '' );
								if ( ! $has_upload_state ) {
									echo esc_html__( 'never', 'npcink-cloud-addon' );
								} else {
									echo ! empty( $monitoring['last_upload_ok'] ) ? esc_html__( 'ok', 'npcink-cloud-addon' ) : esc_html__( 'failed', 'npcink-cloud-addon' );
								}
								?>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Last uploaded', 'npcink-cloud-addon' ); ?></th>
							<td><?php echo esc_html( self::format_datetime_value( (string) ( $monitoring['last_uploaded_at'] ?? '' ) ) ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Last upload error', 'npcink-cloud-addon' ); ?></th>
							<td><?php echo esc_html( self::format_empty( (string) ( $monitoring['last_upload_error'] ?? '' ) ) ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Cloud events', 'npcink-cloud-addon' ); ?></th>
							<td><?php echo esc_html( (string) absint( $totals['events_total'] ?? 0 ) ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Cloud errors', 'npcink-cloud-addon' ); ?></th>
							<td><?php echo esc_html( (string) absint( $totals['error_total'] ?? 0 ) ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Agent quality events', 'npcink-cloud-addon' ); ?></th>
							<td><?php echo esc_html( (string) absint( $agent_summary['events_total'] ?? 0 ) ); ?></td>
						</tr>
					</tbody>
				</table>
				<?php
			}

		/**
		 * Renders detailed monitoring diagnostics.
		 *
		 * @param array<string,mixed> $monitoring Monitoring status.
		 * @return void
		 */
		private static function render_monitoring_advanced_diagnostics( array $monitoring ): void {
			$plugins = is_array( $monitoring['plugins'] ?? null ) ? $monitoring['plugins'] : array();
			$remote = is_array( $monitoring['remote_summary'] ?? null ) ? $monitoring['remote_summary'] : array();
			$summary = is_array( $remote['summary'] ?? null ) ? $remote['summary'] : array();
			$agent_feedback = is_array( $monitoring['agent_feedback_summary'] ?? null ) ? $monitoring['agent_feedback_summary'] : array();
			$agent_summary = is_array( $agent_feedback['summary'] ?? null ) ? $agent_feedback['summary'] : array();
			$totals = is_array( $summary['totals'] ?? null ) ? $summary['totals'] : array();
			$cloud_plugins = is_array( $summary['plugins'] ?? null ) ? $summary['plugins'] : array();
			$recent_errors = is_array( $summary['recent_errors'] ?? null ) ? $summary['recent_errors'] : array();
			?>
				<table class="widefat striped" style="max-width: 860px;">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Collection', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo ! empty( $monitoring['enabled'] ) ? esc_html__( 'enabled', 'npcink-cloud-addon' ) : esc_html__( 'disabled', 'npcink-cloud-addon' ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Buffered events', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( (string) absint( $monitoring['buffer_count'] ?? 0 ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Last captured', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_datetime_value( (string) ( $monitoring['last_captured_at'] ?? '' ) ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Sent events', 'npcink-cloud-addon' ); ?></th>
						<td>
							<?php
							printf(
								/* translators: 1: latest batch count, 2: total count. */
								esc_html__( 'last %1$d / total %2$d', 'npcink-cloud-addon' ),
								absint( $monitoring['last_sent_count'] ?? 0 ),
								absint( $monitoring['total_sent'] ?? ( $monitoring['total_uploaded'] ?? 0 ) )
							);
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Stored events', 'npcink-cloud-addon' ); ?></th>
						<td>
							<?php
							printf(
								/* translators: 1: latest batch count, 2: total count. */
								esc_html__( 'last %1$d / total %2$d', 'npcink-cloud-addon' ),
								absint( $monitoring['last_stored_count'] ?? 0 ),
								absint( $monitoring['total_stored'] ?? 0 )
							);
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Duplicate events', 'npcink-cloud-addon' ); ?></th>
						<td>
							<?php
							printf(
								/* translators: 1: latest batch count, 2: total count. */
								esc_html__( 'last %1$d / total %2$d', 'npcink-cloud-addon' ),
								absint( $monitoring['last_duplicate_count'] ?? 0 ),
								absint( $monitoring['total_duplicate'] ?? 0 )
							);
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Last upload status', 'npcink-cloud-addon' ); ?></th>
						<td>
							<?php
							$has_upload_state = '' !== (string) ( $monitoring['last_uploaded_at'] ?? '' )
								|| '' !== (string) ( $monitoring['last_upload_error'] ?? '' );
							if ( ! $has_upload_state ) {
								echo esc_html__( 'never', 'npcink-cloud-addon' );
							} else {
								$upload_status = ! empty( $monitoring['last_upload_ok'] )
									? __( 'ok', 'npcink-cloud-addon' )
									: __( 'failed', 'npcink-cloud-addon' );
								echo esc_html( $upload_status );
							}
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Last uploaded', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_datetime_value( (string) ( $monitoring['last_uploaded_at'] ?? '' ) ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Last upload error', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_empty( (string) ( $monitoring['last_upload_error'] ?? '' ) ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Last event', 'npcink-cloud-addon' ); ?></th>
						<td>
							<code><?php echo esc_html( self::format_empty( (string) ( $monitoring['last_event_kind'] ?? '' ) ) ); ?></code>
							<?php if ( '' !== (string) ( $monitoring['last_plugin_slug'] ?? '' ) ) : ?>
								<span><?php echo esc_html( ' ' . (string) $monitoring['last_plugin_slug'] ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				</tbody>
			</table>
			<table class="widefat striped" style="max-width: 860px; margin-top: 12px;">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Cloud window', 'npcink-cloud-addon' ); ?></th>
						<td>
							<?php
							$window = is_array( $summary['window'] ?? null ) ? $summary['window'] : array();
							printf(
								/* translators: %d: window hours. */
								esc_html__( '%d hours', 'npcink-cloud-addon' ),
								absint( $window['hours'] ?? 0 )
							);
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Cloud events', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( (string) absint( $totals['events_total'] ?? 0 ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Cloud errors', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( (string) absint( $totals['error_total'] ?? 0 ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Success rate', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_percent( (float) ( $totals['success_rate'] ?? 0 ) ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Average latency', 'npcink-cloud-addon' ); ?></th>
						<td>
							<?php
							printf(
								/* translators: %d: latency in milliseconds. */
								esc_html__( '%d ms', 'npcink-cloud-addon' ),
								absint( $totals['avg_latency_ms'] ?? 0 )
							);
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Summary refreshed', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_datetime_value( (string) ( $remote['last_refreshed_at'] ?? '' ) ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Summary error', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_empty( (string) ( $remote['last_refresh_error'] ?? '' ) ) ); ?></td>
					</tr>
				</tbody>
			</table>
			<table class="widefat striped" style="max-width: 860px; margin-top: 12px;">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Agent quality events', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( (string) absint( $agent_summary['events_total'] ?? 0 ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Agent quality window', 'npcink-cloud-addon' ); ?></th>
						<td>
							<?php
							printf(
								/* translators: %d: window hours. */
								esc_html__( '%d hours', 'npcink-cloud-addon' ),
								absint( $agent_summary['window_hours'] ?? 0 )
							);
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Agent quality refreshed', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_datetime_value( (string) ( $agent_feedback['last_refreshed_at'] ?? '' ) ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Agent quality error', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_empty( (string) ( $agent_feedback['last_refresh_error'] ?? '' ) ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Quality boundary', 'npcink-cloud-addon' ); ?></th>
						<td><?php esc_html_e( 'Read-only Cloud eval summary. Approval, proposal, preflight, and WordPress writes remain local.', 'npcink-cloud-addon' ); ?></td>
					</tr>
				</tbody>
			</table>
			<?php self::render_agent_feedback_quality_lists( $agent_summary ); ?>
			<?php if ( ! empty( $cloud_plugins ) ) : ?>
				<table class="widefat striped" style="max-width: 860px; margin-top: 12px;">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Cloud plugin', 'npcink-cloud-addon' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Events', 'npcink-cloud-addon' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Errors', 'npcink-cloud-addon' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Success', 'npcink-cloud-addon' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Avg latency', 'npcink-cloud-addon' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $cloud_plugins as $plugin ) : ?>
							<?php $plugin = is_array( $plugin ) ? $plugin : array(); ?>
							<tr>
								<th scope="row"><code><?php echo esc_html( (string) ( $plugin['plugin_slug'] ?? '' ) ); ?></code></th>
								<td><?php echo esc_html( (string) absint( $plugin['events_total'] ?? 0 ) ); ?></td>
								<td><?php echo esc_html( (string) absint( $plugin['error_total'] ?? 0 ) ); ?></td>
								<td><?php echo esc_html( self::format_percent( (float) ( $plugin['success_rate'] ?? 0 ) ) ); ?></td>
								<td><?php echo esc_html( absint( $plugin['avg_latency_ms'] ?? 0 ) . ' ms' ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
			<?php if ( ! empty( $recent_errors ) ) : ?>
				<table class="widefat striped" style="max-width: 860px; margin-top: 12px;">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Recent error', 'npcink-cloud-addon' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Plugin', 'npcink-cloud-addon' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Event', 'npcink-cloud-addon' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Seen', 'npcink-cloud-addon' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $recent_errors as $error ) : ?>
							<?php $error = is_array( $error ) ? $error : array(); ?>
							<tr>
								<td><code><?php echo esc_html( (string) ( $error['error_code'] ?? '' ) ); ?></code></td>
								<td><code><?php echo esc_html( (string) ( $error['plugin_slug'] ?? '' ) ); ?></code></td>
								<td><code><?php echo esc_html( (string) ( $error['event_kind'] ?? '' ) ); ?></code></td>
								<td><?php echo esc_html( self::format_datetime_value( (string) ( $error['received_at'] ?? '' ) ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
			<table class="widefat striped" style="max-width: 860px; margin-top: 12px;">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Plugin', 'npcink-cloud-addon' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Installed', 'npcink-cloud-addon' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Active', 'npcink-cloud-addon' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Version', 'npcink-cloud-addon' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $plugins as $plugin ) : ?>
						<?php $plugin = is_array( $plugin ) ? $plugin : array(); ?>
						<tr>
							<th scope="row"><?php echo esc_html( (string) ( $plugin['label'] ?? '' ) ); ?></th>
							<td><?php echo ! empty( $plugin['installed'] ) ? esc_html__( 'yes', 'npcink-cloud-addon' ) : esc_html__( 'no', 'npcink-cloud-addon' ); ?></td>
							<td><?php echo ! empty( $plugin['active'] ) ? esc_html__( 'yes', 'npcink-cloud-addon' ) : esc_html__( 'no', 'npcink-cloud-addon' ); ?></td>
							<td><code><?php echo esc_html( self::format_empty( (string) ( $plugin['version'] ?? '' ) ) ); ?></code></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php
			}

		/**
		 * Renders read-only Agent feedback quality detail lists.
		 *
		 * @param array<string,mixed> $summary Sanitized Agent feedback summary.
		 * @return void
		 */
		private static function render_agent_feedback_quality_lists( array $summary ): void {
			$source_runtimes = is_array( $summary['source_runtimes'] ?? null ) ? $summary['source_runtimes'] : array();
			$labels = is_array( $summary['low_quality_labels'] ?? null ) ? $summary['low_quality_labels'] : array();
			if ( empty( $labels ) && is_array( $summary['labels'] ?? null ) ) {
				$labels = $summary['labels'];
			}
			$reasons = is_array( $summary['rejection_reasons'] ?? null ) ? $summary['rejection_reasons'] : array();
			if ( empty( $source_runtimes ) && empty( $labels ) && empty( $reasons ) ) {
				return;
			}
			?>
			<table class="widefat striped" style="max-width: 860px; margin-top: 12px;">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Agent quality signal', 'npcink-cloud-addon' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Value', 'npcink-cloud-addon' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Count', 'npcink-cloud-addon' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $source_runtimes as $runtime ) : ?>
						<tr>
							<th scope="row"><?php esc_html_e( 'Source runtime', 'npcink-cloud-addon' ); ?></th>
							<td><code><?php echo esc_html( (string) $runtime ); ?></code></td>
							<td><?php echo esc_html( self::format_empty( '' ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					<?php self::render_agent_feedback_metric_rows( __( 'Low quality label', 'npcink-cloud-addon' ), $labels ); ?>
					<?php self::render_agent_feedback_metric_rows( __( 'Rejected reason', 'npcink-cloud-addon' ), $reasons ); ?>
				</tbody>
			</table>
			<?php
		}

		/**
		 * Renders sanitized Agent feedback metric rows.
		 *
		 * @param string              $kind Row kind label.
		 * @param array<int,mixed>    $items Sanitized metric items.
		 * @return void
		 */
		private static function render_agent_feedback_metric_rows( string $kind, array $items ): void {
			foreach ( $items as $item ) {
				$item = is_array( $item ) ? $item : array();
				$value = (string) ( $item['label'] ?? ( $item['reason'] ?? ( $item['code'] ?? ( $item['bucket'] ?? '' ) ) ) );
				if ( '' === $value ) {
					continue;
				}
				$count = array_key_exists( 'count', $item ) ? (string) absint( $item['count'] ) : self::format_empty( '' );
				?>
				<tr>
					<th scope="row"><?php echo esc_html( $kind ); ?></th>
					<td><code><?php echo esc_html( $value ); ?></code></td>
					<td><?php echo esc_html( $count ); ?></td>
				</tr>
				<?php
			}
		}

		/**
		 * Renders the entitlement summary.
		 *
		 * @param array<string,mixed> $summary Entitlement summary.
		 * @param bool                $is_verified Whether the connector has verified credentials.
		 * @return void
		 */
		private static function render_entitlement_summary( array $summary, bool $is_verified ): void {
			if ( ! $is_verified ) {
				?>
				<p class="npcink-cloud-empty"><?php esc_html_e( 'Entitlement is checked after the connector verifies successfully.', 'npcink-cloud-addon' ); ?></p>
				<?php
				return;
			}
			?>
			<table class="widefat striped" style="max-width: 860px;">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Availability', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_entitlement_availability( $summary, $is_verified ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Package', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_package_label( $summary, $is_verified ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Entitlement status', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_empty( (string) ( $summary['entitlement_status'] ?? '' ) ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Synced at', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_datetime_value( (string) ( $summary['synced_at'] ?? '' ) ) ); ?></td>
					</tr>
				</tbody>
			</table>
			<?php
		}

		/**
		 * Renders the summary-only Cloud AI credit usage projection.
		 *
		 * @param array<string,mixed> $summary Entitlement summary.
		 * @return void
		 */
		private static function render_credit_usage_summary( array $summary ): void {
			$detail = is_array( $summary['credit_usage_detail'] ?? null ) ? $summary['credit_usage_detail'] : array();
			$usage = is_array( $detail['summary'] ?? null ) ? $detail['summary'] : array();
			$links = is_array( $summary['links'] ?? null ) ? $summary['links'] : array();
			$usage_url = esc_url( (string) ( $links['credit_ledger_url'] ?? ( $links['usage_url'] ?? '' ) ) );
			if ( empty( $detail['available'] ) && '' === $usage_url ) {
				return;
			}
			?>
			<div class="npcink-cloud-section-heading">
				<h3><?php esc_html_e( 'AI Credit Usage', 'npcink-cloud-addon' ); ?></h3>
				<?php if ( '' !== $usage_url ) : ?>
					<a class="button button-secondary" href="<?php echo esc_url( $usage_url ); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'View credit details in Cloud', 'npcink-cloud-addon' ); ?>
					</a>
				<?php endif; ?>
			</div>
			<p class="description"><?php esc_html_e( 'Summary-only Cloud credit projection. The Cloud portal owns the detailed ledger, billing explanation, and historical usage.', 'npcink-cloud-addon' ); ?></p>
			<?php if ( empty( $detail['available'] ) ) : ?>
				<p class="npcink-cloud-empty"><?php esc_html_e( 'Cloud did not return a local credit summary yet. Open the Cloud portal for the current usage detail.', 'npcink-cloud-addon' ); ?></p>
			<?php else : ?>
				<table class="widefat striped" style="max-width: 860px;">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Used credits', 'npcink-cloud-addon' ); ?></th>
							<td><?php echo esc_html( self::format_credit_amount( $usage['used'] ?? 0, (string) ( $usage['unit'] ?? 'credit' ) ) ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Limit', 'npcink-cloud-addon' ); ?></th>
							<td><?php echo esc_html( self::format_credit_amount( $usage['limit'] ?? 0, (string) ( $usage['unit'] ?? 'credit' ) ) ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Remaining', 'npcink-cloud-addon' ); ?></th>
							<td><?php echo esc_html( self::format_credit_amount( $usage['remaining'] ?? null, (string) ( $usage['unit'] ?? 'credit' ) ) ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Status', 'npcink-cloud-addon' ); ?></th>
							<td><code><?php echo esc_html( self::format_empty( (string) ( $usage['status'] ?? '' ) ) ); ?></code></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Generated at', 'npcink-cloud-addon' ); ?></th>
							<td><?php echo esc_html( self::format_datetime_value( (string) ( $detail['generated_at'] ?? '' ) ) ); ?></td>
						</tr>
					</tbody>
				</table>
			<?php endif; ?>
			<?php
		}

		/**
		 * Renders read-only Pro Cloud Runtime entitlement detail.
		 *
		 * @param array<string,mixed> $summary Entitlement summary.
		 * @return void
		 */
		private static function render_pro_cloud_runtime_summary( array $summary ): void {
			$runtime = is_array( $summary['pro_cloud_runtime'] ?? null ) ? $summary['pro_cloud_runtime'] : array();
			$local_truth = is_array( $runtime['local_truth'] ?? null ) ? $runtime['local_truth'] : array();
			if ( empty( $summary['available'] ) || empty( $runtime['feature_id'] ) ) {
				return;
			}
			?>
			<h3><?php esc_html_e( 'Pro Cloud Runtime', 'npcink-cloud-addon' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Read-only Cloud entitlement detail for local plugin displays. This addon does not own billing truth, scheduling, queues, or WordPress writes.', 'npcink-cloud-addon' ); ?></p>
			<table class="widefat striped" style="max-width: 860px;">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Feature', 'npcink-cloud-addon' ); ?></th>
						<td><code><?php echo esc_html( (string) ( $runtime['feature_id'] ?? '' ) ); ?></code></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Nightly runs', 'npcink-cloud-addon' ); ?></th>
						<td>
							<?php
							echo esc_html( self::format_runtime_quota_projection( $runtime ) );
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Batch limit', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_runtime_integer_projection( $runtime, 'max_batch_items' ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Retention', 'npcink-cloud-addon' ); ?></th>
						<td>
							<?php
							echo esc_html( self::format_runtime_days_projection( $runtime, 'result_retention_days' ) );
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Quota exhausted', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_runtime_boolean_projection( $runtime, 'quota_exhausted' ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Local truth', 'npcink-cloud-addon' ); ?></th>
						<td><code><?php echo esc_html( (string) ( $local_truth['final_write_path'] ?? 'core_proposal_required' ) ); ?></code></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Runtime ownership', 'npcink-cloud-addon' ); ?></th>
						<td><?php esc_html_e( 'Cloud owns run state, retry processing, retention, and usage detail. Local Core owns approval and WordPress writes.', 'npcink-cloud-addon' ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Contract reuse', 'npcink-cloud-addon' ); ?></th>
						<td><?php esc_html_e( 'Reuses Cloud runtime/detail, Toolbox product buttons, Core proposal handoff, Adapter execution profiles, and Toolkit ability contracts. This addon adds no registry, scheduler, approval store, queue, or write executor.', 'npcink-cloud-addon' ); ?></td>
					</tr>
				</tbody>
			</table>
			<?php
		}

		/**
		 * Renders low-frequency connector details.
		 *
		 * @param array<string,mixed> $settings Stored settings.
		 * @param array<string,mixed> $state Credential state.
		 * @param array<string,mixed> $entitlement Entitlement summary.
		 * @return void
		 */
		private static function render_advanced_information( array $settings, array $state, array $entitlement ): void {
			$has_entitlement_context = ! empty( $state['verified'] )
				|| '' !== (string) ( $entitlement['message'] ?? '' )
				|| '' !== (string) ( $entitlement['state'] ?? '' );
			?>
			<h3><?php esc_html_e( 'Connection status', 'npcink-cloud-addon' ); ?></h3>
			<p><?php echo $has_entitlement_context ? esc_html__( 'Timeout, verification failure, and entitlement message.', 'npcink-cloud-addon' ) : esc_html__( 'Local connection fallback and last verification failure.', 'npcink-cloud-addon' ); ?></p>
			<table class="widefat striped">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Timeout', 'npcink-cloud-addon' ); ?></th>
						<td>
							<?php
							printf(
								/* translators: %d: timeout in seconds. */
								esc_html__( '%d seconds', 'npcink-cloud-addon' ),
								absint( $settings['timeout'] )
							);
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Connection code', 'npcink-cloud-addon' ); ?></th>
						<td><code><?php echo esc_html( (string) $state['code'] ); ?></code></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Last failure', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_empty( (string) ( $state['last_verification_error'] ?? '' ) ) ); ?></td>
					</tr>
					<?php if ( $has_entitlement_context ) : ?>
						<tr>
							<th scope="row"><?php esc_html_e( 'Entitlement message', 'npcink-cloud-addon' ); ?></th>
							<td><?php echo esc_html( self::format_empty( (string) ( $entitlement['message'] ?? '' ) ) ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Entitlement state', 'npcink-cloud-addon' ); ?></th>
							<td><code><?php echo esc_html( self::format_empty( (string) ( $entitlement['state'] ?? '' ) ) ); ?></code></td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
			<?php
		}

		/**
		 * Formats a probe failure message.
		 *
		 * @param array<string,mixed> $probe Probe payload.
		 * @return string
		 */
		private static function format_probe_failure_message( array $probe ): string {
			$messages = array();
			if ( empty( $probe['live_ok'] ) && ! empty( $probe['live_message'] ) ) {
				$messages[] = sprintf(
					/* translators: %s: liveness error. */
					__( 'Live check failed: %s', 'npcink-cloud-addon' ),
					self::redact_sensitive_message( (string) $probe['live_message'] )
				);
			}
			if ( empty( $probe['auth_ok'] ) && ! empty( $probe['auth_message'] ) ) {
				$messages[] = sprintf(
					/* translators: %s: signed verification error. */
					__( 'Signed verification failed: %s', 'npcink-cloud-addon' ),
					self::redact_sensitive_message( (string) $probe['auth_message'] )
				);
			}

			return '' !== implode( ' ', $messages )
				? sanitize_text_field( implode( ' ', $messages ) )
				: __( 'Cloud verification failed.', 'npcink-cloud-addon' );
		}

		/**
		 * Redacts connection credentials from operator-facing failure text.
		 *
		 * @param string $message Raw message.
		 * @return string
		 */
		private static function redact_sensitive_message( string $message ): string {
			$message = preg_replace( '/mak1_[A-Za-z0-9_-]+/', '[redacted]', $message );
			$message = preg_replace( '/Bearer\s+[A-Za-z0-9._~+\/=-]+/i', 'Bearer [redacted]', (string) $message );

			return sanitize_text_field( (string) $message );
		}

		/**
		 * Stores an admin notice for the redirected request.
		 *
		 * @param string $type Notice type.
		 * @param string $message Notice message.
		 * @return void
		 */
		private static function set_admin_notice( string $type, string $message ): void {
			set_transient(
				self::notice_transient_key(),
				array(
					'type' => sanitize_key( $type ),
					'message' => self::redact_sensitive_message( $message ),
				),
				60
			);
		}

		/**
		 * Stores the latest manual readiness result for this administrator.
		 *
		 * @param array<string,mixed> $result Readiness result.
		 * @return void
		 */
		private static function set_manual_readiness_result( array $result ): void {
			set_transient(
				self::manual_readiness_transient_key(),
				$result,
				10 * MINUTE_IN_SECONDS
			);
		}

		/**
		 * Returns the latest manual readiness result for this administrator.
		 *
		 * @return array<string,mixed>
		 */
		private static function get_manual_readiness_result(): array {
			$result = get_transient( self::manual_readiness_transient_key() );

			return is_array( $result ) ? $result : array();
		}

		/**
		 * Renders and clears the saved admin notice.
		 *
		 * @return void
		 */
		private static function render_admin_notice(): void {
			$notice = get_transient( self::notice_transient_key() );
			delete_transient( self::notice_transient_key() );
			if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
				return;
			}

			$type = sanitize_key( (string) ( $notice['type'] ?? '' ) );
			if ( ! in_array( $type, array( 'success', 'warning', 'error' ), true ) ) {
				$type = 'error';
			}
			?>
			<div class="notice notice-<?php echo esc_attr( $type ); ?> is-dismissible">
				<p><?php echo esc_html( (string) $notice['message'] ); ?></p>
			</div>
			<?php
		}

		/**
		 * Returns a notice transient key for the current user.
		 *
		 * @return string
		 */
		private static function notice_transient_key(): string {
			return 'npcink_cloud_notice_' . absint( get_current_user_id() );
		}

		/**
		 * Returns a manual readiness result transient key for the current user.
		 *
		 * @return string
		 */
		private static function manual_readiness_transient_key(): string {
			return 'npcink_cloud_readiness_' . absint( get_current_user_id() );
		}

		/**
		 * Redirects back to the page.
		 *
		 * @return void
		 */
		private static function redirect_to_page( string $tab = '' ): void {
			$url = self::page_url();
			if ( '' !== $tab ) {
				$url = add_query_arg( 'tab', sanitize_key( $tab ), $url );
			}

			wp_safe_redirect( $url );
			exit;
		}

		/**
		 * Returns the active Cloud Addon page URL.
		 *
		 * @return string
		 */
		private static function page_url(): string {
			$parent = defined( 'NPCINK_TOOLBOX_VERSION' ) ? 'admin.php' : 'options-general.php';
			return admin_url( $parent . '?page=' . self::PAGE_SLUG );
		}

		/**
		 * Formats entitlement availability for default display.
		 *
		 * @param array<string,mixed> $summary Entitlement summary.
		 * @param bool                $is_verified Whether the connector has verified credentials.
		 * @return string
		 */
		private static function format_entitlement_availability( array $summary, bool $is_verified ): string {
			if ( ! $is_verified ) {
				return __( 'Not checked', 'npcink-cloud-addon' );
			}

			if ( ! empty( $summary['available'] ) ) {
				return __( 'available', 'npcink-cloud-addon' );
			}

			$state = sanitize_key( (string) ( $summary['state'] ?? '' ) );
			if ( 'not_refreshed' === $state ) {
				return __( 'not refreshed', 'npcink-cloud-addon' );
			}

			if ( 'not_configured' === $state ) {
				return __( 'not configured', 'npcink-cloud-addon' );
			}

			if ( 'unavailable' === $state ) {
				return __( 'read failed', 'npcink-cloud-addon' );
			}

			return __( 'unavailable', 'npcink-cloud-addon' );
		}

		/**
		 * Formats the Cloud package label without implying local billing truth.
		 *
		 * @param array<string,mixed> $summary Entitlement summary.
		 * @param bool                $is_verified Whether the connector has verified credentials.
		 * @return string
		 */
		private static function format_package_label( array $summary, bool $is_verified ): string {
			if ( ! $is_verified ) {
				return __( 'Not checked', 'npcink-cloud-addon' );
			}

			$package_label = trim( (string) ( $summary['package_label'] ?? '' ) );
			if ( '' !== $package_label ) {
				return $package_label;
			}

			$state = sanitize_key( (string) ( $summary['state'] ?? '' ) );
			if ( 'not_refreshed' === $state ) {
				return __( 'not refreshed', 'npcink-cloud-addon' );
			}

			if ( empty( $summary['available'] ) ) {
				return __( 'unavailable', 'npcink-cloud-addon' );
			}

			return __( 'not returned by Cloud', 'npcink-cloud-addon' );
		}

		/**
		 * Formats monitoring state for the compact default panel.
		 *
		 * @param array<string,mixed> $monitoring Monitoring status.
		 * @return string
		 */
		private static function format_monitoring_overview( array $monitoring ): string {
			$state = ! empty( $monitoring['enabled'] )
				? __( 'enabled', 'npcink-cloud-addon' )
				: __( 'disabled', 'npcink-cloud-addon' );
			$buffer_count = absint( $monitoring['buffer_count'] ?? 0 );

			return sprintf(
				/* translators: 1: monitoring state, 2: buffered event count. */
				__( '%1$s, %2$d buffered', 'npcink-cloud-addon' ),
				$state,
				$buffer_count
			);
		}

		/**
		 * Formats Site Knowledge bridge state for compact status rows.
		 *
		 * @param array<string,mixed> $site_knowledge Site Knowledge bridge status.
		 * @return string
		 */
		private static function format_site_knowledge_overview( array $site_knowledge ): string {
			$status = sanitize_key( (string) ( $site_knowledge['status'] ?? '' ) );
			if ( '' === $status ) {
				$status = ! empty( $site_knowledge['verified'] ) ? 'idle' : 'unverified';
			}

			return sprintf(
				/* translators: 1: bridge status, 2: buffered public change count. */
				__( '%1$s, %2$d public changes buffered', 'npcink-cloud-addon' ),
				self::format_site_knowledge_status_label( $status ),
				absint( $site_knowledge['buffer_count'] ?? 0 )
			);
		}

		/**
		 * Formats a Site Knowledge bridge status for the local admin surface.
		 *
		 * @param string $status Raw bridge status.
		 * @return string
		 */
		private static function format_site_knowledge_status_label( string $status ): string {
			$labels = array(
				'idle'           => __( 'idle', 'npcink-cloud-addon' ),
				'not_configured' => __( 'not configured', 'npcink-cloud-addon' ),
				'unverified'     => __( 'unverified', 'npcink-cloud-addon' ),
				'disabled'       => __( 'disabled', 'npcink-cloud-addon' ),
				'error'          => __( 'error', 'npcink-cloud-addon' ),
				'pending'        => __( 'pending', 'npcink-cloud-addon' ),
				'queued'         => __( 'queued', 'npcink-cloud-addon' ),
				'ok'             => __( 'ok', 'npcink-cloud-addon' ),
			);

			return $labels[ $status ] ?? self::format_empty( $status );
		}

		/**
		 * Renders the Site Knowledge error row with a localized summary.
		 *
		 * @param string $error Raw Cloud/runtime error text.
		 * @return void
		 */
		private static function render_site_knowledge_error_cell( string $error ): void {
			$error = trim( $error );
			if ( '' === $error ) {
				echo esc_html( self::format_empty( '' ) );
				return;
			}

			$summary = self::format_site_knowledge_error_summary( $error );
			?>
			<div class="npcink-cloud-error-summary"><?php echo esc_html( $summary ); ?></div>
			<?php if ( $summary !== $error ) : ?>
				<details class="npcink-cloud-inline-note npcink-cloud-error-detail">
					<summary><?php esc_html_e( 'Show original Cloud error', 'npcink-cloud-addon' ); ?></summary>
					<code><?php echo esc_html( $error ); ?></code>
				</details>
			<?php endif; ?>
			<?php
		}

		/**
		 * Maps known dynamic Cloud/runtime errors to local admin summaries.
		 *
		 * @param string $error Raw Cloud/runtime error text.
		 * @return string
		 */
		private static function format_site_knowledge_error_summary( string $error ): string {
			$normalized = strtolower( $error );

			if ( false !== strpos( $normalized, 'personal data' ) && false !== strpos( $normalized, 'data_classification=pii' ) ) {
				return __( 'The request appears to contain personal data. Cloud requires data_classification=pii before delivery.', 'npcink-cloud-addon' );
			}

			if ( preg_match( "/exceeded max active cloud runs '?([0-9]+)'?/", $normalized, $matches ) ) {
				return sprintf(
					/* translators: %d: maximum active Cloud run count. */
					__( 'Cloud active run limit reached (%d). Wait for the current run to finish, then try again.', 'npcink-cloud-addon' ),
					absint( $matches[1] )
				);
			}

			return $error;
		}

		/**
		 * Formats Site Knowledge diagnostic detail without adding control-plane state.
		 *
		 * @param array<string,mixed> $site_knowledge Site Knowledge bridge status.
		 * @return string
		 */
			private static function format_site_knowledge_diagnostic_detail( array $site_knowledge ): string {
				if ( empty( $site_knowledge['verified'] ) ) {
					return __( 'Cloud settings must be verified before public content-change delivery can run.', 'npcink-cloud-addon' );
				}

				if ( empty( $site_knowledge['delivery_enabled'] ) ) {
					return __( 'Site Knowledge delivery is disabled locally. Existing Cloud index data is unchanged until Delete site index is requested.', 'npcink-cloud-addon' );
				}

				$error_code = sanitize_key( (string) ( $site_knowledge['last_error_code'] ?? '' ) );
			if ( '' !== $error_code ) {
				return sprintf(
					/* translators: %s: Site Knowledge delivery error code. */
					__( 'Last delivery error: %s', 'npcink-cloud-addon' ),
					$error_code
				);
			}

			return __( 'Public content-change delivery and Toolbox runtime transport use signed Cloud runtime only.', 'npcink-cloud-addon' );
		}

		/**
		 * Formats a setting value with a field-specific fallback.
		 *
		 * @param string $value Value.
		 * @param string $fallback Fallback text.
		 * @return string
		 */
		private static function format_setting_value( string $value, string $fallback ): string {
			return '' !== $value ? $value : $fallback;
		}

		/**
		 * Formats an empty display value.
		 *
		 * @param string $value Value.
		 * @return string
		 */
		private static function format_empty( string $value ): string {
			return '' !== $value ? $value : __( 'unavailable', 'npcink-cloud-addon' );
		}

		/**
		 * Formats a Cloud credit amount for summary display.
		 *
		 * @param mixed  $value Credit amount.
		 * @param string $unit Unit label.
		 * @return string
		 */
		private static function format_credit_amount( $value, string $unit ): string {
			if ( null === $value || '' === $value ) {
				return __( 'unavailable', 'npcink-cloud-addon' );
			}

			$amount = is_numeric( $value ) ? (float) $value : 0.0;
			$formatted = function_exists( 'number_format_i18n' )
				? number_format_i18n( $amount, 2 )
				: number_format( $amount, 2, '.', ',' );

			return trim( $formatted . ' ' . sanitize_text_field( $unit ) );
		}

		/**
		 * Formats a stored UTC datetime for the site's WordPress timezone.
		 *
		 * @param string $value    UTC datetime string.
		 * @param string $fallback Fallback text.
		 * @return string
		 */
		private static function format_datetime_value( string $value, string $fallback = '' ): string {
			$value = trim( $value );
			if ( '' === $value ) {
				return '' !== $fallback ? $fallback : __( 'unavailable', 'npcink-cloud-addon' );
			}

			$has_timezone = (bool) preg_match( '/(?:Z|UTC|[+-]\d{2}:?\d{2})$/i', $value );
			$timestamp    = strtotime( $has_timezone ? $value : $value . ' UTC' );
			if ( false === $timestamp ) {
				return $value;
			}

			if ( function_exists( 'wp_date' ) ) {
				return wp_date( self::DATETIME_DISPLAY_FORMAT, $timestamp );
			}

			if ( function_exists( 'date_i18n' ) ) {
				return date_i18n( self::DATETIME_DISPLAY_FORMAT, $timestamp, true );
			}

			return gmdate( self::DATETIME_DISPLAY_FORMAT, $timestamp );
		}

		/**
		 * Formats a normalized ratio as a percentage.
		 *
		 * @param float $value Normalized ratio.
		 * @return string
		 */
		private static function format_percent( float $value ): string {
			$value = max( 0.0, min( 1.0, $value ) );

			return number_format_i18n( $value * 100, 1 ) . '%';
		}
	}
}
