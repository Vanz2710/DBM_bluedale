# Test Cases Context — Library CRM v2 (BGOC CRM)

This document is the complete reference for generating test cases for the BGOC CRM system.
Bring it to Claude Web and ask: "Generate a comprehensive Word document of test cases from this context."

---

## 1. System Overview

**Application type:** Single-Page Application (SPA) CRM  
**Backend:** Laravel 13.7 + PHP 8.3, Sanctum token auth, Spatie RBAC  
**Frontend:** Vue 3 (Composition API, `<script setup>`), Vue Router 5  
**Database:** MySQL/MariaDB  
**Authentication:** Token-based (Bearer token stored in `localStorage` as `crm_token`)  
**Base URL (dev):** `http://localhost/library_crm_v2/public/`  
**API prefix:** `/api/v1/`

---

## 2. User Roles & Permissions

### Roles (from highest to lowest access)

| Role | Description |
|---|---|
| `super-admin` | Bypasses all permission checks — full system access including RBAC |
| `admin` | Full CRM access + admin tools; cannot manage roles/permissions/users |
| `supervisor` | Full user access + data-health, import, email campaigns |
| `user` | Standard day-to-day CRM work + common marketing tools |
| `internal support` | View + create/edit contacts/todos/followups/deals/projects; no delete, no admin |
| `viewer` | Read-only across all CRM resources |

### Permission Matrix

| Permission | super-admin | admin | supervisor | user | internal support | viewer |
|---|---|---|---|---|---|---|
| view contacts | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| create contacts | ✓ | ✓ | ✓ | ✓ | ✓ | ✗ |
| edit contacts | ✓ | ✓ | ✓ | ✓ | ✓ | ✗ |
| delete contacts | ✓ | ✓ | ✓ | ✓ | ✗ | ✗ |
| view todos | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| create todos | ✓ | ✓ | ✓ | ✓ | ✓ | ✗ |
| edit todos | ✓ | ✓ | ✓ | ✓ | ✓ | ✗ |
| delete todos | ✓ | ✓ | ✓ | ✓ | ✗ | ✗ |
| view deals | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| create deals | ✓ | ✓ | ✓ | ✓ | ✗ | ✗ |
| edit deals | ✓ | ✓ | ✓ | ✓ | ✗ | ✗ |
| delete deals | ✓ | ✓ | ✓ | ✓ | ✗ | ✗ |
| view forecasts | ✓ | ✓ | ✓ | ✓ | ✗ | ✓ |
| create/edit/delete forecasts | ✓ | ✓ | ✓ | ✓ | ✗ | ✗ |
| view projects | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| create/edit/delete projects | ✓ | ✓ | ✓ | ✓ | ✗ | ✗ |
| view followups | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| create/edit/delete followups | ✓ | ✓ | ✓ | ✓ | ✓ | ✗ |
| import contacts | ✓ | ✓ | ✓ | ✗ | ✗ | ✗ |
| view analytics | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| view summary | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| view performance | ✓ | ✓ | ✓ | ✓ | ✗ | ✗ |
| view data-health | ✓ | ✓ | ✓ | ✗ | ✗ | ✗ |
| manage lookups | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ |
| manage social-media | ✓ | ✓ | ✓ | ✓ | ✗ | ✗ |
| manage posting-calendar | ✓ | ✓ | ✓ | ✓ | ✗ | ✗ |
| manage email-campaigns | ✓ | ✓ | ✓ | ✗ | ✗ | ✗ |
| manage site-availability | ✓ | ✓ | ✓ | ✓ | ✗ | ✗ |
| manage dept-tasks | ✓ | ✓ | ✓ | ✓ | ✗ | ✗ |
| manage roles | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ |
| manage permissions | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ |
| manage users | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ |

---

## 3. Authentication Module

### 3.1 Login Flow

**Endpoint:** `POST /api/auth/login`  
**Public:** Yes (throttled: 10 requests/minute)  
**Fields:** `username` (required), `password` (required)

**Login outcome states (in order of evaluation):**

1. **Wrong credentials** → 422 Unprocessable Entity; `"The provided credentials are incorrect."`
   - Note: dummy bcrypt is run even for non-existent usernames (prevents username enumeration)

