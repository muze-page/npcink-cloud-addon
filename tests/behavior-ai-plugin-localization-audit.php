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
mkdir( $fixture_root . '/build-scripts/admin', 0777, true );

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
	$fixture_root . '/build-scripts/admin/page.js',
	<<<'JS'
(0,wp.i18n.__)("JS Audit Label","ai");
(0,wp.i18n.__)("Ignored JS Label","default");
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
	&& false !== strpos( $report, 'Missing fixed UI candidates' )
	&& false !== strpos( $report, '"New Audit Button"' )
	&& false !== strpos( $report, '"One audit result"' )
	&& false !== strpos( $report, '"Many audit results"' )
	&& false !== strpos( $report, '"JS Audit Label"' )
	&& false === strpos( $report, 'Ignore Default Domain' )
	&& false === strpos( $report, 'Ignored JS Label' ),
	'AI plugin localization audit reports missing ai-domain strings and ignores other domains.'
);

exec( 'rm -rf ' . escapeshellarg( $fixture_root ) );
