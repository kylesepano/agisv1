export type AuditFocusStatus = "Active" | "Inactive";

export type AuditFocusRecord = {
  auditFocusId: string;
  auditAreaId: string;
  auditAreaName: string;
  officeId?: string;
  officeName?: string;
  auditFocusName: string;
  description: string;
  status: AuditFocusStatus;
  createdAt: string;
  updatedAt: string;
  // Backward compatibility aliases
  id?: string;
  code?: string;
  name?: string;
};

export type AuditFocusSummary = {
  totalFocuses: number;
  activeFocuses: number;
  inactiveFocuses: number;
  areasCovered: number;
};
