# WordPress.org Listing Draft - English

## Plugin Name

Magick AI Cloud Addon

## Short Description

Thin Cloud connector for Magick AI hosted runtime access, signing, health
checks, and entitlement summaries.

## Tags

magick ai, cloud, hosted runtime, ai, connector

## Description

Magick AI Cloud Addon connects a local WordPress site to `magick-ai-cloud`.

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

- WordPress administrators connecting a local Magick AI setup to Magick AI
  Cloud.
- Magick AI deployments that need hosted runtime access while preserving local
  governance truth.
- Developers who need a narrow server-side Cloud transport seam.

## Requirements

- WordPress 7.0 or later.
- PHP 8.0 or later.
- A Cloud Base URL and Cloud API Key issued by Magick AI Cloud.

## Series Boundary

In the Magick AI plugin family:

- Magick AI Abilities owns ability definitions and callbacks.
- Magick AI Core owns governance, approval, preflight, and audit.
- Magick AI Adapter owns OpenClaw channel adaptation.
- Magick AI Cloud Addon owns Cloud service connection and signing.

This separation keeps Cloud as a hosted runtime and service enhancement layer
without moving local governance, approval, prompts, router truth, or final
WordPress writes into the addon.
