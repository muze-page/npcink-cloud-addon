# Npcink Cloud Addon

Standalone WordPress plugin for connecting a local Npcink installation to `npcink-cloud`.

The addon is a thin Cloud connector. It stores the Cloud Base URL and Cloud API Key returned by Cloud site authorization, parses the key into signing credentials, sends signed runtime requests, reads health and entitlement status, transports opt-in metadata-only plugin observability events, shows read-only Agent feedback quality summaries, bridges public Site Knowledge change hints to Cloud, and exposes a minimal PHP interface for local plugins.

## Scope

The addon owns:

- Cloud Base URL and Cloud API Key wrapper storage.
- Cloud site authorization callback exchange and `mak1_{base64url(json)}` parsing.
- HMAC signing, trace headers, idempotency headers, and Cloud error mapping.
- Connectivity probing with `/health/live` and a signed Cloud read.
- Runtime and read projection calls:
  - `POST /v1/runtime/execute`
  - `POST /v1/runtime/media-derivatives`
  - `GET /v1/runs/{run_id}`
  - `GET /v1/runs/{run_id}/result`
  - `GET /v1/runs/nightly-inspection/recent`
  - `POST /v1/runs/{run_id}/retry`
  - `GET /v1/runtime/artifacts/{artifact_id}/download`
  - `GET /v1/stats/*`
  - `GET /v1/entitlements/current`
- Opt-in plugin observability transport:
  - `POST /v1/observability/plugin-events`
  - `GET /v1/observability/plugin-summary`
- Read-only Agent feedback quality projection:
  - `POST /v1/agent-feedback/events`
  - `GET /v1/agent-feedback/summary`
- Site Knowledge public content change bridge through `POST /v1/runtime/execute`, including a bounded settings-page status and manual public refresh transport.
- Toolbox Site Knowledge runtime bridge through `POST /v1/runtime/execute`.
- Bounded image context evidence transport through `POST /v1/runtime/execute`.
- Bounded WordPress AI connector scene runtime through `POST /v1/runtime/execute`.
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
npcink_cloud_addon_request_image_context_evidence(array $image_context_evidence_request, string $trace_id = '', string $idempotency_key = '')
npcink_cloud_addon_execute_wordpress_ai_connector_runtime(array $request, string $trace_id = '', string $idempotency_key = '')
npcink_cloud_addon_dispatch_site_knowledge_runtime(array $runtime_payload, string $ability_name = '', string $contract_version = '')
npcink_cloud_addon_build_media_derivative_proposal_payload(array $ability_response, array $cloud_result, array $derivative_artifact)
npcink_cloud_addon_download_media_derivative_artifact(array $derivative_artifact, string $trace_id = '')
npcink_cloud_addon_site_knowledge_change_bridge_health(): array
```

`Npcink_Cloud_Runtime_Client` exposes:

```php
probe_connectivity(): array
execute_runtime(array $payload, string $trace_id = '', string $idempotency_key = '')
execute_wordpress_ai_connector_runtime(array $request, string $trace_id = '', string $idempotency_key = '')
request_image_context_evidence(array $image_context_evidence_request, string $trace_id = '', string $idempotency_key = '')
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

## Image Context Evidence Transport

The addon can consume a Toolbox-generated
`image_context_evidence_request.v1` artifact for weak media ALT/caption
metadata. It validates the request as suggestion-only, strips it to bounded
media URLs and metadata, sends it through `POST /v1/runtime/execute`, and
normalizes a Cloud response into `image_context_evidence.v1`.

Cloud owns the visual recognition runtime, provider routing, model execution,
and result generation. The addon does not run a local vision model, create a
queue, create a Core proposal, approve anything, or write media metadata.
Returned evidence is candidate basis only and must still be visually confirmed
by the local operator before any future governed apply path.

`npcink_cloud_addon_get_settings()` returns server-side settings, including the stored secret. Do not print it into HTML or logs.

## WordPress AI Connector Runtime

After Cloud settings pass Save and Verify, the addon registers one fixed
`Npcink Cloud` connector on the WordPress Connectors surface. The connector uses
a synthetic local marker setting so the WordPress AI plugin can recognize the
verified Cloud configuration without exposing the stored Cloud secret or adding
split credential fields.

The addon also exposes
`npcink_cloud_addon_execute_wordpress_ai_connector_runtime()` as a narrow seam
for text connector/provider calls, plus
`npcink_cloud_addon_execute_wordpress_ai_image_generation_runtime()` for the
WordPress AI image generation feature. Both are WordPress scene runtimes, not
generic chat APIs, image provider proxies, or OpenAI-compatible provider
proxies.

When the PHP AI Client is available, the addon registers scene-gated text and
image models. The text model only forwards calls that originate from known
WordPress AI plugin Ability classes, such as title, excerpt, metadata, summary,
classification, moderation, rewrite, or alt-text generation. The image model
only forwards text-to-image calls from the WordPress AI image generation
feature and rejects reference-image refinement. Direct `wp_ai_client_prompt()`
usage outside supported scenes is rejected before a Cloud request is made.

The registered `npcink-cloud-scene-text` and `npcink-cloud-scene-image` entries
are WordPress AI scene wrapper models, not direct provider model ids. They are
added to the WordPress AI preferred model lists only after Cloud settings pass
Save and Verify. The addon does not expose bottom-level provider model
selection; Cloud hosted runtime profiles choose the underlying provider/model.
The addon does not register a preferred vision model override. Alt text and
other vision defaults should remain with the WordPress AI plugin unless a
separate bounded vision scene contract is introduced.

