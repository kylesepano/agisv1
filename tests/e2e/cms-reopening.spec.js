import { expect, test } from "@playwright/test";

const caseId = 73;
const requestId = 9301;
const versionId = 9401;

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

function context(status = "CLOSED") {
  return { id: caseId, status, lockVersion: 1, activeCycleNumber: 1, reopeningCount: 0 };
}

function readiness(eligible = true) {
  return { eligible, checklist: [{ code: "terminal_status", label: "Recommendation has an eligible terminal status", passed: eligible, blocking: true, explanation: eligible ? "Only an approved Reopening Decision changes the terminal status." : "The recommendation is not currently eligible." }] };
}

function version(overrides = {}) {
  return { id: versionId, versionNumber: 1, status: "DRAFT", isCurrent: true, isImmutable: false, lockVersion: 1, reasonCode: "NEW_MATERIAL_EVIDENCE", proposedDestinationStatus: "FOR_ACTION_PLAN", narratives: { requestSummary: "New material evidence requires controlled review.", changedConditionOrNewFact: "A material fact changed.", materialityAssessment: "Material.", sourceTerminalDecisionAssessment: "The historical decision was verified.", riskImpact: "Risk requires renewed action.", proposedFollowUpApproach: "Prepare a new action plan.", newActionPlanRequirementExplanation: "A new plan is required.", noAdditionalEvidenceExplanation: "No additional evidence is available." }, evidence: [], availableActions: ["update", "submit"], ...overrides };
}

function request(overrides = {}) {
  return { id: requestId, displayCode: "ROP-CMS-REC-000073-001", requestSequence: 1, initiatorTypeCode: "RESPONSIBLE_OFFICE", sourceTerminalStatus: "CLOSED", currentVersion: version(), isResolved: false, ...overrides };
}

async function mockRecommendation(page, status = "CLOSED") {
  await page.route(new RegExp(`/api/cms/recommendations/${caseId}$`), (route) => json(route, { recommendation: { id: caseId, cmsRecommendationCode: "CMS-REC-000073", status, reopeningSummary: { priorRequestCount: 0, activeCycleNumber: 1 } } }));
}

async function mockOptions(page, overrides = {}) {
  await page.route(new RegExp(`/api/cms/recommendations/${caseId}/reopening-options$`), (route) => json(route, { caseContext: context(overrides.status), readiness: readiness(overrides.eligible !== false), canCreate: overrides.canCreate !== false, sourceTerminalStatus: overrides.status || "CLOSED", sourceDecision: { type: "CLOSURE", id: 801, decisionCode: "APPROVED", decidedAt: "2026-08-01T09:00:00Z", finalSnapshot: { status: "CLOSED" } }, reasons: [{ code: "NEW_MATERIAL_EVIDENCE", label: "New material evidence" }], destinations: [{ code: "FOR_ACTION_PLAN", label: "For Action Plan", eligible: true, explanation: "A new Action Plan is required." }, { code: "MONITORING", label: "Monitoring", eligible: false, explanation: "An accepted Action Plan and current monitor are required." }], initiatorTypes: ["RESPONSIBLE_OFFICE"] }));
}

test("renders scoped reopening list and backend readiness", async ({ page }) => {
  await signIn(page);
  await mockRecommendation(page);
  await page.route(new RegExp(`/api/cms/recommendations/${caseId}/reopenings$`), (route) => json(route, { requests: [], caseContext: context(), permittedActions: ["request"] }));
  await mockOptions(page);
  await page.goto(`/compliance-management/recommendations/${caseId}/reopening-requests`);
  await expect(page.getByRole("heading", { name: "Reopening Requests", exact: true })).toBeVisible();
  await expect(page.getByText("No reopening requests")).toBeVisible();
  await expect(page.getByText("Recommendation has an eligible terminal status")).toBeVisible();
  await expect(page.getByRole("button", { name: /Create Reopening Request/i })).toBeVisible();
});

test("creates a draft and keeps the terminal context visible", async ({ page }) => {
  await signIn(page);
  await mockRecommendation(page);
  await mockOptions(page);
  await page.route(new RegExp(`/api/cms/recommendations/${caseId}/reopenings$`), async (route) => {
    if (route.request().method() === "POST") return json(route, { request: request(), caseContext: context() }, 201);
    return json(route, { requests: [], caseContext: context(), permittedActions: ["request"] });
  });
  await page.route(new RegExp(`/api/cms/reopening-requests/${requestId}$`), (route) => json(route, { request: request(), caseContext: context() }));
  await page.goto(`/compliance-management/recommendations/${caseId}/reopening-requests`);
  await page.getByRole("button", { name: /Create Reopening Request/i }).click();
  await page.getByLabel("Reopening reason").selectOption("NEW_MATERIAL_EVIDENCE");
  await page.getByLabel("Proposed destination").selectOption("FOR_ACTION_PLAN");
  await page.getByRole("button", { name: "Create draft" }).click();
  await expect(page).toHaveURL(new RegExp(`/reopening-requests/${requestId}$`));
  await expect(page.getByText(/previously closed recommendation/i)).toBeVisible();
  await expect(page.getByText(/Only an approved Reopening Decision/i)).toBeVisible();
});

test("approved reopening shows the new active cycle and preserved history", async ({ page }) => {
  await signIn(page);
  await mockRecommendation(page, "FOR_ACTION_PLAN");
  await mockOptions(page, { status: "FOR_ACTION_PLAN", eligible: false, canCreate: false });
  const approved = request({ currentVersion: version({ status: "APPROVED", isImmutable: true, availableActions: [], decision: { decisionCode: "APPROVED", decidedAt: "2026-08-04T09:00:00Z", decisionComment: "Approved after independent review.", sourceTerminalStatus: "CLOSED", approvedDestinationStatus: "FOR_ACTION_PLAN", previousActiveCycleNumber: 1, newActiveCycleNumber: 2, reopeningEffectiveDate: "2026-08-04" } }), isResolved: true });
  await page.route(new RegExp(`/api/cms/reopening-requests/${requestId}$`), (route) => json(route, { request: approved, caseContext: { ...context("FOR_ACTION_PLAN"), activeCycleNumber: 2, reopeningCount: 1 } }));
  await page.goto(`/compliance-management/recommendations/${caseId}/reopening-requests/${requestId}`);
  await expect(page.getByText(/Recommendation reopened through an authorized decision/i)).toBeVisible();
  await expect(page.getByText("New active cycle", { exact: false })).toBeVisible();
  await expect(page.getByRole("tab", { name: "Final Decision" })).toBeVisible();
  await page.getByRole("tab", { name: "Final Decision" }).click();
  await expect(page.getByText(/original terminal decision remains permanently preserved/i)).toBeVisible();
});
