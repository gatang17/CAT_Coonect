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
 *     as the front page, builds an "App Navigation" menu, and stores the
 *     fixed navigation as the "App Shell Nav" synced pattern.
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
		<p>Creates the five app pages — <strong>Home, Resources, Get Involved, Help, More</strong> — each with a page header, one section per tab (headings, placeholder notes, and the parts that already work: the Tutoring button, and post lists for the directory, events, products and the board) and the fixed navigation. Built from core blocks only; each section is a Group ready to become a Stackable Tab. Also creates the <strong>App Shell Nav</strong> synced pattern, sets Home as the front page and creates an "App Navigation" menu.</p>
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
 *    The catp-* classes are hooks for wordpress/catp-app.css (pasted into
 *    Customizer -> Additional CSS) and the matching Block Styles; they impose
 *    structure, not design.
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
/**
 * The navigation shell, as one Custom HTML block.
 *
 * Five links with inline Lucide icons. `catp-appnav` is what the stylesheet
 * keys off: a fixed rail on the left at >=900px, a fixed bottom bar below
 * that. HTML rather than a Group of links so the icons survive; editors can
 * still change labels and hrefs in the block.
 */
function catp_connect_nav_html() {
	$icons = array(
		'home'         => '<path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/><path d="M3 10a2 2 0 0 1 .709-1.528l7-6a2 2 0 0 1 2.582 0l7 6A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
		'resources'    => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.106-3.105c.32-.322.863-.22.983.218a6 6 0 0 1-8.259 7.057l-7.91 7.91a1 1 0 0 1-2.999-3l7.91-7.91a6 6 0 0 1 7.057-8.259c.438.12.54.662.219.984z"/>',
		'get-involved' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><path d="M16 3.128a4 4 0 0 1 0 7.744"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><circle cx="9" cy="7" r="4"/>',
		'help'         => '<path d="M2.992 16.342a2 2 0 0 1 .094 1.167l-1.065 3.29a1 1 0 0 0 1.236 1.168l3.413-.998a2 2 0 0 1 1.099.092 10 10 0 1 0-4.777-4.719"/>',
		'more'         => '<path d="M4 5h16"/><path d="M4 12h16"/><path d="M4 19h16"/>',
	);
	$items = array(
		array( 'home', 'Home', '/' ),
		array( 'resources', 'Resources', '/resources/' ),
		array( 'get-involved', 'Get Involved', '/get-involved/' ),
		array( 'help', 'Help', '/help/' ),
		array( 'more', 'More', '/more/' ),
	);
	$links = '<p class="catp-brand">CATP<span>+</span></p>';
	foreach ( $items as $item ) {
		list( $key, $label, $href ) = $item;
		$links .= sprintf(
			'<a href="%s"><svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%s</svg><span>%s</span></a>',
			esc_url( $href ),
			$icons[ $key ],
			esc_html( $label )
		);
	}
	return '<nav class="catp-appnav" aria-label="App navigation">' . $links . '</nav>';
}

/**
 * Store that nav once as a synced pattern (a `wp_block` post) so all four
 * pages share one copy: edit it in Appearance -> Patterns and every page
 * follows. Returns [id, created].
 */
function catp_connect_ensure_nav_pattern() {
	return catp_connect_ensure_post(
		'wp_block',
		'App Shell Nav',
		array( 'post_content' => '<!-- wp:html -->' . catp_connect_nav_html() . '<!-- /wp:html -->' )
	);
}

/** A reference to that synced pattern — what actually goes on each page. */
function catp_connect_b_nav_ref( $ref ) {
	return $ref ? sprintf( '<!-- wp:block {"ref":%d} /-->', (int) $ref ) : '';
}

/**
 * Page header: the wordmark, the title with a one-line subtitle, and an icon
 * on the right. Matches `.catp-page-header` in the stylesheet.
 */
function catp_connect_b_page_header( $title, $subtitle ) {
	return catp_connect_b_group(
		'catp-page-header',
		catp_connect_b_para( 'CATP<span>+</span>', 'catp-brand' )
		. catp_connect_b_group(
			'catp-page-header-copy',
			catp_connect_b_heading( $title, 1 ) . catp_connect_b_para( esc_html( $subtitle ) )
		)
	);
}

