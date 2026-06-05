# Cloud Addon Complexity Budget

Status: active for `magick-ai-cloud-addon`.

## Purpose

This addon is intentionally allowed to carry security and boundary complexity,
but it must not grow product-control complexity.

The useful complexity here protects the local WordPress host from accidental
Cloud ownership drift. The addon remains a connector and transport layer; local
Core remains the control plane for proposal display, approval, record, replace,
rollback, and all WordPress writes.

## Complexity Worth Keeping

Keep these even if they make the code less minimal:

- HMAC signing, trace headers, idempotency headers, and endpoint allowlists.
- Save-and-Verify gating before media derivative dispatch.
- Ability payload checks that reject credentials, Authorization data, signed
  headers, tokens, and Cloud signing fields.
- Bounded source and watermark media derivative multipart transport.
- Image watermark/logo fail-closed behavior unless the local ability supplies a
  watermark plan and the host supplies one short TTL artifact or upload; text
  watermark plans must remain structured options without a watermark source.
- Bounded signed derivative artifact preview download through the explicit
  runtime artifact download endpoint.
- Non-expired Cloud artifact id requirements for derivative proposal adoption.
- Cloud result to artifact binding checks for artifact id, run id, and checksum.
- Preview-only proposal payloads with `final_write_owner=local_wordpress_host`.
- Tests that prove the addon does not write WordPress objects or attachment
  metadata.

These checks are defensive boundary rules, not product features.

## Complexity To Stop

Do not add these to this addon:

- Proposal UI, approval UI, record, replace, rollback, or preflight ownership.
- Attachment main-file replacement or `_wp_attachment_metadata` updates.
- Artifact registry, generic upload/download manager, or source/logo registry.
- Watermark/logo planning, branding defaults, or logo storage.
- Workflow/task queue control, scheduler truth, repair console, or operator
  recovery console.
- Billing truth, invoice/payment controls, or Cloud service operations console.
- Router, prompt, preset, model-center, ability, workflow, MCP, or OpenClaw
  control planes.
- Generic public Cloud proxy methods or low-level request exposure.

If a change needs any item above, it belongs in local Core, Adapter, or Cloud
service-plane code, not in this addon.

## Review Question

Before adding code, ask:

Is this transport/detail, or is it control/write truth?

- Transport/detail can stay here when bounded and endpoint-allowlisted.
- Control/write truth must stay with the local WordPress host or the appropriate
  Cloud service-plane owner.

## Test Structure

Tests are split by purpose:

- `tests/static-contracts.php` checks source, docs, and boundary text for
  forbidden surfaces and required contracts.
- `tests/behavior-media-derivative.php` calls public PHP APIs with WordPress
  stubs to prove fail-closed behavior and proposal payload shape.
- `tests/helpers.php` contains shared assertions, file readers, WordPress stubs,
  HTTP stubs, settings seed helpers, and media derivative fixtures.
- `tests/run.php` is only the aggregate entry point used by Composer.

Keep new tests in the narrowest matching file. Do not add product workflow
simulation to the helper layer.
