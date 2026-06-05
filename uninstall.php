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
delete_option( 'npcink_cloud_addon_observability_buffer' );
delete_option( 'npcink_cloud_addon_observability_status' );
delete_option( 'npcink_cloud_addon_observability_summary' );
wp_clear_scheduled_hook( 'npcink_cloud_addon_flush_observability' );
