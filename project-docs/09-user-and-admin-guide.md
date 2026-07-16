This file asks for **two** documents in one response, separated by
`# ── PART 1 ──` / `# ── PART 2 ──` headers.

---

# PART 1 — User Manual

Audience: non-technical staff (not admins). Tone: friendly, step-by-step,
task-oriented ("How do I..."-style headings). Do not describe exact button
labels/screen layout since those aren't provided — describe the workflow and what
to expect functionally. Include a short "Getting Started" section and a "Common
Questions" section at the end.

# PART 2 — Admin Guide

Covers everything Part 1 doesn't — i.e. admin/super-admin-only capabilities.
Audience: admins and super-admins. Tone: direct, procedural. Include a "Role
Cheat Sheet" up front summarizing which of the 6 roles can do what at a glance,
then step-by-step sections per admin capability.

Use only the facts below for both parts.

---

## PART 1 facts — what the user can do, by module

**Getting Started**
- Log in with your username and password (not email — the login form uses
  username). Your account must be approved and (if flagged inactive after 14+ days
  away) restored by an admin before you can access the system — contact your admin
  if you get a "pending approval" or "account flagged" message.
- On first login, a guided tour highlights the sidebar, notification bell, and
  settings — you can replay it anytime via the `?` icon in the top bar.
- The notification bell shows overdue/today/upcoming to-dos and follow-ups, plus
  any system alerts meant for you.

**Working with Contacts**
- Search/browse the contacts list; open a contact to see its full profile,
  including who's in charge on the client side (name/email/mobile/office phone).
- Add a new contact with a name, address, remark, and classification (status,
  type, category, industry).
- The system will warn you if a similar contact might already exist (duplicate
  check) before you save.
- A contact's to-dos are shown on its profile, but you edit them from the central
  To-Do page — use the "Open in To-Do" link from the contact view to jump there.

**To-Do / Follow-Up (your task list)**
- The To-Do page is where you manage everything — not the individual contact
  pages. Create a to-do against a contact with a date and a remark, optionally
  tagged with a task type.
- Log one or more follow-ups against a to-do as work progresses, each with its own
  date and note.
- Mark a to-do or follow-up complete (or cancelled) when done; there's a
  bulk-complete option to close out every follow-up under a to-do at once.

**Deals**
- Track a sales opportunity from first contact through to won or lost, recording
  its stage, expected value, probability, and expected close date. If a deal is
  lost, record why.

**Projects**
- Track a project's start/end dates and remarks against the contact it belongs to.

**Forecasts**
- Log an expected-revenue forecast against a contact, tied to a product, forecast
  type, and expected result — useful for your manager's pipeline visibility.

**Performance**
- Check your own KPI progress (new contacts, to-dos/follow-ups completed,
  projects/deals created, deals won and their value) against the targets your
  manager has set.

**Site Availability** (if your role has this permission)
- View which advertising sites are booked for a given month before quoting a
  client; create a booking; generate a proposal document for a client.

**Social Media & Posting Calendar** (if your role has this permission)
- Track a client's social media content through each stage — calendar planning,
  artwork/editing, posting, and reporting.
- See and add scheduled posts on the shared posting calendar.

**Email Marketing** (if your role has this permission)
- Build a campaign, choose an audience group, send a test to yourself first, then
  send or schedule it. Review open/click results afterward.

**Department Task Manager** (internal work, separate from client to-dos)
- View tasks assigned to you or your department; update status as you work;
  comment and attach files; recurring tasks reschedule themselves automatically
  when completed.

**Announcements**
- Company-wide announcements appear for everyone; mark them read once seen.

**Common Questions**
- **"Why can't I see a page or button someone else has?"** — Access is
  role-based; ask your admin if you believe you should have a permission you're
  missing.
- **"I got logged out and see a blank/white page."** — This is a known recoverable
  issue; clear your browser's site data (or clear `localStorage`) and log in again.
  Contact your admin if it persists.
- **"My follow-up isn't linked to the right contact."** — Follow-ups are always
  attached to a to-do, which is attached to a contact — check you logged the
  follow-up under the correct to-do.
