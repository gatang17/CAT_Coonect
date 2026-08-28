# CATP Connect

Companion app for the Communication Arts Technology Program (CATP). It's built as a **WordPress site** — a theme, a handful of plugins, and an app-conversion layer that wraps the site as a mobile app with push notifications. There is no separate backend service and no custom database server to stand up: WordPress *is* the backend.

No login or user accounts exist for any user type. No greetings by name, no avatars, no personalization. The only identifier used anywhere in the app is a **student ID**.

This README is the map of the whole project: what the app looks like, how its data is split between "things an admin sets up once" and "things students submit," and how to actually configure that in WordPress.

## How this repo is organized

| Path | What it is | Who uses it |
|---|---|---|
| `database/schema.sql` | A normalized relational model of the whole app — every entity and how they relate. | **Reference only.** Nothing in WordPress runs this file. It exists so the person building the site (you) has one place that defines the "correct" shape of the data, independent of which plugin ends up storing it. When something in ACF or a plugin setting seems ambiguous, this is the source of truth to check it against. |
| `wordpress/acf-field-groups.json` | An [Advanced Custom Fields](https://www.advancedcustomfields.com/) export — importable directly into the site. | **The person filling in content.** Import it once, then it defines the exact fields to fill in for each catalog item (teachers, locations, classes, etc.). |

Everything below explains how those two fit together and what actually needs to be built in WordPress.

## The core split: catalog data vs. captured data

Almost every question about "where does this go" comes down to one distinction:

- **Catalog data** — teachers, locations, classes, products, events, the approved peer-tutor list. This is set up *once* by an admin, rarely changes, and is purely **informational** on the front end (students browse it, they never edit it). It lives in WordPress as **custom post types with ACF fields**.
- **Captured data** — bookings, requests, orders, submissions. This is what students actually *do* in the app: book a slot, request equipment, place an order, submit work. It's captured through **forms and booking plugins**, not typed into wp-admin by hand.

Concretely, from the schema:

| Catalog (admin-entered, backend, informational) | Captured (student-facing, front end) |
|---|---|
| `location`, `teacher`, `subject`, `product`, `event`, `studio_gear`, `photographer` (role flag), `peer_tutor` (**approved** directory only), `equipment` (catalog fields — see note below) | `tutoring_booking`, `studio_booking`, `equipment_request`, `order`, `volunteer_request`, `photo_session_request`, `submit_work` |

So when the spec says a teacher's name, their schedule, or a classroom shouldn't come from the front end — that's this split. Those are catalog rows an admin fills in ahead of time; the front end just displays them. A student never types a teacher's name into anything; they *select* a teacher (or a subject, which implies a teacher) from what's already there.

One nuance worth calling out explicitly: **not everything "front end" goes through Forminator.** Looking at the plugins already chosen:

| Screen / action | Captured via | Notes |
|---|---|---|
| Tutoring booking | **MotoPress Appointment Booking** | Its own booking UI and storage — not a Forminator form. |
| Studio booking | **Booking Calendar (wpdevelop)** | Same — its own booking UI and storage. |
| Volunteer Form, Photo Form, Submit Work | **Forminator** | These are genuinely Forminator forms. |
| Goods (order) | *Not yet assigned* — likely Forminator with a calculation add-on, since it's the same "itemized form" shape. Still an open decision. |
| Borrow (equipment request/status) | **WP Inventory Manager** | Manages both the equipment catalog *and* live available/checked-out status itself. |

So "informational on the front end, not typed by a student" is the right mental model — just know that the actual capture mechanism is whichever plugin owns that screen (MotoPress, Booking Calendar, Forminator, or WP Inventory Manager), not Forminator across the board.

### A note on `equipment`

`equipment` (the 10 iPads, etc.) is catalog data in the sense that a student never creates or edits an equipment row — but it isn't modeled as an ACF custom post type here, because **WP Inventory Manager already owns this catalog** (item number, color/type, and live available/checked-out status) as part of doing its job for the Borrow tab. Adding a parallel ACF post type for the same items would create two sources of truth that can drift apart. Manage equipment inside WP Inventory Manager directly; `equipment` in `database/schema.sql` documents its *shape* for reference, not a table you need to build by hand.

## App structure

**Home** — Notifications (shown above events, intentionally), and an events/news list. Event cards offer "Volunteer" (→ Volunteer Form, event pre-selected) and "Participate" (→ Submit Work).

**Resources** (tabs: Tutoring, Studio, Goods, Borrow, Photo Form)
- *Tutoring* — student picks a subject → the subject determines the assigned teacher → the calendar shows only that teacher's real availability → picking a slot auto-fills the booking form. (MotoPress Appointment Booking.)
- *Studio* — calendar + 4 fixed time slots (08:00–10:00, 10:00–12:00, 13:00–15:00, 15:00–17:00) + a gear checklist + a contact field. Must also block times already occupied by regular scheduled classes, not just other studio bookings. (Booking Calendar.)
- *Goods* — itemized order form for print materials and merch (see pricing table below).
- *Borrow* — real-time status of shared equipment (currently 10 iPads; may expand to laptops). Used inside a classroom, same-day return only, faculty-supervised — the app shows live status and captures the request; the physical handoff happens in person. (WP Inventory Manager.)
- *Photo Form* — a student requests a photo session (as model, or as photographer). Requires a student ID even when the subject is a guest, since the requesting student is the responsible party.

**Get Involved** (tabs: Volunteer Form, Submit Work)
- *Volunteer Form* — student ID, school email, request date, event (dropdown). Volunteer hours count toward practicum hours (most students don't know this — the app should surface it). Includes the "Become a Peer Tutor" request: subject + availability → goes to a teacher for review → **never automatic** — an admin manually promotes an approved request into the public peer-tutor directory (see the ACF section below).
- *Submit Work* — name, work type (Ad / Photo / Web), a link to a OneDrive folder (no file upload — students already store work there), with visible naming instructions. Optionally linked to an event.

**More** (tabs: My Program, Preparation)
- *My Program* — searchable teacher/classroom directory. This is the `teacher` + `location` + `subject` catalog, read-only.
- *Preparation* — portfolio-readiness progress bar + a NOCTI exam-prep module (game-style, with a streak counter).

## Setting this up in WordPress

### 1. Register the custom post types

ACF (the free version used here) defines *fields*, not the post types those fields attach to. Seven catalog post types need to exist before importing the JSON below:

| Post type slug | Label | Supports | Notes |
|---|---|---|---|
| `location` | Locations | Title | |
| `teacher` | Teachers | Title | No ACF fields — the post title *is* the teacher's name. |
| `subject` | Classes / Subjects | Title | "Class" and "subject" are the same thing here. |
| `product` | Products | Title | |
| `event` | Events | Title, Editor | Editor = the event description. |
| `photographer` | Photographers | Title | |
| `peer_tutor` | Peer Tutors | Title | Public-facing directory — only *approved* tutors. |

You have two ways to register these without writing a plugin from scratch:

- **No-code option:** install the free **Custom Post Type UI** plugin and add each row above through its "Add New Post Type" screen (a couple of minutes each). This wasn't in the original tools list — it's a small addition worth calling out, but it's free and does exactly this one job well.
- **Code option:** a short `register_post_type()` snippet per type, added via a small custom plugin or a snippets plugin (e.g. Code Snippets). Slightly more setup once, but keeps you to one fewer plugin.

Either way, set `public` and `show_ui` to true (so they show up in wp-admin), and you generally do **not** need `has_archive` — students never browse a public "/location/" listing page directly; the app screens query these post types on their own.

### 2. Import the ACF field groups

Once the post types above exist:

1. `Custom Fields → Tools → Import Field Groups` in wp-admin.
2. Upload `wordpress/acf-field-groups.json`.
3. You'll see 6 field groups appear (Location, Class/Subject, Product, Event, Photographer, Peer Tutor) — `teacher` has no field group since it needs no fields beyond its title.

### 3. What to actually type into each one

This is the part meant for whoever is filling in content, not necessarily writing code:

**Locations** — Post Title = the room's name (e.g. "Studio B", "Room 204"). One field, *Type*: free text like "Classroom" or "Studio".

**Teachers** — Post Title = the teacher's name. Nothing else to fill in here.

**Classes / Subjects** — Post Title = the class name (e.g. "Photography I"). Two fields: *Teacher* (pick from the Teachers you already created) and *Classroom* (pick from Locations). This is the important one: the classroom lives **here**, on the class — not on the teacher. That's also how a peer tutor ends up with a room: once a peer tutor is linked to a subject (see below), they automatically inherit that subject's classroom. Nothing extra to assign.

> **Heads up:** this catalog is informational only — it feeds the "My Program" directory and lets other content (like a peer tutor's classroom) resolve correctly. It does **not** drive the actual booking calendar a student sees in Tutoring. That calendar's real availability is configured separately, inside the MotoPress Appointment Booking plugin's own Employee/Service setup. Keep the teacher and subject *names* consistent between the two so they don't look like different people/classes to a visitor, but they are two separate systems.

**Products** — Post Title = the product's name (e.g. "Photo paper", "Pullover"). Three fields: *Size* (optional — leave blank for things like a pullover that have no size), *Price* (USD), *Category* (Print Material or Merch — don't add new category choices without updating `database/schema.sql` and the Goods form to match).

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

**Photographers** — Not every student is a photographer, so only create a post here for students who hold that role (one field: *Student ID* — also set the Post Title to the same ID). How a student *earns* this role isn't decided yet (unlike peer tutoring, there's no described review step) — see Pending Decisions.

**Peer Tutors** — This is the **public-facing directory only**, i.e. tutors who have already been approved. It is deliberately separate from the raw "Become a Peer Tutor" request, which arrives as a Volunteer Form submission carrying only a student ID, a subject, and availability — never a name (remember: no names are captured anywhere in the app). The flow is:

1. Student submits the peer-tutor request through the Volunteer Form (Forminator). This sits in Forminator's own entries as a pending request; it does **not** create anything here.
2. The subject's teacher reviews it. Never automatic.
3. Once approved, an admin manually creates **one post here**. Post Title = the tutor's display name (typed in now, by the admin — this is the one place a student's real name enters the system, and it comes from staff, not from the student's own submission). Fields: *Student ID*, *Subject* (pick from Classes/Subjects — this is also where their classroom comes from, automatically), *Availability* (free text, e.g. "Mon/Wed 2-4pm").

A post existing here **is** the approval — there's no separate "status" toggle to also remember to flip.

### Why no custom taxonomies?

Everything that looks like it could be a WordPress taxonomy (location type, product category, work type on Submit Work, session type on the Photo Form) is instead either a plain text field or an ACF Select field with a fixed set of choices. That mirrors how `database/schema.sql` models the same values — a free-text column or an `ENUM` — and it's simpler to maintain than real taxonomy terms for what are really just small, fixed lists. If a value needs to support hierarchy or WordPress-native archive/filter pages later, it can be promoted to a real taxonomy then.

## Product pricing — open questions

- Exact large-board size (currently ~15x20 or 17x20 — needs confirming).
- Whether dry-mount tissue is priced differently for the large board.
- Whether an envelope is required for *every* submission type, or only some.

## Database schema

See `database/schema.sql` for the full relational model — every table, column, and foreign key, with comments explaining the reasoning behind each design decision (e.g. why `photographer` and `peer_tutor` are modeled as subtypes of `student` rather than independent entities, why the classroom lives on `subject` rather than `teacher`, why `equipment_request` carries its own `location_id`). It's been validated by actually running it against a live MariaDB instance — every table and foreign key creates cleanly — but again, it's a reference model, not something WordPress executes directly.

## Tools decided so far

| Feature | Tool |
|---|---|
| Theme | Blocksy |
| Tabs | Kadence Blocks |
| Volunteer form, Photo form, Submit Work | Forminator |
| Studio booking | Booking Calendar (wpdevelop) |
| Tutoring booking | MotoPress Appointment Booking |
| Borrow (equipment lending) | WP Inventory Manager |
| Catalog data (locations, teachers, classes, products, events, photographer role, approved peer tutors) | Custom post types + ACF (this repo's `wordpress/acf-field-groups.json`) |
| Registering the custom post types above | Custom Post Type UI (no-code) *or* a short `register_post_type()` snippet — not finalized, see setup section above |

## Pending decisions

- Which WordPress-to-app plugin for push notifications (AppPresser / MobiLoud / AppMySite).
- Student ID validation method (how the system confirms an ID belongs to a real enrolled student).
- Exact large-board size and its dry-mount-tissue pricing.
- Whether an envelope is required for every submission type.
- Which plugin captures the Goods (order) form — likely Forminator with a calculation add-on, not yet confirmed.
- How a student becomes a "photographer" — no review/approval step has been described for this role, unlike peer tutoring.
- Custom Post Type UI vs. a code snippet for registering the catalog post types.
