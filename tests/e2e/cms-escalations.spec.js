import { expect, test } from "@playwright/test";

const caseId = 73;
const escalationId = 1201;
const noticeId = 1202;

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

function escalation(overrides = {}) {
  const notice = { id: noticeId, versionNumber: 1, status: "DRAFT", subject: "Escalation notice subject", escalationSummary: "A formal escalation is being prepared.", basisAndContext: "The source context requires attention.", requiredManagementActions: "Provide a management response.", requiredResponseContents: "Describe cause and corrective actions.", responseDueDate: "2026-10-31", additionalTriggerExplanation: "", managementAttentionRequested: true, immutable: false, lockVersion: 1, availableActions: ["update", "submit"], evidence: [], recipients: [], acknowledgements: [] };
  return { id: escalationId, caseId, displayCode: "ESC-CMS-REC-000073-001", sequence: 1, primaryTriggerCode: "OVERDUE_TARGET", triggerSnapshot: { overdueDays: 12, capturedAt: "2026-08-03T00:00:00Z" }, sourceEffectiveTargetDate: "2026-07-31", sourceCaseStatus: "MONITORING", operationalStatus: "PREPARATION", lockVersion: 1, currentNotice: notice, issuedNotice: null, response: null, resolution: null, noticeVersions: [notice], availableActions: ["update", "submit"], ...overrides };
}

async function mockList(page, records = []) {
  await page.route(new RegExp(`/api/cms/recommendations/${caseId}/escalations$`), (route) => json(route, { escalations: records, caseContext: { id: caseId, cmsRecommendationCode: "CMS-REC-000073", status: "MONITORING", originalTargetDate: "2026-07-31", effectiveTargetDate: "2026-07-31", overdue: true }, permittedActions: ["create"] }));
  await page.route(new RegExp(`/api/cms/recommendations/${caseId}/escalation-options$`), (route) => json(route, { creationAllowed: true, triggerCodes: ["OVERDUE_TARGET", "OTHER"], responseDatePolicy: null, caseContext: { id: caseId, status: "MONITORING", originalTargetDate: "2026-07-31", effectiveTargetDate: "2026-07-31", overdue: true } }));
}

test("renders the recommendation-scoped escalation list and empty state", async ({ page }) => {
  await signIn(page);
  await mockList(page);
  await page.goto(`/compliance-management/recommendations/${caseId}/escalations`);
  await expect(page.getByRole("heading", { name: "Escalations", exact: true })).toBeVisible();
  await expect(page.getByText("No escalations recorded")).toBeVisible();
  await expect(page.getByRole("button", { name: "Create escalation" }).first()).toBeVisible();
});

test("shows operational status separately from recommendation status", async ({ page }) => {
  await signIn(page);
  await mockList(page, [escalation()]);
  await page.goto(`/compliance-management/recommendations/${caseId}/escalations`);
  await expect(page.getByText("ESC-CMS-REC-000073-001")).toBeVisible();
  await expect(page.getByText("Preparation")).toBeVisible();
  await expect(page.getByText("Draft")).toBeVisible();
  await page.route(new RegExp(`/api/cms/escalations/${escalationId}$`), (route) => json(route, { escalation: escalation() }));
  await page.route(new RegExp(`/api/cms/recommendations/${caseId}/escalation-options$`), (route) => json(route, { triggerCodes: ["OVERDUE_TARGET", "OTHER"], caseContext: { id: caseId, status: "MONITORING", originalTargetDate: "2026-07-31", effectiveTargetDate: "2026-07-31", overdue: true } }));
  await page.getByRole("button", { name: /ESC-CMS-REC-000073-001/ }).click();
  await expect(page).toHaveURL(new RegExp(`/escalations/${escalationId}$`));
  await expect(page.getByText(/does not change the recommendation's implementation status/i)).toBeVisible();
});

test("sends the notice draft lock version and preserves read-only source context", async ({ page }) => {
  await signIn(page);
  await mockList(page, [escalation()]);
  await page.route(new RegExp(`/api/cms/escalations/${escalationId}$`), (route) => json(route, { escalation: escalation() }));
  await page.goto(`/compliance-management/recommendations/${caseId}/escalations/${escalationId}`);
  await page.getByRole("tab", { name: "Escalation Notice" }).click();
  await expect(page.getByText("Historical source snapshots are preserved")).toBeVisible();
  let payload;
  await page.route(new RegExp(`/api/cms/escalations/${escalationId}/notice-versions/${noticeId}$`), async (route) => { payload = route.request().postDataJSON(); await json(route, { escalation: escalation() }); });
  await page.getByLabel("Subject").fill("Updated escalation notice");
  await page.getByRole("button", { name: "Save draft" }).click();
  await expect.poll(() => payload?.lockVersion).toBe(1);
});
