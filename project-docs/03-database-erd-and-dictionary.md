This file asks for **two** documents in one response, separated by
`# ── PART 1 ──` / `# ── PART 2 ──` headers. Both are built from the exact same
schema facts below — use them once, do not re-derive or guess additional columns
for either part.

---

# PART 1 — Entity Relationship Diagram

Produce a Mermaid `erDiagram`, split into the logical clusters used in the facts
below (CRM Core, Lookups, Performance & Reminders, Department Tasks, Advertising
Products, Social Media & Posting Calendar, Email Campaigns, System/Admin,
Dead/legacy), each as its own Mermaid code block with a one-paragraph note before
it (a single combined diagram of this size would be unreadable). Include a short
prose description of each relationship's cardinality and delete behavior.
Audience: developers and DB reviewers.

# PART 2 — Data Dictionary

Produce one Markdown table per database table, with columns
`Column | Type | Nullable | Default | Notes`, grouped under the same section
headings as Part 1. Audience: developers and DBAs who need exact column-level
reference, not narrative. For any table where the facts below say "additional
columns not enumerated," add a single dictionary row noting "additional column(s)
exist beyond what's documented — verify against `database/migrations/` before
treating this table as fully specified" rather than inventing plausible-sounding
columns.

Where a table has "additional columns not enumerated" noted, say so in both
parts rather than guessing values.

---

## CRM Core

```
users ||--o{ contacts : "owns (user_id, cascade delete)"
contacts ||--o{ contact_incharges : "cascade delete"
contacts ||--o{ to_dos : "cascade delete"
to_dos }o--|| tasks : "task_id, nullable, null on delete"
to_dos ||--o{ follow_ups : "cascade delete"
to_dos }o--|| users : "user_id, nullable, null on delete"
contacts }o--|| contact_statuses : "status_id"
contacts }o--|| contact_types : "type_id"
contacts }o--|| contact_categories : "category_id"
contacts }o--|| contact_industries : "industry_id"
contacts }o--|| contact_areas : "area_id, nullable, unused in UI"
users ||--o{ deals : "cascade delete"
contacts ||--o{ deals : "cascade delete"
users ||--o{ projects : "nullable, cascade delete"
contacts ||--o{ projects : "nullable, cascade delete"
contacts ||--o{ forecasts : "cascade delete"
users }o--|| forecasts : "nullable"
forecasts }o--|| forecast_products : "nullable"
forecasts }o--|| forecast_types : "nullable"
forecasts }o--|| forecast_results : "nullable"
forecasts }o--|| contact_statuses : "contact_status_id snapshot, nullable"
forecasts }o--|| contact_types : "contact_type_id snapshot, nullable"
```

**Column detail:**
- `users`: id, name, email (unique), email_verified_at, password, remember_token,
  timestamps, + username (unique, nullable), is_approved (bool), approved_at,
  approved_by_id, access_requested_at, login_count, last_login_at, soft-deletes,
  phone, job_title, settings (JSON), failed_login_attempts, locked_until,
  lockout_level, permanently_locked, inactivity_flagged_at, blocked_at, role (enum:
  super_admin/hod/staff — a *separate* dept-task role field, distinct from Spatie
  roles), department_id (FK to departments, nullable). Note: dashboard_layout column
  also added by a later migration (exact type not captured here).
- `contacts`: id, name(500), address(255, nullable), remark(800, nullable),
  user_id (FK users, nullable, cascade), status_id/type_id/category_id/industry_id
  (FK lookups, nullable, cascade), area_id (FK contact_areas, nullable, null-on-
  delete, added later — **not used in the UI**), whatsapp_phone, lead_source(50),
  last_contacted_at, is_permanently_closed (bool, default false), soft-deletes,
  timestamps. (An audit-trail migration also touched core tables — exact added
  columns not captured here; note as "additional audit columns may exist.")
- `contact_statuses` / `contact_types` / `contact_categories` / `contact_industries`
  / `contact_areas`: id, name, timestamps (simple lookup tables).
