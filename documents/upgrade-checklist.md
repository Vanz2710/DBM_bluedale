# CRM_BGOC Upgrade Checklist

Use this checklist together with `docs/crm-use-cases.md`. The goal is to preserve current business behavior while cleaning up security, routing, data structure, frontend architecture, and test coverage.

Legend:

- `[ ]` Not started
- `[~]` In progress
- `[x]` Done
- `P0` Must handle before production migration
- `P1` Important for stable upgrade
- `P2` Cleanup or improvement

## 1. Baseline And Inventory

| Status | Priority | Task | Files / Areas | Verification |
| --- | --- | --- | --- | --- |
| [ ] | P0 | Confirm current PHP, Laravel, Node, npm, database, and web server versions. | `composer.json`, `package.json`, `.env`, XAMPP config | Version notes recorded. |
| [ ] | P0 | Back up current database and uploaded files before changes. | SQL dump, `storage/app/public`, `public/storage` | Backup can be restored locally. |
| [ ] | P0 | Export current route list and compare with use cases. | `routes/web.php`, `routes/api.php` | Route list matches expected modules. |
| [ ] | P0 | Identify live-only data that is not represented in migrations/seeders. | `database/migrations`, `database/seeders`, SQL dump | Data migration notes completed. |
| [ ] | P1 | Mark unused folders/files that should not migrate as source. | `vendor`, `node_modules`, compiled assets, backups | Exclusion list documented. |

## 2. Security And Authorization

| Status | Priority | Task | Files / Areas | Verification |
| --- | --- | --- | --- | --- |
| [ ] | P0 | Add or confirm backend authentication on API routes. | `routes/api.php`, `app/Http/Kernel.php` | Unauthenticated API calls are rejected. |
| [ ] | P0 | Move critical permission checks from frontend-only guards into backend middleware/policies. | `routes/api.php`, controllers, policies | User without permission cannot call API directly. |
| [ ] | P0 | Review all admin APIs for role/permission protection. | `app/Http/Controllers/Admin/**` | Non-admin users receive 403. |
| [ ] | P0 | Review broad update calls using `$request->all()`. | `WIPGeneralController`, `WIPTGuideController`, other controllers | Only allowed fields can be updated. |
| [ ] | P0 | Review file import/export endpoints for permission and validation. | `ContactController@import`, `app/Exports`, `app/Imports` | Unauthorized upload/export blocked. |
| [ ] | P1 | Standardize authorization names for modules. | Spatie permissions, `resources/js/routes.js`, `navigation.blade.php` | Permission matrix is consistent. |
| [ ] | P1 | Add backend audit logging for sensitive actions. | Users, roles, permissions, contacts, exports | Important changes are traceable. |

## 3. Routing And App Shell

| Status | Priority | Task | Files / Areas | Verification |
| --- | --- | --- | --- | --- |
| [ ] | P0 | Decide intended behavior for Vue catch-all route. | `routes/web.php` | Catch-all no longer hides important web routes accidentally. |
| [ ] | P0 | Remove duplicate/conflicting web route declarations. | `routes/web.php` | Route list is clean and predictable. |
| [ ] | P1 | Keep auth routes separate and clear. | `routes/auth.php` | Login/logout/password flows still pass. |
| [ ] | P1 | Document browser route versus API route ownership. | `routes/web.php`, `routes/api.php`, `resources/js/routes.js` | New developers can trace navigation quickly. |
| [ ] | P2 | Remove test/debug routes from production. | `/linkstorage`, `test`, `test_api`, commented routes | No debug route is publicly exposed. |

## 4. Database And Data Migration

| Status | Priority | Task | Files / Areas | Verification |
| --- | --- | --- | --- | --- |
| [ ] | P0 | Rebuild schema map from migrations. | `database/migrations` | Tables, columns, indexes, and relations documented. |
| [ ] | P0 | Map current production data to new schema. | SQL dump, migrations, models | Migration script plan completed. |
| [ ] | P0 | Preserve role, permission, and user mappings. | `RolesAndPermissionSeeder`, Spatie tables | Users retain correct access. |
| [ ] | P0 | Preserve module lookup/reference data. | Contact categories/statuses/types/industries, tasks, actions, forecast data, travel guide packages | Forms load all expected options. |
| [ ] | P1 | Add missing foreign keys/indexes where safe. | migrations, models | Query plans and relation integrity improve. |
| [ ] | P1 | Check nullable/default values used by legacy controllers. | models, migrations, controllers | Migration does not break old records. |
| [ ] | P1 | Create rollback plan for upgrade migration. | migration scripts, backups | Restore path tested. |

## 5. Backend API And Controllers

