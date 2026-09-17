# AGIS Operations Guide

Use [AGIS As-Built System Manual](AGIS_AS_BUILT_SYSTEM_MANUAL.md) for the
module contract and [AGIS Operational Playbooks](AGIS_OPERATIONAL_PLAYBOOKS.md)
for operator procedures. This guide covers environment, deployment, and
verification mechanics.

## 1. Requirements

- Node.js and npm;
- PHP 8.2 or newer;
- Composer;
- PostgreSQL 14 or newer;
- PHP extensions required by Laravel plus `pdo_pgsql` and `pgsql`;
- Apache/XAMPP or another supported web server for production-like hosting.

## 2. Environment setup

Copy `backend/.env.example` to `backend/.env` and set environment-specific values.
Never commit `.env`.

Minimum PostgreSQL settings:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=agis
DB_USERNAME=agis_app
DB_PASSWORD=replace-with-a-local-secret
```

Generate the Laravel application key once:

```powershell
cd backend
php artisan key:generate
```

`APP_KEY` protects encrypted configuration secrets. Back it up securely. Changing
it without a migration plan makes existing encrypted SMTP credentials unreadable.

## 3. Install dependencies

From the project root:

```powershell
npm.cmd install
composer install --working-dir=backend
```

## 4. Database setup

For a new local/demo database only:

```powershell
php backend/artisan migrate:fresh --seed
```

`migrate:fresh` destroys all tables. Never use it on retained or production data.

For normal upgrades:

```powershell
php backend/artisan migrate --force
```

The CMS-1 intake-hardening migration performs a read-only preflight before
adding the AEMS `cms_recommendation_id` foreign key. If it reports orphaned
recommendation-to-CMS IDs, stop the deployment. Do not null, rewrite, or discard
those values. Resolve each named pointer through an approved data-correction
migration, take a new backup, and rerun the normal migration. Existing `OPEN`
intake rows are deterministically changed to `TRANSFERRED`; recoverable source
attributes are backfilled without fabricating unavailable historical values,
and each intake receives one case and one initial event.

The additive CMS-2A migration creates `cms_recommendation_assignments` and
PostgreSQL partial unique indexes for one current Compliance Monitor per case.
It does not rewrite intake, case, event, AEMS workflow, or retained assignment
data. After migration, run `RolePermissionSeeder` idempotently to register the
five granular CMS-2A permissions while preserving the six legacy CMS codes.

Run only safe idempotent reference seeders when required:

```powershell
cd backend
php artisan db:seed --class=MasterListSeeder --force
php artisan db:seed --class=RolePermissionSeeder --force
php artisan db:seed --class=SystemConfigurationSeeder --force
```

Review seeders before running them in production. Demo/user/content seeders may
change prototype records.

## 5. File storage

Private documents and evidence remain under Laravel storage and are streamed
through protected endpoints.

Runtime logos use the `public` disk. Create the public storage link once:

```powershell
cd backend
php artisan storage:link
```

Back up:

- PostgreSQL;
- `backend/storage/app` private files;
- `backend/storage/app/public` managed public assets;
- production `.env` and `APP_KEY` through a secure secret-management process.

## 6. Local run

Use two terminals from the repository root:

```powershell
npm.cmd run api
```

```powershell
npm.cmd run dev
```

Vite proxies `/api` and `/sanctum` to Laravel for same-origin Sanctum behavior.

## 7. Demo accounts

Demo accounts are available only when:

```dotenv
DEMO_ACCOUNTS_ENABLED=true
DEMO_DEFAULT_PASSWORD=lala
```

Disable them outside a controlled local/demo environment:

```dotenv
DEMO_ACCOUNTS_ENABLED=false
```

Do not rely on demo passwords in any shared deployment.

## 8. Runtime administration

Platform/AGIS administrators manage supported values in **System Configuration**.
Changes are validated, cached values are cleared, and runtime settings are
reapplied without a process restart.

After direct database maintenance, clear caches:

```powershell
cd backend
php artisan optimize:clear
```

Prefer the UI/API over direct database changes because it preserves encryption,
validation, Activity Log, and Audit Trail behavior.

## 9. SMTP setup

Configure:

- outbound email enabled;
- transport (`smtp` for delivery or `log` for local verification);
- host and port;
- encryption (`tls`, `ssl`, or none);
- username and password;
- sender address and name.

Save first, then use **Send configuration test**. The password is encrypted with
`APP_KEY`, never returned to the browser, and preserved when the secret field is
left blank.

Users must also enable their email notification preference. In-app notifications
remain the authoritative delivery channel.

## 10. Verification

Required checks before handoff:

```powershell
npm.cmd run lint
npm.cmd run build

