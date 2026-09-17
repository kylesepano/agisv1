import { expect, test } from "@playwright/test";

const engagement = {
  id: 9101,
  engagementCode: "AEMS-EXEC-E2E-001",
  title: "Procurement execution review",
  status: "FIELDWORK",
};

const fieldwork = {
  engagement,
  recordTypes: ["INTERVIEW", "OBSERVATION", "WALKTHROUGH", "INSPECTION", "TESTING", "SAMPLING", "ANALYSIS", "INQUIRY", "MEETING", "FIELD_NOTE", "OTHER"],
  executionStatuses: ["PLANNED", "IN_PROGRESS", "COMPLETED"],
  auditAreas: [{ id: 11, code: "AREA-01", name: "Procurement" }],
  auditFocuses: [{ id: 12, auditAreaId: 11, code: "FOCUS-01", name: "Approvals" }],
  procedures: [{
    id: 81,
    procedureCode: "PROC-01",
    objective: "Inspect approval evidence",
    auditCriteria: "Approved procurement policy requires documented supervisory review.",
    description: "Trace a sample of procurement approvals.",
    status: "ACTIVE",
    fieldworkStatus: "IN_PROGRESS",
    fieldworkReviewState: "DRAFT",
    finalizedFieldworkRecords: 0,
    targetDate: "2026-08-30",
    relatedTasks: [],
    relatedRecords: [],
    assignee: { id: 2, name: "Prepared Auditor" },
  }],
  records: [{
    id: 801,
    recordCode: "FWR-EXEC-001",
    recordType: "TESTING",
    status: "SUBMITTED",
    procedureId: 81,
    currentVersionNumber: 1,
    lockVersion: 2,
    reviewerNotes: null,
    latestVersion: {
      id: 901,
      versionNumber: 1,
      performedOn: "2026-08-12",
      location: "Procurement Office",
      objective: "Inspect approvals",
      procedurePerformed: "Inspected a sample of approval documents.",
      populationDescription: "All procurement transactions.",
      sampleDescription: "Five transactions.",
      result: "All sampled approvals were present.",
      conclusion: "No exception identified.",
      executionStatus: "COMPLETED",
      relatedTasks: [JSON.stringify({ title: "Upload sample schedule", assignee: "Prepared Auditor", dueDate: "2026-08-20" })],
      relatedRecords: [],
      participants: [{ id: 1, name: "Prepared Auditor", role: "Auditor" }],
      workingPapers: [{ id: 501, workingPaperVersionId: 601, code: "WP-EXEC-001", title: "Approval testing", status: "APPROVED" }],
      evidence: [{ id: 701, code: "EVD-EXEC-001", title: "Approval sample", status: "VERIFIED", documentVersionId: 1001 }],
    },
    versions: [],
    events: [{ id: 1, action: "SUBMIT", fromStatus: "DRAFT", toStatus: "SUBMITTED", comment: "Ready for review", actor: { name: "Prepared Auditor" }, createdAt: "2026-08-12T08:00:00Z" }],
  }],
  traceability: { procedures: 1, completedProcedures: 0, proceduresWithFinalizedRecords: 0, complete: true },
};

const papers = {
  workingPapers: [{ id: 501, workingPaperCode: "WP-EXEC-001", title: "Approval testing", status: "APPROVED", latestVersion: { versionNumber: 1 } }],
  evidence: [{ id: 701, evidenceCode: "EVD-EXEC-001", title: "Approval sample", status: "VERIFIED", isCurrentRevision: true, documentVersionId: 1001 }],
};

const findings = {
  offices: [{ id: 1, code: "PROC", name: "Procurement Office" }],
  riskRatings: [{ id: 2, label: "Moderate" }],
  workingPaperVersions: [{ id: 601, versionNumber: 1, workingPaper: { workingPaperCode: "WP-EXEC-001", title: "Approval testing" } }],
  evidence: [{ id: 701, evidenceCode: "EVD-EXEC-001", title: "Approval sample", versionNumber: 1 }],
  issues: [],
  findings: [],
  procedures: [{ id: 81, procedureCode: "PROC-01", objective: "Inspect approval evidence", auditCriteria: "Approved procurement policy requires documented supervisory review.", status: "COMPLETED" }],
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
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ success: true, data: { engagements: [engagement], pagination: { currentPage: 1, lastPage: 1, perPage: 100, total: 1 }, summary: {} } }) });
  });
  await page.route("**/api/aems/engagements/9101/fieldwork", async (route) => {
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ success: true, data: fieldwork }) });
  });
  await page.route("**/api/aems/engagements/9101/working-papers", async (route) => {
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ success: true, data: papers }) });
  });
  await page.route("**/api/aems/engagements/9101/findings-workspace", async (route) => {
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ success: true, data: findings }) });
  });
  await signIn(page);
});

test("AEMS-4B exposes linked procedure execution and fieldwork context", async ({ page }) => {
  await page.goto("/audit-engagement-management/execution?engagementId=9101", { waitUntil: "domcontentloaded" });

  await expect(page.getByTestId("aems-execution-workspace")).toBeVisible();
  await expect(page.getByRole("heading", { level: 2, name: "Execution Workspace" })).toBeVisible();
  await expect(page.getByText("PROC-01", { exact: true }).first()).toBeVisible();
  await expect(page.getByTestId("fieldwork-record-list")).toContainText("FWR-EXEC-001");
  await expect(page.getByText("Fieldwork timeline")).toBeVisible();
  await expect(page.getByText("Criteria traceability:", { exact: false })).toBeVisible();
  await expect(page.getByTestId("aems-execution-workspace").getByRole("link", { name: /Working Papers & Evidence/ })).toBeVisible();
  await expect(page.locator('a[href*="/issues?"][href*="procedureId=81"]')).toBeVisible();
  await expect(page.getByRole("button", { name: /Create issue/ })).toBeVisible();
});
