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
  user: { id: 2, employeeId: "CIAS-AUD-001", name: "CIAS Demo Auditor", position: "Internal Auditor", isActive: true },
  office: { id: 1, code: "CIAS", name: "City Internal Audit Service" },
};

function availability(overrides = {}) {
  return {
    id: 41,
    availabilityFamilyUuid: "availability-family-41",
    resourceProfileId: resource.id,
    resourceCode: resource.resourceCode,
    resourceUser: { id: 2, name: resource.user.name, initials: "MB" },
    office: resource.office,
    versionNumber: 1,
    supersedesId: null,
    isCurrentRevision: true,
    availabilityType: "LEAVE",
    startDate: "2026-06-01",
    endDate: "2026-06-05",
    personDays: 5,
    status: "APPROVED",
    notes: "Approved leave period",
    lockVersion: 2,
    ...overrides,
  };
}

function capacity(overrides = {}) {
  return {
    id: 51,
    resourceProfileId: resource.id,
    resourceCode: resource.resourceCode,
    resourceUser: { id: 2, name: resource.user.name, initials: "MB" },
    office: resource.office,
    fiscalYear: 2026,
    versionNumber: 1,
    supersedesId: null,
    isCurrentRevision: true,
    availablePersonDays: 100,
    status: "APPROVED",
    notes: "Annual capacity",
    lockVersion: 2,
    ...overrides,
  };
}

function workload(overrides = {}) {
  return {
    id: 61,
    workloadFamilyUuid: "workload-family-61",
    resourceProfileId: resource.id,
    resourceCode: resource.resourceCode,
    resourceUser: { id: 2, name: resource.user.name, initials: "MB" },
    office: resource.office,
    versionNumber: 1,
    supersedesId: null,
    isCurrentRevision: true,
    sourceModule: "ARMIS",
    sourceType: "AUDIT_ENGAGEMENT",
    sourceId: 9001,
    fiscalYear: 2026,
    plannedPersonDays: 60,
    status: "APPROVED",
    notes: "Planned fieldwork",
    lockVersion: 2,
    ...overrides,
  };
}

