# Current Code Progress For Planning

This document summarizes what has already been built in the current plain PHP workspace. It separates the root-level upgrade code from Ammar's code in the `ammars/` folder, because they currently use different database directions and different file structures.

## Important Context

The documents in this folder describe a larger CRM upgrade plan and database structure. The actual codebase is currently not a Laravel/Vue application. It is mostly plain PHP running under XAMPP.

There are currently two main code areas:

| Area | Main folder | Main database | Purpose |
| --- | --- | --- | --- |
| Root BGOC upgrade app | project root | `bluedale2_crmbgoc` | Newer upgrade/prototype using the BGOC schema |
| Ammar's CRM app | `ammars/` | `dbm_bluedale` | Older/parallel CRM screens for daily CRM work |

There is also a legacy data connection:

| Area | Database | Used by |
| --- | --- | --- |
| Legacy imported data | `bluedale_data_system` | Exhibitions, Travel Hub, old data health, import/migration source |

## Root BGOC Upgrade Code

The root-level code is the newer upgrade direction. It is connected mainly to `bluedale2_crmbgoc`, which matches the BGOC ERD document.

### Database And Connection

| Status | Feature | Files | Notes |
| --- | --- | --- | --- |
| Done | BGOC database connection | `db.php` | Connects to `bluedale2_crmbgoc` using PDO. |
| Done | Legacy database connection | `db_legacy.php` | Connects to `bluedale_data_system` for exhibitions and travel data. |
| Done | BGOC SQL schema file | `sql/bluedale2_crmbgoc_structure.sql` | Schema exists and matches most of `CRM_BGOC_ERD_Tables.md`. |
| Done | Cleanup/fix SQL scripts | `sql/cleanup.sql`, `sql/fix_data.sql` | Support scripts exist for data cleanup. |

### Dashboard And Reporting

| Status | Feature | Files | Notes |
| --- | --- | --- | --- |
| Done | Main dashboard overview | `home.php` | Shows CRM counts, contacts count, task due today count, active contacts, existing clients, and charts. |
| Done | Analytics helper functions | `analytics.php` | Provides totals and grouped counts by status, industry, product/category, user, type, and month. |
| Done | Sidebar/navigation shell | `header.php` | Provides root app navigation and active states. |

### Contacts

| Status | Feature | Files | Notes |
| --- | --- | --- | --- |
| Done | Contact list page | `index.php` | Lists contacts from `contacts` with joined status, type, industry, category, and user. |
| Done | Contact search | `index.php` | Search by company/contact name. |
| Done | Contact filters | `index.php` | Filter by status, industry, category, user, type, and unassigned user. |
| Done | Contact pagination | `index.php` | Uses 100 records per page. |
| Done | Contact detail/profile page | `view.php` | Shows company details, assigned user, status/type/category/industry, address, and remark. |
| Done | PIC display | `view.php` | Shows `contact_incharges` for the selected contact. |
| Done | Todo history display | `view.php` | Shows related `to_dos` records for the selected contact. |
| Not yet | Contact create/edit/delete in root app | none found | Root app can view/import contacts but does not yet provide full CRUD screens. |

### Import And Migration

| Status | Feature | Files | Notes |
| --- | --- | --- | --- |
| Done | Import upload screen | `upload.php` | Allows uploading `.xls`, `.xlsx`, or `.csv` files. |
| Done | Dynamic column mapper | `upload.php` | Detects spreadsheet headers and lets the user map columns to CRM fields. |
| Done | Auto header matching | `upload.php` | Attempts to match company, PIC, phone, email, industry, status, type, category, and assigned user. |
| Done | Import processor | `process_import.php` | Reads spreadsheet data and inserts records. |
| Done | Duplicate skipping | `process_import.php` | Skips records with names already found in the destination database. |
| Done | CRM import path | `process_import.php` | Inserts into BGOC `contacts` and `contact_incharges`. |
| Done | Legacy import path | `process_import.php` | Can insert into old `companies` and `contacts` tables for legacy data. |
| Done | Lookup auto-create | `process_import.php` | Creates missing lookup values such as status, type, industry, category, and user. |
| Done | One-time BGOC migration script | `migrate_bgoc.php` | Migrates old CRM companies into BGOC contacts, PICs into contact incharges, and remarks into todos. |
| Needs verification | Migration result counts | live database | Script exists, but live database counts should be checked after running. |

### Data Health

| Status | Feature | Files | Notes |
| --- | --- | --- | --- |
| Done | Legacy data health page | `data_health.php` | Checks and cleans old `bluedale_data_system` records. |
| Done | BGOC data health page | `data_health_bgoc.php` | Checks BGOC contacts, PICs, todos, follow-ups, missing fields, duplicates, orphan statuses, and overdue records. |
| Done | BGOC trim cleanup action | `data_health_bgoc.php` | Can trim whitespace from contacts and PIC fields. |

### Exhibitions And Travel

