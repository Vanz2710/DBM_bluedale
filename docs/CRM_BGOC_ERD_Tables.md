# CRM BGOC — ERD Table Reference

> Derived from `bluedale2_crmbgoc_structure.sql` (MySQL 5.7, exported Apr 29 2026).
> WordPress `wp_*` tables and Laravel system tables (`migrations`, `failed_jobs`, `password_resets`, `personal_access_tokens`) are documented separately at the end.
> Symbols: 🔑 Primary Key · 🔗 Foreign Key · ❗ NOT NULL · ◻ Nullable

---

## Group 1 — Users & Authentication

### `users`
Core user accounts for the CRM.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | 🔑 AUTO_INCREMENT | |
| `name` | VARCHAR(255) | ❗ | |
| `password` | VARCHAR(255) | ❗ | Hashed |
| `email` | VARCHAR(255) | ❗ UNIQUE | |
| `email_password` | VARCHAR(255) | ❗ | Plaintext email credential — ⚠️ security risk |
| `email_verified_at` | TIMESTAMP | ◻ | |
| `user_cat_id` | BIGINT UNSIGNED | ◻ 🔗 `user_categories.id` | ON DELETE SET NULL |
| `user_auth_id` | BIGINT UNSIGNED | ◻ 🔗 `user_auths.id` | ON DELETE SET NULL |
| `remember_token` | VARCHAR(100) | ◻ | |
| `created_at` | TIMESTAMP | ◻ | |
| `updated_at` | TIMESTAMP | ◻ | |

---

### `user_auths`
Lookup table for user authentication levels/types.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | 🔑 AUTO_INCREMENT | |
| `name` | VARCHAR(255) | ❗ | e.g. Admin, Staff |
| `created_at` | TIMESTAMP | ◻ | |
| `updated_at` | TIMESTAMP | ◻ | |

---

### `user_categories`
Categories that classify users (e.g. for benchmarking purposes).

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | 🔑 AUTO_INCREMENT | |
| `name` | VARCHAR(255) | ❗ | |
| `description` | VARCHAR(255) | ◻ | |
| `created_at` | TIMESTAMP | ◻ | |
| `updated_at` | TIMESTAMP | ◻ | |

---

### `supervisors`
Links a user as a named supervisor entity.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | 🔑 AUTO_INCREMENT | |
| `user_id` | BIGINT UNSIGNED | ❗ 🔗 `users.id` | ON DELETE CASCADE |
| `sv_name` | VARCHAR(255) | ❗ | Display name for the supervisor |
| `created_at` | TIMESTAMP | ◻ | |
| `updated_at` | TIMESTAMP | ◻ | |

---

### `sv_sb_pivots`
Pivot: maps supervisors to their subordinates (many-to-many).

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | 🔑 AUTO_INCREMENT | |
| `supervisor_id` | BIGINT UNSIGNED | ❗ 🔗 `supervisors.user_id` | ON DELETE CASCADE |
| `subordinate_id` | BIGINT UNSIGNED | ❗ 🔗 `users.id` | ON DELETE CASCADE |
| `created_at` | TIMESTAMP | ◻ | |
| `updated_at` | TIMESTAMP | ◻ | |

---

## Group 2 — Roles & Permissions (Spatie)

### `roles`
Named roles assigned to users.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | 🔑 AUTO_INCREMENT | |
| `name` | VARCHAR(255) | ❗ UNIQUE with `guard_name` | |
| `description` | VARCHAR(255) | ◻ | Custom field added to Spatie default |
| `guard_name` | VARCHAR(255) | ❗ | Typically `web` |
| `created_at` | TIMESTAMP | ◻ | |
| `updated_at` | TIMESTAMP | ◻ | |

---

### `permissions`
Granular permission definitions.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | 🔑 AUTO_INCREMENT | |
| `name` | VARCHAR(255) | ❗ UNIQUE with `guard_name` | |
| `description` | VARCHAR(255) | ◻ | Custom field |
| `guard_name` | VARCHAR(255) | ❗ | |
| `created_at` | TIMESTAMP | ◻ | |
| `updated_at` | TIMESTAMP | ◻ | |