| Status | Priority | Task | Files / Areas | Verification |
| --- | --- | --- | --- | --- |
| [ ] | P0 | Create an endpoint inventory by module. | `routes/api.php`, controllers | Every frontend API call has a matching backend route. |
| [ ] | P0 | Fix Vue calls to missing endpoints. | Billboard components, `routes/api.php` | No 404s during normal module use. |
| [ ] | P0 | Standardize validation through FormRequest classes. | `app/Http/Requests`, controllers | Invalid input returns consistent 422 errors. |
| [ ] | P1 | Standardize API response format. | `app/Http/Resources`, controllers | Frontend receives predictable `data`, `message`, `status`. |
| [ ] | P1 | Remove or implement controller methods without routes. | `FollowUpController`, others | Dead code is removed or exposed intentionally. |
| [ ] | P1 | Review role ID hardcoding. | Controllers checking role IDs 1 and 2 | Role behavior uses named permissions/roles. |
| [ ] | P1 | Replace large `paginate(5000)` / unbounded `get()` where needed. | Summary/report endpoints | Large datasets load safely. |
| [ ] | P2 | Rename inconsistent methods and messages. | Controllers | API names and messages are clear. |

## 6. Frontend Upgrade

| Status | Priority | Task | Files / Areas | Verification |
| --- | --- | --- | --- | --- |
| [ ] | P0 | Decide whether to keep Vue SPA architecture. | `resources/js/app.js`, `resources/js/routes.js` | Frontend architecture decision recorded. |
| [ ] | P0 | Preserve route-to-component mapping. | `resources/js/routes.js`, `resources/components/**` | Every use case has a reachable screen. |
| [ ] | P0 | Preserve permission-based navigation. | `navigation.blade.php`, `resources/js/routes.js` | Menus and route guards match backend permissions. |
| [ ] | P1 | Plan migration from Laravel Mix to Vite if upgrading Laravel. | `webpack.mix.js`, `package.json`, `resources/js/app.js` | Frontend builds successfully. |
| [ ] | P1 | Upgrade Vue dependencies carefully. | `package.json`, Vue components | Components render without console errors. |
| [ ] | P1 | Review shared UI/composables for stale endpoints. | `resources/components/composables`, `utils` | No stale `/api/companies` or missing API calls remain. |
| [ ] | P1 | Normalize form components and error display. | Vue create/edit screens | Validation messages are shown consistently. |
| [ ] | P2 | Improve mobile/responsive navigation. | `navigation.blade.php`, Vue screens | Core workflows usable on target devices. |

## 7. Module-Specific Business Checks

| Status | Priority | Task | Files / Areas | Verification |
| --- | --- | --- | --- | --- |
| [ ] | P0 | Contacts: preserve company data, incharges, status/type/category/industry, remarks, color tags. | `ContactController`, `Contact` model, contact Vue components | CRUD, search, import/export, summary pass. |
| [ ] | P0 | Todos: preserve date views, user visibility, source/task/action/color/priority behavior. | `ToDoController`, `ToDo` model, todo Vue components | Day/month/range filters and action updates pass. |
| [ ] | P0 | Follow-ups: preserve contact/todo links and team visibility. | `FollowUpController`, `FollowUp` model | Create/list/delete/export pass. |
| [ ] | P0 | Forecasts: preserve products/types/results, result updates, summary and exports. | `ForecastController`, forecast Vue components | Forecast list and summary totals match legacy data. |
| [ ] | P1 | Projects: preserve contact link, dates, owner, remarks. | `ProjectController`, project Vue components | CRUD and filters pass. |
| [ ] | P1 | Performance: preserve user action targets and action-based metrics. | `PerformanceController`, `UserController`, `PerformanceIndex.vue` | Target update and dashboard load pass. |
| [ ] | P1 | Billboard: preserve site/location/size and tenure records. | `BillboardController`, `BillboardTenureController`, billboard Vue components | Tenure workflows pass. |
| [ ] | P1 | Tempboard: preserve date, size, location, material, printing, installation, collection, user/company. | `TempboardController`, tempboard Vue components | CRUD and year filter pass. |
| [ ] | P1 | Tracking General: preserve master records and auto-created WIP rows by art frequency. | `TrackingGeneralController`, `WIPGeneralController` | Master and WIP flows pass. |
| [ ] | P1 | Tracking Travel Guide: preserve master tracking and package WIP rows. | `TrackingTGuideController`, `WIPTGuideController` | Package add/update/delete pass. |
| [ ] | P1 | Admin: preserve master data, users, categories, benchmarks, supervisors, roles, permissions. | `AdminController`, admin controllers, admin Vue components | Admin workflows pass under correct permissions. |

## 8. Exports, Imports, And Files

| Status | Priority | Task | Files / Areas | Verification |
| --- | --- | --- | --- | --- |
| [ ] | P0 | Confirm all Excel exports needed in the new system. | `app/Exports/**`, module controllers | Export list matches business needs. |
| [ ] | P0 | Test contact import with real sample files. | `ContactImport`, `ContactController@import` | Valid files import; invalid files fail safely. |
| [ ] | P0 | Migrate public assets and uploads. | `storage/app/public`, `public/storage`, logos, tutorials | Images/tutorials load in UI. |
| [ ] | P1 | Replace manual `/linkstorage` route with deployment step. | `routes/web.php` | Storage link is handled safely. |
| [ ] | P1 | Validate export permissions and selected-ID behavior. | export endpoints | Users cannot export unauthorized data. |

