# Vercel, Render, and Supabase deployment

AGIS is a three-service deployment:

```text
Browser -> Vercel (frontend/) -> Render (backend/) -> Supabase PostgreSQL
```

The repository-level `render.yaml` and `frontend/vercel.json` contain the
platform configuration. Secrets and deployment hostnames must be supplied in
the platform dashboards and must never be committed.

## Authentication domain requirement

AGIS uses Laravel Sanctum's cookie-based SPA authentication. For reliable
production authentication, give Vercel and Render sibling HTTPS custom domains
under one registrable domain, for example:

```text
https://app.example.gov     Vercel
https://api.example.gov     Render
```

Raw `*.vercel.app` and `*.onrender.com` hostnames are different browser sites.
Although cross-origin requests can be enabled, browsers may reject their
authentication cookies as third-party cookies. A shared custom parent domain
avoids that failure mode.

## 1. Supabase PostgreSQL

1. Create a Supabase project in the required region.
2. In **Connect**, copy the **Session pooler** connection URI. The pooler is the
   safer choice when the Render runtime cannot use Supabase's direct IPv6
   endpoint. Use the exact URI Supabase provides and percent-encode reserved
   characters in the database password.
3. Store that URI as Render's `DATABASE_URL`; do not commit it.
4. Keep `DB_CONNECTION=pgsql` and `DB_SSLMODE=require`.

The backend container applies pending migrations on startup. Existing
production data is not dropped. Use a dedicated production project, enable
Supabase backups appropriate to the data-retention policy, and test restoration
before treating AGIS as an operational records system.

## 2. Render API

Create a Render Blueprint from the repository. `render.yaml` selects:

- change-detection root: `backend`
- runtime: Docker
- Dockerfile and Docker build context: `backend/`
- health check: `/health`

Set the following secret or deployment-specific values in Render:

```dotenv
APP_KEY=<output of php artisan key:generate --show>
APP_URL=https://api.example.gov
FRONTEND_URL=https://app.example.gov
DATABASE_URL=<Supabase Session pooler URI>
CORS_ALLOWED_ORIGINS=https://app.example.gov
SANCTUM_STATEFUL_DOMAINS=app.example.gov
SESSION_DOMAIN=.example.gov
```

`SANCTUM_STATEFUL_DOMAINS` must not contain a scheme. `CORS_ALLOWED_ORIGINS`
must contain the scheme and must exactly match the browser origin. Keep
`SESSION_SECURE_COOKIE=true` and `SESSION_SAME_SITE=lax` when both services use
HTTPS sibling domains.

The blueprint intentionally disables demo accounts and the full demo seeder.
`RUN_PRODUCTION_SEEDERS=true` installs idempotent roles, permissions, reference
data, and workflows. Review this setting before the first production start.

Render's local filesystem is ephemeral. Evidence and generated report files
stored with `FILESYSTEM_DISK=local` can disappear after a restart or redeploy.
Configure a private, durable object store before production retention; never
make protected evidence publicly addressable.

## 3. Vercel frontend

Import the same repository into Vercel and set:

```text
Root Directory: frontend
Framework Preset: Vite
Build Command: npm run build
Output Directory: dist
```

Add this production environment variable and redeploy:

```dotenv
VITE_API_BASE_URL=https://api.example.gov/api
```

The value must include `/api` and must not end in `/`. Vite embeds it at build
time, so changing it requires a new frontend deployment. `frontend/vercel.json`
provides the React Router fallback for refreshed nested routes.

## 4. Verification

Before deployment, from the repository root:

```powershell
cd frontend
npm ci
npm run lint
npm run build
npm run test:api
```

After deployment, verify:

1. `https://api.example.gov/health` reports a healthy database connection.
2. `https://app.example.gov/login` loads, including after a hard refresh.
3. The browser's `/sanctum/csrf-cookie` request goes to the API domain and
   returns the XSRF cookie for the shared parent domain.
4. Login, logout, a protected JSON request, and a protected file download work.
5. An unauthorized user cannot open a protected route or API endpoint.

The read-only ARMIS smoke check remains available:

```powershell
./scripts/verify-armis-render.ps1 -BaseUrl https://api.example.gov
```

## Local development

Copy `backend/.env.example` to `backend/.env`, configure a local PostgreSQL
database, then run these in separate terminals:

```powershell
cd frontend
npm run api
```

```powershell
cd frontend
npm run dev
```

Vite proxies `/api` and `/sanctum` to Laravel, so no production hostname is
required locally.
