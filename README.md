# CATP Connect

Companion app for the Communication Arts Technology Program (CATP). It's built as a **WordPress site** — a theme, a handful of plugins, and an app-conversion layer that wraps the site as a mobile app with push notifications. There is no separate backend service and no custom database server to stand up: WordPress *is* the backend.

No login or user accounts exist for any user type. No greetings by name, no avatars, no personalization. The only identifier used anywhere in the app is a **school email address** (`@kctcs.edu`), validated with a simple domain check — a plain pattern rule, not a roster lookup or a paid verification service.

**New to the project? Read `HANDOFF.md` first** — it says what's installed, what's not, and what to do in your first session.

This README is the map of the whole project: what the app looks like, how its data is split between "things an admin sets up once" and "things students submit," and how to actually configure that in WordPress.

## How this repo is organized

| Path | What it is | Who uses it |
|---|---|---|
| `database/schema.sql` | A normalized relational model of the whole app — every entity and how they relate. | **Reference only.** Nothing in WordPress runs this file. It exists so the person building the site (you) has one place that defines the "correct" shape of the data, independent of which plugin ends up storing it. When something in ACF or a plugin setting seems ambiguous, this is the source of truth to check it against. |
| `wordpress/catp-connect/` | A small WordPress plugin: registers the app's nine custom post types in code, loads its ACF field groups, and adds a **Setup Tools** page (App Settings → Setup Tools) with two one-click actions: load the known starter catalog, and create the four app pages with one section per tab. | **Whoever sets up the site.** Zip the folder, upload it under Plugins → Add New → Upload Plugin, activate. |
| `wordpress/catp-connect/acf-field-groups.json` | The [Advanced Custom Fields](https://www.advancedcustomfields.com/) field groups, in ACF's own export format. The plugin loads this file automatically; it is also importable by hand via Custom Fields → Tools. | **The person filling in content** reads it (via the ACF screens it produces) to know exactly which fields to fill for each catalog item. **Whoever maintains the site** edits it here, in the repo, not in the ACF UI. |

Everything below explains how those pieces fit together and what actually needs to be built in WordPress.

## The core split: catalog data vs. captured data

Almost every question about "where does this go" comes down to one distinction:

- **Catalog data** — teachers, locations, classes, products, events, the approved peer-tutor list. This is set up *once* by an admin, rarely changes, and is purely **informational** on the front end (students browse it, they never edit it). It lives in WordPress as **custom post types with ACF fields**.
- **Captured data** — bookings, requests, orders, submissions. This is what students actually *do* in the app: book a slot, request equipment, place an order, submit work. It's captured through **forms and booking plugins**, not typed into wp-admin by hand.

Concretely, from the schema:

| Catalog (admin-entered, backend, informational) | Captured (student-facing, front end) |
|---|---|
| `location`, `teacher`, `subject`, `subject_teacher`, `product`, `event`, `studio_gear`, `photographer` (role flag), `peer_tutor` (**approved** directory only), `equipment` (catalog fields — see note below), `app_setting` (a singleton — see the Tutoring section below) | `studio_booking`, `equipment_request`, `volunteer_request`, `photo_session_request`, `submit_work`, `board_post` (a hybrid case — see its own section below) |

So when the spec says a teacher's name, their office, or a classroom shouldn't come from the front end — that's this split. Those are catalog rows an admin fills in ahead of time; the front end just displays them. A student never types a teacher's name into anything; they only ever *browse* what's already there.

One nuance worth calling out explicitly: **not everything "front end" goes through Forminator.** Looking at the plugins already chosen:

| Screen / action | Captured via | Notes |
|---|---|---|
| Tutoring | *Nothing — no capture at all.* | Just a button that opens an external URL. See the Tutoring section below. |
| Studio booking | **Booking Calendar (wpdevelop)** | Its own booking UI and storage — not a Forminator form. |
| Goods | *Nothing — no capture at all.* | A pure calculator: quantity selectors + a running total, computed client-side. Nothing is submitted anywhere. See the Goods section below. |
| Volunteer Form, Photo Form, Submit Work | **Forminator** | These are genuinely Forminator forms. |
| Borrow (equipment request/status) | **WP Inventory Manager** | Manages both the equipment catalog *and* live available/checked-out status itself. |
| Board | **Forminator, with its Post Creation feature** | Different from the other Forminator forms — see the Board section below. |

So "informational on the front end, not typed by a student" is the right mental model — just know that the actual capture mechanism (where one exists at all) is whichever plugin owns that screen (Booking Calendar, Forminator, or WP Inventory Manager), not Forminator across the board. Two screens — Tutoring and Goods — capture nothing at all.

### A note on `equipment`

`equipment` (the 10 iPads, etc.) is catalog data in the sense that a student never creates or edits an equipment row — but it isn't modeled as an ACF custom post type here, because **WP Inventory Manager already owns this catalog** (item number, color/type, and live available/checked-out status) as part of doing its job for the Borrow tab. Adding a parallel ACF post type for the same items would create two sources of truth that can drift apart. Manage equipment inside WP Inventory Manager directly; `equipment` in `database/schema.sql` documents its *shape* for reference, not a table you need to build by hand.

## App structure

**Home** — Notifications (shown above events, intentionally), and an events/news list. Event cards offer "Volunteer" (→ Volunteer Form, event pre-selected) and "Participate" (→ Submit Work).

**Resources** (tabs: Tutoring, Studio, Goods, Borrow, Photo Form)
- *Tutoring* — **not an in-app feature.** A single static link/button that opens an external URL (the program's existing "Request Tutoring" page, which already routes to the correct Microsoft Bookings link). No form, no calendar, no subject/teacher selection, no data model — see the Tutoring section under Setting This Up for the one field that makes the URL editable without touching code.
- *Studio* — calendar + 4 fixed time slots (08:00–10:00, 10:00–12:00, 13:00–15:00, 15:00–17:00) + a gear checklist. The school email doubles as the contact — there's no separate contact field. Must also block times already occupied by regular scheduled classes, not just other studio bookings. (Booking Calendar.)
- *Goods* — a pure price calculator for print materials and merch: quantity selectors + a running total (see pricing table below). **Not a request or reservation system** — nothing gets submitted; a student just sees what their order would cost.
- *Borrow* — real-time status of shared equipment (currently 10 iPads; may expand to laptops). Used inside a classroom, same-day return only, faculty-supervised — the app shows live status and captures the request; the physical handoff happens in person. (WP Inventory Manager.)
- *Photo Form* — a student requests a photo session (as model, or as photographer). Requires a school email even when the subject is a guest, since the requesting student is the responsible party.

**Get Involved** (tabs: Volunteer Form, Submit Work, Board)
- *Volunteer Form* — school email, request date, event (dropdown). Volunteer hours count toward practicum hours (most students don't know this — the app should surface it). Includes the "Become a Peer Tutor" request: subject + availability → goes to a teacher for review → **never automatic** — an admin manually promotes an approved request into the public peer-tutor directory (see the ACF section below).
- *Submit Work* — name, work type (Ad / Photo / Web), a link to a OneDrive folder (no file upload — students already store work there), with visible naming instructions. Optionally linked to an event.
- *Board* — a new, image-gallery-style tab (grid of thumbnails, not a text feed). A student submits an image + title + an optional display name. Requires school email to submit (used only for moderation contact, **never shown publicly**). If no display name is given, the front end shows "Anonymous" instead — otherwise, the name given *is* shown publicly. This is the one place in the whole app where a real name is displayed. An admin approves every post manually before it's visible; the approval bar is deliberately low — reject only if something is discriminatory or disrespectful. See the Board section under Setting This Up for how this is wired, since it works differently from the other two tabs here.

**More** (tabs: My Program, Preparation)
- *My Program* — searchable teacher/classroom directory. This is the `teacher` + `location` + `subject` catalog, read-only.
- *Preparation* — portfolio-readiness progress bar + a NOCTI exam-prep module (game-style, with a streak counter).

## Setting this up in WordPress

### 1. Install Advanced Custom Fields (free)

Plugins → Add New → search "Advanced Custom Fields" → install the free one (not "ACF PRO") → activate. Everything below depends on it.

### 2. Upload and activate the CATP Connect plugin

`wordpress/catp-connect/` in this repo is a small WordPress plugin. It registers all nine custom post types in code and loads the ACF field groups from the `acf-field-groups.json` it ships with — so there is nothing to click through in Custom Post Type UI and nothing to import by hand.

1. Zip the folder — from the repo root: `cd wordpress && zip -r catp-connect.zip catp-connect`.
2. Plugins → Add New → Upload Plugin → choose `catp-connect.zip` → Install Now → Activate.
3. The admin menu now shows Locations, Teachers, Classes / Subjects, Products, Events, Photographers, Peer Tutors, Board Posts and App Settings, and Custom Fields → Field Groups lists the nine groups.
4. **App Settings → Setup Tools** has two buttons, both safe to press repeatedly (they skip what already exists): **Load starter catalog** creates the faculty offices, the 11 teachers with titles/offices, the 4 subjects linked to their teachers, and the 6 products; **Create page skeleton** creates Home / Resources / Get Involved / More with one Group per tab (built from core blocks, ready to be converted into Stackable Tabs), sets Home as the front page and builds an "App Navigation" menu. Activate the theme first so the menu can attach to it. What the buttons can't know (classrooms, the Tutoring URL, Large board's real size) they list on screen as "still to do by hand". They appear there as read-only (loaded from the plugin) **on purpose**: to change a field, edit `acf-field-groups.json` in the repo and re-upload the plugin, so the configuration stays versioned instead of living only in one site's database.

| Post type slug | Label | Supports | Public / REST | Notes |
|---|---|---|---|---|
| `location` | Locations | Title | yes | Also used for teacher offices (Type = "Office"). |
| `teacher` | Teachers | Title | yes | |
| `subject` | Classes / Subjects | Title | yes | "Class" and "subject" are the same thing here. |
| `product` | Products | Title | yes | |
| `event` | Events | Title, Editor | yes | Editor = the event description. |
| `photographer` | Photographers | Title | **no** | Post titles are student emails — kept out of the front end and the REST API entirely. |
| `peer_tutor` | Peer Tutors | Title | yes | Public directory — only *approved* tutors. |
| `board_post` | Board Posts | Title, Editor, Thumbnail | yes | Created *by the Board form* (Forminator Post Creation), not by hand. |
| `app_setting` | App Settings | Title | **no** | A singleton — see the App Settings section below. |

"Public / REST = yes" is what lets WordPress's own Query Loop block list that type on the front end. The two private types never reach `/wp-json`.

This plugin was verified on a real WordPress 7.1 + ACF 6.8.9 install: all nine types register, all nine groups load with the expected field counts, and the fields round-trip (a subject with three teachers, a teacher with title + office, a product with price, the Tutoring URL via its shortcode).

**Manual alternative, if you'd rather not upload a plugin:** install the free Custom Post Type UI plugin, create the nine post types from the table above by hand, then Custom Fields → Tools → Import Field Groups → upload `wordpress/catp-connect/acf-field-groups.json`. Same end result, more clicking, and the configuration then exists only in that site's database.

### 3. Install Meta Field Block (to show ACF values on the front end)

Showing an ACF field's *value* on a page is a paid feature everywhere you'd expect it to be free: Kadence Pro, Stackable Premium, Blocksy Pro, and even ACF's own Block Bindings integration all require the paid tier. **Meta Field Block** (free, on wordpress.org) is the free route: a single block that prints one custom field, and it nests inside WordPress's own **Query Loop** block. So "list every subject with its classroom" is: a Query Loop (post type = Classes / Subjects) → inside it, the post title plus a Meta Field Block set to `subject_location`. No Pro anywhere in the stack.

One thing to check on the real site before building around it: `subject_teachers` is a *multiple* Post Object field (one subject, several teachers). Meta Field Block documents rendering Relationship/Post Object fields "as a Query Loop" — confirm the free tier does that for the three-teacher case. If it doesn't, show the relation from the teacher side instead (each teacher lists their subject, a single Post Object, which the free tier handles).

### 4. What to actually type into each one

This is the part meant for whoever is filling in content, not necessarily writing code:

**Locations** — Post Title = the room's name (e.g. "Studio B", "Room 204", "Chestnut Hall 307D"). One field, *Type*: free text like "Classroom", "Studio", or "Office".

**Teachers** — Post Title = the teacher's name. Two optional fields: *Title* (e.g. "Professor", "Instructor", "Business & Technology Division Dean") and *Office* (pick from Locations — use a location with Type = "Office").

Real faculty data to enter:

| Name | Title | Office |
|---|---|---|
| Terry W. Lutz | Business & Technology Division Dean, Professor | Chestnut Hall 307D |
| Mark Cable | Academic Program Coordinator, Instructor | Chestnut Hall 307F |
| Tyler Ewing | — | Chestnut Hall 307C |
| Rob Womack | — | Chestnut Hall 307C |
| Jesenia Avila-Ugalde | — | Chestnut Hall 307G |
| Jamarr Cox | — | Chestnut Hall 307C |
| April Fultz | — | Chestnut Hall 307H |
| Michael Stewart | — | Chestnut Hall 307B |
| Bryan Moberly | — | Chestnut Hall 307B |
| Ben Stansbury | — | Chestnut Hall 307G |
| Michael Fitzer | — | Chestnut Hall 307G |

**Classes / Subjects** — Post Title = the class name. The real values are **Advertising Design, Web Design, Photography, Digital Video** — just these four. Two fields: *Teachers* (pick **all** teachers who teach this class — this is a multi-select, since a class can have more than one teacher; e.g. Advertising Design has 3: Tyler Ewing, Rob Womack, Jesenia Avila-Ugalde) and *Classroom* (pick one, from Locations). The classroom lives **here**, on the class — not on any one teacher. That's also how a peer tutor ends up with a room: once a peer tutor is linked to a subject (see below), they automatically inherit that subject's classroom. Nothing extra to assign.

Suggested grouping from the real faculty data above: Advertising Design → Ewing, Womack, Avila-Ugalde; Web Design → Cox, Fultz; Photography → Stewart, Moberly; Digital Video → Stansbury, Fitzer.

> **Heads up:** this catalog is informational only — it feeds the "My Program" directory and lets other content (like a peer tutor's classroom) resolve correctly. It has nothing to do with Tutoring anymore, since that's now just a static external link (see below) with no subject/teacher selection of its own.

**Products** — Post Title = the product's name (e.g. "Photo paper", "Pullover"). Three fields: *Size* (optional — leave blank for things like a pullover that have no size), *Price* (USD), *Category* (Print Material or Merch — don't add new category choices without updating `database/schema.sql` and the Goods calculator to match). This is the *only* thing the Goods tab needs from wp-admin — the calculator just reads this list and does arithmetic on the front end; there's nothing to submit or moderate.

Current price list to enter:

| Product | Size | Price | Category |
|---|---|---|---|
| Photo paper | 8x10 | $0.50 | Print Material |
| Mount board | 11x14 | $1.50 | Print Material |
| Dry mount tissue | — | $0.50 | Print Material |
| Envelope | — | $0.50 | Print Material |
| Large board | ~15x20 or 17x20 (TBD) | $3.00–$3.50 | Print Material |
| Pullover | — | $13.00 | Merch |

(Large board's exact size, and whether dry mount tissue is priced differently for it, are still open — see Pending Decisions.)

**Events** — Post Title = event name, the normal content editor below it = description. One ACF field: *Event Date*.

**Photographers** — Not every student is a photographer, so only create a post here for students who hold that role (one field: *Student School Email* — also set the Post Title to the same email). How a student *earns* this role isn't decided yet (unlike peer tutoring, there's no described review step) — see Pending Decisions.

**Peer Tutors** — This is the **public-facing directory only**, i.e. tutors who have already been approved. It is deliberately separate from the raw "Become a Peer Tutor" request, which arrives as a Volunteer Form submission carrying only a school email, a subject, and availability — never a name (remember: no names are captured anywhere in the app *except* Board, see below). The flow is:

1. Student submits the peer-tutor request through the Volunteer Form (Forminator). This sits in Forminator's own entries as a pending request; it does **not** create anything here.
2. The subject's teacher reviews it. Never automatic.
3. Once approved, an admin manually creates **one post here**. Post Title = the tutor's display name (typed in now, by the admin — this is one of only two places a student's real name enters the system, and it comes from staff, not from the student's own submission). Fields: *Student School Email*, *Subject* (pick from Classes/Subjects — this is also where their classroom comes from, automatically), *Availability* (free text, e.g. "Mon/Wed 2-4pm").

A post existing here **is** the approval — there's no separate "status" toggle to also remember to flip.

**Board Posts** — Unlike every other post type above, you don't create these by hand — a student submission does, through **Forminator's Post Creation feature**: the Board form is configured to create a `board_post` post directly (as a Draft) instead of just logging a form entry. That's what makes "approve manually via ACF" work at all here — there's a real post for the admin to open, review, and act on.

- Post Title = the title the student gave it.
- *Student School Email (internal only)* — captured for moderation contact only. **Never** query or display this field in any front-end template.
- *Image* — the photo/artwork shown in the gallery grid.
- *Display Name (optional)* — shown publicly if the student filled it in; the front end shows "Anonymous" if it's blank. This is the second (and only other) place a real name can appear in the app.
- *Date*.

Approving a post is just **publishing it** — clicking Publish on the Draft the form created. There's no separate status field to flip; WordPress's own draft/publish state *is* the pending/approved state. Reject (or just leave as Draft) only for anything discriminatory or disrespectful — the bar is deliberately low otherwise.

**App Settings** — This is where the Tutoring external link lives. Create **exactly one** post here (e.g. titled "App Settings") and never a second one — it's a stand-in for an ACF Options Page, which is normally the natural place for a single global value like this, but Options Pages are ACF PRO-only. A dedicated singleton post type gets the same result for free, without depending on a specific Page's ID (which doesn't exist yet at JSON-authoring time). One field: *Tutoring External URL* — the program's existing "Request Tutoring" page, which already routes to the correct Microsoft Bookings link. The Tutoring tab in the app is just a button that opens whatever URL is in this field — the plugin provides it as a shortcode: paste `[catp_tutoring_button text="Request Tutoring"]` into the Tutoring tab and it renders that button (it renders nothing at all until the URL has been filled in).

### Why no custom taxonomies?

Everything that looks like it could be a WordPress taxonomy (location type, product category, work type on Submit Work, session type on the Photo Form) is instead either a plain text field or an ACF Select field with a fixed set of choices. That mirrors how `database/schema.sql` models the same values — a free-text column or an `ENUM` — and it's simpler to maintain than real taxonomy terms for what are really just small, fixed lists. If a value needs to support hierarchy or WordPress-native archive/filter pages later, it can be promoted to a real taxonomy then.

## Product pricing — open questions

- Exact large-board size (currently ~15x20 or 17x20 — needs confirming).
- Whether dry-mount tissue is priced differently for the large board.
- Whether an envelope is required for *every* submission type, or only some.

## Database schema

See `database/schema.sql` for the full relational model — every table, column, and foreign key, with comments explaining the reasoning behind each design decision (e.g. why `photographer` and `peer_tutor` are modeled as subtypes of `student` rather than independent entities, why the classroom lives on `subject` rather than `teacher`, why Tutoring and the Goods request flow have no tables at all anymore). It's been validated by actually running it against a live MariaDB instance — every table, foreign key, and constraint (including the `@kctcs.edu` domain check) behaves as designed — but again, it's a reference model, not something WordPress executes directly.

## Tools decided so far

| Feature | Tool |
|---|---|
| Theme | Blocksy |
| Tabs / layout | Stackable (free). Its free tier covers what the app needs — tabs and listing post types; Kadence Blocks would have too, Stackable was the supervisor's call |
| Volunteer form, Photo form, Submit Work | Forminator |
| Board | Forminator, using its Post Creation feature to write into the `board_post` CPT |
| Studio booking | Booking Calendar (wpdevelop) |
| Tutoring | No plugin — a static external link (Microsoft Bookings, via the program's existing "Request Tutoring" page), stored in one ACF field on a singleton `app_setting` post and rendered by the `[catp_tutoring_button]` shortcode |
| Goods | No plugin — a pure calculator reading the `product` catalog; nothing is captured |
| Borrow (equipment lending) | WP Inventory Manager |
| Catalog data (locations, teachers, classes, products, events, photographer role, approved peer tutors, app settings) | The `catp-connect` plugin (this repo's `wordpress/catp-connect/`) + Advanced Custom Fields (free) |
| Showing ACF field values on the front end | Meta Field Block (free), nested in WordPress's core Query Loop block — see setup step 3 |

## Pending decisions

- Which WordPress-to-app plugin for push notifications (AppPresser / MobiLoud / AppMySite).
- Exact large-board size and its dry-mount-tissue pricing.
- Whether an envelope is required for every submission type.
- How a student becomes a "photographer" — no review/approval step has been described for this role, unlike peer tutoring.
- Confirm Meta Field Block's free tier renders the multi-teacher `subject_teachers` field; if not, show that relation from the teacher side (see setup step 3).
- Whether `board_post.image_url` should be a native ACF Image upload (what's currently modeled) or, to stay consistent with Submit Work's "paste a link, no upload" pattern, a pasted image URL instead.
