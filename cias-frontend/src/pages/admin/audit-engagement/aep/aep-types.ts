export type AepStatus = "Draft" | "Under Review" | "Approved" | "Amended";

export type AepMilestone = {
  id: string;
  phaseTitle: string;
  targetStartDate: string;
  targetEndDate: string;
  responsibleLead: string;
  deliverable: string;
};

export type AepKpiMetric = {
  id: string;
  metricName: string;
  targetValue: string;
  measurementMethod: string;
  responsibleRole: string;
};

export type AepFormState = {
  // AEP Identity (§7)
  aepId: string;
  engagementNumber: string;
  engagementTitle: string;
  office: string;
  version: string;
  effectiveDate: string;
  status: AepStatus;
  isAmendment: boolean;
  amendmentReason: string;

  // Objectives & Scope (§7)
  objectives: string;
  refinedScope: string;
  criteriaFramework: string[];

  // Risks & Methodology (§7)
  riskMatrixSummaryRef: string;
  methodology: string;
  evidenceRequirements: string[];

  // Resources (§7)
  assignedTeamRef: string;
  requiredAuditTools: string;

  // Milestones & KPIs (§7)
  milestones: AepMilestone[];
  kpis: AepKpiMetric[];

  // Communication & Approval (§7, §19 OD-001)
  communicationToManagement: string;
  reviewingSupervisor: string;
  approvingAuthority: string;
  approvalDate: string;
};
