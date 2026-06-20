# Agent Notes

This repository is the standalone `npcink-cloud-addon` WordPress plugin.

## Boundaries

- Keep this addon a Cloud connector only.
- Do not add router, prompt, preset, approval, proposal, workflow/task queue,
  scheduler truth, workflow engine, billing truth, or WordPress write ownership.
- Bounded observability buffering and WP-Cron flushing are allowed only for
  opt-in, verified, metadata-only plugin monitoring uploads.
- Bounded Site Knowledge change buffering and WP-Cron flushing are allowed only
  for public content-change delivery to Cloud Site Knowledge; Cloud owns index
  lifecycle and freshness policy.
- Do not reintroduce `/v1/runtime/workflows/runs`.
- Do not expose split credential fields in the UI.
- Do not print or log the stored `secret`.
- Do not make the settings page a second control plane.
- Read `docs/cloud-addon-complexity-budget.md` before expanding addon scope;
  keep security/boundary checks, but do not add product-control complexity.

## Current Cloud Runtime Contract

Allowed runtime/read endpoints:

- `POST /v1/runtime/execute`
- `POST /v1/runtime/media-derivatives`
- `GET /v1/runs/{run_id}`
- `GET /v1/runs/{run_id}/result`
- `GET /v1/stats/*`
- `GET /v1/entitlements/current`
- `POST /v1/observability/plugin-events`
- `GET /v1/observability/plugin-summary`
- `GET /v1/agent-feedback/summary`
- `GET /health/live` for unsigned liveness

## Local Verification

Run before handing off changes:

```bash
find /Users/muze/gitee/magick-ai-cloud-addon -name '*.php' -print0 | xargs -0 -n1 php -l
git diff --check
rg "/v1/runtime/workflows/runs|wp_insert_post|wp_update_post" /Users/muze/gitee/magick-ai-cloud-addon
```

Documentation may mention forbidden concepts only to describe what this addon must not own.
