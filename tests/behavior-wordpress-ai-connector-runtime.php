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
			'run_id' => 'run_wp_ai_alt_text_1',
			'data'   => array(
				'result' => array(
					'output_text' => 'A blue ceramic mug on a white table.',
				),
			),
		)
	),
);

$alt_text_result = $client->execute_wordpress_ai_connector_runtime(
	array(
		'contract_version' => 'wp_ai_connector_runtime.v1',
		'task'             => 'alt_text_suggest',
		'prompt'           => 'Generate accessible alt text for this media item.',
		'input'            => array(
			'image_url'        => 'https://cdn.example.test/uploads/blue-mug.jpg',
			'thumbnail_url'    => 'https://cdn.example.test/uploads/blue-mug-150x150.jpg',
			'mime_type'        => 'image/jpeg',
			'filename'         => 'blue-mug.jpg',
			'title'            => 'Blue mug',
			'existing_alt'     => '',
			'existing_caption' => '',
			'locale'           => 'en_US',
		),
		'timeout_seconds'  => 90,
	),
	'trace-wp-ai-alt-text',
	'wp-ai-alt-text-idempotency'
);
$alt_text_request      = end( $GLOBALS['maca_http_requests'] );
$alt_text_request_body = json_decode( (string) ( $alt_text_request['args']['body'] ?? '' ), true );

maca_assert(
	is_array( $alt_text_result ) && 'run_wp_ai_alt_text_1' === (string) ( $alt_text_result['run_id'] ?? '' ),
	'Behavior: WordPress AI connector runtime returns the Cloud response for a supported alt text scene task.'
);

maca_assert(
	is_array( $alt_text_request_body )
	&& 'npcink-cloud/wp-ai-connector' === (string) ( $alt_text_request_body['ability_name'] ?? '' )
	&& 'wordpress_ai_connector' === (string) ( $alt_text_request_body['execution_kind'] ?? '' )
	&& 'alt_text_suggest' === (string) ( $alt_text_request_body['input']['task'] ?? '' )
	&& 'https://cdn.example.test/uploads/blue-mug.jpg' === (string) ( $alt_text_request_body['input']['request']['image_url'] ?? '' )
	&& 'image/jpeg' === (string) ( $alt_text_request_body['input']['request']['mime_type'] ?? '' )
	&& 'suggestion_only' === (string) ( $alt_text_request_body['input']['write_posture'] ?? '' )
	&& false === (bool) ( $alt_text_request_body['input']['direct_wordpress_write'] ?? true ),
	'Behavior: WordPress AI connector runtime projects bounded alt text image URL context without local writes.'
);

$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body'     => wp_json_encode(
		array(
			'status' => 'ok',
			'run_id' => 'run_wp_ai_alt_text_data_url_1',
			'data'   => array(
				'result' => array(
					'output_text' => 'A small inline test image.',
				),
			),
		)
	),
);

$alt_text_data_url = 'data:image/png;base64,' . base64_encode( 'image-bytes' );
$data_url_result   = $client->execute_wordpress_ai_connector_runtime(
	array(
		'contract_version' => 'wp_ai_connector_runtime.v1',
		'task'             => 'alt_text_suggest',
		'prompt'           => 'Generate alt text.',
		'input'            => array(
			'image_url' => $alt_text_data_url,
			'mime_type' => 'image/png',
			'filename'  => 'inline-test.png',
		),
	),
	'trace-wp-ai-alt-text-data-url',
	'wp-ai-alt-text-data-url-idempotency'
);
$data_url_request      = end( $GLOBALS['maca_http_requests'] );
$data_url_request_body = json_decode( (string) ( $data_url_request['args']['body'] ?? '' ), true );

maca_assert(
	is_array( $data_url_result )
	&& 'run_wp_ai_alt_text_data_url_1' === (string) ( $data_url_result['run_id'] ?? '' )
	&& $alt_text_data_url === (string) ( $data_url_request_body['input']['request']['image_url'] ?? '' ),
	'Behavior: WordPress AI connector runtime accepts bounded alt text data URL references without accepting generic base64 fields.'
);

