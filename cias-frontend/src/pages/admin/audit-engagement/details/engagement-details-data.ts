import type {
  EngagementAdminStatus,
  EngagementLifecyclePhase,
  EngagementTeamMember,
  FullEngagementDetails,
} from "./engagement-details-types";

export const phaseDefinitions: { number: number; name: EngagementLifecyclePhase; description: string; shortName: string }[] = [
  {
    number: 1,
    name: "1. Engagement Initiation and Authorization",
    shortName: "Initiation",
    description: "Formally activate IAP plan item or authorized ad-hoc mandate; establish authority and issue AEO.",
  },
  {
    number: 2,
    name: "2. Audit Planning",
    shortName: "Planning",
    description: "Preliminary survey, Audit Engagement Plan (AEP), Audit Programs, Risk Matrix, and man-day allocations.",
  },
  {
    number: 3,
    name: "3. Entry Conference or Authorized Commencement",
    shortName: "Entry Conf",
    description: "Conduct formal Entry Conference with auditee leadership; review audit scope, logistics, and protocols.",
  },
  {
    number: 4,
    name: "4. Audit Execution and Documentation",
    shortName: "Execution",
    description: "Fieldwork testing, sampling working papers, evidence management, and fieldwork observation logs.",
  },
  {
    number: 5,
    name: "5. Audit Issue Evaluation and Findings Development",
    shortName: "Issues / AFRs",
    description: "Evaluate preliminary issues, form Audit Finding Recommendations (AFRs) with 5Cs criteria.",
  },
  {
    number: 6,
    name: "6. Management Comment and Auditor Evaluation",
    shortName: "Comments",
    description: "Formal transmission of findings for auditee management response and auditor rejoinders.",
  },
  {
    number: 7,
    name: "7. Exit Conference and Final Validation",
    shortName: "Exit Conf",
    description: "Exit conference presentation, validation of corrective commitments, and agreement wrap-up.",
  },
  {
    number: 8,
    name: "8. Audit Reporting and Issuance",
    shortName: "Reporting",
    description: "Quality review, Department Head approval, and formal issuance of the Final Audit Report.",
  },
  {
    number: 9,
    name: "9. Engagement Completion and CMS Transfer",
    shortName: "CMS Transfer",
    description: "Reconciliation of findings, transfer of recommendations into CMS for long-term tracking.",
  },
  {
    number: 10,
    name: "10. Administrative Closure",
    shortName: "Closure",
    description: "Formal archiving of working papers and administrative closure; editing locked.",
  },
];

export const statusDefinitions: Record<EngagementAdminStatus, string> = {
  Draft: "Record is being prepared and initialized; not yet submitted for formal authority review (MDS-200 §10.2).",
  "For Approval": "Submitted to supervising leadership or CIAS Department Head for formal review and mandate validation.",
  Approved: "Formally approved by Department Head; authorized for AEO generation and resource assignment.",
  Active: "Audit Engagement Order (AEO) is issued and effective; engagement is actively proceeding in fieldwork or planning.",
  "On Hold": "Formally suspended due to declared disaster, emergency operational priority, or authorized executive pause.",
  Completed: "All fieldwork, reporting, and recommendation hand-offs to CMS are finished; pending administrative closure.",
  Closed: "Formally closed and archived; all ordinary record editing is locked with preserved audit history.",
  Cancelled: "Formally discontinued with preserved audit trail and mandatory cancellation justification.",
};

function getOfficeAbbreviation(officeName: string): string {
  const commonAbbreviations: Record<string, string> = {
    "City General Services Office": "CGSO",
    "City Treasurer's Office": "CTO",
    "City Budget Office": "CBO",
    "City Information and Communications Technology Office": "CICTO",
    "City Engineer's Office": "CEO",
    "Business Permits and Licensing Division": "BPLD",
    "City Assessment Department": "CAD",
    "City Health Department": "CHD",
    "City Disaster Risk Reduction and Management Department": "CDRRMD",
    "City Housing and Urban Development Department": "CHUDD",
    "City Agriculture Office": "CAO",
  };

  if (commonAbbreviations[officeName]) {
    return commonAbbreviations[officeName];
  }

  return (
    officeName
      .split(" ")
      .filter((w) => w.length > 2 && !["and", "the", "for"].includes(w.toLowerCase()))
      .map((w) => w[0])
      .join("")
      .toUpperCase() || "LGU"
  );
}

