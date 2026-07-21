<?php
/**
 * Behavior tests for stable Cloud failure projection into WordPress errors.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
maca_load_addon_classes();

/**
 * Invokes the Runtime Client response decoder with one deterministic failure.
 *
 * @param Npcink_Cloud_Runtime_Client $client Runtime client.
 * @param int                         $status HTTP status.
 * @param string                      $cloud_error_code Cloud error code.
 * @param string                      $message Safe Cloud error message.
 * @param array<string,mixed>         $extra_payload Additional untrusted Cloud fields.
 * @return WP_Error
 */
function maca_decode_wordpress_ai_failure(
	Npcink_Cloud_Runtime_Client $client,
	int $status,
	string $cloud_error_code,
	string $message,
	array $extra_payload = array()
): WP_Error {
	$decoder = new ReflectionMethod( Npcink_Cloud_Runtime_Client::class, 'decode_response' );
	$decoder->setAccessible( true );
	$result = $decoder->invoke(
		$client,
		array(
			'response' => array( 'code' => $status ),
			'headers'  => array( 'Content-Type' => 'application/json' ),
			'body'     => wp_json_encode(
				array_merge(
					array(
						'status'     => 'error',
						'error_code' => $cloud_error_code,
						'message'    => $message,
					),
					$extra_payload
				)
			),
		)
	);

	maca_assert( is_wp_error( $result ), 'Behavior: ' . $cloud_error_code . ' decodes to WP_Error.' );

	return $result;
}

$transport_secret = 'K9rXP5B3OpaqueValue7Qm2Lc8';
$bearer_token     = 'p5b3.sensitive.authorization';
$bearer_secret    = 'Bearer ' . $bearer_token;
$client           = new Npcink_Cloud_Runtime_Client(
	array(
		'base_url'     => 'https://cloud.example.test',
		'site_id'      => 'site_p5b3',
		'key_id'       => 'key_p5b3',
		'secret'       => $transport_secret,
		'authorization' => $bearer_secret,
	)
);

$cases = array(
	array(
		'cloud_error_code'  => 'auth.invalid_signature',
		'http_status'       => 401,
		'local_http_status' => 401,
		'local_error_code'  => 'cloud_auth_invalid_signature',
		'message'           => 'Cloud request signature is invalid.',
	),
	array(
		'cloud_error_code'  => 'commercial.entitlement_denied',
		'http_status'       => 403,
		'local_http_status' => 403,
		'local_error_code'  => 'cloud_commercial_entitlement_denied',
		'message'           => 'This site is not entitled to the requested operation.',
	),
	array(
		'cloud_error_code'  => 'provider.invalid_request',
		'http_status'       => 200,
		'local_http_status' => 502,
		'local_error_code'  => 'cloud_provider_invalid_request',
		'message'           => 'The provider rejected the bounded generation request.',
	),
	array(
		'cloud_error_code'  => 'runtime.idempotency_conflict',
		'http_status'       => 409,
		'local_http_status' => 409,
		'local_error_code'  => 'cloud_runtime_idempotency_conflict',
		'message'           => 'The idempotency key was already used for a different request.',
	),
);

foreach ( $cases as $case ) {
	$error = maca_decode_wordpress_ai_failure(
		$client,
		(int) $case['http_status'],
		(string) $case['cloud_error_code'],
		(string) $case['message'],
		array(
			'debug' => array(
				'authorization' => $bearer_secret,
				'api_key'       => $transport_secret,
			),
		)
	);
	$data  = $error->get_error_data();

	maca_assert(
		(string) $case['local_error_code'] === $error->get_error_code(),
		'Behavior: ' . $case['cloud_error_code'] . ' keeps its stable addon WP_Error code.'
	);
	maca_assert(
		is_array( $data )
		&& (string) $case['cloud_error_code'] === (string) ( $data['cloud_error_code'] ?? '' )
		&& (int) $case['local_http_status'] === (int) ( $data['status'] ?? 0 )
		&& (int) $case['http_status'] === (int) ( $data['cloud_http_status'] ?? -1 ),
		'Behavior: ' . $case['cloud_error_code'] . ' preserves upstream HTTP evidence without projecting a local success status.'
	);
	maca_assert(
		(string) $case['message'] === $error->get_error_message(),
		'Behavior: ' . $case['cloud_error_code'] . ' exposes only the bounded Cloud failure message.'
	);

	$serialized_projection = (string) wp_json_encode(
		array(
			'code'    => $error->get_error_code(),
			'message' => $error->get_error_message(),
			'data'    => $data,
		)
	);
	maca_assert(
		! str_contains( $serialized_projection, $transport_secret )
		&& ! str_contains( $serialized_projection, $bearer_secret )
		&& ! str_contains( $serialized_projection, $bearer_token )
		&& ! array_key_exists( 'cloud_payload', $data )
		&& 1 !== preg_match( '/["\'](?:authorization|api_key|secret|cookie|password)["\']\s*:/i', $serialized_projection ),
		'Behavior: ' . $case['cloud_error_code'] . ' projects only bounded status/code evidence, never raw Cloud payload or credentials.'
	);
}

