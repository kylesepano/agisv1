import type { PlanningPackageState } from "./planning-types";

export const defaultPlanningPackageState: PlanningPackageState = {
  version: "v1.0",
  status: "Draft",
  preparedBy: "Juan Dela Cruz (Lead Auditor)",
  reviewer: "Atty. Fernando M. Gomez, CPA (Supervising Auditor)",
  approver: "Director Manuel C. Tan, CPA, CFE (Department Head)",
  processFlows: [
    {
      stepNumber: 1,
      title: "Purchase Request (PR) & Budget Certification",
      responsibleRole: "Requisitioning Office & City Budget Office",
      description: "End-user office submits approved PR with PPMP alignment and Certificate of Availability of Funds.",
      controlPoint: "Verification of APP line item and pre-encumbrance in budget registry.",
      identifiedRisks: ["Splitting of contracts to bypass threshold", "Unbudgeted procurement"],
    },
    {
      stepNumber: 2,
      title: "BAC Mode Determination & Public Bidding",
      responsibleRole: "Bids & Awards Committee (BAC) Secretariat",
      description: "BAC evaluates procurement mode, publishes Invitation to Bid on PhilGEPS, and conducts Pre-Bid.",
      controlPoint: "PhilGEPS publication compliance (min 7 days) and complete bidding document trails.",
      identifiedRisks: ["Non-competitive bidding specifications", "Shortened posting periods"],
    },
    {
      stepNumber: 3,
      title: "Bid Evaluation & Post-Qualification",
      responsibleRole: "BAC Technical Working Group (TWG)",
      description: "TWG evaluates lowest calculated bid and verifies legal, technical, and financial capability.",
      controlPoint: "Authentication of Mayor's Permit, Tax Clearance, and Single Largest Completed Contract (SLCC).",
      identifiedRisks: ["Inadequate post-qualification verification", "Conflict of interest with bidders"],
    },
    {
      stepNumber: 4,
      title: "Award, Contract & Purchase Order (PO) Issuance",
      responsibleRole: "City Mayor (HOPE) & City General Services Office",
      description: "BAC recommends award via Resolution; HOPE approves Notice of Award, Contract, and NTP.",
      controlPoint: "Posting of Performance Security and statutory approval timelines (RA 9184 Sec 37).",
      identifiedRisks: ["Delayed PO issuance", "Unapproved contract alterations"],
    },
    {
      stepNumber: 5,
      title: "Delivery Inspection, Acceptance (IAR) & Asset Tagging",
      responsibleRole: "City Technical Inspection Unit & CGSO Warehouse",
      description: "Goods received, physically inspected against specifications, and issued Inspection & Acceptance Report.",
      controlPoint: "Dual physical inspection signatures and Property Transfer Reports (PTR).",
      identifiedRisks: ["Substandard item substitution", "Ghost deliveries without physical count"],
    },
  ],
  risks: [
    {
      id: "RMI-2026-001",
      auditFocusArea: "Alternative Methods of Procurement (AMP) Justifications",
      processArea: "AMP Mode Selection & Statutory Thresholds (RA 9184)",
      riskEvent: "Non-adherence to RA 9184 competitive bidding rules and ungrounded resort to Alternative Methods of Procurement (AMP).",
      inherentRisk: "Critical",
      existingControls: "BAC Resolution justifying alternative procurement approved by HOPE.",
      controlEffectiveness: "Partially Effective",
      residualRisk: "High",
      linkedProcedureRef: "PROC-2026-AMP-01 (100% AMP Sample Verification)",
    },
    {
      id: "RMI-2026-002",
      auditFocusArea: "Competitive Public Bidding Document Trail & BAC Minutes",
      processArea: "Public Bidding Specifications & Pre-Procurement Review",
      riskEvent: "Incomplete or tailored technical specifications that favor specific suppliers in BAC bidding documents.",
      inherentRisk: "High",
      existingControls: "TWG pre-procurement conference review and market price validation.",
      controlEffectiveness: "Effective",
      residualRisk: "Medium",
      linkedProcedureRef: "PROC-2026-BID-02 (Technical Specs & Price Matrix Vouching)",
    },
    {
      id: "RMI-2026-003",
      auditFocusArea: "Delivery Inspection & Acceptance Report (IAR) Authentication",
      processArea: "Warehouse Receiving, Inspection & Asset Custody",
      riskEvent: "Delivery of supplies not conforming to contract specs; delayed IAR issuance and lack of warranty retention.",
      inherentRisk: "High",
      existingControls: "Inspection and Acceptance Committee sign-off prior to voucher processing.",
      controlEffectiveness: "Partially Effective",
      residualRisk: "High",
      linkedProcedureRef: "PROC-2026-IAR-03 (Physical Warehouse Sample Inspection)",
    },
    {
      id: "RMI-2026-004",
      auditFocusArea: "Procurement Process Compliance & Bidding Controls",
      processArea: "Purchase Request (PR) Aggregation & PPMP Reconciliation",
      riskEvent: "Splitting of Purchase Requests across adjacent dates to avoid public bidding thresholds.",
      inherentRisk: "Critical",
      existingControls: "Budget encumbrance check against Annual Procurement Plan (APP).",
      controlEffectiveness: "Ineffective",
      residualRisk: "Critical",
      linkedProcedureRef: "PROC-2026-SPLIT-04 (Consecutive PR Trend & Date Sequence Analysis)",
    },
  ],
  aep: {
    aepNumber: "AEP-2026-010-01",
    version: "v1.0",
    auditStrategySummary:
      "Risk-based compliance and operations audit focusing on high-value public bidding contracts, AMP justifications, physical inventory matching, and internal control effectiveness in the City General Services Office.",
    plannedManDays: 45,
    fieldworkStartDate: "2026-02-15",
    fieldworkEndDate: "2026-04-15",
    reportingTargetDate: "2026-04-30",
    keyKpiTarget: "100% verification of contracts > PHP 1M; 30 random sample vouchers of Small Value Procurement.",
  },
  auditPrograms: [
    {
      id: "PROG-01",
      programTitle: "Public Bidding & PhilGEPS Posting Audit Program",
      auditArea: "Procurement Process Compliance & Bidding Controls",
      proceduresCount: 6,
      workingPaperRef: "WP-2026-001-BID",
      samplingMethod: "100% of major infrastructure and capital goods > PHP 1M",
      plannedHours: 120,
      assignedAuditor: "Juan Dela Cruz",
    },
    {
      id: "PROG-02",
      programTitle: "Alternative Methods of Procurement (AMP) Program",
      auditArea: "Procurement Process Compliance & Bidding Controls",
      proceduresCount: 5,
      workingPaperRef: "WP-2026-002-AMP",
      samplingMethod: "Monetary Unit Sampling (30 vouchers across SVP and Shopping)",
      plannedHours: 90,
      assignedAuditor: "Kristine Joy B. Ramos",
    },
    {
      id: "PROG-03",
      programTitle: "Warehouse Delivery & Physical Asset Custody Audit",
      auditArea: "Supply Chain, Warehouse Inventory & Asset Custody",
      proceduresCount: 4,
      workingPaperRef: "WP-2026-003-INV",
      samplingMethod: "Physical count of top 20 high-value stock items + 15 spot samples",
      plannedHours: 75,
      assignedAuditor: "Engr. Daniel L. Torres",
    },
  ],
  entryConference: {
    conferenceId: "CONF-2026-010-ENTRY",
    type: "Entry Conference",
    status: "Documented",
    date: "2026-02-18",
    isConductedOrWaived: true,
  },
};

