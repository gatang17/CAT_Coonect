# CATP Connect — Handoff guide (start here)

You're picking up a WordPress project that has its **data layer built and installed** but **no content entered and no pages designed yet**. This file tells you exactly where things stand and what to do first. The full map of the project is `README.md`; read this first, then that.

Last updated: September 2026 (after the starter catalog and page skeleton were loaded on the live site).

## What exists today

**In this repo**

| Path | What it is |
|---|---|
| `README.md` | The project map: every screen, every data type, every decision and why. |
| `database/schema.sql` | Reference model of the data. Nothing runs it — it's the "correct shape" to check against. |
| `wordpress/catp-connect/` | A WordPress plugin. Registers the 9 custom post types, loads the ACF field groups (`acf-field-groups.json`), provides the `[catp_tutoring_button]` shortcode, and a **Setup Tools** page with two one-click actions (see below). Ships no CSS and renders no markup. |
| `wordpress/catp-app.css` | The whole look, in one file you paste into **Appearance → Customize → Additional CSS**. Tokens at the top, then the navigation shell, the tab styling and every reusable component class. Deliberately outside the plugin so a colour change never means editing code. |

**On the live site** (Hostinger, `catconnect.gatangdesigns.io`) — as of this handoff:

| Done | Not yet done |
|---|---|
| Advanced Custom Fields (free) installed | Stackable (blocks) — install it |
| CATP Connect plugin **v0.2.0** installed | Forminator, Booking Calendar, WP Inventory Manager — install them |
| Meta Field Block installed | The app-conversion / push-notifications plugin (undecided) |
| Blocksy theme active | Classrooms/studios, the Classroom of each subject, the Tutoring URL, Large board size/price — nobody had these yet |
| **Starter catalog loaded**: 6 offices, 11 teachers, 4 subjects, 6 products | Converting the page sections into Stackable tabs, adding the Meta Field Blocks, building the forms |
| **Page skeleton created**: Home / Resources / Get Involved / Help / More, each with a page header and the fixed navigation; Home is the front page, "App Navigation" menu exists | |

So the data layer and the page structure exist; what's left is content that wasn't known yet, and the front-end build.

## Your first session, in order

1. **Get an admin account** from your supervisor (a user for you — don't reuse someone else's password).
2. **Look around first.** The front page should be "Home"; the header menu should show Home · Resources · Get Involved · More (if it doesn't, Appearance → Menus → assign "App Navigation" to Blocksy's header location). Locations / Teachers / Classes / Products already have entries. App Settings → Setup Tools is where those came from — both buttons are safe to press again; they skip what already exists.
3. **Install and activate:** Stackable (free), Forminator, Booking Calendar (by wpdevelop), WP Inventory Manager. All free, all from Plugins → Add New.
4. *(The plugin, the theme, the starter catalog and the page skeleton are already done — skip ahead.)*
5. *(Same.)*
6. **Fill in what the setup couldn't know** (Setup Tools lists this too):
   - Add the **classrooms/studios** as Locations (Type = "Classroom" or "Studio") — nobody had the room list yet.
   - Open each of the 4 Classes / Subjects and pick its **Classroom** (the field is required, so the screen will insist).
   - Confirm the **Large board** size and price (seeded as placeholders: 15x20, $3.00).
   - Create **one** App Settings post (title "App Settings") and paste the **Tutoring External URL** — the program's existing "Request Tutoring" page. Never create a second App Settings post.
7. **Paste the stylesheet.** Copy all of `wordpress/catp-app.css` into **Appearance → Customize → Additional CSS**. Nothing is styled until you do — the plugin loads no CSS. The look is a wireframe on purpose (neutral borders, no brand colors); design on top of it by editing the tokens at the top of that file, plus Blocksy's Customizer for global typography and palette. Whatever you change on the site, paste back into the repo copy so the two don't drift. See README → "How the styling is organised".
8. **Check the navigation shell.** "Create page skeleton" already builds it: a synced pattern called **App Shell Nav** (Appearance → Patterns) holding the five links with their icons, placed on every page. The stylesheet makes it a fixed rail on the left at ≥900px and a fixed bar along the bottom below that — one markup, one media query. Edit the pattern once and all pages follow. Each page also gets a `catp-page-header` (wordmark, title, one-line subtitle).

