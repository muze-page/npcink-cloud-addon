<?php
/**
 * Cloud addon bootstrap and public seams.
 *
 * @package NpcinkCloudAddon
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
require_once __DIR__ . '/class-cloud-site-knowledge-change-bridge.php';
require_once __DIR__ . '/class-cloud-settings-page.php';

if ( ! function_exists( 'npcink_cloud_addon_is_configured' ) ) {
	/**
	 * Returns whether Cloud addon credentials are complete.
	 *
	 * @return bool
	 */
	function npcink_cloud_addon_is_configured(): bool {
		return Npcink_Cloud_Addon_Settings::is_configured();
	}
}

if ( ! function_exists( 'npcink_cloud_addon_get_settings' ) ) {
	/**
	 * Returns normalized Cloud addon settings.
	 *
	 * The returned array includes the stored secret for server-side callers.
	 * Do not print this array into admin HTML or logs.
	 *
	 * @return array<string,mixed>
	 */
	function npcink_cloud_addon_get_settings(): array {
		return Npcink_Cloud_Addon_Settings::get_settings();
	}
}

if ( ! function_exists( 'npcink_cloud_addon_runtime_client' ) ) {
	/**
	 * Returns a configured runtime client, or null when credentials are incomplete.
	 *
	 * @return Npcink_Cloud_Runtime_Client|null
	 */
	function npcink_cloud_addon_runtime_client(): ?Npcink_Cloud_Runtime_Client {
		if ( ! Npcink_Cloud_Addon_Settings::is_configured() ) {
			return null;
		}

		return new Npcink_Cloud_Runtime_Client( Npcink_Cloud_Addon_Settings::get_settings() );
	}
}

if ( ! function_exists( 'npcink_cloud_addon_verified_runtime_client' ) ) {
	/**
	 * Returns a verified runtime client, or null when credentials have not verified.
	 *
	 * Use this helper for Cloud jobs that move local media bytes or generated
	 * artifacts. It fails closed until Save and Verify has passed.
	 *
	 * @return Npcink_Cloud_Runtime_Client|null
	 */
	function npcink_cloud_addon_verified_runtime_client(): ?Npcink_Cloud_Runtime_Client {
		$client = Npcink_Cloud_Media_Derivative_Transport::verified_client();

		return is_wp_error( $client ) ? null : $client;
	}
}

if ( ! function_exists( 'npcink_cloud_addon_dispatch_media_derivative_cloud_request' ) ) {
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
	function npcink_cloud_addon_dispatch_media_derivative_cloud_request( array $ability_response, array $source_artifact, string $trace_id = '', string $idempotency_key = '', array $watermark_artifact = array() ) {
		return Npcink_Cloud_Media_Derivative_Transport::dispatch_from_ability_response(
			$ability_response,
			$source_artifact,
			$trace_id,
			$idempotency_key,
			$watermark_artifact
		);
	}
}

if ( ! function_exists( 'npcink_cloud_addon_build_media_derivative_proposal_payload' ) ) {
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
	function npcink_cloud_addon_build_media_derivative_proposal_payload( array $ability_response, array $cloud_result, array $derivative_artifact ) {
		return Npcink_Cloud_Media_Derivative_Transport::build_local_proposal_payload(
			$ability_response,
			$cloud_result,
			$derivative_artifact
		);
	}
}

if ( ! function_exists( 'npcink_cloud_addon_get_media_derivative_run' ) ) {
	/**
	 * Reads one Cloud media derivative run projection.
	 *
	 * @param string $run_id Cloud run id.
	 * @param string $trace_id Optional trace id.
	 * @return array<string,mixed>|WP_Error
	 */
	function npcink_cloud_addon_get_media_derivative_run( string $run_id, string $trace_id = '' ) {
		return Npcink_Cloud_Media_Derivative_Transport::get_run_projection( $run_id, $trace_id );
	}
}

if ( ! function_exists( 'npcink_cloud_addon_get_media_derivative_run_result' ) ) {
	/**
	 * Reads one Cloud media derivative run result projection.
	 *
	 * @param string $run_id Cloud run id.
	 * @param string $trace_id Optional trace id.
	 * @return array<string,mixed>|WP_Error
	 */
	function npcink_cloud_addon_get_media_derivative_run_result( string $run_id, string $trace_id = '' ) {
		return Npcink_Cloud_Media_Derivative_Transport::get_run_result_projection( $run_id, $trace_id );
	}
}

if ( ! function_exists( 'npcink_cloud_addon_media_derivative_run_id' ) ) {
	/**
	 * Extracts a media derivative run id from supported Cloud response shapes.
	 *
	 * @param array<string,mixed> $cloud_response Cloud response.
	 * @return string
	 */
	function npcink_cloud_addon_media_derivative_run_id( array $cloud_response ): string {
		return Npcink_Cloud_Media_Derivative_Transport::run_id( $cloud_response );
	}
}

