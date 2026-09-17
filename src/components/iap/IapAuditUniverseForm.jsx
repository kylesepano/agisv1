import { useMemo, useState } from "react";
import FormField from "../ui/FormField";
import SearchableSelect from "../ui/SearchableSelect";

const inputClass =
  "min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-sky-500 focus:ring-2 focus:ring-sky-100";

function firstError(errors, key) {
  return Array.isArray(errors?.[key]) ? errors[key][0] : "";
}

function initialForm(item) {
  return {
    subjectCode: item?.subjectCode ?? "",
    name: item?.name ?? "",
    subjectTypeId: item?.subjectTypeId ?? "",
    responsibleOfficeId: item?.responsibleOfficeId ?? "",
    primaryAuditAreaId: item?.primaryAuditAreaId ?? "",
    materialityLevelId: item?.materialityLevelId ?? "",
    description: item?.description ?? "",
    auditScope: item?.auditScope ?? "",
    materialityExposure: item?.materialityExposure ?? "",
    lastAuditDate: item?.lastAuditDate ?? "",
    historicalAuditSummary: item?.historicalAuditSummary ?? "",
    stakeholderOfficeIds:
      item?.stakeholderOffices?.map((office) => office.id) ?? [],
    isActive: item?.isActive ?? true,
    lockVersion: item?.lockVersion,
  };
}

