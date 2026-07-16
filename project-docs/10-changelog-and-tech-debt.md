This file asks for **two** documents in one response, separated by
`# ── PART 1 ──` / `# ── PART 2 ──` headers.

---

# PART 1 — Change Log / Release Notes

Audience: internal stakeholders tracking what shipped and when. This system does
not currently maintain a formal changelog, so produce (a) a short explanation of
the recommended format going forward, and (b) a best-effort **retroactive** first
entry reconstructed from the real, dated facts below — labeled clearly as
"reconstructed from migration history," not a verified release log. Format each
entry as `## [Date] — Title` with a bullet list of changes, grouped Added/Changed/
Removed/Fixed where it's clear which applies.

# PART 2 — Known Issues / Technical Debt Register

Audience: engineering team planning post-launch cleanup. Format: a table of
`ID | Area | Description | Risk if unaddressed | Suggested action`. Use only the
concrete items below — do not pad the list with generic "add more tests"-style
filler.

Use only the facts below for both parts.

---

## PART 1 facts

### Recommended format going forward
Keep one entry per deployment, dated, in reverse-chronological order, using
Added/Changed/Removed/Fixed sections. Reference the migration filenames or PR/
commit references where possible so the changelog stays traceable to the actual
code change.

### Reconstructed history (from migration timestamps and known facts — treat as a
starting point to correct against actual deployment records, not ground truth)

**2026-07-15 — Department Task Manager: approval workflow removed**
- Removed: the approval-gated task completion workflow in the Department Task
  Manager (`requires_approval` column retained on `dept_tasks` for backward
  compatibility but no longer drives behavior).

**2026-07-08 — Forecast indexing**
- Added: a date index on `forecasts.forecast_date` for query performance.

**2026-07-02 — Advertising bookings: multi-month grouping**
- Added: `booking_group` column on `advertising_product_bookings` to support
  bookings spanning multiple months.

**2026-07-01 — Contact communication tables removed**
- Removed: `contact_emails` and `contact_calls` tables (created 2026-05-18,
  dropped here) — superseded by other tracking (verify with the team what replaced
  this, if anything, before publishing).

**2026-06-25 to 2026-06-30 — Security & site-availability additions**
- Added: login lockout escalation fields, `blocked_at` hard-block state on users;
  `data_injections` tracking table for the internal dev-panel tool; additional
  contact-related fields on `advertising_products`.

**2026-06-22 to 2026-06-23 — E-signatures, prepared-by profiles, announcements**
- Added: `user_signatures` (e-signature capture), `user_prepared_by` (proposal
  "prepared by" profile), `announcements` + `announcement_reads`.

**2026-06-19 — Webhooks removed; contact last-contacted tracking added**
- Removed: `webhooks` tables (created 2026-05-18, dropped here).
- Added: `contacts.last_contacted_at`.

**2026-06-17 — Email campaigns migrated to Brevo-oriented design; login lockout added**
- Changed: `email_campaigns` restructured for Brevo (removed `provider`,
  `template_id`, `audience` JSON, `schedule_at`; added `preview_text`,
  `sender_name`, `scheduled_at`, `sent_at`, `audience_count`, `open_rate`,
  `click_rate`, `brevo_campaign_id`, `brevo_list_id`).
- Added: `failed_login_attempts` / `locked_until` on users.

**2026-06-15 — Contact edit delegation; audit trail on core tables**
- Added: `contact_edit_grants` (delegated per-user contact edit access);
  `is_permanently_closed` flag on contacts; audit-trail columns added to core
  tables (exact columns not captured in this reconstruction — verify against
  migration `2026_06_15_000003_add_audit_trail_to_core_tables.php`).

**2026-06-03 — Department Task Manager introduced**
- Added: `departments`, `dept_tasks`, `dept_task_comments`,
  `dept_task_attachments`, `dept_notifications` — the internal work-tracking
  module, plus `role`/`department_id` fields on users. Also added: email
  templates, email-campaign-contacts tables.

**2026-06-02 — System settings & in-app alerts introduced**
- Added: `system_settings` (seeded with `admin_notification_email`);
  `system_alerts` (in-app admin notifications).

**2026-06-01 — User tracking & audit log**
- Added: approval/login tracking fields on users (`is_approved`, `login_count`,
  `last_login_at`, etc.); soft-deletes on users; `admin_audit_logs` table.

**2026-05-28 — Scalability indexes and lookup uniqueness**
- Added: missing indexes for scale; unique constraints on lookup tables.

**2026-05-18 to 2026-05-26 — Marketing modules introduced**
- Added: social media reminders + packages, posting calendar reminders, initial
  email campaigns table, contact_emails/contact_calls (later removed — see
  2026-07-01 above), webhooks (later removed — see 2026-06-19 above),
  whatsapp_messages table (no corresponding model currently exists in the
  codebase — flag as possibly unused/in-progress), notifications table (Laravel
  framework default).

