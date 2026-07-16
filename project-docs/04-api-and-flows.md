This file asks for **two** documents in one response, separated by
`# ── PART 1 ──` / `# ── PART 2 ──` headers.

---

# PART 1 — API Specification

Audience: frontend/integration developers. Format: one section per module; within
each, a table of `Method | Path | Permission required | Purpose`. Add a short
prefix note on auth (Bearer token via Sanctum, base path `/api/v1/`, throttling,
and the maintenance-mode gate). Do not invent request/response body schemas beyond
what's implied by the module names — where a body schema isn't given, write "See
implementation" rather than guessing field names, EXCEPT where the accompanying
Data Dictionary document already documents the underlying table's columns (those
*do* imply the create/update body — reference that document for exact fields if
you have it in this conversation).

# PART 2 — Sequence / Flow Diagrams

Produce Mermaid `sequenceDiagram` blocks for each flow listed below, plus a short
prose walkthrough under each. Audience: developers. Use only the steps given — do
not invent additional steps, error codes, or side effects.

Use only the facts below for both parts.

---

## Global auth & middleware notes (for Part 1)
- All `/api/v1/*` routes require `auth:sanctum` (Bearer token) + a custom
  `maintenance` middleware (blocks non-essential traffic during maintenance mode).
- Fine-grained authorization is per-route via Spatie's `can:<permission>`
  middleware — `auth:sanctum` alone only proves identity, never authorization.
- `POST /auth/login` is public, throttled 10 requests/minute.
- `POST /public/lead` is public (lead capture form), throttled 10 requests/minute.
- A separate `_dp` prefix (outside `/api/v1`) exposes internal diagnostics behind a
  custom `devpanel.auth` middleware and its own throttle — treat as an internal
  tool, not part of the public API surface, and document its endpoints in an
  appendix marked "internal/diagnostic only" rather than the main spec body.

## Auth
| Method | Path | Permission | Purpose |
|---|---|---|---|
| POST | /auth/login | public (throttled 10/min) | Authenticate, returns Sanctum token |
| POST | /auth/logout | authenticated | Revoke current token |
| GET | /auth/me | authenticated | Current user profile + roles |

## Profile, Sessions, Settings (own-data only, no extra permission)
| Method | Path | Permission | Purpose |
|---|---|---|---|
| GET/PUT | /v1/profile | authenticated | View/update own profile |
| PUT | /v1/profile/password | authenticated | Change own password |
| GET | /v1/sessions | authenticated | List own active tokens/sessions |
| DELETE | /v1/sessions/{id} | authenticated | Revoke one session |
| DELETE | /v1/sessions/all | authenticated | Revoke all sessions |
| GET/PUT | /v1/me/settings | authenticated | Own preferences |
| GET/PUT | /v1/user/dashboard-layout | authenticated | Own dashboard widget layout |
| GET | /v1/lookups | authenticated | All dropdown reference data |
| GET | /v1/reminders | authenticated | Overdue/today/upcoming + admin alerts |
| POST | /v1/reminders/read | authenticated | Mark a reminder read |
| GET/PUT | /v1/my-signature | authenticated | Own e-signature |
| GET/PUT | /v1/prepared-by/own | manage site-availability | Own "prepared by" profile |
| GET | /v1/prepared-by/active | manage site-availability | Active prepared-by profile |

## Announcements
| Method | Path | Permission | Purpose |
|---|---|---|---|
| GET | /v1/announcements | authenticated | List active announcements |
| POST | /v1/announcements/{id}/read | authenticated | Mark read |
| GET | /v1/announcements/admin/all | manage announcements | List all (admin) |
| POST/PUT/DELETE | /v1/announcements[/{id}] | manage announcements | CRUD |

