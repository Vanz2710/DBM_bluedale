# Documentation

Architecture and the day-to-day development guide live in [`CLAUDE.md`](../CLAUDE.md) at the repo root. Everything else is here.

## Architecture & standards

| Doc | What it covers |
|-----|----------------|
| [feature-file-map.md](feature-file-map.md) | Which controller, model and Vue page own each feature |
| [ui-design-standards.md](ui-design-standards.md) | Design tokens, spacing, typography and component rules for the Vue frontend |
| [dev-panel.md](dev-panel.md) | The standalone ops console (`/xp`) and its master-key auth |
| [legacy-erd.md](legacy-erd.md) | Schema of the legacy CRM this system replaced |

## Deployment

| Doc | What it covers |
|-----|----------------|
| [deploy-cpanel.md](deploy-cpanel.md) | Step-by-step deployment to shared cPanel hosting |
| [production-readiness.md](production-readiness.md) | Cache/queue drivers, indexes, rate limiting, backups and health checks |

## Audits & roadmap

| Doc | What it covers |
|-----|----------------|
| [major-improvements.md](major-improvements.md) | System-wide audit of backend, frontend, database, UX and security |
| [feature-audit.md](feature-audit.md) | Feature coverage measured against a Zoho CRM checklist |
| [admin-diagnosis.md](admin-diagnosis.md) | Bug audit of the administration features |
| [deferred-work.md](deferred-work.md) | Scalability, data-quality and frontend work deferred for later |
