<?php
/**
 * The teacher directory, with a search box that filters as you type.
 *
 * Like the Goods calculator, this exists because the behaviour cannot come
 * from a block: filtering a list needs script. It renders the structure and
 * the classes catp-app.css already styles — never appearance.
 *
 * Grouping is derived, not hard-coded: a teacher named in some subject's
 * `subject_teachers` is Faculty; a teacher named in none is Administration
 * (the dean and the program coordinator, who carry a title instead).
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'catp_connect_register_directory_script' );
function catp_connect_register_directory_script() {
	wp_register_script(
		'catp-directory',
		plugins_url( 'assets/catp-directory.js', CATP_CONNECT_FILE ),
		array(),
		CATP_CONNECT_VERSION,
		true
	);
}

/**
 * Which subjects each teacher is listed under.
 *
 * One pass over the subjects rather than a meta query per teacher, because
 * `subject_teachers` stores post IDs and there are four subjects in total.
 *
 * @return array<int, string[]> teacher ID => subject titles
 */
function catp_connect_subjects_by_teacher() {
	if ( ! function_exists( 'get_field' ) ) {
		return array();
	}
	$map      = array();
	$subjects = get_posts(
		array(
			'post_type'   => 'subject',
			'post_status' => 'publish',
			'numberposts' => 50,
			'orderby'     => 'title',
			'order'       => 'ASC',
		)
	);
	foreach ( $subjects as $subject ) {
		$teachers = get_field( 'subject_teachers', $subject->ID );
		if ( empty( $teachers ) ) {
			continue;
		}
		// The field may return one post or many, as objects or as IDs.
		foreach ( (array) $teachers as $teacher ) {
			$id = is_object( $teacher ) ? (int) $teacher->ID : (int) $teacher;
			if ( $id ) {
				$map[ $id ][] = get_the_title( $subject );
			}
		}
	}
	return $map;
}

/**
 * Every published teacher, split into the two groups the app shows.
 *
 * @return array{administration: array, faculty: array}
 */
function catp_connect_directory_groups() {
	if ( ! function_exists( 'get_field' ) ) {
		return array( 'administration' => array(), 'faculty' => array() );
	}
	$by_teacher = catp_connect_subjects_by_teacher();
	$groups     = array( 'administration' => array(), 'faculty' => array() );

	$teachers = get_posts(
		array(
			'post_type'   => 'teacher',
			'post_status' => 'publish',
			'numberposts' => 200,
			'orderby'     => 'title',
			'order'       => 'ASC',
		)
	);
	foreach ( $teachers as $teacher ) {
		$subjects = isset( $by_teacher[ $teacher->ID ] ) ? $by_teacher[ $teacher->ID ] : array();
		$title    = (string) get_field( 'teacher_title', $teacher->ID );
		$office   = get_field( 'teacher_office_location', $teacher->ID );
		$room     = '';
		if ( $office ) {
			$room = is_object( $office ) ? get_the_title( $office ) : get_the_title( (int) $office );
		}
		$entry = array(
			'name' => get_the_title( $teacher ),
			// Faculty read as their subjects; administration as their title.
			'role' => $subjects ? implode( ' · ', $subjects ) : $title,
			'room' => $room,
		);
		$groups[ $subjects ? 'faculty' : 'administration' ][] = $entry;
	}
	return $groups;
}

/**
 * Lowercase and strip accents, so "avila" finds "Ávila" and "Avila-Ugalde".
 * The same normalisation runs in catp-directory.js on what the visitor types.
 */
function catp_connect_search_key( $text ) {
	$text = wp_strip_all_tags( $text );
	if ( function_exists( 'remove_accents' ) ) {
		$text = remove_accents( $text );
	}
	return strtolower( trim( preg_replace( '/\s+/', ' ', $text ) ) );
}

