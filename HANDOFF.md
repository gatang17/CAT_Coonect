# CATP Connect — Handoff guide (start here)

You're picking up a WordPress project that has its **data layer built and installed** but **no content entered and no pages designed yet**. This file tells you exactly where things stand and what to do first. The full map of the project is `README.md`; read this first, then that.

Last updated: September 2026.

## What exists today

**In this repo**

| Path | What it is |
|---|---|
| `README.md` | The project map: every screen, every data type, every decision and why. |
| `database/schema.sql` | Reference model of the data. Nothing runs it — it's the "correct shape" to check against. |
| `wordpress/catp-connect/` | A WordPress plugin. Registers the 9 custom post types, loads the ACF field groups (`acf-field-groups.json`), provides the `[catp_tutoring_button]` shortcode, and a **Setup Tools** page with two one-click actions (see below). |

**On the live site** (Hostinger, `catconnect.gatangdesigns.io`) — as of this handoff:

| Installed | Not yet installed |
|---|---|
| Advanced Custom Fields (free) | Blocksy (theme) |
| CATP Connect plugin **v0.1.0** — you need to update it to **v0.2.0** (step 2 below) | Stackable (blocks) |
| Meta Field Block | Forminator, Booking Calendar, WP Inventory Manager |
| | The app-conversion / push-notifications plugin (undecided) |

No catalog content has been entered and no pages exist yet. That's intentional — it was left for you, and most of it is one click.

## Your first session, in order

1. **Get an admin account** from your supervisor (a user for you — don't reuse someone else's password).
2. **Update the plugin to v0.2.0.** From the repo: `cd wordpress && zip -r catp-connect.zip catp-connect`. In wp-admin: Plugins → Add New → Upload Plugin → choose the zip → WordPress will say a version already exists → **Replace current with uploaded**. Confirm "App Settings → Setup Tools" now appears in the menu.
3. **Install and activate the Blocksy theme** (Appearance → Themes → Add New). Do this *before* step 5 so the navigation menu the skeleton creates can attach to Blocksy's header.
4. **Install and activate:** Stackable (free), Forminator, Booking Calendar (by wpdevelop), WP Inventory Manager. All free, all from Plugins → Add New.
5. **App Settings → Setup Tools → "Load starter catalog"**, then **"Create page skeleton"**. Both are safe to press again later — they skip anything that already exists. After this you have: 6 offices, 11 teachers with titles/offices, the 4 subjects linked to their teachers, 6 products with prices; and the pages Home / Resources / Get Involved / More, with Home as the front page and an "App Navigation" menu.
6. **Fill in what the seeder couldn't know** (it tells you this on screen too):
   - Add the **classrooms/studios** as Locations (Type = "Classroom" or "Studio") — nobody had the room list yet.
   - Open each of the 4 Classes / Subjects and pick its **Classroom** (the field is required, so the screen will insist).
   - Confirm the **Large board** size and price (seeded as placeholders: 15x20, $3.00).
   - Create **one** App Settings post (title "App Settings") and paste the **Tutoring External URL** — the program's existing "Request Tutoring" page. Never create a second App Settings post.
7. **Turn the page sections into tabs.** Each page has a "Setup note" at the top and one Group block per tab. Add a **Stackable → Tabs** block with the tab labels, move each Group into its tab, delete the setup note. Where a note says "add a Meta Field Block for `some_field`", add that block inside the post list and pick that ACF field.
8. **Build the forms** in the tabs whose notes name them (details in README → App structure):
   - Volunteer Form, Photo Form, Submit Work → **Forminator**. Every one has a school-email field: add a **pattern/regex validation** that only accepts `@kctcs.edu` addresses — that's the whole identity system, there is no login.
   - Board → **Forminator with Post Creation**: post type = Board Posts, status = **Draft**, and map the form fields to the ACF fields `board_post_email`, `board_post_image`, `board_post_display_name`, `board_post_date`. An admin then approves by publishing.
   - Studio → **Booking Calendar**: 4 fixed slots (08:00–10:00, 10:00–12:00, 13:00–15:00, 15:00–17:00), gear checklist, school email as contact; block times taken by regular classes.
   - Borrow → **WP Inventory Manager**: add the 10 iPads there (it owns the equipment catalog — there is deliberately no ACF post type for equipment).
9. **One thing to verify early:** `subject_teachers` is a multi-value field. Check that the free Meta Field Block renders all three Advertising Design teachers. If it only handles single values, show the relation from the teacher side instead.

## Rules that must not break

These come from the program's requirements, not from taste:

- **No emails on the front end, ever.** Photographer post titles, `board_post_email`, `peer_tutor_school_email` are for staff only. The plugin already keeps `photographer` and `app_setting` out of the REST API; the rest is template discipline — don't add those fields to any page.
- **No names, with two deliberate exceptions:** a peer tutor's display name (typed by staff after approval) and a Board post's optional display name ("Anonymous" if blank).
- **App Settings is a singleton.** One post. The shortcode reads the first one it finds.
- **Peer Tutors and Board Posts are approval lists.** A Peer Tutor post existing = approved (staff create it by hand after reviewing the Volunteer Form request). A Board post published = approved (the form creates it as a Draft).
- **Tutoring is only a link. Goods is only a calculator.** Neither captures anything. Don't add forms to them.

## How to change things

- **A field** (add/rename/reorder): edit `wordpress/catp-connect/acf-field-groups.json` (it's ACF's own export format), re-zip, re-upload with "Replace current with uploaded". The groups are read-only in the ACF admin on purpose — the repo is the source of truth.
- **A post type**: edit `catp_connect_post_types()` in `wordpress/catp-connect/catp-connect.php`.
- **The starter catalog or the page skeleton**: `wordpress/catp-connect/includes/setup-tools.php`.
- **The data model itself**: update `database/schema.sql` too, so the reference stays honest.
- Commit to the repo. Don't let the live site drift from what's in git.

## Decisions already made (so you don't re-litigate them)

| Decision | Why |
|---|---|
| ACF (free) rather than Pods | Performance is a non-issue at this size; Forminator's Post Creation (needed for Board) is better proven with ACF. |
| Stackable for tabs/layout | Supervisor's call. Free tier covers what the app needs; Kadence would have too. |
| Meta Field Block + core Query Loop to show field values | Every block library's "dynamic content" (Kadence Pro, Stackable Premium, Blocksy Pro) and ACF's Block Bindings are paid. This route is free. |
| A plugin instead of Custom Post Type UI + manual import | Versioned, one upload, idempotent setup buttons. |
| Tutoring = external link (Microsoft Bookings) | The program already has a working request page. |
| Goods = calculator only | The supply-request flow was cut. |
| No custom taxonomies | Fixed-choice values are ACF select/text fields, mirroring the schema's ENUM/VARCHAR columns. |
| Identifier = school email, validated by a domain pattern | No accounts, no roster lookup, no paid verification. |

## Still undecided (ask your supervisor)

- Which plugin wraps the site as an app with push notifications (AppPresser / MobiLoud / AppMySite).
- Large board exact size and dry-mount-tissue pricing; whether an envelope is required per submission type.
- How a student becomes a "photographer" (no review step is described — unlike peer tutoring).
- Whether the Board image is an ACF Image upload (current) or a pasted link like Submit Work.
- The list of classrooms/studios (needed for step 6).

## Known cosmetic note

With a plain block theme the Tutoring button rendered by the shortcode uses the `wp-element-button` class and may sit tight against the paragraph above it. Under Blocksy, inside a Stackable tab, style it like any other button — or pass `class="..."` in the shortcode.
