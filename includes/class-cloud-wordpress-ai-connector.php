<?php
/**
 * WordPress AI connector registration for the Cloud addon.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Npcink_Cloud_WordPress_AI_Connector' ) ) {
	/**
	 * Projects verified Cloud settings into the WordPress Connectors / AI Client surface.
	 */
	final class Npcink_Cloud_WordPress_AI_Connector {
		public const CONNECTOR_ID = 'npcink-cloud';
		public const CONNECTOR_NAME = 'Npcink Cloud';
		public const MODEL_ID = 'npcink-cloud-scene-text';
		public const IMAGE_MODEL_ID = 'npcink-cloud-scene-image';
		public const SETTING_NAME = 'npcink_cloud_addon_wp_ai_connector_connected';

		/**
		 * Registers hooks.
		 *
		 * @return void
		 */
		public static function register(): void {
			add_action( 'init', array( __CLASS__, 'register_ai_provider' ), 5 );
			add_action( 'wp_connectors_init', array( __CLASS__, 'register_connector' ) );
			add_action( 'admin_init', array( __CLASS__, 'sync_connected_marker' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_connectors_page_assets' ) );
			add_filter( 'wpai_has_ai_credentials', array( __CLASS__, 'filter_has_ai_credentials' ), 100, 2 );
			add_filter( 'wpai_preferred_text_models', array( __CLASS__, 'filter_preferred_text_models' ) );
			add_filter( 'wpai_preferred_image_models', array( __CLASS__, 'filter_preferred_image_models' ) );
			add_filter( 'wp_get_abilities_result', array( __CLASS__, 'prioritize_wordpress_ai_abilities_for_rest_list' ), 20, 2 );
		}

		/**
		 * Registers the optional PHP AI Client provider when the AI Client is loaded.
		 *
		 * @return void
		 */
		public static function register_ai_provider(): void {
			if ( ! self::is_cloud_connector_available() ) {
				return;
			}

			if ( ! class_exists( 'WordPress\\AiClient\\AiClient' ) || ! class_exists( 'Npcink_Cloud_WordPress_AI_Provider' ) ) {
				return;
			}

			$registry = \WordPress\AiClient\AiClient::defaultRegistry();
			if ( $registry->hasProvider( self::CONNECTOR_ID ) || $registry->hasProvider( 'Npcink_Cloud_WordPress_AI_Provider' ) ) {
				return;
			}

			$registry->registerProvider( 'Npcink_Cloud_WordPress_AI_Provider' );
		}

		/**
		 * Registers the fixed Npcink Cloud connector card.
		 *
		 * @param object $registry WordPress connector registry.
		 * @return void
		 */
		public static function register_connector( $registry ): void {
			self::register_marker_setting();
			self::sync_connected_marker();

			if ( ! is_object( $registry ) || ! method_exists( $registry, 'register' ) ) {
				return;
			}

			if ( method_exists( $registry, 'is_registered' ) && $registry->is_registered( self::CONNECTOR_ID ) ) {
				if ( method_exists( $registry, 'unregister' ) ) {
					$registry->unregister( self::CONNECTOR_ID );
				} else {
					return;
				}
			}

			if ( ! self::is_cloud_connector_available() ) {
				return;
			}

			$registry->register(
				self::CONNECTOR_ID,
				array(
					'name'           => self::CONNECTOR_NAME,
					'description'    => __( 'Use verified Npcink Cloud settings for bounded WordPress AI scene tasks.', 'npcink-cloud-addon' ),
					'type'           => 'ai_provider',
					'authentication' => array(
						'method'       => 'api_key',
						'setting_name' => self::SETTING_NAME,
					),
					'plugin'         => array(
						'file'      => function_exists( 'plugin_basename' ) ? plugin_basename( NPCINK_CLOUD_ADDON_FILE ) : '',
						'is_active' => '__return_true',
					),
				)
			);
		}

		/**
		 * Keeps the WordPress Connectors card status-only for this fixed Cloud connector.
		 *
		 * @param string $hook_suffix Admin hook suffix.
		 * @return void
		 */
		public static function enqueue_connectors_page_assets( string $hook_suffix ): void {
			if ( 'options-connectors' !== $hook_suffix ) {
				return;
			}

			wp_enqueue_style(
				'npcink-cloud-addon-admin',
				plugins_url( 'assets/admin.css', NPCINK_CLOUD_ADDON_FILE ),
				array(),
				NPCINK_CLOUD_ADDON_VERSION
			);
		}

		/**
		 * Keeps the synthetic connector credential marker aligned with Cloud verification.
		 *
		 * @return void
		 */
		public static function sync_connected_marker(): void {
			if ( self::is_cloud_connector_available() ) {
				update_option( self::SETTING_NAME, '1', false );
				return;
			}

			delete_option( self::SETTING_NAME );
		}

		/**
		 * Lets the AI plugin see verified Cloud settings as available credentials.
		 *
		 * @param bool                 $has_credentials Existing credential state.
		 * @param array<string,mixed>  $connectors Registered connectors.
		 * @return bool
		 */
		public static function filter_has_ai_credentials( bool $has_credentials, array $connectors ): bool {
			if ( $has_credentials ) {
				return true;
			}

			return isset( $connectors[ self::CONNECTOR_ID ] )
				&& class_exists( 'Npcink_Cloud_Addon_Settings' )
				&& Npcink_Cloud_Addon_Settings::is_wordpress_ai_connector_enabled();
		}

		/**
		 * Makes the scene-bound Cloud model the first preference when available.
		 *
		 * @param array<int,mixed> $preferred_models Existing preferred model list.
		 * @return array<int,mixed>
		 */
		public static function filter_preferred_text_models( array $preferred_models ): array {
			if ( ! self::is_cloud_connector_available() ) {
				return $preferred_models;
			}

			array_unshift( $preferred_models, array( self::CONNECTOR_ID, self::MODEL_ID ) );

			return $preferred_models;
		}

		/**
		 * Makes the scene-bound Cloud image model the first preference when available.
		 *
		 * @param array<int,mixed> $preferred_models Existing preferred model list.
		 * @return array<int,mixed>
		 */
		public static function filter_preferred_image_models( array $preferred_models ): array {
			if ( ! self::is_cloud_connector_available() ) {
				return $preferred_models;
			}

			array_unshift( $preferred_models, array( self::CONNECTOR_ID, self::IMAGE_MODEL_ID ) );

			return $preferred_models;
		}

		/**
		 * Keeps WordPress AI abilities discoverable on the default Abilities REST list.
		 *
		 * The AI plugin may use the unfiltered REST list as a client-side discovery
		 * cache. Busy local sites can exceed the first page before ai/* abilities are
		 * reached, which makes clients report "Ability not found" and fall back even
		 * though the individual ability endpoint and run callback work.
		 *
		 * @param array<string,mixed> $abilities Matched abilities keyed by ability name.
		 * @param array<string,mixed> $args      Query arguments passed to wp_get_abilities().
		 * @return array<string,mixed>
		 */
		public static function prioritize_wordpress_ai_abilities_for_rest_list( array $abilities, array $args ): array {
			if ( ! self::is_cloud_connector_available() || empty( $abilities ) ) {
				return $abilities;
			}

			if ( ! empty( $args['namespace'] ) || ! empty( $args['category'] ) ) {
				return $abilities;
			}

			$meta = isset( $args['meta'] ) && is_array( $args['meta'] ) ? $args['meta'] : array();
			if ( true !== ( $meta['show_in_rest'] ?? null ) ) {
				return $abilities;
			}

			$wordpress_ai = array();
			$others       = array();

			foreach ( $abilities as $key => $ability ) {
				$name = is_object( $ability ) && method_exists( $ability, 'get_name' )
					? (string) $ability->get_name()
					: (string) $key;

				if ( str_starts_with( $name, 'ai/' ) ) {
					$wordpress_ai[ $key ] = $ability;
					continue;
				}

				$others[ $key ] = $ability;
			}

			if ( empty( $wordpress_ai ) ) {
				return $abilities;
			}

			return $wordpress_ai + $others;
		}

		/**
		 * Registers the synthetic marker setting without exposing it through REST.
		 *
		 * @return void
		 */
		private static function register_marker_setting(): void {
			if ( ! function_exists( 'register_setting' ) ) {
				return;
			}

			if ( function_exists( 'get_registered_settings' ) ) {
				$registered = get_registered_settings();
				if ( isset( $registered[ self::SETTING_NAME ] ) ) {
					return;
				}
			}

				register_setting(
					'npcink_cloud_addon',
					self::SETTING_NAME,
					array(
						'type'         => 'string',
						'default'      => '',
						'show_in_rest' => false,
					)
				);
			}

			/**
			 * Checks whether verified Cloud settings may be exposed to WordPress AI.
			 *
			 * @return bool
			 */
			private static function is_cloud_connector_available(): bool {
				return class_exists( 'Npcink_Cloud_Addon_Settings' )
					&& Npcink_Cloud_Addon_Settings::is_wordpress_ai_connector_enabled();
			}
		}
	}

	if (
	class_exists( 'WordPress\\AiClient\\Providers\\AbstractProvider' )
	&& interface_exists( 'WordPress\\AiClient\\Providers\\Contracts\\ProviderAvailabilityInterface' )
	&& interface_exists( 'WordPress\\AiClient\\Providers\\Contracts\\ModelMetadataDirectoryInterface' )
	&& ! class_exists( 'Npcink_Cloud_WordPress_AI_Provider' )
) {
	/**
	 * Npcink Cloud provider metadata for the PHP AI Client.
	 */
	final class Npcink_Cloud_WordPress_AI_Provider extends \WordPress\AiClient\Providers\AbstractProvider {
		/**
		 * Creates the scene-bound text model.
		 *
		 * @param \WordPress\AiClient\Providers\Models\DTO\ModelMetadata $model_metadata Model metadata.
		 * @param \WordPress\AiClient\Providers\DTO\ProviderMetadata     $provider_metadata Provider metadata.
		 * @return \WordPress\AiClient\Providers\Models\Contracts\ModelInterface
		 */
		protected static function createModel(
			\WordPress\AiClient\Providers\Models\DTO\ModelMetadata $model_metadata,
			\WordPress\AiClient\Providers\DTO\ProviderMetadata $provider_metadata
		): \WordPress\AiClient\Providers\Models\Contracts\ModelInterface {
			if ( Npcink_Cloud_WordPress_AI_Connector::IMAGE_MODEL_ID === $model_metadata->getId() ) {
				return new Npcink_Cloud_WordPress_AI_Image_Model( $model_metadata, $provider_metadata );
			}

			return new Npcink_Cloud_WordPress_AI_Text_Model( $model_metadata, $provider_metadata );
		}

		/**
		 * Creates provider metadata.
		 *
		 * @return \WordPress\AiClient\Providers\DTO\ProviderMetadata
		 */
		protected static function createProviderMetadata(): \WordPress\AiClient\Providers\DTO\ProviderMetadata {
			return new \WordPress\AiClient\Providers\DTO\ProviderMetadata(
				Npcink_Cloud_WordPress_AI_Connector::CONNECTOR_ID,
				Npcink_Cloud_WordPress_AI_Connector::CONNECTOR_NAME,
				\WordPress\AiClient\Providers\Enums\ProviderTypeEnum::cloud(),
				function_exists( 'admin_url' ) ? admin_url( 'admin.php?page=npcink-cloud-addon' ) : null,
				\WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod::apiKey(),
				__( 'Bounded WordPress AI scene tasks through verified Npcink Cloud settings.', 'npcink-cloud-addon' )
			);
		}

		/**
		 * Creates provider availability.
		 *
		 * @return \WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface
		 */
		protected static function createProviderAvailability(): \WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface {
			return new Npcink_Cloud_WordPress_AI_Availability();
		}

		/**
		 * Creates the fixed model metadata directory.
		 *
		 * @return \WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface
		 */
		protected static function createModelMetadataDirectory(): \WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface {
			return new Npcink_Cloud_WordPress_AI_Model_Metadata_Directory();
		}
	}

	/**
	 * Availability is driven by Save and Verify plus local connector exposure consent.
	 */
	final class Npcink_Cloud_WordPress_AI_Availability implements \WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface {
		/**
		 * Checks whether verified Cloud settings may be exposed to WordPress AI.
		 *
		 * @return bool
		 */
		public function isConfigured(): bool {
			return class_exists( 'Npcink_Cloud_Addon_Settings' )
				&& Npcink_Cloud_Addon_Settings::is_wordpress_ai_connector_enabled();
		}
	}

	/**
	 * Fixed model directory for the Npcink Cloud scene text model.
	 */
	final class Npcink_Cloud_WordPress_AI_Model_Metadata_Directory implements \WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface {
		/**
		 * Lists available model metadata.
		 *
		 * @return list<\WordPress\AiClient\Providers\Models\DTO\ModelMetadata>
		 */
		public function listModelMetadata(): array {
			return array( $this->text_model_metadata(), $this->image_model_metadata() );
		}

		/**
		 * Checks if metadata exists for a model.
		 *
		 * @param string $model_id Model id.
		 * @return bool
		 */
		public function hasModelMetadata( string $model_id ): bool {
			return in_array(
				$model_id,
				array(
					Npcink_Cloud_WordPress_AI_Connector::MODEL_ID,
					Npcink_Cloud_WordPress_AI_Connector::IMAGE_MODEL_ID,
				),
				true
			);
		}

		/**
		 * Gets metadata for a model.
		 *
		 * @param string $model_id Model id.
		 * @return \WordPress\AiClient\Providers\Models\DTO\ModelMetadata
		 */
		public function getModelMetadata( string $model_id ): \WordPress\AiClient\Providers\Models\DTO\ModelMetadata {
			if ( ! $this->hasModelMetadata( $model_id ) ) {
				throw new \WordPress\AiClient\Common\Exception\InvalidArgumentException( 'Npcink Cloud model metadata not found.' );
			}

			if ( Npcink_Cloud_WordPress_AI_Connector::IMAGE_MODEL_ID === $model_id ) {
				return $this->image_model_metadata();
			}

			return $this->text_model_metadata();
		}

		/**
		 * Builds the fixed model metadata.
		 *
		 * @return \WordPress\AiClient\Providers\Models\DTO\ModelMetadata
		 */
		private function text_model_metadata(): \WordPress\AiClient\Providers\Models\DTO\ModelMetadata {
			return new \WordPress\AiClient\Providers\Models\DTO\ModelMetadata(
				Npcink_Cloud_WordPress_AI_Connector::MODEL_ID,
				'Npcink Cloud Scene Text',
				array(
					\WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::textGeneration(),
				),
				array(
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption(
						\WordPress\AiClient\Providers\Models\Enums\OptionEnum::inputModalities(),
						array( array( \WordPress\AiClient\Messages\Enums\ModalityEnum::text() ) )
					),
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption(
						\WordPress\AiClient\Providers\Models\Enums\OptionEnum::outputModalities(),
						array( array( \WordPress\AiClient\Messages\Enums\ModalityEnum::text() ) )
					),
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption(
						\WordPress\AiClient\Providers\Models\Enums\OptionEnum::outputMimeType(),
						array( 'text/plain', 'application/json' )
					),
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption( \WordPress\AiClient\Providers\Models\Enums\OptionEnum::outputSchema() ),
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption( \WordPress\AiClient\Providers\Models\Enums\OptionEnum::systemInstruction() ),
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption( \WordPress\AiClient\Providers\Models\Enums\OptionEnum::candidateCount() ),
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption( \WordPress\AiClient\Providers\Models\Enums\OptionEnum::maxTokens() ),
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption( \WordPress\AiClient\Providers\Models\Enums\OptionEnum::temperature() ),
				)
			);
		}

		/**
		 * Builds the fixed image generation model metadata.
		 *
		 * @return \WordPress\AiClient\Providers\Models\DTO\ModelMetadata
		 */
		private function image_model_metadata(): \WordPress\AiClient\Providers\Models\DTO\ModelMetadata {
			return new \WordPress\AiClient\Providers\Models\DTO\ModelMetadata(
				Npcink_Cloud_WordPress_AI_Connector::IMAGE_MODEL_ID,
				'Npcink Cloud Scene Image',
				array(
					\WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::imageGeneration(),
				),
				array(
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption(
						\WordPress\AiClient\Providers\Models\Enums\OptionEnum::inputModalities(),
						array( array( \WordPress\AiClient\Messages\Enums\ModalityEnum::text() ) )
					),
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption(
						\WordPress\AiClient\Providers\Models\Enums\OptionEnum::outputModalities(),
						array( array( \WordPress\AiClient\Messages\Enums\ModalityEnum::image() ) )
					),
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption(
						\WordPress\AiClient\Providers\Models\Enums\OptionEnum::outputFileType(),
						array(
							\WordPress\AiClient\Files\Enums\FileTypeEnum::inline(),
							\WordPress\AiClient\Files\Enums\FileTypeEnum::remote(),
						)
					),
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption(
						\WordPress\AiClient\Providers\Models\Enums\OptionEnum::outputMimeType(),
						array( 'image/png', 'image/jpeg', 'image/webp' )
					),
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption( \WordPress\AiClient\Providers\Models\Enums\OptionEnum::candidateCount() ),
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption( \WordPress\AiClient\Providers\Models\Enums\OptionEnum::outputMediaAspectRatio() ),
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption( \WordPress\AiClient\Providers\Models\Enums\OptionEnum::outputMediaOrientation() ),
				)
			);
		}
	}

	/**
	 * Scene-gated text model that forwards only known AI plugin ability calls to Cloud.
	 */
	final class Npcink_Cloud_WordPress_AI_Text_Model implements
		\WordPress\AiClient\Providers\Models\Contracts\ModelInterface,
		\WordPress\AiClient\Providers\Models\TextGeneration\Contracts\TextGenerationModelInterface {
		/**
		 * Model metadata.
		 *
		 * @var \WordPress\AiClient\Providers\Models\DTO\ModelMetadata
		 */
		private $metadata;

		/**
		 * Provider metadata.
		 *
		 * @var \WordPress\AiClient\Providers\DTO\ProviderMetadata
		 */
		private $provider_metadata;

		/**
		 * Model config.
		 *
		 * @var \WordPress\AiClient\Providers\Models\DTO\ModelConfig
		 */
		private $config;

		/**
		 * Constructor.
		 *
		 * @param \WordPress\AiClient\Providers\Models\DTO\ModelMetadata $metadata Model metadata.
		 * @param \WordPress\AiClient\Providers\DTO\ProviderMetadata     $provider_metadata Provider metadata.
		 */
		public function __construct(
			\WordPress\AiClient\Providers\Models\DTO\ModelMetadata $metadata,
			\WordPress\AiClient\Providers\DTO\ProviderMetadata $provider_metadata
		) {
			$this->metadata          = $metadata;
			$this->provider_metadata = $provider_metadata;
			$this->config            = new \WordPress\AiClient\Providers\Models\DTO\ModelConfig();
		}

		/**
		 * Gets model metadata.
		 *
		 * @return \WordPress\AiClient\Providers\Models\DTO\ModelMetadata
		 */
		public function metadata(): \WordPress\AiClient\Providers\Models\DTO\ModelMetadata {
			return $this->metadata;
		}

		/**
		 * Gets provider metadata.
		 *
		 * @return \WordPress\AiClient\Providers\DTO\ProviderMetadata
		 */
		public function providerMetadata(): \WordPress\AiClient\Providers\DTO\ProviderMetadata {
			return $this->provider_metadata;
		}

		/**
		 * Sets model config.
		 *
		 * @param \WordPress\AiClient\Providers\Models\DTO\ModelConfig $config Model config.
		 * @return void
		 */
		public function setConfig( \WordPress\AiClient\Providers\Models\DTO\ModelConfig $config ): void {
			$this->config = $config;
		}

		/**
		 * Gets model config.
		 *
		 * @return \WordPress\AiClient\Providers\Models\DTO\ModelConfig
		 */
		public function getConfig(): \WordPress\AiClient\Providers\Models\DTO\ModelConfig {
			return $this->config;
		}

		/**
		 * Generates a text result through the bounded Cloud runtime seam.
		 *
		 * @param list<\WordPress\AiClient\Messages\DTO\Message> $prompt Prompt messages.
		 * @return \WordPress\AiClient\Results\DTO\GenerativeAiResult
		 */
		public function generateTextResult( array $prompt ): \WordPress\AiClient\Results\DTO\GenerativeAiResult {
			$task = $this->detect_scene_task();
			if ( '' === $task ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( 'Npcink Cloud AI connector only accepts known WordPress AI ability scene calls.' );
			}

			if ( 1 !== count( $prompt ) ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( 'Npcink Cloud AI connector does not support chat history.' );
			}

			if ( null !== $this->config->getFunctionDeclarations() || null !== $this->config->getWebSearch() ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( 'Npcink Cloud AI connector does not support tools or web search.' );
			}

			$text = $this->prompt_text( $prompt );
			if ( '' === $text ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( 'Npcink Cloud AI connector requires text scene input.' );
			}

			$request = array(
				'contract_version' => 'wp_ai_connector_runtime.v1',
				'task'             => $task,
				'prompt'           => $text,
				'input'            => array(
					'prompt'             => $text,
					'system_instruction' => (string) ( $this->config->getSystemInstruction() ?? '' ),
					'response_format'    => $this->response_format_hint( $task ),
					'candidate_count'    => $this->config->getCandidateCount(),
					'max_tokens'         => $this->config->getMaxTokens(),
					'temperature'        => $this->config->getTemperature(),
					'scene_gate'         => array(
						'source' => 'wordpress_ai_plugin_ability',
						'task'   => $task,
					),
				),
				'timeout_seconds'  => 60,
				'retention_ttl'    => 86400,
				'retry_max'        => 0,
			);

			$response = npcink_cloud_addon_execute_wordpress_ai_connector_runtime(
				$request,
				'trace_wp_ai_connector_' . wp_generate_uuid4(),
				'wp_ai_connector_' . wp_generate_uuid4()
			);

			if ( is_wp_error( $response ) ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( esc_html( $response->get_error_message() ) );
			}

			$output_text = $this->extract_text( is_array( $response ) ? $response : array() );
			if ( '' === $output_text ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( 'Npcink Cloud AI connector response did not include text output.' );
			}

			return new \WordPress\AiClient\Results\DTO\GenerativeAiResult(
				(string) ( $response['run_id'] ?? ( $response['data']['run_id'] ?? wp_generate_uuid4() ) ),
				array(
					new \WordPress\AiClient\Results\DTO\Candidate(
						new \WordPress\AiClient\Messages\DTO\ModelMessage(
							array( new \WordPress\AiClient\Messages\DTO\MessagePart( $output_text ) )
						),
						\WordPress\AiClient\Results\Enums\FinishReasonEnum::stop()
					),
				),
				new \WordPress\AiClient\Results\DTO\TokenUsage( 0, 0, 0 ),
				$this->provider_metadata,
				$this->metadata,
				array(
					'contract_version' => 'wp_ai_connector_result.v1',
					'task'             => $task,
					'suggestion_only'  => true,
				)
			);
		}

		/**
		 * Extracts text from a single user prompt.
		 *
		 * @param list<\WordPress\AiClient\Messages\DTO\Message> $prompt Prompt messages.
		 * @return string
		 */
		private function prompt_text( array $prompt ): string {
			$message = $prompt[0];
			if ( ! $message->getRole()->isUser() ) {
				return '';
			}

			$parts = array();
			foreach ( $message->getParts() as $part ) {
				$text = $part->getText();
				if ( null !== $text && '' !== trim( $text ) ) {
					$parts[] = trim( $text );
				}
			}

			return trim( implode( "\n\n", $parts ) );
		}

		/**
		 * Returns a shallow response-format hint for Cloud-side scene projection.
		 *
		 * @param string $task WordPress AI ability task.
		 * @return string
		 */
		private function response_format_hint( string $task ): string {
			return in_array( $task, array( 'content_classification', 'comment_moderation' ), true ) ? 'json' : 'text';
		}

		/**
		 * Detects the WordPress AI ability scene from the current call stack.
		 *
		 * @return string
		 */
		private function detect_scene_task(): string {
			$map = array(
				'WordPress\\AI\\Abilities\\Content_Classification\\Content_Classification' => 'content_classification',
				'WordPress\\AI\\Abilities\\Comment_Moderation\\Comment_Analysis'           => 'comment_moderation',
				'WordPress\\AI\\Abilities\\Content_Resizing\\Content_Resizing'              => 'content_rewrite',
				'WordPress\\AI\\Abilities\\Editorial_Updates\\Editorial_Updates'            => 'content_rewrite',
				'WordPress\\AI\\Abilities\\Editorial_Notes\\Editorial_Notes'                => 'content_summary',
				'WordPress\\AI\\Abilities\\Excerpt_Generation\\Excerpt_Generation'          => 'excerpt_generation',
				'WordPress\\AI\\Abilities\\Meta_Description\\Meta_Description'              => 'meta_description',
				'WordPress\\AI\\Abilities\\Title_Generation\\Title_Generation'              => 'title_generation',
				'WordPress\\AI\\Abilities\\Summarization\\Summarization'                    => 'content_summary',
				'WordPress\\AI\\Abilities\\Image\\Alt_Text_Generation'                      => 'alt_text_suggest',
			);

			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- Bounded stack inspection gates calls to known WordPress AI ability classes.
			foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 24 ) as $frame ) {
				$class = isset( $frame['class'] ) ? (string) $frame['class'] : '';
				if ( isset( $map[ $class ] ) ) {
					return $map[ $class ];
				}
			}

			return '';
		}

		/**
		 * Extracts text from common Cloud runtime result shapes.
		 *
		 * @param array<string,mixed> $response Cloud response.
		 * @return string
		 */
		private function extract_text( array $response ): string {
			$candidates = array(
				$response['text'] ?? null,
				$response['content'] ?? null,
				$response['output_text'] ?? null,
				$response['result']['text'] ?? null,
				$response['result']['content'] ?? null,
				$response['result']['output_text'] ?? null,
				$response['data']['text'] ?? null,
				$response['data']['content'] ?? null,
				$response['data']['output_text'] ?? null,
				$response['data']['result']['text'] ?? null,
				$response['data']['result']['content'] ?? null,
				$response['data']['result']['output_text'] ?? null,
			);

			foreach ( $candidates as $candidate ) {
				if ( is_string( $candidate ) && '' !== trim( $candidate ) ) {
					return trim( $candidate );
				}
			}

			if ( isset( $response['choices'][0]['text'] ) && is_string( $response['choices'][0]['text'] ) ) {
				return trim( $response['choices'][0]['text'] );
			}
			if ( isset( $response['data']['choices'][0]['text'] ) && is_string( $response['data']['choices'][0]['text'] ) ) {
				return trim( $response['data']['choices'][0]['text'] );
			}

			return '';
		}
	}

	/**
	 * Scene-gated image model that forwards text-to-image WordPress AI calls to Cloud.
	 */
	final class Npcink_Cloud_WordPress_AI_Image_Model implements
		\WordPress\AiClient\Providers\Models\Contracts\ModelInterface,
		\WordPress\AiClient\Providers\Models\ImageGeneration\Contracts\ImageGenerationModelInterface {
		/**
		 * Model metadata.
		 *
		 * @var \WordPress\AiClient\Providers\Models\DTO\ModelMetadata
		 */
		private $metadata;

		/**
		 * Provider metadata.
		 *
		 * @var \WordPress\AiClient\Providers\DTO\ProviderMetadata
		 */
		private $provider_metadata;

		/**
		 * Model config.
		 *
		 * @var \WordPress\AiClient\Providers\Models\DTO\ModelConfig
		 */
		private $config;

		/**
		 * Constructor.
		 *
		 * @param \WordPress\AiClient\Providers\Models\DTO\ModelMetadata $metadata Model metadata.
		 * @param \WordPress\AiClient\Providers\DTO\ProviderMetadata     $provider_metadata Provider metadata.
		 */
		public function __construct(
			\WordPress\AiClient\Providers\Models\DTO\ModelMetadata $metadata,
			\WordPress\AiClient\Providers\DTO\ProviderMetadata $provider_metadata
		) {
			$this->metadata          = $metadata;
			$this->provider_metadata = $provider_metadata;
			$this->config            = new \WordPress\AiClient\Providers\Models\DTO\ModelConfig();
		}

		/**
		 * Gets model metadata.
		 *
		 * @return \WordPress\AiClient\Providers\Models\DTO\ModelMetadata
		 */
		public function metadata(): \WordPress\AiClient\Providers\Models\DTO\ModelMetadata {
			return $this->metadata;
		}

		/**
		 * Gets provider metadata.
		 *
		 * @return \WordPress\AiClient\Providers\DTO\ProviderMetadata
		 */
		public function providerMetadata(): \WordPress\AiClient\Providers\DTO\ProviderMetadata {
			return $this->provider_metadata;
		}

		/**
		 * Sets model config.
		 *
		 * @param \WordPress\AiClient\Providers\Models\DTO\ModelConfig $config Model config.
		 * @return void
		 */
		public function setConfig( \WordPress\AiClient\Providers\Models\DTO\ModelConfig $config ): void {
			$this->config = $config;
		}

		/**
		 * Gets model config.
		 *
		 * @return \WordPress\AiClient\Providers\Models\DTO\ModelConfig
		 */
		public function getConfig(): \WordPress\AiClient\Providers\Models\DTO\ModelConfig {
			return $this->config;
		}

		/**
		 * Generates an image result through the bounded Cloud runtime seam.
		 *
		 * @param list<\WordPress\AiClient\Messages\DTO\Message> $prompt Prompt messages.
		 * @return \WordPress\AiClient\Results\DTO\GenerativeAiResult
		 */
		public function generateImageResult( array $prompt ): \WordPress\AiClient\Results\DTO\GenerativeAiResult {
			if ( 1 !== count( $prompt ) ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( 'Npcink Cloud AI image connector does not support chat history.' );
			}

			if ( null !== $this->config->getFunctionDeclarations() || null !== $this->config->getWebSearch() ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( 'Npcink Cloud AI image connector does not support tools or web search.' );
			}

			$text = $this->prompt_text( $prompt );
			if ( '' === $text ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( 'Npcink Cloud AI image connector requires text scene input.' );
			}

			$request = array(
				'contract_version' => 'image_generation_request.v1',
				'task'             => 'image_generation',
				'prompt'           => $text,
				'n'                => $this->image_count(),
				'response_format'  => $this->response_format(),
				'aspect_ratio'     => $this->aspect_ratio(),
				'resolution'       => 'medium',
				'timeout_seconds'  => 90,
				'retention_ttl'    => 86400,
			);

			$response = npcink_cloud_addon_execute_wordpress_ai_image_generation_runtime(
				$request,
				'trace_wp_ai_image_' . wp_generate_uuid4(),
				'wp_ai_image_' . wp_generate_uuid4()
			);

			if ( is_wp_error( $response ) ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( esc_html( $response->get_error_message() ) );
			}

			$result     = $this->extract_result( is_array( $response ) ? $response : array() );
			$candidates = $this->extract_image_candidates( $result );
			if ( empty( $candidates ) ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( 'Npcink Cloud AI image connector response did not include image output.' );
			}

			return new \WordPress\AiClient\Results\DTO\GenerativeAiResult(
				(string) ( $response['run_id'] ?? ( $response['data']['run_id'] ?? wp_generate_uuid4() ) ),
				$candidates,
				new \WordPress\AiClient\Results\DTO\TokenUsage( 0, 0, 0 ),
				$this->provider_metadata,
				$this->metadata,
				array(
					'contract_version'          => 'image_generation_result.v1',
					'task'                      => 'image_generation',
					'suggestion_only'           => true,
					'direct_wordpress_write'    => false,
					'model_id'                  => (string) ( $result['model_id'] ?? '' ),
					'provider_response_format'  => (string) ( $result['provider_response_format'] ?? '' ),
				)
			);
		}

		/**
		 * Extracts text from a single user prompt and rejects reference-image refinement.
		 *
		 * @param list<\WordPress\AiClient\Messages\DTO\Message> $prompt Prompt messages.
		 * @return string
		 */
		private function prompt_text( array $prompt ): string {
			$message = $prompt[0];
			if ( ! $message->getRole()->isUser() ) {
				return '';
			}

			$parts = array();
			foreach ( $message->getParts() as $part ) {
				if ( null !== $part->getFile() ) {
					throw new \WordPress\AiClient\Common\Exception\RuntimeException( 'Npcink Cloud AI image connector does not support reference image refinement yet.' );
				}

				$text = $part->getText();
				if ( null !== $text && '' !== trim( $text ) ) {
					$parts[] = trim( $text );
				}
			}

			return trim( implode( "\n\n", $parts ) );
		}

		/**
		 * Returns the requested image candidate count.
		 *
		 * @return int
		 */
		private function image_count(): int {
			$count = $this->config->getCandidateCount();

			return min( 4, max( 1, null === $count ? 1 : (int) $count ) );
		}

		/**
		 * Returns the Cloud image response format.
		 *
		 * @return string
		 */
		private function response_format(): string {
			$file_type = $this->config->getOutputFileType();

			return ( null !== $file_type && $file_type->isRemote() ) ? 'url' : 'b64_json';
		}

		/**
		 * Returns a Cloud-supported aspect ratio.
		 *
		 * @return string
		 */
		private function aspect_ratio(): string {
			$aspect_ratio = $this->config->getOutputMediaAspectRatio();
			if ( is_string( $aspect_ratio ) && '' !== $aspect_ratio ) {
				return $aspect_ratio;
			}

			$orientation = $this->config->getOutputMediaOrientation();
			if ( null !== $orientation ) {
				if ( $orientation->isLandscape() ) {
					return '16:9';
				}
				if ( $orientation->isPortrait() ) {
					return '9:16';
				}
			}

			return '1:1';
		}

		/**
		 * Extracts the Cloud image result payload.
		 *
		 * @param array<string,mixed> $response Cloud response.
		 * @return array<string,mixed>
		 */
		private function extract_result( array $response ): array {
			if ( isset( $response['data']['result'] ) && is_array( $response['data']['result'] ) ) {
				return $response['data']['result'];
			}
			if ( isset( $response['result'] ) && is_array( $response['result'] ) ) {
				return $response['result'];
			}
			if ( isset( $response['data'] ) && is_array( $response['data'] ) ) {
				return $response['data'];
			}

			return $response;
		}

		/**
		 * Extracts image candidates from a Cloud image generation response.
		 *
		 * @param array<string,mixed> $result Cloud result payload.
		 * @return list<\WordPress\AiClient\Results\DTO\Candidate>
		 */
		private function extract_image_candidates( array $result ): array {
			$images = isset( $result['images'] ) && is_array( $result['images'] ) ? $result['images'] : array();
			if ( empty( $images ) && isset( $result['data'] ) && is_array( $result['data'] ) ) {
				$images = $result['data'];
			}

			$candidates = array();
			foreach ( $images as $image ) {
				if ( ! is_array( $image ) ) {
					continue;
				}

				$mime_type = isset( $image['mime_type'] ) && is_string( $image['mime_type'] ) && '' !== $image['mime_type']
					? $image['mime_type']
					: 'image/png';
				$file_data = '';
				if ( isset( $image['b64_json'] ) && is_string( $image['b64_json'] ) && '' !== $image['b64_json'] ) {
					$file_data = $image['b64_json'];
				} elseif ( isset( $image['url'] ) && is_string( $image['url'] ) && '' !== $image['url'] ) {
					$file_data = $image['url'];
				}

				if ( '' === $file_data ) {
					continue;
				}

				$file = new \WordPress\AiClient\Files\DTO\File( $file_data, $mime_type );
				$candidates[] = new \WordPress\AiClient\Results\DTO\Candidate(
					new \WordPress\AiClient\Messages\DTO\Message(
						\WordPress\AiClient\Messages\Enums\MessageRoleEnum::model(),
						array( new \WordPress\AiClient\Messages\DTO\MessagePart( $file ) )
					),
					\WordPress\AiClient\Results\Enums\FinishReasonEnum::stop()
				);
			}

			return $candidates;
		}
	}
}
