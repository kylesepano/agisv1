export type AuditAreaStatus = "Active" | "Inactive";

export type AuditAreaCategory =
  | "Compliance & Procurement"
  | "Asset Governance"
  | "Financial & Statutory"
  | "Revenue & Valuation"
  | "Process & Compliance"
  | "Information Systems"
  | "Asset & Logistics"
  | "Asset & Clinical Supplies"
  | "Operations & Logistics"
  | "Treasury Operations"
  | "Operations & Civil Works"
  | "Financial Compliance"
  | "Legal & Governance"
  | "Program Operations"
  | "General Administration"
  | "Environmental Governance"
  | "Social Welfare & Protection"
  | "Public Health & Safety"
  | "Special Program Oversight";

export type AuditAreaRecord = {
  auditAreaId: string;
  officeId: string;
  officeName: string;
  auditAreaName: string;
  auditAreaCategory: AuditAreaCategory;
  description: string;
  status: AuditAreaStatus;
  createdAt: string;
  updatedAt: string;
  // Backward compatibility aliases
  id?: string;
  code?: string;
  name?: string;
  category?: string;
};

export type AuditAreaSummary = {
  totalAreas: number;
  activeAreas: number;
  inactiveAreas: number;
  officesCovered: number;
};