$inline_alt_text = $client->execute_wordpress_ai_connector_runtime(
	array(
		'contract_version' => 'wp_ai_connector_runtime.v1',
		'task'             => 'alt_text_suggest',
		'prompt'           => 'Generate alt text.',
		'input'            => array(
			'image_base64' => base64_encode( 'image-bytes' ),
			'mime_type'    => 'image/png',
		),
	)
);
maca_assert(
	is_wp_error( $inline_alt_text ) && 'cloud_wp_ai_connector_chat_shape_not_allowed' === $inline_alt_text->get_error_code(),
	'Behavior: WordPress AI connector runtime rejects inline base64 alt text image payloads.'
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

$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body'     => wp_json_encode(
		array(
			'status' => 'ok',
			'run_id' => 'run_toolbox_site_ops_1',
			'data'   => array(
				'status' => 'succeeded',
				'result' => array(
					'contract_version'       => 'site_ops_cloud_analysis_result.v1',
					'direct_wordpress_write' => false,
					'priority_queue'         => array(
						array( 'finding_id' => 'finding_media_alt_gap' ),
					),
				),
			),
		)
	),
);

$toolbox_site_ops_result = $client->execute_toolbox_site_ops_cloud_analysis_runtime(
	array(
		'artifact_type'              => 'site_ops_cloud_analysis_request',
		'contract_version'           => 'site_ops_cloud_analysis_request.v1',
		'expected_result_contract'   => 'site_ops_cloud_analysis_result.v1',
		'cloud_role'                => 'runtime_detail',
		'execution_pattern'          => 'whole_run_offload',
		'write_posture'              => 'suggestion_only',
		'direct_wordpress_write'     => false,
		'core_proposal_created'      => false,
		'profile_id'                 => 'site-ops-analysis.managed',
		'timeout_seconds'            => 120,
		'input'                      => array(
			'local_summary'  => array( 'finding_count' => 3 ),
			'local_findings' => array(
				array(
					'id'             => 'finding_media_alt_gap',
					'issue_type'     => 'media',
					'write_boundary' => 'core_handoff_candidate',
				),
			),
		),
		'safety'                     => array(
			'cloud_is_runtime_detail_only' => true,
			'direct_wordpress_write'       => false,
		),
	),
	'trace-toolbox-site-ops',
	'toolbox-site-ops-idempotency'
);
$toolbox_site_ops_request      = end( $GLOBALS['maca_http_requests'] );
$toolbox_site_ops_request_body = json_decode( (string) ( $toolbox_site_ops_request['args']['body'] ?? '' ), true );

maca_assert(
	is_array( $toolbox_site_ops_result ) && 'run_toolbox_site_ops_1' === (string) ( $toolbox_site_ops_result['run_id'] ?? '' ),
	'Behavior: Toolbox Site Ops Cloud analysis runtime returns the Cloud response for a supported detail request.'
);

maca_assert(
	is_array( $toolbox_site_ops_request_body )
	&& 'npcink-toolbox/analyze-site-ops' === (string) ( $toolbox_site_ops_request_body['ability_name'] ?? '' )
	&& 'site_ops_cloud_analysis_request.v1' === (string) ( $toolbox_site_ops_request_body['contract_version'] ?? '' )
	&& 'toolbox_site_ops_cloud_analysis' === (string) ( $toolbox_site_ops_request_body['channel'] ?? '' )
	&& 'site_ops_cloud_analysis' === (string) ( $toolbox_site_ops_request_body['execution_kind'] ?? '' )
	&& 'whole_run_offload' === (string) ( $toolbox_site_ops_request_body['execution_pattern'] ?? '' )
	&& 'result_only' === (string) ( $toolbox_site_ops_request_body['storage_mode'] ?? '' )
	&& 90 === (int) ( $toolbox_site_ops_request_body['timeout_seconds'] ?? 0 )
	&& 0 === (int) ( $toolbox_site_ops_request_body['retry_max'] ?? -1 )
	&& false === (bool) ( $toolbox_site_ops_request_body['policy']['allow_fallback'] ?? true )
	&& 'site_ops_cloud_analysis_request.v1' === (string) ( $toolbox_site_ops_request_body['input']['contract_version'] ?? '' )
	&& 'site_ops_cloud_analysis_result.v1' === (string) ( $toolbox_site_ops_request_body['input']['expected_result_contract'] ?? '' )
	&& 'runtime_detail' === (string) ( $toolbox_site_ops_request_body['input']['cloud_role'] ?? '' )
	&& 'suggestion_only' === (string) ( $toolbox_site_ops_request_body['input']['write_posture'] ?? '' )
	&& false === (bool) ( $toolbox_site_ops_request_body['input']['direct_wordpress_write'] ?? true )
	&& false === (bool) ( $toolbox_site_ops_request_body['input']['core_proposal_created'] ?? true ),
	'Behavior: Toolbox Site Ops Cloud analysis runtime projects transport-only runtime detail without local writes.'
);

