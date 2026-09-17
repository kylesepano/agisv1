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

async function expectResponsiveAemsShell(page, heading) {
  await expect(
    page.getByRole("heading", { level: 2, name: heading, exact: true }),
  ).toBeVisible();
  const main = page.locator("main").first();
  await expect(main).toBeVisible();
  const padding = await main.evaluate((element) => {
    const style = window.getComputedStyle(element);
    return {
      left: Number.parseFloat(style.paddingLeft),
      right: Number.parseFloat(style.paddingRight),
    };
  });
  expect(padding.left).toBeGreaterThanOrEqual(16);
  expect(padding.right).toBeGreaterThanOrEqual(16);
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

test.beforeEach(async ({ page }) => {
  await signIn(page);
});

test("AEMS workflow pages preserve spacing and usability at the active viewport", async ({
  page,
  isMobile,
}) => {
  const pages = [
    [
      "/audit-engagement-management/working-papers",
      "Working Papers & Audit Evidence",
    ],
    ["/audit-engagement-management/issues", "Audit Issues"],
    [
      "/audit-engagement-management/findings",
      "Findings & Recommendations",
    ],
    [
      "/audit-engagement-management/auditee-responses",
      "Auditee Responses & Dialogue",
    ],
    [
      "/audit-engagement-management/entry-conferences",
      "Entry Conferences",
    ],
    ["/audit-engagement-management/dashboard", "AEMS Dashboard"],
  ];

  for (const [path, heading] of pages) {
    await openAuthenticatedPage(page, path);
    await expectResponsiveAemsShell(page, heading);
    await expect(page.getByText(/SQLSTATE|Undefined column/i)).toHaveCount(0);
  }

  await expect(
    page.getByRole("button", { name: /Export Progress CSV/i }),
  ).toBeVisible();

  if (!isMobile) {
    const activeEngagementsCard = page
      .locator("article")
      .filter({ hasText: "Active engagements" })
      .first();
    await activeEngagementsCard.hover();
    await expect
      .poll(() =>
        activeEngagementsCard.evaluate(
          (card) => getComputedStyle(card).translate,
        ),
      )
      .not.toBe("none");
  }

  if (isMobile) {
    await page.getByRole("button", { name: "Open navigation" }).click();
    const drawer = page.getByRole("complementary");
    await expect(
      drawer.getByRole("link", { name: "Audit Engagement Monitoring" }),
    ).toBeVisible();
    await expect(
      drawer.getByRole("link", { name: "Findings & Recommendations" }),
    ).toBeVisible();
    await expect(
      drawer.getByRole("link", { name: "Entry Conferences" }),
    ).toBeVisible();
    await expect(
      drawer.getByRole("link", { name: "Auditee Responses" }),
    ).toBeVisible();
  }
});

test("engagement workspace exposes lifecycle gates and the Entry Conference form", async ({
  page,
}) => {
  const engagementId = 999999;
  await page.route(
    new RegExp(`/api/aems/engagements/${engagementId}$`),
    async (route) => {
      await route.fulfill({
        contentType: "application/json",
        body: JSON.stringify({
          success: true,
          data: {
            engagement: {
              id: engagementId,
              engagementCode: "AEMS-UI-001",
              title: "Lifecycle UI Regression Engagement",
              sourceType: "SPECIAL",
              sourceSnapshot: {},
              status: "ENGAGEMENT_PLANNING",
              isArchived: false,
            },
          },
        }),
      });
    },
  );
  await page.route(
    new RegExp(`/api/aems/engagements/${engagementId}/lifecycle$`),
    async (route) => {
      await new Promise((resolve) => setTimeout(resolve, 150));
      await route.fulfill({
        contentType: "application/json",
        body: JSON.stringify({
          success: true,
          data: {
            engagement: {
              id: engagementId,
              engagementCode: "AEMS-UI-001",
              title: "Lifecycle UI Regression Engagement",
              status: "ENGAGEMENT_PLANNING",
              lockVersion: 4,
              isArchived: false,
            },
            states: [
              "DRAFT",
              "AUTHORIZATION_PREPARATION",
              "AUTHORIZED",
              "ENGAGEMENT_PLANNING",
              "ENTRY_CONFERENCE",
              "FIELDWORK",
              "FINDINGS_COMMUNICATION",
              "REPORTING",
              "ISSUED",
              "CLOSURE_REVIEW",
              "CLOSED",
            ],
            actions: [
              {
                action: "START_ENTRY_CONFERENCE",
                label: "Start Entry Conference",
                targetStatus: "ENTRY_CONFERENCE",
                requiresComment: false,
                canExecute: false,
                blockers: ["Current Audit Program is approved"],
                requirements: [
                  {
                    key: "approvedAep",
                    label: "Current AEP is approved",
                    met: true,
                  },
                  {
                    key: "approvedProgram",
                    label: "Current Audit Program is approved",
                    met: false,
                  },
                ],
              },
            ],
            timeline: [
              {
                id: 1,
                action: "START_PLANNING",
                fromStatus: "AUTHORIZED",
                toStatus: "ENGAGEMENT_PLANNING",
                comment: null,
                actor: { id: 3, name: "CIAS Management" },
                createdAt: new Date().toISOString(),
              },
            ],
            relatedLinks: [
              {
                label: "AEP",
                path: `/audit-engagement-management/aep?engagementId=${engagementId}`,
              },
            ],
            closureDeferred: false,
          },
        }),
      });
    },
  );
  await page.route(
    new RegExp(`/api/aems/engagements/${engagementId}/entry-conference$`),
    async (route) => {
      await route.fulfill({
        contentType: "application/json",
        body: JSON.stringify({
          success: true,
          data: {
            engagement: {
              id: engagementId,
              engagementCode: "AEMS-UI-001",
              title: "Lifecycle UI Regression Engagement",
              status: "ENTRY_CONFERENCE",
              lockVersion: 5,
            },
            conference: null,
            references: {
              users: [],
              offices: [],
              statuses: [
                "DRAFT",
                "SCHEDULED",
                "RESCHEDULED",
                "HELD",
                "NOTES_FOR_ACKNOWLEDGEMENT",
                "ACKNOWLEDGED",
                "COMPLETED",
                "WAIVED",
                "CANCELLED",
              ],
              attachmentCategories: [
                "BRIEFING_PAPER",
                "AGENDA",
                "CONFERENCE_NOTES",
                "WAIVER_SUPPORT",
                "OTHER",
              ],
            },
            history: [],
          },
        }),
      });
    },
  );

  await openAuthenticatedPage(
    page,
    `/audit-engagement-management/${engagementId}?tab=lifecycle`,
  );
  await expect(page.getByTestId("lifecycle-loading")).toBeVisible();
  await expect(page.getByTestId("aems-lifecycle-workspace")).toBeVisible();
  await expect(page.getByTestId("lifecycle-timeline")).toBeVisible();
  await expect(page.getByText("Allowed actions and gates")).toBeVisible();
  await expect(
    page.getByText("Current Audit Program is approved"),
  ).toBeVisible();
  await expect(page.getByText("Formal closure available")).toBeVisible();

  await page
    .getByRole("button", { name: "Entry Conference", exact: true })
    .click();
  await expect(page.getByTestId("entry-conference-workspace")).toBeVisible();
  await expect(
    page.getByRole("heading", {
      name: "Entry Conference (Entrance Conference)",
    }),
  ).toBeVisible();
  await page.getByRole("button", { name: "Create conference" }).click();
  await expect(page.getByTestId("entry-conference-form")).toBeVisible();
  await expect(page.getByLabel("Agenda")).toBeVisible();
  await expect(page.getByText("Briefing paper", { exact: true })).toBeVisible();
  await page.getByRole("button", { name: "Cancel", exact: true }).click();
});

test("engagement workspace exposes formal closure records and immutable closed state", async ({
  page,
}) => {
  const engagementId = 999998;
  const engagement = {
    id: engagementId,
    engagementCode: "AEMS-CLOSE-UI-001",
    title: "Formal Closure UI Regression Engagement",
    sourceType: "SPECIAL",
    sourceSnapshot: {},
    status: "CLOSED",
    isArchived: false,
  };
  await page.route(
    new RegExp(`/api/aems/engagements/${engagementId}$`),
    (route) =>
      route.fulfill({
        contentType: "application/json",
        body: JSON.stringify({ success: true, data: { engagement } }),
      }),
  );
  await page.route(
    new RegExp(
      `/api/aems/engagements/${engagementId}/completion-assessments$`,
    ),
    (route) =>
      route.fulfill({
        contentType: "application/json",
        body: JSON.stringify({
          success: true,
          data: {
            currentAssessment: {
              id: 1,
              statusCode: "APPROVED",
              overallResultCode: "SATISFACTORY",
              objectivesAchievementSummary: "Objectives achieved.",
              scopeCompletionSummary: "Scope completed.",
              methodologyAssessment: "Methodology complied.",
              standardsComplianceAssessment: "Standards complied.",
              evidenceSufficiencyAssessment: "Evidence was sufficient.",
              supervisionAssessment: "Supervision was complete.",
              reportTimelinessAssessment: "Report issued.",
              managementResponseAssessment: "Dialogue completed.",
              recommendationTransferAssessment: "CMS complete.",
              resourceUtilizationAssessment: "Effort recorded.",
              recommendationForClosure: "Approve closure.",
              lockVersion: 3,
              items: [
                {
                  id: 1,
                  criterionCode: "ENGAGEMENT_OBJECTIVES_ACHIEVED",
                  resultCode: "PASS",
                  plannedValue: "Approved objectives",
                  actualValue: "Completed objectives",
                  explanation: "Verified from the issued report.",
                  blockingFlag: true,
                  blockerAccepted: false,
                },
              ],
            },
            revisions: [],
          },
        }),
      }),
  );
  const closureWorkspace = {
    engagement: {
      ...engagement,
      isClosed: true,
      lockVersion: 12,
    },
    closure: {
      id: 7,
      statusCode: "CLOSED",
      closureSummary: "Formally closed.",
      completionAssessmentStatus: "APPROVED",
      documentIndexLockedAt: new Date().toISOString(),
      readiness: { ready: true },
      lockVersion: 6,
      timeline: [],
    },
    readiness: { ready: true, percentage: 100, blockers: [], warnings: [] },
    evaluatedChecklist: [
      {
        checklistCode: "FINAL_REPORT_ISSUED",
        checklistCategoryCode: "REPORTING",
        description: "Final Report is issued",
        explanation: "Issued version and checksum verified.",
        resultCode: "PASS",
        sourcePath: `/audit-engagement-management/reports?engagementId=${engagementId}`,
      },
    ],
    cms: { total: 1, transferred: 1, excluded: 0 },
    permittedActions: [],
    retention: {
      id: 9,
      retentionClassificationCode: "AUDIT_ENGAGEMENT_RECORD",
      retentionTriggerCode: "ENGAGEMENT_CLOSED",
      retentionStartDate: "2026-07-30",
      retentionPeriodValue: 10,
      retentionPeriodUnit: "YEARS",
      permanentFlag: false,
      custodianUserId: 3,
      custodianOfficeId: 1,
      approvedAt: new Date().toISOString(),
    },
    retentionOptions: {
      custodians: [{ id: 3, name: "Records Custodian" }],
      offices: [{ id: 1, name: "CIAS" }],
    },
    lessonsLearned: [],
    events: [],
  };
  await page.route(
    new RegExp(`/api/aems/engagements/${engagementId}/closure$`),
    (route) =>
      route.fulfill({
        contentType: "application/json",
        body: JSON.stringify({ success: true, data: closureWorkspace }),
      }),
  );
  await page.route(
    new RegExp(`/api/aems/engagements/${engagementId}/reopen-requests$`),
    (route) =>
      route.fulfill({
        contentType: "application/json",
        body: JSON.stringify({ success: true, data: { requests: [] } }),
      }),
  );
  await page.route(
    new RegExp(`/api/aems/engagements/${engagementId}/document-index$`),
    (route) =>
      route.fulfill({
        contentType: "application/json",
        body: JSON.stringify({
          success: true,
          data: {
            lockedAt: new Date().toISOString(),
            summary: {
              total: 1,
              included: 1,
              excluded: 0,
              eligibleMissing: 0,
              brokenReferences: 0,
            },
            eligibleMissing: [],
            items: [
              {
                id: 1,
                sequenceNo: 1,
                recordCategoryCode: "FINAL_REPORT",
                referenceCode: "AEMS-FR-001",
                title: "Issued Final Report",
                documentVersionId: 44,
                versionLabel: "v1",
                checksum: "abc123",
                fileAvailable: true,
                includedFlag: true,
              },
            ],
          },
        }),
      }),
  );

  await openAuthenticatedPage(
    page,
    `/audit-engagement-management/${engagementId}?tab=completion-assessment`,
  );
  await expect(page.getByTestId("completion-assessment-workspace")).toBeVisible();
  await expect(page.getByText("Required assessment areas")).toBeVisible();

  await page.getByRole("button", { name: "Closure", exact: true }).click();
  await expect(page.getByTestId("closure-workspace")).toBeVisible();
  await expect(page.getByText("Immutable closed engagement")).toBeVisible();
  await expect(page.getByText("Final Report is issued")).toBeVisible();

  await page
    .getByRole("button", { name: "Final Document Index", exact: true })
    .click();
  await expect(page.getByTestId("document-index-workspace")).toBeVisible();
  await expect(page.getByText("Issued Final Report")).toBeVisible();
  await expect(page.getByText(/Final index locked at/)).toBeVisible();

  await page.getByRole("button", { name: "Retention", exact: true }).click();
  await expect(page.getByTestId("retention-workspace")).toBeVisible();
  await expect(page.getByText(/Approved retention metadata is immutable/)).toBeVisible();

  await page
    .getByRole("button", { name: "Lessons Learned", exact: true })
    .click();
  await expect(page.getByTestId("lessons-workspace")).toBeVisible();
  await expect(page.getByText(/never alter Findings/)).toBeVisible();
});