$redacted = maca_decode_wordpress_ai_failure(
	$client,
	502,
	'provider.invalid_request',
	'Provider debug value ' . $transport_secret . ' rejected Authorization: ' . $bearer_secret . '.',
	array(
		'password' => 'p5b3-remote-password-must-not-project',
		'cookie'   => 'p5b3-remote-cookie-must-not-project',
	)
);
$redacted_projection = (string) wp_json_encode(
	array(
		'message' => $redacted->get_error_message(),
		'data'    => $redacted->get_error_data(),
	)
);
maca_assert(
	'Provider debug value [redacted] rejected [redacted]' === $redacted->get_error_message()
	&& ! str_contains( $redacted_projection, $transport_secret )
	&& ! str_contains( $redacted_projection, $bearer_secret )
	&& ! str_contains( $redacted_projection, $bearer_token )
	&& ! str_contains( $redacted_projection, 'p5b3-remote-password-must-not-project' )
	&& ! str_contains( $redacted_projection, 'p5b3-remote-cookie-must-not-project' ),
	'Behavior: untrusted Cloud error text and fields are redacted or omitted before WordPress projection.'
);

$additional_token = 'P5B3AdditionalTokenValue9vQ2';
$bounded = maca_decode_wordpress_ai_failure(
	$client,
	502,
	'provider.invalid_request',
	str_repeat( 'A', 5000 ) . ' access_token=' . $additional_token,
	array(
		'x_npcink_signature' => 'P5B3NestedSignatureMustNotProject',
	)
);
maca_assert(
	strlen( $bounded->get_error_message() ) <= 4100
	&& ! str_contains( $bounded->get_error_message(), $additional_token )
	&& ! str_contains( (string) wp_json_encode( $bounded->get_error_data() ), 'P5B3NestedSignatureMustNotProject' ),
	'Behavior: projected error messages are length-bounded and redact token/signature-shaped credentials.'
);

$plural_credential = 'P5B3PluralCredentialValue4Lx7';
$plural_redacted = maca_decode_wordpress_ai_failure(
	$client,
	502,
	'provider.invalid_request',
	'Remote credentials=' . $plural_credential . ' must not project.',
	array()
);
maca_assert(
	str_contains( $plural_redacted->get_error_message(), '[redacted]' )
	&& ! str_contains( $plural_redacted->get_error_message(), $plural_credential ),
	'Behavior: plural credential key names are redacted from untrusted Cloud messages.'
);

$invalid_code_decoder = new ReflectionMethod( Npcink_Cloud_Runtime_Client::class, 'decode_response' );
$invalid_code_decoder->setAccessible( true );
$invalid_code = $invalid_code_decoder->invoke(
	$client,
	array(
		'response' => array( 'code' => 502 ),
		'headers'  => array( 'Content-Type' => 'application/json' ),
		'body'     => wp_json_encode(
			array(
				'status'     => 'error',
				'error_code' => array( 'unexpected' => $transport_secret ),
				'message'    => 'Cloud returned an invalid structured error code.',
			)
		),
	)
);
maca_assert(
	is_wp_error( $invalid_code )
	&& 'cloud_runtime_failed' === $invalid_code->get_error_code()
	&& '' === (string) ( $invalid_code->get_error_data()['cloud_error_code'] ?? 'not-empty' ),
	'Behavior: non-string Cloud error codes fail closed to the generic bounded addon code without scalar casting.'
);
