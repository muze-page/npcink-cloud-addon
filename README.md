# Npcink Cloud Addon

Standalone WordPress plugin for connecting a local Npcink installation to `npcink-cloud`.

The addon is a thin Cloud connector. It stores the Cloud Base URL and Cloud API Key, parses the key into signing credentials, sends signed runtime requests, reads health and entitlement status, transports opt-in metadata-only plugin observability events, bridges public Site Knowledge change hints to Cloud, and exposes a minimal PHP interface for local plugins.

## Scope

The addon owns:

- Cloud Base URL and Cloud API Key storage.
- `mak1_{base64url(json)}` and JSON Cloud API Key parsing.
- HMAC signing, trace headers, idempotency headers, and Cloud error mapping.
- Connectivity probing with `/health/live` and a signed Cloud read.
- Runtime and read projection calls:
  - `POST /v1/runtime/execute`
  - `POST /v1/runtime/media-derivatives`
  - `GET /v1/runs/{run_id}`
  - `GET /v1/runs/{run_id}/result`
  - `GET /v1/stats/*`
  - `GET /v1/entitlements/current`
- Opt-in plugin observability transport:
  - `POST /v1/observability/plugin-events`
  - `GET /v1/observability/plugin-summary`
- Site Knowledge public content change bridge through `POST /v1/runtime/execute`.
- `Npcink > Cloud Addon`.

The addon does not own approval truth, proposal truth, WordPress writes,
workflow/task queue control, scheduler truth, billing truth, prompt ownership,
router ownership, preset ownership, or Site Knowledge index lifecycle. Its local
observability and Site Knowledge buffers are only bounded delivery buffers for
Cloud transport; they are not audit, execution, billing, indexing, or workflow
truth.

The entitlement summary preserves Cloud `pro_cloud_runtime` detail such as
Nightly Site Inspection run limits, used and remaining runs, batch limits,
retention, payload modes, and quota-exhausted state. These fields are read-only
display projections for local plugins such as Toolbox; the addon does not turn
them into local billing truth, a local quota engine, scheduler truth, or a
WordPress write path.

## Public PHP Interface

```php
npcink_cloud_addon_is_configured(): bool
npcink_cloud_addon_get_settings(): array
npcink_cloud_addon_runtime_client(): ?Npcink_Cloud_Runtime_Client
npcink_cloud_addon_verified_runtime_client(): ?Npcink_Cloud_Runtime_Client
npcink_cloud_addon_dispatch_media_derivative_cloud_request(array $ability_response, array $source_artifact, string $trace_id = '', string $idempotency_key = '')
npcink_cloud_addon_build_media_derivative_proposal_payload(array $ability_response, array $cloud_result, array $derivative_artifact)
npcink_cloud_addon_download_media_derivative_artifact(array $derivative_artifact, string $trace_id = '')
npcink_cloud_addon_site_knowledge_change_bridge_health(): array
```

`Npcink_Cloud_Runtime_Client` exposes:

```php
probe_connectivity(): array
execute_runtime(array $payload, string $trace_id = '', string $idempotency_key = '')
create_media_derivative(array $payload, array $files = array(), string $trace_id = '', string $idempotency_key = '')
get_run(string $run_id, string $trace_id = '')
get_run_result(string $run_id, string $trace_id = '')
download_media_derivative_artifact(string $artifact_id, string $trace_id = '')
get_current_entitlement(string $trace_id = '')
get_profile_stats(string $profile_id, string $trace_id = '')
get_instance_stats(string $instance_id, string $trace_id = '')
send_observability_events(array $events, string $trace_id = '', string $idempotency_key = '')
get_observability_summary(int $window_hours = 24, string $trace_id = '')
```

The low-level signed request method is private and endpoint-allowlisted. New
callers should use the named methods above instead of sending arbitrary Cloud
paths through the addon.

`npcink_cloud_addon_get_settings()` returns server-side settings, including the stored secret. Do not print it into HTML or logs.

## Media Derivative Transport

The addon can consume the read-only
`npcink-abilities-toolkit/build-media-derivative-cloud-request` ability output as a transport
input. It validates that the ability payload has no Cloud credentials,
Authorization data, or signed headers, requires verified Cloud settings, and
dispatches through the named `/v1/runtime/media-derivatives` runtime service
endpoint.

