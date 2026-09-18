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

function recommendation(overrides = {}) {
  return {
    id: 42,
    cmsRecommendationCode: "CMS-REC-000042",
    status: "TRANSFERRED",
    openedAt: "2026-07-01T08:00:00+08:00",
    transferredAt: "2026-07-01T08:00:00+08:00",
    effectiveTargetDate: "2026-06-30",
    isOverdue: true,
    lockVersion: 3,
    recommendationCode: "REC-2026-0042",
    recommendation:
      "Strengthen quarterly access reviews and retain documented approval evidence.",
    finding: { code: "FND-042", title: "Access reviews were incomplete" },
    engagement: { code: "AEMS-026", title: "Information systems audit" },
    finalReportNumber: "FR-2026-026",
    risk: { id: 1, code: "HIGH", label: "High" },
    confidentiality: { id: 2, code: "INTERNAL", label: "Internal" },
    responsibleOffice: {
      id: 7,
      code: "ICT",
      name: "Information and Communications Technology Office",
      acronym: "ICTO",
    },
    currentMonitor: null,
    ...overrides,
  };
}

function detailRecommendation(overrides = {}) {
  return recommendation({
    intake: {
      id: 41,
      transferKey: "cms-transfer-42",
      transferredAt: "2026-07-01T08:00:00+08:00",
      transferredBy: {
        id: 1,
        employeeId: "CIAS-HEAD-001",
        name: "CIAS Management",
      },
      sourceSchemaVersion: "1.0",
      originalTargetDate: "2026-06-15",
      responsibleOfficeSnapshot: [{ id: 7, code: "ICT", name: "ICTO" }],
      confidentialitySnapshot: {
        id: 2,
        code: "INTERNAL",
        label: "Internal",
      },
      riskSnapshot: { id: 1, code: "HIGH", label: "High" },
    },
    sourceLineage: {
      engagement: {
        id: 26,
        code: "AEMS-026",
        title: "Information systems audit",
      },
      finding: {
        id: 42,
        code: "FND-042",
        title: "Access reviews were incomplete",
      },
      recommendation: {
        id: 84,
        code: "REC-2026-0042",
        wording:
          "Strengthen quarterly access reviews and retain documented approval evidence.",
      },
      report: {
        id: 13,
        finalReportNumber: "FR-2026-026",
        versionId: 17,
        versionNumber: 2,
        issuedAt: "2026-06-29T08:00:00+08:00",
        checksumSha256: "a".repeat(64),
      },
    },
    officeAccountability: {
      leadResponsibleOffice: {
        id: 7,
        code: "ICT",
        name: "Information and Communications Technology Office",
        acronym: "ICTO",
      },
      originalResponsibleOffices: [{ id: 7, code: "ICT", name: "ICTO" }],
    },
    assignments: [],
    timeline: [
      {
        id: 1,
        eventCode: "INTAKE_CREATED",
        sourceModule: "AEMS",
        previousStatus: null,
        newStatus: "TRANSFERRED",
        metadata: { caseLockVersion: 1 },
        createdAt: "2026-07-01T08:00:00+08:00",
        actor: { id: 1, name: "CIAS Management" },
      },
    ],
    ...overrides,
  });
}

function registryPayload(params = {}) {
  return {
    recommendations: [recommendation()],
    filters: {
      statuses: ["TRANSFERRED"],
      responsibleOffices: [
        { id: 7, code: "ICT", name: "Information and Communications Technology Office" },
      ],
      riskLevels: [{ id: 1, code: "HIGH", label: "High" }],
      confidentialityLevels: [
        { id: 2, code: "INTERNAL", label: "Internal" },
      ],
      assignedMonitors: [],
    },
    pagination: {
      currentPage: Number(params.page || 1),
      lastPage: 2,
      perPage: 25,
      total: 26,
      from: 1,
      to: 25,
    },
    evaluationDate: "2026-07-31",
  };
}