## Contacts
| Method | Path | Permission | Purpose |
|---|---|---|---|
| GET | /v1/contacts/daily | view contacts | Daily view |
| GET | /v1/contacts/export | view contacts | CSV export |
| GET | /v1/contacts/check-duplicate | view contacts | Duplicate check |
| GET | /v1/contacts | view contacts | List/search/filter |
| GET | /v1/contacts/{id} | view contacts | Show |
| GET | /v1/contacts/{id}/incharges | view contacts | List in-charges |
| GET | /v1/contacts/{id}/todos | view contacts | Contact-scoped to-dos (read) |
| GET | /v1/contacts/find-duplicates | manage duplicates | Bulk duplicate finder |
| POST | /v1/contacts/merge | edit contacts | Merge duplicate contacts |
| POST | /v1/contacts/bulk-reassign | edit contacts | Reassign owner in bulk |
| POST | /v1/contacts | create contacts | Create |
| PUT/PATCH | /v1/contacts/{id} | edit contacts | Update |
| PATCH | /v1/contacts/{id}/closed | edit contacts | Toggle permanently-closed |
| DELETE | /v1/contacts/{id} | delete contacts | Soft delete |
| POST/PUT/DELETE | /v1/contacts/{id}/incharges[/{id}] | edit contacts | In-charge CRUD |
| POST/PUT/DELETE | /v1/contacts/{id}/todos[/{id}] | edit contacts | Contact-scoped to-do writes |

## Global To-Do List
| Method | Path | Permission | Purpose |
|---|---|---|---|
| GET | /v1/todos/export | view todos | CSV export |
| GET | /v1/todos/active-dates | view todos | Dates with active to-dos |
| GET | /v1/todos | view todos | List |
| GET | /v1/todos/{id} | view todos | Show |
| PATCH | /v1/todos/{id}/status | edit todos | Update completion status |
| POST | /v1/todos | create todos | Create |
| PUT | /v1/todos/{id} | edit todos | Update |
| DELETE | /v1/todos/{id} | delete todos | Delete |

## Follow-Ups
| Method | Path | Permission | Purpose |
|---|---|---|---|
| GET | /v1/followups/export | view followups | CSV export |
| GET | /v1/followups | view followups | List |
| GET | /v1/followups/{id} | view followups | Show |
| PATCH | /v1/followups/{id}/status | edit followups | Update completion status |
| PATCH | /v1/todos/{todoId}/complete-followups | edit followups | Bulk-complete under a to-do |
| POST | /v1/followups | create followups | Create |
| PUT | /v1/followups/{id} | edit followups | Update |
| DELETE | /v1/followups/{id} | delete followups | Delete |

## Deals
| Method | Path | Permission | Purpose |
|---|---|---|---|
| GET | /v1/deals/export | view deals | CSV export |
| GET | /v1/deals/summary | view deals | Pipeline summary |
| GET | /v1/deals | view deals | List |
| GET | /v1/deals/{id} | view deals | Show |
| POST | /v1/deals | create deals | Create |
| PUT | /v1/deals/{id} | edit deals | Update |
| DELETE | /v1/deals/{id} | delete deals | Delete |

## Projects
| Method | Path | Permission | Purpose |
|---|---|---|---|
| GET | /v1/projects/export | view projects | CSV export |
| GET | /v1/projects/{id}/remark | view projects | Remark detail |
| GET | /v1/projects | view projects | List |
| GET | /v1/projects/{id} | view projects | Show |
| POST | /v1/projects | create projects | Create |
| PUT | /v1/projects/{id} | edit projects | Update |
| DELETE | /v1/projects/{id} | delete projects | Delete |

## Forecasts
| Method | Path | Permission | Purpose |
|---|---|---|---|
| GET | /v1/forecasts/summary | view forecast summary | Aggregate summary |
| GET | /v1/forecasts | view forecasts | List |
| GET | /v1/forecasts/{id} | view forecasts | Show |
| POST | /v1/forecasts | create forecasts | Create |
| PUT | /v1/forecasts/{id} | edit forecasts | Update |
| DELETE | /v1/forecasts/{id} | delete forecasts | Delete |

