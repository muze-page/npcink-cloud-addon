<?php
/**
 * Site Knowledge public content change bridge.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Npcink_Cloud_Site_Knowledge_Change_Bridge' ) ) {
	/**
	 * Notifies Cloud Site Knowledge about bounded public content changes.
	 */
	final class Npcink_Cloud_Site_Knowledge_Change_Bridge {
		public const BUFFER_OPTION = 'npcink_cloud_addon_site_knowledge_change_buffer';
		public const STATUS_OPTION = 'npcink_cloud_addon_site_knowledge_change_status';
		public const FLUSH_HOOK = 'npcink_cloud_addon_flush_site_knowledge_changes';
		public const RECONCILE_HOOK = 'npcink_cloud_addon_reconcile_site_knowledge_changes';

		private const STATUS_CONTRACT = 'site_knowledge_change_bridge_status.v1';
		private const HEALTH_DETAIL_CONTRACT = 'site_knowledge_bridge_health.v1';
		private const DEFAULT_POST_TYPES = array( 'post', 'page' );
		private const MAX_BUFFER_ITEMS = 500;
		private const MAX_BATCH_ITEMS = 25;
		private const DEBOUNCE_SECONDS = 180;
		private const RETRY_SECONDS = 300;
			private const MAX_DELIVERY_ATTEMPTS = 3;
			private const RECONCILE_POSTS = 50;
			private const MANUAL_INDEX_POSTS = 50;
			private const MAX_DOCUMENT_CHARS = 12000;

		/**
		 * Registers content change hooks and delivery cron hooks.
		 *
		 * @return void
		 */
		public static function register(): void {
			add_action( 'transition_post_status', array( __CLASS__, 'handle_post_status_transition' ), 10, 3 );
			add_action( 'save_post', array( __CLASS__, 'handle_saved_post' ), 20, 2 );
			add_action( 'trashed_post', array( __CLASS__, 'handle_removed_post' ), 10, 1 );
			add_action( 'before_delete_post', array( __CLASS__, 'handle_removed_post' ), 10, 1 );
			add_action( 'transition_comment_status', array( __CLASS__, 'handle_comment_status_transition' ), 10, 3 );
			add_action( 'comment_post', array( __CLASS__, 'handle_comment_posted' ), 20, 3 );
			add_action( 'edit_comment', array( __CLASS__, 'handle_edited_comment' ), 20, 2 );
			add_action( 'trashed_comment', array( __CLASS__, 'handle_removed_comment' ), 10, 1 );
			add_action( 'deleted_comment', array( __CLASS__, 'handle_removed_comment' ), 10, 1 );
			add_action( self::FLUSH_HOOK, array( __CLASS__, 'flush_buffer' ) );
			add_action( self::RECONCILE_HOOK, array( __CLASS__, 'buffer_recent_public_content' ) );

			self::sync_schedule();
		}

		/**
		 * Returns whether the bridge can deliver through verified Cloud settings.
		 *
		 * @return bool
		 */
		public static function is_enabled(): bool {
			return Npcink_Cloud_Addon_Settings::is_site_knowledge_delivery_enabled();
		}

		/**
		 * Returns local bridge status for other plugins.
		 *
		 * @return array<string,mixed>
		 */
		public static function health_snapshot(): array {
			$buffer = self::get_buffer();
			$status = self::get_status();
			$next_flush = function_exists( 'wp_next_scheduled' ) ? wp_next_scheduled( self::FLUSH_HOOK ) : false;
			$next_reconcile = function_exists( 'wp_next_scheduled' ) ? wp_next_scheduled( self::RECONCILE_HOOK ) : false;
			$configured = Npcink_Cloud_Addon_Settings::is_configured();
			$verified = Npcink_Cloud_Addon_Settings::is_verified();
			$delivery_enabled = self::is_enabled();
			$buffer_count = count( $buffer['post_ids'] );
			$last_success_at = ! empty( $status['last_delivery_ok'] ) ? sanitize_text_field( (string) ( $status['last_delivered_at'] ?? '' ) ) : '';
			$has_error = empty( $status['last_delivery_ok'] ) && ( '' !== (string) ( $status['last_delivery_error'] ?? '' ) || '' !== (string) ( $status['last_error_code'] ?? '' ) );
			$bridge_status = ! $configured ? 'not_configured' : ( ! $verified ? 'unverified' : ( ! $delivery_enabled ? 'disabled' : ( $has_error ? 'error' : ( $buffer_count > 0 ? 'queued' : 'idle' ) ) ) );

			return array(
				'owner' => 'cloud_addon',
				'mode' => 'site_knowledge_change_bridge',
				'status_contract' => self::STATUS_CONTRACT,
				'preferred_status_field' => 'change_bridge',
				'preferred_count_field' => 'buffer_count',
				'health_detail_version' => self::HEALTH_DETAIL_CONTRACT,
				'enabled' => $delivery_enabled,
				'delivery_enabled' => $delivery_enabled,
				'configured' => $configured,
				'verified' => $verified,
				'status' => $bridge_status,
				'buffer_count' => $buffer_count,
				'buffer_semantics' => 'bounded_delivery_buffer',
				'buffer_truth' => 'local_delivery_durability_only',
				'delivery_attempts' => absint( $buffer['attempts'] ?? 0 ),
				'max_buffer_items' => self::MAX_BUFFER_ITEMS,
				'batch_size' => self::MAX_BATCH_ITEMS,
				'max_delivery_attempts' => self::MAX_DELIVERY_ATTEMPTS,
				'last_delivery_ok' => ! empty( $status['last_delivery_ok'] ),
				'last_delivered_at' => sanitize_text_field( (string) ( $status['last_delivered_at'] ?? '' ) ),
				'last_delivery_at' => sanitize_text_field( (string) ( $status['last_delivered_at'] ?? '' ) ),
				'last_success_at' => $last_success_at,
				'last_delivery_error' => sanitize_text_field( (string) ( $status['last_delivery_error'] ?? '' ) ),
				'last_error_code' => sanitize_key( (string) ( $status['last_error_code'] ?? '' ) ),
				'last_error_at' => sanitize_text_field( (string) ( $status['last_error_at'] ?? '' ) ),
				'last_changed_at' => sanitize_text_field( (string) ( $status['last_changed_at'] ?? '' ) ),
				'last_post_id' => absint( $status['last_post_id'] ?? 0 ),
				'last_sent_count' => absint( $status['last_sent_count'] ?? 0 ),
				'total_sent' => absint( $status['total_sent'] ?? 0 ),
				'last_index_action' => sanitize_key( (string) ( $status['last_index_action'] ?? '' ) ),
				'last_index_action_at' => sanitize_text_field( (string) ( $status['last_index_action_at'] ?? '' ) ),
				'last_index_action_sent_count' => absint( $status['last_index_action_sent_count'] ?? 0 ),
				'next_flush_at' => false === $next_flush ? '' : gmdate( 'c', (int) $next_flush ),
				'next_reconcile_at' => false === $next_reconcile ? '' : gmdate( 'c', (int) $next_reconcile ),
				'wp_cron_disabled' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
				'cron_command' => 'wp cron event run ' . self::FLUSH_HOOK,
				'wp_cli_command' => 'wp cron event run ' . self::FLUSH_HOOK,
				'write_posture' => 'suggestion_only',
				'cloud_runtime_contract' => 'site_knowledge_sync.v1',
				'transport_owner' => 'cloud_addon',
				'delivery_truth_owner' => 'cloud_addon',
				'index_execution_owner' => 'cloud_service',
				'index_lifecycle_owner' => 'cloud_service',
				'freshness_policy_owner' => 'cloud_service',
				'diagnostics_detail_owner' => 'cloud_service',
				'ownership' => array(
					'source_content_owner' => 'local_wordpress_host',
					'delivery_bridge_owner' => 'cloud_addon',
					'index_execution_owner' => 'cloud_service',
					'index_lifecycle_owner' => 'cloud_service',
					'freshness_policy_owner' => 'cloud_service',
					'diagnostics_detail_owner' => 'cloud_service',
					'vector_storage_owner' => 'cloud_service',
					'embedding_execution_owner' => 'cloud_service',
					'approval_owner' => 'local_wordpress_host',
					'final_write_owner' => 'local_wordpress_host',
					'wordpress_write_owner' => 'local_wordpress_host',
				),
				'truth_boundaries' => array(
					'cloud_is_index_truth' => true,
					'cloud_is_freshness_truth' => true,
					'cloud_is_diagnostics_truth' => true,
					'cloud_is_wordpress_control_plane' => false,
					'cloud_creates_wordpress_writes' => false,
					'cloud_owns_local_approval' => false,
					'cloud_owns_ability_registry' => false,
					'cloud_owns_workflow_registry' => false,
				),
				'legacy_toolbox_fallback' => false,
				'scheduler_truth' => false,
				'workflow_truth' => false,
				'wordpress_write_included' => false,
			);
		}

		/**
		 * Deletes addon-owned Site Knowledge bridge state.
		 *
		 * @return void
		 */
		public static function delete_data(): void {
			delete_option( self::BUFFER_OPTION );
			delete_option( self::STATUS_OPTION );
			wp_clear_scheduled_hook( self::FLUSH_HOOK );
			wp_clear_scheduled_hook( self::RECONCILE_HOOK );
		}

		/**
		 * Handles post status changes.
		 *
		 * @param string $new_status New status.
		 * @param string $old_status Old status.
		 * @param mixed  $post Post object.
		 * @return void
		 */
		public static function handle_post_status_transition( string $new_status, string $old_status, $post ): void {
			$post_id = self::post_id_from_value( $post );
			if ( $post_id <= 0 ) {
				return;
			}

			if ( 'publish' === $new_status || 'publish' === $old_status ) {
				self::buffer_post_ids( array( $post_id ) );
			}
		}

		/**
		 * Handles saved public posts and pages.
		 *
		 * @param int   $post_id Post id.
		 * @param mixed $post Post object.
		 * @return void
		 */
		public static function handle_saved_post( int $post_id, $post ): void {
			if ( self::is_public_post( $post ) ) {
				self::buffer_post_ids( array( $post_id ) );
			}
		}

		/**
		 * Handles removed public posts and pages.
		 *
		 * @param int $post_id Post id.
		 * @return void
		 */
		public static function handle_removed_post( int $post_id ): void {
			if ( $post_id > 0 ) {
				self::buffer_post_ids( array( $post_id ) );
			}
		}

		/**
		 * Handles comment approval transitions.
		 *
		 * @param string $new_status New status.
		 * @param string $old_status Old status.
		 * @param mixed  $comment Comment object.
		 * @return void
		 */
		public static function handle_comment_status_transition( string $new_status, string $old_status, $comment ): void {
			$post_id = self::post_id_from_comment( $comment );
			if ( $post_id <= 0 ) {
				return;
			}

			if ( 'approved' === $new_status || 'approved' === $old_status || '1' === $new_status || '1' === $old_status ) {
				self::buffer_post_ids( array( $post_id ) );
			}
		}

		/**
		 * Handles newly posted approved comments.
		 *
		 * @param int        $comment_id Comment id.
		 * @param int|string $comment_approved Approval state.
		 * @param mixed      $commentdata Comment data.
		 * @return void
		 */
		public static function handle_comment_posted( int $comment_id, $comment_approved, $commentdata = null ): void {
			if ( 1 !== (int) $comment_approved ) {
				return;
			}

			$post_id = is_array( $commentdata ) ? absint( $commentdata['comment_post_ID'] ?? 0 ) : 0;
			if ( $post_id <= 0 && function_exists( 'get_comment' ) ) {
				$post_id = self::post_id_from_comment( get_comment( $comment_id ) );
			}

			if ( $post_id > 0 ) {
				self::buffer_post_ids( array( $post_id ) );
			}
		}

		/**
		 * Handles approved comment edits.
		 *
		 * @param int   $comment_id Comment id.
		 * @param mixed $data Comment data.
		 * @return void
		 */
		public static function handle_edited_comment( int $comment_id, $data = null ): void {
			$post_id = is_array( $data ) ? absint( $data['comment_post_ID'] ?? 0 ) : 0;
			if ( $post_id <= 0 && function_exists( 'get_comment' ) ) {
				$post_id = self::post_id_from_comment( get_comment( $comment_id ) );
			}

			if ( $post_id > 0 ) {
				self::buffer_post_ids( array( $post_id ) );
			}
		}

		/**
		 * Handles removed comments.
		 *
		 * @param int $comment_id Comment id.
		 * @return void
		 */
		public static function handle_removed_comment( int $comment_id ): void {
			if ( ! function_exists( 'get_comment' ) ) {
				return;
			}

			$post_id = self::post_id_from_comment( get_comment( $comment_id ) );
			if ( $post_id > 0 ) {
				self::buffer_post_ids( array( $post_id ) );
			}
		}

		/**
		 * Buffers recent public content for low-frequency reconciliation.
		 *
		 * @return void
		 */
		public static function buffer_recent_public_content(): void {
			if ( ! self::is_enabled() || ! function_exists( 'get_posts' ) ) {
				return;
			}

			$posts = get_posts(
				array(
					'post_type' => self::post_types(),
					'post_status' => 'publish',
					'posts_per_page' => self::RECONCILE_POSTS,
					'orderby' => 'modified',
					'order' => 'DESC',
					'fields' => 'ids',
					'no_found_rows' => true,
				)
			);

			self::buffer_post_ids( is_array( $posts ) ? array_map( 'absint', $posts ) : array() );
		}

		/**
		 * Sends one bounded batch of changed public content to Cloud.
		 *
		 * @return array<string,mixed>
		 */
			public static function flush_buffer(): array {
				if ( ! Npcink_Cloud_Addon_Settings::is_verified() ) {
					return self::record_delivery_result( false, 0, __( 'Cloud Addon settings are not verified.', 'npcink-cloud-addon' ), 'cloud_addon_unverified' );
				}

				if ( ! self::is_enabled() ) {
					return self::record_delivery_result( false, 0, __( 'Site Knowledge delivery is disabled locally.', 'npcink-cloud-addon' ), 'cloud_site_knowledge_delivery_disabled' );
				}

			$buffer = self::get_buffer();
			if ( empty( $buffer ) ) {
				return self::record_delivery_result( true, 0, '' );
			}

				$post_ids = array_slice( $buffer['post_ids'], 0, self::MAX_BATCH_ITEMS );
				$result = self::request_site_knowledge_sync( 'refresh', $post_ids, 'change_bridge' );
				if ( is_wp_error( $result ) ) {
					return self::retry_or_drop_buffer( $buffer, $result->get_error_message() );
				}

			$remaining = array_values( array_diff( $buffer['post_ids'], $post_ids ) );
			self::save_buffer( $remaining, 0 );
			if ( ! empty( $remaining ) ) {
				self::schedule_flush( self::RETRY_SECONDS );
			}

				return self::record_delivery_result( true, count( $post_ids ), '' );
			}

			/**
			 * Sends one administrator-requested index operation to Cloud.
			 *
			 * @param string $operation Operation: start, rebuild, or delete.
			 * @return array<string,mixed>|WP_Error
			 */
			public static function request_manual_index_operation( string $operation ) {
					if ( ! Npcink_Cloud_Addon_Settings::is_verified() ) {
						return new WP_Error(
							'cloud_site_knowledge_unverified',
							__( 'Cloud Addon settings are not verified.', 'npcink-cloud-addon' ),
						array( 'status' => 400 )
					);
				}

				$operation = sanitize_key( $operation );
					if ( ! in_array( $operation, array( 'start', 'rebuild', 'delete' ), true ) ) {
					return new WP_Error(
						'cloud_site_knowledge_index_action_not_allowed',
						__( 'The requested Site Knowledge index action is not supported.', 'npcink-cloud-addon' ),
						array( 'status' => 400 )
					);
					}

					if ( 'delete' !== $operation && ! self::is_enabled() ) {
						return new WP_Error(
							'cloud_site_knowledge_delivery_disabled',
							__( 'Site Knowledge delivery is disabled locally. Enable delivery before starting or rebuilding the index.', 'npcink-cloud-addon' ),
							array( 'status' => 400 )
						);
					}

				$sync_mode = 'start' === $operation ? 'refresh' : $operation;
				$post_ids  = 'delete' === $operation ? array() : self::recent_public_post_ids( self::MANUAL_INDEX_POSTS );
				if ( 'start' === $operation && empty( $post_ids ) ) {
					return new WP_Error(
						'cloud_site_knowledge_no_public_content',
						__( 'No public posts or pages were found for Site Knowledge indexing.', 'npcink-cloud-addon' ),
						array( 'status' => 400 )
					);
				}

				$result = self::request_site_knowledge_sync( $sync_mode, $post_ids, 'admin_' . $operation );
				if ( is_wp_error( $result ) ) {
					self::record_manual_operation_result( $operation, false, 0, $result->get_error_message(), $result->get_error_code() );
					return $result;
				}

				return self::record_manual_operation_result( $operation, true, count( $post_ids ), '' );
			}

		/**
		 * Keeps reconcile cron aligned with Cloud configuration.
		 *
		 * @return void
		 */
			public static function sync_schedule(): void {
			if ( ! function_exists( 'wp_next_scheduled' ) ) {
				return;
			}

			if ( self::is_enabled() ) {
				if ( false === wp_next_scheduled( self::RECONCILE_HOOK ) && function_exists( 'wp_schedule_event' ) ) {
					wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::RECONCILE_HOOK );
				}
				return;
			}

			if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
				wp_clear_scheduled_hook( self::FLUSH_HOOK );
				wp_clear_scheduled_hook( self::RECONCILE_HOOK );
			}
		}

		/**
		 * Buffers changed post ids.
		 *
		 * @param array<int,int> $post_ids Post ids.
		 * @return void
		 */
		private static function buffer_post_ids( array $post_ids ): void {
			if ( ! self::is_enabled() ) {
				return;
			}

			$clean = array_values( array_filter( array_map( 'absint', $post_ids ) ) );
			if ( empty( $clean ) ) {
				return;
			}

			$buffer = self::get_buffer();
			$merged = array_values( array_unique( array_merge( $buffer['post_ids'], $clean ) ) );
			if ( count( $merged ) > self::MAX_BUFFER_ITEMS ) {
				$merged = array_slice( $merged, -1 * self::MAX_BUFFER_ITEMS );
			}
			if ( $merged === array_values( $buffer['post_ids'] ) ) {
				self::schedule_flush( self::DEBOUNCE_SECONDS );
				return;
			}

			self::save_buffer( $merged, absint( $buffer['attempts'] ?? 0 ) );
			update_option(
				self::STATUS_OPTION,
				array_merge(
					self::get_status(),
					array(
						'last_changed_at' => gmdate( 'c' ),
						'last_post_id' => (int) end( $clean ),
						'buffer_count' => count( $merged ),
					)
				),
				false
			);
			self::schedule_flush( self::DEBOUNCE_SECONDS );
		}

		/**
		 * Requests a Cloud Site Knowledge refresh for changed posts.
		 *
		 * @param array<int,int> $post_ids Changed post ids.
		 * @return array<string,mixed>|WP_Error
		 */
			private static function request_site_knowledge_sync( string $sync_mode, array $post_ids, string $operation_source = 'change_bridge' ) {
				$sync_mode = sanitize_key( $sync_mode );
				if ( ! in_array( $sync_mode, array( 'refresh', 'rebuild', 'delete' ), true ) ) {
					return new WP_Error(
						'cloud_site_knowledge_sync_mode_not_allowed',
						__( 'Site Knowledge sync mode must be refresh, rebuild, or delete.', 'npcink-cloud-addon' ),
						array( 'status' => 400 )
					);
				}

				$post_ids = array_values( array_unique( array_filter( array_map( 'absint', $post_ids ) ) ) );
				$limit    = 'change_bridge' === $operation_source ? self::MAX_BATCH_ITEMS : self::MANUAL_INDEX_POSTS;
				$client  = new Npcink_Cloud_Runtime_Client();
				$payload = array(
					'ability_name' => 'npcink-cloud/site-knowledge-sync',
					'contract_version' => 'site_knowledge_sync.v1',
					'execution_pattern' => 'whole_run_offload',
					'input' => array(
						'contract_version' => 'site_knowledge_sync.v1',
						'sync_mode' => $sync_mode,
						'operation_source' => sanitize_key( $operation_source ),
						'post_ids' => $post_ids,
						'max_posts' => $limit,
						'documents' => 'delete' === $sync_mode ? array() : self::collect_documents( $post_ids, $limit ),
						'write_posture' => 'suggestion_only',
						'direct_wordpress_write' => false,
					),
					'data_classification' => 'public_site_content',
					'storage_mode' => 'result_only',
					'retention_ttl' => DAY_IN_SECONDS,
					'timeout_seconds' => 60,
					'retry_max' => 1,
					'policy' => array(
						'allow_fallback' => true,
					),
				);

				return $client->execute_runtime(
					$payload,
					'trace_site_knowledge_' . sanitize_key( $operation_source ) . '_' . wp_generate_uuid4(),
					'site_knowledge_' . sanitize_key( $operation_source ) . '_' . wp_generate_uuid4()
				);
			}

		/**
		 * Collects bounded public document manifests.
		 *
		 * @param array<int,int> $post_ids Post ids.
		 * @return array<int,array<string,mixed>>
		 */
			private static function collect_documents( array $post_ids, int $limit = self::MAX_BATCH_ITEMS ): array {
				if ( ! function_exists( 'get_post' ) ) {
					return array();
				}

				$documents = array();
				foreach ( array_slice( array_values( array_unique( array_map( 'absint', $post_ids ) ) ), 0, max( 1, $limit ) ) as $post_id ) {
					$post = get_post( $post_id );
					if ( ! self::is_public_post( $post ) ) {
						continue;
				}

				$documents[] = self::post_document( $post );
			}

				return $documents;
			}

			/**
			 * Returns recent public post/page ids for manual index operations.
			 *
			 * @param int $limit Max ids.
			 * @return array<int,int>
			 */
			private static function recent_public_post_ids( int $limit ): array {
				if ( ! function_exists( 'get_posts' ) ) {
					return array();
				}

				$posts = get_posts(
					array(
						'post_type' => self::post_types(),
						'post_status' => 'publish',
						'posts_per_page' => max( 1, min( self::MANUAL_INDEX_POSTS, $limit ) ),
						'orderby' => 'modified',
						'order' => 'DESC',
						'fields' => 'ids',
						'no_found_rows' => true,
					)
				);

				return is_array( $posts ) ? array_values( array_filter( array_map( 'absint', $posts ) ) ) : array();
			}

		/**
		 * Builds one public post/page manifest.
		 *
		 * @param mixed $post Post object.
		 * @return array<string,mixed>
		 */
		private static function post_document( $post ): array {
			$post_id = self::post_id_from_value( $post );
			$content = self::bounded_text( (string) ( $post->post_content ?? '' ), self::MAX_DOCUMENT_CHARS );

			return array(
				'id' => 'wp_post_' . $post_id,
				'source' => 'wordpress',
				'kind' => 'post',
				'post_id' => $post_id,
				'post_type' => sanitize_key( (string) ( $post->post_type ?? '' ) ),
				'status' => sanitize_key( (string) ( $post->post_status ?? '' ) ),
				'title' => self::post_title( $post ),
				'url' => self::post_url( $post_id ),
				'modified_gmt' => sanitize_text_field( (string) ( $post->post_modified_gmt ?? '' ) ),
				'excerpt' => self::bounded_text( (string) ( $post->post_excerpt ?? '' ), 1000 ),
				'body' => $content,
				'comments' => self::approved_comment_documents( $post_id ),
			);
		}

		/**
		 * Collects bounded approved comment manifests.
		 *
		 * @param int $post_id Post id.
		 * @return array<int,array<string,mixed>>
		 */
		private static function approved_comment_documents( int $post_id ): array {
			if ( ! function_exists( 'get_comments' ) ) {
				return array();
			}

			$comments = get_comments(
				array(
					'post_id' => $post_id,
					'status' => 'approve',
					'number' => 20,
					'orderby' => 'comment_date_gmt',
					'order' => 'ASC',
				)
			);
			if ( ! is_array( $comments ) ) {
				return array();
			}

			$documents = array();
			foreach ( $comments as $comment ) {
				$comment_id = absint( $comment->comment_ID ?? 0 );
				if ( $comment_id <= 0 ) {
					continue;
				}

				$documents[] = array(
					'id' => 'wp_comment_' . $comment_id,
					'comment_id' => $comment_id,
					'post_id' => $post_id,
					'date_gmt' => sanitize_text_field( (string) ( $comment->comment_date_gmt ?? '' ) ),
					'body' => self::bounded_text( (string) ( $comment->comment_content ?? '' ), 2000 ),
				);
			}

			return $documents;
		}

		/**
		 * Retries or drops the buffered batch after bounded delivery attempts.
		 *
		 * @param array<string,mixed> $buffer Buffer payload.
		 * @param string              $error Error message.
		 * @return array<string,mixed>
		 */
		private static function retry_or_drop_buffer( array $buffer, string $error ): array {
			$attempts = absint( $buffer['attempts'] ?? 0 ) + 1;
			if ( $attempts >= self::MAX_DELIVERY_ATTEMPTS ) {
				self::save_buffer( array(), 0 );
				return self::record_delivery_result( false, 0, $error, 'delivery_attempts_exhausted' );
			}

			self::save_buffer( $buffer['post_ids'], $attempts );
			self::schedule_flush( self::RETRY_SECONDS );

			return self::record_delivery_result( false, 0, $error, 'delivery_failed_retry_scheduled' );
		}

		/**
		 * Schedules one debounced flush.
		 *
		 * @param int $delay Delay in seconds.
		 * @return void
		 */
		private static function schedule_flush( int $delay ): void {
			if ( ! function_exists( 'wp_next_scheduled' ) || ! function_exists( 'wp_schedule_single_event' ) ) {
				return;
			}

			if ( false === wp_next_scheduled( self::FLUSH_HOOK ) ) {
				wp_schedule_single_event( time() + max( 1, $delay ), self::FLUSH_HOOK );
			}
		}

		/**
		 * Records latest delivery result.
		 *
		 * @param bool   $ok Whether Cloud accepted the request.
		 * @param int    $sent Sent post count.
		 * @param string $error Error message.
		 * @return array<string,mixed>
		 */
		private static function record_delivery_result( bool $ok, int $sent, string $error, string $error_code = '' ): array {
			$buffer = self::get_buffer();
			$status = array_merge(
				self::get_status(),
				array(
					'last_delivery_ok' => $ok,
					'last_delivered_at' => $ok ? gmdate( 'c' ) : (string) ( self::get_status()['last_delivered_at'] ?? '' ),
					'last_delivery_error' => $ok ? '' : sanitize_text_field( $error ),
					'last_error_code' => $ok ? '' : sanitize_key( $error_code ),
					'last_error_at' => $ok ? '' : gmdate( 'c' ),
					'last_sent_count' => $ok ? max( 0, $sent ) : 0,
					'total_sent' => absint( self::get_status()['total_sent'] ?? 0 ) + ( $ok ? max( 0, $sent ) : 0 ),
					'buffer_count' => count( $buffer['post_ids'] ),
				)
			);
			update_option( self::STATUS_OPTION, $status, false );

				return $status;
			}

			/**
			 * Records the latest administrator index action.
			 *
			 * @param string $operation Operation slug.
			 * @param bool   $ok Whether Cloud accepted the request.
			 * @param int    $sent Public post count sent.
			 * @param string $error Error message.
			 * @param string $error_code Error code.
			 * @return array<string,mixed>
			 */
			private static function record_manual_operation_result( string $operation, bool $ok, int $sent, string $error, string $error_code = '' ): array {
				$status = self::record_delivery_result( $ok, $sent, $error, $error_code );
				$status = array_merge(
					$status,
					array(
						'last_index_action' => sanitize_key( $operation ),
						'last_index_action_at' => gmdate( 'c' ),
						'last_index_action_sent_count' => max( 0, $sent ),
					)
				);
				update_option( self::STATUS_OPTION, $status, false );

				return $status;
			}

		/**
		 * Returns buffered post ids and attempts.
		 *
		 * @return array<string,mixed>
		 */
		private static function get_buffer(): array {
			$value = get_option( self::BUFFER_OPTION, array() );
			$value = is_array( $value ) ? $value : array();
			$post_ids = isset( $value['post_ids'] ) && is_array( $value['post_ids'] ) ? $value['post_ids'] : $value;

			return array(
				'post_ids' => array_values( array_filter( array_map( 'absint', is_array( $post_ids ) ? $post_ids : array() ) ) ),
				'attempts' => absint( $value['attempts'] ?? 0 ),
			);
		}

		/**
		 * Saves the local delivery buffer.
		 *
		 * @param array<int,int> $post_ids Post ids.
		 * @param int            $attempts Delivery attempts.
		 * @return void
		 */
		private static function save_buffer( array $post_ids, int $attempts ): void {
			$post_ids = array_values( array_unique( array_filter( array_map( 'absint', $post_ids ) ) ) );
			if ( empty( $post_ids ) ) {
				delete_option( self::BUFFER_OPTION );
				return;
			}

			update_option(
				self::BUFFER_OPTION,
				array(
					'post_ids' => $post_ids,
					'attempts' => max( 0, $attempts ),
					'updated_at' => gmdate( 'c' ),
				),
				false
			);
		}

		/**
		 * Returns raw status.
		 *
		 * @return array<string,mixed>
		 */
		private static function get_status(): array {
			$status = get_option( self::STATUS_OPTION, array() );

			return is_array( $status ) ? $status : array();
		}

		/**
		 * Returns allowed public post types.
		 *
		 * @return array<int,string>
		 */
		private static function post_types(): array {
			$post_types = apply_filters( 'npcink_cloud_addon_site_knowledge_post_types', self::DEFAULT_POST_TYPES );
			$post_types = is_array( $post_types ) ? $post_types : self::DEFAULT_POST_TYPES;
			$post_types = array_values( array_unique( array_filter( array_map( 'sanitize_key', $post_types ) ) ) );

			return empty( $post_types ) ? self::DEFAULT_POST_TYPES : $post_types;
		}

		/**
		 * Returns whether a post object is public and allowed.
		 *
		 * @param mixed $post Post object.
		 * @return bool
		 */
		private static function is_public_post( $post ): bool {
			$post_id = self::post_id_from_value( $post );
			$post_type = sanitize_key( (string) ( $post->post_type ?? '' ) );
			$post_status = sanitize_key( (string) ( $post->post_status ?? '' ) );

			return $post_id > 0
				&& 'publish' === $post_status
				&& 'attachment' !== $post_type
				&& in_array( $post_type, self::post_types(), true );
		}

		/**
		 * Extracts post id from a post-like value.
		 *
		 * @param mixed $post Post-like value.
		 * @return int
		 */
		private static function post_id_from_value( $post ): int {
			if ( is_object( $post ) ) {
				return absint( $post->ID ?? 0 );
			}

			return absint( $post );
		}

		/**
		 * Extracts parent post id from a comment-like value.
		 *
		 * @param mixed $comment Comment-like value.
		 * @return int
		 */
		private static function post_id_from_comment( $comment ): int {
			if ( is_object( $comment ) ) {
				return absint( $comment->comment_post_ID ?? 0 );
			}

			return 0;
		}

		/**
		 * Returns a bounded plain text value.
		 *
		 * @param string $value Raw text.
		 * @param int    $limit Character limit.
		 * @return string
		 */
		private static function bounded_text( string $value, int $limit ): string {
			$value = wp_strip_all_tags( $value );
			$value = preg_replace( '/\s+/', ' ', $value );
			$value = trim( is_string( $value ) ? $value : '' );

			if ( strlen( $value ) <= $limit ) {
				return $value;
			}

			return substr( $value, 0, $limit );
		}

		/**
		 * Returns a sanitized post title.
		 *
		 * @param mixed $post Post object.
		 * @return string
		 */
		private static function post_title( $post ): string {
			if ( function_exists( 'get_the_title' ) ) {
				return sanitize_text_field( (string) get_the_title( self::post_id_from_value( $post ) ) );
			}

			return sanitize_text_field( (string) ( $post->post_title ?? '' ) );
		}

		/**
		 * Returns a public post URL.
		 *
		 * @param int $post_id Post id.
		 * @return string
		 */
		private static function post_url( int $post_id ): string {
			if ( function_exists( 'get_permalink' ) ) {
				return esc_url_raw( (string) get_permalink( $post_id ) );
			}

			return '';
		}
	}
}
