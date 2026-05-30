# Agent Notes

This repository is the standalone `magick-ai-cloud-addon` WordPress plugin.

## Boundaries

- Keep this addon a Cloud connector only.
- Do not add router, prompt, preset, approval, proposal, queue, scheduler, workflow engine, billing truth, or WordPress write ownership.
- Do not reintroduce `/v1/runtime/workflows/runs`.
- Do not expose split credential fields in the UI.
- Do not print or log the stored `secret`.
- Do not make the settings page a second control plane.

## Current Cloud Runtime Contract

Allowed runtime/read endpoints:

- `POST /v1/runtime/execute`
- `GET /v1/runs/{run_id}`
- `GET /v1/runs/{run_id}/result`
- `GET /v1/stats/*`
- `GET /v1/entitlements/current`
- `GET /health/live` for unsigned liveness

## Local Verification

Run before handing off changes:

```bash
find /Users/muze/gitee/magick-ai-cloud-addon -name '*.php' -print0 | xargs -0 -n1 php -l
git diff --check
rg "/v1/runtime/workflows/runs|wp_insert_post|wp_update_post" /Users/muze/gitee/magick-ai-cloud-addon
```

Documentation may mention forbidden concepts only to describe what this addon must not own.