2. **Permanently locked** → 403; `status: "permanently_locked"`
   - Triggered after 9+ failed attempts; only admin can clear via unlock endpoint

3. **Temporarily locked** → 429; `status: "temporarily_locked"` + minutes remaining
   - Level 1: locked 15 minutes after 3 failed attempts
   - Level 2: locked 1 hour after 6 failed attempts
   - Level 3: permanently locked after 9 failed attempts + admin notified via email

4. **Account not approved** (`is_approved = false`) → 403; `status: "pending_approval"`
   - On first attempt: sets `access_requested_at`, sends `UserPendingApproval` email to admin

5. **Inactivity flagged** (`inactivity_flagged_at` is set) → 403; `status: "inactivity_flagged"`
   - Admin must call `PUT /api/v1/rbac/users/{id}/restore-access` to clear

6. **First login after 14+ days of inactivity** → sets `inactivity_flagged_at`, sends `InactivityLoginAlert` to admin → 403; `status: "inactivity_flagged"`

7. **First ever login** (`login_count === 0`) → sends `FirstLoginAlert` email to admin → **proceeds normally** (not blocked)

8. **Success** → 200; returns `{ token, user: { id, name, username, email, roles[], permissions[], last_login_at, login_count } }`

**On success:**
- `login_count` incremented, `last_login_at` updated
- Failed login counters reset if previously had attempts

### 3.2 Logout

**Endpoint:** `POST /api/auth/logout` (requires auth)  
**Action:** Deletes current Sanctum token only

### 3.3 Admin Notification Routing

- Checks `SystemSetting: admin_notification_email` first
- Falls back to all users with `admin` or `super-admin` role

---

## 4. Contacts Module

### 4.1 Contact List

**Endpoint:** `GET /api/v1/contacts`  
**Permission:** `view contacts`

**Filters:**
- `search` / `q` — name search (LIKE)
- `status_id`, `industry_id`, `category_id`, `type_id` — dropdown filters
- `user_id` — filter by owner; `user=unassigned` shows contacts with no owner
- `sort_by` — `name` (default), `created_at`, `updated_at`
- `sort_dir` — `asc` (default), `desc`
- `per_page` — max 500, default 100

**Response includes:** `can_edit` flag per contact
- `true` for admins/super-admins always
- For regular users: `true` if they own the contact OR have a `ContactEditGrant` for the owner

### 4.2 Contact CRUD

| Action | Endpoint | Permission |
|---|---|---|
| List | `GET /api/v1/contacts` | `view contacts` |
| Show | `GET /api/v1/contacts/{id}` | `view contacts` |
| Create | `POST /api/v1/contacts` | `create contacts` |
| Update | `PUT/PATCH /api/v1/contacts/{id}` | `edit contacts` |
| Delete | `DELETE /api/v1/contacts/{id}` | `delete contacts` |
| Toggle closed | `PATCH /api/v1/contacts/{id}/closed` | `edit contacts` |
| Merge | `POST /api/v1/contacts/merge` | `edit contacts` |
| Export CSV | `GET /api/v1/contacts/export` | `view contacts` |
| Check duplicate | `GET /api/v1/contacts/check-duplicate` | `view contacts` |
| Daily report | `GET /api/v1/contacts/daily` | `view contacts` |

### 4.3 Contact Sub-Resources

All reads require `view contacts`; writes require `edit contacts`.

| Sub-Resource | Endpoints |
|---|---|
| In-charges | `GET/POST /contacts/{id}/incharges`, `PUT/DELETE /contacts/{id}/incharges/{incharge}` |
| Todos (contact-scoped) | `GET/POST /contacts/{id}/todos`, `PUT/DELETE /contacts/{id}/todos/{todo}` |
| Emails | `GET/POST /contacts/{id}/emails`, `DELETE /contacts/{id}/emails/{email}` |
| Calls | `GET/POST /contacts/{id}/calls`, `DELETE /contacts/{id}/calls/{call}` |

---

## 5. To-Do Module (Global)

**Endpoint prefix:** `/api/v1/todos`  
**Permission:** `view todos` / `create todos` / `edit todos` / `delete todos`

