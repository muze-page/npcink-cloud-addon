<?php
/**
 * Cloud runtime client.
 *
 * @package MagickAICloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Magick_AI_Cloud_Runtime_Client' ) ) {
	/**
	 * Signs and dispatches requests to the Magick AI Cloud runtime plane.
	 */
	final class Magick_AI_Cloud_Runtime_Client {
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
			$base = class_exists( 'Magick_AI_Cloud_Addon_Settings' )
				? Magick_AI_Cloud_Addon_Settings::get_settings()
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
				$auth_probe['message'] = __( 'Signed verification was not attempted because the Cloud service is not reachable.', 'magick-ai-cloud-addon' );
			} elseif ( ! $this->is_configured() ) {
				$auth_probe['message'] = __( 'Cloud credentials are incomplete.', 'magick-ai-cloud-addon' );
			} else {
				$result = $this->get_current_entitlement( 'trace_cloud_probe_' . wp_generate_uuid4() );
				if ( is_wp_error( $result ) ) {
					$auth_probe['message'] = $result->get_error_message();
				} else {
					$auth_probe['ok'] = true;
					$auth_probe['message'] = __( 'Signed Cloud request verified.', 'magick-ai-cloud-addon' );
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
					__( 'Cloud run_id is required.', 'magick-ai-cloud-addon' )
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
					__( 'Cloud run_id is required.', 'magick-ai-cloud-addon' )
				);
			}

			return $this->request( 'GET', '/v1/runs/' . rawurlencode( $run_id ) . '/result', null, '', $trace_id );
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
					__( 'Cloud site_id is required.', 'magick-ai-cloud-addon' )
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
					__( 'Cloud profile_id is required.', 'magick-ai-cloud-addon' )
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
					__( 'Cloud instance_id is required.', 'magick-ai-cloud-addon' )
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
					__( 'No observability events are ready to upload.', 'magick-ai-cloud-addon' )
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
					'source'           => 'magick-ai-cloud-addon',
					'events'           => array_values( $events ),
				),
				$idempotency_key,
				$trace_id
			);
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
		private function request( string $method, string $path, ?array $payload = null, string $idempotency_key = '', string $trace_id = '' ) {
			if ( ! $this->is_configured() ) {
				return new WP_Error(
					'cloud_runtime_unconfigured',
					__( 'Magick AI Cloud is not configured.', 'magick-ai-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$method = strtoupper( trim( $method ) );
			$path = '/' . ltrim( trim( $path ), '/' );
			if ( ! $this->is_allowed_request_path( $method, $path ) ) {
				return new WP_Error(
					'cloud_runtime_endpoint_not_allowed',
					__( 'This Cloud endpoint is not allowed by the Cloud Addon runtime contract.', 'magick-ai-cloud-addon' ),
					array( 'status' => 403 )
				);
			}

			$trace_id = $this->normalize_trace_id( $trace_id );
			$idempotency_key = sanitize_text_field( $idempotency_key );
			$body = '';

			if ( is_array( $payload ) ) {
				$encoded = wp_json_encode( $payload );
				if ( ! is_string( $encoded ) || '' === $encoded ) {
					return new WP_Error(
						'cloud_runtime_encode_failed',
						__( 'Cloud runtime request payload could not be encoded.', 'magick-ai-cloud-addon' )
					);
				}
				$body = $encoded;
			}

			$args = array(
				'method' => $method,
				'timeout' => max( 5, absint( $this->config['timeout'] ?? 8 ) ),
				'headers' => $this->build_signed_headers( $method, $path, $body, $idempotency_key, $trace_id ),
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
		 * Requests the public liveness endpoint.
		 *
		 * @return array<string,mixed>
		 */
		private function request_live_probe(): array {
			$base_url = untrailingslashit( (string) ( $this->config['base_url'] ?? '' ) );
			if ( '' === $base_url ) {
				return array(
					'ok' => false,
					'message' => __( 'Cloud Base URL is required.', 'magick-ai-cloud-addon' ),
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
					'message' => '' !== $message ? $message : __( 'Cloud liveness check failed.', 'magick-ai-cloud-addon' ),
				);
			}

			return array(
				'ok' => true,
				'message' => '' !== $message ? $message : __( 'Cloud service is live.', 'magick-ai-cloud-addon' ),
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
				$message = sanitize_text_field( (string) ( $decoded['message'] ?? $decoded['detail'] ?? '' ) );
				if ( '' === $message ) {
					$message = __( 'Cloud runtime request failed.', 'magick-ai-cloud-addon' );
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
		 * Returns whether one signed request path is within the Addon contract.
		 *
		 * @param string $method HTTP method.
		 * @param string $path Relative path with optional query.
		 * @return bool
		 */
		private function is_allowed_request_path( string $method, string $path ): bool {
			$method = strtoupper( $method );
			$path_only = parse_url( $path, PHP_URL_PATH );
			$path_only = is_string( $path_only ) ? $path_only : $path;

			if ( 'POST' === $method && '/v1/runtime/execute' === $path_only ) {
				return true;
			}
			if ( 'GET' === $method && '/v1/entitlements/current' === $path_only ) {
				return true;
			}
			if ( 'GET' === $method && 1 === preg_match( '#^/v1/runs/[A-Za-z0-9._:-]+(?:/result)?$#', $path_only ) ) {
				return true;
			}
			if ( 'GET' === $method && 1 === preg_match( '#^/v1/stats/(?:profiles|instances)/[A-Za-z0-9._:-]+$#', $path_only ) ) {
				return true;
			}
			if ( 'POST' === $method && '/v1/observability/plugin-events' === $path_only ) {
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
		private function build_signed_headers( string $method, string $path, string $body, string $idempotency_key, string $trace_id ): array {
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
				'Content-Type' => 'application/json',
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
				return __( 'Cannot connect to Magick AI Cloud. Check the Cloud Base URL.', 'magick-ai-cloud-addon' );
			}

			return sprintf(
				/* translators: 1: Cloud base URL, 2: transport error. */
				__( 'Cannot connect to %1$s. Original error: %2$s', 'magick-ai-cloud-addon' ),
				(string) ( $this->config['base_url'] ?? '' ),
				$message
			);
		}
	}
}
