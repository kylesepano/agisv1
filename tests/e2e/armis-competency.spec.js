import { expect, test } from "@playwright/test";

async function signIn(page) {
  await page.goto("/login");
  await page.getByLabel("Employee ID").fill("CIAS-HEAD-001");
  await page.locator("#password").fill("lala");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/dashboard$/);
}

const resource = {
  id: 17,
  resourceCode: "ARMIS-RES-CIAS001",
  status: "ACTIVE",
  user: { id: 2, employeeId: "CIAS-AUD-001", name: "CIAS Demo Auditor", position: "Internal Auditor", isActive: true },
  office: { id: 1, code: "CIAS", name: "City Internal Audit Service" },
};

function competency(overrides = {}) {
  return {
    id: 31,
    competencyFamilyUuid: "armis-family-31",
    resourceProfileId: resource.id,
    resourceCode: resource.resourceCode,
    resourceUser: { id: 2, employee_id: "CIAS-AUD-001", name: "CIAS Demo Auditor", initials: "MB" },
    competencyId: 101,
    code: "FINANCIAL_AUDIT",
    label: "Financial Audit and Accounting",
    description: "Financial reporting, accounting, treasury, disbursement, and safeguarding controls.",
    versionNumber: 1,
    supersedesId: null,
    isCurrentRevision: true,
    proficiencyLevel: "ADVANCED",
    credentialType: "Professional certification",
    credentialReference: "CERT-ARMIS-001",
    issuer: "Professional Institute",
    issuedAt: "2026-01-01",
    status: "DRAFT",
    evidenceDocumentVersionId: 7001,
    evidenceDocument: { id: 7001, document_id: 700, version_number: 1, original_file_name: "certificate.pdf", checksum_sha256: "abc123" },
    expiresAt: "2028-01-01",
    submittedBy: null,
    submittedAt: null,
    verifiedBy: null,
    verifiedAt: null,
    reviewedBy: null,
    reviewedAt: null,
    notes: "Certification evidence is preserved in Core.",
    verificationNotes: null,
    lockVersion: 1,
    ...overrides,
  };
}

async function mockArmis(page) {
  let current = competency();
  let createdPayload;
  let reviewPayload;

  await page.route(/\/api\/armis\/competencies\/metadata$/, (route) => route.fulfill({
    status: 200,
    contentType: "application/json",
    body: JSON.stringify({ success: true, data: {
      statuses: ["DRAFT", "RETURNED", "PENDING_VERIFICATION", "VERIFIED", "EXPIRED", "REVOKED"].map((code) => ({ code, label: code })),
      proficiencyLevels: ["BASIC", "INTERMEDIATE", "ADVANCED", "EXPERT"].map((code) => ({ code, label: code })),
      competencies: [{ id: 101, code: "FINANCIAL_AUDIT", label: "Financial Audit and Accounting", description: "Financial controls", catalogue: "IAP_AUDITOR_SPECIALIZATION" }],
    } }),
  }));
  await page.route(/\/api\/armis\/resources(?:\?[^/]*)?$/, (route) => route.fulfill({
    status: 200,
    contentType: "application/json",
    body: JSON.stringify({ success: true, data: [resource] }),
  }));
  await page.route(/\/api\/armis\/competencies(?:\?[^/]*)?$/, async (route) => {
    if (route.request().method() === "POST") {
      createdPayload = JSON.parse(route.request().postData() || "{}");
      current = competency({ id: 31, lockVersion: 1, status: "DRAFT" });
      return route.fulfill({ status: 201, contentType: "application/json", body: JSON.stringify({ success: true, data: current }) });
    }
    return route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ success: true, data: [current], meta: { total: 1, currentOnly: true } }) });
  });
  await page.route(/\/api\/armis\/competencies\/31$/, (route) => route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ success: true, data: current }) }));
  await page.route(/\/api\/armis\/competencies\/31\/events$/, (route) => route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ success: true, data: [
    { id: 1, eventCode: "COMPETENCY_CREATED", toStatus: "DRAFT", createdAt: "2026-08-01T09:00:00Z" },
    ...(current.status === "VERIFIED" ? [{ id: 3, eventCode: "COMPETENCY_VERIFIED", fromStatus: "PENDING_VERIFICATION", toStatus: "VERIFIED", createdAt: "2026-08-02T09:00:00Z" }] : current.status === "PENDING_VERIFICATION" ? [{ id: 2, eventCode: "COMPETENCY_SUBMITTED", fromStatus: "DRAFT", toStatus: "PENDING_VERIFICATION", createdAt: "2026-08-02T09:00:00Z" }] : []),
  ] }) }));
  await page.route(/\/api\/armis\/competencies\/31\/submit$/, async (route) => {
    current = competency({ status: "PENDING_VERIFICATION", lockVersion: 2, submittedBy: 3, submittedAt: "2026-08-02T09:00:00Z" });
    return route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ success: true, data: current }) });
  });
  await page.route(/\/api\/armis\/competencies\/31\/review$/, async (route) => {
    reviewPayload = JSON.parse(route.request().postData() || "{}");
    current = competency({ status: reviewPayload.decision === "VERIFY" ? "VERIFIED" : "RETURNED", lockVersion: 3, verifiedBy: reviewPayload.decision === "VERIFY" ? 3 : null, verificationNotes: reviewPayload.notes || null });
    return route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ success: true, data: current }) });
  });
  return { getCreatedPayload: () => createdPayload, getReviewPayload: () => reviewPayload };
}

test("renders the ARMIS competency registry and certification history", async ({ page }) => {
  await signIn(page);
  await mockArmis(page);
  await page.goto("/audit-resource-management/competencies");

  await expect(page.getByRole("heading", { name: "ARMIS Competencies & Certifications", exact: true, level: 2 })).toBeVisible();
  await expect(page.getByText("Financial Audit and Accounting", { exact: true })).toBeVisible();
  await page.getByRole("button", { name: "Open", exact: true }).click();
  await expect(page).toHaveURL(/\/audit-resource-management\/competencies\/31$/);
  await expect(page.getByTestId("armis-competency-detail")).toBeVisible();
  await expect(page.getByText("Exact Core evidence", { exact: true })).toBeVisible();
  await expect(page.getByText("Certification history", { exact: true })).toBeVisible();
});

test("creates, submits, and independently verifies a certification draft", async ({ page }) => {
  await signIn(page);
  const armis = await mockArmis(page);
  await page.goto("/audit-resource-management/competencies");
  await page.getByRole("button", { name: "New certification draft" }).click();
  await page.getByLabel("Resource profile").selectOption("17");
  await page.getByLabel("Core competency").selectOption("101");
  await page.getByLabel("Core Document Version ID").fill("7001");
  await page.getByRole("button", { name: "Create draft", exact: true }).click();
  await expect(page.getByText("Competency draft created.", { exact: true })).toBeVisible();
  expect(armis.getCreatedPayload()).toMatchObject({ resourceProfileId: 17, competencyId: 101, evidenceDocumentVersionId: 7001 });

  await page.goto("/audit-resource-management/competencies/31");
  await page.getByTestId("armis-competency-detail").waitFor();
  await page.getByRole("button", { name: "Submit for verification" }).click();
  await expect(page.getByText("Competency submitted for independent verification.", { exact: true })).toBeVisible();
  await page.getByRole("button", { name: "Verify" }).click();
  await page.getByRole("button", { name: "Confirm verification", exact: true }).click();
  await expect(page.getByText("Competency verified.", { exact: true })).toBeVisible();
  expect(armis.getReviewPayload()).toMatchObject({ decision: "VERIFY", lockVersion: 2 });
});
