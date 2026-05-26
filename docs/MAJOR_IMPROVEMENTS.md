# Library CRM v2 — Major Improvements Roadmap

> System-wide audit conducted 2026-05-22.
> Scope: backend (Laravel 13), frontend (Vue 3 SPA), database, UX, security, features.
> Methodology: parallel deep-dives across `app/`, `resources/js/`, `database/migrations/`, `routes/`.
>
> **Companion docs:** `CRM_FEATURE_AUDIT.md` (Zoho-parity gaps) and `FEATURE_FILE_MAP.md` (where each feature lives).
> This document covers everything those two do NOT — code quality, security, UX, scalability, and net-new feature ideas.

---

## Executive Summary

The CRM is feature-rich (45% Zoho-parity) and the core domain model is sound. But there are **structural risks** that will hurt the product as it grows: missing authorization checks, no audit trail, N+1 queries in hot paths, no soft deletes, scattered duplicated frontend code, and several large pages with no shared component library.

**The biggest risks (fix first):**

1. Several controllers have **no ownership/authorization checks** — any logged-in user can edit/delete others' contacts, deals, todos.
2. **Hard deletes everywhere** — one accidental click destroys data permanently with no audit trail.
3. **N+1 queries** in `PerformanceController::team()` and unbounded queries in lookups/pagination.
4. **Silent error swallowing** in the frontend (`catch (_) {}`) — users never see what failed.
5. **No 2FA, no audit log, no GDPR export** — blocking for any business handling real customer data.

**Where the biggest UX wins are:**

- Bulk actions, saved filters, global search (Cmd-K), and an activity timeline per contact — these four would dramatically reduce daily clicks.
- Replace browser `confirm()` with a real modal; replace fire-and-forget toasts with an inbox/notification center.
- Extract a `DataTable`, `Modal`, and `useLookups()` composable — every list page is currently ~500 LOC of near-identical code.

---

## Top 10 Priority Fixes (do these first)

