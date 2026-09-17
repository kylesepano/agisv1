import { expect, test } from "@playwright/test";

async function signIn(page) {
  await page.goto("/login");
  await page.getByLabel("Employee ID").fill("CIAS-HEAD-001");
  await page.locator("#password").fill("lala");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/dashboard$/);
}

const engagement = { id: 8801, engagementCode: "AEMS-RPT-8801", title: "Reporting workspace contract", status: "REPORTING" };
const finding = { id: 8811, findingCode: "FND-RPT-001", title: "Finalized control finding", status: "FINALIZED", responsibleOffice: { name: "Records Office" }, recommendationCount: 1 };

test("reporting workspace exposes interim assembly and finalized-only reporting controls", async ({ page }) => {
  await signIn(page);
  await page.route("**/api/aems/report-workspaces", (route) => route.fulfill({ contentType: "application/json", body: JSON.stringify({ success: true, data: { engagements: [engagement] } }) }));
  await page.route("**/api/aems/engagements/8801/reports", (route) => route.fulfill({ contentType: "application/json", body: JSON.stringify({ success: true, data: { engagement, report: null, references: { findings: [finding], confidentialityLevels: [{ id: 1, code: "INTERNAL", label: "Internal" }], users: [], offices: [] } } }) }));
  await page.goto("/audit-engagement-management/reports?engagementId=8801");
  await expect(
    page.locator("main").getByRole("heading", {
      name: "Audit Reporting Workspace",
      exact: true,
    }),
  ).toBeVisible();
  await page.getByRole("button", { name: "Interim Report", exact: true }).click();
  await expect(page.getByRole("heading", { name: "Generate Interim Audit Report", exact: true })).toBeVisible();
  await expect(page.getByText("Quality checklist", { exact: true })).toBeVisible();
  await expect(page.getByText("Only eligible findings are included", { exact: true })).toBeVisible();
});

test("issued reporting versions expose protected download, comparison, and distribution review", async ({ page }) => {
  await signIn(page);
  await page.route("**/api/aems/report-workspaces", (route) => route.fulfill({ contentType: "application/json", body: JSON.stringify({ success: true, data: { engagements: [engagement] } }) }));
  const version = { id: 8821, versionNumber: 1, reportStage: "FINAL_REPORT", contentSnapshot: { executiveSummary: "Issued summary.", sections: [{ title: "Scope" }] }, checksumSha256: "abc123", fileSize: 20, isLocked: true, createdAt: "2026-08-12T00:00:00Z", createdBy: { name: "Auditor" }, findings: [finding], recipients: [{ id: 8831, recipientType: "OFFICE", office: { name: "Records Office" }, deliveryStatus: "SENT" }], reviewComments: [] };
  await page.route("**/api/aems/engagements/8801/reports", (route) => route.fulfill({ contentType: "application/json", body: JSON.stringify({ success: true, data: { engagement, report: { id: 8820, reportCode: "AR-AEMS-RPT-8801-01", title: "Issued report", reportStage: "FINAL_REPORT", status: "ISSUED", currentVersionNumber: 1, currentVersionId: 8821, confidentialityLevel: { label: "Internal" }, preparedBy: { name: "Auditor" }, lockVersion: 2, versions: [version], cmsTransfers: [], canDownloadCurrent: true }, references: { findings: [finding], confidentialityLevels: [{ id: 1, code: "INTERNAL", label: "Internal" }], users: [], offices: [] } } }) }));
  await page.goto("/audit-engagement-management/reports?engagementId=8801");
  await expect(page.getByText("Locked versions", { exact: true })).toBeVisible();
  await expect(page.getByRole("button", { name: "Protected PDF", exact: true })).toBeVisible();
  await expect(page.getByRole("button", { name: "Compare", exact: true })).toBeVisible();
  await expect(page.getByRole("button", { name: "Amend", exact: true })).toBeVisible();
  await expect(page.getByRole("button", { name: "Withdraw", exact: true })).toBeVisible();
});
