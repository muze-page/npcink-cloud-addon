<?php
/**
 * Media derivative Cloud transport helper.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Npcink_Cloud_Media_Derivative_Transport' ) ) {
	/**
	 * Converts local media derivative request contracts into signed Cloud jobs.
	 */
	final class Npcink_Cloud_Media_Derivative_Transport {
		private const REQUEST_CONTRACT_VERSION = 'media_derivative_cloud_request.v1';
		private const PROPOSAL_CONTRACT_VERSION = 'media_derivative_cloud_proposal.v1';
		private const MAX_UPLOAD_BYTES = 26214400;

		/**
		 * Dispatches a Cloud derivative job from an abilities-side request contract.
		 *
		 * @param array<string,mixed> $ability_response Ability response envelope.
		 * @param array<string,mixed> $source_artifact Short TTL source artifact descriptor supplied by the local host.
		 * @param string              $trace_id Optional trace id.
		 * @param string              $idempotency_key Optional idempotency key.
		 * @param array<string,mixed> $watermark_artifact Optional short TTL watermark artifact or upload descriptor.
		 * @return array<string,mixed>|WP_Error
		 */
		public static function dispatch_from_ability_response( array $ability_response, array $source_artifact, string $trace_id = '', string $idempotency_key = '', array $watermark_artifact = array() ) {
			$client = self::verified_client();
			if ( is_wp_error( $client ) ) {
				return $client;
			}

			$contract = self::extract_contract_data( $ability_response );
			$validated = self::validate_request_contract( $contract );
			if ( is_wp_error( $validated ) ) {
				return $validated;
			}

			if ( self::descriptor_has_upload_file( $source_artifact ) && self::descriptor_has_artifact_id( $source_artifact ) ) {
				return new WP_Error(
					'cloud_media_derivative_source_mode_conflict',
					__( 'Source upload and source artifact id cannot be sent together.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$source_upload = self::normalize_upload_file_descriptor( $source_artifact, 'source_file' );
			if ( is_wp_error( $source_upload ) ) {
				return $source_upload;
			}

			$source_reference = array();
			if ( empty( $source_upload ) ) {
				$source_reference = self::normalize_required_artifact_reference( $source_artifact, 'source' );
				if ( is_wp_error( $source_reference ) ) {
					return $source_reference;
				}
			}

			$watermark_upload = array();
			$watermark_reference = array();
			if ( ! empty( $watermark_artifact ) ) {
				if ( self::descriptor_has_upload_file( $watermark_artifact ) && self::descriptor_has_artifact_id( $watermark_artifact ) ) {
					return new WP_Error(
						'cloud_media_derivative_watermark_source_conflict',
						__( 'Watermark upload and watermark artifact id cannot be sent together.', 'npcink-cloud-addon' ),
						array( 'status' => 400 )
					);
				}
				$watermark_upload = self::normalize_upload_file_descriptor( $watermark_artifact, 'watermark_file' );
				if ( is_wp_error( $watermark_upload ) ) {
					return $watermark_upload;
				}
				if ( empty( $watermark_upload ) ) {
					$watermark_reference = self::normalize_required_artifact_reference( $watermark_artifact, 'watermark' );
					if ( is_wp_error( $watermark_reference ) ) {
						return $watermark_reference;
					}
				}
			}

			$media_payload = self::build_media_derivative_request_payload(
				$contract,
				$source_reference,
				$watermark_reference,
				! empty( $watermark_upload )
			);
			if ( is_wp_error( $media_payload ) ) {
				return $media_payload;
			}

			$files = array();
			if ( ! empty( $source_upload ) ) {
				$files['source_file'] = $source_upload;
			}
			if ( ! empty( $watermark_upload ) ) {
				$files['watermark_file'] = $watermark_upload;
			}
			return $client->create_media_derivative(
				$media_payload,
				$files,
				$trace_id,
				'' !== $idempotency_key ? $idempotency_key : 'media_derivative_' . wp_generate_uuid4()
			);
		}

		/**
		 * Reads and projects one Cloud media derivative run.
		 *
		 * @param string $run_id Cloud run id.
		 * @param string $trace_id Optional trace id.
		 * @return array<string,mixed>|WP_Error
		 */
		public static function get_run_projection( string $run_id, string $trace_id = '' ) {
			$client = self::verified_client();
			if ( is_wp_error( $client ) ) {
				return $client;
			}

			$result = $client->get_run( $run_id, $trace_id );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			return self::public_cloud_projection( $result );
		}

		/**
		 * Reads and projects one Cloud media derivative run result.
		 *
		 * @param string $run_id Cloud run id.
		 * @param string $trace_id Optional trace id.
		 * @return array<string,mixed>|WP_Error
		 */
		public static function get_run_result_projection( string $run_id, string $trace_id = '' ) {
			$client = self::verified_client();
			if ( is_wp_error( $client ) ) {
				return $client;
			}

			$result = $client->get_run_result( $run_id, $trace_id );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			return self::public_cloud_projection( $result );
		}

		/**
		 * Extracts a run id from supported Cloud response shapes.
		 *
		 * @param array<string,mixed> $cloud_response Cloud response.
		 * @return string
		 */
		public static function run_id( array $cloud_response ): string {
			$data = is_array( $cloud_response['data'] ?? null ) ? $cloud_response['data'] : $cloud_response;
			return sanitize_text_field( (string) ( $data['run_id'] ?? $data['id'] ?? $cloud_response['run_id'] ?? '' ) );
		}

		/**
		 * Returns a bounded run/result projection for local channel adapters.
		 *
		 * @param array<string,mixed> $cloud_response Cloud response.
		 * @return array<string,mixed>
		 */
		public static function public_cloud_projection( array $cloud_response ): array {
			$data       = is_array( $cloud_response['data'] ?? null ) ? $cloud_response['data'] : $cloud_response;
			$derivative = self::public_artifact_descriptor( self::artifact_from_cloud_result( $data ) );
			$error      = is_array( $data['error'] ?? null ) ? $data['error'] : array();
			$warnings   = is_array( $data['warnings'] ?? null ) ? $data['warnings'] : array();
			if ( empty( $warnings ) && is_array( $derivative['processing_warnings'] ?? null ) ) {
				$warnings = $derivative['processing_warnings'];
			}

			return array(
				'run_id'     => sanitize_text_field( (string) ( $data['run_id'] ?? $data['id'] ?? '' ) ),
				'status'     => sanitize_key( (string) ( $data['status'] ?? $cloud_response['status'] ?? '' ) ),
				'job_type'   => sanitize_key( (string) ( $data['job_type'] ?? $data['cloud_job_payload']['job_type'] ?? '' ) ),
				'created_at' => sanitize_text_field( (string) ( $data['created_at'] ?? '' ) ),
				'updated_at' => sanitize_text_field( (string) ( $data['updated_at'] ?? '' ) ),
				'derivative' => $derivative,
				'warnings'   => array_values( array_map( 'sanitize_text_field', $warnings ) ),
				'error'      => self::sanitize_projection_value( $error ),
			);
		}

		/**
		 * Infers a derivative artifact descriptor from a Cloud result.
		 *
		 * @param array<string,mixed> $cloud_result Cloud result.
		 * @return array<string,mixed>
		 */
		public static function artifact_from_cloud_result( array $cloud_result ): array {
			$data       = is_array( $cloud_result['data'] ?? null ) ? $cloud_result['data'] : $cloud_result;
			$derivative = is_array( $data['derivative'] ?? null ) ? $data['derivative'] : array();
			if ( empty( $derivative ) && is_array( $data['result']['artifact'] ?? null ) ) {
				$derivative = $data['result']['artifact'];
			}

			return self::sanitize_descriptor_for_projection( $derivative );
		}

		/**
		 * Removes local-only and inline-content artifact fields from projections.
		 *
		 * @param array<string,mixed> $artifact Artifact descriptor.
		 * @return array<string,mixed>
		 */
		public static function public_artifact_descriptor( array $artifact ): array {
			foreach ( array( 'path', 'file_path', 'tmp_name', 'bytes', 'content' ) as $key ) {
				unset( $artifact[ $key ] );
			}

			return $artifact;
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
			$binding_valid = self::validate_derivative_artifact_binding( $cloud_data, $artifact );
			if ( is_wp_error( $binding_valid ) ) {
				return $binding_valid;
			}

			$original = self::normalize_media_metrics( $contract['cloud_job_payload']['source_asset'] ?? array() );
			$derivative = self::normalize_media_metrics(
				array_merge(
					is_array( $cloud_data['derivative'] ?? null ) ? $cloud_data['derivative'] : array(),
					$artifact
				)
			);
			$warnings = self::sanitize_string_list( $contract['cloud_job_payload']['warnings'] ?? array() );
			$warnings = array_merge( $warnings, self::sanitize_string_list( $cloud_data['warnings'] ?? array() ) );
			$warnings = self::append_metric_warnings( $warnings, $original, $derivative );

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
		 * Builds a Core from-plan media optimization payload for local adapters.
		 *
		 * This does not create, approve, preflight, or execute a proposal.
		 *
		 * @param array<string,mixed> $ability_response Ability response envelope.
		 * @param array<string,mixed> $cloud_result Cloud run result envelope.
		 * @param array<string,mixed> $derivative_artifact Cloud derivative artifact descriptor.
		 * @param array<string,mixed> $media_details_input Reviewed media metadata input.
		 * @return array<string,mixed>|WP_Error
		 */
		public static function build_media_optimization_payload( array $ability_response, array $cloud_result, array $derivative_artifact, array $media_details_input ) {
			if ( empty( $derivative_artifact ) ) {
				$derivative_artifact = self::artifact_from_cloud_result( $cloud_result );
			}

			$proposal_payload = self::build_local_proposal_payload( $ability_response, $cloud_result, $derivative_artifact );
			if ( is_wp_error( $proposal_payload ) ) {
				return $proposal_payload;
			}

			$ability_data = is_array( $ability_response['data'] ?? null ) ? $ability_response['data'] : array();
			if ( is_array( $ability_data['content_reference_repairs_preview'] ?? null ) && ! is_array( $proposal_payload['content_reference_repairs_preview'] ?? null ) ) {
				$proposal_payload['content_reference_repairs_preview'] = $ability_data['content_reference_repairs_preview'];
			}

			$optimization_plan = self::media_optimization_plan_from_derivative_payload( $proposal_payload, $media_details_input );
			$response_payload  = array(
				'contract_version'        => 'media_derivative_cloud_optimization_payload.v1',
				'proposal_payload'        => $proposal_payload,
				'media_optimization_plan' => $optimization_plan,
				'core_proposal_required'  => true,
				'commit_execution'        => false,
				'proposal_ready'          => true === (bool) ( $optimization_plan['proposal_ready'] ?? false ),
				'preferred_core_route'    => 'POST /proposals/from-plan',
				'legacy_derivative_proposal_payload_available' => true,
				'required_plan_ability_id' => 'npcink-abilities-toolkit/build-media-optimization-plan',
			);

			if ( is_array( $optimization_plan['write_actions'] ?? null ) && count( (array) $optimization_plan['write_actions'] ) >= 2 ) {
				$response_payload['from_plan_request'] = array(
					'plan_ability_id' => 'npcink-abilities-toolkit/build-media-optimization-plan',
					'plan'            => $optimization_plan,
				);
				$response_payload['next_step'] = 'POST /proposals/from-plan with from_plan_request for one Core batch proposal.';
			} else {
				$response_payload['next_step'] = 'Provide reviewed media_details_input, then build the media derivative optimization payload again and submit the returned from_plan_request to Core; do not split one optimize-media intent into two proposals.';
			}

			return $response_payload;
		}

		/**
		 * Downloads a derivative artifact for local preview through a signed Cloud request.
		 *
		 * This returns bytes only to the trusted local caller. It does not persist,
		 * register, adopt, or write the artifact into WordPress.
		 *
		 * @param array<string,mixed> $derivative_artifact Cloud derivative artifact descriptor.
		 * @param string              $trace_id Optional trace id.
		 * @return array<string,mixed>|WP_Error
		 */
		public static function download_artifact_preview( array $derivative_artifact, string $trace_id = '' ) {
			$client = self::verified_client();
			if ( is_wp_error( $client ) ) {
				return $client;
			}

			$artifact = self::normalize_artifact_descriptor( $derivative_artifact, 'derivative' );
			if ( is_wp_error( $artifact ) ) {
				return $artifact;
			}

			$download = $client->download_media_derivative_artifact(
				(string) $artifact['artifact_id'],
				$trace_id
			);
			if ( is_wp_error( $download ) ) {
				return $download;
			}

			$contents = is_string( $download['body'] ?? null ) ? $download['body'] : '';
			if ( '' === $contents ) {
				return new WP_Error(
					'cloud_media_derivative_artifact_empty',
					__( 'Cloud derivative artifact download returned no bytes.', 'npcink-cloud-addon' ),
					array( 'status' => 502 )
				);
			}

			$artifact_mime = self::normalize_media_type( (string) ( $artifact['mime_type'] ?? '' ) );
			$response_mime = self::normalize_response_mime_type( (string) ( $download['content_type'] ?? '' ) );
			if ( '' !== $artifact_mime && '' !== $response_mime && $artifact_mime !== $response_mime ) {
				return new WP_Error(
					'cloud_media_derivative_artifact_mime_mismatch',
					__( 'Cloud derivative artifact mime type does not match the descriptor.', 'npcink-cloud-addon' ),
					array( 'status' => 409 )
				);
			}

			$mime_type = '' !== $artifact_mime ? $artifact_mime : $response_mime;
			if ( '' === $mime_type ) {
				return new WP_Error(
					'cloud_media_derivative_artifact_mime_invalid',
					__( 'Media derivative artifact mime type must be a supported image type.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$actual_sha256 = hash( 'sha256', $contents );
			if ( '' !== (string) ( $artifact['sha256'] ?? '' ) && $actual_sha256 !== (string) $artifact['sha256'] ) {
				return new WP_Error(
					'cloud_media_derivative_artifact_checksum_mismatch',
					__( 'Derivative artifact checksum does not match the downloaded bytes.', 'npcink-cloud-addon' ),
					array( 'status' => 409 )
				);
			}

			return array(
				'artifact_id'    => (string) $artifact['artifact_id'],
				'contents'       => $contents,
				'mime_type'      => $mime_type,
				'filesize_bytes' => strlen( $contents ),
				'sha256'         => $actual_sha256,
				'expires_at'     => (string) $artifact['expires_at'],
			);
		}

		/**
		 * Builds the Core from-plan media optimization payload shape.
		 *
		 * @param array<string,mixed> $proposal_payload Cloud derivative proposal payload.
		 * @param array<string,mixed> $media_details_input Reviewed metadata action input.
		 * @return array<string,mixed>
		 */
		private static function media_optimization_plan_from_derivative_payload( array $proposal_payload, array $media_details_input ): array {
			$attachment_id  = absint( $proposal_payload['attachment_id'] ?? 0 );
			$artifact       = is_array( $proposal_payload['artifact'] ?? null ) ? $proposal_payload['artifact'] : array();
			$original       = is_array( $proposal_payload['original'] ?? null ) ? $proposal_payload['original'] : array();
			$derivative     = is_array( $proposal_payload['derivative'] ?? null ) ? $proposal_payload['derivative'] : array();
			$metadata_input = self::sanitize_media_details_plan_input( $attachment_id, $media_details_input );

			$metadata_preview = array(
				'before' => array(),
				'after'  => array_diff_key( $metadata_input, array( 'attachment_id' => true ) ),
			);
			$derivative_preview = array(
				'before' => array(
					'mime_type'      => sanitize_text_field( (string) ( $original['mime_type'] ?? '' ) ),
					'width'          => absint( $original['width'] ?? 0 ),
					'height'         => absint( $original['height'] ?? 0 ),
					'filesize_bytes' => absint( $original['filesize_bytes'] ?? 0 ),
				),
				'after'  => array(
					'artifact_id'    => sanitize_text_field( (string) ( $artifact['artifact_id'] ?? '' ) ),
					'mime_type'      => sanitize_text_field( (string) ( $derivative['mime_type'] ?? ( $artifact['mime_type'] ?? '' ) ) ),
					'width'          => absint( $derivative['width'] ?? ( $artifact['width'] ?? 0 ) ),
					'height'         => absint( $derivative['height'] ?? ( $artifact['height'] ?? 0 ) ),
					'filesize_bytes' => absint( $derivative['filesize_bytes'] ?? ( $artifact['filesize_bytes'] ?? 0 ) ),
				),
			);
			$content_reference_repairs_preview = array();
			if ( is_array( $proposal_payload['content_reference_repairs_preview'] ?? null ) ) {
				$content_reference_repairs_preview = $proposal_payload['content_reference_repairs_preview'];
			} elseif ( is_array( $proposal_payload['derivative_preview']['content_reference_repairs'] ?? null ) ) {
				$content_reference_repairs_preview = $proposal_payload['derivative_preview']['content_reference_repairs'];
			} elseif ( is_array( $derivative['content_reference_repairs'] ?? null ) ) {
				$content_reference_repairs_preview = $derivative['content_reference_repairs'];
			}
			if ( ! empty( $content_reference_repairs_preview ) ) {
				$derivative_preview['content_reference_repairs'] = $content_reference_repairs_preview;
			}

			$plan = array(
				'artifact_type'      => 'media_optimization_plan',
				'version'            => 1,
				'batch_id'           => 'media_optimization_' . $attachment_id . '_' . gmdate( 'Ymd_His' ),
				'attachment_id'      => $attachment_id,
				'optimization_goal'  => 'image_seo_and_derivative_adoption',
				'requires_approval'  => true,
				'dry_run'            => true,
				'commit_execution'   => false,
				'proposal_mode'      => 'batch',
				'batch_approval'     => true,
				'action_count'       => 0,
				'action_ids'         => array(),
				'target_ability_ids' => array(),
				'metadata_preview'   => $metadata_preview,
				'derivative_preview' => $derivative_preview,
				'content_reference_repairs_preview' => $content_reference_repairs_preview,
				'preview'            => array(),
				'write_actions'      => array(),
				'requires_input'     => array(),
				'proposal_ready'     => false,
				'risk'               => array(
					'level'  => 'medium',
					'reason' => 'One attachment metadata update and one reviewed Cloud derivative adoption share one Core approval.',
				),
			);

			if ( $attachment_id <= 0 || empty( $artifact['artifact_id'] ) ) {
				$plan['requires_input'][] = 'valid_derivative_proposal_payload';
				return $plan;
			}

			if ( count( $metadata_input ) <= 1 ) {
				$plan['requires_input'][] = 'media_details_input';
				return $plan;
			}

			$derivative_input = array(
				'attachment_id'       => $attachment_id,
				'derivative_artifact' => $artifact,
			);
			$current_mime    = sanitize_text_field( (string) ( $original['mime_type'] ?? '' ) );
			$derivative_mime = sanitize_text_field( (string) ( $derivative['mime_type'] ?? ( $artifact['mime_type'] ?? '' ) ) );
			if ( '' !== $current_mime ) {
				$derivative_input['expected_current_mime_type'] = $current_mime;
			}
			if ( '' !== $derivative_mime ) {
				$derivative_input['expected_derivative_mime_type'] = $derivative_mime;
			}
			if ( ! empty( $content_reference_repairs_preview ) ) {
				$derivative_input['expected_content_reference_post_ids'] = array_slice(
					array_values(
						array_unique(
							array_filter(
								array_map(
									static function ( $repair ) {
										return absint( is_array( $repair ) ? ( $repair['post_id'] ?? 0 ) : 0 );
									},
									(array) ( $content_reference_repairs_preview['repairs'] ?? array() )
								)
							)
						)
					),
					0,
					50
				);
				$derivative_input['expected_content_reference_post_count'] = absint( $content_reference_repairs_preview['post_count'] ?? 0 );
				$derivative_input['expected_content_reference_replacement_count'] = absint( $content_reference_repairs_preview['replacement_count'] ?? 0 );
			}

			$write_actions = array(
				self::plan_action( 'update_media_details_' . $attachment_id, 'npcink-abilities-toolkit/update-media-details', $metadata_input, 'medium', 'Apply reviewed media SEO and source metadata as part of one media optimization approval.' ),
				self::plan_action( 'adopt_cloud_media_derivative_' . $attachment_id, 'npcink-abilities-toolkit/adopt-cloud-media-derivative', $derivative_input, 'medium', 'Adopt the reviewed Cloud derivative artifact as the attachment main file after Core approval.' ),
			);
			$action_ids = array_values(
				array_map(
					static function ( $action ) {
						return is_array( $action ) ? sanitize_key( (string) ( $action['action_id'] ?? '' ) ) : '';
					},
					$write_actions
				)
			);
			$target_ability_ids = array_values(
				array_unique(
					array_filter(
						array_map(
							static function ( $action ) {
								return is_array( $action ) ? sanitize_text_field( (string) ( $action['target_ability_id'] ?? '' ) ) : '';
							},
							$write_actions
						)
					)
				)
			);
			$plan['write_actions']      = $write_actions;
			$plan['action_count']       = count( $plan['write_actions'] );
			$plan['action_ids']         = $action_ids;
			$plan['target_ability_ids'] = $target_ability_ids;
			$plan['proposal_ready']     = true;
			$plan['preview'][]          = array(
				'attachment_id'    => $attachment_id,
				'before'           => array(
					'metadata'   => array(),
					'derivative' => $derivative_preview['before'],
				),
				'after_suggestion' => array(
					'metadata'   => $metadata_preview['after'],
					'derivative' => $derivative_preview['after'],
				),
				'action_ids'         => $action_ids,
				'target_ability_ids' => $target_ability_ids,
			);

			return $plan;
		}

		/**
		 * Sanitizes update-media-details input for a generated plan.
		 *
		 * @param int                 $attachment_id Attachment id.
		 * @param array<string,mixed> $input Raw metadata input.
		 * @return array<string,mixed>
		 */
		private static function sanitize_media_details_plan_input( int $attachment_id, array $input ): array {
			$output = array( 'attachment_id' => $attachment_id );
			foreach ( array( 'title', 'alt', 'caption', 'description', 'source_page_url', 'photographer_name', 'attribution_text', 'copyright_notice' ) as $field ) {
				if ( array_key_exists( $field, $input ) && '' !== (string) $input[ $field ] ) {
					$output[ $field ] = 'source_page_url' === $field ? esc_url_raw( (string) $input[ $field ] ) : sanitize_text_field( (string) $input[ $field ] );
				}
			}
			if ( array_key_exists( 'source_type', $input ) ) {
				$source_type = sanitize_key( (string) $input['source_type'] );
				if ( in_array( $source_type, array( 'owned', 'ai_generated', 'stock', 'external', 'test' ), true ) ) {
					$output['source_type'] = $source_type;
				}
			}
			return $output;
		}

		/**
		 * Builds one local-host plan action.
		 *
		 * @param string              $action_id Action id.
		 * @param string              $ability_id Target ability id.
		 * @param array<string,mixed> $input Target ability input.
		 * @param string              $risk Risk.
		 * @param string              $reason Reason.
		 * @return array<string,mixed>
		 */
		private static function plan_action( string $action_id, string $ability_id, array $input, string $risk, string $reason ): array {
			$input['dry_run'] = true;
			$input['commit']  = false;
			return array(
				'action_id'         => sanitize_key( $action_id ),
				'target_ability_id' => sanitize_text_field( $ability_id ),
				'input'             => $input,
				'requires_approval' => true,
				'commit_execution'  => false,
				'required_scopes'   => array( 'media.write' ),
				'risk'              => sanitize_key( $risk ),
				'reason'            => sanitize_text_field( $reason ),
				'requires_input'    => array(),
				'proposal_ready'    => true,
			);
		}

		/**
		 * Sanitizes a descriptor for projection without enforcing artifact expiry.
		 *
		 * @param array<string,mixed> $descriptor Descriptor.
		 * @return array<string,mixed>
		 */
		private static function sanitize_descriptor_for_projection( array $descriptor ): array {
			$clean = array();
			foreach ( array( 'artifact_id', 'id', 'download_url', 'url', 'expires_at', 'run_id', 'mime_type', 'format', 'path', 'file_path', 'tmp_name', 'filename', 'name', 'field_name', 'sha256', 'checksum' ) as $key ) {
				if ( isset( $descriptor[ $key ] ) && is_scalar( $descriptor[ $key ] ) ) {
					$clean[ $key ] = sanitize_text_field( (string) $descriptor[ $key ] );
				}
			}
			if ( ! isset( $clean['sha256'] ) && isset( $clean['checksum'] ) && 0 === strpos( strtolower( (string) $clean['checksum'] ), 'sha256:' ) ) {
				$clean['sha256'] = substr( strtolower( (string) $clean['checksum'] ), 7 );
			}
			foreach ( array( 'width', 'height', 'filesize_bytes', 'size_bytes' ) as $key ) {
				if ( isset( $descriptor[ $key ] ) ) {
					$clean[ $key ] = absint( $descriptor[ $key ] );
				}
			}
			if ( is_array( $descriptor['processing_warnings'] ?? null ) ) {
				$clean['processing_warnings'] = array_values( array_map( 'sanitize_text_field', $descriptor['processing_warnings'] ) );
			}

			return $clean;
		}

		/**
		 * Sanitizes bounded Cloud projection values recursively.
		 *
		 * @param mixed $value Raw value.
		 * @return mixed
		 */
		private static function sanitize_projection_value( $value ) {
			if ( is_array( $value ) ) {
				$clean = array();
				foreach ( $value as $key => $item ) {
					$clean[ is_int( $key ) ? $key : sanitize_key( (string) $key ) ] = self::sanitize_projection_value( $item );
				}
				return $clean;
			}

			if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
				return $value;
			}

			return sanitize_text_field( (string) $value );
		}

		/**
		 * Returns a verified runtime client or a fail-closed error.
		 *
		 * @return Npcink_Cloud_Runtime_Client|WP_Error
		 */
		public static function verified_client() {
			if ( ! Npcink_Cloud_Addon_Settings::is_verified() ) {
				return new WP_Error(
					'cloud_runtime_unverified',
					__( 'Npcink Cloud credentials must verify before dispatching media derivative jobs.', 'npcink-cloud-addon' ),
					array( 'status' => 403 )
				);
			}

			return new Npcink_Cloud_Runtime_Client( Npcink_Cloud_Addon_Settings::get_settings() );
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
			$data = is_array( $cloud_result['data'] ?? null ) ? $cloud_result['data'] : $cloud_result;
			if ( empty( $data['derivative'] ) && is_array( $data['result']['artifact'] ?? null ) ) {
				$data['derivative'] = self::normalize_result_artifact_for_cloud_data( $data['result']['artifact'] );
			}

			return $data;
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
					__( 'Media derivative request contract version is invalid.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( empty( $contract['readonly'] ) || empty( $contract['proposal_only'] ) ) {
				return new WP_Error(
					'cloud_media_derivative_contract_not_readonly',
					__( 'Media derivative request must be read-only and proposal-only.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( 'local_wordpress_host' !== (string) ( $contract['local_adoption']['final_write_owner'] ?? '' ) ) {
				return new WP_Error(
					'cloud_media_derivative_write_owner_invalid',
					__( 'Media derivative final write owner must remain the local WordPress host.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( ! empty( $contract['local_adoption']['wordpress_write_included'] ) ) {
				return new WP_Error(
					'cloud_media_derivative_wordpress_write_present',
					__( 'Media derivative request must not include WordPress writes.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( self::contains_forbidden_secret_fields( $contract ) ) {
				return new WP_Error(
					'cloud_media_derivative_credentials_present',
					__( 'Media derivative ability payload must not include credentials or signed headers.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$job_payload = is_array( $contract['cloud_job_payload'] ?? null ) ? $contract['cloud_job_payload'] : array();
			if ( 'generate_optimized_media_derivative' !== (string) ( $job_payload['job_type'] ?? '' ) ) {
				return new WP_Error(
					'cloud_media_derivative_job_type_invalid',
					__( 'Cloud media derivative job type is invalid.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( ! empty( $job_payload['requested_derivative']['replace_original'] ) ) {
				return new WP_Error(
					'cloud_media_derivative_replace_original_requested',
					__( 'Media derivative jobs must not request original file replacement.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			return true;
		}

		/**
		 * Builds the strict Cloud media derivative request payload.
		 *
		 * @param array<string,mixed> $contract Ability contract data.
		 * @param array<string,mixed> $source_reference Optional source artifact reference.
		 * @param array<string,mixed> $watermark_reference Optional watermark artifact reference.
		 * @param bool                $has_watermark_upload Whether a watermark file is attached.
		 * @return array<string,mixed>|WP_Error
		 */
		private static function build_media_derivative_request_payload( array $contract, array $source_reference, array $watermark_reference, bool $has_watermark_upload ) {
			$job_payload = is_array( $contract['cloud_job_payload'] ?? null ) ? $contract['cloud_job_payload'] : array();
			$requested = is_array( $job_payload['requested_derivative'] ?? null ) ? $job_payload['requested_derivative'] : array();
			$watermark = is_array( $job_payload['watermark'] ?? null ) ? $job_payload['watermark'] : array();
			$watermark_type = sanitize_key( (string) ( $watermark['type'] ?? 'image' ) );
			if ( ! in_array( $watermark_type, array( 'image', 'text' ), true ) ) {
				$watermark_type = 'image';
			}

			if ( ( ! empty( $watermark_reference ) || $has_watermark_upload ) && empty( $watermark ) ) {
				return new WP_Error(
					'cloud_media_derivative_watermark_plan_missing',
					__( 'Watermark artifact transport requires a watermark plan in the ability response.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( 'text' === $watermark_type && ( $has_watermark_upload || ! empty( $watermark_reference ) || ! empty( $watermark['artifact_id'] ) ) ) {
				return new WP_Error(
					'cloud_media_derivative_watermark_source_conflict',
					__( 'Text watermark plans must not include a watermark upload or artifact id.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( 'image' === $watermark_type && $has_watermark_upload && ! empty( $watermark['artifact_id'] ) ) {
				return new WP_Error(
					'cloud_media_derivative_watermark_source_conflict',
					__( 'Watermark upload and watermark artifact id cannot be sent together.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( 'image' === $watermark_type && ! empty( $watermark ) && empty( $watermark['artifact_id'] ) && empty( $watermark_reference ) && ! $has_watermark_upload ) {
				return new WP_Error(
					'cloud_media_derivative_watermark_source_missing',
					__( 'Watermark plans require a watermark upload or artifact id before Cloud dispatch.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$target_format = sanitize_key( (string) ( $job_payload['target_format'] ?? $requested['format'] ?? '' ) );
			if ( ! in_array( $target_format, array( 'webp', 'avif', 'jpeg', 'png', 'original' ), true ) ) {
				return new WP_Error(
					'cloud_media_derivative_target_format_missing',
					__( 'Media derivative request must include a bounded target format from the ability response.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$max_width = absint( $job_payload['max_width'] ?? $requested['max_width'] ?? 0 );
			if ( $max_width <= 0 ) {
				return new WP_Error(
					'cloud_media_derivative_max_width_missing',
					__( 'Media derivative request must include max_width from the ability response.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$quality = absint( $job_payload['quality'] ?? $requested['quality'] ?? 0 );
			if ( $quality <= 0 ) {
				return new WP_Error(
					'cloud_media_derivative_quality_missing',
					__( 'Media derivative request must include quality from the ability response.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$cloud_job_payload = array(
				'job_type'          => 'generate_optimized_media_derivative',
				'target_format'     => $target_format,
				'max_width'         => max( 1, min( 10000, $max_width ) ),
				'quality'           => max( 1, min( 100, $quality ) ),
			);
			$raw_source_media_type = (string) ( $job_payload['source_media_type'] ?? '' );
			$source_media_type = self::normalize_media_type( $raw_source_media_type, true );
			if ( '' !== trim( $raw_source_media_type ) && '' === $source_media_type ) {
				return new WP_Error(
					'cloud_media_derivative_source_media_type_invalid',
					__( 'Media derivative source media type must be a supported image type.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( '' !== $source_media_type ) {
				$cloud_job_payload['source_media_type'] = $source_media_type;
			}
			if ( is_array( $job_payload['crop'] ?? null ) && ! empty( $job_payload['crop'] ) ) {
				$cloud_job_payload['crop'] = self::sanitize_crop_payload( $job_payload['crop'] );
			}
			if ( ! empty( $watermark ) ) {
				$cloud_job_payload['watermark'] = self::sanitize_watermark_payload( $watermark );
				if ( ! empty( $watermark_reference['artifact_id'] ) ) {
					if ( ! empty( $cloud_job_payload['watermark']['artifact_id'] ) && $watermark_reference['artifact_id'] !== $cloud_job_payload['watermark']['artifact_id'] ) {
						return new WP_Error(
							'cloud_media_derivative_watermark_source_conflict',
							__( 'Watermark artifact id does not match the ability watermark plan.', 'npcink-cloud-addon' ),
							array( 'status' => 400 )
						);
					}
					$cloud_job_payload['watermark']['artifact_id'] = $watermark_reference['artifact_id'];
				}
				if ( $has_watermark_upload ) {
					unset( $cloud_job_payload['watermark']['artifact_id'] );
				}
			}
			$payload = array(
				'request_contract_version' => self::REQUEST_CONTRACT_VERSION,
				'cloud_job_payload'        => $cloud_job_payload,
				'ttl_minutes'              => 30,
			);
			if ( ! empty( $source_reference['artifact_id'] ) ) {
				$payload['source'] = array(
					'artifact_id' => $source_reference['artifact_id'],
				);
			}

			return $payload;
		}

		/**
		 * Sanitizes Cloud crop options for bounded aspect-ratio derivative processing.
		 *
		 * @param array<string,mixed> $crop Crop payload.
		 * @return array<string,string>
		 */
		private static function sanitize_crop_payload( array $crop ): array {
			$aspect_ratio = trim( sanitize_text_field( (string) ( $crop['aspect_ratio'] ?? '16:9' ) ) );
			if ( 1 !== preg_match( '/^([1-9][0-9]{0,2}):([1-9][0-9]{0,2})$/', $aspect_ratio, $matches ) ) {
				$aspect_ratio = '16:9';
			} else {
				$aspect_ratio = max( 1, min( 100, absint( $matches[1] ) ) ) . ':' . max( 1, min( 100, absint( $matches[2] ) ) );
			}

			$position = sanitize_key( (string) ( $crop['position'] ?? 'center' ) );
			if ( ! in_array( $position, array( 'top_left', 'top', 'top_right', 'left', 'center', 'right', 'bottom_left', 'bottom', 'bottom_right' ), true ) ) {
				$position = 'center';
			}

			return array(
				'type'         => 'aspect_ratio',
				'aspect_ratio' => $aspect_ratio,
				'position'     => $position,
			);
		}

		/**
		 * Sanitizes Cloud watermark options without creating a logo registry.
		 *
		 * @param array<string,mixed> $watermark Watermark payload.
		 * @return array<string,mixed>
		 */
		private static function sanitize_watermark_payload( array $watermark ): array {
			$type = sanitize_key( (string) ( $watermark['type'] ?? 'image' ) );
			if ( ! in_array( $type, array( 'image', 'text' ), true ) ) {
				$type = 'image';
			}

			if ( 'text' === $type ) {
				$text = sanitize_text_field( (string) ( $watermark['text'] ?? 'AI' ) );
				if ( '' === $text ) {
					$text = 'AI';
				}
				$text = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 64 ) : substr( $text, 0, 64 );

				return array(
					'type'       => 'text',
					'text'       => $text,
					'position'   => sanitize_key( (string) ( $watermark['position'] ?? 'bottom_right' ) ),
					'opacity'    => is_numeric( $watermark['opacity'] ?? null ) ? max( 0.0, min( 1.0, (float) $watermark['opacity'] ) ) : 0.75,
					'font_size'  => max( 8, min( 256, absint( $watermark['font_size'] ?? 48 ) ) ),
					'color'      => self::sanitize_watermark_color( $watermark['color'] ?? '#FFFFFF', '#FFFFFF' ),
					'background' => self::sanitize_watermark_color( $watermark['background'] ?? 'rgba(0,0,0,0.35)', 'rgba(0,0,0,0.35)' ),
					'margin_px'  => max( 0, min( 1000, absint( $watermark['margin_px'] ?? 24 ) ) ),
				);
			}

			$sanitized = array(
				'type'          => 'image',
				'position'      => sanitize_key( (string) ( $watermark['position'] ?? 'bottom_right' ) ),
				'opacity'       => is_numeric( $watermark['opacity'] ?? null ) ? max( 0.0, min( 1.0, (float) $watermark['opacity'] ) ) : 0.75,
				'scale_percent' => max( 1, min( 100, absint( $watermark['scale_percent'] ?? 18 ) ) ),
				'margin_px'     => max( 0, min( 1000, absint( $watermark['margin_px'] ?? 24 ) ) ),
			);
			$artifact_id = sanitize_text_field( (string) ( $watermark['artifact_id'] ?? '' ) );
			if ( '' !== $artifact_id ) {
				$sanitized['artifact_id'] = $artifact_id;
			}

			return $sanitized;
		}

		/**
		 * Sanitizes a text watermark color token for Cloud transport.
		 *
		 * @param mixed  $value Raw color.
		 * @param string $default Default color.
		 * @return string
		 */
		private static function sanitize_watermark_color( $value, string $default ): string {
			$color = trim( sanitize_text_field( (string) $value ) );
			if ( 'transparent' === strtolower( $color ) ) {
				return 'transparent';
			}
			if ( 1 === preg_match( '/^#[0-9A-Fa-f]{3}([0-9A-Fa-f]{3})?$/', $color ) ) {
				return strtoupper( $color );
			}
			if ( 1 === preg_match( '/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})(?:\s*,\s*(0|1|0?\.\d+))?\s*\)$/', $color, $matches ) ) {
				$r     = max( 0, min( 255, (int) $matches[1] ) );
				$g     = max( 0, min( 255, (int) $matches[2] ) );
				$b     = max( 0, min( 255, (int) $matches[3] ) );
				$alpha = isset( $matches[4] ) && '' !== $matches[4] ? max( 0, min( 1, (float) $matches[4] ) ) : null;

				return null === $alpha
					? sprintf( 'rgb(%d,%d,%d)', $r, $g, $b )
					: sprintf( 'rgba(%d,%d,%d,%s)', $r, $g, $b, rtrim( rtrim( sprintf( '%.3F', $alpha ), '0' ), '.' ) );
			}

			return $default;
		}

		/**
		 * Normalizes an upload file descriptor.
		 *
		 * @param array<string,mixed> $descriptor Local upload descriptor.
		 * @param string              $field_name Multipart field name.
		 * @return array<string,string>|WP_Error
		 */
		private static function normalize_upload_file_descriptor( array $descriptor, string $field_name ) {
			$contents = '';
			if ( is_string( $descriptor['bytes'] ?? null ) ) {
				$contents = (string) $descriptor['bytes'];
			} elseif ( is_string( $descriptor['content'] ?? null ) ) {
				$contents = (string) $descriptor['content'];
			} else {
				$path = sanitize_text_field( (string) ( $descriptor['path'] ?? $descriptor['file_path'] ?? $descriptor['tmp_name'] ?? '' ) );
				if ( '' === $path ) {
					return array();
				}
				if ( ! is_readable( $path ) ) {
					return new WP_Error(
						'cloud_media_derivative_upload_file_unreadable',
						__( 'Media derivative upload file is not readable.', 'npcink-cloud-addon' ),
						array( 'status' => 400 )
					);
				}
				$size = filesize( $path );
				if ( false !== $size && $size > self::MAX_UPLOAD_BYTES ) {
					return new WP_Error(
						'cloud_media_derivative_upload_file_too_large',
						__( 'Media derivative upload file exceeds the Cloud size limit.', 'npcink-cloud-addon' ),
						array( 'status' => 413 )
					);
				}
				$read = file_get_contents( $path );
				$contents = is_string( $read ) ? $read : '';
			}

			if ( '' === $contents ) {
				return new WP_Error(
					'cloud_media_derivative_upload_file_empty',
					__( 'Media derivative upload file is empty.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( strlen( $contents ) > self::MAX_UPLOAD_BYTES ) {
				return new WP_Error(
					'cloud_media_derivative_upload_file_too_large',
					__( 'Media derivative upload file exceeds the Cloud size limit.', 'npcink-cloud-addon' ),
					array( 'status' => 413 )
				);
			}

			return array(
				'field_name' => sanitize_key( $field_name ),
				'filename'   => sanitize_file_name( (string) ( $descriptor['filename'] ?? $descriptor['name'] ?? $field_name ) ),
				'mime_type'  => sanitize_text_field( (string) ( $descriptor['mime_type'] ?? 'application/octet-stream' ) ),
				'contents'   => $contents,
			);
		}

		/**
		 * Returns whether a descriptor includes a local upload source.
		 *
		 * @param array<string,mixed> $descriptor Artifact or upload descriptor.
		 * @return bool
		 */
		private static function descriptor_has_upload_file( array $descriptor ): bool {
			return is_string( $descriptor['bytes'] ?? null )
				|| is_string( $descriptor['content'] ?? null )
				|| '' !== (string) ( $descriptor['path'] ?? $descriptor['file_path'] ?? $descriptor['tmp_name'] ?? '' );
		}

		/**
		 * Returns whether a descriptor includes a Cloud artifact id.
		 *
		 * @param array<string,mixed> $descriptor Artifact or upload descriptor.
		 * @return bool
		 */
		private static function descriptor_has_artifact_id( array $descriptor ): bool {
			return '' !== sanitize_text_field( (string) ( $descriptor['artifact_id'] ?? $descriptor['id'] ?? '' ) );
		}

		/**
		 * Normalizes a required Cloud artifact id reference for runtime processing.
		 *
		 * @param array<string,mixed> $artifact Artifact descriptor.
		 * @param string              $role Artifact role.
		 * @return array<string,mixed>|WP_Error
		 */
		private static function normalize_required_artifact_reference( array $artifact, string $role ) {
			$normalized = self::normalize_artifact_descriptor( $artifact, $role );
			if ( is_wp_error( $normalized ) ) {
				return $normalized;
			}
			if ( '' === (string) ( $normalized['artifact_id'] ?? '' ) ) {
				return new WP_Error(
					'cloud_media_derivative_artifact_id_missing',
					__( 'Media derivative runtime artifacts require an artifact id.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			return $normalized;
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
					__( 'Media derivative artifact expiry is required.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( self::is_expired( $expires_at ) ) {
				return new WP_Error(
					'cloud_media_derivative_artifact_expired',
					__( 'Expired Cloud artifacts cannot be adopted.', 'npcink-cloud-addon' ),
					array( 'status' => 409 )
				);
			}

			$artifact_id = sanitize_text_field( (string) ( $artifact['artifact_id'] ?? $artifact['id'] ?? '' ) );
			if ( 'derivative' === $role && '' === $artifact_id ) {
				return new WP_Error(
					'cloud_media_derivative_derivative_artifact_id_missing',
					__( 'Derivative Cloud artifacts require an artifact id before local proposal adoption.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$download_url = esc_url_raw( (string) ( $artifact['download_url'] ?? $artifact['url'] ?? '' ) );
			if ( '' === $artifact_id && '' === $download_url ) {
				return new WP_Error(
					'cloud_media_derivative_artifact_ref_missing',
					__( 'Media derivative artifact requires an artifact id or download URL.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			$raw_mime_type = (string) ( $artifact['mime_type'] ?? '' );
			$mime_type = self::normalize_media_type( $raw_mime_type );
			if ( '' !== trim( $raw_mime_type ) && '' === $mime_type ) {
				return new WP_Error(
					'cloud_media_derivative_artifact_mime_invalid',
					__( 'Media derivative artifact mime type must be a supported image type.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			return array(
				'role'           => sanitize_key( $role ),
				'artifact_id'    => $artifact_id,
				'download_url'   => $download_url,
				'expires_at'     => $expires_at,
				'run_id'         => sanitize_text_field( (string) ( $artifact['run_id'] ?? '' ) ),
				'mime_type'      => $mime_type,
				'width'          => absint( $artifact['width'] ?? 0 ),
				'height'         => absint( $artifact['height'] ?? 0 ),
				'filesize_bytes' => absint( $artifact['filesize_bytes'] ?? $artifact['size_bytes'] ?? 0 ),
				'sha256'         => self::normalize_sha256( (string) ( $artifact['sha256'] ?? $artifact['checksum'] ?? '' ) ),
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
			$raw_mime_type = (string) ( $metrics['mime_type'] ?? '' );

			return array(
				'width'          => absint( $metrics['width'] ?? 0 ),
				'height'         => absint( $metrics['height'] ?? 0 ),
				'filesize_bytes' => absint( $metrics['filesize_bytes'] ?? $metrics['size_bytes'] ?? 0 ),
				'mime_type'      => self::normalize_media_type( $raw_mime_type ),
			);
		}

		/**
		 * Validates that the adopted artifact matches the Cloud result summary.
		 *
		 * @param array<string,mixed> $cloud_data Cloud result data.
		 * @param array<string,mixed> $artifact Normalized derivative artifact.
		 * @return true|WP_Error
		 */
		private static function validate_derivative_artifact_binding( array $cloud_data, array $artifact ) {
			$derivative = is_array( $cloud_data['derivative'] ?? null ) ? $cloud_data['derivative'] : array();
			$result_artifact_id = sanitize_text_field( (string) ( $derivative['artifact_id'] ?? $derivative['id'] ?? '' ) );
			$artifact_id = sanitize_text_field( (string) ( $artifact['artifact_id'] ?? '' ) );
			if ( '' !== $result_artifact_id && '' !== $artifact_id && $result_artifact_id !== $artifact_id ) {
				return new WP_Error(
					'cloud_media_derivative_artifact_binding_mismatch',
					__( 'Derivative artifact id does not match the Cloud result.', 'npcink-cloud-addon' ),
					array( 'status' => 409 )
				);
			}

			$result_run_id = sanitize_text_field( (string) ( $cloud_data['run_id'] ?? '' ) );
			$artifact_run_id = sanitize_text_field( (string) ( $artifact['run_id'] ?? '' ) );
			if ( '' !== $result_run_id && '' !== $artifact_run_id && $result_run_id !== $artifact_run_id ) {
				return new WP_Error(
					'cloud_media_derivative_artifact_run_mismatch',
					__( 'Derivative artifact run_id does not match the Cloud result.', 'npcink-cloud-addon' ),
					array( 'status' => 409 )
				);
			}

			$result_sha256 = self::normalize_sha256( (string) ( $derivative['sha256'] ?? $derivative['checksum'] ?? '' ) );
			$artifact_sha256 = self::normalize_sha256( (string) ( $artifact['sha256'] ?? $artifact['checksum'] ?? '' ) );
			if ( '' !== $result_sha256 && '' !== $artifact_sha256 && $result_sha256 !== $artifact_sha256 ) {
				return new WP_Error(
					'cloud_media_derivative_artifact_checksum_mismatch',
					__( 'Derivative artifact checksum does not match the Cloud result.', 'npcink-cloud-addon' ),
					array( 'status' => 409 )
				);
			}

			return true;
		}

		/**
		 * Adds explicit warnings when proposal comparison metrics are incomplete.
		 *
		 * @param array<int,string>    $warnings Existing warnings.
		 * @param array<string,mixed> $original Original metrics.
		 * @param array<string,mixed> $derivative Derivative metrics.
		 * @return array<int,string>
		 */
		private static function append_metric_warnings( array $warnings, array $original, array $derivative ): array {
			if ( ! self::has_complete_media_metrics( $original ) ) {
				$warnings[] = __( 'Original media metrics are incomplete.', 'npcink-cloud-addon' );
			}
			if ( ! self::has_complete_media_metrics( $derivative ) ) {
				$warnings[] = __( 'Derivative media metrics are incomplete.', 'npcink-cloud-addon' );
			}

			return $warnings;
		}

		/**
		 * Returns whether media comparison metrics have the fields needed by UI.
		 *
		 * @param array<string,mixed> $metrics Normalized metrics.
		 * @return bool
		 */
		private static function has_complete_media_metrics( array $metrics ): bool {
			return absint( $metrics['width'] ?? 0 ) > 0
				&& absint( $metrics['height'] ?? 0 ) > 0
				&& absint( $metrics['filesize_bytes'] ?? 0 ) > 0
				&& '' !== (string) ( $metrics['mime_type'] ?? '' );
		}

		/**
		 * Normalizes supported media derivative image mime types.
		 *
		 * @param string $mime_type Raw mime type.
		 * @param bool   $allow_generic_image Whether the generic image type is accepted.
		 * @return string
		 */
		private static function normalize_media_type( string $mime_type, bool $allow_generic_image = false ): string {
			$mime_type = strtolower( trim( sanitize_text_field( $mime_type ) ) );
			if ( $allow_generic_image && 'image' === $mime_type ) {
				return 'image';
			}

			$allowed = array(
				'image/avif',
				'image/gif',
				'image/jpeg',
				'image/png',
				'image/webp',
			);

			return in_array( $mime_type, $allowed, true ) ? $mime_type : '';
		}

		/**
		 * Normalizes a response Content-Type header into a supported image mime.
		 *
		 * @param string $content_type Raw Content-Type header.
		 * @return string
		 */
		private static function normalize_response_mime_type( string $content_type ): string {
			$content_type = trim( explode( ';', $content_type )[0] ?? '' );

			return self::normalize_media_type( $content_type );
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
			$derivative = is_array( $cloud_data['derivative'] ?? null ) ? $cloud_data['derivative'] : array();

			return array(
				'run_id' => sanitize_text_field( (string) ( $cloud_data['run_id'] ?? '' ) ),
				'status' => sanitize_key( (string) ( $cloud_data['status'] ?? '' ) ),
				'warnings' => self::sanitize_string_list( $cloud_data['warnings'] ?? array() ),
				'derivative_artifact_id' => sanitize_text_field( (string) ( $derivative['artifact_id'] ?? $derivative['id'] ?? '' ) ),
			);
		}

		/**
		 * Normalizes a runtime result artifact into the derivative summary shape.
		 *
		 * @param array<string,mixed> $artifact Runtime result artifact.
		 * @return array<string,mixed>
		 */
		private static function normalize_result_artifact_for_cloud_data( array $artifact ): array {
			return array(
				'artifact_id'    => sanitize_text_field( (string) ( $artifact['artifact_id'] ?? $artifact['id'] ?? '' ) ),
				'download_url'   => esc_url_raw( (string) ( $artifact['download_url'] ?? $artifact['url'] ?? '' ) ),
				'expires_at'     => sanitize_text_field( (string) ( $artifact['expires_at'] ?? '' ) ),
				'mime_type'      => self::normalize_media_type( (string) ( $artifact['mime_type'] ?? '' ) ),
				'format'         => sanitize_key( (string) ( $artifact['format'] ?? '' ) ),
				'width'          => absint( $artifact['width'] ?? 0 ),
				'height'         => absint( $artifact['height'] ?? 0 ),
				'filesize_bytes' => absint( $artifact['filesize_bytes'] ?? $artifact['size_bytes'] ?? 0 ),
				'sha256'         => self::normalize_sha256( (string) ( $artifact['sha256'] ?? $artifact['checksum'] ?? '' ) ),
				'processing_warnings' => self::sanitize_string_list( $artifact['processing_warnings'] ?? array() ),
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
			if ( 0 === strpos( $value, 'sha256:' ) ) {
				$value = substr( $value, 7 );
			}

			return preg_match( '/^[a-f0-9]{64}$/', $value ) ? $value : '';
		}
	}
}
