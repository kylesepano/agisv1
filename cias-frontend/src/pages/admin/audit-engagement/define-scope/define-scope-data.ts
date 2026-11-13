import type {
  AuditAreaRegistryItem,
  AuditFocusRegistryItem,
  DefineScopeFormState,
} from "./define-scope-types";

export const auditAreaRegistry: AuditAreaRegistryItem[] = [
  // City General Services Office (CGSO)
  {
    id: "AA-CGSO-01",
    code: "AA-001",
    name: "Procurement Process Compliance & Bidding Controls",
    officeName: "City General Services Office",
    category: "Compliance & Procurement",
    description: "Review of statutory procurement thresholds, BAC resolutions, and bidding transparency under RA 9184.",
  },
  {
    id: "AA-CGSO-02",
    code: "AA-002",
    name: "Supply Chain, Warehouse Inventory & Asset Custody",
    officeName: "City General Services Office",
    category: "Asset Governance",
    description: "Inspection of physical warehouse inventories, custody receipts (PAR/ICS), and disposal workflows.",
  },
  {
    id: "AA-CGSO-03",
    code: "AA-003",
    name: "Contract Administration & Delivery Acceptance",
    officeName: "City General Services Office",
    category: "Contract Review",
    description: "Verification of technical inspection reports, delivery acceptance timestamps, and supplier warranty tracking.",
  },

  // City Assessment Department
  {
    id: "AA-ASST-01",
    code: "AA-004",
    name: "Real Property Tax Assessment & Tax Mapping",
    officeName: "City Assessment Department",
    category: "Revenue & Valuation",
    description: "Assessment roll validation, tax mapping accuracy, and real property reassessment controls.",
  },
  {
    id: "AA-ASST-02",
    code: "AA-005",
    name: "Notice of Assessment Delivery & Dispute Logging",
    officeName: "City Assessment Department",
    category: "Process & Compliance",
    description: "Review of assessment notice delivery proof, taxpayer dispute timelines, and assessment adjustments.",
  },

  // City Information and Communications Technology Office (CICTO)
  {
    id: "AA-ICTO-01",
    code: "AA-006",
    name: "Integrated Financial Management System (IFMS) Security Controls",
    officeName: "City Information and Communications Technology Office",
    category: "Information Systems",
    description: "User access control matrix, database backup integrity, disaster recovery, and change management.",
  },
  {
    id: "AA-ICTO-02",
    code: "AA-007",
    name: "Network Infrastructure & Cyber Safeguards",
    officeName: "City Information and Communications Technology Office",
    category: "Information Systems",
    description: "Firewall rule auditing, endpoint security enforcement, and server room physical security.",
  },

  // City Disaster Risk Reduction and Management Department (CDRRMD)
  {
    id: "AA-DRRM-01",
    code: "AA-008",
    name: "LDRRMF Fund Allocation & Utilization Monitoring",
    officeName: "City Disaster Risk Reduction and Management Department",
    category: "Financial & Statutory",
    description: "Compliance with 70% preparedness and 30% Quick Response Fund statutory allocation and drawdowns.",
  },
  {
    id: "AA-DRRM-02",
    code: "AA-009",
    name: "Disaster Emergency Asset Readiness & Warehouse Relief Stocks",
    officeName: "City Disaster Risk Reduction and Management Department",
    category: "Asset & Logistics",
    description: "Physical verification of rescue boats, emergency vehicles, generators, and relief food stock shelf-life.",
  },

  // City Health Department (CHO)
  {
    id: "AA-HLTH-01",
    code: "AA-010",
    name: "Medicine & Vaccine Cold Chain Inventory Controls",
    officeName: "City Health Department",
    category: "Asset & Clinical Supplies",
    description: "Cold-chain temperature loggers, stock replenishment, expiry mitigation, and pharmacy disbursements.",
  },
  {
    id: "AA-HLTH-02",
    code: "AA-011",
    name: "Barangay Health Center Supply Distribution & Accountability",
    officeName: "City Health Department",
    category: "Operations & Logistics",
    description: "Requisition workflows and proof of receipt across peripheral barangay health stations.",
  },

  // City Veterinary Office (CVO)
  {
    id: "AA-VET-01",
    code: "AA-019",
    name: "Veterinary Medicine & Biologics Cold Chain Controls",
    officeName: "City Veterinary Office",
    category: "Asset & Clinical Supplies",
    description: "Cold-chain temperature loggers, veterinary vaccine replenishment, expiry mitigation, and pharmacy disbursements.",
  },

  // City Treasurer's Office (CTO)
  {
    id: "AA-CTO-01",
    code: "AA-012",
    name: "Cash and Revenue Collection Management",
    officeName: "City Treasurer's Office",
    category: "Treasury Operations",
    description: "Daily cash collections, accountable forms (AF51/AF56), electronic payment gateway reconciliation.",
  },
  {
    id: "AA-CTO-02",
    code: "AA-013",
    name: "Disbursement Operations & Check Releasing Controls",
    officeName: "City Treasurer's Office",
    category: "Treasury Operations",
    description: "Review of check preparation, Advice of Checks Issued and Cancelled (ACIC), and petty cash custodianship.",
  },

  // Business Permits and Licensing Division (BPLD)
  {
    id: "AA-BPLD-01",
    code: "AA-014",
    name: "Business Tax Assessment & One-Stop-Shop Processing",
    officeName: "Business Permits and Licensing Division",
    category: "Process & Compliance",
    description: "Assessment of gross sales declarations, regulatory clearance verification, and eBPLS turnaround time.",
  },

  // City Engineer's Office (CEO)
  {
    id: "AA-CEO-01",
    code: "AA-015",
    name: "Infrastructure Projects Implementation & Quality Validation",
    officeName: "City Engineer's Office",
    category: "Operations & Civil Works",
    description: "Physical project inspections against Bill of Quantities (BOQ), material testing, and variation order approvals.",
  },

  // City Accounting Department
  {
    id: "AA-ACCT-01",
    code: "AA-016",
    name: "Financial Reporting & General Ledger Reconciliation",
    officeName: "City Accounting Department",
    category: "Financial Compliance",
    description: "Trial balance adjustments, bank reconciliation statements, and COA annual financial report compliance.",
  },

  // City Legal Department
  {
    id: "AA-LEGL-01",
    code: "AA-017",
    name: "Contract Review & Legal Compliance Operations",
    officeName: "City Legal Department",
    category: "Legal & Governance",
    description: "Turnaround times for LGU contract reviews, legal opinions, and litigation tracking.",
  },

  // City Agriculture Office
  {
    id: "AA-AGRI-01",
    code: "AA-018",
    name: "Agricultural Assistance & Farmer Input Subsidy Distribution",
    officeName: "City Agriculture Office",
    category: "Program Operations",
    description: "Beneficiary validation, seeds and fertilizer distribution registries, and farm equipment loan monitoring.",
  },
];

