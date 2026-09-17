import { expect, test } from "@playwright/test";

async function signIn(page) {
  await page.goto("/login");
  await page.getByLabel("Employee ID").fill("CIAS-HEAD-001");
  await page.locator("#password").fill("lala");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/dashboard$/);
}

async function expectNoPageOverflow(page) {
  await expect
    .poll(() =>
      page.evaluate(
        () =>
          document.documentElement.scrollWidth <=
          document.documentElement.clientWidth + 1,
      ),
    )
    .toBe(true);
}

async function expectResponsivePageShell(page) {
  const main = page.locator("main").first();
  await expect(main).toBeVisible();
  const paddingLeft = await main.evaluate((element) =>
    Number.parseFloat(window.getComputedStyle(element).paddingLeft),
  );
  expect(paddingLeft).toBeGreaterThanOrEqual(16);
  await expectNoPageOverflow(page);
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

test("IAP scheduling and reports remain usable at the active viewport", async ({
  page,
  isMobile,
}) => {
  await openAuthenticatedPage(page, "/internal-audit-planning/scheduling");
  await expect(
    page.getByRole("heading", { name: "Audit Scheduling", exact: true }),
  ).toBeVisible();
  await expect(page.getByText(/SQLSTATE|Undefined column/i)).toHaveCount(0);
  await expect(page.getByText("Plan Engagements")).toBeVisible();
  await expectNoPageOverflow(page);

  if (isMobile) {
    await page.getByRole("button", { name: "Calendar", exact: true }).click();
    await expect(
      page
        .getByText(
          /January|February|March|April|May|June|July|August|September|October|November|December/,
        )
        .first(),
    ).toBeVisible();
    await expectNoPageOverflow(page);

    await page.getByRole("button", { name: "Open navigation" }).click();
    const drawer = page.getByRole("complementary");
    await expect(drawer.getByText("AGIS", { exact: true })).toBeVisible();
    await expect(
      drawer.getByRole("button", { name: /Expand|Collapse Internal Audit Planning/ }),
    ).toBeVisible();
    await page
      .getByRole("complementary")
      .getByRole("button", { name: "Close navigation" })
      .click();
  }

  await openAuthenticatedPage(page, "/internal-audit-planning");
  await expect(
    page.getByRole("heading", { name: "Internal Audit Planning", exact: true }),
  ).toBeVisible();
  await expect(page.getByText("Active Plan Records")).toBeVisible();
  await expect(
    page.getByText("The include archived field must be true or false."),
  ).toHaveCount(0);
  await expectNoPageOverflow(page);

  await openAuthenticatedPage(
    page,
    "/internal-audit-planning/risk-assessment",
  );
  await expect(
    page.getByRole("heading", {
      level: 2,
      name: "Risk Assessment",
      exact: true,
    }),
  ).toBeVisible();
  await expect(page.getByText("Assessment periods")).toBeVisible();
  await expectResponsivePageShell(page);

  await openAuthenticatedPage(page, "/internal-audit-planning/prioritization");
  await expect(
    page.getByRole("heading", {
      level: 2,
      name: "Audit Prioritization",
      exact: true,
    }),
  ).toBeVisible();
  await expect(page.getByText("Prioritization runs")).toBeVisible();
  await expectResponsivePageShell(page);

  await openAuthenticatedPage(page, "/internal-audit-planning/reports");
  await expect(
    page.getByRole("heading", { name: "IAP Reports and Exports" }),
  ).toBeVisible();
  await expect(page.getByText("Audit Universe Report").first()).toBeVisible();
  await expectResponsivePageShell(page);

  if (isMobile) {
    const reportCard = page
      .getByRole("button", { name: /Approved Strategic Internal Audit Plan/ })
      .first();
    const cardBox = await reportCard.boundingBox();
    expect(cardBox?.width ?? 0).toBeGreaterThan(300);
  }
});