---

### `role_has_permissions`
Pivot: assigns permissions to roles.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `permission_id` | BIGINT UNSIGNED | 🔑 🔗 `permissions.id` | ON DELETE CASCADE |
| `role_id` | BIGINT UNSIGNED | 🔑 🔗 `roles.id` | ON DELETE CASCADE |

---

### `model_has_roles`
Pivot: assigns roles to any model (typically `App\Models\User`).

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `role_id` | BIGINT UNSIGNED | 🔑 🔗 `roles.id` | ON DELETE CASCADE |
| `model_type` | VARCHAR(255) | 🔑 ❗ | Polymorphic model class |
| `model_id` | BIGINT UNSIGNED | 🔑 ❗ | Polymorphic model ID |

---

### `model_has_permissions`
Pivot: assigns permissions directly to models (bypassing roles).

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `permission_id` | BIGINT UNSIGNED | 🔑 🔗 `permissions.id` | ON DELETE CASCADE |
| `model_type` | VARCHAR(255) | 🔑 ❗ | Polymorphic model class |
| `model_id` | BIGINT UNSIGNED | 🔑 ❗ | Polymorphic model ID |

---

## Group 3 — Contacts

### `contacts`
Core company/client records — the central entity of the CRM.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | 🔑 AUTO_INCREMENT | |
| `name` | VARCHAR(500) | ❗ | Company/contact name |
| `address` | VARCHAR(255) | ◻ | |
| `remark` | VARCHAR(800) | ◻ | |
| `user_id` | BIGINT UNSIGNED | ◻ 🔗 `users.id` | Owner/assigned user. ON DELETE CASCADE |
| `status_id` | BIGINT UNSIGNED | ◻ 🔗 `contact_statuses.id` | ON DELETE CASCADE |
| `type_id` | BIGINT UNSIGNED | ◻ 🔗 `contact_types.id` | ON DELETE CASCADE |
| `category_id` | BIGINT UNSIGNED | ◻ 🔗 `contact_categories.id` | ON DELETE CASCADE |
| `industry_id` | BIGINT UNSIGNED | ◻ 🔗 `contact_industries.id` | ON DELETE CASCADE |
| `created_at` | TIMESTAMP | ◻ | |
| `updated_at` | TIMESTAMP | ◻ | |

---

### `contact_categories`
Lookup: categories for contacts (e.g. Client, Prospect).

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | 🔑 AUTO_INCREMENT | |
| `name` | VARCHAR(255) | ❗ | |
| `created_at` | TIMESTAMP | ◻ | |
| `updated_at` | TIMESTAMP | ◻ | |

---

### `contact_statuses`
Lookup: status values shared across contacts, todos, follow-ups, forecasts.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | 🔑 AUTO_INCREMENT | |
| `name` | VARCHAR(255) | ❗ | |
| `created_at` | TIMESTAMP | ◻ | |
| `updated_at` | TIMESTAMP | ◻ | |

---

### `contact_types`
Lookup: type values shared across contacts, todos, follow-ups, forecasts.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | 🔑 AUTO_INCREMENT | |
| `name` | VARCHAR(255) | ❗ | |
| `created_at` | TIMESTAMP | ◻ | |
| `updated_at` | TIMESTAMP | ◻ | |

---

### `contact_industries`
Lookup: industry classification for contacts.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | 🔑 AUTO_INCREMENT | |
| `name` | VARCHAR(255) | ❗ | |
| `created_at` | TIMESTAMP | ◻ | |
| `updated_at` | TIMESTAMP | ◻ | |

---

### `contact_incharges`
People-in-charge (PIC) linked to a contact/company.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | 🔑 AUTO_INCREMENT | |
| `contact_id` | BIGINT UNSIGNED | ◻ 🔗 `contacts.id` | ON DELETE CASCADE |
| `name` | VARCHAR(255) | ❗ | |
| `email` | VARCHAR(255) | ❗ | |
| `phone_mobile` | VARCHAR(255) | ❗ | |
| `phone_office` | VARCHAR(255) | ◻ | |
| `created_at` | TIMESTAMP | ◻ | |
| `updated_at` | TIMESTAMP | ◻ | |

