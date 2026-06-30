<?php
/**
 * Site Knowledge runtime bridge for Toolbox.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Npcink_Cloud_Site_Knowledge_Runtime_Bridge' ) ) {
	/**
	 * Validates and forwards bounded Toolbox Site Knowledge runtime requests.
	 */
	final class Npcink_Cloud_Site_Knowledge_Runtime_Bridge {
		private const MAX_RUNTIME_PAYLOAD_BYTES = 900000;
		private const ALLOWED_CONTRACTS = array(
			'npcink-cloud/site-knowledge-search' => 'site_knowledge_search.v1',
			'npcink-cloud/site-knowledge-status' => 'site_knowledge_status.v1',
			'npcink-cloud/site-knowledge-sync'   => 'site_knowledge_sync.v1',
		);
		private const ALLOWED_EXECUTION_PATTERNS = array(
			'npcink-cloud/site-knowledge-search' => array( 'inline' ),
			'npcink-cloud/site-knowledge-status' => array( 'inline' ),
			'npcink-cloud/site-knowledge-sync'   => array( 'whole_run_offload' ),
		);
		private const FORBIDDEN_KEYS = array(
			'api_key',
			'authorization',
			'cookie',
			'credentials',
			'nonce',
			'password',
			'secret',
			'token',
			'x_magick_signature',
			'x_npcink_signature',
		);

		/**
		 * Registers the Toolbox Site Knowledge Cloud request filter.
		 *
		 * @return void
		 */
		public static function register(): void {
			add_filter( 'npcink_toolbox_site_knowledge_cloud_request', array( __CLASS__, 'handle_toolbox_request' ), 10, 4 );
		}

		/**
		 * Handles Toolbox Site Knowledge Cloud requests when this addon can do so.
		 *
		 * @param mixed               $handled Existing filter result.
		 * @param array<string,mixed> $runtime_payload Toolbox runtime payload.
		 * @param string              $ability_name Ability name.
		 * @param string              $contract_version Contract version.
		 * @return mixed
		 */
		public static function handle_toolbox_request( $handled, array $runtime_payload, string $ability_name, string $contract_version ) {
			if ( null !== $handled || ! self::is_site_knowledge_request( $ability_name, $contract_version ) ) {
				return $handled;
			}

			if ( ! Npcink_Cloud_Addon_Settings::is_configured() ) {
				return null;
			}

			return self::dispatch_runtime( $runtime_payload, $ability_name, $contract_version );
		}

		/**
		 * Dispatches a bounded Site Knowledge runtime payload.
		 *
		 * This is transport only. Cloud owns Site Knowledge vector/index
		 * lifecycle, while the local WordPress host owns review and writes.
		 *
		 * @param array<string,mixed> $runtime_payload Runtime execute payload.
		 * @param string              $ability_name Optional ability name.
		 * @param string              $contract_version Optional contract version.
		 * @return array<string,mixed>|WP_Error
		 */
		public static function dispatch_runtime( array $runtime_payload, string $ability_name = '', string $contract_version = '' ) {
			$ability_name     = '' !== $ability_name ? $ability_name : sanitize_text_field( (string) ( $runtime_payload['ability_name'] ?? '' ) );
			$contract_version = '' !== $contract_version ? $contract_version : sanitize_text_field( (string) ( $runtime_payload['contract_version'] ?? '' ) );

			$payload = self::normalize_runtime_payload( $runtime_payload, $ability_name, $contract_version );
			if ( is_wp_error( $payload ) ) {
				return $payload;
			}

			$client = function_exists( 'npcink_cloud_addon_runtime_client' )
				? npcink_cloud_addon_runtime_client()
				: ( Npcink_Cloud_Addon_Settings::is_configured() ? new Npcink_Cloud_Runtime_Client() : null );
			if ( ! $client ) {
				return new WP_Error(
					'cloud_site_knowledge_runtime_unconfigured',
					__( 'Npcink Cloud is not configured.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$trace_id = 'trace_site_knowledge_toolbox_' . wp_generate_uuid4();
			$idempotency_key = 'site_knowledge_' . str_replace( '.', '_', $contract_version ) . '_' . substr( md5( (string) wp_json_encode( $payload['input'] ?? array() ) ), 0, 16 );

			return $client->execute_runtime( $payload, $trace_id, $idempotency_key );
		}

		/**
		 * Returns whether an ability/contract pair belongs to Site Knowledge.
		 *
		 * @param string $ability_name Ability name.
		 * @param string $contract_version Contract version.
		 * @return bool
		 */
		private static function is_site_knowledge_request( string $ability_name, string $contract_version ): bool {
			return isset( self::ALLOWED_CONTRACTS[ $ability_name ] )
				&& self::ALLOWED_CONTRACTS[ $ability_name ] === $contract_version;
		}

		/**
		 * Normalizes and validates a Site Knowledge runtime execute payload.
		 *
		 * @param array<string,mixed> $payload Runtime payload.
		 * @param string              $ability_name Ability name.
		 * @param string              $contract_version Contract version.
		 * @return array<string,mixed>|WP_Error
		 */
		private static function normalize_runtime_payload( array $payload, string $ability_name, string $contract_version ) {
			if ( ! isset( self::ALLOWED_CONTRACTS[ $ability_name ] ) || self::ALLOWED_CONTRACTS[ $ability_name ] !== $contract_version ) {
				return new WP_Error(
					'cloud_site_knowledge_contract_not_allowed',
					__( 'Site Knowledge runtime requests require a supported ability and contract pair.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$execution_pattern = sanitize_key( (string) ( $payload['execution_pattern'] ?? '' ) );
			if ( ! in_array( $execution_pattern, self::ALLOWED_EXECUTION_PATTERNS[ $ability_name ], true ) ) {
				return new WP_Error(
					'cloud_site_knowledge_execution_pattern_not_allowed',
					__( 'Site Knowledge runtime requests require the expected execution pattern.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$input = is_array( $payload['input'] ?? null ) ? $payload['input'] : array();
			if (
				$contract_version !== (string) ( $input['contract_version'] ?? '' )
				|| 'suggestion_only' !== (string) ( $input['write_posture'] ?? '' )
				|| true === (bool) ( $input['direct_wordpress_write'] ?? false )
			) {
				return new WP_Error(
					'cloud_site_knowledge_request_invalid',
					__( 'Site Knowledge runtime requests must be suggestion-only and no-write.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			if (
				'npcink-cloud/site-knowledge-sync' === $ability_name
				&& 'refresh' !== sanitize_key( (string) ( $input['sync_mode'] ?? '' ) )
			) {
				return new WP_Error(
					'cloud_site_knowledge_sync_mode_not_allowed',
					__( 'Site Knowledge sync transport only accepts public refresh requests. Rebuild, delete, and collection lifecycle operations belong in Cloud Site Knowledge.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$forbidden_key = self::find_forbidden_key( $payload );
			if ( '' !== $forbidden_key ) {
				return new WP_Error(
					'cloud_site_knowledge_sensitive_key_not_allowed',
					__( 'Site Knowledge runtime requests must not include credentials or signing fields.', 'npcink-cloud-addon' ),
					array(
						'status' => 400,
						'key'    => $forbidden_key,
					)
				);
			}

			$payload['ability_name']        = $ability_name;
			$payload['contract_version']    = $contract_version;
			$payload['data_classification'] = 'public_site_content';
			$payload['storage_mode']        = 'result_only';
			$payload['input']               = $input;

			$encoded = wp_json_encode( $payload );
			if ( ! is_string( $encoded ) || '' === $encoded ) {
				return new WP_Error(
					'cloud_site_knowledge_payload_encode_failed',
					__( 'Site Knowledge runtime payload could not be encoded.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( strlen( $encoded ) > self::MAX_RUNTIME_PAYLOAD_BYTES ) {
				return new WP_Error(
					'cloud_site_knowledge_payload_too_large',
					__( 'Site Knowledge runtime payload exceeds the transport size limit.', 'npcink-cloud-addon' ),
					array( 'status' => 413 )
				);
			}

			return $payload;
		}

		/**
		 * Finds a forbidden key in a nested payload.
		 *
		 * @param mixed $value Raw value.
		 * @return string
		 */
		private static function find_forbidden_key( $value ): string {
			if ( ! is_array( $value ) ) {
				return '';
			}

			foreach ( $value as $key => $item ) {
				$normalized_key = sanitize_key( str_replace( '-', '_', (string) $key ) );
				if ( in_array( $normalized_key, self::FORBIDDEN_KEYS, true ) ) {
					return $normalized_key;
				}
				$nested = self::find_forbidden_key( $item );
				if ( '' !== $nested ) {
					return $nested;
				}
			}

			return '';
		}
	}
}
