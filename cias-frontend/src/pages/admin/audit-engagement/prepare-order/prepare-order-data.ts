import type {
  AeoRecipient,
  AeoSourceBaselineSnapshot,
  PrepareAeoFormState,
} from "./prepare-order-types";
import type { DefineScopeFormState } from "../define-scope/define-scope-types";
import { isPlaceholderText, localStore } from "../../../../lib/localStore";

export const sampleInitialRecipients: AeoRecipient[] = [
  {
    id: "REC-01",
    name: "Engr. Bernie Daba",
    title: "Department Head",
    office: "City General Services Office",
    email: "cgso.head@cagayandeoro.gov.ph",
    deliveryMethod: "Digital Notification",
    distributionStatus: "Pending",
  },
  {
    id: "REC-02",
    name: "Atty. Roy Hilario P. Raagas",
    title: "City Administrator",
    office: "Office of the City Administrator",
    email: "cityadmin@cagayandeoro.gov.ph",
    deliveryMethod: "Digital Notification",
    distributionStatus: "Pending",
  },
  {
    id: "REC-03",
    name: "Atty. Kirsten Kate F. Calvo, CPA",
    title: "City Accountant",
    office: "City Accounting Department",
    email: "accounting@cagayandeoro.gov.ph",
    deliveryMethod: "Digital Notification",
    distributionStatus: "Pending",
  },
  {
    id: "REC-04",
    name: "Rolando A. Uy",
    title: "City Mayor / Directing Authority",
    office: "Office of the City Mayor",
    email: "mayor@cagayandeoro.gov.ph",
    deliveryMethod: "Official Courier",
    distributionStatus: "Pending",
  },
];

export const sampleBaselineSnapshot: AeoSourceBaselineSnapshot = {
  snapshottedAt: "2026-08-19 09:30 AM",
  snapshotVersion: "BL-v1.0",
  engagementNumber: "AEMS-2026-010",
  engagementTitle: "Procurement Process & Supply Chain Compliance Audit",
  originType: "Planned",
  iapSourceId: "IAP-2026-001",
  authorityReference: "Resolution No. 2026-018 / Annual Audit Plan 2026",
  directingAuthority: "Annual Internal Audit Plan (IAP-2026 Mandate)",
  office: "City General Services Office",
  auditAreas: ["Procurement Process Compliance & Bidding Controls"],
  auditFocuses: [
    "Competitive Public Bidding Document Trail & BAC Minutes",
    "Alternative Methods of Procurement (AMP) Justifications",
  ],
  periodStart: "2026-02-01",
  periodEnd: "2026-04-30",
  auditType: "Compliance Audit",
  priority: "High",
  confidentiality: "Normal",
  team: [
    {
      id: "TM-01",
      name: "Juan Dela Cruz",
      role: "Lead Auditor",
      department: "Internal Audit Service",
      email: "j.delacruz@cagayandeoro.gov.ph",
    },
    {
      id: "TM-02",
      name: "Kristine Joy B. Ramos, CISA",
      role: "Auditor",
      department: "Internal Audit Service",
      email: "k.ramos@cagayandeoro.gov.ph",
    },
    {
      id: "TM-03",
      name: "Engr. Daniel L. Torres",
      role: "Technical Specialist",
      department: "City Technical Inspection Unit",
      email: "d.torres@cagayandeoro.gov.ph",
    },
  ],
};

