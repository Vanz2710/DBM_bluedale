# Deferred Work & Development Roadmap

## Current Phase Assessment — 2026-06-15

**Phase: Late Active Development → Pre-Staging**

The core CRM is feature-rich and has a staging deployment on InfinityFree (`<staging-url>`). Feature additions are still in progress (most recently: CalendarPicker with todo date indicators, Reports user scoping, UI tokenization pass). `TESTING_PHASE.md` exists but is 0% complete — formal testing has not started. The production target is cPanel.

**Key facts driving priorities:**
- Features are still being added → component splits and constraint migrations would conflict with active work
- InfinityFree staging is up but not fully bootstrapped (private/STAGING_TODO.md steps 2–6 pending)
- InfinityFree required non-standard code hacks (`usePublicPath`, IIFE build format) that must be reverted before cPanel
- `docs/current-code-progress.md` and `docs/upgrade-checklist.md` are pre-rewrite plain PHP artifacts — they no longer describe the codebase

---

## DO NOW — Safe During Active Feature Development

These are additive and low-risk. No conflicts with ongoing feature work.

### ~~NOW-1. Audit Trail — `created_by` / `updated_by`~~ ✅ Done 2026-06-15

**Why now:** The longer this waits, the more records exist without attribution. Fully additive — just nullable columns and model events. Every new record from this point on will be tracked. Hardest to retrofit after data accumulates.

**Files:** New migration + `booted()` events on `Contact`, `Deal`, `Project`, `ToDo`, `FollowUp`

```php
// Migration (one per table, or combine into one):
$table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
$table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

// Model booted():
use Illuminate\Support\Facades\Auth;
static::creating(fn($m) => $m->created_by = Auth::id());
static::updating(fn($m) => $m->updated_by = Auth::id());
```

---

### ~~NOW-2. Extract `useLookups.js` Composable~~ ✅ Done 2026-06-15

**Why now:** Every add/edit form copies the same `GET /api/v1/lookups` fetch on mount. Centralizing it means every new form written from here on uses one line instead of a repeated block. Low risk, immediate ROI.

**File:** Create `resources/js/composables/useLookups.js`

```js
import { ref, onMounted } from 'vue';
import api from '../api.js';

export function useLookups() {
  const lookups = ref({});
  const loading = ref(false);
  onMounted(async () => {
    loading.value = true;
    try {
      const { data } = await api.get('/lookups');
      lookups.value = data;
    } finally {
      loading.value = false;
    }
  });
  return { lookups, loading };
}
```

---

## BEFORE TESTING — Must complete before TESTING_PHASE.md can run

### BT-1. Finish InfinityFree Staging (private/STAGING_TODO.md steps 1–6)

The following are still pending in `private/STAGING_TODO.md`:

- [ ] Delete the now-empty `public/` folder from `htdocs/` in File Manager
- [ ] Create `.env` on the server (File Manager → New File, paste the `.env` block from `private/STAGING_TODO.md`)
- [ ] Create `run_setup.php` and visit it to run: migrate, seed, config:cache, route:cache, view:cache, storage:link
- [ ] **Delete `run_setup.php` immediately after** — it grants anyone full database access
- [ ] Visit `<staging-url>` and confirm login page loads
- [ ] Log in as super-admin, check a few pages, verify no 500 errors

Until staging is bootstrapped end-to-end, `TESTING_PHASE.md` cannot be formally checked off.

---

### BT-2. Build Production Assets Locally (TESTING_PHASE.md A1)

InfinityFree has no Node.js. Build must happen locally before upload:

```bash
# In local .env, set:
VITE_BASE_URL=/

npm run build
# Confirm public/build/ is generated, then upload to staging
```

Restore local `VITE_BASE_URL` to the XAMPP path after.

---

### BT-3. Email Provider Decision (TESTING_PHASE.md A3)

Gmail SMTP is fine for staging. Choose:

- **Option A (quick):** Keep Gmail SMTP for staging — add credentials to staging `.env`
- **Option B (proper for production):** Create free Brevo account (300/day free), swap SMTP creds in `.env.production.example`

Minimum email tests to confirm delivery from the live server:
1. First-login admin alert email
2. Inactivity flag email
3. Password change system alert (in-app bell only — no email)

---

