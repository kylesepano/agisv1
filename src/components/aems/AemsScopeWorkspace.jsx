import { useEffect, useMemo, useState } from "react";
import {
  AlertTriangle,
  Building2,
  CheckCircle2,
  Save,
  ShieldCheck,
} from "lucide-react";
import { useAuth } from "../../auth/auth-context";
import { hasPermission } from "../../config/navigation";
import {
  aemsEngagementApi,
  ApiError,
  auditAreaApi,
  auditFocusApi,
  officeApi,
} from "../../services/api";
import FormField from "../ui/FormField";
import SearchableSelect from "../ui/SearchableSelect";
import StatusBadge from "../ui/StatusBadge";
import { useToast } from "../../ui/toast-context";

const inputClass =
  "min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100";
const textareaClass = `${inputClass} min-h-24 py-3`;

const lifecycleEditableStatuses = new Set([
  "DRAFT",
  "AUTHORIZATION_PREPARATION",
  "AUTHORIZED",
]);

function metadataFor(area) {
  const metadata = area?.coverageMetadata;
  if (!metadata || typeof metadata !== "object") return {};
  return metadata;
}

function coverageFrom(engagement) {
  const focuses = engagement?.auditFocuses ?? [];
  return (engagement?.auditAreas ?? []).map((area) => {
    const metadata = metadataFor(area);
    const metadataFocusIds = Array.isArray(metadata.focusIds)
      ? metadata.focusIds
          .map((focusId) => Number(focusId))
          .filter((focusId) => focusId > 0)
      : [];
    return {
      auditAreaId: area.id,
      boundary: metadata.boundary ?? "",
      limitations: metadata.limitations ?? "",
      sourceVariance: metadata.sourceVariance ?? "",
      objective: metadata.objective ?? "",
      focusIds: metadataFocusIds.length
        ? metadataFocusIds
        : focuses
            .filter((focus) => String(focus.auditAreaId) === String(area.id))
            .map((focus) => Number(focus.id))
            .filter((focusId) => focusId > 0),
    };
  });
}

function toForm(engagement) {
  return {
    officeId:
      engagement?.engagementOfficeId ?? engagement?.offices?.[0]?.id ?? "",
    scopeBoundaries: engagement?.scopeBoundaries ?? "",
    scopeLimitations: engagement?.scopeLimitations ?? "",
    scopeSourceVariance: {
      decision: engagement?.scopeSourceVariance?.decision ?? "ALIGNED",
      explanation: engagement?.scopeSourceVariance?.explanation ?? "",
      authority: engagement?.scopeSourceVariance?.authority ?? "",
    },
    areaCoverage: coverageFrom(engagement),
    lockVersion: engagement?.lockVersion ?? 1,
  };
}

function errorMessage(error) {
  if (error instanceof ApiError && Object.keys(error.errors ?? {}).length) {
    return Object.values(error.errors).flat().join(" ");
  }
  return error instanceof Error
    ? error.message
    : "The scope could not be saved.";
}