export const vetVaccineColdChainPlanningPackage: PlanningPackageState = {
  version: "v1.0",
  status: "Draft",
  preparedBy: "Lead Auditor (Veterinary & Health Audit Team)",
  reviewer: "Atty. Fernando M. Gomez, CPA (Supervising Auditor)",
  approver: "Director Manuel C. Tan, CPA, CFE (Department Head)",
  processFlows: [
    {
      stepNumber: 1,
      title: "Vaccine Requisition & Cold-Chain Receiving",
      responsibleRole: "City Veterinary / Health Pharmacy & Cold Chain Custodian",
      description: "Receipt of biological supplies and vaccines with immediate verification of cold-chain packing integrity, transit data loggers, and batch documentation.",
      controlPoint: "Continuous temperature verification (+2°C to +8°C / -20°C) and cold-chain inventory ledger entry.",
      identifiedRisks: ["Temperature excursions during transit", "Spoilage due to delayed cold-storage transfer"],
    },
    {
      stepNumber: 2,
      title: "Cold Room & Biologics Storage Monitoring",
      responsibleRole: "Technical Biologics Officer",
      description: "Storage in primary cold rooms and specialized pharmaceutical refrigerators with 24/7 digital thermal monitoring and backup power safeguards.",
      controlPoint: "Twice-daily calibrated manual log verification and automated SMS temperature alarm failover.",
      identifiedRisks: ["Unmonitored refrigeration unit failure", "Lack of auxiliary generator auto-cutover"],
    },
    {
      stepNumber: 3,
      title: "FEFO (First-Expired, First-Out) Batch Tracking & Stock Rotation",
      responsibleRole: "Veterinary Pharmacist & Inventory Custodian",
      description: "Sequential stock rotation ensuring earliest expiring vaccine lots are staged for immediate dispensation to avoid obsolescence and wastage.",
      controlPoint: "Lot number and expiry date cross-verification against physical cold room stock bins.",
      identifiedRisks: ["Near-expiry vaccine wastage", "Dispensation of expired biological supplies"],
    },
    {
      stepNumber: 4,
      title: "Field Vaccination Cold Box Packing & Dispatch",
      responsibleRole: "Field Veterinary Vaccination Teams",
      description: "Transfer of biologicals into validated mobile vaccine cold boxes with conditioned ice packs and freeze-indicator monitors for barangay outreach.",
      controlPoint: "Conditioned ice pack placement, freeze-indicator tag inspection, and dispatch logbook sign-off.",
      identifiedRisks: ["Direct contact with unconditioned frozen packs", "Thermal degradation in field transit"],
    },
    {
      stepNumber: 5,
      title: "Field Dispensation, Wastage Logging & Return Reconciliation",
      responsibleRole: "Field Team Leader & City Veterinarian",
      description: "Administration of vaccines in field clinics, logging of animal beneficiaries, and same-day return reconciliation of unused and discarded vials.",
      controlPoint: "Daily vial reconciliation: doses administered + unopened vials returned + broken/discarded vials logged.",
      identifiedRisks: ["Unreconciled vial discard", "Discrepancy in beneficiary vaccination records"],
    },
  ],
  risks: [
    {
      id: "RMI-2026-021-001",
      auditFocusArea: "Vaccine Cold Chain Temperature Monitoring & Alert Logs (AF-013)",
      processArea: "Cold Storage & Continuous Temperature Monitoring",
      riskEvent: "Unrecorded temperature fluctuations or power failover gaps leading to biological degradation of rabies and livestock vaccines.",
      inherentRisk: "Critical",
      existingControls: "Digital sensor alerts and backup generator auto-cutover protocol.",
      controlEffectiveness: "Partially Effective",
      residualRisk: "High",
      linkedProcedureRef: "PROC-2026-021-01 (Continuous Sensor & Logbook Audit)",
    },
    {
      id: "RMI-2026-021-002",
      auditFocusArea: "FEFO (First-Expired, First-Out) Dispensation Enforcement (AF-014)",
      processArea: "Inventory Expiry Mitigation & Dispensation Sequencing",
      riskEvent: "Failure to enforce FEFO rotation resulting in expired vaccine batches held in active dispensing refrigerators.",
      inherentRisk: "High",
      existingControls: "Stock card expiry tracking and quarterly physical count reconciliation.",
      controlEffectiveness: "Effective",
      residualRisk: "Medium",
      linkedProcedureRef: "PROC-2026-021-02 (Batch Expiry & Physical Stock Inspection)",
    },
    {
      id: "RMI-2026-021-003",
      auditFocusArea: "Field Vaccination Cold Box Packing & Dispatch (AF-013)",
      processArea: "Cold Box Packing & Transit Cold-Chain Maintenance",
      riskEvent: "Inadequate cold pack conditioning causing freezing damage or heat exposure during barangay field operations.",
      inherentRisk: "High",
      existingControls: "Visual inspection of freeze-watch indicators and ice pack conditioning guidelines.",
      controlEffectiveness: "Partially Effective",
      residualRisk: "High",
      linkedProcedureRef: "PROC-2026-021-03 (Field Cold Box Protocol Verification)",
    },
    {
      id: "RMI-2026-021-004",
      auditFocusArea: "Field Dispensation & Wastage Reconciliation (AF-014)",
      processArea: "Dose Administration & Empty/Unused Vial Accounting",
      riskEvent: "Unaccounted vial wastage or discrepancy between reported animal vaccination totals and inventory drawdowns.",
      inherentRisk: "High",
      existingControls: "Daily field reconciliation sheets signed by supervising veterinarian.",
      controlEffectiveness: "Partially Effective",
      residualRisk: "Medium",
      linkedProcedureRef: "PROC-2026-021-04 (Vial Drawdown vs Animal Register Cross-Check)",
    },
  ],
  aep: {
    aepNumber: "AEP-2026-021-01",
    version: "v1.0",
    auditStrategySummary:
      "Risk-based operational and compliance audit of biological vaccine cold storage facilities, digital temperature monitoring loggers, FEFO dispensation protocols, and field vaccination transit boxes in the City Veterinary Office.",
    plannedManDays: 35,
    fieldworkStartDate: "2026-03-01",
    fieldworkEndDate: "2026-04-30",
    reportingTargetDate: "2026-05-15",
    keyKpiTarget: "100% sensor log review of cold storage units; 100% batch reconciliation of rabies and livestock vaccines.",
  },
  auditPrograms: [
    {
      id: "PROG-01",
      programTitle: "Cold Storage Temperature Monitoring & Data Log Audit",
      auditArea: "Medicine & Vaccine Cold Chain Inventory Controls",
      proceduresCount: 4,
      workingPaperRef: "WP-2026-021-TEMP",
      samplingMethod: "100% electronic temperature log audit for prior 6 months",
      plannedHours: 80,
      assignedAuditor: "Lead Auditor",
    },
    {
      id: "PROG-02",
      programTitle: "FEFO Stock Rotation & Expiry Mitigation Audit Program",
      auditArea: "Medicine & Vaccine Cold Chain Inventory Controls",
      proceduresCount: 4,
      workingPaperRef: "WP-2026-021-FEFO",
      samplingMethod: "Physical sample inspection of 100% active vaccine batches",
      plannedHours: 70,
      assignedAuditor: "Auditor",
    },
  ],
  entryConference: {
    conferenceId: "CONF-2026-021-ENTRY",
    type: "Entry Conference",
    status: "Documented",
    date: "2026-03-02",
    isConductedOrWaived: true,
  },
};

