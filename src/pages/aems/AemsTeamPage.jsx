import { useCallback, useEffect, useMemo, useState } from "react";
import {
  AlertTriangle,
  ArrowRightLeft,
  CalendarDays,
  Pencil,
  Plus,
  ShieldCheck,
  UserMinus,
  UsersRound,
} from "lucide-react";
import { Link, useSearchParams } from "react-router";
import { useAuth } from "../../auth/auth-context";
import ConfirmDialog from "../../components/ui/ConfirmDialog";
import DataTable from "../../components/ui/DataTable";
import Modal from "../../components/ui/Modal";
import RegistryHeader from "../../components/ui/RegistryHeader";
import SearchableSelect from "../../components/ui/SearchableSelect";
import SummaryCard from "../../components/ui/SummaryCard";
import AemsTeamSafeguardsPanel from "../../components/aems/AemsTeamSafeguardsPanel";
import { hasPermission } from "../../config/navigation";
import {
  aemsEngagementApi,
  aemsTeamApi,
  aemsTeamSafeguardApi,
  ApiError,
} from "../../services/api";
import { useToast } from "../../ui/toast-context";

const emptyForm = {
  userId: "",
  assignmentRoleCode: "AUDITOR",
  plannedPersonDays: "",
  assignedFrom: "",
  assignedUntil: "",
  assignmentNotes: "",
  reason: "",
  amendmentAuthority: "AEMS_TEAM_ASSIGNMENT_AUTHORITY",
  consequenceAssessment: "",
};

