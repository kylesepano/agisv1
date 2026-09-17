import { expect, test } from "@playwright/test";

async function signIn(page) {
  await page.goto("/login");
  await page.getByLabel("Employee ID").fill("CIAS-HEAD-001");
  await page.locator("#password").fill("lala");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/dashboard$/);
}

test.beforeEach(async ({ page }) => { await signIn(page); });

test("BAICS foundation workspace is available inside IAP", async ({ page, isMobile }) => {
  await page.goto("/internal-audit-planning/baics", { waitUntil: "domcontentloaded" });
  await expect(page.getByRole("heading", { name: "Baseline Assessment (BAICS)", exact: true })).toBeVisible();
  await expect(page.getByText("Assessment cycles", { exact: true })).toBeVisible();
  await expect(page.getByText(/SQLSTATE|Undefined column|database error/i)).toHaveCount(0);
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1)).toBe(true);
  if (isMobile) {
    await page.getByRole("button", { name: "Open navigation" }).click();
    await expect(page.getByRole("complementary").getByRole("link", { name: "Baseline Assessment (BAICS)" })).toBeVisible();
  }
});

test("BAICS contextual workspaces are canonical, responsive, and error-free", async ({ page }) => {
  const workspaces = [
    ["/internal-audit-planning/baics/control-universe", "Control Universe & Baseline Assessment Report"],
    ["/internal-audit-planning/baics/integration", "BAICS IAP Integration"],
  ];

  for (const [path, heading] of workspaces) {
    await page.goto(path, { waitUntil: "domcontentloaded" });
    await expect(page.getByRole("heading", { name: heading, exact: true })).toBeVisible();
    await expect(page.getByText(/SQLSTATE|Undefined column|database error/i)).toHaveCount(0);
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1)).toBe(true);
  }
});
