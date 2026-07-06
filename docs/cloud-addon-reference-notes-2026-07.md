# Cloud Addon Reference Notes - 2026-07

Status: reference notes for next-stage planning.

Question: for the Cloud Addon connection, diagnostics, entitlement, and runtime
detail surface, do mature WordPress plugins already solve similar operator
problems we can learn from?

Short answer: yes. Cloud Addon should learn connection onboarding, status
summaries, test actions, and troubleshooting affordances from mature connector
plugins. It should not copy their broader product ownership, provider
administration, billing console, workflow runtime, or WordPress write control.

## Current Addon Baseline

Cloud Addon already owns:

- Cloud Base URL and Cloud API Key wrapper storage;
- `mak1_{base64url(json)}` parsing into server-side signing credentials;
- Save-and-Verify gating, HMAC signing, trace headers, idempotency headers, and
  endpoint allowlists;
- read-only liveness, entitlement, quota, monitoring, Agent feedback, Site
  Knowledge bridge, and runtime run detail;
- bounded transport seams for Toolbox, WordPress AI connector scenes, media
  derivatives, Site Knowledge, image context evidence, observability, and
  Agent feedback.

Cloud Addon must continue to avoid:

- split credential editing or secret display;
- approval, proposal, preflight, audit, rollback, or final WordPress write
  ownership;
- router, prompt, preset, model-center, ability, workflow, MCP, OpenClaw, or
  Agent Gateway control planes;
- billing truth, invoice/payment controls, or Cloud service operations console;
- local workflow queues, scheduler truth, retry queues, or runtime repair
  consoles;
- Site Knowledge index lifecycle, freshness policy, collection management, or
  vector provider settings.

## Reference Sources