---

### `text_colors`
Lookup: color codes used for contact text labeling.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | 🔑 AUTO_INCREMENT | |
| `color_code` | VARCHAR(255) | ❗ | e.g. `#FF0000` |
| `created_at` | TIMESTAMP | ◻ | |
| `updated_at` | TIMESTAMP | ◻ | |

---

### `contact_text_colors`
Pivot: assigns a display color to a contact. ⚠️ No surrogate PK — no `id` column.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `company_id` | BIGINT UNSIGNED | ◻ 🔗 `contacts.id` | ON DELETE CASCADE |
| `color_id` | BIGINT UNSIGNED | ◻ 🔗 `text_colors.id` | ON DELETE SET NULL |
| `created_at` | TIMESTAMP | ◻ | |
| `updated_at` | TIMESTAMP | ◻ | |

---

## Group 4 — To-Dos & Follow-Ups

### `tasks`
Lookup: task type labels (e.g. Call, Meeting, Email).

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | 🔑 AUTO_INCREMENT | |
| `name` | VARCHAR(255) | ❗ | |
| `created_at` | TIMESTAMP | ◻ | |
| `updated_at` | TIMESTAMP | ◻ | |

---

### `actions`
Lookup: action types used in todos and performance targets.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | 🔑 AUTO_INCREMENT | |
| `name` | VARCHAR(255) | ❗ | |
| `created_at` | TIMESTAMP | ◻ | |
| `updated_at` | TIMESTAMP | ◻ | |

---

### `priorities`
Lookup: priority levels (e.g. High, Normal, Low).

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | 🔑 AUTO_INCREMENT | |
| `name` | VARCHAR(255) | ❗ | |
| `created_at` | TIMESTAMP | ◻ | |
| `updated_at` | TIMESTAMP | ◻ | |

---

### `to_do_sources`
Lookup: origin/source of a to-do entry.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | 🔑 AUTO_INCREMENT | |
| `name` | VARCHAR(255) | ❗ | |
| `created_at` | TIMESTAMP | ◻ | |
| `updated_at` | TIMESTAMP | ◻ | |

---

### `to_dos`
Daily activity entries per user, linked to a contact.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | 🔑 AUTO_INCREMENT | |
| `todo_date` | DATE | ❗ | |
| `todo_deadline` | DATE | ◻ | |
| `todo_remark` | VARCHAR(800) | ❗ | |
| `contact_id` | BIGINT UNSIGNED | ◻ 🔗 `contacts.id` | ON DELETE CASCADE |
| `user_id` | BIGINT UNSIGNED | ◻ 🔗 `users.id` | ON DELETE NO ACTION |
| `task_id` | BIGINT UNSIGNED | ◻ 🔗 `tasks.id` | ON DELETE NO ACTION |
| `status_id` | BIGINT UNSIGNED | ◻ 🔗 `contact_statuses.id` | ON DELETE NO ACTION |
| `type_id` | BIGINT UNSIGNED | ◻ 🔗 `contact_types.id` | ON DELETE NO ACTION |
| `priority_id` | BIGINT UNSIGNED | ◻ 🔗 `priorities.id` | ON DELETE NO ACTION |
| `color_id` | BIGINT UNSIGNED | ◻ 🔗 `text_colors.id` | ON DELETE NO ACTION |
| `source_id` | BIGINT UNSIGNED | ◻ 🔗 `to_do_sources.id` | ON DELETE NO ACTION |
| `action_id` | BIGINT UNSIGNED | ◻ 🔗 `actions.id` | ON DELETE NO ACTION |
| `created_at` | TIMESTAMP | ◻ | |
| `updated_at` | TIMESTAMP | ◻ | |

---