- **"A deal I marked lost still shows as open."** — Confirm you saved the status
  change on the deal's edit form, not just the lost reason.

## PART 2 facts

### Role Cheat Sheet
- **super-admin** — everything, unconditionally (bypasses all permission checks).
  Only super-admin can manage roles, manage permissions, and manage users
  (create/approve/edit/delete accounts, assign roles).
- **admin** — everything super-admin has except RBAC/user administration.
- **supervisor** — full day-to-day CRM + import contacts + data-health + email
  campaigns, but not admin tools.
- **user** — full day-to-day CRM + common marketing tools, no import/data-health/
  email-campaigns/admin tools.
- **internal support** — view + create/edit (no delete), no admin/marketing tools.
- **viewer** — read-only across CRM resources.

### User Management (super-admin only, via `manage users`)
- Create a new staff account — new accounts are **auto-approved and auto-verified
  at creation** (no email verification step exists in this system by design), so
  creating an account grants immediate access once a role is assigned.
- Assign/sync one or more roles to a user.
- Approve a pending user (`PUT .../approve`) — relevant if an account's approval
  was manually revoked after creation, not for normal new-account flow.
- Restore access after an inactivity lockout (`PUT .../restore-access`) — triggers
  when a user hasn't logged in for 14+ days; clears `inactivity_flagged_at`.
- Unlock after a failed-login lockout (`PUT .../unlock`) — clears
  `failed_login_attempts`/`locked_until`/escalated `lockout_level`/
  `permanently_locked` state.
- Soft-delete and restore user accounts; bulk role-assignment and bulk-delete are
  available for managing several accounts at once.
- **Contact Edit Grants** — grant one specific user edit rights over another
  specific user's contacts (e.g. covering for someone on leave), independent of
  role. Revoke the same way.

### RBAC (super-admin only, via `manage roles` / `manage permissions`)
- Create/edit/delete roles and sync which permissions each role has. Permissions
  themselves are **code-defined, not editable via the UI** — the RBAC panel's
  permission list is read-only reference; adding a brand-new permission requires a
  code change.

### System Settings (via `manage system`)
- `admin_notification_email` — the single most important setting to get right:
  if set, every admin alert email (pending-approval, inactivity, first-login)
  routes only to this address; if left blank, every alert instead fans out to
  every admin/super-admin account. Set this immediately after a fresh deployment.

### Lookups (via `manage lookups`)
- CRUD contact statuses, types, categories, industries, (area — present in the
  data model but not surfaced anywhere in the UI, so there's nothing to manage
  here in practice), and task types. A merge tool exists for consolidating
  duplicate lookup values.

### Duplicate Contact Management (via `manage duplicates`)
- Run the bulk duplicate finder across the whole contacts table and merge
  confirmed duplicates — merging preserves the surviving contact's child records
  (to-dos, deals, projects, forecasts, in-charges).

### Announcements (via `manage announcements`)
- Create a company-wide announcement with an optional publish/expiry window;
  everyone with any role can read and mark it read, only admins manage authoring.

### Admin Audit Log (via `manage users`)
- Every admin action (who, what, old/new values, IP, when) is recorded and
  exportable — use this as your first stop when investigating "who changed this."

### Email Marketing, Site Availability, Social Media, Dept Tasks
These are permission-gated per role (see cheat sheet above) rather than
admin-exclusive — an admin's job here is mainly deciding which roles get
`manage email-campaigns`, `manage site-availability`, `manage social-media`,
`manage posting-calendar`, and `manage dept-tasks` by role, or granting them
individually through the RBAC panel.

### A note on the internal diagnostics tool (`_dp` routes)
There is a separate, hidden diagnostics surface behind its own auth gate — it is
**not** part of the RBAC system described above and is not intended for regular
admin use; it should be treated as a developer/operator tool, restricted at the
infrastructure level, and is out of scope for this guide beyond flagging its
existence to whoever administers the server (see the Security document for the
risk this surface represents).
