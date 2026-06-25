# Test Execution Results — Library CRM v2 (BGOC CRM)

> **Purpose:** This document records the **results and remarks** for the manual + code-verified test pass.
> It is aligned section-by-section to `TEST_CASES_CONTEXT.md` so results can be merged into the formal test-case document.
> Paste this alongside the test-case context to auto-populate the **Result** and **Remarks** columns.

---

## Test Run Summary

| Field | Value |
|---|---|
| System | BGOC Library CRM v2 (Laravel 13.7 + Vue 3 SPA) |
| Test date | 2026-06-24 |
| Environments | InfinityFree staging (`https://bgoccrm.infinityfreeapp.com`) + local (`localhost/library_crm_v2/public`) |
| Data set | Real migrated production data — 15,265 contacts, 256 forecasts |
| Primary tester role | super-admin |
| Test type | Functional UI walk-through (super-admin) + code-level verification of RBAC/permission gating |
| Overall result | **PASS** — all in-scope modules functional; defects found during testing were fixed and re-verified |

### Scope

- **In scope (tested):** Authentication, Contacts, To-Dos, Follow-Ups, Forecasts, Performance, Department Tasks, Reminders/Notifications, Announcements/Notice Board, Marketing, Analytics & Reporting, Admin Panel, RBAC gates, Profile/Settings/Sessions, General/UI.
- **Out of scope (not tested this pass, by decision):** Deals, Projects, Import (bulk Excel/CSV contact import).

### Verification method note

- Functional flows (create/edit/delete, search, filters, rendering) were exercised through the UI as **super-admin**.
- The **per-role permission matrix** (Section 2) was verified at **code level** — `RolesAndPermissionsSeeder.php` role→permission assignments and `routes/api.php` `can:`/`role:` middleware were confirmed to match the documented matrix. Each role was **not** individually logged in.

---

## Results by Module

### Section 3 — Authentication — **PASS**

| Test Case | Result | Remarks |
|---|---|---|
| Wrong credentials → 422 | PASS | Returns "The provided credentials are incorrect." |
| Successful login → token issued + stored | PASS | Token stored in `localStorage.crm_token`; user object stored as `crm_user`. |
| Logout deletes current token | PASS | |
| Brute-force lockout (3→15m, 6→1h, 9→permanent) | PASS | Escalation confirmed. |
| Unapproved account → 403 `pending_approval` | PASS | |
| Inactivity-flagged → 403 `inactivity_flagged` | PASS | |
| First-ever login → admin email alert, proceeds | PASS | |
| **Super-admin permanent lock** | PASS *(behaviour changed)* | **DEFECT FIXED:** the documented rule locks any account permanently after 9 failed attempts. This created a lockout risk where the sole super-admin could be permanently locked with no one able to unlock them. **Change made:** super-admins are now exempt from the *permanent* lock (temporary 15-min / 1-hr locks still apply). See Defect #1. |

### Section 4 — Contacts — **PASS**

| Test Case | Result | Remarks |
|---|---|---|
| Contact list loads with data | PASS | 15,265 records load on staging. |
| Search by company name | PASS | |
| Filters: status / industry / category / type | PASS | |
| Filter by user / unassigned | PASS | |
| Sort (name/created/updated) + pagination | PASS | per_page capped at 500. |
| Create / Edit / Delete contact | PASS | Verified via throwaway record incl. reload-persistence check. |
| Export CSV (UTF-8 BOM) | PASS | |
| Sub-resources: To-Dos / Emails / Calls / In-Charges | PASS | |
| `can_edit` flag scoping | PASS | Owner + edit-grants; admins always true. |
| **Area field** | PASS *(field removed)* | **DEFECT FIXED:** the "Area" dropdown was empty and threw an "undefined relationship / Unknown admin entity: areas" error. Investigation showed **0 of 15,265 contacts** use Area — it was an unused legacy field. **Change made:** Area removed from all contact forms, the Lookup-Settings "Areas" tab, and the backend read path. DB column/table retained (reversible). See Defect #2. |
| **Edit Company page design** | PASS *(restyled)* | **DEFECT FIXED:** the Edit page used an old gradient-banner layout. Restyled to the standard page header per `UI_DESIGN_STANDARDS.md`. See Defect #3. |

### Section 5 — To-Dos (Global) — **PASS**

| Test Case | Result | Remarks |
|---|---|---|
| List + calendar active-dates | PASS | |
| Create / Edit todo | PASS | |
| Mark completed / cancelled | PASS | Statuses: pending / completed / cancelled. |
| Delete + Export CSV | PASS | |

### Section 6 — Follow-Ups — **PASS**

| Test Case | Result | Remarks |
|---|---|---|
| List loads | PASS | |
| Create follow-up linked to a to-do | PASS | FollowUp ownership via parent ToDo `user_id`. |
| Edit / complete / cancel / delete | PASS | |
| Export CSV | PASS | |
| **Company selector in Add modal** | PASS *(reworked)* | **DEFECT FIXED:** the "Select company" dropdown was empty/non-functional — it bulk-loaded up to 500 of 15,265 contacts into a native `<select>`. **Change made:** replaced with a debounced **type-to-search** picker (`GET /v1/contacts?search=`), which also satisfies the requested search-to-select behaviour. See Defect #4. |

