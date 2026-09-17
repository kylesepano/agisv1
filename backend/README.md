# AGIS Laravel API

This directory contains the Laravel 12/PostgreSQL backend for the Audit
Governance Information System.

Start with the project documentation:

- [Documentation index](../docs/README.md)
- [System flow](../docs/SYSTEM_FLOW.md)
- [AGIS Core workflow](../docs/CORE_WORKFLOW_DESIGN.md)
- [IAP workflow](../docs/IAP_WORKFLOW_DESIGN.md)
- [API and data reference](../docs/API_AND_DATA_REFERENCE.md)
- [Operations guide](../docs/OPERATIONS_GUIDE.md)

Common commands:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
php artisan migrate
php artisan test --testsuite=Feature
php artisan route:list
```

The backend is the authorization and business-rule boundary. React visibility
checks do not replace Laravel authentication, permission middleware, scope
services, validation, transactions, locks, Activity Log, or Audit Trail.
