import { expect, test } from "@playwright/test";

const caseId = 73;
const extensionId = 901;
const versionId = 902;

async function signIn(page) {
  await page.goto("/login");
  await page.getByLabel("Employee ID").fill("AUDITEE-001");
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
    recommendationCode: "REC-073",
    recommendation: "Standardize permit review controls and retain approval evidence.",
    status: "MONITORING",
    lockVersion: 7,
    responsibleOffice: { id: 2, code: "BPLD", name: "Business Permits and Licensing Division" },
    originalTargetDate: "2026-09-30",
    effectiveTargetDate: "2026-09-30",
    targetDateExtensionSummary: { requestCount: 0, openRequestCount: 0, originalTargetDate: "2026-09-30", currentEffectiveTargetDate: "2026-09-30" },
  };
}

function version(overrides = {}) {
  return {
    id: versionId,
    displayCode: "EXT-CMS-REC-000073-001-V1",
    versionNumber: 1,
    status: "DRAFT",
    baselineEffectiveTargetDate: "2026-09-30",
    requestedTargetDate: "2026-10-31",
    extensionDays: 31,
    extensionJustification: "Additional time is required to complete corrective actions.",
    causeOfDelay: "Procurement timing.",
    actionsAlreadyTaken: "Control owners have started the revised procedure.",
    remainingActions: "Complete implementation and verification.",
    recoveryPlan: "Weekly management checkpoints.",
    impactIfNotApproved: "The corrective action may remain incomplete.",
    revisedScheduleSummary: "Complete by October 31.",
    managementProgressSummary: "Management reported 60% progress.",
    noEvidenceExplanation: null,
    activeEvidenceLinks: [],
    evidenceLinks: [],
    availableActions: ["update", "submit", "upload-evidence"],
    lockVersion: 1,
    ...overrides,
  };
}

function extension(overrides = {}) {
  return { id: extensionId, displayCode: "EXT-CMS-REC-000073-001", requestSequence: 1, baselineEffectiveTargetDate: "2026-09-30", currentVersionId: versionId, currentVersion: version(), versions: [version()], lockVersion: 1, ...overrides };
}

async function mockWorkspace(page, detail = false) {
  await page.route(new RegExp(`/api/cms/recommendations/${caseId}$`), (route) => json(route, { recommendation: recommendation() }));
  await page.route(new RegExp(`/api/cms/recommendations/${caseId}/extensions$`), (route) => json(route, { extensions: detail ? [extension()] : [], caseContext: { id: caseId, cmsRecommendationCode: recommendation().cmsRecommendationCode, status: "MONITORING", originalTargetDate: "2026-09-30", effectiveTargetDate: "2026-09-30", responsibleOffice: recommendation().responsibleOffice }, permittedActions: ["create"] }));
  await page.route(new RegExp(`/api/cms/recommendations/${caseId}/extension-options$`), (route) => json(route, { creationAllowed: true, caseLockVersion: 7, earliestAllowedRequestedDate: "2026-10-01", maximumExtensionDate: null, maximumExtensionDays: null, retroactiveAllowed: false, permittedActions: ["create"], unavailableReasons: [], caseContext: { id: caseId, status: "MONITORING", originalTargetDate: "2026-09-30", effectiveTargetDate: "2026-09-30" } }));
  if (detail) await page.route(new RegExp(`/api/cms/extensions/${extensionId}$`), (route) => json(route, { extension: extension(), caseContext: { id: caseId, cmsRecommendationCode: recommendation().cmsRecommendationCode, status: "MONITORING", originalTargetDate: "2026-09-30", effectiveTargetDate: "2026-09-30", responsibleOffice: recommendation().responsibleOffice } }));
}

test("opens the recommendation-specific extension list and keeps pending dates distinct", async ({ page }) => {
  await signIn(page);
  await mockWorkspace(page);
  await page.goto(`/compliance-management/recommendations/${caseId}/extensions`);
  await expect(page.getByRole("heading", { name: "Target-Date Extensions" })).toBeVisible();
  await expect(page.getByRole("heading", { name: "No target-date extension requests" })).toBeVisible();
  await expect(page.getByText(/Current effective target/i)).toBeVisible();
  await expect(page.getByRole("button", { name: "Create Extension Request" })).toBeVisible();
  await expect(page.getByText(/requested date is a proposal/i)).toHaveCount(0);
});

test("creates a draft with the requested date and preserves the effective date", async ({ page }) => {
  await signIn(page);
  await mockWorkspace(page);
  let payload;
  await page.route(new RegExp(`/api/cms/recommendations/${caseId}/extensions$`), async (route) => {
    if (route.request().method() !== "POST") return route.fallback();
    payload = route.request().postDataJSON();
    await json(route, { extension: extension() }, 201);
  });
  await page.goto(`/compliance-management/recommendations/${caseId}/extensions`);
  await page.getByRole("button", { name: "Create Extension Request" }).click();
  await page.getByLabel("Requested target date").fill("2026-10-31");
  await page.getByLabel("Extension justification").fill("Additional time is required to complete corrective actions.");
  await page.getByRole("button", { name: "Save draft" }).click();
  await expect.poll(() => payload?.requestedTargetDate).toBe("2026-10-31");
  await expect.poll(() => payload?.lockVersion).toBe(7);
  await expect(page).toHaveURL(new RegExp(`/extensions/${extensionId}$`));
});

test("renders an immutable pending draft detail without presenting it as approved", async ({ page }) => {
  await signIn(page);
  await mockWorkspace(page, true);
  await page.goto(`/compliance-management/recommendations/${caseId}/extensions/${extensionId}`);
  await expect(page.getByText("Effective date unchanged")).toBeVisible();
  await expect(page.getByText("Requested target (pending)")).toBeVisible();
  await expect(page.getByText("Not an approved target")).toBeVisible();
  await expect(page.getByRole("tab", { name: "Supporting Evidence" })).toBeVisible();
  await expect(page.getByRole("button", { name: "Submit" })).toBeVisible();
});
