param(
    [switch] $SkipFrontend,
    [switch] $SkipPlaywright,
    [switch] $UseConfiguredDatabase
)

$ErrorActionPreference = 'Stop'
$repositoryRoot = Split-Path -Parent $PSScriptRoot
$backendRoot = Join-Path $repositoryRoot 'backend'

Push-Location $backendRoot
try {
    if (-not $UseConfiguredDatabase) {
        # Keep the default rehearsal isolated from a developer's configured
        # PostgreSQL database. Pass -UseConfiguredDatabase only when an
        # explicitly disposable database has been provisioned.
        $env:DB_CONNECTION = 'sqlite'
        $env:DB_DATABASE = ':memory:'
    }
    Write-Host 'Rehearsing the AEMS migration chain on a fresh seeded schema...'
    php artisan migrate:fresh --seed --force --quiet
    php artisan test tests/Feature/Api/AemsG9ConformanceTest.php tests/Feature/Api/AemsG10EAcceptanceTest.php
}
finally {
    Pop-Location
}

if (-not $SkipFrontend) {
    Push-Location $repositoryRoot
    try {
        npm.cmd run lint
        npm.cmd run build
    }
    finally {
        Pop-Location
    }
}

if (-not $SkipPlaywright) {
    Push-Location $repositoryRoot
    try {
        npx.cmd playwright test tests/e2e/aems-g9-conformance.spec.js tests/e2e/aems-g10e-final-acceptance.spec.js --project desktop-chrome --project mobile-chrome
    }
    finally {
        Pop-Location
    }
}
