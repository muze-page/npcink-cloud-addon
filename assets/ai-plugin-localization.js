( function( window ) {
	var config = window.NpcinkCloudAiPluginLocalization || {};
	var wp = window.wp || {};

	if ( ! config.localeData || ! wp.i18n || ! wp.i18n.setLocaleData ) {
		return;
	}

	wp.i18n.setLocaleData( config.localeData, 'ai' );
}( window ) );
