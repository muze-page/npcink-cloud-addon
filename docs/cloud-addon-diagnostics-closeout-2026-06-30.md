# Cloud Addon Diagnostics Closeout

Status: accepted

Date: 2026-06-30

## Context

Toolbox removed its local Cloud Checks and Troubleshooting Checks surface. It no
longer owns basic Cloud diagnostics for AI connection, Hosted Runtime, Cloud
search, Cloud image/source readiness, quota, entitlement, or service health.

`npcink-cloud-addon` is the correct local WordPress-side home for those details
because this plugin already owns hosted credential entry, signed Cloud
transport, connection verification, Cloud service entry links, entitlement
summary, and read-only monitoring detail.

The target was not to recreate the old Toolbox page. The target was to provide
one bounded Cloud connector diagnostics entry that reduces duplicated local
entry points and keeps product workflows in their owning plugins.

## Boundary Decision

Cloud Addon may show:

- Cloud Base URL and whether a Cloud API Key has been saved.
- liveness and signed Cloud read verification status.
- entitlement, quota, usage summary, and Cloud detail links.
- hosted runtime entitlement detail already returned by Cloud.
- capability readiness rows when there is an explicit addon-side contract.
- Site Knowledge bridge status for signed runtime transport.
- a dedicated Site Knowledge tab with local delivery consent, bounded manual
  public content refresh transport, and local administrator start/rebuild/delete
  index intents.
- metadata-only monitoring status and read-only aggregate summaries.

Cloud Addon must not become:

- an ability registry, workflow registry, router, prompt, preset, MCP, or Agent
  Gateway control plane;
- a Developer diagnostics route or service operations console;
- a provider operations UI;
- Tavily, Unsplash, Cloud search, image source search, or image generation
  product UX;
- approval, proposal, billing, scheduler, workflow/task queue, or WordPress
  final-write truth.

Secrets remain server-side. The admin page must not display split credentials,
stored secrets, raw provider credentials, internal tokens, or raw signed request
data.

## Implemented Shape

The verified Cloud Addon admin page now has `Diagnostics` and `Site Knowledge`
tabs. They are visible only in the verified flow alongside Status, Details, and
Advanced.

The diagnostics table shows:

- connection storage state for Cloud Base URL and Cloud API Key;
- Cloud liveness based on the existing save-and-verify flow;
- signed Cloud read status through the existing entitlement summary;
- entitlement and quota availability;
- hosted runtime detail when Cloud entitlement includes it;
- Platform Models and provider readiness as Cloud-owned unless a future addon
  read contract is added;
- Cloud web search and image source search as not connected when there is no
  addon contract;
- Cloud image generation as a scene runtime only, not a provider/source tool;
- Site Knowledge bridge status as signed runtime transport only;
- Site Knowledge local delivery consent, manual public content refresh, and
  explicit administrator start/rebuild/delete intents as delivery transport only;
- monitoring detail as metadata-only and read-only.

Advanced raw status is folded behind a disclosure and contains sanitized local
status fields only. It omits secrets and split credentials.

The Site Knowledge tab shows bridge delivery status, local delivery consent, a
manual `Request public content refresh` action, and explicit administrator
start/rebuild/delete index intents. These actions only send local intent and
bounded public WordPress manifests through the existing signed delivery
transport. Turning delivery off stops future refresh/start/rebuild transport but
does not delete existing Cloud index data; the confirmed delete action remains
available. The addon does not own index execution truth, freshness policy,
collection management, or deep troubleshooting.

## Related Transport Closeout

The branch also records a Site Knowledge runtime bridge for Toolbox. The bridge
registers `npcink_toolbox_site_knowledge_cloud_request` and forwards only the
known Site Knowledge ability/contract pairs through `POST /v1/runtime/execute`:

- `npcink-cloud/site-knowledge-search` with `site_knowledge_search.v1`
- `npcink-cloud/site-knowledge-status` with `site_knowledge_status.v1`
- `npcink-cloud/site-knowledge-sync` with `site_knowledge_sync.v1`

Every payload must remain `write_posture=suggestion_only`, avoid credential-like
fields, and avoid local WordPress writes. Cloud owns Site Knowledge vector,
index, freshness, and collection lifecycle.

## Documentation and Tests

The following docs now describe the new ownership boundary:

- `README.md`
- `docs/admin-surface-standard.md`
- `docs/cloud-addon-boundary.md`
- `docs/cloud-runtime-client-contract.md`

Static contract tests assert that Diagnostics stays a bounded status/detail
surface, does not register Developer diagnostics routes, does not add product
search tools, and does not simulate missing Cloud service contracts locally.

Language files were refreshed and zh_CN strings were filled for the new admin
diagnostics text and adjacent Site Knowledge runtime bridge errors.

## Verification

Commands run:

```bash
composer run test:all
git diff --check
rg "/v1/runtime/workflows/runs|wp_insert_post|wp_update_post" --glob '*.php' --glob '!build/**' .
WP_CLI_BIN=/opt/homebrew/bin/wp composer run i18n:refresh
WP_CLI_BIN=/opt/homebrew/bin/wp composer run i18n:make-mo
composer run ai:i18n:audit
```

Results:

- `composer run test:all` passed.
- `git diff --check` passed.
- forbidden runtime workflow endpoint and direct WordPress write grep returned no
  matches.
- i18n refresh and MO generation passed when using the local `wp` executable;
  the default `/tmp/wp-cli.phar` path was not present.
- `composer run ai:i18n:audit` exited successfully. It still reports existing
  AI plugin shim missing/stale candidates outside this diagnostics workstream.
- There is no root `package.json` or `pnpm-lock.yaml`, so `pnpm run
  check:cloud:addon-seam` and `pnpm run check:risk` are not available in this
  repository.

## Commit Split

The closeout branch keeps the work split by responsibility:

- `Add Site Knowledge runtime bridge`
- `Add bounded Cloud diagnostics surface`
- this documentation closeout

## Follow-Up Rule

If Cloud later exposes explicit read contracts for Platform Models, provider
readiness, web search, image source readiness, or image generation status, add a
named runtime client method, update the endpoint allowlist, document the
contract, and add focused contract tests before showing a live diagnostics row.

Do not add generic request proxies, Developer diagnostics routes, provider tool
actions, workflow controls, or local Cloud service simulations to fill gaps.