| Status | Feature | Files | Notes |
| --- | --- | --- | --- |
| Done | Exhibitions directory | `exhibitions.php` | Reads legacy `companies` where `source_department = 'Exhibitions (Clean List)'`. |
| Done | Exhibitions search/filter | `exhibitions.php` | Search by event/company data and filter by event month. |
| Done | Travel Hub directory | `travel_hub.php` | Reads legacy Travel Guide companies and displays cards. |
| Done | Travel search/filter | `travel_hub.php` | Search by name/address/info and filter by state/category. |
| Done | Travel detail page | `travel_view.php` | Shows selected legacy travel company and contact details. |

### Root App Gaps

These are not complete in the root BGOC app yet:

| Gap | Notes |
| --- | --- |
| Authentication | Root app pages are not protected by a proper login/session system. |
| User roles and permissions | The BGOC schema includes users/roles/permissions, but root app pages do not enforce them yet. |
| Contact CRUD | Root app has list/view/import, but not full create/edit/delete screens. |
| Todo CRUD | Root app reads todo history and health data, but does not provide full todo create/edit/delete flows. |
| Follow-up module | Schema exists, health checks exist, but full UI/workflow is not built in root app. |
| Forecast module | Schema exists, but no root pages found. |
| Project module | Schema exists, but no root pages found. |
| Performance module | Schema exists, but no root pages found. |
| Billboard/tempboard module | Schema exists, but no root pages found. |
| Tracking General module | Schema exists, but no root pages found. |
| Tracking Travel Guide module | Schema exists, but no root pages found. |
| Admin/master data management | No full BGOC admin module in root app yet. |
| Tests | No automated test suite found for the plain PHP app. |

## Ammar's Code

Ammar's code is located in `ammars/`. It is a separate plain PHP CRM app using `dbm_bluedale`. It already has several operational CRM screens, but it is not yet using the new BGOC schema.

### Authentication

| Status | Feature | Files | Notes |
| --- | --- | --- | --- |
| Done | Login page | `ammars/login.php` | Checks email and password against `dbm_bluedale.users`. |
| Done | Logout flow | `ammars/logout.php`, `ammars/logout_action.php` | Logout-related pages exist. |
| Risk | Password handling | `ammars/login.php` | Password check appears to compare plaintext values. This should be changed before production use. |
| Partial | Session behavior | multiple files | Some pages set or assume `$_SESSION["user_id"]`; access protection is inconsistent. |

### Navigation And Layout

| Status | Feature | Files | Notes |
| --- | --- | --- | --- |
| Done | Sidebar/navigation | `ammars/header.php` | Provides navigation for Summary, Daily List, Add Data, To Do, and Admin. |
| Done | CSS styling | various `.css` files | Ammar's screens have their own CSS files and styling. |

### Company/CRM Records

| Status | Feature | Files | Notes |
| --- | --- | --- | --- |
| Done | Daily company list | `ammars/listPage.php` | Lists companies filtered by selected date, user, status, and search. |
| Done | Company add form | `ammars/addData.php`, `ammars/addData2.php` | Add company screens exist. |
| Done | Company insert processing | `ammars/process_data.php`, `ammars/finalize_registration.php` | Inserts company data into `dbm_bluedale.companies`. |
| Done | Duplicate check | `ammars/check_duplicate.php` | Checks whether a company already exists. |
| Done | Company detail page | `ammars/company_info.php` | Shows company details and PICs. |
| Done | Company edit page | `ammars/edit_company.php` | Updates company data. |
| Done | Company history | `ammars/history.php` | Shows task/history records for a company. |

### Persons In Charge

| Status | Feature | Files | Notes |
| --- | --- | --- | --- |
| Done | Add PIC | `ammars/add_pic.php`, `ammars/save_new_pic.php` | Adds PIC records to old app. |
| Done | Edit PIC | `ammars/edit_pic.php`, `ammars/update_pic.php` | Updates PIC records. |
| Done | Delete PIC | `ammars/delete_pic.php` | Deletes PIC records. |

### Todos, Tasks, And Reminders

| Status | Feature | Files | Notes |
| --- | --- | --- | --- |
| Done | Todo list | `ammars/to_do.php` | Shows task/todo records with filters and action update controls. |
| Done | Add task/todo | `ammars/task.php`, `ammars/save_task.php` | Adds tasks for companies. |
| Done | Edit task/todo | `ammars/edit_task.php`, `ammars/update_task.php` | Updates task and related company status/type. |
| Done | Add reminder | `ammars/add_reminder.php` | Creates reminders/tasks and updates company status/type. |
| Done | Task export | `ammars/export_todo.php` | Exports todo/task records. |

### Summary And Export

| Status | Feature | Files | Notes |
| --- | --- | --- | --- |
| Done | Summary/report screen | `ammars/summary.php` | Shows filtered CRM summaries by year and lookup filters. |
| Done | Excel export | `ammars/export_excel.php` | Exports company/CRM records. |