| Action | Endpoint | Permission |
|---|---|---|
| List | `GET /todos` | `view todos` |
| Show | `GET /todos/{id}` | `view todos` |
| Create | `POST /todos` | `create todos` |
| Update | `PUT /todos/{id}` | `edit todos` |
| Update status | `PATCH /todos/{id}/status` | `edit todos` |
| Delete | `DELETE /todos/{id}` | `delete todos` |
| Export CSV | `GET /todos/export` | `view todos` |
| Active dates | `GET /todos/active-dates` | `view todos` |

**Status values:** `pending`, `completed`, `cancelled`

---

## 6. Follow-Ups Module

**Endpoint prefix:** `/api/v1/followups`  
**Permission:** `view followups` / `create followups` / `edit followups` / `delete followups`

**Key rule:** Follow-ups are linked to a ToDo via `todo_id`. FollowUp has no direct `user_id` — user ownership is via the parent ToDo's `user_id`.

| Action | Endpoint | Permission |
|---|---|---|
| List | `GET /followups` | `view followups` |
| Show | `GET /followups/{id}` | `view followups` |
| Create | `POST /followups` | `create followups` |
| Update | `PUT /followups/{id}` | `edit followups` |
| Update status | `PATCH /followups/{id}/status` | `edit followups` |
| Delete | `DELETE /followups/{id}` | `delete followups` |
| Export CSV | `GET /followups/export` | `view followups` |

---

## 7. Deals Module

**Endpoint prefix:** `/api/v1/deals`  
**Permission:** `view deals` / `create deals` / `edit deals` / `delete deals`

**Deal stages:** `New Lead`, `Contacted`, `Quotation Sent`, `Negotiation`, `Won`, `Lost`  
**Deal status:** `open`, `won`, `lost`

**Visibility rule:**
- Regular users see only their own deals
- Admins see all deals; can filter by `user_id`

| Action | Endpoint | Permission |
|---|---|---|
| List | `GET /deals` | `view deals` |
| Summary | `GET /deals/summary` | `view deals` |
| Show | `GET /deals/{id}` | `view deals` |
| Create | `POST /deals` | `create deals` |
| Update | `PUT /deals/{id}` | `edit deals` |
| Delete | `DELETE /deals/{id}` | `delete deals` |
| Export CSV | `GET /deals/export` | `view deals` |

**Filters:** `q` (search), `stage`, `status`, `user_id` (admin only), `from_date`, `to_date`  
**Sorts:** `title`, `stage`, `value`, `probability`, `expected_close_date`, `created_at`

---

## 8. Projects Module

**Endpoint prefix:** `/api/v1/projects`  
**Permission:** `view projects` / `create projects` / `edit projects` / `delete projects`

| Action | Endpoint | Permission |
|---|---|---|
| List | `GET /projects` | `view projects` |
| Show | `GET /projects/{id}` | `view projects` |
| Remark | `GET /projects/{id}/remark` | `view projects` |
| Create | `POST /projects` | `create projects` |
| Update | `PUT /projects/{id}` | `edit projects` |
| Delete | `DELETE /projects/{id}` | `delete projects` |
| Export CSV | `GET /projects/export` | `view projects` |

---

## 9. Forecasts Module

**Endpoint prefix:** `/api/v1/forecasts`  
**Permissions:** `view forecasts`, `create forecasts`, `edit forecasts`, `delete forecasts`, `view forecast summary`

| Action | Endpoint | Permission |
|---|---|---|
| List | `GET /forecasts` | `view forecasts` |
| Summary | `GET /forecasts/summary` | `view forecast summary` |
| Show | `GET /forecasts/{id}` | `view forecasts` |
| Create | `POST /forecasts` | `create forecasts` |
| Update | `PUT /forecasts/{id}` | `edit forecasts` |
| Delete | `DELETE /forecasts/{id}` | `delete forecasts` |

---

## 10. Performance Module

**Endpoint prefix:** `/api/v1/performance`  
**Permission:** `view performance`

| Endpoint | Description |
|---|---|
| `GET /performance/overview` | KPI counts for a period (week/month/year/range), overdue items, target progress |
| `GET /performance/team` | Admin-only cross-user comparison table |
| `GET /performance/report` | Legacy task-based report |
| `GET /performance/targets/{userId}` | Fetch legacy targets for a user |
| `PUT /performance/targets/{userId}` | Update legacy targets |
| `GET /performance/kpi-targets/{userId}` | Fetch KPI targets |
| `PUT /performance/kpi-targets/{userId}` | Update KPI targets |

