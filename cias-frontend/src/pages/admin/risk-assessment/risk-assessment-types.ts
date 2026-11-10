export type AssessmentStatus = "Completed" | "In Progress" | "Pending";

export type RiskAssessmentRating = "Low" | "Medium" | "High" | "Critical";

export type RiskRegisterCategory =
  | "Process"
  | "Fund"
  | "System"
  | "Asset"
  | "Department"
  | "Program"
  | "Project";

export type AssessmentCriterionKey = "impact" | "likelihood";

export type AssessmentMatrixCriteriaKey =
  | "financialImpact"
  | "complianceRisk"
  | "operationalImpact"
  | "fraudExposure"
  | "publicInterest"
  | "previousFindings"
  | "timeSinceLastAudit";

export type AssessmentMatrixValues = {
  auditableUnit: string;
  office: string;
  assessor: string;
  assessmentDate: string;
  assessmentYear: string;
  remarks: string;
};

export type AssessmentMatrixScores = Record<AssessmentMatrixCriteriaKey, number>;

export type AssessmentSummaryMetric = {
  label: string;
  value: string;
  subtitle: string;
};

export type RiskRegisterRecord = {
  id: string;
  auditCode: string;
  auditableUnit: string;
  category: RiskRegisterCategory;
  office: string;
  assessor: string;
  assessmentDate: string;
  impact: number;
  likelihood: number;
  score: number;
  rating: RiskAssessmentRating;
  status: AssessmentStatus;
  remarks: string;
};
