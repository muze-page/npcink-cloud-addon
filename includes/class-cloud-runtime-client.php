<?php
/**
 * Cloud runtime client.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Npcink_Cloud_Runtime_Client' ) ) {
	/**
	 * Signs and dispatches requests to the Npcink Cloud runtime plane.
	 */
	final class Npcink_Cloud_Runtime_Client {
		private const MAX_JSON_RESPONSE_BYTES = 1048576;
		private const MAX_DOWNLOAD_BYTES = 26214400;
		private const WP_AI_CONNECTOR_CONTRACT = 'wp_ai_connector_runtime.v1';
		private const WP_AI_CONNECTOR_MAX_REQUEST_BYTES = 24000;
		private const WP_AI_CONNECTOR_MAX_PROMPT_CHARS = 12000;
		private const WP_AI_CONNECTOR_MAX_TIMEOUT_SECONDS = 60;
		private const WP_AI_CONNECTOR_MAX_RETENTION_TTL = 86400;
		private const WP_AI_IMAGE_GENERATION_CONTRACT = 'image_generation_request.v1';
		private const WP_AI_IMAGE_GENERATION_MAX_REQUEST_BYTES = 12000;
		private const WP_AI_IMAGE_GENERATION_MAX_PROMPT_CHARS = 4000;
		private const WP_AI_IMAGE_GENERATION_MAX_TIMEOUT_SECONDS = 90;
		private const WP_AI_IMAGE_GENERATION_MAX_RETENTION_TTL = 86400;
		private const WP_AI_IMAGE_GENERATION_ALLOWED_RESPONSE_FORMATS = array( 'url', 'b64_json' );
		private const WP_AI_IMAGE_GENERATION_ALLOWED_ASPECT_RATIOS = array( '1:1', '2:3', '3:2', '3:4', '4:3', '4:5', '5:4', '9:16', '16:9', '21:9' );
		private const TOOLBOX_IMAGE_GENERATION_ALLOWED_SOURCE_SURFACES = array( 'toolbox_featured_image', 'toolbox_editor_featured_image', 'toolbox_editor_image_modal', 'toolbox_ai_image_generation' );
		private const WP_AI_CONNECTOR_ALLOWED_TASKS = array(
			'alt_text_suggest',
			'comment_moderation',
			'comment_reply_suggest',
			'content_classification',
			'content_rewrite',
			'content_summary',
			'excerpt_generation',
			'meta_description',
			'title_generation',
		);
		private const WP_AI_CONNECTOR_FORBIDDEN_KEYS = array(
			'api_key',
			'authorization',
			'chat_id',
			'conversation_id',
			'cookie',
			'credentials',
			'function_call',
			'functions',
			'messages',
			'nonce',
			'password',
			'secret',
			'session_id',
			'stream',
			'thread_id',
			'tool_calls',
			'tools',
			'x_npcink_signature',
			'x_magick_signature',
		);

		/**
		 * Normalized client configuration.
		 *
		 * @var array<string,mixed>
		 */
		private $config = array();

		/**
		 * Constructor.
		 *
		 * @param array<string,mixed> $config Optional settings override.
		 */
		public function __construct( array $config = array() ) {
			$base = class_exists( 'Npcink_Cloud_Addon_Settings' )
				? Npcink_Cloud_Addon_Settings::get_settings()
				: array();

			$this->config = array_merge( is_array( $base ) ? $base : array(), $config );
		}

		/**
		 * Returns whether credentials are complete.
		 *
		 * @return bool
		 */
		public function is_configured(): bool {
			return '' !== (string) ( $this->config['base_url'] ?? '' )
				&& '' !== (string) ( $this->config['site_id'] ?? '' )
				&& '' !== (string) ( $this->config['key_id'] ?? '' )
				&& '' !== (string) ( $this->config['secret'] ?? '' );
		}

		/**
		 * Probes liveness and one signed read endpoint.
		 *
		 * @return array<string,mixed>
		 */
		public function probe_connectivity(): array {
			$live_probe = $this->request_live_probe();
			$auth_probe = array(
				'ok' => false,
				'message' => '',
			);

			if ( empty( $live_probe['ok'] ) ) {
				$auth_probe['message'] = __( 'Signed verification was not attempted because the Cloud service is not reachable.', 'npcink-cloud-addon' );
			} elseif ( ! $this->is_configured() ) {
				$auth_probe['message'] = __( 'Cloud credentials are incomplete.', 'npcink-cloud-addon' );
			} else {
				$result = $this->get_current_entitlement( 'trace_cloud_probe_' . wp_generate_uuid4() );
				if ( is_wp_error( $result ) ) {
					$auth_probe['message'] = $result->get_error_message();
				} else {
					$auth_probe['ok'] = true;
					$auth_probe['message'] = __( 'Signed Cloud request verified.', 'npcink-cloud-addon' );
					$auth_probe['entitlement_response'] = $result;
				}
			}

			return array(
				'ok' => ! empty( $live_probe['ok'] ) && ! empty( $auth_probe['ok'] ),
				'live_ok' => ! empty( $live_probe['ok'] ),
				'auth_ok' => ! empty( $auth_probe['ok'] ),
				'live_message' => sanitize_text_field( (string) ( $live_probe['message'] ?? '' ) ),
				'auth_message' => sanitize_text_field( (string) ( $auth_probe['message'] ?? '' ) ),
				'entitlement_response' => is_array( $auth_probe['entitlement_response'] ?? null ) ? $auth_probe['entitlement_response'] : array(),
			);
		}

		/**
		 * Executes one runtime request.
		 *
		 * @param array<string,mixed> $payload Runtime execute payload.
		 * @param string              $trace_id Optional trace id.
		 * @param string              $idempotency_key Optional idempotency key.
		 * @return array<string,mixed>|WP_Error
		 */
		public function execute_runtime( array $payload, string $trace_id = '', string $idempotency_key = '' ) {
			if ( '' === $idempotency_key ) {
				$idempotency_key = 'runtime_' . wp_generate_uuid4();
			}

			return $this->request( 'POST', '/v1/runtime/execute', $payload, $idempotency_key, $trace_id );
		}

		/**
		 * Executes one bounded WordPress AI connector scene request.
		 *
		 * This method is intentionally not a generic chat transport. Callers
		 * must provide a known WordPress task surface, and the addon projects it
		 * into a suggestion-only runtime contract for Cloud execution.
		 *
		 * @param array<string,mixed> $request WordPress AI connector request.
		 * @param string              $trace_id Optional trace id.
		 * @param string              $idempotency_key Optional idempotency key.
		 * @return array<string,mixed>|WP_Error
		 */
		public function execute_wordpress_ai_connector_runtime( array $request, string $trace_id = '', string $idempotency_key = '' ) {
			$payload = $this->normalize_wordpress_ai_connector_request( $request );
			if ( is_wp_error( $payload ) ) {
				return $payload;
			}

			if ( '' === $idempotency_key ) {
				$idempotency_key = 'wp_ai_connector_' . wp_generate_uuid4();
			}

			return $this->request( 'POST', '/v1/runtime/execute', $payload, $idempotency_key, $trace_id );
		}

		/**
		 * Executes one bounded WordPress AI image generation scene request.
		 *
		 * This method is not a generic image provider proxy. It only transports
		 * text-to-image requests coming from the WordPress AI image generation
		 * feature and lets Cloud own provider routing and model choice.
		 *
		 * @param array<string,mixed> $request WordPress AI image generation request.
		 * @param string              $trace_id Optional trace id.
		 * @param string              $idempotency_key Optional idempotency key.
		 * @return array<string,mixed>|WP_Error
		 */
		public function execute_wordpress_ai_image_generation_runtime( array $request, string $trace_id = '', string $idempotency_key = '' ) {
			$payload = $this->normalize_wordpress_ai_image_generation_request( $request );
			if ( is_wp_error( $payload ) ) {
				return $payload;
			}

			if ( '' === $idempotency_key ) {
				$idempotency_key = 'wp_ai_image_generation_' . wp_generate_uuid4();
			}

			return $this->request( 'POST', '/v1/runtime/execute', $payload, $idempotency_key, $trace_id );
		}

		/**
		 * Executes one bounded Toolbox AI image generation runtime request.
		 *
		 * This method is a transport seam for Toolbox image candidates. The
		 * addon signs and dispatches the Cloud runtime request only; Toolbox
		 * keeps candidate UX/normalization, and Core/Abilities keep adoption.
		 *
		 * @param array<string,mixed> $request Toolbox image generation request.
		 * @param string              $trace_id Optional trace id.
		 * @param string              $idempotency_key Optional idempotency key.
		 * @return array<string,mixed>|WP_Error
		 */
		public function execute_toolbox_image_generation_runtime( array $request, string $trace_id = '', string $idempotency_key = '' ) {
			$payload = $this->normalize_toolbox_image_generation_request( $request );
			if ( is_wp_error( $payload ) ) {
				return $payload;
			}

			if ( '' === $idempotency_key ) {
				$idempotency_key = 'toolbox_ai_image_generation_' . wp_generate_uuid4();
			}

			return $this->request( 'POST', '/v1/runtime/execute', $payload, $idempotency_key, $trace_id );
		}

		/**
		 * Requests Cloud-owned image context evidence for weak media metadata.
		 *
		 * The addon only signs and transports the bounded request. Image
		 * recognition execution, provider routing, and model ownership remain in
		 * Cloud. Returned evidence is suggestion-only and never authorizes local
		 * WordPress media writes.
		 *
		 * @param array<string,mixed> $image_context_evidence_request Toolbox image_context_evidence_request.v1 artifact.
		 * @param string              $trace_id Optional trace id.
		 * @param string              $idempotency_key Optional idempotency key.
		 * @return array<string,mixed>|WP_Error
		 */
		public function request_image_context_evidence( array $image_context_evidence_request, string $trace_id = '', string $idempotency_key = '' ) {
			$request = $this->normalize_image_context_evidence_request( $image_context_evidence_request );
			if ( is_wp_error( $request ) ) {
				return $request;
			}
			if ( '' === $idempotency_key ) {
				$idempotency_key = 'image_context_evidence_' . wp_generate_uuid4();
			}

			$runtime_payload = array(
				'ability_name'        => 'npcink-cloud/image-context-evidence',
				'contract_version'    => 'image_context_evidence_request.v1',
				'profile_id'          => 'vision.ai',
				'execution_kind'      => 'image_context_evidence',
				'execution_pattern'   => 'inline',
				'input'               => array(
					'image_context_evidence_request' => $request,
				),
				'data_classification' => 'public_site_media_metadata',
				'storage_mode'        => 'result_only',
				'retention_ttl'       => 86400,
				'timeout_seconds'     => 30,
				'retry_max'           => 0,
				'policy'              => array(
					'allow_fallback' => false,
				),
			);

			$response = $this->request( 'POST', '/v1/runtime/execute', $runtime_payload, $idempotency_key, $trace_id );
			if ( is_wp_error( $response ) ) {
				return $response;
			}

			return $this->normalize_image_context_evidence_response( is_array( $response ) ? $response : array(), $request );
		}

		/**
		 * Creates one media derivative run through the named runtime service endpoint.
		 *
		 * @param array<string,mixed> $payload Media derivative request payload.
		 * @param array<string,array<string,string>> $files Optional multipart source_file.
		 * @param string              $trace_id Optional trace id.
		 * @param string              $idempotency_key Optional idempotency key.
		 * @return array<string,mixed>|WP_Error
		 */
		public function create_media_derivative( array $payload, array $files = array(), string $trace_id = '', string $idempotency_key = '' ) {
			if ( '' === $idempotency_key ) {
				$idempotency_key = 'media_derivative_' . wp_generate_uuid4();
			}
			foreach ( array_keys( $files ) as $field_name ) {
				if ( ! in_array( (string) $field_name, array( 'source_file', 'watermark_file' ), true ) ) {
					return new WP_Error(
						'cloud_runtime_media_derivative_file_field_not_allowed',
						__( 'Only source_file and watermark_file uploads are allowed for media derivative transport.', 'npcink-cloud-addon' ),
						array( 'status' => 400 )
					);
				}
			}

			if ( empty( $files ) ) {
				return $this->request( 'POST', '/v1/runtime/media-derivatives', $payload, $idempotency_key, $trace_id );
			}

			$multipart = $this->build_media_derivative_multipart_body( $payload, $files );
			if ( is_wp_error( $multipart ) ) {
				return $multipart;
			}

			return $this->request(
				'POST',
				'/v1/runtime/media-derivatives',
				null,
				$idempotency_key,
				$trace_id,
				(string) $multipart['body'],
				(string) $multipart['content_type']
			);
		}

		/**
		 * Reads one runtime run.
		 *
		 * @param string $run_id Cloud run id.
		 * @param string $trace_id Optional trace id.
		 * @return array<string,mixed>|WP_Error
		 */
		public function get_run( string $run_id, string $trace_id = '' ) {
			$run_id = $this->normalize_identifier( $run_id );
			if ( '' === $run_id ) {
				return new WP_Error(
					'cloud_runtime_run_missing',
					__( 'Cloud run_id is required.', 'npcink-cloud-addon' )
				);
			}

			return $this->request( 'GET', '/v1/runs/' . rawurlencode( $run_id ), null, '', $trace_id );
		}

		/**
		 * Reads one runtime run result.
		 *
		 * @param string $run_id Cloud run id.
		 * @param string $trace_id Optional trace id.
		 * @return array<string,mixed>|WP_Error
		 */
		public function get_run_result( string $run_id, string $trace_id = '' ) {
			$run_id = $this->normalize_identifier( $run_id );
			if ( '' === $run_id ) {
				return new WP_Error(
					'cloud_runtime_run_missing',
					__( 'Cloud run_id is required.', 'npcink-cloud-addon' )
				);
			}

			return $this->request( 'GET', '/v1/runs/' . rawurlencode( $run_id ) . '/result', null, '', $trace_id );
		}

		/**
		 * Reads recent Nightly Inspection run cards for the current site.
		 *
		 * @param int    $limit Maximum run cards to read.
		 * @param string $trace_id Optional trace id.
		 * @return array<string,mixed>|WP_Error
		 */
		public function get_recent_nightly_inspection_runs( int $limit = 10, string $trace_id = '' ) {
			$limit = max( 1, min( 50, absint( $limit ) ) );

			return $this->request( 'GET', '/v1/runs/nightly-inspection/recent?limit=' . rawurlencode( (string) $limit ), null, '', $trace_id );
		}

		/**
		 * Queues a Cloud-owned retry for one terminal Nightly Inspection run.
		 *
		 * @param string              $run_id Cloud source run id.
		 * @param array<string,mixed> $input Runtime input payload for the retry.
		 * @param string              $trace_id Optional trace id.
		 * @param string              $idempotency_key Required idempotency key.
		 * @return array<string,mixed>|WP_Error
		 */
		public function retry_run( string $run_id, array $input, string $trace_id = '', string $idempotency_key = '' ) {
			$run_id = $this->normalize_identifier( $run_id );
			if ( '' === $run_id ) {
				return new WP_Error(
					'cloud_runtime_run_missing',
					__( 'Cloud run_id is required.', 'npcink-cloud-addon' )
				);
			}
			if ( '' === $idempotency_key ) {
				$idempotency_key = 'runtime_retry_' . wp_generate_uuid4();
			}

			return $this->request(
				'POST',
				'/v1/runs/' . rawurlencode( $run_id ) . '/retry',
				array(
					'input' => $input,
				),
				$idempotency_key,
				$trace_id
			);
		}

		/**
		 * Downloads one short-TTL derivative artifact through a signed runtime request.
		 *
		 * @param string $artifact_id Cloud artifact id.
		 * @param string $trace_id Optional trace id.
		 * @return array<string,mixed>|WP_Error
		 */
		public function download_media_derivative_artifact( string $artifact_id, string $trace_id = '' ) {
			$artifact_id = $this->normalize_identifier( $artifact_id );
			if ( '' === $artifact_id ) {
				return new WP_Error(
					'cloud_runtime_artifact_missing',
					__( 'Cloud artifact_id is required.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			return $this->request_raw(
				'GET',
				'/v1/runtime/artifacts/' . rawurlencode( $artifact_id ) . '/download',
				'',
				$trace_id,
				'image/*'
			);
		}

		/**
		 * Reads the current site entitlement projection.
		 *
		 * @param string $trace_id Optional trace id.
		 * @return array<string,mixed>|WP_Error
		 */
		public function get_current_entitlement( string $trace_id = '' ) {
			$site_id = $this->normalize_identifier( (string) ( $this->config['site_id'] ?? '' ) );
			if ( '' === $site_id ) {
				return new WP_Error(
					'cloud_runtime_site_missing',
					__( 'Cloud site_id is required.', 'npcink-cloud-addon' )
				);
			}

			$path = '/v1/entitlements/current?object_type=site&object_id=' . rawurlencode( $site_id );

			return $this->request( 'GET', $path, null, '', $trace_id );
		}

		/**
		 * Reads profile stats.
		 *
		 * @param string $profile_id Hosted profile id.
		 * @param string $trace_id Optional trace id.
		 * @return array<string,mixed>|WP_Error
		 */
		public function get_profile_stats( string $profile_id, string $trace_id = '' ) {
			$profile_id = $this->normalize_identifier( $profile_id );
			if ( '' === $profile_id ) {
				return new WP_Error(
					'cloud_runtime_profile_missing',
					__( 'Cloud profile_id is required.', 'npcink-cloud-addon' )
				);
			}

			return $this->request( 'GET', '/v1/stats/profiles/' . rawurlencode( $profile_id ), null, '', $trace_id );
		}

		/**
		 * Reads instance stats.
		 *
		 * @param string $instance_id Hosted instance id.
		 * @param string $trace_id Optional trace id.
		 * @return array<string,mixed>|WP_Error
		 */
		public function get_instance_stats( string $instance_id, string $trace_id = '' ) {
			$instance_id = $this->normalize_identifier( $instance_id );
			if ( '' === $instance_id ) {
				return new WP_Error(
					'cloud_runtime_instance_missing',
					__( 'Cloud instance_id is required.', 'npcink-cloud-addon' )
				);
			}

			return $this->request( 'GET', '/v1/stats/instances/' . rawurlencode( $instance_id ), null, '', $trace_id );
		}

		/**
		 * Sends a batch of plugin observability events.
		 *
		 * @param array<int,array<string,mixed>> $events Event batch.
		 * @param string                         $trace_id Optional trace id.
		 * @param string                         $idempotency_key Optional idempotency key.
		 * @return array<string,mixed>|WP_Error
		 */
		public function send_observability_events( array $events, string $trace_id = '', string $idempotency_key = '' ) {
			if ( empty( $events ) ) {
				return new WP_Error(
					'cloud_observability_events_empty',
					__( 'No observability events are ready to upload.', 'npcink-cloud-addon' )
				);
			}

			if ( '' === $idempotency_key ) {
				$idempotency_key = 'obs_' . wp_generate_uuid4();
			}

			return $this->request(
				'POST',
				'/v1/observability/plugin-events',
				array(
					'contract_version' => 'magick-plugin-observability-v1',
					'source'           => 'npcink-cloud-addon',
					'events'           => array_values( $events ),
				),
				$idempotency_key,
				$trace_id
			);
		}

		/**
		 * Sends one local Agent handoff feedback event for Cloud eval rollups.
		 *
		 * @param array<string,mixed> $payload Agent feedback payload.
		 * @param string              $trace_id Optional trace id.
		 * @param string              $idempotency_key Optional idempotency key.
		 * @return array<string,mixed>|WP_Error
		 */
		public function send_agent_feedback_event( array $payload, string $trace_id = '', string $idempotency_key = '' ) {
			if ( empty( $payload ) || 'cloud_agent_feedback.v1' !== (string) ( $payload['contract_version'] ?? '' ) ) {
				return new WP_Error(
					'cloud_agent_feedback_payload_invalid',
					__( 'Agent feedback requires the cloud_agent_feedback.v1 contract.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			if ( '' === $idempotency_key ) {
				$idempotency_key = 'agent_feedback_' . wp_generate_uuid4();
			}

			return $this->request(
				'POST',
				'/v1/agent-feedback/events',
				$payload,
				$idempotency_key,
				$trace_id
			);
		}

		/**
		 * Reads the Cloud Agent feedback eval summary.
		 *
		 * @param int    $window_hours Summary window in hours.
		 * @param string $trace_id Optional trace id.
		 * @return array<string,mixed>|WP_Error
		 */
		public function get_agent_feedback_summary( int $window_hours = 24, string $trace_id = '' ) {
			$window_hours = min( 168, max( 1, absint( $window_hours ) ) );

			return $this->request( 'GET', '/v1/agent-feedback/summary?window_hours=' . rawurlencode( (string) $window_hours ), null, '', $trace_id );
		}

		/**
		 * Reads the Cloud plugin observability summary.
		 *
		 * @param int    $window_hours Summary window in hours.
		 * @param string $trace_id Optional trace id.
		 * @return array<string,mixed>|WP_Error
		 */
		public function get_observability_summary( int $window_hours = 24, string $trace_id = '' ) {
			$window_hours = min( 168, max( 1, absint( $window_hours ) ) );

			return $this->request( 'GET', '/v1/observability/plugin-summary?window_hours=' . rawurlencode( (string) $window_hours ), null, '', $trace_id );
		}

		/**
		 * Executes one signed Cloud request.
		 *
		 * @param string              $method HTTP method.
		 * @param string              $path Relative path with optional query.
		 * @param array<string,mixed>|null $payload Optional JSON payload.
		 * @param string              $idempotency_key Optional idempotency key.
		 * @param string              $trace_id Optional trace id.
		 * @return array<string,mixed>|WP_Error
		 */
		private function request( string $method, string $path, ?array $payload = null, string $idempotency_key = '', string $trace_id = '', ?string $raw_body = null, string $content_type = 'application/json' ) {
			if ( ! $this->is_configured() ) {
				return new WP_Error(
					'cloud_runtime_unconfigured',
					__( 'Npcink Cloud is not configured.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$method = strtoupper( trim( $method ) );
			$path = '/' . ltrim( trim( $path ), '/' );
			if ( ! $this->is_allowed_request_path( $method, $path ) ) {
				return new WP_Error(
					'cloud_runtime_endpoint_not_allowed',
					__( 'This Cloud endpoint is not allowed by the Cloud Addon runtime contract.', 'npcink-cloud-addon' ),
					array( 'status' => 403 )
				);
			}

			$trace_id = $this->normalize_trace_id( $trace_id );
			$idempotency_key = sanitize_text_field( $idempotency_key );
			$body = '';

			if ( null !== $raw_body ) {
				$body = $raw_body;
			} elseif ( is_array( $payload ) ) {
				$encoded = wp_json_encode( $payload );
				if ( ! is_string( $encoded ) || '' === $encoded ) {
					return new WP_Error(
						'cloud_runtime_encode_failed',
						__( 'Cloud runtime request payload could not be encoded.', 'npcink-cloud-addon' )
					);
				}
				$body = $encoded;
			}

			$timeout = max( 5, absint( $this->config['timeout'] ?? 8 ) );
			if ( 'POST' === $method && '/v1/runtime/execute' === $path && is_array( $payload ) ) {
				$requested_timeout = absint( $payload['timeout_seconds'] ?? 0 );
				if ( $requested_timeout > 0 ) {
					$timeout_cap = 'npcink-cloud/generate-image' === (string) ( $payload['ability_name'] ?? '' )
						? self::WP_AI_IMAGE_GENERATION_MAX_TIMEOUT_SECONDS
						: self::WP_AI_CONNECTOR_MAX_TIMEOUT_SECONDS;
					$timeout = max( $timeout, min( $timeout_cap, $requested_timeout ) );
				}
			}

			$args = array(
				'method' => $method,
				'timeout' => $timeout,
				'limit_response_size' => self::MAX_JSON_RESPONSE_BYTES,
				'headers' => $this->build_signed_headers( $method, $path, $body, $idempotency_key, $trace_id, $content_type ),
			);

			if ( '' !== $body ) {
				$args['body'] = $body;
			}

			$response = wp_remote_request( $this->build_request_url( $path ), $args );
			if ( is_wp_error( $response ) ) {
				return new WP_Error(
					'cloud_runtime_request_failed',
					$this->format_transport_error_message( $response->get_error_message() ),
					array( 'status' => 502 )
				);
			}

			return $this->decode_response( $response );
		}

		/**
		 * Executes one signed Cloud request and returns raw response bytes.
		 *
		 * @param string $method HTTP method.
		 * @param string $path Relative path with optional query.
		 * @param string $idempotency_key Optional idempotency key.
		 * @param string $trace_id Optional trace id.
		 * @param string $accept Accept header.
		 * @return array<string,mixed>|WP_Error
		 */
		private function request_raw( string $method, string $path, string $idempotency_key = '', string $trace_id = '', string $accept = '*/*' ) {
			if ( ! $this->is_configured() ) {
				return new WP_Error(
					'cloud_runtime_unconfigured',
					__( 'Npcink Cloud is not configured.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$method = strtoupper( trim( $method ) );
			$path   = '/' . ltrim( trim( $path ), '/' );
			if ( ! $this->is_allowed_request_path( $method, $path ) ) {
				return new WP_Error(
					'cloud_runtime_endpoint_not_allowed',
					__( 'This Cloud endpoint is not allowed by the Cloud Addon runtime contract.', 'npcink-cloud-addon' ),
					array( 'status' => 403 )
				);
			}

			$trace_id        = $this->normalize_trace_id( $trace_id );
			$idempotency_key = sanitize_text_field( $idempotency_key );
			$headers         = $this->build_signed_headers( $method, $path, '', $idempotency_key, $trace_id, 'application/octet-stream' );
			$headers['Accept'] = sanitize_text_field( $accept );
			unset( $headers['Content-Type'] );

			$response = wp_remote_request(
				$this->build_request_url( $path ),
				array(
					'method'  => $method,
					'timeout' => max( 5, absint( $this->config['timeout'] ?? 8 ) ),
					'limit_response_size' => self::MAX_DOWNLOAD_BYTES,
					'headers' => $headers,
				)
			);
			if ( is_wp_error( $response ) ) {
				return new WP_Error(
					'cloud_runtime_request_failed',
					$this->format_transport_error_message( $response->get_error_message() ),
					array( 'status' => 502 )
				);
			}

			return $this->decode_raw_response( $response );
		}

		/**
		 * Requests the public liveness endpoint.
		 *
		 * @return array<string,mixed>
		 */
		private function request_live_probe(): array {
			$base_url = untrailingslashit( (string) ( $this->config['base_url'] ?? '' ) );
			if ( '' === $base_url ) {
				return array(
					'ok' => false,
					'message' => __( 'Cloud Base URL is required.', 'npcink-cloud-addon' ),
				);
			}

			$response = wp_remote_get(
				$base_url . '/health/live',
				array(
					'timeout' => max( 5, absint( $this->config['timeout'] ?? 8 ) ),
					'limit_response_size' => self::MAX_JSON_RESPONSE_BYTES,
					'headers' => array(
						'Accept' => 'application/json',
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				return array(
					'ok' => false,
					'message' => $this->format_transport_error_message( $response->get_error_message() ),
				);
			}

			$status = absint( wp_remote_retrieve_response_code( $response ) );
			$raw_body = wp_remote_retrieve_body( $response );
			$body = is_string( $raw_body ) ? $raw_body : '';
			if ( strlen( $body ) > self::MAX_JSON_RESPONSE_BYTES ) {
				return array(
					'ok' => false,
					'message' => __( 'Cloud liveness response exceeds the local size limit.', 'npcink-cloud-addon' ),
				);
			}
			$decoded = json_decode( $body, true );
			$decoded = is_array( $decoded ) ? $decoded : array();
			$message = sanitize_text_field( (string) ( $decoded['message'] ?? '' ) );

			if ( $status < 200 || $status >= 300 ) {
				return array(
					'ok' => false,
					'message' => '' !== $message ? $message : __( 'Cloud liveness check failed.', 'npcink-cloud-addon' ),
				);
			}

			return array(
				'ok' => true,
				'message' => '' !== $message ? $message : __( 'Cloud service is live.', 'npcink-cloud-addon' ),
			);
		}

		/**
		 * Decodes a WordPress HTTP response into the local client envelope.
		 *
		 * @param array<string,mixed> $response WP HTTP response.
		 * @return array<string,mixed>|WP_Error
		 */
		private function decode_response( array $response ) {
			$status = absint( wp_remote_retrieve_response_code( $response ) );
			$raw_body = wp_remote_retrieve_body( $response );
			$body = is_string( $raw_body ) ? $raw_body : '';
			$content_len = absint( $this->response_header( $response, 'content-length' ) );
			if ( $content_len > self::MAX_JSON_RESPONSE_BYTES || strlen( $body ) > self::MAX_JSON_RESPONSE_BYTES ) {
				return new WP_Error(
					'cloud_runtime_response_too_large',
					__( 'Cloud runtime response exceeds the local size limit.', 'npcink-cloud-addon' ),
					array( 'status' => 413 )
				);
			}

			$decoded = json_decode( $body, true );
			if ( ! is_array( $decoded ) && '' !== trim( $body ) ) {
				$is_likely_truncated = strlen( $body ) >= self::MAX_JSON_RESPONSE_BYTES;
				return new WP_Error(
					$is_likely_truncated ? 'cloud_runtime_response_too_large' : 'cloud_runtime_response_invalid',
					$is_likely_truncated
						? __( 'Cloud runtime response exceeds the local size limit.', 'npcink-cloud-addon' )
						: __( 'Cloud runtime response was not valid JSON.', 'npcink-cloud-addon' ),
					array( 'status' => $is_likely_truncated ? 413 : 502 )
				);
			}
			$decoded = is_array( $decoded ) ? $decoded : array();
			$envelope_status = sanitize_key( (string) ( $decoded['status'] ?? '' ) );

			$successful_envelope_statuses = array( '', 'ok', 'ready', 'submitted', 'queued', 'running', 'completed', 'success' );
			if ( $status < 200 || $status >= 300 || ! in_array( $envelope_status, $successful_envelope_statuses, true ) ) {
				$error_code = sanitize_text_field( (string) ( $decoded['error_code'] ?? $decoded['code'] ?? '' ) );
				$message = $this->normalize_error_message( $decoded['message'] ?? $decoded['detail'] ?? '' );
				if ( '' === $message ) {
					$message = __( 'Cloud runtime request failed.', 'npcink-cloud-addon' );
				}

				return new WP_Error(
					$this->map_remote_error_code( $error_code ),
					$message,
					array(
						'status' => $status > 0 ? $status : 502,
						'cloud_error_code' => $error_code,
						'cloud_payload' => $decoded,
					)
				);
			}

			return $this->normalize_runtime_artifact_urls( $decoded );
		}

		/**
		 * Decodes a raw byte response with bounded size checks.
		 *
		 * @param array<string,mixed> $response WP HTTP response.
		 * @return array<string,mixed>|WP_Error
		 */
		private function decode_raw_response( array $response ) {
			$status       = absint( wp_remote_retrieve_response_code( $response ) );
			$raw_body     = wp_remote_retrieve_body( $response );
			$body         = is_string( $raw_body ) ? $raw_body : '';
			$content_type = $this->response_header( $response, 'content-type' );
			$content_len  = absint( $this->response_header( $response, 'content-length' ) );

			if ( $status < 200 || $status >= 300 ) {
				$decoded = json_decode( $body, true );
				$decoded = is_array( $decoded ) ? $decoded : array();
				$error_code = sanitize_text_field( (string) ( $decoded['error_code'] ?? $decoded['code'] ?? '' ) );
				$message = $this->normalize_error_message( $decoded['message'] ?? $decoded['detail'] ?? '' );
				if ( '' === $message ) {
					$message = __( 'Cloud runtime artifact download failed.', 'npcink-cloud-addon' );
				}

				return new WP_Error(
					$this->map_remote_error_code( $error_code ),
					$message,
					array(
						'status'           => $status > 0 ? $status : 502,
						'cloud_error_code' => $error_code,
						'cloud_payload'    => $decoded,
					)
				);
			}

			if ( $content_len > self::MAX_DOWNLOAD_BYTES || strlen( $body ) > self::MAX_DOWNLOAD_BYTES ) {
				return new WP_Error(
					'cloud_runtime_artifact_too_large',
					__( 'Cloud artifact download exceeds the local preview size limit.', 'npcink-cloud-addon' ),
					array( 'status' => 413 )
				);
			}

			return array(
				'status'         => $status,
				'body'           => $body,
				'content_type'   => $content_type,
				'content_length' => $content_len,
			);
		}

		/**
		 * Converts Cloud runtime artifact paths into absolute Cloud URLs.
		 *
		 * Runtime responses may return short TTL artifact paths such as
		 * /v1/runtime/artifacts/{id}/download or tokenized public-download
		 * paths. WordPress editor surfaces need absolute URLs for media
		 * preview controls.
		 *
		 * @param mixed $value Decoded Cloud response value.
		 * @return mixed
		 */
		private function normalize_runtime_artifact_urls( $value ) {
			if ( is_string( $value ) ) {
				return $this->absolute_runtime_artifact_url( $value );
			}
			if ( ! is_array( $value ) ) {
				return $value;
			}

			$next = array();
			foreach ( $value as $key => $item ) {
				$next[ $key ] = $this->normalize_runtime_artifact_urls( $item );
			}
			return $next;
		}

		/**
		 * Builds an absolute Cloud URL for one runtime artifact path.
		 *
		 * @param string $value Decoded scalar value.
		 * @return string
		 */
		private function absolute_runtime_artifact_url( string $value ): string {
			if ( 1 !== preg_match( '#^/v1/runtime/artifacts/[A-Za-z0-9._:-]+/(?:download|public-download)(?:\\?token=[A-Za-z0-9._~-]+)?$#', $value ) ) {
				return $value;
			}

			$base_url = untrailingslashit( (string) ( $this->config['base_url'] ?? '' ) );
			if ( '' === $base_url ) {
				return $value;
			}

			return esc_url_raw( $base_url . $value );
		}

		/**
		 * Normalizes scalar or structured Cloud error detail into readable text.
		 *
		 * @param mixed $value Cloud error message/detail value.
		 * @return string
		 */
		private function normalize_error_message( $value ): string {
			$parts = array();
			$this->collect_error_message_parts( $value, $parts );

			return sanitize_text_field( implode( '; ', array_unique( array_filter( $parts ) ) ) );
		}

		/**
		 * Recursively collects Cloud error text without casting arrays to strings.
		 *
		 * @param mixed         $value Error value.
		 * @param array<int,string> $parts Message parts.
		 * @param int           $depth Recursion depth.
		 * @return void
		 */
		private function collect_error_message_parts( $value, array &$parts, int $depth = 0 ): void {
			if ( $depth > 6 || '' === $value || null === $value ) {
				return;
			}

			if ( is_scalar( $value ) ) {
				$parts[] = sanitize_text_field( (string) $value );
				return;
			}

			if ( ! is_array( $value ) ) {
				return;
			}

			$message_value = null;
			foreach ( array( 'msg', 'message', 'detail', 'error', 'error_message' ) as $key ) {
				if ( array_key_exists( $key, $value ) ) {
					$message_value = $value[ $key ];
					break;
				}
			}

			if ( null !== $message_value ) {
				$nested_parts = array();
				$this->collect_error_message_parts( $message_value, $nested_parts, $depth + 1 );
				$message = implode( '; ', array_filter( $nested_parts ) );
				$path    = $this->normalize_error_path( $value['loc'] ?? $value['path'] ?? array() );
				if ( '' !== $message && '' !== $path ) {
					$parts[] = $path . ': ' . $message;
				} elseif ( '' !== $message ) {
					$parts[] = $message;
				}
				return;
			}

			foreach ( $value as $item ) {
				$this->collect_error_message_parts( $item, $parts, $depth + 1 );
			}
		}

		/**
		 * Normalizes structured Cloud error location into a dotted path.
		 *
		 * @param mixed $value Error location value.
		 * @return string
		 */
		private function normalize_error_path( $value ): string {
			if ( is_scalar( $value ) || null === $value ) {
				return sanitize_text_field( (string) $value );
			}

			if ( ! is_array( $value ) ) {
				return '';
			}

			return sanitize_text_field( implode( '.', array_map( 'strval', $value ) ) );
		}

		/**
		 * Retrieves one response header without depending on WP internals in tests.
		 *
		 * @param array<string,mixed> $response WP HTTP response.
		 * @param string              $header Header name.
		 * @return string
		 */
		private function response_header( array $response, string $header ): string {
			if ( function_exists( 'wp_remote_retrieve_header' ) ) {
				return sanitize_text_field( (string) wp_remote_retrieve_header( $response, $header ) );
			}

			$headers = is_array( $response['headers'] ?? null ) ? $response['headers'] : array();
			foreach ( $headers as $name => $value ) {
				if ( strtolower( (string) $name ) === strtolower( $header ) ) {
					return sanitize_text_field( (string) $value );
				}
			}

			return '';
		}

		/**
		 * Returns whether one signed request path is within the Addon contract.
		 *
		 * @param string $method HTTP method.
		 * @param string $path Relative path with optional query.
		 * @return bool
		 */
		private function is_allowed_request_path( string $method, string $path ): bool {
			$method = strtoupper( $method );
			$path_only = wp_parse_url( $path, PHP_URL_PATH );
			$path_only = is_string( $path_only ) ? $path_only : $path;

			if ( 'POST' === $method && '/v1/runtime/execute' === $path_only ) {
				return true;
			}
			if ( 'POST' === $method && '/v1/runtime/media-derivatives' === $path_only ) {
				return true;
			}
			if ( 'GET' === $method && '/v1/entitlements/current' === $path_only ) {
				return true;
			}
			if ( 'GET' === $method && 1 === preg_match( '#^/v1/runs/[A-Za-z0-9._:-]+(?:/result)?$#', $path_only ) ) {
				return true;
			}
			if ( 'GET' === $method && '/v1/runs/nightly-inspection/recent' === $path_only ) {
				return true;
			}
			if ( 'POST' === $method && 1 === preg_match( '#^/v1/runs/[A-Za-z0-9._:-]+/retry$#', $path_only ) ) {
				return true;
			}
			if ( 'GET' === $method && 1 === preg_match( '#^/v1/runtime/artifacts/[A-Za-z0-9._:-]+/download$#', $path_only ) ) {
				return true;
			}
			if ( 'GET' === $method && 1 === preg_match( '#^/v1/stats/(?:profiles|instances)/[A-Za-z0-9._:-]+$#', $path_only ) ) {
				return true;
			}
			if ( 'POST' === $method && '/v1/observability/plugin-events' === $path_only ) {
				return true;
			}
			if ( 'POST' === $method && '/v1/agent-feedback/events' === $path_only ) {
				return true;
			}
			if ( 'GET' === $method && '/v1/agent-feedback/summary' === $path_only ) {
				return true;
			}
			if ( 'GET' === $method && '/v1/observability/plugin-summary' === $path_only ) {
				return true;
			}

			return false;
		}

		/**
		 * Builds signed Cloud headers.
		 *
		 * @param string $method HTTP method.
		 * @param string $path Relative path with optional query.
		 * @param string $body JSON request body.
		 * @param string $idempotency_key Idempotency key.
		 * @param string $trace_id Trace id.
		 * @return array<string,string>
		 */
		private function build_signed_headers( string $method, string $path, string $body, string $idempotency_key, string $trace_id, string $content_type = 'application/json' ): array {
			$timestamp = (string) time();
			$traceparent = $this->build_traceparent( $trace_id );
			$nonce = $this->build_request_nonce( $method, $trace_id, $idempotency_key );
			$body_digest = hash( 'sha256', $body );
			$canonical = implode(
				"\n",
				array(
					strtoupper( $method ),
					$path,
					(string) ( $this->config['site_id'] ?? '' ),
					(string) ( $this->config['key_id'] ?? '' ),
					$timestamp,
					$nonce,
					$idempotency_key,
					$traceparent,
					$body_digest,
				)
			);
			$signature = hash_hmac( 'sha256', $canonical, (string) ( $this->config['secret'] ?? '' ) );

			$headers = array(
				'Accept' => 'application/json',
				'Content-Type' => sanitize_text_field( $content_type ),
				'X-Npcink-Site-Id' => (string) ( $this->config['site_id'] ?? '' ),
				'X-Npcink-Key-Id' => (string) ( $this->config['key_id'] ?? '' ),
				'X-Npcink-Timestamp' => $timestamp,
				'X-Npcink-Signature' => strtolower( $signature ),
				'X-Npcink-Trace-Id' => $trace_id,
				'traceparent' => $traceparent,
			);

			if ( '' !== $nonce ) {
				$headers['X-Npcink-Nonce'] = $nonce;
			}
			if ( '' !== $idempotency_key ) {
				$headers['Idempotency-Key'] = $idempotency_key;
			}

			return $headers;
		}

		/**
		 * Builds a bounded multipart body for the media derivative endpoint.
		 *
		 * @param array<string,mixed> $payload Media derivative JSON request.
		 * @param array<string,array<string,string>> $files Multipart files.
		 * @return array{body:string,content_type:string}|WP_Error
		 */
		private function build_media_derivative_multipart_body( array $payload, array $files ) {
			$encoded = wp_json_encode( $payload );
			if ( ! is_string( $encoded ) || '' === $encoded ) {
				return new WP_Error(
					'cloud_runtime_encode_failed',
					__( 'Cloud media derivative request payload could not be encoded.', 'npcink-cloud-addon' )
				);
			}

			$boundary = 'npcink-cloud-addon-' . wp_generate_uuid4();
			$body = '--' . $boundary . "\r\n";
			$body .= "Content-Disposition: form-data; name=\"request\"\r\n";
			$body .= "Content-Type: application/json\r\n\r\n";
			$body .= $encoded . "\r\n";

			foreach ( array( 'source_file', 'watermark_file' ) as $field_name ) {
				if ( empty( $files[ $field_name ]['contents'] ) ) {
					continue;
				}

				$filename = sanitize_file_name( (string) ( $files[ $field_name ]['filename'] ?? $field_name ) );
				$mime_type = sanitize_text_field( (string) ( $files[ $field_name ]['mime_type'] ?? 'application/octet-stream' ) );
				$body .= '--' . $boundary . "\r\n";
				$body .= 'Content-Disposition: form-data; name="' . $field_name . '"; filename="' . $filename . "\"\r\n";
				$body .= 'Content-Type: ' . $mime_type . "\r\n\r\n";
				$body .= (string) $files[ $field_name ]['contents'] . "\r\n";
			}

			$body .= '--' . $boundary . "--\r\n";

			return array(
				'body'         => $body,
				'content_type' => 'multipart/form-data; boundary=' . $boundary,
			);
		}

		/**
		 * Normalizes a WordPress AI connector request into a bounded runtime payload.
		 *
		 * @param array<string,mixed> $request Raw connector request.
		 * @return array<string,mixed>|WP_Error
		 */
		private function normalize_wordpress_ai_connector_request( array $request ) {
			$contract_version = (string) ( $request['contract_version'] ?? self::WP_AI_CONNECTOR_CONTRACT );
			if ( self::WP_AI_CONNECTOR_CONTRACT !== $contract_version ) {
				return new WP_Error(
					'cloud_wp_ai_connector_contract_invalid',
					__( 'WordPress AI connector requests require the wp_ai_connector_runtime.v1 contract.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$task = sanitize_key( (string) ( $request['task'] ?? ( $request['feature_id'] ?? '' ) ) );
			if ( ! in_array( $task, self::WP_AI_CONNECTOR_ALLOWED_TASKS, true ) ) {
				return new WP_Error(
					'cloud_wp_ai_connector_task_not_allowed',
					__( 'WordPress AI connector requests require a supported site-task surface.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$forbidden_key = $this->find_forbidden_wordpress_ai_connector_key( $request );
			if ( '' !== $forbidden_key ) {
				return new WP_Error(
					'cloud_wp_ai_connector_chat_shape_not_allowed',
					__( 'WordPress AI connector requests do not support generic chat sessions, tool calls, streams, or credential fields.', 'npcink-cloud-addon' ),
					array(
						'status' => 400,
						'key'    => $forbidden_key,
					)
				);
			}

			$encoded_request = wp_json_encode( $request );
			if ( ! is_string( $encoded_request ) || '' === $encoded_request ) {
				return new WP_Error(
					'cloud_wp_ai_connector_encode_failed',
					__( 'WordPress AI connector request could not be encoded.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( strlen( $encoded_request ) > self::WP_AI_CONNECTOR_MAX_REQUEST_BYTES ) {
				return new WP_Error(
					'cloud_wp_ai_connector_request_too_large',
					__( 'WordPress AI connector request exceeds the scene runtime size limit.', 'npcink-cloud-addon' ),
					array( 'status' => 413 )
				);
			}

			$prompt = (string) ( $request['prompt'] ?? '' );
			if ( '' !== $prompt && $this->text_length( $prompt ) > self::WP_AI_CONNECTOR_MAX_PROMPT_CHARS ) {
				return new WP_Error(
					'cloud_wp_ai_connector_prompt_too_large',
					__( 'WordPress AI connector prompt exceeds the scene runtime size limit.', 'npcink-cloud-addon' ),
					array( 'status' => 413 )
				);
			}

			$timeout_seconds = absint( $request['timeout_seconds'] ?? 20 );
			$retention_ttl   = absint( $request['retention_ttl'] ?? self::WP_AI_CONNECTOR_MAX_RETENTION_TTL );
			$retry_max       = absint( $request['retry_max'] ?? 0 );
			$profile_id      = $this->normalize_identifier( (string) ( $request['profile_id'] ?? 'text.balanced' ) );
			$input           = is_array( $request['input'] ?? null ) ? $request['input'] : array();

			if ( '' !== $prompt ) {
				$input['prompt'] = $prompt;
			}

			return array(
				'ability_name'        => 'npcink-cloud/wp-ai-connector',
				'ability_family'      => 'text',
				'contract_version'    => self::WP_AI_CONNECTOR_CONTRACT,
				'channel'             => 'wordpress_ai_connector',
				'execution_kind'      => 'wordpress_ai_connector',
				'execution_pattern'   => 'inline',
				'profile_id'          => '' !== $profile_id ? $profile_id : 'text.balanced',
				'input'               => array(
					'contract_version'           => self::WP_AI_CONNECTOR_CONTRACT,
					'source_surface'             => 'wordpress_ai_connector',
					'connector_id'                => 'npcink-cloud',
					'task'                        => $task,
					'write_posture'               => 'suggestion_only',
					'direct_wordpress_write'      => false,
					'no_conversation'             => true,
					'expected_response_contract'  => 'wp_ai_connector_result.v1',
					'request'                     => $input,
				),
				'data_classification' => 'public_site_content',
				'storage_mode'        => 'result_only',
				'retention_ttl'       => min( self::WP_AI_CONNECTOR_MAX_RETENTION_TTL, max( 0, $retention_ttl ) ),
				'timeout_seconds'     => min( self::WP_AI_CONNECTOR_MAX_TIMEOUT_SECONDS, max( 1, $timeout_seconds ) ),
				'retry_max'           => min( 1, $retry_max ),
				'policy'              => array(
					'allow_fallback' => false,
				),
			);
		}

		/**
		 * Normalizes a WordPress AI image generation request into a bounded runtime payload.
		 *
		 * @param array<string,mixed> $request Raw image generation request.
		 * @return array<string,mixed>|WP_Error
		 */
		private function normalize_wordpress_ai_image_generation_request( array $request ) {
			$contract_version = (string) ( $request['contract_version'] ?? self::WP_AI_IMAGE_GENERATION_CONTRACT );
			if ( self::WP_AI_IMAGE_GENERATION_CONTRACT !== $contract_version ) {
				return new WP_Error(
					'cloud_wp_ai_image_generation_contract_invalid',
					__( 'WordPress AI image generation requests require the image_generation_request.v1 contract.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$task = sanitize_key( (string) ( $request['task'] ?? 'image_generation' ) );
			if ( 'image_generation' !== $task ) {
				return new WP_Error(
					'cloud_wp_ai_image_generation_task_not_allowed',
					__( 'WordPress AI image generation requests require the supported image_generation task surface.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$forbidden_key = $this->find_forbidden_wordpress_ai_connector_key( $request );
			if ( '' !== $forbidden_key ) {
				return new WP_Error(
					'cloud_wp_ai_image_generation_shape_not_allowed',
					__( 'WordPress AI image generation requests do not support generic chat sessions, tool calls, streams, or credential fields.', 'npcink-cloud-addon' ),
					array(
						'status' => 400,
						'key'    => $forbidden_key,
					)
				);
			}

			$encoded_request = wp_json_encode( $request );
			if ( ! is_string( $encoded_request ) || '' === $encoded_request ) {
				return new WP_Error(
					'cloud_wp_ai_image_generation_encode_failed',
					__( 'WordPress AI image generation request could not be encoded.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( strlen( $encoded_request ) > self::WP_AI_IMAGE_GENERATION_MAX_REQUEST_BYTES ) {
				return new WP_Error(
					'cloud_wp_ai_image_generation_request_too_large',
					__( 'WordPress AI image generation request exceeds the scene runtime size limit.', 'npcink-cloud-addon' ),
					array( 'status' => 413 )
				);
			}

			$prompt = trim( (string) ( $request['prompt'] ?? '' ) );
			if ( '' === $prompt ) {
				return new WP_Error(
					'cloud_wp_ai_image_generation_prompt_required',
					__( 'WordPress AI image generation requires text scene input.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( $this->text_length( $prompt ) > self::WP_AI_IMAGE_GENERATION_MAX_PROMPT_CHARS ) {
				return new WP_Error(
					'cloud_wp_ai_image_generation_prompt_too_large',
					__( 'WordPress AI image generation prompt exceeds the scene runtime size limit.', 'npcink-cloud-addon' ),
					array( 'status' => 413 )
				);
			}

			$response_format = (string) ( $request['response_format'] ?? 'b64_json' );
			if ( ! in_array( $response_format, self::WP_AI_IMAGE_GENERATION_ALLOWED_RESPONSE_FORMATS, true ) ) {
				return new WP_Error(
					'cloud_wp_ai_image_generation_response_format_invalid',
					__( 'WordPress AI image generation response format must be url or b64_json.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$aspect_ratio = (string) ( $request['aspect_ratio'] ?? '1:1' );
			if ( ! in_array( $aspect_ratio, self::WP_AI_IMAGE_GENERATION_ALLOWED_ASPECT_RATIOS, true ) ) {
				$aspect_ratio = '1:1';
			}

			$image_count = absint( $request['n'] ?? 1 );
			$image_count = min( 4, max( 1, $image_count ) );

			$timeout_seconds = absint( $request['timeout_seconds'] ?? self::WP_AI_IMAGE_GENERATION_MAX_TIMEOUT_SECONDS );
			$retention_ttl   = absint( $request['retention_ttl'] ?? self::WP_AI_IMAGE_GENERATION_MAX_RETENTION_TTL );

			return array(
				'ability_name'        => 'npcink-cloud/generate-image',
				'ability_family'      => 'vision',
				'contract_version'    => self::WP_AI_IMAGE_GENERATION_CONTRACT,
				'channel'             => 'wordpress_ai_connector',
				'execution_kind'      => 'image_generation',
				'execution_pattern'   => 'inline',
				'input'               => array(
					'contract_version' => self::WP_AI_IMAGE_GENERATION_CONTRACT,
					'source_surface'   => 'wordpress_ai_connector',
					'connector_id'      => 'npcink-cloud',
					'task'              => 'image_generation',
					'prompt'            => $prompt,
					'n'                 => $image_count,
					'response_format'   => $response_format,
					'aspect_ratio'      => $aspect_ratio,
					'resolution'        => sanitize_key( (string) ( $request['resolution'] ?? 'medium' ) ),
				),
				'data_classification' => 'internal',
				'storage_mode'        => 'result_only',
				'retention_ttl'       => min( self::WP_AI_IMAGE_GENERATION_MAX_RETENTION_TTL, max( 0, $retention_ttl ) ),
				'timeout_seconds'     => min( self::WP_AI_IMAGE_GENERATION_MAX_TIMEOUT_SECONDS, max( 1, $timeout_seconds ) ),
				'retry_max'           => 0,
				'policy'              => array(
					'allow_fallback' => false,
				),
			);
		}

		/**
		 * Normalizes a Toolbox AI image generation request into a bounded runtime payload.
		 *
		 * @param array<string,mixed> $request Raw Toolbox image generation request.
		 * @return array<string,mixed>|WP_Error
		 */
		private function normalize_toolbox_image_generation_request( array $request ) {
			$contract_version = (string) ( $request['contract_version'] ?? self::WP_AI_IMAGE_GENERATION_CONTRACT );
			if ( self::WP_AI_IMAGE_GENERATION_CONTRACT !== $contract_version ) {
				return new WP_Error(
					'cloud_toolbox_image_generation_contract_invalid',
					__( 'Toolbox image generation requests require the image_generation_request.v1 contract.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$task = sanitize_key( (string) ( $request['task'] ?? 'image_generation' ) );
			if ( 'image_generation' !== $task ) {
				return new WP_Error(
					'cloud_toolbox_image_generation_task_not_allowed',
					__( 'Toolbox image generation requests require the supported image_generation task surface.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$forbidden_key = $this->find_forbidden_wordpress_ai_connector_key( $request );
			if ( '' !== $forbidden_key ) {
				return new WP_Error(
					'cloud_toolbox_image_generation_shape_not_allowed',
					__( 'Toolbox image generation requests do not support generic chat sessions, tool calls, streams, or credential fields.', 'npcink-cloud-addon' ),
					array(
						'status' => 400,
						'key'    => $forbidden_key,
					)
				);
			}

			$encoded_request = wp_json_encode( $request );
			if ( ! is_string( $encoded_request ) || '' === $encoded_request ) {
				return new WP_Error(
					'cloud_toolbox_image_generation_encode_failed',
					__( 'Toolbox image generation request could not be encoded.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( strlen( $encoded_request ) > self::WP_AI_IMAGE_GENERATION_MAX_REQUEST_BYTES ) {
				return new WP_Error(
					'cloud_toolbox_image_generation_request_too_large',
					__( 'Toolbox image generation request exceeds the scene runtime size limit.', 'npcink-cloud-addon' ),
					array( 'status' => 413 )
				);
			}

			$prompt = trim( (string) ( $request['prompt'] ?? '' ) );
			if ( '' === $prompt ) {
				return new WP_Error(
					'cloud_toolbox_image_generation_prompt_required',
					__( 'Toolbox image generation requires text scene input.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( $this->text_length( $prompt ) > self::WP_AI_IMAGE_GENERATION_MAX_PROMPT_CHARS ) {
				return new WP_Error(
					'cloud_toolbox_image_generation_prompt_too_large',
					__( 'Toolbox image generation prompt exceeds the scene runtime size limit.', 'npcink-cloud-addon' ),
					array( 'status' => 413 )
				);
			}

			$response_format = (string) ( $request['response_format'] ?? 'url' );
			if ( ! in_array( $response_format, self::WP_AI_IMAGE_GENERATION_ALLOWED_RESPONSE_FORMATS, true ) ) {
				return new WP_Error(
					'cloud_toolbox_image_generation_response_format_invalid',
					__( 'Toolbox image generation response format must be url or b64_json.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$aspect_ratio = (string) ( $request['aspect_ratio'] ?? '16:9' );
			if ( ! in_array( $aspect_ratio, self::WP_AI_IMAGE_GENERATION_ALLOWED_ASPECT_RATIOS, true ) ) {
				$aspect_ratio = '16:9';
			}

			$source_surface = sanitize_key( (string) ( $request['source_surface'] ?? 'toolbox_featured_image' ) );
			if ( ! in_array( $source_surface, self::TOOLBOX_IMAGE_GENERATION_ALLOWED_SOURCE_SURFACES, true ) ) {
				$source_surface = 'toolbox_featured_image';
			}

			$image_count = absint( $request['n'] ?? 1 );
			$image_count = min( 4, max( 1, $image_count ) );

			$timeout_seconds = absint( $request['timeout_seconds'] ?? self::WP_AI_IMAGE_GENERATION_MAX_TIMEOUT_SECONDS );
			$retention_ttl   = absint( $request['retention_ttl'] ?? self::WP_AI_IMAGE_GENERATION_MAX_RETENTION_TTL );

			return array(
				'ability_name'        => 'npcink-cloud/generate-image',
				'ability_family'      => 'vision',
				'contract_version'    => self::WP_AI_IMAGE_GENERATION_CONTRACT,
				'channel'             => 'toolbox_image_generation',
				'execution_kind'      => 'image_generation',
				'execution_pattern'   => 'inline',
				'input'               => array(
					'contract_version' => self::WP_AI_IMAGE_GENERATION_CONTRACT,
					'source_surface'   => $source_surface,
					'connector_id'      => 'npcink-cloud-addon',
					'task'              => 'image_generation',
					'prompt'            => $prompt,
					'n'                 => $image_count,
					'response_format'   => $response_format,
					'aspect_ratio'      => $aspect_ratio,
					'resolution'        => sanitize_key( (string) ( $request['resolution'] ?? 'high' ) ),
				),
				'data_classification' => 'internal',
				'storage_mode'        => 'result_only',
				'retention_ttl'       => min( self::WP_AI_IMAGE_GENERATION_MAX_RETENTION_TTL, max( 0, $retention_ttl ) ),
				'timeout_seconds'     => min( self::WP_AI_IMAGE_GENERATION_MAX_TIMEOUT_SECONDS, max( 1, $timeout_seconds ) ),
				'retry_max'           => 0,
				'policy'              => array(
					'allow_fallback' => false,
				),
			);
		}

		/**
		 * Finds a forbidden chat/provider-control key in a connector request.
		 *
		 * @param mixed $value Raw request value.
		 * @return string
		 */
		private function find_forbidden_wordpress_ai_connector_key( $value ): string {
			if ( ! is_array( $value ) ) {
				return '';
			}

			foreach ( $value as $key => $item ) {
				$normalized_key = sanitize_key( str_replace( '-', '_', (string) $key ) );
				if ( in_array( $normalized_key, self::WP_AI_CONNECTOR_FORBIDDEN_KEYS, true ) ) {
					return $normalized_key;
				}
				$nested = $this->find_forbidden_wordpress_ai_connector_key( $item );
				if ( '' !== $nested ) {
					return $nested;
				}
			}

			return '';
		}

		/**
		 * Normalizes a Toolbox image context evidence request.
		 *
		 * @param array<string,mixed> $request Raw request.
		 * @return array<string,mixed>|WP_Error
		 */
		private function normalize_image_context_evidence_request( array $request ) {
			if (
				'image_context_evidence_request.v1' !== (string) ( $request['contract_version'] ?? '' )
				|| 'suggestion_only' !== (string) ( $request['write_posture'] ?? '' )
				|| false !== (bool) ( $request['direct_wordpress_write'] ?? true )
				|| false === (bool) ( $request['no_local_model'] ?? false )
				|| false === (bool) ( $request['no_media_write'] ?? false )
			) {
				return new WP_Error(
					'cloud_image_context_evidence_request_invalid',
					__( 'Image context evidence requires a suggestion-only no-write request contract.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$items = is_array( $request['items'] ?? null ) ? $request['items'] : array();
			$normalized_items = array();
			foreach ( $items as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$attachment_id = absint( $item['attachment_id'] ?? 0 );
				$url           = esc_url_raw( (string) ( $item['url'] ?? '' ) );
				$thumbnail_url = esc_url_raw( (string) ( $item['thumbnail_url'] ?? '' ) );
				if ( 0 >= $attachment_id || ( '' === $url && '' === $thumbnail_url ) ) {
					continue;
				}
				$normalized_items[] = array(
					'attachment_id'            => $attachment_id,
					'title'                    => $this->bounded_text( (string) ( $item['title'] ?? '' ), 160 ),
					'filename'                 => sanitize_file_name( (string) ( $item['filename'] ?? '' ) ),
					'thumbnail_url'            => $thumbnail_url,
					'url'                      => $url,
					'mime_type'                => sanitize_text_field( (string) ( $item['mime_type'] ?? '' ) ),
					'current_alt_status'       => sanitize_key( (string) ( $item['current_alt_status'] ?? '' ) ),
					'current_caption_status'   => sanitize_key( (string) ( $item['current_caption_status'] ?? '' ) ),
					'candidate_quality_flags'  => array_slice( $this->sanitize_string_list( $item['candidate_quality_flags'] ?? array() ), 0, 12 ),
					'filtered_candidate_notes' => array_slice( $this->sanitize_string_list( $item['filtered_candidate_notes'] ?? array() ), 0, 12 ),
				);
				if ( count( $normalized_items ) >= 10 ) {
					break;
				}
			}

			if ( empty( $normalized_items ) ) {
				return new WP_Error(
					'cloud_image_context_evidence_request_empty',
					__( 'Image context evidence requires at least one bounded media URL.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			return array(
				'contract_version'           => 'image_context_evidence_request.v1',
				'artifact_type'              => 'image_context_evidence_request',
				'runtime_owner'              => 'cloud_or_host_runtime',
				'write_posture'              => 'suggestion_only',
				'direct_wordpress_write'     => false,
				'proposal_created'           => false,
				'execution_created'          => false,
				'no_local_model'             => true,
				'no_media_write'             => true,
				'source_policy'              => 'bounded_media_urls_for_visual_context_only',
				'expected_response_contract' => 'image_context_evidence.v1',
				'requested_count'            => count( $normalized_items ),
				'max_items'                  => count( $normalized_items ),
				'items'                      => $normalized_items,
				'operator_next_action'       => 'request_cloud_image_context_evidence',
			);
		}

		/**
		 * Normalizes a Cloud response into image_context_evidence.v1.
		 *
		 * @param array<string,mixed> $response Cloud response.
		 * @param array<string,mixed> $request Normalized request.
		 * @return array<string,mixed>|WP_Error
		 */
		private function normalize_image_context_evidence_response( array $response, array $request ) {
			$payload = $this->extract_image_context_evidence_payload( $response );
			$requested_ids = array();
			foreach ( (array) ( $request['items'] ?? array() ) as $request_item ) {
				if ( is_array( $request_item ) ) {
					$requested_ids[ absint( $request_item['attachment_id'] ?? 0 ) ] = true;
				}
			}

			$items = array();
			foreach ( (array) ( $payload['items'] ?? array() ) as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$attachment_id = absint( $item['attachment_id'] ?? 0 );
				if ( 0 >= $attachment_id || empty( $requested_ids[ $attachment_id ] ) ) {
					continue;
				}
				$items[] = array(
					'attachment_id'              => $attachment_id,
					'contract_version'           => 'image_context_evidence.v1',
					'source'                     => sanitize_key( (string) ( $item['source'] ?? 'cloud_or_host_runtime' ) ),
					'visual_summary'             => $this->bounded_text( (string) ( $item['visual_summary'] ?? '' ), 240 ),
					'scene'                      => $this->bounded_text( (string) ( $item['scene'] ?? '' ), 160 ),
					'objects'                    => array_slice( $this->sanitize_string_list( $item['objects'] ?? array() ), 0, 12 ),
					'text_seen'                  => array_slice( $this->sanitize_string_list( $item['text_seen'] ?? array() ), 0, 8 ),
					'confidence'                 => sanitize_text_field( (string) ( $item['confidence'] ?? '' ) ),
					'write_posture'              => 'suggestion_only',
					'direct_wordpress_write'     => false,
					'needs_human_visual_check'   => true,
				);
				if ( count( $items ) >= 10 ) {
					break;
				}
			}

			if ( empty( $items ) ) {
				return new WP_Error(
					'cloud_image_context_evidence_empty',
					__( 'Cloud did not return usable image context evidence.', 'npcink-cloud-addon' ),
					array( 'status' => 502 )
				);
			}

			return array(
				'contract_version'         => 'image_context_evidence.v1',
				'artifact_type'            => 'image_context_evidence',
				'runtime_owner'            => 'cloud_service',
				'source'                   => sanitize_key( (string) ( $payload['source'] ?? 'cloud_or_host_runtime' ) ),
				'write_posture'            => 'suggestion_only',
				'direct_wordpress_write'   => false,
				'proposal_created'         => false,
				'execution_created'        => false,
				'requested_count'          => (int) ( $request['requested_count'] ?? count( $items ) ),
				'evidence_count'           => count( $items ),
				'run_id'                   => sanitize_text_field( (string) ( $response['run_id'] ?? ( $payload['run_id'] ?? '' ) ) ),
				'model_id'                 => sanitize_text_field( (string) ( $payload['model_id'] ?? '' ) ),
				'items'                    => $items,
				'safety'                   => array(
					'local_model_used'             => false,
					'core_proposal_created'        => false,
					'direct_wordpress_write'       => false,
					'requires_human_visual_check'  => true,
				),
			);
		}

		/**
		 * Extracts image context evidence from common runtime envelopes.
		 *
		 * @param array<string,mixed> $response Cloud response.
		 * @return array<string,mixed>
		 */
		private function extract_image_context_evidence_payload( array $response ): array {
			$candidates = array(
				$response['image_context_evidence'] ?? null,
				$response['data']['image_context_evidence'] ?? null,
				$response['result']['image_context_evidence'] ?? null,
				$response['data']['result']['image_context_evidence'] ?? null,
				$response['result'] ?? null,
				$response['data']['result'] ?? null,
				$response['data'] ?? null,
				$response,
			);

			foreach ( $candidates as $candidate ) {
				if ( ! is_array( $candidate ) ) {
					continue;
				}
				if ( 'image_context_evidence.v1' === (string) ( $candidate['contract_version'] ?? '' ) || is_array( $candidate['items'] ?? null ) ) {
					return $candidate;
				}
			}

			return array();
		}

		/**
		 * Sanitizes a scalar-or-array string list.
		 *
		 * @param mixed $value Raw list.
		 * @return array<int,string>
		 */
		private function sanitize_string_list( $value ): array {
			$items = is_array( $value ) ? $value : preg_split( '/[\r\n,]+/', (string) $value );
			$items = is_array( $items ) ? $items : array();

			return array_values(
				array_filter(
					array_map(
						function ( $item ): string {
							return $this->bounded_text( (string) $item, 120 );
						},
						$items
					),
					static function ( string $item ): bool {
						return '' !== $item;
					}
				)
			);
		}

		/**
		 * Sanitizes and bounds text.
		 *
		 * @param string $value Raw value.
		 * @param int    $max_chars Maximum characters.
		 * @return string
		 */
		private function bounded_text( string $value, int $max_chars ): string {
			$value = trim( sanitize_text_field( wp_strip_all_tags( $value ) ) );
			$value = preg_replace( '/\s+/u', ' ', $value );
			$value = is_string( $value ) ? trim( $value ) : '';
			$max_chars = max( 1, $max_chars );
			if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) && mb_strlen( $value, 'UTF-8' ) > $max_chars ) {
				return mb_substr( $value, 0, $max_chars, 'UTF-8' );
			}

			return strlen( $value ) > $max_chars ? substr( $value, 0, $max_chars ) : $value;
		}

		/**
		 * Returns the character length of a UTF-8 string when available.
		 *
		 * @param string $value Raw text.
		 * @return int
		 */
		private function text_length( string $value ): int {
			if ( function_exists( 'mb_strlen' ) ) {
				return mb_strlen( $value, 'UTF-8' );
			}

			return strlen( $value );
		}

		/**
		 * Builds full request URL.
		 *
		 * @param string $path Relative path.
		 * @return string
		 */
		private function build_request_url( string $path ): string {
			return untrailingslashit( (string) ( $this->config['base_url'] ?? '' ) ) . $path;
		}

		/**
		 * Builds a request nonce for signed POST calls.
		 *
		 * @param string $method HTTP method.
		 * @param string $trace_id Trace id.
		 * @param string $idempotency_key Idempotency key.
		 * @return string
		 */
		private function build_request_nonce( string $method, string $trace_id, string $idempotency_key ): string {
			if ( 'POST' !== strtoupper( $method ) ) {
				return '';
			}

			$seed = '' !== $trace_id ? $trace_id : $idempotency_key;
			if ( '' === $seed ) {
				$seed = wp_generate_uuid4();
			}

			return 'nonce-' . substr( strtolower( hash( 'sha256', $seed ) ), 0, 24 );
		}

		/**
		 * Builds a W3C traceparent header from a local trace id.
		 *
		 * @param string $trace_id Trace id.
		 * @return string
		 */
		private function build_traceparent( string $trace_id ): string {
			$normalized = strtolower( preg_replace( '/[^a-f0-9]/', '', $trace_id ) );
			$normalized = is_string( $normalized ) ? $normalized : '';
			if ( 32 !== strlen( $normalized ) ) {
				$normalized = substr( hash( 'sha256', $trace_id ), 0, 32 );
			}
			$parent_id = substr( hash( 'sha256', $normalized . '|parent' ), 0, 16 );

			return '00-' . $normalized . '-' . $parent_id . '-01';
		}

		/**
		 * Normalizes one trace id.
		 *
		 * @param string $trace_id Raw trace id.
		 * @return string
		 */
		private function normalize_trace_id( string $trace_id ): string {
			$trace_id = sanitize_text_field( trim( $trace_id ) );

			return '' !== $trace_id ? $trace_id : 'trace_cloud_' . wp_generate_uuid4();
		}

		/**
		 * Normalizes Cloud ids used in URL paths.
		 *
		 * @param string $value Raw identifier.
		 * @return string
		 */
		private function normalize_identifier( string $value ): string {
			$value = sanitize_text_field( trim( $value ) );
			$value = preg_replace( '/[^A-Za-z0-9._:-]/', '', $value );

			return is_string( $value ) ? $value : '';
		}

		/**
		 * Maps Cloud error codes into the addon namespace.
		 *
		 * @param string $cloud_error_code Raw Cloud error code.
		 * @return string
		 */
		private function map_remote_error_code( string $cloud_error_code ): string {
			$cloud_error_code = sanitize_text_field( $cloud_error_code );
			if ( '' === $cloud_error_code ) {
				return 'cloud_runtime_failed';
			}

			return 'cloud_' . sanitize_key( str_replace( '.', '_', $cloud_error_code ) );
		}

		/**
		 * Formats a transport error for operators.
		 *
		 * @param string $message Raw transport error.
		 * @return string
		 */
		private function format_transport_error_message( string $message ): string {
			$message = trim( wp_strip_all_tags( $message ) );
			if ( '' === $message ) {
				return __( 'Cannot connect to Npcink Cloud. Check the Cloud Base URL.', 'npcink-cloud-addon' );
			}

			return sprintf(
				/* translators: 1: Cloud base URL, 2: transport error. */
				__( 'Cannot connect to %1$s. Original error: %2$s', 'npcink-cloud-addon' ),
				(string) ( $this->config['base_url'] ?? '' ),
				$message
			);
		}
	}
}
