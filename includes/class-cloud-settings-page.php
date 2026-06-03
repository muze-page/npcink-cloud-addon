<?php
/**
 * Cloud addon settings page.
 *
 * @package MagickAICloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Magick_AI_Cloud_Settings_Page' ) ) {
	/**
	 * Renders Magick AI > Cloud Addon and handles save-and-verify.
	 */
	final class Magick_AI_Cloud_Settings_Page {
		private const PARENT_MENU_SLUG = 'magick-ai';
		private const PAGE_SLUG = 'magick-ai-cloud-addon';
		private const MENU_CAPABILITY = 'manage_options';
		private const ACTION_SAVE = 'magick_ai_cloud_addon_save';
		private const ACTION_REFRESH_MONITORING = 'magick_ai_cloud_addon_refresh_monitoring';

		/**
		 * Registers admin hooks.
		 *
		 * @return void
		 */
		public static function register(): void {
			add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ), 50 );
			add_action( 'admin_post_' . self::ACTION_SAVE, array( __CLASS__, 'handle_save' ) );
			add_action( 'admin_post_' . self::ACTION_REFRESH_MONITORING, array( __CLASS__, 'handle_refresh_monitoring' ) );
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
				__( 'Magick AI Cloud Addon', 'magick-ai-cloud-addon' ),
				__( 'Cloud Addon', 'magick-ai-cloud-addon' ),
				self::MENU_CAPABILITY,
				self::PAGE_SLUG,
				array( __CLASS__, 'render' ),
				50
			);
		}

		/**
		 * Ensures the shared Magick AI parent menu exists.
		 *
		 * @return void
		 */
		private static function ensure_parent_menu(): void {
			if ( self::has_parent_menu() ) {
				return;
			}

			add_menu_page(
				__( 'Magick AI', 'magick-ai-cloud-addon' ),
				__( 'Magick AI', 'magick-ai-cloud-addon' ),
				self::MENU_CAPABILITY,
				self::PARENT_MENU_SLUG,
				array( __CLASS__, 'render_overview' ),
				'dashicons-superhero',
				58
			);

			add_submenu_page(
				self::PARENT_MENU_SLUG,
				__( 'Magick AI Overview', 'magick-ai-cloud-addon' ),
				__( 'Overview', 'magick-ai-cloud-addon' ),
				self::MENU_CAPABILITY,
				self::PARENT_MENU_SLUG,
				array( __CLASS__, 'render_overview' ),
				0
			);
		}

		/**
		 * Returns whether another Magick AI plugin already created the parent menu.
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
		 * Renders the shared Magick AI overview page.
		 *
		 * @return void
		 */
		public static function render_overview(): void {
			if ( ! current_user_can( self::MENU_CAPABILITY ) ) {
				wp_die( esc_html__( 'You do not have permission to manage Magick AI settings.', 'magick-ai-cloud-addon' ) );
			}
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'Magick AI', 'magick-ai-cloud-addon' ); ?></h1>
				<p><?php esc_html_e( 'Local WordPress entry points for Magick AI governance, connections, cloud access, and ability packages.', 'magick-ai-cloud-addon' ); ?></p>
				<h2><?php esc_html_e( 'Installed Surfaces', 'magick-ai-cloud-addon' ); ?></h2>
				<table class="widefat striped" style="max-width: 860px;">
					<tbody>
						<?php
						self::render_overview_row( __( 'Core', 'magick-ai-cloud-addon' ), __( 'Review proposals, approval decisions, commit preflight, audit, and Core app keys.', 'magick-ai-cloud-addon' ), 'magick-ai-core' );
						self::render_overview_row( __( 'Adapter', 'magick-ai-cloud-addon' ), __( 'Connect OpenClaw through the Adapter surface.', 'magick-ai-cloud-addon' ), 'magick-ai-adapter' );
						self::render_overview_row( __( 'Abilities', 'magick-ai-cloud-addon' ), __( 'Verify WordPress Abilities API packages and demo ability controls.', 'magick-ai-cloud-addon' ), 'magick-ai-abilities' );
						self::render_overview_row( __( 'Cloud Addon', 'magick-ai-cloud-addon' ), __( 'Connect this site to Magick AI Cloud without moving local control-plane truth.', 'magick-ai-cloud-addon' ), self::PAGE_SLUG );
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
						<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $slug ) ); ?>"><?php esc_html_e( 'Open', 'magick-ai-cloud-addon' ); ?></a>
					<?php else : ?>
						<span style="color: #646970;"><?php esc_html_e( 'Not installed', 'magick-ai-cloud-addon' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
			<?php
		}

		/**
		 * Returns whether a Magick AI submenu has been registered.
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
				wp_die( esc_html__( 'You do not have permission to manage Magick AI Cloud settings.', 'magick-ai-cloud-addon' ) );
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

			$settings = Magick_AI_Cloud_Addon_Settings::build_settings_from_admin_payload( $payload );
			if ( is_wp_error( $settings ) ) {
				self::set_admin_notice( 'error', $settings->get_error_message() );
				self::redirect_to_page();
			}

			Magick_AI_Cloud_Addon_Settings::write_settings( $settings );

			$client = new Magick_AI_Cloud_Runtime_Client( $settings );
			$probe = $client->probe_connectivity();
			if ( ! empty( $probe['ok'] ) ) {
				Magick_AI_Cloud_Addon_Settings::mark_verification_result( true, '' );
				Magick_AI_Cloud_Observability_Collector::sync_schedule();
				Magick_AI_Cloud_Entitlement_Summary::refresh();
				self::set_admin_notice( 'success', __( 'Cloud settings saved and verified.', 'magick-ai-cloud-addon' ) );
			} else {
				$message = self::format_probe_failure_message( $probe );
				Magick_AI_Cloud_Addon_Settings::mark_verification_result( false, $message );
				Magick_AI_Cloud_Observability_Collector::sync_schedule();
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
				wp_die( esc_html__( 'You do not have permission to manage Magick AI Cloud settings.', 'magick-ai-cloud-addon' ) );
			}

			check_admin_referer( self::ACTION_REFRESH_MONITORING );

			if ( ! Magick_AI_Cloud_Addon_Settings::is_monitoring_enabled() ) {
				self::set_admin_notice( 'error', __( 'Monitoring is disabled or Cloud is not verified.', 'magick-ai-cloud-addon' ) );
				self::redirect_to_page( 'monitoring' );
			}

			$upload = Magick_AI_Cloud_Observability_Collector::flush_buffer();
			if ( empty( $upload['last_upload_ok'] ) ) {
				$message = sanitize_text_field( (string) ( $upload['last_upload_error'] ?? '' ) );
				self::set_admin_notice( 'error', '' !== $message ? $message : __( 'Monitoring upload failed.', 'magick-ai-cloud-addon' ) );
				self::redirect_to_page( 'monitoring' );
			}

			$summary = Magick_AI_Cloud_Observability_Collector::refresh_summary();
			if ( empty( $summary['last_refresh_ok'] ) ) {
				$message = sanitize_text_field( (string) ( $summary['last_refresh_error'] ?? '' ) );
				self::set_admin_notice( 'error', '' !== $message ? $message : __( 'Monitoring summary refresh failed.', 'magick-ai-cloud-addon' ) );
				self::redirect_to_page( 'monitoring' );
			}

			$remaining = absint( $upload['buffer_count'] ?? 0 );
			self::set_admin_notice(
				'success',
				sprintf(
					/* translators: %d: remaining buffered event count. */
					__( 'Monitoring refreshed. Remaining buffered events: %d.', 'magick-ai-cloud-addon' ),
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

			$settings = Magick_AI_Cloud_Addon_Settings::get_settings();
			$state = Magick_AI_Cloud_Addon_Settings::get_credential_state();
			$entitlement = Magick_AI_Cloud_Entitlement_Summary::get_summary();
			$monitoring = Magick_AI_Cloud_Observability_Collector::get_status();
			$is_verified = ! empty( $state['verified'] );
			$active_tab = self::get_active_tab( $is_verified );
			?>
			<div class="wrap magick-ai-cloud-addon">
				<?php self::render_page_styles(); ?>
				<h1><?php esc_html_e( 'Magick AI Cloud Addon', 'magick-ai-cloud-addon' ); ?></h1>
				<p><?php esc_html_e( 'Cloud connector status and access settings for this WordPress site.', 'magick-ai-cloud-addon' ); ?></p>
				<?php self::render_admin_notice(); ?>

				<?php self::render_connection_summary( $settings, $state, $entitlement ); ?>
				<?php self::render_tab_navigation( $active_tab, $is_verified ); ?>

				<?php if ( 'entitlement' === $active_tab ) : ?>
					<section class="magick-ai-cloud-section magick-ai-cloud-tab-panel">
						<h2><?php esc_html_e( 'Entitlement Summary', 'magick-ai-cloud-addon' ); ?></h2>
						<?php self::render_entitlement_summary( $entitlement, $is_verified ); ?>
					</section>
				<?php elseif ( 'monitoring' === $active_tab ) : ?>
					<section class="magick-ai-cloud-section magick-ai-cloud-tab-panel">
						<h2><?php esc_html_e( 'Monitoring', 'magick-ai-cloud-addon' ); ?></h2>
						<?php self::render_monitoring_summary( $monitoring ); ?>
					</section>
				<?php elseif ( 'advanced' === $active_tab ) : ?>
					<section class="magick-ai-cloud-section magick-ai-cloud-tab-panel">
						<h2><?php esc_html_e( 'Advanced Information', 'magick-ai-cloud-addon' ); ?></h2>
						<?php self::render_advanced_information( $settings, $state, $entitlement ); ?>
					</section>
				<?php else : ?>
					<section class="magick-ai-cloud-section magick-ai-cloud-tab-panel">
						<h2><?php esc_html_e( 'Cloud Settings', 'magick-ai-cloud-addon' ); ?></h2>
						<?php if ( $is_verified ) : ?>
							<p><?php esc_html_e( 'Update the connector or replace the stored key.', 'magick-ai-cloud-addon' ); ?></p>
						<?php else : ?>
							<p><?php esc_html_e( 'Save a Cloud Base URL and Cloud API Key to verify this connector.', 'magick-ai-cloud-addon' ); ?></p>
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
			$requested = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

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
					'entitlement' => __( 'Entitlement', 'magick-ai-cloud-addon' ),
					'monitoring'  => __( 'Monitoring', 'magick-ai-cloud-addon' ),
					'settings'    => __( 'Settings', 'magick-ai-cloud-addon' ),
					'advanced'    => __( 'Advanced', 'magick-ai-cloud-addon' ),
				);
			}

			return array(
				'settings' => __( 'Settings', 'magick-ai-cloud-addon' ),
				'advanced' => __( 'Advanced', 'magick-ai-cloud-addon' ),
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
			<nav class="nav-tab-wrapper magick-ai-cloud-tabs" aria-label="<?php esc_attr_e( 'Cloud addon sections', 'magick-ai-cloud-addon' ); ?>">
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
		 * Renders page-local styles for the compact admin surface.
		 *
		 * @return void
		 */
		private static function render_page_styles(): void {
			?>
			<style>
				.magick-ai-cloud-addon {
					max-width: 960px;
				}

				.magick-ai-cloud-summary,
				.magick-ai-cloud-section,
				.magick-ai-cloud-disclosure,
				.magick-ai-cloud-tabs {
					box-sizing: border-box;
					max-width: 860px;
				}

				.magick-ai-cloud-summary {
					background: #fff;
					border: 1px solid #c3c4c7;
					margin: 16px 0 18px;
					padding: 16px;
				}

				.magick-ai-cloud-summary__header {
					align-items: flex-start;
					display: flex;
					gap: 16px;
					justify-content: space-between;
				}

				.magick-ai-cloud-summary__state {
					margin: 0 0 4px;
				}

				.magick-ai-cloud-summary__message {
					margin: 0;
					color: #50575e;
				}

				.magick-ai-cloud-badge {
					border-radius: 999px;
					display: inline-block;
					font-size: 12px;
					font-weight: 600;
					line-height: 1;
					margin-right: 8px;
					padding: 5px 8px;
				}

				.magick-ai-cloud-badge--ok {
					background: #edfaef;
					color: #008a20;
				}

				.magick-ai-cloud-badge--error {
					background: #fcf0f1;
					color: #b32d2e;
				}

				.magick-ai-cloud-badge--pending,
				.magick-ai-cloud-badge--inactive {
					background: #fcf9e8;
					color: #8a5a00;
				}

				.magick-ai-cloud-summary__grid {
					border-top: 1px solid #dcdcde;
					display: grid;
					gap: 0;
					grid-template-columns: repeat(2, minmax(0, 1fr));
					margin-top: 14px;
				}

				.magick-ai-cloud-summary__item {
					border-bottom: 1px solid #f0f0f1;
					padding: 10px 12px 10px 0;
				}

				.magick-ai-cloud-summary__label {
					color: #646970;
					display: block;
					font-size: 12px;
					margin-bottom: 3px;
				}

				.magick-ai-cloud-summary__value {
					display: block;
					font-weight: 600;
					overflow-wrap: anywhere;
				}

				.magick-ai-cloud-verify-form {
					margin: 0;
					min-width: 96px;
				}

				.magick-ai-cloud-section {
					margin-top: 18px;
				}

				.magick-ai-cloud-tabs {
					margin-top: 14px;
				}

				.magick-ai-cloud-tab-panel {
					background: #fff;
					border: 1px solid #dcdcde;
					border-top: 0;
					margin-top: 0;
					padding: 16px;
				}

				.magick-ai-cloud-tab-panel > h2:first-child {
					margin-top: 0;
				}

				.magick-ai-cloud-disclosure {
					background: #fff;
					border: 1px solid #dcdcde;
					margin-top: 16px;
				}

				.magick-ai-cloud-disclosure > summary {
					cursor: pointer;
					display: flex;
					gap: 8px;
					justify-content: space-between;
					padding: 12px 14px;
				}

				.magick-ai-cloud-disclosure > summary:hover {
					background: #f6f7f7;
				}

				.magick-ai-cloud-disclosure > summary span {
					color: #646970;
				}

				.magick-ai-cloud-disclosure form,
				.magick-ai-cloud-disclosure table {
					margin: 0 14px 14px;
				}

				.magick-ai-cloud-empty {
					background: #fff;
					border-left: 4px solid #dba617;
					margin: 0;
					max-width: 820px;
					padding: 10px 12px;
				}

				@media (max-width: 782px) {
					.magick-ai-cloud-summary__header,
					.magick-ai-cloud-disclosure > summary {
						display: block;
					}

					.magick-ai-cloud-summary__grid {
						grid-template-columns: 1fr;
					}

					.magick-ai-cloud-verify-form {
						margin-top: 12px;
					}
				}
			</style>
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
			<section class="magick-ai-cloud-summary">
				<div class="magick-ai-cloud-summary__header">
					<div>
						<p class="magick-ai-cloud-summary__state">
							<span class="magick-ai-cloud-badge magick-ai-cloud-badge--<?php echo esc_attr( $severity ); ?>"><?php echo esc_html( (string) $state['label'] ); ?></span>
						</p>
						<p class="magick-ai-cloud-summary__message"><?php echo esc_html( (string) $state['message'] ); ?></p>
					</div>
					<?php if ( $is_configured ) : ?>
						<?php self::render_reverify_form( $settings ); ?>
					<?php endif; ?>
				</div>
				<div class="magick-ai-cloud-summary__grid">
					<div class="magick-ai-cloud-summary__item">
						<span class="magick-ai-cloud-summary__label"><?php esc_html_e( 'Cloud Base URL', 'magick-ai-cloud-addon' ); ?></span>
						<span class="magick-ai-cloud-summary__value"><?php echo esc_html( self::format_setting_value( (string) $settings['base_url'], __( 'Not set', 'magick-ai-cloud-addon' ) ) ); ?></span>
					</div>
					<div class="magick-ai-cloud-summary__item">
						<span class="magick-ai-cloud-summary__label"><?php esc_html_e( 'Last verified', 'magick-ai-cloud-addon' ); ?></span>
						<span class="magick-ai-cloud-summary__value"><?php echo esc_html( self::format_setting_value( (string) $settings['verified_at'], __( 'Never', 'magick-ai-cloud-addon' ) ) ); ?></span>
					</div>
					<div class="magick-ai-cloud-summary__item">
						<span class="magick-ai-cloud-summary__label"><?php esc_html_e( 'Entitlement', 'magick-ai-cloud-addon' ); ?></span>
						<span class="magick-ai-cloud-summary__value"><?php echo esc_html( self::format_entitlement_availability( $entitlement, $is_verified ) ); ?></span>
					</div>
					<div class="magick-ai-cloud-summary__item">
						<span class="magick-ai-cloud-summary__label"><?php esc_html_e( 'Package', 'magick-ai-cloud-addon' ); ?></span>
						<span class="magick-ai-cloud-summary__value"><?php echo esc_html( $is_verified ? self::format_empty( (string) ( $entitlement['package_label'] ?? '' ) ) : __( 'Not checked', 'magick-ai-cloud-addon' ) ); ?></span>
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
			<form class="magick-ai-cloud-verify-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( self::ACTION_SAVE ) ); ?>" />
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_SAVE ); ?>" />
				<input type="hidden" name="base_url" value="<?php echo esc_attr( (string) $settings['base_url'] ); ?>" />
				<input type="hidden" name="api_key" value="" />
				<input type="hidden" name="timeout" value="<?php echo esc_attr( (string) $settings['timeout'] ); ?>" />
				<input type="hidden" name="monitoring_enabled" value="<?php echo esc_attr( ! empty( $settings['monitoring_enabled'] ) ? '1' : '0' ); ?>" />
				<button type="submit" class="button button-secondary"><?php esc_html_e( 'Re-verify', 'magick-ai-cloud-addon' ); ?></button>
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
								<label for="magick-ai-cloud-base-url"><?php esc_html_e( 'Cloud Base URL', 'magick-ai-cloud-addon' ); ?></label>
							</th>
							<td>
								<input
									type="url"
									class="regular-text code"
									id="magick-ai-cloud-base-url"
									name="base_url"
									value="<?php echo esc_attr( (string) $settings['base_url'] ); ?>"
									placeholder="https://cloud.example.com"
									required
								/>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="magick-ai-cloud-api-key"><?php esc_html_e( 'Cloud API Key', 'magick-ai-cloud-addon' ); ?></label>
							</th>
							<td>
								<input
									type="password"
									class="regular-text code"
									id="magick-ai-cloud-api-key"
									name="api_key"
									value=""
									autocomplete="new-password"
									placeholder="<?php echo esc_attr__( 'Paste a mak1_ key or JSON key to replace the stored key', 'magick-ai-cloud-addon' ); ?>"
								/>
								<p class="description"><?php esc_html_e( 'Leave blank to keep the stored Cloud API Key. The secret is never printed in this page.', 'magick-ai-cloud-addon' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="magick-ai-cloud-timeout"><?php esc_html_e( 'Timeout', 'magick-ai-cloud-addon' ); ?></label>
							</th>
							<td>
								<input
									type="number"
									id="magick-ai-cloud-timeout"
									name="timeout"
									min="5"
									max="60"
									step="1"
									value="<?php echo esc_attr( (string) $settings['timeout'] ); ?>"
								/>
								<span><?php esc_html_e( 'seconds', 'magick-ai-cloud-addon' ); ?></span>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Monitoring', 'magick-ai-cloud-addon' ); ?></th>
							<td>
								<label for="magick-ai-cloud-monitoring-enabled">
									<input
										type="checkbox"
										id="magick-ai-cloud-monitoring-enabled"
										name="monitoring_enabled"
										value="1"
										<?php checked( ! empty( $settings['monitoring_enabled'] ) ); ?>
									/>
									<?php esc_html_e( 'Enable Cloud monitoring for installed Magick AI plugins.', 'magick-ai-cloud-addon' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'The addon collects local metadata events only: plugin, event kind, status, timing, ids, and error codes. Prompts, content, results, secrets, and raw request payloads are not collected.', 'magick-ai-cloud-addon' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
				<?php submit_button( __( 'Save and Verify', 'magick-ai-cloud-addon' ) ); ?>
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
			<form class="magick-ai-cloud-verify-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin: 0 0 12px;">
				<?php wp_nonce_field( self::ACTION_REFRESH_MONITORING ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_REFRESH_MONITORING ); ?>" />
				<button type="submit" class="button button-secondary"><?php esc_html_e( 'Refresh monitoring', 'magick-ai-cloud-addon' ); ?></button>
			</form>
			<table class="widefat striped" style="max-width: 860px;">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Collection', 'magick-ai-cloud-addon' ); ?></th>
						<td><?php echo ! empty( $monitoring['enabled'] ) ? esc_html__( 'enabled', 'magick-ai-cloud-addon' ) : esc_html__( 'disabled', 'magick-ai-cloud-addon' ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Buffered events', 'magick-ai-cloud-addon' ); ?></th>
						<td><?php echo esc_html( (string) absint( $monitoring['buffer_count'] ?? 0 ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Last captured', 'magick-ai-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_empty( (string) ( $monitoring['last_captured_at'] ?? '' ) ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Uploaded events', 'magick-ai-cloud-addon' ); ?></th>
						<td><?php echo esc_html( (string) absint( $monitoring['total_uploaded'] ?? 0 ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Last upload status', 'magick-ai-cloud-addon' ); ?></th>
						<td>
							<?php
							$has_upload_state = '' !== (string) ( $monitoring['last_uploaded_at'] ?? '' )
								|| '' !== (string) ( $monitoring['last_upload_error'] ?? '' );
							if ( ! $has_upload_state ) {
								echo esc_html__( 'never', 'magick-ai-cloud-addon' );
							} else {
								$upload_status = ! empty( $monitoring['last_upload_ok'] )
									? __( 'ok', 'magick-ai-cloud-addon' )
									: __( 'failed', 'magick-ai-cloud-addon' );
								echo esc_html( $upload_status );
							}
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Last uploaded', 'magick-ai-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_empty( (string) ( $monitoring['last_uploaded_at'] ?? '' ) ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Last upload error', 'magick-ai-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_empty( (string) ( $monitoring['last_upload_error'] ?? '' ) ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Last event', 'magick-ai-cloud-addon' ); ?></th>
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
						<th scope="row"><?php esc_html_e( 'Cloud window', 'magick-ai-cloud-addon' ); ?></th>
						<td>
							<?php
							$window = is_array( $summary['window'] ?? null ) ? $summary['window'] : array();
							printf(
								/* translators: %d: window hours. */
								esc_html__( '%d hours', 'magick-ai-cloud-addon' ),
								absint( $window['hours'] ?? 0 )
							);
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Cloud events', 'magick-ai-cloud-addon' ); ?></th>
						<td><?php echo esc_html( (string) absint( $totals['events_total'] ?? 0 ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Cloud errors', 'magick-ai-cloud-addon' ); ?></th>
						<td><?php echo esc_html( (string) absint( $totals['error_total'] ?? 0 ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Success rate', 'magick-ai-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_percent( (float) ( $totals['success_rate'] ?? 0 ) ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Average latency', 'magick-ai-cloud-addon' ); ?></th>
						<td>
							<?php
							printf(
								/* translators: %d: latency in milliseconds. */
								esc_html__( '%d ms', 'magick-ai-cloud-addon' ),
								absint( $totals['avg_latency_ms'] ?? 0 )
							);
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Summary refreshed', 'magick-ai-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_empty( (string) ( $remote['last_refreshed_at'] ?? '' ) ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Summary error', 'magick-ai-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_empty( (string) ( $remote['last_refresh_error'] ?? '' ) ) ); ?></td>
					</tr>
				</tbody>
			</table>
			<?php if ( ! empty( $cloud_plugins ) ) : ?>
				<table class="widefat striped" style="max-width: 860px; margin-top: 12px;">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Cloud plugin', 'magick-ai-cloud-addon' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Events', 'magick-ai-cloud-addon' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Errors', 'magick-ai-cloud-addon' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Success', 'magick-ai-cloud-addon' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Avg latency', 'magick-ai-cloud-addon' ); ?></th>
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
							<th scope="col"><?php esc_html_e( 'Recent error', 'magick-ai-cloud-addon' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Plugin', 'magick-ai-cloud-addon' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Event', 'magick-ai-cloud-addon' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Seen', 'magick-ai-cloud-addon' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $recent_errors as $error ) : ?>
							<?php $error = is_array( $error ) ? $error : array(); ?>
							<tr>
								<td><code><?php echo esc_html( (string) ( $error['error_code'] ?? '' ) ); ?></code></td>
								<td><code><?php echo esc_html( (string) ( $error['plugin_slug'] ?? '' ) ); ?></code></td>
								<td><code><?php echo esc_html( (string) ( $error['event_kind'] ?? '' ) ); ?></code></td>
								<td><?php echo esc_html( self::format_empty( (string) ( $error['received_at'] ?? '' ) ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
			<table class="widefat striped" style="max-width: 860px; margin-top: 12px;">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Plugin', 'magick-ai-cloud-addon' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Installed', 'magick-ai-cloud-addon' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Active', 'magick-ai-cloud-addon' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Version', 'magick-ai-cloud-addon' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $plugins as $plugin ) : ?>
						<?php $plugin = is_array( $plugin ) ? $plugin : array(); ?>
						<tr>
							<th scope="row"><?php echo esc_html( (string) ( $plugin['label'] ?? '' ) ); ?></th>
							<td><?php echo ! empty( $plugin['installed'] ) ? esc_html__( 'yes', 'magick-ai-cloud-addon' ) : esc_html__( 'no', 'magick-ai-cloud-addon' ); ?></td>
							<td><?php echo ! empty( $plugin['active'] ) ? esc_html__( 'yes', 'magick-ai-cloud-addon' ) : esc_html__( 'no', 'magick-ai-cloud-addon' ); ?></td>
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
				<p class="magick-ai-cloud-empty"><?php esc_html_e( 'Entitlement is checked after the connector verifies successfully.', 'magick-ai-cloud-addon' ); ?></p>
				<?php
				return;
			}
			?>
			<table class="widefat striped" style="max-width: 860px;">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Availability', 'magick-ai-cloud-addon' ); ?></th>
						<td><?php echo ! empty( $summary['available'] ) ? esc_html__( 'available', 'magick-ai-cloud-addon' ) : esc_html__( 'unavailable', 'magick-ai-cloud-addon' ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Package', 'magick-ai-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_empty( (string) ( $summary['package_label'] ?? '' ) ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Entitlement status', 'magick-ai-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_empty( (string) ( $summary['entitlement_status'] ?? '' ) ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Synced at', 'magick-ai-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_empty( (string) ( $summary['synced_at'] ?? '' ) ) ); ?></td>
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
			<p><?php esc_html_e( 'Timeout, verification failure, and entitlement message.', 'magick-ai-cloud-addon' ); ?></p>
			<table class="widefat striped">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Timeout', 'magick-ai-cloud-addon' ); ?></th>
						<td>
							<?php
							printf(
								/* translators: %d: timeout in seconds. */
								esc_html__( '%d seconds', 'magick-ai-cloud-addon' ),
								absint( $settings['timeout'] )
							);
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Connection code', 'magick-ai-cloud-addon' ); ?></th>
						<td><code><?php echo esc_html( (string) $state['code'] ); ?></code></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Last failure', 'magick-ai-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_empty( (string) ( $state['last_verification_error'] ?? '' ) ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Entitlement message', 'magick-ai-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_empty( (string) ( $entitlement['message'] ?? '' ) ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Entitlement state', 'magick-ai-cloud-addon' ); ?></th>
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
					__( 'Live check failed: %s', 'magick-ai-cloud-addon' ),
					(string) $probe['live_message']
				);
			}
			if ( empty( $probe['auth_ok'] ) && ! empty( $probe['auth_message'] ) ) {
				$messages[] = sprintf(
					/* translators: %s: signed verification error. */
					__( 'Signed verification failed: %s', 'magick-ai-cloud-addon' ),
					(string) $probe['auth_message']
				);
			}

			return '' !== implode( ' ', $messages )
				? sanitize_text_field( implode( ' ', $messages ) )
				: __( 'Cloud verification failed.', 'magick-ai-cloud-addon' );
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
				return __( 'Not checked', 'magick-ai-cloud-addon' );
			}

			return ! empty( $summary['available'] )
				? __( 'available', 'magick-ai-cloud-addon' )
				: __( 'unavailable', 'magick-ai-cloud-addon' );
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
			return '' !== $value ? $value : __( 'unavailable', 'magick-ai-cloud-addon' );
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
