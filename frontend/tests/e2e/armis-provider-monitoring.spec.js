import { expect, test } from "@playwright/test";

async function signIn(page) {
  await page.goto("/login");
  await page.getByLabel("Employee ID").fill("CIAS-HEAD-001");
  await page.locator("#password").fill("lala");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/dashboard$/);
}

const checks = [
  { code: "CONFIGURATION_CONSISTENCY", status: "PASS", message: "Configured and effective provider modes agree.", observed: { configuredMode: "ARMIS_AUTHORITATIVE", effectiveMode: "ARMIS_AUTHORITATIVE" } },
  { code: "AEMS_READ_PATH", status: "PASS", message: "AEMS active reads resolve to ARMIS, the sole operational provider.", observed: { activeProvider: "App\\Integrations\\Aems\\ArmisResourcePlanningGateway" } },
];

function monitoringCheck(overrides = {}) {
  return {
    id: 101,
    displayCode: "ARMIS-MON-000101",
    sourceQueryVersion: "ARMIS-6D-v1",
    providerMode: "ARMIS_AUTHORITATIVE",
    configuredMode: "ARMIS_AUTHORITATIVE",
    overallStatus: "HEALTHY",
    checks,
    resultChecksumSha256: "abcdef0123456789abcdef0123456789abcdef0123456789abcdef0123456789",
    performedAt: "2026-08-11T09:00:00Z",
    performedBy: { id: 2, name: "CIAS Management" },
    ...overrides,
  };
}

async function mockMonitoring(page) {
  let currentCheck = monitoringCheck();
  await page.route(/\/api\/armis\/provider\/monitoring\/status$/, async (route) => {
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ success: true, data: { providerMode: "ARMIS_AUTHORITATIVE", configuredMode: "ARMIS_AUTHORITATIVE", overallStatus: currentCheck.overallStatus, checks: currentCheck.checks, providerSnapshot: { provider: { mode: "ARMIS_AUTHORITATIVE" } }, latestCheck: currentCheck, monitoringControls: { checkIsReadOnly: true } } }) });
  });
  await page.route(/\/api\/armis\/provider\/monitoring\/checks$/, async (route) => {
    if (route.request().method() === "POST") {
      currentCheck = monitoringCheck({ id: 102, displayCode: "ARMIS-MON-000102", overallStatus: "HEALTHY" });
      await route.fulfill({ status: 201, contentType: "application/json", body: JSON.stringify({ success: true, data: { check: currentCheck } }) });
      return;
    }
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ success: true, data: { checks: [currentCheck], meta: { total: 1 } } }) });
  });
  await page.route(/\/api\/armis\/provider\/monitoring\/checks\/\d+$/, async (route) => {
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ success: true, data: { check: currentCheck } }) });
  });
}

test("renders ARMIS provider health checks and runs a verification snapshot", async ({ page }) => {
  await signIn(page);
  await mockMonitoring(page);
  await page.goto("/audit-resource-management/provider-monitoring");

  await expect(page.getByRole("heading", { name: "ARMIS Provider Monitoring", exact: true, level: 2 })).toBeVisible();
  await expect(page.getByText("ARMIS-MON-000101", { exact: true }).first()).toBeVisible();
  await expect(page.getByText("Provider verification status", { exact: true })).toBeVisible();
  await page.getByRole("button", { name: "Run provider check" }).click();
  await expect(page.getByText("ARMIS provider check completed: Healthy.", { exact: true })).toBeVisible();
  await expect(page.getByText("ARMIS-MON-000102", { exact: true }).first()).toBeVisible();
});

test("keeps the provider monitoring workspace usable on mobile", async ({ page }) => {
  await signIn(page);
  await mockMonitoring(page);
  await page.goto("/audit-resource-management/provider-monitoring");
  await expect(page.getByRole("heading", { name: "ARMIS Provider Monitoring", exact: true, level: 2 })).toBeVisible();
  await expect(page.locator("body")).toHaveJSProperty("scrollWidth", await page.evaluate(() => document.documentElement.clientWidth));
});
