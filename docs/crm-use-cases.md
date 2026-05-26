# CRM_BGOC Current Use Cases

This document captures the use cases currently present in CRM_BGOC based on the active Laravel routes, Vue router, controllers, models, and visible Vue components.

Primary source files:

- `routes/web.php`
- `routes/auth.php`
- `routes/api.php`
- `resources/js/routes.js`
- `resources/components/**`
- `app/Http/Controllers/**`
- `app/Models/**`

## Actors

| Actor | Description |
| --- | --- |
| Guest | Unauthenticated visitor using login, registration, password reset, or verification screens. |
| CRM User | Authenticated user using CRM modules assigned by permission. |
| Supervisor | Authenticated user who can view own records plus assigned subordinate records. |
| Admin | User with admin permissions for master data, users, roles, permissions, exports, announcements, and setup. |
| Super Admin | Highest permission user. In several APIs, role IDs 1 and 2 receive broad visibility. |
| System | Laravel, Vue, scheduled/browser actions, import/export process, and storage handling. |

## Authentication And Account Access

| ID | Use case | Actor | Main flow | Result | Source files |
| --- | --- | --- | --- | --- | --- |
| AUTH-01 | Register a new account | Guest | Open register page, submit name/email/password. | User account is created and authenticated or redirected based on Breeze flow. | `routes/auth.php`, `RegisteredUserController`, `resources/views/auth/register.blade.php` |
| AUTH-02 | Log in | Guest | Open login page, submit credentials. | User enters authenticated CRM shell. | `routes/auth.php`, `AuthenticatedSessionController`, `LoginRequest`, `resources/views/auth/login.blade.php` |
| AUTH-03 | Log out | CRM User | Click logout in navigation. | Session is destroyed and user is redirected. | `routes/auth.php`, `AuthenticatedSessionController`, `resources/views/layouts/navigation.blade.php` |
| AUTH-04 | Request password reset | Guest | Open forgot-password page and submit email. | Password reset link is sent if the email is valid. | `routes/auth.php`, `PasswordResetLinkController` |
| AUTH-05 | Reset password | Guest | Open reset-password token URL and submit new password. | Password is updated. | `routes/auth.php`, `NewPasswordController` |
| AUTH-06 | Confirm password | CRM User | Open confirm-password flow before protected action. | User confirms password for sensitive actions. | `routes/auth.php`, `ConfirmablePasswordController` |
| AUTH-07 | Verify email | CRM User | Open email verification notice, receive link, click signed verification URL. | User email is marked verified. | `routes/auth.php`, `VerifyEmailController`, `EmailVerificationPromptController` |
| AUTH-08 | Restrict screens by permission | CRM User, Admin | User navigates to a module; Vue route guard and Blade `@can` checks evaluate permissions. | Allowed users see screens; denied users go to no-authorization page or hidden menus. | `resources/js/routes.js`, `resources/views/layouts/navigation.blade.php`, `app/Http/Kernel.php` |

## Home, Announcements, And Tutorials

| ID | Use case | Actor | Main flow | Result | Source files |
| --- | --- | --- | --- | --- | --- |
| HOME-01 | View announcement landing page | CRM User | Open `/` or `/announcement/index`. | Announcement list/content is shown as the default Vue route. | `routes/web.php`, `resources/js/routes.js`, `AnnouncementIndex.vue`, `AdminController@announcement_reminder` |
| HOME-02 | Manage announcements | Admin | Open announcement edit page, create/update/delete announcement reminder records. | Announcements are maintained for users. | `routes/web.php`, `routes/api.php`, `AnnouncementEdit.vue`, `AdminController@message_create`, `message_update`, `message_delete` |
| HOME-03 | View tutorials | CRM User | Open tutorial routes for contact, todo/followup, forecast, project, performance, billboard/tempboard, tracking, or admin. | Module tutorial component is displayed. | `routes/web.php`, `resources/js/routes.js`, `resources/components/tutorials/**` |
| HOME-04 | Download tutorial files | Admin or permitted user | Request tutorial download by tutorial number. | Tutorial file is returned for download. | `routes/api.php`, `AdminController@download_tutorial` |

## Contacts