- `contact_incharges`: id, contact_id (FK, cascade), name(255,nullable),
  email(255,nullable), phone_mobile(50,nullable), phone_office(50,nullable),
  timestamps.
- `tasks` (lookup, not to be confused with dept_tasks): id, name(100), timestamps.
- `to_dos`: id, contact_id (FK, cascade), task_id (FK tasks, nullable, null-on-
  delete), todo_date, todo_remark (nullable), user_id (FK users, nullable, null-on-
  delete), date_created (nullable), completion_status(20, default pending),
  completed_at (nullable), timestamps.
- `follow_ups`: id, todo_id (FK to_dos, cascade), followup_date, note (nullable),
  action_type(100, nullable), completion_status(20, default pending), completed_at
  (nullable), timestamps. **No direct user_id or contact_id** — always reached via
  its parent to_do.
- `deals`: id, contact_id (FK, cascade), user_id (FK, cascade), title(500),
  stage(100, default "New Lead"), value (decimal 15,2 nullable), probability
  (tinyint nullable), expected_close_date (nullable), status(20, default "open"),
  lost_reason(500, nullable), notes (nullable), soft-deletes, timestamps.
- `projects`: id, project_startdate, project_enddate, project_name,
  project_remark (nullable), user_id (FK, nullable, cascade), contact_id (FK,
  nullable, cascade), soft-deletes, timestamps.
- `forecast_products` / `forecast_types` / `forecast_results`: id, name (unique),
  timestamps.
