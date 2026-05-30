<?php
/**
 * Cloud addon bootstrap and public seams.
 *
 * @package MagickAICloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-cloud-addon-settings.php';
require_once __DIR__ . '/class-cloud-runtime-client.php';
require_once __DIR__ . '/class-cloud-entitlement-summary.php';
require_once __DIR__ . '/class-cloud-settings-page.php';

if ( ! function_exists( 'magick_ai_cloud_addon_is_configured' ) ) {
	/**
	 * Returns whether Cloud addon credentials are complete.
	 *
	 * @return bool
	 */
	function magick_ai_cloud_addon_is_configured(): bool {
		return Magick_AI_Cloud_Addon_Settings::is_configured();
	}
}

if ( ! function_exists( 'magick_ai_cloud_addon_get_settings' ) ) {
	/**
	 * Returns normalized Cloud addon settings.
	 *
	 * The returned array includes the stored secret for server-side callers.
	 * Do not print this array into admin HTML or logs.
	 *
	 * @return array<string,mixed>
	 */
	function magick_ai_cloud_addon_get_settings(): array {
		return Magick_AI_Cloud_Addon_Settings::get_settings();
	}
}

if ( ! function_exists( 'magick_ai_cloud_addon_runtime_client' ) ) {
	/**
	 * Returns a configured runtime client, or null when credentials are incomplete.
	 *
	 * @return Magick_AI_Cloud_Runtime_Client|null
	 */
	function magick_ai_cloud_addon_runtime_client(): ?Magick_AI_Cloud_Runtime_Client {
		if ( ! Magick_AI_Cloud_Addon_Settings::is_configured() ) {
			return null;
		}

		return new Magick_AI_Cloud_Runtime_Client( Magick_AI_Cloud_Addon_Settings::get_settings() );
	}
}

if ( ! function_exists( 'magick_ai_cloud_addon_bootstrap' ) ) {
	/**
	 * Boots the standalone Cloud addon.
	 *
	 * @return void
	 */
	function magick_ai_cloud_addon_bootstrap(): void {
		Magick_AI_Cloud_Addon_Settings::register();
		Magick_AI_Cloud_Settings_Page::register();
	}
}

add_action( 'plugins_loaded', 'magick_ai_cloud_addon_bootstrap', 20 );