| ID | Use case | Actor | Main flow | Result | Source files |
| --- | --- | --- | --- | --- | --- |
| CON-01 | View contact list | CRM User | Open `/contact/index`; system loads paginated contacts. | User sees contacts with company, status, type, category, industry, owner, and color tag data. | `ContactIndex.vue`, `routes/api.php`, `ContactController@index`, `ContactResource`, `Contact` model |
| CON-02 | Search, sort, and filter contacts | CRM User | Enter search term or filter by user, status, category, type, industry; choose sort field/direction. | Contact list refreshes using matching criteria. | `ContactIndex.vue`, `ContactController@index`, `Contact::scopeSearch` |
| CON-03 | Create contact | CRM User with `create contact` | Open contact create form, fill name, industry, address, remark, category, type, status. | New contact is saved with current user as owner. | `ContactCreate.vue`, `ContactController@store`, `ContactRequest`, `Contact` model |
| CON-04 | Check duplicate contact name | CRM User | Enter/search contact name during create. | System indicates whether contact exists. | `ContactCreate.vue`, `ContactController@contact_check_result` |
| CON-05 | Edit contact | CRM User with `edit contact` | Open contact edit page, update company details, owner, status, type, category, industry, remark, color. | Contact and contact text color are updated. | `ContactEdit.vue`, `ContactController@edit`, `ContactController@update`, `ContactTextColor` model |
| CON-06 | Delete contact | CRM User with `delete contact` | Choose delete on a contact. | Contact record is removed. | `ContactIndex.vue`, `ContactController@delete` |
| CON-07 | View contact info | CRM User | Open contact info page. | Contact details, incharges, owner, category/type/status/industry, and forecasts are shown. | `ContactInfo.vue`, `ContactController@info`, `Contact` relationships |
| CON-08 | View contact todo history | CRM User | Open contact history page. | Historical todo records for the contact are listed by latest todo date. | `ContactHistory.vue`, `ContactController@history` |
| CON-09 | View contact remark | CRM User | Open contact remark modal. | Contact remark text is displayed. | `ContactRemarkModal.vue`, `ContactController@remark` |
| CON-10 | Manage contact incharge | CRM User | Create, edit, view, or delete incharge records for a contact. | Contact person/incharge data is maintained. | `InchargeCreate.vue`, `InchargeEdit.vue`, `ContactInchargeController`, `ContactInchargeRequest` |
| CON-11 | Export selected contacts | CRM User with export permission | Select contacts and export. | Excel file is generated for selected contact IDs. | `ContactIndex.vue`, `ContactController@export`, `ContactExport` |
| CON-12 | Select all contacts for export | CRM User | Click select-all export option. | System returns all contact IDs. | `ContactController@selectAll` |
| CON-13 | Import contacts | Admin or permitted user | Upload contact spreadsheet. | Contacts are imported through Excel import logic. | `AdminExport.vue`, `ContactController@import`, `ContactImport` |
| CON-14 | View contact action summary | CRM User with `view contact summary` | Open contact summary and view grouped actions by month/year. | Summary of latest actions by company is displayed. | `ContactSummary.vue`, `ContactController@summary_action` |
| CON-15 | View contact todo summary | CRM User with `view contact summary` | Open contact summary and view todo grouping by year/month. | Summary of todos by company is displayed. | `ContactSummary.vue`, `ContactController@summary_todo` |

## To Do

| ID | Use case | Actor | Main flow | Result | Source files |
| --- | --- | --- | --- | --- | --- |
| TODO-01 | View todo list | CRM User | Open `/todo/index`; system loads todos. | User sees todos with contact, task, priority, color, source, action, status, type, and owner. | `ToDoIndex.vue`, `ToDoController@index`, `ToDoResource`, `ToDo` model |
| TODO-02 | Search, sort, and filter todos | CRM User | Search and filter by source, status, user, task, type, action, day, month, or date range. | Todo list refreshes with matching records. | `ToDoIndex.vue`, `ToDoController@index`, `ToDo::scopeSearch` |
| TODO-03 | Apply team visibility to todos | CRM User, Supervisor, Admin | System checks current role and supervisor/subordinate mapping. | Admins see broad data; supervisors see own plus subordinates; users see own data. | `ToDoController@index`, `SvSbPivot`, `model_has_roles` |
| TODO-04 | Create internal todo | CRM User with `create todo` | Open todo create form, select contact/user/task/date/deadline/status/type/priority/source/color/remark. | Todo is created. | `ToDoCreate.vue`, `ToDoController@store`, `ToDoInternalRequest` |
| TODO-05 | Insert todo from contact | CRM User with `insert todo` | Open insert todo from a contact or workflow. | Todo is added for the selected contact. | `ToDoInsert.vue`, `ToDoController@insert` |
| TODO-06 | Edit todo | CRM User with `edit todo` | Open todo edit page and update todo details. | Todo data is updated. | `ToDoEdit.vue`, `ToDoController@show`, `ToDoController@update` |
| TODO-07 | Set todo action/result | CRM User | Choose an action for a todo. | Todo action ID is updated. | `ToDoIndex.vue`, `ToDoController@action` |
| TODO-08 | Delete todo | CRM User with `delete todo` | Delete selected todo. | Todo record is removed. | `ToDoIndex.vue`, `ToDoController@delete` |
| TODO-09 | View todo remark/detail | CRM User | Open todo remark or detail. | Todo remark/detail data is displayed. | `TodoRemarkModal.vue`, `ToDoController@remark`, `ToDoController@info` |
| TODO-10 | Export selected todos | CRM User with export permission | Select todos and export. | Excel file is generated. | `ToDoIndex.vue`, `ToDoController@export`, `TodoExport` |
| TODO-11 | Select all todos for export | CRM User | Click select all. | System returns all todo IDs. | `ToDoController@selectAll` |

