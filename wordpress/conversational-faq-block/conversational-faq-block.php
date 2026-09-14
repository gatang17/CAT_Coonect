<?php
/**
 * Plugin Name: Conversational FAQ Block
 * Description: A reusable Gutenberg FAQ block styled like a conversation. Visitors ask new questions from the same box; a moderator answers them and the conversation grows.
 * Version: 2.1.0
 * Author: Gretel Alvarez Tang
 * License: GPL-2.0-or-later
 * Text Domain: conversational-faq-block
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CFAQ_VERSION   = '2.1.0';
const CFAQ_POST_TYPE = 'cfaq_question';

/**
 * Where questions from visitors live.
 *
 * The block's own questions are attributes, saved inside the page's content —
 * a visitor cannot write there. So a submitted question becomes a post instead,
 * and the block renders both: the curated list first, then whatever has been
 * answered since.
 *
 * Post title = the question. Post content = the answer. WordPress's own
 * pending -> publish flow IS the moderation: a pending question is invisible on
 * the front end, and publishing it is what answers it.
 */
add_action( 'init', 'cfaq_register_post_type' );
function cfaq_register_post_type() {
	register_post_type(
		CFAQ_POST_TYPE,
		array(
			'labels'              => array(
				'name'               => __( 'FAQ Questions', 'conversational-faq-block' ),
				'singular_name'      => __( 'FAQ Question', 'conversational-faq-block' ),
				'menu_name'          => __( 'FAQ Questions', 'conversational-faq-block' ),
				'all_items'          => __( 'All Questions', 'conversational-faq-block' ),
				'add_new'            => __( 'Add New', 'conversational-faq-block' ),
				'add_new_item'       => __( 'Add New Question', 'conversational-faq-block' ),
				'edit_item'          => __( 'Answer Question', 'conversational-faq-block' ),
				'search_items'       => __( 'Search Questions', 'conversational-faq-block' ),
				'not_found'          => __( 'No questions yet.', 'conversational-faq-block' ),
				'not_found_in_trash' => __( 'No questions in Trash.', 'conversational-faq-block' ),
			),
			'description'         => __( 'Questions submitted by visitors. Publishing one is what answers it.', 'conversational-faq-block' ),
			// Never a page of its own: these only ever appear inside the block.
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_rest'        => false,
			'has_archive'         => false,
			'rewrite'             => false,
			'menu_icon'           => 'dashicons-format-chat',
			'supports'            => array( 'title', 'editor' ),
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
		)
	);
}

/** A count bubble on the menu, so a waiting question is not missed. */
add_action( 'admin_menu', 'cfaq_pending_bubble', 999 );
function cfaq_pending_bubble() {
	global $menu;
	$pending = wp_count_posts( CFAQ_POST_TYPE );
	$count   = isset( $pending->pending ) ? (int) $pending->pending : 0;
	if ( ! $count || ! is_array( $menu ) ) {
		return;
	}
	$slug = 'edit.php?post_type=' . CFAQ_POST_TYPE;
	foreach ( $menu as $i => $item ) {
		if ( isset( $item[2] ) && $item[2] === $slug ) {
			$menu[ $i ][0] .= sprintf(
				' <span class="awaiting-mod"><span class="pending-count">%d</span></span>',
				$count
			);
			return;
		}
	}
}

/** Tell the moderator what "publish" means here. */
add_filter( 'manage_' . CFAQ_POST_TYPE . '_posts_columns', 'cfaq_admin_columns' );
function cfaq_admin_columns( $columns ) {
	$out = array();
	foreach ( $columns as $key => $label ) {
		$out[ $key ] = ( 'title' === $key ) ? __( 'Question', 'conversational-faq-block' ) : $label;
		if ( 'title' === $key ) {
			$out['cfaq_state'] = __( 'Answer', 'conversational-faq-block' );
		}
	}
	return $out;
}

add_action( 'manage_' . CFAQ_POST_TYPE . '_posts_custom_column', 'cfaq_admin_column_value', 10, 2 );
function cfaq_admin_column_value( $column, $post_id ) {
	if ( 'cfaq_state' !== $column ) {
		return;
	}
	$post = get_post( $post_id );
	if ( 'publish' === $post->post_status ) {
		echo '<span style="color:#1a7f37">' . esc_html__( 'Answered — live in the FAQ', 'conversational-faq-block' ) . '</span>';
		return;
	}
	if ( '' === trim( (string) $post->post_content ) ) {
		echo '<strong>' . esc_html__( 'Waiting for an answer', 'conversational-faq-block' ) . '</strong>';
		return;
	}
	echo esc_html__( 'Answer written — publish to show it', 'conversational-faq-block' );
}

