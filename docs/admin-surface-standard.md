# Cloud Addon Admin Surface Standard

Status: active for `Magick AI -> Cloud Addon`.

## Purpose

The Cloud Addon admin page is a thin connector surface. It stores the Cloud
Base URL and Cloud API Key, verifies signed connectivity, and shows read-only
connection, entitlement, and opt-in monitoring state.

## Default View

When not configured or not verified, the default page should prioritize:

- connection state and blocking reason;
- Cloud Base URL and Cloud API Key entry;
- `Save and Verify` as the primary action.

Cloud Base URL must use HTTPS except for local development hosts (`localhost`,
`127.0.0.1`, or `::1`).

When verified, the default page should prioritize:

- compact Cloud status;
- Site ID, Key ID, last verification time;
- read-only entitlement summary;
- opt-in monitoring state and read-only Cloud observability summary;
- a clear path to update/re-verify settings.

## Advanced / Low-Frequency Details

Low-frequency details may include:

- timeout setting;
- read-only entitlement fields;
- metadata-only monitoring upload status;
- aggregate Cloud observability counters;
- last verification failure text.

## Do Not Add

Cloud Addon admin must not add:

- split credential editing fields for `site_id`, `key_id`, or `secret`;
- proposal, approval, preflight, audit, or WordPress write controls;
- router, prompt, preset, workflow/task queue, scheduler truth, or runtime repair
  control planes;
- billing truth, invoices, service operations console, or developer diagnostic
  routes.

The admin page may expose a monitoring toggle and read-only observability
summary only when that surface remains metadata-only and clearly separate from
Core audit, proposal, approval, execution, billing, and workflow truth.

## Verification

Checks should confirm that the page never prints the stored secret, never
applies WordPress writes, and remains a connector/detail surface rather than a
second local or cloud control plane.