export const defaultPrepareAeoState: PrepareAeoFormState = {
  aeoNumber: "AEO-2026-010-CGSO",
  engagementNumber: "AEMS-2026-010",
  version: "v1.0",
  status: "Draft",
  lifecyclePhase: "Engagement Initiation",
  baseline: sampleBaselineSnapshot,
  isSourceDrifted: false,
  driftSummary: undefined,
  orderTitle: "AUDIT ENGAGEMENT ORDER FOR THE COMPLIANCE AUDIT OF CITY GENERAL SERVICES PROCUREMENT WORKFLOWS",
  legalMandate:
    "Pursuant to the powers vested in the City Internal Audit Service (CIAS) under Executive Order No. 2024-002, Republic Act No. 7160 (Local Government Code of 1991), and the Approved Annual Internal Audit Plan for FY 2026 (IAP-2026-001), this Audit Engagement Order is hereby issued.",
  formalObjectives:
    "Evaluate statutory compliance with RA 9184 and local procurement threshold controls, verifying the transparency, efficiency, and integrity of BAC bidding documentation and procurement transactions.",
  formalScopeSummary:
    "Includes all procurement transactions processed through the City General Services Office and Bids and Awards Committee (BAC) from January 1, 2025 to December 31, 2025.",
  engagementDirectives:
    "1. Full cooperation and unhindered access to all relevant books of accounts, bidding folders, electronic bid submissions, purchase requests, and delivery receipts shall be extended to the designated Audit Team.\n2. The Head of Office shall designate a primary liaison officer within two (2) working days of receipt of this Order.\n3. An Entry Conference shall be scheduled on or before February 05, 2026.",
  specialInstructions:
    "All working papers and audit observations generated during this engagement are confidential and subject to the Data Privacy Act of 2012 and CIAS Information Security Protocols.",
  targetDeliverables:
    "1. Inception Audit Plan (AIP) & Audit Program Matrix\n2. Interim Audit Observations & Management Responses\n3. Final Audit Report & Corrective Action Plan (CAP)",
  confidentialityClassification: "Normal",
  reviewedBy: "Atty. Fernando M. Gomez, CPA",
  reviewedRole: "Engagement Manager / Supervising Auditor",
  reviewDate: "",
  reviewRemarks: "Source parameters, audit area boundaries, and team competency verified. Ready for CIAS Department Head approval.",
  approvedBy: "Director Manuel C. Tan, CPA, CFE",
  approverRole: "Department Head, City Internal Audit Service",
  approvalDate: "",
  effectiveDate: "",
  recipients: sampleInitialRecipients,
  history: [
    {
      id: "TR-01",
      timestamp: "2026-08-19 09:35 AM",
      action: "AEO Draft Generated",
      actor: "CIAS Lead Auditor (System)",
      role: "Lead Auditor",
      previousStatus: "Draft",
      newStatus: "Draft",
      version: "v1.0",
      remarks: "Initial controlled AEO drafted from SCR-211, SCR-212, and SCR-213 baselines.",
    },
  ],
};

