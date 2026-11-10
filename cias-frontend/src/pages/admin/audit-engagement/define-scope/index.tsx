import { useMemo, useState } from "react";
import {
  ArrowLeft,
  ArrowRight,
  Lock,
  RotateCcw,
  Save,
} from "lucide-react";
import { AdminLayout } from "../../../../layouts/admin/AdminLayout";
import { Button } from "../../../../components/ui/button";
import { useToast } from "../../../../components/ui/toast";
import { navigateTo } from "../../../../routes";
import {
  AuditAreaSelector,
  AuditFocusSelector,
  ScopeOfficeSelector,
  ScopeParametersSection,
  ScopeSystemControlsCard,
} from "./components";
import {
  defaultScopeFormState,
} from "./define-scope-data";
import { defineScopeSchema } from "./define-scope-schema";
import type { DefineScopeFormState } from "./define-scope-types";
import { localStore } from "../../../../lib/localStore";
import { evaluateEngagementGating } from "../../../../lib/engagementPhaseGating";
import { approvedIapItems } from "../create/create-engagement-data";

export function DefineScopePage() {
  const { showToast } = useToast();

  // Extract engagement ID from pathname e.g. /admin/audit-engagement/AEMS-2025-001/define-scope
  const currentPath = window.location.pathname;
  const pathParts = currentPath.split("/");
  const engagementParamId = pathParts[3] || "AEMS-2026-010";

  // Resolve matching engagement from localStore
  const existingEngagement = useMemo(() => {
    return localStore.getEngagement(engagementParamId);
  }, [engagementParamId]);

  // Resolve matching IAP item if planned
  const matchingIap = useMemo(() => {
    const planRef = existingEngagement?.annualPlanRef?.trim();
    if (planRef && planRef !== "N/A" && planRef.length > 0) {
      return (
        approvedIapItems.find(
          (i) =>
            i.id.toLowerCase() === planRef.toLowerCase() ||
            i.code.toLowerCase() === planRef.toLowerCase(),
        ) || null
      );
    }
    return null;
  }, [existingEngagement]);

  // Initialize Form State
  const [formState, setFormState] = useState<DefineScopeFormState>(() => {
    const savedScope = localStore.getScopeState(engagementParamId);
    if (savedScope) return savedScope;

    if (existingEngagement) {
      const isSeededSample = existingEngagement.code === "AEMS-2026-010";
      const planRef = existingEngagement.annualPlanRef?.trim();
      const isPlanned = Boolean(
        planRef &&
        planRef !== "N/A" &&
        planRef.length > 0 &&
        (planRef.startsWith("IAP-") || planRef.startsWith("AAP-") || isSeededSample),
      );
      const originType: "Planned" | "Unplanned" = isPlanned ? "Planned" : "Unplanned";
      const iapSourceId = isPlanned ? planRef || "" : "";
      const iapOffice = isPlanned ? (matchingIap?.office || existingEngagement.office || "") : "";
      const office = existingEngagement.office || (isPlanned ? iapOffice : "");

      // Match default areas for office
      const allAreas = localStore.getAuditAreas();
      const allFocuses = localStore.getAuditFocuses();
      const officeAreas = office
        ? allAreas.filter(
            (a) =>
              (a.officeName || "").toLowerCase().trim() === office.toLowerCase().trim() ||
              (a.officeId || "").toLowerCase().trim() === office.toLowerCase().trim(),
          )
        : [];
      const defaultAreaIds: string[] = isSeededSample
        ? ["AA-001"]
        : officeAreas.length > 0
        ? [officeAreas[0].auditAreaId || officeAreas[0].code || officeAreas[0].id || ""]
        : [];

      const defaultFocusIds: string[] = isSeededSample
        ? ["AF-001"]
        : allFocuses
            .filter((f) => defaultAreaIds.includes(f.auditAreaId))
            .map((f) => f.auditFocusId || f.code || f.id || "");

      return {
        engagementNumber: existingEngagement.code,
        engagementTitle: existingEngagement.auditArea || existingEngagement.subject || "",
        auditType: existingEngagement.auditType || existingEngagement.category || "Compliance Audit",
        originType: originType,
        iapSourceId: iapSourceId,
        iapOffice: iapOffice,
        office: office,
        isOfficeChangedFromIap: false,
        sourceVarianceReason: "",
        selectedAuditAreaIds: defaultAreaIds,
        coverageNotes: isSeededSample
          ? { [defaultAreaIds[0] || "AA-001"]: existingEngagement.scope || "Covers all public bidding and procurement transactions for FY 2025." }
          : {},
        selectedAuditFocusIds: defaultFocusIds,
        objectives: existingEngagement.objective || "",
        boundaries: isSeededSample ? defaultScopeFormState.boundaries : "",
        exclusions: isSeededSample ? defaultScopeFormState.exclusions : "",
        knownLimitations: isSeededSample ? defaultScopeFormState.knownLimitations : "",
        phase: "Engagement Initiation",
        status: "Draft",
      };
    }

    return {
      ...defaultScopeFormState,
      engagementNumber: engagementParamId,
      originType: "Unplanned",
      iapSourceId: "",
      iapOffice: "",
      office: "",
      isOfficeChangedFromIap: false,
      sourceVarianceReason: "",
      selectedAuditAreaIds: [],
      coverageNotes: {},
      selectedAuditFocusIds: [],
      objectives: "",
      boundaries: "",
      exclusions: "",
      knownLimitations: "",
    };
  });

  const [touched, setTouched] = useState<Record<string, boolean>>({});
  const [isSubmitted, setIsSubmitted] = useState(false);

  // Office change handler with automatic IAP variance tracking (Planned only)
  const handleChangeOffice = (newOffice: string) => {
    const isChanged =
      formState.originType === "Planned" &&
      Boolean(formState.iapOffice) &&
      formState.iapOffice.trim().length > 0 &&
      newOffice.toLowerCase().trim() !== formState.iapOffice.toLowerCase().trim();

    // Auto-select first matching audit area for the newly selected office
    const allAreas = localStore.getAuditAreas();
    const newOfficeAreas = newOffice && newOffice.trim().length > 0
      ? allAreas.filter(
          (a) =>
            (a.officeName || "").toLowerCase().trim() === newOffice.toLowerCase().trim() ||
            (a.officeId || "").toLowerCase().trim() === newOffice.toLowerCase().trim(),
        )
      : [];
    const newAreaIds: string[] = newOfficeAreas.length > 0
      ? [newOfficeAreas[0].auditAreaId || newOfficeAreas[0].code || newOfficeAreas[0].id || ""]
      : [];

    setFormState((prev) => ({
      ...prev,
      office: newOffice,
      isOfficeChangedFromIap: isChanged,
      sourceVarianceReason: isChanged ? prev.sourceVarianceReason : "",
      selectedAuditAreaIds: newAreaIds,
      coverageNotes: {},
      selectedAuditFocusIds: [],
    }));

    setTouched((prev) => ({
      ...prev,
      office: true,
    }));
  };

  const handleResetToIapOffice = () => {
    if (formState.iapOffice) {
      handleChangeOffice(formState.iapOffice);
      setFormState((prev) => ({
        ...prev,
        sourceVarianceReason: "",
        isOfficeChangedFromIap: false,
      }));
      showToast({
        title: "Office Reset to IAP Source",
        description: `Restored auditee office to ${formState.iapOffice}.`,
        tone: "info",
      });
    }
  };

  // Toggle Audit Area (Strictly scoped to current office)
  const handleToggleArea = (areaId: string) => {
    setFormState((prev) => {
      // Validate that the area belongs to current office
      const allAreas = localStore.getAuditAreas();
      const currentOfficeAreas = prev.office
        ? allAreas.filter(
            (a) =>
              (a.officeName || "").toLowerCase().trim() === prev.office.toLowerCase().trim() ||
              (a.officeId || "").toLowerCase().trim() === prev.office.toLowerCase().trim(),
          )
        : [];

      const matchesOffice = currentOfficeAreas.some(
        (a) =>
          a.auditAreaId?.toLowerCase() === areaId.toLowerCase() ||
          a.code?.toLowerCase() === areaId.toLowerCase() ||
          a.id?.toLowerCase() === areaId.toLowerCase(),
      );

      if (!matchesOffice && currentOfficeAreas.length > 0) {
        return prev;
      }

      const exists = prev.selectedAuditAreaIds.some(
        (id) => id.toLowerCase() === areaId.toLowerCase(),
      );
      const updatedAreaIds = exists
        ? prev.selectedAuditAreaIds.filter((id) => id.toLowerCase() !== areaId.toLowerCase())
        : [...prev.selectedAuditAreaIds, areaId];

      // Remove focus items belonging to deselected area
      let remainingFocusIds = prev.selectedAuditFocusIds;
      if (exists) {
        const allFocuses = localStore.getAuditFocuses();
        const deselectedAreaFocusKeys = allFocuses
          .filter(
            (f) =>
              f.auditAreaId?.toLowerCase() === areaId.toLowerCase() ||
              f.code?.toLowerCase() === areaId.toLowerCase() ||
              f.id?.toLowerCase() === areaId.toLowerCase(),
          )
          .flatMap((f) => [f.auditFocusId, f.code, f.id].filter(Boolean) as string[])
          .map((k) => k.toLowerCase());

        remainingFocusIds = prev.selectedAuditFocusIds.filter(
          (fId) => !deselectedAreaFocusKeys.includes(fId.toLowerCase()),
        );
      }

      return {
        ...prev,
        selectedAuditAreaIds: updatedAreaIds,
        selectedAuditFocusIds: remainingFocusIds,
      };
    });

    setTouched((prev) => ({
      ...prev,
      selectedAuditAreaIds: true,
    }));
  };

  // Update Coverage Note
  const handleChangeCoverageNote = (areaId: string, note: string) => {
    setFormState((prev) => ({
      ...prev,
      coverageNotes: {
        ...prev.coverageNotes,
        [areaId]: note,
      },
    }));
  };

  // Toggle Audit Focus
  const handleToggleFocus = (focusId: string) => {
    setFormState((prev) => {
      const exists = prev.selectedAuditFocusIds.includes(focusId);
      const updatedFocusIds = exists
        ? prev.selectedAuditFocusIds.filter((id) => id !== focusId)
        : [...prev.selectedAuditFocusIds, focusId];

      return {
        ...prev,
        selectedAuditFocusIds: updatedFocusIds,
      };
    });
  };

  // Validation
  const validationResult = useMemo(() => {
    return defineScopeSchema.safeParse(formState);
  }, [formState]);

  const errors = useMemo<Record<string, string>>(() => {
    const errorMap: Record<string, string> = {};
    if (!validationResult.success) {
      for (const issue of validationResult.error.issues) {
        const path = issue.path[0];
        if (typeof path === "string" && !errorMap[path]) {
          errorMap[path] = issue.message;
        }
      }
    }
    return errorMap;
  }, [validationResult]);

  const isFormValid = validationResult.success;

  const gate = useMemo(() => {
    return evaluateEngagementGating(engagementParamId);
  }, [engagementParamId]);

  const isReadOnly = gate.isScopeLocked;

  const handleResetForm = () => {
    if (isReadOnly) return;
    setFormState((prev) => ({
      ...prev,
      selectedAuditAreaIds: [],
      coverageNotes: {},
      selectedAuditFocusIds: [],
      objectives: "",
      boundaries: "",
      exclusions: "",
      knownLimitations: "",
    }));
    setTouched({});
    setIsSubmitted(false);
    showToast({
      title: "Scope Form Cleared",
      description: "Scope parameters and boundaries have been reset to empty fields.",
      tone: "info",
    });
  };

  const handleSaveAndProceed = (e: React.FormEvent) => {
    e.preventDefault();
    if (isReadOnly) {
      navigateTo(`/admin/audit-engagement/${formState.engagementNumber}/team-assignment`);
      return;
    }

    setIsSubmitted(true);

    if (!isFormValid) {
      showToast({
        title: "Validation Incomplete",
        description: "Please select an office, at least one audit area, and document engagement objectives.",
        tone: "warning",
      });
      return;
    }

    localStore.saveScopeState(formState.engagementNumber, formState);

    if (existingEngagement) {
      const allAreas = localStore.getAuditAreas();
      const areaNames = formState.selectedAuditAreaIds
        .map((id) => {
          const a = allAreas.find(
            (item) =>
              item.auditAreaId?.toLowerCase() === id.toLowerCase() ||
              item.code?.toLowerCase() === id.toLowerCase() ||
              item.id?.toLowerCase() === id.toLowerCase(),
          );
          return a ? `${a.auditAreaId || a.code} — ${a.auditAreaName || a.name}` : id;
        })
        .join(", ");

      localStore.saveEngagement({
        ...existingEngagement,
        office: formState.office || existingEngagement.office,
        auditArea: areaNames || existingEngagement.auditArea,
        objective: formState.objectives || existingEngagement.objective,
        scope: formState.boundaries || existingEngagement.scope,
        progress: Math.max(existingEngagement.progress || 0, 20),
      });
    }

    showToast({
      title: "Engagement Scope Saved",
      description: `Scope & boundaries established for ${formState.engagementNumber}. Proceeding to Team Assignment (SCR-213)...`,
      tone: "success",
    });

    setTimeout(() => {
      navigateTo(`/admin/audit-engagement/${formState.engagementNumber}/team-assignment`);
    }, 400);
  };

  return (
    <AdminLayout>
      <div className="space-y-2.5 pb-8">
        {/* Unified Top Header Card matching SCR-211 */}
        <div className="rounded-lg border border-slate-200 bg-white p-3 shadow-sm space-y-2">
          {/* Breadcrumb row */}
          <div className="flex items-center justify-between text-[11px] text-slate-400">
            <div className="flex items-center gap-1.5 font-medium">
              <button
                type="button"
                onClick={() => navigateTo("/admin/audit-engagements")}
                className="hover:text-blue-600 transition"
              >
                Audit Engagements
              </button>
              <span>/</span>
              <span className="text-slate-700 font-semibold">
                Define Scope & Boundaries (SCR-212)
              </span>
            </div>
            <span className="text-[10px] font-mono text-slate-400 hidden sm:inline">
              MDS-200 v0.8 §7 • Phase 1 Initiation
            </span>
          </div>

          {/* Main Title and Action Group */}
          <div className="flex flex-col gap-2 pt-0.5 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex items-center gap-2.5">
              <button
                type="button"
                onClick={() => navigateTo("/admin/audit-engagements")}
                className="grid h-8 w-8 shrink-0 place-items-center rounded-md border border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition"
                title="Back to Engagements Workspace"
              >
                <ArrowLeft className="h-4 w-4" />
              </button>
              <div>
                <div className="flex items-center gap-1.5">
                  <span className="font-mono text-[10px] font-bold text-blue-700 bg-blue-50 px-1.5 py-0.2 rounded border border-blue-200">
                    SCR-212
                  </span>
                  <h1 className="text-[1.1rem] font-bold tracking-tight text-slate-900 leading-snug">
                    Define Engagement Scope & Boundaries
                  </h1>
                </div>
                <p className="text-[11px] text-slate-500">
                  Establish single target office, audit areas, focus items, engagement objectives, coverage, and boundaries.
                </p>
              </div>
            </div>

            {/* Action Buttons */}
            <div className="flex items-center gap-2 shrink-0">
              <Button
                type="button"
                variant="outline"
                className="h-10 rounded-lg border-slate-200 px-3.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition active:scale-[0.99]"
                onClick={() => navigateTo("/admin/audit-engagements")}
              >
                Cancel
              </Button>

              {!isReadOnly ? (
                <>
                  <Button
                    type="button"
                    variant="outline"
                    className="h-10 rounded-lg border-slate-200 px-3.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition active:scale-[0.99]"
                    onClick={handleResetForm}
                    title="Reset scope parameters"
                  >
                    <RotateCcw className="h-4 w-4 sm:mr-1" />
                    <span className="hidden sm:inline">Reset</span>
                  </Button>

                  <Button
                    type="button"
                    className="h-10 rounded-lg px-4 text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white shadow-sm disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2 transition active:scale-[0.99]"
                    disabled={!isFormValid}
                    onClick={handleSaveAndProceed}
                  >
                    <Save className="h-4 w-4" />
                    <span>Save & Proceed (SCR-213)</span>
                    <ArrowRight className="h-4 w-4" />
                  </Button>
                </>
              ) : (
                <div className="flex items-center gap-2">
                  <div className="flex items-center gap-1.5 rounded-lg bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-700 border border-emerald-200">
                    <Lock size={13} />
                    <span>Scope Locked (AEO Issued)</span>
                  </div>
                  <Button
                    type="button"
                    className="h-10 rounded-lg px-4 text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white shadow-sm flex items-center gap-2 transition active:scale-[0.99]"
                    onClick={() => navigateTo(`/admin/audit-engagement/${formState.engagementNumber}/team-assignment`)}
                  >
                    <span>View Team Assignment (SCR-213)</span>
                    <ArrowRight className="h-4 w-4" />
                  </Button>
                </div>
              )}
            </div>
          </div>
        </div>

        {/* 2-Column Main Form & Right Context Sidebar */}
        <div className="grid grid-cols-1 gap-2.5 xl:grid-cols-12">
          {/* Main Transaction Form Column (8 cols) */}
          <form onSubmit={handleSaveAndProceed} className="space-y-2.5 xl:col-span-8">
            {/* Step 1: Office Selector with Source Variance Handler */}
            <ScopeOfficeSelector
              selectedOffice={formState.office}
              iapOffice={formState.iapOffice}
              originType={formState.originType}
              isOfficeChangedFromIap={formState.isOfficeChangedFromIap}
              sourceVarianceReason={formState.sourceVarianceReason}
              error={errors.office}
              varianceError={errors.sourceVarianceReason}
              touched={touched.office || isSubmitted}
              isReadOnly={isReadOnly}
              onChangeOffice={handleChangeOffice}
              onChangeVarianceReason={(val) =>
                setFormState((prev) => ({ ...prev, sourceVarianceReason: val }))
              }
              onResetToIapOffice={handleResetToIapOffice}
            />

            {/* Step 2: Audit Area Multi-Selector with Coverage Notes */}
            <AuditAreaSelector
              selectedOffice={formState.office}
              selectedAreaIds={formState.selectedAuditAreaIds}
              coverageNotes={formState.coverageNotes}
              error={touched.selectedAuditAreaIds || isSubmitted ? errors.selectedAuditAreaIds : undefined}
              isReadOnly={isReadOnly}
              onToggleArea={handleToggleArea}
              onChangeCoverageNote={handleChangeCoverageNote}
            />

            {/* Step 3: Applicable Audit Focuses (Grouped by Area) */}
            <AuditFocusSelector
              selectedAreaIds={formState.selectedAuditAreaIds}
              selectedFocusIds={formState.selectedAuditFocusIds}
              isReadOnly={isReadOnly}
              onToggleFocus={handleToggleFocus}
            />

            {/* Step 4: Engagement Objectives, Boundaries, Exclusions & Limitations */}
            <ScopeParametersSection
              objectives={formState.objectives}
              boundaries={formState.boundaries}
              exclusions={formState.exclusions}
              knownLimitations={formState.knownLimitations}
              objectivesError={errors.objectives}
              touchedObjectives={touched.objectives || isSubmitted}
              isReadOnly={isReadOnly}
              onChangeObjectives={(val) =>
                setFormState((prev) => ({ ...prev, objectives: val }))
              }
              onChangeBoundaries={(val) =>
                setFormState((prev) => ({ ...prev, boundaries: val }))
              }
              onChangeExclusions={(val) =>
                setFormState((prev) => ({ ...prev, exclusions: val }))
              }
              onChangeLimitations={(val) =>
                setFormState((prev) => ({ ...prev, knownLimitations: val }))
              }
              onBlurObjectives={() =>
                setTouched((prev) => ({ ...prev, objectives: true }))
              }
            />

            {/* Bottom Action Footer */}
            <div className="mt-2.5 flex flex-col gap-2 rounded-lg border border-slate-200 bg-white p-3 shadow-sm sm:flex-row sm:items-center sm:justify-between">
              <div className="flex items-center gap-1.5 text-xs text-slate-500">
                <span className={`h-2 w-2 rounded-full shrink-0 ${isReadOnly ? "bg-emerald-500" : "bg-amber-500"}`} />
                <span>
                  {isReadOnly
                    ? "This engagement scope is officially locked under Issued AEO per MDS-200 Rule 31. Changes require a controlled AEO revision."
                    : "Status remains Draft • Phase: Engagement Initiation • Audit Team assignment happens next (SCR-213)"}
                </span>
              </div>

              <div className="flex items-center gap-2 shrink-0">
                <Button
                  type="button"
                  variant="outline"
                  className="h-10 rounded-lg border-slate-200 px-3.5 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                  onClick={() => navigateTo("/admin/audit-engagement/create")}
                >
                  Back (SCR-211)
                </Button>
                {!isReadOnly ? (
                  <Button
                    type="submit"
                    className="h-10 rounded-lg px-4 text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white shadow-sm disabled:opacity-50 flex items-center gap-2 transition active:scale-[0.99]"
                    disabled={!isFormValid}
                  >
                    <span>Save & Proceed (SCR-213)</span>
                    <ArrowRight className="h-4 w-4" />
                  </Button>
                ) : (
                  <Button
                    type="button"
                    className="h-10 rounded-lg px-4 text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white shadow-sm flex items-center gap-2 transition active:scale-[0.99]"
                    onClick={() => navigateTo(`/admin/audit-engagement/${formState.engagementNumber}/team-assignment`)}
                  >
                    <span>Proceed to Team Assignment (SCR-213)</span>
                    <ArrowRight className="h-4 w-4" />
                  </Button>
                )}
              </div>
            </div>
          </form>

          {/* Right Governance Context Column (4 cols) */}
          <div className="xl:col-span-4">
            <div className="sticky top-2">
              <ScopeSystemControlsCard
                formState={formState}
                isFormValid={isFormValid}
                isLocked={gate.isScopeLocked}
                aeoStatus={gate.aeoStatus}
              />
            </div>
          </div>
        </div>
      </div>
    </AdminLayout>
  );
}

export default DefineScopePage;
