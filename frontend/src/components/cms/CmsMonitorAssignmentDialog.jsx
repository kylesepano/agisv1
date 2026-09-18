import { useMemo, useState } from "react";
import FormField from "../ui/FormField";
import Modal from "../ui/Modal";

const inputClass =
  "h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100 disabled:bg-slate-100";

export default function CmsMonitorAssignmentDialog({
  open,
  currentMonitor,
  eligibleMonitors = [],
  busy = false,
  errors = {},
  message = "",
  onClose,
  onSubmit,
}) {
  const replacing = Boolean(currentMonitor);
  const options = useMemo(
    () =>
      eligibleMonitors.filter(
        (candidate) => candidate.id !== currentMonitor?.user?.id,
      ),
    [currentMonitor, eligibleMonitors],
  );
  const [values, setValues] = useState({
    userId: "",
    reason: "",
    effectiveFrom: "",
    effectiveUntil: "",
  });
  const [clientErrors, setClientErrors] = useState({});

  function reset() {
    setValues({
      userId: "",
      reason: "",
      effectiveFrom: "",
      effectiveUntil: "",
    });
    setClientErrors({});
  }

  function submit() {
    const nextErrors = {};
    if (!values.userId) nextErrors.userId = "Select a Compliance Monitor.";
    if (replacing && !values.reason.trim()) {
      nextErrors.reason = "A replacement reason is required.";
    }
    setClientErrors(nextErrors);
    if (Object.keys(nextErrors).length > 0) return;
    onSubmit(values);
  }

  function fieldError(name) {
    const server = errors[name];
    return clientErrors[name] || (Array.isArray(server) ? server[0] : server);
  }

  const proposed = options.find(
    (candidate) => String(candidate.id) === String(values.userId),
  );

  return (
    <Modal
      description={
        replacing
          ? "The current assignment remains in history and will be ended when the replacement succeeds."
          : "Only backend-authorized eligible monitors are shown."
      }
      footer={
        <>
          <button
            className="h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700 hover:bg-slate-100 disabled:opacity-60"
            disabled={busy}
            onClick={() => {
              reset();
              onClose();
            }}
            type="button"
          >
            Cancel
          </button>
          <button
            className="h-10 rounded-lg bg-sky-700 px-5 text-sm font-bold text-white hover:bg-sky-800 disabled:cursor-not-allowed disabled:opacity-60"
            disabled={busy || options.length === 0}
            onClick={submit}
            type="button"
          >
            {busy
              ? "Saving..."
              : replacing
                ? "Replace monitor"
                : "Assign monitor"}
          </button>
        </>
      }
      onClose={() => {
        if (busy) return;
        reset();
        onClose();
      }}
      open={open}
      title={
        replacing ? "Replace Compliance Monitor" : "Assign Compliance Monitor"
      }
    >
      <div className="grid gap-4">
        {currentMonitor?.user && (
          <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
            Current monitor: <strong>{currentMonitor.user.name}</strong>
          </div>
        )}
        {message && (
          <p className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
            {message}
          </p>
        )}
        <FormField
          error={fieldError("userId")}
          htmlFor="cms-monitor-user"
          label="Compliance Monitor"
          required
        >
          <select
            className={inputClass}
            disabled={busy || options.length === 0}
            id="cms-monitor-user"
            onChange={(event) =>
              setValues((current) => ({
                ...current,
                userId: event.target.value,
              }))
            }
            value={values.userId}
          >
            <option value="">
              {options.length ? "Select a monitor" : "No eligible monitors"}
            </option>
            {options.map((candidate) => (
              <option key={candidate.id} value={candidate.id}>
                {candidate.name} ({candidate.employeeId})
              </option>
            ))}
          </select>
        </FormField>
        {proposed && (
          <p className="text-sm text-slate-600">
            Proposed monitor: <strong>{proposed.name}</strong>
          </p>
        )}
        <FormField
          error={fieldError("reason")}
          htmlFor="cms-monitor-reason"
          label={replacing ? "Replacement reason" : "Assignment reason"}
          required={replacing}
        >
          <textarea
            className="min-h-24 w-full rounded-lg border border-slate-300 p-3 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100"
            id="cms-monitor-reason"
            maxLength={2000}
            onChange={(event) =>
              setValues((current) => ({
                ...current,
                reason: event.target.value,
              }))
            }
            value={values.reason}
          />
        </FormField>
        <div className="grid gap-4 sm:grid-cols-2">
          <FormField
            error={fieldError("effectiveFrom")}
            htmlFor="cms-monitor-from"
            label="Effective from"
          >
            <input
              className={inputClass}
              id="cms-monitor-from"
              onChange={(event) =>
                setValues((current) => ({
                  ...current,
                  effectiveFrom: event.target.value,
                }))
              }
              type="datetime-local"
              value={values.effectiveFrom}
            />
          </FormField>
          <FormField
            error={fieldError("effectiveUntil")}
            htmlFor="cms-monitor-until"
            label="Effective until"
          >
            <input
              className={inputClass}
              id="cms-monitor-until"
              onChange={(event) =>
                setValues((current) => ({
                  ...current,
                  effectiveUntil: event.target.value,
                }))
              }
              type="datetime-local"
              value={values.effectiveUntil}
            />
          </FormField>
        </div>
      </div>
    </Modal>
  );
}
