<?php
/**
 * Behavior tests for Agent feedback Cloud transport.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

maca_load_addon_classes();

maca_reset_test_state();
maca_seed_settings( true );
$client = new Npcink_Cloud_Runtime_Client( Npcink_Cloud_Addon_Settings::get_settings() );

$invalid = $client->send_agent_feedback_event(
	array(
		'contract_version' => 'wrong_contract.v1',
	)
);
maca_assert(
	is_wp_error( $invalid ) && 'cloud_agent_feedback_payload_invalid' === $invalid->get_error_code(),
	'Behavior: Agent feedback transport rejects non-contract payloads.'
);

$result = $client->send_agent_feedback_event(
	array(
		'contract_version' => 'cloud_agent_feedback.v1',
		'agent_id'         => 'site_knowledge_suggestion_agent',
		'source_runtime'   => 'site_knowledge',
		'handoff_type'     => 'proposal_input',
		'local_surface'    => 'toolbox_site_knowledge',
		'local_outcome'    => 'accepted',
		'created_at'       => '2026-06-07T00:00:00Z',
	),
	'agent_feedback_trace',
	'agent-feedback-test'
);
$request = $GLOBALS['maca_http_requests'][0] ?? array();
maca_assert(
	is_array( $result )
	&& false !== strpos( (string) ( $request['url'] ?? '' ), '/v1/agent-feedback/events' )
	&& 'agent-feedback-test' === (string) ( $request['args']['headers']['Idempotency-Key'] ?? '' ),
	'Behavior: Agent feedback transport posts one signed event to the explicit Cloud endpoint.'
);
