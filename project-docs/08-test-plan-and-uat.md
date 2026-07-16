This file asks for **two** documents in one response, separated by
`# ── PART 1 ──` / `# ── PART 2 ──` headers.

---

# PART 1 — Test Plan / Test Cases Document

Audience: QA. Format: one section per module, each with a table of
`Test ID | Scenario | Steps | Expected Result | Priority`. Cover: happy path, the
specific permission boundaries listed (since RBAC gating is central to this
system), and the edge cases explicitly called out below. Do not invent UI element
names or exact copy — describe actions functionally (e.g. "submit the create-deal
form") since the actual component markup isn't provided here.

# PART 2 — UAT (User Acceptance Testing) Sign-off Sheet

Audience: business stakeholders performing acceptance testing before go-live.
Format: one section per module with a checklist of business-facing acceptance
criteria (not technical test cases — phrase everything from the perspective of
"can a staff member actually do their job with this"), followed by a sign-off
table (Module | Tested By | Date | Pass/Fail | Notes | Signature).

Use only the facts below for both parts.

---

## PART 1 facts

**Testing context:** Backend tests run against a dedicated database
`bgoc_crm_test` (port 3307), `QUEUE_CONNECTION=sync` (jobs run inline during
tests), bcrypt rounds reduced to 4 for speed. Config lives in `phpunit.xml`.
`APP_URL` in `phpunit.xml` must be pinned to `http://localhost` — every Feature
test that makes an HTTP call otherwise silently gets a 405 in this local XAMPP
setup. `composer run test` runs the suite; `php artisan test --filter=X` runs one
file. 6 roles exist (super-admin, admin, supervisor, user, internal support,
viewer) with materially different permission sets — every module's test coverage
should include at least: an authorized role succeeding, an unauthorized role
getting 403, and an unauthenticated request getting 401.

**Priority test areas per module — include these as concrete cases:**

**Auth / Login**
- Wrong credentials → 422.
- Unapproved user → 403 `pending_approval`; admin notification sent only on first
  attempt (test that a second attempt does not re-send).
- Inactivity-flagged user → 403 `inactivity_flagged`, no repeat email.
- Login 14+ days after `last_login_at` → flags the account, sends
  `InactivityLoginAlert`, returns 403 (i.e. this login attempt itself must fail,
  not just flag-and-continue).
- First-ever login (`login_count === 0`) → `FirstLoginAlert` sent, login succeeds.
- Token revocation: `DELETE /v1/sessions/{id}` invalidates that token only;
  `DELETE /v1/sessions/all` invalidates all of the current user's tokens.
- Corrupted `localStorage.crm_user` value (invalid JSON) must not blank the app —
  this is a known historical bug class; a regression test/manual check should
  confirm the router guard and `App.vue` both self-heal rather than throwing.

**Contacts**
- Duplicate detection (`check-duplicate`) flags an existing name/similar match.
- Merge two duplicate contacts preserves child records (to-dos, in-charges, deals,
  projects, forecasts) under the surviving contact.
- Bulk reassign moves ownership (`user_id`) for a set of contacts atomically.
- Soft-delete then restore round-trip.
- `edit contacts` permission gates the contact's to-do and in-charge sub-resources
  too (no separate sub-resource permission) — verify a role with `view contacts`
  only cannot write to-dos on that contact.

**To-Do / Follow-Up**
- Creating a follow-up requires an existing to-do (`todo_id`) — a follow-up cannot
  exist without a parent to-do.
- Bulk-complete endpoint (`PATCH /v1/todos/{todoId}/complete-followups`) marks all
  of that to-do's follow-ups complete without affecting other to-dos' follow-ups.
- Completing a to-do does NOT automatically complete its follow-ups (verify these
  are independent unless the bulk-complete endpoint is explicitly called).

**Deals / Projects / Forecasts**
- Full CRUD per permission (`view/create/edit/delete <resource>` each gated
  independently — verify each is enforced separately, not as a single combined
  check).
- Deal `status` transitions between open/won/lost; `lost_reason` should be
  recorded when status is set to lost.
- Forecast's snapshot fields (`contact_status_id`, `contact_type_id`) capture the
  contact's classification *at forecast time* — verify changing the contact's
  current status later does not retroactively change an existing forecast's
  snapshot.

**RBAC / Permissions**
- Every permission in the RBAC matrix document: verify a role that has it
  succeeds and a role that lacks it gets 403 on the corresponding route.
- super-admin bypass: verify a super-admin succeeds on a route gated by a
  permission that was never explicitly synced to that role (since the bypass is
  unconditional via `Gate::before`, not permission-based).
- `ContactEditGrant`: user A granted access to user B's contacts can edit B's
  contacts even without a matching role-level permission difference; revoking the
  grant removes that access.

**Department Task Manager**
- Verify no approval-gated completion logic exists post-2026-07-15 removal — a
  task with `requires_approval = true` should still be completable directly (this
  guards against a regression reintroducing the removed workflow).
- Recurrence: completing a recurring task creates/schedules the next occurrence
  per `recurrence_type` and `next_recurrence_date`.
- Attachments: upload/list/rename/delete, including the "direct" attachment
  endpoints separate from the task-scoped ones.

**Email Marketing**
- Sending a campaign creates one `EmailCampaignRecipient` per resolved audience
  member and updates `email_campaigns`' aggregate counters
  (sent_count/delivered_count/etc.) consistently with the individual recipient
  rows.
- Unsubscribe via a recipient's token sets that `EmailContact`'s status to
  `unsubscribed` and excludes them from future sends to the same audience group.
- Dynamic audience groups (`type = 'dynamic'`, JSON `filters`) resolve membership
  at send time, not at group-creation time — verify a contact added after group
  creation is still included if it matches the filter.

**Data Integrity / Migration Edge Cases**
- Verify soft-deleted `contacts`/`deals`/`projects`/`users` are excluded from
  default list queries but retrievable via an explicit "show deleted" path where
  one exists.
- Verify the dropped tables (`contact_emails`, `contact_calls`, `webhooks`) have no
  remaining code paths that reference them (regression check after their removal).

## PART 2 facts — modules and business-facing acceptance criteria

- **Contacts** — sales staff can find their own contacts quickly, add a new lead in
  under a minute, see who else is assigned to a contact, and merge an accidental
  duplicate without losing history.
- **To-Do / Follow-Up** — a staff member can see everything due today across all
  their contacts in one place (the To-Do page), and a follow-up they log is
  correctly attached to the right scheduled task.
- **Deals** — a sales rep can track a deal from first contact through won/lost and
  see the reason recorded when a deal is marked lost.
- **Projects** — project timelines and remarks are visible and editable by the
  assigned staff member.
- **Forecasts** — a rep can log an expected-revenue forecast and a manager can see
  an aggregate summary across the team.
- **Performance** — each staff member can see their own KPI progress; a manager can
  compare the team.
- **Site Availability** — staff can see which advertising sites are booked for a
  given month, create a booking without double-booking a site, and generate a
  client-ready proposal document.
- **Social Media & Posting Calendar** — the marketing team can track a client's
  social content through each production stage (calendar → artwork → posting →
  report) and see a shared posting calendar.
- **Email Marketing** — a campaign can be built, sent to the right audience, and its
  open/click results reviewed afterward; an unsubscribe request is honored
  immediately.
- **Department Task Manager** — internal work can be assigned to a department/
  person, tracked to completion, commented on, and have files attached — without
  requiring a separate approval step (the approval workflow was intentionally
  removed; confirm business stakeholders agree tasks should complete directly).
- **RBAC / User Management** — an admin can create a new staff account, assign the
  right role, and that staff member's access matches expectations on first login;
  a locked-out or flagged account can be restored by an admin.
- **Announcements** — a company-wide announcement reaches all staff and can be
  scheduled to expire.
- **Reporting / Analytics / Data Health** — the dashboards reflect real data
  accurately enough to trust for a business decision, and the data-health page
  surfaces genuinely bad/incomplete records.

### Sign-off table template
| Module | Tested By | Date | Pass / Fail | Notes | Signature |
|---|---|---|---|---|---|
| Contacts | | | | | |
| To-Do / Follow-Up | | | | | |
| Deals | | | | | |
| Projects | | | | | |
| Forecasts | | | | | |
| Performance | | | | | |
| Site Availability | | | | | |
| Social Media & Posting Calendar | | | | | |
| Email Marketing | | | | | |
| Department Task Manager | | | | | |
| RBAC / User Management | | | | | |
| Announcements | | | | | |
| Reporting / Analytics / Data Health | | | | | |

Add a final overall sign-off block: "Approved for production go-live by
_________________ (name/title) on _________ (date)."
