<?php
/**
 * Behavior tests for the AI plugin localization audit command.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

$fixture_root = sys_get_temp_dir() . '/npcink-cloud-addon-ai-i18n-audit-' . getmypid();
if ( is_dir( $fixture_root ) ) {
	exec( 'rm -rf ' . escapeshellarg( $fixture_root ) );
}
mkdir( $fixture_root . '/includes', 0777, true );
mkdir( $fixture_root . '/includes/Abilities/Demo', 0777, true );
mkdir( $fixture_root . '/build-scripts/admin', 0777, true );
mkdir( $fixture_root . '/build-scripts/features', 0777, true );

file_put_contents(
	$fixture_root . '/includes/settings.php',
	<<<'PHP'
<?php
esc_html__( 'Generate Image', 'ai' );
esc_html__( 'New Audit Button', 'ai' );
esc_html__( 'Ignore Default Domain', 'default' );
_n( 'One audit result', 'Many audit results', $count, 'ai' );
PHP
);

file_put_contents(
	$fixture_root . '/includes/Abilities/Demo/Demo.php',
	<<<'PHP'
<?php
esc_html__( 'Demo ability label', 'ai' );
esc_html__( 'The ID of the demo object.', 'ai' );
PHP
);

file_put_contents(
	$fixture_root . '/build-scripts/admin/page.js',
	<<<'JS'
(0,wp.i18n.__)("JS Audit Label","ai");
(0,wp.i18n.__)("Unicode ellipsis\u2026","ai");
(0,wp.i18n.__)("\u2014 Unicode default \u2014","ai");
(0,wp.i18n.__)("Ignored JS Label","default");
JS
);

file_put_contents(
	$fixture_root . '/build-scripts/features/image-generation.js',
	<<<'JS'
(0,wp.i18n.__)("Outpaint the image to create a wider panoramic view. Expand the scene outward in all directions to fill the empty transparent border while preserving the original style, lighting, colors, and perspective. Continue textures, structures, and environmental elements naturally so the extension blends with the original image.","ai");
JS
);

$command = 'AI_PLUGIN_PATH=' . escapeshellarg( $fixture_root ) . ' php ' . escapeshellarg( MACA_TEST_ROOT . '/scripts/audit-ai-plugin-localization.php' ) . ' 2>&1';
$output  = array();
$status  = 0;
exec( $command, $output, $status );
$report = implode( "\n", $output );

maca_assert(
	0 === $status
	&& false !== strpos( $report, 'WordPress AI plugin localization audit' )
	&& false !== strpos( $report, 'Missing strings' )
	&& false !== strpos( $report, 'Fixed UI review candidates' )
	&& false !== strpos( $report, 'Missing review groups' )
	&& false !== strpos( $report, 'fixed_ui_candidates:' )
	&& false !== strpos( $report, 'dynamic_ability_metadata:' )
	&& false !== strpos( $report, 'schema_or_json_fields:' )
	&& false !== strpos( $report, 'long_prompt_copy:' )
	&& false !== strpos( $report, 'stale_review:' )
	&& false !== strpos( $report, '"New Audit Button"' )
	&& false !== strpos( $report, '"One audit result"' )
	&& false !== strpos( $report, '"Many audit results"' )
	&& false !== strpos( $report, '"JS Audit Label"' )
	&& false !== strpos( $report, '"Demo ability label"' )
	&& false !== strpos( $report, '"The ID of the demo object."' )
	&& false !== strpos( $report, '"Outpaint the image to create a wider panoramic view.' )
	&& false !== strpos( $report, '"Unicode ellipsis…"' )
	&& false !== strpos( $report, '"— Unicode default —"' )
	&& false === strpos( $report, 'Unicode ellipsisu2026' )
	&& false === strpos( $report, 'u2014 Unicode default u2014' )
	&& false === strpos( $report, 'Ignore Default Domain' )
	&& false === strpos( $report, 'Ignored JS Label' ),
	'AI plugin localization audit reports missing ai-domain strings and ignores other domains.'
);

exec( 'rm -rf ' . escapeshellarg( $fixture_root ) );
