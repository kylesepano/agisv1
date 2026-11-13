export const assessmentStatusOptions = [
  "All Statuses",
  "Completed",
  "In Progress",
  "Pending",
] as const;

export const assessmentMatrixCriteria = [
  {
    key: "financialImpact",
    label: "Financial Impact",
    description: "Magnitude of financial loss or cost to the organization.",
  },
  {
    key: "complianceRisk",
    label: "Compliance Risk",
    description: "Likelihood of non-compliance with laws, regulations, policies.",
  },
  {
    key: "operationalImpact",
    label: "Operational Impact",
    description: "Effect on operations and service delivery if risk occurs.",
  },
  {
    key: "fraudExposure",
    label: "Fraud Exposure",
    description: "Likelihood of fraud, misappropriation or irregularities.",
  },
  {
    key: "publicInterest",
    label: "Public Interest",
    description: "Impact on stakeholders and public confidence.",
  },
  {
    key: "previousFindings",
    label: "Previous Findings",
    description: "Results of previous audits and unresolved issues.",
  },
  {
    key: "timeSinceLastAudit",
    label: "Time Since Last Audit",
    description: "Length of time since the last audit was conducted.",
  },
] as const;

export const assessmentMatrixDefaultScores: Record<
  (typeof assessmentMatrixCriteria)[number]["key"],
  number
> = {
  financialImpact: 5,
  complianceRisk: 4,
  operationalImpact: 3,
  fraudExposure: 5,
  publicInterest: 4,
  previousFindings: 3,
  timeSinceLastAudit: 4,
};

export const assessmentMatrixScoreGuide = [
  { range: "29 - 35", label: "Critical Risk", tone: "text-rose-600" },
  { range: "21 - 28", label: "High Risk", tone: "text-red-600" },
  { range: "14 - 20", label: "Medium Risk", tone: "text-amber-600" },
  { range: "7 - 13", label: "Low Risk", tone: "text-emerald-600" },
] as const;

export const riskRegisterInfo =
  "Risk scores are calculated by combining the impact and likelihood scores, then mapped against the configured risk levels.";