### `follow_ups`
Follow-up records tied to a to-do and/or contact.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | 🔑 AUTO_INCREMENT | |
| `followup_date` | DATE | ❗ | |
| `followup_time` | TIME | ◻ | |
| `followup_remark` | VARCHAR(800) | ❗ | |
| `todo_id` | BIGINT UNSIGNED | ◻ 🔗 `to_dos.id` | ON DELETE CASCADE |
| `contact_id` | BIGINT UNSIGNED | ◻ 🔗 `contacts.id` | ON DELETE CASCADE |
| `user_id` | BIGINT UNSIGNED | ◻ 🔗 `users.id` | ON DELETE NO ACTION |
| `task_id` | BIGINT UNSIGNED | ◻ 🔗 `tasks.id` | ON DELETE NO ACTION |
| `status_id` | BIGINT UNSIGNED | ◻ 🔗 `contact_statuses.id` | ON DELETE NO ACTION |
| `type_id` | BIGINT UNSIGNED | ◻ 🔗 `contact_types.id` | ON DELETE NO ACTION |
| `priority_id` | BIGINT UNSIGNED | ◻ 🔗 `priorities.id` | ON DELETE NO ACTION |
| `created_at` | TIMESTAMP | ◻ | |
| `updated_at` | TIMESTAMP | ◻ | |

---

## Group 5 — Forecasts

### `forecast_products`
Lookup: products available in forecast entries.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | 🔑 AUTO_INCREMENT | |
| `name` | VARCHAR(255) | ❗ | |
| `created_at` | TIMESTAMP | ◻ | |
| `updated_at` | TIMESTAMP | ◻ | |

---

### `forecast_types`
Lookup: type of forecast (e.g. New, Renewal).

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | 🔑 AUTO_INCREMENT | |
| `name` | VARCHAR(255) | ❗ | |
| `created_at` | TIMESTAMP | ◻ | |
| `updated_at` | TIMESTAMP | ◻ | |

---

### `forecast_results`
Lookup: outcome/result of a forecast (e.g. Won, Lost, Pending).

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | 🔑 AUTO_INCREMENT | |
| `name` | VARCHAR(255) | ❗ | |
| `created_at` | TIMESTAMP | ◻ | |
| `updated_at` | TIMESTAMP | ◻ | |

---

### `forecasts`
Sales forecast entries per user/contact/product.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | 🔑 AUTO_INCREMENT | |
| `forecast_date` | DATE | ❗ | Expected closing date |
| `forecast_updatedate` | DATE | ❗ | Last result update date |
| `amount` | INT(11) | ❗ | Forecast value |
| `result_id` | BIGINT UNSIGNED | ◻ 🔗 `forecast_results.id` | ON DELETE NO ACTION |
| `user_id` | BIGINT UNSIGNED | ◻ 🔗 `users.id` | ON DELETE NO ACTION |
| `contact_id` | BIGINT UNSIGNED | ◻ 🔗 `contacts.id` | ON DELETE CASCADE |
| `product_id` | BIGINT UNSIGNED | ◻ 🔗 `forecast_products.id` | ON DELETE NO ACTION |
| `forecast_type_id` | BIGINT UNSIGNED | ◻ 🔗 `forecast_types.id` | ON DELETE NO ACTION |
| `contact_status_id` | BIGINT UNSIGNED | ◻ 🔗 `contact_statuses.id` | Snapshot at time of entry |
| `contact_type_id` | BIGINT UNSIGNED | ◻ 🔗 `contact_types.id` | Snapshot at time of entry |
| `created_at` | TIMESTAMP | ◻ | |
| `updated_at` | TIMESTAMP | ◻ | |

---

## Group 6 — Projects

### `projects`
Project records linked to a contact and owner user.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | 🔑 AUTO_INCREMENT | |
| `project_name` | VARCHAR(255) | ❗ | |
| `project_startdate` | DATE | ❗ | |
| `project_enddate` | DATE | ❗ | |
| `project_remark` | VARCHAR(800) | ❗ | |
| `user_id` | BIGINT UNSIGNED | ◻ 🔗 `users.id` | ON DELETE NO ACTION |
| `contact_id` | BIGINT UNSIGNED | ◻ 🔗 `contacts.id` | ON DELETE CASCADE |
| `created_at` | TIMESTAMP | ◻ | |
| `updated_at` | TIMESTAMP | ◻ | |

