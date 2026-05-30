# Magick AI Cloud Addon

Standalone WordPress plugin for connecting a local Magick AI installation to `magick-ai-cloud`.

The addon is a thin Cloud connector. It stores the Cloud Base URL and Cloud API Key, parses the key into signing credentials, sends signed runtime requests, reads health and entitlement status, and exposes a minimal PHP interface for local plugins.

## Scope

The addon owns:

- Cloud Base URL and Cloud API Key storage.
- `mak1_{base64url(json)}` and JSON Cloud API Key parsing.
- HMAC signing, trace headers, idempotency headers, and Cloud error mapping.
- Connectivity probing with `/health/live` and a signed Cloud read.
- Runtime and read projection calls:
  - `POST /v1/runtime/execute`
  - `GET /v1/runs/{run_id}`
  - `GET /v1/runs/{run_id}/result`
  - `GET /v1/stats/*`
  - `GET /v1/entitlements/current`
- `Settings > Magick AI Cloud`.

The addon does not own approval truth, proposal truth, WordPress writes, queue control, scheduling, billing truth, prompt ownership, router ownership, or preset ownership.

## Public PHP Interface

```php
magick_ai_cloud_addon_is_configured(): bool
magick_ai_cloud_addon_get_settings(): array
magick_ai_cloud_addon_runtime_client(): ?Magick_AI_Cloud_Runtime_Client
```

`Magick_AI_Cloud_Runtime_Client` exposes:

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

`magick_ai_cloud_addon_get_settings()` returns server-side settings, including the stored secret. Do not print it into HTML or logs.

## Settings Page

Admin path:

`Settings > Magick AI Cloud`

Fields:

- Cloud Base URL
- Cloud API Key
- Timeout

The page saves and verifies in one action. It displays connection state, Site ID, Key ID, last verification time, and a read-only entitlement summary. It never displays the secret and does not provide split credential editing.

## Local Checks

```bash
find /Users/muze/gitee/magick-ai-cloud-addon -name '*.php' -print0 | xargs -0 -n1 php -l
git diff --check
```

Boundary checks:

```bash
rg "/v1/runtime/workflows/runs|queue|scheduler|workflow engine|wp_insert_post|wp_update_post" /Users/muze/gitee/magick-ai-cloud-addon
```

`queue`, `scheduler`, and `workflow engine` may appear in documentation only as forbidden responsibilities.
