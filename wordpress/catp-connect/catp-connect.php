<?php
/**
 * Plugin Name:       CATP Connect
 * Plugin URI:        https://github.com/gatang17/CAT_Coonect
 * Description:       Data layer for the CATP Connect companion app: registers its custom post types, loads its ACF field groups from acf-field-groups.json, provides the [catp_tutoring_button], [catp_goods_calculator] and [catp_directory] shortcodes, and one-click Setup Tools (starter catalog, page skeleton) under App Settings. Ships no CSS — the styling lives in catp-app.css, pasted into the Customizer.
 * Version:           0.7.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Requires Plugins:  advanced-custom-fields
 * Author:            CATP Connect
 * License:           GPL-2.0-or-later
 * Text Domain:       catp-connect
 */

defined( 'ABSPATH' ) || exit;

define( 'CATP_CONNECT_VERSION', '0.7.0' );
define( 'CATP_CONNECT_DIR', plugin_dir_path( __FILE__ ) );
define( 'CATP_CONNECT_FILE', __FILE__ );

require_once CATP_CONNECT_DIR . 'includes/setup-tools.php';
require_once CATP_CONNECT_DIR . 'includes/front-end.php';
require_once CATP_CONNECT_DIR . 'includes/goods-calculator.php';
require_once CATP_CONNECT_DIR . 'includes/directory.php';

/**
 * The app's custom post types.
 *
 * Slugs must stay in sync with the `post_type` location rules in
 * acf-field-groups.json and with the table names in database/schema.sql.
 *
 * `public` controls whether a type is reachable on the front end and in
 * the REST API. Types whose post titles are student emails (photographer)
 * or that hold configuration (app_setting) are deliberately NOT public.
 */
function catp_connect_post_types() {
	return array(
		'location'     => array(
			'singular'    => 'Location',
			'plural'      => 'Locations',
			'description' => 'Rooms: classrooms, studios, and teacher offices (Type = "Office").',
			'supports'    => array( 'title' ),
			'public'      => true,
			'menu_icon'   => 'dashicons-location',
		),
		'teacher'      => array(
			'singular'    => 'Teacher',
			'plural'      => 'Teachers',
			'description' => 'Faculty and administration. Post Title = the teacher\'s name.',
			'supports'    => array( 'title' ),
			'public'      => true,
			'menu_icon'   => 'dashicons-groups',
		),
		'subject'      => array(
			'singular'    => 'Class / Subject',
			'plural'      => 'Classes / Subjects',
			'description' => 'Advertising Design, Web Design, Photography, Digital Video. Carries the classroom and the list of teachers.',
			'supports'    => array( 'title' ),
			'public'      => true,
			'menu_icon'   => 'dashicons-welcome-learn-more',
		),
		'product'      => array(
			'singular'    => 'Product',
			'plural'      => 'Products',
			'description' => 'Price list for the Goods calculator. Nothing is ever ordered or submitted.',
			'supports'    => array( 'title' ),
			'public'      => true,
			'menu_icon'   => 'dashicons-cart',
		),
		'event'        => array(
			'singular'    => 'Event',
			'plural'      => 'Events',
			'description' => 'Events/news shown on Home. Post Content = the description.',
			'supports'    => array( 'title', 'editor' ),
			'public'      => true,
			'menu_icon'   => 'dashicons-calendar-alt',
		),
		'photographer' => array(
			'singular'    => 'Photographer',
			'plural'      => 'Photographers',
			'description' => 'Students who hold the photographer role. Post titles are student emails: never public, never in the REST API.',
			'supports'    => array( 'title' ),
			'public'      => false,
			'menu_icon'   => 'dashicons-camera',
		),
		'peer_tutor'   => array(
			'singular'    => 'Peer Tutor',
			'plural'      => 'Peer Tutors',
			'description' => 'Approved peer tutors only (the public directory). A post existing here IS the approval.',
			'supports'    => array( 'title' ),
			'public'      => true,
			'menu_icon'   => 'dashicons-businessperson',
		),
		'board_post'   => array(
			'singular'    => 'Board Post',
			'plural'      => 'Board Posts',
			'description' => 'Student gallery submissions. Created by the Board form (Forminator Post Creation) as Drafts; publishing = approval.',
			'supports'    => array( 'title', 'editor', 'thumbnail' ),
			'public'      => true,
			'menu_icon'   => 'dashicons-format-gallery',
		),
		'app_setting'  => array(
			'singular'    => 'App Setting',
			'plural'      => 'App Settings',
			'description' => 'Singleton: create exactly ONE post here. Holds the Tutoring external URL.',
			'supports'    => array( 'title' ),
			'public'      => false,
			'menu_icon'   => 'dashicons-admin-generic',
		),
	);
}

