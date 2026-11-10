import type { RiskLevelRecord } from "./risk-management-types";

export const riskLevelRecords: RiskLevelRecord[] = [
  {
    code: "RL-001",
    name: "Low",
    description: "Low risk with minimal impact on operations and objectives.",
    scoreRange: "1 - 4",
    riskWeight: 1,
    status: "Active",
  },
  {
    code: "RL-002",
    name: "Medium",
    description: "Moderate risk with some impact on operations and objectives.",
    scoreRange: "5 - 8",
    riskWeight: 2,
    status: "Active",
  },
  {
    code: "RL-003",
    name: "High",
    description:
      "High risk with significant impact on operations and objectives.",
    scoreRange: "10 - 15",
    riskWeight: 3,
    status: "Active",
  },
  {
    code: "RL-004",
    name: "Critical",
    description: "Critical risk with severe impact on operations and objectives.",
    scoreRange: "16 - 20",
    riskWeight: 4,
    status: "Active",
  },
];
