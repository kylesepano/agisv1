[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidatePattern('^https://')]
    [string] $BaseUrl
)

$ErrorActionPreference = 'Stop'
$BaseUrl = $BaseUrl.TrimEnd('/')
$failures = 0

function Invoke-CheckedRequest {
    param(
        [Parameter(Mandatory = $true)]
        [string] $Uri,
        [int[]] $ExpectedStatus = @(200),
        [switch] $AllowErrorResponse
    )

    try {
        $response = Invoke-WebRequest -Uri $Uri -UseBasicParsing -ErrorAction Stop
        $status = [int] $response.StatusCode
    } catch {
        if (-not $AllowErrorResponse -or -not $_.Exception.Response) {
            throw
        }

        $status = [int] $_.Exception.Response.StatusCode
        $response = $_.Exception.Response
    }

    if ($ExpectedStatus -notcontains $status) {
        throw "Expected HTTP $($ExpectedStatus -join ' or '), received HTTP $status for $Uri."
    }

    return $response
}

function Test-Check {
    param(
        [Parameter(Mandatory = $true)]
        [string] $Name,
        [Parameter(Mandatory = $true)]
        [scriptblock] $Action
    )

    try {
        & $Action
        Write-Host "PASS  $Name" -ForegroundColor Green
    } catch {
        $script:failures++
        Write-Host "FAIL  ${Name}: $($_.Exception.Message)" -ForegroundColor Red
    }
}

Test-Check 'Render health endpoint' {
    $response = Invoke-CheckedRequest -Uri "$BaseUrl/health"
    $payload = $response.Content | ConvertFrom-Json
    if ($payload.success -ne $true -or $payload.data.status -ne 'healthy') {
        throw 'The health response is not healthy.'
    }
}

Test-Check 'Compiled SPA root' {
    $response = Invoke-CheckedRequest -Uri "$BaseUrl/"
    if ($response.Content -notmatch 'id="root"' -or $response.Content -notmatch 'AGIS') {
        throw 'The compiled AGIS shell was not returned.'
    }
}

Test-Check 'Nested SPA fallback' {
    $response = Invoke-CheckedRequest -Uri "$BaseUrl/audit-resource-management/provider-monitoring"
    if ($response.Content -notmatch 'id="root"') {
        throw 'The nested React route did not return the compiled shell.'
    }
}

Test-Check 'ARMIS API rejects anonymous access' {
    $null = Invoke-CheckedRequest -Uri "$BaseUrl/api/armis/provider/status" -ExpectedStatus @(401) -AllowErrorResponse
}

Test-Check 'Security headers are present' {
    $response = Invoke-CheckedRequest -Uri "$BaseUrl/health"
    $headers = $response.Headers
    foreach ($header in @('X-Content-Type-Options', 'X-Frame-Options', 'Referrer-Policy', 'Permissions-Policy')) {
        if (-not $headers[$header]) {
            throw "Missing $header response header."
        }
    }
}

if ($failures -gt 0) {
    Write-Host "$failures Render verification check(s) failed." -ForegroundColor Red
    exit 1
}

Write-Host 'ARMIS Render smoke verification completed successfully.' -ForegroundColor Green
exit 0