## Follow Ups

| ID | Use case | Actor | Main flow | Result | Source files |
| --- | --- | --- | --- | --- | --- |
| FUP-01 | View follow-up list | CRM User | Open `/followup/index`; system loads followups. | Follow-ups are listed with contact, task, user, date, time, and remark context. | `FollowUpIndex.vue`, `FollowUpController@index`, `FollowUpResource`, `FollowUp` model |
| FUP-02 | Search, sort, and filter follow-ups | CRM User | Search/filter by user, task, exact date, month/year, or date range. | Follow-up list refreshes. | `FollowUpIndex.vue`, `FollowUpController@index`, `monthrange`, `daterange` |
| FUP-03 | Apply team visibility to follow-ups | CRM User, Supervisor, Admin | System checks role and supervisor/subordinate mapping. | Users see allowed follow-up records. | `FollowUpController@index`, `SvSbPivot` |
| FUP-04 | Create follow-up from todo/contact | CRM User with `create followup` | Open follow-up create screen from selected contact/todo, enter date/time/task/remark/user/status/type. | Follow-up is created and linked to todo and contact. | `FollowUpCreate.vue`, `FollowUpController@store` |
| FUP-05 | View follow-up remark | CRM User | Open follow-up remark modal. | Follow-up remark is displayed. | `FollowupRemarkModal.vue`, `FollowUpController@remark` |
| FUP-06 | Delete follow-up | CRM User with `delete followup` | Delete selected follow-up. | Follow-up record is removed. | `FollowUpIndex.vue`, `FollowUpController@delete` |
| FUP-07 | Export selected follow-ups | CRM User with export permission | Select follow-up records and export. | Excel file is generated. | `FollowUpIndex.vue`, `FollowUpController@export`, `FollowUpExport` |
| FUP-08 | Select all follow-ups for export | CRM User | Click select all. | System returns all follow-up IDs. | `FollowUpController@selectAll` |

## Forecasts

| ID | Use case | Actor | Main flow | Result | Source files |
| --- | --- | --- | --- | --- | --- |
| FC-01 | View forecast list | CRM User | Open `/forecast/index`; system loads paginated forecasts. | Forecast records are shown with contact, user, product, forecast type, amount, and result. | `ForecastIndex.vue`, `ForecastController@index`, `ForecastResource`, `Forecast` model |
| FC-02 | Search, sort, and filter forecasts | CRM User | Filter by product, forecast type, user, result, or search text. | Forecast list refreshes with matching records. | `ForecastIndex.vue`, `ForecastController@index`, `Forecast::scopeSearch` |
| FC-03 | Apply team visibility to forecasts | CRM User, Supervisor, Admin | System checks role and supervisor/subordinate mapping. | Users see allowed forecast records. | `ForecastController@index`, `SvSbPivot` |
| FC-04 | Create forecast for contact | CRM User with `create forecast` | Open forecast create screen from a contact, enter date, amount, user, forecast type, product. | Forecast is created and linked to contact. | `ForecastCreate.vue`, `ForecastController@store`, `ForecastRequest` |
| FC-05 | Edit forecast | CRM User with `edit forecast` | Open forecast edit page and update date, amount, user, type, contact, product. | Forecast is updated and update date is refreshed. | `ForecastEdit.vue`, `ForecastController@show`, `ForecastController@update` |
| FC-06 | Set forecast result | CRM User | Choose forecast result from list. | Forecast result and update date are saved. | `ForecastIndex.vue`, `ForecastController@resultSelected` |
| FC-07 | Delete forecast | CRM User with `delete forecast` | Delete selected forecast. | Forecast record is removed. | `ForecastIndex.vue`, `ForecastController@delete` |
| FC-08 | View forecast info | CRM User | Open forecast detail/info. | Forecast relations are returned for detail display. | `ForecastController@info`, `Forecast` relationships |
| FC-09 | Export selected forecasts | CRM User with export permission | Select forecasts and export. | Excel forecast file is generated. | `ForecastIndex.vue`, `ForecastController@export`, `ForecastExport` |
| FC-10 | Select all forecasts for export | CRM User | Click select all. | System returns all forecast IDs. | `ForecastController@selectAll` |
| FC-11 | View forecast summary | CRM User with `view forecast summary` | Open summary, filter by contact/status/type/product/forecast type/user/year/result. | Forecast summary grouped for reporting is shown. | `ForecastSummary.vue`, `ForecastController@summary` |
| FC-12 | Export forecast summary | CRM User with export permission | Click export summary. | Forecast summary Excel file is generated. | `ForecastSummary.vue`, `ForecastController@exportSummary`, `ForecastSummaryExport` |