The request must use `wp_ai_connector_runtime.v1` and one supported task
surface, such as `title_generation`, `excerpt_generation`, `meta_description`,
`content_summary`, `content_rewrite`, `content_classification`,
`comment_moderation`, `comment_reply_suggest`, or `alt_text_suggest`. The addon
projects the request into `ability_name=npcink-cloud/wp-ai-connector`,
`channel=wordpress_ai_connector`, `execution_kind=wordpress_ai_connector`,
`write_posture=suggestion_only`, `direct_wordpress_write=false`, and
`no_conversation=true`.

This helper rejects generic chat or provider-control shapes such as `messages`,
`conversation_id`, `session_id`, `thread_id`, `tools`, `tool_calls`, `functions`,
`function_call`, `stream`, credentials, cookies, nonces, and signed headers. It
also clamps timeout to 60 seconds, retention, and retry values to the
lightweight scene runtime limits.

The addon also carries a bounded zh_CN compatibility shim for high-traffic
WordPress AI plugin admin/editor UI strings. Maintenance rules and the future
one-command audit contract are documented in
`docs/ai-plugin-localization-maintenance.md`. Do not turn that shim into a full
language pack or translate dynamic ability metadata in this addon.

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
enabled, the addon listens for local `npcink_observability_event` metadata,
stores a bounded local observability buffer, flushes buffered metadata to
Cloud, and reads aggregate Cloud summaries for the local monitoring view.
The local monitoring status distinguishes sent events, Cloud-stored events, and
Cloud-reported duplicates so operators do not confuse upload attempts with
durable Cloud storage.
The Monitoring view may also read the Cloud Agent feedback quality summary even
when monitoring upload is disabled. That summary is aggregate eval metadata
only; it is not approval, proposal, preflight, workflow, billing, prompt,
router, preset, or WordPress write truth.

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

When Cloud settings are verified, the addon listens for public post/page and
approved comment changes, stores a bounded local delivery buffer, and sends a
Cloud Site Knowledge refresh request through the existing
`POST /v1/runtime/execute` runtime contract.

The bridge only sends public content manifests and affected WordPress post ids
with `write_posture=suggestion_only`. Cloud remains the Site Knowledge vector,
index, freshness, and collection lifecycle owner. The addon does not create a
local index, decide stale-index policy, register a workflow engine, own scheduler
truth, or perform WordPress writes.

## Site Knowledge Runtime Bridge

The addon registers the `npcink_toolbox_site_knowledge_cloud_request` filter so
Toolbox can keep the operator UI and ability buttons while this addon owns the
signed Cloud transport detail. The bridge accepts only:

- `npcink-cloud/site-knowledge-search` with `site_knowledge_search.v1`
- `npcink-cloud/site-knowledge-status` with `site_knowledge_status.v1`
- `npcink-cloud/site-knowledge-sync` with `site_knowledge_sync.v1`

All requests must remain `write_posture=suggestion_only` and use
`POST /v1/runtime/execute`. The bridge does not create local indexing jobs,
collection lifecycle state, approval records, proposal records, or WordPress
writes.

## Settings Page

Admin path:

`Npcink > Cloud Addon`

The default flow opens Cloud Portal site authorization with a `return_url` and
state token. After Cloud returns a code, the addon exchanges it at
`/portal/v1/addon-connections/exchange`, stores the returned Cloud API Key
wrapper and base URL, and verifies the signed connection immediately.

Cloud Base URL must use `https://` unless it points to local development hosts
such as `localhost`, `127.0.0.1`, or `::1`. Timeout and manual recovery key entry
are kept in Advanced for local debugging or authorization outages.

When verified, the page prioritizes Cloud status, last verification time, and a
read-only entitlement summary. The Diagnostics tab is the Cloud Addon-side
replacement for the old Toolbox Cloud Checks / Troubleshooting Checks entry:
it shows connection, liveness, signed Cloud read, entitlement/quota, hosted
runtime entitlement detail, capability readiness notes, Site Knowledge bridge
status, and monitoring status. It does not recreate Toolbox product tools for
Cloud search, image source search, provider operations, or task execution.

The Details tab includes a shallow Site Knowledge section for connector state,
buffered public changes, last delivery, and a manual public content refresh
request. It is a transport/status surface only: Cloud owns indexing, freshness
policy, collection lifecycle, and deep diagnostics. Toolbox consumes Site
Knowledge results in fixed best-practice buttons instead of owning index
management UI.

The settings page never displays split signing credentials and does not provide
split credential editing.

The admin page scope is documented in
[`docs/admin-surface-standard.md`](docs/admin-surface-standard.md). The Cloud
site connection flow history is summarized in
[`docs/cloud-site-connection-flow-history.md`](docs/cloud-site-connection-flow-history.md).

## Repository Management

GitHub is the primary remote for ongoing development:

`https://github.com/muze-page/npcink-cloud-addon`

The former Gitee remote may be kept as a read-only or backup mirror, but new
work should branch, review, and merge through GitHub. Keep `master` as the
default branch unless the CI and release process are intentionally migrated.

## Local Checks

```bash
composer run test:all
git diff --check
```

Boundary checks:

```bash
rg "/v1/runtime/workflows/runs|wp_insert_post|wp_update_post" --glob '*.php' --glob '!build/**' .
rg "workflow engine|approval truth|proposal truth|billing truth" docs README.md AGENTS.md
```

`workflow/task queue`, `scheduler truth`, and `workflow engine` may appear in
documentation only as forbidden responsibilities. `observability buffer`,
`Site Knowledge change buffer`, and their WordPress cron flush hooks are allowed
only as bounded Cloud delivery transport.