### BT-4. Note InfinityFree-Specific Code Hacks for cPanel Revert

**These must be reverted when deploying to cPanel.** Documented in `private/INFINITYFREE_STATUS.md` Part 2.

| File | InfinityFree change | cPanel action |
|------|--------------------|--------------------|
| `bootstrap/app.php` | `->usePublicPath(dirname(__DIR__))` appended | Remove this line |
| `vite.config.js` | `format: 'iife'`, `inlineDynamicImports: true` | Revert to standard chunk output, OR keep IIFE (both work on cPanel) |
| `resources/views/app.blade.php` | Manual manifest reader replacing `@vite()` | Keep (works fine on cPanel) or revert to `@vite()` |

The public path override (`bootstrap/app.php`) is the only **required** revert. The others are optional.

---

### BT-5. Archive Stale Pre-Rewrite Docs — ✅ DONE (2026-06-24)

Removed the obsolete pre-Laravel/Vue plain-PHP docs (`docs/current-code-progress.md`, `docs/upgrade-checklist.md`, `docs/crm-use-cases.md`) and the generic `INFINITYFREE_DEPLOYMENT_GUIDE.md` during the pre-cPanel context cleanup.

---

## BEFORE PRODUCTION — After Feature Freeze

Only start these once new feature additions have stopped. Structural changes mid-development cause merge conflicts and slow active work.

### PP-1. DQ-2 — ENUM / CHECK Constraints on Status Fields

**Condition:** Do only after all status/stage values are confirmed final — adding a new valid value after constraints are set requires a new migration.

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

```php
// Option A — ENUM (simplest):
$table->enum('completion_status', ['pending', 'completed', 'cancelled'])->default('pending')->change();

// Option B — CHECK constraint (MySQL 8.0.16+, more flexible):
DB::statement("ALTER TABLE to_dos ADD CONSTRAINT chk_completion_status
    CHECK (completion_status IN ('pending','completed','cancelled'))");
```

---

### PP-2. FS-1 — Split ContactList.vue (3,042 lines)

**Most important split.** A 3,000-line Vue component is the biggest maintainability risk in the codebase. A developer asked to change one tab's filter has to understand everything before touching anything.

**Target structure:**
```
pages/contacts/
├── ContactListPage.vue      (shell: tabs, shared state, API calls)
├── ContactsTab.vue          (contacts table + filters)
├── SummaryTab.vue           (analytics/summary tab)
├── TasksTab.vue             (todos tab — CalendarPicker already uses correct props)
└── ForecastTab.vue          (forecast tab)
```

Each tab receives data as props from `ContactListPage.vue` and emits events back. Shared filter state stays in the parent.

---

### PP-3. FS-3 — Split Performance.vue (1,106 lines)

4 tabs in one 1,100-line file. Second priority after ContactList.vue.

```
pages/performance/
├── PerformancePage.vue      (shell: tab switching)
├── OverviewTab.vue          (KPI cards + target progress + overdue)
├── ActivityTab.vue          (legacy task report)
├── TeamTab.vue              (admin cross-user comparison)
└── TargetsTab.vue           (KPI target editor)
```

---

### PP-4. Complete TESTING_PHASE.md (Parts B–F)

Work through all testing sections in order once staging is stable:

- **Part B** — Staging environment setup (Railway or InfinityFree fully running)
- **Part C** — Feature testing checklist (~65 local items + ~8 live items)
- **Part D** — Non-functional: mobile, cross-browser, security spot-check, email delivery
- **Part E** — Bug log — resolve all Critical and High bugs before proceeding
- **Part F** — Go/No-Go sign-off

Do not open `DEPLOY_CPANEL.md` until all Part F boxes are checked.

---

## PRODUCTION LAUNCH — cPanel Deployment Gate

Open items from `PRODUCTION_READINESS.md`:

- [ ] Configure Redis on production server: set `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD` in `.env` — OR confirm file driver fallback is acceptable for load level
- [ ] Run `php artisan permission:cache-reset` after first deploy to warm Spatie permission cache
- [ ] Register on UptimeRobot (free) — point to `https://your-domain.com/up`, configure email/SMS alert
- [ ] Enable automated daily MySQL backups (cPanel built-in or mysqldump cron), verify restoration works
- [ ] Swap to production email provider (Brevo or SendGrid) if still on Gmail SMTP
- [ ] Set `admin_notification_email` in Admin → System Settings on first super-admin login
- [ ] Run artisan cache commands after all `.env` values are final:
  ```bash
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
  php artisan permission:cache-reset
  ```

