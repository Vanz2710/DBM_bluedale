This file asks for **two** documents in one response. Produce both, clearly
separated by an `# ── PART 1 ──` / `# ── PART 2 ──` header.

---

# PART 1 — System Overview / Project Charter

Audience: management/stakeholders who did not build the system and may not be
technical. Tone: plain business language, minimal jargon. Length: 1–2 pages
equivalent. Sections:
1. Purpose & Background — what problem this system solves
2. Scope — what is in scope / out of scope
3. Target Users — who uses it and how
4. Business Objectives — what success looks like
5. Key Modules (one paragraph each — a full functional breakdown of each module
   follows in Part 2, so keep this section brief/summary-level)
6. Stakeholders (roles, not names)
7. High-level Constraints & Assumptions

# PART 2 — Functional Requirements Specification (FRS)

Audience: developers/QA who need to know exactly what each module must do. Format:
one section per module, each with a bullet list of discrete, testable requirements
(e.g. "The system shall allow a user with `create deals` permission to create a
deal linked to a contact, with title, stage, value, probability, expected close
date, and notes."). Number requirements per module (e.g. FR-CONTACT-01).

Use only the facts below for both parts — do not invent metrics, customer names,
company details, additional fields, statuses, or workflows not described here.

---

## Facts about the system

**Name:** Bluedale CRM ("CRM Bluedale")

**What it is:** An internal CRM web application for a company that sells/manages
advertising sites (billboards/signage), used to track sales contacts, deals,
projects, follow-ups, marketing activity, and staff performance.

**Users:** Internal staff only (no external customer-facing accounts). Roles range
from front-line sales staff to supervisors to admins/super-admins who configure the
system.

**Hosting model:** Runs on local XAMPP for development and is deployed to cPanel
shared/VPS hosting for production.

**Roles (6):** `super-admin`, `admin`, `supervisor`, `user`, `internal support`,
`viewer` — from full CRM control down to read-only access. Full permission
breakdown lives in the Security document (RBAC matrix).

**Modules at a glance (for Part 1):** Contacts, To-Do / Follow-Up, Deals, Projects,
Forecasts, Performance, Site Availability (advertising products/bookings), Social
Media Reminders, Posting Calendar, Email Marketing, Department Task Manager,
RBAC/User Management, System Settings, Announcements, Reports/Analytics/Data
Health.

## Modules in full detail (for Part 2)

### Contacts
Core entity, owned by a `user_id`. Classified by status, type, category, industry,
and (rarely used) area. Fields: name, address, remark. Supports: list/search/filter,
create, edit, soft-delete, permanent-close toggle, duplicate detection
(`find-duplicates`, `check-duplicate`), merge duplicates, bulk reassign to another
owner, CSV export, CSV import (preview + process), "daily" view, lead source
tracking, last-contacted-at tracking. Has sub-resources: Contact In-Charges (contact
person: name/email/mobile/office phone) and contact-scoped To-Dos.

### To-Do / Follow-Up
`ToDo` belongs to a Contact and optionally a `Task` (lookup). Fields: todo_date,
todo_remark, date_created, completion_status (pending/completed/cancelled),
completed_at. `FollowUp` belongs to a ToDo (not directly to a user or contact).
Fields: followup_date, note, action_type, completion_status, completed_at. There is
also a **Global To-Do list** (not scoped to a contact) with its own controller,
supporting status update, bulk-complete of follow-ups under a to-do, active-dates
lookup, and export. The To-Do page is the single editor for tasks/follow-ups;
contact-level views are read-only with a deep-link back to it.

### Deals
Belongs to a Contact and a User. Fields: title, stage (default "New Lead"), value,
probability, expected_close_date, status (open/won/lost, default open), lost_reason,
notes. Supports index/show/create/edit/delete, summary aggregation, export, soft
delete.

### Projects
Belongs to a Contact and a User. Fields: project_startdate, project_enddate,
project_name, project_remark, soft delete. Supports CRUD, "remark" endpoint, export.

### Forecasts
Belongs to a Contact, User, ForecastProduct, ForecastType, ForecastResult, and
(denormalized) the contact's ContactStatus/ContactType at forecast time. Fields:
amount, forecast_date, forecast_updatedate. Supports CRUD plus a separate
"forecast summary" aggregate view gated by its own permission.

### Performance
Read-oriented module: overview (KPI counts for week/month/year/custom range,
overdue items, target progress), team comparison (admin-only), legacy task-based
report, per-user KPI targets (metrics: new_contacts, todos_completed,
followups_completed, projects_created, deals_created, deals_won, won_deal_value) —
viewable and editable (upsert) per user.

### Site Availability (Advertising Products)
Tracks physical/advertising sites: `AdvertisingProduct` (site_name, status, type,
product_type, plus later-added detail/photo/contact fields, is_pending flag,
illumination/facing fields). Bookings (`AdvertisingProductBooking`) link a product to
a contact/company for a given year+month (one booking per site per month, enforced by
a unique constraint), with a booking_group field for multi-month bookings. Supports
listing, creating/updating/discarding products, photo upload/delete, "confirm"
workflow for pending products, proposal generation, resolving a Google Maps URL,
booking update/delete.

### Social Media Reminders & Posting Calendar
`SocialMediaReminder`: company_name, package (FK-like string to
`SocialMediaPackage`), month, and four independent status fields (content calendar,
artwork/editing, posting, report), plus posting staff initials. `PostingCalendar
Reminder`: per-user scheduled post with platform (FB/IG/TikTok/LinkedIn/Website),
client, date, time, status (planned/design/approval/scheduled/posted).

### Email Marketing
Full email campaign system: `EmailContact` (subscribed/unsubscribed/bounced/pending,
sourced manually/from CRM/import), `EmailTag`, `EmailAudienceGroup` (static or
dynamic with JSON filter rules, system-flagged defaults), `EmailCampaign` +
`EmailCampaignRecipient` (per-recipient status: pending→sent→delivered→opened→
clicked, or bounced/unsubscribed/failed, with open/click counts and a unique tracking
token), `EmailLog` (raw event stream: sent/delivered/open/click/bounce/unsubscribe/
failed), `EmailTemplate`, `EmailSetting` (single-row SMTP/branding config). Supports:
campaign CRUD, duplicate, send, schedule, send-test, view recipients; template CRUD;
image upload for campaign content; contact CRUD/bulk/import/export/sync-from-CRM;
tag CRUD; audience group CRUD + preview + member list; analytics dashboard.

### Department Task Manager ("Dept Tasks")
Separate from the customer-facing To-Do system — internal staff/department work
tracking. `Department` (name, unique code, color, icon). `DeptTask`: title,
description, department, assigned_to (nullable user), created_by, priority (low/
medium/high/critical), status (pending/in_progress/waiting_approval/completed/
cancelled), due_date, is_recurring + recurrence_type (daily/weekly/monthly/
quarterly) + next_recurrence_date, board_position (for a kanban-style board). Note:
the `requires_approval` column exists but the approval **workflow itself was removed**
(2026-07-15) — do not describe an approval-gated completion flow. Supports comments
(`DeptTaskComment`), file attachments (`DeptTaskAttachment` — filename/path/size/
mime), per-user notifications (`DeptNotification` — assigned/due_soon/overdue/
completed/comment types), dashboard, weekly view, report, calendar export, CSV
export.

### RBAC & User Management
Roles: super-admin, admin, supervisor, user, internal support, viewer. Admins
manage roles (create/edit/delete, sync permissions to a role), manage users
(create, approve pending, edit, soft-delete, restore, bulk role-assign, bulk delete,
sync roles per user, restore access after inactivity lockout, unlock after
failed-login lockout). `ContactEditGrant` lets one user be granted edit access to
another specific user's contacts (delegated access, distinct from role-based
permission).

### System Settings & Announcements
`SystemSetting`: global key/value config (currently `admin_notification_email`).
`Announcement`: title, body, published_at, expires_at, created_by, with per-user
read tracking (`AnnouncementRead`) and optional urgency/target fields. All users can
read announcements; only users with `manage announcements` can create/edit/delete.

### Reporting & Data Quality
`Analytics` (summary dashboard), `Summary` (CRM activity summary), `DataHealth`
(data quality audit), `ContactAnalysis` (overview, lead source, status distribution,
engagement, follow-up actions), `Predictive` (forecast, at-risk contacts, pace,
overdue risk, deal win-rates/velocity/pipeline coverage) — all read-only, gated by
their own view permissions.

### Admin Audit Log
Every admin action is recorded: user_id, action, entity_type, entity_id/name,
old_values/new_values (JSON), ip_address, timestamp. Viewable and exportable by users
with `manage users`.