cd backend
php artisan test --testsuite=Feature
php artisan route:list
php artisan migrate:status
```

After CMS-1 deployment, also verify that intake, case, and initial-event counts
match:

```powershell
php artisan tinker --execute="dump([
    'intakes' => App\Models\CmsRecommendation::count(),
    'cases' => App\Models\CmsRecommendationCase::count(),
    'events' => App\Models\CmsRecommendationEvent::where('event_code', 'INTAKE_CREATED')->count(),
]);"
```

Each valid intake must have one case and one `INTAKE_CREATED` event. Formally
excluded AEMS recommendations are intentionally absent from all three counts.

After CMS-2A deployment, verify the routes and assignment integrity:

```powershell
php artisan route:list --path=cms
php artisan tinker --execute="dump([
    'assignments' => App\Models\CmsRecommendationAssignment::count(),
    'currentAssignments' => App\Models\CmsRecommendationAssignment::current()->count(),
    'cmsPermissions' => App\Models\Permission::where('code', 'like', 'cms.%')->count(),
]);"
```

After CMS-3A, run the additive migrations
`2026_07_31_020000_create_cms_action_plan_tables` and
`2026_07_31_030000_enforce_cms_action_plan_active_slot`, then rerun
`RolePermissionSeeder`. The CMS permission count is 19 (six legacy, five
CMS-2A, and eight CMS-3A Action Plan codes).
No case may have more than one current `COMPLIANCE_MONITOR`.

Verify the controlled Action Plan foundation:

```powershell
php artisan migrate:status
php artisan route:list --path=cms
php artisan test --filter=CmsActionPlan
php artisan tinker --execute="dump([
    'families' => App\Models\CmsCorrectiveActionPlan::count(),
    'versions' => App\Models\CmsActionPlanVersion::count(),
    'milestones' => App\Models\CmsActionPlanMilestone::count(),
    'acceptedBaselines' => App\Models\CmsCorrectiveActionPlan::whereNotNull('accepted_version_id')->count(),
]);"
```

For each family, at most one version may have `active_slot = ACTIVE`; every
accepted pointer must reference a version in the same family. Do not use
`migrate:fresh` against retained data.

Smoke-test:

1. `GET /api/health`;
2. login/logout;
3. one allowed and one forbidden role action;
4. a document upload/download;
5. notification deep link;
6. System Configuration public branding refresh;
7. an IAP detail page and report export;
8. `/compliance-management/dashboard`, the server-driven Recommendation
   Registry, one authorized detail record, and one denied/out-of-scope detail
   request;
9. one authorized
   `/compliance-management/recommendations/{caseId}/action-plan` workspace,
   including draft milestone editing, submit/review/return or acceptance,
   immutable history, and one denied/out-of-scope request.

CMS frontend regression checks:

```powershell
npm.cmd run test:e2e -- cms-responsive.spec.js cms-action-plan.spec.js
```

The CMS-3B workspace uses the existing CMS-3A routes and schema; it requires no
additional migration or permission seeding.

After CMS-4A, run the additive migration
`2026_07_31_040000_create_cms_progress_update_tables`, then rerun
`RolePermissionSeeder`. CMS now has 31 permissions: the existing 19 plus eight
`cms.progress.*` and four `cms.evidence.*` codes.

Verify management-reported progress and private evidence:

```powershell
php artisan migrate:status
php artisan route:list --path=cms
php artisan test --filter=CmsProgress
php artisan test --filter=CmsActionPlanTest
php artisan test --filter=CmsRecommendationApiTest
php artisan tinker --execute="dump([
    'progressFamilies' => App\Models\CmsProgressUpdate::count(),
    'progressVersions' => App\Models\CmsProgressUpdateVersion::count(),
    'milestoneProgress' => App\Models\CmsMilestoneProgress::count(),
    'evidenceLinks' => App\Models\CmsProgressEvidenceLink::count(),
    'recordedVersions' => App\Models\CmsProgressUpdate::whereNotNull('recorded_version_id')->count(),
]);"
```

CMS progress evidence remains on the private `local` disk under the normal
Laravel storage backup boundary. Back up the database and private file storage
together. CMS-4A has no React deployment change.

After CMS-5A, run the additive migration
`2026_07_31_050000_create_cms_validation_tables`, then rerun
`RolePermissionSeeder`. CMS has 58 permissions: the prior 31 plus nine
`cms.validation.*` and four `cms.validation-evidence.*` codes. The migration
adds six validation tables and expands only the recommendation-case status
constraint; it does not rewrite intake, Action Plan, Progress Update, evidence,
extension, escalation, or closure data.

Verify the independent-validation backend:

```powershell
php artisan migrate:status
php artisan route:list --path=cms
php artisan test --filter=CmsValidation
php artisan test --filter=CmsProgressUpdateTest
php artisan tinker --execute="dump([
    'validationReviews' => App\Models\CmsValidationReview::count(),
    'validationVersions' => App\Models\CmsValidationVersion::count(),
    'finalizedValidations' => App\Models\CmsValidationVersion::where('status_code', 'FINALIZED')->count(),
    'currentValidatorAssignments' => App\Models\CmsValidationAssignment::where('is_current', true)->count(),
]);"
```

At most one review may have `active_slot = ACTIVE` per case; at most one
version may have `active_slot = ACTIVE` per review; and at most one assignment
may have `current_slot = CURRENT` per review. Validator evidence is private
Core storage and must be backed up with its matching database snapshot.
CMS-5B is a frontend deployment change only; it adds no migration or seed step.
CMS-6A and CMS-6B now provide the target-date extension backend and React
workspace. CMS-7A adds the additive escalation backend; CMS-7B React pages,
automatic escalation, closure, reopening, reports, AIS, and ARMIS remain
deferred.

After CMS-6A, run the additive migration
`2026_08_03_000000_create_cms_target_date_extension_tables`, then rerun
`RolePermissionSeeder`. The migration backfills immutable initial/effective
target-date history for existing CMS cases and adds extension request,
version, assessment, decision, evidence-link, and history tables. It does not
change original recommendation dates or case statuses. Back up the database
and private document storage together before applying it. No reminder,
escalation, or closure jobs are installed by CMS-6A.

CMS-6B is a frontend deployment change for the CMS-6A extension workspace. It
adds no migration, seed step, permission, or API endpoint. Run the normal
frontend lint, build, and focused Playwright suite after deployment. The
workspace continues to use authenticated CMS-6A endpoints and does not add
reminders, closure, reopening, reporting, AIS, or ARMIS behavior. CMS-7A
requires the additive `2026_08_04_000000_create_cms_escalation_tables`
migration and no seed reset; run the focused CMS escalation feature tests after
applying it. CMS-7B is a frontend-only deployment: it adds the protected
recommendation-scoped escalation routes, live CMS dashboard metrics, and
authenticated evidence controls. Verify with `npm.cmd run lint`,
`npm.cmd run build`, and the focused CMS Playwright specs when available. The
current backend resources do not compute `availableActions`, so Laravel 403
responses remain the final authority for professional controls.

CMS-8 through CMS-12 are now also deployed and verified: formal closure,
Accepted-Risk and No-Longer-Applicable dispositions, controlled reopening,
scheduled automation/candidates, and protected reports/CSV/PDF exports. The
older CMS-5B through CMS-7B notes above are historical deployment gates. ARMIS
is operational through ARMIS-7C; its provider authority remains a separate
reconciliation decision. AIS-5D is available as an integrated hardened
read-only analytical and protected reporting surface with per-source health
diagnostics, immutable integration snapshots, private responses, rate limits,
audit events, and fail-closed source reconciliation; operational AIS writes
remain disabled.

## 11. Production checklist

- `APP_ENV=production`;
- `APP_DEBUG=false`;
- strong unique database credentials;
- HTTPS and secure cookies;
- demo accounts disabled;
- production `APP_KEY` securely backed up;
- scheduled database/file backups;
- tested restore procedure;
- storage capacity and permissions checked;
- SMTP test passed;
- queue/scheduler configured when background jobs are enabled;
- web server routes unknown frontend paths to `index.html`;
- `/api` and `/sanctum` reach Laravel;
- writable Laravel cache/session/log/storage directories;
- migrations applied;
- lint, build, and feature tests passed;
- default demo passwords removed/reset.

## 12. Backup and restore

A valid backup set contains the database and the matching file-storage snapshot.
Restoring only one can leave document metadata without files or files without
records.

Recommended restore test:

1. provision an isolated database and storage directory;
2. restore PostgreSQL;
3. restore private/public storage;
4. use the same protected `APP_KEY`;
5. run `php artisan optimize:clear`;
6. verify login, a current document, a historical version, an IAP attachment,
   notifications, and reports;
7. document duration and failures.

## 13. Monitoring

Monitor:

- health endpoint and HTTP error rate;
- failed logins and account lockouts;
- database connections, query latency, locks, and disk;
- application logs and mail failures;
- private/public storage size;
- export volume;
- workflow deadlines and overdue notifications;
- backup completion and restore-test age;
- certificate and domain expiry.

## 14. Troubleshooting

### Login fails for every account

- confirm Laravel is running;
- confirm PostgreSQL connection values;
- run `php artisan optimize:clear`;
- inspect account active/archived/manual-lock state;
- verify `APP_KEY` and session domain/cookie settings.

### Frontend route works by click but fails on refresh

Configure the web server fallback to the built `index.html`. API and physical
asset paths must remain excluded from the SPA fallback.

### Uploaded logo does not display

- run `php artisan storage:link`;
- confirm `backend/public/storage` resolves to `storage/app/public`;
- check web-server access to the public link;
- reload runtime configuration.

### Document download returns 403

Confirm `documents.download`, the document confidentiality permission, account
status, and uploader/role policy.

### Workflow action says the record changed

Another session advanced the lock version. Refresh the record, re-evaluate the
latest state, and submit the action again.

### Email test fails

- enable outbound email;
- verify host/port/encryption;
- confirm firewall/network access;
- re-enter the SMTP secret if `APP_KEY` changed;
- use `log` transport to isolate application logic from network delivery.

## 15. Change-management rule

Every production change should include:

- reviewed migration impact and rollback limitations;
- safe backup;
- feature/permission tests;
- updated documentation;
- deployment steps;
- smoke-test result;
- named rollback/restore decision owner.

Frontend CMS-4B verification also includes:

```powershell
npm.cmd run lint
npm.cmd run build
npm.cmd run test:e2e
```

The focused suite `tests/e2e/cms-progress-updates.spec.js` uses mocked CMS-4A
responses for route, list, create, milestone, evidence, and management-reporting
boundary coverage. Full frontend regression should be run before release. The
browser workspace does not change database schema or migration requirements.

CMS-5B frontend verification additionally covers the independent-validation
routes, scoped validation-options selector data, read-only rendering of immutable versions, protected validator-evidence
downloads, lock-conflict reload messaging, and the explicit
implemented-versus-closed distinction. The frontend does not require a new
migration or seed step. Run the normal lint, production build, focused CMS
Playwright tests, and backend CMS validation regression tests before release.

The current CMS-5B regression verification completed successfully on 2026-07-31:

- `npm.cmd run lint` — passed.
- `npm.cmd run build` — passed.
- `npx playwright test tests/e2e/cms-validations.spec.js` — 10 passed across
  desktop and mobile projects, including the scoped-options permission denial.
- `npm.cmd run test:e2e` — 33 of 34 tests passed across all six configured
  specs and both browser projects (10.6 minutes). The final mobile validation
  test was blocked only by the existing demo-account sign-in throttle before
  the page loaded; rerunning that exact test after the throttle window passed
  (1 test, 18.1 seconds).
- `php artisan route:list --path=cms` — 45 CMS routes, including the scoped
  `validation-options` endpoint.
- `php artisan test --filter=CmsValidationTest` — 9 tests, 156 assertions.
- `php artisan test --filter=CmsRecommendationApiTest` — 7 tests, 74
  assertions.
- `php artisan test --testsuite=Feature` — 163 tests, 2,795 assertions.
- `git diff --check` — passed.
## CMS-8A deployment notes

Run the additive `2026_08_05_000000_create_cms_closure_tables` migration and reseed `RolePermissionSeeder` when deploying CMS-8A. The migration preserves existing CMS history and adds closure tables, case closure lineage, and the `FOR_CLOSURE`/`CLOSED` statuses. Verify permissions, PostgreSQL constraints, Core document access, and immutable decision records before enabling a future CMS-8B client.

CMS-8B verification includes `npm.cmd run lint`, `npm.cmd run build`, and the focused `tests/e2e/cms-closure.spec.js` browser checks. The full Playwright inventory should be run in deterministic groups when the local environment exceeds the command limit; a timeout is not a passing result.

CMS-8B stabilization gate (2026-08-03): the focused closure suite passed 6
desktop/mobile tests. The full Playwright command timed out after ten minutes;
deterministic groups covered all 52 discovered tests. AEMS (6), IAP (2), CMS
action-plan (10), CMS closure (6), CMS responsive (2), and the remaining CMS
groups (26) were run separately. One legacy validation test was throttled before
page load; the account remained active and unlocked, so the login throttle was
not bypassed; the exact test passed independently after the cooldown. A stale
AGIS Vite process on port 5173 caused the earlier web
server timeout; Laravel health on port 8000 was healthy, the verified repo Vite
process was restarted, and the focused suite then started normally.

Backend gate checks confirm migrations through
`2026_08_05_000000_create_cms_closure_tables`, 102 CMS routes, CMS regression
coverage of 47 tests/644 assertions, and AEMS coverage of 38 tests/723
assertions. The complete Feature suite remains a local timeout; deterministic
groups are the reproducible record. `npm.cmd run lint`, `npm.cmd run build`, and
`git diff --check` pass.

## CMS-9A deployment and verification

CMS-9A is an additive backend migration. Deploy
`2026_08_06_000000_create_cms_disposition_tables`, then reseed
`RolePermissionSeeder`; do not remove legacy permissions or alter existing CMS
tables. Verify the PostgreSQL case-status constraint includes
`FOR_DISPOSITION`, `ACCEPTED_RISK`, and `NO_LONGER_APPLICABLE`.

The focused gate is:

```powershell
php artisan test --filter="accepted_risk_disposition|no_longer_applicable"
php artisan test --filter=CmsValidationTest
php artisan test --filter=CmsClosureTest
php artisan route:list --path=disposition
```

CMS-9A has no frontend migration or AIS/ARMIS dependency. The React workspace,
controlled reopening, reminders/automation, reports, and protected exports
remain gated for later phases.

CMS-9B verification adds:

```powershell
npm.cmd run lint
npm.cmd run build
npx.cmd playwright test tests/e2e/cms-dispositions.spec.js
```

The focused browser suite covers desktop and mobile routes, empty/readiness
states, creation, type-specific fields, terminal presentation, and safe
no-reopening behavior. A login-throttle failure must be rerun independently
after cooldown and reported separately; authentication throttling must not be
weakened.

## CMS-10B controlled reopening workspace

CMS-10B adds no infrastructure or deployment service. It is delivered through
the existing same-origin React build and authenticated `/api` routes. The
recommendation-scoped entry points are:

```text
/compliance-management/recommendations/:recommendationId/reopening-requests
/compliance-management/recommendations/:recommendationId/reopening-requests/:reopeningRequestId
```

The frontend is a protected presentation and workflow client for CMS-10A. It
uses backend readiness and `availableActions`, preserves optimistic lock
versions, and never changes a case status directly. Exact Core Document
Version evidence is linked and downloaded through protected endpoints; no
storage paths or public document URLs are exposed. After approval, the UI shows
the new active cycle and approved destination while retaining the immutable
historical terminal decision. Rejection leaves the terminal status unchanged.

Playwright starts Laravel from `backend/` and Vite from the repository root.
The default readiness URLs are `http://127.0.0.1:8000/health` and
`http://127.0.0.1:5173/login`. If those ports are occupied by another local
application, use explicit overrides without terminating the unrelated process:

