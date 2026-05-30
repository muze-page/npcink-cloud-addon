# Adapter Integration Interface

## Rule

`magick-ai-adapter` must not store Cloud credentials.

Adapter code should call this addon through the public PHP functions and runtime client.

## Public Functions

```php
magick_ai_cloud_addon_is_configured(): bool
magick_ai_cloud_addon_get_settings(): array
magick_ai_cloud_addon_runtime_client(): ?Magick_AI_Cloud_Runtime_Client
```

## Expected Adapter Flow

1. Check `function_exists( 'magick_ai_cloud_addon_runtime_client' )`.
2. Call `magick_ai_cloud_addon_runtime_client()`.
3. If it returns `null`, fail closed or use the local fallback path.
4. Shape the OpenClaw/Core payload in adapter code.
5. Call `execute_runtime()`.
6. Poll with `get_run()` and read final output with `get_run_result()`.
7. If the Cloud result contains write intent, pass it to Core proposal/preflight.

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

## Adapter Does Not Own

- Cloud credential storage.
- Cloud secret display.
- HMAC implementation.
- Cloud billing truth.
- Final WordPress writes from Cloud output.
