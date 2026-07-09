# Connect UI and zh_CN Localization Closeout

Status: development closeout for the 2026-07-09 admin surface and localization
iteration.

## Context

This iteration started from two operator-facing problems on the Cloud Addon
settings page:

- the default connection area exposed Cloud endpoint detail too prominently and
  did not give third-party self-hosted Cloud deployments a clear, bounded path;
- several fixed admin strings still appeared in English on a zh_CN WordPress
  site, even after the bundled plugin language files were updated.

The resulting changes keep the addon inside its connector boundary. The addon
may help a local administrator start Cloud authorization, show connection
status, and expose fixed admin labels in Chinese. It must not become a Cloud
site manager, billing console, runtime control plane, workflow registry, prompt
or model center, or WordPress write owner.

## Admin Surface Decisions

The default Connect view should stay simple:

- show the current connection state;
- show the Cloud authorization target and current WordPress site URL as context;
- provide one primary action to add or activate the current site in Npcink
  Cloud.

Self-hosted Cloud support belongs behind a folded advanced section. The field is
named around the Cloud base URL because the only thing it changes is the
authorization target. Submitting the advanced form starts the same Cloud
authorization flow against the supplied compatible endpoint.

The advanced form must not save partial credentials. Cloud site creation,
activation, key issuance, billing, models, router, workflows, and runtime
policy remain Cloud-owned. The local addon only stores the returned connector
settings after the authorization callback completes.

Both authorization entry points open in a new browser tab. That keeps the
WordPress admin page available while Cloud creates or activates the site
connection and reduces the chance that an interrupted redirect leaves the user
without local context.

## Layout Decisions

The previous table made the Connect card look like a diagnostics table even
when the user only needed one action. The revised layout treats endpoint and
site URL values as compact context beside the action instead of making them the
main page content.

Low-frequency or operator-only inputs remain in explicit details. The page
should not stack broad panels, repeated headings, or secondary controls above
the primary connection task.

The layout follows the broader admin surface standard:

- primary actions sit beside the relevant state;
- fixed admin terminology is translated in zh_CN;
- dynamic IDs, contract names, provider IDs, model IDs, slugs, and source-owned
  metadata remain untranslated;
- internal control-plane concepts stay out of the default WordPress admin UI.

## Localization Root Cause

WordPress loads site-level language packs from
`wp-content/languages/plugins/` before plugin-bundled translation files. A stale
site-level file such as `npcink-cloud-addon-zh_CN.mo` can therefore mask newer
translations shipped inside this plugin.

The fix is not to empty those language-pack files. Empty files still behave like
installed language packs and can keep masking bundled updates. For local
development, move or rename the stale files out of the active language-pack
path, then regenerate the plugin-bundled PO/MO files.

This repository now also carries a narrow addon-domain zh_CN fallback for fixed
admin strings so that high-traffic settings text does not fall back to English
when the active language pack is incomplete.

## Addon-Domain Fallback Boundary

The addon-domain fallback is intentionally narrow:

- it runs only in `wp-admin`;
- it runs only for Chinese locales;
- it targets only the `npcink-cloud-addon` text domain;
- it preserves existing non-empty translations from normal WordPress loading;
- it maps fixed source strings only;
- it does not translate dynamic ability metadata, provider IDs, model IDs,
  contract IDs, JSON fields, or slugs;
- it does not call Cloud runtime, external translation services, or remote HTTP
  APIs.

This keeps the fallback as a local usability layer, not a second language
service or product-control plane.

The existing WordPress AI plugin compatibility shim remains separate. That shim
targets the upstream `ai` text domain for fixed UI strings only and should be
maintained through its own audit workflow.

## Maintenance Workflow

When fixed addon UI strings change, use the normal gettext workflow first:

```sh
WP_CLI_BIN=/opt/homebrew/bin/wp composer run i18n:refresh
WP_CLI_BIN=/opt/homebrew/bin/wp composer run i18n:make-mo
```

Then update the addon-domain fallback only for fixed strings that still need
runtime coverage in local zh_CN admin screens.

Required verification for this surface:

```sh
composer run test:all
composer run check:wporg
git diff --check
rg "/v1/runtime/workflows/runs|wp_insert_post|wp_update_post" --glob '*.php' --glob '!build/**' .
```

If the local WordPress site has stale files under
`wp-content/languages/plugins/npcink-cloud-addon-zh_CN.*`, move them out of the
active path before testing bundled translations.

## Non-Goals

These remain out of scope for the addon:

- split credential fields in the UI;
- saving partial authorization details before Cloud callback completion;
- Cloud object management, billing, model, router, workflow, or runtime policy
  controls;
- dynamic ability metadata translation;
- proposal, approval, audit, or WordPress write ownership;
- reintroducing `/v1/runtime/workflows/runs`.

## Verification Notes

The implementation adds behavior and static coverage for the zh_CN fallback,
connect authorization behavior, and language-file completeness. The fixed
Chinese PO file should not contain empty fixed-string entries beyond the PO
header marker. The boundary grep should continue to return no PHP matches for
forbidden runtime workflow or WordPress write ownership strings.
