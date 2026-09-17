import { useCallback, useEffect, useMemo, useState } from "react";
import {
  CalendarClock,
  CheckCircle2,
  Download,
  FileText,
  LoaderCircle,
  LockKeyhole,
  Plus,
  RefreshCw,
  Users,
  Upload,
} from "lucide-react";
import { useAuth } from "../../auth/auth-context";
import { hasPermission } from "../../config/navigation";
import { aemsEntryConferenceApi } from "../../services/api";
import Modal from "../ui/Modal";
import StatusBadge from "../ui/StatusBadge";

const emptyRecord = {
  scheduledStartAt: "",
  scheduledEndAt: "",
  venue: "",
  meetingLink: "",
  onlineMeetingDetails: "",
  agenda: "",
  briefingPaper: {
    auditSelectionBackground: "",
    auditAuthority: "",
    preliminaryObjectives: "",
    scopeAndExclusions: "",
    methodology: "",
    auditCriteria: "",
    plannedTiming: "",
    teamMembersAndRoles: "",
    previousAuditMatters: "",
    engagementMilestones: "",
    expectedDeliverables: "",
    initialInformationRequirements: "",
  },
  auditeeViews: "",
  auditeeExpectations: "",
  conferenceNotes: "",
  materialMattersDisposition: "",
  participants: [],
  matters: [],
  agreements: [],
};

function dateInput(value) {
  return value ? new Date(value).toISOString().slice(0, 16) : "";
}

