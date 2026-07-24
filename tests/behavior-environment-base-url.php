<?php
/**
 * Behavior tests for environment-specific Cloud Base URL isolation.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

maca_load_addon_classes();

maca_reset_test_state();
$GLOBALS['maca_wp_environment_type'] = 'local';

maca_assert(
	'http://localhost:8010' === Npcink_Cloud_Addon_Settings::get_default_base_url(),
	'Behavior: a generic local WordPress environment defaults to the loopback Cloud preview.'
);

$local_public_settings = Npcink_Cloud_Addon_Settings::normalize_settings(
	array(
		'base_url' => 'https://cloud.npc.ink/',
		'site_id'  => 'site_local_public_rejected',
		'key_id'   => 'key_local_public_rejected',
		'secret'   => 'secret_local_public_rejected',
		'verified' => true,
	)
);
maca_assert(
	'' === (string) $local_public_settings['base_url']
	&& 'http://localhost:8010' === Npcink_Cloud_Addon_Settings::get_effective_base_url( $local_public_settings ),
	'Behavior: local WordPress rejects the public Cloud endpoint and falls back to its loopback preview entry.'
);

$local_public_save = Npcink_Cloud_Addon_Settings::build_settings_from_admin_payload(
	array( 'base_url' => 'https://cloud.npc.ink/' )
);
maca_assert(
	is_wp_error( $local_public_save )
	&& 'invalid_cloud_base_url' === $local_public_save->get_error_code(),
	'Behavior: local WordPress cannot save the public Cloud endpoint.'
);

$GLOBALS['maca_wp_environment_type'] = 'production';
$production_settings = Npcink_Cloud_Addon_Settings::normalize_settings(
	array( 'base_url' => 'https://cloud.npc.ink/' )
);
maca_assert(
	'https://cloud.npc.ink' === Npcink_Cloud_Addon_Settings::get_default_base_url()
	&& 'https://cloud.npc.ink' === (string) $production_settings['base_url'],
	'Behavior: non-local WordPress retains the canonical public Cloud endpoint.'
);

$GLOBALS['maca_wp_environment_type'] = 'local';
