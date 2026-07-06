<?php
/**
 * Behavior tests for the Toolbox Site Knowledge runtime bridge.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

maca_load_addon_classes();

/**
 * Returns a valid Site Knowledge runtime payload fixture.
 *
 * @return array<string,mixed>
 */
function maca_site_knowledge_runtime_payload(): array {
	return array(
		'ability_name'        => 'npcink-cloud/site-knowledge-search',
		'contract_version'    => 'site_knowledge_search.v1',
		'execution_pattern'   => 'inline',
		'input'               => array(
			'contract_version' => 'site_knowledge_search.v1',
			'query'            => 'internal links for launch checklist',
			'intent'           => 'internal_link_candidates',
			'max_results'      => 5,
			'write_posture'    => 'suggestion_only',
		),
		'data_classification' => 'public_site_content',
		'storage_mode'        => 'result_only',
		'retention_ttl'       => 86400,
		'timeout_seconds'     => 20,
		'retry_max'           => 0,
		'policy'              => array(
			'allow_fallback' => true,
		),
	);
}

/**
 * Returns a Site Knowledge sync runtime payload fixture.
 *
 * @param string $sync_mode Sync mode.
 * @return array<string,mixed>
 */
function maca_site_knowledge_sync_runtime_payload( string $sync_mode = 'refresh' ): array {
	return array(
		'ability_name'        => 'npcink-cloud/site-knowledge-sync',
		'contract_version'    => 'site_knowledge_sync.v1',
		'execution_pattern'   => 'whole_run_offload',
		'input'               => array(
			'contract_version' => 'site_knowledge_sync.v1',
			'sync_mode'        => $sync_mode,
			'post_ids'         => array( 123 ),
			'max_posts'        => 1,
			'documents'        => array(),
			'write_posture'    => 'suggestion_only',
		),
		'data_classification' => 'public_site_content',
		'storage_mode'        => 'result_only',
		'retention_ttl'       => 86400,
		'timeout_seconds'     => 60,
		'retry_max'           => 1,
		'policy'              => array(
			'allow_fallback' => true,
		),
	);
}

maca_reset_test_state();
Npcink_Cloud_Site_Knowledge_Runtime_Bridge::register();
maca_assert(
	! empty( $GLOBALS['maca_filters']['npcink_toolbox_site_knowledge_cloud_request'] ),
	'Behavior: Site Knowledge runtime bridge registers the Toolbox Cloud request filter.'
);

maca_reset_test_state();
Npcink_Cloud_Site_Knowledge_Runtime_Bridge::register();
$unconfigured = apply_filters(
	'npcink_toolbox_site_knowledge_cloud_request',
	null,
	maca_site_knowledge_runtime_payload(),
	'npcink-cloud/site-knowledge-search',
	'site_knowledge_search.v1'
);
maca_assert(
	null === $unconfigured && array() === $GLOBALS['maca_http_requests'],
	'Behavior: Site Knowledge runtime bridge lets Toolbox fail closed when Cloud settings are missing.'
);

maca_reset_test_state();
maca_seed_settings( true );
Npcink_Cloud_Site_Knowledge_Runtime_Bridge::register();
$handled = apply_filters(
	'npcink_toolbox_site_knowledge_cloud_request',
	null,
	maca_site_knowledge_runtime_payload(),
	'npcink-cloud/site-knowledge-search',
	'site_knowledge_search.v1'
);
$request = $GLOBALS['maca_http_requests'][0] ?? array();
$body = json_decode( (string) ( $request['args']['body'] ?? '' ), true );
$body = is_array( $body ) ? $body : array();
maca_assert(
	is_array( $handled )
	&& false !== strpos( (string) ( $request['url'] ?? '' ), '/v1/runtime/execute' )
	&& 'POST' === (string) ( $request['args']['method'] ?? '' )
	&& 'npcink-cloud/site-knowledge-search' === (string) ( $body['ability_name'] ?? '' )
	&& 'site_knowledge_search.v1' === (string) ( $body['contract_version'] ?? '' )
	&& 'suggestion_only' === (string) ( $body['input']['write_posture'] ?? '' ),
	'Behavior: Site Knowledge runtime bridge forwards valid Toolbox requests through signed runtime execute only.'
);