export default function IapAuditUniverseForm({
  item,
  offices,
  subjectTypes,
  riskLevels,
  errors = {},
  formId = "audit-universe-form",
  onSubmit,
}) {
  const [form, setForm] = useState(() => initialForm(item));
  const selectedOffice = offices.find(
    (office) => String(office.id) === String(form.responsibleOfficeId),
  );

  const officeOptions = useMemo(
    () =>
      offices.map((office) => ({
        value: office.id,
        label: `${office.code} — ${office.name}`,
        description: office.sector,
        keywords: `${office.code} ${office.name} ${office.sector ?? ""}`,
      })),
    [offices],
  );
  const stakeholderOptions = officeOptions.filter(
    (option) => String(option.value) !== String(form.responsibleOfficeId),
  );
  const areaOptions = (selectedOffice?.auditAreas ?? []).map((area) => ({
    value: area.id,
    label: `${area.code} — ${area.name}`,
    description: area.description,
  }));

  function update(key, value) {
    setForm((current) => ({ ...current, [key]: value }));
  }

  return (
    <form
      className="grid gap-5"
      id={formId}
      onSubmit={(event) => {
        event.preventDefault();
        onSubmit({
          subjectCode: form.subjectCode.trim(),
          name: form.name.trim(),
          subjectTypeId: Number(form.subjectTypeId),
          responsibleOfficeId: Number(form.responsibleOfficeId),
          primaryAuditAreaId: Number(form.primaryAuditAreaId),
          materialityLevelId: form.materialityLevelId
            ? Number(form.materialityLevelId)
            : null,
          description: form.description.trim(),
          auditScope: form.auditScope.trim() || null,
          materialityExposure: form.materialityExposure.trim() || null,
          lastAuditDate: form.lastAuditDate || null,
          historicalAuditSummary: form.historicalAuditSummary.trim() || null,
          stakeholderOfficeIds: form.stakeholderOfficeIds.map(Number),
          isActive: form.isActive,
          ...(item ? { lockVersion: form.lockVersion } : {}),
        });
      }}
    >
      <section className="grid gap-4 rounded-xl border border-slate-200 bg-slate-50/70 p-4 sm:grid-cols-2">
        <div className="sm:col-span-2">
          <h3 className="text-sm font-bold text-slate-800">
            Auditable subject identity
          </h3>
          <p className="mt-1 text-xs leading-5 text-slate-500">
            Define the process, program, system, service, project, entity, fund,
            contract, asset, or cross-office activity that may be audited.
          </p>
        </div>
        <FormField
          error={firstError(errors, "subjectCode")}
          htmlFor="universe-code"
          label="Subject code"
          required
        >
          <input
            className={inputClass}
            id="universe-code"
            onChange={(event) =>
              update("subjectCode", event.target.value.toUpperCase())
            }
            placeholder="AU-REV-001"
            value={form.subjectCode}
          />
        </FormField>
        <FormField
          error={firstError(errors, "subjectTypeId")}
          label="Subject type"
          required
        >
          <SearchableSelect
            onChange={(value) => update("subjectTypeId", value)}
            options={subjectTypes.map((type) => ({
              value: type.id,
              label: type.label,
              description: type.description,
            }))}
            placeholder="Select a subject type"
            searchPlaceholder="Search subject types..."
            value={form.subjectTypeId}
          />
        </FormField>
        <div className="sm:col-span-2">
          <FormField
            error={firstError(errors, "name")}
            htmlFor="universe-name"
            label="Auditable subject name"
            required
          >
            <input
              className={inputClass}
              id="universe-name"
              onChange={(event) => update("name", event.target.value)}
              placeholder="Business Tax Assessment and Collection Process"
              value={form.name}
            />
          </FormField>
        </div>
        <div className="sm:col-span-2">
          <FormField
            error={firstError(errors, "description")}
            htmlFor="universe-description"
            label="Description"
            required
          >
            <textarea
              className={`${inputClass} min-h-28 py-3`}
              id="universe-description"
              onChange={(event) => update("description", event.target.value)}
              placeholder="Describe the auditable subject and its purpose."
              value={form.description}
            />
          </FormField>
        </div>
      </section>

      <section className="grid gap-4 sm:grid-cols-2">
        <div className="sm:col-span-2">
          <h3 className="text-sm font-bold text-slate-800">
            Ownership and classification
          </h3>
        </div>
        <FormField
          error={firstError(errors, "responsibleOfficeId")}
          label="Responsible office"
          required
        >
          <SearchableSelect
            onChange={(responsibleOfficeId) =>
              setForm((current) => ({
                ...current,
                responsibleOfficeId,
                primaryAuditAreaId: "",
                stakeholderOfficeIds: current.stakeholderOfficeIds.filter(
                  (id) => String(id) !== String(responsibleOfficeId),
                ),
              }))
            }
            options={officeOptions}
            placeholder="Select the responsible office"
            searchPlaceholder="Search offices..."
            value={form.responsibleOfficeId}
          />
        </FormField>
        <FormField
          error={firstError(errors, "primaryAuditAreaId")}
          label="Primary audit area"
          required
          hint="Only audit areas linked to the responsible office are shown."
        >
          <SearchableSelect
            disabled={!form.responsibleOfficeId}
            onChange={(value) => update("primaryAuditAreaId", value)}
            options={areaOptions}
            placeholder="Select a linked audit area"
            searchPlaceholder="Search linked audit areas..."
            value={form.primaryAuditAreaId}
          />
        </FormField>
        <div className="sm:col-span-2">
          <FormField
            error={firstError(errors, "stakeholderOfficeIds")}
            label={`Additional stakeholder offices (${form.stakeholderOfficeIds.length} selected)`}
            hint="These offices participate in, depend on, or are materially affected by the subject."
          >
            <SearchableSelect
              multiple
              multipleDisplay="summary"
              onChange={(value) => update("stakeholderOfficeIds", value)}
              options={stakeholderOptions}
              placeholder="Select stakeholder offices"
              searchPlaceholder="Search stakeholder offices..."
              value={form.stakeholderOfficeIds}
            />
          </FormField>
        </div>
      </section>

      <section className="grid gap-4 rounded-xl border border-amber-200 bg-amber-50/50 p-4 sm:grid-cols-2">
        <div className="sm:col-span-2">
          <h3 className="text-sm font-bold text-slate-800">
            Exposure and audit coverage
          </h3>
        </div>
        <FormField
          error={firstError(errors, "materialityLevelId")}
          label="Materiality / exposure level"
        >
          <SearchableSelect
            onChange={(value) => update("materialityLevelId", value)}
            options={riskLevels.map((level) => ({
              value: level.id,
              label: level.label,
              description: level.description,
            }))}
            placeholder="Select exposure level"
            searchPlaceholder="Search exposure levels..."
            value={form.materialityLevelId}
          />
        </FormField>
        <FormField
          error={firstError(errors, "lastAuditDate")}
          htmlFor="universe-last-audit"
          label="Last audit date"
          hint="Leave blank when no reliable audit history is available."
        >
          <input
            className={inputClass}
            id="universe-last-audit"
            max={new Date().toISOString().slice(0, 10)}
            onChange={(event) => update("lastAuditDate", event.target.value)}
            type="date"
            value={form.lastAuditDate}
          />
        </FormField>
        <div className="sm:col-span-2">
          <FormField
            error={firstError(errors, "materialityExposure")}
            htmlFor="universe-exposure"
            label="Materiality and exposure"
          >
            <textarea
              className={`${inputClass} min-h-24 py-3`}
              id="universe-exposure"
              onChange={(event) =>
                update("materialityExposure", event.target.value)
              }
              placeholder="Describe financial, service, regulatory, safety, data, asset, or reputational exposure."
              value={form.materialityExposure}
            />
          </FormField>
        </div>
        <div className="sm:col-span-2">
          <FormField
            error={firstError(errors, "auditScope")}
            htmlFor="universe-scope"
            label="Indicative audit scope"
          >
            <textarea
              className={`${inputClass} min-h-24 py-3`}
              id="universe-scope"
              onChange={(event) => update("auditScope", event.target.value)}
              placeholder="Identify the boundaries, components, locations, systems, or transactions normally included."
              value={form.auditScope}
            />
          </FormField>
        </div>
        <div className="sm:col-span-2">
          <FormField
            error={firstError(errors, "historicalAuditSummary")}
            htmlFor="universe-history-summary"
            label="Historical audit summary"
          >
            <textarea
              className={`${inputClass} min-h-24 py-3`}
              id="universe-history-summary"
              onChange={(event) =>
                update("historicalAuditSummary", event.target.value)
              }
              placeholder="Summarize known prior assurance coverage and outstanding context."
              value={form.historicalAuditSummary}
            />
          </FormField>
        </div>
      </section>

      <label className="flex items-center gap-2 text-sm font-semibold text-slate-700">
        <input
          checked={form.isActive}
          onChange={(event) => update("isActive", event.target.checked)}
          type="checkbox"
        />
        Active auditable subject
      </label>
    </form>
  );
}
