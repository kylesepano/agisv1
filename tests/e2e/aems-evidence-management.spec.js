import { expect, test } from "@playwright/test";

const engagement = {
  id: 9201,
  engagementCode: "AEMS-EVD-E2E-001",
  title: "Procurement evidence review",
  status: "FIELDWORK",
};

const evidence = {
  id: 701,
  evidenceCode: "EVD-REQ-001",
  title: "Approval sample",
  status: "VERIFIED",
  outcome: "ACCEPTED",
  versionNumber: 1,
  isCurrentRevision: true,
  documentVersionId: 1001,
  familyUuid: "evidence-family-1",
  fileName: "approval-sample.pdf",
  fileSize: 2048,
  mimeType: "application/pdf",
  checksumSha256: "abcdef0123456789abcdef0123456789abcdef0123456789abcdef0123456789",
  dateObtained: "2026-08-12",
  custodianName: "Procurement Office",
  acquisitionMethod: "AUDITEE_SUBMISSION",
  acquisitionForm: "DIGITAL_FILE",
  controlReference: "CTRL-AP-01",
  reportVersions: [{ id: 1201, reportId: 3001, versionNumber: 1, stage: "DRAFT_REPORT" }],
  confidentialityLevel: { label: "Internal" },
  assessment: {
    id: 801,
    evidenceId: 701,
    evidenceCode: "EVD-REQ-001",
    documentVersionId: 1001,
    versionNumber: 1,
    isCurrentRevision: true,
    status: "ASSESSED",
    sufficiency: "YES",
    reliability: "HIGH",
    confidentiality: "INTERNAL",
    assessedBy: { name: "Independent Reviewer" },
    assessedAt: "2026-08-12T08:00:00Z",
    eligibleForFinalizedFinding: true,
  },
};

const request = {
  id: 501,
  requestCode: "ERQ-AEMS-EVD-E2E-001-001",
  title: "Approval evidence request",
  purpose: "Obtain approval support for sampled transactions.",
  status: "RECEIVED",
  dueDate: "2026-08-20",
  currentVersionNumber: 1,
  lockVersion: 3,
  events: [{ id: 701, eventType: "EVIDENCE_LINKED", fromStatus: "RECEIVED", toStatus: "RECEIVED", reason: "Exact document version received.", createdAt: "2026-08-12T07:30:00Z", versionNumber: 3 }],
  versions: [{ id: 601, versionNumber: 1, requestedItems: ["Signed approval sample"], createdAt: "2026-08-12T07:00:00Z" }],
  evidence: [{ id: 701, evidenceId: 701, evidenceCode: "EVD-REQ-001", documentVersionId: 1001, receivedAt: "2026-08-12T07:30:00Z", checksumSha256: evidence.checksumSha256, assessment: evidence.assessment }],
};

const requestWorkspace = {
  engagement,
  requestStatuses: ["DRAFT", "SUBMITTED", "SENT", "ACKNOWLEDGED", "PARTIALLY_RECEIVED", "RECEIVED", "FOR_REVIEW", "ASSESSED", "OVERDUE", "EXTENSION_REQUESTED", "EXTENDED", "ESCALATED", "CANCELLED", "CLOSED_WITHOUT_SUBMISSION", "CLOSED"],
  assessmentStatuses: ["ASSESSED"],
  requests: [request],
  assessments: [evidence.assessment],
  evidence: [evidence],
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
  await page.route("**/api/aems/engagements/9201/evidence-requests", async (route) => {
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ success: true, data: requestWorkspace }) });
  });
  await page.route("**/api/aems/engagements/9201/working-papers", async (route) => {
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ success: true, data: { evidence: [evidence], workingPapers: [] } }) });
  });
  await signIn(page);
});

test("AEMS-5B exposes request tracking, assessment state, custody, and linked context", async ({ page }) => {
  await page.goto("/audit-engagement-management/evidence?engagementId=9201", { waitUntil: "domcontentloaded" });

  await expect(page.getByTestId("aems-evidence-workspace")).toBeVisible();
  await expect(page.getByRole("heading", { level: 2, name: "Evidence Management" })).toBeVisible();
  await expect(page.getByText(request.requestCode, { exact: true }).first()).toBeVisible();
  await expect(page.getByText("RECEIVED", { exact: true }).first()).toBeVisible();
  await expect(page.getByText("Submission tracking")).toBeVisible();
  await expect(page.getByText("Lifecycle audit trail")).toBeVisible();

  await page.getByRole("button", { name: "Evidence Register" }).click();
  await expect(page.getByText(evidence.evidenceCode, { exact: true }).first()).toBeVisible();
  await expect(page.getByText("Accepted for reporting", { exact: true }).first()).toBeVisible();
  await expect(page.getByText("AUDITEE SUBMISSION", { exact: true })).toBeVisible();
  await expect(page.getByText("1 report version link", { exact: true })).toBeVisible();
  await expect(page.getByText(evidence.checksumSha256, { exact: true })).toBeVisible();
  await expect(page.getByTestId("aems-evidence-workspace").getByRole("link", { name: "Working Papers" })).toBeVisible();

  await page.getByRole("button", { name: "Assessments & Gaps" }).click();
  await expect(page.getByText("Assessment and evidence gaps")).toBeVisible();
  await expect(page.getByText("EVD-REQ-001", { exact: true }).last()).toBeVisible();
});
