import type {
  FieldworkPackageState,
  FieldworkRecord,
} from "./fieldwork-types";

export const defaultFieldworkRecords: FieldworkRecord[] = [
  {
    id: "FWR-2026-001",
    engagementNumber: "AEMS-2026-010",
    procedureId: "PROC-2026-003-01",
    procedureTitle: "Physical Inventory Sample Count & Stock Card Reconciliation",
    auditArea: "City General Services Office Governance & Compliance Controls",
    workingPaperRef: "WP-2026-003-WH-01",

    type: "Physical Inspection",
    date: "2026-03-09",
    location: "CGSO Central Warehouse, Bay 2 (Medical & Consumables), City Hall Compound",
    personsInvolved:
      "Engr. Daniel L. Torres (Technical Specialist / Inspector), Mr. Eduardo S. Pineda (Supply & Property Custodian), Ms. Rowena Santos (Warehouse Clerk)",
    purpose:
      "Conduct unannounced physical spot count of top 20 high-value consumable inventory items (PPE, medical gloves, tire sets) and reconcile physical count against stock cards and e-NGAS ledger.",
    factsObtained:
      "1. Conducted 100% physical count for 12 selected SKUs.\n2. Item SKU-MED-2025-09 (Surgical Gloves, Box of 100): Physical count = 412 boxes; Stock card balance = 412 boxes (Zero variance).\n3. Item SKU-AUT-2025-14 (Heavy Duty Truck Tires 11R22.5): Physical count = 18 units; Stock card balance = 20 units (Discrepancy: 2 units issued on March 6 without storekeeper signed Requisition Slip in file).\n4. All counted storage bins were labeled with barcoded asset tags.",
    notes:
      "Custodian Mr. Pineda explained that the 2 missing tires were urgently released to Motorpool Division for Emergency Rescue Vehicle #04. Signed RIS was retrieved during inspection and appended to evidence link.",

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

    preparer: "Engr. Daniel L. Torres (Technical Specialist)",
    reviewer: "Atty. Fernando M. Gomez, CPA (Supervising Auditor)",
    status: "Submitted",
    version: "v1.0",
    createdAt: "2026-03-09 16:30",
    updatedAt: "2026-03-09 17:15",
  },
  {
    id: "FWR-2026-002",
    engagementNumber: "AEMS-2026-010",
    procedureId: "PROC-2026-002-01",
    procedureTitle: "Small Value Procurement (SVP) Threshold & Justification Review",
    auditArea: "Procurement Process & Supply Chain Compliance Audit",
    workingPaperRef: "WP-2026-002-AMP-01",

    type: "Interview / Inquiry",
    date: "2026-03-04",
    location: "BAC Secretariat Office, 2nd Floor City Hall",
    personsInvolved:
      "Kristine Joy B. Ramos (Auditor), Ms. Maricor E. Villanueva (Head, BAC Secretariat)",
    purpose:
      "Inquire into the justification mechanisms, price canvass procedures, and supplier quotation rotation for Small Value Procurement transactions under PHP 1,000,000.",
    factsObtained:
      "1. BAC Secretariat utilizes a manual logbook for tracking Request for Quotations (RFQs) sent to at least 3 accredited suppliers.\n2. In 8 out of 30 tested sample vouchers, only 2 supplier quotations were on file, citing lack of available local suppliers.\n3. Abstract of Quotations was consistently approved by BAC Chairperson prior to PO issuance.",
    notes:
      "Auditor inquired if posting on PhilGEPS was conducted for SVP transactions with ABC exceeding PHP 50,000 as mandated by RA 9184 IRR Annex H. Ms. Villanueva confirmed PhilGEPS posting was done intermittently due to system connection bottlenecks.",

    evidenceLinks: [
      {
        id: "EVD-2026-003",
        title: "BAC Secretariat RFQ Logbook Extract (Q3-Q4 2025)",
        type: "Scanned Logbook",
        sourceUrlOrRef: "WP-2026-002-AMP-01/BAC-RFQ-EXTRACT.pdf",
      },
    ],

    preparer: "Kristine Joy B. Ramos (Internal Auditor II)",
    reviewer: "Atty. Fernando M. Gomez, CPA (Supervising Auditor)",
    status: "Reviewed",
    version: "v1.1",
    createdAt: "2026-03-04 14:00",
    updatedAt: "2026-03-05 11:20",
  },
  {
    id: "FWR-2026-003",
    engagementNumber: "AEMS-2026-010",
    procedureId: "PROC-2026-001-01",
    procedureTitle: "PhilGEPS Posting & Mandatory Advertisement Verification",
    auditArea: "Procurement Process & Supply Chain Compliance Audit",
    workingPaperRef: "WP-2026-001-BID-01",

    type: "Process Walkthrough",
    date: "2026-03-02",
    location: "City Management Information System (MIS) / BAC Posting Terminal",
    personsInvolved:
      "Juan Dela Cruz (Lead Auditor), Mr. Aris G. Santos (BAC Electronic Posting Officer)",
    purpose:
      "Walk through the PhilGEPS electronic posting workflow from ITB preparation, digital token authentication, 7-day public visibility, to award notice upload.",
    factsObtained:
      "1. Demonstrated live posting verification on PhilGEPS portal.\n2. Verified electronic timestamp receipts for 5 major capital infrastructure tenders.\n3. ITB posting dates ranged from 7 to 12 calendar days prior to pre-bid conferences.",
    notes:
      "Electronic audit trail is well maintained. Screenshots and timestamp certificates were downloaded and archived.",

    evidenceLinks: [
      {
        id: "EVD-2026-004",
        title: "PhilGEPS Electronic Posting Receipts Batch 01",
        type: "Electronic PDF",
        sourceUrlOrRef: "WP-2026-001-BID-01/PHILGEPS-RECEIPTS.pdf",
      },
    ],

    preparer: "Juan Dela Cruz (Lead Auditor)",
    reviewer: "Atty. Fernando M. Gomez, CPA (Supervising Auditor)",
    status: "Draft",
    version: "v1.0",
    createdAt: "2026-03-02 10:15",
    updatedAt: "2026-03-02 11:45",
  },
];

export const defaultFieldworkPackageState: FieldworkPackageState = {
  engagementNumber: "AEMS-2026-010",
  engagementTitle: "Procurement Process & Supply Chain Compliance Audit",
  office: "City General Services Office",
  activeRecordId: "FWR-2026-001",
  records: defaultFieldworkRecords,
};
