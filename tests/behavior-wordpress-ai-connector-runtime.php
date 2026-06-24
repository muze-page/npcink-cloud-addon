<?php
/**
 * Behavior tests for the WordPress AI connector runtime seam.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

maca_load_addon_classes();

maca_seed_settings( true );
$client = new Npcink_Cloud_Runtime_Client( Npcink_Cloud_Addon_Settings::get_settings() );

$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body'     => wp_json_encode(
		array(
			'status' => 'ok',
			'run_id' => 'run_wp_ai_connector_1',
			'data'   => array(
				'content' => 'Suggested title',
			),
		)
	),
);

$result = $client->execute_wordpress_ai_connector_runtime(
	array(
		'contract_version' => 'wp_ai_connector_runtime.v1',
		'task'             => 'title_generation',
		'prompt'           => 'Suggest a concise title for this post.',
		'input'            => array(
			'post_title'   => 'Old title',
			'post_excerpt' => 'A short public excerpt.',
		),
		'timeout_seconds'  => 120,
		'retention_ttl'    => 999999,
		'retry_max'        => 9,
	),
	'trace-wp-ai-connector',
	'wp-ai-idempotency'
);

$request      = end( $GLOBALS['maca_http_requests'] );
$request_body = json_decode( (string) ( $request['args']['body'] ?? '' ), true );

maca_assert(
	is_array( $result ) && 'run_wp_ai_connector_1' === (string) ( $result['run_id'] ?? '' ),
	'Behavior: WordPress AI connector runtime returns the Cloud response for a supported scene task.'
);

maca_assert(
	is_array( $request_body )
	&& 'npcink-cloud/wp-ai-connector' === (string) ( $request_body['ability_name'] ?? '' )
	&& 'wp_ai_connector_runtime.v1' === (string) ( $request_body['contract_version'] ?? '' )
	&& 'wordpress_ai_connector' === (string) ( $request_body['channel'] ?? '' )
	&& 'wordpress_ai_connector' === (string) ( $request_body['execution_kind'] ?? '' )
	&& 'inline' === (string) ( $request_body['execution_pattern'] ?? '' )
	&& 'result_only' === (string) ( $request_body['storage_mode'] ?? '' )
	&& 60 === (int) ( $request_body['timeout_seconds'] ?? 0 )
	&& 86400 === (int) ( $request_body['retention_ttl'] ?? 0 )
	&& 1 === (int) ( $request_body['retry_max'] ?? -1 )
	&& false === (bool) ( $request_body['policy']['allow_fallback'] ?? true )
	&& 'wordpress_ai_connector' === (string) ( $request_body['input']['source_surface'] ?? '' )
	&& 'npcink-cloud' === (string) ( $request_body['input']['connector_id'] ?? '' )
	&& 'title_generation' === (string) ( $request_body['input']['task'] ?? '' )
	&& 'suggestion_only' === (string) ( $request_body['input']['write_posture'] ?? '' )
	&& false === (bool) ( $request_body['input']['direct_wordpress_write'] ?? true )
	&& true === (bool) ( $request_body['input']['no_conversation'] ?? false ),
	'Behavior: WordPress AI connector runtime projects a scene-bound no-chat no-write Cloud payload.'
);

$chat_shape = $client->execute_wordpress_ai_connector_runtime(
	array(
		'contract_version' => 'wp_ai_connector_runtime.v1',
		'task'             => 'content_summary',
		'messages'         => array(
			array(
				'role'    => 'user',
				'content' => 'Let us chat.',
			),
		),
	)
);
maca_assert(
	is_wp_error( $chat_shape ) && 'cloud_wp_ai_connector_chat_shape_not_allowed' === $chat_shape->get_error_code(),
	'Behavior: WordPress AI connector runtime rejects generic chat message shapes.'
);

$unknown_task = $client->execute_wordpress_ai_connector_runtime(
	array(
		'contract_version' => 'wp_ai_connector_runtime.v1',
		'task'             => 'free_chat',
		'prompt'           => 'Talk about anything.',
	)
);
maca_assert(
	is_wp_error( $unknown_task ) && 'cloud_wp_ai_connector_task_not_allowed' === $unknown_task->get_error_code(),
	'Behavior: WordPress AI connector runtime rejects unsupported task surfaces.'
);

$large_prompt = $client->execute_wordpress_ai_connector_runtime(
	array(
		'contract_version' => 'wp_ai_connector_runtime.v1',
		'task'             => 'content_rewrite',
		'prompt'           => str_repeat( 'x', 12001 ),
	)
);
maca_assert(
	is_wp_error( $large_prompt ) && 'cloud_wp_ai_connector_prompt_too_large' === $large_prompt->get_error_code(),
	'Behavior: WordPress AI connector runtime rejects oversized prompts.'
);
