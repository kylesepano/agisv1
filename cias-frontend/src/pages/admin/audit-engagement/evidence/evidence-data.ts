import type {
  AuditEvidenceItem,
  EvidencePackageState,
} from "./evidence-types";

export const defaultAuditEvidenceItems: AuditEvidenceItem[] = [
  {
    id: "EVD-2026-001",
    title: "Signed Physical Inventory Count Sheets (March 9, 2026)",
    engagementNumber: "AEMS-2026-010",
    relatedRequestId: "N/A (Direct Fieldwork Acquisition)",

    evidenceType: "Scanned Document / Voucher",
    auditProcedureMethod: "Inspection",
    source: "CGSO Central Warehouse Custodian Mr. Eduardo Pineda",
    sourceOrigin: "Internal",
    acquisitionMode: "Direct (Auditor-Observed/Gathered)",
    physicalForm: "Scanned Copy",
    custodian: "Engr. Daniel L. Torres (Technical Specialist / Inspector)",
    dateReceived: "2026-03-09",
    fileReference: "WP-2026-003-WH-01/EVD-01.pdf",
    fileSize: "2.4 MB",
    fileMimeType: "application/pdf",

    hashAlgorithm: "SHA-256",
    hashChecksum: "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",

    relatedCriteria:
      "Objective 3: Warehouse Custody Controls; RA 7160 Sec. 375; COA Circular 2012-001; Risk RMI-2026-003",
    sufficiencyContribution:
      "High — 100% physical spot recount covering 12 selected high-value consumable SKUs with verified balances.",
    appropriateness: "High",
    relevance:
      "Direct primary factual evidence substantiating physical stock quantities and zero count variance across 11 SKUs.",
    reliability:
      "High — Simultaneously countersigned on-site by Lead Inspector and Warehouse Custodian.",
    accuracyCompletenessProcedures:
      "Dual recount verification and cross-check against e-NGAS electronic ledger stock cards.",
    corroboration:
      "Corroborated by digital photographs (EVD-2026-002) and CGSO barcoded bin labels.",
    contradictoryEvidence:
      "None — initial 2-tire discrepancy was resolved by signed emergency Requisition and Issue Slip (EVD-2026-003).",
    authenticity:
      "Verified original wet-ink signatures, warehouse official seal, and inspector initial stamps.",

    confidentiality: "Internal Audit Confidential",
    limitation: "None — count sheet was executed synchronously during unannounced audit inspection.",
    accessRestriction: "Engagement Team & Reviewers",
    legalHold: false,
    archivalCondition: "Active Repository",
    assessmentResult:
      "Accepted for full substantiation in Working Paper WP-2026-003-WH-01 and procedure PROC-2026-003-01.",

    relatedArtifacts: [
      {
        id: "ART-01",
        type: "Procedure",
        ref: "PROC-2026-003-01",
        title: "Physical Inventory Sample Count & Stock Card Reconciliation",
      },
      {
        id: "ART-02",
        type: "Working Paper",
        ref: "WP-2026-003-WH-01",
        title: "Warehouse Inventory Physical Count & Reconciliation WP",
      },
      {
        id: "ART-03",
        type: "Fieldwork Record",
        ref: "FWR-2026-001",
        title: "Physical Inspection at CGSO Central Depot Bay 2",
      },
    ],

    custodyLog: [
      {
        id: "CST-001",
        date: "2026-03-09 14:30",
        action: "Direct On-Site Acquisition & Spot Count",
        person: "Engr. Daniel L. Torres",
        role: "Lead Inspector / Technical Specialist",
        notes: "Completed physical counts of 12 SKUs with Warehouse Custodian Eduardo Pineda.",
      },
      {
        id: "CST-002",
        date: "2026-03-09 15:45",
        action: "Simultaneous Countersignature & Endorsement",
        person: "Mr. Eduardo Pineda",
        role: "CGSO Supply & Property Custodian",
        notes: "Countersigned count sheet with no contested physical count figures.",
      },
      {
        id: "CST-003",
        date: "2026-03-09 16:30",
        action: "Digital Scanning & Cryptographic Hashing",
        person: "Engr. Daniel L. Torres",
        role: "Lead Inspector / Custodian",
        notes: "Scanned to PDF and generated SHA-256 hash checksum for repository vault.",
      },
      {
        id: "CST-004",
        date: "2026-03-10 09:00",
        action: "Supervisory Review & Verification",
        person: "Atty. Fernando M. Gomez, CPA",
        role: "Supervising Auditor",
        notes: "Verified count sheet signatures and approved for Working Paper WP-2026-003-WH-01.",
      },
    ],

    status: "Accepted",
    version: "v1.0",
    createdAt: "2026-03-09 16:30",
    updatedAt: "2026-03-10 09:00",
  },
  {
    id: "EVD-2026-002",
    title: "Photographic Evidence of Warehouse Bay 2 Storage Shelves & Barcode Tags",
    engagementNumber: "AEMS-2026-010",
    relatedRequestId: "N/A (Direct Fieldwork Acquisition)",

    evidenceType: "Digital Photo / Image",
    auditProcedureMethod: "Observation",
    source: "On-site inspection camera capture (CGSO Central Warehouse Bay 2)",
    sourceOrigin: "Internal",
    acquisitionMode: "Direct (Auditor-Observed/Gathered)",
    physicalForm: "Native Electronic Record",
    custodian: "Engr. Daniel L. Torres (Technical Specialist / Inspector)",
    dateReceived: "2026-03-09",
    fileReference: "WP-2026-003-WH-01/IMG-09-BAY2.jpg",
    fileSize: "4.8 MB",
    fileMimeType: "image/jpeg",

    hashAlgorithm: "SHA-256",
    hashChecksum: "9f83c605d4c82b0907e5c6a1e3b8b0e8c7d6e5f4a3b2c1d0e9f8a7b6c5d4e3f2",

    relatedCriteria:
      "Physical Asset Protection & Storage Standards; COA Circular 2012-001; Risk RMI-2026-003",
    sufficiencyContribution:
      "High — 8 high-resolution digital photographs capturing storage bins, barcoded tags, and physical shelving condition.",
    appropriateness: "High",
    relevance:
      "Corroborates physical existence, condition, and barcoded labeling of medical gloves, PPE, and truck tire racks.",
    reliability: "High — Direct visual evidence recorded live by auditor during walkthrough.",
    accuracyCompletenessProcedures:
      "Metadata and timestamp extraction verified against inspector digital camera log.",
    corroboration:
      "Corroborates physical count sheets (EVD-2026-001).",
    contradictoryEvidence: "None.",
    authenticity: "Original EXIF metadata intact with verified timestamp: 2026-03-09 14:45 PST.",

    confidentiality: "Internal Audit Confidential",
    limitation: "None.",
    accessRestriction: "Engagement Team & Reviewers",
    legalHold: false,
    archivalCondition: "Active Repository",
    assessmentResult:
      "Accepted as corroborative digital photo evidence substantiating warehouse physical controls.",

    relatedArtifacts: [
      {
        id: "ART-01",
        type: "Procedure",
        ref: "PROC-2026-003-01",
        title: "Physical Inventory Sample Count & Stock Card Reconciliation",
      },
      {
        id: "ART-02",
        type: "Working Paper",
        ref: "WP-2026-003-WH-01",
        title: "Warehouse Inventory Physical Count & Reconciliation WP",
      },
      {
        id: "ART-03",
        type: "Fieldwork Record",
        ref: "FWR-2026-001",
        title: "Physical Inspection at CGSO Central Depot Bay 2",
      },
    ],

    custodyLog: [
      {
        id: "CST-001",
        date: "2026-03-09 14:45",
        action: "Digital Photographic Capture",
        person: "Engr. Daniel L. Torres",
        role: "Lead Inspector",
        notes: "Captured 8 digital photos of Bay 2 medical supply and auto spare racks.",
      },
      {
        id: "CST-002",
        date: "2026-03-09 16:45",
        action: "Cryptographic Hashing & Ingestion",
        person: "Engr. Daniel L. Torres",
        role: "Lead Inspector",
        notes: "SHA-256 hash generated and archived in evidence repository.",
      },
    ],

    status: "Accepted",
    version: "v1.0",
    createdAt: "2026-03-09 16:45",
    updatedAt: "2026-03-09 16:45",
  },
  {
    id: "EVD-2026-003",
    title: "Signed RIS (Requisition and Issue Slip) — Motorpool Urgent Issue #04",
    engagementNumber: "AEMS-2026-010",
    relatedRequestId: "N/A (Direct Fieldwork Acquisition)",

    evidenceType: "Scanned Document / Voucher",
    auditProcedureMethod: "Inspection",
    source: "Motorpool Division / Warehouse Custodian Mr. Eduardo Pineda",
    sourceOrigin: "Internal",
    acquisitionMode: "Direct (Auditor-Observed/Gathered)",
    physicalForm: "Scanned Copy",
    custodian: "Engr. Daniel L. Torres (Technical Specialist / Inspector)",
    dateReceived: "2026-03-09",
    fileReference: "WP-2026-003-WH-01/RIS-2026-03-06.pdf",
    fileSize: "1.1 MB",
    fileMimeType: "application/pdf",

    hashAlgorithm: "SHA-256",
    hashChecksum: "4b227777d4dd1fc61c6f884f48641d02b4d121d3fd328cb08b5531fcacdabf8a",

    relatedCriteria:
      "Local Government Code RA 7160 Sec. 375; COA Circular 2012-001; Stock Issuance Control C-04; Risk RMI-2026-003",
    sufficiencyContribution:
      "Moderate — Specifically accounts for the 2-unit variance in Heavy Duty Truck Tires (SKU-AUT-2025-14).",
    appropriateness: "Moderate",
    relevance:
      "Explains and reconciles the difference between physical count (18 units) and stock card ledger (20 units).",
    reliability:
      "Moderate — Carries signatures of receiving driver and storekeeper, but reflects delayed administrative posting to the bin ledger.",
    accuracyCompletenessProcedures:
      "Cross-referenced against Motorpool Emergency Dispatch Vehicle #04 work order log.",
    corroboration:
      "Corroborated by vehicle maintenance repair order #ER-04-2026 dated March 6, 2026.",
    contradictoryEvidence:
      "Warehouse stock card ledger was not updated upon issuance (3-day posting delay).",
    authenticity: "Verified signatures of driver J. Mercado and storekeeper R. Santos.",

    confidentiality: "Internal Audit Confidential",
    limitation:
      "Delayed administrative posting: Issued on March 6, 2026, but not yet posted to stock card ledger at time of March 9 audit spot count. Usable subject to documented limitation.",
    accessRestriction: "Engagement Team & Reviewers",
    legalHold: false,
    archivalCondition: "Active Repository",
    assessmentResult:
      "Accepted with Limitation — Usable to resolve physical count reconciliation; cited in working paper as evidence of delayed stock ledger update controls.",

    relatedArtifacts: [
      {
        id: "ART-01",
        type: "Procedure",
        ref: "PROC-2026-003-01",
        title: "Physical Inventory Sample Count & Stock Card Reconciliation",
      },
      {
        id: "ART-02",
        type: "Working Paper",
        ref: "WP-2026-003-WH-01",
        title: "Warehouse Inventory Physical Count & Reconciliation WP",
      },
      {
        id: "ART-03",
        type: "Fieldwork Record",
        ref: "FWR-2026-001",
        title: "Physical Inspection at CGSO Central Depot Bay 2",
      },
    ],

    custodyLog: [
      {
        id: "CST-001",
        date: "2026-03-09 15:15",
        action: "Retrieved from Warehouse Storekeeper Files",
        person: "Engr. Daniel L. Torres",
        role: "Lead Inspector",
        notes: "Obtained original signed paper RIS from storekeeper desk file.",
      },
      {
        id: "CST-002",
        date: "2026-03-09 16:50",
        action: "Scanned & Indexed into Audit Evidence Vault",
        person: "Engr. Daniel L. Torres",
        role: "Lead Inspector",
        notes: "Uploaded scanned copy with cryptographic SHA-256 seal.",
      },
      {
        id: "CST-003",
        date: "2026-03-10 09:30",
        action: "Evaluative Assessment Signoff (Accepted with Limitation)",
        person: "Atty. Fernando M. Gomez, CPA",
        role: "Supervising Auditor",
        notes: "Designated as Accepted with Limitation due to 3-day posting delay.",
      },
    ],

    status: "Accepted with Limitation",
    version: "v1.0",
    createdAt: "2026-03-09 16:50",
    updatedAt: "2026-03-10 09:30",
  },
  {
    id: "EVD-2026-004",
    title: "PhilGEPS Electronic Posting Receipts Batch 01 (Capital Infrastructure Tenders)",
    engagementNumber: "AEMS-2026-010",
    relatedRequestId: "EVR-2026-001",

    evidenceType: "Electronic File / Extract",
    auditProcedureMethod: "Inspection",
    source: "PhilGEPS Portal Electronic Audit Trail (BAC Posting Terminal)",
    sourceOrigin: "External",
    acquisitionMode: "Direct (Auditor-Observed/Gathered)",
    physicalForm: "Native Electronic Record",
    custodian: "Juan Dela Cruz (Lead Auditor)",
    dateReceived: "2026-03-02",
    fileReference: "WP-2026-001-BID-01/PHILGEPS-RECEIPTS.pdf",
    fileSize: "3.2 MB",
    fileMimeType: "application/pdf",

    hashAlgorithm: "SHA-256",
    hashChecksum: "2c26b46b68ffc68ff99b453c1d30413413422d706483bfa0f98a5e886266e7ae",

    relatedCriteria:
      "RA 9184 Section 21; 2016 Revised IRR Section 21.2; GPPB Res. 03-2020; Risk RMI-2026-002",
    sufficiencyContribution:
      "High — 100% electronic timestamp receipts for 5 major capital infrastructure tenders exceeding PHP 1.0M.",
    appropriateness: "High",
    relevance:
      "Substantiates mandatory 7-calendar-day public advertisement and pre-bid conference intervals.",
    reliability: "High — External cryptographically timestamped electronic government receipt.",
    accuracyCompletenessProcedures:
      "Recalculation of calendar days against City Legal official non-working holiday calendar.",
    corroboration:
      "Corroborated by BAC Minutes and newspaper advertisement tear-sheets.",
    contradictoryEvidence: "None.",
    authenticity: "Verified digital watermark, Document Ref. No., and PhilGEPS electronic seal.",

    confidentiality: "Unclassified / Public",
    limitation: "None.",
    accessRestriction: "Publicly Disclosable",
    legalHold: false,
    archivalCondition: "Active Repository",
    assessmentResult:
      "Accepted for Working Paper WP-2026-001-BID-01; validates statutory bidding advertisement compliance.",

    relatedArtifacts: [
      {
        id: "ART-01",
        type: "Procedure",
        ref: "PROC-2026-001-01",
        title: "PhilGEPS Posting & Mandatory Advertisement Verification",
      },
      {
        id: "ART-02",
        type: "Working Paper",
        ref: "WP-2026-001-BID-01",
        title: "PhilGEPS Public Bidding Advertisement Working Paper",
      },
      {
        id: "ART-03",
        type: "Fieldwork Record",
        ref: "FWR-2026-003",
        title: "Process Walkthrough at MIS / BAC Posting Terminal",
      },
    ],

    custodyLog: [
      {
        id: "CST-001",
        date: "2026-03-02 10:15",
        action: "Direct Electronic Extraction from PhilGEPS Terminal",
        person: "Juan Dela Cruz",
        role: "Lead Auditor",
        notes: "Extracted 5 timestamp certificates during live process walkthrough with BAC posting officer.",
      },
      {
        id: "CST-002",
        date: "2026-03-02 11:45",
        action: "Cryptographic SHA-256 Hashing & Ingestion",
        person: "Juan Dela Cruz",
        role: "Lead Auditor",
        notes: "Archived in SCR-229 evidence repository.",
      },
    ],

    status: "Accepted",
    version: "v1.0",
    createdAt: "2026-03-02 11:45",
    updatedAt: "2026-03-02 11:45",
  },
  {
    id: "EVD-2026-005",
    title: "BAC Secretariat RFQ Canvass Logbook Extract (Q3-Q4 2025 SVP Sample)",
    engagementNumber: "AEMS-2026-010",
    relatedRequestId: "EVR-2026-002",

    evidenceType: "Scanned Document / Voucher",
    auditProcedureMethod: "Inquiry",
    source: "BAC Secretariat Head Ms. Maricor Villanueva",
    sourceOrigin: "Internal",
    acquisitionMode: "Indirect (Provided by Auditee/Third-Party)",
    physicalForm: "Scanned Copy",
    custodian: "Kristine Joy B. Ramos (Internal Auditor II)",
    dateReceived: "2026-03-04",
    fileReference: "WP-2026-002-AMP-01/BAC-RFQ-EXTRACT.pdf",
    fileSize: "1.8 MB",
    fileMimeType: "application/pdf",

    hashAlgorithm: "SHA-256",
    hashChecksum: "1f83c605d4c82b0907e5c6a1e3b8b0e8c7d6e5f4a3b2c1d0e9f8a7b6c5d4e3f9",

    relatedCriteria:
      "RA 9184 IRR Annex H (Small Value Procurement Ceilings); Risk RMI-2026-001",
    sufficiencyContribution:
      "Moderate — Logbook covers 30 sample SVP vouchers, but only 2 quotations were obtained in 8 cases.",
    appropriateness: "Moderate",
    relevance:
      "Evaluates price canvass rotation and minimum supplier quotation compliance.",
    reliability: "Moderate — Manual logbook extract provided by auditee.",
    accuracyCompletenessProcedures:
      "Vouching against Accounting disbursement records and supplier quotation letters.",
    corroboration:
      "Cross-referenced with BAC Chairperson Abstract of Quotations.",
    contradictoryEvidence:
      "8 sample vouchers lacked mandatory 3 quotations citing local market supply limits.",
    authenticity: "Certified extract signed by BAC Secretariat Head.",

    confidentiality: "Internal Audit Confidential",
    limitation:
      "Under active assessment pending third-party supplier quotation rotation confirmation.",
    accessRestriction: "Engagement Team & Reviewers",
    legalHold: false,
    archivalCondition: "Active Repository",
    assessmentResult:
      "Under active review for Working Paper WP-2026-002-AMP-01; potential audit finding in discussion.",

    relatedArtifacts: [
      {
        id: "ART-01",
        type: "Procedure",
        ref: "PROC-2026-002-01",
        title: "Small Value Procurement (SVP) Threshold & Justification Review",
      },
      {
        id: "ART-02",
        type: "Working Paper",
        ref: "WP-2026-002-AMP-01",
        title: "Alternative Methods of Procurement Justification WP",
      },
      {
        id: "ART-03",
        type: "Fieldwork Record",
        ref: "FWR-2026-002",
        title: "Interview / Inquiry at BAC Secretariat Office",
      },
    ],

    custodyLog: [
      {
        id: "CST-001",
        date: "2026-03-04 14:00",
        action: "Received Certified Logbook Extract",
        person: "Kristine Joy B. Ramos",
        role: "Internal Auditor II",
        notes: "Handed over by BAC Secretariat Head Ms. Maricor Villanueva.",
      },
      {
        id: "CST-002",
        date: "2026-03-04 15:30",
        action: "Cryptographic SHA-256 Hashing",
        person: "Kristine Joy B. Ramos",
        role: "Internal Auditor II",
        notes: "Ingested into repository for assessment.",
      },
    ],

    status: "For Assessment",
    version: "v1.0",
    createdAt: "2026-03-04 15:30",
    updatedAt: "2026-03-05 11:20",
  },
];

export const defaultEvidencePackageState: EvidencePackageState = {
  engagementNumber: "AEMS-2026-010",
  engagementTitle: "Procurement Process & Supply Chain Compliance Audit",
  office: "City General Services Office",
  activeEvidenceId: "EVD-2026-003",
  evidenceItems: defaultAuditEvidenceItems,
};
