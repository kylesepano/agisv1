# AGIS Development Standards

These rules apply to every module and workflow added to AGIS.

## Required order of work

1. Document actors, permissions, business rules, statuses, approvals, reports, and retention needs.
2. Design normalized tables, foreign keys, unique constraints, indexes, soft deletion, and audit history.
3. Implement authentication and action-specific backend authorization.
4. Add backend validation and database constraints, then matching frontend feedback.
5. Add accessible, responsive, understandable user interfaces.
6. Add feature, permission, integration, and regression tests.
7. Review queries, pagination, caching, queues, backups, deployment, and monitoring.

## Module source organization

- Keep frontend pages under `src/pages/<module>/` and backend models under
  `backend/app/Models/<Module>/` using the module names Core, Iap, Aems, Cms,
  Armis, and Ais.
- Keep backend services under `backend/app/Services/<Module>/`. Shared platform
  services belong in `backend/app/Services/Core/`.
- Existing model and service classes retain their established flat
  `App\Models` and `App\Services` namespaces for compatibility. The Composer
  PSR-4 map is responsible for resolving the module directories; do not change
  namespaces or imports solely to move a file.
- New classes should follow the module folder convention and update the
  as-built catalog when a new module surface is introduced.

## Security and privacy

- React must never connect directly to PostgreSQL or decide authorization by itself.
- Laravel middleware, policies, or gates must verify every protected action.
- Store only hashed passwords and use secure, HTTP-only session cookies.
- Keep CSRF protection, login throttling, lockout, input validation, parameter binding, HTTPS, and audit logging enabled.
- Validate file content, MIME type, size, filename, storage location, and download permission.
- Do not expose secrets or personal data through URLs, logs, errors, exports, or browser storage.
- Collect only necessary personal data and define retention, correction, access, and deletion rules consistent with the Philippine Data Privacy Act.

## Data integrity and concurrency

- Use transactions for multi-record workflows and approvals.
- Prevent duplicates with database constraints, not only form checks.
- Use version columns or row locking where simultaneous edits could overwrite work or double-approve a record.
- Keep previous/new values, actor, timestamp, IP address, user agent, and related record in audit history.

## Code comments and documentation

- Add comments where authorization, scope, state transitions, concurrency,
  encryption, file cleanup, fallback behavior, or cross-module lineage is not
  obvious from the code.
- Explain why a rule exists and which invariant it protects; do not restate a
  straightforward assignment or method name.
- Use PHPDoc/JSDoc for public services, non-trivial payloads, and reusable
  helpers.
- Keep workflow statuses, transition rules, permissions, runtime settings,
  endpoints, and data relationships synchronized with the documents in `docs/`.
- Remove or update stale comments in the same change that modifies behavior.

## User experience and accessibility

- Provide readable text, sufficient contrast, clear labels, visible focus, keyboard navigation, alt text, useful validation, empty states, loading states, and delete confirmations.
- Do not rely on color alone for status.
- Add search, sorting, filtering, and server-side pagination to record lists.
- Test tables, forms, navigation, dashboards, and dialogs on desktop, laptop, tablet, and phone layouts.

## Reliability and operations

- Show safe user-facing errors while logging technical details on the server.
- Plan encrypted, retained, off-site backups for PostgreSQL and uploaded files and test restoration.
- Separate local, testing, staging, and production configuration; never run production with `APP_DEBUG=true`.
- Monitor uptime, latency, errors, database performance, disk use, queue failures, scheduled tasks, certificates, and suspicious sign-ins.
- Optimize queries and indexes before adding infrastructure. Use caching, Redis, queues, object storage, or load balancing only when justified.

## Definition of done for a module

- Workflow and permission matrix approved
- Migration, constraints, indexes, relationships, and seed/reference data included
- Request validation and policies/middleware included
- List/detail/create/edit workflow with loading, empty, success, and error states included
- Audit events included
- Feature and permission tests passing
- Responsive and keyboard checks completed
- API and operating documentation updated
- Workflow design, system-flow impact, and code comments updated
