# AGIS — Audit Governance Information System

AGIS uses a React 19 + Vite frontend styled with Tailwind CSS, Lucide React icons, Recharts dashboard visualizations, React Router for page URLs, and a Laravel 12 API backed by PostgreSQL. Authentication uses Laravel Sanctum's first-party SPA cookie sessions; passwords and permissions are verified by the backend and are no longer stored in browser storage.

For a feature-by-feature comparison of the current implementation, start with
the [As-Built Feature Catalog](docs/AS_BUILT_FEATURE_CATALOG.md). The complete
documentation index is in [docs/README.md](docs/README.md); source code and
automated tests remain authoritative when a reference specification differs.

## Requirements

- Node.js and npm
- PHP 8.2 or newer with `pdo_pgsql` and `pgsql`
- Composer
- PostgreSQL 14 or newer

The current local machine has PostgreSQL 18 installed at `C:\Program Files\PostgreSQL\18`.

## PostgreSQL setup

Create a dedicated application role and database from pgAdmin or `psql` while signed in as a PostgreSQL administrator:

```sql
CREATE ROLE agis_app WITH LOGIN PASSWORD 'replace-with-a-strong-local-password';
CREATE DATABASE agis OWNER agis_app ENCODING 'UTF8';
```

Then connect to the new `agis` database and remove public schema-creation rights:

```sql
REVOKE CREATE ON SCHEMA public FROM PUBLIC;
```

Copy `backend/.env.example` to `backend/.env` if the file does not exist, then set only the local secret:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=agis
DB_USERNAME=agis_app
DB_PASSWORD=your-local-password
```

Never commit `backend/.env`. For the initial empty database, run:

```powershell
php backend/artisan migrate:fresh --seed
```

`migrate:fresh` deletes existing tables and is only appropriate for initial/demo setup. Use `php backend/artisan migrate` for normal upgrades.

## Run locally

Use two terminals from the repository root:

```powershell
npm run api
```

```powershell
npm run dev
```

Vite proxies `/api` and `/sanctum` to `http://127.0.0.1:8000`, keeping Sanctum authentication same-origin in development.

## Frontend routes and permissions

Public authentication uses `/login`; authenticated users start at `/dashboard`. Each module has its own URL, including `/office-registry`, `/internal-audit-planning`, `/audit-engagement-management`, `/audit-findings-recommendations`, and the remaining registry and administration pages defined in `src/config/navigation.js`.

The sidebar and dashboard cards only show routes allowed by the authenticated user's permission list. Typing a disallowed URL directly redirects to `/unauthorized`. This frontend guard improves navigation, but every future module API must also use Laravel's `auth:sanctum` and `permission` middleware.

The production build includes `public/.htaccess`, which sends unknown Apache paths back to `index.html` so routed pages continue to work after a browser refresh.

## Demo accounts

Demo accounts are seeded with hashed passwords. Their clickable credentials are returned by `/api/demo-accounts` only when `DEMO_ACCOUNTS_ENABLED=true`. Disable that setting outside local/demo environments.

| Role                   | Employee ID         | Default local password |
| ---------------------- | ------------------- | ---------------------- |
| Platform Administrator | `AGIS-PLATFORM-001` | `lala`                 |
| AGIS Administrator     | `AGIS-ADMIN-001`    | `lala`                 |
| CIAS Management        | `CIAS-HEAD-001`     | `lala`                 |
| AGIS User              | `CIAS-AUD-001`      | `lala`                 |
| Auditee Representative | `AUDITEE-001`       | `lala`                 |
| Read Only User         | `MAYOR-001`         | `lala`                 |

Change these through `backend/.env` before seeding any shared environment.

## Verification

```powershell
npm run lint
npm run build
npm run test:api
```

The API health endpoint is `GET /api/health`. Authentication endpoints are `POST /api/login`, `GET /api/me`, and `POST /api/logout`.

AGIS Core includes working Office, Audit Area, Audit Focus, User, Access Role,
Permission, and Master List registries; System Configuration; Activity Log; and
self-service Profile/Password pages. Demo seeding creates the 42 referenced city
offices plus the AGIS system office, an office head and employee account per city
office, realistic audit areas/focuses, and shared workflow reference lists.

