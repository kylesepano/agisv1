export type RiskLevelStatus = "Active" | "Inactive";

export type RiskLevelTone = "Low" | "Medium" | "High" | "Critical";

export type RiskLevelRecord = {
  code: string;
  name: RiskLevelTone;
  description: string;
  scoreRange: string;
  riskWeight: number;
  status: RiskLevelStatus;
};
