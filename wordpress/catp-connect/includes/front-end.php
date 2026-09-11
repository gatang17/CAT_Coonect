<?php
/**
 * Front-end glue. Deliberately small: it marks the app pages with a body
 * class and registers the reusable Block Styles so editors can apply them
 * from the sidebar instead of typing class names. It renders NO markup and
 * loads NO stylesheet — the styling lives in wordpress/catp-app.css, which
 * is pasted into Appearance → Customize → Additional CSS, so designers own
 * it without touching plugin files.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Which pages get the `catp-app` body class.
 *
 * The slug list is a convenience, not the rule. Renaming a page, changing
 * its permalink or building the app somewhere else would all take the class
 * away, and the styles that depend on it with it. So the last check is the
 * honest one: a page carrying the app's own markup IS an app page, whatever
 * it is called.
 *
 * (The stylesheet's layout rules deliberately do not depend on this class —
 * only its typography and colours do. A page that slips past this still
 * lays out correctly; it just loses the app's look.)
 */
function catp_connect_is_app_page() {
	if ( is_front_page() ) {
		return true;
	}
	if ( is_singular( array( 'event', 'board_post', 'subject', 'teacher', 'peer_tutor', 'product' ) ) ) {
		return true;
	}
	if ( is_page( array( 'home', 'resources', 'get-involved', 'more', 'help' ) ) ) {
		return true;
	}

	$post = get_queried_object();
	if ( ! $post instanceof WP_Post || '' === (string) $post->post_content ) {
		return false;
	}
	// The page column the setup tool always wraps a page in.
	if ( false !== strpos( $post->post_content, 'catp-page' ) ) {
		return true;
	}
	foreach ( array( 'catp_goods_calculator', 'catp_directory', 'catp_board', 'catp_tutoring_button' ) as $shortcode ) {
		if ( has_shortcode( $post->post_content, $shortcode ) ) {
			return true;
		}
	}
	return false;
}

add_filter( 'body_class', 'catp_connect_body_class' );
function catp_connect_body_class( $classes ) {
	if ( catp_connect_is_app_page() ) {
		$classes[] = 'catp-app';
	}
	return $classes;
}

/** Block Styles: one click in the editor sidebar instead of a class name. */
add_action( 'init', 'catp_connect_register_block_styles' );
function catp_connect_register_block_styles() {
	if ( ! function_exists( 'register_block_style' ) ) {
		return;
	}
	register_block_style( 'core/group', array( 'name' => 'catp-card', 'label' => 'CATP Card' ) );
	register_block_style( 'core/group', array( 'name' => 'catp-section', 'label' => 'CATP Section header' ) );
	register_block_style( 'core/paragraph', array( 'name' => 'catp-eyebrow', 'label' => 'CATP Eyebrow label' ) );
	register_block_style( 'core/paragraph', array( 'name' => 'catp-note', 'label' => 'CATP Note' ) );
}

/**
 * Events: keep the post's own date equal to its ACF "Event Date", so the
 * core Query Loop can order events by date and the core Post Date block
 * shows the event's date — no custom code needed on the pages.
 */
add_action( 'acf/save_post', 'catp_connect_sync_event_date', 20 );
function catp_connect_sync_event_date( $post_id ) {
	if ( 'event' !== get_post_type( $post_id ) || ! function_exists( 'get_field' ) ) {
		return;
	}
	$date = get_field( 'event_date', $post_id ); // Y-m-d
	if ( ! $date || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
		return;
	}
	remove_action( 'acf/save_post', 'catp_connect_sync_event_date', 20 );
	wp_update_post(
		array(
			'ID'            => $post_id,
			'post_date'     => $date . ' 09:00:00',
			'post_date_gmt' => get_gmt_from_date( $date . ' 09:00:00' ),
		)
	);
	add_action( 'acf/save_post', 'catp_connect_sync_event_date', 20 );
}
