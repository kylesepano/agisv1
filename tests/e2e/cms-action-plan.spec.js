import { expect, test } from "@playwright/test";

const caseId = 73;
const ownerOffice = {
  id: 2,
  code: "BPLD",
  name: "Business Permits and Licensing Division",
  acronym: "BPLD",
};
const focalUser = {
  id: 22,
  employeeId: "BPLD-HEAD",
  name: "BPLD Office Head",
  initials: "BO",
};

async function signIn(page, employeeId) {
  await page.goto("/login");
  await page.getByLabel("Employee ID").fill(employeeId);
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

function recommendation(overrides = {}) {
  return {
    id: caseId,
    cmsRecommendationCode: "CMS-REC-000073",
    status: "TRANSFERRED",
    lockVersion: 3,
    recommendation:
      "Standardize permit review controls and retain evidence of supervisory approval.",
    responsibleOffice: ownerOffice,
    officeAccountability: { leadResponsibleOffice: ownerOffice },
    effectiveTargetDate: "2026-12-31",
    actionPlanSummary: {
      hasActionPlan: false,
      permittedActions: [],
    },
    ...overrides,
  };
}

function milestone(id, sequenceNumber, title, weightPercentage = null) {
  return {
    id,
    sequenceNumber,
    title,
    description: `${title} description`,
    expectedOutput: `${title} output`,
    successIndicator: `${title} indicator`,
    verificationMethod: "Document inspection",
    responsibleOffice: ownerOffice,
    responsibleUser: focalUser,
    plannedStartDate: "2026-08-01",
    plannedTargetDate: "2026-10-31",
    weightPercentage,
    displayOrder: sequenceNumber,
  };
}

function actionPlanVersion(overrides = {}) {
  return {
    id: 101,
    displayCode: "CAP-CMS-REC-000073-V1",
    versionNumber: 1,
    status: "DRAFT",
    previousVersionId: null,
    planSummary: "Management will standardize permit review controls.",
    implementationStrategy: "Issue procedures and perform staff orientation.",
    expectedOutcome: "Every permit receives documented supervisory review.",
    rootCauseResponse: "The plan addresses inconsistent review practice.",
    resourcesRequired: "Existing operating resources.",
    dependencies: "Management approval.",
    risksAndConstraints: "Scheduling availability.",
    plannedStartDate: "2026-08-01",
    plannedTargetDate: "2026-12-15",
    ownerOffice,
    focalUser,
    preparedBy: focalUser,
    submittedBy: null,
    submittedAt: null,
    reviewStartedBy: null,
    reviewStartedAt: null,
    acceptedBy: null,
    acceptedAt: null,
    acceptanceComment: null,
    returnedBy: null,
    returnedAt: null,
    returnReason: null,
    revisionReason: null,
    hasSubmissionSnapshot: false,
    lockVersion: 4,
    milestones: [
      milestone(501, 1, "Approve procedure", 50),
      milestone(502, 2, "Orient staff", 50),
    ],
    completeness: {
      complete: true,
      errors: {},
      missingTargetDatePolicy: false,
      weightingUsed: true,
    },
    availableActions: ["update", "submit"],
    isCurrent: true,
    isAcceptedCurrent: false,
    isSuperseded: false,
    createdAt: "2026-07-31T08:00:00+08:00",
    ...overrides,
  };
}

function actionPlan(versions, currentVersionId, acceptedVersionId = null, status = "FOR_ACTION_PLAN") {
  const normalized = versions.map((version) => ({
    ...version,
    isCurrent: version.id === currentVersionId,
    isAcceptedCurrent: version.id === acceptedVersionId,
    isSuperseded:
      version.status === "ACCEPTED" &&
      acceptedVersionId !== null &&
      version.id !== acceptedVersionId,
  }));
  return {
    id: 31,
    caseId,
    displayCode: "CAP-CMS-REC-000073",
    ownerOffice,
    createdBy: focalUser,
    currentVersionId,
    acceptedVersionId,
    lockVersion: 5,
    currentVersion:
      normalized.find((version) => version.id === currentVersionId) ?? null,
    acceptedVersion:
      normalized.find((version) => version.id === acceptedVersionId) ?? null,
    versions: [...normalized].sort(
      (left, right) => right.versionNumber - left.versionNumber,
    ),
    caseContext: {
      id: caseId,
      cmsRecommendationCode: "CMS-REC-000073",
      status,
      recommendationCode: "REC-2026-0073",
      recommendation:
        "Standardize permit review controls and retain evidence of supervisory approval.",
      responsibleOffice: ownerOffice,
      originalTargetDate: "2026-12-31",
      effectiveTargetDate: "2026-12-31",
      currentMonitor: {
        id: 3,
        employeeId: "CIAS-AUD-001",
        name: "Internal Auditor",
      },
    },
  };
}

async function fulfillJson(route, data, status = 200) {
  await route.fulfill({
    status,
    contentType: "application/json",
    body: JSON.stringify(data),
  });
}

async function mockRecommendation(page, currentRecommendation = recommendation()) {
  await page.route(
    new RegExp(`/api/cms/recommendations/${caseId}$`),
    (route) =>
      fulfillJson(route, {
        success: true,
        data: { recommendation: currentRecommendation },
      }),
  );
}

async function assertNoHorizontalOverflow(page) {
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

test("responsible office creates, edits, reorders, submits, and cannot double-submit", async ({
  page,
}) => {
  await signIn(page, "BPLD-HEAD");
  await mockRecommendation(page);

  let plan = null;
  let createAttempts = 0;
  let createdPayload;
  let submitPayload;
  let submitRequests = 0;

  await page.route(
    new RegExp(`/api/cms/recommendations/${caseId}/action-plan$`),
    (route) =>
      fulfillJson(route, {
        success: true,
        data: {
          actionPlan: plan,
          caseContext: {
            id: caseId,
            cmsRecommendationCode: "CMS-REC-000073",
            status: plan?.caseContext.status ?? "TRANSFERRED",
            responsibleOffice: ownerOffice,
            originalTargetDate: "2026-12-31",
            effectiveTargetDate: "2026-12-31",
          },
          permittedActions: plan ? [] : ["create"],
        },
      }),
  );
  await page.route(
    new RegExp(`/api/cms/recommendations/${caseId}/action-plans$`),
    async (route) => {
      createAttempts += 1;
      createdPayload = route.request().postDataJSON();
      if (createAttempts === 1) {
        await fulfillJson(
          route,
          {
            message: "The given data was invalid.",
            errors: {
              planSummary: ["Confirm the management commitment wording."],
              "milestones.0.title": ["Confirm the first milestone title."],
            },
          },
          422,
        );
        return;
      }
      const draft = actionPlanVersion({
        focalUser,
        milestones: createdPayload.milestones.map((item, index) => ({
          ...milestone(600 + index, index + 1, item.title, item.weightPercentage),
          description: item.description,
          expectedOutput: item.expectedOutput,
          successIndicator: item.successIndicator,
          verificationMethod: item.verificationMethod,
          plannedStartDate: item.plannedStartDate,
          plannedTargetDate: item.plannedTargetDate,
        })),
        planSummary: createdPayload.planSummary,
        implementationStrategy: createdPayload.implementationStrategy,
        expectedOutcome: createdPayload.expectedOutcome,
        plannedStartDate: createdPayload.plannedStartDate,
        plannedTargetDate: createdPayload.plannedTargetDate,
      });
      plan = actionPlan([draft], draft.id);
      await fulfillJson(
        route,
        { success: true, data: { actionPlan: plan } },
        201,
      );
    },
  );
  await page.route(
    /\/api\/cms\/action-plans\/31\/versions\/101\/transitions\/submit$/,
    async (route) => {
      submitRequests += 1;
      submitPayload = route.request().postDataJSON();
      await new Promise((resolve) => setTimeout(resolve, 200));
      const submitted = {
        ...plan.currentVersion,
        status: "SUBMITTED",
        lockVersion: 5,
        submittedBy: focalUser,
        submittedAt: "2026-07-31T09:00:00+08:00",
        hasSubmissionSnapshot: true,
        availableActions: [],
      };
      plan = actionPlan([submitted], submitted.id);
      await fulfillJson(route, {
        success: true,
        data: { actionPlan: plan },
      });
    },
  );

  await openAuthenticatedPage(
    page,
    `/compliance-management/recommendations/${caseId}/action-plan`,
  );
  await expect(
    page.getByRole("heading", {
      level: 2,
      name: "Corrective Action Plan",
      exact: true,
    }),
  ).toBeVisible();
  await expect(page.getByText("No Corrective Action Plan yet")).toBeVisible();
  await page
    .getByRole("button", { name: "Create Action Plan draft" })
    .click();

  await page.getByLabel("Plan summary").fill("Standardize permit reviews.");
  await page
    .getByLabel("Implementation strategy")
    .fill("Issue procedures, orient staff, and verify adoption.");
  await page
    .getByLabel("Expected outcome")
    .fill("Every permit has documented supervisory approval.");
  await page.getByLabel("Planned start date").fill("2026-08-01");
  await page.getByLabel("Planned target date").fill("2026-12-15");

  await page.getByRole("button", { name: "Add milestone" }).click();
  await page.getByRole("button", { name: "Add milestone" }).click();
  await page.locator("#milestone-0-title").fill("Approve procedure");
  await page.locator("#milestone-0-output").fill("Approved procedure");
  await page.locator("#milestone-0-start").fill("2026-08-01");
  await page.locator("#milestone-0-target").fill("2026-09-15");
  await page.locator("#milestone-0-weight").fill("40");
  await expect(
    page.getByText(
      "Every milestone must be weighted when any weight is used.",
      { exact: true },
    ),
  ).toBeVisible();
  await page.locator("#milestone-1-title").fill("Orient staff");
  await page.locator("#milestone-1-output").fill("Orientation records");
  await page.locator("#milestone-1-start").fill("2026-09-16");
  await page.locator("#milestone-1-target").fill("2026-10-31");
  await page.locator("#milestone-1-weight").fill("60");
  await expect(page.getByText(/100\.00% across 2 of 2 milestones/)).toBeVisible();

  await page
    .getByRole("button", { name: "Move milestone 1 down" })
    .click();
  await expect(page.locator("#milestone-0-title")).toHaveValue("Orient staff");
  await page.getByRole("button", { name: "Remove milestone 1" }).click();
  await expect(page.locator("#milestone-0-title")).toHaveValue(
    "Approve procedure",
  );
  await page.getByRole("button", { name: "Add milestone" }).click();
  await page.locator("#milestone-1-title").fill("Verify adoption");
  await page.locator("#milestone-1-output").fill("Verification report");
  await page.locator("#milestone-1-start").fill("2026-09-16");
  await page.locator("#milestone-1-target").fill("2026-12-15");
  await page.locator("#milestone-1-weight").fill("60");

  await page.getByRole("button", { name: "Create draft" }).click();
  await expect(
    page
      .getByRole("paragraph")
      .filter({ hasText: "Confirm the management commitment wording." }),
  ).toBeVisible();
  await expect(page.getByLabel("Plan summary")).toHaveValue(
    "Standardize permit reviews.",
  );
  await page.getByRole("button", { name: "Create draft" }).click();
  await expect(
    page.getByText("Corrective Action Plan draft created."),
  ).toBeVisible();

  expect(createdPayload.lockVersion).toBe(3);
  expect(createdPayload.focalUserId).toBeTruthy();
  expect(createdPayload.milestones.map((item) => item.title)).toEqual([
    "Approve procedure",
    "Verify adoption",
  ]);
  expect(createdPayload.milestones.map((item) => item.sequenceNumber)).toEqual([
    1, 2,
  ]);

  await page.getByRole("button", { name: "Submit", exact: true }).click();
  const submitDialog = page.getByRole("dialog", {
    name: "Review and submit Action Plan",
  });
  await expect(
    submitDialog.getByText(/creates an immutable snapshot/i),
  ).toBeVisible();
  await submitDialog
    .getByRole("button", { name: "Submit Action Plan" })
    .dblclick({ force: true });
  await expect(page.getByText("Action Plan submitted.")).toBeVisible();
  expect(submitRequests).toBe(1);
  expect(submitPayload).toEqual({ lockVersion: 4, confirmation: true });
  await expect(page.getByText("Submitted", { exact: true }).first()).toBeVisible();
  await expect(page.locator("#action-plan-summary")).toHaveCount(0);
  await assertNoHorizontalOverflow(page);
});

test("reviewer starts review and returns an immutable version with exact instructions", async ({
  page,
}) => {
  await signIn(page, "CIAS-HEAD-001");
  await mockRecommendation(
    page,
    recommendation({ status: "FOR_ACTION_PLAN", lockVersion: 4 }),
  );
  let reviewPayload;
  let returnPayload;
  let returnRequests = 0;
  let submitted = actionPlanVersion({
    status: "SUBMITTED",
    lockVersion: 5,
    submittedBy: focalUser,
    submittedAt: "2026-07-31T09:00:00+08:00",
    hasSubmissionSnapshot: true,
    availableActions: ["start-review"],
  });
  let plan = actionPlan([submitted], submitted.id);

  await page.route(
    new RegExp(`/api/cms/recommendations/${caseId}/action-plan$`),
    (route) =>
      fulfillJson(route, {
        success: true,
        data: { actionPlan: plan, permittedActions: [] },
      }),
  );
  await page.route(
    /\/api\/cms\/action-plans\/31\/versions\/101\/transitions\/start-review$/,
    async (route) => {
      reviewPayload = route.request().postDataJSON();
      submitted = {
        ...submitted,
        status: "UNDER_REVIEW",
        lockVersion: 6,
        reviewStartedBy: {
          id: 1,
          employeeId: "CIAS-HEAD-001",
          name: "CIAS Management",
        },
        reviewStartedAt: "2026-07-31T10:00:00+08:00",
        availableActions: ["return", "accept"],
      };
      plan = actionPlan([submitted], submitted.id);
      await fulfillJson(route, {
        success: true,
        data: { actionPlan: plan },
      });
    },
  );
  await page.route(
    /\/api\/cms\/action-plans\/31\/versions\/101\/transitions\/return$/,
    async (route) => {
      returnRequests += 1;
      returnPayload = route.request().postDataJSON();
      submitted = {
        ...submitted,
        status: "RETURNED",
        lockVersion: 7,
        returnedBy: {
          id: 1,
          employeeId: "CIAS-HEAD-001",
          name: "CIAS Management",
        },
        returnedAt: "2026-07-31T10:30:00+08:00",
        returnReason: returnPayload.returnReason,
        availableActions: [],
      };
      plan = actionPlan([submitted], submitted.id);
      await fulfillJson(route, {
        success: true,
        data: { actionPlan: plan },
      });
    },
  );

  await openAuthenticatedPage(
    page,
    `/compliance-management/recommendations/${caseId}/action-plan`,
  );
  await expect(page.locator("#action-plan-summary")).toHaveCount(0);
  await page.getByRole("button", { name: "Start review" }).click();
  await page
    .getByRole("dialog", { name: "Start Action Plan review" })
    .getByRole("button", { name: "Start review" })
    .click();
  await expect(page.getByText("Under Review", { exact: true }).first()).toBeVisible();
  expect(reviewPayload).toEqual({ lockVersion: 5 });

  await page.getByRole("button", { name: "Return" }).click();
  const returnDialog = page.getByRole("dialog", {
    name: "Return Action Plan",
  });
  await returnDialog
    .getByRole("button", { name: "Return Action Plan" })
    .click();
  await expect(
    returnDialog.getByText("Return instructions are required."),
  ).toBeVisible();
  expect(returnRequests).toBe(0);
  await returnDialog
    .getByLabel("Return instructions")
    .fill("Define a measurable verification method.");
  await returnDialog
    .getByRole("button", { name: "Return Action Plan" })
    .click();
  await expect(returnDialog).toBeHidden();
  expect(returnPayload).toEqual({
    lockVersion: 6,
    returnReason: "Define a measurable verification method.",
  });
  await expect(
    page.getByText("Define a measurable verification method."),
  ).toBeVisible();
  await expect(page.getByText("Immutable version")).toBeVisible();
});

test("acceptance treats a server 403 as authoritative and then establishes the baseline", async ({
  page,
}) => {
  await signIn(page, "CIAS-HEAD-001");
  await mockRecommendation(
    page,
    recommendation({ status: "FOR_ACTION_PLAN", lockVersion: 4 }),
  );
  let acceptRequests = 0;
  let acceptPayload;
  let reviewing = actionPlanVersion({
    status: "UNDER_REVIEW",
    lockVersion: 6,
    submittedBy: focalUser,
    submittedAt: "2026-07-31T09:00:00+08:00",
    reviewStartedBy: {
      id: 1,
      employeeId: "CIAS-HEAD-001",
      name: "CIAS Management",
    },
    reviewStartedAt: "2026-07-31T10:00:00+08:00",
    hasSubmissionSnapshot: true,
    availableActions: ["return", "accept"],
  });
  let plan = actionPlan([reviewing], reviewing.id);
  await page.route(
    new RegExp(`/api/cms/recommendations/${caseId}/action-plan$`),
    (route) =>
      fulfillJson(route, {
        success: true,
        data: { actionPlan: plan, permittedActions: [] },
      }),
  );
  await page.route(
    /\/api\/cms\/action-plans\/31\/versions\/101\/transitions\/accept$/,
    async (route) => {
      acceptRequests += 1;
      acceptPayload = route.request().postDataJSON();
      if (acceptRequests === 1) {
        await fulfillJson(
          route,
          { message: "An independent reviewer is required." },
          403,
        );
        return;
      }
      reviewing = {
        ...reviewing,
        status: "ACCEPTED",
        lockVersion: 7,
        acceptedBy: {
          id: 1,
          employeeId: "CIAS-HEAD-001",
          name: "CIAS Management",
        },
        acceptedAt: "2026-07-31T11:00:00+08:00",
        acceptanceComment: acceptPayload.acceptanceComment,
        availableActions: [],
      };
      plan = actionPlan(
        [reviewing],
        reviewing.id,
        reviewing.id,
        "MONITORING",
      );
      await fulfillJson(route, {
        success: true,
        data: { actionPlan: plan },
      });
    },
  );

  await openAuthenticatedPage(
    page,
    `/compliance-management/recommendations/${caseId}/action-plan`,
  );
  await page.getByRole("button", { name: "Accept" }).click();
  const acceptDialog = page.getByRole("dialog", {
    name: "Accept Action Plan",
  });
  await acceptDialog
    .getByLabel("Acceptance comment")
    .fill("The plan is measurable and responsive.");
  await acceptDialog.getByRole("checkbox").check();
  await acceptDialog
    .getByRole("button", { name: "Accept as baseline" })
    .click();
  await expect(
    page.getByText(
      "The server did not authorize this Action Plan action. Your local data was not submitted.",
    ),
  ).toBeVisible();
  await expect(acceptDialog).toBeVisible();
  await acceptDialog
    .getByRole("button", { name: "Accept as baseline" })
    .click();
  expect(acceptPayload).toEqual({
    lockVersion: 6,
    acceptanceComment: "The plan is measurable and responsive.",
    confirmation: true,
  });
  await expect(
    page.getByText("Accepted monitoring baseline: Version 1"),
  ).toBeVisible();
  await expect(page.getByText("Monitoring", { exact: true })).toBeVisible();
});

test("returned and superseded history creates a backend revision and preserves local work on conflict", async ({
  page,
}) => {
  await signIn(page, "BPLD-HEAD");
  await mockRecommendation(
    page,
    recommendation({ status: "MONITORING", lockVersion: 7 }),
  );
  const superseded = actionPlanVersion({
    id: 99,
    versionNumber: 1,
    displayCode: "CAP-CMS-REC-000073-V1",
    status: "ACCEPTED",
    lockVersion: 7,
    acceptedAt: "2026-05-01T09:00:00+08:00",
    acceptanceComment: "Original baseline.",
    availableActions: [],
  });
  const accepted = actionPlanVersion({
    id: 100,
    versionNumber: 2,
    displayCode: "CAP-CMS-REC-000073-V2",
    previousVersionId: 99,
    status: "ACCEPTED",
    lockVersion: 8,
    acceptedAt: "2026-06-01T09:00:00+08:00",
    acceptanceComment: "Current baseline.",
    availableActions: [],
  });
  let returned = actionPlanVersion({
    id: 101,
    versionNumber: 3,
    displayCode: "CAP-CMS-REC-000073-V3",
    previousVersionId: 100,
    status: "RETURNED",
    lockVersion: 9,
    returnedAt: "2026-07-30T09:00:00+08:00",
    returnReason: "Clarify milestone verification.",
    revisionReason: "Update the accepted plan.",
    availableActions: ["revise"],
  });
  let plan = actionPlan(
    [returned, accepted, superseded],
    returned.id,
    accepted.id,
    "MONITORING",
  );
  let revisionPayload;
  let staleSavePayload;

  await page.route(
    new RegExp(`/api/cms/recommendations/${caseId}/action-plan$`),
    (route) =>
      fulfillJson(route, {
        success: true,
        data: { actionPlan: plan, permittedActions: [] },
      }),
  );
  await page.route(
    /\/api\/cms\/action-plans\/31\/versions\/101\/revisions$/,
    async (route) => {
      revisionPayload = route.request().postDataJSON();
      const revision = {
        ...returned,
        id: 102,
        displayCode: "CAP-CMS-REC-000073-V4",
        versionNumber: 4,
        previousVersionId: 101,
        status: "DRAFT",
        lockVersion: 1,
        returnedAt: null,
        returnedBy: null,
        returnReason: null,
        revisionReason: revisionPayload.revisionReason,
        availableActions: ["update", "submit"],
        milestones: returned.milestones.map((item, index) => ({
          ...item,
          id: 700 + index,
        })),
      };
      plan = actionPlan(
        [revision, returned, accepted, superseded],
        revision.id,
        accepted.id,
        "MONITORING",
      );
      await fulfillJson(
        route,
        { success: true, data: { actionPlan: plan } },
        201,
      );
    },
  );
  await page.route(
    /\/api\/cms\/action-plans\/31\/versions\/102$/,
    async (route) => {
      staleSavePayload = route.request().postDataJSON();
      await fulfillJson(
        route,
        {
          message: "The Action Plan version changed. Refresh before retrying.",
          errors: {
            lockVersion: [
              "The Action Plan version changed. Refresh before retrying.",
            ],
          },
        },
        422,
      );
    },
  );

  await openAuthenticatedPage(
    page,
    `/compliance-management/recommendations/${caseId}/action-plan`,
  );
  await expect(
    page.getByText("Accepted monitoring baseline: Version 2"),
  ).toBeVisible();
  await page.getByRole("button", { name: /Version 1/ }).click();
  await expect(page.getByText("Superseded accepted version")).toBeVisible();
  await page.getByRole("button", { name: /Version 3/ }).click();
  await page.getByRole("button", { name: "Create revision" }).click();
  const revisionDialog = page.getByRole("dialog", {
    name: "Create controlled revision",
  });
  await revisionDialog
    .getByRole("button", { name: "Create revision" })
    .click();
  await expect(
    revisionDialog.getByText("A revision reason is required."),
  ).toBeVisible();
  await revisionDialog
    .getByLabel("Revision reason")
    .fill("Respond to the return instructions.");
  await revisionDialog
    .getByRole("button", { name: "Create revision" })
    .click();
  await expect(page.getByText("Version 4", { exact: true }).first()).toBeVisible();
  expect(revisionPayload).toEqual({
    lockVersion: 9,
    revisionReason: "Respond to the return instructions.",
  });
  await expect(
    page.getByText(/Remains authoritative while the current revision is pending/),
  ).toBeVisible();

  await page
    .getByLabel("Plan summary")
    .fill("Locally revised management commitment.");
  await page.getByRole("button", { name: "Save draft" }).click();
  await expect(
    page.getByText(/Your local draft has been preserved/),
  ).toBeVisible();
  expect(staleSavePayload.lockVersion).toBe(1);
  await expect(page.getByLabel("Plan summary")).toHaveValue(
    "Locally revised management commitment.",
  );
  await page.getByRole("button", { name: "Reload latest" }).click();
  await expect(page.getByLabel("Plan summary")).toHaveValue(
    "Locally revised management commitment.",
  );
  await assertNoHorizontalOverflow(page);
});

test("scope-safe 404 presents an unavailable workspace without disclosure", async ({
  page,
}) => {
  await signIn(page, "CIAS-HEAD-001");
  await page.route(
    new RegExp(`/api/cms/recommendations/${caseId}/action-plan$`),
    (route) =>
      fulfillJson(
        route,
        { message: "The Action Plan is unavailable." },
        404,
      ),
  );
  await page.route(
    new RegExp(`/api/cms/recommendations/${caseId}$`),
    (route) =>
      fulfillJson(
        route,
        { message: "The recommendation is unavailable." },
        404,
      ),
  );

  await openAuthenticatedPage(
    page,
    `/compliance-management/recommendations/${caseId}/action-plan`,
  );
  await expect(
    page.getByRole("heading", { name: "Action Plan unavailable" }),
  ).toBeVisible();
  await expect(
    page.getByText(/unavailable within your authorized scope/),
  ).toBeVisible();
  await expect(page.getByText("CAP-CMS-REC-000073")).toHaveCount(0);
});
