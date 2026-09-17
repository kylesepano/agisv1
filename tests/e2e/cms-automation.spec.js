import { expect, test } from "@playwright/test";

async function signIn(page) {
  await page.goto("/login");
  await page.getByLabel("Employee ID").fill("CIAS-HEAD-001");
  await page.locator("#password").fill("lala");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/dashboard$/);
}

async function json(route, data, status = 200) {
  await route.fulfill({
    status,
    contentType: "application/json",
    body: JSON.stringify({ success: true, data }),
  });
}

function rule(overrides = {}) {
  return {
    id: 1,
    ruleCode: "CMS_TARGET_DATE_REMINDER",
    name: "Target-date reminders",
    description: "Reminds authorized recipients about approaching target dates.",
    ruleType: "REMINDER",
    statusCode: "ACTIVE",
    scheduleCode: "DAILY",
    configuration: { daysAhead: 7 },
    currentVersion: {
      id: 11,
      versionNumber: 1,
      effectiveFrom: "2026-08-01T00:00:00Z",
      configuration: { daysAhead: 7 },
    },
    ...overrides,
  };
}

function closureCandidate(overrides = {}) {
  return {
    id: 81,
    statusCode: "OPEN",
    detectedAt: "2026-08-07T09:00:00Z",
    readiness: {
      eligible: true,
      checklist: [
        { code: "case_status", label: "Recommendation is IMPLEMENTED", passed: true, blocking: true },
        { code: "validation", label: "Finalized validation concludes IMPLEMENTED", passed: true, blocking: true },
      ],
    },
    case: {
      id: 73,
      code: "CMS-REC-000073",
      status: "IMPLEMENTED",
      responsibleOffice: { id: 7, code: "ICT", name: "Information and Communications Technology Office" },
    },
    ...overrides,
  };
}

function escalationCandidate(overrides = {}) {
  return {
    id: 91,
    statusCode: "OPEN",
    triggerCode: "OVERDUE_TARGET_DATE",
    severityCode: "HIGH",
    reason: "The effective target date is 30 days overdue.",
    detectedAt: "2026-08-07T09:00:00Z",
    triggerSnapshot: { overdueDays: 30, automationOnly: true },
    case: { id: 74, code: "CMS-REC-000074", status: "MONITORING", responsibleOffice: { name: "Finance Office" } },
    ...overrides,
  };
}

async function mockAutomation(page, { closure = closureCandidate(), escalation = escalationCandidate() } = {}) {
  let reviewRequest;
  await page.route(/\/api\/cms\/automation\/dashboard$/, (route) => json(route, {
    activeRules: 3,
    openClosureCandidates: closure?.statusCode === "DISMISSED" ? 0 : 1,
    openEscalationCandidates: escalation?.statusCode === "DISMISSED" ? 0 : 1,
    recentReminderCount: 4,
    lastRunAt: "2026-08-07T07:00:00Z",
  }));
  await page.route(/\/api\/cms\/automation\/rules$/, async (route) => {
    if (route.request().method() === "POST") return json(route, { rule: rule({ id: 4, ruleCode: "CMS_CUSTOM_RULE", name: "Custom reminder" }) }, 201);
    return json(route, { rules: [rule()] });
  });
  await page.route(/\/api\/cms\/automation\/runs$/, (route) => json(route, {
    runs: [{ id: 31, ruleCode: "CMS_TARGET_DATE_REMINDER", statusCode: "COMPLETED", startedAt: "2026-08-07T07:00:00Z", scannedCount: 12, createdCount: 4, errorCount: 0 }],
  }));
  await page.route(/\/api\/cms\/automation\/candidates$/, (route) => json(route, {
    closureCandidates: { data: closure ? [closure] : [] },
    escalationCandidates: { data: escalation ? [escalation] : [] },
  }));
  await page.route(/\/api\/cms\/automation\/closure-candidates\/\d+\/review$/, async (route) => {
    reviewRequest = JSON.parse(route.request().postData() || "{}");
    return json(route, { candidate: closureCandidate({ statusCode: reviewRequest.action === "DISMISS" ? "DISMISSED" : "ACKNOWLEDGED", reviewNote: reviewRequest.reviewNote }) });
  });
  await page.route(/\/api\/cms\/automation\/escalation-candidates\/\d+\/review$/, async (route) => {
    reviewRequest = JSON.parse(route.request().postData() || "{}");
    return json(route, { candidate: escalationCandidate({ statusCode: reviewRequest.action === "DISMISS" ? "DISMISSED" : "ACKNOWLEDGED", reviewNote: reviewRequest.reviewNote }) });
  });
  return { getReviewRequest: () => reviewRequest };
}

test("renders CMS automation summary, rules, candidates, and run history", async ({ page }) => {
  await signIn(page);
  await mockAutomation(page);
  await page.goto("/compliance-management/automation");

  await expect(page.getByRole("heading", { name: "CMS Automation & Candidate Review", exact: true })).toBeVisible();
  await expect(page.getByText("Active rules", { exact: true })).toBeVisible();
  await expect(page.getByText("Automation identifies readiness", { exact: false })).toBeVisible();

  await page.getByRole("tab", { name: "Candidate review" }).click();
  await expect(page.getByText("CMS-REC-000073", { exact: true })).toBeVisible();
  await expect(page.getByText("notices are never issued automatically", { exact: false })).toBeVisible();

  await page.getByRole("tab", { name: "Run history" }).click();
  await expect(page.getByText("CMS_TARGET_DATE_REMINDER", { exact: true })).toBeVisible();
});

test("creates a versioned automation rule through the workspace", async ({ page }) => {
  await signIn(page);
  await mockAutomation(page);
  await page.goto("/compliance-management/automation");
  await page.getByRole("button", { name: "New rule" }).first().click();
  await page.getByLabel("Rule code").fill("CMS_CUSTOM_RULE");
  await page.getByLabel("Name").fill("Custom reminder");
  await page.getByRole("button", { name: "Save version" }).click();
  await expect(page.getByText("Automation rule created.", { exact: true })).toBeVisible();
});

test("acknowledges a candidate without changing the recommendation status", async ({ page }) => {
  await signIn(page);
  const automation = await mockAutomation(page);
  await page.goto("/compliance-management/automation");
  await page.getByRole("tab", { name: "Candidate review" }).click();
  await page.getByRole("button", { name: "Acknowledge" }).first().click();
  await page.getByLabel("Review note").fill("Reviewed against the finalized validation record.");
  await page.getByRole("button", { name: "Acknowledge candidate" }).click();
  await expect(page.getByText("Candidate acknowledged.", { exact: true })).toBeVisible();
  expect(automation.getReviewRequest()).toMatchObject({ action: "ACKNOWLEDGE", reviewNote: "Reviewed against the finalized validation record." });
  await expect(page.getByText("The recommendation case status remains unchanged.", { exact: false })).not.toBeVisible();
});
