# Cloud Addon Admin Surface Standard

Status: active for `Npcink AI -> Cloud Addon` when Toolbox is active, with
`Settings -> Npcink Cloud Addon` as the standalone fallback.

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
`http://localhost:8010/` for local WordPress environments and
`https://cloud.npc.ink/` otherwise. Manual Base URL and Cloud API Key wrapper
entry belong in `Connection Management > Manual fallback` as a recovery
fallback for local debugging or authorization outages.

The default `Connect` view may expose one folded `Advanced connection /
Self-hosted Cloud endpoint` entry. That entry only changes the authorization target for a
compatible Npcink Cloud deployment; it must not save partial credentials before
authorization completes and must not manage Cloud sites, keys, billing, models,
router, workflows, or runtime policy.
Cloud authorization entries should open in a new browser tab so the WordPress
admin page remains available while Cloud creates or activates the site
connection.

See `docs/connect-ui-and-zh-cn-localization-closeout-2026-07-09.md` for the
closeout rationale behind the folded endpoint entry, new-tab authorization
behavior, and zh_CN fixed-string maintenance boundary.

When verified, the default page should prioritize:

- `Local permissions` as the first working tab for WordPress AI connector
  exposure, Site Knowledge delivery, and metadata-only monitoring consent;
- compact Cloud status, last verification time, read-only entitlement
  availability, and entitlement summary freshness in `Status > Overview`;
- a dedicated Site Knowledge tab for local delivery consent, bounded public
  content refresh transport, explicit administrator delivery intents, and
  shallow bridge state while Cloud owns index execution, rebuild/delete
  handling, lifecycle, and freshness policy;
- a bounded Troubleshooting tab for connection, liveness, signed Cloud read, entitlement/quota, hosted runtime entitlement detail, capability readiness notes, Site Knowledge bridge status, monitoring status, and low-frequency `Runtime runs` detail, including entitlement/quota detail, batch limit, result retention, recent runs, one-run status/result reads, and bounded retry requests;
- a Connection Management tab for connection recovery, local disconnect,
  manual fallback, timeout, last verification failure, and other low-frequency
  connector details;
- opt-in monitoring state and read-only Cloud observability / Agent feedback
  quality summaries;
- a clear path to update/re-verify settings.

Toolbox no longer owns Cloud Checks or Troubleshooting Checks for basic AI
connection, Hosted Runtime, Cloud search, Cloud image/source, quota,
entitlement, or service health. The Cloud Addon Troubleshooting tab is the local
entry for those Cloud connection and service-status details, but it must remain
a summary/detail surface rather than a Toolbox product workflow or operations
console.

## Tab Model

Verified admin navigation should stay shallow:

- `Local permissions`: immediate-save switches for locally exposed Cloud
  connector services.
- `Status`: compact local connector summaries, account/usage, monitoring
  quality, and monitoring diagnostics as secondary tabs.
- `Site Knowledge`: read-only local delivery state, public content refresh
  transport, index operations, and shallow bridge health detail as secondary
  tabs.
- `Troubleshooting`: read-only checks, runtime run detail, Cloud-owned
  capability notes, and direct Cloud detail links as secondary tabs.
- `Connection Management`: connection status, Cloud-side connection change,
  local disconnect, and manual fallback as secondary tabs.

Do not reintroduce separate `Details`, `Runtime Runs`, or `Advanced` product
tabs when the content is low-frequency detail that fits one of the entries
above. Old `details` and `runtime_runs` URLs may redirect to the current tab
owners for compatibility.

## Advanced / Low-Frequency Details

Low-frequency details may include:

- manual Cloud Base URL and Cloud API Key wrapper recovery entry;
- timeout setting;
- read-only entitlement fields;
- Cloud-owned runtime recent/status/result detail and retry request entry;
- sanitized diagnostics rows and Cloud detail links;
- metadata-only monitoring upload status;
- aggregate Cloud observability counters;
- aggregate Agent feedback quality counters;
- last verification failure text.

These details should move into a clear secondary tab or explicit inline detail
entry unless they are blocking the current task. Do not surface internal enum fields such as credit policy or runtime local truth in the default admin UI;
keep those in tests and boundary documentation.

## Layout and Copy Rules

Admin panels should use utility copy and summary/detail separation:

- keep one page title and one scope sentence;
- avoid repeated section titles when the active tab already names the area;
- place primary actions beside the relevant state, not scattered above and
  below the same section;
- prefer one table row per fact and avoid narrow label columns that force
  awkward wrapping;
- use table headers for tabular diagnostics;
- keep long explanatory text behind explicit detail affordances such as
  disclosure rows or a small `!` detail entry;
- avoid nested disclosure controls inside another disclosure;
- keep fixed admin terminology translated in zh_CN, while dynamic ability
  metadata, provider IDs, model IDs, slugs, and contract IDs remain owned by
  their source systems.

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
- Toolbox scheduled-review submission, local snapshot building, or Core handoff
  workspaces;
- billing truth, invoices, service operations console, or developer diagnostic
  routes.
- Tavily, Unsplash, provider-model selection, Cloud search execution, or image
  source search product tools.
- Site Knowledge execution truth, freshness policy controls, collection
  management, embedding/vector provider settings, or deep troubleshooting
  controls.

The admin page may expose a monitoring toggle, Site Knowledge local delivery
consent toggle, read-only observability summary, and read-only Agent feedback
quality summary only when those surfaces remain metadata-only or transport-only
and clearly separate from Core audit, proposal, approval, execution, billing,
index lifecycle, and workflow truth.

## Verification

Checks should confirm that the page never prints the stored secret, never
applies WordPress writes, and remains a connector/detail surface rather than a
second local or cloud control plane.
