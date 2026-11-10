import type {
  WorkingPaper,
  WorkingPaperPackageState,
} from "./working-papers-types";

export const defaultWorkingPapers: WorkingPaper[] = [
  {
    id: "WP-2026-003-WH-01",
    referenceNumber: "AEMS-2026-WP-003",
    engagementNumber: "AEMS-2026-010",

    type: "Execution",
    plannedReference: "AEP-2026-010-01 / PROG-2026-003-WH",
    procedureId: "PROC-2026-003-01",
    procedureTitle: "Physical Inventory Sample Count & Stock Card Reconciliation",
    riskMatrixItemId: "RMI-2026-003",
    riskMatrixTitle: "Warehouse Inventory Receiving, Custody & Stock Card Variance Risk",
    auditArea: "City General Services Office Governance & Compliance Controls",

    purpose:
      "Execute substantive audit testing on high-value consumable inventory items at CGSO Central Depot (Bay 2), verify stock card ledger integrity, and determine physical count variance per MDS-200 §7 and Rule 13.",
    workPerformed:
      "1. Conducted unannounced physical spot count of 12 selected high-value consumable SKUs on March 9, 2026 in coordination with CGSO Warehouse Custodian Mr. Eduardo Pineda.\n2. Reconciled physical quantities against bin cards, storekeeper tally sheets, and electronic e-NGAS inventory balances.\n3. Examined warehouse security controls, barcoded bin tagging, and custody transfer authorizations for stock releases.\n4. Verified physical condition and proper shelving of emergency response consumables.",

    sourceFieldworkRecordId: "FWR-2026-001",
    sourceFieldworkType: "Physical Inspection",

    evidenceLinks: [
      {
        id: "EVD-2026-001",
        title: "Signed Physical Inventory Count Sheets (March 9, 2026)",
        type: "Scanned Document",
        sourceUrlOrRef: "WP-2026-003-WH-01/EVD-01.pdf",
      },
      {
        id: "EVD-2026-002",
        title: "Photographic Evidence of Warehouse Bay 2 Storage Shelves",
        type: "Digital Photo",
        sourceUrlOrRef: "WP-2026-003-WH-01/IMG-09-BAY2.jpg",
      },
      {
        id: "EVD-2026-003",
        title: "Signed RIS (Requisition and Issue Slip) — Motorpool Urgent Issue",
        type: "Scanned Document",
        sourceUrlOrRef: "WP-2026-003-WH-01/RIS-2026-03-06.pdf (SCR-229: Accepted with Limitation)",
      },
    ],

    results:
      "11 out of 12 SKUs showed 100% reconciliation with zero variance. SKU-AUT-2025-14 (Heavy Duty Truck Tires) exhibited an initial physical deficit of 2 units (18 counted vs 20 on ledger), which was accounted for via a physical signed Requisition and Issue Slip (RIS) urgently issued to Motorpool for Emergency Rescue Vehicle #04.",
    conclusion:
      "Inventory physical controls in Bay 2 are generally satisfactory with verified barcoded tagging. However, storekeeper issuance logging was delayed by 3 business days before stock card posting. Recommended immediate electronic RIS recording to eliminate temporary ledger discrepancies.",

    preparer: "Engr. Daniel L. Torres (Technical Specialist)",
    reviewer: "Atty. Fernando M. Gomez, CPA (Supervising Auditor)",

    reviewNotes: [
      {
        id: "RN-2026-002",
        reviewer: "Atty. Fernando M. Gomez, CPA (Supervising Auditor)",
        note: "Ensure physical count sheets carry simultaneous countersignatures from Warehouse Custodian Mr. Eduardo Pineda before indexing.",
        response: "On-site count sheet countersigned by Mr. Pineda on March 9. Scanning underway.",
        resolution: "Pending upload of signed sheet to Working Paper repository.",
        status: "In Discussion",
      },
    ],

    reviewStatus: "Submitted",
    version: "v1.0",
    createdAt: "2026-03-09 17:00",
    updatedAt: "2026-03-09 17:45",
  },
  {
    id: "WP-2026-001-BID-01",
    referenceNumber: "AEMS-2026-WP-001",
    engagementNumber: "AEMS-2026-010",

    type: "Execution",
    plannedReference: "AEP-2026-010-01 / PROG-2026-001-BID",
    procedureId: "PROC-2026-001-01",
    procedureTitle: "PhilGEPS Posting & Mandatory Advertisement Verification",
    riskMatrixItemId: "RMI-2026-002",
    riskMatrixTitle: "Public Bidding Transparency & Timelines Non-Compliance Risk",
    auditArea: "Procurement Process & Supply Chain Compliance Audit",

    purpose:
      "Verify 100% compliance of Invitation to Bid (ITB) PhilGEPS postings, minimum 7-calendar-day public visibility, and electronic timestamp authenticity for FY 2025 high-value infrastructure and goods tenders.",
    workPerformed:
      "1. Verified electronic audit trail and PhilGEPS posting receipts for 5 major capital infrastructure tenders directly from BAC posting terminal.\n2. Cross-referenced PhilGEPS posting start dates and pre-bid conference schedules against RA 9184 statutory minimum timelines.\n3. Recalculated timeline intervals including public holidays and weekend spans.\n4. Reconciled newspaper advertisement clippings against BAC minutes.",

    sourceFieldworkRecordId: "FWR-2026-003",
    sourceFieldworkType: "Process Walkthrough",

    evidenceLinks: [
      {
        id: "EVD-2026-004",
        title: "PhilGEPS Electronic Posting Receipts Batch 01",
        type: "Electronic PDF",
        sourceUrlOrRef: "WP-2026-001-BID-01/PHILGEPS-RECEIPTS.pdf",
      },
    ],

    results:
      "All 5 sampled high-value projects demonstrated continuous electronic posting on the PhilGEPS portal with durations between 7 and 12 calendar days prior to pre-bid conferences. Timestamp receipts were mathematically verified and validated.",
    conclusion:
      "Public bidding mandatory advertisement requirements under RA 9184 Section 21 are fully compliant. Electronic audit trail is well-maintained and verified with zero non-compliance exceptions noted.",

    preparer: "Juan Dela Cruz (Lead Auditor)",
    reviewer: "Atty. Fernando M. Gomez, CPA (Supervising Auditor)",

    reviewNotes: [
      {
        id: "RN-2026-001",
        reviewer: "Atty. Fernando M. Gomez, CPA (Supervising Auditor)",
        note: "Please verify if the 7-day PhilGEPS posting period for Project CGSO-2025-04 includes non-working holidays pursuant to GPPB Resolution 03-2020.",
        response: "Cross-checked with City Legal calendar. 7 calendar days were met, but verification working paper reference updated to include PhilGEPS timestamp receipt.",
        resolution: "Satisfactorily reconciled. Added timestamp to WP-2026-001-BID-02.",
        clearedBy: "Atty. Fernando M. Gomez, CPA",
        clearanceDate: "2026-03-16",
        status: "Cleared",
      },
    ],

    clearance: {
      clearedBy: "Atty. Fernando M. Gomez, CPA",
      clearanceDate: "2026-03-16",
      clearanceRemarks: "All posting timeline verifications and timestamp receipts confirmed valid.",
    },

    reviewStatus: "Cleared",
    version: "v1.1",
    createdAt: "2026-03-02 11:30",
    updatedAt: "2026-03-16 09:30",
  },
  {
    id: "WP-2026-002-AMP-01",
    referenceNumber: "AEMS-2026-WP-002",
    engagementNumber: "AEMS-2026-010",

    type: "Execution",
    plannedReference: "AEP-2026-010-01 / PROG-2026-002-AMP",
    procedureId: "PROC-2026-002-01",
    procedureTitle: "Small Value Procurement (SVP) Threshold & Justification Review",
    riskMatrixItemId: "RMI-2026-001",
    riskMatrixTitle: "Alternative Procurement Justification & Statutory Ceiling Risk",
    auditArea: "Procurement Process & Supply Chain Compliance Audit",

    purpose:
      "Evaluate statutory threshold compliance, price canvass procedures, and supplier rotation for 30 sample Small Value Procurement disbursement vouchers under PHP 1,000,000.",
    workPerformed:
      "1. Audited 30 sample SVP vouchers against RA 9184 statutory ceilings and approved Annual Procurement Plan.\n2. Examined BAC Secretariat manual RFQ logbook extracts and verified whether at least 3 price quotations were obtained.\n3. Interviewed BAC Secretariat Head Ms. Maricor Villanueva regarding quotation rotation and PhilGEPS posting compliance.\n4. Verified BAC Chairperson approval on Abstract of Quotations before purchase order issuance.",

    sourceFieldworkRecordId: "FWR-2026-002",
    sourceFieldworkType: "Interview / Inquiry",

    evidenceLinks: [
      {
        id: "EVD-2026-003",
        title: "BAC Secretariat RFQ Logbook Extract (Q3-Q4 2025)",
        type: "Scanned Logbook",
        sourceUrlOrRef: "WP-2026-002-AMP-01/BAC-RFQ-EXTRACT.pdf",
      },
    ],

    results:
      "8 out of 30 tested sample vouchers had only 2 supplier quotations on file, citing lack of local suppliers. In addition, PhilGEPS posting for transactions exceeding PHP 50,000 was intermittently omitted due to reported connectivity bottlenecks.",
    conclusion:
      "Controls over supplier canvassing require tightening. A formal finding will be drafted regarding unposted SVP transactions exceeding PHP 50,000 in accordance with RA 9184 IRR Annex H.",

    preparer: "Kristine Joy B. Ramos (Internal Auditor II)",
    reviewer: "Atty. Fernando M. Gomez, CPA (Supervising Auditor)",

    reviewNotes: [],

    reviewStatus: "Reviewed",
    version: "v1.1",
    createdAt: "2026-03-04 15:30",
    updatedAt: "2026-03-05 14:00",
  },
];

export const defaultWorkingPaperPackageState: WorkingPaperPackageState = {
  engagementNumber: "AEMS-2026-010",
  engagementTitle: "Procurement Process & Supply Chain Compliance Audit",
  office: "City General Services Office",
  activeWorkingPaperId: "WP-2026-003-WH-01",
  workingPapers: defaultWorkingPapers,
};