## Projects

| ID | Use case | Actor | Main flow | Result | Source files |
| --- | --- | --- | --- | --- | --- |
| PRJ-01 | View project list | CRM User with `view project` | Open `/project/index`; system loads projects. | Projects are listed with company, user, dates, name, and remark. | `ProjectIndex.vue`, `ProjectController@index`, `ProjectResource`, `Project` model |
| PRJ-02 | Search, sort, and filter projects | CRM User | Search and filter by start date, end date, entry date, sort field/direction. | Project list refreshes. | `ProjectIndex.vue`, `ProjectController@index`, `Project::scopeSearch` |
| PRJ-03 | Create project | CRM User with `create project` | Open project create screen, select contact and enter project details/dates. | Project is saved with current user. | `ProjectCreate.vue`, `ProjectController@store`, `ProjectRequest` |
| PRJ-04 | Edit project | CRM User with `edit project` | Open project edit screen and update details. | Project is updated with current user. | `ProjectEdit.vue`, `ProjectController@show`, `ProjectController@update` |
| PRJ-05 | Delete project | CRM User with `delete project` | Delete selected project. | Project record is removed. | `ProjectIndex.vue`, `ProjectController@delete` |
| PRJ-06 | View project remark | CRM User | Open project remark modal. | Project remark is displayed. | `ProjectRemarkModal.vue`, `ProjectController@remark` |

## Performance

| ID | Use case | Actor | Main flow | Result | Source files |
| --- | --- | --- | --- | --- | --- |
| PERF-01 | View performance dashboard | CRM User with `view performance` | Open `/performance/index`; system loads users, actions, targets, and performance data. | Performance view is displayed for selected user/time. | `PerformanceIndex.vue`, `UserController@performance`, `ActionController@performance` |
| PERF-02 | View user performance target | CRM User, Admin | Select a user. | Existing performance targets by action are loaded. | `PerformanceController@target`, `PerformanceTarget` model |
| PERF-03 | Update user performance target | Admin or permitted user | Enter target values per action and save. | Existing targets are updated or new targets are created. | `PerformanceIndex.vue`, `PerformanceController@target_update` |
| PERF-04 | Use action list for performance | CRM User | System loads actions used for performance measurement. | Available action metrics are available to performance screen. | `ActionController@index`, `ActionController@performance` |

## Billboard And Tempboard

| ID | Use case | Actor | Main flow | Result | Source files |
| --- | --- | --- | --- | --- | --- |
| BB-01 | View billboard index | CRM User with `view billboard/tempboard` | Open `/billboard/index`; system loads billboard sites and tenure summary. | Billboard list is shown with site, location, size, and tenure/company summary. | `BillboardIndex.vue`, `BillboardController@index`, `Billboard` model |
| BB-02 | Search, sort, and filter billboards | CRM User | Search/sort and filter by selected year. | Billboard list refreshes with matching records. | `BillboardIndex.vue`, `BillboardController@index`, `Billboard::scopeSearch` |
| BB-03 | Create billboard | CRM User with `create billboard` | Enter site number parts, location, and size dimensions. | Billboard record is created. | `BillboardCreate.vue`, `BillboardController@store` |
| BB-04 | Edit billboard details | CRM User with `edit billboard` | Update billboard site, location, or size. | Billboard record is updated. | `BillboardTenure.vue`, `BillboardController@update` |
| BB-05 | Delete billboard | CRM User with `delete billboard` | Delete selected billboard. | Billboard record is removed. | `BillboardController@delete` |
| BB-06 | View billboard tenure details | CRM User | Open billboard tenure screen. | Tenure records and billboard details are displayed. | `BillboardTenure.vue`, `BillboardController@info`, `BillboardTenure` model |
| BB-07 | Add billboard tenure | CRM User | Add contact/user/start/end date to billboard. | Tenure record is created. | `BillboardTenure.vue`, `BillboardTenureController@store` |
| BB-08 | Delete billboard tenure | CRM User | Delete selected tenure. | Tenure record is removed. | `BillboardTenure.vue`, `BillboardTenureController@delete` |
| BB-09 | View billboard tenure listing | CRM User | Load tenure endpoint with search/year/sort. | Tenure records are returned for reporting/listing. | `BillboardController@tenure`, `BillboardResource` |
| TP-01 | View tempboard list | CRM User with `view billboard/tempboard` | Open `/tempboard/index`; system loads tempboard records. | Tempboard list is shown with company, user, dates, size, location, unit, collection, material, printing, installation, remark. | `TempboardIndex.vue`, `TempboardController@index`, `Tempboard` model |
| TP-02 | Search, sort, and filter tempboards | CRM User | Search/filter by user or selected year. | Tempboard list refreshes. | `TempboardIndex.vue`, `TempboardController@index`, `Tempboard::scopeSearch` |
| TP-03 | Create tempboard | CRM User with `create tempboard` | Enter tempboard dates, contact, user, size, location, unit, collection, material, printing, installation, remark. | Tempboard is created. | `TempboardCreate.vue`, `TempboardController@store` |
| TP-04 | Edit tempboard | CRM User with `edit tempboard` | Open edit page and update details. | Tempboard record is updated. | `TempboardEdit.vue`, `TempboardController@info`, `TempboardController@update` |
| TP-05 | Delete tempboard | CRM User with `delete tempboard` | Delete selected tempboard. | Tempboard record is removed. | `TempboardIndex.vue`, `TempboardController@delete` |
| TP-06 | View tempboard remark | CRM User | Open tempboard remark modal. | Tempboard remark is displayed. | `TempboardRemarkModal.vue`, `TempboardController@remark` |

