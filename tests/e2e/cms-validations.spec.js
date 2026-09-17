import { expect, test } from "@playwright/test";

const caseId = 73;
const validationId = 501;

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

function recommendation() {
  return {
    id: caseId,
    cmsRecommendationCode: "CMS-REC-000073",
    status: "MONITORING",
    lockVersion: 7,
    recommendation: "Standardize permit review controls and retain approval evidence.",
    responsibleOffice: { id: 2, code: "BPLD", name: "Business Permits and Licensing Division" },
    effectiveTargetDate: "2026-12-31",
    validationSummary: { hasValidationReviews: true, latestValidationVersionStatus: "FINALIZED", latestFinalizedConclusion: "IMPLEMENTED" },
  };
}

function validation() {
  const version = {
    id: 9001,
    displayCode: "VAL-CMS-REC-000073-001-V1",
    versionNumber: 1,
    status: "FINALIZED",
    validationScope: "Review corrective action implementation.",
    validationObjectives: "Determine whether the recommendation was implemented.",
    methodologySummary: "Inspection and inquiry.",
    overallWorkPerformed: "Validated the submitted evidence.",
    overallEvidenceSummary: "Evidence was assessed against the criteria.",
    limitations: null,
    professionalJudgmentRationale: "Sufficient appropriate evidence supports the conclusion.",
    proposedConclusionCode: "IMPLEMENTED",
    finalConclusionCode: "IMPLEMENTED",
    validatedCompletionPercentage: 100,
    validator: { id: 10, name: "Independent Validator" },
    finalizer: { id: 11, name: "CIAS Management" },
    availableActions: [],
    validationItems: [],
    evidenceAssessments: [],
    validatorEvidence: [],
    completeness: { complete: true, errors: {} },
    lockVersion: 3,
    isCurrent: true,
    isFinalizedCurrent: true,
    isHistorical: false,
    createdAt: "2026-08-01T08:00:00+08:00",
  };
  return {
    id: validationId,
    caseId,
    displayCode: "VAL-CMS-REC-000073-001",
    validationSequence: 1,
    acceptedActionPlanVersionId: 101,
    recordedProgressUpdateVersionId: 1201,
    currentVersionId: version.id,
    finalizedVersionId: version.id,
    lockVersion: 4,
    currentPrimaryValidator: { id: 10, name: "Independent Validator" },
    assignments: [],
    currentVersion: version,
    finalizedVersion: version,
    versions: [version],
    sourceContext: {
      recommendationCode: "CMS-REC-000073",
      recommendation: recommendation().recommendation,
      responsibleOffice: recommendation().responsibleOffice,
      caseStatus: "IMPLEMENTED",
      managementReportedPercentage: 100,
      managementEvidenceCount: 2,
      activeComplianceMonitor: { name: "Compliance Monitor" },
    },
  };
}

function validationOptions(overrides = {}) {
  return {
    caseContext: { ...recommendation(), cmsRecommendationCode: recommendation().cmsRecommendationCode },
    eligibleRecordedProgressUpdates: [{ id: 801, displayCode: "CMS-UPD-000073-001", reportingPeriodStart: "2026-08-01", reportingPeriodEnd: "2026-09-30", recordedVersionId: 1201, recordedVersionNumber: 1, managementReportedPercentage: 75, evidenceCount: 1, acceptedActionPlanVersion: { id: 101, versionNumber: 1 } }],
    eligibleValidators: [{ id: 10, employeeId: "CIAS-EMP-001", name: "Independent Validator", initials: "IV" }],
    unavailableReasons: [],
    ...overrides,
  };
}

async function mockList(page, options = validationOptions()) {
  await page.route(new RegExp(`/api/cms/recommendations/${caseId}$`), (route) => json(route, { recommendation: recommendation() }));
  await page.route(new RegExp(`/api/cms/recommendations/${caseId}/validations$`), (route) => json(route, { validations: [], caseContext: { ...recommendation(), cmsRecommendationCode: recommendation().cmsRecommendationCode }, permittedActions: ["create"] }));
  await page.route(new RegExp(`/api/cms/recommendations/${caseId}/validation-options$`), (route) => json(route, options));
}

test("opens the recommendation-specific Validation Review list and explains the safe creation boundary", async ({ page }) => {
  await signIn(page);
  await mockList(page, validationOptions({ eligibleRecordedProgressUpdates: [], eligibleValidators: [], unavailableReasons: ["No latest current recorded Progress Update Version is eligible for validation.", "No active, unlocked, independently eligible professional validator is available."] }));
  await page.goto(`/compliance-management/recommendations/${caseId}/validations`);
  await expect(page.locator("h2").filter({ hasText: "Independent Validation" })).toBeVisible();
  await expect(page.getByRole("heading", { name: "No Validation Reviews yet" })).toBeVisible();
  await expect(page.getByText(/No active, unlocked, independently eligible professional validator is available/i)).toBeVisible();
});

