import { useMemo, useState } from "react";
import {
  ArrowRight,
  Lock,
} from "lucide-react";
import { Button } from "../../../../components/ui/button";
import { useToast } from "../../../../components/ui/toast";
import { navigateTo } from "../../../../routes";
import {
  EngagementTasksSection,
  ExecutionBlockersSection,
  ExecutionControlsRail,
  ExecutionIdentityBanner,
  ProcedureProgressSection,
  ReviewNotesSection,
  SubWorkspacesGrid,
} from "./components";
import {
  defaultExecutionWorkspaceState,
  defaultProcedureProgressList,
} from "./execution-data";
import { localStore } from "../../../../lib/localStore";
import type {
  EngagementTask,
  ExecutionWorkspaceState,
  ProcedureProgressItem,
  ReviewNote,
} from "./execution-types";

type ExecutionWorkspaceProps = {
  engagementNumber?: string;
  engagementTitle?: string;
  office?: string;
  isGated?: boolean;
};

export function ExecutionWorkspace({
  engagementNumber = "AEMS-2026-010",
  engagementTitle = "Procurement Process & Supply Chain Compliance Audit",
  office = "City General Services Office",
  isGated = false,
}: ExecutionWorkspaceProps) {
  const { showToast } = useToast();
  const isSeeded = engagementNumber === "AEMS-2026-010";

  // Derive procedures from SCR-223 Programs Package
  const programsPkg = useMemo(() => {
    return localStore.getProgramsState(engagementNumber);
  }, [engagementNumber]);

  const derivedProcedures = useMemo<ProcedureProgressItem[]>(() => {
    if (programsPkg?.programs && programsPkg.programs.length > 0) {
      return programsPkg.programs.flatMap((prog) =>
        (prog.procedures || []).map((proc) => ({
          id: proc.id,
          programId: prog.id,
          auditArea: prog.auditArea,
          title: proc.procedureTitle,
          purpose: proc.purpose,
          auditMethod: proc.auditMethod,
          assignedAuditor: proc.personResponsible || "Lead Auditor",
          plannedManDays: proc.plannedManDays || 1,
          actualManDays: 0,
          workingPaperRef:
            proc.workingPaperRef || `WP-${proc.id.replace("PROC-", "")}`,
          dueDate: proc.dueDate || "",
          status: "Pending Execution" as const,
        })),
      );
    }
    return isSeeded ? defaultProcedureProgressList : [];
  }, [programsPkg, isSeeded]);

  // Derive available procedures for task attachment
  const availableProcedures = useMemo(() => {
    return derivedProcedures.map((p) => ({
      id: p.id,
      title: p.title,
    }));
  }, [derivedProcedures]);

  // Derive available records for review notes (procedures, WPs, fieldwork records)
  const availableReviewRecords = useMemo(() => {
    const list: string[] = [];
    derivedProcedures.forEach((p) => {
      list.push(p.id);
      if (p.workingPaperRef && !list.includes(p.workingPaperRef)) {
        list.push(p.workingPaperRef);
      }
    });

    const wpState = localStore.getWorkingPapersState(engagementNumber);
    if (wpState?.workingPapers) {
      wpState.workingPapers.forEach((wp) => {
        if (!list.includes(wp.id)) list.push(wp.id);
      });
    }

    const fwState = localStore.getFieldworkState(engagementNumber);
    if (fwState?.records) {
      fwState.records.forEach((fw) => {
        if (!list.includes(fw.id)) list.push(fw.id);
      });
    }

    const engSuffix = (engagementNumber || "").replace(/^AEMS-/, "") || "2026-020";

    return list.length > 0
      ? list
      : [
          `PROC-${engSuffix}-01-01`,
          `WP-${engSuffix}-OPS-01`,
          `FWR-${engSuffix}-001`,
        ];
  }, [derivedProcedures, engagementNumber]);

  // Derive assigned audit team members for task assignment
  const teamState = useMemo(() => {
    return localStore.getTeamAssignment(engagementNumber);
  }, [engagementNumber]);

  const availableAssignees = useMemo(() => {
    if (teamState?.team && teamState.team.length > 0) {
      return teamState.team.map((t) => t.name);
    }
    return [
      "Juan Dela Cruz (Lead Auditor)",
      "Kristine Joy B. Ramos",
      "Engr. Daniel L. Torres",
    ];
  }, [teamState]);

  const cleanTitle = useMemo(() => {
    if (!engagementTitle || engagementTitle.trim() === "asd" || engagementTitle.trim().length <= 3) {
      return office === "City Agriculture Office"
        ? "Agricultural Assistance & Farmer Input Subsidy Distribution Audit"
        : "Operational Controls & Compliance Audit";
    }
    return engagementTitle;
  }, [engagementTitle, office]);

  const [state, setState] = useState<ExecutionWorkspaceState>(() => {
    const saved = localStore.getExecutionState(engagementNumber);

    if (saved) {
      // Clean up any stale duplicate placeholder tasks referencing non-existent procedures
      let cleanedTasks = saved.tasks;
      if (!isSeeded) {
        cleanedTasks = saved.tasks.filter((t) => {
          const isDuplicateSample =
            t.title ===
            "Sample 15 additional procurement disbursement vouchers" &&
            t.relatedRecord === "PROC-2026-002-01";
          const existsInDerived = derivedProcedures.some(
            (p) => p.id === t.relatedRecord,
          );
          // If no procedures exist yet, or it's the fake sample, strip it
          return existsInDerived && !isDuplicateSample;
        });
      }

      return {
        ...saved,
        engagementTitle: cleanTitle,
        procedures:
          derivedProcedures.length > 0 ? derivedProcedures : saved.procedures,
        tasks: cleanedTasks,
      };
    }

    if (isSeeded) {
      return {
        ...defaultExecutionWorkspaceState,
        engagementNumber,
        engagementTitle: cleanTitle,
        office,
        procedures:
          derivedProcedures.length > 0
            ? derivedProcedures
            : defaultProcedureProgressList,
      };
    }

    return {
      ...defaultExecutionWorkspaceState,
      engagementNumber,
      engagementTitle: cleanTitle,
      office,
      overallProgress: 0,
      phase: "Fieldwork Execution",
      status: "Active",
      tasks: [],
      blockers: [],
      reviewNotes: [],
      procedures: derivedProcedures,
      fieldworkCount: 0,
      workingPaperCount: 0,
      evidenceCount: 0,
    };
  });

  const handleAddTask = (taskData: Omit<EngagementTask, "id">) => {
    const engSuffix = (engagementNumber || "").replace(/^AEMS-/, "") || "2026-020";
    const nextNum = state.tasks.length + 1;
    const newId = `TSK-${engSuffix}-${String(nextNum).padStart(3, "0")}`;
    const newTask: EngagementTask = {
      id: newId,
      ...taskData,
    };

    const updatedState: ExecutionWorkspaceState = {
      ...state,
      tasks: [...state.tasks, newTask],
    };
    setState(updatedState);
    localStore.saveExecutionState(engagementNumber, updatedState);

    showToast({
      title: "Fieldwork Task Scheduled",
      description: `Task ${newId} (${newTask.title}) assigned to ${newTask.assignee}.`,
      tone: "success",
    });
  };

  const handleAddReviewNote = (noteData: Omit<ReviewNote, "id">) => {
    const engSuffix = (engagementNumber || "").replace(/^AEMS-/, "") || "2026-020";
    const nextNum = state.reviewNotes.length + 1;
    const newId = `RN-${engSuffix}-${String(nextNum).padStart(3, "0")}`;
    const newNote: ReviewNote = {
      id: newId,
      ...noteData,
    };

    const updatedState: ExecutionWorkspaceState = {
      ...state,
      reviewNotes: [...state.reviewNotes, newNote],
    };
    setState(updatedState);
    localStore.saveExecutionState(engagementNumber, updatedState);

    showToast({
      title: "Supervisory Review Note Issued",
      description: `Note ${newId} logged for ${newNote.relatedEntity} by ${newNote.reviewer}.`,
      tone: "success",
    });
  };

  const handleRemoveTask = (taskId: string) => {
    const updatedTasks = state.tasks.filter((t) => t.id !== taskId);
    const updatedState: ExecutionWorkspaceState = {
      ...state,
      tasks: updatedTasks,
    };
    setState(updatedState);
    localStore.saveExecutionState(engagementNumber, updatedState);

    showToast({
      title: "Task Removed",
      description: `Task ${taskId} removed from schedule.`,
      tone: "info",
    });
  };

  const handleToggleTaskStatus = (taskId: string) => {
    const nextStatus = (curr: EngagementTask["status"]): EngagementTask["status"] => {
      if (curr === "Pending") return "In Progress";
      if (curr === "In Progress") return "Completed";
      if (curr === "Completed") return "Pending";
      return "In Progress";
    };

    const updatedTasks = state.tasks.map((t) =>
      t.id === taskId ? { ...t, status: nextStatus(t.status) } : t,
    );
    const updatedState: ExecutionWorkspaceState = {
      ...state,
      tasks: updatedTasks,
    };
    setState(updatedState);
    localStore.saveExecutionState(engagementNumber, updatedState);
  };

  const handleReportBlocker = () => {
    showToast({
      title: "Report Blocker / Urgent Matter",
      description:
        "Blocker intake modal opened. No critical impediments currently logged.",
      tone: "info",
    });
  };

  const handleRecordProgress = () => {
    showToast({
      title: "Fieldwork Progress Recorded",
      description: "Updated testing logs and man-day allocations saved.",
      tone: "success",
    });
  };

  // If Gated: Show Phase 4 Gate
  if (isGated) {
    return (
      <div className="rounded-xl border border-amber-200 bg-linear-to-b from-amber-50/80 to-white p-6 shadow-sm space-y-4">
        <div className="flex items-start gap-3.5">
          <div className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-amber-100 text-amber-800 border border-amber-200">
            <Lock className="h-5 w-5" />
          </div>
          <div className="space-y-1">
            <div className="flex items-center gap-2">
              <span className="font-mono text-[10px] font-bold text-amber-800 bg-amber-100 px-1.5 py-0.2 rounded border border-amber-300">
                MDS-200 §10.1 Lifecycle Gate
              </span>
              <h3 className="text-base font-bold text-amber-950">
                Phase 4 Audit Execution is Gated
              </h3>
            </div>
            <p className="text-xs text-amber-900 leading-relaxed max-w-2xl">
              Per MDS-200 §10.1 and §11 (Module Rule 11), <strong>Audit Execution</strong> (Phase 4) becomes active only after Phase 2 (Audit Planning) is finalized and the <strong>Phase 3 Entry Conference</strong> (or authorized commencement waiver) has been formally documented in SCR-225.
            </p>
          </div>
        </div>

        <div className="rounded-lg border border-amber-200/80 bg-white p-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div className="text-xs space-y-0.5">
            <span className="font-bold text-slate-800 block">
              Required Upstream Action to Unlock:
            </span>
            <span className="text-slate-500">
              Conduct and document the Entry Conference in <strong>SCR-225</strong>.
            </span>
          </div>

          <Button
            type="button"
            className="h-9.5 px-4 text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white shadow-sm flex items-center gap-2 transition active:scale-[0.99] cursor-pointer shrink-0"
            onClick={() =>
              navigateTo(
                `/admin/audit-engagement/${engagementNumber}/conference/entry`,
              )
            }
          >
            <span>Go to Entry Conference (SCR-225)</span>
            <ArrowRight className="h-4 w-4" />
          </Button>
        </div>
      </div>
    );
  }

  const computedActualManDays = useMemo(() => {
    return (state.procedures || []).reduce(
      (acc, p) => acc + (p.actualManDays || 0),
      0,
    );
  }, [state.procedures]);

  const computedPlannedManDays = useMemo(() => {
    const sum = (state.procedures || []).reduce(
      (acc, p) => acc + (p.plannedManDays || 0),
      0,
    );
    return sum > 0 ? sum : state.totalPlannedManDays || 45;
  }, [state.procedures, state.totalPlannedManDays]);

  const liveState: ExecutionWorkspaceState = {
    ...state,
    totalActualManDays: computedActualManDays,
    totalPlannedManDays: computedPlannedManDays,
  };

  return (
    <div className="space-y-3">
      {/* 1. Context Banner */}
      <ExecutionIdentityBanner
        engagementNumber={state.engagementNumber}
        engagementTitle={cleanTitle}
        office={state.office}
        fieldworkStartDate={state.fieldworkStartDate}
        fieldworkEndDate={state.fieldworkEndDate}
        totalPlannedManDays={computedPlannedManDays}
        totalActualManDays={computedActualManDays}
      />

      {/* 2-Column Main Form Grid */}
      <div className="grid grid-cols-1 gap-3 lg:grid-cols-12">
        {/* Left Column (8 cols): Controlled Execution Artifacts */}
        <div className="space-y-3 lg:col-span-8">
          {/* 2. Sub-Workspaces Coordinates */}
          <SubWorkspacesGrid engagementNumber={state.engagementNumber} />

          {/* 3. Audit Procedures Execution Progress */}
          <ProcedureProgressSection
            procedures={state.procedures}
            engagementNumber={state.engagementNumber}
          />

          {/* 4. Engagement Tasks Table */}
          <EngagementTasksSection
            tasks={state.tasks}
            availableProcedures={availableProcedures}
            availableAssignees={availableAssignees}
            engagementNumber={state.engagementNumber}
            onAddTask={handleAddTask}
            onRemoveTask={handleRemoveTask}
            onToggleStatus={handleToggleTaskStatus}
          />

          {/* 5. Supervisory Review Notes */}
          <ReviewNotesSection
            reviewNotes={state.reviewNotes}
            availableRecords={availableReviewRecords}
            onAddReviewNote={handleAddReviewNote}
          />

          {/* 6. Execution Blockers / Urgent Matters */}
          <ExecutionBlockersSection
            blockers={state.blockers}
            onReportBlocker={handleReportBlocker}
          />
        </div>

        {/* Right Column (4 cols): Execution Controls & Progress Summary */}
        <div className="space-y-3 lg:col-span-4">
          <div className="sticky top-4">
            <ExecutionControlsRail
              state={liveState}
              onRecordProgress={handleRecordProgress}
              onReportBlocker={handleReportBlocker}
            />
          </div>
        </div>
      </div>
    </div>
  );
}

export default ExecutionWorkspace;
