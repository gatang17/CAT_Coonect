<?php
/**
 * Setup Tools: an admin page (App Settings -> Setup Tools) with two
 * one-click, idempotent actions meant for whoever sets the site up:
 *
 *  1. Load starter catalog  — creates the catalog entries we already know
 *     (faculty offices, teachers, the four subjects with their teachers,
 *     the six products). Re-running it never duplicates: anything that
 *     already exists (matched by post type + title) is skipped, and
 *     existing entries are never overwritten.
 *  2. Create page skeleton  — creates the four app pages (Home, Resources,
 *     Get Involved, More) with one section per tab, built from core
 *     blocks only, ready to be converted into Stackable Tabs. Sets Home
 *     as the front page and builds an "App Navigation" menu.
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * Admin page
 * ---------------------------------------------------------------------- */

add_action( 'admin_menu', 'catp_connect_setup_menu' );
function catp_connect_setup_menu() {
	add_submenu_page(
		'edit.php?post_type=app_setting',
		'CATP Connect — Setup Tools',
		'Setup Tools',
		'manage_options',
		'catp-connect-setup',
		'catp_connect_setup_page'
	);
}

function catp_connect_setup_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}

	$result = null;
	if ( isset( $_POST['catp_action'] ) && check_admin_referer( 'catp_connect_setup', 'catp_nonce' ) ) {
		$action = sanitize_key( wp_unslash( $_POST['catp_action'] ) );
		if ( 'seed_catalog' === $action ) {
			$result = catp_connect_seed_catalog();
		} elseif ( 'create_pages' === $action ) {
			$result = catp_connect_create_page_skeleton( ! empty( $_POST['catp_overwrite'] ) );
		}
	}

	$catalog = catp_connect_starter_catalog();
	?>
	<div class="wrap">
		<h1>CATP Connect — Setup Tools</h1>
		<p>One-click setup steps for a fresh site. Both buttons are safe to press more than once: anything that already exists is skipped, never duplicated or overwritten.</p>

		<?php if ( $result ) : ?>
			<div class="notice notice-<?php echo empty( $result['errors'] ) ? 'success' : 'warning'; ?>" style="padding:12px 16px">
				<p><strong><?php echo esc_html( $result['title'] ); ?></strong></p>
				<?php foreach ( array( 'created' => 'Created', 'skipped' => 'Already existed (skipped)', 'errors' => 'Errors' ) as $k => $label ) : ?>
					<?php if ( ! empty( $result[ $k ] ) ) : ?>
						<p><strong><?php echo esc_html( $label ); ?> (<?php echo count( $result[ $k ] ); ?>)</strong></p>
						<ul style="list-style:disc;margin-left:1.5em">
							<?php foreach ( $result[ $k ] as $line ) : ?>
								<li><?php echo esc_html( $line ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				<?php endforeach; ?>
				<?php if ( ! empty( $result['next'] ) ) : ?>
					<p><strong>Still to do by hand:</strong></p>
					<ul style="list-style:disc;margin-left:1.5em">
						<?php foreach ( $result['next'] as $line ) : ?>
							<li><?php echo wp_kses_post( $line ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<hr>
		<h2>1. Load starter catalog</h2>
		<p>Creates the catalog entries that are already known, so nobody has to retype them:</p>
		<ul style="list-style:disc;margin-left:1.5em">
			<li><strong><?php echo count( $catalog['locations'] ); ?> Locations</strong> — the faculty offices (Chestnut Hall 307B–307H, Type = "Office").</li>
			<li><strong><?php echo count( $catalog['teachers'] ); ?> Teachers</strong> — with title and office where known.</li>
			<li><strong><?php echo count( $catalog['subjects'] ); ?> Classes / Subjects</strong> — each linked to its teachers. <em>Classroom is left empty</em> (not known yet) — the edit screen will ask for it.</li>
			<li><strong><?php echo count( $catalog['products'] ); ?> Products</strong> — with size, price, category. Large board's size/price are still to be confirmed.</li>
		</ul>
		<form method="post">
			<?php wp_nonce_field( 'catp_connect_setup', 'catp_nonce' ); ?>
			<input type="hidden" name="catp_action" value="seed_catalog">
			<?php submit_button( 'Load starter catalog', 'primary', 'submit', false ); ?>
		</form>

		<hr>
		<h2>2. Create page skeleton</h2>
		<p>Creates the four app pages — <strong>Home, Resources, Get Involved, More</strong> — with one section per tab (headings, placeholder notes, and the parts that already work: the Tutoring button, and post lists for the directory, events, products and the board). Built from core blocks only; each section is a Group ready to become a Stackable Tab. Also sets Home as the front page and creates an "App Navigation" menu.</p>
		<form method="post">
			<?php wp_nonce_field( 'catp_connect_setup', 'catp_nonce' ); ?>
			<input type="hidden" name="catp_action" value="create_pages">
			<p><label><input type="checkbox" name="catp_overwrite" value="1"> <strong>Overwrite</strong> the content of pages that already exist (Home, Resources, Get Involved, More). Leave unchecked to only create the missing ones. Overwriting replaces whatever is on those pages with the fresh skeleton — use it right after a plugin update, before anyone has designed the pages.</label></p>
			<?php submit_button( 'Create page skeleton', 'primary', 'submit', false ); ?>
		</form>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * Shared helpers
 * ---------------------------------------------------------------------- */

/** Find a post of a type by exact title (any status). Returns ID or 0. */
function catp_connect_find_post( $post_type, $title ) {
	$q = new WP_Query(
		array(
			'post_type'      => $post_type,
			'title'          => $title,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);
	return $q->posts ? (int) $q->posts[0] : 0;
}

/** Return [id, created(bool)] — creates the post if it doesn't exist. */
function catp_connect_ensure_post( $post_type, $title, $extra = array() ) {
	$id = catp_connect_find_post( $post_type, $title );
	if ( $id ) {
		return array( $id, false );
	}
	$id = wp_insert_post(
		array_merge(
			array(
				'post_type'   => $post_type,
				'post_title'  => $title,
				'post_status' => 'publish',
			),
			$extra
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		return array( 0, false );
	}
	return array( (int) $id, true );
}

/* -------------------------------------------------------------------------
 * 1. Starter catalog
 * ---------------------------------------------------------------------- */

function catp_connect_starter_catalog() {
	return array(
		'locations' => array(
			'Chestnut Hall 307B' => 'Office',
			'Chestnut Hall 307C' => 'Office',
			'Chestnut Hall 307D' => 'Office',
			'Chestnut Hall 307F' => 'Office',
			'Chestnut Hall 307G' => 'Office',
			'Chestnut Hall 307H' => 'Office',
		),
		// name => [ title, office ]
		'teachers'  => array(
			'Terry W. Lutz'        => array( 'Business & Technology Division Dean, Professor', 'Chestnut Hall 307D' ),
			'Mark Cable'           => array( 'Academic Program Coordinator, Instructor', 'Chestnut Hall 307F' ),
			'Tyler Ewing'          => array( '', 'Chestnut Hall 307C' ),
			'Rob Womack'           => array( '', 'Chestnut Hall 307C' ),
			'Jesenia Avila-Ugalde' => array( '', 'Chestnut Hall 307G' ),
			'Jamarr Cox'           => array( '', 'Chestnut Hall 307C' ),
			'April Fultz'          => array( '', 'Chestnut Hall 307H' ),
			'Michael Stewart'      => array( '', 'Chestnut Hall 307B' ),
			'Bryan Moberly'        => array( '', 'Chestnut Hall 307B' ),
			'Ben Stansbury'        => array( '', 'Chestnut Hall 307G' ),
			'Michael Fitzer'       => array( '', 'Chestnut Hall 307G' ),
		),
		// name => teachers
		'subjects'  => array(
			'Advertising Design' => array( 'Tyler Ewing', 'Rob Womack', 'Jesenia Avila-Ugalde' ),
			'Web Design'         => array( 'Jamarr Cox', 'April Fultz' ),
			'Photography'        => array( 'Michael Stewart', 'Bryan Moberly' ),
			'Digital Video'      => array( 'Ben Stansbury', 'Michael Fitzer' ),
		),
		// name => [ size, price, category ]
		'products'  => array(
			'Photo paper'      => array( '8x10', 0.50, 'print_material' ),
			'Mount board'      => array( '11x14', 1.50, 'print_material' ),
			'Dry mount tissue' => array( '', 0.50, 'print_material' ),
			'Envelope'         => array( '', 0.50, 'print_material' ),
			'Large board'      => array( '15x20', 3.00, 'print_material' ),
			'Pullover'         => array( '', 13.00, 'merch' ),
		),
	);
}

function catp_connect_seed_catalog() {
	$log = array(
		'title'   => 'Starter catalog',
		'created' => array(),
		'skipped' => array(),
		'errors'  => array(),
		'next'    => array(),
	);
	if ( ! function_exists( 'update_field' ) ) {
		$log['errors'][] = 'Advanced Custom Fields is not active — nothing was created.';
		return $log;
	}
	$data = catp_connect_starter_catalog();
	$note = function ( &$log, $created, $label ) {
		$log[ $created ? 'created' : 'skipped' ][] = $label;
	};

	// Locations.
	$location_ids = array();
	foreach ( $data['locations'] as $name => $type ) {
		list( $id, $created ) = catp_connect_ensure_post( 'location', $name );
		if ( ! $id ) {
			$log['errors'][] = "Location: $name";
			continue;
		}
		$location_ids[ $name ] = $id;
		if ( $created ) {
			update_field( 'location_type', $type, $id );
		}
		$note( $log, $created, "Location: $name ($type)" );
	}

	// Teachers.
	$teacher_ids = array();
	foreach ( $data['teachers'] as $name => $info ) {
		list( $id, $created ) = catp_connect_ensure_post( 'teacher', $name );
		if ( ! $id ) {
			$log['errors'][] = "Teacher: $name";
			continue;
		}
		$teacher_ids[ $name ] = $id;
		if ( $created ) {
			if ( $info[0] ) {
				update_field( 'teacher_title', $info[0], $id );
			}
			if ( ! empty( $location_ids[ $info[1] ] ) ) {
				update_field( 'teacher_office_location', $location_ids[ $info[1] ], $id );
			}
		}
		$note( $log, $created, "Teacher: $name" . ( $info[0] ? " — {$info[0]}" : '' ) . " ({$info[1]})" );
	}

	// Subjects (classroom deliberately left empty — not known yet).
	foreach ( $data['subjects'] as $name => $teachers ) {
		list( $id, $created ) = catp_connect_ensure_post( 'subject', $name );
		if ( ! $id ) {
			$log['errors'][] = "Subject: $name";
			continue;
		}
		if ( $created ) {
			$ids = array();
			foreach ( $teachers as $t ) {
				if ( ! empty( $teacher_ids[ $t ] ) ) {
					$ids[] = $teacher_ids[ $t ];
				}
			}
			update_field( 'subject_teachers', $ids, $id );
		}
		$note( $log, $created, "Subject: $name — " . implode( ', ', $teachers ) );
	}

	// Products.
	foreach ( $data['products'] as $name => $p ) {
		list( $id, $created ) = catp_connect_ensure_post( 'product', $name );
		if ( ! $id ) {
			$log['errors'][] = "Product: $name";
			continue;
		}
		if ( $created ) {
			if ( $p[0] ) {
				update_field( 'product_size', $p[0], $id );
			}
			update_field( 'product_price', $p[1], $id );
			update_field( 'product_category', $p[2], $id );
		}
		$note( $log, $created, sprintf( 'Product: %s — %s$%.2f (%s)', $name, $p[0] ? "{$p[0]}, " : '', $p[1], $p[2] ) );
	}

	$log['next'] = array(
		'Add the <strong>classrooms/studios</strong> as Locations (Type = "Classroom" or "Studio"), then open each of the 4 Classes / Subjects and pick its <strong>Classroom</strong>.',
		'Confirm the <strong>Large board</strong> size and price (seeded as 15x20 / $3.00 — placeholders).',
		'Create the single <strong>App Settings</strong> post and paste the <strong>Tutoring External URL</strong>.',
		'Add <strong>Events</strong> as they come up. Photographers and Peer Tutors only once real students are approved.',
	);
	return $log;
}

/* -------------------------------------------------------------------------
 * 2. Page skeleton — core blocks only, editable like any other page.
 *    The catp-* classes are hooks for assets/catp-app.css (and the matching
 *    Block Styles); they impose structure, not design.
 * ---------------------------------------------------------------------- */

function catp_connect_b_heading( $text, $level = 2 ) {
	return sprintf( '<!-- wp:heading {"level":%1$d} --><h%1$d class="wp-block-heading">%2$s</h%1$d><!-- /wp:heading -->', (int) $level, esc_html( $text ) );
}
function catp_connect_b_para( $html, $class = '' ) {
	if ( $class ) {
		return sprintf( '<!-- wp:paragraph {"className":"%1$s"} --><p class="%1$s">%2$s</p><!-- /wp:paragraph -->', esc_attr( $class ), wp_kses_post( $html ) );
	}
	return '<!-- wp:paragraph --><p>' . wp_kses_post( $html ) . '</p><!-- /wp:paragraph -->';
}
function catp_connect_b_eyebrow( $text ) {
	return catp_connect_b_para( esc_html( $text ), 'catp-eyebrow' );
}
function catp_connect_b_note( $text ) {
	return catp_connect_b_para( $text, 'catp-note' );
}
function catp_connect_b_shortcode( $sc ) {
	return '<!-- wp:shortcode -->' . $sc . '<!-- /wp:shortcode -->';
}
/** A Group with a class. $layout: constrained (default) or none. */
function catp_connect_b_group( $class, $inner ) {
	return sprintf(
		'<!-- wp:group {"className":"%1$s","layout":{"type":"constrained"}} --><div class="wp-block-group %1$s">%2$s</div><!-- /wp:group -->',
		esc_attr( $class ),
		$inner
	);
}
/** Section header: eyebrow + h2 (+ optional "See all" link). */
function catp_connect_b_section( $eyebrow, $title, $more_text = '', $more_href = '' ) {
	$row = catp_connect_b_heading( $title, 2 );
	if ( $more_text ) {
		$row .= catp_connect_b_para( sprintf( '<a href="%s">%s &rarr;</a>', esc_url( $more_href ? $more_href : '#' ), esc_html( $more_text ) ) );
	}
	return catp_connect_b_group( 'catp-section', catp_connect_b_eyebrow( $eyebrow ) . catp_connect_b_group( 'catp-section-row', $row ) );
}
/** One tab-to-be. */
function catp_connect_b_tab( $slug, $label, $inner ) {
	return catp_connect_b_group( 'catp-tab catp-tab-' . $slug, catp_connect_b_heading( $label, 2 ) . $inner );
}
/** A card (used inside a Query Loop's post template). */
function catp_connect_b_card( $inner, $extra = '' ) {
	return catp_connect_b_group( trim( 'catp-card ' . $extra ), $inner );
}
/** Core Query Loop over one post type. */
function catp_connect_b_query( $post_type, $query_id, $inner, $order_by = 'title', $order = 'asc', $per_page = 20 ) {
	$attrs = array(
		'queryId'   => (int) $query_id,
		'query'     => array(
			'perPage'  => (int) $per_page,
			'pages'    => 0,
			'offset'   => 0,
			'postType' => $post_type,
			'order'    => $order,
			'orderBy'  => $order_by,
			'author'   => '',
			'search'   => '',
			'exclude'  => array(),
			'sticky'   => '',
			'inherit'  => false,
		),
		'className' => 'catp-list',
	);
	return '<!-- wp:query ' . wp_json_encode( $attrs ) . ' --><div class="wp-block-query catp-list"><!-- wp:post-template -->' . $inner . '<!-- /wp:post-template --></div><!-- /wp:query -->';
}
/** Full-width buttons. $buttons = [ [text, href, extraClass], ... ] */
function catp_connect_b_buttons( $buttons ) {
	$out = '<!-- wp:buttons {"className":"catp-ctas"} --><div class="wp-block-buttons catp-ctas">';
	foreach ( $buttons as $b ) {
		$class = trim( 'catp-cta ' . ( isset( $b[2] ) ? $b[2] : '' ) );
		$out  .= sprintf(
			'<!-- wp:button {"className":"%1$s"} --><div class="wp-block-button %1$s"><a class="wp-block-button__link wp-element-button" href="%2$s">%3$s</a></div><!-- /wp:button -->',
			esc_attr( $class ),
			esc_url( $b[1] ),
			esc_html( $b[0] )
		);
	}
	return $out . '</div><!-- /wp:buttons -->';
}
function catp_connect_b_tabs_intro( $tabs ) {
	return catp_connect_b_note(
		'<strong>Setup note (delete once done):</strong> each Group below is one tab: ' . esc_html( implode( ' · ', $tabs ) ) .
		'. To turn them into real tabs, add a <strong>Stackable → Tabs</strong> block with these tab labels and move each Group into its tab. ' .
		'Where a note says "Meta Field Block", add that block inside the post list and pick the named ACF field. ' .
		'Every Group and paragraph here has a CATP Block Style (sidebar → Styles) — the look comes from assets/catp-app.css in the plugin.'
	);
}

function catp_connect_page_skeleton() {
	$title       = '<!-- wp:post-title {"level":3,"isLink":true} /-->';
	$post_date   = '<!-- wp:post-date {"format":"F j, Y","className":"catp-eyebrow"} /-->';
	$badge       = catp_connect_b_group( 'catp-badge', '<!-- wp:post-date {"format":"M","className":"catp-badge-month"} /--><!-- wp:post-date {"format":"d","className":"catp-badge-day"} /-->' );
	$update_card = catp_connect_b_card( $post_date . $title . '<!-- wp:post-excerpt {"excerptLength":22} /-->' );
	$event_card  = catp_connect_b_card( $badge . catp_connect_b_eyebrow( 'Event' ) . $title, 'catp-card--event' );
	$plain_card  = catp_connect_b_card( $title );

	$home = catp_connect_b_eyebrow( 'Today' )
		. catp_connect_b_heading( 'Good morning.', 1 )
		. catp_connect_b_para( 'Your creative week, in one place.', 'catp-sub' )
		. catp_connect_b_note( 'Notifications will appear at the top of this page once the app/push plugin is chosen (keep them above Events). The date label above is static for now.' )
		. catp_connect_b_section( 'Stay in the loop', 'Latest updates', 'See all', '#' )
		. catp_connect_b_query( 'post', 10, $update_card, 'date', 'desc', 2 )
		. catp_connect_b_note( '"Latest updates" lists normal Posts (news). Point "See all" at the news page once it exists.' )
		. catp_connect_b_section( 'On the program', 'Upcoming', 'View calendar', '#' )
		. catp_connect_b_query( 'event', 11, $event_card, 'date', 'asc', 3 )
		. catp_connect_b_note( 'Events are ordered by their Event Date (the plugin keeps the post date in sync with the ACF field). The "Event" label is static — add an event type field later if needed. Each card should offer "Volunteer" (→ Volunteer Form with the event pre-selected) and "Participate" (→ Submit Work).' )
		. catp_connect_b_buttons( array(
			array( 'Reserve a resource', '/resources/', 'catp-cta--calendar' ),
			array( 'Drop Zone', '/get-involved/', 'catp-cta--camera' ),
		) )
		. catp_connect_b_note( 'Button labels are plain text — rename "Drop Zone" once the Board naming is decided.' );

	$resources = catp_connect_b_heading( 'Resources', 1 )
		. catp_connect_b_tabs_intro( array( 'Tutoring', 'Studio', 'Goods', 'Borrow', 'Photo Form' ) )
		. catp_connect_b_tab( 'tutoring', 'Tutoring', catp_connect_b_para( 'Book tutoring through the program\'s existing request page:' ) . catp_connect_b_shortcode( '[catp_tutoring_button text="Request Tutoring"]' ) . catp_connect_b_note( 'The button appears once the Tutoring External URL is filled in under App Settings. Nothing else goes in this tab — the app captures nothing for tutoring.' ) )
		. catp_connect_b_tab( 'studio', 'Studio', catp_connect_b_note( 'Booking Calendar goes here: 4 fixed slots (08:00–10:00, 10:00–12:00, 13:00–15:00, 15:00–17:00), a gear checklist, and the school email as contact. Must also block times taken by regular classes.' ) )
		. catp_connect_b_tab( 'goods', 'Goods', catp_connect_b_para( 'Price calculator for print materials and merch — nothing is ordered here.' ) . catp_connect_b_query( 'product', 12, $plain_card ) . catp_connect_b_note( 'Add Meta Field Blocks for <code>product_size</code>, <code>product_price</code> and <code>product_category</code> inside each card, then a quantity input + running total.' ) )
		. catp_connect_b_tab( 'borrow', 'Borrow', catp_connect_b_note( 'WP Inventory Manager goes here: live available / checked-out status of the shared iPads, and the request form. Same-day, in-classroom use only.' ) )
		. catp_connect_b_tab( 'photo-form', 'Photo Form', catp_connect_b_note( 'Forminator form goes here: school email (@kctcs.edu, required even for a guest), session type (model / photographer), desired date, guest name.' ) );

	$get_involved = catp_connect_b_heading( 'Get Involved', 1 )
		. catp_connect_b_tabs_intro( array( 'Volunteer Form', 'Submit Work', 'Board' ) )
		. catp_connect_b_tab( 'volunteer-form', 'Volunteer Form', catp_connect_b_note( 'Forminator form goes here: school email, request date, event (dropdown), and the "Become a Peer Tutor" request (subject + availability). Say clearly that volunteer hours count toward practicum hours.' ) )
		. catp_connect_b_tab( 'submit-work', 'Submit Work', catp_connect_b_note( 'Forminator form goes here: name, work type (Ad / Photo / Web), OneDrive folder link (no upload), optional event. Show the file-naming instructions next to the form.' ) )
		. catp_connect_b_tab( 'board', 'Board', catp_connect_b_query( 'board_post', 13, catp_connect_b_card( '<!-- wp:post-featured-image /-->' . $title ), 'date', 'desc' ) . catp_connect_b_note( 'Image-gallery grid. Add a Meta Field Block for <code>board_post_image</code> (or use the featured image) and one for <code>board_post_display_name</code> with "Anonymous" as the fallback. Never show <code>board_post_email</code>. The submission form (Forminator, Post Creation → Board Posts, as Draft) goes above the grid.' ) );

	$more = catp_connect_b_heading( 'More', 1 )
		. catp_connect_b_tabs_intro( array( 'My Program', 'Preparation' ) )
		. catp_connect_b_tab( 'my-program', 'My Program',
			catp_connect_b_heading( 'Classes', 3 ) . catp_connect_b_query( 'subject', 14, $plain_card ) . catp_connect_b_note( 'Add Meta Field Blocks for <code>subject_teachers</code> and <code>subject_location</code> inside each card.' )
			. catp_connect_b_heading( 'Faculty & Staff', 3 ) . catp_connect_b_query( 'teacher', 15, $plain_card ) . catp_connect_b_note( 'Add Meta Field Blocks for <code>teacher_title</code> and <code>teacher_office_location</code>.' )
			. catp_connect_b_heading( 'Peer Tutors', 3 ) . catp_connect_b_query( 'peer_tutor', 16, $plain_card ) . catp_connect_b_note( 'Add Meta Field Blocks for <code>peer_tutor_subject</code> and <code>peer_tutor_availability</code>. Never show <code>peer_tutor_school_email</code>.' )
		)
		. catp_connect_b_tab( 'preparation', 'Preparation', catp_connect_b_note( 'Portfolio-readiness progress bar + NOCTI exam-prep module (game-style, with a streak counter). Not designed yet.' ) );

	return array(
		'home'         => array( 'title' => 'Home', 'content' => catp_connect_b_group( 'catp-page', $home ) ),
		'resources'    => array( 'title' => 'Resources', 'content' => catp_connect_b_group( 'catp-page', $resources ) ),
		'get-involved' => array( 'title' => 'Get Involved', 'content' => catp_connect_b_group( 'catp-page', $get_involved ) ),
		'more'         => array( 'title' => 'More', 'content' => catp_connect_b_group( 'catp-page', $more ) ),
	);
}

function catp_connect_create_page_skeleton( $overwrite = false ) {
	$log = array(
		'title'   => 'Page skeleton',
		'created' => array(),
		'skipped' => array(),
		'errors'  => array(),
		'next'    => array(),
	);
	$ids = array();
	foreach ( catp_connect_page_skeleton() as $slug => $page ) {
		$existing = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $existing ) {
			$ids[ $slug ] = (int) $existing->ID;
			if ( $overwrite ) {
				wp_update_post( array( 'ID' => $existing->ID, 'post_content' => $page['content'] ) );
				$log['created'][] = "Page: {$page['title']} (/$slug/) — content replaced with the fresh skeleton";
			} else {
				$log['skipped'][] = "Page: {$page['title']} (/$slug/)";
			}
			continue;
		}
		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_title'   => $page['title'],
				'post_name'    => $slug,
				'post_status'  => 'publish',
				'post_content' => $page['content'],
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			$log['errors'][] = "Page: {$page['title']} — " . $id->get_error_message();
			continue;
		}
		$ids[ $slug ] = (int) $id;
		$log['created'][] = "Page: {$page['title']} (/$slug/)";
	}

	// Home as the static front page (only if no front page is set yet).
	if ( ! empty( $ids['home'] ) && ( 'page' !== get_option( 'show_on_front' ) || ! get_option( 'page_on_front' ) ) ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $ids['home'] );
		$log['created'][] = 'Settings → Reading: Home set as the front page';
	} else {
		$log['skipped'][] = 'Front page setting (already set)';
	}

	// "App Navigation" menu with the four pages.
	$menu_name = 'App Navigation';
	$menu      = wp_get_nav_menu_object( $menu_name );
	if ( ! $menu ) {
		$menu_id = wp_create_nav_menu( $menu_name );
		if ( ! is_wp_error( $menu_id ) ) {
			foreach ( array( 'home', 'resources', 'get-involved', 'more' ) as $slug ) {
				if ( empty( $ids[ $slug ] ) ) {
					continue;
				}
				wp_update_nav_menu_item(
					$menu_id,
					0,
					array(
						'menu-item-object-id' => $ids[ $slug ],
						'menu-item-object'    => 'page',
						'menu-item-type'      => 'post_type',
						'menu-item-status'    => 'publish',
					)
				);
			}
			$log['created'][] = "Menu: $menu_name (Home, Resources, Get Involved, More)";
			$locations  = get_nav_menu_locations();
			$registered = array_keys( get_registered_nav_menus() );
			if ( $registered ) {
				$first = $registered[0];
				if ( empty( $locations[ $first ] ) ) {
					$locations[ $first ] = $menu_id;
					set_theme_mod( 'nav_menu_locations', $locations );
					$log['created'][] = "Menu assigned to theme location \"$first\"";
				} else {
					$log['next'][] = "Assign the <strong>$menu_name</strong> menu to the theme's header/footer location (Appearance → Menus) — that slot already had a menu, so it was left alone.";
				}
			} else {
				$log['next'][] = "Assign the <strong>$menu_name</strong> menu to the theme's header/footer location once the theme (Blocksy) is active.";
			}
		}
	} else {
		$log['skipped'][] = "Menu: $menu_name";
	}

	$log['next'][] = 'Open each page and convert its Groups into a <strong>Stackable → Tabs</strong> block (the setup note at the top of each page says how), then delete the setup notes.';
	$log['next'][] = 'Drop the Forminator / Booking Calendar / WP Inventory Manager blocks into the tabs whose notes name them.';
	$log['next'][] = 'For a phone-style bottom bar: Customizer → Footer → add the App Navigation menu, give that footer row the class <code>catp-bottom-nav</code> (the plugin\'s stylesheet pins it to the bottom on phones).';
	return $log;
}
