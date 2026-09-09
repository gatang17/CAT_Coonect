<?php
/**
 * Form behaviour the app pages need around Forminator.
 *
 * Forminator renders the forms and, in its free version, already does
 * conditional fields — so "show Guest name when Has a guest? is ticked"
 * needs no code. What it cannot do is look like the prototype's sliding
 * pill; that is CSS, in catp-app.css.
 *
 * This file loads one small script for the cases Forminator's own logic
 * does not cover: a toggle outside a form, or one whose target is a whole
 * block rather than another field in the same form.
 *
 * It loads on the app pages rather than only where a toggle exists, because
 * the toggle lives inside markup the plugin does not render, so there is
 * nothing to detect. The script is ~50 lines and does nothing when it finds
 * no toggle.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_enqueue_scripts', 'catp_connect_enqueue_forms_script' );
function catp_connect_enqueue_forms_script() {
	if ( ! catp_connect_is_app_page() ) {
		return;
	}
	wp_enqueue_script(
		'catp-forms',
		plugins_url( 'assets/catp-forms.js', CATP_CONNECT_FILE ),
		array(),
		CATP_CONNECT_VERSION,
		true
	);
}