```powershell
$env:PLAYWRIGHT_BACKEND_PORT = "8010"
$env:PLAYWRIGHT_FRONTEND_PORT = "5174"
npx.cmd playwright test tests/e2e/cms-reopening.spec.js
```

The harness passes the selected backend port to Vite through
`VITE_API_PROXY_TARGET` and adds the selected frontend port to
`SANCTUM_STATEFUL_DOMAINS`, preserving same-origin session behavior.

Operational verification for CMS-10B:

```powershell
npm.cmd run lint
npm.cmd run build
php artisan test tests/Feature/CmsReopeningTest.php
npx.cmd playwright test tests/e2e/cms-reopening.spec.js --project=desktop-chrome
```

The Playwright spec uses mocked CMS-10A responses for frontend contract checks.
If the local Laravel/Vite web server cannot start, report that as an
environment failure rather than a passing browser test. At the CMS-10B
checkpoint, CMS-11 automation and CMS-12 reports/exports were later phases;
they are now deployed and verified in the sections below. AIS is not
implemented, and ARMIS is documented separately.

CMS-10B verification gate (2026-08-04): the focused reopening specification
completed all 6 tests across desktop and mobile after one mobile login-throttle
rerun. The complete inventory contained 68 tests in 11 specifications and two
projects. Its aggregate command exceeded the local execution window, so
deterministic desktop and mobile groups were run instead: desktop 34/34 and
mobile 34/34 passed, including independent reruns of throttle-affected tests.
The backend Feature suite passed 174 tests and 2,928 assertions. No Render
infrastructure files were changed.
## Current module operations status

