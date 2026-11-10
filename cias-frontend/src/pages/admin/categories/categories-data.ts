import type { CategoryRecord } from "./categories-types";

export const categoryRecords: CategoryRecord[] = [
  {
    code: "CAT-001",
    name: "Department",
    description: "Organizational offices, departments, and operating units.",
    scope: "Department",
    linkedUnits: 28,
    status: "Active",
  },
  {
    code: "CAT-002",
    name: "Process",
    description: "Operational workflows, procedures, and business processes.",
    scope: "Process",
    linkedUnits: 74,
    status: "Active",
  },
  {
    code: "CAT-003",
    name: "System",
    description:
      "Information systems, applications, databases, and IT platforms.",
    scope: "System",
    linkedUnits: 55,
    status: "Active",
  },
  {
    code: "CAT-004",
    name: "Fund",
    description: "Funds, appropriations, trust funds, and financial resources.",
    scope: "Fund",
    linkedUnits: 14,
    status: "Active",
  },
  {
    code: "CAT-005",
    name: "Program",
    description: "Government programs and service delivery initiatives.",
    scope: "Program",
    linkedUnits: 18,
    status: "Active",
  },
  {
    code: "CAT-006",
    name: "Project",
    description: "Infrastructure, development, and strategic projects.",
    scope: "Project",
    linkedUnits: 10,
    status: "Active",
  },
  {
    code: "CAT-007",
    name: "Asset",
    description:
      "Buildings, equipment, vehicles, facilities, and information assets.",
    scope: "Asset",
    linkedUnits: 22,
    status: "Active",
  },
];
