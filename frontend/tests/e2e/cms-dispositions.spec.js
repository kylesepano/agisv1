import { expect, test } from "@playwright/test";

const caseId = 73;
const requestId = 9101;
const versionId = 9201;

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

function context(status = "MONITORING") {
  return { id: caseId, status, lockVersion: 1, dispositionStatus: status, resolvedAt: null };
}

function readiness(eligible = true) {
  return { eligible, checklist: [{ code: "case_status", label: "Recommendation is under monitoring", passed: eligible, blocking: true, explanation: eligible ? "A disposition may be started from MONITORING." : "The case must be MONITORING or PARTIALLY_IMPLEMENTED." }] };
}

function version(overrides = {}) {
  return {
    id: versionId,
    versionNumber: 1,
    status: "DRAFT",
    statusCode: "DRAFT",
    isCurrent: true,
    isImmutable: false,
    lockVersion: 1,
    previousCaseStatus: "MONITORING",
    narratives: {
      dispositionSummary: "Residual risk requires controlled disposition.",
      basisAndCriteria: "The approved basis is documented.",
      riskImpactAssessment: "Residual risk remains monitored.",
      managementPosition: "Management confirms the position.",
      responsibleOfficeConfirmation: "The responsible office confirms ownership.",
      acceptedRiskRationale: "Further implementation is disproportionate.",
      riskTreatmentAndMonitoring: "Quarterly monitoring continues.",
      noLongerApplicableBasis: "",
      transitionOrRecordsImpact: "",
      noAdditionalEvidenceExplanation: "No additional evidence is required.",
    },
    evidence: [],
    availableActions: ["update", "submit", "upload-evidence"],
    ...overrides,
  };
}

function request(overrides = {}) {
  return {
    id: requestId,
    displayCode: "DSP-CMS-REC-000073-001",
    requestSequence: 1,
    dispositionCode: "ACCEPTED_RISK",
    initiatorTypeCode: "RESPONSIBLE_OFFICE",
    currentVersion: version(),
    isResolved: false,
    availableActions: ["update", "submit"],
    ...overrides,
  };
}

async function mockOptions(page, options = {}) {
  await page.route(new RegExp(`/api/cms/recommendations/${caseId}/disposition-options$`), (route) => json(route, {
    caseContext: context(options.status),
    readiness: readiness(options.eligible !== false),
    canCreate: options.canCreate !== false,
    initiatorTypes: ["RESPONSIBLE_OFFICE"],
    dispositionTypes: ["ACCEPTED_RISK", "NO_LONGER_APPLICABLE"],
    reasons: options.reasons || [],
  }));
}

test("renders the scoped disposition list and backend readiness", async ({ page }) => {
  await signIn(page);
  await page.route(new RegExp(`/api/cms/recommendations/${caseId}/dispositions$`), (route) => json(route, { requests: [], caseContext: context(), permittedActions: ["create"] }));
  await mockOptions(page);
  await page.goto(`/compliance-management/recommendations/${caseId}/dispositions`);
  await expect(page.getByRole("heading", { name: "Dispositions", exact: true })).toBeVisible();
  await expect(page.getByText("No disposition requests")).toBeVisible();
  await expect(page.getByText("Recommendation is under monitoring")).toBeVisible();
  await expect(page.getByRole("button", { name: /Create disposition request/i }).first()).toBeVisible();
});

test("shows creation blocking reasons without inventing eligibility", async ({ page }) => {
  await signIn(page);
  await page.route(new RegExp(`/api/cms/recommendations/${caseId}/dispositions$`), (route) => json(route, { requests: [], caseContext: context("FOR_VALIDATION"), permittedActions: [] }));
  await mockOptions(page, { status: "FOR_VALIDATION", eligible: false, canCreate: false, reasons: ["Complete the active validation review first."] });
  await page.goto(`/compliance-management/recommendations/${caseId}/dispositions`);
  await expect(page.getByText("Creation unavailable")).toBeVisible();
  await expect(page.getByText("Complete the active validation review first.")).toBeVisible();
  await expect(page.getByRole("button", { name: /Create disposition request/i })).toHaveCount(0);
});

