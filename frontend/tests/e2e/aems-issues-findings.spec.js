import { expect, test } from "@playwright/test";

async function signIn(page) {
  await page.goto("/login");
  await page.getByLabel("Employee ID").fill("CIAS-HEAD-001");
  await page.locator("#password").fill("lala");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/dashboard$/);
}

const engagement = {
  id: 9901,
  engagementCode: "AEMS-UI-9901",
  title: "Issues and findings UI contract",
  status: "FIELDWORK",
};

const workspace = {
  engagement,
  issues: [
    {
      id: 91,
      issueCode: "ISS-9901-001",
      title: "Unsupported approval exception",
      exceptionDescription: "The approval evidence does not support the recorded exception.",
      status: "VALIDATED",
      disposition: null,
      responsibleOffice: { id: 1, name: "Internal Audit" },
      raisedBy: { id: 999, name: "Field Auditor" },
      reviewer: { id: 10, name: "Review Supervisor" },
      riskRating: { label: "High" },
      workingPaperVersions: [],
      evidence: [],
      history: [],
      lockVersion: 1,
    },
  ],
  findings: [
    {
      id: 92,
      findingCode: "FND-9901-001",
      title: "Approval control deficiency",
      status: "FINALIZED",
      revisionNumber: 0,
      revisionType: "ORIGINAL",
      isCurrentRevision: true,
      criteria: "Approved policy requires documented review.",
      condition: "Review evidence was not retained.",
      cause: "The control owner did not use the evidence checklist.",
      conclusion: "The control operated ineffectively.",
      effect: "Reliance on the approval control is reduced.",
      significanceClassification: "SIGNIFICANT",
      effectClassification: "OPERATIONAL",
      riskRating: { label: "High" },
      responsibleOffice: { name: "Internal Audit" },
      authoredBy: { id: 999, name: "Field Auditor" },
      finalizedAt: "2026-08-12T00:00:00Z",
      workingPaperVersions: [],
      evidence: [],
      fieldworkRecords: [],
      procedures: [{ id: 81, procedureCode: "PROC-9901", objective: "Review approval evidence", auditCriteria: "Approved policy requires documented review.", criteriaReference: "Approved policy requires documented review.", traceabilityNote: "Criteria linked from approved audit procedure PROC-9901." }],
      recommendations: [],
      managementResponses: [],
      revisions: [],
      history: [],
      lockVersion: 2,
    },
  ],
  offices: [],
  riskRatings: [],
  workingPaperVersions: [],
  evidence: [],
  procedures: [{ id: 81, procedureCode: "PROC-9901", objective: "Review approval evidence", auditCriteria: "Approved policy requires documented review.", status: "COMPLETED" }],
  agreementPositions: ["AGREE", "PARTIALLY_AGREE", "DISAGREE"],
  rejoinderDispositions: ["ACCEPT", "PARTIALLY_ACCEPT", "REJECT", "REQUEST_CLARIFICATION"],
};

async function mockFindingsWorkspace(page) {
  await page.route("**/api/aems/findings-workspaces", async (route) => {
    await route.fulfill({
      contentType: "application/json",
      body: JSON.stringify({ success: true, data: { engagements: [engagement] } }),
    });
  });
  await page.route("**/api/aems/engagements/9901/findings-workspace", async (route) => {
    await route.fulfill({ contentType: "application/json", body: JSON.stringify({ success: true, data: workspace }) });
  });
}

test.beforeEach(async ({ page }) => {
  await signIn(page);
  await mockFindingsWorkspace(page);
});

test("Issues workspace exposes validated disposition controls", async ({ page }) => {
  await page.goto("/audit-engagement-management/issues?engagementId=9901");
  await expect(page.getByRole("main").getByRole("heading", { name: "Audit Issues", exact: true })).toBeVisible();
  await expect(page.getByRole("heading", { name: "Unsupported approval exception", exact: true })).toBeVisible();
  await expect(page.getByRole("button", { name: "Merge", exact: true })).toBeVisible();
  await expect(page.getByRole("button", { name: "Resolve during audit", exact: true })).toBeVisible();
  await expect(page.getByRole("button", { name: "Close without finding", exact: true })).toBeVisible();
});

test("Findings page shows immutable detail and revision history contract", async ({ page }) => {
  await page.goto("/audit-engagement-management/findings?engagementId=9901");
  await expect(page.getByRole("main").getByRole("heading", { name: "Findings & Recommendations", exact: true })).toBeVisible();
  await expect(page.getByText("Immutable snapshot:", { exact: false })).toBeVisible();
  await expect(page.getByText("Criteria", { exact: true })).toBeVisible();
  await expect(page.getByText("Fieldwork traceability", { exact: true })).toBeVisible();
  await expect(page.getByText("Procedure and criteria traceability", { exact: true })).toBeVisible();
  await expect(page.getByText("Immutable revision history", { exact: true })).toBeVisible();
  await expect(page.getByText("Locked after finalized", { exact: false })).toBeVisible();
});
