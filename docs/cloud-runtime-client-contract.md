# Cloud Runtime Client Contract

## Purpose

`Npcink_Cloud_Runtime_Client` is the server-side transport client for current Npcink Cloud runtime APIs.

It is responsible for request signing, Cloud error mapping, and a small set of stable runtime/read methods.

It also exposes a bounded observability transport for opt-in, verified,
metadata-only plugin monitoring. Observability transport is for Cloud dashboard
projection, not for approval, audit, workflow, billing, prompt, router, preset,
or WordPress write truth.

## Constructor

```php
new Npcink_Cloud_Runtime_Client(array $config = array())
```

If no config is provided, the client loads normalized addon settings.

Required config:

- `base_url`
- `site_id`
- `key_id`
- `secret`
- `timeout`

## Methods

```php
probe_connectivity(): array
execute_runtime(array $payload, string $trace_id = '', string $idempotency_key = '')
execute_wordpress_ai_connector_runtime(array $request, string $trace_id = '', string $idempotency_key = '')
request_image_context_evidence(array $image_context_evidence_request, string $trace_id = '', string $idempotency_key = '')
create_media_derivative(array $payload, array $files = array(), string $trace_id = '', string $idempotency_key = '')
get_run(string $run_id, string $trace_id = '')
get_run_result(string $run_id, string $trace_id = '')
get_current_entitlement(string $trace_id = '')
get_profile_stats(string $profile_id, string $trace_id = '')
get_instance_stats(string $instance_id, string $trace_id = '')
send_observability_events(array $events, string $trace_id = '', string $idempotency_key = '')
send_agent_feedback_event(array $payload, string $trace_id = '', string $idempotency_key = '')
get_agent_feedback_summary(int $window_hours = 24, string $trace_id = '')
get_observability_summary(int $window_hours = 24, string $trace_id = '')
```

The low-level signed `request()` helper is private implementation detail. It must enforce the endpoint allowlist in this contract and must not be exposed as a generic public Cloud proxy.

For Cloud jobs that move local media bytes or downloadable artifacts, host code
should use the verified helper:

```php
npcink_cloud_addon_verified_runtime_client(): ?Npcink_Cloud_Runtime_Client
```

It returns `null` until the addon settings have passed Save and Verify.

## Endpoint Mapping

| Method | Endpoint |
| --- | --- |
| `probe_connectivity()` | `GET /health/live`, then signed `GET /v1/entitlements/current` |
| `execute_runtime()` | `POST /v1/runtime/execute` |
| `execute_wordpress_ai_connector_runtime()` | `POST /v1/runtime/execute` |
| `npcink_cloud_addon_dispatch_site_knowledge_runtime()` | `POST /v1/runtime/execute` |
| `request_image_context_evidence()` | `POST /v1/runtime/execute` |
| `create_media_derivative()` | `POST /v1/runtime/media-derivatives` |
| `get_run()` | `GET /v1/runs/{run_id}` |
| `get_run_result()` | `GET /v1/runs/{run_id}/result` |
| `get_recent_nightly_inspection_runs()` | `GET /v1/runs/nightly-inspection/recent` |
| `retry_run()` | `POST /v1/runs/{run_id}/retry` |
| `download_media_derivative_artifact()` | `GET /v1/runtime/artifacts/{artifact_id}/download` |
| `get_current_entitlement()` | `GET /v1/entitlements/current` |
| `get_profile_stats()` | `GET /v1/stats/profiles/{profile_id}` |
| `get_instance_stats()` | `GET /v1/stats/instances/{instance_id}` |
| `send_observability_events()` | `POST /v1/observability/plugin-events` |
| `send_agent_feedback_event()` | `POST /v1/agent-feedback/events` |
| `get_agent_feedback_summary()` | `GET /v1/agent-feedback/summary` |
| `get_observability_summary()` | `GET /v1/observability/plugin-summary` |

## Diagnostics Surface

The Cloud Addon Diagnostics tab reuses the existing connection state,
`probe_connectivity()` result cache, entitlement summary, monitoring summary,
and Cloud Portal links. It does not expose the private `request()` helper,
register a Developer diagnostics route, or add ad hoc Cloud service endpoints.

Capability rows such as Platform Models, provider readiness, Cloud web search,
image source search, image generation, and Site Knowledge bridge must only show
status backed by an existing addon contract. If no addon read contract exists,
the row must say that the capability is not connected or Cloud-owned instead of
fabricating a check.

## Runtime Runs Surface

The Cloud Addon `Troubleshooting > Runtime runs` section may use the existing
run endpoints for Nightly Inspection detail: recent runs, one-run status,
one-run result, and a nonce-protected retry request for a known run. It is a
low-frequency
Cloud-owned recovery/detail surface. It may also show the read-only
`pro_cloud_runtime` projection for run quota, batch limit, result retention,
and quota-exhausted state.

