This file asks for **three** documents in one response, separated by
`# ── PART 1 ──` / `# ── PART 2 ──` / `# ── PART 3 ──` headers. They build on each
other (RBAC matrix → how it's enforced → what to review before launch), so keep
them consistent with one another rather than treating them as unrelated.

---

# PART 1 — RBAC / Permissions Matrix

Audience: admins configuring roles and auditors. Format: a single large Markdown
table — rows = permissions (grouped by module with a sub-header row), columns =
the 6 roles (super-admin, admin, supervisor, user, internal support, viewer) — cell
value ✓ or blank. Use the exact permission-to-role assignments given below; do not
infer or add any not listed. Follow the table with a short prose section on the
delegation mechanism (contact edit grants) which sits outside this permission model.

# PART 2 — Authentication & Authorization Design

Audience: developers and security reviewers. Sections: Authentication Mechanism,
Token Lifecycle, Authorization Model, Account Lifecycle States, Lockout &
Recovery, Notification Routing. Use only the facts given.

# PART 3 — Security Assessment / Threat Model

Audience: security reviewer / whoever signs off before production launch. Format:
a table of `Area | Control in place | Residual risk / thing to verify` plus a
short narrative for anything that needs a decision rather than just a checkbox. Do
not invent vulnerabilities that aren't implied by the facts given — where you
don't have enough information to assess something (e.g. actual input sanitization
in a specific controller), say "requires code-level review" rather than guessing a
verdict.

Use only the facts below across all three parts.

---

## Roles (as defined in `RolesAndPermissionsSeeder`, for Part 1)
- **super-admin** — gets every permission directly, AND bypasses all permission
  checks entirely via a `Gate::before` hook in `AppServiceProvider` (so this role is
  effectively unconditional, not just "has every permission").
- **admin** — every permission except `manage roles`, `manage permissions`,
  `manage users` (RBAC/user administration is super-admin only).
- **supervisor** — same as `user` below, plus `import contacts`, `view data-health`,
  and `manage email-campaigns`.
- **user** — full day-to-day CRM + common marketing tools, no admin tools. Notably
  excludes `import contacts`, `view data-health`, and `manage email-campaigns`
  (all three are admin/supervisor-grantable only).
- **internal support** — view + create/edit across CRM resources, no delete, no
  admin or marketing tools.
- **viewer** — read-only (`view *`) across CRM resources only.

## Full permission list with descriptions and role assignment (Part 1 table source)