maca_reset_test_state();
maca_seed_settings( true );
$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body' => wp_json_encode(
		array(
			'status' => 'ok',
			'data' => array(
				'contract_version' => 'site_knowledge_status.v1',
				'ownership' => array(
					'source_content_owner' => 'local_wordpress_host',
					'delivery_bridge_owner' => 'cloud_addon',
					'index_execution_owner' => 'cloud_service',
					'index_lifecycle_owner' => 'cloud_service',
					'freshness_policy_owner' => 'cloud_service',
					'diagnostics_detail_owner' => 'cloud_service',
					'vector_storage_owner' => 'cloud_service',
					'embedding_execution_owner' => 'cloud_service',
					'approval_owner' => 'local_wordpress_host',
					'final_write_owner' => 'local_wordpress_host',
					'wordpress_write_owner' => 'local_wordpress_host',
				),
				'truth_boundaries' => array(
					'cloud_is_index_truth' => true,
					'cloud_is_freshness_truth' => true,
					'cloud_is_diagnostics_truth' => true,
					'cloud_is_wordpress_control_plane' => false,
					'cloud_creates_wordpress_writes' => false,
					'cloud_owns_local_approval' => false,
					'cloud_owns_ability_registry' => false,
					'cloud_owns_workflow_registry' => false,
				),
			),
		)
	),
);
$status_payload = maca_site_knowledge_runtime_payload();
$status_payload['ability_name'] = 'npcink-cloud/site-knowledge-status';
$status_payload['contract_version'] = 'site_knowledge_status.v1';
$status_payload['input']['contract_version'] = 'site_knowledge_status.v1';
$status_result = Npcink_Cloud_Site_Knowledge_Runtime_Bridge::dispatch_runtime(
	$status_payload,
	'npcink-cloud/site-knowledge-status',
	'site_knowledge_status.v1'
);
maca_assert(
	is_array( $status_result )
	&& 'site_knowledge_status.v1' === (string) ( $status_result['site_knowledge_cloud_boundary']['contract_version'] ?? '' )
	&& 'cloud_service' === (string) ( $status_result['site_knowledge_cloud_boundary']['ownership']['index_execution_owner'] ?? '' )
	&& 'local_wordpress_host' === (string) ( $status_result['site_knowledge_cloud_boundary']['ownership']['final_write_owner'] ?? '' )
	&& true === (bool) ( $status_result['site_knowledge_cloud_boundary']['truth_boundaries']['cloud_is_index_truth'] ?? false )
	&& false === (bool) ( $status_result['site_knowledge_cloud_boundary']['truth_boundaries']['cloud_is_wordpress_control_plane'] ?? true )
	&& false === (bool) ( $status_result['site_knowledge_cloud_boundary']['truth_boundaries']['cloud_creates_wordpress_writes'] ?? true ),
	'Behavior: Site Knowledge runtime bridge preserves Cloud status owner and truth boundary fields as read-only projection.'
);

maca_reset_test_state();
maca_seed_settings( true );
Npcink_Cloud_Site_Knowledge_Runtime_Bridge::register();
$ignored = apply_filters(
	'npcink_toolbox_site_knowledge_cloud_request',
	null,
	maca_site_knowledge_runtime_payload(),
	'npcink-cloud/other-runtime',
	'other_runtime.v1'
);
maca_assert(
	null === $ignored && array() === $GLOBALS['maca_http_requests'],
	'Behavior: Site Knowledge runtime bridge ignores non-Site-Knowledge ability requests.'
);

maca_reset_test_state();
maca_seed_settings( true );
$invalid_payload = maca_site_knowledge_runtime_payload();
$invalid_payload['input']['write_posture'] = 'direct_write';
$invalid = Npcink_Cloud_Site_Knowledge_Runtime_Bridge::dispatch_runtime(
	$invalid_payload,
	'npcink-cloud/site-knowledge-search',
	'site_knowledge_search.v1'
);
maca_assert(
	is_wp_error( $invalid )
	&& 'cloud_site_knowledge_request_invalid' === $invalid->get_error_code()
	&& array() === $GLOBALS['maca_http_requests'],
	'Behavior: Site Knowledge runtime bridge rejects non-suggestion-only payloads without forwarding.'
);

maca_reset_test_state();
maca_seed_settings( true );
$bad_contract = Npcink_Cloud_Site_Knowledge_Runtime_Bridge::dispatch_runtime(
	maca_site_knowledge_runtime_payload(),
	'npcink-cloud/site-knowledge-search',
	'site_knowledge_sync.v1'
);
maca_assert(
	is_wp_error( $bad_contract )
	&& 'cloud_site_knowledge_contract_not_allowed' === $bad_contract->get_error_code()
	&& array() === $GLOBALS['maca_http_requests'],
	'Behavior: Site Knowledge runtime bridge rejects unsupported ability and contract pairs.'
);

maca_reset_test_state();
maca_seed_settings( true );
$secret_payload = maca_site_knowledge_runtime_payload();
$secret_payload['input']['credentials'] = 'should-not-forward';
$secret_result = Npcink_Cloud_Site_Knowledge_Runtime_Bridge::dispatch_runtime(
	$secret_payload,
	'npcink-cloud/site-knowledge-search',
	'site_knowledge_search.v1'
);
maca_assert(
	is_wp_error( $secret_result )
	&& 'cloud_site_knowledge_sensitive_key_not_allowed' === $secret_result->get_error_code()
	&& array() === $GLOBALS['maca_http_requests'],
	'Behavior: Site Knowledge runtime bridge rejects credential-like keys before transport.'
);

maca_reset_test_state();
maca_seed_settings( true );
$rebuild_sync = Npcink_Cloud_Site_Knowledge_Runtime_Bridge::dispatch_runtime(
	maca_site_knowledge_sync_runtime_payload( 'rebuild' ),
	'npcink-cloud/site-knowledge-sync',
	'site_knowledge_sync.v1'
);
$delete_sync = Npcink_Cloud_Site_Knowledge_Runtime_Bridge::dispatch_runtime(
	maca_site_knowledge_sync_runtime_payload( 'delete' ),
	'npcink-cloud/site-knowledge-sync',
	'site_knowledge_sync.v1'
);
maca_assert(
	is_wp_error( $rebuild_sync )
	&& 'cloud_site_knowledge_sync_mode_not_allowed' === $rebuild_sync->get_error_code()
	&& is_wp_error( $delete_sync )
	&& 'cloud_site_knowledge_sync_mode_not_allowed' === $delete_sync->get_error_code()
	&& array() === $GLOBALS['maca_http_requests'],
	'Behavior: Site Knowledge runtime bridge rejects rebuild and delete sync modes before transport.'
);