async function mockPlanning(page) {
  let currentCapacity = capacity();
  let createdCapacityPayload;

  await page.route(/\/api\/armis\/planning\/metadata$/, (route) => route.fulfill({
    status: 200,
    contentType: "application/json",
    body: JSON.stringify({ success: true, data: {
      statuses: ["DRAFT", "SUBMITTED", "RETURNED", "APPROVED", "LOCKED"].map((code) => ({ code, label: code })),
      availabilityTypes: ["AVAILABLE", "UNAVAILABLE", "LEAVE", "TRAINING", "OTHER"].map((code) => ({ code, label: code })),
      fiscalYears: [2025, 2026, 2027],
      reviewDecisions: [{ code: "APPROVE", label: "Approve" }, { code: "RETURN", label: "Return" }],
      workflow: { editableStatuses: ["DRAFT", "RETURNED"], reviewStatus: "SUBMITTED", approvedStatus: "APPROVED", lockedStatus: "LOCKED" },
      provider: { mode: "ARMIS_AUTHORITATIVE" },
    } }),
  }));
  await page.route(/\/api\/armis\/metadata$/, (route) => route.fulfill({
    status: 200,
    contentType: "application/json",
    body: JSON.stringify({ success: true, data: { provider: { mode: "ARMIS_AUTHORITATIVE" } } }),
  }));
  await page.route(/\/api\/armis\/resources(?:\?[^/]*)?$/, (route) => route.fulfill({
    status: 200,
    contentType: "application/json",
    body: JSON.stringify({ success: true, data: [resource] }),
  }));
  await page.route(/\/api\/armis\/availability(?:\?[^/]*)?$/, (route) => route.fulfill({
    status: 200,
    contentType: "application/json",
    body: JSON.stringify({ success: true, data: [availability()], meta: { total: 1, currentOnly: true } }),
  }));
  await page.route(/\/api\/armis\/capacity(?:\?[^/]*)?$/, async (route) => {
    if (route.request().method() === "POST") {
      createdCapacityPayload = JSON.parse(route.request().postData() || "{}");
      currentCapacity = capacity({ status: "DRAFT", lockVersion: 1, availablePersonDays: Number(createdCapacityPayload.availablePersonDays) });
      return route.fulfill({ status: 201, contentType: "application/json", body: JSON.stringify({ success: true, data: currentCapacity }) });
    }
    return route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ success: true, data: [currentCapacity], meta: { total: 1, currentOnly: true } }) });
  });
  await page.route(/\/api\/armis\/workload(?:\?[^/]*)?$/, (route) => route.fulfill({
    status: 200,
    contentType: "application/json",
    body: JSON.stringify({ success: true, data: [workload()], meta: { total: 1, currentOnly: true } }),
  }));
  await page.route(/\/api\/armis\/utilization\?[^/]*$/, (route) => route.fulfill({
    status: 200,
    contentType: "application/json",
    body: JSON.stringify({ success: true, data: { rows: [{ resourceProfileId: resource.id, resourceCode: resource.resourceCode, resourceUser: { name: resource.user.name }, office: resource.office, fiscalYear: 2026, capacityPersonDays: Number(currentCapacity.availablePersonDays), plannedPersonDays: 60, remainingPersonDays: Number(currentCapacity.availablePersonDays) - 60, utilizationPercent: 60, overCapacity: false }], summary: { fiscalYear: 2026, resourceCount: 1, capacityPersonDays: Number(currentCapacity.availablePersonDays), plannedPersonDays: 60, remainingPersonDays: Number(currentCapacity.availablePersonDays) - 60, utilizationPercent: 60, overCapacityCount: 0 } } }),
  }));
  await page.route(/\/api\/armis\/capacity\/51\/submit$/, async (route) => {
    currentCapacity = capacity({ status: "SUBMITTED", lockVersion: 2 });
    return route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ success: true, data: currentCapacity }) });
  });
  await page.route(/\/api\/armis\/capacity\/51\/review$/, async (route) => {
    currentCapacity = capacity({ status: "APPROVED", lockVersion: 3 });
    return route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ success: true, data: currentCapacity }) });
  });
  await page.route(/\/api\/armis\/capacity\/51\/lock$/, async (route) => {
    currentCapacity = capacity({ status: "LOCKED", lockVersion: 4 });
    return route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ success: true, data: currentCapacity }) });
  });
  return { getCreatedCapacityPayload: () => createdCapacityPayload };
}

test("renders ARMIS planning overview, utilization, and availability calendar", async ({ page }) => {
  await signIn(page);
  await mockPlanning(page);
  await page.goto("/audit-resource-management/planning");

  await expect(page.getByRole("heading", { name: "ARMIS Planning & Utilization", exact: true, level: 2 })).toBeVisible();
  await expect(page.getByText("ARMIS_AUTHORITATIVE", { exact: true })).toBeVisible();
  await expect(page.getByText("Resource utilization", { exact: true })).toBeVisible();
  await page.getByRole("tab", { name: /Availability calendar/ }).click();
  await expect(page.getByTestId("armis-availability-calendar")).toBeVisible();
  await expect(page.getByText("Approved leave period", { exact: true })).toHaveCount(0);
  await expect(page.getByTestId("armis-availability-calendar").getByText("ARMIS-RES-CIAS001", { exact: true })).toBeVisible();
});

test("creates and submits a capacity draft from the ARMIS planning workspace", async ({ page }) => {
  await signIn(page);
  const armis = await mockPlanning(page);
  await page.goto("/audit-resource-management/planning");
  await page.getByRole("tab", { name: /Capacity/ }).click();
  await page.getByRole("button", { name: "New capacity submission" }).click();
  await page.getByLabel("Resource profile").selectOption("17");
  await page.getByLabel("Available person-days").fill("120");
  await page.getByRole("button", { name: /Create planning draft/ }).click();
  await expect(page.getByText("Capacity draft saved.", { exact: true })).toBeVisible();
  expect(armis.getCreatedCapacityPayload()).toMatchObject({ resourceProfileId: 17, fiscalYear: 2026, availablePersonDays: 120 });

  await page.getByRole("button", { name: "Submit" }).click();
  await expect(page.getByText("Capacity submitted for independent review.", { exact: true })).toBeVisible();
});