| Permission | Description | super-admin | admin | supervisor | user | internal support | viewer |
|---|---|---|---|---|---|---|---|
| view contacts | Browse/search contacts, open profiles | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| create contacts | Add new contacts | ✓ | ✓ | ✓ | ✓ | ✓ | |
| edit contacts | Update contact details/emails/calls/todos/incharges | ✓ | ✓ | ✓ | ✓ | ✓ | |
| delete contacts | Remove contacts | ✓ | ✓ | ✓ | ✓ | | |
| view todos | View global/contact to-do lists | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| create todos | Add to-dos | ✓ | ✓ | ✓ | ✓ | ✓ | |
| edit todos | Update / complete / cancel to-dos | ✓ | ✓ | ✓ | ✓ | ✓ | |
| delete todos | Remove to-dos | ✓ | ✓ | ✓ | ✓ | | |
| view deals | View pipeline/records | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| create deals | Add deals | ✓ | ✓ | ✓ | ✓ | | |
| edit deals | Update stage/value/status | ✓ | ✓ | ✓ | ✓ | | |
| delete deals | Remove deals | ✓ | ✓ | ✓ | ✓ | | |
| view forecasts | View forecast records | ✓ | ✓ | ✓ | ✓ | | ✓ |
| create forecasts | Create forecast entries | ✓ | ✓ | ✓ | ✓ | | |
| edit forecasts | Update forecast/line items | ✓ | ✓ | ✓ | ✓ | | |
| delete forecasts | Remove forecast records | ✓ | ✓ | ✓ | ✓ | | |
| view forecast summary | Aggregate forecast view | ✓ | ✓ | ✓ | ✓ | | ✓ |
| view projects | View project list/records | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| create projects | Create projects | ✓ | ✓ | ✓ | ✓ | | |
| edit projects | Update status/remarks | ✓ | ✓ | ✓ | ✓ | | |
| delete projects | Remove projects | ✓ | ✓ | ✓ | ✓ | | |
| view followups | View follow-up list/records | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| create followups | Log follow-ups against a to-do | ✓ | ✓ | ✓ | ✓ | ✓ | |
| edit followups | Update / complete follow-ups | ✓ | ✓ | ✓ | ✓ | ✓ | |
| delete followups | Remove follow-up records | ✓ | ✓ | ✓ | ✓ | | |
| import contacts | Bulk CSV import | ✓ | ✓ | ✓ | | | |
| view analytics | Analytics dashboard/charts | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| view summary | CRM activity summary report | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| view data-health | Data quality audit page | ✓ | ✓ | ✓ | | | |
| view performance | Individual/team KPI + targets | ✓ | ✓ | ✓ | ✓ | | |
| manage lookups | CRUD lookup values | ✓ | ✓ | | | | |
| manage announcements | CRUD company-wide announcements | ✓ | ✓ | | | | |
| manage duplicates | Duplicate finder + merge | ✓ | ✓ | | | | |
| manage system | Global system settings | ✓ | ✓ | | | | |
| manage social-media | Social media reminder entries | ✓ | ✓ | ✓ | ✓ | | |
| manage posting-calendar | Social posting calendar | ✓ | ✓ | ✓ | ✓ | | |
| manage email-campaigns | Create/schedule/send campaigns | ✓ | ✓ | ✓ | | | |
| manage site-availability | Site availability listings/bookings | ✓ | ✓ | ✓ | ✓ | | |
| manage dept-tasks | Department task manager | ✓ | ✓ | ✓ | ✓ | | |
| manage roles | Create/edit/delete roles, assign permissions | ✓ | | | | | |
| manage permissions | Read-only view of code-defined permissions | ✓ | | | | | |
| manage users | Create/approve/edit/soft-delete user accounts | ✓ | | | | | |

## Delegation mechanism (outside the role/permission model — for Part 1's prose)
`ContactEditGrant` lets an admin grant one specific user edit access to another
specific user's contacts, independent of role — e.g. covering for a colleague. This
is a targeted, per-user-pair grant (managed under `manage users`), not a role or
permission, and should be documented as a distinct escalation path in the matrix's
accompanying notes.

Also note: sub-resources of a Contact (its to-dos, in-charges) are gated on the
parent `view contacts` / `edit contacts` permission — there is no separate
per-sub-resource permission. Deals and Projects use fully granular view/create/
edit/delete permissions per action.

## Authentication (for Part 2)
- Laravel Sanctum, token-based (not session cookies) — suited to a decoupled Vue
  SPA calling a JSON API.
- Login: `POST /auth/login`, throttled 10 requests/minute, public route.
- On success the token is returned to the client and stored in `localStorage` as
  `crm_token`; the user object (including `roles[]`) is stored as `crm_user`.
- Every subsequent API call attaches `Authorization: Bearer <crm_token>` via a
  shared Axios instance.
- Logout (`POST /auth/logout`) and per-session revocation (`DELETE /v1/sessions/
  {id}`, `DELETE /v1/sessions/all`) both revoke Sanctum tokens server-side.
- **Email verification is fully disabled** as a deliberate product decision — no
  verification emails are sent; all users are auto-verified
  (`email_verified_at = now()`) and auto-approved (`is_approved = true`) at
  creation time in `UserManagementController::store()`. Document this as intended
  behavior, not a gap — but flag in Part 3 that it removes a traditional
  signup-abuse control (mitigated here because users are only ever created by
  admins, not via public self-registration).

## Authorization Model (for Part 2)
- Spatie `laravel-permission`, guard `web`. 6 roles: super-admin, admin,
  supervisor, user, internal support, viewer (full matrix in Part 1).
