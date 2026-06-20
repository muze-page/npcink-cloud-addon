# Local Test Guide

## Static Checks

Run:

```bash
find /Users/muze/gitee/magick-ai-cloud-addon -name '*.php' -print0 | xargs -0 -n1 php -l
php /Users/muze/gitee/magick-ai-cloud-addon/tests/run.php
git diff --check
```

`tests/run.php` is only an aggregate runner:

- `tests/static-contracts.php` checks source and docs for boundary contracts.
- `tests/behavior-media-derivative.php` calls public PHP APIs with WordPress
  stubs to prove fail-closed behavior.
- `tests/behavior-site-knowledge-change-bridge.php` calls the Site Knowledge
  bridge handlers with WordPress stubs to prove approved comments buffer their
  parent public post for Cloud refresh transport.
- `tests/helpers.php` contains shared assertions, stubs, and fixtures.

Before expanding addon scope, read `docs/cloud-addon-complexity-budget.md`.

Boundary checks:

```bash
rg "/v1/runtime/workflows/runs" /Users/muze/gitee/magick-ai-cloud-addon
rg "wp_insert_post|wp_update_post|wp_delete_post" /Users/muze/gitee/magick-ai-cloud-addon
rg "approval truth|proposal truth|billing truth|workflow engine" /Users/muze/gitee/magick-ai-cloud-addon
```

Expected:

- No `/v1/runtime/workflows/runs`.
- No WordPress post write calls.
- No ownership of approval, proposal, billing, workflow, or scheduler truth.
- Observability upload uses only a bounded metadata buffer and approved
  observability endpoints.

Media derivative contract checks:

- ability payloads are guarded against credentials, Authorization, and signed
  headers before dispatch;
- unverified Cloud credentials fail closed;
- expired Cloud artifacts cannot produce adoption/proposal payloads;
- default proposal payloads are preview-only and do not replace the original
  attachment file;
- proposal payloads declare `final_write_owner=local_wordpress_host`.

## WordPress Smoke Test

Site:

`https://magick-ai.local/`

Steps:

1. Log in with a local administrator account.
2. Activate `Npcink Cloud Addon`.
3. Open `Npcink > Cloud Addon`.
4. Save Cloud Base URL and Cloud API Key.
5. Confirm failed verification shows a clear error and does not mark the settings as verified.
6. Confirm successful verification shows `configured_valid`.
7. View page source and confirm the Cloud secret is not present.
8. Confirm entitlement read failures show `unavailable` and are not presented as usable entitlement.

## Cloud Contract Smoke Test

With valid Cloud credentials:

- `probe_connectivity()` calls `/health/live` and signed `/v1/entitlements/current`.
- `execute_runtime()` calls `/v1/runtime/execute`.
- `get_run()` calls `/v1/runs/{run_id}`.
- `get_run_result()` calls `/v1/runs/{run_id}/result`.
- `get_profile_stats()` and `get_instance_stats()` only read `/v1/stats/*`.
- `get_current_entitlement()` only reads `/v1/entitlements/current`.
- When the Cloud response includes `entitlement.pro_cloud_runtime`, the local
  entitlement summary preserves the Pro Cloud Runtime quota detail as read-only
  display data.
- `send_observability_events()` only writes metadata-only events to
  `/v1/observability/plugin-events`.
- `get_observability_summary()` only reads `/v1/observability/plugin-summary`.

Stats and entitlement data are read projections only. They must not become local billing truth, local quota engines, scheduler truth, or WordPress write authority.
Observability summaries are dashboard projections only. They must not become
Core audit, proposal, approval, execution, billing, or workflow truth.