9. **The tabs are already there.** The skeleton wraps each page's Groups in `catp-tabs` and the plugin builds the tab strip from their headings — no conversion step, and no Stackable needed for this. Rename a tab by renaming its heading; reorder by dragging a Group. `/resources/#tutoring` opens a tab directly. If you would rather use a **Stackable → Tabs** block, drop it on the same wrapper and it takes over; the look is the same either way. Where a note says "Meta Field Block", add that block inside the post list and pick the named ACF field.
10. **Build the forms** in the tabs whose notes name them (details in README → App structure):
   - Volunteer Form, Photo Form, Submit Work → **Forminator**. Every one has a school-email field: add a **pattern/regex validation** that only accepts `@kctcs.edu` addresses — that's the whole identity system, there is no login.
   - Board → **Forminator with Post Creation**: post type = Board Posts, status = **Draft**, and map the form fields to the ACF fields `board_post_email`, `board_post_image`, `board_post_display_name`, `board_post_date`. An admin then approves by publishing.
   - Studio → **Booking Calendar**: 4 fixed slots (08:00–10:00, 10:00–12:00, 13:00–15:00, 15:00–17:00), gear checklist, school email as contact; block times taken by regular classes.
   - Borrow → **WP Inventory Manager**: add the 10 iPads there (it owns the equipment catalog — there is deliberately no ACF post type for equipment).
11. **One thing to verify early:** `subject_teachers` is a multi-value field. Check that the free Meta Field Block renders all three Advertising Design teachers. If it only handles single values, show the relation from the teacher side instead.

## Rules that must not break

These come from the program's requirements, not from taste:

- **No emails on the front end, ever.** Photographer post titles, `board_post_email`, `peer_tutor_school_email` are for staff only. The plugin already keeps `photographer` and `app_setting` out of the REST API; the rest is template discipline — don't add those fields to any page.
- **No names, with two deliberate exceptions:** a peer tutor's display name (typed by staff after approval) and a Board post's optional display name ("Anonymous" if blank).
- **App Settings is a singleton.** One post. The shortcode reads the first one it finds.
- **Peer Tutors and Board Posts are approval lists.** A Peer Tutor post existing = approved (staff create it by hand after reviewing the Volunteer Form request). A Board post published = approved (the form creates it as a Draft).
- **Tutoring is only a link. Goods is only a calculator.** Neither captures anything. Don't add forms to them.

## How to change things

- **A field** (add/rename/reorder): edit `wordpress/catp-connect/acf-field-groups.json` (it's ACF's own export format), re-zip, re-upload with "Replace current with uploaded". The groups are read-only in the ACF admin on purpose — the repo is the source of truth.
- **Getting that JSON out of a running site**: App Settings → Setup Tools → *Download acf-field-groups.json*. ACF's own Tools → Export cannot list these groups, because it only knows about field groups stored in the database and these are registered from the plugin. The button is there so that design does not cost you the file.
- **A post type**: edit `catp_connect_post_types()` in `wordpress/catp-connect/catp-connect.php`.
- **The starter catalog or the page skeleton**: `wordpress/catp-connect/includes/setup-tools.php`. Re-running "Create page skeleton" with the **Overwrite** checkbox replaces the five pages' content with a fresh skeleton — handy right after a plugin update, destructive once someone has designed those pages, so leave it unchecked by default.
- **The look**: `wordpress/catp-app.css` (tokens at the top), then re-paste it into Customizer → Additional CSS.
- **The Goods prices**: edit the **Products**, not the code — the calculator reads them. Its markup is `includes/goods-calculator.php` and its arithmetic `assets/catp-goods.js`.
- **What is on the Drop Zone**: publish or unpublish **Board Posts**. Published = approved. The gallery and its lightbox are `includes/board.php` + `assets/catp-board.js`, and they never read `board_post_email`.
- **Who appears in the directory, and in which group**: edit the **Teachers** and the **Classes / Subjects**. A teacher listed in a subject's `subject_teachers` shows as Faculty; one listed in none shows as Administration with their `teacher_title`. Markup in `includes/directory.php`, search in `assets/catp-directory.js`.
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
