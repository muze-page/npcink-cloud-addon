<?php
/**
 * Uninstall cleanup for Magick AI Cloud Addon.
 *
 * @package MagickAICloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'magick_ai_cloud_addon_settings' );