## 9. Dependencies And Framework Upgrade

| Status | Priority | Task | Files / Areas | Verification |
| --- | --- | --- | --- | --- |
| [ ] | P0 | Choose target Laravel version and PHP version. | `composer.json` | Upgrade path documented. |
| [ ] | P0 | Review breaking changes from Laravel 8 to target version. | Laravel app, middleware, auth, factories, routes | App boots on target framework. |
| [ ] | P1 | Upgrade Spatie permission safely. | `spatie/laravel-permission`, models, seeders | Roles and permissions still work. |
| [ ] | P1 | Upgrade Laravel Excel safely. | `maatwebsite/excel`, exports/imports | Import/export still works. |
| [ ] | P1 | Upgrade frontend packages. | `package.json` | Build and runtime smoke tests pass. |
| [ ] | P1 | Replace deprecated Laravel Mix setup if needed. | `webpack.mix.js`, Vite config | Production assets compile. |

## 10. Testing Plan

| Status | Priority | Task | Files / Areas | Verification |
| --- | --- | --- | --- | --- |
| [ ] | P0 | Keep and run current auth tests. | `tests/Feature/Auth/**` | Existing auth tests pass. |
| [ ] | P0 | Replace placeholder test with real smoke test. | `tests/Feature/ExampleTest.php` | Home/authenticated shell test is meaningful. |
| [ ] | P0 | Add API tests for authorization boundaries. | API routes, permissions | Unauthorized users get 401/403. |
| [ ] | P1 | Add CRUD tests for contacts, todos, forecasts, projects. | controllers, factories | Core module tests pass. |
| [ ] | P1 | Add export/import tests. | exports/imports | Files generated/imported correctly. |
| [ ] | P1 | Add tracking workflow tests. | tracking controllers/models | Master creates expected WIP rows. |
| [ ] | P1 | Add admin role/permission tests. | admin controllers, Spatie | Access changes are correct. |
| [ ] | P2 | Add frontend smoke/e2e tests for main routes. | Vue routes/components | Main screens load without console errors. |

## 11. Performance And Reliability

| Status | Priority | Task | Files / Areas | Verification |
| --- | --- | --- | --- | --- |
| [ ] | P1 | Profile heavy list/report endpoints. | contact/forecast summaries, tracking, billboard | Slow queries identified. |
| [ ] | P1 | Add indexes for common filters and joins. | migrations/database | List pages remain fast with production data. |
| [ ] | P1 | Review N+1 query risks. | models/resources/controllers | Query counts are acceptable. |
| [ ] | P1 | Add pagination limits and safe defaults. | index/report endpoints | Large requests cannot overload server. |
| [ ] | P2 | Add caching for static lookup data where useful. | lookup controllers | Forms load faster without stale data issues. |

## 12. Deployment And Cutover

| Status | Priority | Task | Files / Areas | Verification |
| --- | --- | --- | --- | --- |
| [ ] | P0 | Create deployment checklist for new environment. | `.env`, web server, scheduler/queue if any | Fresh environment can be deployed. |
| [ ] | P0 | Prepare migration dry run. | database migration scripts | Dry run completes and data counts match. |
| [ ] | P0 | Prepare rollback plan. | backups, deployment scripts | Rollback tested. |
| [ ] | P0 | Run user acceptance tests by module owner. | use cases document | Sign-off captured. |
| [ ] | P1 | Monitor logs after cutover. | Laravel logs, web server logs | No critical runtime errors. |
| [ ] | P1 | Verify permissions after cutover with real user roles. | admin/users/permissions | Users only see allowed modules. |

## 13. Known Follow-Up Items From Current Code

| Status | Priority | Issue | Files / Areas | Decision Needed |
| --- | --- | --- | --- | --- |
| [ ] | P0 | API routes are mostly not grouped under auth middleware. | `routes/api.php` | Add backend auth/permission middleware. |
| [ ] | P0 | Vue route guards do important access checks on client side. | `resources/js/routes.js` | Mirror checks on backend. |
| [ ] | P0 | Catch-all route precedes many named web routes. | `routes/web.php` | Keep intentional SPA routing or reorder/remove duplicates. |
| [ ] | P1 | Billboard components reference `/api/billboards/site` and `/api/billboards/size`, but routes were not found. | `resources/components/billboards/**`, `routes/api.php` | Add endpoints or remove stale calls. |
| [ ] | P1 | Some controller methods appear unused by active routes. | Follow-up/update/info/action, test methods | Restore, test, or delete. |
| [ ] | P1 | Some composables contain stale `/api/companies` references. | `resources/components/composables/**` | Fix or remove unused composables. |
| [ ] | P1 | Role checks use hardcoded role IDs in several controllers. | API controllers | Replace with named roles/permissions. |
| [ ] | P1 | Many inline validations are inconsistent. | controllers | Move to FormRequest classes. |

