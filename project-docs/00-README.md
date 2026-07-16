# Documentation Generation Kit — Bluedale CRM

This folder contains one markdown file per **group** of documents you need to
produce for this system. Each file is a self-contained prompt asking for two or
three related documents in a single response (clearly separated by `PART 1` /
`PART 2` / `PART 3` headers), built from real facts extracted from the codebase
(schema, routes, permissions, roles, actual `.env.production.example` content) so
the generated documents are accurate rather than generic filler.

## How to use

1. Open a new chat.
2. Paste the **entire contents** of one file (e.g. `03-database-erd-and-
   dictionary.md`) as your message.
3. The assistant produces all parts requested in that file, in one response —
   copy each part into your final deliverable (Word, Confluence, PDF, etc.).
4. Repeat for each file. Use a new conversation per file so the facts for one
   group don't bleed into another.

## Files (10 groups, ~22 documents total)

| # | File | Documents produced |
|---|------|------------------|
| 01 | `01-overview-and-requirements.md` | System Overview / Project Charter + Functional Requirements Spec |
| 02 | `02-tech-stack-and-architecture.md` | Tech Stack Document + System Architecture Diagram |
| 03 | `03-database-erd-and-dictionary.md` | Entity Relationship Diagram + Data Dictionary |
| 04 | `04-api-and-flows.md` | API Specification + Sequence/Flow Diagrams |
| 05 | `05-security-rbac-and-auth.md` | RBAC/Permissions Matrix + Auth & Authorization Design + Security Assessment |
| 06 | `06-setup-deployment-and-config.md` | Migration & Seeding Guide + Deployment Guide + Environment Config Reference |
| 07 | `07-operations-backup-and-monitoring.md` | Backup & Recovery Plan + Monitoring & Logging Plan |
| 08 | `08-test-plan-and-uat.md` | Test Plan + UAT Sign-off Sheet |
| 09 | `09-user-and-admin-guide.md` | User Manual + Admin Guide |
| 10 | `10-changelog-and-tech-debt.md` | Change Log / Release Notes + Known Issues / Tech Debt Register |

## Notes on accuracy

Files 03, 04, and 05 were built directly from the actual migrations, routes, and
seeders in this repository as of 2026-07-16, so the schema/route/permission facts
in them are real, not invented. File 06's Part 3 uses the actual
`.env.production.example` file content verbatim. Files 08–10 are process
templates — they need your input (test results, known bugs, release history) to
become fully complete; they're scaffolded with the right structure and real
module names so you're not starting from a blank page.

Three things worth double-checking before treating any generated document as
final (flagged inline in the relevant files too):
- `WHATSAPP_*` env vars exist but no `WhatsappMessage` model was found in the
  codebase — likely unfinished/abandoned integration.
- `contact_emails`/`contact_calls` and `webhooks` tables were created then later
  dropped — dead history, not live schema.
- `dept_tasks.requires_approval` column still exists even though the approval
  workflow itself was removed 2026-07-15.