**KPI metrics:** `new_contacts`, `todos_completed`, `followups_completed`, `projects_created`, `deals_created`, `deals_won`, `won_deal_value`

---

## 11. Department Task Manager Module

**Endpoint prefix:** `/api/v1/dept/`  
**Permission:** `manage dept-tasks`

### Task Statuses
`pending` → `in_progress` → `waiting_approval` → `completed` / `cancelled`

### Endpoints

| Action | Endpoint |
|---|---|
| Dashboard stats | `GET /dept/dashboard` |
| List departments | `GET /dept/departments` |
| List users | `GET /dept/users` |
| Weekly trend | `GET /dept/weekly` |
| Report | `GET /dept/report` |
| Notifications | `GET /dept/notifications` |
| Mark notifications read | `POST /dept/notifications/read` |
| List tasks | `GET /dept/tasks` |
| Create task | `POST /dept/tasks` |
| Show task | `GET /dept/tasks/{id}` |
| Update task | `PUT /dept/tasks/{id}` |
| Delete task | `DELETE /dept/tasks/{id}` |
| Update status | `PUT /dept/tasks/{id}/status` |
| Add comment | `POST /dept/tasks/{id}/comments` |
| Delete comment | `DELETE /dept/tasks/{id}/comments/{commentId}` |
| Upload attachment | `POST /dept/tasks/{id}/attachments` |
| Delete attachment | `DELETE /dept/tasks/{id}/attachments/{attachmentId}` |

**Dashboard user_id filter rule:**
- Admin/super-admin can pass any `user_id`
- Regular user can only pass their own `user_id` (others silently ignored)

---

## 12. User Management Module (Admin Only)

**Permission:** `manage users`

| Endpoint | Description |
|---|---|
| `GET /rbac/users` | List all users (supports `q` search, `include_deleted` flag) |
| `GET /rbac/users/pending` | List unapproved users who have requested access |
| `POST /rbac/users` | Create a new user (auto-approved, auto-verified) |
| `PUT /rbac/users/{id}` | Update user profile |
| `DELETE /rbac/users/{id}` | Soft-delete user |
| `POST /rbac/users/{id}/restore` | Restore soft-deleted user |
| `PUT /rbac/users/{id}/roles` | Sync user roles |
| `PUT /rbac/users/{id}/approve` | Approve pending user |
| `PUT /rbac/users/{id}/restore-access` | Clear `inactivity_flagged_at` |
| `PUT /rbac/users/{id}/unlock` | Clear `permanently_locked` + reset lockout counters |

**User creation validation:**
- `name`: required, max 255
- `username`: required, max 50, unique, alphanumeric + underscore only (`/^[a-zA-Z0-9_]+$/`)
- `email`: nullable, unique if provided
- `password`: min 8, mixed case + numbers + symbols, confirmed
- `role`: optional, must exist in roles table
- New users are auto-set: `is_approved = true`, `approved_at = now()`, `email_verified_at = now()`

---

## 13. RBAC Module (Super-Admin Only)

### Roles (`manage roles`)
| Endpoint | Description |
|---|---|
| `GET /rbac/roles` | List all roles |
| `POST /rbac/roles` | Create role |
| `PUT /rbac/roles/{id}` | Update role |
| `DELETE /rbac/roles/{id}` | Delete role |
| `PUT /rbac/roles/{id}/permissions` | Sync permissions to role |

### Permissions (`manage permissions`)
| Endpoint | Description |
|---|---|
| `GET /rbac/permissions` | List all permissions |
| `POST /rbac/permissions` | Create permission |
| `PUT /rbac/permissions/{id}` | Update permission |
| `DELETE /rbac/permissions/{id}` | Delete permission |

---

## 14. Profile Module (Own User — No Special Permission)

| Endpoint | Description |
|---|---|
| `GET /v1/profile` | Get own profile |
| `PUT /v1/profile` | Update own name/email |
| `PUT /v1/profile/password` | Change own password (triggers SystemAlert to all admins) |

---

## 15. Session Module (Own Tokens Only)