function roleLabel(value) {
  return String(value ?? "")
    .replaceAll("_", " ")
    .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function date(value) {
  if (!value) return "Open";
  return new Intl.DateTimeFormat("en-PH", {
    month: "short",
    day: "numeric",
    year: "numeric",
  }).format(new Date(`${value}T00:00:00`));
}

/**
 * Maintains the active team for one engagement and presents capacity,
 * scheduling, and competency warnings without blocking justified assignments.
 */
export default function AemsTeamPage() {
  const { user } = useAuth();
  const toast = useToast();
  const [searchParams, setSearchParams] = useSearchParams();
  const [engagements, setEngagements] = useState([]);
  const [selectedId, setSelectedId] = useState(
    searchParams.get("engagementId") ?? "",
  );
  const [overview, setOverview] = useState(null);
  const [safeguards, setSafeguards] = useState(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [formOpen, setFormOpen] = useState(false);
  const [formMode, setFormMode] = useState("assign");
  const [target, setTarget] = useState(null);
  const [form, setForm] = useState(emptyForm);
  const [errors, setErrors] = useState({});
  const [endTarget, setEndTarget] = useState(null);
  const [endReason, setEndReason] = useState("");

  const canAssignPermission = hasPermission(user, "aems.team.assign");
  const canReassignPermission = hasPermission(user, "aems.team.reassign");
  const scopeReady = overview?.engagement?.scopeReady ?? false;
  const phaseReady = overview?.engagement?.status !== "DRAFT";
  const canAssign = canAssignPermission && scopeReady && phaseReady;
  const canReassign = canReassignPermission && scopeReady && phaseReady;

  const loadEngagements = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const data = await aemsEngagementApi.list({
        perPage: 100,
        sortBy: "updated_at",
        sortDirection: "desc",
      });
      setEngagements(data.engagements);
      setSelectedId(
        (current) => current || String(data.engagements[0]?.id ?? ""),
      );
    } catch (requestError) {
      setError(requestError.message);
    } finally {
      setLoading(false);
    }
  }, []);

  const loadTeam = useCallback(async () => {
    if (!selectedId) {
      setOverview(null);
      setSafeguards(null);
      return;
    }
    setLoading(true);
    setError("");
    try {
      const [teamResult, safeguardResult] = await Promise.allSettled([
        aemsTeamApi.show(selectedId),
        aemsTeamSafeguardApi.show(selectedId),
      ]);
      if (teamResult.status === "rejected") throw teamResult.reason;
      setOverview(teamResult.value);
      setSafeguards(
        safeguardResult.status === "fulfilled" ? safeguardResult.value : null,
      );
    } catch (requestError) {
      setError(requestError.message);
      setOverview(null);
      setSafeguards(null);
    } finally {
      setLoading(false);
    }
  }, [selectedId]);

  const refreshSafeguards = useCallback(async () => {
    if (!selectedId) return;
    try {
      setSafeguards(await aemsTeamSafeguardApi.show(selectedId));
    } catch (requestError) {
      setError(requestError.message);
    }
  }, [selectedId]);

  useEffect(() => {
    const timer = window.setTimeout(loadEngagements, 0);
    return () => window.clearTimeout(timer);
  }, [loadEngagements]);

  useEffect(() => {
    const timer = window.setTimeout(loadTeam, 0);
    return () => window.clearTimeout(timer);
  }, [loadTeam]);

  useEffect(() => {
    if (selectedId)
      setSearchParams({ engagementId: selectedId }, { replace: true });
  }, [selectedId, setSearchParams]);

  const engagementOptions = engagements.map((engagement) => ({
    value: engagement.id,
    label: `${engagement.engagementCode} — ${engagement.title}`,
    keywords: engagement.offices?.map((office) => office.name).join(" "),
  }));
  const candidateOptions = useMemo(
    () =>
      (overview?.candidates ?? [])
        .filter(
          (candidate) =>
            !candidate.alreadyAssigned ||
            (formMode === "edit" && candidate.id === target?.userId),
        )
        .map((candidate) => ({
          value: candidate.id,
          label: `${candidate.employeeId} — ${candidate.name}`,
          keywords: `${candidate.office?.code ?? ""} ${candidate.skills
            .map((skill) => skill.label)
            .join(" ")}`,
        })),
    [formMode, overview, target],
  );

  function openAssign() {
    setFormMode("assign");
    setTarget(null);
    setErrors({});
    setForm({
      ...emptyForm,
      assignedFrom: overview?.engagement.plannedStartDate ?? "",
      assignedUntil: overview?.engagement.plannedEndDate ?? "",
    });
    setFormOpen(true);
  }

  function openEdit(member) {
    setFormMode("edit");
    setTarget(member);
    setErrors({});
    setForm({
      userId: member.userId,
      assignmentRoleCode: member.assignmentRoleCode,
      plannedPersonDays: member.plannedPersonDays,
      assignedFrom: member.assignedFrom ?? "",
      assignedUntil: member.assignedUntil ?? "",
      assignmentNotes: member.assignmentNotes ?? "",
      reason: "",
      amendmentAuthority: "AEMS_TEAM_ASSIGNMENT_AUTHORITY",
      consequenceAssessment: "",
    });
    setFormOpen(true);
  }

  function openReassign(member) {
    setFormMode("reassign");
    setTarget(member);
    setErrors({});
    setForm({
      userId: "",
      assignmentRoleCode: member.assignmentRoleCode,
      plannedPersonDays: member.plannedPersonDays,
      assignedFrom: member.assignedFrom ?? "",
      assignedUntil: member.assignedUntil ?? "",
      assignmentNotes: member.assignmentNotes ?? "",
      reason: "",
      amendmentAuthority: "AEMS_TEAM_ASSIGNMENT_AUTHORITY",
      consequenceAssessment: "",
    });
    setFormOpen(true);
  }

  async function save() {
    setSaving(true);
    setErrors({});
    const payload = {
      assignmentRoleCode: form.assignmentRoleCode,
      plannedPersonDays: Number(form.plannedPersonDays),
      assignedFrom: form.assignedFrom || null,
      assignedUntil: form.assignedUntil || null,
      assignmentNotes: form.assignmentNotes || null,
      reason: form.reason || null,
      amendmentAuthority: form.amendmentAuthority || null,
      consequenceAssessment: form.consequenceAssessment || null,
    };
    try {
      if (formMode === "assign") {
        await aemsTeamApi.assign(selectedId, {
          ...payload,
          userId: Number(form.userId),
        });
      } else if (formMode === "edit") {
        await aemsTeamApi.update(selectedId, target.id, payload);
      } else {
        await aemsTeamApi.reassign(selectedId, target.id, {
          ...payload,
          replacementUserId: Number(form.userId),
          reason: form.reason,
        });
      }
      toast.success(
        formMode === "reassign"
          ? "Team member reassigned."
          : formMode === "edit"
            ? "Assignment updated."
            : "Team member assigned.",
      );
      setFormOpen(false);
      await loadTeam();
    } catch (requestError) {
      if (requestError instanceof ApiError) setErrors(requestError.errors);
      toast.error(requestError.message);
    } finally {
      setSaving(false);
    }
  }

  async function endAssignment() {
    if (!endTarget) return;
    setSaving(true);
    try {
      await aemsTeamApi.end(selectedId, endTarget.id, endReason);
      toast.success("Assignment ended and retained in team history.");
      setEndTarget(null);
      setEndReason("");
      await loadTeam();
    } catch (requestError) {
      toast.error(requestError.message);
    } finally {
      setSaving(false);
    }
  }

  const columns = [
    {
      key: "user",
      label: "Team member",
      sortValue: (row) => row.user?.name,
      render: (row) => (
        <div className="min-w-48">
          <strong className="block text-sm text-slate-800">
            {row.user?.name}
          </strong>
          <span className="text-xs text-slate-500">{row.user?.employeeId}</span>
          {!!row.skills?.length && (
            <span className="mt-1 block text-[11px] text-sky-700">
              {row.skills.map((skill) => skill.label).join(", ")}
            </span>
          )}
        </div>
      ),
    },
    {
      key: "assignmentRoleCode",
      label: "Assignment role",
      render: (row) => (
        <span className="rounded-full bg-sky-50 px-2.5 py-1 text-xs font-bold text-sky-700 ring-1 ring-sky-200">
          {roleLabel(row.assignmentRoleCode)}
        </span>
      ),
    },
    {
      key: "plannedPersonDays",
      label: "Person-days",
      render: (row) => (
        <div>
          <strong>{row.plannedPersonDays}</strong>
          <span className="block text-[11px] text-slate-500">
            {row.allocatedPersonDays}/{row.availablePersonDays} annual
            allocation
          </span>
        </div>
      ),
    },
    {
      key: "assignedFrom",
      label: "Assignment dates",
      render: (row) => (
        <span className="whitespace-nowrap text-xs">
          {date(row.assignedFrom)} – {date(row.assignedUntil)}
        </span>
      ),
    },
    {
      key: "actions",
      label: "Actions",
      sortable: false,
      render: (row) => (
        <div className="flex gap-1">
          {canAssign && (
            <button
              aria-label="Edit assignment"
              className="grid h-9 w-9 place-items-center rounded-lg border border-slate-200 text-sky-700 hover:bg-sky-50"
              onClick={(event) => {
                event.stopPropagation();
                openEdit(row);
              }}
              type="button"
            >
              <Pencil size={16} />
            </button>
          )}
          {canReassign && (
            <button
              aria-label="Reassign team member"
              className="grid h-9 w-9 place-items-center rounded-lg border border-slate-200 text-violet-700 hover:bg-violet-50"
              onClick={(event) => {
                event.stopPropagation();
                openReassign(row);
              }}
              type="button"
            >
              <ArrowRightLeft size={16} />
            </button>
          )}
          {canAssign && (
            <button
              aria-label="End assignment"
              className="grid h-9 w-9 place-items-center rounded-lg border border-slate-200 text-red-600 hover:bg-red-50"
              onClick={(event) => {
                event.stopPropagation();
                setEndReason("");
                setEndTarget(row);
              }}
              type="button"
            >
              <UserMinus size={16} />
            </button>
          )}
        </div>
      ),
    },
  ];

  return (
    <main className="min-w-0 p-3 sm:p-5 lg:p-6">
      <RegistryHeader
        icon={UsersRound}
        title="Audit Team"
        description="Assign engagement roles, planned effort, and dates while monitoring capacity, availability, competencies, and reassignment history."
        readOnly={!canAssign && !canReassign}
        actions={
          canAssign && selectedId ? (
            <button
              className="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white shadow-sm hover:bg-sky-800"
              onClick={openAssign}
              type="button"
            >
              <Plus size={17} /> Assign team member
            </button>
          ) : null
        }
      />

      <section className="mb-5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <label className="block text-xs font-bold uppercase tracking-wide text-slate-500">
          Engagement
          <span className="mt-2 block max-w-4xl normal-case">
            <SearchableSelect
              options={engagementOptions}
              placeholder="Select an engagement"
              searchPlaceholder="Search engagement code, title, or office..."
              value={selectedId}
              onChange={(value) => setSelectedId(String(value))}
            />
          </span>
        </label>
      </section>

      {error && (
        <div className="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700">
          {error}
        </div>
      )}

      {overview && (
        <>
          {!scopeReady && (
            <section className="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
              <strong>Engagement Scope is not ready.</strong> Select one office
              and at least one audit area in the Engagement Scope workspace
              before assigning or amending the Audit Team.
            </section>
          )}
          {!phaseReady && (
            <section className="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
              <strong>Audit Team assignment is locked during Draft.</strong>{" "}
              Move the engagement to Authorization Preparation from the Lifecycle
              workspace before assigning or amending team members.
              {selectedId && (
                <Link
                  className="ml-2 inline-flex rounded-lg border border-amber-300 bg-white px-3 py-1.5 text-xs font-bold text-amber-900 hover:bg-amber-100"
                  to={`/audit-engagement-management/${selectedId}?tab=lifecycle`}
                >
                  Open Lifecycle
                </Link>
              )}
            </section>
          )}
          <section className="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <SummaryCard
              icon={UsersRound}
              label="Team members"
              value={overview.summary.members}
              tone="sky"
            />
            <SummaryCard
              icon={ShieldCheck}
              label="Roles filled"
              value={`${overview.summary.rolesFilled}/4`}
              tone="emerald"
            />
            <SummaryCard
              icon={CalendarDays}
              label="Assigned days"
              value={overview.summary.plannedPersonDays}
              tone="amber"
            />
            <SummaryCard
              icon={AlertTriangle}
              label="Warnings"
              value={overview.warnings.length}
              tone={overview.warnings.length ? "red" : "slate"}
            />
          </section>

          {!!overview.warnings.length && (
            <section className="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4">
              <h2 className="flex items-center gap-2 text-sm font-bold text-amber-900">
                <AlertTriangle size={17} /> Resource and assignment warnings
              </h2>
              <ul className="mt-3 grid gap-2 lg:grid-cols-2">
                {overview.warnings.map((warning, index) => (
                  <li
                    className={`rounded-lg border bg-white/80 px-3 py-2 text-xs leading-5 ${
                      warning.severity === "danger"
                        ? "border-red-200 text-red-700"
                        : "border-amber-200 text-amber-800"
                    }`}
                    key={`${warning.type}-${warning.userId ?? index}-${index}`}
                  >
                    {warning.message}
                  </li>
                ))}
              </ul>
            </section>
          )}

          <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div className="border-b border-slate-200 px-4 py-3 sm:px-5">
              <h2 className="font-bold text-slate-800">Current team</h2>
              <p className="mt-1 text-xs text-slate-500">
                {overview.engagement.engagementCode} ·{" "}
                {overview.engagement.title}
              </p>
            </div>
            <DataTable
              columns={columns}
              rows={overview.teamMembers}
              loading={loading}
              emptyMessage="No team members have been assigned."
            />
          </section>

          <section className="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div className="border-b border-slate-200 px-4 py-3 sm:px-5">
              <h2 className="font-bold text-slate-800">Assignment history</h2>
              <p className="mt-1 text-xs text-slate-500">
                Append-only assignment, update, reassignment, and removal
                events.
              </p>
            </div>
            <div className="divide-y divide-slate-100">
              {overview.history.map((item) => (
                <div
                  className="grid gap-2 px-4 py-3 text-xs sm:grid-cols-[9rem_1fr_auto] sm:px-5"
                  key={item.id}
                >
                  <strong className="text-sky-800">
                    {roleLabel(item.action)}
                  </strong>
                  <span className="text-slate-600">
                    {item.reason ||
                      `${item.actor?.name} updated the team assignment.`}
                  </span>
                  <span className="text-slate-400">
                    {new Intl.DateTimeFormat("en-PH", {
                      month: "short",
                      day: "numeric",
                      year: "numeric",
                      hour: "numeric",
                      minute: "2-digit",
                    }).format(new Date(item.createdAt))}
                  </span>
                </div>
              ))}
              {!overview.history.length && (
                <p className="p-5 text-sm text-slate-500">
                  No assignment history yet.
                </p>
              )}
            </div>
          </section>

          <section className="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div className="border-b border-slate-200 px-4 py-3 sm:px-5">
              <h2 className="font-bold text-slate-800">
                Amendment authority and access history
              </h2>
              <p className="mt-1 text-xs text-slate-500">
                Every assignment change records authority, consequence
                assessment, and access grant or revocation.
              </p>
            </div>
            <div className="grid gap-5 p-4 lg:grid-cols-2 sm:p-5">
              <div className="space-y-2">
                {(overview.amendments ?? []).map((item) => (
                  <div
                    className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs"
                    key={item.id}
                  >
                    <strong className="text-sky-800">
                      {roleLabel(item.action)}
                    </strong>
                    <p className="mt-1 text-slate-600">{item.reason}</p>
                    <p className="mt-1 text-slate-500">
                      Authority: {item.authorityCode} ·{" "}
                      {item.consequenceAssessment}
                    </p>
                  </div>
                ))}
                {!overview.amendments?.length && (
                  <p className="text-sm text-slate-500">
                    No controlled amendments recorded.
                  </p>
                )}
              </div>
              <div className="space-y-2">
                {(overview.accessHistory ?? []).map((item) => (
                  <div
                    className="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-xs"
                    key={item.id}
                  >
                    <span>
                      <strong className="text-slate-700">
                        {item.user?.name}
                      </strong>
                      <span className="ml-2 text-slate-500">
                        {roleLabel(item.assignmentRoleCode)}
                      </span>
                    </span>
                    <span
                      className={
                        item.action === "REVOKED"
                          ? "font-bold text-red-700"
                          : "font-bold text-emerald-700"
                      }
                    >
                      {item.action}
                    </span>
                  </div>
                ))}
                {!overview.accessHistory?.length && (
                  <p className="text-sm text-slate-500">
                    No access history recorded.
                  </p>
                )}
              </div>
            </div>
          </section>

          {safeguards && (
            <AemsTeamSafeguardsPanel
              engagementId={selectedId}
              loading={loading}
              onRefresh={refreshSafeguards}
              overview={safeguards}
            />
          )}
        </>
      )}

      <Modal
        open={formOpen}
        onClose={() => !saving && setFormOpen(false)}
        size="lg"
        title={
          formMode === "assign"
            ? "Assign team member"
            : formMode === "edit"
              ? "Edit assignment"
              : "Reassign team member"
        }
        description={
          formMode === "reassign"
            ? `${target?.user?.name} will remain in immutable assignment history.`
            : "Set the engagement role, planned effort, and active assignment dates."
        }
        footer={
          <>
            <button
              className="h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700"
              disabled={saving}
              onClick={() => setFormOpen(false)}
              type="button"
            >
              Cancel
            </button>
            <button
              className="h-10 rounded-lg bg-sky-700 px-5 text-sm font-bold text-white disabled:opacity-60"
              disabled={saving}
              onClick={save}
              type="button"
            >
              {saving
                ? "Saving..."
                : formMode === "reassign"
                  ? "Reassign"
                  : "Save assignment"}
            </button>
          </>
        }
      >
        <div className="grid gap-4 sm:grid-cols-2">
          {formMode !== "edit" && (
            <label className="sm:col-span-2 text-sm font-semibold text-slate-700">
              {formMode === "reassign" ? "Replacement employee" : "Employee"}
              <span className="mt-1.5 block">
                <SearchableSelect
                  options={candidateOptions}
                  placeholder="Search active CIAS employees..."
                  value={form.userId}
                  onChange={(value) =>
                    setForm((current) => ({ ...current, userId: value }))
                  }
                />
              </span>
              {errors.userId && (
                <small className="text-red-600">{errors.userId[0]}</small>
              )}
              {errors.replacementUserId && (
                <small className="text-red-600">
                  {errors.replacementUserId[0]}
                </small>
              )}
            </label>
          )}
          <label className="text-sm font-semibold text-slate-700">
            Assignment role
            <select
              className="mt-1.5 h-11 w-full rounded-lg border border-slate-300 bg-white px-3"
              value={form.assignmentRoleCode}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  assignmentRoleCode: event.target.value,
                }))
              }
            >
              {(overview?.roles ?? []).map((role) => (
                <option key={role.code} value={role.code}>
                  {role.label}
                </option>
              ))}
            </select>
            {errors.assignmentRoleCode && (
              <small className="text-red-600">
                {errors.assignmentRoleCode[0]}
              </small>
            )}
          </label>
          <label className="text-sm font-semibold text-slate-700">
            Planned person-days
            <input
              className="mt-1.5 h-11 w-full rounded-lg border border-slate-300 px-3"
              min="0.25"
              step="0.25"
              type="number"
              value={form.plannedPersonDays}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  plannedPersonDays: event.target.value,
                }))
              }
            />
          </label>
          <label className="text-sm font-semibold text-slate-700">
            Assigned from
            <input
              className="mt-1.5 h-11 w-full rounded-lg border border-slate-300 px-3"
              type="date"
              value={form.assignedFrom}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  assignedFrom: event.target.value,
                }))
              }
            />
          </label>
          <label className="text-sm font-semibold text-slate-700">
            Assigned until
            <input
              className="mt-1.5 h-11 w-full rounded-lg border border-slate-300 px-3"
              type="date"
              value={form.assignedUntil}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  assignedUntil: event.target.value,
                }))
              }
            />
          </label>
          <label className="sm:col-span-2 text-sm font-semibold text-slate-700">
            Assignment notes
            <textarea
              className="mt-1.5 min-h-24 w-full rounded-lg border border-slate-300 p-3"
              value={form.assignmentNotes}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  assignmentNotes: event.target.value,
                }))
              }
            />
          </label>
          {formMode === "reassign" && (
            <label className="sm:col-span-2 text-sm font-semibold text-slate-700">
              Reassignment reason
              <textarea
                className="mt-1.5 min-h-24 w-full rounded-lg border border-slate-300 p-3"
                value={form.reason}
                onChange={(event) =>
                  setForm((current) => ({
                    ...current,
                    reason: event.target.value,
                  }))
                }
              />
              {errors.reason && (
                <small className="text-red-600">{errors.reason[0]}</small>
              )}
            </label>
          )}
          {formMode !== "assign" && (
            <>
              <label className="text-sm font-semibold text-slate-700">
                Amendment authority
                <input
                  className="mt-1.5 h-11 w-full rounded-lg border border-slate-300 px-3 font-normal"
                  value={form.amendmentAuthority}
                  onChange={(event) =>
                    setForm((current) => ({
                      ...current,
                      amendmentAuthority: event.target.value,
                    }))
                  }
                />
              </label>
              <label className="text-sm font-semibold text-slate-700">
                Consequence assessment
                <textarea
                  className="mt-1.5 min-h-20 w-full rounded-lg border border-slate-300 p-3 font-normal"
                  value={form.consequenceAssessment}
                  onChange={(event) =>
                    setForm((current) => ({
                      ...current,
                      consequenceAssessment: event.target.value,
                    }))
                  }
                  placeholder="Assess independence, capacity, schedule, and control consequences."
                />
              </label>
            </>
          )}
        </div>
      </Modal>

      <ConfirmDialog
        open={Boolean(endTarget)}
        title="End audit team assignment?"
        description={`${endTarget?.user?.name ?? "This employee"} will be removed from the current team but retained in assignment history.`}
        confirmLabel="End assignment"
        tone="danger"
        busy={saving}
        onCancel={() => setEndTarget(null)}
        onConfirm={endAssignment}
      >
        <label className="mt-3 block text-xs font-bold text-slate-700">
          Reason
          <textarea
            className="mt-1 min-h-20 w-full rounded-lg border border-slate-300 p-2 font-normal"
            value={endReason}
            onChange={(event) => setEndReason(event.target.value)}
          />
        </label>
      </ConfirmDialog>
    </main>
  );
}
