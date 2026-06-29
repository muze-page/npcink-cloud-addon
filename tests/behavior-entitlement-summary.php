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

maca_reset_test_state();
maca_seed_settings( true );
$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body'     => wp_json_encode( $entitlement_response ),
);

$client = new Npcink_Cloud_Runtime_Client( Npcink_Cloud_Addon_Settings::get_settings() );
$probe  = $client->probe_connectivity();

maca_assert(
	! empty( $probe['ok'] )
	&& is_array( $probe['entitlement_response'] ?? null )
	&& 'Pro' === (string) ( $probe['entitlement_response']['data']['package'] ?? '' )
	&& 1 === count( $GLOBALS['maca_http_requests'] ),
	'Behavior: connectivity verification exposes the signed entitlement response for cache reuse.'
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
	&& 1 === count( $GLOBALS['maca_http_requests'] )
	&& ! empty( $post_verify_summary['available'] )
	&& 'Pro' === (string) ( $post_verify_summary['package_label'] ?? '' ),
	'Behavior: re-verify and refresh reuses the verification entitlement response without a second Cloud read.'
);