## Tracking - General

| ID | Use case | Actor | Main flow | Result | Source files |
| --- | --- | --- | --- | --- | --- |
| TRG-01 | View general tracking master list | CRM User with `view tracking general` | Open `/tracking/general/index` in master view. | Master tracking records are listed by company, category, division, user, progress, dates, amount, and remark. | `TrackingGeneralIndex.vue`, `TrackingGeneralController@index`, `TrackingGeneral` model |
| TRG-02 | View general tracking WIP list | CRM User | Switch to WIP view or report view. | WIP rows are shown with production steps, dates, done flags, users, remarks, and master tracking context. | `TrackingGeneralIndex.vue`, `TrackingGeneralController@index`, `WipGeneral` model |
| TRG-03 | Search, sort, and filter general tracking | CRM User | Search/filter by user, category, progress/result, division, view type; optionally sort WIP columns. | Tracking list refreshes. | `TrackingGeneralController@index`, `TrackingGeneral::scopeSearch`, `WipGeneral::scopeSearch` |
| TRG-04 | Create general tracking master | CRM User with `create tracking general` | Enter master tracking details: dates, user, company, category, division, frequency, amount, type, reach, tenure, art format, remark. | Master record is created with progress `Pending`; WIP records are auto-created for each art frequency. | `MasterGeneralCreate.vue`, `TrackingGeneralController@store` |
| TRG-05 | Edit general tracking master | CRM User with `edit master general` | Open edit screen and update master tracking fields and progress. | Master tracking record is updated. | `MasterGeneralEdit.vue`, `TrackingGeneralController@show`, `TrackingGeneralController@update` |
| TRG-06 | Delete general tracking master | CRM User | Delete selected master record. | Master tracking record is removed. | `TrackingGeneralController@delete` |
| TRG-07 | Edit general WIP workflow | CRM User with `edit wip general` | Open WIP edit screen and update WIP progress, remark, dates, done flags, assigned users, and remarks for art chase, art received, art todo, CNS sent, CNS record, schedule, actual live, client posting, report. | WIP row and selected master dates/users/category/company fields are updated. | `WIPGeneralEdit.vue`, `WIPGeneralController@show`, `WIPGeneralController@update` |
| TRG-08 | Quick update WIP from index | CRM User | Update WIP row directly from index/report table. | WIP row is updated with submitted fields. | `TrackingGeneralIndex.vue`, `WIPGeneralController@indexWIPUpdate` |
| TRG-09 | Delete general WIP row | CRM User | Delete selected WIP row. | WIP record is removed. | `WIPGeneralController@delete` |
| TRG-10 | Load BGOC divisions | CRM User | Open tracking forms or filters. | Division lookup list is returned. | `BluedaleDivisionController@index`, `BluedaleDivision` model |

## Tracking - Travel Guide

