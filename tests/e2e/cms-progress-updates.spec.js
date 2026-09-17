import { expect, test } from "@playwright/test";

const caseId = 73;
const updateId = 901;
const ownerOffice = {
  id: 2,
  code: "BPLD",
  name: "Business Permits and Licensing Division",
  acronym: "BPLD",
};

async function signIn(page, employeeId) {
  await page.goto("/login");
  await page.getByLabel("Employee ID").fill(employeeId);
  await page.locator("#password").fill("lala");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/dashboard$/);
}

async function openAuthenticatedPage(page, path) {
  const restoredSession = page.waitForResponse(
    (response) => response.url().endsWith("/api/me") && response.status() === 200,
    { timeout: 30_000 },
  );
  await page.goto(path, { waitUntil: "domcontentloaded" });
  await restoredSession;
  await expect(page.getByText("Loading AGIS", { exact: true })).toBeHidden({ timeout: 30_000 });
}

function milestone(id, sequenceNumber, title, weightPercentage = 50) {
  return {
    id,
    sequenceNumber,
    title,
    description: `${title} description`,
    expectedOutput: `${title} output`,
    responsibleOffice: ownerOffice,
    responsibleUser: { id: 22, employeeId: "BPLD-HEAD", name: "BPLD Office Head", initials: "BO" },
    plannedStartDate: "2026-08-01",
    plannedTargetDate: "2026-10-31",
    weightPercentage,
    displayOrder: sequenceNumber,
  };
}

function recommendation() {
  return {
    id: caseId,
    cmsRecommendationCode: "CMS-REC-000073",
    status: "MONITORING",
    lockVersion: 7,
    recommendation: "Standardize permit review controls and retain evidence of supervisory approval.",
    responsibleOffice: ownerOffice,
    effectiveTargetDate: "2026-12-31",
    currentMonitor: { user: { name: "Internal Auditor" } },
    actionPlanSummary: { hasActionPlan: true, acceptedVersionId: 101 },
  };
}

function version(overrides = {}) {
  return {
    id: 1201,
    displayCode: "CMS-UPD-000073-001-V1",
    versionNumber: 1,
    previousVersionId: null,
    status: "DRAFT",
    accomplishmentSummary: "",
    managementReportedOverallPercentage: 0,
    systemCalculatedWeightedReportedPercentage: 0,
    baselineWeighted: true,
    issuesAndConstraints: "",
    correctiveActionsForDelays: "",
    nextSteps: "",
    forecastCompletionDate: "",
    managementDeclaration: "",
    generalEvidenceExplanation: "",
    preparedBy: { id: 22, name: "BPLD Office Head", initials: "BO" },
    submittedBy: null,
    submittedAt: null,
    reviewStartedBy: null,
    reviewStartedAt: null,
    returnReason: null,
    recordingComment: null,
    lockVersion: 1,
    milestoneProgress: [
      {
        id: 3001,
        actionPlanMilestoneId: 501,
        milestoneSequence: 1,
        milestoneSnapshot: milestone(501, 1, "Approve procedure"),
        managementReportedStatusCode: "NOT_STARTED",
        managementReportedPercentage: 0,
        accomplishmentDescription: "",
        issuesAndConstraints: "",
        nextStep: "",
        forecastCompletionDate: "",
        noEvidenceExplanation: "",
        displayOrder: 1,
        evidenceCount: 0,
      },
      {
        id: 3002,
        actionPlanMilestoneId: 502,
        milestoneSequence: 2,
        milestoneSnapshot: milestone(502, 2, "Orient staff"),
        managementReportedStatusCode: "NOT_STARTED",
        managementReportedPercentage: 0,
        accomplishmentDescription: "",
        issuesAndConstraints: "",
        nextStep: "",
        forecastCompletionDate: "",
        noEvidenceExplanation: "",
        displayOrder: 2,
        evidenceCount: 0,
      },
    ],
    evidence: [],
    completeness: { complete: false, errors: { accomplishmentSummary: ["Required"] }, evidenceCount: 0, milestoneProgressCount: 2 },
    availableActions: ["update", "submit", "upload-evidence"],
    isCurrent: true,
    isRecordedCurrent: false,
    isSuperseded: false,
    reportedCompleteAwaitingValidation: false,
    notIndependentlyValidated: true,
    createdAt: "2026-08-01T08:00:00+08:00",
    ...overrides,
  };
}