---

## Group 7 — Billboard

### `billboards`
Permanent billboard assets identified by site ID.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | 🔑 AUTO_INCREMENT | |
| `site_id` | VARCHAR(255) | ❗ | Unique site code |
| `bboard_location` | VARCHAR(255) | ❗ | Physical address/description |
| `bboard_size` | VARCHAR(255) | ❗ | Dimensions (e.g. 10x20) |
| `created_at` | TIMESTAMP | ◻ | |
| `updated_at` | TIMESTAMP | ◻ | |

---

### `billboard_tenures`
Tenure/booking periods per billboard, linked to client contact.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | 🔑 AUTO_INCREMENT | |
| `tenure_startdate` | DATE | ❗ | |
| `tenure_enddate` | DATE | ❗ | |
| `bboard_id` | BIGINT UNSIGNED | ◻ 🔗 `billboards.id` | ON DELETE CASCADE |
| `contact_id` | BIGINT UNSIGNED | ◻ 🔗 `contacts.id` | ON DELETE CASCADE |
| `user_id` | BIGINT UNSIGNED | ◻ 🔗 `users.id` | ON DELETE NO ACTION |
| `created_at` | TIMESTAMP | ◻ | |
| `updated_at` | TIMESTAMP | ◻ | |

---

## Group 8 — Tempboard

### `tempboards`
Temporary/event board orders per client.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | 🔑 AUTO_INCREMENT | |
| `tpboard_entrydate` | DATE | ❗ | Record entry date |
| `tpboard_startdate` | DATE | ❗ | Display start date |
| `tpboard_enddate` | DATE | ❗ | Display end date |
| `contact_id` | BIGINT UNSIGNED | ◻ 🔗 `contacts.id` | ON DELETE CASCADE |
| `user_id` | BIGINT UNSIGNED | ◻ 🔗 `users.id` | ON DELETE CASCADE |
| `tpboard_size` | VARCHAR(255) | ◻ | |
| `tpboard_location` | VARCHAR(255) | ◻ | |
| `tpboard_unit` | VARCHAR(255) | ◻ | Quantity |
| `tpboard_collection` | VARCHAR(255) | ◻ | Collection status/notes |
| `tpboard_material` | VARCHAR(255) | ◻ | |
| `tpboard_printing` | VARCHAR(255) | ◻ | |
| `tpboard_installation` | VARCHAR(255) | ◻ | |
| `tpboard_remark` | VARCHAR(800) | ◻ | |
| `created_at` | TIMESTAMP | ◻ | |
| `updated_at` | TIMESTAMP | ◻ | |

---

## Group 9 — Tracking General

### `bluedale_divisions`
Lookup: internal company divisions.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | 🔑 AUTO_INCREMENT | |
| `name` | VARCHAR(255) | ❗ | |
| `created_at` | TIMESTAMP | ◻ | |
| `updated_at` | TIMESTAMP | ◻ | |

---

### `tracking_generals`
Master tracking record for general advertising/media placements.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | 🔑 AUTO_INCREMENT | |
| `progress` | VARCHAR(255) | ◻ | Overall progress status |
| `general_startdate` | DATE | ◻ | |
| `general_enddate` | DATE | ◻ | |
| `division_id` | BIGINT UNSIGNED | ◻ 🔗 `bluedale_divisions.id` | |
| `user_id` | BIGINT UNSIGNED | ◻ 🔗 `users.id` | |
| `company_id` | BIGINT UNSIGNED | ◻ 🔗 `contacts.id` | Client contact |
| `contact_category_id` | BIGINT UNSIGNED | ◻ 🔗 `contact_categories.id` | |
| `category_description` | VARCHAR(255) | ◻ | |
| `art_frequency` | INT(11) | ◻ | Number of artwork WIP rows to auto-create |
| `general_type` | VARCHAR(255) | ◻ | |
| `general_amount` | INT(11) | ◻ | |
| `general_reach` | INT(11) | ◻ | |
| `general_tenure` | INT(11) | ◻ | Duration in days/months |
| `art_format` | VARCHAR(255) | ◻ | |
| `general_remark` | VARCHAR(255) | ◻ | |
| `created_at` | TIMESTAMP | ◻ | |
| `updated_at` | TIMESTAMP | ◻ | |

