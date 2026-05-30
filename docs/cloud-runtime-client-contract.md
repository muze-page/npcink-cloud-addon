# Cloud Runtime Client Contract

## Purpose

`Magick_AI_Cloud_Runtime_Client` is the server-side transport client for current Magick AI Cloud runtime APIs.

It is responsible for request signing, Cloud error mapping, and a small set of stable runtime/read methods.

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
get_run(string $run_id, string $trace_id = '')
get_run_result(string $run_id, string $trace_id = '')
get_current_entitlement(string $trace_id = '')
get_profile_stats(string $profile_id, string $trace_id = '')
get_instance_stats(string $instance_id, string $trace_id = '')
request(string $method, string $path, ?array $payload = null, string $idempotency_key = '', string $trace_id = '')
```

## Endpoint Mapping

| Method | Endpoint |
| --- | --- |
| `probe_connectivity()` | `GET /health/live`, then signed `GET /v1/entitlements/current` |
| `execute_runtime()` | `POST /v1/runtime/execute` |
| `get_run()` | `GET /v1/runs/{run_id}` |
| `get_run_result()` | `GET /v1/runs/{run_id}/result` |
| `get_current_entitlement()` | `GET /v1/entitlements/current` |
| `get_profile_stats()` | `GET /v1/stats/profiles/{profile_id}` |
| `get_instance_stats()` | `GET /v1/stats/instances/{instance_id}` |

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

## Boundary

The client must not add workflow repair, queue operations, scheduler operations, approval operations, billing mutation, prompt mutation, router mutation, preset mutation, or WordPress write methods.
