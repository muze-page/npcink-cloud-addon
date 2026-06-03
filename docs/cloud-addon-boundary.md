# Cloud Addon Boundary

## Position

`magick-ai-cloud-addon` is a WordPress-side Cloud Connector / Cloud Addon.

It connects a local WordPress site to `magick-ai-cloud` and keeps only the local settings required to sign Cloud runtime requests.

It may also act as an opt-in observability transport for installed Magick AI
plugins. Observability is metadata-only plugin monitoring for Cloud dashboards;
it is not local governance truth, Cloud governance truth, or a workflow/task
queue.

## Addon Owns

- Cloud Base URL.
- Cloud API Key entry.
- `mak1_{base64url(json)}` and JSON key parsing.
- Internal `site_id`, `key_id`, and `secret` storage for server-side signing.
- HMAC signatures, trace headers, idempotency headers, and request nonce headers.
- Health and signed connectivity checks.
- Runtime request dispatch and run/result reads.
- Verified dispatch helpers for host-owned media derivative Cloud jobs.
- Stats and entitlement read projections.
- Opt-in, verified, metadata-only plugin observability upload.
- A bounded local observability buffer used only to survive temporary delivery
  failures before upload.
- A light `Magick AI -> Cloud Addon` page.

## Addon Does Not Own

- Approval truth.
- Proposal truth.
- Final WordPress writes.
- Workflow/task queue control.
- Scheduler truth.
- Workflow engine truth.
- Billing truth.
- Prompt control plane.
- Router control plane.
- Preset control plane.
- Cloud service operations console.
- Developer diagnostics routes.

## Local Truth Rule

Local Core remains the owner for final WordPress permissions, proposal/preflight, approval, apply, and canonical WordPress writes.

Cloud may return generated output or write intent, but this addon must not apply it. Any write intent must be passed to Core proposal/preflight and final local approval paths.

For media derivative Cloud jobs, the addon may validate the local
`magick-ai/build-media-derivative-cloud-request` output, sign the runtime
request, and build a Core-ready proposal payload from a non-expired Cloud
artifact descriptor. It may forward a host-supplied source upload or same-site
short TTL artifact id, and it may forward an optional host-supplied watermark
upload or artifact id when the local ability output contains a watermark plan.
Derivative proposal adoption requires a non-expired Cloud artifact id, and any
Cloud result artifact id, run id, or checksum must match the artifact descriptor
before the addon returns a local proposal payload. It must not call the ability,
persist the proposal, choose logo defaults, own a logo registry, replace the
attachment file, update attachment metadata, or perform adoption.

## Observability Transport Rule

Cloud Addon may collect and upload plugin behavior metadata only after Cloud
settings are verified and the administrator explicitly enables monitoring.

Allowed event fields are limited to operational metadata such as:

- `plugin_slug`
- `plugin_version`
- `source`
- `event_kind`
- `event_id`
- `emitted_at`
- `captured_at`
- `status`
- `status_detail`
- `error_code`
- `latency_ms`
- `ability_id`
- `proposal_id`
- `correlation_id`
- `adapter_request_id`
- `method`
- `route`
- `status_code`
- `mode`
- `deduplicated`
- `proposal_count`
- `blocked_count`
- `executed_count`
- `failed_count`

The local observability buffer is a bounded transport buffer only. It must not
be used as Core audit, proposal state, execution state, AI request logs, billing
truth, or local workflow/task queue state.

Monitoring must not collect or upload prompts, generated content, article body
content, media bytes, raw request payloads, raw response payloads, provider
credentials, Cloud API secrets, passwords, cookies, nonces, Authorization
headers, database names, table names, or filesystem paths.

Cloud observability summaries are read-only dashboard projections. They must
not drive local approval, proposal status, WordPress writes, router, prompt, or
preset configuration.

## Endpoint Rule

Allowed Cloud contract endpoints:

- `GET /health/live`
- `POST /v1/runtime/execute`
- `POST /v1/runtime/media-derivatives`
- `GET /v1/runs/{run_id}`
- `GET /v1/runs/{run_id}/result`
- `GET /v1/runtime/artifacts/{artifact_id}/download`
- `GET /v1/stats/*`
- `GET /v1/entitlements/current`
- `POST /v1/observability/plugin-events`
- `GET /v1/observability/plugin-summary`

Forbidden legacy endpoint:

- `/v1/runtime/workflows/runs`

Media derivative transport must use the named runtime media derivative endpoint,
run/result endpoints, and explicit derivative artifact download endpoint above.
Do not silently add ad hoc artifact upload, generic download, source registry,
or logo registry endpoints to the addon.

Observability transport must use only the observability endpoints above. Do not
add ad hoc log upload, support bundle, file upload, database export, or raw
payload endpoints to the addon.

## UI Rule

The local UI stays shallow:

- status
- access settings
- validation feedback
- site/key ids
- last verification time
- entitlement summary
- opt-in monitoring status and read-only Cloud observability summary

It must not become a second control plane.
