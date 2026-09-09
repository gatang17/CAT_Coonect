<?php
/**
 * Tabs, built from the page's own Groups.
 *
 * Loaded on the app pages because the Groups are editor content the plugin
 * does not render — there is nothing to detect from PHP. The script does
 * nothing on a page with no `catp-tabs` wrapper.
 *
 * This makes a tabs block optional rather than required: Stackable still
 * works (the stylesheet targets the ARIA roles either way), but the setup
 * skeleton no longer needs anyone to convert its Groups by hand.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_enqueue_scripts', 'catp_connect_enqueue_tabs_script' );
function catp_connect_enqueue_tabs_script() {
	if ( ! catp_connect_is_app_page() ) {
		return;
	}
	wp_enqueue_script(
		'catp-tabs',
		plugins_url( 'assets/catp-tabs.js', CATP_CONNECT_FILE ),
		array(),
		CATP_CONNECT_VERSION,
		true
	);
}