| ID | Use case | Actor | Main flow | Result | Source files |
| --- | --- | --- | --- | --- | --- |
| TGT-01 | View travel guide tracking master list | CRM User with `view tracking tguide` | Open `/tracking/travel_guide/index` in master view. | Travel guide tracking records are listed with company, user, edition, size, reminder/record fields, and packages. | `TrackingTravelGuideIndex.vue`, `TrackingTGuideController@index`, `TrackingTravelGuide` model |
| TGT-02 | View travel guide WIP/report list | CRM User | Switch to WIP or report view. | Travel guide package WIP records are displayed with package dates, done flags, users, and remarks. | `TrackingTravelGuideIndex.vue`, `TrackingTGuideController@index`, `WipTravelGuide` model |
| TGT-03 | Search, sort, and filter travel guide tracking | CRM User | Search/filter by user, year, and view type. | Travel guide tracking list refreshes. | `TrackingTGuideController@index`, `TrackingTravelGuide::scopeSearch` |
| TGT-04 | Create travel guide tracking | CRM User with `create tracking travelguide` | Enter user, company, edition, size, remark, art reminder/record dates, users, and remarks. | Travel guide tracking master record is created. | `MasterTravelGuideCreate.vue`, `TrackingTGuideController@store` |
| TGT-05 | Edit travel guide tracking | CRM User with `edit master travelguide` | Open edit screen and update master fields, reminder/record status, dates, users, and remarks. | Travel guide tracking master record is updated. | `MasterTravelGuideEdit.vue`, `TrackingTGuideController@show`, `TrackingTGuideController@update` |
| TGT-06 | Delete travel guide tracking | CRM User | Delete selected travel guide tracking master. | Master record is removed. | `TrackingTGuideController@delete` |
| TGT-07 | Add travel guide WIP packages | CRM User | Add one or more package rows to a tracking record. | Package WIP rows are created and linked to tracking. | `TrackingTravelGuideIndex.vue`, `WIPTGuideController@wip_tg_store` |
| TGT-08 | Update travel guide WIP packages | CRM User | Edit package name, date, done flag, user, and remark. | Existing package rows are updated or new rows created. | `WIPTGuideController@wip_tg_update`, `WIPTGuideController@update` |
| TGT-09 | Delete travel guide WIP package | CRM User | Delete selected package row. | Package WIP record is removed. | `WIPTGuideController@delete` |
| TGT-10 | Load travel guide package lookup | CRM User, Admin | Open package selection. | Package reference list or selected package detail is returned. | `WIPTGuideController@getTravelGuidePackage`, `showTravelGuidePackage`, `TravelGuidePackage` model |

## Admin - Master Data

| ID | Use case | Actor | Main flow | Result | Source files |
| --- | --- | --- | --- | --- | --- |
| ADM-DATA-01 | Maintain contact categories | Admin | Create, update, delete, list, or inspect contact categories. | Contact category lookup data is maintained. | `AdminData.vue`, `AdminController@createContactCategory`, `updateContactCategory`, `deleteContactCategory`, `ContactCategoryController` |
| ADM-DATA-02 | Maintain contact statuses | Admin | Create, update, delete, list, or inspect contact statuses. | Contact status lookup data is maintained. | `AdminData.vue`, `AdminController@createContactStatus`, `updateContactStatus`, `deleteContactStatus`, `ContactStatusController` |
| ADM-DATA-03 | Maintain contact types | Admin | Create, update, delete, list, or inspect contact types. | Contact type lookup data is maintained. | `AdminData.vue`, `AdminController@createContactType`, `updateContactType`, `deleteContactType`, `ContactTypeController` |
| ADM-DATA-04 | Maintain contact industries | Admin | Create, update, delete, list, or inspect industries. | Contact industry lookup data is maintained. | `AdminData.vue`, `AdminController@createContactIndustry`, `updateContactIndustry`, `deleteContactIndustry`, `ContactIndustryController` |
| ADM-DATA-05 | Maintain todo tasks | Admin | Create, update, delete, list, or inspect task records. | Task lookup data is maintained. | `AdminData.vue`, `AdminController@createToDoTask`, `updateToDoTask`, `deleteToDoTask`, `TaskController` |
| ADM-DATA-06 | Maintain todo/follow-up actions | Admin | Create, update, delete, list, inspect, or use actions for performance. | Action lookup data is maintained. | `AdminData.vue`, `AdminController@createToDoAction`, `updateToDoAction`, `deleteToDoAction`, `ActionController` |
| ADM-DATA-07 | Maintain text colors | Admin | Create, update, delete, list, or inspect text colors. | Color tags are maintained for todos/contacts. | `AdminData.vue`, `AdminController@createTextColor`, `updateTextColor`, `deleteTextColor`, `ToDoController@colors`, `color_info` |
| ADM-DATA-08 | Maintain forecast types | Admin | Create, update, delete, list, or inspect forecast types. | Forecast type lookup data is maintained. | `AdminData.vue`, `AdminController@createForecastType`, `updateForecastType`, `deleteForecastType`, `ForecastTypeController` |
| ADM-DATA-09 | Maintain forecast products | Admin | Create, update, delete, list, or inspect products. | Forecast product lookup data is maintained. | `AdminData.vue`, `AdminController@createForecastProduct`, `updateForecastProduct`, `deleteForecastProduct`, `ForecastProductController` |
| ADM-DATA-10 | Maintain forecast results | Admin | Create, update, delete, list, or inspect results. | Forecast result lookup data is maintained. | `AdminData.vue`, `AdminController@createForecastResult`, `updateForecastResult`, `deleteForecastResult`, `ForecastResultController` |
| ADM-DATA-11 | Maintain travel guide packages | Admin | Create, update, delete, list, or inspect travel guide packages. | Travel guide package lookup data is maintained. | `AdminData.vue`, `AdminController@createTravelGuidePackage`, `updateTravelGuidePackage`, `deleteTravelGuidePackage`, `WIPTGuideController` |