## Performance
| Method | Path | Permission | Purpose |
|---|---|---|---|
| GET | /v1/performance/overview | view performance | KPI overview |
| GET | /v1/performance/team | view performance | Team comparison (admin) |
| GET | /v1/performance/report | view performance | Legacy task-based report |
| GET/PUT | /v1/performance/targets/{userId} | view performance | Legacy weekly targets |
| GET/PUT | /v1/performance/kpi-targets/{userId} | view performance | KPI targets |

## Analytics, Reporting, Data Health, Predictive
| Method | Path | Permission | Purpose |
|---|---|---|---|
| GET | /v1/analytics | view analytics | Summary dashboard |
| GET | /v1/summary | view summary | Activity summary |
| GET | /v1/data-health | view data-health | Data quality audit |
| GET | /v1/contact-analysis/{overview,lead-source,status-distribution,engagement,followup-actions} | view contacts | Contact analytics |
| GET | /v1/predictive/{summary,forecast,at-risk,pace,overdue-risk,deals,win-rates,deal-velocity,pipeline-coverage} | view contacts | Predictive insights |

## Import
| Method | Path | Permission | Purpose |
|---|---|---|---|
| POST | /v1/import/preview | import contacts | Validate a CSV before import |
| POST | /v1/import/process | import contacts | Execute the import |

## Site Availability (Advertising Products)
| Method | Path | Permission | Purpose |
|---|---|---|---|
| GET/POST | /v1/site-availability | manage site-availability | List / create |
| POST | /v1/site-availability/proposal | manage site-availability | Generate proposal (PDF) |
| POST | /v1/site-availability/products | manage site-availability | Create product |
| POST | /v1/site-availability/resolve-maps-url | manage site-availability | Resolve a Google Maps URL |
| PUT | /v1/site-availability/products/{id} | manage site-availability | Update product |
| POST/DELETE | /v1/site-availability/products/{id}/photo | manage site-availability | Upload/delete photo |
| POST | /v1/site-availability/products/{id}/confirm | manage site-availability | Confirm a pending product |
| DELETE | /v1/site-availability/products/{id} | manage site-availability | Discard product |
| PUT/DELETE | /v1/site-availability/bookings/{id} | manage site-availability | Update/delete booking |
| GET/PUT | /v1/prepared-by/profiles[/{user}/activate] | role super-admin | Manage prepared-by profiles |

## Social Media & Posting Calendar
| Method | Path | Permission | Purpose |
|---|---|---|---|
| GET/POST/PUT/DELETE | /v1/social-media-reminders[/{id}] | manage social-media | CRUD |
| GET/POST/PUT/DELETE | /v1/posting-calendar[/{id}] | manage posting-calendar | CRUD |

## Email Marketing
All under `manage email-campaigns`:
| Method | Path | Purpose |
|---|---|---|
| GET/POST/PUT/DELETE | /v1/email-campaigns[/{id}] | Campaign CRUD |
| POST | /v1/email-campaigns/{id}/duplicate, /send, /schedule, /send-test | Campaign actions |
| GET | /v1/email-campaigns/{id}/recipients | Recipient list |
| GET/POST/PUT/DELETE | /v1/email-templates[/{id}] | Template CRUD |
| POST | /v1/email-images | Upload campaign image |
| GET/PUT/POST | /v1/email-settings[/test] | SMTP/branding settings |
| GET/POST/PUT/DELETE | /v1/email-contacts[/{id}] | Contact CRUD |
| POST | /v1/email-contacts/bulk, /import, /sync-crm | Bulk ops |
| GET | /v1/email-contacts/export | CSV export |
| GET/POST/PUT/DELETE | /v1/email-tags[/{id}] | Tag CRUD |
| GET | /v1/email-analytics, /v1/email-analytics/dashboard | Analytics |
| GET/POST/PUT/DELETE | /v1/email-groups[/{id}], /preview, /{id}/members | Audience group CRUD |

