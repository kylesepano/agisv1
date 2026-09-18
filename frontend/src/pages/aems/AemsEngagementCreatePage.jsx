import { useEffect, useMemo, useState } from "react";
import {
  ArrowLeft,
  CalendarDays,
  ChevronDown,
  Info,
  ShieldCheck,
  Target,
} from "lucide-react";
import { useNavigate, useSearchParams } from "react-router";
import {
  aemsEngagementApi,
  ApiError,
  auditAreaApi,
  masterListApi,
  officeApi,
  userApi,
} from "../../services/api";
import SearchableSelect from "../../components/ui/SearchableSelect";
import { useToast } from "../../ui/toast-context";

const inputClass =
  "h-10 w-full rounded border border-[#b7d1e5] bg-white px-3 text-sm text-slate-700 outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100 disabled:bg-[#eaf3fa] disabled:text-slate-500";

function Card({ icon: Icon, title, children }) {
  return (
    <section className="relative overflow-visible rounded-md border border-[#73b6d6] bg-[#e3f1fc]/65">
      <header className="flex items-center gap-3 border-b border-[#73b6d6] bg-[#d5e9f8] px-5 py-3 text-[#10389a]">
        <Icon size={27} />
        <h3 className="text-lg font-semibold">{title}</h3>
      </header>
      <div className="space-y-3 px-6 py-5">{children}</div>
    </section>
  );
}

function Field({ label, children, error }) {
  return (
    <label className="grid min-w-0 gap-2 text-sm text-[#123890] sm:grid-cols-[9.5rem_minmax(0,1fr)] sm:items-center">
      <span className="text-right">{label}</span>
      <span className="min-w-0">
        {children}
        {error && <small className="mt-1 block text-red-600">{error}</small>}
      </span>
    </label>
  );
}

function listItems(lists, code) {
  return (
    lists.find((list) => list.code === code)?.items?.filter(
      (item) => item.isActive && !item.isArchived,
    ) ?? []
  );
}

function dateLabel(value) {
  if (!value) return "—";
  return new Intl.DateTimeFormat("en-PH", {
    month: "short",
    day: "numeric",
    year: "numeric",
  }).format(new Date(`${value.slice(0, 10)}T00:00:00`));
}

function inclusiveDays(start, end) {
  if (!start || !end) return 0;
  const value = Math.floor(
    (new Date(`${end}T00:00:00`) - new Date(`${start}T00:00:00`)) /
      86400000,
  );
  return value >= 0 ? value + 1 : 0;
}

