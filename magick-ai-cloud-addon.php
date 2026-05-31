<?php
/**
 * Plugin Name:       Magick AI Cloud Addon
 * Description:       Cloud connector for Magick AI hosted runtime access, signing, health checks, and entitlement summaries.
 * Version:           0.1.0
 * Requires at least: 7.0
 * Requires PHP:      8.0
 * Author:            Npcink
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       magick-ai-cloud-addon
 * Domain Path:       /languages
 *
 * @package MagickAICloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'MAGICK_AI_CLOUD_ADDON_FILE' ) ) {
	define( 'MAGICK_AI_CLOUD_ADDON_FILE', __FILE__ );
}

if ( ! defined( 'MAGICK_AI_CLOUD_ADDON_DIR' ) ) {
	define( 'MAGICK_AI_CLOUD_ADDON_DIR', __DIR__ );
}

if ( ! defined( 'MAGICK_AI_CLOUD_ADDON_VERSION' ) ) {
	define( 'MAGICK_AI_CLOUD_ADDON_VERSION', '0.1.0' );
}

if ( ! defined( 'MAGICK_AI_CLOUD_ADDON_OPTION_NAME' ) ) {
	define( 'MAGICK_AI_CLOUD_ADDON_OPTION_NAME', 'magick_ai_cloud_addon_settings' );
}

require_once __DIR__ . '/includes/bootstrap.php';
