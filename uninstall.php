<?php
/**
 * Uninstall cleanup for Npcink Cloud Addon.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'npcink_cloud_addon_settings' );
delete_option( 'magick_ai_cloud_addon_settings' );
delete_option( 'npcink_cloud_addon_observability_buffer' );
delete_option( 'npcink_cloud_addon_observability_status' );
delete_option( 'npcink_cloud_addon_observability_summary' );
delete_option( 'npcink_cloud_addon_site_knowledge_change_buffer' );
delete_option( 'npcink_cloud_addon_site_knowledge_change_status' );
wp_clear_scheduled_hook( 'npcink_cloud_addon_flush_observability' );
wp_clear_scheduled_hook( 'npcink_cloud_addon_flush_site_knowledge_changes' );
wp_clear_scheduled_hook( 'npcink_cloud_addon_reconcile_site_knowledge_changes' );
