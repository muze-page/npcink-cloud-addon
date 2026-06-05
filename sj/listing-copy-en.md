# WordPress.org Listing Draft - English

## Plugin Name

Npcink Cloud Addon

## Short Description

Thin Cloud connector for Npcink hosted runtime access, signing, health
checks, and entitlement summaries.

## Tags

magick ai, cloud, hosted runtime, ai, connector

## Description

Npcink Cloud Addon connects a local WordPress site to `npcink-cloud`.

It stores the Cloud Base URL and Cloud API Key, parses Cloud-issued keys,
signs runtime requests, probes Cloud health, and reads Cloud entitlement
summaries for local display.

The addon is intentionally thin. It is the cloud service connection layer, not
the local control plane, governance layer, ability registry, workflow engine,
router owner, prompt owner, preset owner, queue owner, scheduler, billing truth
source, or WordPress write executor.

Local WordPress remains the control plane. Final WordPress writes must still go
through local Core proposal, approval, preflight, and apply paths. Cloud remains
a hosted runtime and service enhancement layer.

## Key Features

- Store Cloud Base URL and Cloud API Key settings.
- Parse customer-facing Cloud API Keys into signing credentials.
- Sign hosted runtime requests server-side.
- Probe Cloud liveness and signed verification status.
- Read Cloud entitlement summaries for local display.
- Expose a small PHP interface for local plugins.
- Keep Cloud connection separate from governance, abilities, adapter routing,
  model prompts, presets, queues, schedulers, and final WordPress writes.

## Who This Is For

- WordPress administrators connecting a local Npcink setup to Npcink
  Cloud.
- Npcink deployments that need hosted runtime access while preserving local
  governance truth.
- Developers who need a narrow server-side Cloud transport seam.

## Requirements

- WordPress 7.0 or later.
- PHP 8.0 or later.
- A Cloud Base URL and Cloud API Key issued by Npcink Cloud.

## External Services

This plugin connects to the Npcink Cloud service configured by the site
administrator through the Cloud Base URL setting.

The plugin contacts the configured Cloud service only after an administrator
enters a Cloud Base URL and Cloud API Key, saves the settings, verifies the
connection, or a local Npcink component explicitly uses the Cloud runtime
client.

Requests may include the configured site identifier, key identifier, request
timestamp, nonce, trace identifier, idempotency key, HMAC signature headers,
runtime request payloads supplied by local Npcink components, and read-only
requests for health, run status, run result, usage statistics, and entitlement
summaries. The stored Cloud API Key secret is used server-side for request
signing and is not printed in wp-admin.

The configured Cloud service is responsible for its own privacy policy, terms
of service, data retention, and account/key issuance. Site administrators
should only connect this plugin to a Cloud service whose terms and privacy
policy they have reviewed.

## Series Boundary

In the Npcink plugin family:

- Npcink Abilities Toolkit owns ability definitions and callbacks.
- Npcink Governance Core owns governance, approval, preflight, and audit.
- Npcink OpenClaw Adapter owns OpenClaw channel adaptation.
- Npcink Cloud Addon owns Cloud service connection and signing.

This separation keeps Cloud as a hosted runtime and service enhancement layer
without moving local governance, approval, prompts, router truth, or final
WordPress writes into the addon.