| Endpoint | Description |
|---|---|
| `GET /v1/sessions` | List own active sessions |
| `DELETE /v1/sessions/{id}` | Revoke a specific session |
| `DELETE /v1/sessions/all` | Revoke all sessions (logout everywhere) |

---

## 16. Admin Lookup Management

**Permission:** `manage lookups`  
**Endpoint:** `GET/POST /api/v1/admin/{entity}`, `PUT/DELETE /api/v1/admin/{entity}/{id}`

**Lookup entities:** `statuses`, `types`, `categories`, `industries`, `areas`, `tasks`

**Public lookups (all authenticated users):** `GET /api/v1/lookups` — returns all dropdown reference data

---

## 17. Marketing Modules

### Social Media Reminders
**Permission:** `manage social-media`  
**Endpoints:** `GET/POST/PUT/DELETE /api/v1/social-media-reminders`

### Posting Calendar
**Permission:** `manage posting-calendar`  
**Endpoints:** `GET/POST /api/v1/posting-calendar`, `PUT/DELETE /api/v1/posting-calendar/{id}`

### Email Campaigns
**Permission:** `manage email-campaigns`

| Endpoint | Description |
|---|---|
| `GET /email-campaigns` | List campaigns |
| `POST /email-campaigns` | Create campaign |
| `PUT /email-campaigns/{id}` | Update campaign |
| `DELETE /email-campaigns/{id}` | Delete campaign |
| `POST /email-campaigns/{id}/duplicate` | Duplicate a campaign |
| `POST /email-campaigns/{id}/send` | Send campaign immediately |
| `POST /email-campaigns/{id}/schedule` | Schedule campaign send |
| `POST /email-campaigns/{id}/send-test` | Send test email |
| `GET /email-campaigns/{id}/recipients` | List campaign recipients |
| `GET/POST/PUT/DELETE /email-templates` | Template CRUD |
| `POST /email-images` | Upload image for email body |
| `GET /email-settings` | Get campaign SMTP/from settings |
| `PUT /email-settings` | Update campaign SMTP/from settings |
| `POST /email-settings/test` | Send test using saved settings |
| `GET /email-contacts` | List email contacts |
| `POST /email-contacts` | Add single contact |
| `POST /email-contacts/bulk` | Add multiple contacts |
| `POST /email-contacts/import` | Import contacts from CSV |
| `GET /email-contacts/export` | Export contacts to CSV |
| `POST /email-contacts/sync-crm` | Sync contacts from CRM |
| `PUT /email-contacts/{id}` | Update email contact |
| `DELETE /email-contacts/{id}` | Delete email contact |
| `GET/POST/PUT/DELETE /email-tags` | Email contact tag CRUD |
| `GET /email-groups` | List audience groups |
| `POST /email-groups` | Create audience group |
| `POST /email-groups/preview` | Preview group member count |
| `GET /email-groups/{id}/members` | List members of a group |
| `PUT /email-groups/{id}` | Update audience group |
| `DELETE /email-groups/{id}` | Delete audience group |
| `GET /email-analytics/dashboard` | Analytics dashboard totals |
| `GET /email-analytics` | Per-campaign analytics |

---

## 18. Site Availability Module

**Permission:** `manage site-availability`

| Endpoint | Description |
|---|---|
| `GET /site-availability` | List all sites/bookings |
| `POST /site-availability` | Create availability entry |
| `POST /site-availability/proposal` | Generate PDF proposal |
| `POST /site-availability/products` | Create product |
| `PUT /site-availability/products/{id}` | Update product |
| `POST /site-availability/products/{id}/photo` | Upload product photo |
| `DELETE /site-availability/products/{id}/photo` | Delete product photo |
| `POST /site-availability/products/{id}/confirm` | Confirm product |
| `DELETE /site-availability/products/{id}` | Discard (delete) product |
| `PUT /site-availability/bookings/{id}` | Update booking |
| `DELETE /site-availability/bookings/{id}` | Delete booking |
| `POST /site-availability/resolve-maps-url` | Resolve Google Maps short URL |

---

## 19. Analytics & Reporting Modules

