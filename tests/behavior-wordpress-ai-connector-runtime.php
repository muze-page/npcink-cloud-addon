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

$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body'     => wp_json_encode(
		array(
			'status' => 'ok',
			'run_id' => 'run_wp_ai_image_1',
			'data'   => array(
				'result' => array(
					'artifact_type'             => 'image_generation_candidates',
					'contract_version'          => 'image_generation_result.v1',
					'provider_response_format'  => 'b64_json',
					'direct_wordpress_write'    => false,
					'images'                    => array(
						array(
							'b64_json'  => base64_encode( 'image-bytes' ),
							'mime_type' => 'image/png',
						),
					),
				),
			),
		)
	),
);

$image_result = $client->execute_wordpress_ai_image_generation_runtime(
	array(
		'contract_version' => 'image_generation_request.v1',
		'task'             => 'image_generation',
		'prompt'           => 'A clean product image of a blue ceramic mug.',
		'n'                => 2,
		'response_format'  => 'b64_json',
		'aspect_ratio'     => '16:9',
		'resolution'       => 'medium',
		'timeout_seconds'  => 120,
	),
	'trace-wp-ai-image',
	'wp-ai-image-idempotency'
);
$image_request      = end( $GLOBALS['maca_http_requests'] );
$image_request_body = json_decode( (string) ( $image_request['args']['body'] ?? '' ), true );

maca_assert(
	is_array( $image_result ) && 'run_wp_ai_image_1' === (string) ( $image_result['run_id'] ?? '' ),
	'Behavior: WordPress AI image generation runtime returns the Cloud response for a supported image scene task.'
);

maca_assert(
	is_array( $image_request_body )
	&& 'npcink-cloud/generate-image' === (string) ( $image_request_body['ability_name'] ?? '' )
	&& 'image_generation_request.v1' === (string) ( $image_request_body['contract_version'] ?? '' )
	&& 'wordpress_ai_connector' === (string) ( $image_request_body['channel'] ?? '' )
	&& 'image_generation' === (string) ( $image_request_body['execution_kind'] ?? '' )
	&& 'inline' === (string) ( $image_request_body['execution_pattern'] ?? '' )
	&& 'result_only' === (string) ( $image_request_body['storage_mode'] ?? '' )
	&& 90 === (int) ( $image_request_body['timeout_seconds'] ?? 0 )
	&& 90 === (int) ( $image_request['args']['timeout'] ?? 0 )
	&& false === (bool) ( $image_request_body['policy']['allow_fallback'] ?? true )
	&& 'wordpress_ai_connector' === (string) ( $image_request_body['input']['source_surface'] ?? '' )
	&& 'npcink-cloud' === (string) ( $image_request_body['input']['connector_id'] ?? '' )
	&& 'image_generation' === (string) ( $image_request_body['input']['task'] ?? '' )
	&& 'b64_json' === (string) ( $image_request_body['input']['response_format'] ?? '' )
	&& '16:9' === (string) ( $image_request_body['input']['aspect_ratio'] ?? '' )
	&& 2 === (int) ( $image_request_body['input']['n'] ?? 0 ),
	'Behavior: WordPress AI image generation runtime projects a bounded Cloud image-generation payload.'
);

$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body'     => wp_json_encode(
		array(
			'status' => 'ok',
			'run_id' => 'run_toolbox_image_1',
			'data'   => array(
				'result' => array(
					'artifact_type'             => 'image_generation_candidates',
					'contract_version'          => 'image_generation_result.v1',
					'provider_response_format'  => 'url',
					'direct_wordpress_write'    => false,
					'images'                    => array(
						array(
							'url' => 'https://cdn.example.test/toolbox-image.png',
						),
					),
				),
			),
		)
	),
);

$toolbox_image_result = $client->execute_toolbox_image_generation_runtime(
	array(
		'contract_version' => 'image_generation_request.v1',
		'task'             => 'image_generation',
		'prompt'           => 'A cinematic featured image for a WordPress article.',
		'n'                => 3,
		'response_format'  => 'url',
		'aspect_ratio'     => '16:9',
		'resolution'       => 'high',
		'source_surface'   => 'toolbox_featured_image',
		'timeout_seconds'  => 120,
	),
	'trace-toolbox-image',
	'toolbox-image-idempotency'
);
$toolbox_image_request      = end( $GLOBALS['maca_http_requests'] );
$toolbox_image_request_body = json_decode( (string) ( $toolbox_image_request['args']['body'] ?? '' ), true );

maca_assert(
	is_array( $toolbox_image_result ) && 'run_toolbox_image_1' === (string) ( $toolbox_image_result['run_id'] ?? '' ),
	'Behavior: Toolbox image generation runtime returns the Cloud response for a supported candidate request.'
);