- `forecasts`: id, contact_id (FK, cascade), user_id (FK, nullable, null-on-delete),
  product_id / forecast_type_id / result_id (FK, nullable, null-on-delete),
  contact_status_id / contact_type_id (FK, nullable, null-on-delete — point-in-time
  snapshot of the contact's classification), amount (decimal 15,2), forecast_date,
  forecast_updatedate (nullable), timestamps.

## Performance & Reminders

- `kpi_targets`: id, user_id (FK, cascade), metric(50), target_value (decimal
  12,2, default 0), timestamps. Unique (user_id, metric).
- `performance_targets`: id, user_id (FK, cascade), task_id (FK tasks, cascade),
  weekly_target (uint, default 0), timestamps. Unique (user_id, task_id). (Legacy
  task-based targets, predates kpi_targets.)
- `reminder_reads`: id, user_id (FK, cascade), source_type(20, "todo" or
  "followup"), source_id, read_at, timestamps. Unique (user_id, source_type,
  source_id).
- `round_robin_state`: id, last_user_id (FK users, nullable, null-on-delete),
  timestamps. Single/few-row table tracking round-robin lead assignment state.

## Department Task Manager (internal, separate from CRM To-Do/Follow-Up)

```
departments ||--o{ dept_tasks : "cascade delete"
users |o--o{ dept_tasks : "assigned_to, nullable, null on delete"
users ||--o{ dept_tasks : "created_by, cascade delete"
dept_tasks ||--o{ dept_task_comments : "cascade delete"
dept_tasks ||--o{ dept_task_attachments : "cascade delete"
dept_tasks |o--o{ dept_notifications : "nullable, null on delete"
users ||--o{ dept_notifications : "cascade delete"
```

- `departments`: id, name, code(10, unique), color(20, default #3B82F6),
  icon(10, default 📁), timestamps.
- `dept_tasks`: id, title, description (nullable), department_id (FK, cascade),
  assigned_to (FK users, nullable, null-on-delete), created_by (FK users, cascade),
  priority (enum low/medium/high/critical, default medium), status (enum pending/
  in_progress/waiting_approval/completed/cancelled, default pending), due_date
  (nullable), requires_approval (bool, default false — **column retained but the
  approval workflow was removed on 2026-07-15; do not describe it as functional**),
  is_recurring (bool), recurrence_type (enum daily/weekly/monthly/quarterly,
  nullable), next_recurrence_date (nullable), board_position (uint, default 0),
  timestamps.
- `dept_task_comments`: id, task_id (FK dept_tasks, cascade), user_id (FK, cascade),
  comment (text), timestamps.
- `dept_task_attachments`: id, task_id (FK dept_tasks, cascade), user_id (FK,
  cascade), filename, path, size (default 0), mime_type (nullable), timestamps.
- `dept_notifications`: id, user_id (FK, cascade), task_id (FK dept_tasks,
  nullable, null-on-delete), type (assigned/due_soon/overdue/completed/comment,
  free-text string), message, read_at (nullable), timestamps.

## Marketing: Advertising Products (Site Availability)

- `advertising_products`: id, site_name, status (default "Existing"), type
  (default "A1"), product_type, timestamps. **Additional descriptive, photo, and
  contact-related columns were added in later migrations** (illumination/facing,
  is_pending flag, sheet_type_label, contact fields, photo fields) — exact column
  list not enumerated here; note in the document that these should be verified
  against the migrations if precise DDL is required.
- `advertising_product_bookings`: id, advertising_product_id (FK, cascade),
  contact_id (FK contacts, nullable, null-on-delete), company_name, year (uint16),
  month (uint8), timestamps. Unique (advertising_product_id, year, month). A
  `booking_group` column was added later for multi-month bookings.

## Marketing: Social Media & Posting Calendar

- `social_media_packages`: id, name (unique), timestamps. Seeded with "FB IG
  MANAGEMENT", "FB ADS SPONSORED", "TIKTOK MANAGEMENT".
- `social_media_reminders`: id, company_name, package, month,
  content_calendar_status/artwork_editing_status/posting_status/report_status
  (each default "pending"), posting_staff_initials(10, nullable), timestamps. A
  `contact_id` FK was added in a later migration.
- `posting_calendar_reminders`: id, user_id (FK, cascade), title, platform(20),
  client(255, nullable), date, time (nullable), status(20, default "planned"),
  timestamps.

## Marketing: Email Campaigns

```
email_campaigns }o--|| users : "cascade delete"
email_campaigns }o--|| email_audience_groups : "nullable"
email_campaigns ||--o{ email_campaign_recipients : "cascade delete"
email_campaigns ||--o{ email_logs : "nullable, null on delete"
email_campaigns ||--o{ email_campaign_contacts : "cascade delete"
email_contacts }o--o{ email_tags : "via email_contact_tag"
email_audience_groups }o--o{ email_contacts : "via email_audience_group_contact"
email_campaign_recipients }o--|| email_contacts : "nullable, null on delete"
email_logs }o--|| email_campaign_recipients : "nullable, null on delete"
email_logs }o--|| email_contacts : "nullable, null on delete"
```

- `email_campaigns`: id, user_id (FK, cascade), name, status(20, default "draft"),
  subject(500, nullable), preview_text(255, nullable), sender_name(255, nullable),
  sender_email(255, nullable), body (longtext, nullable), audience_group_id (FK
  email_audience_groups, nullable), scheduled_at, sent_at, audience_count (default
  0), sent_count/delivered_count/opened_count/clicked_count/bounced_count/
  unsubscribed_count (each uint, default 0), open_rate/click_rate (decimal 5,2,
  nullable), brevo_campaign_id/brevo_list_id (nullable), timestamps. (Originally
  also had provider, template_id, audience(JSON), schedule_at — these were dropped
  when the table was migrated to the Brevo-based design.)
- `email_templates`: id, name, category(100, nullable), subject(500), body
  (longtext), timestamps.
- `email_campaign_contacts`: id, email_campaign_id (FK, cascade),
  contact_incharge_id (nullable, no FK constraint), email(255), name(255,
  nullable), timestamps.
- `email_contacts`: id, full_name (nullable), email (unique), phone (nullable),
  company (nullable), status (enum subscribed/unsubscribed/bounced/pending,
  default subscribed), source (enum manual/crm/import, default manual),
  crm_incharge_id (FK contact_incharges, nullable, null-on-delete),
  unsubscribed_at, timestamps.
- `email_tags`: id, name (unique), color (nullable), timestamps.
- `email_contact_tag` (pivot): id, email_contact_id (FK, cascade), email_tag_id
  (FK, cascade), timestamps. Unique pair.
- `email_audience_groups`: id, name, description (nullable), type (enum static/
  dynamic, default static), filters (JSON, nullable), is_system (bool, default
  false), timestamps. A `max_contacts` column was added in a later migration.
- `email_audience_group_contact` (pivot): id, email_audience_group_id (FK,
  cascade), email_contact_id (FK, cascade), timestamps. Unique pair.
- `email_campaign_recipients`: id, email_campaign_id (FK, cascade),
  email_contact_id (FK, nullable, null-on-delete), email, name (nullable), status
  (enum pending/sent/delivered/opened/clicked/bounced/unsubscribed/failed, default
  pending), token(64, unique — used in tracking-pixel/click/unsubscribe URLs),
  error (nullable), open_count/click_count (default 0), sent_at/opened_at/
  clicked_at, timestamps.
- `email_logs`: id, email_campaign_id (FK, nullable, null-on-delete),
  email_campaign_recipient_id (FK, nullable, null-on-delete), email_contact_id
  (FK, nullable, null-on-delete), event (enum sent/delivered/open/click/bounce/
  unsubscribe/failed), url (nullable, the clicked URL), ip_address (nullable),
  user_agent (nullable), timestamps.
- `email_settings`: single-row config — id, smtp_host/port/username/password
  (password encrypted at the model layer)/encryption (nullable), from_name/
  from_email/reply_to (nullable), company_name/company_address (nullable),
  email_footer (nullable), tracking_enabled (bool, default true), timestamps.

## System / Admin

- `system_settings`: id, key (unique), value (nullable), label, description
  (nullable), timestamps. Seeded row: `admin_notification_email`.
- `system_alerts`: id, for_user_id (FK users, cascade), type, title, body, link
  (nullable), read_at (nullable), timestamps.
- `admin_audit_logs`: id, user_id (nullable, no FK constraint), action(50),
  entity_type(50), entity_id(50, nullable), entity_name(255, nullable),
  old_values/new_values (JSON, nullable), ip_address(45, nullable), timestamps.
- `announcements`: id, created_by (FK users, cascade), title, body, published_at
  (nullable), expires_at (nullable), timestamps. (urgency/target columns added
  later.)
- `announcement_reads`: id, announcement_id (FK, cascade), user_id (FK, cascade),
  read_at (default current timestamp). Unique (announcement_id, user_id).
- `contact_edit_grants`: id, user_id (FK users, cascade — grant recipient),
  target_user_id (FK users, cascade — whose contacts can be edited), granted_by
  (FK users, cascade). Unique (user_id, target_user_id).
- `user_signatures`: id, user_id (FK, unique, cascade), signature_data (longtext,
  base64 PNG data URI), timestamps.
- `user_prepared_by`: id, user_id (FK, unique, cascade), name, title (nullable),
  mobile_code(10, default "+60"), mobile_local(30, nullable),
  signature_label(50, nullable), timestamps.
- `data_injections`: id, label, preset(50), injected_ids (JSON), record_count
  (uint, default 0), timestamps. (Dev-panel-only seed/rollback tracking table.)
- Spatie `laravel-permission` standard tables: `roles`, `permissions`,
  `model_has_roles`, `model_has_permissions`, `role_has_permissions` (guard `web`
  throughout) — do not redesign these, they are the package's own schema.
- `personal_access_tokens` — Sanctum's standard token table.

## Dead / legacy tables (note but do not model relationships for)
`contact_emails` and `contact_calls` were created (2026-05-18) then **dropped**
(2026-07-01) — no longer exist. `webhooks` was created then **dropped** (2026-06-19).
A `whatsapp_messages` table was created (2026-05-19) but has no corresponding Eloquent
model — treat as unused/legacy, not part of the active data model. A generic Laravel
`notifications` table also exists (standard framework table).
