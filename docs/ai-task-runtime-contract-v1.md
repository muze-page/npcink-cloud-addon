# Registered AI task runtime contract v1

## Purpose

`ai_task_contract.v1` lets a registered WordPress Ability describe the minimum
runtime semantics Cloud needs. It removes the need to add one Addon transport
method for every title, summary, excerpt, SEO, or classification feature.

WordPress remains the source of truth for the Ability name, permissions, input
schema, output schema, instruction, and any later write. The Addon only reads
that truth, validates a fixed vocabulary, signs the request, and transports it
through the existing `/v1/runtime/execute` endpoint.

## Publishing a contract

An Ability may include this metadata when it is registered:

```php
'meta' => array(
	'npcink_ai_task_contract' => array(
		'task'                 => 'seo_headline',
		'task_family'          => 'generation',
		'context_requirements' => array( 'current_content', 'site_style_profile' ),
		'constraints'          => array( 'single_value', 'source_grounded', 'no_new_numbers' ),
	),
),
```

The Addon projects the registered Ability name and output schema itself. It
always forces `write_posture=suggestion_only`. Current ai-wp-admin abilities
without metadata use the Addon's bounded built-in projection.

Public PHP seams:

- `npcink_cloud_addon_project_ai_task_contract( $ability_name )` returns the
  validated read-only projection.
- `npcink_cloud_addon_execute_registered_ai_task_runtime( $ability_name,
  $request, $trace_id, $idempotency_key )` sends one bounded scene request.

The execution helper does not execute the Ability or bypass its permission
callback. Callers must perform normal Ability permission and input handling
before invoking the model-runtime seam.

## Connector runtime envelope

The execution helper sends the task projection through
`cloud_connector_runtime.v1` with verified top-level `site_id`,
`ability_name=npcink-cloud/connector-runtime`, `channel=editor`, and a connector
identity containing canonical `site_url`, `platform_kind=wordpress`,
`connector_id=npcink-cloud-addon`, connector version, and
`suggestion_only=true`.

The WordPress task is nested as `wordpress_operation.v1` with exactly
`contract_version`, `task`, and `request`. For `title_generation`,
`content_summary`, and `content_rewrite`, the request uses the actual single AI
Client user message as `source_text` plus optional `system_instruction`; legacy
prompt and post fields are rejected. Text results use
`cloud_connector_result.v1` and must remain `suggestion_only=true`, identify
`connector_id=npcink-cloud-addon`, and carry a matching
`wordpress_operation.v1` task before output is read from
`response.data.result.output.output_text`.

## Fixed vocabulary and boundary

Task families are limited to `generation`, `classification`, `transformation`,
and `analysis`. Context requirements and output constraints are also
allowlisted in code. Provider choice, retrieval Top-K, score thresholds, token
budgets, and retry detail remain Cloud-owned runtime policy.

The contract is not a task registry, prompt store, chat API, workflow registry,
or WordPress write authorization. Generic messages, tools, streams,
credentials, and direct writes remain forbidden.
