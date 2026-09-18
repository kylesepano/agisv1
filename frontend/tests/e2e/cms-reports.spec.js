import { expect, test } from "@playwright/test";

async function signIn(page) {
  await page.goto("/login");
  await page.getByLabel("Employee ID").fill("CIAS-HEAD-001");
  await page.locator("#password").fill("lala");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/dashboard$/);
}

function report(overrides = {}) {
  return {
    code: "portfolio-status",
    title: "CMS Recommendation Portfolio Status",
    description: "Current status, risk, responsibility, targets, and source lineage for visible recommendations.",
    filters: ["search", "status", "officeId", "riskCode", "dateFrom", "dateTo"],
    columns: [
      { key: "caseCode", label: "CMS Case" },
      { key: "status", label: "Status" },
      { key: "risk", label: "Risk" },
    ],
    ...overrides,
  };
}

function run(overrides = {}) {
  return {
    id: 41,
    displayCode: "CMS-RPT-000041",
    reportCode: "portfolio-status",
    title: "CMS Recommendation Portfolio Status",
    sourceQueryVersion: "CMS-12A-v1",
    filters: {},
    generatedAt: "2026-08-10T08:00:00Z",
    rowCount: 1,
    resultChecksumSha256: "a".repeat(64),
    columns: report().columns,
    rows: [{ caseCode: "CMS-REC-000073", status: "MONITORING", risk: "HIGH" }],
    exports: [],
    ...overrides,
  };
}

async function mockReports(page) {
  let generatedFilters;
  let downloadRequested = false;
  await page.route(/\/api\/cms\/reports$/, (route) => route.fulfill({
    status: 200,
    contentType: "application/json",
    body: JSON.stringify({
      success: true,
      data: {
        reports: [report(), report({ code: "implementation-progress", title: "CMS Implementation Progress Report" })],
        formats: [
          { code: "csv", label: "CSV", mimeType: "text/csv" },
          { code: "pdf", label: "PDF", mimeType: "application/pdf" },
        ],
        scope: { portfolioWide: true, assignmentScoped: false, confidentiality: { confidential: true, restricted: false } },
        canExport: true,
      },
    }),
  }));
  await page.route(/\/api\/cms\/reports\/runs$/, (route) => route.fulfill({
    status: 200,
    contentType: "application/json",
    body: JSON.stringify({ success: true, data: { runs: [run()] } }),
  }));
  await page.route(/\/api\/cms\/reports\/[^/]+\/generate/, async (route) => {
    generatedFilters = JSON.parse(route.request().postData() || "{}");
    return route.fulfill({ status: 201, contentType: "application/json", body: JSON.stringify({ success: true, data: { run: run({ filters: generatedFilters }) } }) });
  });
  await page.route(/\/api\/cms\/reports\/runs\/41\/exports$/, (route) => route.fulfill({
    status: 201,
    contentType: "application/json",
    body: JSON.stringify({ success: true, data: { export: { id: 71, reportRunId: 41, format: "csv", versionNumber: 1, fileName: "cms-report-v1.csv", mimeType: "text/csv", fileSize: 120, checksumSha256: "b".repeat(64), generatedAt: "2026-08-10T08:01:00Z" } } }),
  }));
  await page.route(/\/api\/cms\/report-exports\/71\/download$/, async (route) => {
    downloadRequested = true;
    return route.fulfill({ status: 200, contentType: "text/csv", body: "CMS Case,Status\nCMS-REC-000073,MONITORING\n" });
  });
  return {
    getGeneratedFilters: () => generatedFilters,
    downloadRequested: () => downloadRequested,
  };
}

test("renders CMS report catalog, scope, immutable run history, and filters", async ({ page }) => {
  await signIn(page);
  await mockReports(page);
  await page.goto("/compliance-management/reports");

  await expect(page.getByRole("heading", { name: "CMS Reports & Exports", exact: true })).toBeVisible();
  await expect(page.getByText("Authorized scope", { exact: true })).toBeVisible();
  await expect(page.getByRole("heading", { name: "CMS-RPT-000041", exact: true })).toBeVisible();
  await expect(page.getByRole("button", { name: "Generate report" })).toBeVisible();
  await expect(page.getByRole("heading", { name: "CMS Recommendation Portfolio Status", exact: true })).toBeVisible();
});

test("generates a filtered report and creates/downloads a protected CSV export", async ({ page }) => {
  await signIn(page);
  const reports = await mockReports(page);
  await page.goto("/compliance-management/reports");

  await page.getByLabel("Report status").selectOption("MONITORING");
  const generateResponse = page.waitForResponse(/\/api\/cms\/reports\/[^/]+\/generate/);
  await page.getByRole("button", { name: "Generate report" }).click();
  await generateResponse;
  await expect(page.getByText("CMS-REC-000073", { exact: true })).toBeVisible();
  expect(reports.getGeneratedFilters()).toMatchObject({ status: "MONITORING" });

  await page.getByRole("button", { name: "Generate CSV" }).click();
  await expect(page.getByText("CSV v1", { exact: false })).toBeVisible();
  const downloadPromise = page.waitForEvent("download");
  await page.getByRole("button", { name: /CSV v1/ }).click();
  await downloadPromise;
  expect(reports.downloadRequested()).toBe(true);
});
