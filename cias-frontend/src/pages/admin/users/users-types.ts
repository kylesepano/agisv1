export type UserStatus = "Active" | "Inactive";

export type UserRole =
  | "Administrator"
  | "Compliance Officer"
  | "Senior Auditor"
  | "Audit Team Leader"
  | "Auditor"
  | "Audit Staff"
  | "Viewer"
  | "Data Encoder";

export type UserRecord = {
  id: string;
  name: string;
  title: string;
  username: string;
  email: string;
  role: UserRole;
  department: string;
  status: UserStatus;
  lastLoginDate: string;
  lastLoginTime: string;
};
