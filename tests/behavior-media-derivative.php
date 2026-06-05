<?php
/**
 * Behavior tests for media derivative Cloud transport.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

maca_load_addon_classes();

maca_seed_settings( false );
$unverified = Npcink_Cloud_Media_Derivative_Transport::dispatch_from_ability_response(
	maca_ability_fixture(),
	array(
		'artifact_id' => 'source_artifact',
		'expires_at' => maca_future_expiry(),
	)
);
maca_assert(
	is_wp_error( $unverified ) && 'cloud_runtime_unverified' === $unverified->get_error_code(),
	'Behavior: media derivative dispatch fails closed before verified Cloud credentials.'
);

maca_seed_settings( true );
$credential_payload = maca_ability_fixture();
$credential_payload['cloud_job_payload']['signed_headers'] = array( 'Authorization' => 'Bearer secret' );
$credential_result = Npcink_Cloud_Media_Derivative_Transport::dispatch_from_ability_response(
	$credential_payload,
	array(
		'artifact_id' => 'source_artifact',
		'expires_at' => maca_future_expiry(),
	)
);
maca_assert(
	is_wp_error( $credential_result ) && 'cloud_media_derivative_credentials_present' === $credential_result->get_error_code(),
	'Behavior: ability credentials and signed headers are rejected before Cloud dispatch.'
);

$watermark_without_plan = Npcink_Cloud_Media_Derivative_Transport::dispatch_from_ability_response(
	maca_ability_fixture(),
	array(
		'artifact_id' => 'source_artifact',
		'expires_at' => maca_future_expiry(),
	),
	'',
	'',
	array(
		'artifact_id' => 'watermark_artifact',
		'expires_at' => maca_future_expiry(),
	)
);
maca_assert(
	is_wp_error( $watermark_without_plan ) && 'cloud_media_derivative_watermark_plan_missing' === $watermark_without_plan->get_error_code(),
	'Behavior: watermark artifacts require a local ability watermark plan.'
);

$watermark_payload = maca_ability_fixture();
$watermark_payload['cloud_job_payload']['watermark'] = array(
	'type' => 'image',
	'position' => 'bottom_right',
);
$watermark_missing_source = Npcink_Cloud_Media_Derivative_Transport::dispatch_from_ability_response(
	$watermark_payload,
	array(
		'artifact_id' => 'source_artifact',
		'expires_at' => maca_future_expiry(),
	)
);
maca_assert(
	is_wp_error( $watermark_missing_source ) && 'cloud_media_derivative_watermark_source_missing' === $watermark_missing_source->get_error_code(),
	'Behavior: watermark plans require a watermark upload or artifact id.'
);

$watermark_artifact_result = Npcink_Cloud_Media_Derivative_Transport::dispatch_from_ability_response(
	$watermark_payload,
	array(
		'artifact_id' => 'source_artifact',
		'expires_at' => maca_future_expiry(),
	),
	'trace-watermark',
	'idempotency-watermark',
	array(
		'artifact_id' => 'watermark_artifact',
		'expires_at' => maca_future_expiry(),
	)
);
maca_assert(
	is_array( $watermark_artifact_result ) && 'ok' === (string) ( $watermark_artifact_result['status'] ?? '' ),
	'Behavior: watermark artifact references dispatch through the media derivative endpoint.'
);

$text_watermark_payload = maca_ability_fixture();
$text_watermark_payload['cloud_job_payload']['watermark'] = array(
	'type' => 'text',
	'text' => 'AI',
	'position' => 'top_right',
	'opacity' => 0.75,
	'font_size' => 48,
	'color' => '#FFFFFF',
	'background' => 'rgba(0,0,0,0.35)',
	'margin_px' => 24,
);
$text_watermark_result = Npcink_Cloud_Media_Derivative_Transport::dispatch_from_ability_response(
	$text_watermark_payload,
	array(
		'artifact_id' => 'source_artifact',
		'expires_at' => maca_future_expiry(),
	),
	'trace-text-watermark',
	'idempotency-text-watermark'
);
$text_watermark_request = end( $GLOBALS['maca_http_requests'] );
$text_watermark_body = json_decode( (string) ( $text_watermark_request['args']['body'] ?? '' ), true );
maca_assert(
	is_array( $text_watermark_result )
	&& 'ok' === (string) ( $text_watermark_result['status'] ?? '' )
	&& 'text' === (string) ( $text_watermark_body['cloud_job_payload']['watermark']['type'] ?? '' )
	&& 'AI' === (string) ( $text_watermark_body['cloud_job_payload']['watermark']['text'] ?? '' )
	&& empty( $text_watermark_body['cloud_job_payload']['watermark']['artifact_id'] ),
	'Behavior: text watermark plans dispatch without a watermark upload or artifact id.'
);

$text_watermark_source_conflict = Npcink_Cloud_Media_Derivative_Transport::dispatch_from_ability_response(
	$text_watermark_payload,
	array(
		'artifact_id' => 'source_artifact',
		'expires_at' => maca_future_expiry(),
	),
	'',
	'',
	array(
		'artifact_id' => 'watermark_artifact',
		'expires_at' => maca_future_expiry(),
	)
);
maca_assert(
	is_wp_error( $text_watermark_source_conflict ) && 'cloud_media_derivative_watermark_source_conflict' === $text_watermark_source_conflict->get_error_code(),
	'Behavior: text watermark plans reject watermark upload or artifact sources.'
);

$client = new Npcink_Cloud_Runtime_Client( Npcink_Cloud_Addon_Settings::get_settings() );
$watermark_file_result = $client->create_media_derivative(
	array( 'request_contract_version' => 'media_derivative_cloud_request.v1' ),
	array( 'watermark_file' => array( 'contents' => 'x' ) )
);
maca_assert(
	is_array( $watermark_file_result ) && 'ok' === (string) ( $watermark_file_result['status'] ?? '' ),
	'Behavior: runtime client accepts bounded watermark_file multipart transport.'
);

maca_reset_test_state();
maca_seed_settings( true );
$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 422 ),
	'body'     => wp_json_encode(
		array(
			'code'    => 'image_source_provider_error',
			'message' => array(
				array(
					'loc' => array( 'body', 'input', 'provider' ),
					'msg' => array( 'Unsplash provider failed.', 'Check Cloud provider key or runtime availability.' ),
				),
			),
		)
	),
);
$nested_error_result = $client->execute_runtime( array( 'ability_name' => 'npcink-toolbox/search-image-source' ) );
maca_assert(
	is_wp_error( $nested_error_result )
	&& false !== strpos( $nested_error_result->get_error_message(), 'body.input.provider: Unsplash provider failed.' )
	&& false === strpos( $nested_error_result->get_error_message(), 'Array' ),
	'Behavior: runtime client renders nested Cloud error payloads without Array-to-string collapse.'
);

maca_reset_test_state();
maca_seed_settings( true );
$preview_bytes = 'derivative-preview-bytes';
$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'headers'  => array(
		'Content-Type'   => 'image/webp',
		'Content-Length' => (string) strlen( $preview_bytes ),
	),
	'body'     => $preview_bytes,
);
$artifact_preview = Npcink_Cloud_Media_Derivative_Transport::download_artifact_preview(
	array(
		'artifact_id' => 'derivative_artifact',
		'expires_at' => maca_future_expiry(),
		'mime_type'  => 'image/webp',
		'sha256'     => hash( 'sha256', $preview_bytes ),
	),
	'trace-preview'
);
$preview_request = end( $GLOBALS['maca_http_requests'] );
maca_assert(
	is_array( $artifact_preview )
	&& 'derivative_artifact' === $artifact_preview['artifact_id']
	&& $preview_bytes === $artifact_preview['contents']
	&& false !== strpos( (string) ( $preview_request['url'] ?? '' ), '/v1/runtime/artifacts/derivative_artifact/download' )
	&& 'image/*' === (string) ( $preview_request['args']['headers']['Accept'] ?? '' ),
	'Behavior: derivative artifact preview downloads through the explicit signed runtime artifact endpoint.'
);

$expired_preview = Npcink_Cloud_Media_Derivative_Transport::download_artifact_preview(
	array(
		'artifact_id' => 'derivative_artifact',
		'expires_at' => gmdate( 'c', time() - 60 ),
		'mime_type'  => 'image/webp',
	)
);
maca_assert(
	is_wp_error( $expired_preview ) && 'cloud_media_derivative_artifact_expired' === $expired_preview->get_error_code(),
	'Behavior: expired Cloud artifacts cannot be downloaded for local preview.'
);

$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'headers'  => array( 'Content-Type' => 'image/webp' ),
	'body'     => 'different-bytes',
);
$checksum_mismatch_preview = Npcink_Cloud_Media_Derivative_Transport::download_artifact_preview(
	array(
		'artifact_id' => 'derivative_artifact',
		'expires_at' => maca_future_expiry(),
		'mime_type'  => 'image/webp',
		'sha256'     => hash( 'sha256', $preview_bytes ),
	)
);
maca_assert(
	is_wp_error( $checksum_mismatch_preview ) && 'cloud_media_derivative_artifact_checksum_mismatch' === $checksum_mismatch_preview->get_error_code(),
	'Behavior: derivative preview download rejects checksum mismatches.'
);

$expired_proposal = Npcink_Cloud_Media_Derivative_Transport::build_local_proposal_payload(
	maca_ability_fixture(),
	array( 'data' => array( 'run_id' => 'run_media_1' ) ),
	array(
		'artifact_id' => 'derivative_artifact',
		'expires_at' => gmdate( 'c', time() - 60 ),
		'mime_type' => 'image/webp',
	)
);
maca_assert(
	is_wp_error( $expired_proposal ) && 'cloud_media_derivative_artifact_expired' === $expired_proposal->get_error_code(),
	'Behavior: expired Cloud artifacts cannot produce proposal payloads.'
);

$missing_artifact_id = Npcink_Cloud_Media_Derivative_Transport::build_local_proposal_payload(
	maca_ability_fixture(),
	array( 'data' => array( 'run_id' => 'run_media_1' ) ),
	array(
		'download_url' => 'https://cloud.example.test/artifacts/derivative',
		'expires_at' => maca_future_expiry(),
		'mime_type' => 'image/webp',
	)
);
maca_assert(
	is_wp_error( $missing_artifact_id ) && 'cloud_media_derivative_derivative_artifact_id_missing' === $missing_artifact_id->get_error_code(),
	'Behavior: derivative proposal adoption requires a Cloud artifact id.'
);

$mismatched_artifact = Npcink_Cloud_Media_Derivative_Transport::build_local_proposal_payload(
	maca_ability_fixture(),
	array(
		'data' => array(
			'run_id' => 'run_media_1',
			'derivative' => array(
				'artifact_id' => 'expected_artifact',
				'sha256' => str_repeat( 'a', 64 ),
			),
		),
	),
	array(
		'artifact_id' => 'other_artifact',
		'run_id' => 'run_media_1',
		'expires_at' => maca_future_expiry(),
		'mime_type' => 'image/webp',
		'sha256' => str_repeat( 'a', 64 ),
	)
);
maca_assert(
	is_wp_error( $mismatched_artifact ) && 'cloud_media_derivative_artifact_binding_mismatch' === $mismatched_artifact->get_error_code(),
	'Behavior: derivative artifact id must match the Cloud result when provided.'
);

$proposal = Npcink_Cloud_Media_Derivative_Transport::build_local_proposal_payload(
	maca_ability_fixture(),
	array(
		'data' => array(
			'run_id' => 'run_media_1',
			'derivative' => array(
				'artifact_id' => 'derivative_artifact',
				'width' => 1200,
				'height' => 675,
				'filesize_bytes' => 180000,
				'mime_type' => 'image/webp',
			),
		),
	),
	array(
		'artifact_id' => 'derivative_artifact',
		'run_id' => 'run_media_1',
		'expires_at' => maca_future_expiry(),
		'mime_type' => 'image/webp',
		'width' => 1200,
		'height' => 675,
		'filesize_bytes' => 180000,
	)
);
maca_assert(
	is_array( $proposal )
	&& 'local_wordpress_host' === $proposal['final_write_owner']
	&& 'preview_only' === $proposal['default_action']
	&& false === $proposal['local_adoption']['replace_original_default']
	&& false === $proposal['local_adoption']['attachment_metadata_write_included'],
	'Behavior: valid Cloud artifacts become preview-only local WordPress host proposals.'
);

$proposal_from_runtime_artifact = Npcink_Cloud_Media_Derivative_Transport::build_local_proposal_payload(
	maca_ability_fixture(),
	array(
		'data' => array(
			'run_id' => 'run_media_1',
			'status' => 'succeeded',
			'result' => array(
				'artifact' => array(
					'artifact_id' => 'derivative_artifact',
					'download_url' => '/v1/runtime/artifacts/derivative_artifact/download',
					'expires_at' => maca_future_expiry(),
					'mime_type' => 'image/webp',
					'format' => 'webp',
					'width' => 1200,
					'height' => 675,
					'filesize_bytes' => 180000,
					'checksum' => 'sha256:' . str_repeat( 'a', 64 ),
					'processing_warnings' => array(),
				),
			),
		),
	),
	array(
		'artifact_id' => 'derivative_artifact',
		'run_id' => 'run_media_1',
		'expires_at' => maca_future_expiry(),
		'mime_type' => 'image/webp',
		'width' => 1200,
		'height' => 675,
		'filesize_bytes' => 180000,
		'checksum' => 'sha256:' . str_repeat( 'a', 64 ),
	)
);
maca_assert(
	is_array( $proposal_from_runtime_artifact )
	&& 'derivative_artifact' === $proposal_from_runtime_artifact['artifact']['artifact_id']
	&& str_repeat( 'a', 64 ) === $proposal_from_runtime_artifact['artifact']['sha256'],
	'Behavior: runtime result artifact shape can become a local proposal payload.'
);

$incomplete_metrics = maca_ability_fixture();
unset( $incomplete_metrics['cloud_job_payload']['source_asset']['width'] );
$proposal_with_warnings = Npcink_Cloud_Media_Derivative_Transport::build_local_proposal_payload(
	$incomplete_metrics,
	array(
		'data' => array(
			'run_id' => 'run_media_1',
			'derivative' => array(
				'artifact_id' => 'derivative_artifact',
				'width' => 1200,
				'height' => 675,
				'filesize_bytes' => 180000,
				'mime_type' => 'image/webp',
			),
		),
	),
	array(
		'artifact_id' => 'derivative_artifact',
		'run_id' => 'run_media_1',
		'expires_at' => maca_future_expiry(),
		'mime_type' => 'image/webp',
		'width' => 1200,
		'height' => 675,
		'filesize_bytes' => 180000,
	)
);
maca_assert(
	is_array( $proposal_with_warnings )
	&& in_array( 'Original media metrics are incomplete.', $proposal_with_warnings['warnings'], true ),
	'Behavior: proposal payload warns when original or derivative metrics are incomplete.'
);

$invalid_source_type = maca_ability_fixture();
$invalid_source_type['cloud_job_payload']['source_media_type'] = 'text/html';
$invalid_source_result = Npcink_Cloud_Media_Derivative_Transport::dispatch_from_ability_response(
	$invalid_source_type,
	array(
		'artifact_id' => 'source_artifact',
		'expires_at' => maca_future_expiry(),
	)
);
maca_assert(
	is_wp_error( $invalid_source_result ) && 'cloud_media_derivative_source_media_type_invalid' === $invalid_source_result->get_error_code(),
	'Behavior: unsupported source media types fail closed.'
);

$generic_source_type = maca_ability_fixture();
$generic_source_type['cloud_job_payload']['source_media_type'] = 'image';
$generic_source_result = Npcink_Cloud_Media_Derivative_Transport::dispatch_from_ability_response(
	$generic_source_type,
	array(
		'artifact_id' => 'source_artifact',
		'expires_at' => maca_future_expiry(),
	)
);
maca_assert(
	! is_wp_error( $generic_source_result ),
	'Behavior: generic image source media type is accepted for ability contracts.'
);

$unsafe_url = Npcink_Cloud_Addon_Settings::build_settings_from_admin_payload(
	array(
		'base_url' => 'http://cloud.example.test',
		'api_key' => '{"site_id":"site_test","key_id":"key_test","secret":"secret_test"}',
	)
);
$local_url = Npcink_Cloud_Addon_Settings::build_settings_from_admin_payload(
	array(
		'base_url' => 'http://127.0.0.1:8787',
		'api_key' => '{"site_id":"site_test","key_id":"key_test","secret":"secret_test"}',
	)
);
maca_assert(
	is_wp_error( $unsafe_url )
	&& 'invalid_cloud_base_url' === $unsafe_url->get_error_code()
	&& is_array( $local_url )
	&& 'http://127.0.0.1:8787' === $local_url['base_url'],
	'Behavior: Cloud Base URL requires HTTPS except localhost development URLs.'
);
