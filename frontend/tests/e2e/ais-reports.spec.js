import { expect, test } from "@playwright/test";

async function signIn(page) {
  await page.goto("/login");
  await page.getByLabel("Employee ID").fill("CIAS-HEAD-001");
  await page.locator("#password").fill("lala");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/dashboard$/);
}

function contract() {
  return {
    contractVersion: "AIS-5D.0",
    status: "READ_ONLY_VERIFIED",
    enabled: false,
    readOnlyDashboardEnabled: true,
    readOnlyReportsEnabled: true,
    protectedExportsEnabled: true,
    scope: { office: "ALL", engagement: "ALL", confidentiality: { confidential: true, restricted: true } },
    sourceModules: [],
    professionalControls: { noOperationalWrites: true, noProfessionalDecisions: true, sourceScopeRechecked: true, confidentialityRechecked: true },
    hardening: {
      status: "ENFORCED",
      checks: { authRequired: true, protectedDownloads: true, immutableReportRuns: true, diagnosticsRedacted: true, namedRateLimits: true },
      rateLimits: { readPerMinute: 120, generatePerMinute: 12, exportPerMinute: 20 },
    },
    plannedCapabilities: [{ code: "AIS-5D", label: "Integration verification", status: "IMPLEMENTED" }],
  };
}

function dashboard() {
  return {
    dashboardVersion: "AIS-2.0",
    contractVersion: "AIS-1.0",
    generatedAt: "2026-08-14T08:00:00Z",
    scope: { officeScope: "ALL", engagementScope: "ALL" },
    sourceModes: [],
    metrics: { core: { offices: 2, activeUsers: 6 }, aems: { activeEngagements: 1 }, cms: { openCases: 1, overdueCases: 0 }, armis: { plannedPersonDays: 4, approvedAssignments: 1 } },
    headline: { approvedIapPlans: 1, activeEngagements: 1, findingsAwaitingReview: 0, findingsAwaitingResponse: 0, evidenceAwaitingAssessment: 0, openCmsCases: 1, overdueCmsCases: 0, approvedArmisAssignments: 1, plannedPersonDays: 4 },
    distributions: { engagementStatuses: [], findingStatuses: [], evidenceOutcomes: [], cmsStatuses: [], armisResourceStatuses: [] },
    snapshotTrend: [],
    attention: [],
    latestSnapshot: null,
    limitations: {},
  };
}

function reportRun(overrides = {}) {
  return {
    id: 41,
    displayCode: "AIS-RPT-000041",
    reportCode: "portfolio-overview",
    title: "AIS Portfolio Overview",
    sourceQueryVersion: "AIS-3-v1",
    generatedAt: "2026-08-14T08:00:00Z",
    rowCount: 1,
    resultSnapshot: { columns: [{ key: "indicator", label: "Indicator" }], rows: [{ indicator: "Active engagements" }] },
    exports: [],
    ...overrides,
  };
}

test("AIS dashboard exposes hardened status and consistent sidebar navigation", async ({ page }) => {
  await signIn(page);
  await page.route(/\/api\/ais\/contract$/, (route) => route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ success: true, data: contract() }) }));
  await page.route(/\/api\/ais\/dashboard$/, (route) => route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ success: true, data: dashboard() }) }));

  await page.goto("/audit-intelligence-system");

  await expect(page.getByRole("heading", { name: "Audit Intelligence System", exact: true }).last()).toBeVisible();
  await expect(page.getByText("Deployment hardening", { exact: true })).toBeVisible();
  await expect(page.getByText("ENFORCED", { exact: true })).toBeVisible();
  await expect(page.getByText("120 reads/min", { exact: false })).toBeVisible();
  await expect(page.getByRole("complementary").getByRole("button", { name: /Expand|Collapse Audit Intelligence System/ })).toBeVisible();
});