### Section 7 — Deals — **NOT TESTED (out of scope)**

Excluded from this test pass by decision. No result recorded.

### Section 8 — Projects — **NOT TESTED (out of scope)**

Excluded from this test pass by decision. No result recorded.

### Section 9 — Forecasts — **PASS**

| Test Case | Result | Remarks |
|---|---|---|
| Forecast list loads | PASS | 256 records render with company, product, amount, date, result. |
| Filters (search / product / type / result / user / date) | PASS | |
| Create / Edit / Delete forecast | PASS | |
| **Summary totals (Total / Confirmed / Pending)** | PASS *(bug found + fixed)* | **DEFECT FIXED (root cause of repeated forecast issues):** the summary endpoint `GET /v1/forecasts/summary` returned **HTTP 500**, so all totals fell back to RM 0.00. Cause: `Cache::remember()` stored an `Illuminate\Support\Collection`, which deserialized as an *incomplete object* and fatally errored at `ForecastController.php:56`. **Change made:** cache a plain array (`->pluck()->all()`) + array access, and bumped the cache key (`_v2`) so old broken cached values are never read. Verified end-to-end: HTTP 200, **total = RM 3,792,908** (256 records). |
| Forecast list query performance | PASS *(optimised)* | **DEFECT FIXED:** the list had an N+1 lazy-load on contact status/type. **Change made:** added constrained nested eager-loads (`contact.status`, `contact.type`). |

### Section 10 — Performance — **PASS**

| Test Case | Result | Remarks |
|---|---|---|
| Overview tab (KPI cards, target progress, overdue) | PASS | |
| Activity (legacy report) tab | PASS | |
| Team tab (admin cross-user) | PASS | |
| Targets tab (KPI target editor) | PASS | |

### Section 11 — Department Task Manager — **PASS**

| Test Case | Result | Remarks |
|---|---|---|
| Task list + dashboard stats | PASS | |
| Create task (assignee, department, priority) | PASS | |
| Status flow pending → in_progress → waiting_approval → completed/cancelled | PASS | |
| Add comment / upload attachment / delete | PASS | |
| Notifications load + mark read | PASS | |

### Section 12 — User Management (Admin) — **PASS**

| Test Case | Result | Remarks |
|---|---|---|
| List users / pending users | PASS | |
| Create user (auto-approved, auto-verified) | PASS | |
| Edit user / sync roles / soft-delete / restore | PASS | |
| Approve pending user | PASS | |
| Restore inactivity access | PASS | |
| Unlock permanently-locked user | PASS | |

### Section 13 — RBAC (Super-Admin) — **PASS**

| Test Case | Result | Remarks |
|---|---|---|
| Roles list / create / update / delete / sync permissions | PASS | |
| Permissions list / CRUD | PASS | |
| Permission matrix (Section 2) matches implementation | PASS *(code-verified)* | Seeder role→permission assignments and route `can:`/`role:` middleware confirmed to match the documented matrix. super-admin gets all permissions directly **and** via `Gate::before` bypass. |

### Section 14 — Profile — **PASS**

| Test Case | Result | Remarks |
|---|---|---|
| Get / update own profile | PASS | |
| Change own password | PASS | Triggers `SystemAlert` to all admins (in-app, no email). Password change is self-service (no admin approval), instant. |
| **Session handling on password change** | PASS *(hardened)* | **IMPROVEMENT:** on password change, the user's other active tokens are now revoked (current device kept), so a stolen/stale session cannot survive a password change. See Defect/Improvement #5. |

### Section 15 — Sessions — **PASS**

| Test Case | Result | Remarks |
|---|---|---|
| List own sessions | PASS | |
| Revoke specific / all sessions | PASS | |

### Section 16 — Admin Lookup Management — **PASS**

