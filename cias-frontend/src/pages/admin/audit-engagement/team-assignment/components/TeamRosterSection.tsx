import {
  AlertTriangle,
  Calendar,
  CheckCircle2,
  Crown,
  Info,
  Plus,
  Trash2,
  UserCheck,
  Users,
  XCircle,
} from "lucide-react";
import { Button } from "../../../../../components/ui/button";
import { auditorRegistry, supervisorOptions } from "../team-assignment-data";
import type { AssignedTeamMember, TeamRole } from "../team-assignment-types";

type TeamRosterSectionProps = {
  team: AssignedTeamMember[];
  periodStart: string;
  periodEnd: string;
  isReadOnly?: boolean;
  onUpdateMember: (id: string, updates: Partial<AssignedTeamMember>) => void;
  onAddMember: () => void;
  onRemoveMember: (id: string) => void;
};

export function TeamRosterSection({
  team,
  periodStart,
  periodEnd,
  isReadOnly = false,
  onUpdateMember,
  onAddMember,
  onRemoveMember,
}: TeamRosterSectionProps) {
  const leadCount = team.filter((m) => m.role === "Lead Auditor").length;

  const handleAuditorSelect = (rowId: string, selectedUserId: string) => {
    if (isReadOnly) return;
    const selectedUser = auditorRegistry.find((u) => u.id === selectedUserId);
    if (!selectedUser) {
      onUpdateMember(rowId, {
        userId: "",
        name: "",
      });
      return;
    }

    onUpdateMember(rowId, {
      userId: selectedUser.id,
      name: selectedUser.name,
    });
  };

  return (
    <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-3">
      {/* Header with Lead Auditor Governance Alert */}
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2">
        <div className="flex items-center gap-2">
          <Users className="h-4 w-4 text-blue-600" />
          <h3 className="text-[13px] font-bold text-slate-800">
            Audit Team Roster & Competency Allocation (§9.1 AEM-FR-008)
          </h3>
          <span className="rounded bg-slate-100 px-1.5 py-0.2 text-[10px] font-bold text-slate-600">
            {team.length} {team.length === 1 ? "Member" : "Members"}
          </span>
        </div>

        <div className="flex items-center gap-2">
          {leadCount === 1 ? (
            <div className="flex items-center gap-1 rounded bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 border border-emerald-200">
              <CheckCircle2 size={11} />
              <span>1 Lead Auditor Designated</span>
            </div>
          ) : (
            <div className="flex items-center gap-1 rounded bg-rose-50 px-2 py-0.5 text-[10px] font-bold text-rose-700 border border-rose-200">
              <AlertTriangle size={11} />
              <span>
                {leadCount === 0
                  ? "Requires Exactly 1 Lead Auditor"
                  : `${leadCount} Lead Auditors (Only 1 Allowed)`}
              </span>
            </div>
          )}

          {!isReadOnly && (
            <Button
              type="button"
              variant="outline"
              onClick={onAddMember}
              className="h-7 text-xs px-2.5 flex items-center gap-1 text-blue-600 hover:text-blue-700 cursor-pointer"
            >
              <Plus size={12} />
              <span>Add Member</span>
            </Button>
          )}
        </div>
      </div>

      {/* Governance Notice */}
      <div className="flex items-center gap-1.5 rounded-md border border-amber-200 bg-amber-50/60 px-2.5 py-1.5 text-[11px] text-amber-900">
        <Info size={13} className="text-amber-600 shrink-0" />
        <span>
          <strong>Provisional Manual Entry (§19 OD-007):</strong> Document each member's specific competency (min. 20 chars), availability, and verify dates align with the engagement window (<strong>{periodStart || "YYYY-MM-DD"}</strong> to <strong>{periodEnd || "YYYY-MM-DD"}</strong>).
        </span>
      </div>

      {/* Repeatable Member Cards */}
      <div className="space-y-3">
        {team.map((member, index) => {
          const isLead = member.role === "Lead Auditor";
          const compLen = (member.competencySummary || "").trim().length;
          const isCompValid = compLen >= 20;

          // Date window validation
          const isStartOutOfRange =
            periodStart &&
            member.assignmentStartDate &&
            member.assignmentStartDate < periodStart;
          const isEndOutOfRange =
            periodEnd &&
            member.assignmentEndDate &&
            member.assignmentEndDate > periodEnd;
          const isEndBeforeStart =
            member.assignmentStartDate &&
            member.assignmentEndDate &&
            member.assignmentEndDate < member.assignmentStartDate;
          const isDateInvalid =
            isStartOutOfRange || isEndOutOfRange || isEndBeforeStart || !member.assignmentStartDate || !member.assignmentEndDate;

          return (
            <div
              key={member.id}
              className={`rounded-lg border p-3 space-y-3 transition ${
                isLead
                  ? "border-blue-200 bg-blue-50/20"
                  : "border-slate-200 bg-white"
              }`}
            >
              {/* Row Header: Member Index, User Select, Role, and Remove */}
              <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2">
                <div className="flex items-center gap-2 flex-1 min-w-[280px]">
                  <span className="grid h-6 w-6 shrink-0 place-items-center rounded bg-slate-100 text-[11px] font-bold text-slate-600 font-mono">
                    #{index + 1}
                  </span>

                  {/* Auditor Registry Selector */}
                  <div className="flex-1 min-w-[200px]">
                    <select
                      value={member.userId}
                      disabled={isReadOnly}
                      onChange={(e) => handleAuditorSelect(member.id, e.target.value)}
                      className={`h-8 w-full rounded border px-2 text-xs font-semibold focus:outline-hidden disabled:bg-slate-100 disabled:text-slate-700 disabled:cursor-not-allowed ${
                        !member.userId && !isReadOnly
                          ? "border-rose-300 bg-rose-50/30 text-rose-800"
                          : "border-slate-200 bg-white text-slate-800 focus:border-blue-500"
                      }`}
                    >
                      <option value="">-- Select Auditor from Registry --</option>
                      {auditorRegistry.map((user) => (
                        <option key={user.id} value={user.id}>
                          {user.name} ({user.designation})
                        </option>
                      ))}
                    </select>
                  </div>
                </div>

                <div className="flex items-center gap-2">
                  {/* Role Selector */}
                  <div className="flex items-center gap-1">
                    {isLead && <Crown size={13} className="text-amber-500 shrink-0" />}
                    <select
                      value={member.role}
                      disabled={isReadOnly}
                      onChange={(e) =>
                        onUpdateMember(member.id, {
                          role: e.target.value as TeamRole,
                        })
                      }
                      className={`h-7 rounded border px-2 text-xs font-bold disabled:bg-slate-100 disabled:text-slate-700 disabled:cursor-not-allowed ${
                        isLead
                          ? "border-blue-300 bg-blue-50 text-blue-800"
                          : "border-slate-200 bg-white text-slate-700"
                      }`}
                    >
                      <option value="Lead Auditor">Lead Auditor</option>
                      <option value="Auditor">Auditor</option>
                      <option value="Technical Specialist">Technical Specialist</option>
                    </select>
                  </div>

                  {/* Delete Button */}
                  {!isReadOnly && team.length > 1 && (
                    <button
                      type="button"
                      onClick={() => onRemoveMember(member.id)}
                      className="text-slate-300 hover:text-rose-600 p-1 transition cursor-pointer"
                      title="Remove member from roster"
                    >
                      <Trash2 size={14} />
                    </button>
                  )}
                </div>
              </div>

              {/* Assignment Period & Supervision */}
              <div className="space-y-1">
                <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-3 text-xs">
                  {/* Start Date */}
                  <div className="space-y-1">
                    <label className="text-[11px] font-semibold text-slate-600 flex items-center gap-1">
                      <Calendar size={11} className="text-slate-400" />
                      <span>Assignment Start Date</span>
                    </label>
                    <input
                      type="date"
                      value={member.assignmentStartDate}
                      min={periodStart}
                      max={periodEnd}
                      disabled={isReadOnly}
                      onChange={(e) =>
                        onUpdateMember(member.id, {
                          assignmentStartDate: e.target.value,
                        })
                      }
                      className={`h-8 w-full rounded border px-2 text-xs font-mono disabled:bg-slate-100 disabled:text-slate-700 disabled:cursor-not-allowed ${
                        (isStartOutOfRange || !member.assignmentStartDate) && !isReadOnly
                          ? "border-rose-400 bg-rose-50/30 text-rose-900"
                          : "border-slate-200 bg-white text-slate-800 focus:border-blue-500"
                      }`}
                      required
                    />
                  </div>

                  {/* End Date */}
                  <div className="space-y-1">
                    <label className="text-[11px] font-semibold text-slate-600 flex items-center gap-1">
                      <Calendar size={11} className="text-slate-400" />
                      <span>Assignment End Date</span>
                    </label>
                    <input
                      type="date"
                      value={member.assignmentEndDate}
                      min={member.assignmentStartDate || periodStart}
                      max={periodEnd}
                      disabled={isReadOnly}
                      onChange={(e) =>
                        onUpdateMember(member.id, {
                          assignmentEndDate: e.target.value,
                        })
                      }
                      className={`h-8 w-full rounded border px-2 text-xs font-mono disabled:bg-slate-100 disabled:text-slate-700 disabled:cursor-not-allowed ${
                        (isEndOutOfRange || isEndBeforeStart || !member.assignmentEndDate) && !isReadOnly
                          ? "border-rose-400 bg-rose-50/30 text-rose-900"
                          : "border-slate-200 bg-white text-slate-800 focus:border-blue-500"
                      }`}
                      required
                    />
                  </div>

                  {/* Supervision */}
                  <div className="space-y-1">
                    <label className="text-[11px] font-semibold text-slate-600 flex items-center gap-1">
                      <UserCheck size={11} className="text-slate-400" />
                      <span>Direct Supervisor / Oversight</span>
                    </label>
                    <select
                      value={member.supervisorName}
                      disabled={isReadOnly}
                      onChange={(e) =>
                        onUpdateMember(member.id, {
                          supervisorName: e.target.value,
                        })
                      }
                      className="h-8 w-full rounded border border-slate-200 bg-white px-2 text-[11px] text-slate-800 disabled:bg-slate-100 disabled:text-slate-700 disabled:cursor-not-allowed"
                    >
                      {supervisorOptions.map((sup, idx) => (
                        <option key={idx} value={sup}>
                          {sup}
                        </option>
                      ))}
                    </select>
                  </div>
                </div>

                {/* Inline Date Validation Error */}
                {!isReadOnly && isDateInvalid && (
                  <p className="text-[11px] text-rose-600 flex items-center gap-1 pt-0.5">
                    <XCircle size={12} className="shrink-0" />
                    <span>
                      {isEndBeforeStart
                        ? "Assignment end date cannot be earlier than start date."
                        : `Assignment dates must fall within the engagement Period Covered window (${periodStart || "start"} to ${periodEnd || "end"}).`}
                    </span>
                  </p>
                )}
              </div>

              {/* Individual Competency Note */}
              <div className="space-y-1 text-xs">
                <div className="flex items-center justify-between">
                  <label className="text-[11px] font-semibold text-slate-700 flex items-center gap-1">
                    <span>Documented Competencies & Technical Qualifications</span>
                    <span className="text-rose-500">*</span>
                  </label>
                  <span
                    className={`text-[10px] px-1.5 py-0.2 rounded border font-mono ${
                      isCompValid || isReadOnly
                        ? "text-emerald-700 bg-emerald-50 border-emerald-200"
                        : "text-amber-700 bg-amber-50 border-amber-200"
                    }`}
                  >
                    {compLen}/20 chars min
                  </span>
                </div>
                <textarea
                  rows={2}
                  value={member.competencySummary}
                  disabled={isReadOnly}
                  onChange={(e) =>
                    onUpdateMember(member.id, {
                      competencySummary: e.target.value,
                    })
                  }
                  placeholder="e.g. 12+ years compliance auditing experience, RA 9184 procurement specialist, Certified Internal Auditor (CIA)..."
                  className={`w-full rounded border p-2 text-xs text-slate-800 leading-relaxed focus:outline-hidden disabled:bg-slate-100 disabled:text-slate-700 disabled:cursor-not-allowed resize-none ${
                    !isCompValid && compLen > 0 && !isReadOnly
                      ? "border-amber-300 bg-amber-50/20"
                      : !isCompValid && !isReadOnly
                      ? "border-slate-200 bg-white"
                      : "border-emerald-300 bg-white focus:border-emerald-500"
                  }`}
                  required
                />
                {!isCompValid && !isReadOnly && (
                  <p className="text-[10px] text-amber-700">
                    Document at least 20 characters detailing professional certifications, auditing experience, and relevant technical expertise.
                  </p>
                )}
              </div>

              {/* Availability & Workload Row */}
              <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-2 text-xs rounded-md bg-slate-50/70 p-2.5 border border-slate-100">
                {/* Availability Checkbox + Capacity */}
                <div className="space-y-1.5">
                  <label className={`flex items-center gap-2 font-bold text-slate-800 ${isReadOnly ? "cursor-default opacity-80" : "cursor-pointer"}`}>
                    <input
                      type="checkbox"
                      checked={member.isAvailable}
                      disabled={isReadOnly}
                      onChange={(e) =>
                        onUpdateMember(member.id, {
                          isAvailable: e.target.checked,
                        })
                      }
                      className={`h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 ${isReadOnly ? "cursor-not-allowed" : "cursor-pointer"}`}
                    />
                    <span className={`text-xs ${member.isAvailable || isReadOnly ? "text-slate-800" : "text-rose-700"}`}>
                      Confirmed Available (Zero Workload Overload) *
                    </span>
                  </label>
                  {!member.isAvailable && !isReadOnly && (
                    <p className="text-[10px] text-rose-600">
                      Must confirm availability before team assignment can be finalized.
                    </p>
                  )}
                  <input
                    type="text"
                    value={member.workloadNote}
                    disabled={isReadOnly}
                    onChange={(e) =>
                      onUpdateMember(member.id, {
                        workloadNote: e.target.value,
                      })
                    }
                    placeholder="Workload capacity note (e.g. 100% capacity assigned)..."
                    className="h-7 w-full rounded border border-slate-200 bg-white px-2 text-[11px] disabled:bg-slate-100 disabled:text-slate-700 disabled:cursor-not-allowed"
                  />
                </div>

                {/* Schedule Conflicts */}
                <div className="space-y-1.5">
                  <div className="flex items-center justify-between">
                    <span className="font-semibold text-slate-700 text-[11px]">
                      Schedule Conflicts / Concurrent Audits?
                    </span>
                    <div className="flex items-center gap-2">
                      <label className={`flex items-center gap-1 ${isReadOnly ? "cursor-default opacity-80" : "cursor-pointer"}`}>
                        <input
                          type="radio"
                          name={`conflict_${member.id}`}
                          checked={!member.hasScheduleConflict}
                          disabled={isReadOnly}
                          onChange={() =>
                            onUpdateMember(member.id, {
                              hasScheduleConflict: false,
                              scheduleConflictNote: "",
                            })
                          }
                          className={`h-3 w-3 text-blue-600 ${isReadOnly ? "cursor-not-allowed" : "cursor-pointer"}`}
                        />
                        <span className="text-[11px] text-slate-600">No Conflict</span>
                      </label>
                      <label className={`flex items-center gap-1 ${isReadOnly ? "cursor-default opacity-80" : "cursor-pointer"}`}>
                        <input
                          type="radio"
                          name={`conflict_${member.id}`}
                          checked={member.hasScheduleConflict}
                          disabled={isReadOnly}
                          onChange={() =>
                            onUpdateMember(member.id, {
                              hasScheduleConflict: true,
                            })
                          }
                          className={`h-3 w-3 text-amber-600 ${isReadOnly ? "cursor-not-allowed" : "cursor-pointer"}`}
                        />
                        <span className="text-[11px] text-amber-700 font-bold">Conflict</span>
                      </label>
                    </div>
                  </div>

                  {member.hasScheduleConflict && (
                    <input
                      type="text"
                      value={member.scheduleConflictNote}
                      disabled={isReadOnly}
                      onChange={(e) =>
                        onUpdateMember(member.id, {
                          scheduleConflictNote: e.target.value,
                        })
                      }
                      placeholder="Specify overlapping audit dates or mitigated scheduling arrangements..."
                      className="h-7 w-full rounded border border-amber-300 bg-amber-50/50 px-2 text-[11px] text-amber-900 disabled:bg-slate-100 disabled:text-slate-700 disabled:cursor-not-allowed"
                      required
                    />
                  )}
                </div>
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}
