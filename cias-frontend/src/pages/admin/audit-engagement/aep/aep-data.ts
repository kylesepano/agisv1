import type { AepFormState } from "./aep-types";

export const defaultCriteriaFrameworkOptions = [
  "Republic Act No. 9184 (Government Procurement Reform Act) & 2016 Revised IRR",
  "COA Circular No. 2012-001 (Prescribing Revised Guidelines on Document Requirements for Common Government Transactions)",
  "COA Circular No. 2009-006 (Restructuring the Rules and Regulations on Settlement of Accounts)",
  "Republic Act No. 7160 (Local Government Code of 1991 - Title VI Property and Supply Management)",
  "National Internal Control Standards for the Philippine Public Sector (NICSPPS)",
  "Philippine Internal Auditing Standards for Public Sector (PIASPS)",
  "Executive Order No. 2024-002 (Strengthening Internal Control Systems in City Government)",
];

export const defaultEvidenceCategories = [
  "Annual Procurement Plan (APP) and Project Procurement Management Plan (PPMP)",
  "Purchase Requests (PR), Certificate of Availability of Funds, and Pre-Encumbrance slips",
  "BAC Resolutions, PhilGEPS Postings, and Minutes of Bidding Meetings",
  "Abstract of Bids, TWG Bid Evaluation and Post-Qualification Reports",
  "Purchase Orders (PO), Contracts, Notice of Award (NOA), Notice to Proceed (NTP)",
  "Delivery Receipts, Inspection and Acceptance Reports (IAR), and Property Transfer Reports (PTR)",
  "Disbursement Vouchers (DV), Official Receipts (OR), and BIR Tax Withholding Certificates",
  "Warehouse Stock Cards, Bin Cards, and Physical Count Inventory Sheets",
];

