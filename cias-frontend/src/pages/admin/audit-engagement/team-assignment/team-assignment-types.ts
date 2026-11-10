export type TeamRole = "Lead Auditor" | "Auditor" | "Technical Specialist";

export type AuditorUser = {
  id: string;
  name: string;
  designation: string;
  department: string;
  email: string;
  defaultCompetency?: string;
  certifications?: string[];
};

export type AssignedTeamMember = {
  id: string; // Unique row ID
  userId: string;
  name: string;
  role: TeamRole;
  assignmentStartDate: string;
  assignmentEndDate: string;
  competencySummary: string;
  isAvailable: boolean;
  workloadNote: string;
  hasScheduleConflict: boolean;
  scheduleConflictNote: string;
  supervisorName: string;

  // Independence & Objectivity (§9.1 AEM-FR-008)
  hasConflictOfInterest: boolean;
  disclosedImpairments: string;
  safeguardsDocumented: string;
  hasSystemAccessConfirmed: boolean;
};

export type TeamAssignmentFormState = {
  engagementNumber: string;
  title: string;
  office: string;
  auditType: string;
  auditAreas: string[];
  periodStart: string;
  periodEnd: string;

  // Team Roster
  team: AssignedTeamMember[];

  // Collective Team Sufficiency
  isCollectiveSufficiencyConfirmed: boolean;
  collectiveSufficiencyJustification: string;
};