async function mockCms(
  page,
  { assignmentConflict = false, conflictOnSecondAssignment = false } = {},
) {
  let assignmentRequests = 0;
  await page.route(/\/api\/cms\/dashboard$/, async (route) => {
    await route.fulfill({
      contentType: "application/json",
      body: JSON.stringify({
        success: true,
        data: {
          evaluationDateTime: "2026-07-31T09:30:00+08:00",
          evaluationDate: "2026-07-31",
          scope: {
            portfolioWide: true,
            officeId: null,
            assignmentScoped: false,
            confidentiality: { confidential: true, restricted: true },
          },
          cards: {
            totalVisibleCases: 26,
            transferredOpenCases: 26,
            assignedCases: 18,
            unassignedCases: 8,
            overdueCases: 6,
            withoutTargetDate: 3,
            transferredThisMonth: 11,
            highRiskCases: 9,
            highRiskOverdueCases: 4,
          },
          groups: {
            byResponsibleOffice: [
              { id: 7, code: "ICT", label: "ICTO", count: 12 },
            ],
            byRiskLevel: [{ id: 1, code: "HIGH", label: "High", count: 9 }],
            byConfidentialityLevel: [
              { id: 2, code: "INTERNAL", label: "Internal", count: 26 },
            ],
            byAssignedMonitor: [
              { id: null, code: "UNASSIGNED", label: "Unassigned", count: 8 },
            ],
          },
          recentlyTransferred: [recommendation()],
          oldestUnresolvedTargetDates: [recommendation()],
          dueSoon: {
            available: false,
            reason: "No approved CMS due-soon runtime threshold is configured.",
          },
          dataLimitations: [
            "CMS-2A cases currently use the TRANSFERRED workflow state only.",
          ],
        },
      }),
    });
  });

  await page.route(/\/api\/cms\/recommendations\?.*|\/api\/cms\/recommendations$/, async (route) => {
    const url = new URL(route.request().url());
    await route.fulfill({
      contentType: "application/json",
      body: JSON.stringify({
        success: true,
        data: registryPayload(Object.fromEntries(url.searchParams)),
      }),
    });
  });

  await page.route(
    /\/api\/cms\/recommendations\/42\/assignments$/,
    async (route) => {
      if (route.request().method() === "POST") {
        assignmentRequests += 1;
        if (
          assignmentConflict ||
          (conflictOnSecondAssignment && assignmentRequests === 2)
        ) {
          await route.fulfill({
            status: 422,
            contentType: "application/json",
            body: JSON.stringify({
              message: "The CMS recommendation changed. Refresh before retrying.",
              errors: {
                lockVersion: [
                  "The CMS recommendation changed. Refresh before retrying.",
                ],
              },
            }),
          });
          return;
        }
        await route.fulfill({
          status: 201,
          contentType: "application/json",
          body: JSON.stringify({
            success: true,
            message: "Compliance Monitor assigned successfully.",
            data: {
              assignment: {
                id: 5,
                isCurrent: true,
                user: {
                  id: 12,
                  employeeId: "CIAS-MON-012",
                  name: "Alex Monitor",
                },
              },
              caseLockVersion: 4,
            },
          }),
        });
        return;
      }

      await route.fulfill({
        contentType: "application/json",
        body: JSON.stringify({
          success: true,
          data: {
            caseId: 42,
            lockVersion: 3,
            assignments: [],
            eligibleMonitors: [
              {
                id: 12,
                employeeId: "CIAS-MON-012",
                name: "Alex Monitor",
                officeId: 1,
              },
            ],
          },
        }),
      });
    },
  );

  await page.route(/\/api\/cms\/recommendations\/42$/, async (route) => {
    await route.fulfill({
      contentType: "application/json",
      body: JSON.stringify({
        success: true,
        data: { recommendation: detailRecommendation() },
      }),
    });
  });
}

test.beforeEach(async ({ page }) => {
  await signIn(page);
});