- **super-admin bypasses all permission checks** via a `Gate::before` hook in
  `AppServiceProvider` — it does not merely hold every permission, it is
  unconditionally authorized. This should be called out explicitly since it's a
  different trust mechanism than "has all permissions" and matters for anyone
  reasoning about what a compromised admin vs. super-admin account can do.
- Every protected API route is wrapped in `can:<permission>` middleware; the
  project convention is that `auth:sanctum` alone must never be the only guard on
  a route — routes without a `can:` middleware are non-conformant with the design
  and should be flagged in any audit.
- A parallel delegation mechanism, `ContactEditGrant`, allows an admin to grant one
  specific user edit rights over another specific user's contacts, independent of
  role. This is per-user-pair, not role-based, and is a distinct authorization
  path worth calling out separately from the RBAC matrix.
- Admin-only route groups also exist gated directly on Spatie's `role:` middleware
  (e.g. `role:admin|super-admin`, `role:super-admin`) rather than a permission —
  used for a small number of routes (viewing/removing any user's e-signature;
  managing "prepared-by" profiles).

## Account Lifecycle States (on the `users` table, for Part 2)
- `is_approved` (bool) — must be true to log in; new accounts are auto-approved at
  creation (admin-driven creation only, no public self-signup).
- `approved_at` / `approved_by_id` — audit of a manual approval action.
- `login_count` / `last_login_at` — drive first-login and inactivity detection.
- `inactivity_flagged_at` — set automatically when `last_login_at` is 14+ days
  stale at a login attempt; blocks login with `inactivity_flagged` until an admin
  clears it via `PUT /v1/rbac/users/{user}/restore-access`.
- `failed_login_attempts` / `locked_until` — short-term lockout after repeated bad
  passwords.
- `lockout_level` / `permanently_locked` — escalation state for repeated lockouts;
  cleared via `PUT /v1/rbac/users/{user}/unlock`.
- `blocked_at` — a separate hard-block state (distinct from soft-delete and from
  inactivity flagging).
- Soft-deletes — user records are soft-deleted, not hard-deleted, and can be
  restored via `POST /v1/rbac/users/{id}/restore`.

## Login Decision Flow (for Part 2's Token Lifecycle section)
1. Bad credentials → 422.
2. Not approved → 403 `pending_approval` (+ email to admins on first attempt only).
3. Already inactivity-flagged → 403 `inactivity_flagged` (no repeat email).
4. Newly stale (14+ days) → flag now, email admins, 403.
5. First-ever login (`login_count === 0`) → email admins, then proceed.
6. Success → increment login_count, update last_login_at, issue token.

## Admin Notification Routing (for Part 2)
`AuthController::notifyAdmins()` reads `SystemSetting::get('admin_notification_
email')` first; if set, all admin notifications route to that single address via
`Notification::route('mail', $email)`. Only if that setting is empty does it fall
back to emailing every admin/super-admin user individually. This means a
misconfigured or unset `admin_notification_email` silently changes the blast
radius of every security-relevant email (approval requests, inactivity alerts,
first-login alerts) — worth a callout as an operational dependency, not just a
config nicety, and worth flagging again in Part 3.

## In-app alerting (parallel to email, for Part 2)
`SystemAlert` records are created per-admin for events like password changes
(`ProfileController::changePassword()` creates one for every admin/super-admin,
in-app only, no email) and surfaced in the notification bell alongside CRM
reminders — this is a second, independent notification channel from the email
path above, not a replacement for it.

## Known controls (for Part 3)
- Token auth via Sanctum; tokens revocable individually or all-at-once per user.
- RBAC via Spatie permissions on every `/api/v1/*` route (project convention:
  `auth:sanctum` alone is never sufficient — every route also carries a `can:` or
  `role:` middleware).
- super-admin has an unconditional `Gate::before` bypass — treat any super-admin
  account compromise as full system compromise; recommend this role be granted
  sparingly and audited.
- Public routes are minimal and throttled: `POST /auth/login` (10/min),
  `POST /public/lead` (10/min, unauthenticated lead capture form — treat as the
  system's only unauthenticated write path and review its validation/sanitization
  and spam/abuse exposure specifically).
