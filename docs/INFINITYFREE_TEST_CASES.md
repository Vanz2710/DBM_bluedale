# InfinityFree Staging — Live Test Cases

**Site:** `https://bgoccrm.infinityfreeapp.com`  
**Purpose:** Verify the CRM works correctly on the live InfinityFree staging environment before moving to cPanel production.

**Legend:** `[ ]` = not tested · `[x]` = passed · `[!]` = failed (log in Bug section at the bottom)

---

## Pre-Test Setup

Before running any tests, complete these steps exactly once:

- [ ] Visit `https://bgoccrm.infinityfreeapp.com/setup_admin.php`
  - Confirm each artisan command prints success (migrate, seed, config:cache, etc.)
  - If any command fails, note the error and do not proceed
- [ ] **Delete `setup_admin.php`** from File Manager immediately after it runs
- [ ] Open `https://bgoccrm.infinityfreeapp.com/login` in an **incognito window**
  - Confirm the login page renders with styling (not a blank page)
  - Confirm no red errors in DevTools Console (F12)

---

## T1 — Page Load & Asset Delivery

These confirm InfinityFree is actually serving the JS bundle and CSS correctly.

- [ ] Open DevTools → Network tab, reload the login page
  - [ ] `app-[hash].js` returns HTTP **200** (not 404 or 500)
  - [ ] `app-[hash].css` (or inline styles) returns HTTP **200**
  - [ ] No file shows MIME type `text/html` for a `.js` file
- [ ] DevTools → Console: **zero red errors** on the login page
- [ ] Login page is fully styled (not raw HTML, not white with unstyled text)
- [ ] The `<div id="app">` element is **not empty** (DevTools → Elements, find `#app` — it should contain the login form markup)

---

## T2 — Authentication

- [ ] **Correct login** — username `superadmin`, password `Admin@1234` → redirects to dashboard
- [ ] **Wrong password** — enter wrong password → error message shown on screen (not a 500 crash)
- [ ] **Wrong username** — enter nonexistent username → same error message (no info leak)
- [ ] **Page refresh while logged in** — after login, press F5 → stays on dashboard (not kicked to login)
- [ ] **Logout** — click logout → redirected to `/login`, cannot navigate back to dashboard without logging in again
- [ ] **Token persistence** — open new tab, navigate to `https://bgoccrm.infinityfreeapp.com/` → automatically loads dashboard (token still in localStorage)

---

## T3 — Dashboard

- [ ] Dashboard page loads without a blank screen or JS error
- [ ] Stat cards display numbers (contact count, todo count, etc.) — not zeros with errors in console
- [ ] Charts/graphs render (or a graceful empty state if no data)
- [ ] Notification bell icon is visible in the top bar
- [ ] Sidebar navigation is visible and links are clickable
- [ ] Username shown correctly in the top bar / profile area

---

## T4 — Contacts

- [ ] Contact list page loads (`/contacts`) — table or empty state shown
- [ ] Add new contact:
  - [ ] Click "Add Contact" / equivalent
  - [ ] Fill required fields (name, status, type)
  - [ ] Save → contact appears in the list
- [ ] Search / filter — type a name in the search box → list filters correctly
- [ ] Click a contact → contact detail page loads (all tabs: Emails, Calls, In-Charge, Todos, Follow-Ups)
- [ ] Edit contact → change a field, save → change persists after page refresh

---

## T5 — Todos

- [ ] Global todo list loads (`/todos`)
- [ ] Add a new todo:
  - [ ] Fill in title and due date
  - [ ] Save → todo appears in list
- [ ] Mark a todo as complete → status changes visually
- [ ] Add a todo from within a contact detail page → appears in that contact's Todos tab

---

## T6 — Follow-Ups

- [ ] Follow-up list loads
- [ ] Add a follow-up linked to an existing todo → saves
- [ ] Mark a follow-up complete → status changes

---

## T7 — Deals

- [ ] Deals list/pipeline loads
- [ ] Add a new deal:
  - [ ] Link it to a contact
  - [ ] Set a value and expected close date
  - [ ] Save → deal appears in list
- [ ] Edit deal → change stage → saves correctly
- [ ] Mark deal as Won → status updates, appears in performance metrics

---

## T8 — Projects

- [ ] Projects list loads
- [ ] Add a new project linked to a contact → saves
- [ ] Edit project → updates correctly

---

## T9 — Notification Bell

- [ ] Bell icon shows a badge count if there are overdue or upcoming items
- [ ] Click bell → dropdown opens with Overdue, Today, Upcoming sections
- [ ] Overdue items are shown if any todos/follow-ups are past their date
- [ ] Mark an item as read → badge count decreases
- [ ] System alerts section appears (change your password to trigger one, then check the bell)

---

## T10 — Performance

- [ ] Performance page loads (`/performance`)
- [ ] Overview tab: KPI cards show counts for the current period
- [ ] Change period (week / month / year) → numbers update without page refresh
- [ ] Activity tab loads