test("AIS report export is generated and downloaded through the protected endpoint", async ({ page }) => {
  await signIn(page);
  let downloadRequested = false;
  await page.route(/\/api\/ais\/reports$/, (route) => route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ success: true, data: { reports: [{ code: "portfolio-overview", title: "AIS Portfolio Overview", description: "Scope-aware portfolio indicators.", columns: [{ key: "indicator", label: "Indicator" }] }], canExport: true } } ) }));
  await page.route(/\/api\/ais\/reports\/runs$/, (route) => route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ success: true, data: { runs: [] } }) }));
  await page.route(/\/api\/ais\/alerts$/, (route) => route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ success: true, data: { alerts: [] } }) }));
  await page.route(/\/api\/ais\/reports\/portfolio-overview\/generate$/, (route) => route.fulfill({ status: 201, contentType: "application/json", body: JSON.stringify({ success: true, data: { run: reportRun() } }) }));
  await page.route(/\/api\/ais\/reports\/runs\/41\/exports$/, (route) => route.fulfill({ status: 201, contentType: "application/json", body: JSON.stringify({ success: true, data: { export: { id: 71, runId: 41, format: "CSV", versionNumber: 1, fileName: "ais-portfolio-v1.csv", mimeType: "text/csv", fileSize: 80, checksumSha256: "b".repeat(64) } } }) }));
  await page.route(/\/api\/ais\/report-exports\/71\/download$/, async (route) => { downloadRequested = true; await route.fulfill({ status: 200, contentType: "text/csv", body: "Indicator\nActive engagements\n" }); });

  await page.goto("/audit-intelligence-system/reports");
  await expect(page.getByRole("heading", { name: "AIS Reports and Protected Exports", exact: true })).toBeVisible();
  await page.getByRole("button", { name: "Generate report" }).click();
  await expect(page.getByText("AIS-RPT-000041", { exact: true })).toBeVisible();
  await page.getByRole("button", { name: "CSV" }).click();
  await expect(page.getByText("Protected exports", { exact: true })).toBeVisible();
  const downloadPromise = page.waitForEvent("download");
  await page.getByRole("button", { name: "Download" }).click();
  await downloadPromise;
  expect(downloadRequested).toBe(true);
});

test("AIS integration health shows source cards, scope controls, and authoritative links", async ({ page }) => {
  await signIn(page);
  const modules = ["CORE", "IAP", "AEMS", "CMS", "ARMIS"].map((module) => ({
    module,
    authority: module,
    adapter: `${module}_READ_ONLY_GATE`,
    mode: "READ_ONLY",
    available: true,
    freshness: { mode: "LIVE_DATABASE_QUERY", status: "CURRENT", observedAt: "2026-08-14T08:00:00Z" },
    reconciliation: { status: "PASS", eligible: true },
    scopeRevalidated: true,
    confidentialityRevalidated: true,
  }));
  const health = {
    healthContractVersion: "AIS-5B.0",
    integrationContractVersion: "AIS-5A.0",
    integrationStatus: "READ_ONLY_READY",
    status: "HEALTHY",
    mode: "READ_ONLY",
    checkedAt: "2026-08-14T08:00:00Z",
    scope: { officeScope: "ALL", engagementScope: "ALL", confidentiality: { confidential: true, restricted: true } },
    sourceModules: modules,
    reconciliation: { status: "PASS", eligible: true, blockedSources: [] },
    validation: { status: "PASS", eligible: true, failedChecks: [] },
    diagnostics: modules.map((source) => ({ module: source.module, status: "PASS", issues: [] })),
    controls: { immutableSnapshots: true, sourceWrites: false, professionalDecisions: false, duplicateOwnershipTables: false, sourceContractRevalidated: true, failureMode: "FAIL_CLOSED" },
  };
  await page.route(/\/api\/ais\/integration-health$/, (route) => route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ success: true, data: health }) }));
  await page.route(/\/api\/ais\/integration-health\/snapshots$/, (route) => route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ success: true, data: { snapshots: [] } }) }));

  await page.goto("/audit-intelligence-system/integration-health");

  await expect(page.getByRole("heading", { name: "AIS Integration Health", exact: true })).toBeVisible();
  await expect(page.getByText("Source integration Healthy", { exact: true })).toBeVisible();
  await expect(page.getByText("Core Records", { exact: true })).toBeVisible();
  await expect(page.getByText("Scope rechecked", { exact: true }).first()).toBeVisible();
  await expect(page.getByRole("link", { name: "Open authoritative module" }).first()).toHaveAttribute("href", "/office-registry");
  await expect(page.getByRole("complementary").getByRole("link", { name: "Integration Health" })).toBeVisible();
});
