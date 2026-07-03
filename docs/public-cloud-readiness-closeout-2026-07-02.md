# Public Cloud Readiness Closeout - 2026-07-02

Status: active handoff for the next public Cloud smoke test.

This document summarizes the release discussion and follow-up work that moved
the addon from "package a new version" to "verify public Cloud onboarding with
the existing version."

## Decision

Do not publish a new plugin version solely for the Cloud Base URL default.

The current WordPress.org `0.1.1` package already contains the environment-aware
default:

- local WordPress environments resolve to `http://127.0.0.1:8010` or
  `http://localhost:8010/` depending on package revision and local code;
- non-local WordPress environments resolve to `https://cloud.npc.ink`;
- an explicitly saved `base_url` remains authoritative over the default;
- `NPCINK_CLOUD_ADDON_DEFAULT_BASE_URL` can still override the default for a
  controlled smoke test.

The local development default is intentional and should not be removed. It lets
local sites keep testing against the local Cloud service while packaged public
sites point to Npcink Cloud.

## What Changed Locally

The follow-up commit `2d6b3a5` added the public Cloud onboarding checklist and
release-readiness hardening:

- documented the public onboarding smoke path in
  `docs/public-cloud-onboarding-checklist.md`;
- linked that checklist from `README.md`;
- made the runtime retry admin action sanitize submitted `runtime_run_id`
  before retry dispatch;
- added static contract coverage for the onboarding checklist and retry
  sanitization.

No version bump was made, and the release default URL strategy was not changed.

## Verification Performed

The local addon gates passed:

```bash
composer run test:all
git diff --check
rg "/v1/runtime/workflows/runs|wp_insert_post|wp_update_post" --glob '*.php' --glob '!build/**' .
```

Full release verification passed when using the working Homebrew WP-CLI binary:

```bash
WP_CLI_BIN=/opt/homebrew/Cellar/wp-cli/2.12.0/bin/wp composer run release:verify
```

Plugin Check completed with no errors after the retry run ID sanitization fix.

Public Cloud entrypoints were checked:

```bash
curl -fsS https://cloud.npc.ink/health/live
curl -fsSI https://cloud.npc.ink/portal/sites
```

Observed result:

- `/health/live` returned a production healthy response.
- `/portal/sites` redirected to Portal login, which is expected for an
  unauthenticated authorization entry.

## Boundary Conclusion

This work stays within the standalone Cloud connector boundary.

The addon may:

- resolve the public Cloud Base URL on non-local sites;
- open Cloud Portal authorization;
- exchange a one-time authorization code;
- store the returned Cloud Base URL and Cloud API Key wrapper;
- verify signed connectivity;
- show read-only status, entitlement, monitoring, Agent feedback, and runtime
  detail projections.

The addon must still not:

- own proposal, approval, replace, rollback, or WordPress write paths;
- expose split `site_id`, `key_id`, or `secret` fields in the UI;
- create router, prompt, preset, workflow, scheduler, queue, billing, or Site
  Knowledge index lifecycle truth;
- reintroduce `/v1/runtime/workflows/runs`;
- turn Cloud Portal detail surfaces into a second WordPress control plane.

If future onboarding fixes require any of those capabilities, the fix belongs
in local Core, Adapter, Toolbox, or Cloud service-plane code instead of this
addon.

## Next Step

Run one clean public-site end-to-end smoke:

1. Start from a WordPress site with no stored `npcink_cloud_addon_settings`
   option.
2. Install and activate the current WordPress.org package.
3. Open `Npcink > Cloud Addon`.
4. Confirm the unresolved Cloud value is `https://cloud.npc.ink/`.
5. Open the primary "Add this site in Npcink Cloud" action.
6. Complete Cloud Portal login and site authorization.
7. Confirm callback return to WordPress.
8. Confirm code exchange, storage, and immediate signed verification.
9. Confirm the default page shows verified connection state and a read-only
   entitlement summary.
10. Confirm monitoring remains disabled until explicit administrator opt-in.

Use `docs/public-cloud-onboarding-checklist.md` as the operational checklist for
that smoke test.
