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

				self::redirect_to_page( 'advanced', 'checks' );
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
					self::redirect_to_page( 'advanced', 'runs' );
				}

				$run_id = isset( $_POST['runtime_run_id'] ) ? self::normalize_run_id( sanitize_text_field( wp_unslash( $_POST['runtime_run_id'] ) ) ) : '';
				if ( '' === $run_id ) {
					self::set_admin_notice( 'error', __( 'Enter a Cloud run ID before requesting retry.', 'npcink-cloud-addon' ) );
					self::redirect_to_page( 'advanced', 'runs' );
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
					self::redirect_to_page( 'advanced', 'runs' );
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
							'tab'              => 'advanced',
							'view'             => 'runs',
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
					<?php self::render_overview_page( $settings, $state, $entitlement, $monitoring, $site_knowledge, $is_verified ); ?>
				<?php elseif ( 'site_knowledge' === $active_tab ) : ?>
					<section class="npcink-cloud-section npcink-cloud-tab-panel">
						<h2 class="screen-reader-text"><?php esc_html_e( 'Site Knowledge', 'npcink-cloud-addon' ); ?></h2>
						<?php self::render_site_knowledge_summary( $site_knowledge, $settings, $is_verified ); ?>
					</section>
				<?php elseif ( 'advanced' === $active_tab ) : ?>
					<section class="npcink-cloud-section npcink-cloud-tab-panel">
						<h2 class="screen-reader-text"><?php esc_html_e( 'Advanced and troubleshooting', 'npcink-cloud-addon' ); ?></h2>
						<?php self::render_advanced_page( $settings, $state, $entitlement, $monitoring, $is_verified ); ?>
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
			if ( $is_verified && in_array( $requested, array( 'details', 'status' ), true ) ) {
				$requested = 'permissions';
			}
			if ( $is_verified && in_array( $requested, array( 'runtime_runs', 'diagnostics' ), true ) ) {
				$requested = 'advanced';
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
					'permissions'    => __( 'Overview', 'npcink-cloud-addon' ),
					'site_knowledge' => __( 'Site Knowledge', 'npcink-cloud-addon' ),
					'advanced'       => __( 'Advanced and troubleshooting', 'npcink-cloud-addon' ),
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
		 * Builds an admin URL for one tab subview.
		 *
		 * @param string $tab Tab slug.
		 * @param string $view Subview slug.
		 * @return string
		 */
		private static function tab_view_url( string $tab, string $view ): string {
			return add_query_arg(
				array(
					'page' => self::PAGE_SLUG,
					'tab'  => sanitize_key( $tab ),
					'view' => sanitize_key( $view ),
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
				'tab'          => 'advanced',
				'view'         => 'runs',
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

			return in_array( $view, array( 'service', 'checks', 'runs', 'connection' ), true ) ? $view : 'service';
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
			$is_custom_base_url = untrailingslashit( $display_base_url ) !== untrailingslashit( Npcink_Cloud_Addon_Settings::get_default_base_url() );
			$show_connection_meta = ! $is_verified || $is_custom_base_url;
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
				<?php if ( $is_configured && $show_connection_meta ) : ?>
					<div class="npcink-cloud-summary__grid">
						<?php if ( ! $is_verified || $is_custom_base_url ) : ?>
							<div class="npcink-cloud-summary__item">
								<span class="npcink-cloud-summary__label"><?php esc_html_e( 'Cloud Base URL', 'npcink-cloud-addon' ); ?></span>
								<span class="npcink-cloud-summary__value"><?php echo esc_html( self::format_setting_value( $display_base_url, __( 'Not set', 'npcink-cloud-addon' ) ) ); ?></span>
							</div>
						<?php endif; ?>
						<?php if ( ! $is_verified ) : ?>
							<div class="npcink-cloud-summary__item">
								<span class="npcink-cloud-summary__label"><?php esc_html_e( 'Last verified', 'npcink-cloud-addon' ); ?></span>
								<span class="npcink-cloud-summary__value"><?php echo esc_html( self::format_datetime_value( (string) $settings['verified_at'], __( 'Never', 'npcink-cloud-addon' ) ) ); ?></span>
							</div>
						<?php endif; ?>
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
					'description' => __( 'Allow WordPress AI to use Npcink Cloud.', 'npcink-cloud-addon' ),
				),
				'site_knowledge_delivery_enabled' => array(
					'label'       => __( 'Site Knowledge delivery', 'npcink-cloud-addon' ),
					'description' => __( 'Send public content changes to Cloud Site Knowledge.', 'npcink-cloud-addon' ),
				),
				'site_knowledge_generation_reference_enabled' => array(
					'label'       => __( 'Reference site content during generation', 'npcink-cloud-addon' ),
					'description' => __( 'Use indexed public articles as generation context.', 'npcink-cloud-addon' ),
				),
				'monitoring_enabled' => array(
					'label'       => __( 'Monitoring', 'npcink-cloud-addon' ),
					'description' => __( 'Upload metadata-only plugin monitoring events.', 'npcink-cloud-addon' ),
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
				</div>
				<div class="npcink-cloud-local-permissions__list">
					<?php $definitions = self::get_local_permission_definitions(); ?>
					<?php foreach ( array( 'wordpress_ai_connector_enabled', 'site_knowledge_delivery_enabled' ) as $permission ) : ?>
						<?php self::render_local_permission_switch( $permission, $definitions[ $permission ], ! empty( $settings[ $permission ] ) ); ?>
					<?php endforeach; ?>
					<?php if ( ! empty( $settings['site_knowledge_delivery_enabled'] ) ) : ?>
						<div class="npcink-cloud-local-permission--dependent">
							<?php self::render_local_permission_switch( 'site_knowledge_generation_reference_enabled', $definitions['site_knowledge_generation_reference_enabled'], ! empty( $settings['site_knowledge_generation_reference_enabled'] ) ); ?>
						</div>
					<?php endif; ?>
				</div>
				<details class="npcink-cloud-advanced-detail npcink-cloud-local-permissions__more">
					<summary><?php esc_html_e( 'More local permissions', 'npcink-cloud-addon' ); ?></summary>
					<div class="npcink-cloud-advanced-detail__body">
						<?php self::render_local_permission_switch( 'monitoring_enabled', $definitions['monitoring_enabled'], ! empty( $settings['monitoring_enabled'] ) ); ?>
					</div>
				</details>
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
				<?php if ( $is_verified ) : ?>
					<a class="button button-secondary" href="<?php echo esc_url( untrailingslashit( Npcink_Cloud_Addon_Settings::get_effective_base_url( $settings ) ) . '/portal/sites' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open Cloud sites', 'npcink-cloud-addon' ); ?></a>
				<?php else : ?>
					<?php self::render_reverify_form( $settings ); ?>
				<?php endif; ?>
			</div>
			<?php
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
				<?php if ( Npcink_Cloud_Addon_Settings::is_verified() ) : ?>
					<?php self::render_reverify_form( $settings ); ?>
				<?php endif; ?>
				<a class="button button-secondary" href="<?php echo esc_url( self::build_authorization_url( $settings ) ); ?>"><?php esc_html_e( 'Change connection in Cloud', 'npcink-cloud-addon' ); ?></a>
				<?php self::render_disconnect_form(); ?>
			</div>
			<?php
		}

		/**
		 * Renders the folded manual recovery form.
		 *
		 * @param array<string,mixed> $settings Stored settings.
		 * @return void
		 */
		private static function render_manual_connection_disclosure( array $settings ): void {
			?>
			<details class="npcink-cloud-advanced-detail">
				<summary><?php esc_html_e( 'Manual connection fallback', 'npcink-cloud-addon' ); ?></summary>
				<div class="npcink-cloud-advanced-detail__body">
					<p class="description"><?php esc_html_e( 'Use only for recovery or local debugging when Cloud authorization is unavailable.', 'npcink-cloud-addon' ); ?></p>
					<?php self::render_settings_form( $settings ); ?>
				</div>
			</details>
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
		 * Renders the compact verified overview.
		 *
		 * @param array<string,mixed> $settings Stored settings.
		 * @param array<string,mixed> $state Credential state.
		 * @param array<string,mixed> $entitlement Entitlement summary.
		 * @param array<string,mixed> $monitoring Monitoring status.
		 * @param bool                $is_verified Whether the connector has verified credentials.
		 * @return void
		 */
		private static function render_overview_page( array $settings, array $state, array $entitlement, array $monitoring, array $site_knowledge, bool $is_verified ): void {
			if ( ! $is_verified ) {
				self::render_cloud_authorization_panel( $settings, $state );
				return;
			}
			$monitoring_needs_attention = absint( $monitoring['buffer_count'] ?? 0 ) > 0
				|| '' !== (string) ( $monitoring['last_upload_error'] ?? '' );
			$site_knowledge_needs_attention = absint( $site_knowledge['buffer_count'] ?? 0 ) > 0
				|| '' !== (string) ( $site_knowledge['last_delivery_error'] ?? '' );
			?>
			<section class="npcink-cloud-section npcink-cloud-tab-panel">
				<h2 class="screen-reader-text"><?php esc_html_e( 'Overview', 'npcink-cloud-addon' ); ?></h2>
				<div class="npcink-cloud-section-heading">
					<h3><?php esc_html_e( 'Service summary', 'npcink-cloud-addon' ); ?></h3>
					<a class="button button-secondary" href="<?php echo esc_url( self::tab_view_url( 'advanced', 'service' ) ); ?>"><?php esc_html_e( 'View service details', 'npcink-cloud-addon' ); ?></a>
				</div>
				<table class="widefat striped npcink-cloud-overview-status">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Plan and entitlement', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_package_label( $entitlement, $is_verified ) . ' · ' . self::format_entitlement_availability( $entitlement, $is_verified ) ); ?></td>
					</tr>
					<?php if ( $monitoring_needs_attention ) : ?>
						<tr>
							<th scope="row"><?php esc_html_e( 'Monitoring needs attention', 'npcink-cloud-addon' ); ?></th>
							<td><?php echo esc_html( self::format_monitoring_overview( $monitoring ) ); ?></td>
						</tr>
					<?php endif; ?>
					<?php if ( $site_knowledge_needs_attention ) : ?>
						<tr>
							<th scope="row"><?php esc_html_e( 'Site Knowledge needs attention', 'npcink-cloud-addon' ); ?></th>
							<td><?php echo esc_html( self::format_site_knowledge_overview( $site_knowledge ) ); ?></td>
						</tr>
					<?php endif; ?>
				</tbody>
				</table>
			</section>
			<?php self::render_local_permissions( $settings, $is_verified ); ?>
			<?php
		}

		/**
		 * Renders bounded Cloud service detail and troubleshooting.
		 *
		 * @param array<string,mixed> $settings Stored settings.
		 * @param array<string,mixed> $state Credential state.
		 * @param array<string,mixed> $entitlement Entitlement summary.
		 * @param array<string,mixed> $monitoring Monitoring status.
		 * @param array<string,mixed> $site_knowledge Site Knowledge bridge status.
		 * @param bool                $is_verified Whether the connector has verified credentials.
		 * @return void
		 */
		private static function render_advanced_page( array $settings, array $state, array $entitlement, array $monitoring, bool $is_verified ): void {
			if ( ! $is_verified ) {
				self::render_advanced_information( $state );
				self::render_connection_management( $settings );
				self::render_manual_connection_disclosure( $settings );
				return;
			}

			$active_view = self::diagnostics_view_from_request();
			self::render_secondary_tab_navigation(
				$active_view,
				array(
					'service'    => __( 'Service details', 'npcink-cloud-addon' ),
					'checks'     => __( 'Checks', 'npcink-cloud-addon' ),
					'runs'       => __( 'Runtime runs', 'npcink-cloud-addon' ),
					'connection' => __( 'Connection recovery', 'npcink-cloud-addon' ),
				),
				'advanced',
				__( 'Advanced and troubleshooting sections', 'npcink-cloud-addon' )
			);

			if ( 'runs' === $active_view ) {
				self::render_runtime_runs( $settings, $state, $entitlement, $is_verified );
				return;
			}

			if ( 'checks' === $active_view ) {
				self::render_diagnostic_checks( $settings, $state, $entitlement, $is_verified );
				return;
			}

			if ( 'connection' === $active_view ) {
				self::render_advanced_information( $state );
				self::render_connection_management( $settings );
				self::render_manual_connection_disclosure( $settings );
				return;
			}

			self::render_status_account_usage( $entitlement, $is_verified );
			self::render_status_monitoring_quality( $monitoring );
		}

		/**
		 * Renders bounded Cloud service checks.
		 *
		 * @param array<string,mixed> $settings Stored settings.
		 * @param array<string,mixed> $state Credential state.
		 * @param array<string,mixed> $entitlement Entitlement summary.
		 * @param bool                $is_verified Whether the connector has verified credentials.
		 * @return void
		 */
		private static function render_diagnostic_checks( array $settings, array $state, array $entitlement, bool $is_verified ): void {
			$runtime = ! empty( $entitlement['available'] ) && is_array( $entitlement['pro_cloud_runtime'] ?? null ) ? $entitlement['pro_cloud_runtime'] : array();
			$readiness = self::get_manual_readiness_result();
			$connection_detail = sprintf(
				/* translators: 1: last verification time, 2: signed read status. */
				__( 'Last checked: %1$s · Signed read: %2$s', 'npcink-cloud-addon' ),
				self::format_datetime_value( (string) ( $settings['verified_at'] ?? '' ), __( 'Never', 'npcink-cloud-addon' ) ),
				self::format_entitlement_availability( $entitlement, $is_verified )
			);
			?>
			<div class="npcink-cloud-section-heading">
				<h3><?php esc_html_e( 'Checks', 'npcink-cloud-addon' ); ?></h3>
				<div class="npcink-cloud-summary__actions">
					<?php self::render_manual_readiness_test_form(); ?>
					<a class="button button-secondary" href="<?php echo esc_url( untrailingslashit( Npcink_Cloud_Addon_Settings::get_effective_base_url( $settings ) ) . '/portal/sites' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open Cloud status detail', 'npcink-cloud-addon' ); ?></a>
				</div>
			</div>
			<p class="description"><?php esc_html_e( 'Run the bounded connection checks or open Cloud for service detail.', 'npcink-cloud-addon' ); ?></p>
			<table class="widefat striped" style="max-width: 980px;">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Check', 'npcink-cloud-addon' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'npcink-cloud-addon' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Detail', 'npcink-cloud-addon' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php self::render_diagnostic_row( __( 'Credentials', 'npcink-cloud-addon' ), self::diagnostic_status( ! empty( $state['configured'] ), __( 'saved', 'npcink-cloud-addon' ), __( 'missing', 'npcink-cloud-addon' ) ), self::format_setting_value( (string) ( $settings['base_url'] ?? '' ), __( 'Not set', 'npcink-cloud-addon' ) ) ); ?>
					<?php self::render_diagnostic_row( __( 'Cloud connection', 'npcink-cloud-addon' ), self::diagnostic_status( ! empty( $state['verified'] ), __( 'verified', 'npcink-cloud-addon' ), __( 'not verified', 'npcink-cloud-addon' ) ), $connection_detail ); ?>
					<?php self::render_diagnostic_row( __( 'Hosted Runtime', 'npcink-cloud-addon' ), self::diagnostic_status( ! empty( $runtime['feature_id'] ), __( 'reported', 'npcink-cloud-addon' ), __( 'not returned', 'npcink-cloud-addon' ) ), self::format_hosted_runtime_diagnostic_detail( $runtime ) ); ?>
					<?php if ( ! empty( $readiness ) ) : ?>
						<?php self::render_diagnostic_row( __( 'Readiness result', 'npcink-cloud-addon' ), self::format_readiness_status( $readiness ), self::format_readiness_detail( $readiness ) ); ?>
					<?php endif; ?>
				</tbody>
			</table>
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
			<?php self::render_pro_cloud_runtime_summary( $entitlement ); ?>

			<details class="npcink-cloud-advanced-detail">
				<summary><?php esc_html_e( 'Inspect by run ID', 'npcink-cloud-addon' ); ?></summary>
				<div class="npcink-cloud-advanced-detail__body">
					<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="max-width: 860px; margin: 8px 0;">
						<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
						<input type="hidden" name="tab" value="advanced" />
						<input type="hidden" name="view" value="runs" />
						<label for="npcink-cloud-runtime-run-id"><strong><?php esc_html_e( 'Cloud run ID', 'npcink-cloud-addon' ); ?></strong></label>
						<input id="npcink-cloud-runtime-run-id" class="regular-text code" type="text" name="runtime_run_id" value="<?php echo esc_attr( $run_id ); ?>" placeholder="run_..." />
						<button class="button" type="submit" name="runtime_view" value="status"><?php esc_html_e( 'Read status', 'npcink-cloud-addon' ); ?></button>
						<button class="button" type="submit" name="runtime_view" value="result"><?php esc_html_e( 'Read result', 'npcink-cloud-addon' ); ?></button>
					</form>
				</div>
			</details>

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
			$error_code = self::runtime_pick( $payload, array( 'data.error_code', 'error_code', 'data.run_lifecycle.error_code' ) );
			?>
			<table class="widefat striped" style="max-width: 980px;">
				<tbody>
					<?php self::render_diagnostic_row( __( 'Run ID', 'npcink-cloud-addon' ), self::runtime_pick( $payload, array( 'data.run_id', 'run.run_id', 'run_id' ) ), __( 'Cloud run identifier.', 'npcink-cloud-addon' ) ); ?>
					<?php self::render_diagnostic_row( __( 'Run status', 'npcink-cloud-addon' ), self::runtime_pick( $payload, array( 'data.status', 'run.status', 'status' ) ), __( 'Cloud-owned run state.', 'npcink-cloud-addon' ) ); ?>
					<?php self::render_diagnostic_row( __( 'Result status', 'npcink-cloud-addon' ), self::runtime_pick( $payload, array( 'data.result_status', 'result.status', 'result_status' ) ), __( 'Result availability from Cloud.', 'npcink-cloud-addon' ) ); ?>
					<?php if ( '' !== $error_code ) : ?>
						<?php self::render_diagnostic_row( __( 'Error code', 'npcink-cloud-addon' ), $error_code, __( 'Cloud error classification.', 'npcink-cloud-addon' ) ); ?>
					<?php endif; ?>
					<?php self::render_diagnostic_row( __( 'Started', 'npcink-cloud-addon' ), self::format_datetime_value( self::runtime_pick( $payload, array( 'data.run_lifecycle.processing_started_at', 'data.started_at', 'started_at' ) ) ), __( 'Displayed in the WordPress site timezone.', 'npcink-cloud-addon' ) ); ?>
					<?php self::render_diagnostic_row( __( 'Finished', 'npcink-cloud-addon' ), self::format_datetime_value( self::runtime_pick( $payload, array( 'data.run_lifecycle.processing_finished_at', 'data.completed_at', 'completed_at' ) ) ), __( 'Displayed in the WordPress site timezone.', 'npcink-cloud-addon' ) ); ?>
				</tbody>
			</table>
			<div class="npcink-cloud-summary__actions npcink-cloud-summary__actions--start npcink-cloud-run-detail-actions">
				<a class="button button-secondary" href="<?php echo esc_url( self::runtime_tab_url( 'result', $run_id ) ); ?>"><?php esc_html_e( 'Read result', 'npcink-cloud-addon' ); ?></a>
				<?php self::render_runtime_retry_form( $run_id ); ?>
			</div>
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
			$active_view = self::site_knowledge_view_from_request();
			$last_delivery_error = (string) ( $site_knowledge['last_delivery_error'] ?? '' );
			$show_technical_detail = ! empty( $site_knowledge['wp_cron_disabled'] )
				|| '' !== $last_delivery_error
				|| '' !== (string) ( $site_knowledge['last_error_code'] ?? '' );
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
						<a class="button button-secondary" href="<?php echo esc_url( self::tab_url( 'permissions' ) ); ?>"><?php esc_html_e( 'Change in Overview', 'npcink-cloud-addon' ); ?></a>
					</div>
					<?php
					if ( 'index' === $active_view ) {
						?>
						<p><a href="<?php echo esc_url( self::tab_url( 'site_knowledge' ) ); ?>">&larr; <?php esc_html_e( 'Back to Site Knowledge', 'npcink-cloud-addon' ); ?></a></p>
						<?php
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
							<a class="button button-secondary" href="<?php echo esc_url( self::tab_view_url( 'site_knowledge', 'index' ) ); ?>"><?php esc_html_e( 'Manage index', 'npcink-cloud-addon' ); ?></a>
							<a class="button button-secondary" href="<?php echo esc_url( $base_url . '/portal/site-knowledge' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open Cloud Site Knowledge', 'npcink-cloud-addon' ); ?></a>
						</div>
					</div>
					<?php if ( ! $delivery_enabled ) : ?>
						<p class="description npcink-cloud-site-knowledge-disabled-note"><?php esc_html_e( 'Delivery is off; refresh controls and routine delivery rows are hidden.', 'npcink-cloud-addon' ); ?></p>
						<?php if ( $show_technical_detail ) : ?>
							<details class="npcink-cloud-advanced-detail">
								<summary><?php esc_html_e( 'Technical delivery details', 'npcink-cloud-addon' ); ?></summary>
								<div class="npcink-cloud-advanced-detail__body">
									<?php self::render_site_knowledge_bridge_health_detail( $site_knowledge ); ?>
								</div>
							</details>
						<?php endif; ?>
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
								<th scope="row"><?php esc_html_e( 'Buffered public changes', 'npcink-cloud-addon' ); ?></th>
								<td><?php echo esc_html( (string) absint( $site_knowledge['buffer_count'] ?? 0 ) ); ?></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Last delivery', 'npcink-cloud-addon' ); ?></th>
								<td><?php echo esc_html( self::format_datetime_value( (string) ( $site_knowledge['last_delivery_at'] ?? '' ) ) ); ?></td>
							</tr>
							<?php if ( '' !== $last_delivery_error ) : ?>
							<tr>
								<th scope="row"><?php esc_html_e( 'Last error', 'npcink-cloud-addon' ); ?></th>
								<td><?php self::render_site_knowledge_error_cell( $last_delivery_error ); ?></td>
							</tr>
							<?php endif; ?>
						</tbody>
					</table>
					<?php if ( $show_technical_detail ) : ?>
					<details class="npcink-cloud-advanced-detail">
						<summary><?php esc_html_e( 'Technical delivery details', 'npcink-cloud-addon' ); ?></summary>
						<div class="npcink-cloud-advanced-detail__body">
							<?php self::render_site_knowledge_bridge_health_detail( $site_knowledge ); ?>
						</div>
					</details>
					<?php endif; ?>
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
			$last_error_code = (string) ( $site_knowledge['last_error_code'] ?? '' );
			?>
			<h4><?php esc_html_e( 'Bridge health detail', 'npcink-cloud-addon' ); ?></h4>
			<table class="widefat striped npcink-cloud-site-knowledge-status npcink-cloud-site-knowledge-health-detail">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Last success', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_datetime_value( (string) ( $site_knowledge['last_success_at'] ?? '' ) ) ); ?></td>
					</tr>
					<?php if ( '' !== $last_error_code ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Last error code', 'npcink-cloud-addon' ); ?></th>
						<td><code><?php echo esc_html( $last_error_code ); ?></code></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Last error time', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_datetime_value( (string) ( $site_knowledge['last_error_at'] ?? '' ) ) ); ?></td>
					</tr>
					<?php endif; ?>
					<?php if ( $wp_cron_disabled ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'WP-Cron disabled', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo $wp_cron_disabled ? esc_html__( 'yes', 'npcink-cloud-addon' ) : esc_html__( 'no', 'npcink-cloud-addon' ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Manual flush command', 'npcink-cloud-addon' ); ?></th>
						<td><code><?php echo esc_html( self::format_empty( (string) ( $site_knowledge['wp_cli_command'] ?? $site_knowledge['cron_command'] ?? '' ) ) ); ?></code></td>
					</tr>
					<?php endif; ?>
				</tbody>
			</table>
			<?php
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
		 * Renders local monitoring upload problems for the service detail tab.
		 *
		 * @param array<string,mixed> $monitoring Monitoring status.
		 * @return void
		 */
		private static function render_status_monitoring_quality( array $monitoring ): void {
			if ( absint( $monitoring['buffer_count'] ?? 0 ) < 1 && '' === (string) ( $monitoring['last_upload_error'] ?? '' ) ) {
				return;
			}

			?>
			<h3><?php esc_html_e( 'Monitoring needs attention', 'npcink-cloud-addon' ); ?></h3>
			<?php
			self::render_monitoring_summary( $monitoring );
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
				|| '' !== (string) ( $summary['state'] ?? '' );
		}

		/**
		 * Renders monitoring status.
		 *
		 * @param array<string,mixed> $monitoring Monitoring status.
		 * @return void
		 */
		private static function render_monitoring_summary( array $monitoring ): void {
			$is_enabled = ! empty( $monitoring['enabled'] );
			$last_upload_error = (string) ( $monitoring['last_upload_error'] ?? '' );
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
						<?php if ( '' !== $last_upload_error ) : ?>
						<tr>
							<th scope="row"><?php esc_html_e( 'Last upload error', 'npcink-cloud-addon' ); ?></th>
							<td><?php echo esc_html( $last_upload_error ); ?></td>
						</tr>
						<?php endif; ?>
					</tbody>
				</table>
				<?php
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
							<th scope="row"><?php esc_html_e( 'Credits', 'npcink-cloud-addon' ); ?></th>
							<td>
								<?php
								printf(
									/* translators: 1: used credits, 2: credit limit, 3: remaining credits. */
									esc_html__( '%1$s used / %2$s limit / %3$s remaining', 'npcink-cloud-addon' ),
									esc_html( self::format_credit_amount( $usage['used'] ?? 0, (string) ( $usage['unit'] ?? 'credit' ) ) ),
									esc_html( self::format_credit_amount( $usage['limit'] ?? 0, (string) ( $usage['unit'] ?? 'credit' ) ) ),
									esc_html( self::format_credit_amount( $usage['remaining'] ?? null, (string) ( $usage['unit'] ?? 'credit' ) ) )
								);
								?>
							</td>
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
			if ( empty( $summary['available'] ) || empty( $runtime['feature_id'] ) ) {
				return;
			}
			?>
			<h3><?php esc_html_e( 'Pro Cloud Runtime', 'npcink-cloud-addon' ); ?></h3>
			<table class="widefat striped" style="max-width: 860px;">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Nightly runs', 'npcink-cloud-addon' ); ?></th>
						<td>
							<?php
							echo esc_html( self::format_runtime_quota_projection( $runtime ) );
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Retention', 'npcink-cloud-addon' ); ?></th>
						<td>
							<?php
							echo esc_html( self::format_runtime_days_projection( $runtime, 'result_retention_days' ) );
							?>
						</td>
					</tr>
				</tbody>
			</table>
			<?php
		}

		/**
		 * Renders low-frequency connector details.
		 *
		 * @param array<string,mixed> $state Credential state.
		 * @return void
		 */
		private static function render_advanced_information( array $state ): void {
			$last_failure = (string) ( $state['last_verification_error'] ?? '' );
			if ( '' === $last_failure ) {
				return;
			}
			?>
			<h3><?php esc_html_e( 'Connection status', 'npcink-cloud-addon' ); ?></h3>
			<table class="widefat striped">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Cloud error classification', 'npcink-cloud-addon' ); ?></th>
						<td><code><?php echo esc_html( (string) $state['code'] ); ?></code></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Last failure', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( $last_failure ); ?></td>
					</tr>
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
		 * @param string $view Optional tab subview.
		 * @return void
		 */
		private static function redirect_to_page( string $tab = '', string $view = '' ): void {
			$url = self::page_url();
			if ( '' !== $tab ) {
				$url = add_query_arg( 'tab', sanitize_key( $tab ), $url );
			}
			if ( '' !== $view ) {
				$url = add_query_arg( 'view', sanitize_key( $view ), $url );
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

	}
}
