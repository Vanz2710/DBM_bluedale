# Administration Feature Diagnosis

> Generated 2026-07-01. Read-only audit — no production data was modified.

---

## 1. Confirmed Bugs

**`AdminPanel.vue` — Edit errors surface in the wrong place**
`saveEdit()` writes its error to `addError.value`, which renders in the "Add new item" bar above the table. When you edit row 12 and it fails a duplicate check, the error appears next to the blank "Add" input, not near the row you were editing.

**`AuditLog.vue` — `updated_password` badge is dead code**
`AuditLog.vue` has CSS class `.action-badge-updated_password` and `summaryText` handles the `'updated_password'` action. But `UserManagementController::update()` uses a generic `'updated'` audit action for all updates including password changes. The "Set Pwd" modal in `RbacPanel.vue` calls the same `PUT /v1/rbac/users/{user}` endpoint, so password resets silently appear as ordinary "updated" entries.

**`RbacPanel.vue` — Restore User fires without confirmation**
`restoreUser()` (line ~984) makes the API call immediately. Every other destructive or reversal action in the same file (unlock, restore access, delete, approve, delete role) is gated behind a modal. One mis-click can accidentally restore a terminated employee's account.

**`AdminAuditLogController` — Hard 300-record ceiling, no pagination**
`.limit(300)->get()` with all filtering done client-side. On a live deployment the log fills and older entries become invisible with no indication that they've been truncated.

---

## 2. RBAC / Security Gaps — ✅ FIXED 2026-07-01

**`RoleController` writes zero audit logs** — FIXED
Added a private `audit()` helper (same shape as `UserManagementController`/`AdminController`) and calls it from `store()`, `update()`, `destroy()`, and `syncPermissions()`. New entity types `role` and `role_permissions` added to `AuditLog.vue`'s `KNOWN_ENTITIES` filter list.

**`PermissionController` CRUD routes are wired but unreachable from the UI** — FIXED
Removed `store()`, `update()`, `destroy()` from `PermissionController` and their routes from `api.php`. The seeder's own permission description already documents permissions as code-defined/read-only (`RbacPanel.vue`'s Permissions tab says as much in its info banner); the CRUD routes contradicted that design and let an admin rename/delete a permission that's hardcoded into `can:` middleware elsewhere, silently breaking access control with no way to detect it. Only `GET /v1/rbac/permissions` remains.

**`ContactDuplicates.vue` and `Announcements.vue` — no specific permission** — FIXED (correction added 2026-07-01, see Section 5)
Added two new permissions: `manage announcements` and `manage duplicates`. Announcement admin routes switched from `role:admin|super-admin` to `can:manage announcements`. The bulk duplicate-finder listing route (`GET /v1/contacts/find-duplicates`, used only by `ContactDuplicates.vue`) switched from `can:view contacts` to `can:manage duplicates`. Both pages' route meta and nav items now carry the matching `permission` field alongside their existing `adminOnly: true`, so they're delegable via the RBAC panel instead of hard-locked to the admin role. Neither permission was added to the `user`/`viewer`/`supervisor` default sets — behaviour is unchanged (admin-only) until an admin explicitly delegates it.
>
> **Correction:** this entry originally claimed the shared `contacts/merge` endpoint was "delegable via `can:edit contacts`". That was inaccurate — `ContactController::merge()` had its own hardcoded `hasRole(['admin','super-admin'])` check *inside the method*, independent of the route middleware, which silently blocked any non-admin from actually completing a merge regardless of permissions. Found and fixed while doing Section 5's merge-audit-logging work: the internal check now also accepts `can('manage duplicates')`, so a delegated non-admin can both browse *and* complete merges as originally intended, while every other `edit contacts` holder (i.e. all regular staff by default) still cannot merge — merging permanently deletes contact records, so it intentionally stays behind its own stricter gate rather than the general contact-editing permission.

**`SystemSettings` page uses `manage users` permission as its gate** — FIXED
Added a new `manage system` permission. `system-settings` routes moved out of the `can:manage users` group into their own `can:manage system` group; route meta and nav item updated to match.

**`SystemSettingsController::update()` — `admin_notification_email` is required** — FIXED
The controller now converts an empty string to `null` before validating (`nullable|email|max:255` instead of `required`), so clearing the field in the UI and saving now correctly reverts to the all-admins fallback in `AuthController::notifyAdmins()`.

> All three new permissions (`manage announcements`, `manage duplicates`, `manage system`) were added to `RolesAndPermissionsSeeder.php` and the seeder was re-run — `admin`/`super-admin` already have them. Any already-logged-in admin session will pick them up automatically on next page load via the existing `GET /auth/me` refresh in `App.vue`; no re-login required.