---

### `wip_generals`
Work-in-progress rows auto-generated from `tracking_generals.art_frequency`. Each row tracks one artwork cycle through multiple workflow stages.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | 🔑 AUTO_INCREMENT | |
| `tracking_general_id` | BIGINT UNSIGNED | ◻ 🔗 `tracking_generals.id` | ON DELETE CASCADE |
| `frequency_no` | INT(11) | ◻ | Which frequency cycle (1, 2, 3…) |
| `wip_remark` | VARCHAR(255) | ◻ | |
| `wip_progress` | VARCHAR(255) | ◻ | |
| `art_chase_date` | DATE | ◻ | Stage: Art Chase |
| `art_chase_done` | INT(11) | ◻ | 0 = pending, 1 = done |
| `art_chase_remark` | VARCHAR(255) | ◻ | |
| `art_chase_user_id` | BIGINT UNSIGNED | ◻ 🔗 `users.id` | Responsible user |
| `art_received_date` | DATE | ◻ | Stage: Art Received |
| `art_received_done` | INT(11) | ◻ | |
| `art_received_remark` | VARCHAR(255) | ◻ | |
| `art_received_user_id` | BIGINT UNSIGNED | ◻ 🔗 `users.id` | |
| `art_todo_date` | DATE | ◻ | Stage: Art To-Do |
| `art_todo_done` | INT(11) | ◻ | |
| `art_todo_remark` | VARCHAR(255) | ◻ | |
| `art_todo_user_id` | BIGINT UNSIGNED | ◻ 🔗 `users.id` | |
| `cns_sent_date` | DATE | ◻ | Stage: CNS Sent |
| `cns_sent_done` | INT(11) | ◻ | |
| `cns_sent_remark` | VARCHAR(255) | ◻ | |
| `cns_sent_user_id` | BIGINT UNSIGNED | ◻ 🔗 `users.id` | |
| `cns_record_date` | DATE | ◻ | Stage: CNS Record |
| `cns_record_done` | INT(11) | ◻ | |
| `cns_record_remark` | VARCHAR(255) | ◻ | |
| `cns_record_user_id` | BIGINT UNSIGNED | ◻ 🔗 `users.id` | |
| `schedule_date` | DATE | ◻ | Stage: Schedule |
| `schedule_done` | INT(11) | ◻ | |
| `schedule_remark` | VARCHAR(255) | ◻ | |
| `schedule_user_id` | BIGINT UNSIGNED | ◻ 🔗 `users.id` | |
| `actual_live_date` | DATE | ◻ | Stage: Actual Live |
| `actual_live_done` | INT(11) | ◻ | |
| `actual_live_remark` | VARCHAR(255) | ◻ | |
| `actual_live_user_id` | BIGINT UNSIGNED | ◻ 🔗 `users.id` | |
| `client_posting_date` | DATE | ◻ | Stage: Client Posting |
| `client_posting_done` | INT(11) | ◻ | |
| `client_posting_remark` | VARCHAR(255) | ◻ | |
| `client_posting_user_id` | BIGINT UNSIGNED | ◻ 🔗 `users.id` | |
| `report_date` | DATE | ◻ | Stage: Report |
| `report_done` | INT(11) | ◻ | |
| `report_remark` | VARCHAR(255) | ◻ | |
| `report_user_id` | BIGINT UNSIGNED | ◻ 🔗 `users.id` | |
| `created_at` | TIMESTAMP | ◻ | |
| `updated_at` | TIMESTAMP | ◻ | |

