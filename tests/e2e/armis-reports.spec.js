import { expect, test } from "@playwright/test";

async function signIn(page) {
  await page.goto("/login");
  await page.getByLabel("Employee ID").fill("CIAS-HEAD-001");
  await page.locator("#password").fill("lala");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/dashboard$/);
}

const reports = [
  {
    code: "resource-utilization",
    title: "ARMIS Resource Utilization Report",
    description: "Resource utilization",
    filters: ["search", "status", "officeId", "fiscalYear"],
    columns: [
      { key: "resourceCode", label: "Resource Code" },
      { key: "resource", label: "Resource" },
      { key: "utilizationPercent", label: "Utilization" },
    ],
  },
  {
    code: "assignment-register",
    title: "ARMIS Assignment Register",
    description: "Assignment register",
    filters: ["search", "status", "officeId", "fiscalYear"],
    columns: [{ key: "assignmentCode", label: "Assignment" }],
  },
];

const run = {
  id: 71,
  displayCode: "ARMIS-RUN-000071",
  reportCode: "resource-utilization",
  title: reports[0].title,
  sourceQueryVersion: "ARMIS-5A-v1",
  filters: { fiscalYear: 2026 },
  generatedAt: "2026-08-10T09:00:00Z",
  rowCount: 1,
  resultChecksumSha256: "abcdef0123456789abcdef0123456789abcdef0123456789abcdef0123456789",
  columns: reports[0].columns,
  rows: [{ resourceCode: "ARMIS-RES-001", resource: "Demo Auditor", utilizationPercent: 42.5 }],
  exports: [],
};

async function fulfill(route, data, status = 200) {
  await route.fulfill({
    status,
    contentType: "application/json",
    body: JSON.stringify({ success: true, data }),
  });
}

async function mockReports(page) {
  let generatedRun = { ...run };
  let generatedPayload;
  await page.route(/\/api\/armis\/reports$/, (route) => fulfill(route, {
    reports,
    formats: [
      { code: "csv", label: "CSV", mimeType: "text/csv" },
      { code: "pdf", label: "PDF", mimeType: "application/pdf" },
    ],
    scope: { portfolioWide: true, assignmentScoped: false, confidentiality: { confidential: true, restricted: false } },
    canExport: true,
    provider: { mode: "ARMIS_AUTHORITATIVE" },
  }));
  await page.route(/\/api\/armis\/reports\/runs$/, (route) => fulfill(route, { runs: [generatedRun], meta: { total: 1 } }));
  await page.route(/\/api\/armis\/administration$/, (route) => fulfill(route, {
    provider: { mode: "ARMIS_AUTHORITATIVE" },
    permissions: { "armis.report.view": true, "armis.report.export": true },
    workflows: { assignments: ["DRAFT", "SUBMITTED", "APPROVED", "LOCKED"] },
    notifications: { visibleCount: 2, unreadCount: 1 },
    hardening: { immutableReportRuns: true, immutableExports: true, privateDownloads: true, checksumHeaders: true },
  }));
  await page.route(/\/api\/armis\/reports\/resource-utilization\/generate$/, async (route) => {
    generatedPayload = route.request().postDataJSON();
    generatedRun = { ...generatedRun, filters: generatedPayload, generatedAt: "2026-08-10T10:00:00Z" };
    await fulfill(route, { run: generatedRun }, 201);
  });
  await page.route(/\/api\/armis\/reports\/runs\/71\/exports$/, async (route) => {
    await fulfill(route, { export: { id: 81, reportRunId: 71, format: "csv", versionNumber: 1, fileName: "armis-report.csv", fileSize: 120, checksumSha256: "1234", generatedAt: "2026-08-10T10:01:00Z" } }, 201);
  });
  await page.route(/\/api\/armis\/report-exports\/81\/download$/, (route) => route.fulfill({ status: 200, contentType: "text/csv", body: "resourceCode,utilizationPercent\nARMIS-RES-001,42.5\n" }));
  return { getGeneratedPayload: () => generatedPayload };
}

test("renders ARMIS reports and the administration contract", async ({ page }) => {
  await signIn(page);
  await mockReports(page);
  await page.goto("/audit-resource-management/reports");

  await expect(page.getByRole("heading", { name: "ARMIS Reports & Administration", exact: true, level: 2 })).toBeVisible();
  await expect(page.getByRole("button", { name: /ARMIS Resource Utilization Report/ }).first()).toBeVisible();
  await expect(page.getByRole("heading", { name: "ARMIS-RUN-000071", exact: true })).toBeVisible();
  await page.getByRole("tab", { name: "Administration" }).click();
  await expect(page.getByText("Provider authority", { exact: true })).toBeVisible();
  await expect(page.getByText("ARMIS_AUTHORITATIVE", { exact: true })).toBeVisible();
});

test("generates a filtered ARMIS report and creates a protected export", async ({ page }) => {
  await signIn(page);
  const armis = await mockReports(page);
  await page.goto("/audit-resource-management/reports");

  await page.getByLabel("Report fiscal year").fill("2026");
  await page.getByRole("button", { name: "Generate report" }).click();
  await expect(page.getByText("ARMIS report generated from an immutable snapshot.", { exact: true })).toBeVisible();
  expect(armis.getGeneratedPayload()).toMatchObject({ fiscalYear: "2026" });
  await page.getByRole("button", { name: "Generate CSV" }).click();
  await expect(page.getByRole("button", { name: /CSV v1/ })).toBeVisible();
  await page.getByRole("button", { name: /CSV v1/ }).click();
  await expect(page.getByText("CSV export downloaded.", { exact: true })).toBeVisible();
});

test("keeps the ARMIS reports workspace usable on mobile", async ({ page }) => {
  await signIn(page);
  await mockReports(page);
  await page.goto("/audit-resource-management/reports");
  await expect(page.getByRole("heading", { name: "ARMIS Reports & Administration", exact: true, level: 2 })).toBeVisible();
  await expect(page.locator("body")).toHaveJSProperty("scrollWidth", await page.evaluate(() => document.documentElement.clientWidth));
});
