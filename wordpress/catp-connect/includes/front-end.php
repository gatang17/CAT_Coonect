<?php
/**
 * Front-end glue. Deliberately small: it loads the base stylesheet, marks
 * the app pages with a body class, and registers the reusable Block Styles
 * so editors can apply them from the sidebar instead of typing classes.
 * It renders NO markup of its own — everything visible lives in the pages.
 */

defined( 'ABSPATH' ) || exit;

/** The four app pages (by slug) get the `catp-app` body class. */
function catp_connect_is_app_page() {
	if ( is_front_page() ) {
		return true;
	}
	$slugs = array( 'home', 'resources', 'get-involved', 'more', 'help' );
	return is_page( $slugs ) || is_singular( array( 'event', 'board_post', 'subject', 'teacher', 'peer_tutor', 'product' ) );
}

add_filter( 'body_class', 'catp_connect_body_class' );
function catp_connect_body_class( $classes ) {
	if ( catp_connect_is_app_page() ) {
		$classes[] = 'catp-app';
	}
	return $classes;
}

/** Base stylesheet — on the front end and inside the block editor. */
add_action( 'enqueue_block_assets', 'catp_connect_enqueue_styles' );
function catp_connect_enqueue_styles() {
	wp_enqueue_style(
		'catp-app',
		plugins_url( 'assets/catp-app.css', CATP_CONNECT_FILE ),
		array(),
		CATP_CONNECT_VERSION
	);
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
