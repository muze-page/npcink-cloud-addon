<?php
/**
 * Behavior tests for the shared Cloud outbound request policy.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

maca_reset_test_state();
$GLOBALS['maca_wp_environment_type'] = 'production';

foreach (
	array(
		'http://cloud.example.test',
		'https://user:pass@cloud.example.test',
		'https://cloud.example.test/base',
		'https://cloud.example.test?token=secret',
		'https://cloud.example.test#fragment',
		'https://10.0.0.8',
		'https://169.254.169.254',
		'https://100.64.0.1',
		'https://198.18.0.1',
		'https://203.0.113.1',
		'https://[2001:db8::1]',
		'http://127.0.0.1:8010',
		'http://[::1]:8010',
	) as $invalid_base_url
) {
	maca_assert(
		'' === Npcink_Cloud_Outbound_Policy::normalize_base_url( $invalid_base_url ),
		'Behavior: outbound policy rejects unsafe Cloud base URL shape: ' . $invalid_base_url
	);
}

$GLOBALS['maca_wp_environment_type'] = 'local';
maca_assert(
	'http://127.0.0.1:8010' === Npcink_Cloud_Outbound_Policy::normalize_base_url( 'http://127.0.0.1:8010/' ),
	'Behavior: exact loopback HTTP is allowed only in an explicit local WordPress environment.'
);
maca_assert(
	'http://[::1]:8010' === Npcink_Cloud_Outbound_Policy::normalize_base_url( 'http://[::1]:8010/' ),
	'Behavior: bracketed IPv6 loopback is normalized as an exact local-development target.'
);
maca_assert(
	'' === Npcink_Cloud_Outbound_Policy::normalize_base_url( 'https://cloud.npc.ink/' ),
	'Behavior: local WordPress rejects the canonical public Cloud Base URL.'
);

$local_public_request = Npcink_Cloud_Outbound_Policy::request_json(
	'https://cloud.npc.ink/health/live',
	array( 'method' => 'GET' )
);
maca_assert(
	is_wp_error( $local_public_request )
	&& 'cloud_outbound_target_not_allowed' === $local_public_request->get_error_code()
	&& 0 === count( $GLOBALS['maca_http_requests'] ),
	'Behavior: local WordPress rejects public Cloud before outbound HTTP dispatch.'
);

$GLOBALS['maca_wp_environment_type'] = 'production';
maca_assert(
	'https://cloud.npc.ink' === Npcink_Cloud_Outbound_Policy::normalize_base_url( 'https://cloud.npc.ink/' ),
	'Behavior: production WordPress retains the canonical public Cloud Base URL.'
);

foreach (
	array(
		'https://169.254.169.254/latest/meta-data',
		'https://100.64.0.1/v1/status',
		'https://198.18.0.1/v1/status',
		'https://203.0.113.1/v1/status',
		'https://[2001:db8::1]/v1/status',
	) as $non_public_target_url
) {
	maca_reset_test_state();
	$GLOBALS['maca_wp_environment_type'] = 'production';
	$private_target = Npcink_Cloud_Outbound_Policy::request_json(
		$non_public_target_url,
		array( 'method' => 'GET' )
	);
	maca_assert(
		is_wp_error( $private_target )
		&& 'cloud_outbound_target_not_allowed' === $private_target->get_error_code()
		&& 0 === count( $GLOBALS['maca_http_requests'] ),
		'Behavior: metadata and non-public IP targets are rejected before HTTP dispatch: ' . $non_public_target_url
	);
}

maca_reset_test_state();
$GLOBALS['maca_wp_environment_type'] = 'local';
add_filter(
	'npcink_cloud_addon_resolved_host_ips',
	static function ( $ips, string $host ) {
		return 'private.example.invalid' === $host ? array( '10.0.0.8' ) : $ips;
	},
	20,
	2
);
$private_dns_target = Npcink_Cloud_Outbound_Policy::request_json(
	'https://private.example.invalid/v1/status',
	array( 'method' => 'GET' )
);
maca_assert(
	is_wp_error( $private_dns_target )
	&& 'cloud_outbound_target_not_allowed' === $private_dns_target->get_error_code()
	&& 0 === count( $GLOBALS['maca_http_requests'] ),
	'Behavior: hostnames resolving to private addresses are rejected before HTTP dispatch.'
);

maca_reset_test_state();
$GLOBALS['maca_wp_environment_type'] = 'local';
$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'headers'  => array( 'Content-Type' => 'text/html; charset=utf-8' ),
	'body'     => '{"status":"ok"}',
);
$wrong_type = Npcink_Cloud_Outbound_Policy::request_json(
	'https://cloud.example.test/v1/status',
	array(
		'method'      => 'GET',
		'redirection' => 7,
		'sslverify'   => false,
	)
);
$guarded_args = $GLOBALS['maca_http_requests'][0]['args'] ?? array();
maca_assert(
	is_wp_error( $wrong_type )
	&& 'cloud_outbound_response_type_invalid' === $wrong_type->get_error_code(),
	'Behavior: JSON Cloud calls reject non-JSON response types.'
);
maca_assert(
	0 === (int) ( $guarded_args['redirection'] ?? -1 )
	&& true === ( $guarded_args['sslverify'] ?? null )
	&& true === ( $guarded_args['reject_unsafe_urls'] ?? null )
	&& ( Npcink_Cloud_Outbound_Policy::MAX_JSON_RESPONSE_BYTES + 1 ) === (int) ( $guarded_args['limit_response_size'] ?? 0 ),
	'Behavior: production Cloud calls force zero redirects, TLS verification, safe URLs, and a response cap.'
);

maca_reset_test_state();
$GLOBALS['maca_wp_environment_type']    = 'local';
$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'headers'  => array( 'Content-Type' => 'application/json' ),
	'body'     => str_repeat( 'x', 17 ),
);
$oversized_without_length = Npcink_Cloud_Outbound_Policy::request_json(
	'https://cloud.example.test/v1/status',
	array( 'method' => 'GET' ),
	16
);
$bounded_args = $GLOBALS['maca_http_requests'][0]['args'] ?? array();
maca_assert(
	is_wp_error( $oversized_without_length )
	&& 'cloud_outbound_response_too_large' === $oversized_without_length->get_error_code()
	&& 17 === (int) ( $bounded_args['limit_response_size'] ?? 0 ),
	'Behavior: a no-Content-Length response is read one byte past the accepted limit and rejected.'
);

maca_reset_test_state();
$GLOBALS['maca_wp_environment_type'] = 'local';
$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'headers'  => array( 'Content-Type' => 'application/problem+json; charset=utf-8' ),
	'body'     => '{"status":"ok"}',
);
$problem_json = Npcink_Cloud_Outbound_Policy::request_json(
	'https://cloud.example.test/v1/status',
	array( 'method' => 'GET' )
);
maca_assert(
	! is_wp_error( $problem_json ),
	'Behavior: structured application plus-json Cloud responses remain accepted.'
);
