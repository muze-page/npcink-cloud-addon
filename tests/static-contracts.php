<?php
/**
 * Static contract tests for Npcink Cloud Addon.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

$root = MACA_TEST_ROOT;
$bootstrap = maca_read( $root . '/includes/bootstrap.php' );
$transport = maca_read( $root . '/includes/class-cloud-media-derivative-transport.php' );
$runtime_client = maca_read( $root . '/includes/class-cloud-runtime-client.php' );
$entitlement_summary = maca_read( $root . '/includes/class-cloud-entitlement-summary.php' );
$observability = maca_read( $root . '/includes/class-cloud-observability-collector.php' );
$site_knowledge_bridge = maca_read( $root . '/includes/class-cloud-site-knowledge-change-bridge.php' );
$settings = maca_read( $root . '/includes/class-cloud-addon-settings.php' );
$settings_page = maca_read( $root . '/includes/class-cloud-settings-page.php' );
$boundary_doc = maca_read( $root . '/docs/cloud-addon-boundary.md' );
$runtime_contract = maca_read( $root . '/docs/cloud-runtime-client-contract.md' );
$adapter_doc = maca_read( $root . '/docs/adapter-integration-seam.md' );
$complexity_doc = maca_read( $root . '/docs/cloud-addon-complexity-budget.md' );
$cloud_bulk_article_doc = maca_read( $root . '/docs/cloud-bulk-article-run-seam.md' );
$admin_surface_standard = maca_read( $root . '/docs/admin-surface-standard.md' );
$agents = maca_read( $root . '/AGENTS.md' );
$readme = maca_read( $root . '/README.md' );
$composer = maca_read( $root . '/composer.json' );
$eval_lab_proxy = maca_read( $root . '/scripts/eval-lab.sh' );

maca_assert(
	false !== strpos( $composer, '"eval:project:quality": "sh scripts/eval-lab.sh task=project_quality_gate' )
	&& false !== strpos( $eval_lab_proxy, 'NPCINK_EVAL_LAB_PATH' )
	&& false !== strpos( $eval_lab_proxy, 'composer eval:task -- "$@"' ),
	'Cloud Addon exposes optional eval-lab project quality gate through the task registry.'
);
maca_assert(
	false === strpos( $composer, '@eval:lab' )
	&& false === strpos( $composer, '@eval:project:quality' )
	&& false !== strpos( $eval_lab_proxy, 'composer "$SCRIPT" -- "$@"' ),
	'Cloud Addon default tests stay independent from eval-lab and the wrapper keeps legacy Composer compatibility.'
);
maca_assert(
	false === strpos( $composer . "\n" . $eval_lab_proxy, 'sk-' ),
	'Cloud Addon eval-lab integration does not contain committed provider keys.'
);

maca_assert(
	false !== strpos( $settings_page, "private const PARENT_MENU_SLUG = 'npcink-ai';" ),
	'Settings page targets the shared Npcink AI parent menu slug.'
);

maca_assert(
	false !== strpos( $bootstrap, 'plugin_action_links_' )
	&& false !== strpos( $bootstrap, 'npcink_cloud_addon_filter_plugin_action_links' )
	&& false !== strpos( $bootstrap, 'admin.php?page=npcink-cloud-addon' ),
	'Plugin screen exposes a Settings shortcut to the Cloud Addon admin page.'
);

maca_assert(
	false !== strpos( maca_read( $root . '/npcink-cloud-addon.php' ), 'Text Domain:       npcink-cloud-addon' )
	&& false !== strpos( maca_read( $root . '/npcink-cloud-addon.php' ), 'Domain Path:       /languages' )
	&& false !== strpos( maca_read( $root . '/languages/npcink-cloud-addon.pot' ), 'X-Domain: npcink-cloud-addon' )
	&& false !== strpos( maca_read( $root . '/languages/npcink-cloud-addon-zh_CN.po' ), 'Language: zh_CN' )
	&& false === strpos( $bootstrap, 'load_plugin_textdomain' ),
	'Plugin declares the npcink-cloud-addon text domain and ships generated language files.'
);

maca_assert(
	false !== strpos( $bootstrap, 'class-cloud-media-derivative-transport.php' )
	&& false !== strpos( $bootstrap, 'npcink_cloud_addon_verified_runtime_client' )
	&& false !== strpos( $bootstrap, 'npcink_cloud_addon_dispatch_media_derivative_cloud_request' )
	&& false !== strpos( $bootstrap, 'npcink_cloud_addon_request_image_context_evidence' )
	&& false !== strpos( $bootstrap, 'array $watermark_artifact = array()' )
	&& false !== strpos( $bootstrap, 'npcink_cloud_addon_build_media_derivative_proposal_payload' )
	&& false !== strpos( $bootstrap, 'npcink_cloud_addon_get_media_derivative_run' )
	&& false !== strpos( $bootstrap, 'npcink_cloud_addon_get_media_derivative_run_result' )
	&& false !== strpos( $bootstrap, 'npcink_cloud_addon_public_media_derivative_cloud_projection' )
	&& false !== strpos( $bootstrap, 'npcink_cloud_addon_build_media_derivative_optimization_payload' )
	&& false !== strpos( $bootstrap, 'npcink_cloud_addon_download_media_derivative_artifact' ),
	'Bootstrap exposes verified runtime and media derivative transport helpers with optional watermark transport, projections, optimization payloads, and signed preview download.'
);

maca_assert(
	false !== strpos( $transport, 'Npcink_Cloud_Addon_Settings::is_verified()' )
	&& false !== strpos( $transport, "'cloud_runtime_unverified'" ),
	'Media derivative dispatch fails closed until Cloud credentials verify.'
);

maca_assert(
	false !== strpos( $runtime_client, 'function create_media_derivative' )
	&& false !== strpos( $runtime_client, "'/v1/runtime/media-derivatives'" )
	&& false !== strpos( $runtime_client, 'build_media_derivative_multipart_body' )
	&& false !== strpos( $runtime_client, "'source_file'" )
	&& false !== strpos( $runtime_client, "'watermark_file'" )
	&& false !== strpos( $runtime_client, "'cloud_runtime_media_derivative_file_field_not_allowed'" )
	&& false !== strpos( $runtime_client, 'Only source_file and watermark_file uploads are allowed' ),
	'Runtime client exposes a named media derivative endpoint with bounded multipart files.'
);

maca_assert(
	false !== strpos( $runtime_client, 'function request_image_context_evidence' )
	&& false !== strpos( $runtime_client, 'normalize_image_context_evidence_request' )
	&& false !== strpos( $runtime_client, 'normalize_image_context_evidence_response' )
	&& false !== strpos( $runtime_client, "'ability_name'        => 'npcink-cloud/image-context-evidence'" )
	&& false !== strpos( $runtime_client, "'profile_id'          => 'vision.ai'" )
	&& false !== strpos( $runtime_client, "'execution_kind'      => 'image_context_evidence'" )
	&& false !== strpos( $runtime_client, "'expected_response_contract' => 'image_context_evidence.v1'" )
	&& false !== strpos( $runtime_client, "'no_local_model'             => true" )
	&& false !== strpos( $runtime_client, "'no_media_write'             => true" ),
	'Runtime client exposes bounded image context evidence transport through the hosted runtime contract.'
);

maca_assert(
	false !== strpos( $readme, 'Image Context Evidence Transport' )
	&& false !== strpos( $readme, 'image_context_evidence_request.v1' )
	&& false !== strpos( $readme, 'image_context_evidence.v1' )
	&& false !== strpos( $readme, 'does not run a local vision model' )
	&& false !== strpos( $runtime_contract, 'request_image_context_evidence(array $image_context_evidence_request' )
	&& false !== strpos( $runtime_contract, 'bounded_media_urls_for_visual_context_only' )
	&& false !== strpos( $runtime_contract, 'not a local image recognition model' )
	&& false !== strpos( $boundary_doc, 'Image context evidence transport must use the existing' )
	&& false !== strpos( $boundary_doc, 'not add a local image recognition model' ),
	'Docs describe image context evidence as bounded runtime transport, not local vision or write ownership.'
);

maca_assert(
	false !== strpos( $runtime_client, 'function download_media_derivative_artifact' )
	&& false !== strpos( $runtime_client, "'/v1/runtime/artifacts/'" )
	&& false !== strpos( $runtime_client, "'/download'" )
	&& false !== strpos( $runtime_client, 'request_raw' )
	&& false !== strpos( $runtime_client, 'MAX_DOWNLOAD_BYTES = 26214400' )
	&& false !== strpos( $transport, 'download_artifact_preview' )
	&& false !== strpos( $transport, 'cloud_media_derivative_artifact_mime_mismatch' )
	&& false !== strpos( $transport, 'Derivative artifact checksum does not match the downloaded bytes.' ),
	'Runtime client and transport expose only a bounded signed media derivative artifact preview download.'
);

maca_assert(
	false !== strpos( $transport, 'create_media_derivative' )
	&& false !== strpos( $transport, 'get_run_projection' )
	&& false !== strpos( $transport, 'get_run_result_projection' )
	&& false !== strpos( $transport, 'public_cloud_projection' )
	&& false !== strpos( $transport, 'build_media_optimization_payload' )
	&& false !== strpos( $transport, 'build_media_derivative_request_payload' )
	&& false !== strpos( $transport, 'normalize_upload_file_descriptor' )
	&& false !== strpos( $transport, 'normalize_required_artifact_reference' ),
	'Media derivative transport shapes strict Cloud requests, projections, and Core-ready optimization payloads from ability contracts and host artifacts.'
);

maca_assert(
	false !== strpos( $transport, 'cloud_media_derivative_watermark_plan_missing' )
	&& false !== strpos( $transport, 'cloud_media_derivative_watermark_source_conflict' )
	&& false !== strpos( $transport, 'cloud_media_derivative_watermark_source_missing' )
	&& false !== strpos( $transport, 'cloud_media_derivative_source_mode_conflict' )
	&& false !== strpos( $transport, 'Watermark artifact transport requires a watermark plan' )
	&& false !== strpos( $transport, 'Watermark plans require a watermark upload or artifact id' )
	&& false !== strpos( $transport, 'Text watermark plans must not include a watermark upload or artifact id' )
	&& false !== strpos( $transport, "'type'       => 'text'" )
	&& false !== strpos( $transport, 'sanitize_crop_payload' )
	&& false !== strpos( $transport, "'crop'" )
	&& false !== strpos( $transport, 'sanitize_watermark_color' )
	&& false !== strpos( $transport, 'array $watermark_artifact = array()' )
	&& false !== strpos( $transport, 'sanitize_watermark_payload' )
	&& false !== strpos( $transport, "'watermark_file'" ),
	'Watermark transport requires a local ability plan and rejects missing or mixed source modes.'
);

maca_assert(
	false !== strpos( $transport, 'contains_forbidden_secret_fields' )
	&& false !== strpos( $transport, "'credentials'" )
	&& false !== strpos( $transport, "'authorization'" )
	&& false !== strpos( $transport, "'signed_headers'" )
	&& false !== strpos( $transport, "'x_magick_signature'" ),
	'Media derivative ability payload is checked for credentials, Authorization, and signed headers.'
);

maca_assert(
	false !== strpos( $transport, 'cloud_media_derivative_artifact_expired' )
	&& false !== strpos( $transport, 'cloud_media_derivative_derivative_artifact_id_missing' )
	&& false !== strpos( $transport, 'cloud_media_derivative_artifact_binding_mismatch' )
	&& false !== strpos( $transport, 'cloud_media_derivative_artifact_run_mismatch' )
	&& false !== strpos( $transport, 'cloud_media_derivative_artifact_checksum_mismatch' )
	&& false !== strpos( $transport, 'Expired Cloud artifacts cannot be adopted.' )
	&& false !== strpos( $transport, 'return $timestamp <= time();' ),
	'Expired, unbound, or mismatched Cloud artifacts are rejected before local adoption payloads are built.'
);

maca_assert(
	false !== strpos( $transport, 'cloud_media_derivative_replace_original_requested' )
	&& false !== strpos( $transport, "'replace_original_default'      => false" )
	&& false !== strpos( $transport, "'default_action'    => 'preview_only'" ),
	'Media derivative proposal payload defaults to preview-only and does not replace the original file.'
);

maca_assert(
	false !== strpos( $transport, "'final_write_owner' => 'local_wordpress_host'" )
	&& false !== strpos( $transport, "'wordpress_write_included'      => false" )
	&& false !== strpos( $transport, "'attachment_metadata_write_included' => false" ),
	'Media derivative proposal payload declares local WordPress host as final write owner.'
);

maca_assert(
	false === strpos( $transport, 'wp_insert_' . 'post' )
	&& false === strpos( $transport, 'wp_update_' . 'post' )
	&& false === strpos( $transport, 'update_attached_file' )
	&& false === strpos( $transport, 'wp_update_attachment_metadata' )
	&& false === strpos( $transport, 'update_post_meta' ),
	'Media derivative transport does not perform WordPress writes or attachment metadata updates.'
);

maca_assert(
	false !== strpos( $runtime_client, "'POST', '/v1/runtime/execute'" )
	&& false !== strpos( $runtime_client, "'POST', '/v1/runtime/media-derivatives'" )
	&& false !== strpos( $runtime_client, "'GET', '/v1/runs/'" )
	&& false !== strpos( $runtime_client, 'get_recent_nightly_inspection_runs' )
	&& false !== strpos( $runtime_client, "'GET', '/v1/runs/nightly-inspection/recent?limit='" )
	&& false !== strpos( $runtime_client, 'public function retry_run' )
	&& false !== strpos( $runtime_client, "rawurlencode( \$run_id ) . '/retry'" )
	&& false !== strpos( $runtime_client, 'private function request' )
	&& false !== strpos( $runtime_client, 'is_allowed_request_path' )
	&& false !== strpos( $runtime_client, "'cloud_runtime_endpoint_not_allowed'" )
	&& false !== strpos( $runtime_client, '#^/v1/runs/[A-Za-z0-9._:-]+(?:/result)?$#' )
	&& false !== strpos( $runtime_client, "'/v1/runs/nightly-inspection/recent' === \$path_only" )
	&& false !== strpos( $runtime_client, '#^/v1/runs/[A-Za-z0-9._:-]+/retry$#' )
	&& false !== strpos( $runtime_client, '#^/v1/runtime/artifacts/[A-Za-z0-9._:-]+/download$#' )
	&& false !== strpos( $runtime_client, '#^/v1/stats/(?:profiles|instances)/[A-Za-z0-9._:-]+$#' )
	&& false === strpos( $transport, '/v1/runtime/workflows/' . 'runs' )
	&& false === strpos( $transport, '/v1/artifacts' ),
	'Runtime client keeps Cloud calls on named allowlisted contract surfaces.'
);

maca_assert(
	false !== strpos( $entitlement_summary, 'normalize_pro_cloud_runtime' )
	&& false !== strpos( $entitlement_summary, "'pro_cloud_runtime'" )
	&& false !== strpos( $entitlement_summary, "'nightly_site_inspection_runs'" )
	&& false !== strpos( $entitlement_summary, "'local_billing_truth' => false" )
	&& false !== strpos( $settings_page, 'render_pro_cloud_runtime_summary' )
	&& false !== strpos( $settings_page, 'This addon does not own billing truth, scheduling, queues, or WordPress writes.' ),
	'Entitlement summary preserves Pro Cloud Runtime detail as a read-only projection without local billing or scheduler truth.'
);

maca_assert(
	false !== strpos( $entitlement_summary, 'normalize_credit_usage_detail' )
	&& false !== strpos( $entitlement_summary, "'local_addon_policy' => sanitize_key" )
	&& false !== strpos( $entitlement_summary, "'summary_and_link_only'" )
	&& false !== strpos( $entitlement_summary, "'credit_ledger_url'" )
	&& false !== strpos( $settings_page, 'render_credit_usage_summary' )
	&& false !== strpos( $settings_page, 'Summary-only Cloud credit projection' )
	&& false !== strpos( $settings_page, 'View credit details in Cloud' )
	&& false === strpos( $settings_page, "'recent_items'" ),
	'Cloud Addon displays only a summary-only AI credit projection and links detailed credit usage back to Cloud.'
);

maca_assert(
	false !== strpos( $cloud_bulk_article_doc, 'Rejected Product Language' )
	&& false !== strpos( $cloud_bulk_article_doc, 'Cloud writing assistant' )
	&& false !== strpos( $cloud_bulk_article_doc, 'Cloud article generator' )
	&& false !== strpos( $cloud_bulk_article_doc, 'hosted article drafting connector' )
	&& false !== strpos( $cloud_bulk_article_doc, 'Cloud connector, service detail, entitlement, health, and diagnostics language' ),
	'Cloud bulk article seam rejects writing-product language and keeps addon copy on service detail.'
);

maca_assert(
	false === strpos( $transport, 'media_derivative_cloud_runtime.v1' )
	&& false === strpos( $transport, 'build_runtime_payload' )
	&& false !== strpos( $transport, 'cloud_media_derivative_target_format_missing' )
	&& false !== strpos( $transport, 'cloud_media_derivative_max_width_missing' )
	&& false !== strpos( $transport, 'cloud_media_derivative_quality_missing' )
	&& false !== strpos( $transport, 'cloud_media_derivative_source_media_type_invalid' )
	&& false !== strpos( $transport, 'cloud_media_derivative_artifact_mime_invalid' )
	&& false !== strpos( $transport, 'Original media metrics are incomplete.' )
	&& false !== strpos( $transport, 'Derivative media metrics are incomplete.' )
	&& false !== strpos( $transport, 'filesize( $path )' )
	&& false !== strpos( $transport, 'MAX_UPLOAD_BYTES = 26214400' ),
	'Media derivative transport has no legacy execute payload builder, requires ability-provided derivative fields, validates media types, warns on incomplete metrics, and preflights upload size.'
);

maca_assert(
	false === strpos( $readme, 'request(string $method' )
	&& false === strpos( $runtime_contract, 'request(string $method' )
	&& false !== strpos( $runtime_contract, 'create_media_derivative(array $payload, array $files = array(), string $trace_id = \'\', string $idempotency_key = \'\')' )
	&& false !== strpos( $runtime_contract, 'download_media_derivative_artifact(string $artifact_id, string $trace_id = \'\')' )
	&& false !== strpos( $readme, 'low-level signed request method is private and endpoint-allowlisted' )
	&& false !== strpos( $runtime_contract, 'must enforce the endpoint allowlist' )
	&& false !== strpos( $runtime_contract, 'must not be exposed as a generic public Cloud proxy' ),
	'Raw Cloud request helper is no longer documented as a public generic endpoint proxy.'
);

maca_assert(
	false !== strpos( $settings, 'invalid_cloud_base_url' )
	&& false !== strpos( $settings, 'is_local_http_base_url' ),
	'Cloud Base URL normalization requires HTTPS except local development hosts.'
);

maca_assert(
	false !== strpos( $boundary_doc, 'POST /v1/runtime/media-derivatives' )
	&& false !== strpos( $boundary_doc, 'GET /v1/runtime/artifacts/{artifact_id}/download' )
	&& false !== strpos( $boundary_doc, 'logo registry' )
	&& false !== strpos( $boundary_doc, 'watermark plan' )
	&& false !== strpos( $adapter_doc, 'Expired Cloud artifacts must not be adopted.' )
	&& false !== strpos( $adapter_doc, 'final_write_owner=local_wordpress_host' )
	&& false !== strpos( $adapter_doc, 'watermark_artifact' )
	&& false !== strpos( $agents, 'POST /v1/runtime/media-derivatives' ),
	'Boundary, adapter integration, and AGENTS docs describe derivative transport ownership.'
);

maca_assert(
	false !== strpos( $runtime_client, 'function send_observability_events' )
	&& false !== strpos( $runtime_client, "'POST'" )
	&& false !== strpos( $runtime_client, "'/v1/observability/plugin-events'" )
	&& false !== strpos( $runtime_client, 'function send_agent_feedback_event' )
	&& false !== strpos( $runtime_client, "'/v1/agent-feedback/events'" )
	&& false !== strpos( $runtime_client, 'function get_agent_feedback_summary' )
	&& false !== strpos( $runtime_client, "'/v1/agent-feedback/summary?window_hours='" )
	&& false !== strpos( $runtime_client, 'function get_observability_summary' )
	&& false !== strpos( $runtime_client, "'GET'" )
	&& false !== strpos( $runtime_client, "'/v1/observability/plugin-summary?window_hours='" )
	&& false !== strpos( $runtime_contract, 'send_observability_events()' )
	&& false !== strpos( $runtime_contract, 'send_agent_feedback_event()' )
	&& false !== strpos( $runtime_contract, 'get_agent_feedback_summary()' )
	&& false !== strpos( $runtime_contract, 'get_observability_summary()' )
	&& false !== strpos( $boundary_doc, 'POST /v1/observability/plugin-events' )
	&& false !== strpos( $boundary_doc, 'POST /v1/agent-feedback/events' )
	&& false !== strpos( $boundary_doc, 'GET /v1/agent-feedback/summary' )
	&& false !== strpos( $boundary_doc, 'GET /v1/observability/plugin-summary' ),
	'Observability and Agent feedback transport endpoints are explicitly allowed by code and docs.'
);

maca_assert(
	false !== strpos( $observability, 'AGENT_SUMMARY_OPTION' )
	&& false !== strpos( $observability, 'refresh_agent_feedback_summary' )
	&& false !== strpos( $observability, 'get_agent_feedback_summary( 24' )
	&& false !== strpos( $observability, 'sanitize_agent_feedback_summary_payload' )
	&& false !== strpos( $settings_page, 'Monitoring & Quality' )
	&& false !== strpos( $settings_page, 'Refresh monitoring and quality' )
	&& false !== strpos( $settings_page, 'Agent quality events' )
	&& false !== strpos( $settings_page, 'Read-only Cloud eval summary. Approval, proposal, preflight, and WordPress writes remain local.' ),
	'Cloud Addon Monitoring owns the read-only Agent quality summary without adding write or control-plane authority.'
);

maca_assert(
	false !== strpos( $observability, 'BUFFER_OPTION' )
	&& false !== strpos( $observability, 'npcink_cloud_addon_observability_buffer' )
	&& false !== strpos( $observability, 'MAX_BUFFER_ITEMS = 200' )
	&& false !== strpos( $observability, 'MAX_BATCH_ITEMS = 50' )
	&& false === strpos( $observability, 'QUEUE_OPTION' )
	&& false === strpos( $observability, 'MAX_QUEUE_ITEMS' ),
	'Observability uses a bounded delivery buffer rather than queue ownership terms.'
);

maca_assert(
	false !== strpos( $observability, 'Npcink_Cloud_Addon_Settings::is_monitoring_enabled()' )
	&& false !== strpos( $observability, 'wp_schedule_event' )
	&& false !== strpos( $agents, 'Bounded observability buffering and WP-Cron flushing are allowed only' ),
	'Observability capture and flush are gated by verified opt-in monitoring.'
);

maca_assert(
	false !== strpos( $bootstrap, 'class-cloud-site-knowledge-change-bridge.php' )
	&& false !== strpos( $bootstrap, 'npcink_cloud_addon_site_knowledge_change_bridge_health' )
	&& false !== strpos( $bootstrap, 'Npcink_Cloud_Site_Knowledge_Change_Bridge::register()' )
	&& false !== strpos( $readme, 'npcink_cloud_addon_site_knowledge_change_bridge_health(): array' ),
	'Bootstrap exposes the Cloud Addon Site Knowledge change bridge health seam.'
);

maca_assert(
	false !== strpos( $site_knowledge_bridge, 'BUFFER_OPTION' )
	&& false !== strpos( $site_knowledge_bridge, 'npcink_cloud_addon_site_knowledge_change_buffer' )
	&& false !== strpos( $site_knowledge_bridge, 'MAX_BUFFER_ITEMS = 500' )
	&& false !== strpos( $site_knowledge_bridge, 'MAX_BATCH_ITEMS = 25' )
	&& false !== strpos( $site_knowledge_bridge, 'Npcink_Cloud_Addon_Settings::is_verified()' )
	&& false === strpos( $site_knowledge_bridge, 'QUEUE_OPTION' )
	&& false === strpos( $site_knowledge_bridge, 'MAX_QUEUE_ITEMS' ),
	'Site Knowledge change bridge uses bounded delivery buffer language instead of queue ownership terms and waits for verified Cloud settings.'
);

maca_assert(
	false !== strpos( $site_knowledge_bridge, "'status' => \$bridge_status" )
	&& false !== strpos( $site_knowledge_bridge, "'last_delivery_at'" )
	&& false !== strpos( $site_knowledge_bridge, "'last_success_at'" )
	&& false !== strpos( $site_knowledge_bridge, "'last_error_code'" )
	&& false !== strpos( $site_knowledge_bridge, "'legacy_toolbox_fallback' => false" ),
	'Site Knowledge change bridge exposes stable health fields for Toolbox without re-enabling the Toolbox legacy fallback.'
);

maca_assert(
	false !== strpos( $site_knowledge_bridge, 'transition_post_status' )
	&& false !== strpos( $site_knowledge_bridge, "add_action( 'save_post'" )
	&& false !== strpos( $site_knowledge_bridge, 'transition_comment_status' )
	&& false !== strpos( $site_knowledge_bridge, 'comment_post' )
	&& false !== strpos( $site_knowledge_bridge, 'edit_comment' )
	&& false !== strpos( $site_knowledge_bridge, 'trashed_comment' ),
	'Site Knowledge change bridge watches public post/page and approved comment changes.'
);

maca_assert(
	false !== strpos( $site_knowledge_bridge, 'request_site_knowledge_refresh' )
	&& false !== strpos( $site_knowledge_bridge, "'site_knowledge_sync.v1'" )
	&& false !== strpos( $site_knowledge_bridge, "'sync_mode' => 'refresh'" )
	&& false !== strpos( $site_knowledge_bridge, "'write_posture' => 'suggestion_only'" )
	&& false !== strpos( $site_knowledge_bridge, 'execute_runtime' )
	&& false !== strpos( $boundary_doc, 'Cloud remains the Site Knowledge vector' )
	&& false !== strpos( $boundary_doc, 'index, freshness, and collection lifecycle owner' ),
	'Site Knowledge change bridge forwards bounded public refresh hints to Cloud runtime only.'
);

maca_assert(
	false !== strpos( $site_knowledge_bridge, 'wp_schedule_single_event' )
	&& false !== strpos( $site_knowledge_bridge, 'wp_schedule_event' )
	&& false !== strpos( $site_knowledge_bridge, 'MAX_DELIVERY_ATTEMPTS' )
	&& false !== strpos( $site_knowledge_bridge, 'retry_or_drop_buffer' )
	&& false !== strpos( $agents, 'Bounded Site Knowledge change buffering and WP-Cron flushing are allowed only' ),
	'Site Knowledge change bridge has bounded delivery attempts and a low-frequency reconciliation safety net.'
);

maca_assert(
	false === strpos( $site_knowledge_bridge, 'wp_insert_' . 'post' )
	&& false === strpos( $site_knowledge_bridge, 'wp_update_' . 'post' )
	&& false === strpos( $site_knowledge_bridge, 'update_post_meta' )
	&& false === strpos( $site_knowledge_bridge, 'register_rest_route' )
	&& false === strpos( $site_knowledge_bridge, 'ActionScheduler' )
	&& false === strpos( $site_knowledge_bridge, 'as_enqueue' )
	&& false !== strpos( $site_knowledge_bridge, "'index_lifecycle_owner' => 'cloud_service'" ),
	'Site Knowledge change bridge does not introduce WordPress writes, REST control routes, Action Scheduler, or local index lifecycle ownership.'
);

maca_assert(
	false !== strpos( $observability, "'plugin_slug'" )
	&& false !== strpos( $observability, "'event_kind'" )
	&& false !== strpos( $observability, "'proposal_id'" )
	&& false !== strpos( $observability, "'correlation_id'" )
	&& false !== strpos( $observability, "'latency_ms'" )
	&& false === strpos( $observability, "'prompt'" )
	&& false === strpos( $observability, "'content'" )
	&& false === strpos( $observability, "'raw_request'" )
	&& false === strpos( $observability, "'raw_response'" )
	&& false === strpos( $observability, "'authorization'" )
	&& false === strpos( $observability, "'cookie'" )
	&& false === strpos( $observability, "'nonce'" )
	&& false === strpos( $observability, "'secret'" ),
	'Observability event normalization is metadata-only and excludes sensitive/raw payload fields.'
);

maca_assert(
	false !== strpos( $settings_page, 'Buffered events' )
	&& false !== strpos( $settings_page, 'buffer_count' )
	&& false !== strpos( $settings_page, "DATETIME_DISPLAY_FORMAT = 'Y-m-d H:i:s'" )
	&& false !== strpos( $settings_page, 'format_datetime_value' )
	&& false !== strpos( $settings_page, 'wp_date( self::DATETIME_DISPLAY_FORMAT, $timestamp )' )
	&& false !== strpos( $settings_page, "self::format_datetime_value( (string) \$settings['verified_at']" )
	&& false !== strpos( $settings_page, "self::format_datetime_value( (string) ( \$monitoring['last_uploaded_at'] ?? '' ) )" )
	&& false !== strpos( $settings_page, "self::format_datetime_value( (string) ( \$summary['synced_at'] ?? '' ) )" )
	&& false !== strpos( $boundary_doc, 'Cloud observability summaries are read-only dashboard projections' )
	&& false !== strpos( $runtime_contract, 'must not be treated as Core audit truth' ),
	'Monitoring UI and docs keep observability as dashboard projection, not governance truth.'
);

maca_assert(
	false !== strpos( $admin_surface_standard, 'Time Display' )
	&& false !== strpos( $admin_surface_standard, 'WordPress site timezone' )
	&& false !== strpos( $admin_surface_standard, 'Y-m-d H:i:s' )
	&& false !== strpos( $admin_surface_standard, 'Do not print raw UTC strings' ),
	'Admin surface standard documents WordPress-time display for human-facing timestamps.'
);

maca_assert(
	false !== strpos( $complexity_doc, 'security and boundary complexity' )
	&& false !== strpos( $complexity_doc, 'product-control complexity' )
	&& false !== strpos( $complexity_doc, 'Is this transport/detail, or is it control/write truth?' )
	&& false !== strpos( $complexity_doc, 'tests/static-contracts.php' )
	&& false !== strpos( $complexity_doc, 'tests/behavior-media-derivative.php' ),
	'Complexity budget document records what complexity is worth keeping and where tests belong.'
);