**2026-05-13 to 2026-05-15 — RBAC and pipeline modules introduced**
- Added: Spatie permission tables; Deals, Performance Targets, Reminder Reads,
  KPI Targets modules.

**2026-05-07 to 2026-05-08 — Initial CRM core**
- Added: contacts and all contact lookup tables, contact in-charges, tasks,
  to-dos, follow-ups, contact areas.

Present the reconstructed section with a visible disclaimer at the top: "Dates
reflect when the underlying database migration was authored, not necessarily when
the feature was deployed to production or announced to users — confirm against
actual deployment records before treating this as an official release history."

## PART 2 facts

### Confirmed dead code / cleanup items
1. **Email verification dead code** — Email verification is functionally disabled
   (all users auto-verified at creation) but the routes, `EmailVerificationController`,
   and `VerifyEmail.vue` page still exist in the codebase. Risk: low (no harm, but
   confuses new developers into thinking verification is active). Suggested
   action: remove in a post-launch cleanup pass, as already flagged in the
   project's own pre-deployment checklist.

2. **Dropped tables still referenced in old migration history** — `contact_emails`
   and `contact_calls` (created 2026-05-18, dropped 2026-07-01) and `webhooks`
   (created 2026-05-18, dropped 2026-06-19) no longer exist as tables. Risk:
   low, but any leftover model classes, routes, or frontend calls referencing them
   would fail at runtime rather than compile time. Suggested action: grep the
   codebase for any remaining references before considering this fully closed.

3. **`whatsapp_messages` table with no corresponding model** — A migration
   (2026-05-19) created this table and `.env.production.example` defines a full set
   of `WHATSAPP_*` config variables (Meta Cloud API), but no `WhatsappMessage`
   model exists in `app/Models/` as of the last review. Risk: medium — unclear
   whether this is an abandoned feature, a partially-built one, or scaffolding for
   planned work; leaving it ambiguous risks someone building against a table with
   no model layer, or removing it while it's still needed. Suggested action:
   confirm with the team whether WhatsApp integration is active, planned, or
   abandoned, then either build out the model/feature or remove the table and env
   vars.

4. **`dept_tasks.requires_approval` column retained after workflow removal** — The
   Department Task Manager's approval-gated completion workflow was removed
   2026-07-15, but the `requires_approval` boolean column remains on the table.
   Risk: low, but a future developer could misread its presence as meaning the
   feature is still active. Suggested action: either remove the column in a
   follow-up migration or add a code comment/doc note clarifying it's vestigial.

5. **`ContactArea` / area_id — modeled but unused in the UI** — The `contact_areas`
   lookup table and `contacts.area_id` foreign key exist in the schema, but the
   Areas field is deliberately not surfaced in any UI, filter, or dropdown per
   project convention. Risk: low, but represents schema complexity with no current
   product value. Suggested action: either decide to actually use it, or remove it
   in a future cleanup once confirmed truly unused.

### Process / testing gaps
6. **`phpunit.xml` `APP_URL` pinning** — Feature tests that make HTTP calls
   silently return 405 in this XAMPP setup unless `APP_URL=http://localhost` is
   pinned in `phpunit.xml`. Risk: medium — a developer unaware of this could see
   mysterious test failures unrelated to their actual change. Suggested action:
   keep this documented prominently and consider a test-suite bootstrap check that
   fails fast with a clear message if `APP_URL` is misconfigured.

7. **Deals/Projects/Import test coverage** — These modules have historically had
   less test coverage than the rest of the CRM (per internal testing notes).
   Risk: medium — regressions in these modules are more likely to reach
   production undetected. Suggested action: prioritize test coverage here before
   any major refactor of these modules.

8. **`admin_audit_logs` has no retention/pruning policy** — The table grows
   indefinitely with no automatic archival. Risk: low near-term, medium long-term
   (table bloat, slower audit queries). Suggested action: decide a retention
   policy before this becomes a performance issue.

### Security-adjacent debt
9. **Internal `_dp` diagnostics surface** — Includes raw Artisan execution and a
   "login-as any user" endpoint, gated by a custom `devpanel.auth` middleware
   rather than the app's normal RBAC. Risk: high if ever reachable in production.
   Suggested action: verify (not just document) that this route group cannot be
   registered/reached when `APP_ENV=production`.

10. **Gmail credential rotation** — A Gmail app password was previously exposed in
    a development `.env` file; it was scrubbed from git history via
    `git filter-repo` and pre-commit/pre-push secret-scanning hooks were added, but
    rotation of the actual credential was still outstanding as of the last
    internal check. Risk: high until confirmed rotated. Suggested action: confirm
    rotation explicitly as a go-live blocker, not an optional cleanup item.
