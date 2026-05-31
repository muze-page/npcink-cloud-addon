# Cloud Addon Admin Surface Standard

Status: active for `Magick AI -> Cloud`.

## Purpose

The Cloud Addon admin page is a thin connector surface. It stores the Cloud
Base URL and Cloud API Key, verifies signed connectivity, and shows read-only
connection and entitlement state.

## Default View

When not configured or not verified, the default page should prioritize:

- connection state and blocking reason;
- Cloud Base URL and Cloud API Key entry;
- `Save and Verify` as the primary action.

When verified, the default page should prioritize:

- compact Cloud status;
- Site ID, Key ID, last verification time;
- read-only entitlement summary;
- a clear path to update/re-verify settings.

## Advanced / Low-Frequency Details

Low-frequency details may include:

- timeout setting;
- read-only entitlement fields;
- last verification failure text.

## Do Not Add

Cloud Addon admin must not add:

- split credential editing fields for `site_id`, `key_id`, or `secret`;
- proposal, approval, preflight, audit, or WordPress write controls;
- router, prompt, preset, workflow, queue, scheduler, or runtime repair
  control planes;
- billing truth, invoices, service operations console, or developer diagnostic
  routes.

## Verification

Checks should confirm that the page never prints the stored secret, never
applies WordPress writes, and remains a connector/detail surface rather than a
second local or cloud control plane.