The normal application startup applies additive migrations, runs approved
seeders, caches Laravel configuration/views, and serves the compiled React SPA.
The current operational modules are Core, IAP, AEMS, CMS through CMS-12B, ARMIS
through ARMIS-7C, and AIS through AIS-5D. AFR is a navigation compatibility
entry owned operationally by AEMS. AIS is read-only and must not mutate source
modules. CMS and AEMS professional decisions remain human-controlled; scheduled automation only produces reminders
or reviewable candidates. ARMIS provider authority is never changed by startup,
the scheduler, or a generic configuration write.

Use the focused gates in this guide after a targeted deployment, then run the
complete Feature suite, frontend lint/build, and applicable desktop/mobile
browser specifications before sign-off. Render-specific preflight and smoke
checks are read-only and are described in [Render Deployment](RENDER_DEPLOYMENT.md).

Latest repository verification baseline: `php artisan test --testsuite=Feature`
passes 220 tests with 3,575 assertions; `php artisan test --filter=Armis`
passes 38 tests with 570 assertions; `npm.cmd run lint`, `npm.cmd run build`, and
`git diff --check` pass. Browser specifications remain environment-dependent
and must be run against the appropriate local or deployed service.

## Render Free deployment operations

The Render Free image is a same-origin demonstration deployment. Follow
[`RENDER_DEPLOYMENT.md`](RENDER_DEPLOYMENT.md) for the Docker image, Render
Dashboard settings, environment variables, and first-administrator bootstrap.

