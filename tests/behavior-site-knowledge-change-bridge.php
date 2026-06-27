<?php
/**
 * Behavior tests for the Site Knowledge change bridge.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

maca_load_addon_classes();
require_once MACA_TEST_ROOT . '/includes/class-cloud-site-knowledge-change-bridge.php';

$GLOBALS['maca_comments'] = array();
$GLOBALS['maca_scheduled_events'] = array();

if ( ! function_exists( 'get_comment' ) ) {
	/**
	 * Minimal get_comment stub for bridge behavior tests.
	 *
	 * @param int $comment_id Comment id.
	 * @return object|null
	 */
	function get_comment( int $comment_id ) {
		return $GLOBALS['maca_comments'][ $comment_id ] ?? null;
	}
}

if ( ! function_exists( 'wp_next_scheduled' ) ) {
	/**
	 * Minimal scheduler lookup stub.
	 *
	 * @param string $hook Hook name.
	 * @return int|false
	 */
	function wp_next_scheduled( string $hook ) {
		return $GLOBALS['maca_scheduled_events'][ $hook ] ?? false;
	}
}

if ( ! function_exists( 'wp_schedule_single_event' ) ) {
	/**
	 * Minimal single-event scheduling stub.
	 *
	 * @param int    $timestamp Event timestamp.
	 * @param string $hook Hook name.
	 * @return bool
	 */
	function wp_schedule_single_event( int $timestamp, string $hook ): bool {
		$GLOBALS['maca_scheduled_events'][ $hook ] = $timestamp;
		return true;
	}
}

if ( ! function_exists( 'wp_clear_scheduled_hook' ) ) {
	/**
	 * Minimal scheduled-hook clearing stub.
	 *
	 * @param string $hook Hook name.
	 * @return void
	 */
	function wp_clear_scheduled_hook( string $hook ): void {
		unset( $GLOBALS['maca_scheduled_events'][ $hook ] );
	}
}

/**
 * Resets Site Knowledge bridge behavior state.
 *
 * @return void
 */
function maca_reset_site_knowledge_bridge_state(): void {
	maca_reset_test_state();
	$GLOBALS['maca_comments'] = array();
	$GLOBALS['maca_scheduled_events'] = array();
}

maca_reset_site_knowledge_bridge_state();
Npcink_Cloud_Site_Knowledge_Change_Bridge::handle_comment_posted(
	101,
	1,
	array( 'comment_post_ID' => 701 )
);
maca_assert(
	array() === get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::BUFFER_OPTION, array() ),
	'Behavior: Site Knowledge comment bridge is disabled until Cloud settings are configured.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( false );
Npcink_Cloud_Site_Knowledge_Change_Bridge::handle_comment_posted(
	1001,
	1,
	array( 'comment_post_ID' => 7001 )
);
$health = Npcink_Cloud_Site_Knowledge_Change_Bridge::health_snapshot();
maca_assert(
	array() === get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::BUFFER_OPTION, array() )
	&& false === (bool) ( $health['enabled'] ?? true )
	&& true === (bool) ( $health['configured'] ?? false )
	&& false === (bool) ( $health['verified'] ?? true )
	&& 'unverified' === (string) ( $health['status'] ?? '' ),
	'Behavior: Site Knowledge comment bridge waits for verified Cloud settings before buffering.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
Npcink_Cloud_Site_Knowledge_Change_Bridge::handle_comment_posted(
	102,
	0,
	array( 'comment_post_ID' => 702 )
);
maca_assert(
	array() === get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::BUFFER_OPTION, array() ),
	'Behavior: Site Knowledge comment bridge ignores unapproved new comments.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
Npcink_Cloud_Site_Knowledge_Change_Bridge::handle_comment_posted(
	103,
	1,
	array( 'comment_post_ID' => 703 )
);
$buffer = get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::BUFFER_OPTION, array() );
$status = get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::STATUS_OPTION, array() );
$health = Npcink_Cloud_Site_Knowledge_Change_Bridge::health_snapshot();
maca_assert(
	is_array( $buffer )
	&& array( 703 ) === array_map( 'absint', (array) ( $buffer['post_ids'] ?? array() ) )
	&& 703 === absint( $status['last_post_id'] ?? 0 )
	&& 1 === absint( $status['buffer_count'] ?? 0 )
	&& 1 === absint( $health['buffer_count'] ?? 0 )
	&& 'suggestion_only' === (string) ( $health['write_posture'] ?? '' )
	&& false === (bool) ( $health['wordpress_write_included'] ?? true )
	&& true === (bool) ( $health['enabled'] ?? false )
	&& 'queued' === (string) ( $health['status'] ?? '' )
	&& false === (bool) ( $health['legacy_toolbox_fallback'] ?? true )
	&& false !== wp_next_scheduled( Npcink_Cloud_Site_Knowledge_Change_Bridge::FLUSH_HOOK ),
	'Behavior: approved new comment data buffers the parent post for suggestion-only Site Knowledge refresh.'
);

$buffer_writes = absint( $GLOBALS['maca_option_update_counts'][ Npcink_Cloud_Site_Knowledge_Change_Bridge::BUFFER_OPTION ] ?? 0 );
$status_writes = absint( $GLOBALS['maca_option_update_counts'][ Npcink_Cloud_Site_Knowledge_Change_Bridge::STATUS_OPTION ] ?? 0 );
Npcink_Cloud_Site_Knowledge_Change_Bridge::handle_comment_posted(
	103,
	1,
	array( 'comment_post_ID' => 703 )
);
maca_assert(
	$buffer_writes === absint( $GLOBALS['maca_option_update_counts'][ Npcink_Cloud_Site_Knowledge_Change_Bridge::BUFFER_OPTION ] ?? 0 )
	&& $status_writes === absint( $GLOBALS['maca_option_update_counts'][ Npcink_Cloud_Site_Knowledge_Change_Bridge::STATUS_OPTION ] ?? 0 ),
	'Behavior: duplicate Site Knowledge changes do not rewrite buffer or status options.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
$GLOBALS['maca_comments'][104] = (object) array(
	'comment_ID'      => 104,
	'comment_post_ID' => 704,
);
Npcink_Cloud_Site_Knowledge_Change_Bridge::handle_comment_posted( 104, 1, null );
$buffer = get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::BUFFER_OPTION, array() );
$status = get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::STATUS_OPTION, array() );
maca_assert(
	is_array( $buffer )
	&& array( 704 ) === array_map( 'absint', (array) ( $buffer['post_ids'] ?? array() ) )
	&& 704 === absint( $status['last_post_id'] ?? 0 ),
	'Behavior: approved new comment fallback lookup buffers the parent post.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
Npcink_Cloud_Site_Knowledge_Change_Bridge::handle_comment_status_transition(
	'approved',
	'hold',
	(object) array( 'comment_post_ID' => 705 )
);
$buffer = get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::BUFFER_OPTION, array() );
maca_assert(
	is_array( $buffer )
	&& array( 705 ) === array_map( 'absint', (array) ( $buffer['post_ids'] ?? array() ) ),
	'Behavior: comment approval transition buffers the parent post.'
);
