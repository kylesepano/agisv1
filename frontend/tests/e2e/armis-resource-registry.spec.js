import { expect, test } from "@playwright/test";

async function signIn(page) {
  await page.goto("/login");
  await page.getByLabel("Employee ID").fill("CIAS-HEAD-001");
  await page.locator("#password").fill("lala");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/dashboard$/);
}

const profile = (overrides = {}) => ({
  id: 17,
  resourceCode: "ARMIS-RES-CIAS001",
  userId: 2,
  officeId: 1,
  category: "AUDIT_RESOURCE",
  status: "ACTIVE",
  effectiveFrom: "2026-08-01",
  effectiveTo: null,
  notes: "CIAS resource profile",
  lockVersion: 2,
  isArchived: false,
  user: { id: 2, employeeId: "CIAS-AUD-001", name: "CIAS Demo Auditor", position: "Internal Auditor", isActive: true },
  office: { id: 1, code: "CIAS", name: "City Internal Audit Service" },
  competencies: [],
  availabilityPeriods: [],
  ...overrides,
});

async function mockArmis(page) {
  let createdPayload;
  let transitionPayload;
  await page.route(/\/api\/armis\/metadata$/, (route) => route.fulfill({
    status: 200,
    contentType: "application/json",
    body: JSON.stringify({ success: true, data: {
      statuses: ["DRAFT", "ACTIVE", "SUSPENDED", "INACTIVE", "ARCHIVED"].map((code) => ({ code, label: code })),
      categories: [{ code: "AUDIT_RESOURCE", label: "Audit Resource" }, { code: "SPECIALIST", label: "Specialist" }],
      provider: { mode: "ARMIS_AUTHORITATIVE", authoritative: true },
    } }),
  }));
  await page.route(/\/api\/armis\/identities$/, (route) => route.fulfill({
    status: 200,
    contentType: "application/json",
    body: JSON.stringify({ success: true, data: [{ id: 2, employeeId: "CIAS-AUD-001", name: "CIAS Demo Auditor", position: "Internal Auditor", officeId: 1, office: { code: "CIAS", name: "City Internal Audit Service" } }] }),
  }));
  await page.route(/\/api\/armis\/resources(?:\?[^/]*)?$/, async (route) => {
    if (route.request().method() === "POST") {
      createdPayload = JSON.parse(route.request().postData() || "{}");
      return route.fulfill({ status: 201, contentType: "application/json", body: JSON.stringify({ success: true, data: profile({ id: 18, resourceCode: "ARMIS-RES-NEW", status: "DRAFT", lockVersion: 1 }) }) });
    }
    return route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ success: true, data: [profile()] }) });
  });
  await page.route(/\/api\/armis\/resources\/17$/, (route) => route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ success: true, data: profile() }) }));
  await page.route(/\/api\/armis\/resources\/17\/events$/, (route) => route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ success: true, data: [{ id: 1, eventCode: "RESOURCE_CREATED", toStatus: "DRAFT", createdAt: "2026-08-01T09:00:00Z" }, { id: 2, eventCode: "RESOURCE_STATUS_CHANGED", fromStatus: "DRAFT", toStatus: "ACTIVE", createdAt: "2026-08-02T09:00:00Z" }] }) }));
  await page.route(/\/api\/armis\/resources\/18$/, (route) => route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ success: true, data: profile({ id: 18, resourceCode: "ARMIS-RES-NEW", status: "DRAFT", lockVersion: 1 }) }) }));
  await page.route(/\/api\/armis\/resources\/18\/events$/, (route) => route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ success: true, data: [{ id: 3, eventCode: "RESOURCE_CREATED", toStatus: "DRAFT", createdAt: "2026-08-10T09:00:00Z" }] }) }));
  await page.route(/\/api\/armis\/resources\/\d+\/transition$/, async (route) => {
    transitionPayload = JSON.parse(route.request().postData() || "{}");
    return route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ success: true, data: profile({ status: transitionPayload.status, lockVersion: 3 }) }) });
  });
  return { getCreatedPayload: () => createdPayload, getTransitionPayload: () => transitionPayload };
}

test("renders the ARMIS resource registry and detail timeline", async ({ page }) => {
  await signIn(page);
  await mockArmis(page);
  await page.goto("/audit-resource-management/resources");

  await expect(page.getByRole("heading", { name: "ARMIS Resource Registry", exact: true, level: 2 })).toBeVisible();
  await expect(page.getByText("ARMIS-RES-CIAS001", { exact: true })).toBeVisible();
  await page.getByRole("button", { name: "Open", exact: true }).click();
  await expect(page).toHaveURL(/\/audit-resource-management\/resources\/17$/);
  await expect(page.getByTestId("armis-resource-detail")).toBeVisible();
  await expect(page.getByText("Profile history", { exact: true })).toBeVisible();
});

test("creates a draft profile and submits a lifecycle transition", async ({ page }) => {
  await signIn(page);
  const armis = await mockArmis(page);
  await page.goto("/audit-resource-management/resources/17");
  await page.getByRole("button", { name: "New resource" }).click();
  await page.getByLabel("Core user").selectOption("2");
  await page.getByLabel("Resource code (optional)").fill("ARMIS-RES-NEW");
  await page.getByRole("button", { name: "Create draft" }).click();
  await expect(page.getByText("ARMIS resource profile created.", { exact: true })).toBeVisible();
  expect(armis.getCreatedPayload()).toMatchObject({ userId: 2, officeId: 1, resourceCode: "ARMIS-RES-NEW" });

  await page.goto("/audit-resource-management/resources/17");
  await page.getByTestId("armis-resource-detail").waitFor();
  await page.getByRole("button", { name: "Inactive", exact: true }).click();
  await page.getByRole("button", { name: "Confirm Inactive", exact: true }).click();
  await expect(page.getByText("Resource profile changed to Inactive.", { exact: true })).toBeVisible();
  expect(armis.getTransitionPayload()).toMatchObject({ status: "INACTIVE", lockVersion: 2 });
});
