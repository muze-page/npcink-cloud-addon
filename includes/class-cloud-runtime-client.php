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
		private const MAX_DOWNLOAD_BYTES = 26214400;

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
				}
			}

			return array(
				'ok' => ! empty( $live_probe['ok'] ) && ! empty( $auth_probe['ok'] ),
				'live_ok' => ! empty( $live_probe['ok'] ),
				'auth_ok' => ! empty( $auth_probe['ok'] ),
				'live_message' => sanitize_text_field( (string) ( $live_probe['message'] ?? '' ) ),
				'auth_message' => sanitize_text_field( (string) ( $auth_probe['message'] ?? '' ) ),
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
					$timeout = max( $timeout, min( 60, $requested_timeout ) );
				}
			}

			$args = array(
				'method' => $method,
				'timeout' => $timeout,
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
			$decoded = json_decode( is_string( $raw_body ) ? $raw_body : '', true );
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
			$decoded = json_decode( is_string( $raw_body ) ? $raw_body : '', true );
			$decoded = is_array( $decoded ) ? $decoded : array();
			$envelope_status = sanitize_key( (string) ( $decoded['status'] ?? '' ) );

			if ( $status < 200 || $status >= 300 || ( '' !== $envelope_status && 'ok' !== $envelope_status ) ) {
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

			return $decoded;
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
				'X-Magick-Site-Id' => (string) ( $this->config['site_id'] ?? '' ),
				'X-Magick-Key-Id' => (string) ( $this->config['key_id'] ?? '' ),
				'X-Magick-Timestamp' => $timestamp,
				'X-Magick-Signature' => strtolower( $signature ),
				'X-Magick-Trace-Id' => $trace_id,
				'traceparent' => $traceparent,
			);

			if ( '' !== $nonce ) {
				$headers['X-Magick-Nonce'] = $nonce;
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
