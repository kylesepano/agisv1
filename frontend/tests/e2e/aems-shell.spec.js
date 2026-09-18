import { expect, test } from "@playwright/test";

async function signIn(page) {
  await page.goto("/login");
  await page.getByLabel("Employee ID").fill("CIAS-HEAD-001");
  await page.locator("#password").fill("lala");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/dashboard$/);
}

async function openAuthenticatedPage(page, path) {
  const restoredSession = page.waitForResponse(
    (response) =>
      response.url().endsWith("/api/me") && response.status() === 200,
    { timeout: 30_000 },
  );
  await page.goto(path, { waitUntil: "domcontentloaded" });
  await restoredSession;
  await expect(page.getByText("Loading AGIS", { exact: true })).toBeHidden({
    timeout: 30_000,
  });
}

test.beforeEach(async ({ page }) => {
  await signIn(page);
});

test("AEMS-1B exposes grouped navigation and foundation registry filters", async ({
  page,
}) => {
  await openAuthenticatedPage(page, "/audit-engagement-management");

  await expect(
    page.getByRole("heading", { level: 2, name: "Engagement Registry" }),
  ).toBeVisible();
  await expect(
    page.getByRole("button", { name: "Filter by phase" }),
  ).toBeVisible();
  await expect(
    page.getByRole("button", { name: "Filter by administrative status" }),
  ).toBeVisible();

  const navigation = page.getByRole("complementary");
  if (await page.getByRole("button", { name: "Open navigation" }).isVisible()) {
    await page.getByRole("button", { name: "Open navigation" }).click();
  }
  await expect(navigation.getByText("Portfolio", { exact: true })).toBeVisible();
  await expect(navigation.getByText("Foundation", { exact: true })).toBeVisible();
  await expect(navigation.getByText("Planning", { exact: true })).toBeVisible();
  await expect(navigation.getByText("Issues & AFR", { exact: true })).toBeVisible();
  await expect(
    navigation.getByText("Audit Engagement Management", { exact: true }),
  ).toBeVisible();
  await expect(
    navigation.getByRole("link", { name: "Working Papers & Evidence" }),
  ).toBeVisible();
  await expect(
    navigation.getByRole("link", { name: "Audit Reporting Workspace" }),
  ).toBeVisible();
});
