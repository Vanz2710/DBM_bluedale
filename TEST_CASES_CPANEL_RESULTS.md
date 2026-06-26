# Test Execution Results — Library CRM v2 (cPanel Production)

> **Environment:** `crm.kltheguide.com.my` (bluedale.com.my cPanel, account `kltheguidecom`)
> **Deployed:** 2026-06-25
> **Test date:** 2026-06-26
> **Previous pass:** InfinityFree staging 2026-06-24 — all in-scope modules PASS (7 defects fixed).
> **This pass scope:** Full re-verification on cPanel + first-time coverage of **Deals, Projects, Import**.

---

## Test Run Summary

| Field | Value |
|---|---|
| System | BGOC Library CRM v2 (Laravel 13.7 + Vue 3 SPA) |
| Environment | `https://crm.kltheguide.com.my` — cPanel shared hosting (ea-php83, MySQL 3306, file session/cache, QUEUE=sync) |
| Data set | Production migrated data (15,265 contacts, 256 forecasts) |
| Primary tester role | super-admin |
| Test type | Functional UI walk-through |
| Overall result | **IN PROGRESS** |

---

## Phase 0 — Platform & Asset Checks (Do this FIRST)

These are cPanel-specific checks that have nothing to do with features. Failures here block everything else.

| Check | How to verify | Result | Notes |
|---|---|---|---|
| Site loads over HTTPS | Visit `https://crm.kltheguide.com.my` — should get green padlock, NO mixed-content warning | | |
| HTTP → HTTPS redirect | Visit `http://crm.kltheguide.com.my` — should auto-redirect to HTTPS | | |
| Login page renders with correct styles | Login form has blue logo, styled card, no raw HTML | | |
| No MIME-type console errors | Open DevTools → Console → Log in, navigate to Contacts — look for `Failed to load module script` or `"text/html"` errors. These = broken lazy chunks (wrong VITE_BASE_URL) | | |
| No 404s on JS/CSS assets | DevTools → Network → filter JS, CSS — all 200 or 304 | | |
| Vue DevTools detects Vue 3 | Optional but good to confirm the SPA mounted | | |
| `/up` health check | Visit `https://crm.kltheguide.com.my/up` — should return `{"status":"up"}` or OK | | |
| Page refresh works (SPA routing) | Navigate to Contacts, press F5 — should NOT get a 404 (the routing .htaccess handles this) | | |
| File uploads work (storage link) | Upload a file attachment on a dept task — should save and be downloadable | | |

---

## Phase 1 — Authentication

| Test Case | Result | Notes |
|---|---|---|
| Wrong credentials → error message | | |
| Successful login → dashboard loads | | |
| Bell icon loads after login | | |
| Logout → redirected to login | | |
| Brute-force lockout (3 bad attempts → 15-min lock message) | | |
| Unapproved account → "pending approval" message | | |
| First-ever login admin alert fires (check log: `storage/logs/laravel.log` — MAIL_MAILER=log) | | |

---

## Phase 2 — Contacts

| Test Case | Result | Notes |
|---|---|---|
| Contact list loads with data (15,265 records expected) | | |
| Search by company name | | |
| Filters: status / industry / category / type | | |
| Filter by user / unassigned | | |
| Sort + pagination | | |
| Create contact → saves → visible in list | | |
| Edit contact → saves changes | | |
| Delete contact | | |
| Export CSV → downloads file, opens in Excel | | |
| Open a contact → sub-tabs: To-Dos, Emails, Calls, In-Charges | | |

---

## Phase 3 — To-Dos (Global)

| Test Case | Result | Notes |
|---|---|---|
| Global To-Do list loads | | |
| Calendar shows active dates | | |
| Create to-do | | |
| Mark completed / cancelled | | |
| Export CSV | | |

---

## Phase 4 — Follow-Ups

| Test Case | Result | Notes |
|---|---|---|
| Follow-Up list loads | | |
| Type-to-search company picker in Add modal (fixed in InfinityFree pass) | | |
| Create follow-up linked to a to-do | | |
| Edit / complete / cancel / delete | | |
| Export CSV | | |

---

## Phase 5 — Deals *(first-time coverage)*

> These were excluded from the InfinityFree pass. Test from scratch.

| Test Case | Result | Notes |
|---|---|---|
| Deals list page loads | | |
| Create a deal (company, value, status, close date) | | |
| Edit a deal | | |
| Change deal status (open → won / lost) | | |
| Delete a deal | | |
| Deal shows on its linked Contact page | | |
| Filter / search deals | | |

---

## Phase 6 — Projects *(first-time coverage)*

> These were excluded from the InfinityFree pass. Test from scratch.

| Test Case | Result | Notes |
|---|---|---|
| Projects list page loads | | |
| Create a project | | |
| Edit a project | | |
| Delete a project | | |
| Project shows on its linked Contact page | | |

---

## Phase 7 — Forecasts

