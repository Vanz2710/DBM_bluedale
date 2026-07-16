This file asks for **two** documents in one response, separated by
`# ── PART 1 ──` / `# ── PART 2 ──` headers.

---

# PART 1 — Technology Stack Document

Audience: developers and technical reviewers (including future maintainers who
didn't build it). Include a rationale line for each major choice where one is
evident from the facts given (don't invent rationale that isn't implied). Present
as a table per layer (Backend / Frontend / Database / Build Tooling / Infra &
Hosting / Third-party services) plus a short prose section on notable
architectural decisions (see the "Notable architectural decisions" list at the end
of the facts below).

# PART 2 — System Architecture Diagram

A Mermaid `flowchart`/`graph` diagram of the components and request flow described
below, plus a short prose explanation of each component. Audience: developers
onboarding to the codebase. Do not invent components (no message broker, no
microservices, no separate API gateway — this is a monolithic Laravel app serving
a decoupled SPA).

Use only the facts below for both parts.

---

## Facts about the stack

**Backend**
- Laravel 13.7 (PHP 8.3+ required — app uses 8.3 syntax)
- Auth: Laravel Sanctum (token-based, stored client-side, not session cookies for the
  SPA)
- Authorization: Spatie `laravel-permission` (roles + permissions, guard `web`)
- All feature API routes are under `/api/v1/` prefix, gated by `auth:sanctum` +
  a custom `maintenance` middleware; email verification middleware was deliberately
  removed (email verification is fully disabled — users are auto-approved and
  auto-verified at creation)
- PDF/document generation: dompdf and mpdf/FPDI-style libraries used for proposal
  generation (site availability module); PhpSpreadsheet (phpoffice) used for
  spreadsheet import/export
- A hidden internal diagnostics/dev panel (`/_dp` prefix) exists behind its own
  `devpanel.auth` middleware and throttling — used for local/staging troubleshooting,
  not part of the public app surface

**Frontend**
- Vue 3, exclusively Composition API with `<script setup>`
- Vue Router 5, client-side history mode (`createWebHistory`)
- Axios as the HTTP client, with a custom instance (`resources/js/api.js`) that
  auto-attaches the Bearer token, strips Content-Type for FormData uploads, redirects
  to `/login` on 401 (deduplicated across concurrent requests), and invalidates a
  short-lived (~30s) GET response cache after any successful non-GET request
- Chart.js for all charts/graphs
- No CSS framework (explicitly **no Tailwind**) — hand-written scoped CSS per
  component using a shared CSS-variable design token system (colors, radii, spacing)
- No emoji/icon-font usage in the UI — an inline SVG icon system is used instead
- lottie (animation library) is present as its own manual Vite chunk

**Build tooling**
- Vite 8, with manual chunk splitting configured for chart.js, vue-router, axios,
  and lottie (kept out of the main bundle)
- Two separate env vars control base paths: `VITE_BASE_URL` (Vite asset base, where
  built JS/CSS chunks are served from) and `VITE_APP_BASE` (Vue Router history base,
  the app's URL root) — these must NOT be conflated, since XAMPP/cPanel deployments
  often serve the app from a subfolder

**Database**
- MySQL, primary database on port 3307 in local dev (XAMPP default has been changed
  from the usual 3306), database name `bgoc_crm_newdb`; production deployments run on
  the standard port 3306
- Two additional **read-only legacy databases** exist on port 3306 locally, used only
  by a one-off legacy-data import command — not part of the live application's
  read/write path
- Dedicated test database `bgoc_crm_test` on port 3307

**Infra & Hosting**
- Local development: XAMPP (Apache + MySQL) on Windows
- Production: cPanel shared/VPS hosting; Laravel project lives outside `public_html`,
  document root points at the Laravel `public/` folder
- Redis is typically unavailable on shared cPanel hosting — the app falls back to
  file-based session/cache drivers and either `sync` or database-backed queue when
  Redis isn't present; Upstash is the documented fallback for hosted Redis if a real
  queue is needed
- Background jobs run via a cron-triggered `queue:work --stop-when-empty` on shared
  hosting (no persistent daemons available), or Supervisor on a VPS

**Third-party services**
- Transactional email: originally Gmail SMTP in development; production guidance is
  to move to Brevo/SendGrid/Mailgun
- Email marketing campaigns are built around Brevo-style SMTP sending with
  open/click tracking via a signed per-recipient token

## Notable architectural decisions (for Part 1's prose section)
- Token auth (Sanctum) instead of session auth, chosen for a decoupled SPA
- Deliberate removal of email verification as a gate (all users auto-verified/
  auto-approved on creation) — this is a business decision, not an oversight
- Separation of the customer-facing To-Do/Follow-Up system from the internal
  Department Task Manager — two conceptually different task systems that must not be
  conflated
- Vite base-URL vs. router base-URL kept as two separate env vars specifically to
  avoid a known class of subfolder-deployment bugs

## Components and flow (for Part 2's diagram)

1. **Browser (Vue 3 SPA)** — the entire frontend is a single-page app. It is served
   as static built assets (JS/CSS chunks produced by Vite) from the Laravel
   `public/build/` directory.
2. **Laravel `routes/web.php` catch-all** — any non-API URL is served
   `resources/views/app.blade.php`, a near-empty Blade shell whose only job is to
   `@vite(['resources/js/app.js'])` and mount the div the Vue app attaches to.
3. **`resources/js/app.js`** — bootstraps Vue, sets up Vue Router with
   `createWebHistory(import.meta.env.VITE_APP_BASE)`, registers the router with the
   Axios instance (`setRouter()`), and mounts the app only after
   `router.isReady()` resolves (with a catch-based fallback mount so a bad
   `localStorage` value can never blank the page).
4. **Vue Router guard (`setupGuard` in `router/index.js`)** — checks
   `localStorage.crm_token` / `crm_user` before every protected navigation; enforces
   `adminOnly` route meta.
5. **Axios instance (`resources/js/api.js`)** — attaches `Authorization: Bearer
   <crm_token>` to every request, strips `Content-Type` for FormData uploads,
   redirects to `/login` on any 401 (de-duplicated across concurrent in-flight
   requests), maintains a short-lived client-side GET cache that is invalidated on
   any successful non-GET response.
6. **Laravel API (`routes/api.php`, prefix `/api/v1/`)** — all feature endpoints,
   behind `auth:sanctum` + `maintenance` middleware, with fine-grained `can:
   <permission>` middleware per route group. A small set of routes are public/
   unauthenticated: `POST /auth/login`, `POST public/lead` (throttled lead
   capture), and (separately, outside `/v1`) a `_dp` diagnostics prefix behind its
   own `devpanel.auth` middleware.
7. **Controllers → Eloquent Models → MySQL** — standard Laravel MVC-style flow;
   models are flat in `app/Models/` (no sub-namespacing by domain).
8. **MySQL primary database** (`bgoc_crm_newdb`, port 3307 locally / 3306 in
   production) — single source of truth for all live data.
9. **Two read-only legacy MySQL databases** (port 3306 locally) — consulted only by
   a one-time legacy-data import Artisan command; not touched by normal request
   traffic.
10. **Queue worker** — processes background jobs (e.g. outbound email sending for
    campaigns, admin notification emails); in production this is a cron-triggered
    `queue:work --stop-when-empty` (shared hosting, no persistent daemon) or a
    Supervisor-managed daemon (VPS); `sync` driver is acceptable if load is low
    enough that no queue is really needed.
11. **Mail service** — outbound transactional and campaign email via SMTP (Gmail in
    dev, Brevo/SendGrid/Mailgun recommended in production).
12. **Cache/session store** — file-based by default (Redis optional, generally
    unavailable on shared cPanel hosting).

**Request flow to describe in prose:** Browser loads SPA shell → Vue Router resolves
route → guard checks local auth state → page component mounts → Axios call to
`/api/v1/...` with Bearer token → Sanctum authenticates → `maintenance` +
`can:<permission>` middleware authorize → controller executes → Eloquent hits MySQL
→ JSON response → Axios cache updated/invalidated → Vue re-renders.
