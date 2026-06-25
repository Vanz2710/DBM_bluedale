# BGOC CRM — Documentation Index

All important project docs in one place. Files at the project root are listed here with a one-line summary.

---

## Deployment & Operations

> **Status:** InfinityFree staging testing is **complete** (see `TEST_CASES_RESULTS.md`). Next step is the
> production deploy to **NetOnBoard cPanel** via `DEPLOY_CPANEL.md`.

| File | What it covers |
|------|---------------|
| [DEPLOY_CPANEL.md](../DEPLOY_CPANEL.md) | **START HERE for production** — step-by-step NetOnBoard cPanel deployment checklist (phases 1–7) |
| [PRODUCTION_READINESS.md](../PRODUCTION_READINESS.md) | Infra checklist — cache/queue drivers, indexes, Sentry, rate limiting, health check, backups (cPanel) |
| [TEST_CASES_CONTEXT.md](../TEST_CASES_CONTEXT.md) | Reference for generating the formal test-case document |
| [TEST_CASES_RESULTS.md](../TEST_CASES_RESULTS.md) | Results & remarks from the completed test pass (companion to the context doc) |
| [private/INFINITYFREE_STATUS.md](../private/INFINITYFREE_STATUS.md) | Historical — InfinityFree staging setup + the server-side changes that were NOT committed. ⚠️ Live credentials, local only. |
| [TESTING_PHASE.md](../TESTING_PHASE.md) | Historical — original pre-deployment testing tracker (superseded by `TEST_CASES_RESULTS.md`) |

---

## Development Standards & Architecture

| File | What it covers |
|------|---------------|
| [CLAUDE.md](../CLAUDE.md) | Full architecture overview, stack, auth pattern, RBAC rules, data model, dev commands |
| [UI_DESIGN_STANDARDS.md](../UI_DESIGN_STANDARDS.md) | CSS variable tokens, spacing, typography, component rules — must read before any frontend work |
| [README.md](../README.md) | Project intro and quick-start |

---

## Backlog & Deferred Work

| File | What it covers |
|------|---------------|
| [DEFERRED_WORK.md](../DEFERRED_WORK.md) | Scalability fixes, data quality items, frontend structure splits — do before production load |

---

## Deployment Order (Quick Reference)

```
InfinityFree (staging) ........ DONE — all in-scope modules PASS (TEST_CASES_RESULTS.md)
  → Defects found during testing were fixed & re-verified

NetOnBoard cPanel (production) .. NEXT
  → Follow DEPLOY_CPANEL.md (standard build — do NOT use VITE_IIFE)
  → Check PRODUCTION_READINESS.md items (cache/queue driver, backups, SSL, health check)
  → Still untested going into production: Deals, Projects, Import (were out of scope)
```
