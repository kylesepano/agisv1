export type CategoryScope =
  | "Department"
  | "Process"
  | "System"
  | "Fund"
  | "Program"
  | "Project"
  | "Asset";

export type CategoryStatus = "Active" | "Inactive";

export type CategoryRecord = {
  code: string;
  name: string;
  description: string;
  scope: CategoryScope;
  linkedUnits: number;
  status: CategoryStatus;
};
