import { expect, test } from "@playwright/test";

const caseId = 73;
const requestId = 8101;
const versionId = 8201;

async function signIn(page) {
  await page.goto("/login");
  await page.getByLabel("Employee ID").fill("CIAS-HEAD-001");
  await page.locator("#password").fill("lala");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/dashboard$/);
}

async function json(route, data, status = 200) {
  await route.fulfill({ status, contentType: "application/json", body: JSON.stringify({ success: true, data }) });
}

function version(overrides = {}) {
  return {
    id: versionId,
    versionNumber: 1,
    status: "DRAFT",
    isCurrent: true,
    lockVersion: 1,
    source: { validationReviewId: 91, validationVersionId: 92, actionPlanVersionId: 44, progressUpdateVersionId: 55 },
    narratives: { closureRequestSummary: "Summary", implementationBasis: "Basis", validatedImplementationSummary: "Validation confirms implementation.", residualMattersSummary: "None", residualRiskStatement: "Residual risk is monitored.", ongoingMonitoringRequirements: "Annual monitoring", recordsAndDocumentationSummary: "Records are complete.", resolvedEscalationSummary: "No unresolved escalation.", managementConfirmation: "Confirmed", complianceMonitorRecommendationSummary: "Recommend approval", noAdditionalEvidenceExplanation: "No additional evidence is required." },
    availableActions: ["update", "submit"],
    evidence: [],
    ...overrides,
  };
}

function request(overrides = {}) {
  return { id: requestId, displayCode: "CLS-CMS-REC-000073-001", requestSequence: 1, initiatorTypeCode: "COMPLIANCE_MONITOR", currentVersion: version(), isResolved: false, availableActions: ["update", "submit"], ...overrides };
}

function context(status = "IMPLEMENTED") {
  return { id: caseId, status, lockVersion: 1, responsibleOffice: { id: 3, name: "Operations Office" }, closedAt: null };
}

function readiness(eligible = true) {
  return { eligible, checklist: [{ code: "case_status", label: "Recommendation is IMPLEMENTED", passed: eligible, blocking: true, explanation: eligible ? "Formal closure starts from IMPLEMENTED." : "The case must be IMPLEMENTED." }, { code: "validation", label: "Finalized validation concludes IMPLEMENTED", passed: eligible, blocking: true, explanation: "A finalized independent conclusion is required." }] };
}

async function mockList(page, options = {}) {
  await page.route(new RegExp(`/api/cms/recommendations/${caseId}/closure-requests$`), (route) => json(route, { requests: options.requests ?? [], caseContext: context(options.status), permittedActions: [] }));
  await page.route(new RegExp(`/api/cms/recommendations/${caseId}/closure-options$`), (route) => json(route, { caseContext: context(options.status), readiness: readiness(options.eligible !== false), canCreate: options.canCreate !== false, initiatorTypes: ["COMPLIANCE_MONITOR"], reasons: options.reasons || [] }));
}

test("renders the recommendation-scoped closure list and readiness empty state", async ({ page }) => {
  await signIn(page);
  await mockList(page);
  await page.goto(`/compliance-management/recommendations/${caseId}/closure-requests`);
  await expect(page.getByRole("heading", { name: "Recommendation Closure", exact: true })).toBeVisible();
  await expect(page.getByText("No Closure Requests")).toBeVisible();
  await expect(page.getByText("Recommendation is IMPLEMENTED")).toBeVisible();
  await expect(page.getByRole("button", { name: /Create Closure Request/ }).first()).toBeVisible();
});

test("renders a draft closure workspace and keeps source lineage read-only", async ({ page }) => {
  await signIn(page);
  await page.route(new RegExp(`/api/cms/closure-requests/${requestId}$`), (route) => json(route, { request: request(), caseContext: context() }));
  await page.route(new RegExp(`/api/cms/recommendations/${caseId}/closure-options$`), (route) => json(route, { caseContext: context(), readiness: readiness(), canCreate: true, initiatorTypes: ["COMPLIANCE_MONITOR"], reasons: [] }));
  await page.goto(`/compliance-management/recommendations/${caseId}/closure-requests/${requestId}`);
  await expect(page.getByText("Closure narrative")).toBeVisible();
  await page.getByRole("tab", { name: "Source & Lineage" }).click();
  await expect(page.getByText("Immutable source lineage")).toBeVisible();
  await page.getByRole("tab", { name: "Closure Readiness" }).click();
  await expect(page.getByText("Finalized validation concludes IMPLEMENTED")).toBeVisible();
});

test("closed case displays a read-only formal closure banner without reopening controls", async ({ page }) => {
  await signIn(page);
  const closed = request({ currentVersion: version({ status: "APPROVED", availableActions: [], decision: { decisionCode: "APPROVED", decidedAt: "2026-08-03T09:00:00Z", decisionComment: "Approved after independent review.", newCaseStatus: "CLOSED" } }), availableActions: [] });
  await page.route(new RegExp(`/api/cms/closure-requests/${requestId}$`), (route) => json(route, { request: closed, caseContext: context("CLOSED") }));
  await page.route(new RegExp(`/api/cms/recommendations/${caseId}/closure-options$`), (route) => json(route, { caseContext: context("CLOSED"), readiness: readiness(false), canCreate: false, initiatorTypes: [], reasons: ["The recommendation is already closed."] }));
  await page.goto(`/compliance-management/recommendations/${caseId}/closure-requests/${requestId}`);
  await expect(page.getByText("This recommendation is formally closed.", { exact: true })).toBeVisible();
  await expect(page.getByText(/Any reopening must use the separate controlled Reopening workspace/i).first()).toBeVisible();
  await expect(page.getByRole("button", { name: /reopen/i })).toHaveCount(0);
});
