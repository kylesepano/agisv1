import { expect, test } from "@playwright/test";

async function signIn(page) {
  await page.goto("/login");
  await page.getByLabel("Employee ID").fill("CIAS-HEAD-001");
  await page.locator("#password").fill("lala");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/dashboard$/);
}

const engagement = { id: 9740, engagementCode: "AEMS-G10D-001", title: "Records and closure contract", status: "COMPLETED" };
const closure = {
  engagement: { ...engagement, lockVersion: 3, isClosed: false },
  closure: null,
  readiness: { ready: false, percentage: 72, blockers: [{ checklistCode: "RETENTION_METADATA", description: "Approved retention metadata is required." }] },
  evaluatedChecklist: [],
  retention: { id: 40, archiveStatus: "ACTIVE", destructionEligibilityStatus: "NOT_REVIEWED", legalHoldFlag: true, lockVersion: 1 },
  retentionOptions: { offices: [], custodians: [] },
  cms: { total: 0, transferred: 0, excluded: 0 },
  permittedActions: [],
};
const records = { items: [], blockers: [{ code: "LEGAL_HOLD", description: "Active legal hold blocks closure." }], retention: closure.retention, retentionReadiness: { ready: false, blockers: ["Legal hold is active."] } };

test("G10D exposes completed-versus-closed status and records controls", async ({ page }) => {
  await page.route("**/api/aems/engagements?*", (route) => route.fulfill({ contentType: "application/json", body: JSON.stringify({ success: true, data: { engagements: [engagement], pagination: {}, summary: {} } }) }));
  await page.route("**/api/aems/engagements/9740/closure", (route) => route.fulfill({ contentType: "application/json", body: JSON.stringify({ success: true, data: closure }) }));
  await page.route("**/api/aems/engagements/9740/records", (route) => route.fulfill({ contentType: "application/json", body: JSON.stringify({ success: true, data: records }) }));
  await signIn(page);
  await page.goto("/audit-engagement-management/records-closure?engagementId=9740");
  await expect(page.getByTestId("aems-records-closure-page")).toBeVisible();
  await expect(page.getByText("Completed", { exact: true }).first()).toBeVisible();
  await expect(page.getByText("Substantive audit work is complete", { exact: false })).toBeVisible();
  await expect(page.getByText("72%", { exact: true }).first()).toBeVisible();
  await page.getByRole("button", { name: /Records & Disposition/ }).click();
  await expect(page.getByTestId("aems-records-workspace")).toBeVisible();
  await expect(page.getByText("Active legal hold blocks archive and disposition.", { exact: true })).toBeVisible();
  await expect(page.getByText("Closure blockers", { exact: true })).toBeVisible();
});
