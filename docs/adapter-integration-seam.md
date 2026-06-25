# Adapter Integration Interface

## Rule

`npcink-openclaw-adapter` must not store Cloud credentials.

Adapter code should call this addon through the public PHP functions and runtime client.

## Public Functions

```php
npcink_cloud_addon_is_configured(): bool
npcink_cloud_addon_get_settings(): array
npcink_cloud_addon_runtime_client(): ?Npcink_Cloud_Runtime_Client
npcink_cloud_addon_verified_runtime_client(): ?Npcink_Cloud_Runtime_Client
npcink_cloud_addon_execute_wordpress_ai_connector_runtime(array $request, string $trace_id = '', string $idempotency_key = '')
npcink_cloud_addon_dispatch_media_derivative_cloud_request(array $ability_response, array $source_artifact, string $trace_id = '', string $idempotency_key = '', array $watermark_artifact = array())
npcink_cloud_addon_build_media_derivative_proposal_payload(array $ability_response, array $cloud_result, array $derivative_artifact)
npcink_cloud_addon_download_media_derivative_artifact(array $derivative_artifact, string $trace_id = '')
```

## Expected Adapter Flow

1. Check `function_exists( 'npcink_cloud_addon_runtime_client' )`.
2. Call `npcink_cloud_addon_runtime_client()`.
3. If it returns `null`, fail closed or use the local fallback path.
4. Shape the OpenClaw/Core payload in adapter code.
5. Call `execute_runtime()`.
6. Poll with `get_run()` and read final output with `get_run_result()`.
7. If the Cloud result contains write intent, pass it to Core proposal/preflight.

## WordPress AI Connector Flow

The addon already registers a fixed `Npcink Cloud` connector on the WordPress
Connectors surface after Cloud settings verify. Adapter or host code should not
create another Cloud credential UI, connector registry, prompt registry, or
OpenAI-compatible endpoint for this path.

For the WordPress AI connector/provider flow:

1. Host/provider code maps a WordPress AI feature into a supported site task
   such as `title_generation`, `excerpt_generation`, `meta_description`,
   `content_summary`, `content_rewrite`, `content_classification`,
   `comment_moderation`, `comment_reply_suggest`, or `alt_text_suggest`.
2. Host/provider code calls
   `npcink_cloud_addon_execute_wordpress_ai_connector_runtime()` for text
   scenes or `npcink_cloud_addon_execute_wordpress_ai_image_generation_runtime()`
   for the WordPress AI image generation feature.
3. The addon rejects generic chat message/session/tool/stream shapes and
   projects the request into `wp_ai_connector_runtime.v1` or Cloud's existing
   `image_generation_request.v1` runtime contract.
4. Cloud returns suggestion-only runtime output.
5. Host/provider code maps the output back into the WordPress AI feature
   response shape.

This connector flow must not expose an OpenAI-compatible endpoint, a human chat
UI, conversation sessions, image provider proxy, model-key passthrough,
prompt/router/preset editing, or WordPress writes. The addon provider also
rejects direct free-form `wp_ai_client_prompt()` calls unless the current call
originates from a supported WordPress AI plugin scene.

## Media Derivative Flow

For `npcink-abilities-toolkit/build-media-derivative-cloud-request`:

1. Host/Adapter calls the local WordPress ability and receives the read-only
   request contract.
2. Host/Adapter creates or obtains a local source upload descriptor or same-site
   short TTL source artifact id. The addon does not invent undocumented generic
   upload/download endpoints.
3. Host/Adapter calls
   `npcink_cloud_addon_dispatch_media_derivative_cloud_request()`.
4. The addon validates that the ability payload has no credentials,
   Authorization data, or signed headers, and fails closed when Cloud settings
   are not verified.
5. Host/Adapter polls `get_run()` and `get_run_result()`.
6. Host/Adapter may call
   `npcink_cloud_addon_download_media_derivative_artifact()` to serve a
   same-origin local preview proxy for a non-expired derivative artifact. The
   addon signs the Cloud download and verifies MIME, size, and optional
   checksum, but does not persist or register the artifact.
7. Host/Adapter calls
   `npcink_cloud_addon_build_media_derivative_proposal_payload()` to produce
   Core proposal input.
8. Core/local host owns proposal display, approval, record, replace, rollback,
   and all WordPress writes.

Expired Cloud artifacts must not be adopted. The proposal payload must keep
`final_write_owner=local_wordpress_host`, `default_action=preview_only`, and
`replace_original_default=false`.

Optional watermarks are part of the same derivative request. For image
watermarks, the local ability response must include
`cloud_job_payload.watermark` before adapter code passes the fifth
`watermark_artifact` argument. That argument can be a local upload descriptor
(`path`, `bytes`, or `content`) or a same-site short TTL Cloud artifact id. Text
watermark plans do not use the fifth argument; the addon forwards their text,
font, color, background, margin, opacity, and position as structured Cloud
payload options. The addon forwards the watermark plan and optional image
artifact reference only; it does not own a logo registry, choose default
branding, approve adoption, or write attachment metadata.

## Example

```php
$client = function_exists( 'npcink_cloud_addon_runtime_client' )
	? npcink_cloud_addon_runtime_client()
	: null;

if ( ! $client ) {
	return new WP_Error( 'cloud_addon_unavailable', 'Npcink Cloud Addon is not configured.' );
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
