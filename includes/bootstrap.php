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
require_once __DIR__ . '/class-cloud-media-derivative-transport.php';
require_once __DIR__ . '/class-cloud-entitlement-summary.php';
require_once __DIR__ . '/class-cloud-observability-collector.php';
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

if ( ! function_exists( 'magick_ai_cloud_addon_verified_runtime_client' ) ) {
	/**
	 * Returns a verified runtime client, or null when credentials have not verified.
	 *
	 * Use this helper for Cloud jobs that move local media bytes or generated
	 * artifacts. It fails closed until Save and Verify has passed.
	 *
	 * @return Magick_AI_Cloud_Runtime_Client|null
	 */
	function magick_ai_cloud_addon_verified_runtime_client(): ?Magick_AI_Cloud_Runtime_Client {
		$client = Magick_AI_Cloud_Media_Derivative_Transport::verified_client();

		return is_wp_error( $client ) ? null : $client;
	}
}

if ( ! function_exists( 'magick_ai_cloud_addon_dispatch_media_derivative_cloud_request' ) ) {
	/**
	 * Dispatches a media derivative Cloud job from a local ability response.
	 *
	 * The source artifact must be created by the local host or an approved
	 * upload seam. This addon only signs and dispatches the runtime request.
	 *
	 * @param array<string,mixed> $ability_response Ability response envelope.
	 * @param array<string,mixed> $source_artifact Short TTL source artifact descriptor.
	 * @param string              $trace_id Optional trace id.
	 * @param string              $idempotency_key Optional idempotency key.
	 * @param array<string,mixed> $watermark_artifact Optional short TTL watermark artifact or upload descriptor.
	 * @return array<string,mixed>|WP_Error
	 */
	function magick_ai_cloud_addon_dispatch_media_derivative_cloud_request( array $ability_response, array $source_artifact, string $trace_id = '', string $idempotency_key = '', array $watermark_artifact = array() ) {
		return Magick_AI_Cloud_Media_Derivative_Transport::dispatch_from_ability_response(
			$ability_response,
			$source_artifact,
			$trace_id,
			$idempotency_key,
			$watermark_artifact
		);
	}
}

if ( ! function_exists( 'magick_ai_cloud_addon_build_media_derivative_proposal_payload' ) ) {
	/**
	 * Builds a Core-ready local proposal payload for a Cloud derivative artifact.
	 *
	 * This does not store a proposal, approve anything, or write WordPress media.
	 *
	 * @param array<string,mixed> $ability_response Ability response envelope.
	 * @param array<string,mixed> $cloud_result Cloud run result envelope.
	 * @param array<string,mixed> $derivative_artifact Cloud derivative artifact descriptor.
	 * @return array<string,mixed>|WP_Error
	 */
	function magick_ai_cloud_addon_build_media_derivative_proposal_payload( array $ability_response, array $cloud_result, array $derivative_artifact ) {
		return Magick_AI_Cloud_Media_Derivative_Transport::build_local_proposal_payload(
			$ability_response,
			$cloud_result,
			$derivative_artifact
		);
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
		Magick_AI_Cloud_Observability_Collector::register();
		Magick_AI_Cloud_Settings_Page::register();
	}
}

add_action( 'plugins_loaded', 'magick_ai_cloud_addon_bootstrap', 20 );
