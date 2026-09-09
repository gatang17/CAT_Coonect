<?php
/**
 * The Drop Zone gallery.
 *
 * Rendered by the plugin rather than left to a Query Loop the editor wires by
 * hand, for one reason above the others: `board_post_email` must never reach
 * the front end. A hand-built loop makes that a rule someone has to remember;
 * this makes it something they cannot do by accident. The email is simply
 * never read here.
 *
 * The display name is optional by design — blank means "Anonymous".
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'catp_connect_register_board_script' );
function catp_connect_register_board_script() {
	wp_register_script(
		'catp-board',
		plugins_url( 'assets/catp-board.js', CATP_CONNECT_FILE ),
		array(),
		CATP_CONNECT_VERSION,
		true
	);
}

/**
 * Published board posts, newest first.
 *
 * Only published ones: a draft is a submission awaiting review, and
 * publishing it IS the approval.
 */
function catp_connect_board_posts( $limit ) {
	if ( ! function_exists( 'get_field' ) ) {
		return array();
	}
	$posts = get_posts(
		array(
			'post_type'   => 'board_post',
			'post_status' => 'publish',
			'numberposts' => $limit,
			'orderby'     => 'date',
			'order'       => 'DESC',
		)
	);

	$items = array();
	foreach ( $posts as $post ) {
		$image = get_field( 'board_post_image', $post->ID );
		$thumb = '';
		$full  = '';
		if ( is_array( $image ) ) {
			$full  = isset( $image['url'] ) ? $image['url'] : '';
			$thumb = isset( $image['sizes']['medium_large'] ) ? $image['sizes']['medium_large'] : $full;
		} elseif ( is_numeric( $image ) ) {
			$full  = (string) wp_get_attachment_image_url( (int) $image, 'full' );
			$thumb = (string) wp_get_attachment_image_url( (int) $image, 'medium_large' );
		} elseif ( is_string( $image ) && '' !== $image ) {
			$full  = $image;
			$thumb = $image;
		}
		if ( '' === $full ) {
			// Fall back to the featured image, then give up on this post.
			$full  = (string) get_the_post_thumbnail_url( $post->ID, 'full' );
			$thumb = (string) get_the_post_thumbnail_url( $post->ID, 'medium_large' );
			if ( '' === $full ) {
				continue;
			}
		}

		$name = trim( (string) get_field( 'board_post_display_name', $post->ID ) );
		$date = (string) get_field( 'board_post_date', $post->ID );

		$items[] = array(
			'title'  => get_the_title( $post ),
			'thumb'  => $thumb ? $thumb : $full,
			'full'   => $full,
			'author' => '' !== $name ? $name : 'Anonymous',
			'date'   => $date ? $date : get_the_date( 'F j, Y', $post ),
			// board_post_email is deliberately absent. Do not add it.
		);
	}
	return $items;
}

/**
 * [catp_board]
 *
 * Attributes:
 *   limit     How many to show. Default 12.
 *   footnote  The line under the grid. Pass footnote="" to drop it.
 */
add_shortcode( 'catp_board', 'catp_connect_board_shortcode' );
function catp_connect_board_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'limit'    => 12,
			'footnote' => 'New submissions start as pending and only appear here after review.',
		),
		$atts,
		'catp_board'
	);

	$items = catp_connect_board_posts( max( 1, (int) $atts['limit'] ) );
	if ( empty( $items ) ) {
		return current_user_can( 'edit_posts' )
			? '<p class="catp-note">Drop Zone: nothing published yet. Submissions arrive as <strong>Draft</strong> Board Posts — publishing one is what approves it.</p>'
			: '<p class="catp-note">Nothing on the board yet.</p>';
	}

	wp_enqueue_script( 'catp-board' );

	ob_start();
	?>
	<div class="catp-gallery" data-catp-gallery>
		<?php foreach ( $items as $item ) : ?>
			<button type="button" class="catp-tile"
				data-catp-full="<?php echo esc_url( $item['full'] ); ?>"
				data-catp-title="<?php echo esc_attr( $item['title'] ); ?>"
				data-catp-author="<?php echo esc_attr( $item['author'] ); ?>"
				data-catp-date="<?php echo esc_attr( $item['date'] ); ?>">
				<img src="<?php echo esc_url( $item['thumb'] ); ?>" alt="<?php echo esc_attr( $item['title'] ); ?>" loading="lazy">
				<span><?php echo esc_html( $item['title'] ); ?></span>
			</button>
		<?php endforeach; ?>
	</div>

	<?php if ( '' !== $atts['footnote'] ) : ?>
		<p class="catp-board-footnote"><?php echo esc_html( $atts['footnote'] ); ?></p>
	<?php endif; ?>

	<dialog class="catp-lightbox" data-catp-lightbox aria-label="Board post">
		<button type="button" class="catp-lightbox-close" data-catp-close aria-label="Close">&times;</button>
		<img alt="" data-catp-lightbox-image>
		<p class="catp-eyebrow" data-catp-lightbox-date></p>
		<h2 data-catp-lightbox-title></h2>
		<p data-catp-lightbox-author></p>
	</dialog>
	<?php
	return ob_get_clean();
}