Important operational limits:

- The web service has no persistent disk.
- `FILESYSTEM_DISK=local` evidence uploads are temporary and may disappear on
  restart, redeploy, or spin-down.
- Evidence remains private and protected; do not create `public/storage` or
  public download URLs.
- Queues run synchronously with `QUEUE_CONNECTION=sync`; no worker or scheduled
  automation service is part of the Free deployment.
- Run production seeders only with the explicit
  `RUN_PRODUCTION_SEEDERS=true` flag, then disable it after initialization.
- For a controlled Render demonstration dataset, also set
  `RUN_FULL_DEMO_SEEDERS=true`, `DEMO_ACCOUNTS_ENABLED=true`, and an explicit
  temporary `DEMO_DEFAULT_PASSWORD`. Disable all three after verification.
- The Render demo runner is idempotent and excludes AEMS/CMS operational
  records and physical document/evidence fixture files; it does not call the
  destructive local `DatabaseSeeder` path.
- Remove all `BOOTSTRAP_ADMIN_*` variables immediately after the first secure
  administrator is created.

The unauthenticated `GET /health` endpoint returns safe JSON and performs a
lightweight database check. Render should use `/health` as its health-check
path. A failing database check returns HTTP 503 without exposing credentials or
debug details.

## CMS-11A automation operations

