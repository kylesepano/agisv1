import { expect, test } from "@playwright/test";

async function signIn(page) {
  await page.goto("/login");
  await page.getByLabel("Employee ID").fill("CIAS-HEAD-001");
  await page.locator("#password").fill("lala");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/dashboard$/);
}

const engagement = { id: 7701, engagementCode: "AEMS-UI-7701", title: "Conference dialogue UI contract", status: "CONFERENCES" };
const finding = {
  id: 7711,
  findingCode: "FND-7701-001",
  title: "Control response is pending",
  status: "AWAITING_MANAGEMENT_RESPONSE",
  responsibleOffice: { id: 4, name: "Records Office" },
  managementResponseDueDate: "2026-08-01",
  managementResponses: [{
    id: 7721,
    responseCode: "MR-7701-001",
    versionNumber: 1,
    agreementPosition: "PARTIALLY_AGREE",
    managementComment: "Management accepts the observation and proposes a corrective action.",
    proposedAction: "Introduce a monthly supervisory checklist.",
    status: "CLARIFICATION_REQUESTED",
    clarificationRequest: "Please provide the target implementation date.",
    clarificationRequestedAt: "2026-08-05T08:00:00Z",
    createdAt: "2026-08-02T08:00:00Z",
    rejoinders: [{ id: 7731, disposition: "REQUEST_CLARIFICATION", rejoinder: "Please confirm the responsible officer.", status: "DRAFT", createdAt: "2026-08-06T08:00:00Z", authoredBy: { name: "Audit Supervisor" } }],
  }],
};

async function mockWorkspace(page) {
  await page.route("**/api/aems/entry-conference-workspaces", async (route) => route.fulfill({ contentType: "application/json", body: JSON.stringify({ success: true, data: { engagements: [engagement] } }) }));
  await page.route("**/api/aems/exit-conference-workspaces", async (route) => route.fulfill({ contentType: "application/json", body: JSON.stringify({ success: true, data: { engagements: [engagement] } }) }));
  await page.route("**/api/aems/findings-workspaces", async (route) => route.fulfill({ contentType: "application/json", body: JSON.stringify({ success: true, data: { engagements: [engagement] } }) }));
  await page.route("**/api/aems/engagements/7701/entry-conference", async (route) => route.fulfill({ contentType: "application/json", body: JSON.stringify({ success: true, data: { engagement, conference: { id: 7702, conferenceCode: "ENTRY-AEMS-UI-7701", status: "ACKNOWLEDGED", scheduledStartAt: "2026-07-20T08:00:00Z", conferenceNotes: "Opening meeting completed." }, history: [{ id: 1, action: "ACKNOWLEDGED", toStatus: "ACKNOWLEDGED", createdAt: "2026-07-20T10:00:00Z", actor: { name: "Audit Supervisor" }, comment: "Opening meeting acknowledged." }] } }) }));
  await page.route("**/api/aems/engagements/7701/exit-conferences", async (route) => route.fulfill({ contentType: "application/json", body: JSON.stringify({ success: true, data: { engagement, conferences: [{ id: 7703, conferenceCode: "EXIT-AEMS-UI-7701", status: "COMPLETED", scheduledStartAt: "2026-08-10T08:00:00Z", completedAt: "2026-08-10T11:00:00Z", venue: "CIAS Conference Room", agenda: "Discuss findings.", minutes: "Minutes recorded.", agreements: "Corrective action accepted.", disagreements: "None.", participants: [{ id: 1 }], findings: [finding], acknowledgements: [{ id: 2, status: "ACKNOWLEDGED", acknowledgedAt: "2026-08-11T08:00:00Z", actor: { name: "Records Office" } }] }] } }) }));
  await page.route("**/api/aems/engagements/7701/findings-workspace", async (route) => route.fulfill({ contentType: "application/json", body: JSON.stringify({ success: true, data: { engagement, findings: [finding] } }) }));
  await page.route("**/api/aems/engagements/7701/work-queue", async (route) => route.fulfill({ contentType: "application/json", body: JSON.stringify({ success: true, data: { tasks: [{ id: 1, taskCode: "TASK-001", title: "Review response clarification", status: "OPEN", dueState: "OVERDUE", dueAt: "2026-08-07T08:00:00Z" }], dueProcess: [{ id: 1, eventType: "FINAL_NON_RESPONSE", content: "Response deadline passed.", recordedAt: "2026-08-08T08:00:00Z", actor: { name: "Audit Supervisor" } }], escalationCandidates: [{ id: 1, candidateCode: "ESC-001", candidateType: "MANAGEMENT_RESPONSE_NON_RESPONSE", status: "OPEN", reason: "Review required.", detectedAt: "2026-08-08T08:00:00Z" }], reviewNotes: [] } }) }));
  await page.route("**/api/notifications/recent", async (route) => route.fulfill({ contentType: "application/json", body: JSON.stringify({ success: true, data: { notifications: [{ id: 1, moduleCode: "AEMS", title: "Clarification requested", message: "A response clarification needs attention.", createdAt: "2026-08-08T09:00:00Z", readAt: null }], unreadCount: 1 } }) }));
}

test.beforeEach(async ({ page }) => {
  await signIn(page);
  await mockWorkspace(page);
});

test("conference and dialogue workspace exposes timelines and review queues", async ({ page }) => {
  await page.goto("/audit-engagement-management/conferences?engagementId=7701");
  await expect(page.getByRole("heading", { name: "Conferences & Dialogue", exact: true })).toBeVisible();
  await expect(page.getByText("Entry Conference ACKNOWLEDGED", { exact: true }).first()).toBeVisible();
  await expect(page.getByText("EXIT-AEMS-UI-7701 completed", { exact: false })).toBeVisible();
  await expect(page.getByText("Clarification requested", { exact: false }).first()).toBeVisible();
  await page.getByRole("button", { name: "Review queues", exact: true }).click();
  await expect(page.getByText("Overdue response queue", { exact: true })).toBeVisible();
  await expect(page.getByText("TASK-001", { exact: false })).toBeVisible();
  await expect(page.getByText("ESC-001", { exact: true })).toBeVisible();
});

test("dialogue view renders only the scoped formally communicated finding", async ({ page }) => {
  await page.goto("/audit-engagement-management/conferences?engagementId=7701&tab=dialogue");
  await expect(page.getByRole("heading", { name: "Auditee response and auditor rejoinder timeline", exact: true })).toBeVisible();
  await expect(page.getByText("FND-7701-001", { exact: true })).toBeVisible();
  await expect(page.getByText("Please provide the target implementation date.", { exact: true })).toBeVisible();
  await expect(page.getByText("Please confirm the responsible officer.", { exact: true })).toBeVisible();
  await expect(page.getByText("No formally communicated findings are visible", { exact: false })).toHaveCount(0);
});
