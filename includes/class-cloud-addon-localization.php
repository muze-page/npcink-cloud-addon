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
				'Overview' => '概览',
				'Advanced and troubleshooting' => '高级与排查',
				'Service summary' => '服务摘要',
				'View service details' => '查看服务详情',
				'Plan and entitlement' => '套餐与权益',
				'Monitoring needs attention' => '监控需要处理',
				'Site Knowledge needs attention' => '站点知识库需要处理',
				'Service details' => '服务详情',
				'Connection recovery' => '连接恢复',
				'Status' => '状态',
				'Site Knowledge' => '站点知识库',
				'Troubleshooting' => '排查',
				'Connection Management' => '连接管理',
				'WordPress AI connector' => 'WordPress AI 连接器',
				'Allow WordPress AI to use Npcink Cloud.' => '允许 WordPress AI 使用 Npcink Cloud。',
				'Site Knowledge delivery' => '站点知识库投递',
				'Send public content changes to Cloud Site Knowledge.' => '将公开内容变更发送到 Cloud 站点知识库。',
				'Reference site content during generation' => '生成时参考站点内容',
				'Use indexed public articles as generation context.' => '使用已索引的公开文章作为生成上下文。',
				'Allow Npcink Cloud to reference indexed public articles when generating titles and summaries so suggestions better match this site\'s writing style. WordPress content is not changed.' => '允许 Npcink Cloud 在生成标题和摘要时参考已索引的公开文章，使建议更贴近本站写作风格。不会更改 WordPress 内容。',
				'AI generation reference' => 'AI 生成参考',
				'enabled for supported editor tasks' => '已为支持的编辑器任务启用',
				'The AI task contract does not match the requested task.' => 'AI 任务契约与请求的任务不匹配。',
				'Monitoring' => '监控',
				'Upload metadata-only plugin monitoring events.' => '上传仅包含元数据的插件监控事件。',
				'More local permissions' => '更多本地授权',
				'Run the bounded connection checks or open Cloud for service detail.' => '运行有限的连接检查，或前往 Cloud 查看服务详情。',
				'Credentials' => '凭据',
				'Cloud connection' => 'Cloud 连接',
				'Last checked: %1$s · Signed read: %2$s' => '上次检查：%1$s · 签名读取：%2$s',
				'Run readiness test' => '运行就绪检查',
				'Readiness result' => '就绪检查结果',
				'Inspect by run ID' => '按运行 ID 检查',
				'Cloud error classification' => 'Cloud 错误分类',
				'Cloud error classification.' => 'Cloud 错误分类。',
				'Credits' => '额度',
				'%1$s used / %2$s limit / %3$s remaining' => '已用 %1$s / 上限 %2$s / 剩余 %3$s',
				'Delivery is off; refresh controls and routine delivery rows are hidden.' => '投递已关闭；刷新控件和常规投递行已隐藏。',
				'Change in Overview' => '在概览中更改',
				'Manage index' => '管理索引',
				'Back to Site Knowledge' => '返回站点知识库',
				'Technical delivery details' => '技术投递详情',
				'Bridge health detail' => '桥接健康详情',
				'Last success' => '上次成功',
				'Last error code' => '上次错误代码',
				'Last error time' => '上次错误时间',
				'WP-Cron disabled' => 'WP-Cron 已禁用',
				'Manual flush command' => '手动刷新命令',
			);
		}
	}
}
