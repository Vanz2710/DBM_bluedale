# Deferred Scalability Work

These items were identified in the May 2026 scalability audit but deferred because features are still being added.
Implement these once the system is feature-stable, **before going to production with real user load** or when data exceeds ~20K contacts / 100K todos.

---

## Priority 1 — Do before ~20K records or 100+ concurrent users

### 1. ~~Cache LookupController (1 hour TTL)~~ ✅ Done 2026-06-04

`Cache::remember('lookups', 3600, ...)` added to `LookupController::all()`.
`Cache::forget('lookups')` added to `AdminController` (store/update/destroy) and `UserManagementController` (store/update/destroy) so the cache busts whenever any lookup value or user record changes.

---

### 2. Cache PerformanceController overview (5 min TTL)

**File:** `app/Http/Controllers/Api/V1/PerformanceController.php`

**Problem:** Every performance dashboard load fires 10+ COUNT queries. With 20 users refreshing every few minutes this becomes hundreds of DB round-trips per minute.

**Fix:** Cache per user per period key:
```php
$cacheKey = "perf_overview_{$userId}_{$period}_{$from}_{$to}";
return Cache::remember($cacheKey, 300, function () use (...) {
    // existing KPI logic
});
```

**Invalidate** when a todo/follow-up/deal is created or completed (use model observers or Controller after-save calls).

---

### 3. Refactor PerformanceController to single aggregation query

**File:** `app/Http/Controllers/Api/V1/PerformanceController.php` — `overview()` and `team()` methods

**Problem:** Currently fires one `COUNT()` query per KPI metric per user. The `team()` method multiplies this by N users (O(N×10) queries).

**Fix:** Replace the individual counts with a single query using conditional aggregation:
```sql
SELECT
  COUNT(CASE WHEN table = 'contacts' AND user_id = ? AND created_at BETWEEN ? AND ? THEN 1 END) as contacts_added,
  COUNT(CASE WHEN table = 'to_dos'   AND user_id = ? AND date_created BETWEEN ? AND ? THEN 1 END) as todos_created,
  ...
FROM dual
```

Or use `DB::table()->selectRaw('COUNT(CASE WHEN ...) as metric_name, ...')` per entity and merge results. This reduces from 10+ queries to 1 per entity table.

---

### 4. Paginate contact emails and calls

**Files:**
- `app/Http/Controllers/Api/V1/ContactEmailController.php` — `index()` method, line 14
- `app/Http/Controllers/Api/V1/ContactCallController.php` — `index()` method, line 14

**Problem:** Loads ALL emails/calls for a contact into memory. A contact with 500+ emails will OOM.

**Fix:** Replace `.get()` with `.paginate(20)` and update the Vue component to handle paginated responses with a "Load more" button or infinite scroll.

```php
// Before
$emails = $contact->emails()->with('user')->orderByDesc('emailed_at')->get();

// After
$emails = $contact->emails()->with('user')->orderByDesc('emailed_at')->paginate(20);
```

---

### 5. ~~Add `per_page` cap to all list endpoints~~ ✅ Done 2026-06-04

`min((int) $request->input('per_page', N), 500)` applied to:
- `ContactController::index()` and `daily()`
- `DealController::index()`
- `FollowUpController::index()`
- `GlobalTodoController::index()`

---

## Priority 2 — Do before ~50K contacts

### 6. Full-text search on contacts.name

**File:** `app/Http/Controllers/Api/V1/ContactController.php` — search logic

**Problem:** `WHERE name LIKE '%term%'` forces a full table scan. At 50K+ contacts, search becomes noticeably slow (0.5s+).

**Options (pick one):**

**Option A — MySQL Full-Text (simplest, no new infrastructure):**
```php
// Migration:
$table->fullText('name');

// Query:
Contact::whereFullText('name', $search)->paginate(25);
```
Limitation: no partial-word matching (use `*` suffix: `"john*"` in boolean mode).

**Option B — Meilisearch + Laravel Scout (best UX, requires separate service):**
```bash
composer require laravel/scout
# Add Meilisearch driver, sync contacts index
```
Gives instant, typo-tolerant search. Requires running Meilisearch server.

---

### 7. Refactor AnalyticsController to fewer queries

**File:** `app/Http/Controllers/Api/V1/AnalyticsController.php`

**Problem:** Fires 5 separate `GROUP BY` queries sequentially (by status, industry, category, user, type). These all scan the same `contacts` table.

**Fix:** Run them all concurrently using `DB::statement` in parallel, or restructure as a single pass that groups by multiple dimensions. At minimum, add the applied filters to all 5 queries consistently so they benefit from indexes.

---

### 8. Fix GlobalTodoController N+1 subquery

**File:** `app/Http/Controllers/Api/V1/GlobalTodoController.php`