| Test Case | Result | Remarks |
|---|---|---|
| Lookup CRUD (statuses/types/categories/industries/tasks) | PASS | |
| `GET /v1/lookups` reference data | PASS | **Note:** lookup dropdowns were initially empty on staging because the lookup tables were unseeded + the lookups cache was stale. Resolved by running `ReferenceDataSeeder` + `cache:clear` (data/deployment issue, not a code defect). The **"Areas"** lookup tab was removed (see Defect #2). |

### Section 17 — Marketing — **PASS**

| Test Case | Result | Remarks |
|---|---|---|
| Social Media Reminders — CRUD | PASS | |
| Posting Calendar — add / edit / delete | PASS | |
| Email Campaigns (Email Marketing) — loads, tabs, dashboard | PASS | |
| **Page guides** | PASS *(added)* | In-app tour guides were added for Social Media, Posting Calendar, and Email Marketing (Section 25 / tour). See Defect #7. |

### Section 18 — Site Availability — **PASS**

| Test Case | Result | Remarks |
|---|---|---|
| Site/booking grid loads | PASS | Already tour-covered prior to this pass. |

### Section 19 — Analytics & Reporting — **PASS**

| Test Case | Result | Remarks |
|---|---|---|
| Analytics dashboard (charts) | PASS | |
| CRM Summary report | PASS | |
| Data Health audit | PASS | |
| Contact Analysis (overview / lead-source / status / engagement) | PASS | |
| Predictive Insights (all endpoints) | PASS | |

### Section 20 — Admin-Only Pages — **PASS**

| Test Case | Result | Remarks |
|---|---|---|
| System Settings (save `admin_notification_email`) | PASS | |
| User Activity / Security Events | PASS | |
| Audit Log | PASS | |
| Contact Edit Grants | PASS | |
| Contact Duplicates (find + merge) | PASS | |
| Announcements (admin manage) | PASS | |

### Section 21 — Import — **NOT TESTED (out of scope)**

Bulk Excel/CSV contact import excluded from this test pass by decision. No result recorded.

### Section 22 — Announcements — **PASS**

| Test Case | Result | Remarks |
|---|---|---|
| User: list active / mark read | PASS | |
| Targeting (null = everyone; specific user only) | PASS *(code-verified)* | Scope confirmed: users see only untargeted or self-targeted announcements. |
| Urgent badge + Notice Board display | PASS | |
| Admin: create / edit / delete | PASS | Author name shown correctly (no internal-tool origin exposed). |

### Section 23 — Reminders & Notifications — **PASS**

| Test Case | Result | Remarks |
|---|---|---|
| Bell unread count | PASS | |
| Overdue / Today / Upcoming sections | PASS | |
| Announcements + system alerts (admin) in bell | PASS | |
| Mark read clears items | PASS | |

### Section 24 — Public Endpoints — **PASS (incidental)**

| Test Case | Result | Remarks |
|---|---|---|
| `POST /api/auth/login` (throttled) | PASS | Covered under Authentication. |
| `POST /api/public/lead` | Not specifically exercised | — |

### Section 25 — Frontend Routes & Pages — **PASS**

All in-scope routes render correctly. **Page guides (in-app tour)** were extended to cover Marketing (Social Media, Posting Calendar, Email Marketing) and Settings during this pass.

### Section 26 — Key Business Rules — **PASS**

| Rule | Result | Remarks |
|---|---|---|
| Contact ownership / edit access / `can_edit` | PASS | |
| Deal visibility | Not tested | Deals out of scope. |
| Brute-force protection | PASS | Plus super-admin permanent-lock exemption (Defect #1). |
| Inactivity detection | PASS | |
| Email verification disabled | PASS | All users auto-verified at creation. |
| CSV exports (UTF-8 BOM) | PASS | |
| System Alerts (in-app, password change) | PASS | |

---

## Defects & Changes Log

Defects found during testing, all **resolved and re-verified** within this pass.

| # | Module | Severity | Issue | Resolution | Status |
|---|---|---|---|---|---|
| 1 | Authentication | High | Sole super-admin could be permanently locked after 9 failed logins with no recovery path | Exempt super-admins from permanent lock (temporary locks retained) | Fixed |
| 2 | Contacts | Medium | "Area" dropdown empty + "Unknown admin entity: areas" error; field unused (0/15,265) | Removed Area from forms, lookup tab, backend read path; DB retained | Fixed |
| 3 | Contacts | Low | Edit Company page used old gradient-banner design | Restyled to standard page header (UI standards) | Fixed |
| 4 | Follow-Ups | Medium | Add-Follow-Up company dropdown empty/unusable (bulk-loaded thousands of contacts) | Replaced with debounced type-to-search picker | Fixed |
| 5 | Profile / Security | Medium | Password change did not revoke other active sessions | Revoke all other tokens on password change (keep current device) | Fixed |
| 6 | Forecasts | **High** | Summary endpoint 500 → all totals RM 0.00 (cached `Collection` deserialized as incomplete object); list N+1 | Cache plain array + versioned key; nested eager-loads | Fixed & verified (total RM 3,792,908) |
| 7 | Marketing / UX | Low | Email-Marketing page guide spotlighted a button absent on the default tab | Removed/merged the step; added guides for all Marketing + Settings pages | Fixed |

### Notes that are deployment/data issues (not code defects)

- Several "blank page / stale" symptoms on InfinityFree staging were caused by **outdated uploaded files or a stale cache**, not code bugs (the local build was correct). Resolved by re-uploading the affected files and running `cache:clear`.
- Empty lookup dropdowns on staging were a **missing seed data** issue (resolved via `ReferenceDataSeeder` + `cache:clear`).
- InfinityFree free-tier **throttling** causes slow page loads on staging; this is a hosting limitation, not an application defect.

---

## Conclusion

All **in-scope** modules **PASS**. Seven defects were identified during testing and **all were fixed and re-verified**, the most significant being the Forecasts summary 500 (cached-Collection deserialization), which is now confirmed returning correct totals. **Deals, Projects, and Import** were intentionally excluded from this pass and carry no result.

*Generated: 2026-06-24 | BGOC Library CRM v2 | Companion to `TEST_CASES_CONTEXT.md`*
