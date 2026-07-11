<?php
/**
 * Bounded fallback localization for this addon admin UI.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Npcink_Cloud_Addon_Localization' ) ) {
	/**
	 * Provides a narrow zh_CN fallback for fixed addon strings when site language packs lag.
	 */
	final class Npcink_Cloud_Addon_Localization {
		private const TEXT_DOMAIN = 'npcink-cloud-addon';

		/**
		 * Registers addon-owned localization hooks.
		 *
		 * @return void
		 */
		public static function register(): void {
			add_filter( 'gettext', array( __CLASS__, 'filter_gettext' ), 20, 3 );
		}

		/**
		 * Translates selected addon strings only when no upstream zh_CN translation exists.
		 *
		 * @param string $translation Existing translation.
		 * @param string $text Original English string.
		 * @param string $domain Text domain.
		 * @return string
		 */
		public static function filter_gettext( string $translation, string $text, string $domain ): string {
			if ( self::TEXT_DOMAIN !== $domain || ! self::should_localize() ) {
				return $translation;
			}

			if ( '' !== $translation && $translation !== $text ) {
				return $translation;
			}

			$translations = self::translations();

			return $translations[ $text ] ?? $translation;
		}

		/**
		 * Returns whether the fallback translations should run.
		 *
		 * @return bool
		 */
		public static function should_localize(): bool {
			if ( function_exists( 'is_admin' ) && ! is_admin() ) {
				return false;
			}

			$locale = '';
			if ( function_exists( 'determine_locale' ) ) {
				$locale = (string) determine_locale();
			} elseif ( function_exists( 'get_user_locale' ) ) {
				$locale = (string) get_user_locale();
			} elseif ( function_exists( 'get_locale' ) ) {
				$locale = (string) get_locale();
			}

			if ( '' === $locale ) {
				return false;
			}

			return 0 === strpos( str_replace( '-', '_', strtolower( $locale ) ), 'zh_' );
		}

		/**
		 * Returns fixed addon admin translation fallbacks.
		 *
		 * @return array<string,string>
		 */
		public static function translations(): array {
			return array(
				'Enter a Cloud Base URL before starting self-hosted authorization.' => '请输入 Cloud Base URL 后再开始自托管授权。',
				'Connection context' => '连接上下文',
				'Advanced connection' => '高级连接',
				'Self-hosted Cloud endpoint' => '自托管 Cloud 端点',
				'Authorize with this endpoint' => '使用此端点授权',
				'For compatible Npcink Cloud deployments only. Cloud still owns site activation and key issuance.' => '仅用于兼容的 Npcink Cloud 部署。Cloud 仍负责站点激活和密钥签发。',
				'This does not manage Cloud sites, keys, billing, models, router, workflows, or runtime policy.' => '这里不管理 Cloud 站点、密钥、账单、模型、路由器、工作流或运行时策略。',
				'Local permissions' => '本地授权',
				'Status' => '状态',
				'Site Knowledge' => '站点知识库',
				'Troubleshooting' => '排查',
				'Connection Management' => '连接管理',
				'WordPress AI connector' => 'WordPress AI 连接器',
				'Allow the WordPress AI plugin to select Npcink Cloud as an AI connector.' => '允许 WordPress AI 插件选择 Npcink Cloud 作为 AI 连接器。',
				'Site Knowledge delivery' => '站点知识库投递',
				'Allow public content-change delivery and explicit administrator delivery intents for Cloud-owned Site Knowledge indexing.' => '允许为 Cloud 拥有的站点知识库索引投递公开内容变更，并发送管理员明确的投递意图。',
				'Reference site content during generation' => '生成时参考站点内容',
				'Allow Npcink Cloud to reference indexed public articles during supported WordPress AI generation tasks so suggestions better match this site\'s writing style and taxonomy. WordPress content is not changed.' => '允许 Npcink Cloud 在支持的 WordPress AI 生成任务中参考已索引的公开文章，使建议更贴近本站写作风格和分类习惯。不会更改 WordPress 内容。',
				'AI generation reference' => 'AI 生成参考',
				'enabled for supported editor tasks' => '已为支持的编辑器任务启用',
				'Monitoring' => '监控',
				'Upload metadata-only plugin monitoring events. Prompts, content, results, secrets, and raw request payloads are not collected.' => '上传仅包含元数据的插件监控事件。不会收集提示词、内容、结果、secret 或原始请求 payload。',
				'Choose which verified Cloud connector services this WordPress site may expose locally. Changes save immediately.' => '选择此 WordPress 站点可在本地公开哪些已验证的 Cloud 连接器服务。更改会立即保存。',
				'Delivery is off; refresh controls and routine delivery rows are hidden.' => '投递已关闭；刷新控件和常规投递行已隐藏。',
				'Bridge health detail' => '桥接健康详情',
				'Health contract' => '健康合同',
				'Last success' => '上次成功',
				'Last error code' => '上次错误代码',
				'Last error time' => '上次错误时间',
				'Delivery attempts' => '投递尝试',
				'Total sent' => '已发送总数',
				'Last index action time' => '上次索引操作时间',
				'Last index action sent' => '上次索引操作发送数',
				'Next reconcile' => '下次同步校准',
				'WP-Cron disabled' => 'WP-Cron 已禁用',
				'Manual flush command' => '手动刷新命令',
				'This detail is local connector health only; Cloud remains the owner of indexing, freshness policy, collection lifecycle, and diagnostics.' => '此详情仅表示本地连接器健康状态；Cloud 仍拥有索引、新鲜度策略、集合生命周期和诊断。',
			);
		}
	}
}