export function buildBaselineSnapshot(
  engagement: {
    code: string;
    office?: string;
    subject?: string;
    auditArea?: string;
    auditType?: string;
    category?: string;
    startDate?: string;
    endDate?: string;
    annualPlanRef?: string;
    objective?: string;
    scope?: string;
    riskRating?: string;
    year?: string;
    leadAuditor?: string;
    authorityReference?: string;
    directingAuthority?: string;
    authorizationDecision?: string;
    dateReceived?: string;
    originType?: "Planned" | "Unplanned";
  },
  teamState?: {
    team: Array<{
      id: string;
      name: string;
      role: "Lead Auditor" | "Auditor" | "Technical Specialist" | "Supervising Auditor";
    }>;
  } | null,
  scopeState?: DefineScopeFormState | null,
  snapshotVersion = "BL-v1.0",
): AeoSourceBaselineSnapshot {
  const office = scopeState?.office || engagement.office || "Auditee Department";
  const auditType = scopeState?.auditType || engagement.auditType || engagement.category || "Internal Audit";
  const engagementTitle = engagement.auditArea || engagement.subject || `${auditType} of ${office}`;

  // 1. Resolve Audit Areas from SCR-212 Scope State
  let resolvedAuditAreas: string[] = [];
  const allAreas = localStore.getAuditAreas();

  if (scopeState && scopeState.selectedAuditAreaIds && scopeState.selectedAuditAreaIds.length > 0) {
    resolvedAuditAreas = scopeState.selectedAuditAreaIds
      .map((id) => {
        const area = allAreas.find(
          (a) =>
            a.auditAreaId?.toLowerCase() === id.toLowerCase() ||
            a.code?.toLowerCase() === id.toLowerCase() ||
            a.id?.toLowerCase() === id.toLowerCase(),
        );
        return area
          ? `${area.auditAreaId || area.code} — ${area.auditAreaName || area.name}`
          : id;
      });
  } else {
    // Match office in registry
    const matchingOfficeAreas = allAreas.filter(
      (a) =>
        (a.officeName || "").toLowerCase().trim() === office.toLowerCase().trim() ||
        (a.officeId || "").toLowerCase().trim() === office.toLowerCase().trim(),
    );
    if (matchingOfficeAreas.length > 0) {
      resolvedAuditAreas = matchingOfficeAreas.map(
        (a) => `${a.auditAreaId || a.code} — ${a.auditAreaName || a.name}`,
      );
    } else if (engagement.auditArea && !isPlaceholderText(engagement.auditArea)) {
      resolvedAuditAreas = [engagement.auditArea];
    } else {
      resolvedAuditAreas = [`General Internal Controls Audit of ${office}`];
    }
  }

  // 2. Resolve Audit Focuses from SCR-212 Scope State
  let resolvedAuditFocuses: string[] = [];
  const allFocuses = localStore.getAuditFocuses();

  if (scopeState && scopeState.selectedAuditFocusIds && scopeState.selectedAuditFocusIds.length > 0) {
    resolvedAuditFocuses = scopeState.selectedAuditFocusIds
      .map((fId) => {
        const focus = allFocuses.find(
          (f) =>
            f.auditFocusId?.toLowerCase() === fId.toLowerCase() ||
            f.code?.toLowerCase() === fId.toLowerCase() ||
            f.id?.toLowerCase() === fId.toLowerCase(),
        );
        return focus
          ? `${focus.auditFocusId || focus.code} — ${focus.auditFocusName || focus.name}`
          : fId;
      });
  } else if (scopeState && scopeState.selectedAuditAreaIds && scopeState.selectedAuditAreaIds.length > 0) {
    const activeFocuses = allFocuses.filter(
      (f) =>
        f.status === "Active" &&
        scopeState.selectedAuditAreaIds.some(
          (areaId) =>
            areaId.toLowerCase() === (f.auditAreaId || "").toLowerCase() ||
            areaId.toLowerCase() === (f.code || "").toLowerCase(),
        ),
    );
    resolvedAuditFocuses = activeFocuses.map(
      (f) => `${f.auditFocusId || f.code} — ${f.auditFocusName || f.name}`,
    );
  } else {
    // Match areas in registry
    const matchingOfficeAreas = allAreas.filter(
      (a) =>
        (a.officeName || "").toLowerCase().trim() === office.toLowerCase().trim() ||
        (a.officeId || "").toLowerCase().trim() === office.toLowerCase().trim(),
    );
    if (matchingOfficeAreas.length > 0) {
      const areaIds = matchingOfficeAreas.map(
        (a) => a.auditAreaId || a.code || a.id || "",
      );
      const defaultFocuses = allFocuses.filter(
        (f) =>
          f.status === "Active" &&
          areaIds.some((aId) => aId.toLowerCase() === (f.auditAreaId || "").toLowerCase()),
      );
      resolvedAuditFocuses = defaultFocuses.map(
        (f) => `${f.auditFocusId || f.code} — ${f.auditFocusName || f.name}`,
      );
    }
  }

  const isPlanned = scopeState?.originType
    ? scopeState.originType === "Planned"
    : engagement.originType
    ? engagement.originType === "Planned"
    : Boolean(
        engagement.annualPlanRef &&
        engagement.annualPlanRef.trim().length > 0 &&
        !["none", "n/a", "unplanned"].includes(engagement.annualPlanRef.trim().toLowerCase()),
      );

  // Derive team snapshot from SCR-213 assignment if available
  const teamSnapshot = teamState && teamState.team.length > 0
    ? teamState.team.map((m) => ({
      id: m.id,
      name: m.name,
      role: m.role,
      department: "Internal Audit Service",
      email: `${m.name.toLowerCase().replace(/[^a-z0-9]/g, "")}@cagayandeoro.gov.ph`,
    }))
    : [
      {
        id: "TM-01",
        name: engagement.leadAuditor || "Lead Auditor",
        role: "Lead Auditor" as const,
        department: "Internal Audit Service",
        email: "leadauditor@cagayandeoro.gov.ph",
      },
    ];

  const now = new Date().toISOString().replace("T", " ").substring(0, 16);

  return {
    snapshottedAt: now,
    snapshotVersion: snapshotVersion,
    engagementNumber: engagement.code,
    engagementTitle: engagementTitle,
    originType: isPlanned ? "Planned" : "Unplanned",
    iapSourceId: isPlanned ? engagement.annualPlanRef : undefined,
    authorityReference: isPlanned
      ? `Approved Annual Internal Audit Plan (${engagement.annualPlanRef || "AAP-2026"})`
      : engagement.authorityReference || "Special Audit Directive / Executive Order No. 2024-002",
    directingAuthority: isPlanned
      ? `Annual Internal Audit Plan (${engagement.annualPlanRef || "AAP-2026"} Mandate)`
      : engagement.directingAuthority || "Special Executive / Mayor Directive",
    office: office,
    auditAreas: resolvedAuditAreas,
    auditFocuses: resolvedAuditFocuses,
    periodStart: engagement.startDate || "",
    periodEnd: engagement.endDate || "",
    auditType: auditType,
    priority: (engagement.riskRating as any) || "High",
    confidentiality: "Normal",
    team: teamSnapshot,
  };
}