## Admin - Users, Roles, Permissions, And Teams

| ID | Use case | Actor | Main flow | Result | Source files |
| --- | --- | --- | --- | --- | --- |
| ADM-USER-01 | View user management list | Admin | Open admin user management. | Users and related category data are listed. | `AdminUserManage.vue`, `UserController@manage_list`, `AdminController@user_info` |
| ADM-USER-02 | Create user | Admin | Fill user creation form with account and assignment data. | User account is created. | `AdminController@user_create` |
| ADM-USER-03 | Update user | Admin | Edit user details, credentials, category, or related setup. | User account is updated. | `AdminController@user_update` |
| ADM-USER-04 | Remove user category | Admin | Remove assigned category from user. | User category link is removed. | `AdminController@user_cat_remove` |
| ADM-USER-05 | Maintain user categories | Admin | Create, update, delete, list, or inspect user categories. | User category records are maintained. | `UserCategoryController`, `AdminUserCategoryAssign.vue` |
| ADM-USER-06 | Maintain user category benchmarks | Admin | Create, delete, inspect, or update benchmark target values. | Benchmark/target data by category is maintained. | `UserCategoryBenchmarkController`, `AdminUserCategoryBenchmark.vue` |
| ADM-TEAM-01 | Maintain supervisors | Admin | Create/list/delete supervisor records. | Supervisor records are maintained. | `SupervisorController@index`, `create`, `delete`, `AdminSupervisorUserAssign.vue` |
| ADM-TEAM-02 | Manage supervisor subordinates | Admin | Add or remove users under a supervisor. | Supervisor-subordinate mapping is maintained. | `SupervisorController@info`, `user_add`, `user_remove`, `SvSbPivot` |
| ADM-TEAM-03 | Check supervisor status | Admin/System | Request supervisor check for current context. | System returns supervisor-related status. | `AdminController@check_supervisor` |
| ADM-ACCESS-01 | Maintain roles | Admin | Create, update, delete, list, or inspect roles. | Role records are maintained. | `RoleController`, `AdminUserAccess.vue` |
| ADM-ACCESS-02 | Maintain permissions | Admin | Create, update, delete, list, or inspect permissions. | Permission records are maintained. | `PermissionController`, `AdminUserAccess.vue` |
| ADM-ACCESS-03 | Assign/remove permissions on roles | Admin | Add or remove permission for a role. | Role permission set is updated. | `RoleController@add_permission`, `RoleController@remove_permission`, `AdminPermissionAddViaRole.vue` |
| ADM-ACCESS-04 | View user role/permission matrix | Admin | Open user access screen. | Users, roles, permissions, and direct permissions are displayed. | `AdminController@user_role_permissions`, `user_role_permissions_info`, `AdminUserRolePermissionEdit.vue` |
| ADM-ACCESS-05 | Update user roles | Admin | Assign selected role(s) to user. | User roles are synced/updated. | `AdminController@user_role_update` |
| ADM-ACCESS-06 | Add direct user permissions | Admin | Assign direct permission to user. | User direct permission is added. | `AdminController@user_permission_update` |
| ADM-ACCESS-07 | Remove direct user permissions | Admin | Remove direct permission from user. | User direct permission is removed. | `AdminController@user_permission_remove` |

## Admin - Export, Import, And System Data

| ID | Use case | Actor | Main flow | Result | Source files |
| --- | --- | --- | --- | --- | --- |
| ADM-EXP-01 | Open admin export/import page | Admin | Open `/admin/export`. | Export/import UI is displayed. | `routes/web.php`, `AdminExport.vue` |
| ADM-EXP-02 | Get module export data | Admin | Request module export options/data. | Exportable module data is returned. | `AdminController@module_export`, `AdminExport.vue` |
| ADM-EXP-03 | Export module records | Admin or permitted user | Trigger module-specific export such as contacts, todos, followups, forecasts, performance, billboard, tempboard, users. | Excel export is downloaded when implemented by the module export class. | `app/Exports/**`, `AdminExport.vue`, module controllers |
| ADM-EXP-04 | Import contacts | Admin | Upload contacts spreadsheet. | Contact data is imported. | `ContactImport`, `ContactController@import`, `AdminExport.vue` |