import { getAuditEngagementRecords } from "../service";

export function getFullEngagementDetails(codeOrId: string): FullEngagementDetails {
  const normalizedTarget = (codeOrId || "").trim().toLowerCase();

  // Find matching record from live engagements
  const liveList = getAuditEngagementRecords();
  const matchingRecord = liveList.find(
    (e) =>
      e.id.toLowerCase() === normalizedTarget ||
      e.code.toLowerCase() === normalizedTarget
  );

  // If not found, build a strictly scoped dynamic record
  const base =
    matchingRecord || {
      id: codeOrId || "AEMS-2026-001",
      code: codeOrId || "AEMS-2026-001",
      auditArea: "Audit Engagement",
      subject: "Audit Engagement",
      office: "City Office",
      auditType: "Compliance Audit",
      category: "Compliance Audit",
      riskRating: "Medium",
      annualPlanRef: "N/A",
      leadAuditor: "Juan dela Cruz",
      auditTeam: "Audit Team",
      startDate: new Date().toISOString().split("T")[0],
      endDate: new Date().toISOString().split("T")[0],
      status: "Active",
      objective: "Assess adequacy and compliance with applicable regulations.",
      scope: "Audit transactions covering the audit period.",
      remarks: "",
      updatedBy: "System",
      lastUpdated: "Today",
      year: "2026",
      progress: 25,
    };

  const isIssued =
    base.status === "Active" ||
    base.status === "Completed" ||
    base.status === "Closed";
  const phaseNum = isIssued ? 2 : 1;
  const phaseName: EngagementLifecyclePhase = isIssued
    ? "2. Audit Planning"
    : "1. Engagement Initiation and Authorization";

  const officeAbbr = getOfficeAbbreviation(base.office);
  const codeParts = base.code.split("-");
  const yearStr = base.year || codeParts[1] || "2026";
  const numStr = codeParts[2] || "001";
  const aeoNumber = `AEO-${yearStr}-${numStr}-${officeAbbr}`;

  // Build team array dynamically from leadAuditor and auditTeam
  const teamMembers: EngagementTeamMember[] = [
    {
      id: "TM-01",
      name: base.leadAuditor || "Juan Dela Cruz",
      role: "Lead Auditor",
      department: "Internal Audit Service",
      email: `${(base.leadAuditor || "j.delacruz").toLowerCase().replace(/[^a-z]/g, ".")}@cagayandeoro.gov.ph`,
    },
  ];

  if (base.auditTeam) {
    const rawNames = base.auditTeam
      .split(/[,+]/)
      .map((s) => s.trim())
      .filter((s) => s.length > 0 && !s.toLowerCase().includes("more"));

    rawNames.forEach((name, idx) => {
      teamMembers.push({
        id: `TM-0${idx + 2}`,
        name: name,
        role: idx === 0 ? "Auditor" : idx === 1 ? "Technical Specialist" : "Supervising Auditor",
        department: "Internal Audit Service",
        email: `${name.toLowerCase().replace(/[^a-z]/g, ".")}@cagayandeoro.gov.ph`,
      });
    });
  }

  // Ensure at least 3 team members
  if (teamMembers.length < 3) {
    teamMembers.push(
      {
        id: "TM-03",
        name: "Kristine Joy B. Ramos, CISA",
        role: "Auditor",
        department: "Internal Audit Service",
        email: "k.ramos@cagayandeoro.gov.ph",
      },
      {
        id: "TM-04",
        name: "Atty. Fernando M. Gomez, CPA",
        role: "Supervising Auditor",
        department: "Internal Audit Service",
        email: "f.gomez@cagayandeoro.gov.ph",
      },
    );
  }

  const officeDisplay =
    base.office === "CGSO" || base.office === "City General Services Office"
      ? "City General Services Office (CGSO)"
      : base.office.includes("(")
        ? base.office
        : `${base.office} (${officeAbbr})`;

  const isAem2026001 =
    normalizedTarget === "aem-2026-001" ||
    normalizedTarget === "1" ||
    base.code === "AEM-2026-001";

  return {
    id: base.id,
    code: isAem2026001 ? "AEM-2026-001" : base.code,
    title: isAem2026001 ? "Procurement Process Audit" : base.auditArea || base.subject || `${base.auditType} of ${base.office}`,
    auditType: isAem2026001 ? "Operations Audit" : (base.auditType?.includes("Audit") ? base.auditType : `${base.auditType || "Operations"} Audit`),
    office: isAem2026001 ? "City General Services Office (CGSO)" : officeDisplay,
    phase: isAem2026001 ? "2. Audit Planning" : phaseName,
    phaseNumber: isAem2026001 ? 2 : phaseNum,
    status: isAem2026001 ? ("Active" as EngagementAdminStatus) : (base.status as EngagementAdminStatus),
    originType: isAem2026001 ? "Planned" : base.annualPlanRef ? "Planned" : "Unplanned",
    annualPlanRef: isAem2026001 ? "AAP-2026-01" : base.annualPlanRef || "AAP-2026-01",
    authorityReference: isAem2026001
      ? "SP Resolution No. 2026-018 / Approved AAP-2026-01"
      : base.annualPlanRef
        ? `SP Resolution No. ${yearStr}-018 / Approved ${base.annualPlanRef}`
        : `Executive Order No. ${yearStr}-002`,
    directingAuthority: isAem2026001 ? "City Mayor" : base.directingAuthority || "City Mayor",
    dateAuthorized: isAem2026001 ? "2025-11-15" : `${yearStr}-01-15`,
    priority: isAem2026001 ? "High" : "High",
    confidentiality: "Normal",
    startDate: isAem2026001 ? "2026-01-15" : base.startDate || "2026-01-15",
    endDate: isAem2026001 ? "2026-03-31" : base.endDate || "2026-03-31",
    plannedManDays: isAem2026001 ? 76 : 76,
    progressPercent: base.progress || (isIssued ? 25 : 10),
    objectives: isAem2026001
      ? "Assess the adequacy, efficiency, and compliance of the procurement process in CGSO in accordance with applicable laws, rules, and regulations."
      : base.objective ||
        `Assess the adequacy, efficiency, and compliance of the procurement process in ${base.office} in accordance with applicable laws, rules, and regulations.`,
    scopeSummary: isAem2026001
      ? "Procurement Planning, Procurement Processing, Bidding and Award Support, Delivery, Inspection and Acceptance"
      : base.scope ||
        `Procurement transactions and operational records in ${base.office}.`,
    auditAreas: isAem2026001
      ? [
          "Procurement Planning",
          "Procurement Processing",
          "Bidding and Award Support",
          "Delivery, Inspection and Acceptance",
        ]
      : [
          base.auditArea || base.subject || "Procurement Planning",
          "Procurement Processing",
          "Bidding and Award Support",
          "Delivery, Inspection and Acceptance",
        ],
    auditFocuses: [
      "Procurement Planning",
      "Procurement Processing",
      "Bidding and Award Support",
    ],
    boundaries: `Covers central divisions, administrative units, and operational records of the ${base.office}.`,
    exclusions: "Excludes external agency oversight boundaries and authorized off-scope programs.",
    knownLimitations: "Subject to availability of archived department ledgers and physical inspection access.",
    team: isAem2026001
      ? [
          {
            id: "TM-01",
            name: "Juan dela Cruz",
            role: "Lead Auditor",
            department: "Internal Audit Service",
            email: "j.delacruz@cagayandeoro.gov.ph",
          },
        ]
      : teamMembers,
    aeo: isIssued
      ? {
          aeoNumber: aeoNumber,
          status: "Issued",
          version: "v1.0",
          issuedDate: `${yearStr}-01-20`,
          effectiveDate: base.startDate || `${yearStr}-02-01`,
          approver: "Director Manuel C. Tan, CPA, CFE",
        }
      : undefined,
    counts: {
      workingPapersCount: isIssued ? 4 : 0,
      evidenceItemsCount: isIssued ? 12 : 0,
      auditIssuesCount: isIssued ? 2 : 0,
      afrsCount: 0,
      conferencesCount: 1,
      reportsCount: 0,
      openActionItemsCount: 3,
    },
    recentActivity: [],
  };
}