function pretty(value) {
  return value
    ?.replaceAll("_", " ")
    .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function toForm(conference) {
  if (!conference) return structuredClone(emptyRecord);
  return {
    ...emptyRecord,
    ...conference,
    scheduledStartAt: dateInput(conference.scheduledStartAt),
    scheduledEndAt: dateInput(conference.scheduledEndAt),
    briefingPaper: {
      ...emptyRecord.briefingPaper,
      ...(conference.briefingPaper ?? {}),
    },
    participants: conference.participants.map((item) => ({
      userId: item.userId ?? "",
      officeId: item.officeId ?? "",
      participantType: item.participantType,
      participantRole: item.participantRole ?? "",
      externalName: item.externalName ?? "",
      externalEmail: item.externalEmail ?? "",
    })),
    matters: conference.matters,
    agreements: conference.agreements,
  };
}

export default function AemsEntryConferenceWorkspace({ engagementId }) {
  const { user } = useAuth();
  const [workspace, setWorkspace] = useState(null);
  const [form, setForm] = useState(structuredClone(emptyRecord));
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [conflict, setConflict] = useState(false);
  const [editing, setEditing] = useState(false);
  const [action, setAction] = useState(null);
  const [actionForm, setActionForm] = useState({});
  const [upload, setUpload] = useState({
    file: null,
    category: "BRIEFING_PAPER",
    caption: "",
  });
  const conference = workspace?.conference;
  const references = workspace?.references ?? { users: [], offices: [] };
  const ciasOfficeId = references.ciasOfficeId ?? "";
  const canManage = hasPermission(user, "aems.entry-conference.manage");
  const canAcknowledge = hasPermission(
    user,
    "aems.entry-conference.acknowledge",
  );
  const canWaive =
    hasPermission(user, "aems.entry-conference.waive") &&
    (!conference?.createdBy || conference.createdBy.id !== user?.id);

  function participantOptions(participantType, selectedUserId = "") {
    const options = references.users.filter((option) =>
      option.participantTypes?.includes(participantType),
    );
    const selected = references.users.find(
      (option) => String(option.id) === String(selectedUserId),
    );
    return selected && !options.some((option) => option.id === selected.id)
      ? [selected, ...options]
      : options;
  }

  function responsibleOfficeForUser(userId) {
    const selected = references.users.find(
      (option) => String(option.id) === String(userId),
    );
    if (selected?.participantTypes?.includes("AUDIT_TEAM") && ciasOfficeId) {
      return ciasOfficeId;
    }
    return selected?.officeId ?? ciasOfficeId;
  }

  const load = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const result = await aemsEntryConferenceApi.show(engagementId);
      setWorkspace(result);
      setForm(toForm(result.conference));
      setConflict(false);
    } catch (reason) {
      setError(reason.message);
    } finally {
      setLoading(false);
    }
  }, [engagementId]);

  useEffect(() => {
    const timer = window.setTimeout(load, 0);
    return () => window.clearTimeout(timer);
  }, [load]);

  const availableActions = useMemo(() => {
    if (!conference) return [];
    const actions =
      {
        DRAFT: ["SCHEDULE", "CANCEL"],
        SCHEDULED: ["RESCHEDULE", "MARK_HELD", "CANCEL"],
        RESCHEDULED: ["RESCHEDULE", "MARK_HELD", "CANCEL"],
        HELD: ["CIRCULATE_NOTES"],
        NOTES_FOR_ACKNOWLEDGEMENT: ["COMPLETE"],
        ACKNOWLEDGED: ["COMPLETE"],
      }[conference.status] ?? [];
    if (
      canWaive &&
      ["DRAFT", "SCHEDULED", "RESCHEDULED"].includes(conference.status)
    ) {
      actions.push("WAIVE");
    }
    return actions;
  }, [canWaive, conference]);

  async function saveRecord() {
    setSaving(true);
    setError("");
    try {
      let saved = conference
        ? await aemsEntryConferenceApi.update(engagementId, conference.id, {
            ...form,
            lockVersion: conference.lockVersion,
          })
        : await aemsEntryConferenceApi.create(engagementId, form);
      if (!conference && form.scheduledStartAt) {
        saved = await aemsEntryConferenceApi.transition(
          engagementId,
          saved.id,
          "SCHEDULE",
          {
            scheduledStartAt: form.scheduledStartAt,
            scheduledEndAt: form.scheduledEndAt || null,
            venue: form.venue,
            meetingLink: form.meetingLink,
            onlineMeetingDetails: form.onlineMeetingDetails,
            lockVersion: saved.lockVersion,
          },
        );
      }
      setWorkspace((value) => ({ ...value, conference: saved }));
      setForm(toForm(saved));
      setEditing(false);
    } catch (reason) {
      setError(reason.message);
      setConflict(Object.hasOwn(reason.errors ?? {}, "lockVersion"));
    } finally {
      setSaving(false);
    }
  }

  async function runAction() {
    setSaving(true);
    setError("");
    try {
      let payload = { ...actionForm, lockVersion: conference.lockVersion };
      if (["SCHEDULE", "RESCHEDULE"].includes(action)) {
        payload = {
          ...payload,
          scheduledStartAt: form.scheduledStartAt,
          scheduledEndAt: form.scheduledEndAt || null,
          venue: form.venue,
          meetingLink: form.meetingLink,
          onlineMeetingDetails: form.onlineMeetingDetails,
        };
      }
      if (action === "MARK_HELD") {
        payload.heldAt = actionForm.heldAt;
        payload.participantAttendance = conference.participants.map(
          (participant) => ({
            participantId: participant.id,
            attendanceStatus:
              actionForm[`attendance-${participant.id}`] ?? "ATTENDED",
          }),
        );
      }
      const saved = await aemsEntryConferenceApi.transition(
        engagementId,
        conference.id,
        action,
        payload,
      );
      setWorkspace((value) => ({ ...value, conference: saved }));
      setForm(toForm(saved));
      setAction(null);
      setActionForm({});
    } catch (reason) {
      setError(reason.message);
      setConflict(Object.hasOwn(reason.errors ?? {}, "lockVersion"));
    } finally {
      setSaving(false);
    }
  }

  async function acknowledge() {
    setSaving(true);
    setError("");
    try {
      await aemsEntryConferenceApi.acknowledge(engagementId, conference.id, {
        status: actionForm.status ?? "ACKNOWLEDGED",
        reservation: actionForm.reservation || null,
        lockVersion: conference.lockVersion,
      });
      setAction(null);
      setActionForm({});
      await load();
    } catch (reason) {
      setError(reason.message);
      setConflict(Object.hasOwn(reason.errors ?? {}, "lockVersion"));
    } finally {
      setSaving(false);
    }
  }

  async function uploadAttachment() {
    if (!upload.file) return;
    setSaving(true);
    setError("");
    try {
      await aemsEntryConferenceApi.uploadAttachment(
        engagementId,
        conference.id,
        {
          ...upload,
          lockVersion: conference.lockVersion,
        },
      );
      setUpload({ file: null, category: "BRIEFING_PAPER", caption: "" });
      await load();
    } catch (reason) {
      setError(reason.message);
      setConflict(Object.hasOwn(reason.errors ?? {}, "lockVersion"));
    } finally {
      setSaving(false);
    }
  }

  if (loading) {
    return (
      <div
        className="grid min-h-72 place-items-center"
        data-testid="entry-conference-loading"
      >
        <LoaderCircle className="animate-spin text-sky-700" size={30} />
      </div>
    );
  }

  if (!workspace) {
    return (
      <div className="rounded-xl border border-red-200 bg-red-50 p-5 text-red-700">
        {error}
      </div>
    );
  }

  return (
    <div className="space-y-5" data-testid="entry-conference-workspace">
      {error && (
        <div
          className={`rounded-xl border p-4 text-sm font-semibold ${conflict ? "border-amber-300 bg-amber-50 text-amber-800" : "border-red-200 bg-red-50 text-red-700"}`}
          role="alert"
        >
          {conflict && <strong className="block">Stale-state conflict</strong>}
          {error}
          {conflict && (
            <button className="ml-2 underline" onClick={load} type="button">
              Refresh
            </button>
          )}
        </div>
      )}

      <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <p className="text-xs font-bold uppercase tracking-wide text-slate-400">
              Official PGIAM gate
            </p>
            <h2 className="mt-1 text-lg font-bold text-slate-900">
              Entry Conference (Entrance Conference)
            </h2>
          </div>
          <div className="flex items-center gap-2">
            {conference && (
              <StatusBadge tone={conference.immutable ? "success" : "info"}>
                {pretty(conference.status)}
              </StatusBadge>
            )}
            {conference?.immutable && (
              <LockKeyhole
                className="text-emerald-700"
                size={18}
                aria-label="Immutable record"
              />
            )}
            {canManage && !conference?.immutable && (
              <button
                className="rounded-lg bg-sky-700 px-3 py-2 text-xs font-bold text-white"
                onClick={() => setEditing(true)}
                type="button"
              >
                {conference ? "Edit record" : "Create conference"}
              </button>
            )}
          </div>
        </div>
        {!conference ? (
          <p className="mt-5 rounded-lg bg-slate-50 p-4 text-sm text-slate-600">
            Start the aggregate Entry Conference stage, then create the official
            record here.
          </p>
        ) : (
          <div className="mt-5 grid gap-4 md:grid-cols-3">
            <div className="rounded-xl bg-slate-50 p-4">
              <CalendarClock className="text-sky-700" size={20} />
              <strong className="mt-2 block text-sm text-slate-800">
                Schedule
              </strong>
              <span className="text-xs text-slate-600">
                {conference.scheduledStartAt
                  ? new Date(conference.scheduledStartAt).toLocaleString()
                  : "Not scheduled"}
              </span>
              <p className="mt-1 text-xs text-slate-500">
                {conference.venue || conference.meetingLink || "Venue pending"}
              </p>
            </div>
            <div className="rounded-xl bg-slate-50 p-4">
              <Users className="text-sky-700" size={20} />
              <strong className="mt-2 block text-sm text-slate-800">
                Participants
              </strong>
              <span className="text-xs text-slate-600">
                {conference.participants.length} invited
              </span>
              <p className="mt-1 text-xs text-slate-500">
                {
                  conference.participants.filter(
                    (item) => item.attendanceStatus === "ATTENDED",
                  ).length
                }{" "}
                attended
              </p>
            </div>
            <div className="rounded-xl bg-slate-50 p-4">
              <FileText className="text-sky-700" size={20} />
              <strong className="mt-2 block text-sm text-slate-800">
                Governed records
              </strong>
              <span className="text-xs text-slate-600">
                {conference.attachments.length} immutable file versions
              </span>
              <p className="mt-1 text-xs text-slate-500">
                {conference.matters.length} matters ·{" "}
                {conference.agreements.length} agreements
              </p>
            </div>
          </div>
        )}
      </section>

      {conference && (
        <>
          <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <h3 className="font-bold text-slate-900">Workflow actions</h3>
              <button
                className="inline-flex items-center gap-2 text-xs font-bold text-sky-700"
                onClick={load}
                type="button"
              >
                <RefreshCw size={14} /> Refresh
              </button>
            </div>
            <div
              className="mt-4 flex flex-wrap gap-2"
              data-testid="entry-conference-actions"
            >
              {canManage &&
                availableActions.map((item) => (
                  <button
                    className={`rounded-lg px-3 py-2 text-xs font-bold ${item === "WAIVE" ? "border border-amber-300 bg-amber-50 text-amber-800" : "bg-sky-700 text-white"}`}
                    key={item}
                    onClick={() => {
                      setAction(item);
                      setActionForm({});
                    }}
                    type="button"
                  >
                    {pretty(item)}
                  </button>
                ))}
              {canAcknowledge &&
                ["NOTES_FOR_ACKNOWLEDGEMENT", "ACKNOWLEDGED"].includes(
                  conference.status,
                ) && (
                  <button
                    className="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-bold text-white"
                    onClick={() => setAction("ACKNOWLEDGE")}
                    type="button"
                  >
                    Acknowledge notes
                  </button>
                )}
              {!availableActions.length &&
                !["NOTES_FOR_ACKNOWLEDGEMENT", "ACKNOWLEDGED"].includes(
                  conference.status,
                ) && (
                  <span className="text-sm text-slate-500">
                    No action is available at this status.
                  </span>
                )}
            </div>
          </section>

          <div className="grid gap-5 xl:grid-cols-2">
            <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
              <h3 className="font-bold text-slate-900">
                Agenda, briefing, and notes
              </h3>
              {[
                ["Agenda", conference.agenda],
                ["Auditee views", conference.auditeeViews],
                ["Auditee expectations", conference.auditeeExpectations],
                ["Entry Conference Notes", conference.conferenceNotes],
                [
                  "Material matters disposition",
                  conference.materialMattersDisposition,
                ],
              ].map(([label, value]) => (
                <div className="mt-4" key={label}>
                  <strong className="text-xs uppercase tracking-wide text-slate-400">
                    {label}
                  </strong>
                  <p className="mt-1 whitespace-pre-wrap text-sm leading-6 text-slate-700">
                    {value || "Not recorded."}
                  </p>
                </div>
              ))}
            </section>
            <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
              <h3 className="font-bold text-slate-900">
                Matters and commitments
              </h3>
              {[
                ...conference.matters.map((item) => ({
                  id: `m-${item.id}`,
                  title: item.description,
                  meta: `${item.isMaterial ? "Material" : "General"} · ${pretty(item.dispositionStatus)}`,
                })),
                ...conference.agreements.map((item) => ({
                  id: `a-${item.id}`,
                  title: item.agreement,
                  meta: `Agreement · ${pretty(item.status)}`,
                })),
              ].map((item) => (
                <div
                  className="mt-3 rounded-lg border border-slate-200 p-3"
                  key={item.id}
                >
                  <strong className="block text-sm text-slate-800">
                    {item.title}
                  </strong>
                  <span className="text-xs text-slate-500">{item.meta}</span>
                </div>
              ))}
              {!conference.matters.length && !conference.agreements.length && (
                <p className="mt-4 text-sm text-slate-500">
                  No matter or commitment is recorded.
                </p>
              )}
            </section>
          </div>

          <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex items-center justify-between gap-3">
              <h3 className="font-bold text-slate-900">Governed attachments</h3>
              <span className="text-xs font-semibold text-slate-500">
                Exact immutable Core DocumentVersions
              </span>
            </div>
            {canManage && !conference.immutable && (
              <div className="mt-4 grid gap-2 rounded-xl bg-slate-50 p-3 md:grid-cols-[1fr_12rem_1fr_auto]">
                <input
                  className="rounded-lg border border-slate-300 bg-white p-2 text-sm"
                  onChange={(event) =>
                    setUpload((value) => ({
                      ...value,
                      file: event.target.files?.[0] ?? null,
                    }))
                  }
                  type="file"
                />
                <select
                  className="rounded-lg border border-slate-300 bg-white p-2 text-sm"
                  onChange={(event) =>
                    setUpload((value) => ({
                      ...value,
                      category: event.target.value,
                    }))
                  }
                  value={upload.category}
                >
                  {workspace.references.attachmentCategories.map((category) => (
                    <option key={category} value={category}>
                      {pretty(category)}
                    </option>
                  ))}
                </select>
                <input
                  className="rounded-lg border border-slate-300 bg-white p-2 text-sm"
                  onChange={(event) =>
                    setUpload((value) => ({
                      ...value,
                      caption: event.target.value,
                    }))
                  }
                  placeholder="Caption"
                  value={upload.caption}
                />
                <button
                  className="inline-flex items-center justify-center gap-2 rounded-lg bg-sky-700 px-3 py-2 text-xs font-bold text-white disabled:opacity-50"
                  disabled={!upload.file || saving}
                  onClick={uploadAttachment}
                  type="button"
                >
                  <Upload size={14} /> Upload
                </button>
              </div>
            )}
            <div className="mt-4 space-y-2">
              {conference.attachments.map((attachment) => (
                <div
                  className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 p-3"
                  key={attachment.id}
                >
                  <div>
                    <strong className="block text-sm text-slate-800">
                      {attachment.fileName}
                    </strong>
                    <span className="text-xs text-slate-500">
                      {pretty(attachment.category)} · version{" "}
                      {attachment.fileVersionNumber} · SHA-256{" "}
                      {attachment.checksumSha256?.slice(0, 12)}…
                    </span>
                  </div>
                  <button
                    className="inline-flex items-center gap-2 rounded-lg border border-sky-300 px-3 py-2 text-xs font-bold text-sky-700"
                    onClick={() =>
                      aemsEntryConferenceApi.downloadAttachment(
                        engagementId,
                        conference.id,
                        attachment,
                      )
                    }
                    type="button"
                  >
                    <Download size={14} /> Download
                  </button>
                </div>
              ))}
              {!conference.attachments.length && (
                <p className="text-sm text-slate-500">
                  No governed attachment has been uploaded.
                </p>
              )}
            </div>
          </section>

          <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 className="font-bold text-slate-900">Immutable history</h3>
            <ol className="mt-4 space-y-3">
              {workspace.history.map((event) => (
                <li
                  className="border-l-2 border-sky-200 pl-3 text-sm"
                  key={event.id}
                >
                  <strong>{pretty(event.action)}</strong>
                  <span className="ml-2 text-xs text-slate-500">
                    {event.actor?.name} ·{" "}
                    {new Date(event.createdAt).toLocaleString()}
                  </span>
                  {event.comment && (
                    <p className="text-xs text-slate-600">{event.comment}</p>
                  )}
                </li>
              ))}
            </ol>
          </section>
        </>
      )}

      <Modal
        onClose={() => !saving && setEditing(false)}
        open={editing}
        size="xl"
        title={conference ? "Edit Entry Conference" : "Create Entry Conference"}
        footer={
          <>
            <button
              className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-bold"
              onClick={() => setEditing(false)}
              type="button"
            >
              Cancel
            </button>
            <button
              className="rounded-lg bg-sky-700 px-4 py-2 text-sm font-bold text-white disabled:opacity-60"
              disabled={saving}
              onClick={saveRecord}
              type="button"
            >
              {saving
                ? "Saving…"
                : !conference && form.scheduledStartAt
                  ? "Save and schedule"
                  : !conference
                    ? "Save draft"
                    : "Save record"}
            </button>
          </>
        }
      >
        {!conference && (
          <p className="md:col-span-2 rounded-lg border border-sky-200 bg-sky-50 p-3 text-sm leading-6 text-sky-800">
            Schedule the Entry Conference first. If a start time is entered,
            saving this form will create the draft and immediately mark it as
            scheduled; the conference details and actual notes can be added or
            updated afterward.
          </p>
        )}
        <div
          className="grid gap-4 md:grid-cols-2"
          data-testid="entry-conference-form"
        >
          <label className="text-sm font-semibold text-slate-700">
            Scheduled start
            <input
              className="mt-1 w-full rounded-lg border border-slate-300 p-2.5"
              onChange={(event) =>
                setForm((value) => ({
                  ...value,
                  scheduledStartAt: event.target.value,
                }))
              }
              type="datetime-local"
              value={form.scheduledStartAt}
            />
          </label>
          <label className="text-sm font-semibold text-slate-700">
            Scheduled end
            <input
              className="mt-1 w-full rounded-lg border border-slate-300 p-2.5"
              onChange={(event) =>
                setForm((value) => ({
                  ...value,
                  scheduledEndAt: event.target.value,
                }))
              }
              type="datetime-local"
              value={form.scheduledEndAt}
            />
          </label>
          <label className="text-sm font-semibold text-slate-700">
            Venue
            <input
              className="mt-1 w-full rounded-lg border border-slate-300 p-2.5"
              onChange={(event) =>
                setForm((value) => ({ ...value, venue: event.target.value }))
              }
              value={form.venue}
            />
          </label>
          <label className="text-sm font-semibold text-slate-700">
            Online meeting link
            <input
              className="mt-1 w-full rounded-lg border border-slate-300 p-2.5"
              onChange={(event) =>
                setForm((value) => ({
                  ...value,
                  meetingLink: event.target.value,
                }))
              }
              value={form.meetingLink}
            />
          </label>
          {[
            ["Agenda", "agenda"],
            ["Auditee views", "auditeeViews"],
            ["Auditee expectations", "auditeeExpectations"],
            ["Entry Conference Notes", "conferenceNotes"],
            ["Material matters disposition", "materialMattersDisposition"],
          ].map(([label, field]) => (
            <label
              className="text-sm font-semibold text-slate-700 md:col-span-2"
              key={field}
            >
              {label}
              <textarea
                className="mt-1 min-h-24 w-full rounded-lg border border-slate-300 p-3"
                onChange={(event) =>
                  setForm((value) => ({
                    ...value,
                    [field]: event.target.value,
                  }))
                }
                value={form[field]}
              />
            </label>
          ))}
          <div className="rounded-xl border border-slate-200 p-4 md:col-span-2">
            <strong className="text-sm text-slate-800">Briefing paper</strong>
            <div className="mt-3 grid gap-3 md:grid-cols-2">
              {[
                ["Audit-selection background", "auditSelectionBackground"],
                ["Audit authority", "auditAuthority"],
                ["Preliminary objectives", "preliminaryObjectives"],
                ["Scope and exclusions", "scopeAndExclusions"],
                ["Methodology", "methodology"],
                ["Audit criteria", "auditCriteria"],
                ["Planned timing", "plannedTiming"],
                ["Team members and roles", "teamMembersAndRoles"],
                ["Previous audit matters", "previousAuditMatters"],
                ["Engagement milestones", "engagementMilestones"],
                ["Expected deliverables", "expectedDeliverables"],
                [
                  "Initial document and information requirements",
                  "initialInformationRequirements",
                ],
              ].map(([label, field]) => (
                <label
                  className="text-xs font-semibold text-slate-700"
                  key={field}
                >
                  {label}
                  <textarea
                    className="mt-1 min-h-20 w-full rounded-lg border border-slate-300 p-2.5 text-sm"
                    onChange={(event) =>
                      setForm((value) => ({
                        ...value,
                        briefingPaper: {
                          ...value.briefingPaper,
                          [field]: event.target.value,
                        },
                      }))
                    }
                    value={form.briefingPaper[field]}
                  />
                </label>
              ))}
            </div>
          </div>
          <div className="md:col-span-2">
            <div className="flex items-center justify-between">
              <strong className="text-sm text-slate-700">Participants</strong>
              <button
                className="inline-flex items-center gap-1 text-xs font-bold text-sky-700"
                onClick={() =>
                  setForm((value) => ({
                    ...value,
                    participants: [
                      ...value.participants,
                      {
                        userId: "",
                        officeId: "",
                        participantType: "AUDIT_TEAM",
                        participantRole: "",
                        externalName: "",
                        externalEmail: "",
                      },
                    ],
                  }))
                }
                type="button"
              >
                <Plus size={14} /> Add participant
              </button>
            </div>
            {form.participants.map((participant, index) => (
              <div
                className="mt-2 grid gap-3 rounded-lg border border-slate-200 p-3 sm:grid-cols-3"
                key={`${participant.participantType}-${index}`}
              >
                <label className="text-xs font-semibold text-slate-700">
                  Participant type
                  <select
                    className="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm"
                    onChange={(event) =>
                      setForm((value) => ({
                        ...value,
                        participants: value.participants.map((item, itemIndex) =>
                          itemIndex === index
                            ? {
                                ...item,
                                participantType: event.target.value,
                                userId: "",
                                officeId: "",
                                externalName: "",
                                externalEmail: "",
                              }
                            : item,
                        ),
                      }))
                    }
                    value={participant.participantType}
                  >
                    <option value="AUDIT_TEAM">Auditor</option>
                    <option value="AUDITEE">Auditee</option>
                    <option value="EXTERNAL">External</option>
                  </select>
                </label>
                {participant.participantType === "EXTERNAL" ? (
                  <label className="text-xs font-semibold text-slate-700">
                    External participant name
                    <input
                      className="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm"
                      onChange={(event) =>
                        setForm((value) => ({
                          ...value,
                          participants: value.participants.map(
                            (item, itemIndex) =>
                              itemIndex === index
                                ? { ...item, externalName: event.target.value }
                                : item,
                          ),
                        }))
                      }
                      value={participant.externalName}
                    />
                  </label>
                ) : (
                  <label className="text-xs font-semibold text-slate-700">
                    {participant.participantType === "AUDITEE" ? "Auditee" : "Auditor"}
                    <select
                      className="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm"
                      onChange={(event) =>
                        setForm((value) => ({
                          ...value,
                          participants: value.participants.map(
                            (item, itemIndex) =>
                              itemIndex === index
                                ? { ...item, userId: event.target.value }
                                : item,
                          ),
                        }))
                      }
                      value={participant.userId}
                    >
                      <option value="">Select {participant.participantType === "AUDITEE" ? "auditee" : "auditor"}</option>
                      {participantOptions(
                        participant.participantType,
                        participant.userId,
                      ).map((option) => (
                        <option key={option.id} value={option.id}>
                          {option.name}
                        </option>
                      ))}
                    </select>
                  </label>
                )}
                <label className="text-xs font-semibold text-slate-700">
                  Conference role
                  <input
                    className="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm"
                    onChange={(event) =>
                      setForm((value) => ({
                        ...value,
                        participants: value.participants.map((item, itemIndex) =>
                          itemIndex === index
                            ? { ...item, participantRole: event.target.value }
                            : item,
                        ),
                      }))
                    }
                    value={participant.participantRole}
                  />
                </label>
              </div>
            ))}
          </div>
          <div className="md:col-span-2">
            <div className="flex items-center justify-between">
              <strong className="text-sm text-slate-700">Matters raised</strong>
              <button
                className="inline-flex items-center gap-1 text-xs font-bold text-sky-700"
                onClick={() =>
                  setForm((value) => ({
                    ...value,
                    matters: [
                      ...value.matters,
                      {
                        matterType: "GENERAL",
                        description: "",
                        isMaterial: false,
                        dispositionStatus: "OPEN",
                        disposition: "",
                        responsibleOfficeId: ciasOfficeId,
                        dueDate: "",
                      },
                    ],
                  }))
                }
                type="button"
              >
                <Plus size={14} /> Add matter
              </button>
            </div>
            {form.matters.map((matter, index) => (
              <div
                className="mt-2 grid gap-2 rounded-lg border border-slate-200 p-3 sm:grid-cols-2"
                key={`matter-${index}`}
              >
                {[
                  ["Description", "description"],
                  ["Disposition", "disposition"],
                ].map(([label, field]) => (
                  <label className="text-xs font-semibold text-slate-700" key={field}>
                    {label}
                    <input
                      aria-label={`Matter ${label}`}
                      className="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm"
                      onChange={(event) =>
                        setForm((value) => ({
                          ...value,
                          matters: value.matters.map((item, itemIndex) =>
                            itemIndex === index
                              ? { ...item, [field]: event.target.value }
                              : item,
                          ),
                        }))
                      }
                      value={matter[field] ?? ""}
                    />
                  </label>
                ))}
                <label className="text-xs font-semibold text-slate-700">
                  Matter status
                  <select
                    className="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm"
                    onChange={(event) =>
                      setForm((value) => ({
                        ...value,
                        matters: value.matters.map((item, itemIndex) =>
                          itemIndex === index
                            ? { ...item, dispositionStatus: event.target.value }
                            : item,
                        ),
                      }))
                    }
                    value={matter.dispositionStatus}
                  >
                    <option value="OPEN">Open</option>
                    <option value="AGREED">Agreed</option>
                    <option value="RESOLVED">Resolved</option>
                    <option value="DEFERRED">Deferred</option>
                  </select>
                </label>
                <label className="flex items-center gap-2 text-xs font-semibold text-slate-700">
                  <input
                    checked={Boolean(matter.isMaterial)}
                    onChange={(event) =>
                      setForm((value) => ({
                        ...value,
                        matters: value.matters.map((item, itemIndex) =>
                          itemIndex === index
                            ? { ...item, isMaterial: event.target.checked }
                            : item,
                        ),
                      }))
                    }
                    type="checkbox"
                  />
                  Material matter
                </label>
                <label className="text-xs font-semibold text-slate-700">
                  Responsible person
                  <select
                  aria-label="Matter responsible person"
                  className="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm"
                  onChange={(event) =>
                    setForm((value) => ({
                      ...value,
                      matters: value.matters.map((item, itemIndex) =>
                        itemIndex === index
                          ? {
                              ...item,
                              responsibleUserId: event.target.value,
                              responsibleOfficeId: responsibleOfficeForUser(event.target.value),
                            }
                          : item,
                      ),
                    }))
                  }
                  value={matter.responsibleUserId ?? ""}
                >
                  <option value="">Select responsible person</option>
                  {references.users.map((option) => (
                    <option key={option.id} value={option.id}>
                      {option.name}
                    </option>
                  ))}
                  </select>
                </label>
                <label className="text-xs font-semibold text-slate-700">
                  Responsible office (automatic)
                  <select
                    aria-label="Matter responsible office"
                    className="mt-1 w-full rounded-lg border border-slate-300 bg-slate-100 p-2 text-sm"
                    disabled
                    value={matter.responsibleOfficeId ?? ""}
                  >
                    <option value="">Select responsible person first</option>
                    {references.offices.map((option) => (
                      <option key={option.id} value={option.id}>
                        {option.name}
                      </option>
                    ))}
                  </select>
                </label>
                <label className="text-xs font-semibold text-slate-700">
                  Due date
                  <input
                    aria-label="Matter due date"
                    className="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm"
                    onChange={(event) =>
                      setForm((value) => ({
                        ...value,
                        matters: value.matters.map((item, itemIndex) =>
                          itemIndex === index
                            ? { ...item, dueDate: event.target.value }
                            : item,
                        ),
                      }))
                    }
                    type="date"
                    value={matter.dueDate ?? ""}
                  />
                </label>
              </div>
            ))}
          </div>
          <div className="md:col-span-2">
            <div className="flex items-center justify-between">
              <strong className="text-sm text-slate-700">
                Agreements and commitments
              </strong>
              <button
                className="inline-flex items-center gap-1 text-xs font-bold text-sky-700"
                onClick={() =>
                  setForm((value) => ({
                    ...value,
                    agreements: [
                      ...value.agreements,
                      {
                        agreement: "",
                        status: "OPEN",
                        responsibleOfficeId: ciasOfficeId,
                        dueDate: "",
                      },
                    ],
                  }))
                }
                type="button"
              >
                <Plus size={14} /> Add commitment
              </button>
            </div>
            {form.agreements.map((agreement, index) => (
              <div
                className="mt-2 grid gap-2 rounded-lg border border-slate-200 p-3 sm:grid-cols-2"
                key={`agreement-${index}`}
              >
                <label className="text-xs font-semibold text-slate-700">
                  Agreement or commitment
                  <input
                    aria-label="Agreement or commitment"
                    className="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm"
                    onChange={(event) =>
                      setForm((value) => ({
                        ...value,
                        agreements: value.agreements.map((item, itemIndex) =>
                          itemIndex === index
                            ? { ...item, agreement: event.target.value }
                            : item,
                        ),
                      }))
                    }
                    value={agreement.agreement}
                  />
                </label>
                <label className="text-xs font-semibold text-slate-700">
                  Due date
                  <input
                    className="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm"
                    onChange={(event) =>
                      setForm((value) => ({
                        ...value,
                        agreements: value.agreements.map((item, itemIndex) =>
                          itemIndex === index
                            ? { ...item, dueDate: event.target.value }
                            : item,
                        ),
                      }))
                    }
                    type="date"
                    value={agreement.dueDate ?? ""}
                  />
                </label>
                <label className="text-xs font-semibold text-slate-700">
                  Commitment status
                  <select
                  className="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm"
                  onChange={(event) =>
                    setForm((value) => ({
                      ...value,
                      agreements: value.agreements.map((item, itemIndex) =>
                        itemIndex === index
                          ? { ...item, status: event.target.value }
                          : item,
                      ),
                    }))
                  }
                  value={agreement.status}
                >
                  <option value="OPEN">Open</option>
                  <option value="IN_PROGRESS">In progress</option>
                  <option value="COMPLETED">Completed</option>
                  <option value="CANCELLED">Cancelled</option>
                  </select>
                </label>
                <label className="text-xs font-semibold text-slate-700">
                  Responsible person
                  <select
                  aria-label="Commitment responsible person"
                  className="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm"
                  onChange={(event) =>
                    setForm((value) => ({
                      ...value,
                      agreements: value.agreements.map((item, itemIndex) =>
                        itemIndex === index
                          ? {
                              ...item,
                              responsibleUserId: event.target.value,
                              responsibleOfficeId: responsibleOfficeForUser(event.target.value),
                            }
                          : item,
                      ),
                    }))
                  }
                  value={agreement.responsibleUserId ?? ""}
                >
                  <option value="">Select responsible person</option>
                  {references.users.map((option) => (
                    <option key={option.id} value={option.id}>
                      {option.name}
                    </option>
                  ))}
                  </select>
                </label>
                <label className="text-xs font-semibold text-slate-700">
                  Responsible office (automatic)
                  <select
                  aria-label="Commitment responsible office"
                  className="mt-1 w-full rounded-lg border border-slate-300 bg-slate-100 p-2 text-sm"
                  disabled
                  value={agreement.responsibleOfficeId ?? ""}
                >
                  <option value="">Select responsible person first</option>
                  {references.offices.map((option) => (
                    <option key={option.id} value={option.id}>
                      {option.name}
                    </option>
                  ))}
                  </select>
                </label>
              </div>
            ))}
          </div>
        </div>
      </Modal>

      <Modal
        onClose={() => !saving && setAction(null)}
        open={Boolean(action)}
        title={pretty(action)}
        footer={
          <>
            <button
              className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-bold"
              onClick={() => setAction(null)}
              type="button"
            >
              Cancel
            </button>
            <button
              className="rounded-lg bg-sky-700 px-4 py-2 text-sm font-bold text-white disabled:opacity-60"
              disabled={saving}
              onClick={action === "ACKNOWLEDGE" ? acknowledge : runAction}
              type="button"
            >
              {saving ? "Processing…" : "Confirm"}
            </button>
          </>
        }
      >
        <div className="space-y-4">
          {["RESCHEDULE", "CANCEL", "WAIVE"].includes(action) && (
            <label className="block text-sm font-semibold text-slate-700">
              Reason
              <textarea
                className="mt-1 min-h-24 w-full rounded-lg border border-slate-300 p-3"
                onChange={(event) =>
                  setActionForm((value) => ({
                    ...value,
                    reason: event.target.value,
                  }))
                }
                value={actionForm.reason ?? ""}
              />
            </label>
          )}
          {action === "WAIVE" && (
            <label className="block text-sm font-semibold text-slate-700">
              Approving authority
              <input
                className="mt-1 w-full rounded-lg border border-slate-300 p-2.5"
                onChange={(event) =>
                  setActionForm((value) => ({
                    ...value,
                    authority: event.target.value,
                  }))
                }
                value={actionForm.authority ?? ""}
              />
            </label>
          )}
          {action === "MARK_HELD" && (
            <>
              <label className="block text-sm font-semibold text-slate-700">
                Held date and time
                <input
                  className="mt-1 w-full rounded-lg border border-slate-300 p-2.5"
                  onChange={(event) =>
                    setActionForm((value) => ({
                      ...value,
                      heldAt: event.target.value,
                    }))
                  }
                  type="datetime-local"
                  value={actionForm.heldAt ?? ""}
                />
              </label>
              {conference?.participants.map((participant) => (
                <label
                  className="block text-sm font-semibold text-slate-700"
                  key={participant.id}
                >
                  {participant.user?.name || participant.externalName}
                  <select
                    className="mt-1 w-full rounded-lg border border-slate-300 p-2.5"
                    onChange={(event) =>
                      setActionForm((value) => ({
                        ...value,
                        [`attendance-${participant.id}`]: event.target.value,
                      }))
                    }
                    value={
                      actionForm[`attendance-${participant.id}`] ?? "ATTENDED"
                    }
                  >
                    <option value="ATTENDED">Attended</option>
                    <option value="ABSENT">Absent</option>
                    <option value="EXCUSED">Excused</option>
                  </select>
                </label>
              ))}
            </>
          )}
          {action === "ACKNOWLEDGE" && (
            <>
              <label className="block text-sm font-semibold text-slate-700">
                Acknowledgement
                <select
                  className="mt-1 w-full rounded-lg border border-slate-300 p-2.5"
                  onChange={(event) =>
                    setActionForm((value) => ({
                      ...value,
                      status: event.target.value,
                    }))
                  }
                  value={actionForm.status ?? "ACKNOWLEDGED"}
                >
                  <option value="ACKNOWLEDGED">Acknowledge</option>
                  <option value="ACKNOWLEDGED_WITH_RESERVATION">
                    Acknowledge with reservation
                  </option>
                </select>
              </label>
              <label className="block text-sm font-semibold text-slate-700">
                Reservation
                <textarea
                  className="mt-1 min-h-24 w-full rounded-lg border border-slate-300 p-3"
                  onChange={(event) =>
                    setActionForm((value) => ({
                      ...value,
                      reservation: event.target.value,
                    }))
                  }
                  value={actionForm.reservation ?? ""}
                />
              </label>
            </>
          )}
          {action === "CIRCULATE_NOTES" && (
            <div className="flex gap-2 rounded-lg bg-sky-50 p-3 text-sm text-sky-800">
              <CheckCircle2 size={18} /> Circulate the current immutable Notes
              version to auditee participants.
            </div>
          )}
        </div>
      </Modal>
    </div>
  );
}