AEMS now includes a protected module dashboard at
`/audit-engagement-management/dashboard` and the Audit Engagement Workspace at
`/audit-engagement-management`. The dashboard is an access-scoped Engagement
Tracker with portfolio cards, overdue indicators, 14 workflow-stage progress
measures, and derived pre-closure gates. Approved IAP engagements are imported
without duplication and special/unplanned engagements start as `DRAFT` with a
separate Engagement Scope transaction. Scope, team, AEO, planning, and detail
workspaces are unlocked by their backend phase gates; locked workspaces explain
the current phase and the action required to continue. Audit Team assignment
adds person-days, dates, ARMIS competency/capacity warnings, and immutable
reassignment history. The AEO workspace implements review,
return/resubmission, approval, issuance, formal revisions, immutable versions,
and approved-version PDF generation. AEP and Audit Program establish the
fieldwork baseline. The Working Papers and Evidence workspace now provides
procedure-linked immutable content versions, independent return/approval,
revision history, protected checksum-verified evidence versions, exact evidence
locking, confidentiality, and authorized downloads.

The aggregate engagement lifecycle is executable through one authoritative
transition service from `DRAFT` through the guarded atomic `CLOSED`
transition. Its workspace shows
the status timeline, permitted actions, child-workflow blockers, related
records, and immutable history. The official PGIAM Entry Conference is a
separate pre-fieldwork gate with structured briefing content, internal,
auditee, and external participants, attendance, matters, commitments, Notes,
exact Core DocumentVersions, auditee acknowledgement, elevated waiver, and
immutable completion. Formal Completion Assessment and Engagement Closure add
25 completion criteria, a source-derived checklist, exact final document index,
interim retention/custody metadata, lessons learned, independent approval,
immutable closed snapshots, and written-authority exceptional reopening.

The Issues, Findings, and Recommendations workspace completes supported issue
capture, independent validation, idempotent conversion, criteria-condition-
cause-effect findings, formal communication, versioned management responses,
auditor rejoinders, and immutable finalized recommendations ready for later CMS
transfer.

The AEMS sidebar now separates Audit Issues, Findings & Recommendations, and
Auditee Responses into focused workspaces. Dialogue exchanges preserve the
actor, timestamps, content, version, and private supporting documents; each
attachment is checksum-verified and pinned to the exact response or rejoinder
version.

Exit Conferences now have a dedicated workspace for hybrid scheduling,
participants and attendance, directly linked finding discussions, agreements
and disagreements, revised target dates, locked minutes, private immutable
attachments, and versioned auditee acknowledgements.

Audit Reports now have a dedicated generation workspace with arranged sections,
validated-Finding Draft Reports, finalized-Finding Final Reports, immutable
private PDF versions, version-bound reviewer comments and recipients, controlled
issuance, SHA-256 checksums, and idempotent recommendation intake into CMS.
The tracker derives every count and percentage from those authoritative
workflow records; it does not maintain a second copy of workflow state. CIAS
management can export the same scoped data as an audited Engagement Progress
CSV.

AEMS cross-module dependencies now resolve through explicit integration
boundaries. Approved IAP engagements are consumed read-only with immutable
source snapshots; issued recommendations enter CMS through an idempotent
create-once adapter; and capacity, availability, competencies, workload, and
person-days are supplied operationally by ARMIS. `ARMIS_AUTHORITATIVE` is the
only active provider mode; historical IAP/fallback values are lineage-only and
cannot be selected or used for approval. Assignments, review transitions,
returned Working Papers, communicated Findings, Exit Conferences, report
approval/issuance, and scheduled deadline reminders use the deduplicated Core
Notification service. Core remains the authority for identities, access,
reference data, documents, logs, runtime configuration, and document numbering.

## Documentation

- `docs/README.md` — documentation index
- `docs/SYSTEM_FLOW.md` — complete end-to-end system flow
- `docs/CORE_WORKFLOW_DESIGN.md` — as-built AGIS Core workflow
- `docs/IAP_WORKFLOW_DESIGN.md` — as-built IAP workflow
- `docs/AEMS_WORKFLOW_DESIGN.md` — approved AEMS workflow design baseline
- `docs/API_AND_DATA_REFERENCE.md` — API and entity reference
- `docs/OPERATIONS_GUIDE.md` — setup, deployment, backup, and troubleshooting
- `docs/DEVELOPMENT_STANDARDS.md` — required security, privacy, quality, and operations rules

## Architecture

- `src/` — React Router interface styled with Tailwind CSS
- `src/config/navigation.js` — route, navigation, and permission map
- `lucide-react` — tree-shakable interface icons; navigation stores icon components directly
- `src/services/api.js` — same-origin Sanctum/API client
- `backend/app/` — Laravel controllers, requests, middleware, resources, and models
- `backend/database/migrations/` — PostgreSQL-compatible schema
- `backend/database/seeders/` — roles, permissions, offices, and demo accounts
- `docs/DEVELOPMENT_STANDARDS.md` — required security, privacy, quality, and operations rules

Build Core Registries first, then IAP, AEM, AFR, CMS, ARMS, and AIS. Every module endpoint must enforce permissions on the server; hiding a React button is never authorization.
