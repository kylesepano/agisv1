import { expect, test } from "@playwright/test";

async function signIn(page) {
  await page.goto("/login");
  await page.getByLabel("Employee ID").fill("CIAS-HEAD-001");
  await page.locator("#password").fill("lala");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/dashboard$/);
}

test("AEMS dashboard redacts database diagnostics from user-facing errors", async ({
  page,
}) => {
  await signIn(page);
  await page.route("**/api/aems/dashboard*", (route) =>
    route.fulfill({
      status: 500,
      contentType: "application/json",
      body: JSON.stringify({
        message:
          'SQLSTATE[42703]: Undefined column: 7 ERROR: column "secret" does not exist',
      }),
    }),
  );

  await page.goto("/audit-engagement-management/dashboard");

  await expect(
    page.getByText("The request could not be completed.", { exact: true }),
  ).toBeVisible();
  await expect(page.getByText(/SQLSTATE|Undefined column|secret/i)).toHaveCount(0);
});