test("CMS dashboard, registry, and detail workspace remain responsive", async ({
  page,
  isMobile,
}) => {
  await mockCms(page, { conflictOnSecondAssignment: true });
  await openAuthenticatedPage(page, "/compliance-management/dashboard");

  await expect(
    page.getByRole("heading", {
      level: 2,
      name: "Compliance Management",
      exact: true,
    }),
  ).toBeVisible();
  await expect
    .poll(() =>
      page.locator("main").evaluate((workspace) =>
        Number.parseFloat(getComputedStyle(workspace).paddingInlineStart),
      ),
    )
    .toBeGreaterThanOrEqual(16);
  await expect(page.getByText("Total visible cases")).toBeVisible();
  await expect(page.getByText("Due-soon metric unavailable.")).toBeVisible();
  await page.getByRole("link", { name: /Overdue recommendations/ }).click();
  await expect(page).toHaveURL(
    /\/compliance-management\/recommendations\?overdue=1/,
  );
  await expect(
    page.getByRole("heading", {
      level: 2,
      name: "Recommendation Registry",
      exact: true,
    }),
  ).toBeVisible();
  await expect(page.getByText("26 results")).toBeVisible();
  await expect(page.getByText("CMS-REC-000042")).toBeVisible();

  const targetSortRequest = page.waitForRequest((request) => {
    const url = new URL(request.url());
    return (
      url.pathname === "/api/cms/recommendations" &&
      url.searchParams.get("sortBy") === "targetDate"
    );
  });
  await page.getByRole("button", { name: /Target date/ }).click();
  await targetSortRequest;

  await expect
    .poll(() =>
      page.evaluate(
        () =>
          document.documentElement.scrollWidth <=
          document.documentElement.clientWidth + 1,
      ),
    )
    .toBe(true);

  if (isMobile) {
    await page.getByRole("button", { name: "Open navigation" }).click();
    const drawer = page.getByRole("complementary");
    await expect(
      drawer.getByRole("button", { name: /Expand|Collapse Compliance Management/ }),
    ).toBeVisible();
    await expect(
      drawer.getByRole("link", { name: "Recommendation Registry" }),
    ).toBeVisible();
  }

  await openAuthenticatedPage(
    page,
    "/compliance-management/recommendations/42",
  );

  await expect(
    page.getByRole("heading", { name: "CMS-REC-000042", exact: true }),
  ).toBeVisible();
  await expect(page.getByText("Exact recommendation wording")).toBeVisible();
  await page.getByRole("tab", { name: "Source & Lineage" }).click();
  await expect(page.getByText("Immutable AEMS source lineage")).toBeVisible();
  await expect(page.getByText("FR-2026-026")).toBeVisible();

  await page.getByRole("tab", { name: "Assignments" }).click();
  await page.getByRole("button", { name: "Assign Monitor" }).click();
  const assignmentDialog = page.getByRole("dialog", {
    name: "Assign Compliance Monitor",
  });
  await assignmentDialog.locator("#cms-monitor-user").selectOption("12");
  await assignmentDialog
    .getByLabel("Assignment reason")
    .fill("Portfolio assignment");
  await assignmentDialog.getByRole("button", { name: "Assign monitor" }).click();
  await expect(
    page.getByText("Compliance Monitor assigned successfully."),
  ).toBeVisible();

  await page.getByRole("tab", { name: "History" }).click();
  await expect(
    page.getByText("Recommendation transferred to CMS"),
  ).toBeVisible();

  await page.getByRole("tab", { name: "Assignments" }).click();
  await page.getByRole("button", { name: "Assign Monitor" }).click();
  const staleDialog = page.getByRole("dialog", {
    name: "Assign Compliance Monitor",
  });
  await staleDialog.locator("#cms-monitor-user").selectOption("12");
  await staleDialog.getByRole("button", { name: "Assign monitor" }).click();
  await expect(
    page.getByText(
      "This recommendation changed while you were working. Current data has been reloaded.",
    ),
  ).toBeVisible();
  await expect(staleDialog).toBeHidden();
});
