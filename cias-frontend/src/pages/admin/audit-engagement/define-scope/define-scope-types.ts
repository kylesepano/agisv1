export interface AuditAreaRegistryItem {
  id: string;
  code: string;
  name: string;
  officeName: string;
  category: string;
  description: string;
}

export interface AuditFocusRegistryItem {
  id: string;
  code: string;
  name: string;
  auditAreaId: string;
  auditAreaName: string;
  description: string;
}

export interface DefineScopeFormState {
  engagementNumber: string;
  engagementTitle: string;
  auditType: string;
  originType: "Planned" | "Unplanned";
  iapSourceId: string;
  iapOffice: string;
  office: string;
  isOfficeChangedFromIap: boolean;
  sourceVarianceReason: string;
  selectedAuditAreaIds: string[];
  coverageNotes: Record<string, string>; // areaId -> text
  selectedAuditFocusIds: string[];
  objectives: string;
  boundaries: string;
  exclusions: string;
  knownLimitations: string;
  // Governance read-only tracking
  phase: "Engagement Initiation";
  status: "Draft";
}

export interface ScopeCheckItem {
  label: string;
  completed: boolean;
  required?: boolean;
}
