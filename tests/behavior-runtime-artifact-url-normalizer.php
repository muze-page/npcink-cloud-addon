<?php
/**
 * Behavior tests for runtime artifact URL normalization.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

maca_load_addon_classes();

$base_url = 'https://cloud.example.test/';
$valid_paths = array(
	'/v1/runtime/artifacts/artifact_1/download',
	'/v1/runtime/artifacts/artifact.2/download?token=token_2.~',
	'/v1/runtime/artifacts/artifact:3/public-download',
	'/v1/runtime/artifacts/artifact-4/public-download?token=token-4_~.',
);
foreach ( $valid_paths as $path ) {
	maca_assert(
		'https://cloud.example.test' . $path === Npcink_Cloud_Runtime_Artifact_Url_Normalizer::normalize( $path, $base_url ),
		'Behavior: one exact runtime artifact path is normalized to the Cloud base URL.'
	);
}

$unchanged_values = array(
	'https://cloud.example.test/v1/runtime/artifacts/artifact_1/download',
	'/v1/runtime/artifacts//download',
	'/v1/runtime/artifacts/artifact_1/preview',
	'/v1/runtime/artifacts/artifact_1/download?token=',
	'/v1/runtime/artifacts/artifact_1/download?token=bad%20token',
	'/v1/runtime/artifacts/artifact_1/download?token=bad/token',
	'/v1/runtime/artifacts/artifact_1/download?token=token&extra=1',
	'/v1/runtime/artifacts/artifact_1/download?other=token',
	'/v1/runtime/artifacts/artifact_1/download#preview',
	'not-an-artifact-url',
);
foreach ( $unchanged_values as $value ) {
	maca_assert(
		$value === Npcink_Cloud_Runtime_Artifact_Url_Normalizer::normalize( $value, $base_url ),
		'Behavior: a non-contract artifact value remains unchanged.'
	);
}

$relative_path = '/v1/runtime/artifacts/artifact_5/download';
maca_assert(
	$relative_path === Npcink_Cloud_Runtime_Artifact_Url_Normalizer::normalize( $relative_path, '' ),
	'Behavior: an empty Cloud base URL leaves an artifact path relative.'
);

maca_assert(
	array() === Npcink_Cloud_Runtime_Artifact_Url_Normalizer::normalize( array(), $base_url ),
	'Behavior: an empty decoded array remains empty.'
);

$object_value = new stdClass();
$object_value->url = '/v1/runtime/artifacts/object_artifact/download';
maca_assert(
	$object_value === Npcink_Cloud_Runtime_Artifact_Url_Normalizer::normalize( $object_value, $base_url ),
	'Behavior: an object remains the same instance and is not traversed.'
);

$nested = array(
	'data' => array(
		'url'   => '/v1/runtime/artifacts/artifact_6/download',
		'items' => array(
			array(
				'download_url' => '/v1/runtime/artifacts/artifact_7/public-download?token=token_7',
				'label'        => 'Reviewed artifact',
			),
		),
	),
	'count' => 1,
	'ready' => true,
	'empty' => null,
);
$normalized_nested = Npcink_Cloud_Runtime_Artifact_Url_Normalizer::normalize( $nested, $base_url );
maca_assert(
	is_array( $normalized_nested )
	&& array_keys( $nested ) === array_keys( $normalized_nested )
	&& 'https://cloud.example.test/v1/runtime/artifacts/artifact_6/download' === (string) ( $normalized_nested['data']['url'] ?? '' )
	&& 'https://cloud.example.test/v1/runtime/artifacts/artifact_7/public-download?token=token_7' === (string) ( $normalized_nested['data']['items'][0]['download_url'] ?? '' )
	&& 'Reviewed artifact' === (string) ( $normalized_nested['data']['items'][0]['label'] ?? '' )
	&& 1 === (int) ( $normalized_nested['count'] ?? 0 )
	&& true === (bool) ( $normalized_nested['ready'] ?? false )
	&& array_key_exists( 'empty', $normalized_nested )
	&& null === $normalized_nested['empty'],
	'Behavior: nested runtime values preserve structure and non-URL values while artifact paths normalize recursively.'
);