maca_assert(
	is_array( $toolbox_image_request_body )
	&& 'npcink-cloud/generate-image' === (string) ( $toolbox_image_request_body['ability_name'] ?? '' )
	&& 'image_generation_request.v1' === (string) ( $toolbox_image_request_body['contract_version'] ?? '' )
	&& 'toolbox_image_generation' === (string) ( $toolbox_image_request_body['channel'] ?? '' )
	&& 'image_generation' === (string) ( $toolbox_image_request_body['execution_kind'] ?? '' )
	&& 'inline' === (string) ( $toolbox_image_request_body['execution_pattern'] ?? '' )
	&& 'result_only' === (string) ( $toolbox_image_request_body['storage_mode'] ?? '' )
	&& 90 === (int) ( $toolbox_image_request_body['timeout_seconds'] ?? 0 )
	&& false === (bool) ( $toolbox_image_request_body['policy']['allow_fallback'] ?? true )
	&& 'toolbox_featured_image' === (string) ( $toolbox_image_request_body['input']['source_surface'] ?? '' )
	&& 'npcink-cloud-addon' === (string) ( $toolbox_image_request_body['input']['connector_id'] ?? '' )
	&& 'image_generation' === (string) ( $toolbox_image_request_body['input']['task'] ?? '' )
	&& 'url' === (string) ( $toolbox_image_request_body['input']['response_format'] ?? '' )
	&& '16:9' === (string) ( $toolbox_image_request_body['input']['aspect_ratio'] ?? '' )
	&& 3 === (int) ( $toolbox_image_request_body['input']['n'] ?? 0 ),
	'Behavior: Toolbox image generation runtime projects a transport-only Cloud image-generation payload.'
);

$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body'     => wp_json_encode(
		array(
			'status' => 'ok',
			'run_id' => 'run_toolbox_audio_1',
			'data'   => array(
				'result' => array(
					'artifact_type'            => 'audio_generation_candidates',
					'contract_version'         => 'audio_generation_result.v1',
					'provider_response_format' => 'url',
					'direct_wordpress_write'   => false,
					'audios'                   => array(
						array(
							'url'    => 'https://cdn.example.test/toolbox-audio.mp3',
							'format' => 'mp3',
						),
					),
				),
			),
		)
	),
);

$toolbox_audio_result = $client->execute_toolbox_audio_generation_runtime(
	array(
		'contract_version'  => 'audio_generation_request.v1',
		'intent'            => 'article_audio_summary',
		'text'              => 'A reviewed spoken summary script for the current WordPress article.',
		'summary_text'      => 'A reviewed spoken summary script for the current WordPress article.',
		'script'            => 'A reviewed spoken summary script for the current WordPress article.',
		'voice_id'          => 'voice_editorial',
		'format'            => 'mp3',
		'source_surface'    => 'toolbox_article_audio_candidates',
		'user_instruction'  => 'Use a calm editorial tone.',
		'audio_preferences' => array( 'tone' => 'calm' ),
		'timeout_seconds'   => 120,
	),
	'trace-toolbox-audio',
	'toolbox-audio-idempotency'
);
$toolbox_audio_request      = end( $GLOBALS['maca_http_requests'] );
$toolbox_audio_request_body = json_decode( (string) ( $toolbox_audio_request['args']['body'] ?? '' ), true );

maca_assert(
	is_array( $toolbox_audio_result ) && 'run_toolbox_audio_1' === (string) ( $toolbox_audio_result['run_id'] ?? '' ),
	'Behavior: Toolbox audio generation runtime returns the Cloud response for a supported candidate request.'
);

maca_assert(
	is_array( $toolbox_audio_request_body )
	&& 'npcink-toolbox/generate-audio' === (string) ( $toolbox_audio_request_body['ability_name'] ?? '' )
	&& 'audio_generation_request.v1' === (string) ( $toolbox_audio_request_body['contract_version'] ?? '' )
	&& 'toolbox_audio_generation' === (string) ( $toolbox_audio_request_body['channel'] ?? '' )
	&& 'audio_generation' === (string) ( $toolbox_audio_request_body['execution_kind'] ?? '' )
	&& 'inline' === (string) ( $toolbox_audio_request_body['execution_pattern'] ?? '' )
	&& 'result_only' === (string) ( $toolbox_audio_request_body['storage_mode'] ?? '' )
	&& 90 === (int) ( $toolbox_audio_request_body['timeout_seconds'] ?? 0 )
	&& false === (bool) ( $toolbox_audio_request_body['policy']['allow_fallback'] ?? true )
	&& 'toolbox_article_audio_candidates' === (string) ( $toolbox_audio_request_body['input']['source_surface'] ?? '' )
	&& 'npcink-cloud-addon' === (string) ( $toolbox_audio_request_body['input']['connector_id'] ?? '' )
	&& 'article_audio_summary' === (string) ( $toolbox_audio_request_body['input']['intent'] ?? '' )
	&& 'mp3' === (string) ( $toolbox_audio_request_body['input']['format'] ?? '' )
	&& 'url' === (string) ( $toolbox_audio_request_body['input']['response_format'] ?? '' )
	&& false === (bool) ( $toolbox_audio_request_body['input']['review']['direct_wordpress_write'] ?? true ),
	'Behavior: Toolbox audio generation runtime projects a transport-only Cloud audio-generation payload.'
);

$audio_chat_shape = $client->execute_toolbox_audio_generation_runtime(
	array(
		'contract_version' => 'audio_generation_request.v1',
		'intent'           => 'article_narration',
		'text'             => 'Narrate this article.',
		'messages'         => array(
			array(
				'role'    => 'user',
				'content' => 'Chat with me.',
			),
		),
	)
);
maca_assert(
	is_wp_error( $audio_chat_shape ) && 'cloud_toolbox_audio_generation_shape_not_allowed' === $audio_chat_shape->get_error_code(),
	'Behavior: Toolbox audio generation runtime rejects generic chat message shapes.'
);
