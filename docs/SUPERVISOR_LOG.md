# Supervisor Feedback & Progress Log

> Running log of supervisor meetings, requests, and CRM build progress.
> Started: 2026-05-22
> Owner: paulamikki1@gmail.com

---

## Status Legend

- **[TODO]** — Not yet started
- **[IN PROGRESS]** — Currently working on
- **[BLOCKED]** — Waiting on a dependency (note the blocker)
- **[REVIEW]** — Done, waiting on supervisor approval
- **[DONE]** — Completed and signed off

---

## Current Focus

### Polish core contact-related features

- **Status:** [IN PROGRESS]
- **Assigned:** 2026-05-22
- **Goal:** Make sure Contacts, ToDos, Forecasts, and Summary all work properly end-to-end before moving on to any new feature.
- **Modules in scope:**
  - [ ] **Contacts** — [CrmList.vue](../resources/js/pages/CrmList.vue), [ContactList.vue](../resources/js/pages/ContactList.vue), [ContactAdd.vue](../resources/js/pages/ContactAdd.vue), [ContactEdit.vue](../resources/js/pages/ContactEdit.vue), [ContactView.vue](../resources/js/pages/ContactView.vue), [CrmView.vue](../resources/js/pages/CrmView.vue) — verify CRUD, filters, validation, duplicate check
  - [ ] **ToDos** — [TodoList.vue](../resources/js/pages/TodoList.vue), [TodoAdd.vue](../resources/js/pages/TodoAdd.vue), [TaskAdd.vue](../resources/js/pages/TaskAdd.vue), [TaskEdit.vue](../resources/js/pages/TaskEdit.vue) — verify CRUD, completion flow, filters, export
  - [ ] **Forecasts** — [ForecastList.vue](../resources/js/pages/ForecastList.vue), [ForecastAdd.vue](../resources/js/pages/ForecastAdd.vue), [ForecastEdit.vue](../resources/js/pages/ForecastEdit.vue), [ForecastSummary.vue](../resources/js/pages/ForecastSummary.vue) — verify CRUD, totals, contact-linked add flow
  - [ ] **Summary** — [Summary.vue](../resources/js/pages/Summary.vue) — verify all metrics, charts, date filters
- **Acceptance criteria:**
  - No broken flows in any of the four modules
  - All filters / exports / search working as expected
  - Inline validation clean (no console errors, no silent failures)
  - UI/UX consistent across the four modules

---

## Upcoming / Backlog

### Proposal Letter Generator (for billboard clients)

- **Status:** [BLOCKED]
- **Blocker:** Partner is currently working on a prerequisite task. Cannot start until that is done.
- **Description:** Generate proposal letters to clients with:
  - An adjustable **table of locations** where billboards will be placed
  - **Images** that can be customised per location / proposal
  - An editable **template** so the proposal layout can be tweaked per client
- **Open questions to confirm with supervisor when unblocked:**
  - Output format — PDF, DOCX, or both?
  - Where do the location images come from — upload per proposal, or pulled from a library?
  - Should proposals be saved/versioned per contact?
  - Does the proposal need a signature / approval flow?

---

## Meeting Log

> Add a new dated section after every supervisor meeting. Newest at the top.

### 2026-05-22 — Initial direction

**Supervisor said:**
- Focus first on polishing the **core contact-related features**: Contacts, ToDos, Forecasts, and Summary — make sure they all work properly before adding new features.
- After that, build a **proposal letter generator** for billboard clients: adjustable table of locations and images, with a customisable template.
- Proposal generator can only start once **partner finishes their current task** (it depends on their work).

**My takeaways:**
1. Audit and fix the four core modules until they're all clean end-to-end.
2. Wait for partner before starting the proposal generator.
3. Hold any other ideas / improvements until these two are done.

---

## Completed

> Move tasks here once supervisor signs off. Include completion date and a one-line summary.

_(none yet)_

---

## How to use this file

After every supervisor meeting:

1. **Add a new entry to "Meeting Log"** with today's date — write what supervisor said and your takeaways, in your own words.
2. **Update "Current Focus"** if the supervisor changed what you're working on this week.
3. **Add new requests to "Upcoming / Backlog"** with a description and any open questions.
4. **Move a task to "Completed"** once supervisor signs off — include the completion date and one-line summary.
5. **Update the checkboxes** in Current Focus as sub-tasks finish.

When prompting Claude to continue work:
> "Read docs/SUPERVISOR_LOG.md and continue with the current focus." — Claude will pick up exactly where you left off.

When recording a meeting:
> "Add a meeting log entry for today — supervisor said X, Y, Z." — Claude will append it and reshuffle the backlog if needed.