test("loads only safe creation options and submits the exact validation create payload", async ({ page }) => {
  await signIn(page);
  await mockList(page);
  let payload;
  await page.route(new RegExp(`/api/cms/recommendations/${caseId}/validations$`), async (route) => {
    if (route.request().method() !== "POST") return route.fallback();
    payload = route.request().postDataJSON();
    await json(route, { validation: { ...validation(), id: validationId } }, 201);
  });
  await page.goto(`/compliance-management/recommendations/${caseId}/validations`);
  await page.getByRole("button", { name: "Create Validation Review" }).click();
  await expect(page.getByRole("option", { name: /Independent Validator/ })).toHaveCount(1);
  await expect(page.getByRole("option", { name: /CMS-UPD-000073-001/ })).toHaveCount(1);
  await expect(page.getByRole("option", { name: /CIAS-EMP-001/ })).toHaveCount(1);
  await page.getByLabel("Recorded Progress Update").selectOption("1201");
  await page.getByLabel("Primary Validator").selectOption("10");
  await page.getByLabel("Assignment reason").fill("Independent validation assignment.");
  await page.getByRole("button", { name: "Create and assign" }).click();
  await expect.poll(() => payload?.recordedProgressUpdateVersionId).toBe(1201);
  await expect(payload.validatorUserId).toBe(10);
});

test("renders backend independence errors without exposing unrestricted users", async ({ page }) => {
  await signIn(page);
  await mockList(page);
  await page.route(new RegExp(`/api/cms/recommendations/${caseId}/validations$`), async (route) => {
    if (route.request().method() !== "POST") return route.fallback();
    await route.fulfill({ status: 422, contentType: "application/json", body: JSON.stringify({ message: "The selected user has a prohibited management conflict.", errors: { validatorUserId: ["The selected user has a prohibited management conflict."] } }) });
  });
  await page.goto(`/compliance-management/recommendations/${caseId}/validations`);
  await page.getByRole("button", { name: "Create Validation Review" }).click();
  await page.getByLabel("Recorded Progress Update").selectOption("1201");
  await page.getByLabel("Primary Validator").selectOption("10");
  await page.getByLabel("Assignment reason").fill("Independent validation assignment.");
  await page.getByRole("button", { name: "Create and assign" }).click();
  await expect(page.getByLabel("Create Validation Review").getByText(/prohibited management conflict/i)).toBeVisible();
});

test("keeps creation unavailable when the scoped options contract denies permission", async ({ page }) => {
  await signIn(page);
  await mockList(page);
  await page.route(new RegExp(`/api/cms/recommendations/${caseId}/validation-options$`), async (route) => {
    await route.fulfill({ status: 403, contentType: "application/json", body: JSON.stringify({ message: "This action is unauthorized." }) });
  });
  await page.goto(`/compliance-management/recommendations/${caseId}/validations`);
  await expect(page.getByText(/unauthorized|Validation Reviews could not be loaded/i)).toBeVisible();
  await expect(page.getByRole("button", { name: "Create Validation Review" })).toHaveCount(0);
  await expect(page.getByLabel("Primary Validator")).toHaveCount(0);
});

test("renders an immutable finalized validation and keeps implemented distinct from closure", async ({ page }) => {
  await signIn(page);
  await page.route(new RegExp(`/api/cms/validations/${validationId}$`), (route) => json(route, { validation: validation() }));
  await page.route(new RegExp(`/api/cms/validations/${validationId}/assignments$`), (route) => json(route, { assignments: [], lockVersion: 4 }));
  await page.route(new RegExp(`/api/cms/recommendations/${caseId}/validation-options$`), (route) => json(route, validationOptions()));
  await page.route(/\/api\/documents(?:\?.*)?$/, (route) => json(route, { documents: [], documentTypes: [], confidentialityLevels: [] }));
  await page.goto(`/compliance-management/recommendations/${caseId}/validations/${validationId}`);
  await expect(page.getByText("Implemented · closure pending")).toBeVisible();
  await expect(page.getByText(/closure not yet completed/i)).toBeVisible();
  await expect(page.getByRole("tab", { name: "Procedures & Conclusions" })).toBeVisible();
  await expect(page.getByRole("button", { name: "Submit" })).toHaveCount(0);
});
