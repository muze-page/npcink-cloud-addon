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
		private const ACTION_REFRESH_MONITORING = 'npcink_cloud_addon_refresh_monitoring';
		private const DATETIME_DISPLAY_FORMAT = 'Y-m-d H:i:s';

		/**
		 * Registers admin hooks.
		 *
		 * @return void
		 */
		public static function register(): void {
			add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ), 50 );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
			add_action( 'admin_post_' . self::ACTION_SAVE, array( __CLASS__, 'handle_save' ) );
			add_action( 'admin_post_' . self::ACTION_REFRESH_MONITORING, array( __CLASS__, 'handle_refresh_monitoring' ) );
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

			Npcink_Cloud_Addon_Settings::write_settings( $settings );

			$client = new Npcink_Cloud_Runtime_Client( $settings );
			$probe = $client->probe_connectivity();
			if ( ! empty( $probe['ok'] ) ) {
				Npcink_Cloud_Addon_Settings::mark_verification_result( true, '' );
				Npcink_Cloud_Observability_Collector::sync_schedule();
				Npcink_Cloud_Entitlement_Summary::refresh();
				self::set_admin_notice( 'success', __( 'Cloud settings saved and verified.', 'npcink-cloud-addon' ) );
			} else {
				$message = self::format_probe_failure_message( $probe );
				Npcink_Cloud_Addon_Settings::mark_verification_result( false, $message );
				Npcink_Cloud_Observability_Collector::sync_schedule();
				self::set_admin_notice( 'error', $message );
			}

			self::redirect_to_page();
		}

		/**
		 * Handles manual monitoring upload and summary refresh.
		 *
		 * @return void
		 */
		public static function handle_refresh_monitoring(): void {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to manage Npcink Cloud settings.', 'npcink-cloud-addon' ) );
			}

			check_admin_referer( self::ACTION_REFRESH_MONITORING );

			if ( ! Npcink_Cloud_Addon_Settings::is_monitoring_enabled() ) {
				self::set_admin_notice( 'error', __( 'Monitoring is disabled or Cloud is not verified.', 'npcink-cloud-addon' ) );
				self::redirect_to_page( 'monitoring' );
			}

			$upload = Npcink_Cloud_Observability_Collector::flush_buffer();
			if ( empty( $upload['last_upload_ok'] ) ) {
				$message = sanitize_text_field( (string) ( $upload['last_upload_error'] ?? '' ) );
				self::set_admin_notice( 'error', '' !== $message ? $message : __( 'Monitoring upload failed.', 'npcink-cloud-addon' ) );
				self::redirect_to_page( 'monitoring' );
			}

			$summary = Npcink_Cloud_Observability_Collector::refresh_summary();
			if ( empty( $summary['last_refresh_ok'] ) ) {
				$message = sanitize_text_field( (string) ( $summary['last_refresh_error'] ?? '' ) );
				self::set_admin_notice( 'error', '' !== $message ? $message : __( 'Monitoring summary refresh failed.', 'npcink-cloud-addon' ) );
				self::redirect_to_page( 'monitoring' );
			}

			$remaining = absint( $upload['buffer_count'] ?? 0 );
			self::set_admin_notice(
				'success',
				sprintf(
					/* translators: %d: remaining buffered event count. */
					__( 'Monitoring refreshed. Remaining buffered events: %d.', 'npcink-cloud-addon' ),
					$remaining
				)
			);
			self::redirect_to_page( 'monitoring' );
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
			$entitlement = Npcink_Cloud_Entitlement_Summary::get_summary();
			$monitoring = Npcink_Cloud_Observability_Collector::get_status();
			$is_verified = ! empty( $state['verified'] );
			$active_tab = self::get_active_tab( $is_verified );
			?>
			<div class="wrap npcink-cloud-addon">
				<h1><?php esc_html_e( 'Npcink Cloud Addon', 'npcink-cloud-addon' ); ?></h1>
				<p><?php esc_html_e( 'Cloud connector status and access settings for this WordPress site.', 'npcink-cloud-addon' ); ?></p>
				<?php self::render_admin_notice(); ?>

				<?php self::render_connection_summary( $settings, $state, $entitlement ); ?>
				<?php self::render_tab_navigation( $active_tab, $is_verified ); ?>

				<?php if ( 'entitlement' === $active_tab ) : ?>
					<section class="npcink-cloud-section npcink-cloud-tab-panel">
						<h2><?php esc_html_e( 'Entitlement Summary', 'npcink-cloud-addon' ); ?></h2>
						<?php self::render_entitlement_summary( $entitlement, $is_verified ); ?>
					</section>
				<?php elseif ( 'monitoring' === $active_tab ) : ?>
					<section class="npcink-cloud-section npcink-cloud-tab-panel">
						<h2><?php esc_html_e( 'Monitoring', 'npcink-cloud-addon' ); ?></h2>
						<?php self::render_monitoring_summary( $monitoring ); ?>
					</section>
				<?php elseif ( 'advanced' === $active_tab ) : ?>
					<section class="npcink-cloud-section npcink-cloud-tab-panel">
						<h2><?php esc_html_e( 'Advanced Information', 'npcink-cloud-addon' ); ?></h2>
						<?php self::render_advanced_information( $settings, $state, $entitlement ); ?>
					</section>
				<?php else : ?>
					<section class="npcink-cloud-section npcink-cloud-tab-panel">
						<h2><?php esc_html_e( 'Cloud Settings', 'npcink-cloud-addon' ); ?></h2>
						<?php if ( $is_verified ) : ?>
							<p><?php esc_html_e( 'Update the connector or replace the stored key.', 'npcink-cloud-addon' ); ?></p>
						<?php else : ?>
							<p><?php esc_html_e( 'Save a Cloud Base URL and Cloud API Key to verify this connector.', 'npcink-cloud-addon' ); ?></p>
						<?php endif; ?>
						<?php self::render_settings_form( $settings ); ?>
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
			$default = $is_verified ? 'entitlement' : 'settings';
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
					'entitlement' => __( 'Entitlement', 'npcink-cloud-addon' ),
					'monitoring'  => __( 'Monitoring', 'npcink-cloud-addon' ),
					'settings'    => __( 'Settings', 'npcink-cloud-addon' ),
					'advanced'    => __( 'Advanced', 'npcink-cloud-addon' ),
				);
			}

			return array(
				'settings' => __( 'Settings', 'npcink-cloud-addon' ),
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
						<?php self::render_reverify_form( $settings ); ?>
					<?php endif; ?>
				</div>
				<div class="npcink-cloud-summary__grid">
					<div class="npcink-cloud-summary__item">
						<span class="npcink-cloud-summary__label"><?php esc_html_e( 'Cloud Base URL', 'npcink-cloud-addon' ); ?></span>
						<span class="npcink-cloud-summary__value"><?php echo esc_html( self::format_setting_value( (string) $settings['base_url'], __( 'Not set', 'npcink-cloud-addon' ) ) ); ?></span>
					</div>
					<div class="npcink-cloud-summary__item">
						<span class="npcink-cloud-summary__label"><?php esc_html_e( 'Last verified', 'npcink-cloud-addon' ); ?></span>
						<span class="npcink-cloud-summary__value"><?php echo esc_html( self::format_datetime_value( (string) $settings['verified_at'], __( 'Never', 'npcink-cloud-addon' ) ) ); ?></span>
					</div>
					<div class="npcink-cloud-summary__item">
						<span class="npcink-cloud-summary__label"><?php esc_html_e( 'Entitlement', 'npcink-cloud-addon' ); ?></span>
						<span class="npcink-cloud-summary__value"><?php echo esc_html( self::format_entitlement_availability( $entitlement, $is_verified ) ); ?></span>
					</div>
					<div class="npcink-cloud-summary__item">
						<span class="npcink-cloud-summary__label"><?php esc_html_e( 'Package', 'npcink-cloud-addon' ); ?></span>
						<span class="npcink-cloud-summary__value"><?php echo esc_html( $is_verified ? self::format_empty( (string) ( $entitlement['package_label'] ?? '' ) ) : __( 'Not checked', 'npcink-cloud-addon' ) ); ?></span>
					</div>
				</div>
			</section>
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
				<button type="submit" class="button button-secondary"><?php esc_html_e( 'Re-verify', 'npcink-cloud-addon' ); ?></button>
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
									value="<?php echo esc_attr( (string) $settings['base_url'] ); ?>"
									placeholder="https://cloud.example.com"
									required
								/>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="npcink-cloud-api-key"><?php esc_html_e( 'Cloud API Key', 'npcink-cloud-addon' ); ?></label>
							</th>
							<td>
								<input
									type="password"
									class="regular-text code"
									id="npcink-cloud-api-key"
									name="api_key"
									value=""
									autocomplete="new-password"
									placeholder="<?php echo esc_attr__( 'Paste a mak1_ key or JSON key to replace the stored key', 'npcink-cloud-addon' ); ?>"
								/>
								<p class="description"><?php esc_html_e( 'Leave blank to keep the stored Cloud API Key. The secret is never printed in this page.', 'npcink-cloud-addon' ); ?></p>
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
		 * Renders monitoring status.
		 *
		 * @param array<string,mixed> $monitoring Monitoring status.
		 * @return void
		 */
		private static function render_monitoring_summary( array $monitoring ): void {
			$plugins = is_array( $monitoring['plugins'] ?? null ) ? $monitoring['plugins'] : array();
			$remote = is_array( $monitoring['remote_summary'] ?? null ) ? $monitoring['remote_summary'] : array();
			$summary = is_array( $remote['summary'] ?? null ) ? $remote['summary'] : array();
			$totals = is_array( $summary['totals'] ?? null ) ? $summary['totals'] : array();
			$cloud_plugins = is_array( $summary['plugins'] ?? null ) ? $summary['plugins'] : array();
			$recent_errors = is_array( $summary['recent_errors'] ?? null ) ? $summary['recent_errors'] : array();
			?>
			<form class="npcink-cloud-verify-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin: 0 0 12px;">
				<?php wp_nonce_field( self::ACTION_REFRESH_MONITORING ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_REFRESH_MONITORING ); ?>" />
				<button type="submit" class="button button-secondary"><?php esc_html_e( 'Refresh monitoring', 'npcink-cloud-addon' ); ?></button>
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
						<td><?php echo ! empty( $summary['available'] ) ? esc_html__( 'available', 'npcink-cloud-addon' ) : esc_html__( 'unavailable', 'npcink-cloud-addon' ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Package', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_empty( (string) ( $summary['package_label'] ?? '' ) ) ); ?></td>
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
					(string) $probe['live_message']
				);
			}
			if ( empty( $probe['auth_ok'] ) && ! empty( $probe['auth_message'] ) ) {
				$messages[] = sprintf(
					/* translators: %s: signed verification error. */
					__( 'Signed verification failed: %s', 'npcink-cloud-addon' ),
					(string) $probe['auth_message']
				);
			}

			return '' !== implode( ' ', $messages )
				? sanitize_text_field( implode( ' ', $messages ) )
				: __( 'Cloud verification failed.', 'npcink-cloud-addon' );
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
					'message' => sanitize_text_field( $message ),
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

			$type = 'success' === (string) ( $notice['type'] ?? '' ) ? 'success' : 'error';
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
			return 'magick_ai_cloud_notice_' . absint( get_current_user_id() );
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

			return ! empty( $summary['available'] )
				? __( 'available', 'npcink-cloud-addon' )
				: __( 'unavailable', 'npcink-cloud-addon' );
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
