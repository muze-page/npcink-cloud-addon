# Cloud Runtime Client Contract

## Purpose

`Magick_AI_Cloud_Runtime_Client` is the server-side transport client for current Magick AI Cloud runtime APIs.

It is responsible for request signing, Cloud error mapping, and a small set of stable runtime/read methods.

It also exposes a bounded observability transport for opt-in, verified,
metadata-only plugin monitoring. Observability transport is for Cloud dashboard
projection, not for approval, audit, workflow, billing, prompt, router, preset,
or WordPress write truth.

## Constructor

```php
new Magick_AI_Cloud_Runtime_Client(array $config = array())
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
create_media_derivative(array $payload, array $files = array(), string $trace_id = '', string $idempotency_key = '')
get_run(string $run_id, string $trace_id = '')
get_run_result(string $run_id, string $trace_id = '')
get_current_entitlement(string $trace_id = '')
get_profile_stats(string $profile_id, string $trace_id = '')
get_instance_stats(string $instance_id, string $trace_id = '')
send_observability_events(array $events, string $trace_id = '', string $idempotency_key = '')
get_observability_summary(int $window_hours = 24, string $trace_id = '')
```

The low-level signed `request()` helper is private implementation detail. It must enforce the endpoint allowlist in this contract and must not be exposed as a generic public Cloud proxy.

For Cloud jobs that move local media bytes or downloadable artifacts, host code
should use the verified helper:

```php
magick_ai_cloud_addon_verified_runtime_client(): ?Magick_AI_Cloud_Runtime_Client
```

It returns `null` until the addon settings have passed Save and Verify.

## Endpoint Mapping

| Method | Endpoint |
| --- | --- |
| `probe_connectivity()` | `GET /health/live`, then signed `GET /v1/entitlements/current` |
| `execute_runtime()` | `POST /v1/runtime/execute` |
| `create_media_derivative()` | `POST /v1/runtime/media-derivatives` |
| `get_run()` | `GET /v1/runs/{run_id}` |
| `get_run_result()` | `GET /v1/runs/{run_id}/result` |
| `get_current_entitlement()` | `GET /v1/entitlements/current` |
| `get_profile_stats()` | `GET /v1/stats/profiles/{profile_id}` |
| `get_instance_stats()` | `GET /v1/stats/instances/{instance_id}` |
| `send_observability_events()` | `POST /v1/observability/plugin-events` |
| `get_observability_summary()` | `GET /v1/observability/plugin-summary` |

## Signing

Signed requests include:

- `X-Magick-Site-Id`
- `X-Magick-Key-Id`
- `X-Magick-Timestamp`
- `X-Magick-Signature`
- `X-Magick-Trace-Id`
- `traceparent`
- `X-Magick-Nonce` for POST requests
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

Observability transport must not send prompts, generated content, article body
content, media bytes, raw request payloads, raw response payloads, provider
credentials, Cloud API secrets, passwords, cookies, nonces, Authorization
headers, database names, table names, or filesystem paths.

The Addon-owned observability buffer is a temporary transport buffer only. It
must not be treated as Core audit truth, proposal truth, execution truth, AI
request logs, billing truth, or workflow/task queue truth.

## Boundary

The client must not add workflow repair, workflow/task queue operations,
scheduler truth, approval operations, billing mutation, prompt mutation, router
mutation, preset mutation, or WordPress write methods.

The private request helper must reject any signed path outside the endpoint
mapping above. In particular, it must reject workflow runtime, artifact,
support-bundle, file-upload, database-export, raw-payload, approval, proposal,
billing-mutation, prompt, router, preset, and WordPress write endpoints unless a
future boundary update explicitly adds a named public method.

## Media Derivative Transport

`Magick_AI_Cloud_Media_Derivative_Transport` is a bounded host-facing helper
for the local ability `magick-ai/build-media-derivative-cloud-request`.

It may:

- validate the read-only ability output;
- reject payloads that include credentials, Authorization data, or signed
  headers;
- reject unverified Cloud credentials before dispatch;
- attach a host-supplied source upload or short TTL source artifact id;
- attach an optional host-supplied watermark upload or short TTL watermark
  artifact id when the ability response includes a watermark plan;
- dispatch through `POST /v1/runtime/media-derivatives`;
- convert a Cloud derivative artifact descriptor into a Core-ready local
  proposal payload.

It must not:

- call the ability itself;
- upload or download bytes through undocumented Cloud endpoints;
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
