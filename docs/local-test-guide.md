# Local Test Guide

## Static Checks

Run:

```bash
find /Users/muze/gitee/magick-ai-cloud-addon -name '*.php' -print0 | xargs -0 -n1 php -l
git diff --check
```

Boundary checks:

```bash
rg "/v1/runtime/workflows/runs" /Users/muze/gitee/magick-ai-cloud-addon
rg "wp_insert_post|wp_update_post|wp_delete_post" /Users/muze/gitee/magick-ai-cloud-addon
```

Expected:

- No `/v1/runtime/workflows/runs`.
- No WordPress post write calls.

## WordPress Smoke Test

Site:

`https://magick-ai.local/`

Steps:

1. Log in with a local administrator account.
2. Activate `Magick AI Cloud Addon`.
3. Open `Magick AI > Cloud Addon`.
4. Save Cloud Base URL and Cloud API Key.
5. Confirm failed verification shows a clear error and does not mark the settings as verified.
6. Confirm successful verification shows `configured_valid`.
7. View page source and confirm the Cloud secret is not present.
8. Confirm entitlement read failures show `unavailable` and are not presented as usable entitlement.

## Cloud Contract Smoke Test

With valid Cloud credentials:

- `probe_connectivity()` calls `/health/live` and signed `/v1/entitlements/current`.
- `execute_runtime()` calls `/v1/runtime/execute`.
- `get_run()` calls `/v1/runs/{run_id}`.
- `get_run_result()` calls `/v1/runs/{run_id}/result`.
- `get_profile_stats()` and `get_instance_stats()` only read `/v1/stats/*`.
- `get_current_entitlement()` only reads `/v1/entitlements/current`.

Stats and entitlement data are read projections only. They must not become local billing truth.
