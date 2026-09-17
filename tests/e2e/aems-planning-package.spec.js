import { expect, test } from "@playwright/test";

const engagement = {
  id: 9001,
  engagementCode: "AEMS-PP-E2E-001",
  title: "Procurement and payment controls review",
  sourceType: "PLANNED",
  offices: [{ id: 1, name: "City Government Office" }],
};

const readinessChecks = [
  { key: "iapLineage", label: "IAP source lineage is preserved", met: true },
  { key: "survey", label: "Preliminary survey is complete", met: true },
  { key: "objectives", label: "At least one planning objective exists", met: true },
  { key: "processFlows", label: "Process flow documentation is complete", met: true },
  { key: "riskMatrix", label: "Risk matrix and risk items exist", met: true },
  { key: "riskObjectives", label: "Every risk links to an objective in this version", met: true },
  { key: "riskProcedures", label: "Every risk links to an approved-program procedure", met: true },
  { key: "riskWorkingPapers", label: "Every risk has a working-paper reference", met: true },
  { key: "approvedAep", label: "Current AEP is approved", met: true },
  { key: "approvedProgram", label: "Current Audit Program is approved", met: true },
];

const version = {
  id: 7001,
  versionNumber: 1,
  preliminarySurveyDocumentVersionId: 42,
  preliminarySurvey: {
    purpose: "Understand procurement controls.",
    background: "The office uses a documented procurement cycle.",
    informationSources: "Policies, interviews, and transaction records.",
    observations: "Walkthrough completed.",
    planningImplications: "Test approval and payment controls.",
  },
  planningAttributes: { methodology: "Risk-based" },
  checksumSha256: "e2e-planning-package-checksum",
  changeReason: null,
  createdAt: "2026-08-11T08:00:00Z",
  createdBy: { id: 2, name: "Prepared Auditor", employeeId: "CIAS-AUD-002" },
  objectives: [{ id: 1, code: "OBJ-01", statement: "Assess procurement control design." }],
  processFlows: [{ id: 2, code: "FLOW-01", title: "Procure to pay", description: "Narrative walkthrough.", processOwnerOfficeId: 1, documentVersionId: 42, sourceReference: "Procurement policy" }],
  riskMatrix: {
    id: 3,
    code: "RM-01",
    title: "Procurement risk matrix",
    methodology: "Risk-based",
    items: [{ id: 4, riskCode: "R-01", riskStatement: "Approval evidence may be incomplete.", riskCategory: "Compliance", residualRating: "Moderate", objectiveCodes: ["OBJ-01"], procedureIds: [8], workingPapers: [{ reference: "WP-01" }] }],
  },
};

const workspace = {
  engagement,
  lineage: {
    sourceType: "PLANNED",
    iapPlanEngagementId: 12,
    iapPlanId: 4,
    iapPrioritizationItemId: 5,
    iapRiskAssessmentId: 6,
    iapAuditUniverseItemId: 7,
    capturedAt: "2026-08-11T08:00:00Z",
  },
  approvedAep: true,
  approvedProgram: true,
  package: {
    id: 5001,
    packageCode: "APP-AEMS-PP-E2E-001",
    status: "PENDING_REVIEW",
    currentVersionNumber: 1,
    approvedVersionNumber: null,
    lockVersion: 2,
    preparedBy: { id: 2, name: "Prepared Auditor", employeeId: "CIAS-AUD-002" },
    submittedBy: { id: 2, name: "Prepared Auditor", employeeId: "CIAS-AUD-002" },
    submittedAt: "2026-08-11T08:30:00Z",
    approvedBy: null,
    approvedAt: null,
    latestVersion: version,
    versions: [version],
    reviews: [],
  },
  readiness: { ready: true, checks: readinessChecks },
  procedures: [{ id: 8, code: "PROC-01", objective: "Inspect approval evidence." }],
  capabilities: { canCreate: false, canEdit: false, canReview: true, canApprove: true, canRevise: false },
};

async function signIn(page) {
  await page.goto("/login");
  await page.getByLabel("Employee ID").fill("CIAS-HEAD-001");
  await page.locator("#password").fill("lala");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/dashboard$/);
}

test.beforeEach(async ({ page }) => {
  await page.route("**/api/aems/engagements?*", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ success: true, data: { engagements: [engagement], pagination: { currentPage: 1, lastPage: 1, perPage: 100, total: 1 }, summary: {} } }),
    });
  });
  await page.route("**/api/aems/engagements/9001/planning-package", async (route) => {
    if (route.request().method() !== "GET") return route.continue();
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ success: true, data: workspace }) });
  });
  await signIn(page);
});

test("AEMS-2B planning workspace exposes readiness, review actions, and version inspection", async ({ page }) => {
  await page.goto("/audit-engagement-management/planning-package?engagementId=9001", { waitUntil: "domcontentloaded" });
  await expect(page.getByRole("heading", { level: 2, name: "Planning Package" })).toBeVisible();
  await expect(page.getByTestId("aems-engagement-tabs")).toBeVisible();
  await expect(page.getByText("APP-AEMS-PP-E2E-001")).toBeVisible();
  await expect(page.getByText("Planning objectives")).toBeVisible();
  await expect(page.getByTestId("planning-fieldwork-blocker")).toBeVisible();

  await page.getByRole("tab", { name: "Readiness & Review" }).click({ force: true });
  await expect(page.getByTestId("planning-readiness")).toContainText("Preliminary survey is complete");
  await expect(page.getByRole("button", { name: "Record independent review" }).first()).toBeVisible();
  await expect(page.getByRole("button", { name: "Approve current version" }).first()).toBeVisible();
  await expect(page.getByRole("button", { name: "Save new version" })).toHaveCount(0);

  await page.getByRole("tab", { name: "Versions" }).click({ force: true });
  await expect(page.getByText("Immutable version history")).toBeVisible();
  await expect(page.getByText("Version comparison")).toBeVisible();
  await expect(page.getByText("Approved immutable version")).toHaveCount(0);
});