export const defaultAepState: AepFormState = {
  aepId: "AEP-2026-010-01",
  engagementNumber: "AEMS-2026-010",
  engagementTitle: "Procurement Process & Supply Chain Compliance Audit",
  office: "City General Services Office",
  version: "v1.0",
  effectiveDate: "2026-02-15",
  status: "Draft",
  isAmendment: false,
  amendmentReason: "",

  objectives:
    "Evaluate statutory compliance with RA 9184 and local procurement threshold controls, verifying the transparency, efficiency, competitive bidding, and integrity of BAC documentation, warehouse inventory custody, and disbursement voucher trails.",
  refinedScope:
    "Covers all public bidding and Alternative Methods of Procurement (AMP) transactions processed through the City General Services Office and Bids and Awards Committee (BAC) from January 1, 2025 to December 31, 2025, including warehouse inventory custody as of Q4 2025.",
  criteriaFramework: [
    "Republic Act No. 9184 (Government Procurement Reform Act) & 2016 Revised IRR",
    "COA Circular No. 2012-001 (Prescribing Revised Guidelines on Document Requirements for Common Government Transactions)",
    "Republic Act No. 7160 (Local Government Code of 1991 - Title VI Property and Supply Management)",
    "Executive Order No. 2024-002 (Strengthening Internal Control Systems in City Government)",
  ],

  riskMatrixSummaryRef:
    "Linked to 4 controlled Risk Matrix Items (RMI-2026-001 through RMI-2026-004) under the SCR-221 Engagement Risk Matrix.",
  methodology:
    "1. Preliminary Survey & Flowchart Walkthroughs with BAC Secretariat and CGSO warehouse custodians.\n2. 100% Substantive Testing of major public bidding contracts exceeding PHP 1,000,000.\n3. Monetary Unit Sampling (MUS) on Small Value Procurement (SVP) and Shopping disbursement vouchers.\n4. Dual physical warehouse inspection and sample stock card inventory reconciliation.\n5. Analytical review of Purchase Request submission dates to detect contract splitting.",
  evidenceRequirements: [
    "Annual Procurement Plan (APP) and Project Procurement Management Plan (PPMP)",
    "Purchase Requests (PR), Certificate of Availability of Funds, and Pre-Encumbrance slips",
    "BAC Resolutions, PhilGEPS Postings, and Minutes of Bidding Meetings",
    "Abstract of Bids, TWG Bid Evaluation and Post-Qualification Reports",
    "Delivery Receipts, Inspection and Acceptance Reports (IAR), and Property Transfer Reports (PTR)",
    "Disbursement Vouchers (DV), Official Receipts (OR), and BIR Tax Withholding Certificates",
  ],

  assignedTeamRef:
    "Team Roster: Juan Dela Cruz (Lead Auditor), Kristine Joy B. Ramos (Auditor / IS Specialist), Engr. Daniel L. Torres (Technical Specialist). Supervised by Atty. Fernando M. Gomez, CPA.",
  requiredAuditTools:
    "CIAS e-Sampling Tool, BAC PhilGEPS Verification Ledger, Digital Document Scanner, Physical Count Inventory Sheets.",

  milestones: [
    {
      id: "MS-01",
      phaseTitle: "Preliminary Survey & Entry Conference",
      targetStartDate: "2026-02-15",
      targetEndDate: "2026-02-20",
      responsibleLead: "Juan Dela Cruz",
      deliverable: "Conducted Entry Conference & Signed Attendance Sheet",
    },
    {
      id: "MS-02",
      phaseTitle: "Fieldwork Testing & Voucher Sampling",
      targetStartDate: "2026-02-23",
      targetEndDate: "2026-03-27",
      responsibleLead: "Juan Dela Cruz & Audit Team",
      deliverable: "Completed Working Papers (WP-2026-001 ~ WP-2026-003)",
    },
    {
      id: "MS-03",
      phaseTitle: "Issue Development & AFR Formulation",
      targetStartDate: "2026-03-30",
      targetEndDate: "2026-04-10",
      responsibleLead: "Atty. Fernando M. Gomez, CPA",
      deliverable: "Draft Audit Finding Recommendations (AFRs)",
    },
    {
      id: "MS-04",
      phaseTitle: "Exit Conference & Final Report Issuance",
      targetStartDate: "2026-04-13",
      targetEndDate: "2026-04-30",
      responsibleLead: "Director Manuel C. Tan, CPA, CFE",
      deliverable: "Final Audit Report Issued to City Mayor",
    },
  ],

  kpis: [
    {
      id: "KPI-01",
      metricName: "High-Value Contract Verification Rate",
      targetValue: "100% of contracts > PHP 1,000,000",
      measurementMethod: "Complete audit program testing against PhilGEPS records",
      responsibleRole: "Lead Auditor",
    },
    {
      id: "KPI-02",
      metricName: "Sampling Population Coverage",
      targetValue: "At least 30 random sample vouchers across AMP",
      measurementMethod: "Sample voucher vouching and arithmetic validation",
      responsibleRole: "Auditor (K. Ramos)",
    },
    {
      id: "KPI-03",
      metricName: "Report Issuance Timeline",
      targetValue: "Draft report completed within 15 days of fieldwork end",
      measurementMethod: "Tracking from Fieldwork sign-off to AFR transmission",
      responsibleRole: "Supervising Auditor",
    },
    {
      id: "KPI-04",
      metricName: "Recommendation Actionability Rate",
      targetValue: "100% of AFRs include SMART corrective actions",
      measurementMethod: "Pre-issuance quality assurance checklist",
      responsibleRole: "Department Head",
    },
  ],

  communicationToManagement:
    "Formal briefings will be conducted with the CGSO Department Head and BAC Chairman at the Entry Conference (February 18, 2026) and Exit Conference (April 15, 2026). Weekly progress memos will be transmitted to the designated liaison officer.",
  reviewingSupervisor: "Atty. Fernando M. Gomez, CPA (Supervising Auditor)",
  approvingAuthority: "Director Manuel C. Tan, CPA, CFE (Department Head)",
  approvalDate: "2026-02-12",
};