---

## T11 — Admin Panel

Log out and log back in as `superadmin` to ensure admin access.

- [ ] Navigate to Admin panel — loads without 403
- [ ] **User Management** — list of users loads; the superadmin account is listed
- [ ] **Create a test user:**
  - [ ] Add user with email `testuser@test.com`, any username
  - [ ] Assign `user` role
  - [ ] Save → user appears in list
- [ ] **RBAC Panel** — loads, shows roles and permissions
- [ ] **Lookup Management** — lists contact statuses, types, categories, etc.
- [ ] **System Settings** — loads; set `admin_notification_email` to your email and save

---

## T12 — RBAC & Permissions

Using the test user created in T11:

- [ ] Log out as superadmin, log in as the test user (username / password you set)
- [ ] Test user can access the dashboard
- [ ] Test user **cannot** access Admin panel — should see a forbidden / 403 page, not a crash
- [ ] Sidebar does **not** show admin-only links for the test user
- [ ] Log back out and log back in as `superadmin`

---

## T13 — Navigation & SPA Routing

- [ ] Click through at least 5 different sidebar links — each page loads without a full white screen
- [ ] Use the browser **Back button** after navigating — works correctly (does not send to 404)
- [ ] **Refresh on a non-root page** — e.g. navigate to `/contacts`, press F5 → page reloads correctly (does not 404)
  - This tests the `.htaccess` SPA rewrite rule
- [ ] **Direct URL navigation** — paste `https://bgoccrm.infinityfreeapp.com/contacts` into a new tab → loads the contacts page (not a 404)

---

## T14 — Topbar Search

- [ ] Click the search icon in the topbar
- [ ] Type a page name (e.g. "Contacts") → matching pages appear in results
- [ ] Click a result → navigates to that page
- [ ] Admin-only pages do not appear when logged in as the test user (from T12)

---

## T15 — Profile & Settings

- [ ] Navigate to your profile
- [ ] Change display name → saves
- [ ] Navigate to Settings → both Account and Admin tabs load
- [ ] Settings → Admin tab shows quick links to all admin pages

---

## T16 — Error Monitoring (Sentry)

- [ ] Log in to `sentry.io` dashboard
- [ ] After any JS error or 500 error occurs during testing, confirm it appears in the Sentry project
- [ ] If no errors occurred, manually check: DevTools Console should be clean after a full navigation session

---

## T17 — InfinityFree-Specific Checks

These are unique to InfinityFree and may not apply on cPanel.

- [ ] **No MIME errors** — confirm no `text/html MIME type` errors in DevTools Console across all pages visited
- [ ] **Bundle size** — in DevTools Network, note the size of `app-[hash].js`. It should be ~2 MB (IIFE single bundle). Confirm it loads in a reasonable time (~5–15 seconds on a slow free server is acceptable)
- [ ] **File driver cache** — the site uses file-based caching. After a few navigation events, check that pages still load correctly (no cache corruption)
- [ ] **Sync queue** — any action that would normally queue a job (e.g. WhatsApp webhook) runs inline. Confirm no timeout errors on regular actions
- [ ] **Storage path** — if any file upload is triggered, confirm it succeeds (writes to `htdocs/storage/app/`)

---

## T18 — Smoke Test After Admin Setup

Quick 10-minute run-through to confirm end-to-end flow is working:

1. [ ] Log in as `superadmin`
2. [ ] Create a contact named "Test Company"
3. [ ] Add a todo to that contact with today's date
4. [ ] Add a follow-up to that todo
5. [ ] Create a deal linked to "Test Company" with value 1000
6. [ ] Go to Performance → verify deal appears in metrics
7. [ ] Check notification bell → overdue or today item should appear for the todo
8. [ ] Log out
9. [ ] Log back in → session works, data persists

---

## Bug Log

Record every bug found during testing. Do not proceed to cPanel production deployment until all Critical and High bugs are resolved.

| # | Test | Description | Severity | Status |
|---|------|-------------|----------|--------|
| 1 | | | | |
| 2 | | | | |

**Severity guide:**
- `Critical` — data loss, auth bypass, page crashes, cannot complete a core flow
- `High` — feature broken or gives wrong output
- `Medium` — wrong output but workable
- `Low` — cosmetic / minor UX issue

---

## Sign-Off

- [ ] All T1–T18 items passed (or failures logged and assessed)
- [ ] Zero Critical bugs open
- [ ] Zero High bugs open (or accepted with documented reason)
- [ ] Sentry monitoring confirmed working
- [ ] `setup_admin.php` has been deleted from the server
- [ ] Admin notification email set in System Settings

**When all boxes are checked: ready to proceed to cPanel production.**  
Follow `DEPLOY_CPANEL.md` and reference `INFINITYFREE_STATUS.md` Part 3 for the code changes needed before deploying to cPanel.