---

## Group 10 — Tracking Travel Guide

### `travel_guide_packages`
Lookup: available travel guide ad packages.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | 🔑 AUTO_INCREMENT | |
| `name` | VARCHAR(255) | ❗ | |
| `created_at` | TIMESTAMP | ◻ | |
| `updated_at` | TIMESTAMP | ◻ | |

---

### `tracking_travel_guides`
Master tracking record for travel guide placements.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | 🔑 AUTO_INCREMENT | |
| `user_id` | BIGINT UNSIGNED | ◻ 🔗 `users.id` | ON DELETE CASCADE |
| `company_id` | BIGINT UNSIGNED | ◻ 🔗 `contacts.id` | Client contact. ON DELETE CASCADE |
| `edition` | VARCHAR(255) | ◻ | Publication edition |
| `tguide_size` | VARCHAR(255) | ◻ | Ad size in travel guide |
| `tguide_remark` | VARCHAR(255) | ◻ | |
| `art_reminder_date` | DATE | ◻ | Stage: Art Reminder date |
| `art_reminder_remark` | VARCHAR(255) | ◻ | |
| `art_reminder_done` | INT(11) | ◻ | 0/1 flag |
| `art_reminder_user_id` | BIGINT UNSIGNED | ◻ 🔗 `users.id` | ON DELETE CASCADE |
| `art_record_date` | DATE | ◻ | Stage: Art Record date |
| `art_record_remark` | VARCHAR(255) | ◻ | |
| `art_record_done` | INT(11) | ◻ | |
| `art_record_user_id` | BIGINT UNSIGNED | ◻ 🔗 `users.id` | ON DELETE CASCADE |
| `created_at` | TIMESTAMP | ◻ | |
| `updated_at` | TIMESTAMP | ◻ | |

---

### `wip_travel_guides`
WIP package rows per travel guide tracking record.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | 🔑 AUTO_INCREMENT | |
| `tracking_tguide_id` | BIGINT UNSIGNED | ◻ 🔗 `tracking_travel_guides.id` | ON DELETE CASCADE |
| `wip_package_name` | VARCHAR(255) | ◻ | Package name (free text or from lookup) |
| `wip_package_date` | DATE | ◻ | |
| `wip_package_done` | INT(11) | ◻ | 0/1 flag |
| `wip_package_user_id` | BIGINT UNSIGNED | ◻ 🔗 `users.id` | ON DELETE CASCADE |
| `wip_package_remark` | VARCHAR(255) | ◻ | |
| `created_at` | TIMESTAMP | ◻ | |
| `updated_at` | TIMESTAMP | ◻ | |

---

## Group 11 — Performance

### `performance_targets`
Per-user action targets set by admins or supervisors.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | 🔑 AUTO_INCREMENT | |
| `user_id` | BIGINT UNSIGNED | ◻ 🔗 `users.id` | ON DELETE CASCADE |
| `action_id` | BIGINT UNSIGNED | ◻ 🔗 `actions.id` | ON DELETE CASCADE |
| `action_target` | INT(11) | ❗ | Target count for this action |
| `target_remark` | VARCHAR(255) | ◻ | |
| `created_at` | TIMESTAMP | ◻ | |
| `updated_at` | TIMESTAMP | ◻ | |

---

### `user_category_benchmarks`
Default action targets per user category (used as benchmark baselines).

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | 🔑 AUTO_INCREMENT | |
| `user_cat_id` | BIGINT UNSIGNED | ◻ 🔗 `user_categories.id` | ON DELETE CASCADE |
| `action_id` | BIGINT UNSIGNED | ◻ 🔗 `actions.id` | ON DELETE CASCADE |
| `action_target` | INT(11) | ❗ | Benchmark target for this action/category combo |
| `created_at` | TIMESTAMP | ◻ | |
| `updated_at` | TIMESTAMP | ◻ | |

---

## Group 12 — Announcements

