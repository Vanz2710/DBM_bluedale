# BGOC CRM — Documentation Index

All important project docs in one place. Files at the project root are listed here with a one-line summary.

---

## Deployment & Operations

| File | What it covers |
|------|---------------|
| [docs/INFINITYFREE_STATUS.md](INFINITYFREE_STATUS.md) | **START HERE** — Live InfinityFree setup, what changed, and exact migration steps to real cPanel |
| [docs/INFINITYFREE_TEST_CASES.md](INFINITYFREE_TEST_CASES.md) | All test cases to run against the live InfinityFree staging site |
| [INFINITYFREE_DEPLOYMENT_GUIDE.md](../INFINITYFREE_DEPLOYMENT_GUIDE.md) | Generic framework-agnostic guide for any site on InfinityFree (good to send to others) |
| [STAGING_TODO.md](../STAGING_TODO.md) | InfinityFree account/DB credentials and original staging to-do list |
| [DEPLOY_CPANEL.md](../DEPLOY_CPANEL.md) | Step-by-step cPanel deployment checklist (phases 1–7) |
| [TESTING_PHASE.md](../TESTING_PHASE.md) | Full pre-deployment testing tracker — local + live, all feature modules, go/no-go sign-off |
| [PRODUCTION_READINESS.md](../PRODUCTION_READINESS.md) | Infrastructure checklist — Redis, indexes, Sentry, rate limiting, health check, backups |

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
InfinityFree (current staging)
  → Run test cases from INFINITYFREE_TEST_CASES.md
  → Fix any Critical/High bugs found
  → Review INFINITYFREE_STATUS.md "Migration to cPanel" section
  → Follow DEPLOY_CPANEL.md

cPanel (production)
  → All TESTING_PHASE.md Part C items must pass
  → PRODUCTION_READINESS.md items must be checked
  → DEFERRED_WORK.md Priority 1 items should be done first
```
