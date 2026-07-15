<?php
/**
 * Aggregate test runner for Npcink Cloud Addon.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

$performance_guard_command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/behavior-performance-guards.php' );
passthru( $performance_guard_command, $performance_guard_status );
if ( 0 !== $performance_guard_status ) {
	exit( $performance_guard_status );
}

require __DIR__ . '/static-contracts.php';
require __DIR__ . '/behavior-credential-store.php';
require __DIR__ . '/behavior-outbound-policy.php';
require __DIR__ . '/behavior-cloud-addon-localization.php';
require __DIR__ . '/behavior-wordpress-ai-connector-result.php';
require __DIR__ . '/behavior-wordpress-ai-connector-registration.php';
require __DIR__ . '/behavior-wordpress-ai-request-log-bridge.php';
require __DIR__ . '/behavior-ai-plugin-localization.php';
require __DIR__ . '/behavior-ai-plugin-localization-audit.php';
require __DIR__ . '/behavior-entitlement-summary.php';
require __DIR__ . '/behavior-ai-task-contract.php';
require __DIR__ . '/behavior-wordpress-ai-connector-runtime.php';
require __DIR__ . '/behavior-media-derivative.php';
require __DIR__ . '/behavior-image-context-evidence.php';
require __DIR__ . '/behavior-agent-feedback.php';
require __DIR__ . '/behavior-observability-collector.php';
require __DIR__ . '/behavior-site-knowledge-change-bridge.php';
require __DIR__ . '/behavior-site-knowledge-runtime-bridge.php';
