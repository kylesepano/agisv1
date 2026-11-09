import { expect, test } from "@playwright/test";
import { icsPanels, ppspPanels, surveySections } from "../../src/components/aems/surveyDefinition.js";

const engagement = { id: 9001, engagementCode: "AEM-2026-001", title: "Procurement Process Audit", sourceType: "SPECIAL", phase: "PLANNING", status: "ENGAGEMENT_PLANNING", auditYear: 2026, specialAuthorityReference: "MO-2026-015", createdAt: "2026-11-01T00:00:00Z", plannedEndDate: "2026-12-31", offices: [{ id: 1, code: "CGSO", name: "City General Services Office" }], auditAreas: [], auditFocuses: [] };
test.beforeEach(async ({ page }) => {
  let survey = {};
  let lockVersion = null;
  await page.route("**/api/**", async (route) => {
    const path = new URL(route.request().url()).pathname;
    let data = {};
    if (path === "/api/me") data = { user: { id: 1, name: "Juan dela Cruz", initials: "JC", role: "Audit Team Leader", permissions: ["aems.engagement.view", "aems.planning-package.view", "aems.planning-package.create", "aems.planning-package.update"] } };
    else if (path === "/api/aems/engagements/9001") data = { engagement };
    else if (path.endsWith("/preliminary-survey")) {
      if (route.request().method() === "PUT") { const input = route.request().postDataJSON(); survey = { ...survey, [input.section]: input.data }; lockVersion = (lockVersion ?? 0) + 1; }
      data = { survey, lockVersion, packageStatus: "DRAFT", canEdit: true, attachments: [] };
    }
    await route.fulfill({ json: { success: true, data } });
  });
  await page.route("**/sanctum/csrf-cookie", (route) => route.fulfill({ status: 204 }));
});

test("reference planning menu reaches the four survey sections and saves meeting data", async ({ page }, info) => {
  await page.goto("/audit-engagement-management/9001?tab=planning");
  await expect(page.getByText("Audit Engagement Planning", { exact: true })).toBeVisible();
  await page.screenshot({ path: `test-results/reference-planning-empty-${info.project.name}.png`, fullPage: true });
  await page.getByRole("button", { name: "1 Preliminary Survey", exact: true }).click();
  await expect(page.getByRole("link", { name: "1.1 Preliminary Meeting", exact: true })).toBeVisible();
  await page.screenshot({ path: `test-results/reference-planning-${info.project.name}.png`, fullPage: true });
  await page.getByRole("link", { name: "1.1 Preliminary Meeting", exact: true }).click();
  await page.getByLabel("Venue / Online Link").fill("CIAS Conference Room");
  await page.getByRole("button", { name: "Save Draft", exact: true }).click();
  await expect(page.getByRole("status")).toHaveText("Draft saved successfully.");
  await page.reload();
  await expect(page.getByLabel("Venue / Online Link")).toHaveValue("CIAS Conference Room");
  await page.screenshot({ path: `test-results/reference-meeting-${info.project.name}.png`, fullPage: true });
});

test("all reference accordions and row editors render without page overflow", async ({ page }, info) => {
  const errors = [];
  page.on("pageerror", (error) => errors.push(error.message));
  for (const [key, , title] of surveySections) {
    await page.goto(`/audit-engagement-management/preliminary-survey?engagementId=9001&section=${key}`);
    await expect(page.getByRole("heading", { level: 1, name: new RegExp(title.replace(/[()]/g, "\\$&")) }).last()).toBeVisible();
    await page.screenshot({ path: `test-results/reference-${key}-closed-${info.project.name}.png`, fullPage: true });
    if (key === "auditee") {
      await page.getByRole("button", { name: "+ Add Program or Project", exact: true }).click();
      await page.screenshot({ path: `test-results/reference-program-editor-${info.project.name}.png`, fullPage: true });
      await page.getByRole("button", { name: "Close item editor", exact: true }).click();
    }
    if (key === "ppsp" || key === "ics") {
      for (const [panel, , panelTitle] of key === "ppsp" ? ppspPanels : icsPanels) {
        await page.getByRole("button", { name: new RegExp(panelTitle.replace(/[()]/g, "\\$&")) }).click();
        if (key === "ics") {
          await page.getByLabel(/Actions for row/).first().click();
          await page.getByRole("button", { name: "Edit", exact: true }).first().click();
          await expect(page.getByLabel("Principle", { exact: true })).toBeVisible();
          if (panel === "environment") await page.screenshot({ path: `test-results/reference-ics-editor-${info.project.name}.png`, fullPage: true });
          await page.getByRole("button", { name: "Close item editor", exact: true }).click();
        }
        await page.screenshot({ path: `test-results/reference-${key}-${panel}-${info.project.name}.png`, fullPage: true });
        if (key === "ppsp" && !["profile", "flowchart"].includes(panel)) {
          await page.getByRole("button", { name: panel === "steps" ? "+ Add Activity / Decision" : "+ Add Item to List", exact: true }).click();
          await page.screenshot({ path: `test-results/reference-${panel}-editor-${info.project.name}.png`, fullPage: true });
          await page.getByRole("button", { name: "Close item editor", exact: true }).click();
        }
      }
    }
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1);
    expect(overflow).toBe(false);
  }
  await page.goto("/audit-engagement-management/preliminary-survey?engagementId=9001&section=ppsp&panel=steps");
  await page.getByRole("button", { name: "+ Add Activity / Decision", exact: true }).click();
  await page.getByLabel("Activity / Decision", { exact: true }).fill("Review purchase request");
  await page.getByRole("button", { name: "Update to Table", exact: true }).click();
  await expect(page.getByRole("cell", { name: "Review purchase request", exact: true })).toBeVisible();
  await page.reload();
  await expect(page.getByRole("cell", { name: "Review purchase request", exact: true })).toBeVisible();
  expect(errors).toEqual([]);
});
