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
      data = { survey, lockVersion, packageStatus: "DRAFT", canEdit: true, attachments: [], participantOptions: [{ id: 1, name: "Juan dela Cruz", position: "Auditor", employeeId: "CIAS-001" }, { id: 2, name: "Maria Santos", position: "Team Leader", employeeId: "CIAS-002" }] };
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
  const participants = page.getByRole("group", { name: "CIAS Participants", exact: true });
  await participants.getByRole("button", { name: "Search and select CIAS participants", exact: true }).click();
  await page.getByPlaceholder("Search CIAS participants...").fill("Juan");
  await participants.getByRole("button", { name: /Juan dela Cruz Auditor/ }).click();
  await page.getByPlaceholder("Search CIAS participants...").fill("Maria");
  await participants.getByRole("button", { name: /Maria Santos Team Leader/ }).click();
  await page.getByRole("heading", { level: 1 }).last().click();
  if (info.project.name === "survey-desktop") {
    const date = await page.getByLabel("Proposed Date & Time").boundingBox();
    const venue = await page.getByLabel("Venue / Online Link").boundingBox();
    expect(Math.abs(date.y - venue.y)).toBeLessThan(2);
    const matters = await page.getByLabel("Key Matters to Discuss", { exact: true }).boundingBox();
    const notice = await page.locator(".survey-meeting-notice").boundingBox();
    expect(notice.y).toBeGreaterThan(matters.y + matters.height + 10);
  }
  await page.getByRole("button", { name: "Save Draft", exact: true }).click();
  await expect(page.getByRole("status")).toHaveText("Draft saved successfully.");
  await page.reload();
  await expect(page.getByLabel("Venue / Online Link")).toHaveValue("CIAS Conference Room");
  await expect(participants.getByText("Juan dela Cruz", { exact: true })).toBeVisible();
  await expect(participants.getByText("Maria Santos", { exact: true })).toBeVisible();
  await page.screenshot({ path: `test-results/reference-meeting-${info.project.name}.png`, fullPage: true });
});

test("section actions submit and approve drafts while Go to handles navigation", async ({ page }, info) => {
  let actor = "member";
  const people = { member: { id: 2, name: "Assigned Auditor" }, leader: { id: 3, name: "Team Leader" }, head: { id: 1, name: "CIAS Head" } };
  let survey = {};
  let sectionReviews = {};
  let lockVersion = null;
  await page.route("**/api/me", (route) => route.fulfill({ json: { success: true, data: { user: { ...people[actor], initials: "AU", permissions: ["aems.engagement.view", "aems.planning-package.view", "aems.planning-package.create", "aems.planning-package.update"] } } } }));
  await page.route(/\/api\/aems\/engagements\/9001\/preliminary-survey(?:\/review)?$/, async (route) => {
    const input = route.request().method() === "GET" ? null : route.request().postDataJSON();
    if (input) {
      const stamp = { user: people[actor], at: "2026-11-09T03:00:00Z" };
      const old = sectionReviews[input.section] ?? {};
      if (input.action) {
        sectionReviews[input.section] = { ...old, status: input.action === "SUBMIT" ? "PENDING_REVIEW" : "APPROVED", ...(input.action === "SUBMIT" ? { submittedBy: people[actor], submittedAt: stamp.at } : { approvedBy: people[actor], approvedAt: stamp.at }) };
      } else {
        survey[input.section] = input.data;
        sectionReviews[input.section] = { status: actor === "head" ? "APPROVED" : "DRAFT", lastSavedBy: people[actor], lastSavedAt: stamp.at, fieldSaves: Object.fromEntries(Object.keys(input.data).map((key) => [key, stamp])), ...(actor === "head" ? { approvedBy: people[actor], approvedAt: stamp.at } : {}) };
      }
      lockVersion = (lockVersion ?? 0) + 1;
    }
    const sectionCapabilities = Object.fromEntries(surveySections.map(([key]) => {
      const review = sectionReviews[key] ?? {};
      const pending = review.status === "PENDING_REVIEW";
      return [key, { canEdit: !pending || actor === "head", canSubmit: actor !== "head" && !!review.lastSavedBy && !pending && review.status !== "APPROVED", canApprove: pending && (actor === "head" || (actor === "leader" && review.lastSavedBy?.id !== people.leader.id)), autoApprove: actor === "head" }];
    }));
    await route.fulfill({ json: { success: true, data: { survey, sectionReviews, sectionCapabilities, lockVersion, canEdit: true, packageStatus: "DRAFT", attachments: [], participantOptions: [] } } });
  });
  await page.goto("/audit-engagement-management/preliminary-survey?engagementId=9001&section=meeting");
  await page.getByLabel("Venue / Online Link").fill("Reviewed venue");
  await page.getByRole("button", { name: "Save Draft", exact: true }).click();
  await expect(page.getByText(/Saved by Assigned Auditor/).first()).toBeVisible();
  await page.getByLabel("More Actions", { exact: true }).click();
  await expect(page.getByRole("button", { name: "1.2 Understanding of the Auditee", exact: true })).not.toBeVisible();
  await page.getByRole("button", { name: "Submit for Review", exact: true }).click();
  await expect(page.getByLabel("Section review status")).toContainText("Pending Review");
  await expect(page.getByLabel("Venue / Online Link")).toBeDisabled();
  actor = "leader";
  await page.reload();
  await page.getByLabel("More Actions", { exact: true }).click();
  await page.getByRole("button", { name: "Approve Draft", exact: true }).click();
  await expect(page.getByLabel("Section review status")).toContainText("Approved by Team Leader");
  await page.getByLabel("Go to", { exact: true }).click();
  await page.getByRole("button", { name: "1.2 Understanding of the Auditee", exact: true }).click();
  await page.getByLabel("Mandate", { exact: true }).fill("Leader mandate");
  await page.getByRole("button", { name: "Save Draft", exact: true }).click();
  await page.getByLabel("More Actions", { exact: true }).click();
  await page.getByRole("button", { name: "Submit for Review", exact: true }).click();
  await expect(page.getByLabel("Section review status")).toContainText("Pending Review");
  await page.getByLabel("More Actions", { exact: true }).click();
  await expect(page.getByRole("button", { name: "Approve Draft", exact: true })).toHaveCount(0);
  actor = "head";
  await page.reload();
  await page.getByLabel("More Actions", { exact: true }).click();
  await page.getByRole("button", { name: "Approve Draft", exact: true }).click();
  await expect(page.getByLabel("Section review status")).toContainText("Approved by CIAS Head");
  await page.getByLabel("Mandate", { exact: true }).fill("CIAS head revised mandate");
  await page.getByRole("button", { name: "Save Draft", exact: true }).click();
  await expect(page.getByRole("status")).toHaveText("Saved and automatically approved by the CIAS head.");
  await expect(page.getByLabel("Section review status")).toContainText("Last saved by CIAS Head");
  await page.screenshot({ path: `test-results/reference-section-approval-${info.project.name}.png`, fullPage: true });
  expect(await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1)).toBe(false);
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
