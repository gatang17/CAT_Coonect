<?php
/**
 * Plugin Name:       CATP Connect
 * Plugin URI:        https://github.com/gatang17/CAT_Coonect
 * Description:       Front end for the CATP Connect companion app. Owns no post types and no fields — those are imported into Custom Post Type UI and ACF, and stay editable in the admin. Provides the [catp_tutoring_button], [catp_goods_calculator], [catp_directory] and [catp_board] shortcodes, and one-click Setup Tools (starter catalog, page skeleton) under App Settings. Ships no CSS — the styling lives in catp-app.css, pasted into the Customizer.
 * Version:           0.14.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Requires Plugins:  advanced-custom-fields
 * Author:            CATP Connect
 * License:           GPL-2.0-or-later
 * Text Domain:       catp-connect
 */

defined( 'ABSPATH' ) || exit;

define( 'CATP_CONNECT_VERSION', '0.14.0' );
define( 'CATP_CONNECT_DIR', plugin_dir_path( __FILE__ ) );
define( 'CATP_CONNECT_FILE', __FILE__ );

require_once CATP_CONNECT_DIR . 'includes/setup-tools.php';
require_once CATP_CONNECT_DIR . 'includes/front-end.php';
require_once CATP_CONNECT_DIR . 'includes/goods-calculator.php';
require_once CATP_CONNECT_DIR . 'includes/directory.php';
require_once CATP_CONNECT_DIR . 'includes/forms.php';
require_once CATP_CONNECT_DIR . 'includes/board.php';

/**
 * Read one field from the singleton app_setting post.
 *
 * @param string $field ACF field name, e.g. 'tutoring_external_url'.
 * @return mixed|null
 */
function catp_connect_get_setting( $field ) {
	if ( ! function_exists( 'get_field' ) ) {
		return null;
	}
	$ids = get_posts(
		array(
			'post_type'   => 'app_setting',
			'post_status' => 'publish',
			'numberposts' => 1,
			'orderby'     => 'ID',
			'order'       => 'ASC',
			'fields'      => 'ids',
		)
	);
	if ( empty( $ids ) ) {
		return null;
	}
	return get_field( $field, $ids[0] );
}

/**
 * [catp_tutoring_button text="Request Tutoring"]
 *
 * The whole Tutoring tab: one button that opens the external URL stored in
 * App Settings. Renders nothing until that URL has been filled in.
 */
add_shortcode( 'catp_tutoring_button', 'catp_connect_tutoring_button_shortcode' );
function catp_connect_tutoring_button_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'text'  => 'Request Tutoring',
			'class' => 'catp-tutoring-button wp-element-button',
		),
		$atts,
		'catp_tutoring_button'
	);
	$url = catp_connect_get_setting( 'tutoring_external_url' );
	if ( empty( $url ) ) {
		return '';
	}
	return sprintf(
		'<a class="%s" href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
		esc_attr( $atts['class'] ),
		esc_url( $url ),
		esc_html( $atts['text'] )
	);
}

/**
 * Warn (softly) when the data model this plugin reads is not there.
 *
 * The plugin owns no post types and no fields — both are imported and then
 * live in the database, editable in the admin. That is the point, but it also
 * means a site can have the plugin and nothing to read, and the shortcodes
 * would just render empty. Say so instead.
 */
add_action( 'admin_notices', 'catp_connect_prerequisites_notice' );
function catp_connect_prerequisites_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	$missing = array();
	if ( ! post_type_exists( 'product' ) || ! post_type_exists( 'teacher' ) ) {
		$missing[] = 'the nine post types (Custom Post Type UI &rarr; Tools &rarr; Import, using <code>catp-post-types-cptui.json</code>)';
	}
	if ( ! class_exists( 'ACF' ) ) {
		$missing[] = 'Advanced Custom Fields (free), then Custom Fields &rarr; Tools &rarr; Import with <code>acf-field-groups.json</code>';
	}
	if ( ! $missing ) {
		return;
	}

	echo '<div class="notice notice-warning"><p><strong>CATP Connect</strong> reads a data model it does not create. Still missing: '
		. wp_kses_post( implode( '; and ', $missing ) )
		. '.</p></div>';
}

register_activation_hook( __FILE__, 'catp_connect_activate' );
function catp_connect_activate() {
	flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