---

## 3. UX Issues — ✅ FIXED 2026-07-01

**`Settings.vue` — Admin tab uses `v-show` instead of `v-if`** — CORRECTED & FIXED
Re-investigation found the original diagnosis was imprecise: the admin tab's *content* block (the `adminLinks` list) already used `v-if="tab === 'admin' && isAdmin"` — it was never exposed in the DOM. The actual (much smaller) issue was the sidebar *nav button* for the Admin tab (line 33), which used `v-show="item.tab !== 'admin' || isAdmin"` — this only hid the button, not any link data, so the real risk was near-zero (a non-admin could see a hidden "Admin" button element exists, not its contents). Fixed anyway for consistency: switched to `v-if` so the button itself isn't in the DOM at all for non-admins.

**`AdminPanel.vue` — "Areas" tab is visible and editable but the field is not used** — CORRECTED (no longer applicable)
Re-investigation (independently confirmed twice, including a `git show HEAD` check) found no "Areas" tab exists anywhere in `AdminPanel.vue`, in either the working tree or the last commit. This item in the original diagnosis did not reflect the current state of the file — no action was needed.

**`RbacPanel.vue` — `approveUser()` also fires without a modal** — FIXED
Added an `approveUserModal` confirmation modal, structurally identical to the `restoreUserModal` pattern (reactive `{open, user, loading}` state, matching Teleport/overlay/modal markup, same button classes). `approveUser()` now opens the modal instead of calling the API directly.

**`RbacPanel.vue` — Inactivity-flagged users have no "Edit" button**  — FIXED
Added the same `Edit` button (`openEditUserModal`) used by normal users into the inactivity-flagged branch of the per-row action buttons, alongside "Restore Access" and "Delete".

**`AuditLog.vue` — Client-side filters operate on a capped dataset** — Already resolved
This was fixed as part of Bug 4 in Section 1 (server-side pagination + filtering replaced the 300-record client-side cap). No further action needed here.

**`UserActivity.vue` — Status labels undocumented** — FIXED
Added a legend row above the table and a `:title` tooltip on every status badge, sourced directly from the real thresholds in `UserActivityController.php`: `locked` (inactivity-flagged), `never_logged_in`, `dormant` (30+ days since last login), `at_risk` (14–29 days), `active` (<14 days).

---

## 4. Backend Architecture Issues — ✅ FIXED 2026-07-01

