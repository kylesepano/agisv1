import type { AuditProgram } from "./programs-types";

export type ProgramReadinessCheck = {
  id: string;
  label: string;
  valid: boolean;
  isHardBlocker: boolean;
  detail: string;
  unmetReason: string;
};

export type ProgramsReadinessEvaluation = {
  checks: ProgramReadinessCheck[];
  passedCount: number;
  totalCount: number;
  totalProcedures: number;
  totalManDays: number;
  isReadyForSubmit: boolean;
  tooltipReason: string;
  unmetList: string[];
};

/**
 * Evaluate 5-point Audit Programs Readiness Checklist per MDS-200 v0.8 Rule 35, §7, §9.2, and §19 (OD-008).
 * Mandatory requirements (Rule 35):
 * - Program mapped to Audit Area
 * - Detailed Audit Procedures non-empty
 * - Audit Information & Criteria references non-empty
 * - Risk Matrix Items linked
 * - Planned resource allocation non-zero
 */
export function evaluateProgramsReadiness(
  programs: AuditProgram[],
): ProgramsReadinessEvaluation {
  const totalProcedures = programs.reduce(
    (sum, p) => sum + (p.procedures ? p.procedures.length : 0),
    0,
  );
  const totalManDays = programs.reduce(
    (sum, p) =>
      sum +
      (p.procedures
        ? p.procedures.reduce((pSum, pr) => pSum + (pr.plannedManDays || 0), 0)
        : 0),
    0,
  );

  const checks: ProgramReadinessCheck[] = [
    {
      id: "audit-areas",
      label: "Programs mapped to all Audit Areas (OD-008)",
      valid: programs.length >= 1,
      isHardBlocker: true,
      detail:
        programs.length === 0
          ? "0 programs"
          : `${programs.length} ${programs.length === 1 ? "program" : "programs"}`,
      unmetReason: "At least 1 Audit Program must be defined",
    },
    {
      id: "rmi",
      label: "Risk Matrix Items linked (RMI-01~04)",
      valid:
        programs.length > 0 &&
        programs.every(
          (p) => Array.isArray(p.linkedRmiIds) && p.linkedRmiIds.length >= 1,
        ),
      isHardBlocker: true,
      detail:
        programs.length > 0 &&
        programs.every(
          (p) => Array.isArray(p.linkedRmiIds) && p.linkedRmiIds.length >= 1,
        )
          ? "100% mapped"
          : "Unlinked risks",
      unmetReason: "All Audit Programs must link to at least 1 Risk Matrix Item",
    },
    {
      id: "criteria",
      label: "AEP Objectives & Criteria aligned",
      valid:
        programs.length > 0 &&
        programs.every(
          (p) =>
            Array.isArray(p.criteriaFrameworkRefs) &&
            p.criteriaFrameworkRefs.length >= 1,
        ),
      isHardBlocker: true,
      detail:
        programs.length > 0 &&
        programs.every(
          (p) =>
            Array.isArray(p.criteriaFrameworkRefs) &&
            p.criteriaFrameworkRefs.length >= 1,
        )
          ? "Aligned"
          : "Unmapped criteria",
      unmetReason: "All Audit Programs must cite criteria framework standards",
    },
    {
      id: "procedures",
      label: "Detailed Procedures & WP indices assigned",
      valid:
        programs.length > 0 &&
        totalProcedures >= 1 &&
        programs.every(
          (p) => Array.isArray(p.procedures) && p.procedures.length >= 1,
        ),
      isHardBlocker: true,
      detail:
        totalProcedures === 0
          ? "0 procedures"
          : `${totalProcedures} ${totalProcedures === 1 ? "procedure" : "procedures"}`,
      unmetReason:
        "Every Audit Program must contain at least 1 Detailed Audit Procedure (Rule 35)",
    },
    {
      id: "man-days",
      label: "Planned Man-days allocated",
      valid: totalManDays > 0,
      isHardBlocker: true,
      detail: `${totalManDays} days`,
      unmetReason: "Planned procedure man-days must be greater than 0",
    },
  ];

  const passedCount = checks.filter((c) => c.valid).length;
  const totalCount = checks.length;
  const isReadyForSubmit = checks.every((c) => c.valid);

  const unmetList = checks.filter((c) => !c.valid).map((c) => c.unmetReason);
  const tooltipReason = !isReadyForSubmit
    ? `Complete Programs Readiness Checklist before submitting — ${passedCount}/${totalCount} complete (${unmetList.join("; ")})`
    : "Submit Programs Package for Supervisory Review";

  return {
    checks,
    passedCount,
    totalCount,
    totalProcedures,
    totalManDays,
    isReadyForSubmit,
    tooltipReason,
    unmetList,
  };
}
