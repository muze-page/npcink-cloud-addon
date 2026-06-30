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
		private const ACTION_DISCONNECT = 'npcink_cloud_addon_disconnect';
		private const ACTION_REFRESH_MONITORING = 'npcink_cloud_addon_refresh_monitoring';
		private const ACTION_REFRESH_SITE_KNOWLEDGE = 'npcink_cloud_addon_refresh_site_knowledge';
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
			add_action( 'admin_post_' . self::ACTION_DISCONNECT, array( __CLASS__, 'handle_disconnect' ) );
			add_action( 'admin_post_' . self::ACTION_REFRESH_MONITORING, array( __CLASS__, 'handle_refresh_monitoring' ) );
			add_action( 'admin_post_' . self::ACTION_REFRESH_SITE_KNOWLEDGE, array( __CLASS__, 'handle_refresh_site_knowledge' ) );
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
			self::ensure_parent_menu();

			add_submenu_page(
				self::PARENT_MENU_SLUG,
				__( 'Npcink Cloud Addon', 'npcink-cloud-addon' ),
				__( 'Cloud Addon', 'npcink-cloud-addon' ),
				self::MENU_CAPABILITY,
				self::PAGE_SLUG,
				array( __CLASS__, 'render' ),
				50
			);
		}

		/**
		 * Ensures the shared Npcink parent menu exists.
		 *
		 * @return void
		 */
		private static function ensure_parent_menu(): void {
			if ( self::has_parent_menu() ) {
				return;
			}

			add_menu_page(
				__( 'Npcink', 'npcink-cloud-addon' ),
				__( 'Npcink', 'npcink-cloud-addon' ),
				self::MENU_CAPABILITY,
				self::PARENT_MENU_SLUG,
				array( __CLASS__, 'render_overview' ),
				'dashicons-superhero',
				58
			);

			add_submenu_page(
				self::PARENT_MENU_SLUG,
				__( 'Npcink Overview', 'npcink-cloud-addon' ),
				__( 'Overview', 'npcink-cloud-addon' ),
				self::MENU_CAPABILITY,
				self::PARENT_MENU_SLUG,
				array( __CLASS__, 'render_overview' ),
				0
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
		 * Renders the shared Npcink overview page.
		 *
		 * @return void
		 */
		public static function render_overview(): void {
			if ( ! current_user_can( self::MENU_CAPABILITY ) ) {
				wp_die( esc_html__( 'You do not have permission to manage Npcink settings.', 'npcink-cloud-addon' ) );
			}
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'Npcink', 'npcink-cloud-addon' ); ?></h1>
				<p><?php esc_html_e( 'Local WordPress entry points for Npcink governance, connections, cloud access, and ability packages.', 'npcink-cloud-addon' ); ?></p>
				<h2><?php esc_html_e( 'Installed Surfaces', 'npcink-cloud-addon' ); ?></h2>
				<table class="widefat striped" style="max-width: 860px;">
					<tbody>
						<?php
						self::render_overview_row( __( 'Core', 'npcink-cloud-addon' ), __( 'Review proposals, approval decisions, commit preflight, audit, and Core app keys.', 'npcink-cloud-addon' ), 'npcink-governance-core' );
						self::render_overview_row( __( 'Adapter', 'npcink-cloud-addon' ), __( 'Connect OpenClaw through the Adapter surface.', 'npcink-cloud-addon' ), 'npcink-openclaw-adapter' );
						self::render_overview_row( __( 'Abilities', 'npcink-cloud-addon' ), __( 'Verify WordPress Abilities API packages and demo ability controls.', 'npcink-cloud-addon' ), 'npcink-abilities-toolkit' );
						self::render_overview_row( __( 'Cloud Addon', 'npcink-cloud-addon' ), __( 'Connect this site to Npcink Cloud without moving local control-plane truth.', 'npcink-cloud-addon' ), self::PAGE_SLUG );
						?>
					</tbody>
				</table>
			</div>
			<?php
		}

		/**
		 * Renders one overview row.
		 *
		 * @param string $label       Row label.
		 * @param string $description Row description.
		 * @param string $slug        Menu page slug.
		 * @return void
		 */
		private static function render_overview_row( string $label, string $description, string $slug ): void {
			?>
			<tr>
				<th scope="row"><?php echo esc_html( $label ); ?></th>
				<td><?php echo esc_html( $description ); ?></td>
				<td>
					<?php if ( self::is_submenu_registered( $slug ) ) : ?>
						<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $slug ) ); ?>"><?php esc_html_e( 'Open', 'npcink-cloud-addon' ); ?></a>
					<?php else : ?>
						<span style="color: #646970;"><?php esc_html_e( 'Not installed', 'npcink-cloud-addon' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
			<?php
		}

		/**
		 * Returns whether a Npcink submenu has been registered.
		 *
		 * @param string $slug Menu page slug.
		 * @return bool
		 */
		private static function is_submenu_registered( string $slug ): bool {
			global $submenu;

			foreach ( (array) ( $submenu[ self::PARENT_MENU_SLUG ] ?? array() ) as $item ) {
				if ( isset( $item[2] ) && $slug === $item[2] ) {
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

			$payload = array(
				'base_url'           => $base_url,
				'api_key'            => $api_key,
				'timeout'            => $timeout,
				'monitoring_enabled' => $monitoring_enabled,
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

			self::redirect_to_page( 'status' );
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
				self::redirect_to_page( 'details' );
			}

			$upload = array( 'buffer_count' => Npcink_Cloud_Observability_Collector::get_status()['buffer_count'] ?? 0 );
			if ( Npcink_Cloud_Addon_Settings::is_monitoring_enabled() ) {
				$upload = Npcink_Cloud_Observability_Collector::flush_buffer();
				if ( empty( $upload['last_upload_ok'] ) ) {
					$message = sanitize_text_field( (string) ( $upload['last_upload_error'] ?? '' ) );
					self::set_admin_notice( 'error', '' !== $message ? $message : __( 'Monitoring upload failed.', 'npcink-cloud-addon' ) );
					self::redirect_to_page( 'details' );
				}

				$summary = Npcink_Cloud_Observability_Collector::refresh_summary();
				if ( empty( $summary['last_refresh_ok'] ) ) {
					$message = sanitize_text_field( (string) ( $summary['last_refresh_error'] ?? '' ) );
					self::set_admin_notice( 'error', '' !== $message ? $message : __( 'Monitoring summary refresh failed.', 'npcink-cloud-addon' ) );
					self::redirect_to_page( 'details' );
				}
			}

			$quality = Npcink_Cloud_Observability_Collector::refresh_agent_feedback_summary();
			if ( empty( $quality['last_refresh_ok'] ) ) {
				$message = sanitize_text_field( (string) ( $quality['last_refresh_error'] ?? '' ) );
				self::set_admin_notice( 'error', '' !== $message ? $message : __( 'Agent quality summary refresh failed.', 'npcink-cloud-addon' ) );
				self::redirect_to_page( 'details' );
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
			self::redirect_to_page( 'details' );
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
				self::redirect_to_page( 'details' );
			}

			Npcink_Cloud_Site_Knowledge_Change_Bridge::buffer_recent_public_content();
			$status = Npcink_Cloud_Site_Knowledge_Change_Bridge::flush_buffer();
			if ( empty( $status['last_delivery_ok'] ) ) {
				$message = sanitize_text_field( (string) ( $status['last_delivery_error'] ?? '' ) );
				self::set_admin_notice( 'error', '' !== $message ? $message : __( 'Site Knowledge refresh request failed.', 'npcink-cloud-addon' ) );
				self::redirect_to_page( 'details' );
			}

			self::set_admin_notice(
				'success',
				sprintf(
					/* translators: %d: sent public content count. */
					__( 'Site Knowledge refresh requested. Public content items sent: %d.', 'npcink-cloud-addon' ),
					absint( $status['last_sent_count'] ?? 0 )
				)
			);
			self::redirect_to_page( 'details' );
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
			$active_tab = self::get_active_tab( $is_verified );
			?>
			<div class="wrap npcink-cloud-addon">
				<h1><?php esc_html_e( 'Npcink Cloud Addon', 'npcink-cloud-addon' ); ?></h1>
				<p><?php esc_html_e( 'Cloud connector status and access settings for this WordPress site.', 'npcink-cloud-addon' ); ?></p>
				<?php self::render_admin_notice(); ?>

				<?php self::render_connection_summary( $settings, $state, $entitlement ); ?>
				<?php self::render_tab_navigation( $active_tab, $is_verified ); ?>

				<?php if ( 'connect' === $active_tab ) : ?>
					<section class="npcink-cloud-section npcink-cloud-tab-panel">
						<h2><?php esc_html_e( 'Connect this site', 'npcink-cloud-addon' ); ?></h2>
						<?php self::render_cloud_authorization_panel( $settings, $state ); ?>
					</section>
				<?php elseif ( 'status' === $active_tab ) : ?>
					<section class="npcink-cloud-section npcink-cloud-tab-panel">
						<h2><?php esc_html_e( 'Status', 'npcink-cloud-addon' ); ?></h2>
						<?php self::render_status_overview( $settings, $state, $entitlement, $monitoring, $site_knowledge, $is_verified ); ?>
					</section>
				<?php elseif ( 'diagnostics' === $active_tab ) : ?>
					<section class="npcink-cloud-section npcink-cloud-tab-panel">
						<h2><?php esc_html_e( 'Diagnostics', 'npcink-cloud-addon' ); ?></h2>
						<?php self::render_diagnostics( $settings, $state, $entitlement, $monitoring, $site_knowledge, $is_verified ); ?>
					</section>
				<?php elseif ( 'advanced' === $active_tab ) : ?>
					<section class="npcink-cloud-section npcink-cloud-tab-panel">
						<h2><?php esc_html_e( 'Advanced Information', 'npcink-cloud-addon' ); ?></h2>
						<?php self::render_advanced_information( $settings, $state, $entitlement ); ?>
						<?php self::render_connection_management( $settings ); ?>
						<details class="npcink-cloud-disclosure">
							<summary>
								<strong><?php esc_html_e( 'Manual connection fallback', 'npcink-cloud-addon' ); ?></strong>
								<span><?php esc_html_e( 'Use only for local debugging or when Cloud authorization is unavailable.', 'npcink-cloud-addon' ); ?></span>
							</summary>
							<?php self::render_settings_form( $settings ); ?>
						</details>
					</section>
				<?php elseif ( 'details' === $active_tab ) : ?>
					<section class="npcink-cloud-section npcink-cloud-tab-panel">
						<h2><?php esc_html_e( 'Details', 'npcink-cloud-addon' ); ?></h2>
						<h3><?php esc_html_e( 'Entitlement Summary', 'npcink-cloud-addon' ); ?></h3>
						<?php self::render_entitlement_summary( $entitlement, $is_verified ); ?>
						<h3><?php esc_html_e( 'Site Knowledge', 'npcink-cloud-addon' ); ?></h3>
						<?php self::render_site_knowledge_summary( $site_knowledge, $settings, $is_verified ); ?>
						<h3><?php esc_html_e( 'Monitoring & Quality', 'npcink-cloud-addon' ); ?></h3>
						<?php self::render_monitoring_summary( $monitoring ); ?>
					</section>
				<?php endif; ?>
			</div>
			<?php
		}

		/**
		 * Returns the active settings tab.
		 *
		 * @param bool $is_verified Whether the connector has verified credentials.
		 * @return string
		 */
		private static function get_active_tab( bool $is_verified ): string {
			$tabs = self::get_tab_labels( $is_verified );
			$default = $is_verified ? 'status' : 'connect';
			$raw_tab = filter_input( INPUT_GET, 'tab', FILTER_UNSAFE_RAW );
			$requested = is_string( $raw_tab ) ? sanitize_key( wp_unslash( $raw_tab ) ) : '';

			return isset( $tabs[ $requested ] ) ? $requested : $default;
		}

		/**
		 * Returns available tab labels.
		 *
		 * @param bool $is_verified Whether the connector has verified credentials.
		 * @return array<string,string>
		 */
		private static function get_tab_labels( bool $is_verified ): array {
			if ( $is_verified ) {
				return array(
					'status'   => __( 'Status', 'npcink-cloud-addon' ),
					'diagnostics' => __( 'Diagnostics', 'npcink-cloud-addon' ),
					'details'  => __( 'Details', 'npcink-cloud-addon' ),
					'advanced' => __( 'Advanced', 'npcink-cloud-addon' ),
				);
			}

			return array(
				'connect'  => __( 'Connect', 'npcink-cloud-addon' ),
				'advanced' => __( 'Advanced', 'npcink-cloud-addon' ),
			);
		}

		/**
		 * Renders settings tab navigation.
		 *
		 * @param string $active_tab Active tab slug.
		 * @param bool   $is_verified Whether the connector has verified credentials.
		 * @return void
		 */
		private static function render_tab_navigation( string $active_tab, bool $is_verified ): void {
			$tabs = self::get_tab_labels( $is_verified );
			?>
			<nav class="nav-tab-wrapper npcink-cloud-tabs" aria-label="<?php esc_attr_e( 'Cloud addon sections', 'npcink-cloud-addon' ); ?>">
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
						class="nav-tab<?php echo $is_active ? ' nav-tab-active' : ''; ?>"
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
		 * Builds a Cloud Portal URL for authorizing this WordPress site.
		 *
		 * @param array<string,mixed> $settings Stored settings.
		 * @return string
		 */
		private static function build_authorization_url( array $settings ): string {
			$base_url = Npcink_Cloud_Addon_Settings::get_effective_base_url( $settings );
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
						<?php self::render_connection_actions( $settings ); ?>
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
		 * Renders connection-level actions.
		 *
		 * @param array<string,mixed> $settings Stored settings.
		 * @return void
		 */
		private static function render_connection_actions( array $settings ): void {
			?>
			<div class="npcink-cloud-summary__actions">
				<?php self::render_reverify_form( $settings ); ?>
				<a class="button button-secondary" href="<?php echo esc_url( untrailingslashit( Npcink_Cloud_Addon_Settings::get_effective_base_url( $settings ) ) . '/portal/sites' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open Cloud sites', 'npcink-cloud-addon' ); ?></a>
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
			<h3><?php esc_html_e( 'Connection management', 'npcink-cloud-addon' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Use Cloud for account-level connection changes. Local disconnect only clears this WordPress site.', 'npcink-cloud-addon' ); ?></p>
			<div class="npcink-cloud-summary__actions npcink-cloud-summary__actions--start">
				<a class="button button-secondary" href="<?php echo esc_url( self::build_authorization_url( $settings ) ); ?>"><?php esc_html_e( 'Change connection in Cloud', 'npcink-cloud-addon' ); ?></a>
				<?php self::render_disconnect_form(); ?>
			</div>
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
						<tr>
							<th scope="row"><?php esc_html_e( 'Monitoring', 'npcink-cloud-addon' ); ?></th>
							<td>
								<label for="npcink-cloud-monitoring-enabled">
									<input
										type="checkbox"
										id="npcink-cloud-monitoring-enabled"
										name="monitoring_enabled"
										value="1"
										<?php checked( ! empty( $settings['monitoring_enabled'] ) ); ?>
									/>
									<?php esc_html_e( 'Enable Cloud monitoring for installed Npcink plugins.', 'npcink-cloud-addon' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'The addon collects local metadata events only: plugin, event kind, status, timing, ids, and error codes. Prompts, content, results, secrets, and raw request payloads are not collected.', 'npcink-cloud-addon' ); ?></p>
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

			$runtime = is_array( $entitlement['pro_cloud_runtime'] ?? null ) ? $entitlement['pro_cloud_runtime'] : array();
			$credit_detail = is_array( $entitlement['credit_usage_detail'] ?? null ) ? $entitlement['credit_usage_detail'] : array();
			$links = is_array( $entitlement['links'] ?? null ) ? $entitlement['links'] : array();
			$usage_url = esc_url( (string) ( $links['usage_url'] ?? '' ) );
			?>
			<p class="description"><?php esc_html_e( 'Read-only Cloud connection and service status for this addon. Product actions, provider tools, approvals, and WordPress writes stay in their owning surfaces.', 'npcink-cloud-addon' ); ?></p>
			<div class="npcink-cloud-summary__actions npcink-cloud-summary__actions--start" style="margin: 12px 0;">
				<?php self::render_reverify_form( $settings ); ?>
				<a class="button button-secondary" href="<?php echo esc_url( untrailingslashit( Npcink_Cloud_Addon_Settings::get_effective_base_url( $settings ) ) . '/portal/sites' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open Cloud status detail', 'npcink-cloud-addon' ); ?></a>
			</div>
			<table class="widefat striped" style="max-width: 980px;">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Check', 'npcink-cloud-addon' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'npcink-cloud-addon' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Detail', 'npcink-cloud-addon' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php self::render_diagnostic_row( __( 'Cloud Base URL', 'npcink-cloud-addon' ), self::diagnostic_status( '' !== (string) ( $settings['base_url'] ?? '' ), __( 'saved', 'npcink-cloud-addon' ), __( 'missing', 'npcink-cloud-addon' ) ), self::format_setting_value( (string) ( $settings['base_url'] ?? '' ), __( 'Not set', 'npcink-cloud-addon' ) ) ); ?>
					<?php self::render_diagnostic_row( __( 'Cloud API Key', 'npcink-cloud-addon' ), self::diagnostic_status( ! empty( $state['configured'] ), __( 'saved', 'npcink-cloud-addon' ), __( 'missing', 'npcink-cloud-addon' ) ), __( 'Stored server-side only. Split signing credentials are not displayed.', 'npcink-cloud-addon' ) ); ?>
					<?php self::render_diagnostic_row( __( 'Cloud liveness', 'npcink-cloud-addon' ), self::diagnostic_status( ! empty( $state['verified'] ), __( 'verified', 'npcink-cloud-addon' ), __( 'not verified', 'npcink-cloud-addon' ) ), sprintf( /* translators: %s: last verification time. */ __( 'Last checked: %s', 'npcink-cloud-addon' ), self::format_datetime_value( (string) ( $settings['verified_at'] ?? '' ), __( 'Never', 'npcink-cloud-addon' ) ) ) ); ?>
					<?php self::render_diagnostic_row( __( 'Signed Cloud read', 'npcink-cloud-addon' ), self::format_entitlement_availability( $entitlement, $is_verified ), self::format_empty( (string) ( $entitlement['message'] ?? '' ) ) ); ?>
					<?php self::render_diagnostic_row( __( 'Entitlement and quota', 'npcink-cloud-addon' ), self::diagnostic_status( ! empty( $entitlement['available'] ), __( 'available', 'npcink-cloud-addon' ), __( 'not refreshed', 'npcink-cloud-addon' ) ), self::format_package_label( $entitlement, $is_verified ) ); ?>
					<?php self::render_diagnostic_row( __( 'Hosted Runtime', 'npcink-cloud-addon' ), self::diagnostic_status( ! empty( $runtime['feature_id'] ), __( 'reported', 'npcink-cloud-addon' ), __( 'not returned', 'npcink-cloud-addon' ) ), self::format_hosted_runtime_diagnostic_detail( $runtime ) ); ?>
					<?php self::render_diagnostic_row( __( 'Platform Models and provider readiness', 'npcink-cloud-addon' ), __( 'Cloud-owned', 'npcink-cloud-addon' ), __( 'No addon read contract is currently connected. Use Cloud status detail for provider-level readiness.', 'npcink-cloud-addon' ) ); ?>
					<?php self::render_diagnostic_row( __( 'Cloud web search', 'npcink-cloud-addon' ), __( 'Not connected', 'npcink-cloud-addon' ), __( 'No Cloud Addon status API is currently contracted for web search capability.', 'npcink-cloud-addon' ) ); ?>
					<?php self::render_diagnostic_row( __( 'Cloud image generation', 'npcink-cloud-addon' ), __( 'Scene runtime only', 'npcink-cloud-addon' ), __( 'Available through the WordPress AI image scene after verification; provider and source detail stay Cloud-owned.', 'npcink-cloud-addon' ) ); ?>
					<?php self::render_diagnostic_row( __( 'Image source search', 'npcink-cloud-addon' ), __( 'Not connected', 'npcink-cloud-addon' ), __( 'Tavily, Unsplash, and other product search tools are not Cloud Addon admin actions.', 'npcink-cloud-addon' ) ); ?>
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
			<details class="npcink-cloud-disclosure">
				<summary>
					<strong><?php esc_html_e( 'Advanced raw status', 'npcink-cloud-addon' ); ?></strong>
					<span><?php esc_html_e( 'Sanitized local status fields only; secrets and split credentials are omitted.', 'npcink-cloud-addon' ); ?></span>
				</summary>
				<table class="widefat striped" style="max-width: 980px;">
					<tbody>
						<?php self::render_diagnostic_row( __( 'Connection code', 'npcink-cloud-addon' ), (string) ( $state['code'] ?? '' ), self::format_empty( (string) ( $state['last_verification_error'] ?? '' ) ) ); ?>
						<?php self::render_diagnostic_row( __( 'Entitlement state', 'npcink-cloud-addon' ), (string) ( $entitlement['state'] ?? '' ), sprintf( /* translators: %s: entitlement cache freshness time. */ __( 'Fresh until: %s', 'npcink-cloud-addon' ), self::format_datetime_value( (string) ( $entitlement['fresh_until'] ?? '' ) ) ) ); ?>
						<?php self::render_diagnostic_row( __( 'Credit policy', 'npcink-cloud-addon' ), (string) ( $credit_detail['local_addon_policy'] ?? 'summary_and_link_only' ), __( 'Detailed ledger remains in Cloud.', 'npcink-cloud-addon' ) ); ?>
						<?php self::render_diagnostic_row( __( 'Runtime local truth', 'npcink-cloud-addon' ), (string) ( $runtime['local_truth']['final_write_path'] ?? 'core_proposal_required' ), __( 'The addon does not own final WordPress writes.', 'npcink-cloud-addon' ) ); ?>
					</tbody>
				</table>
			</details>
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
		 * Renders the default Cloud authorization entry.
		 *
		 * @param array<string,mixed> $settings Stored settings.
		 * @param array<string,mixed> $state Credential state.
		 * @return void
		 */
		private static function render_cloud_authorization_panel( array $settings, array $state ): void {
			$base_url = Npcink_Cloud_Addon_Settings::get_effective_base_url( $settings );
			?>
			<table class="widefat striped" style="max-width: 860px;">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Connection', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( (string) ( $state['label'] ?? '' ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Cloud', 'npcink-cloud-addon' ); ?></th>
						<td><code><?php echo esc_html( $base_url ); ?></code></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Current site', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( home_url( '/' ) ); ?></td>
					</tr>
				</tbody>
			</table>
			<p class="npcink-cloud-section-action">
				<a class="button button-primary button-hero" href="<?php echo esc_url( self::build_authorization_url( $settings ) ); ?>">
					<?php esc_html_e( 'Add this site in Npcink Cloud', 'npcink-cloud-addon' ); ?>
				</a>
			</p>
			<p class="description"><?php esc_html_e( 'Cloud will create or activate this site connection and return here with a one-time authorization code.', 'npcink-cloud-addon' ); ?></p>
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
			?>
			<form class="npcink-cloud-verify-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin: 0 0 12px;">
				<?php wp_nonce_field( self::ACTION_REFRESH_SITE_KNOWLEDGE ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_REFRESH_SITE_KNOWLEDGE ); ?>" />
				<button type="submit" class="button button-secondary"><?php esc_html_e( 'Request public content refresh', 'npcink-cloud-addon' ); ?></button>
				<a class="button button-secondary" href="<?php echo esc_url( $base_url . '/portal/site-knowledge' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open Cloud Site Knowledge', 'npcink-cloud-addon' ); ?></a>
			</form>
			<table class="widefat striped" style="max-width: 860px;">
				<tbody>
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
						<td><?php echo esc_html( self::format_empty( (string) ( $site_knowledge['last_delivery_error'] ?? '' ) ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Next flush', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_datetime_value( (string) ( $site_knowledge['next_flush_at'] ?? '' ) ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Boundary', 'npcink-cloud-addon' ); ?></th>
						<td><?php esc_html_e( 'This addon only sends public change hints and signed runtime requests. Cloud owns indexing, freshness policy, collection lifecycle, and diagnostics detail.', 'npcink-cloud-addon' ); ?></td>
					</tr>
				</tbody>
			</table>
			<p class="description"><?php esc_html_e( 'Toolbox uses Site Knowledge results in best-practice buttons. Index lifecycle controls and deep troubleshooting remain Cloud-owned.', 'npcink-cloud-addon' ); ?></p>
			<?php
		}

		/**
		 * Renders monitoring status.
		 *
		 * @param array<string,mixed> $monitoring Monitoring status.
		 * @return void
		 */
		private static function render_monitoring_summary( array $monitoring ): void {
			$plugins = is_array( $monitoring['plugins'] ?? null ) ? $monitoring['plugins'] : array();
			$remote = is_array( $monitoring['remote_summary'] ?? null ) ? $monitoring['remote_summary'] : array();
			$summary = is_array( $remote['summary'] ?? null ) ? $remote['summary'] : array();
			$agent_feedback = is_array( $monitoring['agent_feedback_summary'] ?? null ) ? $monitoring['agent_feedback_summary'] : array();
			$agent_summary = is_array( $agent_feedback['summary'] ?? null ) ? $agent_feedback['summary'] : array();
			$totals = is_array( $summary['totals'] ?? null ) ? $summary['totals'] : array();
			$cloud_plugins = is_array( $summary['plugins'] ?? null ) ? $summary['plugins'] : array();
			$recent_errors = is_array( $summary['recent_errors'] ?? null ) ? $summary['recent_errors'] : array();
			?>
				<form class="npcink-cloud-verify-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin: 0 0 12px;">
					<?php wp_nonce_field( self::ACTION_REFRESH_MONITORING ); ?>
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_REFRESH_MONITORING ); ?>" />
					<button type="submit" class="button button-secondary"><?php esc_html_e( 'Refresh monitoring and quality', 'npcink-cloud-addon' ); ?></button>
				</form>
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
				<details class="npcink-cloud-disclosure">
					<summary>
						<strong><?php esc_html_e( 'Advanced diagnostics', 'npcink-cloud-addon' ); ?></strong>
						<span><?php esc_html_e( 'Local buffers, Cloud aggregate metrics, plugin signals, and recent error codes.', 'npcink-cloud-addon' ); ?></span>
					</summary>
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
				</details>
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
			<?php self::render_credit_usage_summary( $summary ); ?>
			<?php self::render_pro_cloud_runtime_summary( $summary ); ?>
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
			<h3><?php esc_html_e( 'AI Credit Usage', 'npcink-cloud-addon' ); ?></h3>
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
			<?php if ( '' !== $usage_url ) : ?>
				<p class="npcink-cloud-section-action">
					<a class="button button-secondary" href="<?php echo esc_url( $usage_url ); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'View credit details in Cloud', 'npcink-cloud-addon' ); ?>
					</a>
				</p>
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
							printf(
								/* translators: 1: used runs, 2: max runs, 3: remaining runs. */
								esc_html__( '%1$d used / %2$d limit / %3$d remaining', 'npcink-cloud-addon' ),
								absint( $runtime['used_nightly_inspection_runs'] ?? 0 ),
								absint( $runtime['max_nightly_inspection_runs_per_period'] ?? 0 ),
								absint( $runtime['remaining_nightly_inspection_runs'] ?? 0 )
							);
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Batch limit', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( (string) absint( $runtime['max_batch_items'] ?? 0 ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Retention', 'npcink-cloud-addon' ); ?></th>
						<td>
							<?php
							printf(
								/* translators: %d: retention days. */
								esc_html__( '%d days', 'npcink-cloud-addon' ),
								absint( $runtime['result_retention_days'] ?? 0 )
							);
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Quota exhausted', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo ! empty( $runtime['quota_exhausted'] ) ? esc_html__( 'yes', 'npcink-cloud-addon' ) : esc_html__( 'no', 'npcink-cloud-addon' ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Local truth', 'npcink-cloud-addon' ); ?></th>
						<td><code><?php echo esc_html( (string) ( $local_truth['final_write_path'] ?? 'core_proposal_required' ) ); ?></code></td>
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
			?>
			<p><?php esc_html_e( 'Timeout, verification failure, and entitlement message.', 'npcink-cloud-addon' ); ?></p>
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
					<tr>
						<th scope="row"><?php esc_html_e( 'Entitlement message', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_empty( (string) ( $entitlement['message'] ?? '' ) ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Entitlement state', 'npcink-cloud-addon' ); ?></th>
						<td><code><?php echo esc_html( self::format_empty( (string) ( $entitlement['state'] ?? '' ) ) ); ?></code></td>
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
		 * Redirects back to the page.
		 *
		 * @return void
		 */
		private static function redirect_to_page( string $tab = '' ): void {
			$url = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
			if ( '' !== $tab ) {
				$url = add_query_arg( 'tab', sanitize_key( $tab ), $url );
			}

			wp_safe_redirect( $url );
			exit;
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
				$status,
				absint( $site_knowledge['buffer_count'] ?? 0 )
			);
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