## Shared Lookup And Support Use Cases

| ID | Use case | Actor | Main flow | Result | Source files |
| --- | --- | --- | --- | --- | --- |
| REF-01 | Load contact lookup lists | CRM User | Open forms that need category/type/status/industry. | Lookup options are returned. | `ContactCategoryController`, `ContactTypeController`, `ContactStatusController`, `ContactIndustryController` |
| REF-02 | Load contact select list | CRM User | Search company dropdown. | Matching contact IDs and names are returned. | `ContactController@list` |
| REF-03 | Load user select lists | CRM User, Admin | Open filters/forms needing users. | User list, manage list, action/performance user data, subordinates, or home data is returned. | `UserController@index`, `users_list`, `manage_list`, `action`, `performance`, `get_subordinates`, `user_home` |
| REF-04 | Load todo lookup lists | CRM User | Open todo/follow-up forms. | Tasks, sources, actions, and text colors are returned. | `TaskController`, `ToDoSourceController`, `ActionController`, `ToDoController@colors` |
| REF-05 | Load forecast lookup lists | CRM User | Open forecast forms/filters. | Products, types, and results are returned. | `ForecastProductController`, `ForecastTypeController`, `ForecastResultController` |
| REF-06 | Load tracking lookup lists | CRM User | Open tracking forms/filters. | BGOC divisions and travel guide packages are returned. | `BluedaleDivisionController`, `WIPTGuideController@getTravelGuidePackage` |

## System And Routing Behavior

| ID | Use case | Actor | Main flow | Result | Source files |
| --- | --- | --- | --- | --- | --- |
| SYS-01 | Serve Laravel app | System | Browser request enters `public/index.php`; Laravel kernel handles request. | Laravel returns Blade shell, auth page, API response, or asset. | `public/index.php`, `bootstrap/app.php`, `app/Http/Kernel.php` |
| SYS-02 | Serve Vue single-page routes | System | Authenticated page route loads `contact.blade.php`; Vue router renders current component. | Frontend module screen is displayed in `<router-view />`. | `routes/web.php`, `resources/views/contact.blade.php`, `resources/js/routes.js` |
| SYS-03 | Build frontend assets | Developer/System | Run Laravel Mix build scripts. | `resources/js/app.js` and CSS are compiled to `public/js/app.js` and `public/css/app.css`. | `webpack.mix.js`, `package.json`, `resources/js/app.js`, `resources/css/app.css` |
| SYS-04 | Load authenticated user permissions into frontend | System | Blade layout writes role/permission meta and `window.Laravel.jsPermissions`. | Vue route guards and permission checks can evaluate current user access. | `resources/views/layouts/app.blade.php`, `resources/js/routes.js`, `laravel-permission-to-vuejs` |
| SYS-05 | Create public storage symlink | Admin/System | Visit `/linkstorage`. | Laravel `storage:link` is called. | `routes/web.php` |

## Follow-Up Gaps Found While Creating Use Cases

These are not use cases to migrate blindly; they are items to inspect before rebuilding.

| Gap | Why it matters | Source files |
| --- | --- | --- |
| Catch-all web route is registered before many named module web routes. | `/contact/index`, `/todo/index`, and similar paths match the catch-all shell first. This may be intended for Vue routing, but the duplicate later routes are mostly bypassed. | `routes/web.php` |
| Some controller methods exist without active API routes. | Example: `FollowUpController@update`, `FollowUpController@action`, and `FollowUpController@info` are present but not exposed in `routes/api.php`. Decide whether to restore or remove them. | `FollowUpController`, `routes/api.php` |
| Some Vue components reference missing billboard endpoints. | `BillboardSummaryIndex.vue` and `BillboardCompanyIndex.vue` reference `/api/billboards/site` and `/api/billboards/size`, but those routes are not defined in `routes/api.php`. | `resources/components/billboards/**`, `routes/api.php` |
| Admin/API routes are mostly not grouped under auth middleware in `routes/api.php`. | If this app is served publicly, API authorization should be reviewed before migration. | `routes/api.php`, `app/Http/Kernel.php` |
| Several permissions are enforced on the frontend and navigation, while many API routes rely on broad route access. | The new system should enforce authorization at the backend too, not only in Vue route guards. | `resources/js/routes.js`, `routes/api.php`, controllers |