| Module | Endpoint | Permission |
|---|---|---|
| Analytics dashboard | `GET /analytics` | `view analytics` |
| CRM summary report | `GET /summary` | `view summary` |
| Data health audit | `GET /data-health` | `view data-health` |
| Contact analysis — overview | `GET /contact-analysis/overview` | `view contacts` |
| Contact analysis — lead source | `GET /contact-analysis/lead-source` | `view contacts` |
| Contact analysis — status distribution | `GET /contact-analysis/status-distribution` | `view contacts` |
| Contact analysis — engagement | `GET /contact-analysis/engagement` | `view contacts` |
| Predictive — summary | `GET /predictive/summary` | `view contacts` |
| Predictive — forecast | `GET /predictive/forecast` | `view contacts` |
| Predictive — at-risk | `GET /predictive/at-risk` | `view contacts` |
| Predictive — pace | `GET /predictive/pace` | `view contacts` |
| Predictive — overdue risk | `GET /predictive/overdue-risk` | `view contacts` |
| Predictive — deals | `GET /predictive/deals` | `view contacts` |
| Predictive — win rates | `GET /predictive/win-rates` | `view contacts` |
| Predictive — deal velocity | `GET /predictive/deal-velocity` | `view contacts` |
| Predictive — pipeline coverage | `GET /predictive/pipeline-coverage` | `view contacts` |

---

## 20. Admin-Only Pages

| Module | Endpoint | Who Can Access |
|---|---|---|
| System Settings | `GET/PUT /system-settings` | super-admin (`manage users`) |
| User Activity | `GET /user-activity/overview` | super-admin (`manage users`) |
| Security Events | `GET /user-activity/security-events` | super-admin (`manage users`) |
| Audit Log | `GET /admin/audit-log` | super-admin (`manage users`) |
| Contact Edit Grants | `GET/POST/DELETE /contact-edit-grants` | super-admin (`manage users`) |
| Contact Duplicates | `GET /contacts/find-duplicates`, `POST /contacts/merge` | admin + super-admin (role check in controller) |
| Announcements (manage) | `GET /announcements/admin/all`, `POST/PUT/DELETE /announcements/{id}` | admin + super-admin (role middleware) |

---

## 21. Import Module

**Permission:** `import contacts`

| Endpoint | Description |
|---|---|
| `POST /import/preview` | Preview CSV import (validate + show what will be imported) |
| `POST /import/process` | Execute the import |

---

## 22. Announcements Module

Announcements are company-wide or targeted broadcast messages that appear in every user's notification bell and on the Notice Board page.

### User-Facing (all authenticated users)

| Endpoint | Description |
|---|---|
| `GET /announcements` | List active announcements visible to this user (respects `target_user_id` filter + expiry) |
| `POST /announcements/{id}/read` | Mark one announcement as read (idempotent) |

**Response item fields:** `id`, `title`, `body`, `urgency` (`normal`/`urgent`), `published_at` (formatted string), `expires_at`, `author`, `is_read`

### Admin Management (admin + super-admin)

| Endpoint | Description |
|---|---|
| `GET /announcements/admin/all` | Full list including drafts/scheduled — for the admin manage page |
| `POST /announcements` | Create announcement |
| `PUT /announcements/{id}` | Update announcement |
| `DELETE /announcements/{id}` | Delete announcement |

**Fields for create/update:** `title` (required, max 200), `body` (required), `urgency` (`normal`/`urgent`), `target_user_id` (nullable — null = everyone), `published_at` (nullable — null = publish immediately), `expires_at` (nullable — null = never expires)

**Admin response extra fields:** `target_user_id`, `target_user` (name), `reads_count`, `is_draft` (true if unpublished or scheduled for future)

### Business Rules
- `scopeActive` — only shows announcements where `published_at <= now()` and either `expires_at` is null or `expires_at > now()`
- Targeting: null `target_user_id` = visible to all; non-null = only that user sees it
- Read state is per-user via `announcement_reads` pivot table
- Urgent announcements display with a red accent and `URGENT` badge
- Announcements also appear in the notification bell (`source_type: "announcement"`) with unread count factored into the badge

---

## 23. Reminders & Notifications

**Endpoint:** `GET /api/v1/reminders`  
**Permission:** None (personal data only)