/**
 * [catp_directory]
 *
 * Attributes:
 *   placeholder  Text in the search box.
 *   groups       "both" (default), "faculty" or "administration".
 *   actions      "no" to render plain rows with no expandable panel.
 */
add_shortcode( 'catp_directory', 'catp_connect_directory_shortcode' );
function catp_connect_directory_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'placeholder' => 'Search teachers or subjects',
			'groups'      => 'both',
			'actions'     => 'yes',
		),
		$atts,
		'catp_directory'
	);

	$groups = catp_connect_directory_groups();
	if ( empty( $groups['administration'] ) && empty( $groups['faculty'] ) ) {
		return current_user_can( 'edit_posts' )
			? '<p class="catp-note">Directory: no published Teachers yet. Add them under Teachers, or press <strong>App Settings → Setup Tools → Load starter catalog</strong>.</p>'
			: '';
	}

	$wanted = array(
		'administration' => 'Administration',
		'faculty'        => 'Faculty',
	);
	if ( 'both' !== $atts['groups'] ) {
		$wanted = array_intersect_key( $wanted, array( $atts['groups'] => true ) );
	}

	wp_enqueue_script( 'catp-directory' );
	$show_actions = 'no' !== $atts['actions'];

	ob_start();
	?>
	<section class="catp-directory" data-catp-directory>
		<div class="catp-search">
			<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m21 21-4.34-4.34"/><circle cx="11" cy="11" r="8"/></svg>
			<input type="search" data-catp-search placeholder="<?php echo esc_attr( $atts['placeholder'] ); ?>" aria-label="<?php echo esc_attr( $atts['placeholder'] ); ?>" autocomplete="off">
		</div>

		<p class="catp-note" data-catp-empty hidden>Nothing matches that. Try a name, a subject or a room.</p>
		<p class="screen-reader-text" data-catp-count aria-live="polite"></p>

		<div class="catp-directory-list">
			<?php foreach ( $wanted as $key => $label ) : ?>
				<?php if ( empty( $groups[ $key ] ) ) { continue; } ?>
				<div class="catp-directory-group" data-catp-group>
					<p class="catp-eyebrow"><?php echo esc_html( strtoupper( $label ) ); ?></p>
					<?php foreach ( $groups[ $key ] as $person ) : ?>
						<div class="catp-directory-entry" data-catp-entry
							data-catp-search-key="<?php echo esc_attr( catp_connect_search_key( $person['name'] . ' ' . $person['role'] . ' ' . $person['room'] ) ); ?>">
							<?php $tag = $show_actions ? 'button' : 'div'; ?>
							<<?php echo $tag; ?> class="catp-directory-row"<?php echo $show_actions ? ' type="button" data-catp-toggle aria-expanded="false"' : ''; ?>>
								<div>
									<strong><?php echo esc_html( $person['name'] ); ?></strong>
									<?php if ( '' !== $person['role'] ) : ?>
										<span><?php echo esc_html( $person['role'] ); ?></span>
									<?php endif; ?>
								</div>
								<?php if ( '' !== $person['room'] ) : ?>
									<div class="catp-room">
										<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg>
										<?php echo esc_html( $person['room'] ); ?>
									</div>
								<?php endif; ?>
							</<?php echo $tag; ?>>
							<?php if ( $show_actions ) : ?>
								<div class="catp-directory-actions" data-catp-panel hidden>
									<p><?php echo esc_html( 'What would you like to do with ' . $person['name'] . '?' ); ?></p>
									<?php
									// The tab script gives every tab an id from its `catp-tab-<slug>`
									// class, so this lands on Tutoring rather than the top of the
									// page. Both groups go there: the prototype dropped its separate
									// Advising tab, so a meeting is booked the same way.
									?>
									<a href="<?php echo esc_url( home_url( '/resources/#tutoring' ) ); ?>"><?php echo esc_html( 'faculty' === $key ? 'Request tutoring' : 'Ask for a meeting' ); ?></a>
								</div>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
	return ob_get_clean();
}