Run the normal scheduler/queue process after applying migrations and seeders.
The daily `notifications:dispatch-reminders` command also executes active CMS
automation rules. A manual run is available to users with
`cms.automation.run`; it is idempotent for the rule and business date. Review
users need `cms.automation.review` and may acknowledge or dismiss candidates,
but cannot turn a candidate into a closure, disposition, reopening, or
escalation notice. Inspect `/api/cms/automation/dashboard`, `/runs`, and
`/candidates` when monitoring results. CMS-11B supplies the administrative UI;
CMS-12 supplies reports and protected exports.

## CMS-11B workspace verification

Use an account with `cms.automation.view` to open the Automation & Candidate
Review page. Confirm the summary cards, Automation rules, Candidate review,
and Run history tabs. With the appropriate additional permissions, verify
that rule editing, manual execution, acknowledgement, and dismissal controls
appear only for authorized users. Candidate review must leave the
recommendation case status unchanged. The focused desktop and mobile browser
spec is `tests/e2e/cms-automation.spec.js`.

CMS-11B stabilization gate (2026-08-10): the complete CMS browser regression
completed 66/66 tests across desktop and mobile projects. Playwright starts the
Laravel server with `APP_ENV=testing`, which uses a high test-only login-rate
limit so isolated browser contexts can exercise the real sign-in flow. The
production limiter, account lock rules, and authentication behavior are
unchanged. The gate also passed the Feature suite (177 tests, 2,952
assertions), frontend lint, and the production build.

