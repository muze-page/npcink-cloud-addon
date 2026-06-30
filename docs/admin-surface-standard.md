# Cloud Addon Admin Surface Standard

Status: active for `Npcink -> Cloud Addon`.

## Purpose

The Cloud Addon admin page is a thin connector surface. It opens the Cloud
Portal authorization flow by default, stores the returned Cloud Base URL and
Cloud API Key, verifies signed connectivity, and shows read-only connection,
entitlement, opt-in monitoring state, and Agent feedback quality summary.

## Default View

When not configured or not verified, the default page should prioritize:

- connection state and blocking reason;
- one primary action to add the current WordPress site in Npcink Cloud;
- the resolved Cloud base URL and current site URL as context only.

Cloud Base URL must use HTTPS except for local development hosts (`localhost`,
`127.0.0.1`, or `::1`). The environment default is
`http://127.0.0.1:8010` for local WordPress environments and
`https://cloud.npc.ink` otherwise. Manual Base URL and Cloud API Key wrapper
entry belong in Advanced as a recovery fallback for local debugging or
authorization outages.

When verified, the default page should prioritize:

- compact Cloud status;
- last verification time;
- read-only entitlement summary;
- a bounded Diagnostics tab for connection, liveness, signed Cloud read,
  entitlement/quota, hosted runtime entitlement detail, capability readiness
  notes, Site Knowledge bridge status, and monitoring status;
- opt-in monitoring state and read-only Cloud observability / Agent feedback
  quality summaries;
- a clear path to update/re-verify settings.

Toolbox no longer owns Cloud Checks or Troubleshooting Checks for basic AI
connection, Hosted Runtime, Cloud search, Cloud image/source, quota,
entitlement, or service health. The Cloud Addon Diagnostics tab is the local
entry for those Cloud connection and service-status details, but it must remain
a summary/detail surface rather than a Toolbox product workflow or operations
console.

## Advanced / Low-Frequency Details

Low-frequency details may include:

- manual Cloud Base URL and Cloud API Key wrapper recovery entry;
- timeout setting;
- read-only entitlement fields;
- sanitized diagnostics rows and Cloud detail links;
- metadata-only monitoring upload status;
- aggregate Cloud observability counters;
- aggregate Agent feedback quality counters;
- last verification failure text.

## Time Display

Cloud settings, entitlement summaries, and observability buffers may store UTC
or ISO timestamps for machine use, cache freshness, signing, upload status, or
Cloud correlation. Keep those stored values stable.

Any timestamp shown in the wp-admin settings page must be formatted through the
WordPress site timezone as `Y-m-d H:i:s`. Do not print raw UTC strings, ISO
timestamps, or Cloud machine timestamps directly in the human-facing admin UI
unless the label explicitly describes a machine/debug value.

## Do Not Add

Cloud Addon admin must not add:

- split credential editing fields for `site_id`, `key_id`, or `secret`;
- proposal, approval, preflight, audit, or WordPress write controls;
- router, prompt, preset, workflow/task queue, scheduler truth, or runtime repair
  control planes;
- billing truth, invoices, service operations console, or developer diagnostic
  routes.
- Tavily, Unsplash, provider-model selection, Cloud search execution, or image
  source search product tools.

The admin page may expose a monitoring toggle, read-only observability summary,
and read-only Agent feedback quality summary only when those surfaces remain
metadata-only and clearly separate from Core audit, proposal, approval,
execution, billing, and workflow truth.

## Verification

Checks should confirm that the page never prints the stored secret, never
applies WordPress writes, and remains a connector/detail surface rather than a
second local or cloud control plane.