### Admin/Lookup Data

| Status | Feature | Files | Notes |
| --- | --- | --- | --- |
| Done | Simple admin feature creation | `ammars/admin_features.php` | Allows adding lookup values such as statuses/types/products/industries/etc. |
| Partial | Admin permissions | `ammars/admin_features.php` and session logic | Admin features exist, but strong role/permission enforcement is not clear. |

### Ammar App Gaps

| Gap | Notes |
| --- | --- |
| Uses old database | Ammar's app uses `dbm_bluedale`, not `bluedale2_crmbgoc`. |
| Not aligned with BGOC ERD | Table names and columns differ from the BGOC schema. |
| Security needs improvement | Password hashing, session checks, CSRF protection, and consistent authorization should be reviewed. |
| Merge decision needed | Decide whether Ammar's working features should be migrated into the root BGOC app or kept separate. |

## Completed Compared To Planning Documents

The planning docs describe a much bigger CRM system. Based on the current code, the following areas are complete or partially complete:

| Module from docs | Current status | Where it exists |
| --- | --- | --- |
| Database structure | Mostly complete as SQL/docs | Root `sql/`, `documents/CRM_BGOC_ERD_Tables.md` |
| Contacts | Partial in root, more operational in Ammar's app | Root `index.php`, `view.php`; Ammar `listPage.php`, `company_info.php`, `edit_company.php` |
| Contact incharges/PICs | View/import in root, CRUD in Ammar's app | Root `view.php`, `process_import.php`; Ammar PIC files |
| Todos | Read/import/health in root, CRUD in Ammar's app | Root `view.php`, `migrate_bgoc.php`; Ammar todo/task files |
| Imports | Strong progress in root | Root `upload.php`, `process_import.php` |
| Migration | Script exists | Root `migrate_bgoc.php` |
| Data health | Strong progress | Root `data_health.php`, `data_health_bgoc.php` |
| Exhibitions | Done as legacy read-only directory | Root `exhibitions.php` |
| Travel Guide | Done as legacy read-only directory | Root `travel_hub.php`, `travel_view.php` |
| Admin lookup data | Partial in Ammar's app | `ammars/admin_features.php` |
| Authentication | Partial in Ammar's app, missing in root | `ammars/login.php` |
| Roles/permissions | Not complete | Schema/docs only |
| Follow-ups | Not complete in root; related schema exists | SQL/docs |
| Forecasts | Not complete | SQL/docs |
| Projects | Not complete | SQL/docs |
| Performance | Not complete | SQL/docs |
| Billboard/tempboard | Not complete | SQL/docs |
| Tracking General | Not complete | SQL/docs |
| Tracking Travel Guide | Not complete | SQL/docs |

## Recommended Planning Direction

For the next planning phase, choose one of these directions:

### Option A: Continue Root BGOC App As The Main System

Use `bluedale2_crmbgoc` as the main database and slowly move Ammar's working features into the root app.

Best if the goal is a cleaner upgraded system.

Priority order:

1. Add proper login/session protection to the root app.
2. Add contact create/edit/delete using the BGOC tables.
3. Add todo create/edit/delete using the BGOC `to_dos` table.
4. Migrate useful Ammar workflows into root modules.
5. Add admin lookup management for BGOC lookup tables.
6. Add roles/permissions if needed.

### Option B: Continue Ammar's App And Add BGOC Features Later

Keep `ammars/` as the main working CRM and postpone BGOC migration.

Best if users already depend on Ammar's screens and need minimal disruption.

Risks:

- The project continues with the old `dbm_bluedale` schema.
- The BGOC ERD and migration work may become separate from daily CRM usage.
- More cleanup will be needed later.

### Recommended Choice

Use Option A if the goal is an upgrade. The root app already points to the BGOC schema, has import/migration/data health work, and is closer to the documented database structure.

Ammar's code should be treated as working reference code. The useful workflows should be migrated into the root app one module at a time, instead of trying to keep two CRM systems forever.

## Immediate Next Checklist

| Priority | Task | Why |
| --- | --- | --- |
| P0 | Decide the main database direction | Avoid building new features in the wrong database. |
| P0 | Add root app authentication | Private CRM data should not be open. |
| P0 | Create a plain PHP checklist | Current `upgrade-checklist.md` is written for Laravel/Vue and does not match the codebase. |
| P0 | Decide what to migrate from Ammar's app | Prevent duplicate work. |
| P1 | Build BGOC contact create/edit/delete | Completes the core contacts module. |
| P1 | Build BGOC todo CRUD | Brings the daily work module into the new database. |
| P1 | Add admin lookup CRUD for BGOC tables | Needed for statuses, types, industries, categories, tasks, actions, users. |
| P1 | Secure uploads/imports | Import is powerful and should be protected. |
| P2 | Add simple smoke tests/manual test checklist | Helps prevent breakage during the upgrade. |

