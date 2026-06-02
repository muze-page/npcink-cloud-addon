# Adapter Integration Interface

## Rule

`magick-ai-adapter` must not store Cloud credentials.

Adapter code should call this addon through the public PHP functions and runtime client.

## Public Functions

```php
magick_ai_cloud_addon_is_configured(): bool
magick_ai_cloud_addon_get_settings(): array
magick_ai_cloud_addon_runtime_client(): ?Magick_AI_Cloud_Runtime_Client
magick_ai_cloud_addon_verified_runtime_client(): ?Magick_AI_Cloud_Runtime_Client
magick_ai_cloud_addon_dispatch_media_derivative_cloud_request(array $ability_response, array $source_artifact, string $trace_id = '', string $idempotency_key = '')
magick_ai_cloud_addon_build_media_derivative_proposal_payload(array $ability_response, array $cloud_result, array $derivative_artifact)
```

## Expected Adapter Flow

1. Check `function_exists( 'magick_ai_cloud_addon_runtime_client' )`.
2. Call `magick_ai_cloud_addon_runtime_client()`.
3. If it returns `null`, fail closed or use the local fallback path.
4. Shape the OpenClaw/Core payload in adapter code.
5. Call `execute_runtime()`.
6. Poll with `get_run()` and read final output with `get_run_result()`.
7. If the Cloud result contains write intent, pass it to Core proposal/preflight.

## Media Derivative Flow

For `magick-ai/build-media-derivative-cloud-request`:

1. Host/Adapter calls the local WordPress ability and receives the read-only
   request contract.
2. Host/Adapter creates or obtains a local source upload descriptor or same-site
   short TTL source artifact id. The addon does not invent undocumented generic
   upload/download endpoints.
3. Host/Adapter calls
   `magick_ai_cloud_addon_dispatch_media_derivative_cloud_request()`.
4. The addon validates that the ability payload has no credentials,
   Authorization data, or signed headers, and fails closed when Cloud settings
   are not verified.
5. Host/Adapter polls `get_run()` and `get_run_result()`.
6. Host/Adapter calls
   `magick_ai_cloud_addon_build_media_derivative_proposal_payload()` to produce
   Core proposal input.
7. Core/local host owns proposal display, approval, record, replace, rollback,
   and all WordPress writes.

Expired Cloud artifacts must not be adopted. The proposal payload must keep
`final_write_owner=local_wordpress_host`, `default_action=preview_only`, and
`replace_original_default=false`.

Watermark/logo transport is not currently exposed through this addon. If the
local ability output contains `cloud_job_payload.watermark`, the addon must
reject dispatch until the local ability and Cloud runtime contracts are both
frozen. The addon does not own a logo registry, choose default branding,
approve adoption, or write attachment metadata.

## Example

```php
$client = function_exists( 'magick_ai_cloud_addon_runtime_client' )
	? magick_ai_cloud_addon_runtime_client()
	: null;

if ( ! $client ) {
	return new WP_Error( 'cloud_addon_unavailable', 'Magick AI Cloud Addon is not configured.' );
}

$response = $client->execute_runtime(
	$payload,
	$trace_id,
	$idempotency_key
);
```

## Adapter Owns

- OpenClaw request shaping.
- Core/Abilities/Cloud routing decisions within local truth boundaries.
- Local fallback behavior.
- Passing write intent to Core proposal/preflight.
- Calling local abilities and creating the local source artifact descriptor for
  media derivative Cloud jobs.

## Adapter Does Not Own

- Cloud credential storage.
- Cloud secret display.
- HMAC implementation.
- Cloud billing truth.
- Final WordPress writes from Cloud output.
- Attachment file replacement or attachment metadata updates.
