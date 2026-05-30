# Cloud Addon Boundary

## Position

`magick-ai-cloud-addon` is a WordPress-side Cloud Connector / Cloud Addon.

It connects a local WordPress site to `magick-ai-cloud` and keeps only the local settings required to sign Cloud runtime requests.

## Addon Owns

- Cloud Base URL.
- Cloud API Key entry.
- `mak1_{base64url(json)}` and JSON key parsing.
- Internal `site_id`, `key_id`, and `secret` storage for server-side signing.
- HMAC signatures, trace headers, idempotency headers, and request nonce headers.
- Health and signed connectivity checks.
- Runtime request dispatch and run/result reads.
- Stats and entitlement read projections.
- A light `Settings > Magick AI Cloud` page.

## Addon Does Not Own

- Approval truth.
- Proposal truth.
- Final WordPress writes.
- Queue control.
- Scheduler control.
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

## Endpoint Rule

Allowed Cloud contract endpoints:

- `GET /health/live`
- `POST /v1/runtime/execute`
- `GET /v1/runs/{run_id}`
- `GET /v1/runs/{run_id}/result`
- `GET /v1/stats/*`
- `GET /v1/entitlements/current`

Forbidden legacy endpoint:

- `/v1/runtime/workflows/runs`

## UI Rule

The local UI stays shallow:

- status
- access settings
- validation feedback
- site/key ids
- last verification time
- entitlement summary

It must not become a second control plane.
