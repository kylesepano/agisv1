export type OfficeStatus = "Active" | "Inactive";

export type OfficeCategory =
  | "Executive Office"
  | "Line Department"
  | "Support Office"
  | "Special Body"
  | "Statutory Board"
  | "Enterprise / Facility";

export type OfficeRecord = {
  officeId: string;
  officeName: string;
  officeAcronym: string;
  officeCategory: OfficeCategory;
  status: OfficeStatus;
  createdAt: string;
  updatedAt: string;
  // Optional detailed fields
  head?: string;
  designation?: string;
  contactEmail?: string;
  description?: string;
  linkedUnits?: number;
  users?: number;
  // Backward-compatibility aliases
  code?: string;
  name?: string;
  shortName?: string;
};

export type OfficeSummary = {
  totalOffices: number;
  activeOffices: number;
  inactiveOffices: number;
  categoriesCount: number;
};
