<?php
/**
 * Behavior tests for the optional WordPress AI request log bridge.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

namespace WordPress\AI\Logging {
	/**
	 * Stub AI request log manager for pure PHP behavior tests.
	 */
	class AI_Request_Log_Manager {
		/**
		 * Initializes the log store.
		 *
		 * @return void
		 */
		public function init(): void {
			$GLOBALS['maca_wpai_request_log_inits'] = (int) ( $GLOBALS['maca_wpai_request_log_inits'] ?? 0 ) + 1;
		}

		/**
		 * Captures a log record.
		 *
		 * @param array<string,mixed> $data Log data.
		 * @return int
		 */
		public function log( array $data ): int {
			$GLOBALS['maca_wpai_request_logs'][] = $data;

			return count( $GLOBALS['maca_wpai_request_logs'] );
		}
	}
}

namespace {
	require_once __DIR__ . '/helpers.php';

	maca_load_addon_classes();
	require_once MACA_TEST_ROOT . '/includes/class-cloud-wordpress-ai-connector.php';

	$GLOBALS['maca_wpai_request_logs'] = array();
	$GLOBALS['maca_wpai_request_log_inits'] = 0;

	maca_reset_test_state();
	update_option( 'wpai_features_enabled', '1', false );
	update_option( 'wpai_feature_ai-request-logging_enabled', '1', false );

	Npcink_Cloud_WordPress_AI_Connector::maybe_log_wordpress_ai_request_evidence(
		array(
			'type'             => 'image',
			'operation'        => 'npcink-cloud/generate-image',
			'task'             => 'image_generation',
			'contract_version' => 'image_generation_request.v1',
			'response'         => array(
				'run_id' => 'run_image_1',
				'data'   => array(
					'result' => array(
						'model_id' => 'cloud-image-model',
						'provider_metadata' => array(
							'id' => 'cloud-provider',
						),
						'images' => array(
							array(
								'b64_json' => str_repeat( 'a', 64 ),
							),
						),
					),
				),
			),
			'duration_ms'      => 1234,
			'fallback_model_id' => Npcink_Cloud_WordPress_AI_Connector::IMAGE_MODEL_ID,
		)
	);

	$log     = $GLOBALS['maca_wpai_request_logs'][0] ?? array();
	$context = is_array( $log['context'] ?? null ) ? $log['context'] : array();

	maca_assert(
		1 === count( $GLOBALS['maca_wpai_request_logs'] )
		&& 'image' === (string) ( $log['type'] ?? '' )
		&& 'success' === (string) ( $log['status'] ?? '' )
		&& 'npcink-cloud/generate-image:image_generation' === (string) ( $log['operation'] ?? '' )
		&& 'cloud-provider' === (string) ( $log['provider'] ?? '' )
		&& 'cloud-image-model' === (string) ( $log['model'] ?? '' )
		&& 1234 === (int) ( $log['duration_ms'] ?? 0 )
		&& 'run_image_1' === (string) ( $context['cloud_run_id'] ?? '' )
		&& true === (bool) ( $context['suggestion_only'] ?? false )
		&& false === (bool) ( $context['direct_wordpress_write'] ?? true )
		&& ! isset( $context['input_preview'] )
		&& ! isset( $context['output_preview'] )
		&& false === strpos( wp_json_encode( $log ), str_repeat( 'a', 64 ) ),
		'Behavior: WordPress AI request log bridge writes metadata-only Cloud image run evidence.'
	);

	update_option( 'wpai_feature_ai-request-logging_enabled', '', false );
	Npcink_Cloud_WordPress_AI_Connector::maybe_log_wordpress_ai_request_evidence(
		array(
			'type'        => 'text',
			'task'        => 'content_summary',
			'response'    => array( 'run_id' => 'run_disabled' ),
			'duration_ms' => 1,
		)
	);

	maca_assert(
		1 === count( $GLOBALS['maca_wpai_request_logs'] ),
		'Behavior: WordPress AI request log bridge respects the AI request logging feature flag.'
	);

	update_option( 'wpai_feature_ai-request-logging_enabled', '1', false );
	Npcink_Cloud_WordPress_AI_Connector::maybe_log_wordpress_ai_request_evidence(
		array(
			'type'                       => 'text',
			'operation'                  => 'npcink-cloud/connector-runtime',
			'task'                       => 'content_summary',
			'contract_version'           => 'cloud_connector_runtime.v1',
			'operation_contract_version' => 'wordpress_operation.v1',
			'response'                   => new WP_Error( 'cloud_runtime_failed', 'Provider timeout while generating text output.' ),
			'duration_ms'                => 456,
			'fallback_model_id'          => Npcink_Cloud_WordPress_AI_Connector::MODEL_ID,
		)
	);

	$error_log = $GLOBALS['maca_wpai_request_logs'][1] ?? array();
	$error_context = is_array( $error_log['context'] ?? null ) ? $error_log['context'] : array();
	maca_assert(
		2 === count( $GLOBALS['maca_wpai_request_logs'] )
		&& 'error' === (string) ( $error_log['status'] ?? '' )
		&& 'npcink-cloud/connector-runtime:content_summary' === (string) ( $error_log['operation'] ?? '' )
		&& 'Provider timeout while generating text output.' === (string) ( $error_log['error_message'] ?? '' )
		&& 'npcink-cloud' === (string) ( $error_log['provider'] ?? '' )
		&& Npcink_Cloud_WordPress_AI_Connector::MODEL_ID === (string) ( $error_log['model'] ?? '' )
		&& 'cloud_connector_runtime.v1' === (string) ( $error_context['contract_version'] ?? '' )
		&& 'wordpress_operation.v1' === (string) ( $error_context['operation_contract_version'] ?? '' )
		&& 'editor' === (string) ( $error_context['channel'] ?? '' )
		&& 'npcink-cloud-addon' === (string) ( $error_context['connector_id'] ?? '' ),
		'Behavior: WordPress AI request log bridge records bounded Cloud runtime errors without request content.'
	);
}