export const auditFocusRegistry: AuditFocusRegistryItem[] = [
  // Under AA-CGSO-01 (Procurement Compliance)
  {
    id: "AF-CGSO-01-A",
    code: "AF-001",
    name: "Competitive Public Bidding Document Trail & BAC Minutes",
    auditAreaId: "AA-CGSO-01",
    auditAreaName: "Procurement Process Compliance & Bidding Controls",
    description: "Completeness of bidding checklists, PhilGEPS postings, and eligibility evaluations.",
  },
  {
    id: "AF-CGSO-01-B",
    code: "AF-002",
    name: "Alternative Methods of Procurement (AMP) Justifications",
    auditAreaId: "AA-CGSO-01",
    auditAreaName: "Procurement Process Compliance & Bidding Controls",
    description: "Statutory justification for shopping, emergency procurement, and direct contracting.",
  },
  {
    id: "AF-CGSO-01-C",
    code: "AF-003",
    name: "Purchase Request (PR) to Purchase Order (PO) Lead Time",
    auditAreaId: "AA-CGSO-01",
    auditAreaName: "Procurement Process Compliance & Bidding Controls",
    description: "Efficiency benchmarking of procurement turnaround intervals against citizen charter standards.",
  },

  // Under AA-CGSO-02 (Supply & Warehouse)
  {
    id: "AF-CGSO-02-A",
    code: "AF-004",
    name: "Physical Stock Count vs General Ledger Reconciliation",
    auditAreaId: "AA-CGSO-02",
    auditAreaName: "Supply Chain, Warehouse Inventory & Asset Custody",
    description: "Variance analysis between stock cards, physical warehouse tally, and accounting ledger balance.",
  },
  {
    id: "AF-CGSO-02-B",
    code: "AF-005",
    name: "Property Acknowledgement Receipt (PAR) Updates & Unserviceable Assets",
    auditAreaId: "AA-CGSO-02",
    auditAreaName: "Supply Chain, Warehouse Inventory & Asset Custody",
    description: "Custodianship transfer logging and inventory disposal committee (IDC) condemnation trails.",
  },

  // Under AA-CGSO-03 (Contract & Delivery)
  {
    id: "AF-CGSO-03-A",
    code: "AF-006",
    name: "Delivery Inspection & Acceptance Report (IAR) Authentication",
    auditAreaId: "AA-CGSO-03",
    auditAreaName: "Contract Administration & Delivery Acceptance",
    description: "Verification of actual item specs against approved technical terms of reference.",
  },

  // Under AA-ASST-01 (Real Property Tax Assessment)
  {
    id: "AF-ASST-01-A",
    code: "AF-007",
    name: "Classification & Assessment Level Computation Accuracy",
    auditAreaId: "AA-ASST-01",
    auditAreaName: "Real Property Tax Assessment & Tax Mapping",
    description: "Verification of statutory assessment levels applied to residential, commercial, and industrial parcels.",
  },
  {
    id: "AF-ASST-01-B",
    code: "AF-008",
    name: "GIS Tax Mapping Discrepancy & Unassessed Improvements",
    auditAreaId: "AA-ASST-01",
    auditAreaName: "Real Property Tax Assessment & Tax Mapping",
    description: "Cross-comparison between spatial satellite mapping and filed field appraisal assessment sheets.",
  },

  // Under AA-ICTO-01 (IFMS IT Security)
  {
    id: "AF-ICTO-01-A",
    code: "AF-009",
    name: "Privileged Database Access & Administrator Separation of Duties",
    auditAreaId: "AA-ICTO-01",
    auditAreaName: "Integrated Financial Management System (IFMS) Security Controls",
    description: "DBA access logging, multi-factor authentication, and inactive user deprovisioning.",
  },
  {
    id: "AF-ICTO-01-B",
    code: "AF-010",
    name: "Disaster Recovery Replication & Offsite Backup Restorations",
    auditAreaId: "AA-ICTO-01",
    auditAreaName: "Integrated Financial Management System (IFMS) Security Controls",
    description: "Review of backup frequency, test restoration drill logs, and immutable storage controls.",
  },

  // Under AA-DRRM-01 (LDRRMF Fund)
  {
    id: "AF-DRRM-01-A",
    code: "AF-011",
    name: "Quick Response Fund (QRF 30%) Declaration & Cash Drawdowns",
    auditAreaId: "AA-DRRM-01",
    auditAreaName: "LDRRMF Fund Allocation & Utilization Monitoring",
    description: "State of Calamity declaration resolutions and disbursement voucher supporting documents.",
  },
  {
    id: "AF-DRRM-01-B",
    code: "AF-012",
    name: "Pre-Disaster Preparedness (70%) Program Execution",
    auditAreaId: "AA-DRRM-01",
    auditAreaName: "LDRRMF Fund Allocation & Utilization Monitoring",
    description: "Implementation of early warning systems, drainage clearing contracts, and training expenditures.",
  },

  // Under AA-HLTH-01 (Medicine Cold Chain)
  {
    id: "AF-HLTH-01-A",
    code: "AF-013",
    name: "Vaccine Cold Chain Temperature Monitoring & Alert Logs",
    auditAreaId: "AA-HLTH-01",
    auditAreaName: "Medicine & Vaccine Cold Chain Inventory Controls",
    description: "Continuous digital temperature sensor logs, backup generator failovers, and spoilage reports.",
  },
  {
    id: "AF-HLTH-01-B",
    code: "AF-014",
    name: "FEFO (First-Expired, First-Out) Dispensation Enforcement",
    auditAreaId: "AA-HLTH-01",
    auditAreaName: "Medicine & Vaccine Cold Chain Inventory Controls",
    description: "Inventory batch expiry tracking to prevent wastage and near-expiry medicine distributions.",
  },

  // Under AA-VET-01 (Veterinary Medicine & Biologics Cold Chain)
  {
    id: "AF-VET-01-A",
    code: "AF-022",
    name: "Veterinary Vaccine Cold Chain Temperature Monitoring & Alert Logs",
    auditAreaId: "AA-VET-01",
    auditAreaName: "Veterinary Medicine & Biologics Cold Chain Controls",
    description: "Continuous digital temperature sensor logs, backup generator failovers, and veterinary biologics spoilage reports.",
  },
  {
    id: "AF-VET-01-B",
    code: "AF-023",
    name: "Veterinary FEFO (First-Expired, First-Out) Dispensation Enforcement",
    auditAreaId: "AA-VET-01",
    auditAreaName: "Veterinary Medicine & Biologics Cold Chain Controls",
    description: "Inventory batch expiry tracking to prevent wastage and near-expiry veterinary medicine distributions.",
  },

  // Under AA-CTO-01 (Treasurer Cash)
  {
    id: "AF-CTO-01-A",
    code: "AF-015",
    name: "Daily Cashier Remittance & Bank Deposit Slip Verification",
    auditAreaId: "AA-CTO-01",
    auditAreaName: "Cash and Revenue Collection Management",
    description: "Same-day/next-banking-day deposit compliance and Report of Collections and Deposits (RCD).",
  },
  {
    id: "AF-CTO-01-B",
    code: "AF-016",
    name: "Online Payment Gateway Settlement Reconciliation",
    auditAreaId: "AA-CTO-01",
    auditAreaName: "Cash and Revenue Collection Management",
    description: "Matching digital merchant collections against depository bank statements and municipal ledgers.",
  },

  // Under AA-BPLD-01 (BPLD Business Permits)
  {
    id: "AF-BPLD-01-A",
    code: "AF-017",
    name: "Gross Sales Declaration Verification & Tax Audit Assessments",
    auditAreaId: "AA-BPLD-01",
    auditAreaName: "Business Tax Assessment & One-Stop-Shop Processing",
    description: "Cross-matching declared gross receipts against BIR alpha-lists and financial statement submissions.",
  },

  // Under AA-CEO-01 (Engineering Infrastructure)
  {
    id: "AF-CEO-01-A",
    code: "AF-018",
    name: "Bill of Quantities (BOQ) vs Actual Field Workmanship",
    auditAreaId: "AA-CEO-01",
    auditAreaName: "Infrastructure Projects Implementation & Quality Validation",
    description: "On-site core sampling, asphalt thickness testing, and concrete compressive strength results.",
  },

  // Under AA-ACCT-01 (Accounting General Ledger)
  {
    id: "AF-ACCT-01-A",
    code: "AF-019",
    name: "Bank Reconciliation Statement (BRS) Outstanding Reconciling Items",
    auditAreaId: "AA-ACCT-01",
    auditAreaName: "Financial Reporting & General Ledger Reconciliation",
    description: "Aging and clearance of long-outstanding book and bank reconciling items across all fund accounts.",
  },

  // Under AA-LEGL-01 (Legal Contracts)
  {
    id: "AF-LEGL-01-A",
    code: "AF-020",
    name: "Contract Review Turnaround & Mandatory Statutory Clauses",
    auditAreaId: "AA-LEGL-01",
    auditAreaName: "Contract Review & Legal Compliance Operations",
    description: "Timeliness of legal contract vetting and inclusion of anti-graft, liquidated damages, and arbitration clauses.",
  },

  // Under AA-AGRI-01 (Agriculture Subsidies)
  {
    id: "AF-AGRI-01-A",
    code: "AF-021",
    name: "Registry System for Basic Sectors in Agriculture (RSBSA) Validation",
    auditAreaId: "AA-AGRI-01",
    auditAreaName: "Agricultural Assistance & Farmer Input Subsidy Distribution",
    description: "Cross-validation of beneficiary farmer credentials, land ownership proof, and distribution signatures.",
  },
];

