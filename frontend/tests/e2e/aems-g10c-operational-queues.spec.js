import { expect, test } from "@playwright/test";

async function signIn(page) {
  await page.goto("/login");
  await page.getByLabel("Employee ID").fill("CIAS-HEAD-001");
  await page.locator("#password").fill("lala");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/dashboard$/);
}

const engagement = { id: 9730, engagementCode: "AEMS-G10C-001", title: "Operational queue contract", status: "FIELDWORK" };

test("G10C exposes dedicated queue tabs, actions, and scope selection", async ({ page }) => {
  const task = { id: 1, taskCode: "TASK-G10C-001", taskType: "REVIEW", title: "Review evidence", status: "OPEN", dueState: "OVERDUE", dueAt: "2026-08-12T08:00:00Z", lockVersion: 2, assignedTo: { name: "Audit Supervisor" } };
  const queue = { engagement, tasks: [task], reviewNotes: [{ id: 2, noteCode: "RN-G10C-001", versionNumber: 1, status: "DRAFT", content: "Check the evidence trail.", lockVersion: 1, createdBy: { name: "Auditor" } }], dueProcess: [{ id: 3, eventCode: "DP-G10C-001", eventType: "REMINDER", content: "Response reminder.", dueDate: "2026-08-15", finding: { id: 9, finding_code: "FND-G10C-001" } }], escalationCandidates: [{ id: 4, candidateCode: "ESC-G10C-001", candidateType: "TASK_OVERDUE", status: "OPEN", reason: "Task is overdue.", lockVersion: 1, detectedAt: "2026-08-12T08:00:00Z" }] };
  await page.route("**/api/aems/engagements?*", (route) => route.fulfill({ contentType: "application/json", body: JSON.stringify({ success: true, data: { engagements: [engagement], pagination: {}, summary: {} } }) }));
  await page.route("**/api/aems/engagements/9730/work-queue", (route) => route.fulfill({ contentType: "application/json", body: JSON.stringify({ success: true, data: queue }) }));
  await page.route("**/api/aems/engagements/9730/team", (route) => route.fulfill({ contentType: "application/json", body: JSON.stringify({ success: true, data: { teamMembers: [] } }) }));
  await page.route("**/api/notifications/recent", (route) => route.fulfill({ contentType: "application/json", body: JSON.stringify({ success: true, data: [] }) }));
  await signIn(page);
  await page.goto("/audit-engagement-management/work-queues?engagementId=9730");
  await expect(page.getByTestId("aems-operational-queues")).toBeVisible();
  await expect(page.getByText("Dedicated Tasks workspace", { exact: true })).toBeVisible();
  await expect(page.getByText("Overdue", { exact: true })).toBeVisible();
  await page.getByRole("tab", { name: /Review Notes/ }).click();
  await expect(page.getByText("Dedicated Review Notes workspace", { exact: true })).toBeVisible();
  await page.getByRole("tab", { name: /Due Process/ }).click();
  await expect(page.getByText("Due-process work queue", { exact: true })).toBeVisible();
  await page.getByRole("tab", { name: /Escalation Candidates/ }).click();
  await expect(page.getByText("Escalation-candidate queue", { exact: true })).toBeVisible();
});

test("G10C exposes calendar and protected register surfaces", async ({ page }) => {
  await page.route("**/api/aems/engagements?*", (route) => route.fulfill({ contentType: "application/json", body: JSON.stringify({ success: true, data: { engagements: [engagement], pagination: {}, summary: {} } }) }));
  await page.route("**/api/aems/engagements/9730/calendar", (route) => route.fulfill({ contentType: "application/json", body: JSON.stringify({ success: true, data: { summary: { total: 1, open: 1, overdue: 0, completed: 0 }, milestones: [] } }) }));
  await page.route("**/api/aems/engagements/9730/records", (route) => route.fulfill({ contentType: "application/json", body: JSON.stringify({ success: true, data: { summary: { total: 0 }, items: [] } }) }));
  await page.route("**/api/aems/engagements/9730/reports", (route) => route.fulfill({ contentType: "application/json", body: JSON.stringify({ success: true, data: { reports: [] } }) }));
  await signIn(page);
  await page.goto("/audit-engagement-management/calendar?engagementId=9730");
  await expect(page.getByTestId("aems-calendar-page")).toBeVisible();
  await expect(page.getByText("Audit Calendar and Milestones", { exact: true }).first()).toBeVisible();
  await page.goto("/audit-engagement-management/registers?engagementId=9730");
  await expect(page.getByTestId("aems-registers-page")).toBeVisible();
  await expect(page.getByText("AEMS Registers and Protected Exports", { exact: true })).toBeVisible();
  await expect(page.getByRole("button", { name: "Work queues CSV" })).toBeVisible();
});