| # | Item | Area | Effort | Why it matters |
|---|------|------|--------|----------------|
| 1 | Add ownership checks to every `update`/`destroy` controller method (Policies pattern) | Security | 2–3 days | Today: any user can delete any contact / deal / todo |
| 2 | Add soft deletes + restore UI for Contact, Deal, ToDo, FollowUp, Project | Data integrity | 2 days | One bad click = permanent data loss |
| 3 | Cache `GET /v1/lookups` for 1 hour, invalidate on admin updates | Performance | 0.5 day | Currently fires 10+ queries every form open |
| 4 | Replace browser `confirm()` and fire-and-forget toasts with a `ConfirmModal` + toast queue | UX | 1 day | Hardest UX papercut; affects every destructive action |
| 5 | Build a centralized error handler in [api.js](../resources/js/api.js) — kill all `catch (_) {}` | UX | 1 day | Users today get zero feedback on failures |
| 6 | Extract `DataTable.vue`, `Modal.vue`, `useLookups()` composable | Code quality | 3–4 days | Each list page reimplements the same 500 LOC |
| 7 | Audit log table — record who changed what, when (Spatie laravel-auditing) | Compliance | 1 day | Required for any team larger than 3 people |
| 8 | Global search (Cmd/Ctrl-K) across contacts, deals, projects | UX | 2 days | Single biggest QoL win for daily users |
| 9 | Bulk actions in list pages (multi-select → assign / status / delete) | UX | 2 days | One command changes 100 records vs 100 clicks |
| 10 | Add missing DB indexes on hot paths (see [§3](#3-performance--scalability)) | Performance | 0.5 day | Trivial fix, big query speedup once you have >10k rows |

---

## 1. Security & Authorization

### 1.1 CRITICAL — Missing ownership checks on resource mutations

Multiple controllers verify the resource exists but **not that the current user owns it**. Any authenticated user can mutate any record.

| File | Method | Issue |
|------|--------|-------|
| [ContactController.php](../app/Http/Controllers/Api/V1/ContactController.php) | `update()`, `destroy()` | No `user_id` check |
| [DealController.php](../app/Http/Controllers/Api/V1/DealController.php) | `update()`, `destroy()` | No ownership check; reassign silently dropped for non-admins |
| [ProjectController.php](../app/Http/Controllers/Api/V1/ProjectController.php) | `update()`, `destroy()` | Overwrites `user_id` to `Auth::id()` but doesn't verify caller |
| [ToDoController.php](../app/Http/Controllers/Api/V1/ToDoController.php) | `update()`, `index()` | No verification that user owns the parent contact |
| [FollowUpController.php](../app/Http/Controllers/Api/V1/FollowUpController.php) | `destroy()` | No ownership check |
| [GlobalTodoController.php](../app/Http/Controllers/Api/V1/GlobalTodoController.php) | `destroy()` | No ownership check |

**Fix:** Move authorization to Policy classes (`app/Policies/ContactPolicy.php` etc.) and call `$this->authorize('update', $contact)` at the top of each method. Admins bypass via policy `before()` hook.

### 1.2 CRITICAL — Admin-only controllers lack role middleware

These should be behind `Route::middleware('role:admin|super-admin')` but currently anyone authenticated can hit them:

- [AdminController.php](../app/Http/Controllers/Api/V1/AdminController.php) — lookup CRUD
- [RoleController.php](../app/Http/Controllers/Api/V1/RoleController.php) — RBAC mutation
- [UserManagementController.php](../app/Http/Controllers/Api/V1/UserManagementController.php) — user creation/deletion
- [TerritoryController.php](../app/Http/Controllers/Api/V1/TerritoryController.php) — territory CRUD
- [WebhookController.php](../app/Http/Controllers/Api/V1/WebhookController.php) — webhook CRUD

**Fix:** Wrap routes in [routes/api.php](../routes/api.php) with the role middleware group already used elsewhere.

### 1.3 HIGH — Public lead endpoint is spam-bait

[PublicLeadController.php](../app/Http/Controllers/Api/V1/PublicLeadController.php) is throttled at 10/min/IP — easily abused:

- No CAPTCHA / honeypot
- No phone-format validation before insert
- No deduplication beyond raw DB constraints

**Fix:** Add Cloudflare Turnstile or hCaptcha; honeypot hidden field; stricter phone regex; reject if same `phone` submitted within last 24h.

### 1.4 HIGH — Email verification flow is weak

[EmailVerificationController.php:25-31](../app/Http/Controllers/Api/V1/EmailVerificationController.php) uses raw SHA1 hash with no timestamp — verification links never expire, and user IDs in URLs allow enumeration.

**Fix:** Replace with Laravel's built-in `MustVerifyEmail` + signed URLs (`URL::temporarySignedRoute()`).

### 1.5 HIGH — CSV exports pass token via `?_token=` query

The `?_token=` query param is logged by web servers, proxies, and browser history. Anyone with log access can replay it.

**Fix:** Switch to short-lived signed URLs: backend issues a 5-min-expiring signed URL via `POST /v1/exports/sign`, frontend redirects to it. Or stream via `axios` blob download instead of `window.location.href`.

### 1.6 HIGH — Mass assignment of `created_at` allowed

[ContactController.php:86-91](../app/Http/Controllers/Api/V1/ContactController.php) lets any caller set `created_at` arbitrarily — distorts all reports.

**Fix:** Restrict to admins or remove the feature entirely (admins can backfill via tinker).

### 1.7 MEDIUM — Loose comparison in authorization check

[PerformanceController.php:278](../app/Http/Controllers/Api/V1/PerformanceController.php) uses `!=` instead of `!==` for user-ID comparison. String-vs-int edge cases could bypass the guard.

**Fix:** `(int) $authUser->id !== (int) $userId`.

### 1.8 MEDIUM — Side-effect modification of contact via todo update

[GlobalTodoController.php:99-104](../app/Http/Controllers/Api/V1/GlobalTodoController.php) lets a todo update silently change the parent contact's `status_id` / `type_id`. Surprising behavior, easy to abuse.

**Fix:** Remove. Force explicit contact-update calls.

### 1.9 New — 2FA / MFA

No TOTP support today. For any team CRM, this is table stakes.

**Fix:** `pragmarx/google2fa-laravel` package, optional toggle in profile, enforced for admins.

### 1.10 New — Session timeout

No idle timeout. Stolen laptop = open CRM.

**Fix:** Sanctum already supports `expiration` config — set to 8h. Frontend re-prompts login.

### 1.11 New — Audit log of sensitive actions

No record of: login attempts, exports, deletes, merges, role assignments. Required for SOC2 / GDPR.

**Fix:** Spatie `laravel-activitylog` package; auto-log model changes. Add an admin "Activity Log" page.

### 1.12 New — IP allowlist for admin panel

Mitigates phishing — if a super-admin token leaks, attacker still needs office IP.

**Fix:** Middleware that checks `request()->ip()` against env-configured CIDR list for admin routes.

### 1.13 New — Password policy + breach check

No min length, no complexity requirements, no rotation.

**Fix:** Validation rules: `Password::min(12)->mixedCase()->numbers()->symbols()->uncompromised()`.

---

## 2. Data Integrity

### 2.1 CRITICAL — Hard deletes everywhere

No soft deletes on **any** model. One destructive click cascades through todos, deals, follow-ups, forecasts permanently. No recovery.

**Fix:** Add `softDeletes()` to migrations for Contact, Deal, ToDo, FollowUp, Project, Forecast. Add `use SoftDeletes` trait to models. Build a "Trash" page where admins can restore within 30 days; a scheduler `forceDelete`s items older than 30 days.

### 2.2 HIGH — No audit trail of changes

No way to answer "who changed this deal's stage from Negotiation to Lost?" or "who deleted that contact?"

**Fix:** Spatie laravel-auditing. Per-record history visible on edit pages.

### 2.3 HIGH — `ContactController::merge()` has no conflict resolution

Currently merges all sub-resources into the kept record but doesn't handle duplicates (e.g., two deals on different stages for same product). Last-write-wins silently.

**Fix:** Pre-merge "preview" step shows conflicts, lets admin choose which value wins per field.

### 2.4 MEDIUM — `created_at` mutable on contacts

See §1.6 — also a data-integrity issue: distorts cohort analysis, monthly trend reports, KPI targets.

### 2.5 MEDIUM — Bulk import has no row-level error report

[ImportController.php:179-264](../app/Http/Controllers/Api/V1/ImportController.php) wraps the whole import in a single transaction. One bad row = entire import fails, no per-row diagnostics.

**Fix:** Process in chunks, collect row-level errors into a report. Return `{ imported: 145, skipped: 5, errors: [{row: 23, error: "Invalid email"}, ...] }`. Show in UI as a downloadable error CSV.

### 2.6 MEDIUM — No data retention / archival strategy

What happens at 100k contacts? At 1M activity rows? No archival, no partitioning, no cold-storage strategy.

**Fix:** Define retention policy. Move contacts with no activity 24+ months to `contacts_archive` table; exclude from default queries; searchable in dedicated archive view.

### 2.7 MEDIUM — GDPR data export / right-to-be-forgotten

No way for a contact to request their data or have it deleted. Mandatory if you have EU customers.

**Fix:** Admin button on contact view: "Export all data" (JSON dump of contact + all sub-resources) and "Anonymize" (replaces name/email/phone with `[ANONYMIZED]`, retains aggregate for reports).

---

## 3. Performance & Scalability

### 3.1 CRITICAL — N+1 in `PerformanceController::team()`

[PerformanceController.php:224-244](../app/Http/Controllers/Api/V1/PerformanceController.php) iterates users and fires 8+ count queries each. With 20 users = 160+ queries per dashboard load.

**Fix:** Replace with grouped aggregations:
```php
DB::table('deals')
    ->select('user_id', DB::raw('COUNT(*) as deals_won'), DB::raw('SUM(value) as won_value'))
    ->where('status', 'won')
    ->whereBetween('updated_at', [$from, $to])
    ->groupBy('user_id')
    ->get();
```

### 3.2 HIGH — `LookupController::all()` un-cached, hit on every form open

[LookupController.php:20-35](../app/Http/Controllers/Api/V1/LookupController.php) fires 10+ queries every time any add/edit form mounts. With 100 concurrent users on CRM all day, that's tens of thousands of pointless queries.

**Fix:**
```php
return Cache::remember('crm_lookups', 3600, fn() => [...]);
```
Invalidate in `AdminController` create/update/delete handlers.

### 3.3 HIGH — Missing database indexes on hot paths

| Table | Missing index | Used by |
|-------|--------------|---------|
| `to_dos` | `(completion_status, todo_date)` | TodoList filters, performance metrics |
| `follow_ups` | `(followup_date, completion_status)` | FollowUp list date-range queries |
| `deals` | `(user_id, status)` | DealController summary, performance team report |
| `contacts` | `(user_id, created_at)` | Performance overview, monthly growth |
| `whatsapp_messages` | `(contact_id, created_at)` | WhatsApp history card |

**Fix:** One migration adding all of them. Trivial.

### 3.4 HIGH — Unbounded `per_page` parameter

Multiple list endpoints accept any `per_page` value:
```php
$perPage = (int) $request->input('per_page', 100);
```
A user could request `per_page=100000` and tank the DB.

**Fix:** `min((int) $request->input('per_page', 100), 500)`.

### 3.5 HIGH — Frontend bundle ships every page in one chunk

[vite.config.js:14-24](../vite.config.js) splits `chart.js`, `vue-router`, `axios` but not the 35+ page components. Initial load downloads everything.

**Fix:** Add manual chunk for `if (id.includes('pages/')) return 'pages-${pageName}'` and verify `router/index.js` is using `() => import(...)` for all routes (it is — confirm Vite is actually splitting).

### 3.6 HIGH — `NotificationBell` polls every 60s; no WebSocket

Stale reminders, missed overdue items.

**Fix:** Option A (low effort): drop poll to 30s. Option B (proper): Laravel Reverb / Pusher channel, push-based updates.

### 3.7 MEDIUM — Activity grids recompute on every render

Monthly activity grids in [ContactList.vue:268](../resources/js/pages/ContactList.vue) loop 12 times per row, every render. Not memoized.

**Fix:** `computed()` keyed by contact ID + cached.

### 3.8 MEDIUM — Search inputs not debounced

Every keystroke fires API requests in contact / project pickers.

**Fix:** Wrap with `useDebounceFn(fn, 300)` from `@vueuse/core` (or hand-roll a setTimeout-based debounce).

### 3.9 MEDIUM — Forecast filtering done in PHP, not DB

[ForecastController.php:58-61](../app/Http/Controllers/Api/V1/ForecastController.php) loads all forecasts then `filter()` in-memory. Fine for 100, breaks at 10k.

**Fix:** Push filter into the query builder.

### 3.10 MEDIUM — No virtual scrolling for long lists

Lists of 500+ items render every row to DOM.

**Fix:** `vue-virtual-scroller` on TodoList, FollowUpList, ContactList (when > 100 rows).

### 3.11 New — Full-text search

`LIKE %term%` will get slow as contacts grow.

**Fix:** MySQL FULLTEXT indexes on `contacts(name, email, address)`, switch search query to `MATCH...AGAINST`. Or upgrade to MeiliSearch (best DX) / TypeSense.

### 3.12 New — Health check + uptime monitoring

No `/health` endpoint. UptimeRobot / Pingdom can't monitor.

**Fix:** `GET /health` returns `{db: true, cache: true, queue: backlog_count}`.

---

## 4. Code Quality & Architecture

### 4.1 HIGH — Validation logic inline in every controller method

No `app/Http/Requests/` directory exists. Every action calls `$request->validate([...])` inline, with rules duplicated between store and update.

**Fix:** `php artisan make:request StoreContactRequest UpdateContactRequest StoreDealRequest ...`. Authorize there too.

### 4.2 HIGH — No API resource classes

Controllers return raw Eloquent models or hand-rolled arrays. Exposes internal fields, inconsistent shapes across endpoints (some wrap in `data`, others don't).

**Fix:** Generate `ContactResource`, `DealResource`, etc. Define a canonical response shape (`{ data, meta, links }` for collections; `{ data }` for singletons).

### 4.3 HIGH — Magic strings for status / stage values

`'pending'`, `'completed'`, `'cancelled'`, `'won'`, `'lost'`, `'open'`, `'New Lead'`, `'Negotiation'` etc. scattered across both backend and frontend. Renaming a stage is currently a project-wide search.

**Fix:** PHP enums (`ToDoStatus`, `DealStatus`, `DealStage`) + a shared `resources/js/constants/statuses.js` consumed by Vue.

### 4.4 HIGH — No reusable frontend components

Every list page reimplements the same:
- Filter bar (~50 LOC)
- Table + sort + pagination (~150 LOC)
- Empty state, loading state
- Toast, modal, confirm

Total ~500 LOC × 8 list pages = 4000 LOC of duplication.

**Fix:** Extract:
- `<DataTable :columns :rows :loading :meta @sort @page>` — TodoList, FollowUpList, DealList, ProjectList, ForecastList, ContactList all use it
- `<Modal>` and `<ConfirmModal>` — replaces every `confirm()` and ad-hoc overlay
- `<FilterBar>` — wraps the search/select row used on all list pages
- `<StatusBadge :status :variant>` — used for deal stages, todo statuses, lead source badges
- `<EmptyState :icon :title :actionLabel @action>` — every empty list view

### 4.5 HIGH — `localStorage` access scattered, no store

`localStorage.getItem('crm_user')` called in 10+ Vue files. No reactivity — profile updates don't propagate without manual events.

**Fix:** Pinia store (`useAuthStore`, `useLookupsStore`) — single source of truth, reactive everywhere.

### 4.6 HIGH — Errors silently swallowed everywhere on the frontend

Pattern across pages: `try { ... } catch (_) { /* ignore */ }`. Users get zero feedback on failure. Devs can't debug.

**Fix:** Centralize in [api.js](../resources/js/api.js):
```js
api.interceptors.response.use(r => r, err => {
  showToast(err.response?.data?.message ?? 'Request failed');
  if (import.meta.env.DEV) console.error(err);
  return Promise.reject(err);
});
```
Then remove every `catch (_) {}` in pages.

### 4.7 MEDIUM — No Policies, authorization scattered

Authorization checks are inline `abort_if()` in controllers, duplicated and inconsistent.

**Fix:** `php artisan make:policy ContactPolicy --model=Contact`. Register in `AuthServiceProvider`. Use `$this->authorize()`.

### 4.8 MEDIUM — Observers exist but used inconsistently

`DealObserver` exists; nothing else. Cross-cutting concerns (cache invalidation, audit logging, activity feed) are sprinkled in controllers.

**Fix:** Observers for Contact, ToDo, FollowUp at minimum — handle activity-log writes, cache busts, webhook dispatches in one place.

### 4.9 MEDIUM — Inconsistent response shapes

Some endpoints return `['data' => ...]`, others raw arrays, others `['status' => 'success', 'data' => ...]`. Frontend has to handle each differently.

**Fix:** Pick one shape (Laravel's default `JsonResource` is good — `{ data, meta, links }`) and migrate all endpoints.

### 4.10 MEDIUM — Date/currency formatters duplicated

`new Date(s + 'T00:00:00')` and `toLocaleString('en', {minimumFractionDigits:2})` repeated 20+ times across pages.

**Fix:** `resources/js/utils/format.js` exporting `fmtDate(d)`, `fmtDateTime(d)`, `fmtCurrency(n)`, `fmtRelative(d)`.

### 4.11 MEDIUM — Drag-and-drop state is 6 separate refs

[DealList.vue:252-328](../resources/js/pages/DealList.vue): `draggingId`, `currentDragStage`, `dragOverStage`, `savingId`, `moveToast`, `toastTimer`. Hard to follow.

**Fix:** Extract `useKanbanDrag()` composable that owns one reactive object.

### 4.12 MEDIUM — Inline SVG strings in `App.vue`

[App.vue:146-168](../resources/js/App.vue) — 27 inline SVG path strings in one JS object. Hard to maintain.

**Fix:** Move to `components/icons/*.vue` or adopt `@iconify/vue` / Heroicons.

### 4.13 MEDIUM — No request deduplication

Clicking Search twice fires two identical requests.

**Fix:** Wrap mutation/query in a `useRequest()` composable that tracks in-flight requests and returns the cached promise.

### 4.14 LOW — Pagination metadata format inconsistent across exports

CSV exports vs JSON paginated endpoints use different fields.

**Fix:** Standardize via `JsonResource::collection()`.

---

## 5. UX & Frontend Polish

### 5.1 HIGH — Browser native `confirm()` everywhere

[ContactView.vue:243](../resources/js/pages/ContactView.vue), AdminPanel, Webhooks, RbacPanel, TodoList. Breaks on mobile, no focus trap, no keyboard support, no styling consistency.

**Fix:** `<ConfirmModal>` (see §4.4).

### 5.2 HIGH — No loading states on form submission

Forms submit, button doesn't disable, user clicks twice → duplicate records.

**Fix:** Universal pattern: `:disabled="saving"` + `<LoadingSpinner v-if="saving">` inside the button. Component library helps.

### 5.3 HIGH — No optimistic UI / no rollback on failure

[DealList.vue:366](../resources/js/pages/DealList.vue) drag-to-move shows new column instantly but doesn't rollback if API rejects.

**Fix:** Save the previous state, restore on error, show toast.

### 5.4 HIGH — Tables overflow on tablet width (641-1023px)

Sidebar collapses but column widths stay desktop-sized — horizontal scroll.

**Fix:** Sticky table headers (`position: sticky; top: 0`); media query breakpoints at 768px and 1024px hiding less-critical columns.

### 5.5 HIGH — Notification bell polls only on page load

[NotificationBell.vue:173-177](../resources/js/components/NotificationBell.vue) — 60-second poll. Urgent reminders sit unread.

**Fix:** 30s poll minimum; long-term, switch to push (see §3.6).

### 5.6 MEDIUM — Empty states have no call-to-action

"No projects found" — fine. But no "Create one →" link.

**Fix:** `<EmptyState>` component with `actionLabel` slot.

### 5.7 MEDIUM — Form validation feedback is hint-only, not inline error

Required `<span class="req">*</span>` markers exist but errors don't show red on blur/submit.

**Fix:** `<FormField>` wrapper component owning label + error state.

### 5.8 MEDIUM — No keyboard shortcuts anywhere

No Cmd-K search, no `g c` to go to contacts, no `j/k` navigation in lists.

**Fix:** `@vueuse/core` `useMagicKeys()`. Start with Cmd-K global search (§6.1).

### 5.9 MEDIUM — Inconsistent button sizing & spacing

`.btn` height varies 28/36/44px across pages; padding `0 10px` vs `0 14px` randomly.

**Fix:** Define a size scale in scoped CSS variables: `--btn-sm-h: 28px; --btn-md-h: 36px; --btn-lg-h: 44px`. Or migrate to a small utility-CSS layer.

### 5.10 MEDIUM — No mobile-optimized layout

Sidebar takes 240px on a 390px-wide iPhone — leaves 150px for content. No bottom-nav fallback. No swipe-to-back.

**Fix:** Below 640px, switch to bottom-tab nav (Home / Contacts / Tasks / More). Hide sidebar entirely.

### 5.11 MEDIUM — Emoji-only action buttons are not accessible

"✏️" / "🗑️" with no aria-label. Screen readers announce "pencil" / "wastebasket".

**Fix:** `<button aria-label="Edit"><PencilIcon /></button>` with hidden text or icon component.

### 5.12 LOW — No multi-step form progress indicator

ContactAdd, ProjectAdd are 2-step but only have a text label "Step 1 of 2".

**Fix:** Numbered-pill progress bar.

### 5.13 New — Dark mode

Theme setting exists in Settings.vue (light/dark/system) but most pages have hardcoded colors. Half-finished.

**Fix:** CSS custom properties (`--bg-primary`, `--text-primary` etc.) defined per theme on `<body>`. Audit all hardcoded `#fff` / `#000` / `#333` etc.

### 5.14 New — Progressive Web App (PWA)

Installable, basic offline cache, push notifications.

**Fix:** `vite-plugin-pwa` adds manifest + service worker. Cache shell + recent contacts.

### 5.15 New — In-app onboarding tour

New users land on an empty CRM with no guidance.

**Fix:** Shepherd.js or Driver.js tour on first login. Skip button.

---

## 6. New High-Value Features

### 6.1 Global search (Cmd/Ctrl-K)

Single biggest QoL improvement. Search contacts, deals, projects, todos by name/email/phone from any page.

**Implementation:** `<CommandPalette>` modal triggered by `useMagicKeys()`. Backend `GET /v1/search?q=` returns top 20 across entities.

### 6.2 Bulk actions in list pages

Checkbox select + "Assign to..." / "Change status..." / "Delete" / "Export selected".

**Implementation:** Add `selected[]` ref to list components, `POST /v1/contacts/bulk` endpoint with action + ids.

### 6.3 Saved filters / views

User configures "My active deals > $50k" once, one-click to reapply.

**Implementation:** `user_saved_views` table: `{user_id, page, name, filters_json}`. Sidebar shows under each section.

### 6.4 Activity timeline per contact

Today: ContactView has 5 separate cards (calls, emails, WhatsApp, todos, follow-ups). Consolidate into a chronological timeline.

**Implementation:** Backend endpoint `GET /v1/contacts/{id}/timeline` unions all activity types with type discriminator, sorted by date.

### 6.5 Tags / labels system

Free-form taxonomy: VIP / At-risk / Q2-target / Hot-lead.

**Implementation:** `tags` table + `contact_tags` pivot. Already exists as a pattern via Spatie laravel-tags package.

### 6.6 File attachments

Per contact and per deal. Contracts, proposals, screenshots.

**Implementation:** `attachments` polymorphic table (`attachable_type`, `attachable_id`). Laravel media library package handles uploads.

### 6.7 Rich-text notes

Replace plain `remark` textarea with Tiptap editor. Mentions, lists, links.

**Implementation:** `@tiptap/vue-3`. Store as HTML or JSON in existing column.

### 6.8 Recurring tasks

Option on TodoAdd: "Repeat every Daily / Weekly / Monthly". Auto-creates next on completion.

**Implementation:** `recurrence_rule` column (iCalendar RRULE string), scheduler creates next instance.

### 6.9 Calendar view for tasks

Switch TodoList between list / kanban / calendar.

**Implementation:** `@fullcalendar/vue3` — drag to reschedule.

### 6.10 Inline editing in tables

Click a cell, edit, save on blur. Skip the "open → edit → save → back" loop.

**Implementation:** `<EditableCell>` component, `PATCH /v1/contacts/{id}` with single-field payload.

### 6.11 Quick-add modal from anywhere

Keyboard shortcut "+" or floating action button → modal with type picker (contact / deal / todo) → minimal form.

**Implementation:** Reuse forms in a modal context. Pinia store action `useQuickAdd().open('contact')`.

### 6.12 Email templates library

Reps spend hours retyping the same follow-up. Templates with `{{contact.name}}` etc.

**Implementation:** `email_templates` table; insert into compose form; Tiptap handles variables.

### 6.13 Win/loss reason analysis dashboard

`lost_reason` exists on deals but isn't surfaced. Pivot by reason, win rate by reason.

**Implementation:** New tab in Performance.vue — pie chart of lost reasons, table of win-rate-by-reason.

### 6.14 Stale deal alerts

Deals with no activity 14+ days → "Re-engage" list with one-click follow-up.

**Implementation:** `GET /v1/deals/stale?days=14` scoped by user. Surface on dashboard.

### 6.15 Pipeline velocity / time-in-stage

How long deals sit in each stage. Identify bottlenecks.

**Implementation:** `deal_stage_history` table (write via DealObserver). Aggregate avg days per stage.

### 6.16 Lead source ROI / conversion funnel

By lead source: leads → contacts → opportunities → won. Conversion %, avg deal value.

**Implementation:** Already have `lead_source` on contacts and `value` on deals. New analytics endpoint.

### 6.17 Forecast accuracy tracking

Compare forecast value (deals × probability) at month-start vs actual closed at month-end. Track forecaster error %.

**Implementation:** Daily snapshot job, `forecast_snapshots` table.

### 6.18 Calendar sync (Google/Outlook)

iCal feed `/v1/me/calendar.ics` of due todos. User pastes URL into Google Calendar.

**Implementation:** Generate iCal from todos with `eluceo/ical` package.

### 6.19 Slack / Teams notifications

Deal won / lost / WhatsApp lead in → Slack channel.

**Implementation:** Existing webhook system; add Slack-format adapter (Block Kit JSON).

### 6.20 Click-to-call (Twilio)

Phone icon in contact view → initiate call → auto-log.

**Implementation:** Twilio Voice SDK; webhook to log into `contact_calls`.

---

## 7. Testing & DevOps

### 7.1 HIGH — Test coverage is ~10%

Only WhatsApp + Forecast tests exist. No tests for:
- Authorization (the §1 issues would have been caught)
- CRUD happy paths on Deals / Contacts / ToDos / Projects
- Import validation
- Webhook signing
- N+1 (use `n+1` detector package in test env)

**Fix:** Target 60%+ coverage. Pest if greenfield. Add `beyondcode/laravel-query-detector` to fail tests with N+1.

### 7.2 HIGH — No CI

No GitHub Actions / GitLab CI config visible. PRs ship untested.

**Fix:** Action runs `composer test`, `npm run build`, PHPStan / Larastan level 5.

### 7.3 MEDIUM — No error monitoring

Production errors go to `storage/logs/laravel.log` and that's it.

**Fix:** Sentry integration (`sentry/sentry-laravel`). Frontend: `@sentry/vue`.

### 7.4 MEDIUM — Queue setup undocumented

CLAUDE.md mentions `php artisan queue:work` for WhatsApp but no driver guidance, no supervisor config.

**Fix:** README section on prod queue: database driver, systemd or Supervisor unit, restart on deploy.

### 7.5 MEDIUM — No staging environment playbook

No documented "spin up staging" steps.

**Fix:** Document: clone prod DB → sanitize PII → seed lookups → point to staging WhatsApp number. Add `make staging-refresh` recipe.

### 7.6 MEDIUM — No automated backup verification

Whatever backups exist aren't proven restorable.

**Fix:** Weekly cron: dump DB, restore to scratch instance, assert row counts on key tables, alert if fails.

### 7.7 LOW — Environment variable validation missing

If `WHATSAPP_APP_SECRET` is unset, webhook signing silently breaks at runtime.

**Fix:** AppServiceProvider boot: assert required envs present, throw at boot if missing.

---

## 8. Documentation

### 8.1 README is minimal

No "How to set up locally", no architecture overview for new devs.

**Fix:** Expand README: prerequisites, setup commands, "where things live" map (reference FEATURE_FILE_MAP.md), how to add a new lookup type, how to add a webhook event.

### 8.2 No API reference

No OpenAPI spec, no Postman collection. Consumers (mobile app future, integrations) have to read controller code.

**Fix:** Auto-generate via `darkaonline/l5-swagger` or `scribe`. Publish at `/docs/api`.

### 8.3 Inline tooltips / help

No `?` icons on complex fields (probability %, forecast weighting, lead source).

**Fix:** `<HelpTooltip text="...">` component, sprinkle on Performance.vue, ForecastAdd.vue.

---

## Roadmap — Suggested Phasing

### Phase 1 (Week 1-2): Security & Data Integrity Foundation
- Policies for Contact, Deal, ToDo, FollowUp, Project (§1.1, §4.7)
- Role middleware on admin controllers (§1.2)
- Soft deletes + Trash page (§2.1)
- Audit log via laravel-activitylog (§1.11, §2.2)
- Lookup cache (§3.2)
- DB indexes migration (§3.3)
- Per-page limit clamp (§3.4)

### Phase 2 (Week 3-4): Frontend Foundation
- Centralized error handler in api.js, kill all `catch (_) {}` (§4.6)
- Pinia store for auth + lookups (§4.5)
- Extract `DataTable`, `Modal`, `ConfirmModal`, `EmptyState`, `StatusBadge`, `FormField` (§4.4)
- Format utilities (§4.10)
- Loading states + optimistic UI (§5.2, §5.3)

### Phase 3 (Week 5-6): Top UX Wins
- Global search Cmd-K (§6.1)
- Bulk actions on list pages (§6.2)
- Activity timeline per contact (§6.4)
- Saved filters (§6.3)
- Tags system (§6.5)

### Phase 4 (Week 7-8): Compliance & Security Layer
- 2FA (§1.9)
- Session timeout (§1.10)
- GDPR export / anonymize (§2.7)
- Password policy (§1.13)
- Public lead anti-spam (§1.3)

### Phase 5 (Week 9-10): Performance & Scale
- N+1 fixes in PerformanceController (§3.1)
- Full-text search (§3.11)
- Virtual scrolling (§3.10)
- WebSocket notifications (§3.6)
- Bundle splitting verification (§3.5)

### Phase 6 (Week 11-12): Reporting & Insights
- Win/loss analysis (§6.13)
- Stale deal alerts (§6.14)
- Pipeline velocity (§6.15)
- Lead source ROI (§6.16)

### Phase 7+ (Future): Polish & Integrations
- PWA (§5.14)
- Mobile-optimized UI (§5.10)
- Dark mode (§5.13)
- Calendar sync (§6.18)
- Slack notifications (§6.19)
- Click-to-call (§6.20)
- Email templates + sync (§6.12)
- File attachments + rich-text notes (§6.6, §6.7)
- Onboarding tour (§5.15)

---

## Severity Summary

| Severity | Count | Areas |
|----------|-------|-------|
| CRITICAL | 8 | Missing authorization, hard deletes, admin route guards, N+1 in team report, silent error swallowing, ownership checks |
| HIGH | 26 | Email verification, lookup caching, missing indexes, bundle splitting, modal/confirm, form requests, API resources, magic strings, component library, error handling, loading states, optimistic UI, table responsiveness, notification polling |
| MEDIUM | 30 | Audit log, soft deletes, retention, validation, response shapes, observers, formatters, drag state, debounce, search, empty states, mobile, accessibility, dark mode, virtual scrolling |
| NEW FEATURES | 20+ | Global search, bulk actions, saved filters, timeline, tags, attachments, rich notes, recurring tasks, calendar view, inline edit, quick-add, templates, win/loss, stale deals, velocity, source ROI, calendar sync, Slack, click-to-call, 2FA |

**Total: ~85 actionable items.** Prioritize Phase 1 — every day without ownership checks and soft deletes is a day from a "who deleted my data?" incident.
