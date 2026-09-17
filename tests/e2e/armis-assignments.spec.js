import { expect, test } from "@playwright/test";

async function signIn(page) {
  await page.goto("/login");
  await page.getByLabel("Employee ID").fill("CIAS-HEAD-001");
  await page.locator("#password").fill("lala");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/dashboard$/);
}

const resource = {
  id: 17,
  resourceCode: "ARMIS-RES-CIAS001",
  status: "ACTIVE",
  user: { id: 2, employeeId: "CIAS-AUD-001", name: "CIAS Demo Auditor", initials: "CD", isActive: true },
  office: { id: 1, code: "CIAS", name: "City Internal Audit Service" },
};

const engagement = {
  id: 9001,
  engagementCode: "AEMS-2026-001",
  title: "Procurement compliance review",
  status: "FIELDWORK",
  plannedStartDate: "2026-08-01",
  plannedEndDate: "2026-10-31",
  plannedPersonDays: 100,
};

function assignment(overrides = {}) {
  return {
    id: 301,
    assignmentFamilyUuid: "assignment-family-301",
    auditEngagementId: engagement.id,
    engagement: { id: engagement.id, engagement_code: engagement.engagementCode, title: engagement.title, status: engagement.status },
    resourceProfileId: resource.id,
    resourceCode: resource.resourceCode,
    resourceUser: { id: 2, employee_id: resource.user.employeeId, name: resource.user.name, initials: "CD" },
    office: resource.office,
    requirementId: null,
    requirement: null,
    versionNumber: 1,
    supersedesId: null,
    isCurrentRevision: true,
    assignmentRoleCode: "AUDITOR",
    assignedFrom: "2026-08-01",
    assignedUntil: "2026-10-31",
    plannedPersonDays: 40,
    status: "DRAFT",
    notes: "Fieldwork assignment",
    requiredCompetencies: [],
    lockVersion: 1,
    ...overrides,
  };
}

function actual(overrides = {}) {
  return {
    id: 401,
    actualFamilyUuid: "actual-family-401",
    resourceProfileId: resource.id,
    assignmentId: 301,
    engagement: { id: engagement.id, engagement_code: engagement.engagementCode, title: engagement.title, status: engagement.status },
    resourceCode: resource.resourceCode,
    resourceUser: { id: 2, employee_id: resource.user.employeeId, name: resource.user.name, initials: "CD" },
    periodStart: "2026-08-01",
    periodEnd: "2026-08-31",
    versionNumber: 1,
    supersedesId: null,
    isCurrentRevision: true,
    actualPersonDays: 12,
    plannedPersonDays: 40,
    status: "APPROVED",
    notes: "August actuals",
    varianceReason: null,
    lockVersion: 2,
    ...overrides,
  };
}

async function fulfill(route, data, status = 200) {
  await route.fulfill({ status, contentType: "application/json", body: JSON.stringify({ success: true, data }) });
}

async function mockAssignments(page) {
  let currentAssignment = assignment();
  let currentActual = actual();
  let createdPayload;

  await page.route(/\/api\/armis\/assignments\/metadata$/, (route) => fulfill(route, {
    assignmentStatuses: ["DRAFT", "SUBMITTED", "RETURNED", "APPROVED", "LOCKED"].map((code) => ({ code, label: code })),
    actualStatuses: ["DRAFT", "SUBMITTED", "RETURNED", "APPROVED", "LOCKED"].map((code) => ({ code, label: code })),
    assignmentRoles: ["SUPERVISOR", "TEAM_LEADER", "AUDITOR", "REVIEWER"].map((code) => ({ code, label: code })),
    proficiencyLevels: ["BASIC", "INTERMEDIATE", "ADVANCED", "EXPERT"].map((code) => ({ code, label: code })),
    workflow: { editableStatuses: ["DRAFT", "RETURNED"], reviewStatus: "SUBMITTED", approvedStatus: "APPROVED", lockedStatus: "LOCKED" },
  }));
  await page.route(/\/api\/armis\/foundation$/, (route) => fulfill(route, { profiles: [resource], requirements: [], competencies: [], availability: [], capacities: [], workload: [], actuals: [] }));
  await page.route(/\/api\/armis\/competencies\/metadata$/, (route) => fulfill(route, { competencies: [], proficiencyLevels: [] }));
  await page.route(/\/api\/aems\/engagements(?:\?.*)?$/, (route) => fulfill(route, { engagements: [engagement], pagination: { currentPage: 1, lastPage: 1, total: 1 } }));
  await page.route(/\/api\/armis\/assignments(?:\?.*)?$/, async (route) => {
    if (route.request().method() === "POST") {
      createdPayload = route.request().postDataJSON();
      currentAssignment = assignment({ status: "DRAFT", lockVersion: 1, plannedPersonDays: Number(createdPayload.plannedPersonDays) });
    }
    await fulfill(route, [currentAssignment], route.request().method() === "POST" ? 201 : 200);
  });
  await page.route(/\/api\/armis\/actuals(?:\?.*)?$/, (route) => fulfill(route, [currentActual]));
  await page.route(/\/api\/armis\/assignments\/301\/conflicts$/, (route) => fulfill(route, []));
  return { getCreatedPayload: () => createdPayload };
}

test("renders the ARMIS assignment and actuals workspace", async ({ page }) => {
  await signIn(page);
  await mockAssignments(page);
  await page.goto("/audit-resource-management/assignments");

  await expect(page.getByRole("heading", { name: "ARMIS Assignments & Actuals", exact: true, level: 2 })).toBeVisible();
  await expect(page.getByText("Controlled assignment lifecycle", { exact: true })).toBeVisible();
  await page.getByRole("tab", { name: /Assignments/ }).click();
  await expect(page.getByText("AEMS-2026-001", { exact: true })).toBeVisible();
  await page.getByRole("tab", { name: /Actual person-days/ }).click();
  await expect(page.getByText("August actuals", { exact: true })).toHaveCount(0);
  await expect(page.getByText("AEMS-2026-001", { exact: true })).toBeVisible();
});

test("creates an ARMIS assignment draft from the workspace", async ({ page }) => {
  await signIn(page);
  const armis = await mockAssignments(page);
  await page.goto("/audit-resource-management/assignments");
  await page.getByRole("tab", { name: /Assignments/ }).click();
  await page.getByRole("button", { name: "New assignment" }).click();
  await page.getByLabel("AEMS engagement").selectOption("9001");
  await page.getByLabel("Resource profile").selectOption("17");
  await page.getByLabel("Assigned from").fill("2026-08-01");
  await page.getByLabel("Assigned until").fill("2026-10-31");
  await page.getByLabel("Planned person-days").fill("45");
  await page.getByRole("button", { name: "Save Draft" }).click();
  await expect(page.getByText("assignment draft saved.", { exact: true })).toBeVisible();
  expect(armis.getCreatedPayload()).toMatchObject({ auditEngagementId: 9001, resourceProfileId: 17, plannedPersonDays: 45 });
});