## Admin: Audit Log, Lookups, RBAC, System Settings, Users
| Method | Path | Permission | Purpose |
|---|---|---|---|
| GET | /v1/admin/audit-log[/export] | manage users | Audit trail |
| GET/POST/PUT/DELETE | /v1/admin/{entity}[/{id}], /merge | manage lookups | Lookup CRUD (statuses/types/categories/industries/areas/tasks) |
| GET/POST/PUT/DELETE | /v1/rbac/roles[/{id}], /{id}/permissions | manage roles | Role CRUD + permission sync |
| GET | /v1/rbac/permissions | manage permissions | Read-only permission list (code-defined) |
| GET/PUT | /v1/system-settings | manage system | Global settings |
| GET/POST/DELETE | /v1/contact-edit-grants[/{id}] | manage users | Delegated contact-edit grants |
| GET | /v1/user-activity/overview, /security-events | manage users | User activity/security dashboards |
| GET/POST/PUT/DELETE | /v1/rbac/users[/{id}] | manage users | User CRUD |
| GET | /v1/rbac/users/pending | manage users | Pending approvals |
| POST | /v1/rbac/users/bulk-roles, /bulk-delete | manage users | Bulk ops |
| POST | /v1/rbac/users/{id}/restore | manage users | Restore soft-deleted user |
| PUT | /v1/rbac/users/{id}/roles | manage users | Sync roles |
| PUT | /v1/rbac/users/{id}/approve | manage users | Approve pending user |
| PUT | /v1/rbac/users/{id}/restore-access | manage users | Clear inactivity lockout |
| PUT | /v1/rbac/users/{id}/unlock | manage users | Clear failed-login lockout |
| GET/DELETE | /v1/signatures[/{user}] | role admin/super-admin | View/remove any user's signature |

## Department Task Manager
All under `manage dept-tasks`, prefix `/v1/dept/`:
| Method | Path | Purpose |
|---|---|---|
| GET | dashboard, departments, users, weekly, report | Read-only views |
| GET/POST | notifications[/read] | Notifications |
| GET/POST | tasks | List / create |
| GET | tasks/export, tasks/calendar-export | Export |
| GET/PUT/DELETE | tasks/{id} | Show/update/delete |
| PUT | tasks/{id}/status | Status update |
| POST/DELETE | tasks/{id}/comments[/{commentId}] | Comments |
| POST/DELETE | tasks/{id}/attachments[/{attachmentId}] | Attachments |
| GET/PUT/DELETE | attachments[/{id}] | Direct attachment management |

## Appendix: internal diagnostics (`_dp` prefix, `devpanel.auth` + throttle — do not expose publicly)
info, users CRUD, db, artisan, settings, admin-users, announcement broadcast,
maintenance toggle, activity log, block/unblock/quarantine user, login-as, data
injection/rollback. Mark clearly in the doc as **internal tooling, not part of the
public API contract**.

## Flows (for Part 2)

### Flow 1 — Login
Participants: Browser, AuthController, SystemSetting, Mail, Admin.
1. User submits username/password to `POST /auth/login` (throttled 10/min).
2. Wrong credentials → HTTP 422.
3. Credentials valid but `is_approved = false` → HTTP 403 `pending_approval`; on the
   *first* such attempt, send `UserPendingApproval` notification to admins via
   `notifyAdmins()`.
4. `inactivity_flagged_at` is already set → HTTP 403 `inactivity_flagged` (no email
   re-sent).
5. `last_login_at` is 14+ days ago (and not yet flagged) → set
   `inactivity_flagged_at` now, send `InactivityLoginAlert` to admins, return HTTP
   403.
6. `login_count === 0` (very first login ever) → send `FirstLoginAlert` to admins,
   then continue to success.
7. Success → increment `login_count`, update `last_login_at`, issue a Sanctum token,
   return it + user object (including `roles[]`) to the browser.
8. Browser stores token as `crm_token` and user JSON as `crm_user` in `localStorage`.

`notifyAdmins()` behavior to include as a note/branch: reads
`SystemSetting::get('admin_notification_email')` first; if set, routes the
notification only to that address; otherwise falls back to every admin/super-admin
user.

