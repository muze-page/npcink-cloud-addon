<?php
/**
 * Behavior tests for bounded addon fallback localization.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once MACA_TEST_ROOT . '/includes/class-cloud-addon-localization.php';

$GLOBALS['maca_is_admin'] = true;
$GLOBALS['maca_locale'] = 'zh_CN';

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin(): bool {
		return (bool) ( $GLOBALS['maca_is_admin'] ?? false );
	}
}

if ( ! function_exists( 'determine_locale' ) ) {
	function determine_locale(): string {
		return (string) ( $GLOBALS['maca_locale'] ?? 'en_US' );
	}
}

maca_assert(
	'高级连接' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Advanced connection',
		'Advanced connection',
		'npcink-cloud-addon'
	)
	&& '使用此端点授权' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Authorize with this endpoint',
		'Authorize with this endpoint',
		'npcink-cloud-addon'
	)
	&& '本地授权' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Local permissions',
		'Local permissions',
		'npcink-cloud-addon'
	)
	&& '站点知识库' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Site Knowledge',
		'Site Knowledge',
		'npcink-cloud-addon'
	),
	'Addon localization fallback translates fixed npcink-cloud-addon strings in zh_CN admin.'
);

maca_assert(
	'允许 WordPress AI 插件选择 Npcink Cloud 作为 AI 连接器。' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Allow the WordPress AI plugin to select Npcink Cloud as an AI connector.',
		'Allow the WordPress AI plugin to select Npcink Cloud as an AI connector.',
		'npcink-cloud-addon'
	)
	&& '选择此 WordPress 站点可在本地公开哪些已验证的 Cloud 连接器服务。更改会立即保存。' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Choose which verified Cloud connector services this WordPress site may expose locally. Changes save immediately.',
		'Choose which verified Cloud connector services this WordPress site may expose locally. Changes save immediately.',
		'npcink-cloud-addon'
	)
	&& '生成时参考站点内容' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Reference site content during generation',
		'Reference site content during generation',
		'npcink-cloud-addon'
	)
	&& '已为标题生成启用' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'enabled for title generation',
		'enabled for title generation',
		'npcink-cloud-addon'
	),
	'Addon localization fallback covers verified local permissions admin copy.'
);

maca_assert(
	'桥接健康详情' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Bridge health detail',
		'Bridge health detail',
		'npcink-cloud-addon'
	)
	&& '手动刷新命令' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Manual flush command',
		'Manual flush command',
		'npcink-cloud-addon'
	)
	&& '此详情仅表示本地连接器健康状态；Cloud 仍拥有索引、新鲜度策略、集合生命周期和诊断。' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'This detail is local connector health only; Cloud remains the owner of indexing, freshness policy, collection lifecycle, and diagnostics.',
		'This detail is local connector health only; Cloud remains the owner of indexing, freshness policy, collection lifecycle, and diagnostics.',
		'npcink-cloud-addon'
	),
	'Addon localization fallback covers Site Knowledge bridge health detail copy.'
);

maca_assert(
	'已有翻译' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'已有翻译',
		'Advanced connection',
		'npcink-cloud-addon'
	),
	'Addon localization fallback preserves existing language-pack translations.'
);

$GLOBALS['maca_locale'] = 'en_US';
maca_assert(
	'Advanced connection' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Advanced connection',
		'Advanced connection',
		'npcink-cloud-addon'
	),
	'Addon localization fallback does not translate outside zh locales.'
);

$GLOBALS['maca_locale'] = 'zh_CN';
maca_assert(
	'Advanced connection' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Advanced connection',
		'Advanced connection',
		'other-domain'
	),
	'Addon localization fallback is limited to the npcink-cloud-addon text domain.'
);
