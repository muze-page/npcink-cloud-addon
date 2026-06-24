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

## AI Development Rules

- Start AI-assisted work with `git status --short --branch` and a compact
  change envelope: target repositories, focused module, intended change,
  explicit non-goals, public contracts touched, expected files, files or areas
  that must not change, required gates, cross-repo matrix requirement, and
  rollback plan.
- Before staging, inspect `git status --short --branch` and `git diff --stat`.
  Stage only files changed for the current task. Do not use `git add -A` in a
  mixed worktree.
- Do not run `git reset --hard`, `git checkout -- .`, or equivalent destructive
  cleanup unless the user explicitly asks for that exact operation.
- Before committing, verify `git diff --cached --stat` and
  `git diff --cached --name-only`; after committing, verify
  `git show --name-status --stat HEAD`.
- For multi-repo milestones, run the central matrix from
  `/Users/muze/gitee/npcink-toolbox` instead of copying the script into this
  addon: `composer quality:matrix` for status and `composer quality:matrix:run`
  before cross-repo closeout.

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
composer run test:all
git diff --check
rg "/v1/runtime/workflows/runs|wp_insert_post|wp_update_post" --glob '*.php' --glob '!build/**' .
```

Documentation may mention forbidden concepts only to describe what this addon must not own.