### `announcements`
Internal messages/announcements between users.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | 🔑 AUTO_INCREMENT | |
| `message` | TEXT | ❗ | |
| `message_type_id` | INT(11) | ❗ | ⚠️ No FK constraint defined — type is an orphaned int |
| `from_user_id` | BIGINT UNSIGNED | ◻ 🔗 `users.id` | ON DELETE CASCADE |
| `to_user_id` | BIGINT UNSIGNED | ◻ 🔗 `users.id` | ON DELETE CASCADE — NULL = broadcast |
| `created_at` | TIMESTAMP | ◻ | |
| `updated_at` | TIMESTAMP | ◻ | |

---

## Group 13 — Laravel System Tables (CRM App)

These are Laravel framework tables — not business entities, but required for the app to function.

### `migrations`
Tracks which database migrations have been run.

| Column | Type | Notes |
|---|---|---|
| `id` | INT UNSIGNED 🔑 | |
| `migration` | VARCHAR(255) | Migration filename |
| `batch` | INT | Batch number |

---

### `failed_jobs`
Queue jobs that failed during background processing.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED 🔑 | |
| `uuid` | VARCHAR(255) UNIQUE | |
| `connection` | TEXT | |
| `queue` | TEXT | |
| `payload` | LONGTEXT | |
| `exception` | LONGTEXT | |
| `failed_at` | TIMESTAMP | Defaults to NOW() |

---

### `password_resets`
Temporary tokens for password reset emails.

| Column | Type | Notes |
|---|---|---|
| `email` | VARCHAR(255) | Indexed |
| `token` | VARCHAR(255) | |
| `created_at` | TIMESTAMP | |

---

### `personal_access_tokens`
Sanctum API tokens for any tokenable model.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED 🔑 | |
| `tokenable_type` | VARCHAR(255) | Polymorphic model class |
| `tokenable_id` | BIGINT UNSIGNED | Polymorphic model ID |
| `name` | VARCHAR(255) | Token label |
| `token` | VARCHAR(64) UNIQUE | Hashed token value |
| `abilities` | TEXT | JSON array of granted abilities |
| `last_used_at` | TIMESTAMP | |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

---

## Group 14 — WordPress Tables (Shared Database)

These `wp_*` tables belong to a WordPress installation co-hosted in the same database. They are **not part of the CRM application** and should be migrated/kept separately.

| Table | Purpose |
|---|---|
| `wp_users` | WordPress user accounts |
| `wp_usermeta` | Key-value metadata per WP user |
| `wp_posts` | Posts, pages, custom post types |
| `wp_postmeta` | Post metadata |
| `wp_comments` | Post comments |
| `wp_commentmeta` | Comment metadata |
| `wp_terms` | Taxonomy terms |
| `wp_term_taxonomy` | Term–taxonomy associations |
| `wp_term_relationships` | Object–term links |
| `wp_termmeta` | Term metadata |
| `wp_options` | Site-wide settings (key-value) |
| `wp_links` | Blogroll links (legacy) |

---

## Known Issues & Upgrade Notes

| Table / Column | Issue | Priority |
|---|---|---|
| `users.email_password` | Stores plaintext email credential — must be removed or encrypted | P0 |
| `contact_text_colors` | No surrogate primary key (`id`) — makes ORM operations awkward | P1 |
| `announcements.message_type_id` | INT column with no FK constraint — type lookup table is missing | P1 |
| `wip_generals` (all `*_done` columns) | Stored as `INT(11)` instead of `TINYINT(1)` / boolean — inconsistent | P2 |
| `tracking_generals` / `wip_generals` | No explicit FK from `tracking_generals.company_id` to `contacts` defined in constraints (index exists but constraint absent in dump) | P1 |
| `wip_travel_guides.wip_package_name` | Free text instead of FK to `travel_guide_packages.id` — lookup table is unused | P1 |
| `contact_text_colors` | Pivot has no composite PK declared — allows duplicate color rows per contact | P1 |
| WordPress `wp_*` tables | Co-hosted in the same CRM database — should be separated into its own schema | P1 |
