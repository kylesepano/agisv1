export type AuditUniverseCategory =
  | "Department"
  | "Process"
  | "System"
  | "Fund"
  | "Program"
  | "Project"
  | "Asset";

export type RiskLevel = "High" | "Medium" | "Low";

export type RecordStatus = "Active" | "Inactive";

export type AuditUniverseRecord = {
  code: string;
  name: string;
  category: AuditUniverseCategory;

  department: string;
  businessOwner: string;
  technicalOwner: string;

  riskLevel: RiskLevel;

  lastAudit: string;
  nextAudit: string;

  status: RecordStatus;

  description: string;
  remarks: string;

  createdBy: string;
  createdAt: string;
  updatedAt: string;
};
