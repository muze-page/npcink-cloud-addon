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
$GLOBALS['maca_posts'] = array();
$GLOBALS['maca_scheduled_events'] = array();
$GLOBALS['maca_post_terms'] = array();

if ( ! function_exists( 'wp_get_post_terms' ) ) {
	function wp_get_post_terms( int $post_id, string $taxonomy, array $args = array() ): array {
		unset( $args );
		return $GLOBALS['maca_post_terms'][ $post_id ][ $taxonomy ] ?? array();
	}
}

if ( ! function_exists( 'get_posts' ) ) {
	/**
	 * Minimal get_posts stub for bridge behavior tests.
	 *
	 * @param array<string,mixed> $args Query args.
	 * @return array<int,int>
	 */
	function get_posts( array $args = array() ): array {
		$limit = absint( $args['posts_per_page'] ?? 50 );
		$ids   = array();
		foreach ( $GLOBALS['maca_posts'] as $post_id => $post ) {
			if ( 'publish' !== (string) ( $post->post_status ?? '' ) ) {
				continue;
			}
			$ids[] = absint( $post_id );
		}
		return array_slice( $ids, 0, max( 1, $limit ) );
	}
}

if ( ! function_exists( 'get_post' ) ) {
	/**
	 * Minimal get_post stub for bridge behavior tests.
	 *
	 * @param int $post_id Post id.
	 * @return object|null
	 */
	function get_post( int $post_id ) {
		return $GLOBALS['maca_posts'][ $post_id ] ?? null;
	}
}

if ( ! function_exists( 'get_the_title' ) ) {
	/**
	 * Minimal get_the_title stub for bridge behavior tests.
	 *
	 * @param int $post_id Post id.
	 * @return string
	 */
	function get_the_title( int $post_id ): string {
		$post = get_post( $post_id );
		return is_object( $post ) ? (string) ( $post->post_title ?? '' ) : '';
	}
}

if ( ! function_exists( 'get_permalink' ) ) {
	/**
	 * Minimal get_permalink stub for bridge behavior tests.
	 *
	 * @param int $post_id Post id.
	 * @return string
	 */
	function get_permalink( int $post_id ): string {
		return 'https://example.test/?p=' . absint( $post_id );
	}
}

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
	$GLOBALS['maca_posts'] = array();
	$GLOBALS['maca_scheduled_events'] = array();
	$GLOBALS['maca_post_terms'] = array();
}

/**
 * Adds a public post/page fixture.
 *
 * @param int    $post_id Post id.
 * @param string $type Post type.
 * @return void
 */