export function buildInitialAeoState(
  engagement: {
    code: string;
    office?: string;
    subject?: string;
    auditArea?: string;
    auditType?: string;
    category?: string;
    startDate?: string;
    endDate?: string;
    annualPlanRef?: string;
    objective?: string;
    scope?: string;
    riskRating?: string;
    year?: string;
    leadAuditor?: string;
    authorityReference?: string;
    directingAuthority?: string;
    authorizationDecision?: string;
    dateReceived?: string;
    originType?: "Planned" | "Unplanned";
  },
  teamState?: {
    team: Array<{
      id: string;
      name: string;
      role: "Lead Auditor" | "Auditor" | "Technical Specialist" | "Supervising Auditor";
    }>;
  } | null,
  scopeState?: DefineScopeFormState | null,
): PrepareAeoFormState {
  const codeParts = (engagement.code || "AEMS-2026-001").split("-");
  const year = codeParts[1] || engagement.year || "2026";
  const codeNum = codeParts[2] || "001";
  const office = scopeState?.office || engagement.office || "Auditee Department";
  const auditType = scopeState?.auditType || engagement.auditType || engagement.category || "Internal Audit";

  // Derive clean office acronym
  const officeClean = office.replace(/^(City\s+|Office\s+of\s+the\s+)/i, "").trim();
  const officeWords = officeClean.split(/\s+/);
  const officeAcronym =
    officeWords.length >= 2
      ? officeWords.map((w) => w[0]).join("").toUpperCase().slice(0, 5)
      : officeClean.slice(0, 4).toUpperCase();

  const aeoNumber = `AEO-${year}-${codeNum}-${officeAcronym || "AEO"}`;
  const baseline = buildBaselineSnapshot(engagement, teamState, scopeState, "BL-v1.0");

  const isPlanned = baseline.originType === "Planned";
  const legalMandate = isPlanned
    ? `Pursuant to the powers vested in the City Internal Audit Service (CIAS) under Executive Order No. 2024-002, Republic Act No. 7160 (Local Government Code of 1991), and the Approved Annual Internal Audit Plan${engagement.annualPlanRef ? ` (${engagement.annualPlanRef})` : ""}, this Audit Engagement Order is hereby issued.`
    : "Pursuant to the powers vested in the City Internal Audit Service (CIAS) under Executive Order No. 2024-002, Republic Act No. 7160 (Local Government Code of 1991), and by virtue of the Authorization Decision approving this unplanned engagement independent of the Annual Internal Audit Plan, this Audit Engagement Order is hereby issued.";

  // Recipients tailored to office
  const recipients: AeoRecipient[] = [
    {
      id: "REC-01",
      name: `Department Head / Head of Office`,
      title: `Head of ${office}`,
      office: office,
      email: `${officeAcronym.toLowerCase()}.head@cagayandeoro.gov.ph`,
      deliveryMethod: "Digital Notification",
      distributionStatus: "Pending",
    },
    {
      id: "REC-02",
      name: "Atty. Roy Hilario P. Raagas",
      title: "City Administrator",
      office: "Office of the City Administrator",
      email: "cityadmin@cagayandeoro.gov.ph",
      deliveryMethod: "Digital Notification",
      distributionStatus: "Pending",
    },
    {
      id: "REC-03",
      name: "Atty. Kirsten Kate F. Calvo, CPA",
      title: "City Accountant",
      office: "City Accounting Department",
      email: "accounting@cagayandeoro.gov.ph",
      deliveryMethod: "Digital Notification",
      distributionStatus: "Pending",
    },
    {
      id: "REC-04",
      name: "Rolando A. Uy",
      title: "City Mayor / Directing Authority",
      office: "Office of the City Mayor",
      email: "mayor@cagayandeoro.gov.ph",
      deliveryMethod: "Official Courier",
      distributionStatus: "Pending",
    },
  ];

  return {
    aeoNumber,
    engagementNumber: engagement.code,
    version: "v1.0",
    status: "Draft",
    lifecyclePhase: "Engagement Initiation",
    baseline,
    isSourceDrifted: false,
    driftSummary: undefined,
    orderTitle: `AUDIT ENGAGEMENT ORDER FOR THE ${auditType.toUpperCase()} OF ${office.toUpperCase()}`,
    legalMandate: legalMandate,
    formalObjectives:
      scopeState?.objectives?.trim() ||
      engagement.objective?.trim() ||
      `Assess and evaluate statutory compliance, internal controls, and operational efficiency of ${office}.`,
    formalScopeSummary:
      scopeState?.boundaries?.trim() ||
      engagement.scope?.trim() ||
      `Covers designated operational records, systems, and transactions of ${office} for the specified audit period.`,
    engagementDirectives:
      "1. Full cooperation and unhindered access to all relevant books of accounts, bidding folders, electronic systems, and facilities shall be extended to the designated Audit Team.\n2. The Head of Office shall designate a primary liaison officer within two (2) working days of receipt of this Order.\n3. An Entry Conference shall be scheduled pursuant to CIAS protocol.",
    specialInstructions:
      "All working papers, data extracts, interview records, and audit observations generated during this engagement are confidential and subject to the Data Privacy Act of 2012 and CIAS Information Security Protocols.",
    targetDeliverables:
      "1. Audit Engagement Plan (AEP) & Testing Programs\n2. Interim Audit Observations & Management Feedback\n3. Final Audit Report & Corrective Action Plan (CAP)",
    confidentialityClassification: "Normal",
    reviewedBy: "Atty. Fernando M. Gomez, CPA",
    reviewedRole: "Engagement Manager / Supervising Auditor",
    reviewDate: "",
    reviewRemarks:
      "Source parameters, audit area boundaries, and team competency verified. Ready for CIAS Department Head approval.",
    approvedBy: "Director Manuel C. Tan, CPA, CFE",
    approverRole: "Department Head, City Internal Audit Service",
    approvalDate: "",
    effectiveDate: "",
    recipients,
    history: [
      {
        id: "TR-01",
        timestamp: baseline.snapshottedAt,
        action: "AEO Draft Generated",
        actor: "CIAS Lead Auditor (System)",
        role: "Lead Auditor",
        previousStatus: "Draft",
        newStatus: "Draft",
        version: "v1.0",
        remarks: "Initial controlled AEO drafted from SCR-211, SCR-212, and SCR-213 baselines.",
      },
    ],
  };
}