function family(overrides = {}) {
  const current = version();
  return {
    id: updateId,
    caseId,
    displayCode: "CMS-UPD-000073-001",
    reportingSequence: 1,
    reportingPeriodStart: "2026-08-01",
    reportingPeriodEnd: "2026-09-30",
    actionPlanId: 31,
    acceptedActionPlanVersionId: 101,
    acceptedActionPlanVersion: { id: 101, versionNumber: 1, acceptedAt: "2026-07-31T08:00:00+08:00", milestoneCount: 2 },
    currentVersionId: current.id,
    recordedVersionId: null,
    lockVersion: 2,
    currentVersion: current,
    recordedVersion: null,
    versions: [current],
    caseContext: { id: caseId, cmsRecommendationCode: "CMS-REC-000073", status: "MONITORING", recommendation: recommendation().recommendation, responsibleOffice: ownerOffice, effectiveTargetDate: "2026-12-31", currentMonitor: { name: "Internal Auditor" } },
    notIndependentlyValidated: true,
    ...overrides,
  };
}

async function fulfillJson(route, data, status = 200) {
  await route.fulfill({ status, contentType: "application/json", body: JSON.stringify(data) });
}

async function mockProgress(page, currentFamily = family()) {
  await page.route(new RegExp(`/api/cms/recommendations/${caseId}$`), (route) => fulfillJson(route, { success: true, data: { recommendation: recommendation() } }));
  await page.route(new RegExp(`/api/cms/recommendations/${caseId}/progress-updates$`), (route) => {
    if (route.request().method() !== "GET") return route.fallback();
    return fulfillJson(route, { success: true, data: { progressUpdates: [currentFamily], caseContext: currentFamily.caseContext, permittedActions: ["create"], notIndependentlyValidated: true } });
  });
  await page.route(new RegExp(`/api/cms/recommendations/${caseId}/action-plan$`), (route) => fulfillJson(route, { success: true, data: { acceptedVersion: { id: 101, versionNumber: 1, milestones: [milestone(501, 1, "Approve procedure"), milestone(502, 2, "Orient staff")] }, caseContext: currentFamily.caseContext } }));
  await page.route(new RegExp(`/api/cms/progress-updates/${updateId}$`), (route) => fulfillJson(route, { success: true, data: { progressUpdate: currentFamily } }));
  await page.route(/\/api\/documents(?:\?.*)?$/, (route) => fulfillJson(route, { success: true, data: { documents: [], documentTypes: [], confidentialityLevels: [{ id: 1, code: "INTERNAL", label: "Internal" }], linkOptions: [] } }));
}

test("opens the recommendation-specific progress registry and creates a draft against the accepted baseline", async ({ page }) => {
  await signIn(page, "BPLD-HEAD");
  await mockProgress(page, family({ currentVersion: null, versions: [] }));
  let createPayload;
  await page.route(new RegExp(`/api/cms/recommendations/${caseId}/progress-updates$`), async (route) => {
    if (route.request().method() === "POST") {
      createPayload = route.request().postDataJSON();
      await fulfillJson(route, { success: true, data: { progressUpdate: family() } }, 201);
      return;
    }
    await fulfillJson(route, { success: true, data: { progressUpdates: [], caseContext: family().caseContext, permittedActions: ["create"], notIndependentlyValidated: true } });
  });
  await openAuthenticatedPage(page, `/compliance-management/recommendations/${caseId}/progress-updates`);
  await expect(page.getByRole("heading", { name: "No Progress Updates yet" })).toBeVisible();
  await page.getByRole("button", { name: "Create Progress Update" }).first().click();
  await page.getByLabel("Reporting period start").fill("2026-08-01");
  await page.getByLabel("Reporting period end").fill("2026-09-30");
  await page.getByLabel("Accomplishment summary").fill("Management reports that preparation has started.");
  await expect(page.getByText("Approve procedure", { exact: true })).toBeVisible();
  await page.getByRole("button", { name: "Create draft" }).click();
  await expect.poll(() => createPayload?.acceptedActionPlanVersionId).toBeUndefined();
  await expect(page).toHaveURL(new RegExp(`/progress-updates/${updateId}$`));
});

test("renders a draft workspace and keeps reported completion separate from validation", async ({ page }) => {
  await signIn(page, "BPLD-HEAD");
  await mockProgress(page);
  await openAuthenticatedPage(page, `/compliance-management/recommendations/${caseId}/progress-updates/${updateId}`);
  await expect(page.getByText("Management progress report")).toBeVisible();
  await expect(page.getByText("Awaiting independent validation").first()).toBeVisible();
  await expect(page.getByRole("tab", { name: "Milestone Progress" })).toBeVisible();
  await page.getByRole("tab", { name: "Milestone Progress" }).click();
  await expect(page.getByText("Approve procedure", { exact: true })).toBeVisible();
  await page.getByRole("tab", { name: "Evidence" }).click();
  await expect(page.getByText("Supporting evidence").first()).toBeVisible();
});