function maca_add_public_post_fixture( int $post_id, string $type = 'post' ): void {
	$GLOBALS['maca_posts'][ $post_id ] = (object) array(
		'ID'                => $post_id,
		'post_type'         => $type,
		'post_status'       => 'publish',
		'post_title'        => 'Public fixture ' . $post_id,
		'post_excerpt'      => 'Excerpt ' . $post_id,
		'post_content'      => 'Public content for Site Knowledge ' . $post_id,
		'post_modified_gmt' => '2026-06-30 00:00:00',
	);
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
	&& 'site_knowledge_change_bridge_status.v1' === (string) ( $health['status_contract'] ?? '' )
	&& 'change_bridge' === (string) ( $health['preferred_status_field'] ?? '' )
	&& 'buffer_count' === (string) ( $health['preferred_count_field'] ?? '' )
	&& 'bounded_delivery_buffer' === (string) ( $health['buffer_semantics'] ?? '' )
	&& 'local_delivery_durability_only' === (string) ( $health['buffer_truth'] ?? '' )
		&& 'cloud_addon' === (string) ( $health['delivery_truth_owner'] ?? '' )
		&& 'cloud_service' === (string) ( $health['index_execution_owner'] ?? '' )
		&& 'local_wordpress_host' === (string) ( $health['ownership']['source_content_owner'] ?? '' )
		&& 'cloud_addon' === (string) ( $health['ownership']['delivery_bridge_owner'] ?? '' )
		&& 'cloud_service' === (string) ( $health['ownership']['vector_storage_owner'] ?? '' )
		&& 'local_wordpress_host' === (string) ( $health['ownership']['final_write_owner'] ?? '' )
		&& true === (bool) ( $health['truth_boundaries']['cloud_is_index_truth'] ?? false )
		&& false === (bool) ( $health['truth_boundaries']['cloud_is_wordpress_control_plane'] ?? true )
		&& false === (bool) ( $health['truth_boundaries']['cloud_creates_wordpress_writes'] ?? true )
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

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
maca_set_site_knowledge_delivery_enabled( false );
Npcink_Cloud_Site_Knowledge_Change_Bridge::handle_comment_posted(
	106,
	1,
	array( 'comment_post_ID' => 706 )
);
$health = Npcink_Cloud_Site_Knowledge_Change_Bridge::health_snapshot();
maca_assert(
	array() === get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::BUFFER_OPTION, array() )
	&& false === (bool) ( $health['enabled'] ?? true )
	&& false === (bool) ( $health['delivery_enabled'] ?? true )
	&& 'disabled' === (string) ( $health['status'] ?? '' ),
	'Behavior: disabled Site Knowledge delivery consent stops automatic public content buffering.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
update_option(
	Npcink_Cloud_Site_Knowledge_Change_Bridge::BUFFER_OPTION,
	array(
		'post_ids' => array( 707 ),
		'attempts' => 2,
	),
	false
);
update_option(
	Npcink_Cloud_Site_Knowledge_Change_Bridge::STATUS_OPTION,
	array(
		'last_delivery_ok' => false,
		'last_delivery_error' => 'Cloud active run limit reached',
		'last_error_code' => 'delivery_failed_retry_scheduled',
		'last_error_at' => '2026-07-06T00:00:00+00:00',
	),
	false
);
$error_health = Npcink_Cloud_Site_Knowledge_Change_Bridge::health_snapshot();
maca_assert(
	'error' === (string) ( $error_health['status'] ?? '' )
	&& 'site_knowledge_bridge_health.v1' === (string) ( $error_health['health_detail_version'] ?? '' )
	&& 2 === absint( $error_health['delivery_attempts'] ?? 0 )
	&& 3 === absint( $error_health['max_delivery_attempts'] ?? 0 )
	&& 'delivery_failed_retry_scheduled' === (string) ( $error_health['last_error_code'] ?? '' )
	&& '2026-07-06T00:00:00+00:00' === (string) ( $error_health['last_error_at'] ?? '' )
	&& false === (bool) ( $error_health['scheduler_truth'] ?? true )
	&& false === (bool) ( $error_health['workflow_truth'] ?? true )
	&& 'cloud_service' === (string) ( $error_health['freshness_policy_owner'] ?? '' )
	&& 'cloud_service' === (string) ( $error_health['diagnostics_detail_owner'] ?? '' ),
	'Behavior: Site Knowledge bridge health exposes retry and error detail without becoming lifecycle truth.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
maca_set_site_knowledge_delivery_enabled( false );
maca_add_public_post_fixture( 750 );
$disabled_start = Npcink_Cloud_Site_Knowledge_Change_Bridge::request_manual_index_operation( 'start' );
maca_assert(
	is_wp_error( $disabled_start )
	&& 'cloud_site_knowledge_delivery_disabled' === $disabled_start->get_error_code()
	&& array() === $GLOBALS['maca_http_requests'],
	'Behavior: disabled Site Knowledge delivery consent blocks administrator start indexing transport.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
maca_set_site_knowledge_delivery_enabled( false );
maca_add_public_post_fixture( 751 );
$disabled_rebuild = Npcink_Cloud_Site_Knowledge_Change_Bridge::request_manual_index_operation( 'rebuild' );
maca_assert(
	is_wp_error( $disabled_rebuild )
	&& 'cloud_site_knowledge_delivery_disabled' === $disabled_rebuild->get_error_code()
	&& array() === $GLOBALS['maca_http_requests'],
	'Behavior: disabled Site Knowledge delivery consent blocks administrator rebuild transport.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
maca_set_site_knowledge_delivery_enabled( false );
$disabled_delete_status = Npcink_Cloud_Site_Knowledge_Change_Bridge::request_manual_index_operation( 'delete' );
$disabled_delete_request = $GLOBALS['maca_http_requests'][0] ?? array();
$disabled_delete_body = json_decode( (string) ( $disabled_delete_request['args']['body'] ?? '' ), true );
$disabled_delete_body = is_array( $disabled_delete_body ) ? $disabled_delete_body : array();
maca_assert(
	is_array( $disabled_delete_status )
	&& 'delete' === (string) ( $disabled_delete_body['input']['sync_mode'] ?? '' )
	&& 'admin_delete' === (string) ( $disabled_delete_body['input']['operation_source'] ?? '' ),
	'Behavior: disabled Site Knowledge delivery consent still allows explicit delete index cleanup.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
maca_add_public_post_fixture( 801 );
maca_add_public_post_fixture( 802, 'page' );
$GLOBALS['maca_post_terms'][801] = array(
	'category' => array( 'Cloud Runtime', 'WordPress AI' ),
	'post_tag' => array( 'Site Knowledge', 'Writing' ),
);
$start_status = Npcink_Cloud_Site_Knowledge_Change_Bridge::request_manual_index_operation( 'start' );
$start_request = $GLOBALS['maca_http_requests'][0] ?? array();
$start_body = json_decode( (string) ( $start_request['args']['body'] ?? '' ), true );
$start_body = is_array( $start_body ) ? $start_body : array();
$start_documents = (array) ( $start_body['input']['documents'] ?? array() );
$first_start_document = is_array( $start_documents[0] ?? null ) ? $start_documents[0] : array();
maca_assert(
	is_array( $start_status )
	&& false !== strpos( (string) ( $start_request['url'] ?? '' ), '/v1/runtime/execute' )
	&& 'refresh' === (string) ( $start_body['input']['sync_mode'] ?? '' )
	&& 'admin_start' === (string) ( $start_body['input']['operation_source'] ?? '' )
	&& 2 === count( $start_documents )
	&& 'publish' === (string) ( $first_start_document['post_status'] ?? '' )
	&& 'Public content for Site Knowledge 801' === (string) ( $first_start_document['content_excerpt'] ?? '' )
	&& array( 'Cloud Runtime', 'WordPress AI' ) === (array) ( $first_start_document['taxonomies']['category'] ?? array() )
	&& array( 'Site Knowledge', 'Writing' ) === (array) ( $first_start_document['taxonomies']['post_tag'] ?? array() )
	&& ! array_key_exists( 'status', $first_start_document )
	&& ! array_key_exists( 'body', $first_start_document )
	&& ! array_key_exists( 'comments', $first_start_document )
	&& 'start' === (string) ( $start_status['last_index_action'] ?? '' )
	&& 2 === absint( $start_status['last_index_action_sent_count'] ?? 0 ),
	'Behavior: administrator can start Site Knowledge indexing with the canonical Cloud public document contract.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
maca_add_public_post_fixture( 901 );
$rebuild_status = Npcink_Cloud_Site_Knowledge_Change_Bridge::request_manual_index_operation( 'rebuild' );
$rebuild_request = $GLOBALS['maca_http_requests'][0] ?? array();
$rebuild_body = json_decode( (string) ( $rebuild_request['args']['body'] ?? '' ), true );
$rebuild_body = is_array( $rebuild_body ) ? $rebuild_body : array();
maca_assert(
	is_array( $rebuild_status )
	&& 'rebuild' === (string) ( $rebuild_body['input']['sync_mode'] ?? '' )
	&& 'admin_rebuild' === (string) ( $rebuild_body['input']['operation_source'] ?? '' )
	&& 'suggestion_only' === (string) ( $rebuild_body['input']['write_posture'] ?? '' )
	&& false === (bool) ( $rebuild_body['input']['direct_wordpress_write'] ?? true )
	&& 'rebuild' === (string) ( $rebuild_status['last_index_action'] ?? '' ),
	'Behavior: administrator rebuild request forwards only public manifests and no-write posture to Cloud.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
for ( $post_id = 10000; $post_id < 10605; $post_id++ ) {
	maca_add_public_post_fixture( $post_id );
	$GLOBALS['maca_posts'][ $post_id ]->post_content = str_repeat( '中', 2000 );
}
$full_rebuild_status = Npcink_Cloud_Site_Knowledge_Change_Bridge::request_manual_index_operation( 'rebuild' );
$full_rebuild_bodies = array_map(
	static function ( array $request ): array {
		$body = json_decode( (string) ( $request['args']['body'] ?? '' ), true );
		return is_array( $body ) ? $body : array();
	},
	$GLOBALS['maca_http_requests']
);
$full_rebuild_document_ids = array();
$full_rebuild_body_sizes = array();
foreach ( $full_rebuild_bodies as $body ) {
	$full_rebuild_body_sizes[] = strlen( (string) wp_json_encode( $body ) );
	foreach ( (array) ( $body['input']['documents'] ?? array() ) as $document ) {
		$full_rebuild_document_ids[] = absint( $document['post_id'] ?? 0 );
	}
}
maca_assert(
	is_array( $full_rebuild_status )
	&& 4 === count( $full_rebuild_bodies )
	&& 'rebuild' === (string) ( $full_rebuild_bodies[0]['input']['sync_mode'] ?? '' )
	&& array() === (array) ( $full_rebuild_bodies[0]['input']['post_ids'] ?? array() )
	&& 200 === count( (array) ( $full_rebuild_bodies[0]['input']['documents'] ?? array() ) )
	&& 'refresh' === (string) ( $full_rebuild_bodies[1]['input']['sync_mode'] ?? '' )
	&& 200 === count( (array) ( $full_rebuild_bodies[1]['input']['post_ids'] ?? array() ) )
	&& 'refresh' === (string) ( $full_rebuild_bodies[2]['input']['sync_mode'] ?? '' )
	&& 200 === count( (array) ( $full_rebuild_bodies[2]['input']['post_ids'] ?? array() ) )
	&& 'refresh' === (string) ( $full_rebuild_bodies[3]['input']['sync_mode'] ?? '' )
	&& 5 === count( (array) ( $full_rebuild_bodies[3]['input']['post_ids'] ?? array() ) )
	&& 605 === count( $full_rebuild_document_ids )
	&& 605 === count( array_unique( $full_rebuild_document_ids ) )
	&& max( $full_rebuild_body_sizes ) < 900000
	&& 605 === absint( $full_rebuild_status['last_index_action_sent_count'] ?? 0 )
	&& 4 === absint( $full_rebuild_status['last_index_action_batch_count'] ?? 0 ),
	'Behavior: administrator rebuild covers the bounded full public corpus across Cloud-owned rebuild plus refresh batches.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
for ( $post_id = 12000; $post_id < 12405; $post_id++ ) {
	maca_add_public_post_fixture( $post_id );
}
for ( $run_number = 1; $run_number <= 3; $run_number++ ) {
	$GLOBALS['maca_http_response_queue'][] = array(
		'response' => array( 'code' => 200 ),
		'body' => wp_json_encode(
			array(
				'status' => 'ok',
				'data' => array( 'run_id' => 'run_automatic_' . $run_number ),
			)
		),
	);
	$GLOBALS['maca_http_response_queue'][] = array(
		'response' => array( 'code' => 200 ),
		'body' => wp_json_encode(
			array(
				'status' => 'ok',
				'data' => array( 'status' => 'succeeded' ),
			)
		),
	);
}
Npcink_Cloud_Site_Knowledge_Change_Bridge::maybe_schedule_automatic_rebuild(
	array(
		'maintenance' => array(
			'contract_version' => 'site_knowledge_maintenance.v1',
			'status' => 'awaiting_site',
			'action' => 'full_sync',
			'automatic' => true,
			'request_id' => 'skm_automatic_123',
			'target_embedding_space_id' => 'siliconflow:BAAI/bge-m3',
		),
	)
);
$automatic_cursor = get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::MAINTENANCE_OPTION, array() );
Npcink_Cloud_Site_Knowledge_Change_Bridge::flush_buffer();
Npcink_Cloud_Site_Knowledge_Change_Bridge::flush_buffer();
Npcink_Cloud_Site_Knowledge_Change_Bridge::flush_buffer();
Npcink_Cloud_Site_Knowledge_Change_Bridge::flush_buffer();
Npcink_Cloud_Site_Knowledge_Change_Bridge::flush_buffer();
Npcink_Cloud_Site_Knowledge_Change_Bridge::flush_buffer();
$automatic_bodies = array_map(
	static function ( array $request ): array {
		$body = json_decode( (string) ( $request['args']['body'] ?? '' ), true );
		return is_array( $body ) ? $body : array();
	},
	array_values(
		array_filter(
			$GLOBALS['maca_http_requests'],
			static fn( array $request ): bool => 'POST' === (string) ( $request['args']['method'] ?? '' )
		)
	)
);
$automatic_status_requests = array_values(
	array_filter(
		$GLOBALS['maca_http_requests'],
		static fn( array $request ): bool => 'GET' === (string) ( $request['args']['method'] ?? '' )
	)
);
maca_assert(
	3 === absint( $automatic_cursor['batch_count'] ?? 0 )
	&& 3 === count( $automatic_bodies )
	&& 3 === count( $automatic_status_requests )
	&& 'automatic_rebuild' === (string) ( $automatic_bodies[0]['input']['operation_source'] ?? '' )
	&& 'rebuild' === (string) ( $automatic_bodies[0]['input']['sync_mode'] ?? '' )
	&& 'full_sync' === (string) ( $automatic_bodies[0]['input']['maintenance']['action'] ?? '' )
	&& 'skm_automatic_123' === (string) ( $automatic_bodies[0]['input']['maintenance']['request_id'] ?? '' )
	&& 0 === absint( $automatic_bodies[0]['input']['maintenance']['batch_index'] ?? 99 )
	&& false === (bool) ( $automatic_bodies[0]['input']['maintenance']['is_final'] ?? true )
	&& 'refresh' === (string) ( $automatic_bodies[2]['input']['sync_mode'] ?? '' )
	&& 2 === absint( $automatic_bodies[2]['input']['maintenance']['batch_index'] ?? 0 )
	&& true === (bool) ( $automatic_bodies[2]['input']['maintenance']['is_final'] ?? false )
	&& array() === get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::MAINTENANCE_OPTION, array() ),
	'Behavior: Cloud maintenance intent becomes an automatic bounded full-sync cursor and completes without a site-admin action.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
maca_add_public_post_fixture( 10901 );
$GLOBALS['maca_posts'][10901]->post_title = 'Contact editor@example.test or 138 0013 8000';
$GLOBALS['maca_posts'][10901]->post_content = 'Public guide https://example.test/private-path email editor@example.test phone 138 0013 8000 useful ending.';
Npcink_Cloud_Site_Knowledge_Change_Bridge::request_manual_index_operation( 'start' );
$redacted_request = $GLOBALS['maca_http_requests'][0] ?? array();
$redacted_body = json_decode( (string) ( $redacted_request['args']['body'] ?? '' ), true );
$redacted_document = (array) ( $redacted_body['input']['documents'][0] ?? array() );
maca_assert(
	false === strpos( (string) ( $redacted_document['title'] ?? '' ), '@' )
	&& false === strpos( (string) ( $redacted_document['content_excerpt'] ?? '' ), 'https://' )
	&& false === strpos( (string) ( $redacted_document['content_excerpt'] ?? '' ), '@' )
	&& false === strpos( (string) ( $redacted_document['content_excerpt'] ?? '' ), '138 0013 8000' )
	&& 'https://example.test/?p=10901' === (string) ( $redacted_document['url'] ?? '' ),
	'Behavior: public Site Knowledge manifests remove incidental contact data while preserving the canonical public post URL.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
$delete_status = Npcink_Cloud_Site_Knowledge_Change_Bridge::request_manual_index_operation( 'delete' );
$delete_request = $GLOBALS['maca_http_requests'][0] ?? array();
$delete_body = json_decode( (string) ( $delete_request['args']['body'] ?? '' ), true );
$delete_body = is_array( $delete_body ) ? $delete_body : array();
maca_assert(
	is_array( $delete_status )
	&& 'delete' === (string) ( $delete_body['input']['sync_mode'] ?? '' )
	&& 'admin_delete' === (string) ( $delete_body['input']['operation_source'] ?? '' )
	&& array() === (array) ( $delete_body['input']['documents'] ?? array() )
	&& false === (bool) ( $delete_body['input']['direct_wordpress_write'] ?? true )
	&& 'delete' === (string) ( $delete_status['last_index_action'] ?? '' ),
	'Behavior: administrator delete request asks Cloud to remove the site index without WordPress writes.'
);