/**
 * Answered questions, oldest first, so the conversation grows downward.
 *
 * @return array List of question/answer pairs.
 */
function cfaq_answered_questions() {
	$posts = get_posts(
		array(
			'post_type'        => CFAQ_POST_TYPE,
			'post_status'      => 'publish',
			'numberposts'      => 100,
			'orderby'          => 'date',
			'order'            => 'ASC',
			'suppress_filters' => false,
		)
	);

	$out = array();
	foreach ( $posts as $post ) {
		if ( '' === trim( $post->post_title ) ) {
			continue;
		}
		$out[] = array(
			'question' => $post->post_title,
			'answer'   => $post->post_content,
		);
	}
	return $out;
}

/**
 * The submission endpoint.
 *
 * Public by necessity — visitors are not logged in. It is safe because of what
 * it refuses, not because of who calls it: a question always lands as `pending`
 * and can never publish itself, so the worst a flood achieves is a full Trash.
 *
 * No nonce: a page served from a full-page cache carries a stale one, and a
 * submission silently failing for half the visitors is worse than the risk here
 * (this is the same reasoning WordPress applies to its own comment form).
 *
 * The submitter's IP is used to rate limit and is never stored — it lives only
 * in a transient key, hashed, for fifteen minutes.
 */
add_action( 'rest_api_init', 'cfaq_register_routes' );
function cfaq_register_routes() {
	register_rest_route(
		'cfaq/v1',
		'/questions',
		array(
			'methods'             => 'POST',
			'callback'            => 'cfaq_receive_question',
			'permission_callback' => '__return_true',
			'args'                => array(
				'question' => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		)
	);
}

function cfaq_rate_limit_key() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
	return 'cfaq_rl_' . md5( $ip . wp_salt() );
}