- No public self-registration — all accounts are admin-created, auto-approved.
  This removes a signup-abuse vector but concentrates trust in whoever holds
  `manage users`.
- Account lockout: short-term (`failed_login_attempts`/`locked_until`) with
  escalation (`lockout_level`/`permanently_locked`), plus separate inactivity
  auto-flagging after 14 days idle. All three states require explicit admin
  action to clear — verify there's no self-service bypass.
- Admin actions are audit-logged (`admin_audit_logs`: user, action, entity,
  old/new values as JSON, IP, timestamp) — verify coverage is actually
  comprehensive (i.e. every admin-affecting controller action writes a log entry)
  rather than assuming from the table's existence alone.
- A hidden internal diagnostics surface exists (`_dp` prefix) behind its own
  `devpanel.auth` middleware, separate from Sanctum/RBAC, including a "login-as any
  user" endpoint and raw Artisan command execution. **This is the single highest-
  risk surface in the system** if it is ever reachable in production — confirm
  explicitly that `devpanel.auth` cannot pass in a production environment (e.g. it
  should hard-fail unless a specific non-production flag/env check passes), and
  that the route group is not registered at all when `APP_ENV=production`, not just
  gated by a weak shared secret.
- Passwords: bcrypt (implied by Laravel defaults); email marketing SMTP password
  (`email_settings.smtp_password`) is noted as "encrypted cast in the model" —
  verify this uses Laravel's encrypted cast (AES via APP_KEY) and that APP_KEY
  itself is unique per environment and never committed.
- Known project history: a Gmail app password was previously exposed in a
  development `.env` and has since been scrubbed from git history via
  `git filter-repo`, with pre-commit/pre-push secret-scanning hooks added — confirm
  that specific credential has actually been rotated (it was flagged as still
  needing rotation as of the last recorded check) before go-live.
- `APP_DEBUG` must be `false` in production (stack traces/SQL/env vars are exposed
  otherwise) — this is a deployment-checklist item, verify it's actually set, not
  just documented.
- Session/cache fall back to file-based drivers when Redis is unavailable (typical
  on shared cPanel hosting) — verify file-based session storage still sets
  `secure`/`httponly` cookie flags appropriately for the SPA's needs, and that
  `SESSION_ENCRYPT=true` / `SESSION_DOMAIN` are set per the deployment checklist.

## Areas requiring code-level review (for Part 3 — do not assess without reading the code)
- Input validation/sanitization on every controller that accepts user input,
  especially the public lead-capture endpoint and CSV import (`import contacts`
  permission) — CSV import is a classic vector for formula injection (CSV
  injection) if opened in Excel later, and for mass-assignment if request data
  isn't allowlisted against a validated field set.
- File upload handling (site-availability product photos, dept-task attachments,
  email campaign images) — verify MIME/type validation, storage path traversal
  protection, and size limits.
- The e-signature and "prepared by" data (base64 PNG signature data stored in
  plaintext in `user_signatures.signature_data`) — confirm this is treated as
  sensitive personal data in backup/access-control planning even though it isn't
  a password.
- Rate limiting beyond the two explicitly-throttled public routes — confirm
  authenticated endpoints that are expensive (CSV export, bulk reassign, bulk
  delete, campaign send) have appropriate guards against accidental or malicious
  repeated triggering.
- SQL injection / XSS: standard Eloquent/Blade/Vue usage is protective by default;
  flag only if any raw SQL (`DB::raw`, `whereRaw`) or `v-html` usage is found
  during review — do not assume either is present or absent without checking.

## Recommended sign-off checklist (for Part 3)
`APP_DEBUG=false`, `APP_ENV=production`, unique `APP_KEY`, `LOG_LEVEL=warning`,
`SESSION_ENCRYPT=true`, `SESSION_DOMAIN` set, DB on the correct production port,
rotated Gmail credential, transactional mail provider swapped in,
`admin_notification_email` configured before first admin notification fires.
