<?php
/**
 * Plugin Name:       CATP Connect — Field Group Installer (one-shot)
 * Description:       Creates the nine CATP Connect field groups directly in the database through ACF's own save API, without going through ACF's file importer. Run once, read what it reports, then delete this plugin. Importing the same JSON later just updates the groups in place.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 */

defined( 'ABSPATH' ) || exit;

const CATP_INSTALLER_OPTION = 'catp_acf_installer_report';

/**
 * Create (or update) the field groups.
 *
 * Deliberately does NOT use ACF's importer. It calls acf_update_field_group()
 * and acf_update_field() — the same two functions the ACF admin calls when you
 * press "Save Changes" on a field group — so the result is an ordinary database
 * field group, editable in the admin, with none of the file-upload and
 * JSON-parsing path in between.
 *
 * Keyed by `key`, so running it twice updates rather than duplicates.
 */
function catp_installer_run() {
	$groups = catp_installer_groups();
	$report = array();

	foreach ( $groups as $group ) {
		$fields = isset( $group['fields'] ) ? $group['fields'] : array();
		unset( $group['fields'] );

		$saved = acf_update_field_group( $group );
		if ( empty( $saved['ID'] ) ) {
			$report[] = sprintf( 'FAILED: %s (field group could not be saved)', $group['title'] );
			continue;
		}

		$count = 0;
		foreach ( $fields as $i => $field ) {
			$field['parent']     = $saved['ID'];
			$field['menu_order'] = $i;
			if ( acf_update_field( $field ) ) {
				$count++;
			}
		}
		$report[] = sprintf( '%s — %d of %d fields', $group['title'], $count, count( $fields ) );
	}

	update_option( CATP_INSTALLER_OPTION, $report, false );
	return $report;
}

add_action( 'admin_init', 'catp_installer_maybe_run' );
function catp_installer_maybe_run() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( get_option( CATP_INSTALLER_OPTION ) ) {
		return; // already run
	}
	if ( ! function_exists( 'acf_update_field_group' ) || ! function_exists( 'acf_update_field' ) ) {
		return; // ACF not active yet; the notice below says so
	}
	catp_installer_run();
}

add_action( 'admin_notices', 'catp_installer_notice' );
function catp_installer_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( ! function_exists( 'acf_update_field_group' ) ) {
		echo '<div class="notice notice-error"><p><strong>CATP field group installer:</strong> activate Advanced Custom Fields first, then reload this page.</p></div>';
		return;
	}

	$report = get_option( CATP_INSTALLER_OPTION );
	if ( ! $report ) {
		return;
	}

	$failed = false;
	foreach ( (array) $report as $line ) {
		if ( 0 === strpos( $line, 'FAILED' ) ) {
			$failed = true;
		}
	}

	printf(
		'<div class="notice notice-%s"><p><strong>CATP field groups installed.</strong> Go to <em>Custom Fields</em> — they are ordinary database groups now, editable like any other. You can delete this installer plugin.</p><ul style="margin-left:1.5em;list-style:disc">',
		$failed ? 'warning' : 'success'
	);
	foreach ( (array) $report as $line ) {
		echo '<li>' . esc_html( $line ) . '</li>';
	}
	echo '</ul></div>';
}