export const agriSubsidyPlanningPackage: PlanningPackageState = {
  version: "v1.0",
  status: "Draft",
  preparedBy: "Lead Auditor (Agriculture Audit Team)",
  reviewer: "Atty. Fernando M. Gomez, CPA (Supervising Auditor)",
  approver: "Director Manuel C. Tan, CPA, CFE (Department Head)",
  processFlows: [
    {
      stepNumber: 1,
      title: "Farmer Beneficiary RSBSA Registry Verification",
      responsibleRole: "City Agriculture Office & Barangay Coordinators",
      description: "Validation of masterlist of registered smallholder farmers against Registry System for Basic Sectors in Agriculture (RSBSA).",
      controlPoint: "Cross-check with national RSBSA database and barangay captain certification.",
      identifiedRisks: ["Ineligible beneficiary inclusion", "Duplicate beneficiary records"],
    },
    {
      stepNumber: 2,
      title: "Agricultural Input Subsidy Allocation & Warehousing",
      responsibleRole: "Agricultural Supplies Warehouse Officer",
      description: "Receipt, quality inspection, and physical staging of certified seeds, fertilizers, and pesticide inputs.",
      controlPoint: "Delivery receipt matching against technical specifications and physical sack count tally.",
      identifiedRisks: ["Substandard seed germination", "Loss or diversion of subsidized fertilizer"],
    },
    {
      stepNumber: 3,
      title: "Barangay Distribution Schedule & Public Posting",
      responsibleRole: "City Agriculture Extension Workers",
      description: "Public notification and posting of approved distribution dates and allocated quantities per barangay cluster.",
      controlPoint: "Public posting compliance on barangay bulletin boards at least 5 days prior.",
      identifiedRisks: ["Unnotified beneficiaries", "Unauthorized collection representatives"],
    },
    {
      stepNumber: 4,
      title: "On-Site Distribution, Biometrics & Farmer Sign-Off",
      responsibleRole: "Distribution Team & CIAS Field Monitors",
      description: "Physical handover of seed bags and fertilizer vouchers to verified farmers with photo documentation and signatures.",
      controlPoint: "Valid government ID presentation, biometric acknowledgment, and distribution sheet sign-off.",
      identifiedRisks: ["Ghost releases without farmer presence", "Unrecorded partial distributions"],
    },
    {
      stepNumber: 5,
      title: "Distribution Sheet Liquidation & Post-Distribution Audit",
      responsibleRole: "City Agriculturist & Accounting Department",
      description: "Liquidation of distribution acknowledgment sheets and return tally of undistributed buffer stocks.",
      controlPoint: "100% voucher-to-signature reconciliation and physical count of remaining warehouse stocks.",
      identifiedRisks: ["Unliquidated distribution sheets", "Discrepancy in remaining buffer inventory"],
    },
  ],
  risks: [
    {
      id: "RMI-2026-020-001",
      auditFocusArea: "Farmer Beneficiary RSBSA Registry Verification (AF-017)",
      processArea: "Beneficiary Qualification & RSBSA Masterlist Screening",
      riskEvent: "Inclusion of non-farmers or duplicate family members in distribution lists resulting in subsidy misallocation.",
      inherentRisk: "Critical",
      existingControls: "Cross-matching with national DA-RSBSA database.",
      controlEffectiveness: "Partially Effective",
      residualRisk: "High",
      linkedProcedureRef: "PROC-2026-020-01-01 (Farmer Beneficiary Registry Cross-Check)",
    },
    {
      id: "RMI-2026-020-002",
      auditFocusArea: "Agricultural Input Subsidy Allocation & Warehousing (AF-018)",
      processArea: "Warehouse Storage & Seed Quality Custody",
      riskEvent: "Improper warehouse storage causing deterioration of seed germination rates or bag weight variances.",
      inherentRisk: "High",
      existingControls: "Periodic warehouse temperature checks and sample moisture testing.",
      controlEffectiveness: "Effective",
      residualRisk: "Medium",
      linkedProcedureRef: "PROC-2026-020-01-02 (Physical Seed & Fertilizer Stock Count)",
    },
  ],
  aep: {
    aepNumber: "AEP-2026-020-01",
    version: "v1.0",
    auditStrategySummary:
      "Operational and compliance audit of agricultural input subsidy distribution, farmer beneficiary RSBSA verification, and warehouse inventory controls in the City Agriculture Office.",
    plannedManDays: 40,
    fieldworkStartDate: "2026-02-20",
    fieldworkEndDate: "2026-04-20",
    reportingTargetDate: "2026-05-10",
    keyKpiTarget: "100% verification of barangay distribution registries; 50 beneficiary spot-interviews.",
  },
  auditPrograms: [
    {
      id: "PROG-01",
      programTitle: "Farmer Beneficiary & Input Subsidy Distribution Audit",
      auditArea: "Agricultural Assistance & Farmer Input Subsidy Distribution",
      proceduresCount: 5,
      workingPaperRef: "WP-2026-020-OPS-01",
      samplingMethod: "Stratified random sampling of 50 farmer recipients across 5 barangays",
      plannedHours: 100,
      assignedAuditor: "Juan Dela Cruz",
    },
  ],
  entryConference: {
    conferenceId: "CONF-2026-020-ENTRY",
    type: "Entry Conference",
    status: "Documented",
    date: "2026-02-22",
    isConductedOrWaived: true,
  },
};

