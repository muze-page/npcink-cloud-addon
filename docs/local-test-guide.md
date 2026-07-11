# Local Test Guide

## Static Checks

Run:

```bash
composer run test:all
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
rg "/v1/runtime/workflows/runs" --glob '*.php' --glob '!build/**' .
rg "wp_insert_post|wp_update_post|wp_delete_post" --glob '*.php' --glob '!build/**' .
rg "approval truth|proposal truth|billing truth|workflow engine" docs README.md AGENTS.md
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

`https://npcink.local/`

Steps:

1. Log in with a local administrator account.
2. Activate `Npcink Cloud Addon`.
3. Open `Npcink > Cloud Addon`.
4. Use the Cloud authorization action to add the current site.
5. Confirm the callback saves the returned connection key and immediately verifies.
6. Confirm failed verification shows a clear error and does not mark the settings as verified.
7. Confirm successful verification shows `configured_valid` without split credential identifiers.
8. View page source and confirm the stored connection key is not present.
9. Confirm entitlement read failures show `unavailable` and are not presented as usable entitlement.

## WordPress AI Editor Smoke Test

For the local `magick-ai.local` development site with Cloud settings already
verified and WordPress AI features enabled, run:

```bash
composer run smoke:wp-ai-editor
```

The command uses WP-CLI against `WP_PATH`, defaulting to
`/Users/muze/Local Sites/magick-ai/app/public`. Override `WP_AI_SMOKE_USER`,
`WP_PATH`, `WP_CLI_BIN`, `WP_CLI_PHP`, or `WP_DB_SOCKET` when the local site
differs.

This smoke creates one local draft post, runs the same WordPress AI ability
surfaces used by the editor for title, excerpt, summarization, SEO description,
and content classification, applies only the summary block and SEO description
to the draft, and reads the draft back through REST.

Expected:

- the created post remains `draft`;
- title and excerpt return direct suggestion text;
- the content contains an `ai-summarization-summary` block;
- `wpai_meta_description` contains direct suggestion text;
- classification returns labels but the smoke does not accept or create terms;
- no publish action is performed.

Use this after changing the Cloud WordPress AI connector, runtime output
normalization, or the local AI-plugin compatibility shim. Browser visual smoke
is still useful before release, but this command gives a repeatable regression
gate for the editor data path.

## WordPress AI Generation Reference A/B Evaluation

Run a read-only paired evaluation across real published posts:

```bash
composer run eval:wp-ai-generation-reference
```

Override the bounded sample with `WP_AI_EVAL_POST_IDS=1,2,3`. The evaluator
alternates baseline/reference order, runs title, excerpt, summary, meta
description, and classification abilities, and restores the original local
reference permission in a `finally` block. It does not update posts, publish
content, or apply taxonomy terms.

The evaluator waits 3200 ms between provider calls by default to stay within
common OpenAI-compatible provider rate limits. Override
`WP_AI_EVAL_DELAY_MS` only when the configured provider permits a different
request rate.

The JSON result records non-empty output reliability, historical length
distance, existing taxonomy reuse, historical-text similarity, boilerplate,
and numbers not present in the current article. These are operational proxy
metrics for regression detection; final product quality still requires blind
human preference review.

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