The tab must not submit scheduled reviews, rebuild Toolbox local snapshots,
create Core proposals, approve changes, create a local retry queue, or write
WordPress data. If Cloud rejects a retry because the original run input is not
recoverable, the addon shows the Cloud error and fails closed.

## Signing

## Entitlement Projection

`get_current_entitlement()` returns Cloud entitlement detail for local display.
When Cloud includes `entitlement.pro_cloud_runtime`, the addon summary preserves
that read-only detail for local plugins that need Pro Cloud Runtime status, such
as Nightly Site Inspection run quota, remaining runs, batch limits, result
retention, payload modes, and quota exhaustion.

Runtime projection field types:

- `max_nightly_inspection_runs_per_period`, `used_nightly_inspection_runs`, and
  `remaining_nightly_inspection_runs` are numeric quota fields.
- `max_batch_items` and `result_retention_days` are optional numeric fields; if
  Cloud omits them, the addon must render them as unavailable rather than `0`.
- `quota_exhausted` is a boolean projection. False-like strings such as
  `"false"` must not render as exhausted.

This projection must not be treated as local billing truth, a local quota
engine, scheduler truth, queue state, retry ownership, or WordPress write
authority.

## Signing

Signed requests include:

- `X-Npcink-Site-Id`
- `X-Npcink-Key-Id`
- `X-Npcink-Timestamp`
- `X-Npcink-Signature`
- `X-Npcink-Trace-Id`
- `traceparent`
- `X-Npcink-Nonce` for POST requests
- `Idempotency-Key` when provided

The signature is HMAC SHA-256 over:

```text
METHOD
path
site_id
key_id
timestamp
nonce
idempotency_key
traceparent
sha256(body)
```

## Error Shape

The client returns a decoded Cloud response array on success.

On failure it returns `WP_Error` with:

- local error code prefixed with `cloud_`
- human-readable message
- `status`
- `cloud_error_code`
- `cloud_payload`

## Observability Transport

`send_observability_events()` may send only sanitized plugin behavior metadata
received from the Addon observability collector. Event payloads must be shaped
by an explicit allowlist before they reach the runtime client.

`get_observability_summary()` reads a Cloud-generated aggregate dashboard
projection. The local Addon may cache the summary for display only.

`send_agent_feedback_event()` may send only `cloud_agent_feedback.v1` local
operator feedback for Cloud eval and quality rollups. The payload must preserve
local approval, preflight, and final write truth; Cloud must not treat it as
training permission, workflow truth, proposal truth, or WordPress write
authority.

`get_agent_feedback_summary()` reads an aggregate eval/quality projection for
display. It is read-only and must not become local approval, proposal, workflow,
or WordPress write authority.

Observability transport must not send prompts, generated content, article body
content, media bytes, raw request payloads, raw response payloads, provider
credentials, Cloud API secrets, passwords, cookies, nonces, Authorization
headers, database names, table names, or filesystem paths.

The Addon-owned observability buffer is a temporary transport buffer only. It
must not be treated as Core audit truth, proposal truth, execution truth, AI
request logs, billing truth, or workflow/task queue truth.

## Image Context Evidence Transport

`request_image_context_evidence()` accepts only the Toolbox
`image_context_evidence_request.v1` artifact. It must keep:

- `write_posture=suggestion_only`
- `direct_wordpress_write=false`
- `no_local_model=true`
- `no_media_write=true`
- `source_policy=bounded_media_urls_for_visual_context_only`

The method dispatches a bounded `npcink-cloud/image-context-evidence` runtime
execute payload with `profile_id=vision.ai` and
`execution_kind=image_context_evidence`. It may send only the request's bounded
media URL or thumbnail URL, attachment id, MIME type, title/filename context,
and local candidate-quality flags. It must not send provider credentials,
Cloud signing data, raw WordPress database fields, local filesystem paths, or
media bytes.

The method returns `image_context_evidence.v1` only as suggestion-only evidence
for local review. It must filter Cloud evidence to the requested attachment ids,
force `direct_wordpress_write=false`, require human visual confirmation, and
fail closed when Cloud returns no usable evidence.

This helper is not a local image recognition model, a product workflow, a
queue, a proposal creator, a media metadata writer, or a second control plane.
See `docs/image-context-evidence-integration-summary.md` for the cross-repo
Toolbox/Add-on rationale and boundary summary.

## WordPress AI Connector Runtime

The addon registers one fixed `Npcink Cloud` connector on the WordPress
Connectors surface when Cloud settings have passed Save and Verify. The
connector uses a synthetic marker option to satisfy the WordPress Connectors
page's `ai_provider` + `api_key` render contract and local AI plugin
availability checks. The marker is not a Cloud secret and is not exposed through
REST. Cloud credentials remain owned by the addon settings page, and the
Connectors card stays status-only for this fixed Cloud connector.

`execute_wordpress_ai_connector_runtime()` is the bounded transport seam for
text connector/provider calls. `execute_wordpress_ai_image_generation_runtime()`
is the bounded transport seam for the WordPress AI image generation feature.
Both must be treated as scene runtimes, not as generic chat providers, image
provider proxies, model proxies, or OpenAI-compatible endpoints.

