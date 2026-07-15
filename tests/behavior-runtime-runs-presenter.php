<?php
/**
 * Behavior contracts for the Runtime Runs read-only presenter.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);
require_once __DIR__ . '/helpers.php';
maca_load_addon_classes();

$source_run = array( 'run_id' => 'run_<b>safe</b>/bad:1', 'status' => 'queued', 'result_status' => 'success', 'updated_at' => '2026-07-15 01:02:03 UTC' );
$envelopes = array(
	'data.runs' => array( 'data' => array( 'runs' => array( $source_run ) ) ),
	'data.items' => array( 'data' => array( 'runs' => 'invalid', 'items' => array( $source_run ) ) ),
	'runs' => array( 'runs' => array( $source_run ) ),
	'items' => array( 'items' => array( $source_run ) ),
);
foreach ( $envelopes as $name => $envelope ) {
	$rows = Npcink_Cloud_Runtime_Runs_Presenter::recent_rows( $envelope );
	maca_assert(
		1 === count( $rows )
		&& 'run_safebad:1' === (string) ( $rows[0]['run_id'] ?? '' )
		&& 'Queued' === (string) ( $rows[0]['status_label'] ?? '' )
		&& 'Succeeded' === (string) ( $rows[0]['result_status_label'] ?? '' )
		&& '2026-07-15 01:02:03 UTC' === (string) ( $rows[0]['updated_at'] ?? '' ),
		'Behavior: Runtime Runs presenter normalizes the ' . $name . ' response envelope.'
	);
}
$limited_rows = Npcink_Cloud_Runtime_Runs_Presenter::recent_rows( array( 'runs' => array_fill( 0, 6, $source_run ) ) );
maca_assert(
	5 === count( $limited_rows ),
	'Behavior: Runtime Runs presenter projects at most the five rows shown by the settings page.'
);

$filtered_rows = Npcink_Cloud_Runtime_Runs_Presenter::recent_rows(
	array(
		'runs' => array( 'invalid', array( 'id' => 'run_2', 'state' => true, 'result' => false, 'created_at' => 123 ), 42, array( 'run_id' => 'run_3', 'status' => 'custom_state' ) ),
	)
);
maca_assert(
	2 === count( $filtered_rows )
	&& 'yes' === (string) ( $filtered_rows[0]['status_label'] ?? '' )
	&& 'no' === (string) ( $filtered_rows[0]['result_status_label'] ?? '' )
	&& '123' === (string) ( $filtered_rows[0]['updated_at'] ?? '' )
	&& 'custom_state' === (string) ( $filtered_rows[1]['status_label'] ?? '' ),
	'Behavior: Runtime Runs presenter filters non-array entries and preserves boolean, scalar, and unknown status detail.'
);

$detail = Npcink_Cloud_Runtime_Runs_Presenter::detail(
	array(
		'data' => array(
			'run_id' => 'run_<i>detail</i>/bad:2', 'status' => 'running', 'error_code' => 418,
			'run_lifecycle' => array( 'processing_started_at' => '2026-07-15 02:03:04 UTC' ),
			'completed_at' => '2026-07-15 03:04:05 UTC',
		),
		'result' => array( 'status' => true ),
	)
);
maca_assert(
	'run_detailbad:2' === (string) ( $detail['run_id'] ?? '' )
	&& 'Running' === (string) ( $detail['status_label'] ?? '' )
	&& 'yes' === (string) ( $detail['result_status_label'] ?? '' )
	&& '418' === (string) ( $detail['error_code'] ?? '' )
	&& '2026-07-15 02:03:04 UTC' === (string) ( $detail['started_at'] ?? '' )
	&& '2026-07-15 03:04:05 UTC' === (string) ( $detail['finished_at'] ?? '' ),
	'Behavior: Runtime Runs presenter resolves nested detail paths without owning transport or rendering.'
);

$empty_rows = Npcink_Cloud_Runtime_Runs_Presenter::recent_rows( array( 'runs' => array( array() ) ) );
$empty_detail = Npcink_Cloud_Runtime_Runs_Presenter::detail( array() );
maca_assert(
	1 === count( $empty_rows )
	&& '' === (string) ( $empty_rows[0]['run_id'] ?? '' )
	&& 'unavailable' === (string) ( $empty_rows[0]['status_label'] ?? '' )
	&& 'unavailable' === (string) ( $empty_rows[0]['result_status_label'] ?? '' )
	&& '' === (string) ( $empty_rows[0]['updated_at'] ?? '' )
	&& '' === (string) ( $empty_detail['run_id'] ?? '' )
	&& 'unavailable' === (string) ( $empty_detail['status_label'] ?? '' )
	&& 'unavailable' === (string) ( $empty_detail['result_status_label'] ?? '' )
	&& '' === (string) ( $empty_detail['error_code'] ?? '' )
	&& '' === (string) ( $empty_detail['started_at'] ?? '' )
	&& '' === (string) ( $empty_detail['finished_at'] ?? '' ),
	'Behavior: Runtime Runs presenter keeps the empty row and detail contract deterministic.'
);

maca_assert(
	'run_evil:ok' === Npcink_Cloud_Runtime_Runs_Presenter::normalize_run_id( " run_<script>evil</script>/?:ok\n" ),
	'Behavior: Runtime Runs presenter strips markup, control characters, and unsupported run-id characters.'
);
