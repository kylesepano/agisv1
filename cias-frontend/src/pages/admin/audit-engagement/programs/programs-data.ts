import type { AuditProgram, ProgramsPackageState } from "./programs-types";

export const defaultAuditPrograms: AuditProgram[] = [
  {
    id: "PROG-2026-001-BID",
    auditArea: "Procurement Process & Supply Chain Compliance Audit",
    programTitle: "Public Bidding & PhilGEPS Posting Compliance Program",
    description:
      "Substantive testing of public bidding contract files, BAC minutes, PhilGEPS invitation postings, TWG bid evaluations, and award notices for statutory compliance under RA 9184.",
    auditType: "Compliance & Operations Audit",
    auditPeriod: "January 1, 2025 – December 31, 2025",
    linkedRmiIds: ["RMI-2026-002", "RMI-2026-004"],
    objectivesTrace:
      "Traces to AEP-2026-010-01 Objective 1: Verify competitive public bidding transparency and adherence to mandatory posting periods.",
    criteriaFrameworkRefs: [
      "Republic Act No. 9184 (Government Procurement Reform Act) & 2016 Revised IRR",
      "COA Circular No. 2012-001 (Document Requirements for Government Transactions)",
      "Executive Order No. 2024-002 (Strengthening Internal Control Systems)",
    ],
    preparedBy: "Juan Dela Cruz (Lead Auditor)",
    reviewedBy: "Atty. Fernando M. Gomez, CPA (Supervising Auditor)",
    approvedBy: "Director Manuel C. Tan, CPA, CFE (Department Head)",
    effectiveDate: "2026-02-15",
    version: "v1.0",
    status: "Draft",
    procedures: [
      {
        id: "PROC-2026-BID-01",
        riskMatrixItemId: "RMI-2026-002",
        pfdStageRef: "S2: BAC Pre-Procurement Conference & PhilGEPS Posting",
        procedureTitle: "100% High-Value Bidding Document & PhilGEPS Posting Verification",
        purpose:
          "Ascertain that all procurement projects exceeding PHP 1.0M strictly complied with mandatory 7-day PhilGEPS advertisement and pre-procurement conference minutes.",
        auditMethod: "Inspection",
        expectedEvidence:
          "PhilGEPS posting screenshot with Document Reference No., BAC Resolution to Advertise, Newspaper Advertisement proof.",
        personResponsible: "Juan Dela Cruz (Lead Auditor)",
        plannedManDays: 10,
        workingPaperRef: "WP-2026-001-BID",
        dueDate: "2026-03-06",
        status: "Pending Execution",
      },
      {
        id: "PROC-2026-BID-02",
        riskMatrixItemId: "RMI-2026-002",
        pfdStageRef: "S2: BAC Pre-Procurement Conference & PhilGEPS Posting",
        procedureTitle: "Technical Specifications & TWG Evaluation Vouching",
        purpose:
          "Evaluate technical specifications for potential brand tailoring, restrictive clauses, and verify arithmetic accuracy of TWG bid evaluation matrices.",
        auditMethod: "Sampling & Vouching",
        expectedEvidence:
          "TWG Evaluation Sheets, Abstract of Bids As Calculated, Post-Qualification Summary Reports.",
        personResponsible: "Kristine Joy B. Ramos (Auditor / IS Specialist)",
        plannedManDays: 5,
        workingPaperRef: "WP-2026-001-BID-SPEC",
        dueDate: "2026-03-13",
        status: "Pending Execution",
      },
      {
        id: "PROC-2026-BID-03",
        riskMatrixItemId: "RMI-2026-004",
        pfdStageRef: "S1: Purchase Request & PPMP Pre-Encumbrance Verification",
        procedureTitle: "Purchase Request Sequence & Splitting Trend Analysis",
        purpose:
          "Conduct electronic data analysis on consecutive PR numbers and adjacent issuance dates to detect artificial splitting of requisitions.",
        auditMethod: "Analytical Review",
        expectedEvidence:
          "City Financial Electronic Ledger PR Export, Consolidated Purchase Request Logs.",
        personResponsible: "Juan Dela Cruz (Lead Auditor)",
        plannedManDays: 3,
        workingPaperRef: "WP-2026-001-BID-SPLIT",
        dueDate: "2026-03-18",
        status: "Pending Execution",
      },
    ],
  },
  {
    id: "PROG-2026-002-AMP",
    auditArea: "Procurement Process & Supply Chain Compliance Audit",
    programTitle:
      "Alternative Methods of Procurement (AMP) Justification & Voucher Verification Program",
    description:
      "Detailed audit testing of Small Value Procurement (SVP), Shopping, and Direct Contracting transactions to evaluate legal justification adequacy and disbursement voucher authenticity.",
    auditType: "Compliance & Financial Audit",
    auditPeriod: "January 1, 2025 – December 31, 2025",
    linkedRmiIds: ["RMI-2026-001"],
    objectivesTrace:
      "Traces to AEP-2026-010-01 Objective 2: Confirm validity of statutory justifications for resorting to non-competitive AMP modes.",
    criteriaFrameworkRefs: [
      "Republic Act No. 9184 & 2016 Revised IRR (Rule XVI Alternative Methods)",
      "COA Circular No. 2012-001",
      "Republic Act No. 7160 (Local Government Code of 1991)",
    ],
    preparedBy: "Kristine Joy B. Ramos (Auditor / IS Specialist)",
    reviewedBy: "Atty. Fernando M. Gomez, CPA (Supervising Auditor)",
    approvedBy: "Director Manuel C. Tan, CPA, CFE (Department Head)",
    effectiveDate: "2026-02-15",
    version: "v1.0",
    status: "Draft",
    procedures: [
      {
        id: "PROC-2026-AMP-01",
        riskMatrixItemId: "RMI-2026-001",
        pfdStageRef: "S1: Purchase Request & PPMP Pre-Encumbrance Verification",
        procedureTitle: "SVP Statutory Threshold & APP Alignment Vouching",
        purpose:
          "Test a representative sample of 30 Small Value Procurement purchase orders to confirm they were included in the approved APP and did not exceed LGU statutory thresholds.",
        auditMethod: "Sampling & Vouching",
        expectedEvidence:
          "Approved APP-CSE & Supplemental APP, Canvas Request for Quotation forms, Canvass Summary Sheets.",
        personResponsible: "Kristine Joy B. Ramos (Auditor / IS Specialist)",
        plannedManDays: 8,
        workingPaperRef: "WP-2026-002-AMP",
        dueDate: "2026-03-20",
        status: "Pending Execution",
      },
      {
        id: "PROC-2026-AMP-02",
        riskMatrixItemId: "RMI-2026-001",
        pfdStageRef: "S2: BAC Pre-Procurement Conference & PhilGEPS Posting",
        procedureTitle: "Direct Contracting & Emergency Procurement Justification Review",
        purpose:
          "Examine 100% of Direct Contracting and Negotiated Procurement (Emergency Cases) transactions for requisite BAC justification resolutions and HOPE executive approvals.",
        auditMethod: "Inspection",
        expectedEvidence:
          "BAC Resolution Recommending AMP, Exclusive Dealership Certificates, HOPE Formal Approval Letter.",
        personResponsible: "Juan Dela Cruz (Lead Auditor)",
        plannedManDays: 7,
        workingPaperRef: "WP-2026-002-AMP-DIR",
        dueDate: "2026-03-25",
        status: "Pending Execution",
      },
    ],
  },
  {
    id: "PROG-2026-003-WH",
    auditArea: "City General Services Office Governance & Compliance Controls",
    programTitle: "Warehouse Inventory Receiving, Custody & Stock Card Reconciliation Program",
    description:
      "Physical and documentary inspection of warehouse supply custody, Inspection & Acceptance Report (IAR) issuance controls, and bin card inventory reconciliation in the CGSO central depot.",
    auditType: "Operations & Asset Protection Audit",
    auditPeriod: "January 1, 2025 – December 31, 2025",
    linkedRmiIds: ["RMI-2026-003"],
    objectivesTrace:
      "Traces to AEP-2026-010-01 Objective 3: Evaluate internal controls governing delivery inspection, warehouse custody, and issuance records.",
    criteriaFrameworkRefs: [
      "Republic Act No. 7160 (Local Government Code - Title VI Property and Supply Management)",
      "COA Circular No. 2012-001",
      "Executive Order No. 2024-002",
    ],
    preparedBy: "Engr. Daniel L. Torres (Technical Specialist)",
    reviewedBy: "Atty. Fernando M. Gomez, CPA (Supervising Auditor)",
    approvedBy: "Director Manuel C. Tan, CPA, CFE (Department Head)",
    effectiveDate: "2026-02-15",
    version: "v1.0",
    status: "Draft",
    procedures: [
      {
        id: "PROC-2026-WH-01",
        riskMatrixItemId: "RMI-2026-003",
        pfdStageRef: "S3: Goods Delivery, Physical Inspection & Warehouse Storage",
        procedureTitle: "Delivery Inspection & Acceptance Report (IAR) Sign-off Verification",
        purpose:
          "Verify that deliveries of goods were inspected by authorized Inspection and Acceptance Committee members prior to voucher endorsement.",
        auditMethod: "Inspection",
        expectedEvidence:
          "Inspection and Acceptance Reports (IAR), Supplier Delivery Receipts with receiving stamps, Purchase Orders.",
        personResponsible: "Engr. Daniel L. Torres (Technical Specialist)",
        plannedManDays: 6,
        workingPaperRef: "WP-2026-003-WH-IAR",
        dueDate: "2026-03-24",
        status: "Pending Execution",
      },
      {
        id: "PROC-2026-WH-02",
        riskMatrixItemId: "RMI-2026-003",
        pfdStageRef: "S3: Goods Delivery, Physical Inspection & Warehouse Storage",
        procedureTitle: "Physical Sample Count vs Bin Card & Property Transfer Reconciliation",
        purpose:
          "Conduct on-site physical sample counts of high-value consumable inventory items and reconcile physical balances against CGSO warehouse stock cards.",
        auditMethod: "Observation",
        expectedEvidence:
          "Physical Inventory Count Sheets, CGSO Stock Cards, Property Transfer Reports (PTR).",
        personResponsible: "Engr. Daniel L. Torres (Technical Specialist)",
        plannedManDays: 4,
        workingPaperRef: "WP-2026-003-WH-COUNT",
        dueDate: "2026-03-27",
        status: "Pending Execution",
      },
      {
        id: "PROC-2026-WH-03",
        riskMatrixItemId: "RMI-2026-003",
        pfdStageRef: "S3: Goods Delivery, Physical Inspection & Warehouse Storage",
        procedureTitle: "Post-Delivery Warranty Retention & Supplier Performance Tracking",
        purpose:
          "Verify that mandatory 1% warranty retention or special bank guarantee was withheld on goods delivered, and test supplier replacement response times.",
        auditMethod: "Inspection",
        expectedEvidence:
          "Disbursement Voucher deduction lines, Warranty Security documents, Defective Item Replacement logs.",
        personResponsible: "Kristine Joy B. Ramos (Auditor / IS Specialist)",
        plannedManDays: 2,
        workingPaperRef: "WP-2026-003-WH-WARR",
        dueDate: "2026-03-27",
        status: "Pending Execution",
      },
    ],
  },
];

export const defaultProgramsPackageState: ProgramsPackageState = {
  engagementNumber: "AEMS-2026-010",
  engagementTitle: "Procurement Process & Supply Chain Compliance Audit",
  office: "City General Services Office",
  phase: "Phase 2 of 10 • Audit Planning",
  programs: defaultAuditPrograms,
};