export const drrmCalamityFundPlanningPackage: PlanningPackageState = {
  version: "v1.0",
  status: "Draft",
  preparedBy: "Lead Auditor (DRRM & Calamity Audit Team)",
  reviewer: "Atty. Fernando M. Gomez, CPA (Supervising Auditor)",
  approver: "Director Manuel C. Tan, CPA, CFE (Department Head)",
  processFlows: [
    {
      stepNumber: 1,
      title: "LDRRMF Annual Investment Programming & 30% QRF Allocation",
      responsibleRole: "CDRRMD Planning Unit & City Budget Office",
      description:
        "Preparation and Sanggunian approval of Local Disaster Risk Reduction and Management Fund Investment Plan (LDRRMFIP) allocating 70% Preparedness and 30% Quick Response Fund (QRF).",
      controlPoint:
        "Sangguniang Panlungsod Resolution approving LDRRMFIP and budget registry certification.",
      identifiedRisks: [
        "Unbudgeted QRF reprogramming",
        "Non-compliance with statutory 5% LDRRMF allocation",
      ],
    },
    {
      stepNumber: 2,
      title: "Emergency Calamity Declaration & Quick Response Requisition",
      responsibleRole: "CDRRM Council & Office of the City Mayor",
      description:
        "Formal declaration of State of Calamity or emergency executive directive authorizing immediate drawdown of the 30% QRF for disaster relief operations.",
      controlPoint:
        "Verification of council disaster damage assessment reports and Mayor's emergency disbursement authorization.",
      identifiedRisks: [
        "Drawdown without official Calamity Declaration",
        "Excessive emergency fund advance",
      ],
    },
    {
      stepNumber: 3,
      title: "Emergency Relief Procurement & Central Warehouse Buffer Stocking",
      responsibleRole: "CDRRMD Relief Logistics Division & City Inspection Unit",
      description:
        "Requisition, emergency procurement, and warehouse receiving of food packs, medical kits, tents, and rescue gear.",
      controlPoint:
        "Inspection and Acceptance Reports (IAR) and warehouse stock ledger entries upon delivery.",
      identifiedRisks: [
        "Substandard emergency relief supplies",
        "Delayed staging of emergency buffer stocks",
      ],
    },
    {
      stepNumber: 4,
      title: "Evacuation Staging, Barangay Relief Dispatch & Beneficiary Distribution",
      responsibleRole: "CDRRMD Field Distribution Teams & Barangay Disaster Committees",
      description:
        "Packing, transport, and handover of family relief packs and emergency kits to verified disaster-affected families.",
      controlPoint:
        "Beneficiary masterlist validation, Disaster Assistance Family Access Cards (DAFAC), and signed distribution acknowledgment sheets.",
      identifiedRisks: [
        "Ghost or duplicate relief distribution",
        "Diversion of relief goods from affected evacuees",
      ],
    },
    {
      stepNumber: 5,
      title: "LDRRMF Utilization Liquidation & COA Monthly DRRM Reporting",
      responsibleRole: "CDRRMD Administrative & Finance Unit & City Accounting Office",
      description:
        "Comprehensive voucher liquidation, tally of undistributed buffer stocks, and preparation of the Monthly Report on Sources and Utilization of DRRMF.",
      controlPoint:
        "100% voucher reconciliation and submission of monthly DRRMF reports to COA per Circular 2012-002.",
      identifiedRisks: [
        "Unliquidated cash advances for emergency relief",
        "Unrecorded buffer stock losses or spoilage",
      ],
    },
  ],
  risks: [
    {
      id: "RMI-2026-016-001",
      auditFocusArea: "30% Quick Response Fund (QRF) Immediate Relief Drawdowns (AF-011)",
      processArea: "QRF Emergency Allocation & Calamity Declaration Verification",
      riskEvent:
        "Disbursement of 30% QRF funds without formal Declaration of State of Calamity or in violation of statutory LDRRMF restrictions.",
      inherentRisk: "Critical",
      existingControls: "CDRRM Council damage assessment validation and executive order review.",
      controlEffectiveness: "Partially Effective",
      residualRisk: "High",
      linkedProcedureRef: "PROC-2026-016-01 (QRF Legal Mandate & Calamity Declaration Audit)",
    },
    {
      id: "RMI-2026-016-002",
      auditFocusArea: "Relief Goods Buffer Stock Custody & Expiry Safeguards (AF-012)",
      processArea: "Central Disaster Warehouse Receiving & Inventory Staging",
      riskEvent:
        "Stock spoilage, expired food rations, or inventory shrinkage in CDRRMD central relief warehouse due to lack of climate control and FIFO tracking.",
      inherentRisk: "High",
      existingControls: "Stock card physical tally and warehouse temperature logs.",
      controlEffectiveness: "Effective",
      residualRisk: "Medium",
      linkedProcedureRef: "PROC-2026-016-02 (Central Warehouse Relief Stock Physical Inspection)",
    },
    {
      id: "RMI-2026-016-003",
      auditFocusArea: "Relief Goods Distribution Tracking & Beneficiary DAFAC Verification (AF-012)",
      processArea: "Barangay Relief Dispatch & On-Site Evacuation Distribution",
      riskEvent:
        "Distribution of emergency relief packs without verified DAFAC beneficiary sign-offs or duplicate distribution sheets.",
      inherentRisk: "High",
      existingControls: "DAFAC family card cross-check and on-site distribution tally sheets.",
      controlEffectiveness: "Partially Effective",
      residualRisk: "High",
      linkedProcedureRef: "PROC-2026-016-03 (Evacuation Center Beneficiary DAFAC Vouching)",
    },
    {
      id: "RMI-2026-016-004",
      auditFocusArea: "LDRRMF Fund Liquidation & COA Utilization Reporting (AF-011)",
      processArea: "Financial Liquidation & Statutory DRRM Reporting",
      riskEvent:
        "Failure to submit Monthly Report on Sources and Utilization of DRRMF to COA within prescribed deadlines.",
      inherentRisk: "High",
      existingControls: "Quarterly accounting reconciliation and COA transmittal monitoring.",
      controlEffectiveness: "Effective",
      residualRisk: "Low",
      linkedProcedureRef: "PROC-2026-016-04 (COA DRRMF Monthly Report & Cash Liquidation Audit)",
    },
  ],
  aep: {
    aepNumber: "AEP-2026-016-01",
    version: "v1.0",
    auditStrategySummary:
      "Risk-based operational and compliance audit of LDRRMF 70/30 allocation compliance, 30% QRF emergency drawdowns, central relief warehouse buffer stock inventory, and beneficiary distribution controls in the City Disaster Risk Reduction and Management Department (CDRRMD).",
    plannedManDays: 45,
    fieldworkStartDate: "2026-08-18",
    fieldworkEndDate: "2026-10-30",
    reportingTargetDate: "2026-11-15",
    keyKpiTarget:
      "100% verification of QRF disbursement vouchers; physical count of top 10 disaster relief buffer stock items.",
  },
  auditPrograms: [
    {
      id: "PROG-01",
      programTitle: "LDRRMF Allocation & 30% QRF Drawdown Audit Program",
      auditArea: "LDRRMF Fund Allocation & Statutory Reservations",
      proceduresCount: 5,
      workingPaperRef: "WP-2026-016-QRF",
      samplingMethod: "100% of QRF relief disbursement vouchers > PHP 500k",
      plannedHours: 110,
      assignedAuditor: "Lead Auditor",
    },
    {
      id: "PROG-02",
      programTitle: "Emergency Relief Warehouse & Buffer Stock Inventory Audit",
      auditArea: "Disaster Emergency Asset Readiness & Warehouse Relief Stocks",
      proceduresCount: 4,
      workingPaperRef: "WP-2026-016-STK",
      samplingMethod: "Physical count of all food packs and emergency shelter kits",
      plannedHours: 85,
      assignedAuditor: "Auditor",
    },
  ],
  entryConference: {
    conferenceId: "CONF-2026-016-ENTRY",
    type: "Entry Conference",
    status: "Scheduled",
    date: "2026-08-25",
    isConductedOrWaived: false,
  },
};