**Response shape:**
```json
{
  "overdue":            [...],   // pending todos/followups past due date (up to 14 days back)
  "today":              [...],   // pending todos/followups due today
  "upcoming":           [...],   // pending todos/followups due in next 7 days
  "expiring_sites":     [...],   // site bookings expiring within 30 days (all authenticated users)
  "alerts":             [...],   // unread SystemAlert rows — admin/super-admin only
  "task_notifications": [...],   // unread DeptNotification rows for this user
  "announcements":      [...],   // active announcements visible to this user (up to 10)
  "unread_count":       N        // total unread across all sections
}
```

**Item types in `source_type` field:**
- `todo` / `followup` — CRM reminders (overdue/today/upcoming)
- `site_expiry` — advertising booking nearing end date
- `alert` — in-app system alert (admin only)
- `task_notification` — dept task assignment/status notification
- `announcement` — company-wide broadcast message

**Admin filter:** Admins can pass `?user_id={id}` to view another user's CRM reminders (overdue/today/upcoming only — alerts, task_notifications, and announcements always reflect the requesting user).

**Mark read:** `POST /api/v1/reminders/read`  
Body: `{ "items": [{ "type": "todo|followup|alert|task_notification|announcement", "id": N }] }`

**Notice Board (user-facing announcements page):**  
`GET /api/v1/announcements` — returns active announcements visible to the current user with `is_read` flag.  
`POST /api/v1/announcements/{id}/read` — mark one announcement as read.

---

## 24. Public Endpoints (No Auth Required)

| Endpoint | Description |
|---|---|
| `POST /api/auth/login` | Login (throttled 10/min) |
| `POST /api/public/lead` | Public lead capture form (throttled 10/min) |
| `POST /api/auth/email/resend` | Resend verification email (throttled 6/min; currently unused) |

---

## 25. Frontend Routes & Pages

| Route Path | Page Component | Auth Required | Role/Permission |
|---|---|---|---|
| `/login` | Login.vue | No | Public |
| `/lead` | LeadForm.vue | No | Public |
| `/` | Dashboard.vue | Yes | Any authenticated |
| `/list` | ContactList.vue | Yes | `view contacts` |
| `/contacts/add` | ContactAdd.vue | Yes | `create contacts` |
| `/contacts/:id` | ContactView.vue | Yes | `view contacts` |
| `/contacts/:id/edit` | ContactEdit.vue | Yes | `edit contacts` |
| `/contacts/:id/task/add` | TaskAdd.vue | Yes | `edit contacts` |
| `/contact-analysis` | ContactAnalysis.vue | Yes | `view contacts` |
| `/predictive-insights` | PredictiveInsights.vue | Yes | `view contacts` |
| `/todos` | TodoList.vue | Yes | `view todos` |
| `/todos/add` | TodoAdd.vue | Yes | `create todos` |
| `/todos/:id/edit` | TodoEdit.vue | Yes | `edit todos` |
| `/followups` | FollowUpList.vue | Yes | `view followups` |
| `/followups/add` | FollowUpAdd.vue | Yes | `create followups` |
| `/followups/:id/edit` | FollowUpEdit.vue | Yes | `edit followups` |
| `/projects` | ProjectList.vue | Yes | `view projects` |
| `/projects/add` | ProjectAdd.vue | Yes | `create projects` |
| `/projects/:id/edit` | ProjectEdit.vue | Yes | `edit projects` |
| `/deals` | DealList.vue | Yes | `view deals` |
| `/deals/add` | DealAdd.vue | Yes | `create deals` |
| `/deals/:id/edit` | DealEdit.vue | Yes | `edit deals` |
| `/forecasts` | ForecastList.vue | Yes | `view forecasts` |
| `/forecasts/summary` | ForecastSummary.vue | Yes | `view forecast summary` |
| `/performance` | Performance.vue | Yes | `view performance` |
| `/data-health` | DataHealth.vue | Yes | `view data-health` |
| `/import` | Import.vue | Yes | `import contacts` |
| `/reminders` | Reminders.vue | Yes | Any authenticated |
| `/notice-board` | Noticeboard.vue | Yes | Any authenticated |
| `/reports` | Reports.vue | Yes | `view summary` |
| `/social-media` | SocialMediaReminder.vue | Yes | `manage social-media` |
| `/posting-calendar` | PostingCalendar.vue | Yes | `manage posting-calendar` |
| `/marketing-email` | EmailCampaigns.vue | Yes | `manage email-campaigns` |
| `/site-availability` | SiteAvailability.vue | Yes | `manage site-availability` |
| `/dept-tasks` | DeptTaskManager.vue | Yes | `manage dept-tasks` |
| `/profile` | MyProfile.vue | Yes | Any authenticated |
| `/settings` | Settings.vue | Yes | Any authenticated |
| `/admin` | AdminPanel.vue | Yes | Admin + super-admin |
| `/admin/rbac` | RbacPanel.vue | Yes | Admin + super-admin |
| `/admin/system-settings` | SystemSettings.vue | Yes | super-admin (`manage users`) |
| `/admin/user-activity` | UserActivity.vue | Yes | super-admin (`manage users`) |
| `/admin/audit-log` | AuditLog.vue | Yes | super-admin (`manage users`) |
| `/admin/contact-duplicates` | ContactDuplicates.vue | Yes | Admin + super-admin |
| `/admin/announcements` | Announcements.vue | Yes | Admin + super-admin |
| `/forbidden` | Forbidden.vue | Yes | Any authenticated |