**Problem:** Uses `addSelect([last_followup_date => FollowUp::...subquery...])` which fires one subquery per todo row returned. 100 todos per page = 100 extra queries.

**Fix:** Either:
- Use a `JOIN` with `MAX(followup_date)` grouped by `todo_id` instead of a correlated subquery
- Or eager-load the latest follow-up as a relationship with `->latestOfMany()`

```php
// Model relationship:
public function latestFollowUp(): HasOne
{
    return $this->hasOne(FollowUp::class)->latestOfMany('followup_date');
}

// Controller:
->with('latestFollowUp')
```

---

### 9. Cap Forecast detail rows

**File:** `app/Http/Controllers/Api/V1/ForecastController.php` — `summary()` method, line ~80

**Problem:** `->get()` with no limit on the forecast rows query. A filtered view could load tens of thousands of rows into PHP memory.

**Fix:** Add a pagination or reasonable hard limit:
```php
$rows = (clone $base)->orderBy('forecast_date')->paginate(500);
// or for the summary aggregate: ensure only aggregated totals are returned, not raw rows
```

---

## Priority 3 — When you have Redis available

### 10. Switch cache driver from `database` to Redis

**File:** `.env`

**Problem:** The default cache driver is `database`, which means cache reads/writes hit the DB — defeating the purpose.

