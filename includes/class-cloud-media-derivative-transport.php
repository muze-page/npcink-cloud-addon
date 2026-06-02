<?php
/**
 * Media derivative Cloud transport helper.
 *
 * @package MagickAICloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Magick_AI_Cloud_Media_Derivative_Transport' ) ) {
	/**
	 * Converts local media derivative request contracts into signed Cloud jobs.
	 */
	final class Magick_AI_Cloud_Media_Derivative_Transport {
		private const REQUEST_CONTRACT_VERSION = 'media_derivative_cloud_request.v1';
		private const RUNTIME_CONTRACT_VERSION = 'media_derivative_cloud_runtime.v1';
		private const PROPOSAL_CONTRACT_VERSION = 'media_derivative_cloud_proposal.v1';

		/**
		 * Dispatches a Cloud derivative job from an abilities-side request contract.
		 *
		 * @param array<string,mixed> $ability_response Ability response envelope.
		 * @param array<string,mixed> $source_artifact Short TTL source artifact descriptor supplied by the local host.
		 * @param string              $trace_id Optional trace id.
		 * @param string              $idempotency_key Optional idempotency key.
		 * @return array<string,mixed>|WP_Error
		 */
		public static function dispatch_from_ability_response( array $ability_response, array $source_artifact, string $trace_id = '', string $idempotency_key = '' ) {
			$client = self::verified_client();
			if ( is_wp_error( $client ) ) {
				return $client;
			}

			$contract = self::extract_contract_data( $ability_response );
			$validated = self::validate_request_contract( $contract );
			if ( is_wp_error( $validated ) ) {
				return $validated;
			}

			$artifact = self::normalize_artifact_descriptor( $source_artifact, 'source' );
			if ( is_wp_error( $artifact ) ) {
				return $artifact;
			}

			$runtime_payload = self::build_runtime_payload( $contract, $artifact );

			return $client->execute_runtime(
				$runtime_payload,
				$trace_id,
				'' !== $idempotency_key ? $idempotency_key : 'media_derivative_' . wp_generate_uuid4()
			);
		}

		/**
		 * Builds a local-host proposal payload from a Cloud result artifact.
		 *
		 * This method does not store a proposal and does not mutate WordPress. The
		 * returned payload is intended for Core proposal/preflight intake.
		 *
		 * @param array<string,mixed> $ability_response Ability response envelope.
		 * @param array<string,mixed> $cloud_result Cloud run result envelope.
		 * @param array<string,mixed> $derivative_artifact Downloaded or downloadable Cloud derivative artifact descriptor.
		 * @return array<string,mixed>|WP_Error
		 */
		public static function build_local_proposal_payload( array $ability_response, array $cloud_result, array $derivative_artifact ) {
			$contract = self::extract_contract_data( $ability_response );
			$validated = self::validate_request_contract( $contract );
			if ( is_wp_error( $validated ) ) {
				return $validated;
			}

			$artifact = self::normalize_artifact_descriptor( $derivative_artifact, 'derivative' );
			if ( is_wp_error( $artifact ) ) {
				return $artifact;
			}

			$cloud_data = self::extract_cloud_data( $cloud_result );
			$original = self::normalize_media_metrics( $contract['cloud_job_payload']['source_asset'] ?? array() );
			$derivative = self::normalize_media_metrics(
				array_merge(
					is_array( $cloud_data['derivative'] ?? null ) ? $cloud_data['derivative'] : array(),
					$artifact
				)
			);
			$warnings = self::sanitize_string_list( $contract['cloud_job_payload']['warnings'] ?? array() );
			$warnings = array_merge( $warnings, self::sanitize_string_list( $cloud_data['warnings'] ?? array() ) );

			return array(
				'contract_version'  => self::PROPOSAL_CONTRACT_VERSION,
				'proposal_kind'     => 'media_derivative_cloud_artifact',
				'attachment_id'     => absint( $contract['attachment_id'] ?? 0 ),
				'final_write_owner' => 'local_wordpress_host',
				'approval_required' => true,
				'adoption_allowed'  => true,
				'default_action'    => 'preview_only',
				'actions'           => array(
					'preview' => true,
					'record'  => 'requires_local_host_approval',
					'replace' => 'requires_local_host_approval',
					'rollback' => 'requires_local_host_approval',
				),
				'original'          => $original,
				'derivative'        => $derivative,
				'savings_estimate'  => self::build_savings_estimate( $original, $derivative ),
				'warnings'          => array_values( array_unique( $warnings ) ),
				'artifact'          => $artifact,
				'cloud_result'      => self::sanitize_cloud_result_summary( $cloud_data ),
				'local_adoption'    => array(
					'owner'                         => 'local_wordpress_host',
					'final_write_owner'             => 'local_wordpress_host',
					'approval_required'             => true,
					'wordpress_write_included'      => false,
					'replace_original_default'      => false,
					'attachment_metadata_write_included' => false,
				),
			);
		}

		/**
		 * Returns a verified runtime client or a fail-closed error.
		 *
		 * @return Magick_AI_Cloud_Runtime_Client|WP_Error
		 */
		public static function verified_client() {
			if ( ! Magick_AI_Cloud_Addon_Settings::is_verified() ) {
				return new WP_Error(
					'cloud_runtime_unverified',
					__( 'Magick AI Cloud credentials must verify before dispatching media derivative jobs.', 'magick-ai-cloud-addon' ),
					array( 'status' => 403 )
				);
			}

			return new Magick_AI_Cloud_Runtime_Client( Magick_AI_Cloud_Addon_Settings::get_settings() );
		}

		/**
		 * Extracts ability response data.
		 *
		 * @param array<string,mixed> $ability_response Ability response envelope.
		 * @return array<string,mixed>
		 */
		private static function extract_contract_data( array $ability_response ): array {
			if ( is_array( $ability_response['data'] ?? null ) ) {
				return $ability_response['data'];
			}

			return $ability_response;
		}

		/**
		 * Extracts Cloud response data.
		 *
		 * @param array<string,mixed> $cloud_result Cloud response envelope.
		 * @return array<string,mixed>
		 */
		private static function extract_cloud_data( array $cloud_result ): array {
			if ( is_array( $cloud_result['data'] ?? null ) ) {
				return $cloud_result['data'];
			}

			return $cloud_result;
		}

		/**
		 * Validates the local ability contract before any Cloud dispatch.
		 *
		 * @param array<string,mixed> $contract Ability contract data.
		 * @return true|WP_Error
		 */
		private static function validate_request_contract( array $contract ) {
			if ( self::REQUEST_CONTRACT_VERSION !== (string) ( $contract['request_contract_version'] ?? '' ) ) {
				return new WP_Error(
					'cloud_media_derivative_contract_invalid',
					__( 'Media derivative request contract version is invalid.', 'magick-ai-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( empty( $contract['readonly'] ) || empty( $contract['proposal_only'] ) ) {
				return new WP_Error(
					'cloud_media_derivative_contract_not_readonly',
					__( 'Media derivative request must be read-only and proposal-only.', 'magick-ai-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( 'local_wordpress_host' !== (string) ( $contract['local_adoption']['final_write_owner'] ?? '' ) ) {
				return new WP_Error(
					'cloud_media_derivative_write_owner_invalid',
					__( 'Media derivative final write owner must remain the local WordPress host.', 'magick-ai-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( ! empty( $contract['local_adoption']['wordpress_write_included'] ) ) {
				return new WP_Error(
					'cloud_media_derivative_wordpress_write_present',
					__( 'Media derivative request must not include WordPress writes.', 'magick-ai-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$job_payload = is_array( $contract['cloud_job_payload'] ?? null ) ? $contract['cloud_job_payload'] : array();
			if ( 'generate_optimized_media_derivative' !== (string) ( $job_payload['job_type'] ?? '' ) ) {
				return new WP_Error(
					'cloud_media_derivative_job_type_invalid',
					__( 'Cloud media derivative job type is invalid.', 'magick-ai-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( ! empty( $job_payload['requested_derivative']['replace_original'] ) ) {
				return new WP_Error(
					'cloud_media_derivative_replace_original_requested',
					__( 'Media derivative jobs must not request original file replacement.', 'magick-ai-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( self::contains_forbidden_secret_fields( $job_payload ) ) {
				return new WP_Error(
					'cloud_media_derivative_credentials_present',
					__( 'Media derivative ability payload must not include credentials or signed headers.', 'magick-ai-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			return true;
		}

		/**
		 * Builds a runtime payload for /v1/runtime/execute.
		 *
		 * @param array<string,mixed> $contract Ability contract data.
		 * @param array<string,mixed> $source_artifact Source artifact descriptor.
		 * @return array<string,mixed>
		 */
		private static function build_runtime_payload( array $contract, array $source_artifact ): array {
			$job_payload = is_array( $contract['cloud_job_payload'] ?? null ) ? $contract['cloud_job_payload'] : array();
			$job_payload['source_artifact'] = $source_artifact;
			$job_payload['local_adoption'] = array(
				'final_write_owner' => 'local_wordpress_host',
				'proposal_only'     => true,
				'replace_original'  => false,
			);

			return array(
				'contract_version'  => self::RUNTIME_CONTRACT_VERSION,
				'ability_name'      => 'magick-ai/build-media-derivative-cloud-request',
				'execution_pattern' => 'whole_run_offload',
				'input'             => $job_payload,
				'policy'            => array(
					'allow_fallback' => false,
				),
				'final_write_owner' => 'local_wordpress_host',
			);
		}

		/**
		 * Normalizes an artifact descriptor and rejects expired artifacts.
		 *
		 * @param array<string,mixed> $artifact Artifact descriptor.
		 * @param string              $role Artifact role.
		 * @return array<string,mixed>|WP_Error
		 */
		private static function normalize_artifact_descriptor( array $artifact, string $role ) {
			$expires_at = sanitize_text_field( (string) ( $artifact['expires_at'] ?? '' ) );
			if ( '' === $expires_at ) {
				return new WP_Error(
					'cloud_media_derivative_artifact_expiry_missing',
					__( 'Media derivative artifact expiry is required.', 'magick-ai-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( self::is_expired( $expires_at ) ) {
				return new WP_Error(
					'cloud_media_derivative_artifact_expired',
					__( 'Expired Cloud artifacts cannot be adopted.', 'magick-ai-cloud-addon' ),
					array( 'status' => 409 )
				);
			}

			$artifact_id = sanitize_text_field( (string) ( $artifact['artifact_id'] ?? $artifact['id'] ?? '' ) );
			$download_url = esc_url_raw( (string) ( $artifact['download_url'] ?? $artifact['url'] ?? '' ) );
			if ( '' === $artifact_id && '' === $download_url ) {
				return new WP_Error(
					'cloud_media_derivative_artifact_ref_missing',
					__( 'Media derivative artifact requires an artifact id or download URL.', 'magick-ai-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			return array(
				'role'           => sanitize_key( $role ),
				'artifact_id'    => $artifact_id,
				'download_url'   => $download_url,
				'expires_at'     => $expires_at,
				'mime_type'      => sanitize_text_field( (string) ( $artifact['mime_type'] ?? '' ) ),
				'width'          => absint( $artifact['width'] ?? 0 ),
				'height'         => absint( $artifact['height'] ?? 0 ),
				'filesize_bytes' => absint( $artifact['filesize_bytes'] ?? $artifact['size_bytes'] ?? 0 ),
				'sha256'         => self::normalize_sha256( (string) ( $artifact['sha256'] ?? '' ) ),
			);
		}

		/**
		 * Checks whether an ISO-like timestamp is expired.
		 *
		 * @param string $expires_at Expiry timestamp.
		 * @return bool
		 */
		private static function is_expired( string $expires_at ): bool {
			$timestamp = strtotime( $expires_at );
			if ( false === $timestamp ) {
				return true;
			}

			return $timestamp <= time();
		}

		/**
		 * Recursively detects credential or signed-header fields.
		 *
		 * @param mixed $value Payload value.
		 * @return bool
		 */
		private static function contains_forbidden_secret_fields( $value ): bool {
			if ( ! is_array( $value ) ) {
				return false;
			}

			$forbidden = array(
				'api_key',
				'authorization',
				'credentials',
				'key_id',
				'secret',
				'signed_headers',
				'signature',
				'token',
				'x_magick_key_id',
				'x_magick_signature',
				'x_magick_site_id',
			);

			foreach ( $value as $key => $item ) {
				$normalized_key = strtolower( str_replace( '-', '_', (string) $key ) );
				if ( in_array( $normalized_key, $forbidden, true ) ) {
					return true;
				}
				if ( self::contains_forbidden_secret_fields( $item ) ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Normalizes media metrics for proposal display.
		 *
		 * @param mixed $metrics Raw metrics.
		 * @return array<string,mixed>
		 */
		private static function normalize_media_metrics( $metrics ): array {
			$metrics = is_array( $metrics ) ? $metrics : array();

			return array(
				'width'          => absint( $metrics['width'] ?? 0 ),
				'height'         => absint( $metrics['height'] ?? 0 ),
				'filesize_bytes' => absint( $metrics['filesize_bytes'] ?? $metrics['size_bytes'] ?? 0 ),
				'mime_type'      => sanitize_text_field( (string) ( $metrics['mime_type'] ?? '' ) ),
			);
		}

		/**
		 * Builds a conservative byte savings estimate.
		 *
		 * @param array<string,mixed> $original Original metrics.
		 * @param array<string,mixed> $derivative Derivative metrics.
		 * @return array<string,mixed>
		 */
		private static function build_savings_estimate( array $original, array $derivative ): array {
			$original_bytes = absint( $original['filesize_bytes'] ?? 0 );
			$derivative_bytes = absint( $derivative['filesize_bytes'] ?? 0 );
			$saved_bytes = max( 0, $original_bytes - $derivative_bytes );
			$ratio = $original_bytes > 0 ? $saved_bytes / $original_bytes : 0;

			return array(
				'original_bytes'   => $original_bytes,
				'derivative_bytes' => $derivative_bytes,
				'saved_bytes'      => $saved_bytes,
				'percent'          => round( $ratio * 100, 2 ),
			);
		}

		/**
		 * Sanitizes a compact Cloud result summary.
		 *
		 * @param array<string,mixed> $cloud_data Cloud data.
		 * @return array<string,mixed>
		 */
		private static function sanitize_cloud_result_summary( array $cloud_data ): array {
			return array(
				'run_id' => sanitize_text_field( (string) ( $cloud_data['run_id'] ?? '' ) ),
				'status' => sanitize_key( (string) ( $cloud_data['status'] ?? '' ) ),
				'warnings' => self::sanitize_string_list( $cloud_data['warnings'] ?? array() ),
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
		 * Normalizes a SHA-256 digest.
		 *
		 * @param string $value Raw digest.
		 * @return string
		 */
		private static function normalize_sha256( string $value ): string {
			$value = strtolower( trim( $value ) );

			return preg_match( '/^[a-f0-9]{64}$/', $value ) ? $value : '';
		}
	}
}
