# Cloud Addon Admin Surface Closeout

Status: accepted

Date: 2026-07-03

## Context

The Cloud Addon settings page had accumulated several important controls and
diagnostics behind deep or collapsed sections. During review, the highest-value
local controls were identified as:

- WordPress AI connector exposure.
- Site Knowledge delivery consent.
- Metadata-only monitoring consent.

These controls affect whether verified Cloud connector services are exposed to
the local WordPress site, so hiding them near the bottom of the connection form
made the page harder to scan and increased the chance of accidental confusion
between local consent, Cloud-owned service state, and product workflows.

The WordPress AI plugin also shipped new fixed admin strings that needed zh_CN
compatibility coverage in this addon shim. That shim remains bounded to fixed
WordPress AI plugin admin copy and must not translate dynamic ability metadata.

## Decisions

The verified admin surface now uses shallow top-level tabs with secondary tabs
for lower-frequency detail:

- `Local permissions`: the first verified tab and the home for immediate-save
  switches controlling WordPress AI connector exposure, Site Knowledge delivery,
  and metadata-only monitoring consent.
- `Status`: read-only connector summaries with secondary tabs for overview,
  account and usage, monitoring quality, and monitoring diagnostics.
- `Site Knowledge`: read-only local delivery state and Cloud-owned index
  operations, with a link back to `Local permissions` for consent changes.
- `Troubleshooting`: checks, runtime runs, and capability notes as secondary
  tabs instead of collapsed advanced blocks.
- `Connection Management`: connection status, Cloud-side connection actions,
  local disconnect, and manual fallback as secondary tabs.

Switch controls use the shared `npcink-ai-switch` style in this addon so Cloud
Addon can render correctly when installed alone. Other Npcink plugins may adopt
the same class names, but this plugin must not rely on another plugin for the
base switch CSS.

The old sanitized raw status table is intentionally not shown in the default
admin UI. User-relevant entitlement freshness moved into `Status > Overview`;
internal enum-like fields such as credit policy and runtime local truth remain
for tests and boundary documentation rather than day-to-day UI.

## Boundary

This closeout keeps the addon as a Cloud connector only. It does not add:

- a second ability registry, workflow registry, router, prompt, preset, MCP, or
  Agent Gateway control plane;
- Cloud billing truth, scheduler truth, workflow/task queue ownership, proposal
  ownership, or WordPress final-write ownership;
- Developer diagnostics routes, provider operations UI, or product search tools;
- split credential fields, stored secret display, or raw signed request data.

WordPress AI connector registration is gated by verified Cloud settings and the
local `wordpress_ai_connector_enabled` consent setting. Disabling that local
permission removes the Npcink Cloud connector projection and synthetic marker
without changing Cloud connection, monitoring, or Site Knowledge settings.

Site Knowledge delivery remains local consent and bounded transport only. Cloud
owns indexing, rebuild/delete handling, freshness policy, collection lifecycle,
and diagnostics detail.

## Implemented Changes

- Added the `Local permissions` verified tab with immediate-save switch forms.
- Added the `wordpress_ai_connector_enabled` setting and wired it into the
  WordPress AI connector marker/card registration path.
- Reworked `Status`, `Site Knowledge`, `Troubleshooting`, and
  `Connection Management` around secondary tabs instead of nested disclosure
  controls.
- Replaced checkbox-style permission controls with shared switch markup and
  local CSS.
- Moved refresh/re-verify actions into section headers where they support the
  current view without pushing summary content down.
- Removed stale disclosure CSS tied to the old advanced/raw layout.
- Refreshed zh_CN and POT files for the new admin UI and WordPress AI plugin
  compatibility strings.
- Updated README, readme.txt, boundary docs, runtime client contract docs, and
  admin surface standards to match the new structure.
- Extended behavior and static contract tests for the local permission gating,
  switch styling, secondary tab model, and localization coverage.

## Verification

Automated checks passed before the branch was pushed:

```bash
composer run test:all
composer run check:wporg
git diff --check
sh -c '! rg "/v1/runtime/workflows/runs|wp_insert_post|wp_update_post" --glob "*.php" --glob "!build/**" .'
```

The forbidden runtime workflow endpoint and direct WordPress write grep returned
no matches.

## Known Remaining Check

Browser visual QA still needs a logged-in local WordPress admin session. The
local site is reachable, but the admin page redirects to `wp-login.php` without
an existing browser login session. No credentials were entered during this
closeout.

## Follow-Up Rule

Do not add new top-level admin pages or collapsed advanced blocks for ordinary
connector detail. Prefer the current tab model:

- local consent in `Local permissions`;
- read-only connector state in `Status`;
- Site Knowledge transport and Cloud-owned index operation intents in
  `Site Knowledge`;
- low-frequency checks and runtime run detail in `Troubleshooting`;
- credential recovery and disconnect actions in `Connection Management`.

If future Cloud read contracts expose new service readiness data, add named
runtime client methods, update the endpoint allowlist, document the contract,
and add focused contract tests before showing a live UI row.