**Fix:**
```env
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

Install Redis locally or via Docker. All `Cache::remember()` calls from fixes #1 and #2 above benefit automatically.

---

### 11. Queue expensive export/import jobs

**Currently:** CSV exports stream synchronously (fine for now). Large imports (`ImportController`) run inline.

**Future fix:** Move imports > 500 rows to a queued job with progress tracking using Laravel's built-in Queue + a simple status endpoint the frontend polls.

---

## Notes

- All index migrations from this audit were applied on **2026-05-28** via `2026_05_28_000001_add_missing_scalability_indexes.php`.
- The LIKE search on `contacts.name` (Fix #6) is the single biggest UX-affecting issue at scale — prioritize it as soon as contacts exceed ~30K rows.
- Fixes #1–5 can be done in a single afternoon and have the highest impact per effort.

---

# Deferred Data Quality Work

These items were identified in the May 2026 data quality audit. Applied fixes (reminder_reads orphan cleanup, ContactIncharge name required, lookup table unique constraints) were completed on **2026-05-28**.

---

## CRITICAL — Do before going to production

### DQ-1. ~~Soft Deletes on Contact (and User)~~ ✅ Done 2026-06-04

`SoftDeletes` added to `Contact`, `Deal`, and `Project` models. `User` already had it.
Migration `2026_06_04_000001_add_soft_deletes_to_contacts_deals_projects.php` added `deleted_at` to all three tables and was run successfully.
The `booted()` cleanup observer in `Contact` was updated from `static::deleting` to `static::forceDeleting` so `reminder_reads` are only cleaned up on permanent deletion, not soft-delete.

---

## HIGH — Do before going to production

### DQ-2. ENUM / CHECK constraints on status fields

**Problem:** The following fields are validated at the application layer only. A raw SQL insert, a seeder bug, or a future developer bypassing the controller can store invalid values that silently corrupt reports.

| Field | Table | Valid values |
|-------|-------|-------------|
| `completion_status` | `to_dos` | pending, completed, cancelled |
| `completion_status` | `follow_ups` | pending, completed, cancelled |
| `status` | `deals` | open, won, lost |
| `stage` | `deals` | New Lead, Contacted, Proposal Sent, Negotiation, Won, Lost |
| `status` | `email_campaigns` | Draft, Scheduled, Sent |
| `status` | `posting_calendar_reminders` | planned, design, approval, scheduled, posted |
| `content_calendar_status` | `social_media_reminders` | pending, wfa, approved |
| `artwork_editing_status` | `social_media_reminders` | pending, wfa, approved |
| `posting_status` | `social_media_reminders` | pending, wfa, approved, scheduling, posted |
| `report_status` | `social_media_reminders` | pending, wfa, done, completed |

**Fix (Option A — ENUM, simplest):**
```php
// Replace the string column with an ENUM:
$table->enum('completion_status', ['pending', 'completed', 'cancelled'])->default('pending')->change();
```
Downside: adding a new valid value requires a new migration.

**Fix (Option B — CHECK constraint, more flexible):**
```php
DB::statement("ALTER TABLE to_dos ADD CONSTRAINT chk_completion_status 
    CHECK (completion_status IN ('pending','completed','cancelled'))");
```
CHECK constraints are supported in MySQL 8.0.16+.

**Do this AFTER features are stable** — if you're still adding new status values (e.g. a new deal stage), defer until the list is final.

---

### DQ-3. Audit trail — created_by / updated_by

**Problem:** The system records *when* something was created/updated but not *who* changed it last. For a multi-user CRM this becomes a problem when investigating data issues or disputes.

**Fix:** Add `created_by` and `updated_by` columns to core entities and auto-populate them:

```php
// Migration for contacts (repeat for deals, projects, todos):
$table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
$table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

// Model observer or booted event:
static::creating(fn($m) => $m->created_by = Auth::id());
static::updating(fn($m) => $m->updated_by = Auth::id());
```

**Priority tables:** `contacts`, `deals`, `projects`, `to_dos`, `follow_ups`

---

## MEDIUM — Nice to have

### DQ-4. Add timestamps to tables missing them

The following tables have no `created_at` / `updated_at` and no way to know when records were created:

| Table | Impact |
|-------|--------|
| `reminder_reads` | Can't tell when a user read a reminder |
| `round_robin_state` | Can't track last assignment time |
| `webhooks` | Can't tell when a webhook was registered |

**Fix:**
```php
Schema::table('reminder_reads', function (Blueprint $table) {
    $table->timestamps();
});
// Repeat for round_robin_state, webhooks
```

---

### DQ-5. Enforce territory_id cleanup on contact when territory deleted

**Problem:** Deleting a territory leaves all contacts pointing to a now-dead `territory_id`. The FK is nullable so no crash occurs, but contacts silently lose their territory classification.

**Fix:** Either cascade null on delete (already the correct behavior if FK is set to `nullOnDelete`) or add a `Territory::deleting` event that nulls out the field first:

```php
static::deleting(function (Territory $territory) {
    Contact::where('territory_id', $territory->id)->update(['territory_id' => null]);
});
```

Check whether the current FK on `contacts.territory_id` already has `nullOnDelete()` — if it does, MySQL handles this automatically.

---

# Deferred Frontend Structure Work

These items were identified in the May 2026 folder structure audit. Applied fixes (`.env.example` documented, `TaskEdit.vue` → `TodoEdit.vue`, `MarketingEmailDemo.vue` → `EmailCampaigns.vue`) were completed on **2026-05-28**.

Do these **after features are stable** — splitting large components mid-development causes merge conflicts and makes active work harder.

---

## CRITICAL — Do before handing over to another developer

### FS-1. Split ContactList.vue (3,042 lines)

**File:** `resources/js/pages/ContactList.vue`

**Problem:** A single 3,000-line Vue component is the biggest maintainability risk in the codebase. A new developer asked to change one tab's filter behaviour has to understand 3,000 lines before touching anything.

**Fix:** Create a `resources/js/pages/contacts/` subdirectory and split by tab:

```
pages/contacts/
├── ContactListPage.vue      (shell: tabs, shared state, API calls)
├── ContactsTab.vue          (the contacts table + filters)
├── SummaryTab.vue           (summary/analytics tab)
├── TasksTab.vue             (todos tab inside contacts)
└── ForecastTab.vue          (forecast tab inside contacts)
```

Each tab component receives data as props from `ContactListPage.vue` and emits events back. Shared filter state stays in the parent.

---

### FS-2. Split ProductAvailability.vue (1,714 lines)

**File:** `resources/js/pages/ProductAvailability.vue`

**Problem:** Mixes product listing, booking management, photo uploads, and form logic in one file.

**Fix:** Split into:
```
pages/products/
├── ProductAvailabilityPage.vue   (container)
├── ProductsGrid.vue              (listing)
├── BookingForm.vue               (booking modal/form)
└── ProductPhotoUpload.vue        (photo management)
```

---

### FS-3. Split Performance.vue (1,106 lines)

**File:** `resources/js/pages/Performance.vue`

**Problem:** 4 tabs (Overview, Activity, Team, Targets) in one 1,100-line file. Adding a new KPI card requires understanding the whole file.

**Fix:** Split into:
```
pages/performance/
├── PerformancePage.vue      (shell: tab switching)
├── OverviewTab.vue          (KPI cards + target progress + overdue)
├── ActivityTab.vue          (legacy task report)
├── TeamTab.vue              (admin cross-user comparison)
└── TargetsTab.vue           (KPI target editor)
```

---

### FS-4. Split RbacPanel.vue (868 lines)

**File:** `resources/js/pages/RbacPanel.vue`

**Problem:** Mixes role management, permission assignment, and user-role assignment in one file.

**Fix:** Split into:
```
pages/rbac/
├── RbacPage.vue             (container)
├── RolesTab.vue             (role CRUD)
├── PermissionsTab.vue       (permission list + assignment)
└── UsersTab.vue             (user-role assignments)
```

---

### FS-5. Split Settings.vue (865 lines)

**File:** `resources/js/pages/Settings.vue`

**Problem:** Unclear scope — review what sections are in here and split into focused sub-components if mixed concerns.

**Fix:** Audit the sections first, then extract each into a dedicated component under `pages/settings/`.

---

## HIGH — Do alongside the component splits

### FS-6. Extract reusable composables

**File:** `resources/js/composables/` (currently only `useSettings.js`)

**Problem:** Filtering logic, pagination, search state, and form validation are duplicated across all 39 page components. A bug fix in one filter has to be applied 10+ times.

**Fix:** Create one composable per concern:

```js
// useFilters.js — shared filter state + reset logic
// usePagination.js — page/perPage state + URL sync
// useSearch.js — debounced search + clear
// useFormValidation.js — common required field, email, date validation
// useLookups.js — fetch + cache /api/v1/lookups on mount
```

Start with `useLookups.js` — every add/edit form calls the same `GET /api/v1/lookups` endpoint. Centralising it also makes it easy to add frontend caching later.

---

## MEDIUM — Nice to have

### FS-7. Standardize API endpoint naming

**Problem:** API paths mix naming conventions:
- Kebab-case: `/social-media-reminders`, `/product-availability`, `/posting-calendar`
- Single word: `/forecasts`, `/contacts`, `/deals`
- Inconsistent: `/admin/performance-targets` (hyphen) vs `/admin/rbac` (no hyphen)

**Fix:** Pick one convention (kebab-case recommended since it matches all multi-word paths already) and apply consistently in `routes/api.php`. Update Vue `api.js` calls to match.

This is low-priority since it's internal and the inconsistency doesn't cause bugs — but it confuses new developers reading the route file.

---

### FS-8. Rename remaining misleading route names in router

**Problem:** After renaming the files, a few route `name` values are still inconsistent:
- Route `task-edit` maps to `TodoEdit.vue` — should be `todo-edit`
- Route `task-add` maps to `TaskAdd.vue` which adds a todo from a contact — consider `contact-todo-add`

**Fix:** Update the `name` fields in `resources/js/router/index.js` and update any `router.push({ name: 'task-edit' })` calls in Vue components to match.

Search for usages first: `grep -r "task-edit\|task-add" resources/js/`

---

### FS-9. Clarify CrmList.vue / CrmView.vue vs ContactList.vue / ContactView.vue

**Files:** `resources/js/pages/CrmList.vue`, `resources/js/pages/CrmView.vue`

**Problem:** Four files with near-identical names live side by side in `pages/`. A new developer cannot tell the difference without opening both. Currently:
- `/list` → `ContactList.vue` — the primary contacts view (multi-tab, with todos/forecasts/summary)
- `/crm` → `CrmList.vue` — a different filtered view of contacts

**Fix:** Either rename to make the distinction obvious (e.g. `CrmList.vue` → `LegacyContactList.vue` or `ContactBrowse.vue`) or add a one-line comment at the top of each file explaining what makes it different from the other.

---

### FS-10. Subfolder `app/Models/` by domain

**Problem:** All 30+ models sit in a flat `app/Models/` directory except RBAC models which are in `app/Models/Admin/`. The inconsistency confuses new developers ("why do only Role and Permission get a subfolder?").

**Fix (Option A — full domain grouping, cleaner):**
```
app/Models/
├── Contact/         Contact, ContactStatus, ContactType, ContactCategory,
│                    ContactIndustry, ContactArea, ContactIncharge,
│                    ContactEmail, ContactCall
├── Sales/           Deal, Project, Forecast, ForecastProduct, ForecastType, ForecastResult
├── Activity/        ToDo, FollowUp, Task, KpiTarget, PerformanceTarget
├── Marketing/       SocialMediaReminder, SocialMediaPackage, PostingCalendarReminder,
│                    EmailCampaign, AdvertisingProduct, AdvertisingProductBooking
├── Integration/     WhatsAppMessage, Webhook, RoundRobinState
├── Admin/           Role, Permission (already here)
└── (root)           User, Territory, ReminderRead
```

**Fix (Option B — minimal, just flatten Admin/ back to root):**
Move `Admin/Role.php` and `Admin/Permission.php` to `app/Models/` root so everything is consistently flat. Update namespaces and imports.

Option B is much less effort and removes the inconsistency without a large refactor.

---

### FS-11. Fix tailwind.config.js content scanning

**File:** `tailwind.config.js`

**Problem:** The content array only scans `.blade.php` files, not `.vue` files. If any Tailwind utility classes are added to Vue components in the future, they will be purged from the production build and silently disappear.

**Fix:** Add Vue files to the content array:
```js
content: [
    './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
    './storage/framework/views/*.php',
    './resources/views/**/*.blade.php',
    './resources/js/**/*.vue',   // ← add this
],
```

**Low priority** — the project currently uses scoped CSS in Vue components, not Tailwind utilities. Only relevant if that changes.