### Flow 2 — App Boot & Route Guard
Participants: Browser, app.js, Vue Router, localStorage.
1. `app.js` creates the Vue app and router, calls `createWebHistory
   (import.meta.env.VITE_APP_BASE)`.
2. Router's `setupGuard` runs on first navigation: reads `crm_token`/`crm_user` from
   `localStorage`, wrapped in try/catch (a corrupted JSON value is caught and
   self-healed — key cleared, falls back to `null` — rather than throwing).
3. If the target route requires auth and no valid token → redirect to `/login`.
4. If the route has `adminOnly` meta and the user's roles don't include admin/
   super-admin → blocked.
5. `app.js` waits on `router.isReady()` before calling `app.mount('#app')`; if that
   promise rejects, mount is attempted anyway as a last-resort fallback so the page
   is never left blank.

### Flow 3 — Authenticated API Request with Cache Invalidation
Participants: Vue component, Axios instance (api.js), Laravel API.
1. Component calls a GET endpoint via the shared Axios instance.
2. Axios instance attaches `Authorization: Bearer <crm_token>` and checks its
   short-lived (~30s) in-memory GET cache; returns cached data if fresh, otherwise
   requests from the server.
3. On any 401 response, the instance redirects to `/login`, de-duplicating so
   concurrent in-flight requests don't trigger multiple redirects.
4. On any successful non-GET (POST/PUT/PATCH/DELETE) response, the instance clears
   the GET cache entirely so the next read reflects the write immediately (this
   replaced an earlier, broken cache-bust helper that was never actually called).

### Flow 4 — Contact → To-Do → Follow-Up lifecycle
Participants: ContactController, ToDoController/GlobalTodoController,
FollowUpController.
1. A To-Do is created against a Contact (`contact_id`), optionally tagged with a
   Task type (`task_id`) and a `todo_date`.
2. The To-Do page (not the contact page) is the sole place a user edits a to-do's
   remark or completion status — the contact-level view only reads to-dos and
   offers an "Open in To-Do" deep link.
3. One or more Follow-Ups are logged against that To-Do (`todo_id`) with their own
   `followup_date`, note, and action_type — a Follow-Up has no direct link back to
   the Contact or User; both are reached by traversing `followup → todo →
   contact`/`todo → user`.
4. Completing a To-Do does not cascade to its Follow-Ups automatically; there is a
   dedicated bulk-complete endpoint (`PATCH /v1/todos/{todoId}/complete-followups`)
   for marking all of a to-do's follow-ups complete at once.

### Flow 5 — RBAC Permission Check on a Protected Route
Participants: Browser, Sanctum middleware, `maintenance` middleware, `can:
<permission>` middleware, Controller.
1. Request arrives at an `/api/v1/...` route with a Bearer token.
2. `auth:sanctum` resolves the token to a User (401 if invalid/missing — this only
   proves identity).
3. `maintenance` middleware checks system maintenance-mode state.
4. `can:<permission>` middleware (Spatie) checks the resolved User's roles →
   permissions; super-admin bypasses via a `Gate::before` hook in
   `AppServiceProvider` (not a checked permission at all). Authorization failure →
   403.
5. Controller executes only after both authentication and authorization pass.

### Flow 6 — Admin User Approval
Participants: Admin (browser), UserManagementController, User model, Mail.
1. New user account created via `POST /v1/rbac/users` → auto-approved
   (`is_approved = true`) and auto-verified (`email_verified_at = now()`) at
   creation time (no email verification step exists).
2. Note: the "pending approval" 403 path in Flow 1 only occurs if a user's
   `is_approved` is later set to false manually — since creation auto-approves,
   describe this as a manual admin action, not a default new-user state.
3. Admin can separately: approve (`PUT .../approve`), restore access after
   inactivity lock (`PUT .../restore-access`), or unlock after failed-login lockout
   (`PUT .../unlock`).