$site_ops_chat_shape = $client->execute_toolbox_site_ops_cloud_analysis_runtime(
	array(
		'contract_version'         => 'site_ops_cloud_analysis_request.v1',
		'expected_result_contract' => 'site_ops_cloud_analysis_result.v1',
		'cloud_role'              => 'runtime_detail',
		'execution_pattern'        => 'whole_run_offload',
		'write_posture'            => 'suggestion_only',
		'direct_wordpress_write'   => false,
		'core_proposal_created'    => false,
		'input'                    => array( 'local_summary' => array() ),
		'messages'                 => array(
			array(
				'role'    => 'user',
				'content' => 'Chat with me.',
			),
		),
	)
);
maca_assert(
	is_wp_error( $site_ops_chat_shape ) && 'cloud_toolbox_site_ops_cloud_analysis_shape_not_allowed' === $site_ops_chat_shape->get_error_code(),
	'Behavior: Toolbox Site Ops Cloud analysis runtime rejects generic chat message shapes.'
);

$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body'     => wp_json_encode(
		array(
			'status' => 'ok',
			'run_id' => 'run_toolbox_web_search_1',
			'data'   => array(
				'result' => array(
					'artifact_type'          => 'web_search_results',
					'contract_version'       => 'web_search_result.v1',
					'direct_wordpress_write' => false,
					'results'                => array(
						array(
							'title'   => 'Example result',
							'url'     => 'https://example.test/research',
							'snippet' => 'A bounded search result.',
						),
					),
				),
			),
		)
	),
);

$toolbox_web_search_result = $client->execute_toolbox_web_search_runtime(
	array(
		'contract_version' => 'web_search.v1',
		'query'            => 'WordPress editorial workflow research',
		'intent'           => 'writing_context',
		'max_results'      => 4,
		'recency_days'     => 14,
		'timeout_seconds'  => 90,
	),
	'trace-toolbox-web-search',
	'toolbox-web-search-idempotency'
);
$toolbox_web_search_request      = end( $GLOBALS['maca_http_requests'] );
$toolbox_web_search_request_body = json_decode( (string) ( $toolbox_web_search_request['args']['body'] ?? '' ), true );

maca_assert(
	is_array( $toolbox_web_search_result ) && 'run_toolbox_web_search_1' === (string) ( $toolbox_web_search_result['run_id'] ?? '' ),
	'Behavior: Toolbox web search runtime returns the Cloud response for a supported query request.'
);

maca_assert(
	is_array( $toolbox_web_search_request_body )
	&& 'npcink-cloud/web-search' === (string) ( $toolbox_web_search_request_body['ability_name'] ?? '' )
	&& 'web_search.v1' === (string) ( $toolbox_web_search_request_body['contract_version'] ?? '' )
	&& 'toolbox_web_search' === (string) ( $toolbox_web_search_request_body['channel'] ?? '' )
	&& 'web_search' === (string) ( $toolbox_web_search_request_body['execution_kind'] ?? '' )
	&& 'inline' === (string) ( $toolbox_web_search_request_body['execution_pattern'] ?? '' )
	&& 'result_only' === (string) ( $toolbox_web_search_request_body['storage_mode'] ?? '' )
	&& 60 === (int) ( $toolbox_web_search_request_body['timeout_seconds'] ?? 0 )
	&& true === (bool) ( $toolbox_web_search_request_body['policy']['allow_fallback'] ?? false )
	&& 'web_search.v1' === (string) ( $toolbox_web_search_request_body['input']['contract_version'] ?? '' )
	&& 'writing_context' === (string) ( $toolbox_web_search_request_body['input']['intent'] ?? '' )
	&& 4 === (int) ( $toolbox_web_search_request_body['input']['max_results'] ?? 0 )
	&& 'suggestion_only' === (string) ( $toolbox_web_search_request_body['input']['write_posture'] ?? '' )
	&& false === (bool) ( $toolbox_web_search_request_body['input']['direct_wordpress_write'] ?? true ),
	'Behavior: Toolbox web search runtime projects transport-only search evidence without WordPress writes.'
);