if ( ! function_exists( 'npcink_cloud_addon_public_media_derivative_cloud_projection' ) ) {
	/**
	 * Returns a bounded media derivative Cloud run/result projection.
	 *
	 * @param array<string,mixed> $cloud_response Cloud response.
	 * @return array<string,mixed>
	 */
	function npcink_cloud_addon_public_media_derivative_cloud_projection( array $cloud_response ): array {
		return Npcink_Cloud_Media_Derivative_Transport::public_cloud_projection( $cloud_response );
	}
}

if ( ! function_exists( 'npcink_cloud_addon_media_derivative_artifact_from_cloud_result' ) ) {
	/**
	 * Extracts a derivative artifact descriptor from a Cloud result payload.
	 *
	 * @param array<string,mixed> $cloud_result Cloud result.
	 * @return array<string,mixed>
	 */
	function npcink_cloud_addon_media_derivative_artifact_from_cloud_result( array $cloud_result ): array {
		return Npcink_Cloud_Media_Derivative_Transport::artifact_from_cloud_result( $cloud_result );
	}
}

if ( ! function_exists( 'npcink_cloud_addon_build_media_derivative_optimization_payload' ) ) {
	/**
	 * Builds a Core from-plan media optimization payload from a Cloud derivative artifact.
	 *
	 * This does not store a proposal, approve anything, or write WordPress media.
	 *
	 * @param array<string,mixed> $ability_response Ability response envelope.
	 * @param array<string,mixed> $cloud_result Cloud run result envelope.
	 * @param array<string,mixed> $derivative_artifact Cloud derivative artifact descriptor.
	 * @param array<string,mixed> $media_details_input Reviewed media metadata input.
	 * @return array<string,mixed>|WP_Error
	 */
	function npcink_cloud_addon_build_media_derivative_optimization_payload( array $ability_response, array $cloud_result, array $derivative_artifact, array $media_details_input ) {
		return Npcink_Cloud_Media_Derivative_Transport::build_media_optimization_payload(
			$ability_response,
			$cloud_result,
			$derivative_artifact,
			$media_details_input
		);
	}
}

if ( ! function_exists( 'npcink_cloud_addon_download_media_derivative_artifact' ) ) {
	/**
	 * Downloads a short-TTL derivative artifact for a trusted local preview.
	 *
	 * This does not store, register, adopt, or write the artifact.
	 *
	 * @param array<string,mixed> $derivative_artifact Cloud derivative artifact descriptor.
	 * @param string              $trace_id Optional trace id.
	 * @return array<string,mixed>|WP_Error
	 */
	function npcink_cloud_addon_download_media_derivative_artifact( array $derivative_artifact, string $trace_id = '' ) {
		return Npcink_Cloud_Media_Derivative_Transport::download_artifact_preview(
			$derivative_artifact,
			$trace_id
		);
	}
}

if ( ! function_exists( 'npcink_cloud_addon_site_knowledge_change_bridge_health' ) ) {
	/**
	 * Returns local Site Knowledge change bridge health for host plugins.
	 *
	 * The bridge only reports and transports public content change hints to
	 * Cloud Site Knowledge. It is not a local index lifecycle owner.
	 *
	 * @return array<string,mixed>
	 */
	function npcink_cloud_addon_site_knowledge_change_bridge_health(): array {
		return Npcink_Cloud_Site_Knowledge_Change_Bridge::health_snapshot();
	}
}

if ( ! function_exists( 'npcink_cloud_addon_bootstrap' ) ) {
	/**
	 * Boots the standalone Cloud addon.
	 *
	 * @return void
	 */
	function npcink_cloud_addon_bootstrap(): void {
		Npcink_Cloud_Addon_Settings::register();
		Npcink_Cloud_Observability_Collector::register();
		Npcink_Cloud_Site_Knowledge_Change_Bridge::register();
		Npcink_Cloud_Settings_Page::register();
	}
}

if ( ! function_exists( 'npcink_cloud_addon_filter_plugin_action_links' ) ) {
	/**
	 * Adds a settings shortcut on the WordPress plugins screen.
	 *
	 * @param array<int|string,string> $links Existing plugin action links.
	 * @return array<int|string,string>
	 */
	function npcink_cloud_addon_filter_plugin_action_links( array $links ): array {
		array_unshift(
			$links,
			sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( admin_url( 'admin.php?page=npcink-cloud-addon' ) ),
				esc_html__( 'Settings', 'default' )
			)
		);

		return $links;
	}
}

add_action( 'plugins_loaded', 'npcink_cloud_addon_bootstrap', 20 );
add_filter( 'plugin_action_links_' . plugin_basename( NPCINK_CLOUD_ADDON_FILE ), 'npcink_cloud_addon_filter_plugin_action_links' );
