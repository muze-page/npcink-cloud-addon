# Manual Readiness Result Closeout - 2026-07-09

Status: accepted

PR: https://github.com/muze-page/npcink-cloud-addon/pull/32

## Context

The first `npcink-eval-lab` external-plugin learning pass recommended a P1
connector pattern inspired by WP Mail SMTP-style manual test actions:

```text
manual_test_action
-> bounded_status
-> owner_label
-> blocked_reason
-> next_safe_action
-> copyable_support_facts
```

The target for `npcink-cloud-addon` was a minimum bounded connector/readiness
test result. The target was not a new Cloud runtime, provider router, billing
or quota source, request-log owner, workflow runtime, approval store, local
queue, or WordPress write path.

The existing addon already had the correct transport primitive:
`probe_connectivity()` performs an unsigned `GET /health/live` check and then a
signed `GET /v1/entitlements/current` read. This made the safe implementation
path contract reuse, not a new endpoint.

## Decision

Cloud Addon now exposes `cloud_addon_readiness_result.v1` as a bounded,
non-secret readiness result:

- `manual_test_action=probe_connectivity`
- `status` and `bounded_status`
- `owner_label`
- `blocked_reason`
- `next_action` and `next_safe_action`
- `support_facts` and `copyable_support_facts`
- `write_posture=read_only`
- `tested_at`

The result is available through:

- `Npcink_Cloud_Runtime_Client::manual_readiness_test()`
- `npcink_cloud_addon_get_manual_readiness_result()`
- the existing Diagnostics checks surface after an explicit administrator
  action

The result intentionally reuses the existing liveness and signed entitlement
read contract. It does not add an endpoint allowlist entry, Cloud proxy method,
REST route, durable option, custom table, queue, registry, or write target.

## Trigger Semantics

The first implementation pass rendered the manual readiness result directly in
Diagnostics checks. That made the page render perform a live probe plus signed
Cloud read, which conflicted with the word `manual`.

The final design keeps the `manual` name and changes the trigger:

- Diagnostics page render only reads the latest short per-user transient.
- `Run readiness test` is a nonce-protected administrator `admin-post.php`
  action.
- The action executes `/health/live` plus the signed entitlement read.
- The result is stored for the current administrator for 10 minutes.
- If no result exists, Diagnostics shows `not run` and prompts the operator to
  run the test.

This keeps the check explicit and avoids surprise Cloud requests from a page
view.

## Boundary

This feature is allowed because it is connector/readiness detail:

- signed connector status only;
- local connection settings only;
- bounded transport/readiness test only;
- read-only Cloud status/detail projection only;
- non-secret support facts only.

This feature must not grow into:

- workflow registry or ability registry;
- approval store, proposal truth, audit truth, or Core governance truth;
- provider routing, prompt/preset control, billing truth, quota enforcement, or
  provider request logging;
- local runtime queue, retry worker, scheduler, run history, or recovery
  workspace;
- generic diagnostics routes, arbitrary endpoint testers, raw request/response
  logging, or support bundle upload;
- WordPress content writes.

The copyable support facts are intentionally shallow. They include host,
credential-slot presence, timeout, liveness state, signed-read state, signed
read endpoint, and `write_posture=read_only`. They do not include the stored
secret, Cloud API key wrapper, Authorization headers, cookies, nonces, raw
prompts, raw outputs, provider logs, or billing detail.

## Implementation Notes

The implementation is deliberately small:

- `probe_connectivity()` now attaches `readiness_result`.
- `manual_readiness_test()` returns the same bounded result shape.
- `npcink_cloud_addon_get_manual_readiness_result()` exposes the result as a
  PHP seam for trusted local read-only consumers.
- Diagnostics checks gained a `Run readiness test` form and result rows.
- `wp_remote_get()` in tests now records requests so tests can prove
  `/health/live` is part of the readiness path.
- Behavior tests cover `ready`, `not_configured`, and `failed`, including
  request counts and secret redaction.
- Static contract tests assert that the diagnostics integration uses an
  explicit admin action and does not become queue, registry, approval, or write
  ownership.

## Development Thinking

The principal trade-off was whether readiness should be a live diagnostic row
or a manual action. The connector-plugin precedent points to manual tests:
operators expect a button that performs one narrow check and returns actionable
support facts. Automatic checks are acceptable for cached state, but not for a
feature explicitly named manual test.

The safe pattern for future connector tests is:

```text
one explicit administrator action
-> one existing named transport/read contract
-> bounded non-secret result
-> short-lived display state
-> no durable local truth
```

If a future test requires new Cloud data, add a named runtime client method,
document the endpoint, update the allowlist, and add contract tests before
showing the result. If the desired check needs provider-level logs, raw
payloads, billing detail, queues, workflow history, scheduler state, or recovery
state, it belongs in Cloud service-plane tooling, not this addon.

## Verification

Commands run for PR #32 after the manual-trigger fix:

```bash
composer run test:all
git diff --check
sh -c '! rg "/v1/runtime/workflows/runs|wp_insert_post|wp_update_post" --glob "*.php" --glob "!build/**" .'
gh pr checks 32
```

Results:

- `composer run test:all` passed.
- `git diff --check` passed.
- forbidden runtime workflow endpoint and direct WordPress write grep returned
  no matches.
- GitHub `PHP contracts` passed.
- GitHub `PR body contract` passed after the PR body was updated with
  `Scope`, `Boundary`, `Verification`, and `Risk`.

## Follow-Up Rule

Do not add more readiness rows by fabricating local checks. Add a row only when
there is an existing addon-owned contract or a newly documented named contract.

Do not convert the short per-user readiness transient into durable diagnostic
history. If durable support history is needed, Cloud should own it as service
detail.