test("creates an Accepted-Risk draft and renders type-specific fields", async ({ page }) => {
  await signIn(page);
  await page.route(new RegExp(`/api/cms/recommendations/${caseId}/dispositions$`), async (route) => {
    if (route.request().method() === "POST") return json(route, { request: request(), caseContext: context() }, 201);
    return json(route, { requests: [], caseContext: context(), permittedActions: ["create"] });
  });
  await mockOptions(page);
  await page.route(new RegExp(`/api/cms/disposition-requests/${requestId}$`), (route) => json(route, { request: request(), caseContext: context() }));
  await page.goto(`/compliance-management/recommendations/${caseId}/dispositions`);
  await page.getByRole("button", { name: /Create disposition request/i }).click();
  await expect(page).toHaveURL(new RegExp(`/dispositions/${requestId}$`));
  await page.getByRole("tab", { name: "Disposition Details" }).click();
  await expect(page.getByText("Accepted-Risk details")).toBeVisible();
  await expect(page.getByLabel("Accepted-Risk rationale")).toBeVisible();
  await expect(page.getByText(/distinct from implementation/i).first()).toBeVisible();
});

test("renders an approved Accepted-Risk terminal banner without reopening controls", async ({ page }) => {
  await signIn(page);
  const approved = request({ currentVersion: version({ status: "APPROVED", statusCode: "APPROVED", isImmutable: true, availableActions: [], decision: { decisionCode: "APPROVED", decidedAt: "2026-08-04T09:00:00Z", decisionComment: "Residual risk accepted.", newCaseStatus: "ACCEPTED_RISK" } }), availableActions: [] });
  await page.route(new RegExp(`/api/cms/disposition-requests/${requestId}$`), (route) => json(route, { request: approved, caseContext: context("ACCEPTED_RISK") }));
  await mockOptions(page, { status: "ACCEPTED_RISK", eligible: false, canCreate: false, reasons: ["The recommendation already has a final disposition."] });
  await page.goto(`/compliance-management/recommendations/${caseId}/dispositions/${requestId}`);
  await page.getByRole("tab", { name: "Final Decision" }).click();
  await expect(page.getByText("Accepted Risk approved").first()).toBeVisible();
  await expect(page.getByText(/Residual risk was formally accepted/i).first()).toBeVisible();
  await expect(page.getByRole("button", { name: /reopen/i })).toHaveCount(0);
  await expect(page.getByRole("button", { name: /Approve/i })).toHaveCount(0);
});

test("renders No-Longer-Applicable details separately from Accepted Risk", async ({ page }) => {
  await signIn(page);
  const nla = request({ dispositionCode: "NO_LONGER_APPLICABLE", currentVersion: version({ narratives: { noLongerApplicableBasis: "The process was formally retired.", transitionOrRecordsImpact: "Records moved to the replacement process." }, availableActions: [] }), availableActions: [] });
  await page.route(new RegExp(`/api/cms/disposition-requests/${requestId}$`), (route) => json(route, { request: nla, caseContext: context("FOR_DISPOSITION") }));
  await mockOptions(page, { status: "FOR_DISPOSITION", eligible: false, canCreate: false, reasons: ["An unresolved disposition request already exists."] });
  await page.goto(`/compliance-management/recommendations/${caseId}/dispositions/${requestId}`);
  await page.getByRole("tab", { name: "Disposition Details" }).click();
  await expect(page.getByText("No-Longer-Applicable details")).toBeVisible();
  await expect(page.getByLabel("No-Longer-Applicable basis")).toHaveValue("The process was formally retired.");
  await expect(page.getByText("Accepted-Risk details")).toHaveCount(0);
});