export const defaultScopeFormState: DefineScopeFormState = {
  engagementNumber: "AEMS-2026-010",
  engagementTitle: "Procurement Process & Supply Chain Compliance Audit",
  auditType: "Compliance Audit",
  originType: "Planned",
  iapSourceId: "IAP-2026-001",
  iapOffice: "City General Services Office",
  office: "City General Services Office",
  isOfficeChangedFromIap: false,
  sourceVarianceReason: "",
  selectedAuditAreaIds: ["AA-CGSO-01"],
  coverageNotes: {
    "AA-CGSO-01": "Covers all public bidding and alternative procurement transactions processed for FY 2025.",
  },
  selectedAuditFocusIds: ["AF-CGSO-01-A", "AF-CGSO-01-B"],
  objectives:
    "Evaluate statutory compliance with RA 9184 and local procurement threshold controls, ensuring transparency, competitive bidding, and value for government funds.",
  boundaries:
    "Includes all procurement transactions approved by the Bids and Awards Committee (BAC) and processed through the City General Services Office between January 1, 2025 and December 31, 2025.",
  exclusions:
    "Excludes barangay-level local procurement under separate barangay council jurisdiction and direct petty cash emergency purchases below PHP 10,000.",
  knownLimitations:
    "Physical warehouse inventory records prior to Q2 2025 are partially archived in hard-copy ledgers pending complete digitalization into the e-procurement archive.",
  phase: "Engagement Initiation",
  status: "Draft",
};