export const genericPlanningPackage: PlanningPackageState = {
  version: "v1.0",
  status: "Draft",
  preparedBy: "Lead Auditor",
  reviewer: "Atty. Fernando M. Gomez, CPA (Supervising Auditor)",
  approver: "Director Manuel C. Tan, CPA, CFE (Department Head)",
  processFlows: [
    {
      stepNumber: 1,
      title: "Operational Planning & Mandate Governance",
      responsibleRole: "Auditee Department Leadership",
      description:
        "Review of department operational mandate, Annual Investment Program (AIP), and standard operating procedures.",
      controlPoint: "Approved operational manual and statutory mandate alignment.",
      identifiedRisks: ["Lack of formalized standard procedures", "Unaligned operational priorities"],
    },
    {
      stepNumber: 2,
      title: "Transaction Initiation & Authorization",
      responsibleRole: "Designated Unit Supervisor",
      description:
        "Receipt of service requests, transaction processing, and supervisory pre-approval.",
      controlPoint: "Dual authorization signatures and complete supporting document checklists.",
      identifiedRisks: ["Unauthorized transaction processing", "Missing required supporting attachments"],
    },
    {
      stepNumber: 3,
      title: "Execution, Recordkeeping & Custody Safeguards",
      responsibleRole: "Operations Staff & Custodian",
      description:
        "Execution of operational activity, updating of ledgers/registries, and physical asset or document safekeeping.",
      controlPoint: "Logbook/ledger timestamping and segregation of custodial and recording duties.",
      identifiedRisks: ["Delayed recording in official registry", "Inadequate custody security"],
    },
    {
      stepNumber: 4,
      title: "Monitoring, Reconciliation & Management Review",
      responsibleRole: "Department Head / Quality Officer",
      description:
        "Periodic management reconciliation, performance monitoring, and internal quality checks.",
      controlPoint: "Monthly performance summaries and periodic supervisory reconciliation reports.",
      identifiedRisks: ["Unmonitored operational backlogs", "Undetected record variances"],
    },
  ],
  risks: [
    {
      id: "RMI-GEN-001",
      auditFocusArea: "Operational Governance & Authorization Controls",
      processArea: "Transaction Initiation & Supervisory Authorization",
      riskEvent:
        "Processing of departmental transactions without complete documentary requirements or proper delegated authority.",
      inherentRisk: "High",
      existingControls: "Supervisory checklist review prior to processing.",
      controlEffectiveness: "Partially Effective",
      residualRisk: "Medium",
      linkedProcedureRef: "PROC-GEN-01 (Authorization & Documentation Compliance Audit)",
    },
  ],
  aep: {
    aepNumber: "AEP-GEN-01",
    version: "v1.0",
    auditStrategySummary:
      "Operational and compliance audit evaluating internal controls, procedural compliance, and governance safeguards.",
    plannedManDays: 30,
    fieldworkStartDate: "2026-09-01",
    fieldworkEndDate: "2026-10-15",
    reportingTargetDate: "2026-10-31",
    keyKpiTarget: "Sample testing of 30 operational transactions and compliance checklists.",
  },
  auditPrograms: [
    {
      id: "PROG-01",
      programTitle: "Operational Compliance & Internal Controls Program",
      auditArea: "Operational Governance",
      proceduresCount: 4,
      workingPaperRef: "WP-GEN-01",
      samplingMethod: "Random sample of 30 operational records",
      plannedHours: 60,
      assignedAuditor: "Lead Auditor",
    },
  ],
  entryConference: {
    conferenceId: "CONF-GEN-ENTRY",
    type: "Entry Conference",
    status: "Scheduled",
    date: "",
    isConductedOrWaived: false,
  },
};

