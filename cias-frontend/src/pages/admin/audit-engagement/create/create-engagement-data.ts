import type {
  AuditTypeOption,
  DuplicateConflict,
  EngagementConfidentiality,
  EngagementPriority,
  IapSourceItem,
} from "./create-engagement-types";

export const auditTypeOptions: { value: AuditTypeOption; label: string; description: string }[] = [
  {
    value: "Operations Audit",
    label: "Operations Audit",
    description: "Systematic review of organizational operations for efficiency and efficacy.",
  },
  {
    value: "Compliance Audit",
    label: "Compliance Audit",
    description: "Adherence to regulatory mandates, laws, and administrative policies.",
  },
  {
    value: "Financial Audit",
    label: "Financial Audit",
    description: "Examination of financial records, disbursements, cash handling, and revenues.",
  },
  {
    value: "Information Systems Audit",
    label: "Information Systems Audit (IS/IT)",
    description: "Evaluation of IT infrastructure, data integrity, and cyber safeguards.",
  },
  {
    value: "Process Audit",
    label: "Process Audit",
    description: "Assessment of internal process workflows, control points, and handoffs.",
  },
  {
    value: "Asset Audit",
    label: "Asset & Property Audit",
    description: "Physical inspection, inventory controls, and valuation of LGU assets.",
  },
  {
    value: "Value for Money / Performance Audit",
    label: "Value for Money / Performance Audit",
    description: "Assessment of economy, efficiency, and effectiveness of programs.",
  },
  {
    value: "Special / Management Audit",
    label: "Special / Management Audit",
    description: "Ad-hoc directed investigations, inquiries, or executive reviews.",
  },
];

export const priorityOptions: { value: EngagementPriority; label: string; color: string }[] = [
  { value: "Critical", label: "Critical", color: "text-rose-700 bg-rose-50 border-rose-200" },
  { value: "High", label: "High", color: "text-orange-700 bg-orange-50 border-orange-200" },
  { value: "Medium", label: "Medium", color: "text-amber-700 bg-amber-50 border-amber-200" },
  { value: "Low", label: "Low", color: "text-emerald-700 bg-emerald-50 border-emerald-200" },
];

export const confidentialityOptions: {
  value: EngagementConfidentiality;
  label: string;
  badge: string;
}[] = [
    {
      value: "Normal",
      label: "Normal (Standard Internal Audit Access)",
      badge: "bg-slate-100 text-slate-700 border-slate-200",
    },
    {
      value: "Restricted",
      label: "Restricted (Designated Team & Department Heads)",
      badge: "bg-blue-50 text-blue-700 border-blue-200",
    },
    {
      value: "Confidential",
      label: "Confidential (Audit Head, Lead Auditor & Executive Access Only)",
      badge: "bg-purple-50 text-purple-700 border-purple-200",
    },
  ];

export const unplannedDirectingAuthoritySuggestions = [
  "City Mayor / Office of the City Mayor",
  "Sangguniang Panlungsod / City Council",
  "Office of the City Administrator",
  "Commission on Audit (COA) Referral",
  "City Legal Office / Joint Oversight Committee",
  "Internal Audit Committee",
];

export const plannedDirectingAuthoritySuggestions = [
  "Annual Internal Audit Plan (IAP-2026 Mandate)",
  "Internal Audit Committee",
  "Office of the City Administrator",
  "City Mayor / Office of the City Mayor",
];

export const directingAuthoritySuggestions = unplannedDirectingAuthoritySuggestions;

export const unplannedAuthorityChips = [
  { label: "SP Resolution", value: "SP Resolution No. 2026-" },
  { label: "Executive Order", value: "Executive Order No. 2026-" },
  { label: "Mayor Directive", value: "Mayor's Directive No. 2026-" },
  { label: "COA Referral", value: "COA Special Audit Request Ref: " },
];

export const plannedAuthorityChips = [
  { label: "IAP Item", value: "IAP-2026-001" },
  { label: "Annual Plan Resolution", value: "Resolution No. 2026-018 / Annual Audit Plan" },
  { label: "IAP Mandate", value: "Approved Annual Internal Audit Plan FY 2026" },
];

export const authorizationDecisionOptions = [
  { value: "Approved by Sanggunian Resolution", label: "Approved by Sanggunian Resolution" },
  { value: "Special Executive Order Signed", label: "Special Executive Order Signed" },
  { value: "Mayor Directive Affirmation", label: "Mayor Directive Affirmation" },
  { value: "COA Audit Request Endorsed", label: "COA Audit Request Endorsed" },
  { value: "Internal Audit Committee Approved", label: "Internal Audit Committee Approved" },
];