add_action( 'init', 'catp_connect_register_post_types' );
function catp_connect_register_post_types() {
	foreach ( catp_connect_post_types() as $slug => $cfg ) {
		$public = (bool) $cfg['public'];
		$labels = array(
			'name'               => $cfg['plural'],
			'singular_name'      => $cfg['singular'],
			'menu_name'          => $cfg['plural'],
			'all_items'          => 'All ' . $cfg['plural'],
			'add_new'            => 'Add New',
			'add_new_item'       => 'Add New ' . $cfg['singular'],
			'edit_item'          => 'Edit ' . $cfg['singular'],
			'new_item'           => 'New ' . $cfg['singular'],
			'view_item'          => 'View ' . $cfg['singular'],
			'search_items'       => 'Search ' . $cfg['plural'],
			'not_found'          => 'No ' . strtolower( $cfg['plural'] ) . ' found.',
			'not_found_in_trash' => 'No ' . strtolower( $cfg['plural'] ) . ' found in Trash.',
		);

		register_post_type(
			$slug,
			array(
				'labels'              => $labels,
				'description'         => $cfg['description'],
				'public'              => $public,
				'publicly_queryable'  => $public,
				'exclude_from_search' => ! $public,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_nav_menus'   => $public,
				// REST/block-editor exposure follows `public`: a public type
				// can be listed with the core Query Loop block; a private one
				// stays out of /wp-json entirely (and gets the classic editor).
				'show_in_rest'        => $public,
				'has_archive'         => false,
				'rewrite'             => $public ? array( 'slug' => $slug, 'with_front' => false ) : false,
				'supports'            => $cfg['supports'],
				'menu_icon'           => $cfg['menu_icon'],
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
			)
		);
	}
}

/**
 * Load the ACF field groups from acf-field-groups.json.
 *
 * That file is the single source of truth: it is the same file you could
 * import by hand via Custom Fields -> Tools -> Import. Registering it here
 * means the groups are always present (and versioned with the plugin) with
 * no manual import step. They show up in the ACF admin as read-only local
 * groups — edit the JSON, not the UI.
 */
add_action( 'acf/include_fields', 'catp_connect_register_field_groups' );
function catp_connect_register_field_groups() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}
	$file = CATP_CONNECT_DIR . 'acf-field-groups.json';
	if ( ! is_readable( $file ) ) {
		return;
	}
	$groups = json_decode( file_get_contents( $file ), true );
	if ( ! is_array( $groups ) ) {
		return;
	}
	foreach ( $groups as $group ) {
		if ( is_array( $group ) && ! empty( $group['key'] ) ) {
			acf_add_local_field_group( $group );
		}
	}
}

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
 * Warn (softly) if ACF isn't active. WordPress 6.5+ already enforces the
 * "Requires Plugins" header above; this covers older installs.
 */
add_action( 'admin_notices', 'catp_connect_acf_notice' );
function catp_connect_acf_notice() {
	if ( class_exists( 'ACF' ) || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-warning"><p><strong>CATP Connect:</strong> Advanced Custom Fields (free) must be installed and active for the catalog fields to appear.</p></div>';
}

register_activation_hook( __FILE__, 'catp_connect_activate' );
function catp_connect_activate() {
	catp_connect_register_post_types();
	flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