## CMS-12A backend verification

Apply migration `2026_08_10_000000_create_cms_report_tables` and rerun
`RolePermissionSeeder`. Verify the protected CMS report catalog and report
codes with an account that has `cms.report.view`. Generate a run, confirm its
scope snapshot, ordered rows, source-query version, and checksum, then create
both CSV and PDF exports with `cms.report.export`. Confirm CSV formula
neutralization, private storage, export version/checksum/size metadata,
authenticated downloads, and scope/confidentiality revalidation. The focused
backend specification is `tests/Feature/CmsReportTest.php`. The protected
React workspace is `/compliance-management/reports`; its desktop/mobile
contract is `tests/e2e/cms-reports.spec.js`. CMS-12B is now complete.

## ARMIS-7B deployment and migration hardening

ARMIS-7B adds a read-only deployment preflight without adding another business
migration. The normal Render startup continues to run `php artisan migrate
--force`; the eight ARMIS migrations through provider monitoring are expected
to be recorded before the strict gate runs. Set `ARMIS_DEPLOYMENT_CHECK=true`
on the Render web service to run:

```text
php artisan armis:deployment-check --strict
```

The command blocks startup when PostgreSQL, all ARMIS migrations, the provider
authority decision lineage, `APP_KEY`, HTTPS `APP_URL`, private storage, debug
settings, writable runtime directories, or Laravel configuration caching are
not ready. It never updates runtime configuration, provider authority, ARMIS
records, or migration state.

The Render image keeps evidence and report files on the private local disk,
does not create `public/storage`, and serves files only through authenticated
controllers. Apache emits baseline browser security headers. Render Free
storage remains ephemeral; use a durable private object store before
operational retention. Local Docker startup leaves the strict gate disabled by
default so SQLite/HTTP development checks remain available.

## ARMIS-7C post-deployment smoke verification

After each Render deployment, run the read-only PowerShell smoke gate from the
repository root:

```powershell
./scripts/verify-armis-render.ps1 -BaseUrl https://<service>.onrender.com
```

The script confirms the health response, compiled React shell, nested ARMIS
SPA fallback, anonymous ARMIS API rejection, and baseline browser security
headers. It requires HTTPS and returns a non-zero exit code on failure. It
does not sign in, upload, mutate records, run migrations, or change provider
authority.

## AEMS-G1 professional-control migration

Deploy the additive
`2026_08_28_000000_harden_aems_professional_controls` migration with the
normal `php artisan migrate --force` command. It adds direct-Finding authority
provenance columns and restores the current-revision Finding index on SQLite
after the schema rebuild. No new permission seeder is required; existing
`aems.*` permissions and compatibility aliases are unchanged. Verify the
focused AEMS Evidence, Finding, and lifecycle tests after deployment.