$web_search_chat_shape = $client->execute_toolbox_web_search_runtime(
	array(
		'contract_version' => 'web_search.v1',
		'query'            => 'Research this.',
		'messages'         => array(
			array(
				'role'    => 'user',
				'content' => 'Chat with me.',
			),
		),
	)
);
maca_assert(
	is_wp_error( $web_search_chat_shape ) && 'cloud_toolbox_web_search_shape_not_allowed' === $web_search_chat_shape->get_error_code(),
	'Behavior: Toolbox web search runtime rejects generic chat message shapes.'
);

$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body'     => wp_json_encode(
		array(
			'status' => 'ok',
			'run_id' => 'run_toolbox_image_source_1',
			'data'   => array(
				'result' => array(
					'artifact_type'          => 'image_source_candidates',
					'contract_version'       => 'image_source_candidates.v1',
					'direct_wordpress_write' => false,
					'images'                 => array(
						array(
							'id'  => 'image-source-1',
							'url' => 'https://images.example.test/photo.jpg',
						),
					),
				),
			),
		)
	),
);

$toolbox_image_source_result = $client->execute_toolbox_image_source_runtime(
	array(
		'contract_version'    => 'image_source_cloud_request.v1',
		'query'               => 'Editorial office desk',
		'provider'            => 'unsplash',
		'per_page'            => 12,
		'latency_mode'        => 'fast_first',
		'candidate_contract'  => 'image_candidate.v1',
		'timeout_seconds'     => 90,
		'data_classification' => 'public_reference_media',
	),
	'trace-toolbox-image-source',
	'toolbox-image-source-idempotency'
);
$toolbox_image_source_request      = end( $GLOBALS['maca_http_requests'] );
$toolbox_image_source_request_body = json_decode( (string) ( $toolbox_image_source_request['args']['body'] ?? '' ), true );

maca_assert(
	is_array( $toolbox_image_source_result ) && 'run_toolbox_image_source_1' === (string) ( $toolbox_image_source_result['run_id'] ?? '' ),
	'Behavior: Toolbox image-source runtime returns the Cloud response for a supported candidate request.'
);

maca_assert(
	is_array( $toolbox_image_source_request_body )
	&& 'npcink-toolbox/search-image-source' === (string) ( $toolbox_image_source_request_body['ability_name'] ?? '' )
	&& 'image_source_cloud_request.v1' === (string) ( $toolbox_image_source_request_body['contract_version'] ?? '' )
	&& 'toolbox_image_source' === (string) ( $toolbox_image_source_request_body['channel'] ?? '' )
	&& 'image_source' === (string) ( $toolbox_image_source_request_body['execution_kind'] ?? '' )
	&& 'inline' === (string) ( $toolbox_image_source_request_body['execution_pattern'] ?? '' )
	&& 'result_only' === (string) ( $toolbox_image_source_request_body['storage_mode'] ?? '' )
	&& 60 === (int) ( $toolbox_image_source_request_body['timeout_seconds'] ?? 0 )
	&& true === (bool) ( $toolbox_image_source_request_body['policy']['allow_fallback'] ?? false )
	&& 'image_source_cloud_request.v1' === (string) ( $toolbox_image_source_request_body['input']['contract_version'] ?? '' )
	&& 'unsplash' === (string) ( $toolbox_image_source_request_body['input']['provider'] ?? '' )
	&& 'cloud' === (string) ( $toolbox_image_source_request_body['input']['provider_origin'] ?? '' )
	&& 'fast_first' === (string) ( $toolbox_image_source_request_body['input']['latency_mode'] ?? '' )
	&& 'image_candidate.v1' === (string) ( $toolbox_image_source_request_body['input']['candidate_contract'] ?? '' )
	&& 'suggestion_only' === (string) ( $toolbox_image_source_request_body['input']['write_posture'] ?? '' )
	&& false === (bool) ( $toolbox_image_source_request_body['input']['direct_wordpress_write'] ?? true ),
	'Behavior: Toolbox image-source runtime projects transport-only source candidates without media writes.'
);

$image_source_chat_shape = $client->execute_toolbox_image_source_runtime(
	array(
		'contract_version' => 'image_source_cloud_request.v1',
		'query'            => 'Find source images.',
		'messages'         => array(
			array(
				'role'    => 'user',
				'content' => 'Chat with me.',
			),
		),
	)
);
maca_assert(
	is_wp_error( $image_source_chat_shape ) && 'cloud_toolbox_image_source_shape_not_allowed' === $image_source_chat_shape->get_error_code(),
	'Behavior: Toolbox image-source runtime rejects generic chat message shapes.'
);
