# Site Knowledge Vector Operations

Status: active.

Npcink Cloud Addon is the WordPress-side connector for Cloud Site Knowledge. It
does not own vector indexing or vector database lifecycle.

## Local Permission

The settings page action that requests public content refresh requires `manage_options`,
a valid nonce, and verified Cloud settings. Editor-facing Toolbox usage may
consume search results, but it must not trigger refresh, rebuild, delete, or
collection lifecycle actions.

## Allowed Addon Operations

- Listen for published `post` and `page` changes.
- Listen for approved comment changes attached to public posts or pages.
- Buffer affected public ids for bounded delivery durability.
- Send `site_knowledge_sync.v1` only with `sync_mode=refresh`.
- Forward known Toolbox Site Knowledge search, status, and refresh contracts
  through `POST /v1/runtime/execute`.
- Show shallow connector state, buffered public changes, last delivery, last
  error, next flush, and a link to Cloud Site Knowledge.

## Forbidden Addon Operations

The addon must reject or omit:

- `sync_mode=rebuild`
- `sync_mode=delete`
- collection create, update, migrate, or delete controls
- embedding provider settings
- Qdrant or other vector database endpoint settings
- embedding dimensions and collection names
- stale-index policy controls
- local vector stores
- local indexing queues or scheduler truth
- direct WordPress writes from Site Knowledge results

## Content Admission

Refresh payloads may contain only public site content:

- published posts and pages;
- bounded title, excerpt/content, permalink, modified time, post id, and post
  type fields;
- approved comments attached to public posts or pages;
- truncation and payload-limit metadata.

Refresh payloads must not contain:

- drafts, private posts, password-protected posts, or trashed content;
- user emails, orders, memberships, form submissions, support tickets, or
  private CRM data;
- provider credentials, API keys, cookies, nonces, tokens, or signing fields;
- Core approval records, proposal records, or audit truth;
- final WordPress write instructions.

Cloud Site Knowledge remains the owner for embedding, vector storage,
collection lifecycle, freshness policy, re-indexing, rerank, quota, and deep
diagnostics.
