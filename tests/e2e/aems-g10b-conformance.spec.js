import { expect, test } from "@playwright/test";

async function signIn(page) {
  await page.goto("/login");
  await page.getByLabel("Employee ID").fill("CIAS-HEAD-001");
  await page.locator("#password").fill("lala");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/dashboard$/);
}

test("G10B execution exposes the extended fieldwork taxonomy", async ({ page }) => {
  const engagement = { id: 9710, engagementCode: "AEMS-G10B-001", title: "G10B UI contract", status: "FIELDWORK" };
  await page.route("**/api/aems/engagements?*", (route) => route.fulfill({ contentType: "application/json", body: JSON.stringify({ success: true, data: { engagements: [engagement], pagination: {}, summary: {} } }) }));
  await page.route("**/api/aems/engagements/9710/fieldwork", (route) => route.fulfill({ contentType: "application/json", body: JSON.stringify({ success: true, data: { engagement, recordTypes: ["INTERVIEW", "OBSERVATION", "WALKTHROUGH", "INSPECTION", "TESTING", "SAMPLING", "ANALYSIS", "INQUIRY", "MEETING", "FIELD_NOTE", "OTHER"], executionStatuses: ["PLANNED", "IN_PROGRESS", "COMPLETED"], auditAreas: [], auditFocuses: [], procedures: [], records: [], traceability: { complete: true } } }) }));
  await page.route("**/api/aems/engagements/9710/working-papers", (route) => route.fulfill({ contentType: "application/json", body: JSON.stringify({ success: true, data: { workingPapers: [], evidence: [] } }) }));
  await page.route("**/api/aems/engagements/9710/findings-workspace", (route) => route.fulfill({ contentType: "application/json", body: JSON.stringify({ success: true, data: { engagement, issues: [], findings: [], offices: [], riskRatings: [], workingPaperVersions: [], evidence: [], procedures: [] } }) }));
  await signIn(page);
  await page.goto("/audit-engagement-management/execution?engagementId=9710");
  await expect(page.getByTestId("aems-execution-workspace")).toBeVisible();
  await page.getByRole("button", { name: "New record" }).click();
  await expect(page.getByRole("option", { name: "Inquiry" })).toHaveCount(2);
  await expect(page.getByRole("option", { name: "Meeting" })).toHaveCount(2);
  await expect(page.getByRole("option", { name: "Field Note" })).toHaveCount(2);
  await expect(page.getByRole("option", { name: "Other" })).toHaveCount(2);
});

test("G10B finding detail renders the procedure criteria chain", async ({ page }) => {
  const engagement = { id: 9711, engagementCode: "AEMS-G10B-002", title: "G10B finding contract", status: "FIELDWORK" };
  const finding = { id: 1, findingCode: "FND-G10B-001", title: "Criteria traceability finding", status: "FINALIZED", revisionNumber: 0, isCurrentRevision: true, criteria: "Policy criteria", condition: "Condition", cause: "Cause", effect: "Effect", conclusion: "Conclusion", riskRating: { label: "High" }, responsibleOffice: { name: "Office" }, workingPaperVersions: [], evidence: [], fieldworkRecords: [], procedures: [{ id: 91, procedureCode: "PROC-G10B-01", objective: "Review control", auditCriteria: "Policy criteria", criteriaReference: "Policy criteria", traceabilityNote: "Criteria linked from approved audit procedure." }], recommendations: [], managementResponses: [], revisions: [], history: [], transmittals: [], lockVersion: 2 };
  const workspace = { engagement, issues: [], findings: [finding], offices: [], riskRatings: [], workingPaperVersions: [], evidence: [], procedures: [{ id: 91, procedureCode: "PROC-G10B-01", objective: "Review control", auditCriteria: "Policy criteria", status: "COMPLETED" }], agreementPositions: ["AGREE"], rejoinderDispositions: ["ACCEPT"] };
  await page.route("**/api/aems/findings-workspaces", (route) => route.fulfill({ contentType: "application/json", body: JSON.stringify({ success: true, data: { engagements: [engagement] } }) }));
  await page.route("**/api/aems/engagements/9711/findings-workspace", (route) => route.fulfill({ contentType: "application/json", body: JSON.stringify({ success: true, data: workspace }) }));
  await signIn(page);
  await page.goto("/audit-engagement-management/findings?engagementId=9711");
  await expect(page.getByText("Procedure and criteria traceability", { exact: true })).toBeVisible();
  await expect(page.getByText("PROC-G10B-01", { exact: true })).toBeVisible();
  await expect(page.getByText("Policy criteria", { exact: true }).first()).toBeVisible();
});