**`AdminController` — raw SQL string interpolation in usage subqueries** — FIXED
`$usageMap` previously stored raw table-name strings (`'contacts'`, `'to_dos'`, etc.) that were interpolated directly into a `selectRaw()` subquery — if a model's table were ever renamed, this map would silently go stale with no compile-time signal. `$usageMap` now stores the actual Eloquent model class (`Contact::class`, `ToDo::class`, `PerformanceTarget::class`, `Forecast::class`, `SocialMediaReminder::class`) instead of a string; both `index()` and `destroy()` resolve the real table name via `(new $childModel)->getTable()` at call time, so the table reference is always derived from the model's own source of truth instead of a hand-maintained literal. The identifiers are still embedded into raw SQL for the `COUNT(*)` arithmetic (table/column names can't be parameter-bound in any SQL engine, and there is still zero user input reaching this string), but they can no longer drift from the schema. Verified by directly invoking `index()` for all 10 lookup entities and `destroy()`'s reference-counting logic against the live database — all produced correct, non-error usage counts.

**`AdminAuditLogController` — no server-side filtering, no pagination** — Already resolved
This was fixed as part of Bug 4 in Section 1 (server-side `days`/`action`/`entity_type`/`q` filters + real pagination replaced the 300-record `.limit()` call). No further action needed here.

**`SystemSettingsController` — validator is the settings registry** — FIXED
Extracted the inline validation rule into a `private const RULES` array keyed by setting name. `update()` now normalizes and validates only the keys defined in `RULES` that are actually present in the request; any key not in the registry is silently ignored rather than accidentally persisted unvalidated. Adding a future setting is now a one-line addition to `RULES` instead of editing `update()`'s logic directly. Verified live: normal set, clear-to-null, invalid-email rejection, and unknown-key-is-ignored all behave correctly (tested via `php artisan tinker` against the real `system_settings` table, then restored the original value).

---

## 5. Missing Features

| Gap | Impact | Status |
|---|---|---|
| Audit log export (CSV) | Compliance audit trails must be exportable | ✅ FIXED |
| Audit log pagination + server-side search | History beyond 300 entries is inaccessible | ✅ Fixed (Section 1, Bug 4) |
| Role change audit logging | Most critical admin action is completely unlogged | ✅ Fixed (Section 2) |
| `updated_password` audit action | Password resets show as generic "updated" | ✅ Fixed (Section 1, Bug 2) |
| `admin_notification_email` clearable | Cannot revert to the all-admins fallback via UI | ✅ Fixed (Section 2) |
| Contact merge audit logging | Permanent data change leaves no trail | ✅ FIXED |
| Specific permissions for announcements and merges | Coarse-grained admin gate only | ✅ Fixed (Section 2, corrected above) |
| Restore user confirmation modal | Only missing modal among all user management actions | ✅ Fixed (Section 1, Bug 3) |
| Bulk user operations | No bulk delete / role-assign / password reset | ✅ Fixed (role-assign + delete); password reset explicitly out of scope |

**Audit log export (CSV)** — FIXED
Added `AdminAuditLogController::export()`, sharing the same `days`/`action`/`entity_type`/`q` filters as `index()` via a new private `filteredQuery()` helper (extracted to avoid duplicating the filter logic between the two methods). Streams up to 10,000 matching rows as CSV with a UTF-8 BOM, following this codebase's established export pattern (`ProjectController::export()` etc.). New route `GET /v1/admin/audit-log/export` (same `can:manage users` gate). `AuditLog.vue` got an Export button that passes the current filters plus `_token` via `window.location.href`, matching `ProjectList.vue`'s exact pattern. Verified the CSV renders correctly with real data via direct invocation (BOM + header row + real rows confirmed byte-for-byte).

**Contact merge audit logging** — FIXED
`ContactController::merge()` now writes an `AdminAuditLog` row (`action: 'merged'`, `entity_type: 'contact'`) recording which contacts were merged away (`old_values.merged_contacts`) and which one survived (`new_values.kept_contact`), captured *before* the transaction deletes the merged rows. `AuditLog.vue` gained a `merged` badge/summary-text case and a `contact` entity-type filter option. While implementing this, found and fixed the internal-permission-check gap described in the Section 2 correction above. Verified end-to-end (merge, permission-gate on/off, audit row contents) using throwaway contacts created and deleted within the test — no production contact data was touched.

**Bulk user operations** — FIXED (role-assign + delete; user explicitly chose to scope out bulk password reset since this app has no working self-service password-reset email pipeline to build on)
Added checkboxes to the Users table in `RbacPanel.vue` (header "select all" + per-row, hidden for already-deleted rows) and a bulk-actions bar that appears when 1+ users are selected, with **Assign Role** and **Delete Selected** actions, each behind its own confirmation modal listing the affected users by name.
- `POST /v1/rbac/users/bulk-roles` (`UserManagementController::bulkAssignRole`) — applies one role to every selected user via `syncRoles([$role])`, reusing the exact same super-admin protection rules as the single-user `syncRoles()` endpoint (only a super-admin may grant/revoke super-admin; can't strip super-admin from the last super-admin). Rather than failing the whole batch on one bad user, each user is checked independently — a blocked user is *skipped* (with a reason returned to the UI) while the rest of the batch proceeds. Each successful change gets its own `user_roles` audit log entry, identical in shape to the single-user action, so `AuditLog.vue` needed no changes to display them.
- `POST /v1/rbac/users/bulk-delete` (`UserManagementController::bulkDelete`) — soft-deletes each selected user, skipping (with a reason) anyone who is the requesting admin themselves or who still owns contacts/deals/projects/forecasts/to-dos (bulk delete deliberately does not support the single-delete flow's per-user reassignment-target picker — that UX doesn't work at batch scale, so those users must be deleted individually instead). Each deletion gets its own `user`/`deleted` audit log entry, matching the single-delete format.
- Verified end-to-end with throwaway users (never touching real accounts): normal role-assign, the non-super-admin-tries-to-grant-super-admin block, and a mixed bulk-delete batch (clean users deleted, an owner-of-work user skipped, self-delete skipped) — all behaved exactly as designed, and cleanup left zero trace. The "last super-admin" protection branch was *not* independently re-tested since it's identical logic already relied upon by the existing single-user endpoint, and exercising it safely would have required manipulating the one real super-admin account in this database.

---

## Fix Status

- [x] **Bug 1** — `AdminPanel.vue` edit error shown in wrong place
- [x] **Bug 2** — `updated_password` audit action never generated by backend
- [x] **Bug 3** — `restoreUser()` fires without confirmation modal
- [x] **Bug 4** — `AdminAuditLogController` hard 300-record limit (added pagination)
- [x] RBAC gaps (Section 2)
- [x] UX issues (Section 3)
- [x] Architecture issues (Section 4)
- [x] Missing features (Section 5) — all 9 done (bulk password reset explicitly excluded from bulk user operations per user decision)
