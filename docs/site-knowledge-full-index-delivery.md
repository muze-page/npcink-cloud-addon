# Site Knowledge Full-Index Delivery

`npcink-cloud-addon` delivers an administrator-requested `start` or `rebuild`
across the site's bounded public `post` and `page` corpus instead of sampling
only the most recently modified items.

The Addon remains a transport bridge:

- WordPress is the source-content owner and selects at most 10,000 published
  post/page IDs for one explicit administrator action.
- The Addon sends batches of at most 200 public manifests through the existing
  `POST /v1/runtime/execute` contract.
- A rebuild's first batch uses `sync_mode=rebuild` with empty `post_ids`, so
  Cloud clears the site's previous index before indexing that batch. Remaining
  batches use `sync_mode=refresh`.
- Each batch has a stable idempotency key. The Addon stores only its existing
  shallow last-delivery status; it does not create a local index job, queue,
  scheduler, retry ledger, or vector truth.
- Public text is capped at 1,800 UTF-8 bytes per document. Incidental embedded
  URLs, email addresses, and phone-like sequences are removed before delivery;
  the canonical public permalink remains a separate allowlisted field.

Cloud continues to own queued execution, index lifecycle, vector storage,
embedding execution, freshness, and diagnostics. Every request remains
`suggestion_only` and cannot write WordPress content.