The local host or Adapter still owns the ability call, local source file access,
short TTL source artifact creation, Core proposal creation, UI display,
approval, record, replace, rollback, and all WordPress writes. The addon helper
only returns a Core-ready proposal payload with
`final_write_owner=local_wordpress_host`; it does not persist or approve the
proposal.

Source media can be sent either as a local upload descriptor (`path`, `bytes`,
or `content`) or as a same-site short TTL Cloud artifact id. Optional
aspect-ratio crop plans in `cloud_job_payload.crop` are forwarded as bounded
Cloud runtime options. Optional watermarks require `cloud_job_payload.watermark`
in the ability response; the fifth dispatch parameter can then provide a
watermark upload descriptor or a short TTL watermark artifact id.

Expired Cloud artifacts are rejected before proposal adoption payloads are
built. The default action is preview-only and original attachment files are not
replaced by default.

For local operator previews, the addon may download one non-expired derivative
artifact by id through the explicit signed
`GET /v1/runtime/artifacts/{artifact_id}/download` runtime endpoint. The helper
checks descriptor TTL, supported image MIME type, bounded size, and optional
SHA-256 checksum, then returns bytes to the trusted local caller. It does not
persist the artifact, create an artifact registry, or write WordPress media.

## Observability Transport

Administrators may enable Cloud monitoring after Cloud settings verify. When
enabled, the addon listens for local `magick_ai_observability_event` metadata,
stores a bounded local observability buffer, flushes buffered metadata to
Cloud, and reads aggregate Cloud summaries for the local monitoring view.
The local monitoring status distinguishes sent events, Cloud-stored events, and
Cloud-reported duplicates so operators do not confuse upload attempts with
durable Cloud storage.

Allowed uploaded fields are limited to operational metadata such as plugin
slug/version, event kind, status, timing, error code, route, proposal id,
ability id, correlation id, and counts.

Monitoring does not upload prompts, generated content, article body content,
media bytes, raw request/response payloads, provider credentials, Cloud API
secrets, passwords, cookies, nonces, Authorization headers, database names,
table names, or filesystem paths.

Cloud observability summaries are dashboard projections only. They must not be
used to approve proposals, change Core status, execute WordPress writes, or
configure router, prompt, or preset behavior.

## Site Knowledge Change Bridge

When Cloud settings are configured, the addon listens for public post/page and
approved comment changes, stores a bounded local delivery buffer, and sends a
Cloud Site Knowledge refresh request through the existing
`POST /v1/runtime/execute` runtime contract.

The bridge only sends public content manifests and affected WordPress post ids
with `write_posture=suggestion_only`. Cloud remains the Site Knowledge vector,
index, freshness, and collection lifecycle owner. The addon does not create a
local index, decide stale-index policy, register a workflow engine, own scheduler
truth, or perform WordPress writes.

## Settings Page

Admin path:

`Npcink > Cloud Addon`

Fields:

- Cloud Base URL
- Cloud API Key

Cloud Base URL must use `https://` unless it points to local development hosts
such as `localhost`, `127.0.0.1`, or `::1`.
- Timeout

The page saves and verifies in one action. When unverified, it prioritizes the
settings form and `Save and Verify`. When verified, it prioritizes Cloud status,
Site ID, Key ID, last verification time, and a read-only entitlement summary,
with settings collapsed for update/re-verification. It never displays the
secret and does not provide split credential editing.

The admin page scope is documented in
[`docs/admin-surface-standard.md`](docs/admin-surface-standard.md).

## Local Checks

```bash
find /Users/muze/gitee/magick-ai-cloud-addon -name '*.php' -print0 | xargs -0 -n1 php -l
php /Users/muze/gitee/magick-ai-cloud-addon/tests/run.php
git diff --check
```

Boundary checks:

```bash
rg "/v1/runtime/workflows/runs|workflow engine|wp_insert_post|wp_update_post|approval truth|proposal truth|billing truth" /Users/muze/gitee/magick-ai-cloud-addon
```

`workflow/task queue`, `scheduler truth`, and `workflow engine` may appear in
documentation only as forbidden responsibilities. `observability buffer`,
`Site Knowledge change buffer`, and their WordPress cron flush hooks are allowed
only as bounded Cloud delivery transport.