function catp_connect_b_tabs_intro( $tabs ) {
	return catp_connect_b_note(
		'<strong>Setup note (delete once done):</strong> each Group below is one tab: ' . esc_html( implode( ' · ', $tabs ) ) .
		'. To turn them into real tabs, add a <strong>Stackable → Tabs</strong> block with these tab labels, give that block the class <code>catp-tabs catp-tabs--sub</code> (Block → Advanced → Additional CSS class(es)), and move each Group into its tab. ' .
		'Two labels only? add <code>catp-tabs--two</code> as well. A first level that switches whole modes of a page uses <code>catp-tabs--segment</code> instead. ' .
		'Where a note says "Meta Field Block", add that block inside the post list and pick the named ACF field. ' .
		'The look comes from <code>catp-app.css</code>, pasted into Customizer → Additional CSS — the plugin ships no styles.'
	);
}

function catp_connect_page_skeleton( $nav_ref = 0 ) {
	$title       = '<!-- wp:post-title {"level":3,"isLink":true} /-->';
	$post_date   = '<!-- wp:post-date {"format":"F j, Y","className":"catp-eyebrow"} /-->';
	$badge       = catp_connect_b_group( 'catp-badge', '<!-- wp:post-date {"format":"M","className":"catp-badge-month"} /--><!-- wp:post-date {"format":"d","className":"catp-badge-day"} /-->' );
	$update_card = catp_connect_b_card( $post_date . $title . '<!-- wp:post-excerpt {"excerptLength":22} /-->' );
	$event_card  = catp_connect_b_card( $badge . catp_connect_b_eyebrow( 'Event' ) . $title, 'catp-card--event' );
	$plain_card  = catp_connect_b_card( $title );

	// Home's greeting is not a `catp-page-header`: it is a full-width welcome,
	// so it keeps its own eyebrow + h1 + subtitle.
	$home_intro = catp_connect_b_eyebrow( 'Today' )
		. catp_connect_b_heading( 'Good morning.', 1 )
		. catp_connect_b_para( 'Your creative week, in one place.', 'catp-sub' )
		. catp_connect_b_note( 'The date label above is static for now. Notifications will appear under it once the app/push plugin is chosen.' );

	// Tab 1 — Updates: the news and what is coming up.
	$home_updates = catp_connect_b_tab( 'updates', 'Updates',
		catp_connect_b_section( 'Stay in the loop', 'Latest updates', 'See all', '#' )
		. catp_connect_b_query( 'post', 10, $update_card, 'date', 'desc', 2 )
		. catp_connect_b_note( '"Latest updates" lists normal Posts (news). Point "See all" at the news page once it exists.' )
		. catp_connect_b_section( 'On the program', 'Upcoming', 'View calendar', '#' )
		. catp_connect_b_query( 'event', 11, $event_card, 'date', 'asc', 3 )
		. catp_connect_b_note( 'Events are ordered by their Event Date (the plugin keeps the post date in sync with the ACF field). The "Event" label is static — add an event type field later if needed. Each card should offer "Volunteer" (→ Volunteer Form with the event pre-selected) and "Participate" (→ Submit Work).' )
		. catp_connect_b_buttons( array(
			array( 'Reserve a resource', '/resources/', 'catp-cta--calendar' ),
		) )
	);

	// Tab 2 — Drop Zone: the same approved Board Posts as Get Involved → Board,
	// surfaced on Home because that is where students look first. One post type,
	// two places: publishing a Board Post is still the single approval step.
	// Tab 2 — Drop Zone: the moderated gallery. The prototype puts the Board
	// here (and on its own Drop Zone screen), not under Get Involved, so the
	// gallery and its submission form live together.
	$home_dropzone = catp_connect_b_tab( 'drop-zone', 'Drop Zone',
		catp_connect_b_section( 'CAT droppings', 'Student work, on the wall.' )
		. catp_connect_b_para( 'A moderated gallery for sharing creativity across the program.', 'catp-form-description' )
		. catp_connect_b_note( 'Submission form goes here (Forminator → Post Creation, post type Board Posts, status <strong>Draft</strong>): title, image, school email (@kctcs.edu), optional display name. Publishing the draft is the approval. Show the "pending approval" line as a <code>catp-pending</code> paragraph after submitting.' )
		. catp_connect_b_group( 'catp-gallery',
			catp_connect_b_query( 'board_post', 13, catp_connect_b_group( 'catp-tile', '<!-- wp:post-featured-image /-->' . $title ), 'date', 'desc', 12 )
		)
		. catp_connect_b_note( 'Give the Query Loop\'s wrapper <code>catp-gallery</code> and each card <code>catp-tile</code> — 2 columns on a phone, 4 on a desktop. Add a Meta Field Block for <code>board_post_display_name</code> ("Anonymous" when empty). Never show <code>board_post_email</code>.' )
	);

	$home = $home_intro
		. catp_connect_b_note( '<strong>Setup note (delete once done):</strong> the two Groups below are Home\'s top tabs: Updates · Drop Zone. Add a <strong>Stackable → Tabs</strong> block with those labels, give it the class <code>catp-tabs catp-tabs--folder</code> (the file-folder tabs the prototype uses on Home), and move each Group into its tab.' )
		. $home_updates
		. $home_dropzone;

	// Resources has TWO tab levels in the prototype: Bookings vs Materials &
	// Equipment on top, and the individual tools underneath. The Groups below
	// mirror that nesting — an outer Group per category, inner Groups per tool.
	$resources = catp_connect_b_page_header( 'Resources', 'Make space for the work.' )
		. catp_connect_b_note(
			'<strong>Setup note (delete once done):</strong> this page has two tab levels. '
			. 'Add a <strong>Stackable → Tabs</strong> block with <code>catp-tabs catp-tabs--segment</code> and the labels <em>Bookings</em> · <em>Materials &amp; Equipment</em>; '
			. 'inside each of those, nest a second Tabs block with <code>catp-tabs catp-tabs--sub</code> for the tools listed in its heading.'
		)
		. catp_connect_b_tab( 'bookings', 'Bookings',
			catp_connect_b_note( 'Second level here: Tutoring · Studio · Photo Form.' )
			. catp_connect_b_tab( 'tutoring', 'Tutoring', catp_connect_b_para( 'Book tutoring through the program\'s existing request page:' ) . catp_connect_b_shortcode( '[catp_tutoring_button text="Request Tutoring"]' ) . catp_connect_b_note( 'The button appears once the Tutoring External URL is filled in under App Settings. Nothing else goes in this tab — the app captures nothing for tutoring.' ) )
			. catp_connect_b_tab( 'studio', 'Studio', catp_connect_b_note( 'Booking Calendar goes here: 4 fixed slots (08:00–10:00, 10:00–12:00, 13:00–15:00, 15:00–17:00), a gear checklist (<code>catp-check</code> rows), and the school email as contact. Must also block times taken by regular classes.' ) )
			. catp_connect_b_tab( 'photo-form', 'Photo Form', catp_connect_b_note( 'Forminator form goes here: school email (@kctcs.edu, required even for a guest), session type, desired date, guest name, preferred photographer.' ) )
		)
		. catp_connect_b_tab( 'materials', 'Materials & Equipment',
			catp_connect_b_note( 'Second level here: Program Goods · Borrow.' )
			. catp_connect_b_tab( 'program-goods', 'Program Goods',
				catp_connect_b_shortcode( '[catp_goods_calculator]' )
				. catp_connect_b_note( 'The calculator is rendered by the plugin: it lists every published <strong>Product</strong> with its size and price, adds a quantity stepper to each row, and keeps a running total. Print materials first, Merch after the divider — that order comes from <code>product_category</code>. To change what it shows, edit the Products; to change how it looks, edit <code>catp-app.css</code>. Attributes: <code>currency</code>, <code>note</code>, <code>heading="no"</code>.' )
				. catp_connect_b_note( 'To let students send the list: put a Forminator form under this shortcode, give one hidden or textarea field the class <code>catp-goods-summary</code>, and the calculator fills it with the chosen items and the total. Give the submit button the attribute <code>data-catp-requires-items</code> to keep it disabled until something is picked.' )
			)
			. catp_connect_b_tab( 'borrow', 'Borrow', catp_connect_b_note( 'WP Inventory Manager goes here: the 10 shared iPads with live available / checked-out status (<code>catp-status</code> badges) and the request form. Same-day, in-classroom use only, and a faculty member must check the iPad out.' ) )
		);

	$get_involved = catp_connect_b_page_header( 'Get Involved', 'Put your skills into motion.' )
		. catp_connect_b_tabs_intro( array( 'Volunteer Form', 'Submit Work' ) )
		. catp_connect_b_tab( 'volunteer-form', 'Volunteer Form', catp_connect_b_note( 'Forminator form goes here: school email, request date, event (dropdown), and the "Become a Peer Tutor" request (subject + availability). Say clearly that volunteer hours count toward practicum hours.' ) )
		. catp_connect_b_tab( 'submit-work', 'Submit Work', catp_connect_b_note( 'Forminator form goes here: name, work type (Ad / Photo / Web), OneDrive folder link (no upload), optional event. Show the file-naming instructions next to the form.' ) );

	$more = catp_connect_b_page_header( 'More', 'Find your people. Keep growing.' )
		. catp_connect_b_tabs_intro( array( 'My Program', 'Preparation' ) )
		. catp_connect_b_tab( 'my-program', 'My Program',
			catp_connect_b_heading( 'Classes', 3 ) . catp_connect_b_query( 'subject', 14, $plain_card ) . catp_connect_b_note( 'Add Meta Field Blocks for <code>subject_teachers</code> and <code>subject_location</code> inside each card.' )
			. catp_connect_b_heading( 'Faculty & Staff', 3 ) . catp_connect_b_query( 'teacher', 15, $plain_card ) . catp_connect_b_note( 'Add Meta Field Blocks for <code>teacher_title</code> and <code>teacher_office_location</code>.' )
			. catp_connect_b_heading( 'Peer Tutors', 3 ) . catp_connect_b_query( 'peer_tutor', 16, $plain_card ) . catp_connect_b_note( 'Add Meta Field Blocks for <code>peer_tutor_subject</code> and <code>peer_tutor_availability</code>. Never show <code>peer_tutor_school_email</code>.' )
		)
		. catp_connect_b_tab( 'preparation', 'Preparation', catp_connect_b_note( 'Portfolio-readiness progress bar + NOCTI exam-prep module (game-style, with a streak counter). Not designed yet.' ) );

	$help = catp_connect_b_page_header( 'Help', 'Quick answers for your next step.' )
		. catp_connect_b_group( 'catp-chat', catp_connect_b_para( 'Hi — I can help you find CATP answers. Choose a question or type your own.', 'catp-bubble' ) )
		. catp_connect_b_section( 'Suggested questions', 'Start here' )
		. catp_connect_b_note( 'The FAQ list goes here: one link per question, wrapped in a Group with the class <code>catp-faq</code>. Point each at the tab that answers it (Resources → Studio, Get Involved → Submit Work, and so on). A real chat widget is a later decision — until then this page is a linked FAQ, which needs no plugin.' );

	// The nav sits outside the centered column: it is fixed to the viewport,
	// so the column's max-width must not apply to it.
	$nav = catp_connect_b_nav_ref( $nav_ref );

	return array(
		'home'         => array( 'title' => 'Home', 'content' => catp_connect_b_group( 'catp-page', $home ) . $nav ),
		'resources'    => array( 'title' => 'Resources', 'content' => catp_connect_b_group( 'catp-page', $resources ) . $nav ),
		'get-involved' => array( 'title' => 'Get Involved', 'content' => catp_connect_b_group( 'catp-page', $get_involved ) . $nav ),
		'more'         => array( 'title' => 'More', 'content' => catp_connect_b_group( 'catp-page', $more ) . $nav ),
		'help'         => array( 'title' => 'Help', 'content' => catp_connect_b_group( 'catp-page', $help ) . $nav ),
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
	list( $nav_ref, $nav_created ) = catp_connect_ensure_nav_pattern();
	if ( $nav_created ) {
		$log['created'][] = 'Synced pattern: App Shell Nav (the fixed navigation, shared by all four pages)';
	} elseif ( $nav_ref ) {
		$log['skipped'][] = 'Synced pattern: App Shell Nav';
	}

	$ids = array();
	foreach ( catp_connect_page_skeleton( $nav_ref ) as $slug => $page ) {
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
			foreach ( array( 'home', 'resources', 'get-involved', 'help', 'more' ) as $slug ) {
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
			$log['created'][] = "Menu: $menu_name (Home, Resources, Get Involved, Help, More)";
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
	$log['next'][] = 'Paste <code>wordpress/catp-app.css</code> into <strong>Appearance → Customize → Additional CSS</strong> — nothing is styled until you do, because the plugin ships no CSS.';
	$log['next'][] = 'The navigation is already on all four pages as the synced pattern <strong>App Shell Nav</strong> (Appearance → Patterns): a fixed rail on the left on desktop, a fixed bar along the bottom on phones. Edit it once and every page follows.';
	return $log;
}
