# Cloud Bulk Article Run Addon Seam

Status: prohibited and deprecated planning seam.

This document records that `magick-ai-cloud-addon` must not expose Cloud
article writing generation, Cloud bulk article runs, or Cloud article artifact
imports to local plugins.

## Decision

The addon remains a thin Cloud connector. For writing-related product surfaces
it may show only connection, health, entitlement, usage, diagnostics, or other
non-writing service detail.

The addon must not:

- read Cloud article draft artifacts;
- expose Cloud-produced `article_write_plan` candidates;
- import Cloud article items into Toolbox;
- normalize Cloud article run summaries;
- add a bulk article tab or dashboard;
- create Core proposals;
- approve, preflight, publish, schedule, or execute WordPress writes;
- turn Cloud run status into local recipe or proposal state.

## Replacement Path

Article drafting is a local Ability recipe. The safe path remains:

```text
local Ability recipe
  -> local/operator-reviewed artifacts
  -> magick-ai-toolbox/build-article-write-plan
  -> Core /proposals/from-plan
  -> Core approval and commit preflight
  -> Adapter executes magick-ai/create-draft through WordPress Abilities API
```

## Allowed Addon Role

The addon may:

- store and verify Cloud Base URL and Cloud API Key;
- sign allowed non-writing Cloud runtime requests;
- read health, entitlement, usage, diagnostics, and status detail;
- transport metadata-only observability when explicitly enabled;
- expose bounded read-only service summaries.

The addon must not become a writing connector, article import surface, queue
owner, workflow runtime, approval owner, proposal owner, or WordPress write
owner.

## Endpoint Boundary

Do not add a named bulk article endpoint, generic Cloud proxy, local workflow
run route, or article import helper in this addon. Existing run/result methods
must not be repurposed for hosted article writing generation.

## UI Boundary

The settings page and addon detail surfaces must not show:

- bulk article runs;
- article item import controls;
- generated article bodies;
- Cloud writing task state;
- publish/schedule controls;
- Core proposal mutation controls.

## Guardrail Phrase

Cloud Addon connects the site to allowed Cloud service detail. It does not
provide writing generation, article import, or publishing.

## Rejected Product Language

Do not describe the addon as a Cloud writing assistant, Cloud article
generator, hosted article drafting connector, bulk article writer, or article
import bridge. Use Cloud connector, service detail, entitlement, health, and
diagnostics language instead.

Rejected names: Cloud writing assistant, Cloud article generator, hosted
article drafting connector.

Use Cloud connector, service detail, entitlement, health, and diagnostics
language.

Cloud connector, service detail, entitlement, health, and diagnostics language.