/** SCR-212 Define Engagement Scope workspace. */
export default function AemsScopeWorkspace({
  engagementId,
  initialEngagement,
}) {
  const { user } = useAuth();
  const toast = useToast();
  const canEdit = hasPermission(user, "aems.foundation.manage_scope");
  const [engagement, setEngagement] = useState(initialEngagement);
  const [offices, setOffices] = useState([]);
  const [areas, setAreas] = useState([]);
  const [focuses, setFocuses] = useState([]);
  const [form, setForm] = useState(() => toForm(initialEngagement));
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");

  useEffect(() => {
    let active = true;
    Promise.all([
      aemsEngagementApi.scope(engagementId),
      canEdit ? officeApi.list() : Promise.resolve([]),
      canEdit ? auditAreaApi.list() : Promise.resolve([]),
      canEdit ? auditFocusApi.list() : Promise.resolve([]),
    ])
      .then(([result, officeRecords, areaRecords, focusRecords]) => {
        if (!active) return;
        const record = result.scope ?? initialEngagement;
        setEngagement(record);
        setForm(toForm(record));
        setOffices(
          officeRecords.length ? officeRecords : (record?.offices ?? []),
        );
        setAreas(areaRecords.length ? areaRecords : (record?.auditAreas ?? []));
        setFocuses(
          focusRecords.length ? focusRecords : (record?.auditFocuses ?? []),
        );
      })
      .catch((reason) => active && setError(errorMessage(reason)))
      .finally(() => active && setLoading(false));
    return () => {
      active = false;
    };
  }, [canEdit, engagementId, initialEngagement]);

  const selectedAreaIds = useMemo(
    () => form.areaCoverage.map((item) => String(item.auditAreaId)),
    [form.areaCoverage],
  );
  const officeOptions = offices.map((office) => ({
    value: office.id,
    label: `${office.code} — ${office.name}`,
  }));
  const linkedAreas = useMemo(() => {
    if (!form.officeId) return [];

    return areas.filter((area) => {
      const linkedOfficeIds = Array.isArray(area.offices)
        ? area.offices.map((office) => String(office.id))
        : Array.isArray(area.officeIds)
          ? area.officeIds.map((officeId) => String(officeId))
          : Array.isArray(area.office_ids)
            ? area.office_ids.map((officeId) => String(officeId))
            : [];

      // Core also exposes the responsible office as a first-class linkage.
      // Treat it as coverage linkage when an older response omits the
      // many-to-many `offices` relation.
      const responsibleOfficeId =
        area.responsibleOfficeId ?? area.responsible_office_id;

      return (
        area.isActive !== false &&
        (linkedOfficeIds.includes(String(form.officeId)) ||
          String(responsibleOfficeId ?? "") === String(form.officeId))
      );
    });
  }, [areas, form.officeId]);
  const areaOptions = linkedAreas.map((area) => ({
    value: area.id,
    label: `${area.code} — ${area.name}`,
  }));
  const invalidFocusLinks = form.areaCoverage.flatMap((item) =>
    item.focusIds
      .filter((focusId) => {
        const focus = focuses.find(
          (candidate) => String(candidate.id) === String(focusId),
        );
        return !focus || String(focus.auditAreaId) !== String(item.auditAreaId);
      })
      .map((focusId) => ({ areaId: item.auditAreaId, focusId })),
  );

  function updateAreas(values) {
    const next = values.map((value) => {
      const existing = form.areaCoverage.find(
        (item) => String(item.auditAreaId) === String(value),
      );
      return (
        existing ?? {
          auditAreaId: Number(value),
          boundary: "",
          limitations: "",
          sourceVariance: "",
          objective: "",
          focusIds: [],
        }
      );
    });
    setForm((current) => ({ ...current, areaCoverage: next }));
  }

  function updateCoverage(areaId, key, value) {
    const normalizedValue =
      key === "focusIds"
        ? (Array.isArray(value) ? value : [])
            .map((focusId) => Number(focusId))
            .filter((focusId) => focusId > 0)
        : value;
    setForm((current) => ({
      ...current,
      areaCoverage: current.areaCoverage.map((item) =>
        String(item.auditAreaId) === String(areaId)
          ? { ...item, [key]: normalizedValue }
          : item,
      ),
    }));
  }

  async function save() {
    if (!form.officeId) {
      setError("Select one Engagement Office before saving the scope.");
      return;
    }
    if (!form.areaCoverage.length) {
      setError("Select at least one audit area linked to the selected office.");
      return;
    }
    setSaving(true);
    setError("");
    try {
      const payload = {
        ...form,
        areaCoverage: form.areaCoverage.map((item) => ({
          ...item,
          auditAreaId: Number(item.auditAreaId),
          focusIds: (Array.isArray(item.focusIds) ? item.focusIds : [])
            .map((focusId) => Number(focusId))
            .filter((focusId) => focusId > 0),
        })),
      };
      const updated = await aemsEngagementApi.updateScope(engagementId, payload);
      setEngagement(updated);
      setForm(toForm(updated));
      toast.success("Engagement scope saved with one-office coverage.");
    } catch (reason) {
      setError(errorMessage(reason));
    } finally {
      setSaving(false);
    }
  }

  if (loading) {
    return (
      <div className="grid min-h-56 place-items-center rounded-xl border border-slate-200 bg-white">
        <span className="h-8 w-8 animate-spin rounded-full border-2 border-slate-200 border-t-sky-700" />
      </div>
    );
  }

  const editable = canEdit && lifecycleEditableStatuses.has(engagement?.status);
  const officeState = engagement?.officeRule?.state;

  return (
    <section className="space-y-5" data-testid="aems-scr-212-scope-workspace">
      <header className="rounded-xl border border-sky-200 bg-sky-50/70 p-4 sm:p-5">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div className="flex min-w-0 items-start gap-3">
            <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-white text-sky-700 shadow-sm">
              <Building2 size={20} />
            </span>
            <div>
              <p className="text-xs font-bold uppercase tracking-wide text-sky-700">
                Engagement scope · Foundation
              </p>
              <h2 className="mt-1 text-lg font-bold text-slate-900">
                Define Engagement Scope
              </h2>
              <p className="mt-1 text-sm leading-6 text-slate-600">
                Select exactly one Engagement Office and preserve structured
                Area/Focus boundaries, limitations, and approved source
                variance.
              </p>
            </div>
          </div>
          <StatusBadge tone={officeState === "VALID" ? "active" : "warning"}>
            {officeState === "VALID"
              ? "Scope valid"
              : officeState === "MISSING"
                ? "Office required"
                : "Scope review required"}
          </StatusBadge>
        </div>
      </header>

      {error && (
        <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700">
          <AlertTriangle className="mr-2 inline" size={16} />
          {error}
        </div>
      )}

      <div className="grid gap-5 2xl:grid-cols-[minmax(0,1fr)_minmax(20rem,.55fr)]">
        <div className="space-y-5">
          <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <div className="mb-4 flex items-center gap-2">
              <ShieldCheck className="text-sky-700" size={18} />
              <h3 className="font-bold text-slate-800">One-office rule</h3>
            </div>
            <FormField
              label="Engagement Office"
              hint="Rule 1 requires exactly one auditee office for the engagement."
              required
            >
              <SearchableSelect
                disabled={!editable}
                onChange={(value) =>
                  setForm((current) => ({
                    ...current,
                    officeId: value,
                    // Area and focus coverage is office-dependent. Clear the
                    // previous selection when the office changes so stale
                    // links cannot be submitted against the new office.
                    areaCoverage:
                      String(current.officeId) === String(value)
                        ? current.areaCoverage
                        : [],
                  }))
                }
                options={officeOptions}
                placeholder="Select one Engagement Office"
                searchPlaceholder="Search office..."
                value={form.officeId}
              />
            </FormField>
            <p className="mt-3 text-xs leading-5 text-slate-500">
              Current record:{" "}
              {engagement?.offices?.[0]
                ? `${engagement.offices[0].code} — ${engagement.offices[0].name}`
                : "No office assigned"}
              . Legacy extra offices are retained in the backfill review trail
              and cannot be added to new scope.
            </p>
          </section>

          <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <h3 className="font-bold text-slate-800">Engagement boundaries</h3>
            <div className="mt-4 grid gap-4 md:grid-cols-2">
              <FormField label="Scope boundaries">
                <textarea
                  className={textareaClass}
                  disabled={!editable}
                  onChange={(event) =>
                    setForm((current) => ({
                      ...current,
                      scopeBoundaries: event.target.value,
                    }))
                  }
                  value={form.scopeBoundaries}
                />
              </FormField>
              <FormField label="Known limitations">
                <textarea
                  className={textareaClass}
                  disabled={!editable}
                  onChange={(event) =>
                    setForm((current) => ({
                      ...current,
                      scopeLimitations: event.target.value,
                    }))
                  }
                  value={form.scopeLimitations}
                />
              </FormField>
            </div>
            <div className="mt-4 grid gap-4 md:grid-cols-3">
              <FormField label="Source variance decision">
                <select
                  className={inputClass}
                  disabled={!editable}
                  onChange={(event) =>
                    setForm((current) => ({
                      ...current,
                      scopeSourceVariance: {
                        ...current.scopeSourceVariance,
                        decision: event.target.value,
                      },
                    }))
                  }
                  value={form.scopeSourceVariance.decision}
                >
                  <option value="ALIGNED">Aligned with approved source</option>
                  <option value="VARIANCE_APPROVED">Approved variance</option>
                  <option value="NOT_APPLICABLE">Not applicable</option>
                </select>
              </FormField>
              <FormField label="Variance authority">
                <input
                  className={inputClass}
                  disabled={!editable}
                  onChange={(event) =>
                    setForm((current) => ({
                      ...current,
                      scopeSourceVariance: {
                        ...current.scopeSourceVariance,
                        authority: event.target.value,
                      },
                    }))
                  }
                  value={form.scopeSourceVariance.authority}
                />
              </FormField>
              <FormField label="Variance explanation">
                <input
                  className={inputClass}
                  disabled={!editable}
                  onChange={(event) =>
                    setForm((current) => ({
                      ...current,
                      scopeSourceVariance: {
                        ...current.scopeSourceVariance,
                        explanation: event.target.value,
                      },
                    }))
                  }
                  value={form.scopeSourceVariance.explanation}
                />
              </FormField>
            </div>
          </section>

          <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <h3 className="font-bold text-slate-800">
              Area and Focus coverage
            </h3>
            <p className="mt-1 text-sm text-slate-500">
              Each in-scope Area has explicit boundaries and limitations.
              Focuses must belong to the selected Area.
            </p>
            {invalidFocusLinks.length > 0 && (
              <div className="mt-3 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                <strong>Scope integrity warning:</strong> one or more stored
                Focus links do not belong to their selected Area. Remove or
                correct these links before saving.
              </div>
            )}
            <div className="mt-4">
              <FormField label="In-scope Audit Areas" required>
                <SearchableSelect
                  disabled={!editable || !form.officeId}
                  emptyMessage="No audit areas are linked to the selected office."
                  multiple
                  onChange={updateAreas}
                  options={areaOptions}
                  placeholder={
                    form.officeId
                      ? "Select audit areas linked to this office"
                      : "Select an office first"
                  }
                  value={selectedAreaIds}
                />
              </FormField>
              {!form.officeId && (
                <p className="mt-2 text-xs font-medium text-amber-700">
                  Select the Engagement Office first. Only audit areas linked
                  to that office will be available.
                </p>
              )}
              {form.officeId && linkedAreas.length === 0 && (
                <p className="mt-2 text-xs font-medium text-amber-700">
                  No active audit areas are linked to the selected office.
                  Configure the office relationship in the Core Audit Area
                  Registry before continuing.
                </p>
              )}
              {form.officeId && linkedAreas.length > 0 && !form.areaCoverage.length && (
                <p className="mt-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs font-medium leading-5 text-amber-800">
                  Draft scopes are editable. Select at least one linked audit
                  area here before saving; audit focuses are optional and may
                  be added later.
                </p>
              )}
            </div>
            <div className="mt-5 space-y-4">
              {form.areaCoverage.map((item) => {
                const area = areas.find(
                  (candidate) =>
                    String(candidate.id) === String(item.auditAreaId),
                );
                const focusOptions = focuses
                  .filter(
                    (focus) =>
                      String(focus.auditAreaId) === String(item.auditAreaId),
                  )
                  .map((focus) => ({
                    value: focus.id,
                    label: `${focus.code} — ${focus.name}`,
                  }));
                return (
                  <article
                    className="rounded-xl border border-slate-200 bg-slate-50/70 p-4"
                    key={item.auditAreaId}
                  >
                    <div className="flex items-start justify-between gap-3">
                      <div>
                        <p className="text-xs font-bold uppercase tracking-wide text-sky-700">
                          {area?.code ?? "Area"}
                        </p>
                        <h4 className="font-bold text-slate-800">
                          {area?.name ?? `Audit Area ${item.auditAreaId}`}
                        </h4>
                      </div>
                      <CheckCircle2 className="text-emerald-600" size={18} />
                    </div>
                    <div className="mt-3">
                      <FormField label="Audit Focuses">
                        <SearchableSelect
                          disabled={!editable}
                          multiple
                          onChange={(value) =>
                            updateCoverage(item.auditAreaId, "focusIds", value)
                          }
                          options={focusOptions}
                          placeholder="Select focuses"
                          value={item.focusIds}
                        />
                      </FormField>
                    </div>
                    <div className="mt-3 grid gap-3 md:grid-cols-2">
                      <FormField label="Area objective">
                        <textarea
                          className={textareaClass}
                          disabled={!editable}
                          onChange={(event) =>
                            updateCoverage(
                              item.auditAreaId,
                              "objective",
                              event.target.value,
                            )
                          }
                          value={item.objective}
                        />
                      </FormField>
                      <FormField label="Area boundary">
                        <textarea
                          className={textareaClass}
                          disabled={!editable}
                          onChange={(event) =>
                            updateCoverage(
                              item.auditAreaId,
                              "boundary",
                              event.target.value,
                            )
                          }
                          value={item.boundary}
                        />
                      </FormField>
                      <FormField label="Area limitations">
                        <textarea
                          className={textareaClass}
                          disabled={!editable}
                          onChange={(event) =>
                            updateCoverage(
                              item.auditAreaId,
                              "limitations",
                              event.target.value,
                            )
                          }
                          value={item.limitations}
                        />
                      </FormField>
                      <FormField label="Area source variance">
                        <textarea
                          className={textareaClass}
                          disabled={!editable}
                          onChange={(event) =>
                            updateCoverage(
                              item.auditAreaId,
                              "sourceVariance",
                              event.target.value,
                            )
                          }
                          value={item.sourceVariance}
                        />
                      </FormField>
                    </div>
                  </article>
                );
              })}
              {!form.areaCoverage.length && (
                <p className="rounded-lg border border-dashed border-slate-300 p-5 text-center text-sm text-slate-500">
                  Select at least one audit area to define coverage.
                </p>
              )}
            </div>
          </section>
        </div>

        <aside className="space-y-5">
          <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 className="font-bold text-slate-800">Scope controls</h3>
            <dl className="mt-4 space-y-3 text-sm">
              <div className="flex justify-between gap-3">
                <dt className="text-slate-500">Status</dt>
                <dd className="font-semibold text-slate-800">
                  {engagement?.status}
                </dd>
              </div>
              <div className="flex justify-between gap-3">
                <dt className="text-slate-500">Lock version</dt>
                <dd className="font-semibold text-slate-800">
                  {engagement?.lockVersion}
                </dd>
              </div>
              <div className="flex justify-between gap-3">
                <dt className="text-slate-500">Office count</dt>
                <dd className="font-semibold text-slate-800">
                  {engagement?.officeRule?.actualCount ??
                    engagement?.offices?.length ??
                    0}{" "}
                  / 1
                </dd>
              </div>
              <div className="flex justify-between gap-3">
                <dt className="text-slate-500">Risk source</dt>
                <dd className="text-right font-semibold text-slate-800">
                  {engagement?.iapRiskSourceType ?? "Special authority"}
                </dd>
              </div>
            </dl>
          </section>
          {editable ? (
            <button
              className="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white shadow-sm transition hover:bg-sky-800 disabled:opacity-50"
              disabled={
                saving ||
                !form.officeId ||
                !form.areaCoverage.length ||
                invalidFocusLinks.length > 0
              }
              onClick={save}
              type="button"
            >
              <Save size={17} />
              {saving ? "Saving scope..." : "Save scope"}
            </button>
          ) : (
            <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900">
              <strong>Read-only scope</strong>
              <p className="mt-1">
                Scope changes are unavailable after authorization or when your
                permission does not include foundation scope management.
              </p>
            </div>
          )}
        </aside>
      </div>
    </section>
  );
}