function cfaq_receive_question( WP_REST_Request $request ) {
	// Honeypot: a real visitor never sees this field.
	if ( '' !== trim( (string) $request->get_param( 'cfaq_hp' ) ) ) {
		return new WP_REST_Response( array( 'status' => 'received' ), 201 );
	}

	$question = trim( (string) $request->get_param( 'question' ) );
	$length   = function_exists( 'mb_strlen' ) ? mb_strlen( $question ) : strlen( $question );

	if ( $length < 10 ) {
		return new WP_Error( 'cfaq_too_short', __( 'Please write a bit more so we can answer it properly.', 'conversational-faq-block' ), array( 'status' => 400 ) );
	}
	if ( $length > 300 ) {
		return new WP_Error( 'cfaq_too_long', __( 'That is a little long — try to keep it to one question.', 'conversational-faq-block' ), array( 'status' => 400 ) );
	}
	// Link spam has no place in a question.
	if ( preg_match( '#(https?://|www\.)#i', $question ) ) {
		return new WP_Error( 'cfaq_no_links', __( 'Please ask the question without a link in it.', 'conversational-faq-block' ), array( 'status' => 400 ) );
	}

	$key   = cfaq_rate_limit_key();
	$count = (int) get_transient( $key );
	if ( $count >= 3 ) {
		return new WP_Error( 'cfaq_slow_down', __( 'That is a few questions in a row — give us a little while to catch up.', 'conversational-faq-block' ), array( 'status' => 429 ) );
	}

	// Already asked? Point at it instead of collecting another copy.
	$existing = get_posts(
		array(
			'post_type'        => CFAQ_POST_TYPE,
			'post_status'      => array( 'publish', 'pending', 'draft' ),
			'numberposts'      => 1,
			'title'            => $question,
			'suppress_filters' => false,
		)
	);
	if ( $existing ) {
		return new WP_REST_Response(
			array(
				'status'   => 'duplicate',
				'answered' => 'publish' === $existing[0]->post_status,
				'question' => $existing[0]->post_title,
			),
			200
		);
	}

	$post_id = wp_insert_post(
		array(
			'post_type'    => CFAQ_POST_TYPE,
			'post_title'   => $question,
			'post_content' => '',
			'post_status'  => 'pending',
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		return new WP_Error( 'cfaq_failed', __( 'That did not go through. Please try again in a moment.', 'conversational-faq-block' ), array( 'status' => 500 ) );
	}

	set_transient( $key, $count + 1, 15 * MINUTE_IN_SECONDS );

	/**
	 * Fires once a visitor's question has been stored and is awaiting an answer.
	 *
	 * @param int    $post_id  The pending question.
	 * @param string $question The question text.
	 */
	do_action( 'cfaq_question_received', $post_id, $question );

	return new WP_REST_Response( array( 'status' => 'received' ), 201 );
}

function cfaq_register_block() {
	$plugin_url = plugin_dir_url( __FILE__ );

	wp_register_script(
		'cfaq-editor',
		$plugin_url . 'assets/editor.js',
		array( 'wp-blocks', 'wp-components', 'wp-element', 'wp-i18n', 'wp-block-editor' ),
		CFAQ_VERSION,
		true
	);

	wp_register_script( 'cfaq-view', $plugin_url . 'assets/view.js', array(), CFAQ_VERSION, true );
	wp_localize_script(
		'cfaq-view',
		'cfaqConfig',
		array(
			'endpoint'  => esc_url_raw( rest_url( 'cfaq/v1/questions' ) ),
			'minLength' => 10,
			'maxLength' => 300,
			'strings'   => array(
				'tooShort'  => __( 'Please write a bit more so we can answer it properly.', 'conversational-faq-block' ),
				'tooLong'   => __( 'That is a little long — try to keep it to one question.', 'conversational-faq-block' ),
				'sending'   => __( 'Sending…', 'conversational-faq-block' ),
				'failed'    => __( 'That did not go through. Please try again in a moment.', 'conversational-faq-block' ),
				'duplicate' => __( 'Someone already asked that one — here it is.', 'conversational-faq-block' ),
			),
		)
	);

	wp_register_style( 'cfaq-style', $plugin_url . 'assets/style.css', array(), CFAQ_VERSION );
	wp_register_style( 'cfaq-editor-style', $plugin_url . 'assets/editor.css', array( 'wp-edit-blocks' ), CFAQ_VERSION );

	register_block_type(
		__DIR__,
		array(
			'editor_script'   => 'cfaq-editor',
			'editor_style'    => 'cfaq-editor-style',
			'script'          => 'cfaq-view',
			'style'           => 'cfaq-style',
			'render_callback' => 'cfaq_render_block',
		)
	);
}
add_action( 'init', 'cfaq_register_block' );

function cfaq_render_block( $attributes ) {
	$defaults = array(
		'title'         => 'Help',
		'subtitle'      => 'Quick answers for your next step.',
		'greeting'      => 'Hi — choose a question below, or ask a new one.',
		'label'         => 'Suggested questions',
		'placeholder'   => 'Ask your own question',
		'askLabel'      => 'Ask',
		'pendingAnswer' => 'Thanks for asking. Nobody has answered this one yet — check back soon and it will be here.',
		'askSuccess'    => 'Your question is on its way. Once someone answers it, it joins the conversation above.',
		'allowAsking'   => true,
		'accentColor'   => '#8cc63f',
		'darkColor'     => '#111111',
		'items'         => array(),
	);
	$a     = wp_parse_args( $attributes, $defaults );
	$items = is_array( $a['items'] ) ? $a['items'] : array();

	// Curated questions first, then everything answered since.
	$items = array_merge( $items, cfaq_answered_questions() );

	$uid = wp_unique_id( 'cfaq-' );

	// The only two the editor's own panels cannot reach, because they paint
	// inner parts rather than the block: the question bubble and the hover.
	// Everything else — background, text, border, font, spacing — comes from
	// the block's native controls, through the wrapper attributes below.
	$style = sprintf(
		'--cfaq-accent:%s;--cfaq-dark:%s;',
		esc_attr( $a['accentColor'] ),
		esc_attr( $a['darkColor'] )
	);

	// The border panel's three values, handed down as variables.
	//
	// They cannot simply be inherited: <details> slots its content into a
	// shadow tree, so `inherit` inside a card resolves against that slot and
	// not against the card. A custom property crosses it; `inherit` does not.
	$border = isset( $attributes['style']['border'] ) && is_array( $attributes['style']['border'] )
		? $attributes['style']['border']
		: array();

	$width = isset( $border['width'] ) ? (string) $border['width'] : '';
	if ( preg_match( '/^\d+(\.\d+)?(px|em|rem)$/', $width ) ) {
		$style .= '--cfaq-bw:' . esc_attr( $width ) . ';';
	}

	$radius = isset( $border['radius'] ) && is_string( $border['radius'] ) ? $border['radius'] : '';
	if ( preg_match( '/^\d+(\.\d+)?(px|em|rem|%)$/', $radius ) ) {
		$style .= '--cfaq-br:' . esc_attr( $radius ) . ';';
	}

	// A colour picked from the theme palette arrives as a slug, not a value.
	if ( isset( $border['color'] ) && preg_match( '/^#[0-9a-f]{3,8}$/i', (string) $border['color'] ) ) {
		$style .= '--cfaq-bc:' . esc_attr( $border['color'] ) . ';';
	} elseif ( ! empty( $attributes['borderColor'] ) && preg_match( '/^[a-z0-9-]+$/', (string) $attributes['borderColor'] ) ) {
		$style .= '--cfaq-bc:var(--wp--preset--color--' . esc_attr( $attributes['borderColor'] ) . ');';
	}

	// Carries className (Advanced -> Additional CSS class(es)), the anchor,
	// align and the spacing controls. Without this, a class typed in the editor
	// never reaches the front end.
	$wrapper = get_block_wrapper_attributes(
		array(
			'class'                    => 'cfaq',
			'style'                    => $style,
			'data-cfaq-pending-answer' => $a['pendingAnswer'],
			'data-cfaq-success'        => $a['askSuccess'],
		)
	);

	ob_start();
	?>
	<section <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by get_block_wrapper_attributes(). ?>>
		<header class="cfaq__header">
			<h2 class="cfaq__title"><?php echo esc_html( $a['title'] ); ?></h2>
			<?php if ( $a['subtitle'] ) : ?><p class="cfaq__subtitle"><?php echo esc_html( $a['subtitle'] ); ?></p><?php endif; ?>
		</header>
		<?php if ( $a['greeting'] ) : ?><p class="cfaq__greeting"><?php echo esc_html( $a['greeting'] ); ?></p><?php endif; ?>
		<?php if ( $a['label'] ) : ?><p class="cfaq__label"><?php echo esc_html( $a['label'] ); ?></p><?php endif; ?>
		<div class="cfaq__items">
			<?php
			foreach ( $items as $item ) :
				$question = isset( $item['question'] ) ? $item['question'] : '';
				$answer   = isset( $item['answer'] ) ? $item['answer'] : '';
				if ( ! $question ) {
					continue;
				}
				?>
				<details class="cfaq__item">
					<summary><?php echo esc_html( $question ); ?></summary>
					<div class="cfaq__answer"><?php echo wp_kses_post( wpautop( $answer ) ); ?></div>
				</details>
			<?php endforeach; ?>
		</div>

		<p class="cfaq__empty" hidden><?php esc_html_e( 'Nothing matches that yet — ask it below and someone will answer.', 'conversational-faq-block' ); ?></p>

		<?php if ( $a['allowAsking'] ) : ?>
			<form class="cfaq__ask" novalidate>
				<label class="screen-reader-text" for="<?php echo esc_attr( $uid ); ?>-query"><?php esc_html_e( 'Ask a new question', 'conversational-faq-block' ); ?></label>
				<input id="<?php echo esc_attr( $uid ); ?>-query" type="text" name="question"
					placeholder="<?php echo esc_attr( $a['placeholder'] ); ?>"
					maxlength="300" autocomplete="off">
				<?php // Bots fill every field they find; people never see this one. ?>
				<div class="cfaq__hp" aria-hidden="true">
					<label><?php esc_html_e( 'Leave this field empty', 'conversational-faq-block' ); ?>
						<input type="text" name="cfaq_hp" tabindex="-1" autocomplete="off"></label>
				</div>
				<button type="submit"><?php echo esc_html( $a['askLabel'] ); ?></button>
			</form>
			<p class="cfaq__status" role="status" aria-live="polite" hidden></p>
		<?php endif; ?>
	</section>
	<?php
	return ob_get_clean();
}