---

## POST-PRODUCTION — Scalability

Not relevant at current data volumes. Revisit when approaching 20K contacts / 100+ concurrent users.

### Scale-1. Cache PerformanceController Overview (5 min TTL)

**File:** `app/Http/Controllers/Api/V1/PerformanceController.php`

Every performance dashboard load fires 10+ COUNT queries. With 20 users refreshing every few minutes this becomes hundreds of DB round-trips per minute.

```php
$cacheKey = "perf_overview_{$userId}_{$period}_{$from}_{$to}";
return Cache::remember($cacheKey, 300, function () use (...) {
    // existing KPI logic
});
```

Invalidate when a todo/follow-up/deal is created or completed (model observer or after-save controller call).

---

### Scale-2. Refactor PerformanceController to Single Aggregation Query

**File:** `app/Http/Controllers/Api/V1/PerformanceController.php` — `overview()` and `team()` methods

Currently fires one `COUNT()` per KPI metric per user. `team()` multiplies this by N users — O(N×10) queries.

Replace with a single query using conditional aggregation:
```php
DB::table('to_dos')->selectRaw('
    COUNT(CASE WHEN user_id = ? AND date_created BETWEEN ? AND ? THEN 1 END) as todos_created,
    COUNT(CASE WHEN user_id = ? AND completion_status = "completed" AND completed_at BETWEEN ? AND ? THEN 1 END) as todos_completed
    ...
', [...])
```

---

### Scale-3. Paginate Contact Emails and Calls

**Files:** `ContactEmailController::index()` line 14, `ContactCallController::index()` line 14

A contact with 500+ emails will OOM. Replace `.get()` with `.paginate(20)` and add "Load more" in `ContactView.vue`.

```php
// Before:
$emails = $contact->emails()->with('user')->orderByDesc('emailed_at')->get();
// After:
$emails = $contact->emails()->with('user')->orderByDesc('emailed_at')->paginate(20);
```

---

### Scale-4. Full-Text Search on contacts.name

**Current:** `WHERE name LIKE '%term%'` forces a full table scan. Noticeably slow past ~30K contacts.

**Option A — MySQL Full-Text (simplest, no new infrastructure):**
```php
// Migration:
$table->fullText('name');
// Query:
Contact::whereFullText('name', $search)->paginate(25);
```

**Option B — Meilisearch + Laravel Scout** — instant, typo-tolerant search; requires a separate Meilisearch service.

---

### Scale-5. Refactor AnalyticsController to Fewer Queries

**File:** `app/Http/Controllers/Api/V1/AnalyticsController.php`

Fires 5 sequential `GROUP BY` queries on the same `contacts` table (by status, industry, category, user, type). Run concurrently or consolidate into a single pass at high load.

---

### Scale-6. Fix GlobalTodoController N+1 Subquery

**File:** `app/Http/Controllers/Api/V1/GlobalTodoController.php`

`addSelect([last_followup_date => FollowUp::...subquery...])` fires one subquery per todo row. 100 todos per page = 100 extra queries.

```php
// Model — add relationship:
public function latestFollowUp(): HasOne
{
    return $this->hasOne(FollowUp::class)->latestOfMany('followup_date');
}

// Controller — replace addSelect with:
->with('latestFollowUp')
```

---

### Scale-7. Cap Forecast Detail Rows

**File:** `app/Http/Controllers/Api/V1/ForecastController.php` — `summary()` method

`->get()` with no limit can load tens of thousands of rows into PHP memory on a wide date range.

```php
$rows = (clone $base)->orderBy('forecast_date')->paginate(500);
```

---

### Scale-8. Switch Cache Driver to Redis