| Reference | Similar capability | Useful lesson | Boundary note |
| --- | --- | --- | --- |
| [Jetpack](https://wordpress.org/plugins/jetpack/) | WordPress.com-connected plugin with many service-backed modules. | Connection status and feature readiness need one clear account/service summary before feature detail. | Do not copy Jetpack's broad product suite, module catalog, backup/security ownership, or WordPress.com control surface. |
| [Site Kit by Google](https://wordpress.org/plugins/google-site-kit/) | Service authorization and dashboard projections for Google services. | A connector works best when setup, service status, and read-only data projections are visibly separate. | Do not add analytics product ownership, OAuth provider management, or external service configuration beyond the Npcink Cloud connection. |
| [WP Mail SMTP](https://wordpress.org/plugins/wp-mail-smtp/) | Mailer setup, connection checks, test email, and error diagnostics. | Save-and-test flows are useful when the test is explicit, narrow, and tied to one transport contract. | Borrow test-action clarity, not email logging, mailer marketplace, or provider settings expansion. |
| [Health Check & Troubleshooting](https://wordpress.org/plugins/health-check/) | WordPress diagnostic and troubleshooting workflow. | Diagnostics should separate site state, actionable checks, and low-frequency debug detail. | Do not become a general WordPress support console or plugin isolation workflow. |
| [WordPress Application Passwords](https://make.wordpress.org/core/2020/11/05/application-passwords-integration-guide/) | User-facing integration credentials for REST clients. | Credential UX should expose only human-safe wrapper concepts and keep secret material server-side. | Cloud Addon already parses the customer key wrapper; do not expose `site_id`, `key_id`, or `secret` fields in the UI. |

## What To Borrow

Borrow these patterns because they improve connector trust without changing
Cloud Addon ownership:

- a single connection summary that answers configured, verified, last checked,
  and blocking reason;
- explicit setup and recovery paths, with manual fallback kept out of the
  default happy path;
- one narrow test action per transport contract, such as signed read,
  entitlement read, or runtime run lookup;
- status rows grouped by owner: local permission, Addon transport, Cloud
  service, Cloud-owned runtime detail, or external plugin surface;
- clear blocked states for unverified settings, expired/revoked keys, missing
  entitlement, Cloud unavailable, or no named read contract;
- low-frequency troubleshooting details behind secondary tabs or explicit
  detail affordances;
- credential UX that talks about the Cloud API Key wrapper rather than split
  signing internals;
- links to Cloud detail for service-plane issues instead of simulating those
  issues locally.

## What Not To Borrow

Do not import these product patterns into Cloud Addon:

- broad module marketplaces or feature catalogs;
- service billing, invoices, quota enforcement truth, subscription management,
  or key lifecycle operations;
- analytics dashboards, SEO dashboards, backup/security dashboards, or
  provider-specific admin consoles;
- email/request/provider logs that capture raw payloads, prompts, generated
  content, credentials, cookies, nonces, or Authorization headers;
- generic test-any-endpoint tools or developer diagnostics routes;
- local workflow run stores, retry queues, repair consoles, or scheduler truth;
- Site Knowledge collection lifecycle controls, stale-index policy, vector DB
  settings, embedding settings, or deep operations tooling.

## Candidate Improvements

### P1 - Preserve The Thin Connector Shape

Keep the current verified admin model:

- Local permissions;
- Status;
- Site Knowledge;
- Troubleshooting;
- Connection Management.

Do not add new top-level tabs for runtime runs, advanced diagnostics, provider
settings, billing, workflow, model routing, prompt presets, or Site Knowledge
operations.

### P1 - Improve Trust Cues

Future UI or copy work should keep these labels visible:

- owner: WordPress local permission, Cloud Addon transport, Npcink Cloud,
  Toolbox, WordPress AI plugin, or Core/Adapter;
- contract: liveness, signed entitlement read, runtime execute,
  runtime run detail, Site Knowledge transport, observability, or Agent
  feedback;
- posture: read-only projection, metadata-only upload, transport-only,
  suggestion-only runtime result, or blocked;
- secret posture: wrapper accepted, split credentials hidden, secret never
  displayed.

### P1 - Clarify Blocked States

Blocked states should be specific:

- unconfigured: connect through Cloud Portal or use manual fallback only for
  recovery;
- unverified: do not expose runtime helper surfaces yet;
- Cloud liveness failed: show service/detail link, not a simulated local fix;
- signed read failed: surface Cloud error code and re-verify action;
- entitlement unavailable: show read-only unavailable state, not local quota
  enforcement;
- no named read contract: mark the capability as Cloud-owned instead of adding
  a generic proxy.

### P2 - Borrow Setup/Test Discipline

The most useful next polish is not a new runtime feature. It is a review of
existing setup and test affordances:

- every test button should map to one named runtime client method;
- every result should say which endpoint or contract was tested;
- failures should separate local validation, signing/auth, Cloud service
  response, and contract mismatch;
- no test should send raw content, prompts, media bytes, provider credentials,
  or arbitrary Cloud paths.

### P2 - Keep Runtime Runs As Detail

`Troubleshooting > Runtime runs` can learn from mature support tools by showing
compact status and exact next actions. It should remain low-frequency detail:

- recent/status/result/retry for known Cloud-owned runs only;
- quota and retention projections as read-only Cloud data;
- no local retry queue, recovery workspace, scheduled review submission,
  Toolbox snapshot rebuilding, Core proposal creation, approval, or WordPress
  write.

## Decision Gate For New Addon Work

Before adding a new Cloud Addon surface, answer:

1. Is this a bounded connector/detail/read projection, or control/write truth?
2. Which named runtime client method and endpoint allowlist entry owns it?
3. Does the result require Cloud service-plane detail instead of local UI?
4. Does it expose or imply split credentials, provider configuration, prompt,
   router, preset, billing, workflow, or Site Knowledge lifecycle ownership?
5. Does it change any WordPress object or governance state?

If the answer points to control/write truth, service operations, provider
administration, or workflow runtime ownership, the work belongs outside Cloud
Addon.

## Suggested Next Artifact

The next implementation planning artifact should be a connector UI acceptance
checklist for existing Cloud Addon surfaces only. It should verify that each
surface shows:

- connection state and last verification evidence;
- owner/contract/posture labels;
- specific blocked-state guidance;
- no split credential display;
- no generic Cloud proxy path;
- no proposal, approval, billing, workflow, scheduler, Site Knowledge lifecycle,
  or WordPress write ownership.