| Test Case | Result | Notes |
|---|---|---|
| Forecast list loads | | |
| Summary totals (Total / Confirmed / Pending) show RM values — NOT RM 0.00 (was bug #6) | | |
| Filters (product / type / result / user / date) | | |
| Create / Edit / Delete forecast | | |

---

## Phase 8 — Performance

| Test Case | Result | Notes |
|---|---|---|
| Overview tab — KPI cards, target progress | | |
| Activity tab | | |
| Team tab (admin cross-user) | | |
| Targets tab | | |

---

## Phase 9 — Department Tasks

| Test Case | Result | Notes |
|---|---|---|
| Dashboard stats load | | |
| Task list loads | | |
| Create task (assignee, dept, priority) | | |
| Status flow: pending → in_progress → waiting_approval → completed | | |
| Add comment | | |
| Upload attachment → download it back | | |
| Notifications bell shows task notifications | | |

---

## Phase 10 — Reminders & Notifications Bell

| Test Case | Result | Notes |
|---|---|---|
| Bell count shows unread | | |
| Overdue / Today / Upcoming sections | | |
| System alerts appear for admin | | |
| Mark read → item disappears | | |

---

## Phase 11 — Announcements / Notice Board

| Test Case | Result | Notes |
|---|---|---|
| User: Notice Board page loads active announcements | | |
| Admin: create announcement | | |
| Urgent badge shows | | |
| Mark read | | |
| Admin: edit / delete | | |

---

## Phase 12 — Marketing

| Test Case | Result | Notes |
|---|---|---|
| Social Media Reminders — list loads, create/edit/delete | | |
| Posting Calendar — loads, add/edit/delete entry | | |
| Email Campaigns — tabs load, dashboard shows | | |
| Site Availability — grid loads | | |

---

## Phase 13 — Analytics & Reporting

| Test Case | Result | Notes |
|---|---|---|
| Analytics dashboard (charts render) | | |
| CRM Summary report | | |
| Data Health audit | | |
| Contact Analysis tabs | | |
| Predictive Insights | | |

---

## Phase 14 — Admin Panel

| Test Case | Result | Notes |
|---|---|---|
| User Management — list loads | | |
| Create a new user | | |
| Edit user / assign role | | |
| System Settings — save admin_notification_email | | |
| Lookup Management — CRUD for statuses/types/categories | | |
| User Activity / Security Events | | |
| Audit Log | | |
| Contact Edit Grants | | |
| Contact Duplicates | | |

---

## Phase 15 — RBAC Panel (super-admin)

| Test Case | Result | Notes |
|---|---|---|
| Roles list loads | | |
| Create a role / sync permissions | | |
| Permissions list | | |

---

## Phase 16 — Profile & Sessions

| Test Case | Result | Notes |
|---|---|---|
| View / edit own profile | | |
| Change password → admin system alert fires | | |
| Sessions list shows active sessions | | |
| Revoke a session | | |

---

## Phase 17 — Import *(first-time coverage)*

> Excluded from InfinityFree pass. Test from scratch. Requires supervisor+ role.

| Test Case | Result | Notes |
|---|---|---|
| Import page loads | | |
| Download template file | | |
| Upload a small valid Excel/CSV file | | |
| Import preview shows correct rows | | |
| Confirm import → contacts appear in list | | |
| Upload a file with validation errors → errors displayed | | |

---

## Phase 18 — General UI & Tour

| Test Case | Result | Notes |
|---|---|---|
| Sidebar collapses / expands | | |
| Search bar (?) finds pages | | |
| Tour (?) button triggers overlay | | |
| Tour steps spotlight correct elements | | |
| Dark mode / theme works (if implemented) | | |
| Mobile: sidebar collapses on narrow viewport | | |

---

## Defects Found

| # | Phase | Severity | Description | Resolution | Status |
|---|---|---|---|---|---|
| | | | | | |

---

## cPanel-Specific Issues to Watch

These are failure modes that are unique to this cPanel deployment. If something works locally but breaks here, check these first:

1. **Console MIME errors on lazy chunks** → `VITE_BASE_URL` was wrong at build time; need to rebuild locally with `/build/` then re-upload `public/build/`.
2. **Unstyled pages after a fix** → browser served stale cached chunk; test in Incognito. File hash changes on rebuild self-bust the cache.
3. **Blank page on F5** → routing `.htaccess` missing or overwritten; the cPanel-generated block must be preserved AND the rewrite rules must be prepended.
4. **Login returns 500** → check `storage/logs/laravel.log` (via cPanel File Manager). Common causes: DB credentials wrong, storage not writable, `.env` misconfigured.
5. **DB errors** → confirm user `kltheguidecom_crmuser` has ALL PRIVILEGES on `kltheguidecom_crmdb` (MySQL Databases → check grant).
6. **File upload fails** → `storage/` permissions must be 755+; `php artisan storage:link` must have run.
7. **Mail errors → 500 on login** → `MAIL_MAILER=log` must be set; if it's `smtp` with bad credentials, the inline mail in login flow will throw under `QUEUE_CONNECTION=sync`.
8. **502 on phpMyAdmin import** → data is fine; verify table + row counts, don't re-import.

---

*Generated: 2026-06-26 | BGOC Library CRM v2 cPanel Test Pass*