export default function AemsEngagementCreatePage() {
  const navigate = useNavigate();
  const toast = useToast();
  const [searchParams, setSearchParams] = useSearchParams();
  const engagementId = searchParams.get("engagementId");
  const isEditing = Boolean(engagementId);
  const source = searchParams.get("source") === "unplanned" ? "unplanned" : "planned";
  const [sources, setSources] = useState([]);
  const [offices, setOffices] = useState([]);
  const [areas, setAreas] = useState([]);
  const [users, setUsers] = useState([]);
  const [masterLists, setMasterLists] = useState([]);
  const [selectedSourceId, setSelectedSourceId] = useState("");
  const [existingEngagement, setExistingEngagement] = useState(null);
  const [saving, setSaving] = useState(false);
  const [loading, setLoading] = useState(true);
  const [errors, setErrors] = useState({});
  const [form, setForm] = useState({
    authorityType: "WRITTEN_INSTRUCTION",
    authorityReference: "",
    directingAuthorityId: "",
    requestingOfficeId: "",
    dateReceived: "",
    supportingDocument: null,
    title: "",
    officeId: "",
    auditTypeId: "",
    auditYear: String(new Date().getFullYear()),
    auditAreaIds: [],
    auditFocusIds: [],
    objective: "",
    periodStart: "",
    periodEnd: "",
    plannedStart: "",
    plannedEnd: "",
    lockVersion: null,
  });

  useEffect(() => {
    let active = true;
    Promise.all([
      aemsEngagementApi.importOptions(),
      officeApi.list(),
      auditAreaApi.list(),
      userApi.list(),
      masterListApi.list(),
      isEditing ? aemsEngagementApi.show(engagementId) : Promise.resolve(null),
    ])
      .then(([iapSources, officeRows, areaRows, people, lists, record]) => {
        if (!active) return;
        setSources(iapSources);
        setOffices(officeRows);
        setAreas(areaRows);
        setUsers(people);
        setMasterLists(lists);
        if (record) {
          setExistingEngagement(record);
          setForm((current) => ({
            ...current,
            authorityType: record.specialAuthorityTypeCode ?? "WRITTEN_INSTRUCTION",
            authorityReference: record.specialAuthorityReference ?? "",
            directingAuthorityId: record.specialAuthorityApprovedBy ?? "",
            requestingOfficeId: record.requestingOfficeId ?? "",
            dateReceived: record.specialAuthorityReceivedDate ?? record.specialAuthorityDate ?? "",
            title: record.title ?? "",
            officeId: record.offices?.[0]?.id ?? record.engagementOfficeId ?? "",
            auditTypeId: record.auditTypeId ?? "",
            auditYear: String(record.auditYear ?? new Date().getFullYear()),
            auditAreaIds: (record.auditAreas ?? []).map((area) => area.id),
            auditFocusIds: (record.auditFocuses ?? []).map((focus) => focus.id),
            objective: record.objectives ?? "",
            periodStart: record.periodCoveredStartDate ?? "",
            periodEnd: record.periodCoveredEndDate ?? "",
            plannedStart: record.plannedStartDate ?? "",
            plannedEnd: record.plannedEndDate ?? "",
            lockVersion: record.lockVersion,
          }));
        }
      })
      .catch((reason) =>
        active && setErrors({ form: [reason.message || "Unable to load form options."] }),
      )
      .finally(() => active && setLoading(false));
    return () => {
      active = false;
    };
  }, [engagementId, isEditing]);

  const selected = useMemo(
    () => sources.find((item) => String(item.id) === String(selectedSourceId)),
    [selectedSourceId, sources],
  );
  const selectedForDisplay = isEditing
    ? {
        ...existingEngagement,
        plan: existingEngagement?.sourceSnapshot?.plan ?? {},
      }
    : selected;
  const auditTypes = listItems(masterLists, "IAP_ENGAGEMENT_TYPE");
  const duration = inclusiveDays(form.plannedStart, form.plannedEnd);
  const officeOptions = offices.map((office) => ({
    value: office.id,
    label: office.name,
    description: office.code,
    keywords: `${office.code ?? ""} ${office.name}`,
  }));
  const selectableAreas = useMemo(
    () =>
      form.officeId
        ? areas.filter(
            (area) =>
              area.isActive &&
              !area.isArchived &&
              area.offices?.some(
                (office) => String(office.id) === String(form.officeId),
              ),
          )
        : [],
    [areas, form.officeId],
  );
  const selectableFocuses = useMemo(
    () =>
      selectableAreas
        .filter((area) =>
          form.auditAreaIds.some((id) => String(id) === String(area.id)),
        )
        .flatMap((area) =>
          (area.focuses ?? []).filter(
            (focus) => focus.isActive && !focus.isArchived,
          ),
        ),
    [form.auditAreaIds, selectableAreas],
  );
  const areaOptions = selectableAreas.map((area) => ({
    value: area.id,
    label: area.name,
    description: area.code,
    keywords: `${area.code ?? ""} ${area.name}`,
  }));
  const focusOptions = selectableFocuses.map((focus) => ({
    value: focus.id,
    label: focus.name,
    description: focus.code,
    keywords: `${focus.code ?? ""} ${focus.name}`,
  }));
  const set = (key, value) => setForm((current) => ({ ...current, [key]: value }));

  function changeSource(next) {
    setSearchParams({ source: next });
    setErrors({});
  }

  function changeOffice(officeId) {
    const availableAreaIds = new Set(
      areas
        .filter((area) =>
          area.offices?.some(
            (office) => String(office.id) === String(officeId),
          ),
        )
        .map((area) => String(area.id)),
    );
    setForm((current) => {
      const auditAreaIds = current.auditAreaIds.filter((id) =>
        availableAreaIds.has(String(id)),
      );
      const auditFocusIds = current.auditFocusIds.filter((focusId) =>
        areas.some(
          (area) =>
            auditAreaIds.some((areaId) => String(areaId) === String(area.id)) &&
            area.focuses?.some(
              (focus) => String(focus.id) === String(focusId),
            ),
        ),
      );
      return { ...current, officeId, auditAreaIds, auditFocusIds };
    });
  }

  function changeAuditAreas(auditAreaIds) {
    setForm((current) => ({
      ...current,
      auditAreaIds,
      auditFocusIds: current.auditFocusIds.filter((focusId) =>
        areas.some(
          (area) =>
            auditAreaIds.some((areaId) => String(areaId) === String(area.id)) &&
            area.focuses?.some(
              (focus) => String(focus.id) === String(focusId),
            ),
        ),
      ),
    }));
  }

  async function submit(intent) {
    setSaving(true);
    setErrors({});
    try {
      let engagement;
      if (isEditing) {
        engagement = await aemsEngagementApi.update(engagementId, {
          title: form.title,
          specialAuthorityReference: form.authorityReference,
          specialAuthorityTypeCode: form.authorityType,
          specialAuthorityClass: "SPECIAL",
          specialAuthorityDate: form.dateReceived,
          specialAuthorityReceivedDate: form.dateReceived,
          specialAuthorityApprovedBy: form.directingAuthorityId || null,
          requestingOfficeId: form.requestingOfficeId || null,
          auditTypeId: form.auditTypeId || null,
          auditYear: form.auditYear,
          objectives: form.objective,
          scope: "",
          periodCoveredStartDate: form.periodStart || null,
          periodCoveredEndDate: form.periodEnd || null,
          plannedStartDate: form.plannedStart,
          plannedEndDate: form.plannedEnd,
          plannedPersonDays: Math.max(1, duration),
          officeIds: form.officeId ? [form.officeId] : [],
          auditAreaIds: form.auditAreaIds,
          auditFocusIds: form.auditFocusIds,
          lockVersion: form.lockVersion,
        });
      } else if (source === "planned") {
        if (!selectedSourceId) {
          setErrors({ iapPlanEngagementId: ["Select an approved IAP item."] });
          return;
        }
        engagement = await aemsEngagementApi.importFromIap({
          iapPlanEngagementId: Number(selectedSourceId),
        });
      } else {
        const payload = new FormData();
        const values = {
          title: form.title,
          specialAuthorityReference: form.authorityReference,
          specialAuthorityTypeCode: form.authorityType,
          specialAuthorityClass: "SPECIAL",
          specialAuthorityDate: form.dateReceived,
          specialAuthorityReceivedDate: form.dateReceived,
          specialAuthorityApprovedBy: form.directingAuthorityId,
          requestingOfficeId: form.requestingOfficeId,
          auditTypeId: form.auditTypeId,
          auditYear: form.auditYear,
          objectives: form.objective,
          scope: "",
          periodCoveredStartDate: form.periodStart,
          periodCoveredEndDate: form.periodEnd,
          plannedStartDate: form.plannedStart,
          plannedEndDate: form.plannedEnd,
          plannedPersonDays: Math.max(1, duration),
        };
        Object.entries(values).forEach(([key, value]) => {
          if (value !== "" && value !== null && value !== undefined) {
            payload.append(key, value);
          }
        });
        if (form.officeId) payload.append("officeIds[]", form.officeId);
        form.auditAreaIds.forEach((id) => payload.append("auditAreaIds[]", id));
        form.auditFocusIds.forEach((id) => payload.append("auditFocusIds[]", id));
        if (form.supportingDocument) {
          payload.append("supportingDocument", form.supportingDocument);
        }
        engagement = await aemsEngagementApi.createSpecial(payload);
      }
      toast.success(
        `${engagement.engagementCode} was ${isEditing ? "updated" : "created successfully"}.`,
      );
      navigate(
        !isEditing && intent === "draft"
          ? "/audit-engagement-management"
          : `/audit-engagement-management/${engagement.id}`,
      );
    } catch (reason) {
      if (reason instanceof ApiError) setErrors(reason.errors ?? {});
      setErrors((current) => ({
        ...current,
        form: [reason instanceof Error ? reason.message : "Unable to create engagement."],
      }));
    } finally {
      setSaving(false);
    }
  }

  return (
    <main className="min-w-0 bg-[#eef7fa] px-5 py-5 text-[#10389a] sm:px-8">
      <div className="mb-5 text-sm text-sky-700">
        Home <span className="mx-2 text-slate-400">›</span> Audit Engagements{" "}
        <span className="mx-2 text-slate-400">›</span> {isEditing ? "Edit Audit Engagement" : "Create Audit Engagement"}
      </div>

      <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
          <div className="flex flex-wrap items-center gap-4">
            <h2 className="text-3xl font-semibold tracking-tight text-[#073b9b] sm:text-[2.35rem]">
              {isEditing ? "Edit Audit Engagement" : "Create Audit Engagement"}
            </h2>
            <span className="text-sm text-red-500">
              [{source === "planned" ? "WF-SCR-200-02A" : "WF-SCR-200-02B"}]
            </span>
          </div>
          <p className="mt-1 text-sm text-[#154da8]">
            Encode the basic information to create a new audit engagement. Detailed planning information will be completed in the Planning workspace.
          </p>
        </div>
        <div className="flex flex-wrap gap-3">
          <button className="inline-flex h-11 items-center gap-2 rounded-md border border-slate-400 bg-white px-4 text-slate-700" onClick={() => navigate("/audit-engagement-management")} type="button"><ArrowLeft size={17} /> Back to AEM Workspace</button>
          <button className="h-11 rounded-md border border-slate-400 bg-white px-5 text-slate-700 disabled:opacity-50" disabled={saving} onClick={() => isEditing ? navigate(`/audit-engagement-management/${engagementId}`) : submit("draft")} type="button">{isEditing ? "Cancel" : "Save Draft"}</button>
          <button className="h-11 rounded-md bg-[#087bea] px-6 font-semibold text-white disabled:opacity-50" disabled={saving || loading} onClick={() => submit("create")} type="button">{saving ? "Saving..." : isEditing ? "Save Changes" : "Create Audit Engagement"}</button>
          <button className="inline-flex h-11 items-center gap-2 rounded-md bg-[#198ff0] px-5 text-white" type="button">More Actions <ChevronDown size={16} /></button>
        </div>
      </div>

      {errors.form?.[0] && <div className="mb-4 rounded border border-red-300 bg-red-50 p-3 text-sm text-red-700">{errors.form[0]}</div>}

      <div className="grid items-start gap-5 xl:grid-cols-[minmax(32rem,1.25fr)_minmax(27rem,1fr)]">
        <div className="space-y-5">
          <Card icon={Info} title="Engagement Source">
            <Field label="Engagement Source">
              <SearchableSelect
                disabled={isEditing}
                onChange={changeSource}
                options={[
                  { value: "planned", label: "Internal Audit Planning (IAP)" },
                  { value: "unplanned", label: "Authorized Unplanned Engagement" },
                ]}
                value={source}
              />
            </Field>

            {source === "planned" ? (
              <>
                <Field label="Plan Reference" error={errors.iapPlanEngagementId?.[0]}>
                  <select className={inputClass} onChange={(event) => setSelectedSourceId(event.target.value)} value={selectedSourceId}>
                    <option value="">Select an eligible approved IAP item</option>
                    {sources.map((item) => <option key={item.id} value={item.id}>{item.plan.planCode} — {item.engagementCode} — {item.title}</option>)}
                  </select>
                </Field>
                <Field label="Approved By"><input className={inputClass} disabled value={selectedForDisplay?.plan?.approvedBy?.name ?? ""} /></Field>
                <Field label="Approval Date"><input className={inputClass} disabled value={dateLabel(selectedForDisplay?.plan?.approvedAt ?? "")} /></Field>
              </>
            ) : (
              <>
                <Field label="Authority Type" error={errors.specialAuthorityTypeCode?.[0]}>
                  <SearchableSelect
                    onChange={(value) => set("authorityType", value)}
                    options={[
                      { value: "WRITTEN_INSTRUCTION", label: "Written Instruction" },
                      { value: "AUTHORIZED_REQUEST", label: "Authorized Request" },
                      { value: "OTHER_AUTHORIZED_BASIS", label: "Other Authorized Basis" },
                    ]}
                    value={form.authorityType}
                  />
                </Field>
                <Field label="Authority Reference" error={errors.specialAuthorityReference?.[0]}><input className={inputClass} onChange={(event) => set("authorityReference", event.target.value)} placeholder="MO-2026-015" value={form.authorityReference} /></Field>
                <Field label="Directing Authority" error={errors.specialAuthorityApprovedBy?.[0]}>
                  <SearchableSelect
                    onChange={(value) => set("directingAuthorityId", value)}
                    options={users.map((person) => ({
                      value: person.id,
                      label: person.name,
                      description: person.employeeId,
                      keywords: `${person.employeeId ?? ""} ${person.name}`,
                    }))}
                    placeholder="Select directing authority"
                    value={form.directingAuthorityId}
                  />
                </Field>
                <Field label="Requesting Office" error={errors.requestingOfficeId?.[0]}>
                  <SearchableSelect
                    onChange={(value) => set("requestingOfficeId", value)}
                    options={officeOptions}
                    placeholder="Select requesting office"
                    value={form.requestingOfficeId}
                  />
                </Field>
                <Field label="Date Received" error={errors.specialAuthorityDate?.[0]}><input className={inputClass} onChange={(event) => set("dateReceived", event.target.value)} type="date" value={form.dateReceived} /></Field>
                <Field label="Supporting Doc" error={errors.supportingDocument?.[0]}>
                  <input accept=".pdf,.doc,.docx" className={`${inputClass} h-auto py-2`} onChange={(event) => set("supportingDocument", event.target.files?.[0] ?? null)} type="file" />
                </Field>
              </>
            )}
          </Card>

          <Card icon={Target} title="Initial Scope">
            <Field label="Audit Area(s)" error={errors.auditAreaIds?.[0]}>
              {source === "planned" ? (
                <div className="flex min-h-10 flex-wrap gap-2 rounded border border-[#b7d1e5] bg-white p-2">
                  {(selectedForDisplay?.auditAreas ?? []).map((area) => <span className="rounded bg-[#d7eafb] px-2 py-1 text-xs text-slate-700" key={area.id}>{area.name}</span>)}
                  {!selectedForDisplay && <span className="text-sm text-slate-400">Select a plan reference</span>}
                </div>
              ) : (
                <SearchableSelect
                  disabled={!form.officeId}
                  emptyMessage="No audit areas are linked to the selected office."
                  multiple
                  onChange={changeAuditAreas}
                  options={areaOptions}
                  placeholder={form.officeId ? "Search and select audit area(s)" : "Select the engagement office first"}
                  value={form.auditAreaIds}
                />
              )}
            </Field>
            <Field label="Audit Focus(es)" error={errors.auditFocusIds?.[0]}>
              {source === "planned" ? (
                <div className="flex min-h-10 flex-wrap gap-2 rounded border border-[#b7d1e5] bg-white p-2">
                  {(selectedForDisplay?.auditFocuses ?? []).map((focus) => <span className="rounded bg-[#d7eafb] px-2 py-1 text-xs text-slate-700" key={focus.id}>{focus.name}</span>)}
                  {!selectedForDisplay && <span className="text-sm text-slate-400">Select a plan reference</span>}
                  {selectedForDisplay && selectedForDisplay.auditFocuses?.length === 0 && <span className="text-sm text-slate-400">No audit focus was defined in this IAP item</span>}
                </div>
              ) : (
                <SearchableSelect
                  disabled={form.auditAreaIds.length === 0}
                  emptyMessage="No audit focuses are available for the selected audit area(s)."
                  multiple
                  onChange={(value) => set("auditFocusIds", value)}
                  options={focusOptions}
                  placeholder={form.auditAreaIds.length ? "Search and select audit focus(es)" : "Select audit area(s) first"}
                  value={form.auditFocusIds}
                />
              )}
            </Field>
            <Field label="Initial Objective" error={errors.objectives?.[0]}><textarea className={`${inputClass} h-20 py-2`} disabled={source === "planned"} onChange={(event) => set("objective", event.target.value)} value={source === "planned" ? selectedForDisplay?.objectives ?? "" : form.objective} /></Field>
            <Field label="Period Covered">
              <div className="grid grid-cols-[1fr_auto_1fr] items-center gap-2"><input className={inputClass} disabled={source === "planned"} onChange={(event) => set("periodStart", event.target.value)} type="date" value={source === "planned" ? selectedForDisplay?.plan?.periodStart ?? selectedForDisplay?.periodCoveredStartDate ?? "" : form.periodStart} /><span>to</span><input className={inputClass} disabled={source === "planned"} onChange={(event) => set("periodEnd", event.target.value)} type="date" value={source === "planned" ? selectedForDisplay?.plan?.periodEnd ?? selectedForDisplay?.periodCoveredEndDate ?? "" : form.periodEnd} /></div>
            </Field>
          </Card>

          <Card icon={CalendarDays} title="Initial Schedule">
            <Field label="Start & End Date" error={errors.plannedStartDate?.[0] ?? errors.plannedEndDate?.[0]}>
              <div className="grid grid-cols-[1fr_auto_1fr] items-center gap-2"><input className={inputClass} disabled={source === "planned"} onChange={(event) => set("plannedStart", event.target.value)} type="date" value={source === "planned" ? selectedForDisplay?.plannedStartDate ?? "" : form.plannedStart} /><span>to</span><input className={inputClass} disabled={source === "planned"} onChange={(event) => set("plannedEnd", event.target.value)} type="date" value={source === "planned" ? selectedForDisplay?.plannedEndDate ?? "" : form.plannedEnd} /></div>
            </Field>
            <Field label="Planned Duration"><input className={inputClass} disabled value={`${source === "planned" ? inclusiveDays(selectedForDisplay?.plannedStartDate, selectedForDisplay?.plannedEndDate) : duration || 0} days`} /></Field>
          </Card>
        </div>

        <Card icon={ShieldCheck} title="Engagement Identity">
          <Field label="Engagement Title" error={errors.title?.[0]}><input className={inputClass} disabled={source === "planned"} onChange={(event) => set("title", event.target.value)} value={source === "planned" ? selectedForDisplay?.title ?? "" : form.title} /></Field>
          <Field label="Office" error={errors.officeIds?.[0]}>
            {source === "planned" ? <input className={inputClass} disabled value={selectedForDisplay?.offices?.[0]?.name ?? ""} /> : <SearchableSelect onChange={changeOffice} options={officeOptions} placeholder="Select engagement office" value={form.officeId} />}
          </Field>
          <Field label="Audit Type" error={errors.auditTypeId?.[0]}>
            {source === "planned" ? <input className={inputClass} disabled value={selectedForDisplay?.auditType?.label ?? ""} /> : <SearchableSelect onChange={(value) => set("auditTypeId", value)} options={auditTypes.map((item) => ({ value: item.id, label: item.label, description: item.code, keywords: `${item.code ?? ""} ${item.label}` }))} placeholder="Select audit type" value={form.auditTypeId} />}
          </Field>
          <Field label="Audit Year"><input className={inputClass} disabled={source === "planned"} max="2200" min="2000" onChange={(event) => set("auditYear", event.target.value)} type="number" value={source === "planned" ? selectedForDisplay?.plan?.fiscalYear ?? selectedForDisplay?.auditYear ?? "" : form.auditYear} /></Field>
        </Card>
      </div>
    </main>
  );
}
