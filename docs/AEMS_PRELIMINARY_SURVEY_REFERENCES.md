# AEMS planning reference implementation

The 23 JPGs in `reference/images` describe the planning workspace and Preliminary Survey. They are implemented as live forms and tables, using the engagement's real data. Sample names, dates, completion counts, and illustrative assessment responses in the JPGs are not seeded into engagements.

## Screen coverage

| Reference screen | Implementation |
| --- | --- |
| 210-01 | Eight planning activities, initial guidance panel, engagement summary, progress, four stages, Activity Log |
| 210-02 | Preliminary Survey with links to its four sections |
| 210-02-1.1 | Preliminary Meeting: proposed/actual dates, venue, focal person, participants, agenda, information needed, discussion, notice upload and Word template |
| 210-02-1.2 | Auditee mandate, mission, vision, objectives, strategic goals, five budgets, annual budget attachment, program/project review table |
| 210-02-1.2.1 | Program/project add, edit, view and remove |
| 210-02-1.3 | Six PPSP accordion panels |
| 210-02-1.3A | Eight activity profile/purpose fields |
| 210-02-1.3B and 1.3B.1 | Operating-step table and activity/decision editor |
| 210-02-1.3C and 1.3C.1 | Sources consulted table and editor |
| 210-02-1.3D and 1.3D.1 | Information gaps/document requests table and editor |
| 210-02-1.3E | Private process flowchart upload/download |
| 210-02-1.3F and 1.3F.1 | Risk/control matrix and two-column editor, including likelihood, impact and server-calculated score |
| 210-02-1.4 | Five ICS component accordions and calculated completion count |
| 210-02-1.4.1 | ICS assessment view/edit form with fixed principle and question |
| 210-02-1.4A | Control Environment, principles 1–5 |
| 210-02-1.4B | Risk Assessment, principles 6–8 |
| 210-02-1.4C | Control Activities, principles 9–11 |
| 210-02-1.4D | Information and Communication, principles 12–14 |
| 210-02-1.4E | Monitoring, principles 15–16 |

Open an engagement → Planning → Preliminary Survey → choose 1.1, 1.2, 1.3 or 1.4. Direct URLs use `/audit-engagement-management/preliminary-survey?engagementId=ID&section=meeting|auditee|ppsp|ics` with an optional `panel` query parameter. The other seven planning activities open the existing relevant AEP, package, KPI, review or entrance-conference workspace.

## Backend analysis and storage

The existing backend has an engagement aggregate, immutable planning-package versions, formal risk matrices, review/approval permissions and Core document versions. Preliminary Survey previously exposed seven broad narrative fields. The reference images require structured meeting, auditee, PPSP and ICS data that did not have corresponding forms or section endpoints.

The new structured draft is stored at `aems_planning_package_versions.preliminary_survey.workspace` under `meeting`, `auditee`, `ppsp` and `ics`. This uses the existing JSON column. **No new migration is required for this implementation.** Legacy survey narratives and the rest of the package remain available.

Each section save creates an immutable planning-package version through `AemsPlanningPackageService`. It copies objectives, flows, risk links, KPIs and working-paper requirements before replacing only the selected survey section. The package lock version prevents stale writes. Saving preliminary survey observations does not approve a package, mark a stage complete, or replace the separately governed formal risk matrix. Existing draft/returned restrictions, engagement access and approval rules still apply.

The existing package's survey-readiness check also accepts completed structured surveys: actual meeting/discussion, auditee mandate/objectives, PPSP purpose/scope and operating steps/sources/risks, plus a preliminary view and basis for each of the 16 ICS principles. Legacy narrative completion remains supported. All other formal planning readiness checks still apply.

Attachments use Core `documents`, immutable `document_versions`, and engagement-scoped `document_links` with record type `AEMS_PRELIMINARY_SURVEY`. Files remain on Laravel's private `local` disk, outside the public asset library. Uploads accept PDF/DOC/DOCX up to 10 MB; downloads and saved file references are checked against the current engagement. Retain or mount persistent backend storage when deploying.

New endpoints under `/api/aems/engagements/{engagement}`:

- `GET preliminary-survey`: structured survey, edit capability, package status, lock version, attachments.
- `PUT preliminary-survey`: `{ section, lockVersion, data }`; saves one section and returns refreshed data.
- `POST preliminary-survey/attachments`: multipart `file`.
- `GET preliminary-survey/attachments/{version}`: authorized attachment download.
- `GET preliminary-survey/attachments/template`: generated preliminary-meeting Word template.

The ICS editor keeps all 16 principle identities fixed. Later reference images repeat Control Environment checklist questions in other components; the implementation uses questions relevant to each named principle instead. Assessment answers are blank until entered. Completion counts are computed from recorded fields, rather than the illustrative counts shown in the images.

## Verification

- Backend: `php artisan test --filter=AemsPlanningPackageTest` (includes section round trips, immutable history, graph preservation, stale writes, validation, locked stages and private attachments).
- Frontend: `npm run lint` and `npm run build`.
- Desktop/mobile: `npx playwright test --config=playwright.survey.config.js`. Uses mocked API data and a dedicated Vite port; it does not write into the local or Supabase database. Captures all reference accordion/editor states under the ignored `frontend/test-results/` directory.

Deploy both backend and frontend source/build changes. Refresh Laravel's route cache using the deployment's normal `php artisan optimize:clear`/cache process. Earlier database migrations, including audit-type changes, remain separate deployment prerequisites.
