import { expect, test } from "@playwright/test";

async function signIn(page) {
  await page.goto("/login");
  await page.getByLabel("Employee ID").fill("CIAS-HEAD-001");
  await page.locator("#password").fill("lala");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/dashboard$/);
}

test("redirects the retired provider-reconciliation route to ARMIS monitoring", async ({ page }) => {
  await signIn(page);
  await page.goto("/audit-resource-management/provider-reconciliation");

  await expect(page).toHaveURL(/\/audit-resource-management\/provider-monitoring$/);
  await expect(page.getByRole("heading", { name: "ARMIS Provider Monitoring", exact: true, level: 2 })).toBeVisible();
});

test("keeps the retired reconciliation link from creating a horizontal overflow", async ({ page }) => {
  await signIn(page);
  await page.goto("/audit-resource-management/provider-reconciliation");
  await expect(page.locator("body")).toHaveJSProperty("scrollWidth", await page.evaluate(() => document.documentElement.clientWidth));
});
