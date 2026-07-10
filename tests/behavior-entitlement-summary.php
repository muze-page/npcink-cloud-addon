<?php
/**
 * Behavior tests for Cloud entitlement summary caching.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
maca_load_addon_classes();
require_once MACA_TEST_ROOT . '/includes/class-cloud-settings-page.php';

maca_reset_test_state();
maca_seed_settings( true );

$entitlement_response = array(
	'status' => 'ok',
	'data'   => array(
		'contract_version' => 'cloud-billing-entitlement-v1',
		'package'          => 'Pro',
		'package_tier'     => 'pro',
		'status'           => 'active',
		'period'           => array(
			'end_at' => '2026-07-01T00:00:00Z',
		),
		'entitlement'      => array(
			'usage_limits'         => array(
				'period'       => 'month',
				'max_runs'     => 100,
				'max_tokens'   => 2000,
				'max_cost_usd' => 25,
				'max_sites'    => 3,
			),
			'hosted_runtime_quota' => array(
				'max_active_runs'  => 2,
				'max_batch_items'  => 10,
				'execution_tiers'  => array( 'cloud' ),
			),
		),
		'quota_summary'    => array(
			'credit_usage_detail' => array(
				'summary'      => array(
					'used'      => 12.5,
					'limit'     => 100,
					'remaining' => 87.5,
					'unit'      => 'credit',
					'status'    => 'ok',
				),
				'portal_paths' => array(
					'credit_usage' => '/portal/usage',
				),
			),
		),
	),
);

$cached_summary = Npcink_Cloud_Entitlement_Summary::cache_summary_from_response( $entitlement_response );
$read_summary   = Npcink_Cloud_Entitlement_Summary::get_cached_summary();

maca_assert(
	! empty( $cached_summary['available'] )
	&& 'Pro' === (string) ( $cached_summary['package_label'] ?? '' )
	&& 'pro' === (string) ( $cached_summary['package_tier'] ?? '' )
	&& 'active' === (string) ( $cached_summary['entitlement_status'] ?? '' )
	&& 'cached' === (string) ( $read_summary['state'] ?? '' )
	&& 'Pro' === (string) ( $read_summary['package_label'] ?? '' ),
	'Behavior: verification entitlement response can populate the short local summary cache.'
);

$runtime_normalizer = new ReflectionMethod( Npcink_Cloud_Entitlement_Summary::class, 'normalize_pro_cloud_runtime' );
$runtime_normalizer->setAccessible( true );
$runtime_without_optional_fields = $runtime_normalizer->invoke(
	null,
	array(
		'max_nightly_inspection_runs_per_period' => 10,
		'used_nightly_inspection_runs'           => 2,
		'quota_exhausted'                       => 'false',
	)
);
$runtime_with_optional_fields = $runtime_normalizer->invoke(
	null,
	array(
		'max_nightly_inspection_runs_per_period' => 10,
		'used_nightly_inspection_runs'           => 10,
		'max_batch_items'                       => '25',
		'result_retention_days'                 => '14',
	)
);

$format_runtime_integer = new ReflectionMethod( Npcink_Cloud_Settings_Page::class, 'format_runtime_integer_projection' );
$format_runtime_integer->setAccessible( true );
$format_runtime_days = new ReflectionMethod( Npcink_Cloud_Settings_Page::class, 'format_runtime_days_projection' );
$format_runtime_days->setAccessible( true );
$format_runtime_boolean = new ReflectionMethod( Npcink_Cloud_Settings_Page::class, 'format_runtime_boolean_projection' );
$format_runtime_boolean->setAccessible( true );
$format_runtime_quota = new ReflectionMethod( Npcink_Cloud_Settings_Page::class, 'format_runtime_quota_projection' );
$format_runtime_quota->setAccessible( true );

maca_assert(
	is_array( $runtime_without_optional_fields )
	&& null === ( $runtime_without_optional_fields['max_batch_items'] ?? null )
	&& null === ( $runtime_without_optional_fields['result_retention_days'] ?? null )
	&& false === (bool) ( $runtime_without_optional_fields['quota_exhausted'] ?? true )
	&& 'runtime_detail' === (string) ( $runtime_without_optional_fields['contract_reuse']['cloud_role'] ?? '' )
	&& 'product_surface' === (string) ( $runtime_without_optional_fields['contract_reuse']['toolbox_role'] ?? '' )
	&& 'proposal_handoff' === (string) ( $runtime_without_optional_fields['contract_reuse']['core_role'] ?? '' )
	&& 'execution_profiles' === (string) ( $runtime_without_optional_fields['contract_reuse']['adapter_role'] ?? '' )
	&& 'ability_contracts' === (string) ( $runtime_without_optional_fields['contract_reuse']['toolkit_role'] ?? '' )
	&& false === (bool) ( $runtime_without_optional_fields['contract_reuse']['adds_registry'] ?? true )
	&& false === (bool) ( $runtime_without_optional_fields['contract_reuse']['adds_scheduler_truth'] ?? true )
	&& false === (bool) ( $runtime_without_optional_fields['contract_reuse']['adds_approval_store'] ?? true )
	&& false === (bool) ( $runtime_without_optional_fields['contract_reuse']['adds_queue'] ?? true )
	&& false === (bool) ( $runtime_without_optional_fields['contract_reuse']['adds_write_executor'] ?? true )
	&& 'unavailable' === $format_runtime_integer->invoke( null, $runtime_without_optional_fields, 'max_batch_items' )
	&& 'unavailable' === $format_runtime_days->invoke( null, $runtime_without_optional_fields, 'result_retention_days' )
	&& 'no' === $format_runtime_boolean->invoke( null, $runtime_without_optional_fields, 'quota_exhausted' )
	&& '2 used / 10 limit / 8 remaining' === $format_runtime_quota->invoke( null, $runtime_without_optional_fields )
	&& is_array( $runtime_with_optional_fields )
	&& true === (bool) ( $runtime_with_optional_fields['quota_exhausted'] ?? false )
	&& '25' === $format_runtime_integer->invoke( null, $runtime_with_optional_fields, 'max_batch_items' )
	&& '14 days' === $format_runtime_days->invoke( null, $runtime_with_optional_fields, 'result_retention_days' ),
	'Behavior: Pro Cloud Runtime projection preserves unavailable optional fields, contract reuse boundaries, and quota exhaustion strictly.'
);

maca_reset_test_state();
maca_seed_settings( true );
$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body'     => wp_json_encode( $entitlement_response ),
);

$client = new Npcink_Cloud_Runtime_Client( Npcink_Cloud_Addon_Settings::get_settings() );
$probe  = $client->probe_connectivity();
$readiness = is_array( $probe['readiness_result'] ?? null ) ? $probe['readiness_result'] : array();
$readiness_support_facts = is_array( $readiness['copyable_support_facts'] ?? null ) ? $readiness['copyable_support_facts'] : array();
$readiness_groups = is_array( $readiness['diagnostic_panel_groups'] ?? null ) ? $readiness['diagnostic_panel_groups'] : array();
$readiness_groups_by_id = array_column( $readiness_groups, null, 'diagnostic_panel_group' );
$probe_live_request = $GLOBALS['maca_http_requests'][0] ?? array();
$probe_signed_request = $GLOBALS['maca_http_requests'][1] ?? array();

maca_assert(
	! empty( $probe['ok'] )
	&& is_array( $probe['entitlement_response'] ?? null )
	&& 'cloud_addon_readiness_result.v1' === (string) ( $readiness['contract_version'] ?? '' )
	&& 'probe_connectivity' === (string) ( $readiness['manual_test_action'] ?? '' )
	&& 'ready' === (string) ( $readiness['status'] ?? '' )
	&& 'ready' === (string) ( $readiness['bounded_status'] ?? '' )
	&& 'cloud_addon' === (string) ( $readiness['owner_label'] ?? '' )
	&& 'continue' === (string) ( $readiness['next_safe_action'] ?? '' )
	&& 'read_only' === (string) ( $readiness['write_posture'] ?? '' )
	&& 'npcink_cloud_runtime' === (string) ( $readiness['connector_slot'] ?? '' )
	&& 'ready' === (string) ( $readiness['connector_diagnostic_category'] ?? '' )
	&& 'ready' === (string) ( $readiness['credential_slot_readiness'] ?? '' )
	&& 'ready' === (string) ( $readiness['service_liveness_status'] ?? '' )
	&& 'ready' === (string) ( $readiness['signed_transport_status'] ?? '' )
	&& 'ready' === (string) ( $readiness_support_facts['connector_diagnostic_category'] ?? '' )
	&& 'ready' === (string) ( $readiness_support_facts['credential_slot_readiness'] ?? '' )
	&& 'yes' === (string) ( $readiness_support_facts['base_url_present'] ?? '' )
	&& 'yes' === (string) ( $readiness_support_facts['signing_secret_slot_present'] ?? '' )
	&& 5 === count( $readiness_groups )
	&& 'ok' === (string) ( $readiness_groups_by_id['local_configuration']['severity'] ?? '' )
	&& 'cloud_addon' === (string) ( $readiness_groups_by_id['signed_transport']['owner_label'] ?? '' )
	&& 'ready' === (string) ( $readiness_groups_by_id['entitlement_readiness']['bounded_status'] ?? '' )
	&& 'administrator_only' === (string) ( $readiness_groups_by_id['support_facts']['visibility'] ?? '' )
	&& 'read_only' === (string) ( $readiness_groups_by_id['support_facts']['write_posture'] ?? '' )
	&& 'Pro' === (string) ( $probe['entitlement_response']['data']['package'] ?? '' )
	&& 2 === count( $GLOBALS['maca_http_requests'] )
	&& false !== strpos( (string) ( $probe_live_request['url'] ?? '' ), '/health/live' )
	&& false !== strpos( (string) ( $probe_signed_request['url'] ?? '' ), '/v1/entitlements/current' ),
	'Behavior: connectivity verification runs liveness plus signed entitlement read and exposes a bounded ready result for cache reuse.'
);

maca_reset_test_state();
$not_configured_client = new Npcink_Cloud_Runtime_Client( array() );
$not_configured = $not_configured_client->manual_readiness_test();
$not_configured_support_facts = is_array( $not_configured['copyable_support_facts'] ?? null ) ? $not_configured['copyable_support_facts'] : array();
$not_configured_groups = is_array( $not_configured['diagnostic_panel_groups'] ?? null ) ? array_column( $not_configured['diagnostic_panel_groups'], null, 'diagnostic_panel_group' ) : array();

maca_assert(
	'cloud_addon_readiness_result.v1' === (string) ( $not_configured['contract_version'] ?? '' )
	&& 'not_configured' === (string) ( $not_configured['status'] ?? '' )
	&& 'not_configured' === (string) ( $not_configured['bounded_status'] ?? '' )
	&& 'operator' === (string) ( $not_configured['owner_label'] ?? '' )
	&& 'open_settings' === (string) ( $not_configured['next_safe_action'] ?? '' )
	&& 'read_only' === (string) ( $not_configured['write_posture'] ?? '' )
	&& 'npcink_cloud_runtime' === (string) ( $not_configured['connector_slot'] ?? '' )
	&& 'not_configured' === (string) ( $not_configured['connector_diagnostic_category'] ?? '' )
	&& 'not_configured' === (string) ( $not_configured['credential_slot_readiness'] ?? '' )
	&& 'not_configured' === (string) ( $not_configured['service_liveness_status'] ?? '' )
	&& 'not_configured' === (string) ( $not_configured['signed_transport_status'] ?? '' )
	&& 'not_configured' === (string) ( $not_configured_support_facts['connector_diagnostic_category'] ?? '' )
	&& 'no' === (string) ( $not_configured_support_facts['base_url_present'] ?? '' )
	&& 'no' === (string) ( $not_configured_support_facts['signing_secret_slot_present'] ?? '' )
	&& 'inactive' === (string) ( $not_configured_groups['local_configuration']['severity'] ?? '' )
	&& 'open_settings' === (string) ( $not_configured_groups['signed_transport']['next_safe_action'] ?? '' )
	&& '' !== (string) ( $not_configured_groups['signed_transport']['blocked_reason'] ?? '' )
	&& 0 === count( $GLOBALS['maca_http_requests'] ),
	'Behavior: manual readiness test returns a bounded not_configured result without a signed Cloud request.'
);

maca_reset_test_state();
$partial_client = new Npcink_Cloud_Runtime_Client(
	array(
		'base_url' => 'https://cloud.example.test',
	)
);
$partial = $partial_client->manual_readiness_test();
$partial_support_facts = is_array( $partial['copyable_support_facts'] ?? null ) ? $partial['copyable_support_facts'] : array();
$partial_groups = is_array( $partial['diagnostic_panel_groups'] ?? null ) ? array_column( $partial['diagnostic_panel_groups'], null, 'diagnostic_panel_group' ) : array();

maca_assert(
	'not_configured' === (string) ( $partial['status'] ?? '' )
	&& 'operator' === (string) ( $partial['owner_label'] ?? '' )
	&& 'credential_missing' === (string) ( $partial['connector_diagnostic_category'] ?? '' )
	&& 'partial' === (string) ( $partial['credential_slot_readiness'] ?? '' )
	&& 'ready' === (string) ( $partial['service_liveness_status'] ?? '' )
	&& 'not_configured' === (string) ( $partial['signed_transport_status'] ?? '' )
	&& 'credential_missing' === (string) ( $partial_support_facts['connector_diagnostic_category'] ?? '' )
	&& 'yes' === (string) ( $partial_support_facts['base_url_present'] ?? '' )
	&& 'no' === (string) ( $partial_support_facts['signing_credentials_complete'] ?? '' )
	&& 'not_configured' === (string) ( $partial_groups['local_configuration']['bounded_status'] ?? '' )
	&& 'ready' === (string) ( $partial_groups['cloud_connectivity']['bounded_status'] ?? '' )
	&& 1 === count( $GLOBALS['maca_http_requests'] ),
	'Behavior: manual readiness test classifies partial connector credentials as credential_missing without a signed Cloud request.'
);

maca_reset_test_state();
maca_seed_settings( true );
$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 403 ),
	'body'     => wp_json_encode(
		array(
			'status' => 'error',
			'message' => 'Signed read rejected for Bearer secret_test and mak1_sensitive.',
		)
	),
);

$failed_client = new Npcink_Cloud_Runtime_Client( Npcink_Cloud_Addon_Settings::get_settings() );
$failed = $failed_client->manual_readiness_test();
$failed_json = wp_json_encode( $failed );
$failed_support_facts = is_array( $failed['copyable_support_facts'] ?? null ) ? $failed['copyable_support_facts'] : array();
$failed_groups = is_array( $failed['diagnostic_panel_groups'] ?? null ) ? array_column( $failed['diagnostic_panel_groups'], null, 'diagnostic_panel_group' ) : array();
$failed_live_request = $GLOBALS['maca_http_requests'][0] ?? array();
$failed_signed_request = $GLOBALS['maca_http_requests'][1] ?? array();

maca_assert(
	'failed' === (string) ( $failed['status'] ?? '' )
	&& 'failed' === (string) ( $failed['bounded_status'] ?? '' )
	&& 'cloud' === (string) ( $failed['owner_label'] ?? '' )
	&& 'retry_test' === (string) ( $failed['next_safe_action'] ?? '' )
	&& 'read_only' === (string) ( $failed['write_posture'] ?? '' )
	&& 'npcink_cloud_runtime' === (string) ( $failed['connector_slot'] ?? '' )
	&& 'signed_transport_failed' === (string) ( $failed['connector_diagnostic_category'] ?? '' )
	&& 'ready' === (string) ( $failed['credential_slot_readiness'] ?? '' )
	&& 'ready' === (string) ( $failed['service_liveness_status'] ?? '' )
	&& 'failed' === (string) ( $failed['signed_transport_status'] ?? '' )
	&& 'npcink_cloud_runtime' === (string) ( $failed_support_facts['connector_slot'] ?? '' )
	&& 'signed_transport_failed' === (string) ( $failed_support_facts['connector_diagnostic_category'] ?? '' )
	&& 'failed' === (string) ( $failed_support_facts['signed_transport_status'] ?? '' )
	&& 'yes' === (string) ( $failed_support_facts['site_id_present'] ?? '' )
	&& 'yes' === (string) ( $failed_support_facts['key_id_present'] ?? '' )
	&& 'yes' === (string) ( $failed_support_facts['signing_secret_slot_present'] ?? '' )
	&& 'yes' === (string) ( $failed_support_facts['signing_credentials_complete'] ?? '' )
	&& 'error' === (string) ( $failed_groups['signed_transport']['severity'] ?? '' )
	&& 'retry_test' === (string) ( $failed_groups['entitlement_readiness']['next_safe_action'] ?? '' )
	&& false === strpos( (string) $failed_json, 'secret_test' )
	&& false === strpos( (string) $failed_json, 'mak1_sensitive' )
	&& false === strpos( (string) $failed_json, 'Bearer secret' )
	&& 2 === count( $GLOBALS['maca_http_requests'] )
	&& false !== strpos( (string) ( $failed_live_request['url'] ?? '' ), '/health/live' )
	&& false !== strpos( (string) ( $failed_signed_request['url'] ?? '' ), '/v1/entitlements/current' ),
	'Behavior: manual readiness test explicitly runs liveness plus signed entitlement read and returns failed support facts without exposing secrets.'
);

maca_reset_test_state();
maca_seed_settings( false );
$settings = Npcink_Cloud_Addon_Settings::get_settings();
$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body'     => wp_json_encode( $entitlement_response ),
);

$method = new ReflectionMethod( Npcink_Cloud_Settings_Page::class, 'persist_and_verify_settings' );
$method->setAccessible( true );
$method->invoke( null, $settings, 'Verified.' );
$post_verify_summary = Npcink_Cloud_Entitlement_Summary::get_cached_summary();

maca_assert(
	Npcink_Cloud_Addon_Settings::is_verified()
	&& 2 === count( $GLOBALS['maca_http_requests'] )
	&& ! empty( $post_verify_summary['available'] )
	&& 'Pro' === (string) ( $post_verify_summary['package_label'] ?? '' ),
	'Behavior: re-verify and refresh reuses the verification entitlement response without an extra signed Cloud read.'
);