import { annualAuditPlanRecords } from "../../annual-audit-planning/annual-audit-planning-data";
import type { AnnualAuditPlanRecord } from "../../annual-audit-planning/annual-audit-planning-types";

// Direct deterministic mapping from Annual Audit Plan Category
function mapCategoryToAuditType(category: string, _subject: string): AuditTypeOption {
  switch (category) {
    case "Process":
      return "Operations Audit";
    case "System":
      return "Information Systems Audit";
    case "Project":
      return "Special / Management Audit";
    case "Fund":
      return "Financial Audit";
    case "Asset":
      return "Asset Audit";
    default:
      return "Operations Audit";
  }
}

// Convert Annual Audit Plan master dataset into IapSourceItems
// NOTE: provisional_iap_link: true marks this as a unified local mock pending live IAP module API integration.
export function convertAnnualPlanToIapItems(
  records: AnnualAuditPlanRecord[] = annualAuditPlanRecords,
  activeEngagements?: { annualPlanRef?: string; code?: string }[],
): IapSourceItem[] {
  return records.map((rec) => {
    const auditType = mapCategoryToAuditType(rec.category, rec.subject);
    const year = rec.year || "2025";

    // Check if this item is already activated in an engagement
    const linkedEng = activeEngagements?.find(
      (eng) =>
        eng.annualPlanRef === rec.code ||
        eng.annualPlanRef === rec.id ||
        eng.annualPlanRef?.replace("IAP-", "AAP-") === rec.code ||
        eng.annualPlanRef?.replace("AAP-", "IAP-") === rec.code,
    );

    const isActivated = Boolean(linkedEng) || rec.status === "In Progress" || rec.status === "Completed";
    const activatedCode = linkedEng?.code;

    return {
      id: rec.code,
      code: rec.code,
      title: `${rec.subject} (${rec.office})`,
      office: rec.office,
      auditType,
      priority: (rec.riskRating as EngagementPriority) || "Medium",
      year: rec.year,
      quarter: rec.quarter,
      periodStart: rec.planStart,
      periodEnd: rec.planEnd,
      objective: `Assess and evaluate the adequacy, operational effectiveness, governance controls, and regulatory compliance of ${rec.subject} within ${rec.office}.`,
      scopeSummary: `Covers ${rec.subject} operational workflows, records, transactions, and internal control systems within ${rec.office} for period ${rec.planStart} to ${rec.planEnd}.`,
      authorityReference: `SP Resolution No. ${year}-018 / Approved Annual Audit Plan (${rec.code})`,
      directingAuthority: "Annual Internal Audit Plan (IAP Mandate)",
      dateReceived: `${year}-01-15`,
      status: rec.status,
      isAlreadyActivated: isActivated,
      activatedEngagementCode: activatedCode,
      provisional_iap_link: true,
    };
  });
}

// Master Unified IAP Items derived from the Annual Audit Plan dataset
export const approvedIapItems: IapSourceItem[] = convertAnnualPlanToIapItems(annualAuditPlanRecords);

export const sampleActiveEngagementsForDuplicateCheck: DuplicateConflict[] = [
  {
    engagementId: "AEMS-2025-009",
    code: "AEMS-2025-009",
    title: "Audit of Legal Operations and Contract Review",
    office: "City Legal Department",
    auditType: "Compliance Audit",
    startDate: "2026-01-15",
    endDate: "2026-04-15",
    status: "Ongoing",
  },
  {
    engagementId: "AEMS-2025-008",
    code: "AEMS-2025-008",
    title: "Projects Implementation Audit",
    office: "City Engineer's Office",
    auditType: "Operations Audit",
    startDate: "2026-02-01",
    endDate: "2026-06-30",
    status: "Ongoing",
  },
  {
    engagementId: "AEMS-2025-007",
    code: "AEMS-2025-007",
    title: "Business Permits and Licensing Audit",
    office: "Business Permits and Licensing Division",
    auditType: "Compliance Audit",
    startDate: "2026-01-01",
    endDate: "2026-06-30",
    status: "Ongoing",
  },
  {
    engagementId: "AEMS-2025-006",
    code: "AEMS-2025-006",
    title: "IT General Controls Audit",
    office: "City Information and Communications Technology Office",
    auditType: "Information Systems Audit",
    startDate: "2026-06-01",
    endDate: "2026-10-31",
    status: "Planned",
  },
  {
    engagementId: "AEMS-2025-005",
    code: "AEMS-2025-005",
    title: "Cash and Bank Management Audit",
    office: "City Treasurer's Office",
    auditType: "Financial Audit",
    startDate: "2026-03-01",
    endDate: "2026-05-30",
    status: "Ongoing",
  },
];