```env
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

All `Cache::remember()` calls (LookupController, ReminderController, PerformanceController) benefit automatically. Only relevant once Redis is available on production.

---

### Scale-9. Queue Large Export/Import Jobs

Large imports (>500 rows) run inline via `ImportController`. At high usage, move to queued jobs with a progress endpoint the frontend polls. Low priority unless import sizes grow significantly.

---

## POST-PRODUCTION — Frontend Structure

Do after features are stable. Structural splits mid-development cause merge conflicts.

### FS-2. Split ProductAvailability.vue (1,714 lines)

```
pages/products/
├── ProductAvailabilityPage.vue   (container)
├── ProductsGrid.vue              (listing)
├── BookingForm.vue               (booking modal/form)
└── ProductPhotoUpload.vue        (photo management)
```

### FS-4. Split RbacPanel.vue (868 lines)

```
pages/rbac/
├── RbacPage.vue
├── RolesTab.vue
├── PermissionsTab.vue
└── UsersTab.vue
```

### FS-5. Split Settings.vue (865 lines)

Audit sections first, then extract each to `pages/settings/`.

### FS-7. Standardize API Endpoint Naming

Mixed conventions: `/social-media-reminders` (kebab), `/forecasts` (single word), `/admin/rbac` (no hyphen). Pick kebab-case and apply consistently in `routes/api.php`. Update all Vue `api.js` calls to match. Low priority — inconsistency causes no bugs but confuses new developers.

### FS-8. Rename Misleading Route Names in Router

- `task-edit` maps to `TodoEdit.vue` → rename to `todo-edit`
- `task-add` maps to adding a todo from a contact → rename to `contact-todo-add`

Search before changing: `grep -r "task-edit\|task-add" resources/js/`

### FS-9. Clarify CrmList.vue vs ContactList.vue

Add a one-line comment at the top of each explaining the distinction, or rename `CrmList.vue` → `ContactBrowse.vue`.

### FS-10. Subfolder app/Models/ by Domain

**Option A (full grouping):** Group into `Contact/`, `Sales/`, `Activity/`, `Marketing/`, etc.

**Option B (minimal — recommended):** Move `app/Models/Admin/Role.php` and `Permission.php` to `app/Models/` root. Removes the only inconsistency with minimal effort.

### FS-11. Fix tailwind.config.js Content Scanning

The content array scans only `.blade.php`, not `.vue` files. If any Tailwind classes are added to Vue components in the future, they'll be silently purged from the production build.

```js
content: [
    './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
    './storage/framework/views/*.php',
    './resources/views/**/*.blade.php',
    './resources/js/**/*.vue',   // ← add this
],
```

Low priority — the project uses scoped CSS in Vue components, not Tailwind utilities.

---

## POST-PRODUCTION — Data Quality (Nice to Have)

### DQ-4. Add Timestamps to Tables Missing Them

| Table | Impact |
|-------|--------|
| `reminder_reads` | Can't tell when a user read a reminder |
| `round_robin_state` | Can't track last assignment time |
| `webhooks` | Can't tell when a webhook was registered |

```php
Schema::table('reminder_reads', function (Blueprint $table) {
    $table->timestamps();
});
```

### DQ-5. Territory FK Cleanup on Contact Delete

Deleting a territory leaves contacts pointing to a dead `territory_id`. Check whether the FK already uses `nullOnDelete()` — if not, add a model event:

```php
static::deleting(function (Territory $territory) {
    Contact::where('territory_id', $territory->id)->update(['territory_id' => null]);
});
```

---

## Completed Items

| Item | Done |
|------|------|
| Cache LookupController (1h TTL) | 2026-06-04 |
| per_page cap on all list endpoints | 2026-06-04 |
| Soft Deletes on Contact, Deal, Project | 2026-06-04 |
| Cache ReminderController todos + followups (30s TTL) | 2026-06-04 |
| reminder_reads orphan cleanup | 2026-05-28 |
| ContactIncharge name required | 2026-05-28 |
| Lookup table unique constraints | 2026-05-28 |
| All scalability indexes (contacts, to_dos, follow_ups, deals, system_alerts) | 2026-05-28 |
| Sentry wired up (Laravel + Vue) | 2026-06-04 |
| Login rate limiting (throttle:10,1) | 2026-06-04 |
| Health check endpoint GET /up | 2026-06-04 |
| Audit trail: created_by/updated_by on contacts, deals, to_dos, follow_ups, projects | 2026-06-15 |
| useLookups.js composable (singleton, module-level cache) | 2026-06-15 |

---

*Last updated: 2026-06-15*