/** The nine groups, verbatim from acf-field-groups.json. */
function catp_installer_groups() {
	return json_decode( <<<'JSON'
[
  {
    "key": "group_catp_location",
    "title": "Location Details",
    "fields": [
      {
        "key": "field_catp_location_type",
        "label": "Type",
        "name": "location_type",
        "type": "text",
        "instructions": "e.g. Classroom, Studio, Office. Matches the `type` column on the `location` table in database/schema.sql. Use \"Office\" for a teacher's office (see Teacher Details).",
        "required": 1,
        "conditional_logic": 0,
        "wrapper": { "width": "", "class": "", "id": "" },
        "default_value": "",
        "placeholder": "Classroom",
        "prepend": "",
        "append": "",
        "maxlength": ""
      }
    ],
    "location": [
      [{ "param": "post_type", "operator": "==", "value": "location" }]
    ],
    "menu_order": 0,
    "position": "normal",
    "style": "default",
    "label_placement": "top",
    "instruction_placement": "label",
    "hide_on_screen": "",
    "active": true,
    "description": "Post Title = the location's name (e.g. \"Studio B\", \"Room 204\", \"Chestnut Hall 307D\")."
  },
  {
    "key": "group_catp_teacher",
    "title": "Teacher Details",
    "fields": [
      {
        "key": "field_catp_teacher_title",
        "label": "Title",
        "name": "teacher_title",
        "type": "text",
        "instructions": "e.g. \"Professor\", \"Instructor\", \"Business & Technology Division Dean\". Matches `teacher.title`.",
        "required": 0,
        "conditional_logic": 0,
        "wrapper": { "width": "", "class": "", "id": "" },
        "default_value": "",
        "placeholder": "Instructor",
        "prepend": "",
        "append": "",
        "maxlength": ""
      },
      {
        "key": "field_catp_teacher_office_location",
        "label": "Office",
        "name": "teacher_office_location",
        "type": "post_object",
        "instructions": "Pick from Locations (use a location with Type = \"Office\"). Matches `teacher.office_location_id`. Optional — leave blank if not entered yet.",
        "required": 0,
        "conditional_logic": 0,
        "wrapper": { "width": "", "class": "", "id": "" },
        "post_type": ["location"],
        "taxonomy": [],
        "allow_null": 1,
        "multiple": 0,
        "return_format": "id",
        "ui": 1
      }
    ],
    "location": [
      [{ "param": "post_type", "operator": "==", "value": "teacher" }]
    ],
    "menu_order": 0,
    "position": "normal",
    "style": "default",
    "label_placement": "top",
    "instruction_placement": "label",
    "hide_on_screen": "",
    "active": true,
    "description": "Post Title = the teacher's name."
  },
  {
    "key": "group_catp_subject",
    "title": "Class / Subject Details",
    "fields": [
      {
        "key": "field_catp_subject_teachers",
        "label": "Teachers",
        "name": "subject_teachers",
        "type": "post_object",
        "instructions": "Every teacher who teaches this class — a class can have more than one (e.g. Advertising Design has 3). Matches the `subject_teacher` bridge table in database/schema.sql, not a single `teacher_id` column.",
        "required": 1,
        "conditional_logic": 0,
        "wrapper": { "width": "", "class": "", "id": "" },
        "post_type": ["teacher"],
        "taxonomy": [],
        "allow_null": 0,
        "multiple": 1,
        "return_format": "id",
        "ui": 1
      },
      {
        "key": "field_catp_subject_location",
        "label": "Classroom",
        "name": "subject_location",
        "type": "post_object",
        "instructions": "The classroom assigned to this class. Matches `subject.location_id` in database/schema.sql. A peer tutor assigned to this subject inherits this classroom automatically — nothing extra to fill in for them.",
        "required": 1,
        "conditional_logic": 0,
        "wrapper": { "width": "", "class": "", "id": "" },
        "post_type": ["location"],
        "taxonomy": [],
        "allow_null": 0,
        "multiple": 0,
        "return_format": "id",
        "ui": 1
      }
    ],
    "location": [
      [{ "param": "post_type", "operator": "==", "value": "subject" }]
    ],
    "menu_order": 0,
    "position": "normal",
    "style": "default",
    "label_placement": "top",
    "instruction_placement": "label",
    "hide_on_screen": "",
    "active": true,
    "description": "Post Title = the class/subject name (Advertising Design, Web Design, Photography, or Digital Video). This CPT is informational only — it feeds the My Program directory and Peer Tutor subject matching (each teacher's classroom lookup, and a peer tutor's inherited classroom, both come from here). Tutoring itself no longer uses this catalog at all — it's a single external link, see the App Settings group below."
  },
  {
    "key": "group_catp_product",
    "title": "Product Details",
    "fields": [
      {
        "key": "field_catp_product_size",
        "label": "Size",
        "name": "product_size",
        "type": "text",
        "instructions": "e.g. 8x10, 11x14. Leave blank for products with no size (e.g. Pullover). Matches `product.size`.",
        "required": 0,
        "conditional_logic": 0,
        "wrapper": { "width": "", "class": "", "id": "" },
        "default_value": "",
        "placeholder": "8x10",
        "prepend": "",
        "append": "",
        "maxlength": ""
      },
      {
        "key": "field_catp_product_price",
        "label": "Price",
        "name": "product_price",
        "type": "number",
        "instructions": "USD. Matches `product.price`.",
        "required": 1,
        "conditional_logic": 0,
        "wrapper": { "width": "", "class": "", "id": "" },
        "default_value": "",
        "placeholder": "0.50",
        "prepend": "$",
        "append": "",
        "min": 0,
        "max": "",
        "step": "0.01"
      },
      {
        "key": "field_catp_product_category",
        "label": "Category",
        "name": "product_category",
        "type": "select",
        "instructions": "Matches the `product.category` ENUM in database/schema.sql — do not add new options here without also updating the schema and the Goods form.",
        "required": 1,
        "conditional_logic": 0,
        "wrapper": { "width": "", "class": "", "id": "" },
        "choices": {
          "print_material": "Print Material",
          "merch": "Merch"
        },
        "default_value": "print_material",
        "allow_null": 0,
        "multiple": 0,
        "ui": 1,
        "return_format": "value"
      }
    ],
    "location": [
      [{ "param": "post_type", "operator": "==", "value": "product" }]
    ],
    "menu_order": 0,
    "position": "normal",
    "style": "default",
    "label_placement": "top",
    "instruction_placement": "label",
    "hide_on_screen": "",
    "active": true,
    "description": "Post Title = the product's name (e.g. \"Photo paper\", \"Pullover\")."
  },
  {
    "key": "group_catp_event",
    "title": "Event Details",
    "fields": [
      {
        "key": "field_catp_event_date",
        "label": "Event Date",
        "name": "event_date",
        "type": "date_picker",
        "instructions": "Matches `event.date` in database/schema.sql.",
        "required": 1,
        "conditional_logic": 0,
        "wrapper": { "width": "", "class": "", "id": "" },
        "display_format": "F j, Y",
        "return_format": "Y-m-d",
        "first_day": 0
      }
    ],
    "location": [
      [{ "param": "post_type", "operator": "==", "value": "event" }]
    ],
    "menu_order": 0,
    "position": "normal",
    "style": "default",
    "label_placement": "top",
    "instruction_placement": "label",
    "hide_on_screen": "",
    "active": true,
    "description": "Post Title = the event's name. Post Content (the normal WordPress editor below the title) = the event's description — no separate ACF field needed for that."
  },
  {
    "key": "group_catp_photographer",
    "title": "Photographer Details",
    "fields": [
      {
        "key": "field_catp_photographer_school_email",
        "label": "Student School Email",
        "name": "photographer_school_email",
        "type": "text",
        "instructions": "The school email of the student who holds the photographer role. Matches `photographer.school_email` in database/schema.sql. Set the Post Title to this same email.",
        "required": 1,
        "conditional_logic": 0,
        "wrapper": { "width": "", "class": "", "id": "" },
        "default_value": "",
        "placeholder": "jdoe0001@kctcs.edu",
        "prepend": "",
        "append": "",
        "maxlength": ""
      }
    ],
    "location": [
      [{ "param": "post_type", "operator": "==", "value": "photographer" }]
    ],
    "menu_order": 0,
    "position": "normal",
    "style": "default",
    "label_placement": "top",
    "instruction_placement": "label",
    "hide_on_screen": "",
    "active": true,
    "description": "Not every student is a photographer — only create a post here for students who hold that role. How a student earns this role is still an open decision (see README)."
  },
  {
    "key": "group_catp_peer_tutor",
    "title": "Peer Tutor Details",
    "fields": [
      {
        "key": "field_catp_peer_tutor_school_email",
        "label": "Student School Email",
        "name": "peer_tutor_school_email",
        "type": "text",
        "instructions": "The tutor's school email. Matches `peer_tutor.school_email` in database/schema.sql.",
        "required": 1,
        "conditional_logic": 0,
        "wrapper": { "width": "", "class": "", "id": "" },
        "default_value": "",
        "placeholder": "jdoe0001@kctcs.edu",
        "prepend": "",
        "append": "",
        "maxlength": ""
      },
      {
        "key": "field_catp_peer_tutor_subject",
        "label": "Subject",
        "name": "peer_tutor_subject",
        "type": "post_object",
        "instructions": "The subject this student tutors. Matches `peer_tutor.subject_id`. The classroom shown for this tutor comes from the subject's own Classroom field — don't set one here separately.",
        "required": 1,
        "conditional_logic": 0,
        "wrapper": { "width": "", "class": "", "id": "" },
        "post_type": ["subject"],
        "taxonomy": [],
        "allow_null": 0,
        "multiple": 0,
        "return_format": "id",
        "ui": 1
      },
      {
        "key": "field_catp_peer_tutor_availability",
        "label": "Availability",
        "name": "peer_tutor_availability",
        "type": "text",
        "instructions": "Free text, e.g. \"Mon/Wed 2-4pm\". Matches `peer_tutor.availability`.",
        "required": 1,
        "conditional_logic": 0,
        "wrapper": { "width": "", "class": "", "id": "" },
        "default_value": "",
        "placeholder": "Mon/Wed 2-4pm",
        "prepend": "",
        "append": "",
        "maxlength": ""
      }
    ],
    "location": [
      [{ "param": "post_type", "operator": "==", "value": "peer_tutor" }]
    ],
    "menu_order": 0,
    "position": "normal",
    "style": "default",
    "label_placement": "top",
    "instruction_placement": "label",
    "hide_on_screen": "",
    "active": true,
    "description": "Post Title = the tutor's display name, typed in by the admin/teacher (the Volunteer Form submission that started this only carries the student's school email, never a name). Only create a post here once the request has been reviewed and approved — a post existing here IS the approval; there's no separate status field."
  },
  {
    "key": "group_catp_board_post",
    "title": "Board Post Details",
    "fields": [
      {
        "key": "field_catp_board_post_email",
        "label": "Student School Email (internal only — never display)",
        "name": "board_post_email",
        "type": "text",
        "instructions": "Captured automatically from the submission for moderation contact ONLY (e.g. following up on a rejected post). NEVER surface this field in any public template. Matches `board_post.school_email`.",
        "required": 1,
        "conditional_logic": 0,
        "wrapper": { "width": "", "class": "", "id": "" },
        "default_value": "",
        "placeholder": "jdoe0001@kctcs.edu",
        "prepend": "",
        "append": "",
        "maxlength": ""
      },
      {
        "key": "field_catp_board_post_image",
        "label": "Image",
        "name": "board_post_image",
        "type": "image",
        "instructions": "The photo/artwork for the gallery grid. Matches `board_post.image_url`.",
        "required": 1,
        "conditional_logic": 0,
        "wrapper": { "width": "", "class": "", "id": "" },
        "return_format": "url",
        "preview_size": "medium",
        "library": "all"
      },
      {
        "key": "field_catp_board_post_display_name",
        "label": "Display Name (optional)",
        "name": "board_post_display_name",
        "type": "text",
        "instructions": "Shown publicly if filled in. Leave blank and the front end shows \"Anonymous\" instead. This is the one place in the whole app a real name is ever shown. Matches `board_post.display_name`.",
        "required": 0,
        "conditional_logic": 0,
        "wrapper": { "width": "", "class": "", "id": "" },
        "default_value": "",
        "placeholder": "",
        "prepend": "",
        "append": "",
        "maxlength": ""
      },
      {
        "key": "field_catp_board_post_date",
        "label": "Date",
        "name": "board_post_date",
        "type": "date_picker",
        "instructions": "Matches `board_post.date`.",
        "required": 1,
        "conditional_logic": 0,
        "wrapper": { "width": "", "class": "", "id": "" },
        "display_format": "F j, Y",
        "return_format": "Y-m-d",
        "first_day": 0
      }
    ],
    "location": [
      [{ "param": "post_type", "operator": "==", "value": "board_post" }]
    ],
    "menu_order": 0,
    "position": "normal",
    "style": "default",
    "label_placement": "top",
    "instruction_placement": "label",
    "hide_on_screen": "",
    "active": true,
    "description": "Post Title = the post's title, submitted by the student. A submission arrives here as a Draft (via Forminator's Post Creation feature) — publishing the post IS the approval; there's no separate status field to also remember to set. Approval bar is deliberately low: reject/leave as Draft only if discriminatory or disrespectful."
  },
  {
    "key": "group_catp_app_setting",
    "title": "App Setting Details",
    "fields": [
      {
        "key": "field_catp_app_setting_tutoring_url",
        "label": "Tutoring External URL",
        "name": "tutoring_external_url",
        "type": "url",
        "instructions": "The program's existing \"Request Tutoring\" page, which already routes to the correct Microsoft Bookings link. The Tutoring tab in the app is just a button that opens this URL — no form, no calendar, no data model.",
        "required": 1,
        "conditional_logic": 0,
        "wrapper": { "width": "", "class": "", "id": "" },
        "default_value": "",
        "placeholder": "https://..."
      }
    ],
    "location": [
      [{ "param": "post_type", "operator": "==", "value": "app_setting" }]
    ],
    "menu_order": 0,
    "position": "normal",
    "style": "default",
    "label_placement": "top",
    "instruction_placement": "label",
    "hide_on_screen": "",
    "active": true,
    "description": "This is a free-tier stand-in for an ACF Options Page (Options Pages are ACF PRO-only). `app_setting` is a singleton CPT — create exactly ONE post here (e.g. titled \"App Settings\") and never a second one. A specific-Page-ID location rule was considered instead, but a dedicated CPT avoids depending on a page ID that doesn't exist until someone creates that page in this specific WordPress install."
  }
]
JSON
, true );
}
