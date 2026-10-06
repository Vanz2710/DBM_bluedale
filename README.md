# Library CRM v2 (Bluedale Group of Companies)

A production-grade, multi-user Customer Relationship Management (CRM) system built for managing contacts, sales pipelines, forecasting, tasks, follow-ups, and marketing operations. Engineered as a complete end-to-end solution with a Laravel 13 backend API and a reactive Vue 3 SPA frontend.

---

## UI & Interface Design

> **Note:** The UI prioritizes clean data visualization, responsive layouts, and progressive disclosure to handle high information density across three distinct permission tiers without visual clutter.

### Authentication
<img width="1023" height="484" alt="image" src="https://github.com/user-attachments/assets/51ffb572-ca0b-40a6-ba53-3f8eb3697508" />
*A clean, modern authentication gateway for the Bluedale CRM workspace.*

### Management Dashboard
<img width="1024" height="487" alt="image" src="https://github.com/user-attachments/assets/175cfd01-7177-4821-aa51-7770bf3cadbd" />
*The primary administrative view featuring interactive pipeline visualizations, recent contact activity, and pending task overviews.*

### Data Management & Progressive Disclosure
<img width="1575" height="743" alt="image" src="https://github.com/user-attachments/assets/7278c4e3-19f0-43c6-a4e8-234820cc6414" />
*The main contacts directory supporting over 15,000 records. Features a guided user tour and robust filtering (Date Range, Status, Industry) without cluttering the primary view.*

<img width="1024" height="486" alt="image" src="https://github.com/user-attachments/assets/8ee7fd71-eaf6-4622-848c-e14ac38c7e41" />
*Step-by-step modal forms keep the user in the context of their current task without requiring full-page reloads.*

### Detailed Record View
<img width="1543" height="763" alt="image" src="https://github.com/user-attachments/assets/77922cc4-c1fe-40d1-978c-91e09a214705" />
*A comprehensive, single-page view for individual accounts, displaying contact tags, monthly activity heatmaps, assigned personnel, and actionable to-do lists.*

---

## Architectural & Design Decisions

* **Progressive Disclosure:** To prevent cognitive overload on data-heavy reporting screens, complex filters and administrative tools are hidden behind clean, semantic dropdowns and modals until explicitly needed by the user.
* **Unified Role-Based Layout (Spatie RBAC):** Instead of building entirely separate portals, the interface uses a single, unified layout that dynamically strips away unauthorized navigation links and data columns based on backend middleware permissions.
* **Reactive Client State:** Vue 3 (Composition API) is leveraged for complex DOM manipulations and asynchronous state changes, eliminating full-page reloads during high-volume data entry and ensuring a snappy, application-like feel.
* **Multi-Database Architecture:** The system manages concurrent connections to a primary MySQL database (port 3307) for new CRM operations while seamlessly interfacing with two read-only legacy databases (port 3306) to maintain historical data continuity.

---

## Tech Stack

- **Backend:** Laravel 13, PHP 8.3+, Sanctum token auth, Spatie RBAC
- **Frontend:** Vue 3 SPA (Composition API), Vue Router 5, Axios, Chart.js
- **Database:** MySQL (Primary on port 3307) + two read-only legacy DBs (port 3306)
- **Build:** Vite 8

---

## User Roles

| Role | Access |
|------|--------|
| `super-admin` | Full access including user management and RBAC. |
| `admin` | Full access including team performance metrics and administrative panels. |
| Regular user | Scoped access to own contacts, todos, follow-ups, deals, and forecasts. |

---

## Quick Setup

```bash
# 1. Install dependencies
composer install
npm install

# 2. Configure environment
cp .env.example .env
php artisan key:generate
# Edit .env — set DB_* values (see .env.example comments for the multi-DB setup)

# 3. Run migrations and seed reference data
php artisan migrate
php artisan db:seed --class=RolesAndPermissionsSeeder

# 4. Start all services (Laravel + Vite HMR + queue + log tail)
composer run dev