export function buildInitialPlanningPackage(
  engagementCode: string,
  office?: string,
  auditArea?: string,
): PlanningPackageState {
  const cleanCode = (engagementCode || "").toUpperCase();
  const cleanOffice = (office || "").toLowerCase();
  const cleanArea = (auditArea || "").toLowerCase();

  // 1. Disaster / DRRM (016 / CDRRMD / Relief / LDRRMF)
  if (
    cleanCode.includes("016") ||
    cleanOffice.includes("disaster") ||
    cleanOffice.includes("cdrrmd") ||
    cleanOffice.includes("drrm") ||
    cleanArea.includes("disaster") ||
    cleanArea.includes("calamity") ||
    cleanArea.includes("relief") ||
    cleanArea.includes("ldrrmf")
  ) {
    return {
      ...drrmCalamityFundPlanningPackage,
      aep: {
        ...drrmCalamityFundPlanningPackage.aep,
        aepNumber: `AEP-${cleanCode.replace("AEMS-", "")}-01`,
      },
      entryConference: {
        ...drrmCalamityFundPlanningPackage.entryConference!,
        conferenceId: `CONF-${cleanCode.replace("AEMS-", "")}-ENTRY`,
        date: "2026-08-25",
        isConductedOrWaived: false,
      },
    };
  }

  // 2. Veterinary / Health Cold Chain (021)
  if (
    cleanCode.includes("021") ||
    cleanOffice.includes("veterinary") ||
    cleanOffice.includes("health") ||
    cleanArea.includes("vaccine") ||
    cleanArea.includes("cold chain")
  ) {
    return {
      ...vetVaccineColdChainPlanningPackage,
      aep: {
        ...vetVaccineColdChainPlanningPackage.aep,
        aepNumber: `AEP-${cleanCode.replace("AEMS-", "")}-01`,
      },
      entryConference: {
        ...vetVaccineColdChainPlanningPackage.entryConference!,
        conferenceId: `CONF-${cleanCode.replace("AEMS-", "")}-ENTRY`,
      },
    };
  }

  // 3. Agriculture Subsidy (020)
  if (
    cleanCode.includes("020") ||
    cleanOffice.includes("agriculture") ||
    cleanArea.includes("subsidy") ||
    cleanArea.includes("farmer")
  ) {
    return {
      ...agriSubsidyPlanningPackage,
      aep: {
        ...agriSubsidyPlanningPackage.aep,
        aepNumber: `AEP-${cleanCode.replace("AEMS-", "")}-01`,
      },
      entryConference: {
        ...agriSubsidyPlanningPackage.entryConference!,
        conferenceId: `CONF-${cleanCode.replace("AEMS-", "")}-ENTRY`,
      },
    };
  }

  // 4. Reference Engagement AEMS-2026-010 (CGSO / Procurement)
  if (cleanCode.includes("010") || cleanOffice.includes("general services") || cleanArea.includes("procurement") || cleanArea.includes("bidding")) {
    return {
      ...defaultPlanningPackageState,
      aep: {
        ...defaultPlanningPackageState.aep,
        aepNumber: `AEP-${cleanCode.replace("AEMS-", "")}-01`,
      },
      entryConference: {
        ...defaultPlanningPackageState.entryConference!,
        conferenceId: `CONF-${cleanCode.replace("AEMS-", "")}-ENTRY`,
      },
    };
  }

  // 5. Generic Fallback for New / Custom Engagements
  return {
    ...genericPlanningPackage,
    aep: {
      ...genericPlanningPackage.aep,
      aepNumber: `AEP-${cleanCode.replace("AEMS-", "")}-01`,
    },
    entryConference: {
      ...genericPlanningPackage.entryConference!,
      conferenceId: `CONF-${cleanCode.replace("AEMS-", "")}-ENTRY`,
      isConductedOrWaived: false,
    },
  };
}
