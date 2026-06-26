<?php
/**
 * Behavior tests for the bounded AI plugin localization shim.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

if ( ! defined( 'NPCINK_CLOUD_ADDON_FILE' ) ) {
	define( 'NPCINK_CLOUD_ADDON_FILE', MACA_TEST_ROOT . '/npcink-cloud-addon.php' );
}
if ( ! defined( 'NPCINK_CLOUD_ADDON_VERSION' ) ) {
	define( 'NPCINK_CLOUD_ADDON_VERSION', '0.1.0-test' );
}

require_once MACA_TEST_ROOT . '/includes/class-ai-plugin-localization.php';

$GLOBALS['maca_is_admin'] = true;
$GLOBALS['maca_locale'] = 'zh_CN';
$GLOBALS['maca_enqueued_scripts'] = array();
$GLOBALS['maca_localized_scripts'] = array();

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

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( string $handle, string $src = '', array $deps = array(), string $version = '', bool $in_footer = false ): void {
		$GLOBALS['maca_enqueued_scripts'][] = array(
			'handle' => $handle,
			'src' => $src,
			'deps' => $deps,
			'version' => $version,
			'in_footer' => $in_footer,
		);
	}
}

if ( ! function_exists( 'plugins_url' ) ) {
	function plugins_url( string $path = '', string $plugin = '' ): string {
		return 'https://example.test/wp-content/plugins/npcink-cloud-addon/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'wp_localize_script' ) ) {
	function wp_localize_script( string $handle, string $object_name, array $data ): void {
		$GLOBALS['maca_localized_scripts'][] = array(
			'handle' => $handle,
			'object_name' => $object_name,
			'data' => $data,
		);
	}
}

maca_assert(
	'生成图片' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Generate Image',
		'Generate Image',
		'ai'
	),
	'AI plugin localization translates fixed ai-domain PHP strings in zh_CN admin.'
);

maca_assert(
	'能力浏览器' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Abilities Explorer',
		'Abilities Explorer',
		'ai'
	)
	&& '连接器审批' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Connector Approval',
		'Connector Approval',
		'ai'
	),
	'AI plugin localization translates admin experiment feature labels.'
);

maca_assert(
	'生成摘要' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Generate Summary',
		'Generate Summary',
		'ai'
	)
	&& '生成编辑建议' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Generate Editorial Notes',
		'Generate Editorial Notes',
		'ai'
	)
	&& '生成 SEO 描述' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Generate Meta Description',
		'Generate Meta Description',
		'ai'
	),
	'AI plugin localization translates editor action labels.'
);

maca_assert(
	'分类策略' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Taxonomy Strategy',
		'Taxonomy Strategy',
		'ai'
	)
	&& '最大建议数量' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Maximum Suggestions',
		'Maximum Suggestions',
		'ai'
	),
	'AI plugin localization translates editor setting labels before CSS text transforms.'
);

maca_assert(
	'最近 24 小时' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Last 24 Hours',
		'Last 24 Hours',
		'ai'
	)
	&& '请求' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Requests',
		'Requests',
		'ai'
	)
	&& '清空所有日志' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Purge All Logs',
		'Purge All Logs',
		'ai'
	),
	'AI plugin localization translates request log page labels.'
);

maca_assert(
	'能力总数' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Total Abilities',
		'Total Abilities',
		'ai'
	)
	&& '所有提供方' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'All Providers',
		'All Providers',
		'ai'
	)
	&& '查看' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'View',
		'View',
		'ai'
	),
	'AI plugin localization translates abilities explorer labels.'
);

maca_assert(
	'描述' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Description',
		'Description',
		'ai'
	)
	&& '详情' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Details',
		'Details',
		'ai'
	)
	&& '原始数据' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Raw Data',
		'Raw Data',
		'ai'
	)
	&& '输入 Schema' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Input Schema',
		'Input Schema',
		'ai'
	),
	'AI plugin localization translates abilities explorer detail labels.'
);

maca_assert(
	'测试能力：' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Test Ability:',
		'Test Ability:',
		'ai'
	)
	&& '输入数据' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Input Data',
		'Input Data',
		'ai'
	)
	&& '调用能力' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Invoke Ability',
		'Invoke Ability',
		'ai'
	)
	&& '验证输入' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Validate Input',
		'Validate Input',
		'ai'
	)
	&& '清除结果' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Clear Result',
		'Clear Result',
		'ai'
	),
	'AI plugin localization translates ability test runner labels.'
);

maca_assert(
	'Generate Image' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Generate Image',
		'Generate Image',
		'default'
	),
	'AI plugin localization does not translate other text domains.'
);

$GLOBALS['maca_locale'] = 'en_US';
maca_assert(
	'Generate Image' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Generate Image',
		'Generate Image',
		'ai'
	),
	'AI plugin localization is inactive outside Chinese locales.'
);

$GLOBALS['maca_locale'] = 'zh_CN';
$GLOBALS['maca_is_admin'] = false;
maca_assert(
	'Generate Image' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Generate Image',
		'Generate Image',
		'ai'
	),
	'AI plugin localization is inactive outside wp-admin.'
);

$GLOBALS['maca_is_admin'] = true;
Npcink_Cloud_AI_Plugin_Localization::enqueue_script_locale_data();
$enqueued_script = $GLOBALS['maca_enqueued_scripts'][0] ?? array();
$localized_script = $GLOBALS['maca_localized_scripts'][0] ?? array();
$locale_data = isset( $localized_script['data']['localeData'] ) && is_array( $localized_script['data']['localeData'] )
	? $localized_script['data']['localeData']
	: array();
maca_assert(
	'npcink-cloud-addon-ai-plugin-localization' === ( $enqueued_script['handle'] ?? '' )
	&& in_array( 'wp-i18n', $enqueued_script['deps'] ?? array(), true )
	&& 'NpcinkCloudAiPluginLocalization' === ( $localized_script['object_name'] ?? '' )
	&& '生成图片' === ( $locale_data['Generate Image'][0] ?? '' )
	&& '能力浏览器' === ( $locale_data['Abilities Explorer'][0] ?? '' )
	&& '生成摘要' === ( $locale_data['Generate Summary'][0] ?? '' )
	&& 'SEO 描述' === ( $locale_data['Meta Description'][0] ?? '' )
	&& '最近 24 小时' === ( $locale_data['Last 24 Hours'][0] ?? '' )
	&& '所有提供方' === ( $locale_data['All Providers'][0] ?? '' )
	&& '调用能力' === ( $locale_data['Invoke Ability'][0] ?? '' )
	&& '无效的 JSON 输入' === ( $locale_data['Invalid JSON input'][0] ?? '' ),
	'AI plugin localization enqueues an asset-backed wp.i18n locale data shim for JS admin screens.'
);