export function buildInitialAepState(
  engagement: { code: string; auditArea?: string; subject?: string; office?: string; year?: string; objective?: string; scope?: string; startDate?: string; endDate?: string },
  savedTeam?: any,
): AepFormState {
  const codeNum = engagement.code.split("-")[2] || "000";
  const year = engagement.year || "2026";
  const engCode = engagement.code;
  const office = engagement.office || "Auditee Operations Office";
  const area =
    engagement.auditArea ||
    engagement.subject ||
    "Internal Control & Operations Audit";

  const teamMembers = savedTeam?.team || [];
  const lead = teamMembers.find((t: any) => t.role === "Lead Auditor")?.name || "Juan Dela Cruz";
  const supervisor = teamMembers.find((t: any) => t.role?.includes("Supervising"))?.name || "Atty. Fernando M. Gomez, CPA";

  return {
    aepId: `AEP-${year}-${codeNum}-01`,
    engagementNumber: engCode,
    engagementTitle: area,
    office: office,
    version: "v1.0",
    effectiveDate: `${year}-02-15`,
    status: "Draft",
    isAmendment: false,
    amendmentReason: "",

    objectives:
      engagement.objective?.trim() ||
      `Assess and evaluate statutory compliance, internal control effectiveness, and operational performance for ${area} under ${office}.`,
    refinedScope:
      engagement.scope?.trim() ||
      `Covers operational transactions, beneficiary registries, disbursement records, and control systems of ${office} for the approved audit period.`,
    criteriaFramework: [
      "COA Circular No. 2012-001 (Prescribing Revised Guidelines on Document Requirements for Common Government Transactions)",
      "Republic Act No. 7160 (Local Government Code of 1991)",
      "National Internal Control Standards for the Philippine Public Sector (NICSPPS)",
      "Executive Order No. 2024-002 (Strengthening Internal Control Systems in City Government)",
    ],

    riskMatrixSummaryRef: `Linked to controlled Risk Matrix Items for ${engCode} under the SCR-221 Engagement Risk Matrix.`,
    methodology: `1. Preliminary survey, internal control questionnaires, and operational walkthroughs with ${office} personnel.\n2. Substantive compliance testing and sampling on operational records and disbursements.\n3. Physical inspections and validation of inventory/beneficiary distribution masterlists.\n4. Analytical review and reconciliation against electronic ledgers and source transmittals.`,
    evidenceRequirements: [
      `Approved ${year} Operational Plans, Work Programs, and Target Guidelines`,
      "Disbursement Vouchers (DV), Official Receipts (OR), and Transmittal Records",
      "Beneficiary Masterlists, Distribution Acknowledgments, and Receiving Slips",
      "Physical Inspection Reports and Asset/Inventory Verification Sheets",
    ],

    assignedTeamRef: `Team Roster: ${lead} (Lead Auditor), Assigned Audit Staff. Supervised by ${supervisor}.`,
    requiredAuditTools: "CIAS e-Sampling Tool, Operational Verification Checklists, Digital Scanner.",

    milestones: [
      {
        id: "MS-01",
        phaseTitle: "Preliminary Survey & Entry Conference",
        targetStartDate: `${year}-02-15`,
        targetEndDate: `${year}-02-20`,
        responsibleLead: lead,
        deliverable: "Conducted Entry Conference & Signed Attendance Sheet",
      },
      {
        id: "MS-02",
        phaseTitle: "Fieldwork Testing & Sampling",
        targetStartDate: `${year}-02-23`,
        targetEndDate: `${year}-03-27`,
        responsibleLead: `${lead} & Audit Team`,
        deliverable: `Completed Working Papers (WP-${year}-${codeNum}-OPS-01)`,
      },
      {
        id: "MS-03",
        phaseTitle: "Issue Development & Findings Formulation",
        targetStartDate: `${year}-03-30`,
        targetEndDate: `${year}-04-10`,
        responsibleLead: supervisor,
        deliverable: "Draft Audit Finding Recommendations (AFRs)",
      },
      {
        id: "MS-04",
        phaseTitle: "Exit Conference & Final Report Issuance",
        targetStartDate: `${year}-04-13`,
        targetEndDate: `${year}-04-30`,
        responsibleLead: "Director Manuel C. Tan, CPA, CFE",
        deliverable: "Final Audit Report Issued to City Mayor",
      },
    ],

    kpis: [
      {
        id: "KPI-01",
        metricName: "Sampling Population Coverage",
        targetValue: "At least 30 random sample vouchers/records",
        measurementMethod: "Sample vouching and arithmetic validation",
        responsibleRole: "Lead Auditor",
      },
      {
        id: "KPI-02",
        metricName: "Fieldwork Completion Timeline",
        targetValue: "100% of planned fieldwork procedures completed on schedule",
        measurementMethod: "Weekly milestone tracking against AEP timeline",
        responsibleRole: "Lead Auditor",
      },
      {
        id: "KPI-03",
        metricName: "Report Issuance Timeline",
        targetValue: "Draft report completed within 15 days of fieldwork end",
        measurementMethod: "Tracking from Fieldwork sign-off to AFR transmission",
        responsibleRole: "Supervising Auditor",
      },
    ],

    communicationToManagement: `Formal briefings will be conducted with ${office} leadership at the Entry Conference and Exit Conference. Regular coordination memos will be transmitted to designated focal persons.`,
    reviewingSupervisor: supervisor,
    approvingAuthority: "Director Manuel C. Tan, CPA, CFE (Department Head)",
    approvalDate: `${year}-02-12`,
  };
}