---

## 26. Key Business Rules & Edge Cases

### Contact Ownership & Edit Access
- A contact belongs to exactly one user (`user_id`)
- Regular users can only edit contacts they own
- Admins can grant a user the ability to edit contacts owned by another specific user via `ContactEditGrant`
- Admins/super-admins can always edit any contact
- `can_edit` flag is returned per-contact in list responses

### Deal Visibility
- Regular users only see their own deals (enforced at DB query level, not just UI)
- Admins see all deals and can filter by `user_id`

### Brute-Force Protection (Login)
- 3 failed attempts → 15-minute lockout (level 1)
- 6 failed attempts → 1-hour lockout (level 2)
- 9 failed attempts → permanent lock (level 3) + admin email notification
- Only an admin can clear a permanent lock via `PUT /rbac/users/{id}/unlock`

### Inactivity Detection
- 14+ days since `last_login_at` → account flagged as inactive on next login attempt
- `inactivity_flagged_at` is set, `InactivityLoginAlert` email sent to admin
- Account is blocked until admin calls `PUT /rbac/users/{id}/restore-access`

### Email Verification — DISABLED
- All new users are auto-verified (`email_verified_at = now()`) at creation
- The `VerifyEmail.vue` page and `EmailVerificationController` still exist in code but are functionally unused

### CSV Exports
- All exports stream UTF-8 BOM (for Excel compatibility)
- Token passed as `?_token=` query param (because browser downloads bypass Axios)

### System Alerts (In-App)
- Created via `SystemAlert::notifyAdmins()` — creates one row per admin/super-admin user
- Appears in notification bell for admin users only
- Password changes create a SystemAlert for every admin

### User Settings & Dashboard Layout
- Stored server-side per user; fetched on app mount
- Cached in `localStorage` for offline/fast access

---

## 27. Test Accounts (Reference)

> Update these with actual test credentials before using.

| Role | Username | Notes |
|---|---|---|
| super-admin | (configured at setup) | Bypasses all permission checks |
| admin | (configured at setup) | Full CRM access, no RBAC management |
| supervisor | (configured at setup) | Full user access + import + data health |
| user | (configured at setup) | Standard CRM access |
| viewer | (configured at setup) | Read-only access |
| internal support | (configured at setup) | View + create/edit, no delete |

---

## 28. Test Environment Setup Notes

- **API base:** `http://localhost/library_crm_v2/public/api/`
- **Auth header:** `Authorization: Bearer {token}`
- **Content-Type:** `application/json` (or omit for FormData uploads)
- **Token storage:** `localStorage.crm_token`
- **User storage:** `localStorage.crm_user` (JSON with `roles[]` and `permissions[]`)
- Run `php artisan db:seed --class=RolesAndPermissionsSeeder` before testing RBAC
- Test DB: `bgoc_crm_test` on port 3307 (used by PHPUnit, not manual testing)
- For manual API testing, use the main DB: `bgoc_crm_newdb` on port 3307

---

*Generated: 2026-06-23 | System: BGOC Library CRM v2*