The optional PHP AI Client provider registers `npcink-cloud-scene-text` and
`npcink-cloud-scene-image` as scene wrapper models. These ids represent bounded
WordPress AI surfaces, not bottom-level Cloud provider model ids. The addon may
make those wrappers first-choice text/image preferences only after Cloud
settings pass Save and Verify; otherwise it must preserve the WordPress AI
plugin's original preferred model order. Bottom-level provider/model routing
stays with Cloud hosted runtime profiles.

The addon does not register a `wpai_preferred_vision_models` override. Vision
or Alt Text defaults must stay with the WordPress AI plugin until a separate
bounded vision scene contract is defined; image context evidence transport does
not make this addon a generic vision provider or router.

Input must use `contract_version=wp_ai_connector_runtime.v1` and one of the
supported task surfaces:

- `title_generation`
- `excerpt_generation`
- `meta_description`
- `content_summary`
- `content_rewrite`
- `content_classification`
- `comment_moderation`
- `comment_reply_suggest`
- `alt_text_suggest`

The method projects accepted requests into a fixed runtime payload:

- `ability_name=npcink-cloud/wp-ai-connector`
- `channel=wordpress_ai_connector`
- `execution_kind=wordpress_ai_connector`
- `execution_pattern=inline`
- `storage_mode=result_only`
- `write_posture=suggestion_only`
- `direct_wordpress_write=false`
- `no_conversation=true`
- `policy.allow_fallback=false`

The method rejects generic chat or provider-control fields such as `messages`,
`conversation_id`, `session_id`, `thread_id`, `tools`, `tool_calls`,
`functions`, `function_call`, `stream`, credentials, cookies, nonces, and signed
headers. It also bounds prompt/body size and clamps timeout to 60 seconds,
retention, and retry values.

Image generation input must use
`contract_version=image_generation_request.v1`, `task=image_generation`, and a
single text prompt. The addon projects accepted requests into Cloud's existing
`npcink-cloud/generate-image` runtime ability with
`execution_kind=image_generation`, `channel=wordpress_ai_connector`,
`storage_mode=result_only`, and `policy.allow_fallback=false`. The addon does
not choose provider keys or expose model routing; Cloud owns image-generation
provider/profile selection. The image generation seam rejects generic chat,
tool, stream, and credential fields, clamps image count to 1-4, clamps timeout
to 90 seconds, and does not support reference-image refinement.

The optional PHP AI Client provider may call this helper only when the current
call stack originates from a known WordPress AI plugin Ability class and maps to
one of the supported task surfaces. Direct free-form `wp_ai_client_prompt()`
calls, chat history, tools, and web search are rejected before a Cloud request is
made.

For structured WordPress AI scenes, the addon sends only a shallow
`response_format` hint such as `json` or `text`. It must not forward the AI
Client's full `output_schema` into Cloud public runtime input; schema ownership
and response parsing remain with the WordPress AI ability surface.

## Boundary

The client must not add workflow repair, workflow/task queue operations,
scheduler truth, approval operations, billing mutation, prompt mutation, router
mutation, preset mutation, or WordPress write methods.

The private request helper must reject any signed path outside the endpoint
mapping above. In particular, it must reject workflow runtime, generic artifact,
support-bundle, file-upload, database-export, raw-payload, approval, proposal,
billing-mutation, prompt, router, preset, and WordPress write endpoints unless a
future boundary update explicitly adds a named public method.

## Media Derivative Transport

`Npcink_Cloud_Media_Derivative_Transport` is a bounded host-facing helper
for the local ability `npcink-abilities-toolkit/build-media-derivative-cloud-request`.

It may:

- validate the read-only ability output;
- reject payloads that include credentials, Authorization data, or signed
  headers;
- reject unverified Cloud credentials before dispatch;
- attach a host-supplied source upload or short TTL source artifact id;
- attach an optional host-supplied watermark upload or short TTL watermark
  artifact id when the ability response includes a watermark plan;
- dispatch through `POST /v1/runtime/media-derivatives`;
- download one non-expired derivative artifact for local preview through
  `download_media_derivative_artifact(string $artifact_id, string $trace_id = '')`
  and the explicit signed
  `GET /v1/runtime/artifacts/{artifact_id}/download` endpoint;
- convert a Cloud derivative artifact descriptor into a Core-ready local
  proposal payload.

It must not:

- call the ability itself;
- upload or download bytes through undocumented or generic Cloud endpoints;
- own a source media registry or logo registry;
- persist proposals;
- approve proposals;
- replace attachment main files;
- update `_wp_attachment_metadata`;
- perform any WordPress write.

The proposal payload generated by this helper always reports
`final_write_owner=local_wordpress_host` and `default_action=preview_only`.
Derivative proposal adoption requires a non-expired Cloud artifact id. If the
Cloud result includes a derivative artifact id, run id, or checksum, it must
match the derivative artifact descriptor before the helper returns a proposal.
