<?php
/**
 * Behavior tests for the WordPress AI connector registration seam.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once MACA_TEST_ROOT . '/includes/class-cloud-addon-settings.php';
require_once MACA_TEST_ROOT . '/includes/class-cloud-wordpress-ai-connector.php';

if ( ! class_exists( 'Maca_Connector_Registry_Stub' ) ) {
	/**
	 * Minimal connector registry stub.
	 */
	final class Maca_Connector_Registry_Stub {
		/**
		 * Registered connectors.
		 *
		 * @var array<string,array<string,mixed>>
		 */
		public $connectors = array();

		/**
		 * Registers a connector.
		 *
		 * @param string              $id Connector id.
		 * @param array<string,mixed> $args Connector args.
		 * @return array<string,mixed>
		 */
		public function register( string $id, array $args ): array {
			$this->connectors[ $id ] = $args;
			return $args;
		}

		/**
		 * Checks whether a connector exists.
		 *
		 * @param string $id Connector id.
		 * @return bool
		 */
		public function is_registered( string $id ): bool {
			return isset( $this->connectors[ $id ] );
		}

		/**
		 * Removes a connector.
		 *
		 * @param string $id Connector id.
		 * @return array<string,mixed>|null
		 */
		public function unregister( string $id ) {
			$connector = $this->connectors[ $id ] ?? null;
			unset( $this->connectors[ $id ] );
			return $connector;
		}
	}
}

maca_reset_test_state();
maca_seed_settings( true );

$registry = new Maca_Connector_Registry_Stub();
Npcink_Cloud_WordPress_AI_Connector::register_connector( $registry );
$connector = $registry->connectors[ Npcink_Cloud_WordPress_AI_Connector::CONNECTOR_ID ] ?? array();

maca_assert(
	'Npcink Cloud' === ( $connector['name'] ?? '' )
	&& 'ai_provider' === ( $connector['type'] ?? '' )
	&& 'api_key' === ( $connector['authentication']['method'] ?? '' )
	&& Npcink_Cloud_WordPress_AI_Connector::SETTING_NAME === ( $connector['authentication']['setting_name'] ?? '' ),
	'WordPress connector registry receives a fixed Npcink Cloud ai_provider card with a synthetic marker setting.'
);

maca_assert(
	'1' === get_option( Npcink_Cloud_WordPress_AI_Connector::SETTING_NAME, '' ),
	'Verified Cloud settings publish a synthetic connector marker without exposing the stored secret.'
);

$has_credentials = Npcink_Cloud_WordPress_AI_Connector::filter_has_ai_credentials( false, $registry->connectors );
maca_assert(
	true === $has_credentials,
	'AI plugin credential detection sees verified Npcink Cloud settings as available credentials.'
);

$preferred = Npcink_Cloud_WordPress_AI_Connector::filter_preferred_text_models(
	array(
		array( 'openai', 'gpt-4.1-mini' ),
	)
);
maca_assert(
	array( Npcink_Cloud_WordPress_AI_Connector::CONNECTOR_ID, Npcink_Cloud_WordPress_AI_Connector::MODEL_ID ) === $preferred[0],
	'Npcink Cloud scene text model is added as the first preferred AI text model.'
);

$preferred_images = Npcink_Cloud_WordPress_AI_Connector::filter_preferred_image_models(
	array(
		array( 'openai', 'gpt-image-2' ),
	)
);
maca_assert(
	array( Npcink_Cloud_WordPress_AI_Connector::CONNECTOR_ID, Npcink_Cloud_WordPress_AI_Connector::IMAGE_MODEL_ID ) === $preferred_images[0],
	'Npcink Cloud scene image model is added as the first preferred AI image model.'
);

maca_reset_test_state();
maca_seed_settings( false );
Npcink_Cloud_WordPress_AI_Connector::sync_connected_marker();
maca_assert(
	'' === get_option( Npcink_Cloud_WordPress_AI_Connector::SETTING_NAME, '' ),
	'Unverified Cloud settings do not publish the synthetic connector marker.'
);

$fallback_text_models = array(
	array( 'openai', 'gpt-4.1-mini' ),
);
$fallback_image_models = array(
	array( 'openai', 'gpt-image-2' ),
);
maca_assert(
	$fallback_text_models === Npcink_Cloud_WordPress_AI_Connector::filter_preferred_text_models( $fallback_text_models ),
	'Unverified Cloud settings do not change the preferred AI text model list.'
);
maca_assert(
	$fallback_image_models === Npcink_Cloud_WordPress_AI_Connector::filter_preferred_image_models( $fallback_image_models ),
	'Unverified Cloud settings do not change the preferred AI image model list.'
);

maca_reset_test_state();
maca_seed_settings( true );
maca_set_wordpress_ai_connector_enabled( false );
$disabled_registry = new Maca_Connector_Registry_Stub();
$disabled_registry->connectors[ Npcink_Cloud_WordPress_AI_Connector::CONNECTOR_ID ] = array(
	'name' => 'Stale Npcink Cloud',
);
Npcink_Cloud_WordPress_AI_Connector::register_connector( $disabled_registry );
maca_assert(
	! isset( $disabled_registry->connectors[ Npcink_Cloud_WordPress_AI_Connector::CONNECTOR_ID ] )
	&& '' === get_option( Npcink_Cloud_WordPress_AI_Connector::SETTING_NAME, '' ),
	'Disabled WordPress AI connector exposure removes the Npcink Cloud connector card and marker.'
);

maca_assert(
	false === Npcink_Cloud_WordPress_AI_Connector::filter_has_ai_credentials(
		false,
		array(
			Npcink_Cloud_WordPress_AI_Connector::CONNECTOR_ID => $connector,
		)
	),
	'Disabled WordPress AI connector exposure does not satisfy AI plugin credential detection.'
);

maca_assert(
	$fallback_text_models === Npcink_Cloud_WordPress_AI_Connector::filter_preferred_text_models( $fallback_text_models )
	&& $fallback_image_models === Npcink_Cloud_WordPress_AI_Connector::filter_preferred_image_models( $fallback_image_models ),
	'Disabled WordPress AI connector exposure does not change preferred AI model lists.'
);
