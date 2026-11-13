import { useMemo, useState } from "react";
import {
  ArrowLeft,
  ArrowRight,
  CheckCircle2,
  FileSpreadsheet,
  Plus,
  RotateCcw,
  Send,
} from "lucide-react";
import { AdminLayout } from "../../../../layouts/admin/AdminLayout";
import { Button } from "../../../../components/ui/button";
import { useToast } from "../../../../components/ui/toast";
import { navigateTo } from "../../../../routes";
import {
  FieldworkControlsRail,
  FieldworkFormSection,
  FieldworkIdentityBanner,
  FieldworkRecordsListSection,
  WorkingPaperLinkCard,
} from "./components";
import { defaultFieldworkPackageState } from "./fieldwork-data";
import { fieldworkRecordSchema } from "./fieldwork-schema";
import type {
  FieldworkEvidenceLink,
  FieldworkPackageState,
  FieldworkRecord,
} from "./fieldwork-types";
import { evaluateEngagementGating } from "../../../../lib/engagementPhaseGating";
import { EngagementPhaseGate } from "../components/EngagementPhaseGate";
import { localStore } from "../../../../lib/localStore";

export function AdminFieldworkRecordsPage() {
  const { showToast } = useToast();

  const currentPath = window.location.pathname;
  const pathParts = currentPath.split("/");
  const engagementId = pathParts[3] || "AEMS-2026-010";

  // Resolve matching engagement from localStore
  const existingEngagement = useMemo(() => {
    return localStore.getEngagement(engagementId);
  }, [engagementId]);

  const engagementNumber = existingEngagement?.code || engagementId;

  const [packageState, setPackageState] = useState<FieldworkPackageState>(() => {
    const saved = localStore.getFieldworkState(engagementId);
    if (saved) return saved;

    const isSeeded = engagementNumber === "AEMS-2026-010";
    if (isSeeded) {
      return {
        ...defaultFieldworkPackageState,
        engagementNumber: existingEngagement?.code || engagementId,
        engagementTitle:
          existingEngagement?.auditArea ||
          existingEngagement?.subject ||
          defaultFieldworkPackageState.engagementTitle,
        office:
          existingEngagement?.office || defaultFieldworkPackageState.office,
      };
    }

    return {
      engagementNumber,
      engagementTitle:
        existingEngagement?.auditArea ||
        existingEngagement?.subject ||
        "",
      office: existingEngagement?.office || "",
      activeRecordId: "",
      records: [],
    };
  });

  // Enforce Phase Gating (MDS-200 Module Rule 11)
  const gate = evaluateEngagementGating(engagementNumber);

  const activeRecord = useMemo(() => {
    const found = packageState.records.find(
      (r) => r.id === packageState.activeRecordId,
    );
    return found || packageState.records[0];
  }, [packageState.records, packageState.activeRecordId]);

  // Validation
  const validationResult = useMemo(() => {
    const parsed = fieldworkRecordSchema.safeParse(activeRecord);
    return {
      isValid: parsed.success,
      errors: parsed.success
        ? {}
        : parsed.error.issues.reduce<Record<string, string>>((acc, issue) => {
          acc[issue.path.join(".")] = issue.message;
          return acc;
        }, {}),
    };
  }, [activeRecord]);

  // Handlers for active record
  const handleFieldChange = (field: keyof FieldworkRecord, value: any) => {
    setPackageState((prev) => ({
      ...prev,
      records: prev.records.map((r) =>
        r.id === prev.activeRecordId
          ? { ...r, [field]: value, updatedAt: "2026-03-09 17:30" }
          : r,
      ),
    }));
  };

  const handleAddEvidenceLink = () => {
    const newId = `EVD-2026-00${activeRecord.evidenceLinks.length + 1}`;
    const newLink: FieldworkEvidenceLink = {
      id: newId,
      title: "Supplemental Fieldwork Evidence File",
      type: "Scanned Attachment",
      sourceUrlOrRef: `${activeRecord.workingPaperRef}/${newId}.pdf`,
    };

    handleFieldChange("evidenceLinks", [
      ...activeRecord.evidenceLinks,
      newLink,
    ]);
    showToast({
      title: "Evidence Link Added",
      description: `Attached reference ${newId} to ${activeRecord.id}.`,
      tone: "info",
    });
  };

  const handleUpdateEvidenceLink = (
    id: string,
    updates: Partial<FieldworkEvidenceLink>,
  ) => {
    const updated = activeRecord.evidenceLinks.map((evd) =>
      evd.id === id ? { ...evd, ...updates } : evd,
    );
    handleFieldChange("evidenceLinks", updated);
  };

  const handleRemoveEvidenceLink = (id: string) => {
    const filtered = activeRecord.evidenceLinks.filter((evd) => evd.id !== id);
    handleFieldChange("evidenceLinks", filtered);
  };

  const handleCreateNewRecord = () => {
    const engSuffix =
      (packageState.engagementNumber || "").replace(/^AEMS-/, "") || "2026-020";
    const progPkg = localStore.getProgramsState(packageState.engagementNumber);
    const firstProc = progPkg?.programs?.[0]?.procedures?.[0];
    const procId = firstProc?.id || `PROC-${engSuffix}-001-01`;
    const procTitle =
      firstProc?.procedureTitle ||
      "Farmer Beneficiary Registry Cross-Check & Voucher Vouching";
    const wpRef = firstProc?.workingPaperRef || `WP-${engSuffix}-OPS-01`;

    const newNum = packageState.records.length + 1;
    const newId = `FWR-${engSuffix}-${String(newNum).padStart(3, "0")}`;
    const newRecord: FieldworkRecord = {
      id: newId,
      engagementNumber: packageState.engagementNumber,
      procedureId: procId,
      procedureTitle: procTitle,
      auditArea: packageState.engagementTitle || "Operational Compliance Audit",
      workingPaperRef: wpRef,
      type: "Physical Inspection",
      date: new Date().toISOString().split("T")[0],
      location: `${packageState.office || "Auditee Operations Office"}, Records Archive`,
      personsInvolved: "Juan Dela Cruz (Lead Auditor), Assigned Audit Staff",
      purpose: "Inspect original source documents, vouchers, and operational logbooks.",
      factsObtained: "Initial substantive testing and sample transaction inspections commenced.",
      notes: "Logbook stamps reconciled against verified administrative records.",
      evidenceLinks: [],
      preparer: "Juan Dela Cruz (Lead Auditor)",
      reviewer: "Atty. Fernando M. Gomez, CPA (Supervising Auditor)",
      status: "Draft",
      version: "v1.0",
      createdAt: new Date().toISOString().replace("T", " ").substring(0, 16),
      updatedAt: new Date().toISOString().replace("T", " ").substring(0, 16),
    };

    setPackageState((prev) => ({
      ...prev,
      activeRecordId: newId,
      records: [newRecord, ...prev.records],
    }));

    showToast({
      title: "New Fieldwork Record Created",
      description: `Draft record ${newId} initialized.`,
      tone: "info",
    });
  };

  const handleSaveDraft = () => {
    localStore.saveFieldworkState(packageState.engagementNumber, packageState);
    showToast({
      title: "Fieldwork Record Saved",
      description: `Draft for ${activeRecord.id} saved successfully.`,
      tone: "info",
    });
  };

  const handleSubmitRecord = () => {
    if (!validationResult.isValid) {
      showToast({
        title: "Validation Incomplete",
        description: "Please complete all mandatory factual findings and required fields.",
        tone: "warning",
      });
      return;
    }

    // UID-200 §11 Consequence-bearing action confirmation
    const confirmed = window.confirm(
      `Submit Fieldwork Record ${activeRecord.id} to Supervising Auditor for review?\n\nThis will lock factual observations and transmit source evidence toward Working Paper ${activeRecord.workingPaperRef}.`,
    );

    if (!confirmed) return;

    const updatedRecords = packageState.records.map((r) =>
      r.id === activeRecord.id ? { ...r, status: "Submitted" as const } : r,
    );
    const updatedState: FieldworkPackageState = {
      ...packageState,
      records: updatedRecords,
    };
    setPackageState(updatedState);
    localStore.saveFieldworkState(packageState.engagementNumber, updatedState);

    showToast({
      title: "Fieldwork Record Submitted",
      description: `Record ${activeRecord.id} transmitted to ${activeRecord.reviewer} for quality review.`,
      tone: "success",
    });
  };

  const handleReset = () => {
    setPackageState(defaultFieldworkPackageState);
    showToast({
      title: "Records Reset",
      description: "Reverted to default fieldwork records baseline.",
      tone: "info",
    });
  };

  return (
    <AdminLayout>
      <div className="space-y-2.5 pb-8">
        {/* Top Unified Header Card */}
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
              <button
                type="button"
                onClick={() =>
                  navigateTo(
                    `/admin/audit-engagement/${packageState.engagementNumber}`,
                  )
                }
                className="hover:text-blue-600 transition"
              >
                {packageState.engagementNumber}
              </button>
              <span>/</span>
              <button
                type="button"
                onClick={() =>
                  navigateTo(
                    `/admin/audit-engagement/${packageState.engagementNumber}/execution`,
                  )
                }
                className="hover:text-blue-600 transition"
              >
                Execution Workspace (SCR-226)
              </button>
              <span>/</span>
              <span className="text-slate-700 font-semibold">
                Fieldwork Records (SCR-227)
              </span>
            </div>
            <span className="text-[10px] font-mono text-slate-400 hidden sm:inline">
              MDS-200 v0.8 §7, §11 (Rules 11, 13) · UID-200 v0.5 §8.3 · Phase 4 Controlled Record
            </span>
          </div>

          {/* Main Title and Action Group */}
          <div className="flex flex-col gap-2 pt-0.5 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex items-center gap-2.5">
              <button
                type="button"
                onClick={() =>
                  navigateTo(
                    `/admin/audit-engagement/${packageState.engagementNumber}/execution`,
                  )
                }
                className="grid h-8 w-8 shrink-0 place-items-center rounded-md border border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition"
                title="Back to Execution Workspace Hub"
              >
                <ArrowLeft className="h-4 w-4" />
              </button>
              <div>
                <div className="flex items-center gap-1.5">
                  <span className="font-mono text-[10px] font-bold text-blue-700 bg-blue-50 px-1.5 py-0.2 rounded border border-blue-200">
                    SCR-227
                  </span>
                  <h1 className="text-[1.1rem] font-bold tracking-tight text-slate-900 leading-snug">
                    Fieldwork Record Transaction & Source Observation
                  </h1>
                </div>
                <p className="text-[11px] text-slate-500">
                  Preserve and document interviews, inquiries, observations, walkthroughs, and spot counts for <strong>{packageState.engagementNumber}</strong>.
                </p>
              </div>
            </div>

            {/* Header Actions */}
            <div className="flex items-center gap-2 shrink-0">
              <Button
                type="button"
                variant="outline"
                className="h-10 rounded-lg border-slate-200 px-3.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition active:scale-[0.99]"
                onClick={() =>
                  navigateTo(
                    `/admin/audit-engagement/${packageState.engagementNumber}/execution`,
                  )
                }
              >
                Return to Execution Hub
              </Button>

              <Button
                type="button"
                variant="outline"
                className="h-10 rounded-lg border-slate-200 px-3.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 flex items-center gap-1.5 transition active:scale-[0.99]"
                onClick={handleReset}
              >
                <RotateCcw className="h-3.5 w-3.5" />
                <span>Reset</span>
              </Button>

              <Button
                type="button"
                className="h-10 rounded-lg px-4 text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white shadow-sm disabled:opacity-50 flex items-center gap-2 transition active:scale-[0.99] cursor-pointer"
                disabled={!activeRecord || !validationResult.isValid || activeRecord?.status === "Submitted"}
                onClick={handleSubmitRecord}
              >
                <Send className="h-4 w-4" />
                <span>{activeRecord?.status === "Submitted" ? "Record Submitted" : "Submit Fieldwork Record"}</span>
                <ArrowRight className="h-4 w-4" />
              </Button>
            </div>
          </div>
        </div>

        {/* Main Content or Gated View */}
        {!gate.isPhase3Complete ? (
          <EngagementPhaseGate
            engagementNumber={packageState.engagementNumber}
            requiredPhaseName="Phase 4: Fieldwork Execution"
            requiredPhaseNumber={4}
            screenTitle="Fieldwork Records (SCR-227)"
          />
        ) : !activeRecord ? (
          <div className="rounded-xl border border-dashed border-slate-300 bg-white p-12 text-center space-y-4 shadow-sm">
            <div className="grid h-12 w-12 place-items-center rounded-full bg-blue-50 text-blue-600 mx-auto">
              <FileSpreadsheet className="h-6 w-6" />
            </div>
            <div className="space-y-1 max-w-md mx-auto">
              <h3 className="text-sm font-bold text-slate-900">
                No Fieldwork Records Initialized
              </h3>
              <p className="text-xs text-slate-500">
                Initialize your first fieldwork testing record, sampling log, or physical inspection sheet for <strong>{packageState.engagementNumber}</strong>.
              </p>
            </div>
            <Button
              type="button"
              onClick={handleCreateNewRecord}
              className="h-9 px-4 text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white shadow-sm flex items-center gap-2 mx-auto cursor-pointer"
            >
              <Plus className="h-4 w-4" />
              <span>Initialize First Fieldwork Record</span>
            </Button>
          </div>
        ) : (
          <>
            {/* 2-Column Main Form Grid */}
            <div className="grid grid-cols-1 gap-3 lg:grid-cols-12">
              {/* Left Column (8 cols): Controlled Fieldwork Artifacts */}
              <div className="space-y-3 lg:col-span-8">
                {/* 1. Context Identity Banner */}
                <FieldworkIdentityBanner
                  record={activeRecord}
                  engagementTitle={packageState.engagementTitle}
                  office={packageState.office}
                />

                {/* 2. Rule 13 Mandate Card (Working Paper Linkage) */}
                <WorkingPaperLinkCard
                  engagementNumber={packageState.engagementNumber}
                  workingPaperRef={activeRecord.workingPaperRef}
                  procedureId={activeRecord.procedureId}
                />

                {/* 3. Primary Fieldwork Transaction Form */}
                <FieldworkFormSection
                  record={activeRecord}
                  onChangeField={handleFieldChange}
                  onAddEvidenceLink={handleAddEvidenceLink}
                  onUpdateEvidenceLink={handleUpdateEvidenceLink}
                  onRemoveEvidenceLink={handleRemoveEvidenceLink}
                />

                {/* 4. Engagement Fieldwork Records Roster */}
                <FieldworkRecordsListSection
                  records={packageState.records}
                  activeRecordId={packageState.activeRecordId}
                  onSelectRecord={(id) =>
                    setPackageState((prev) => ({ ...prev, activeRecordId: id }))
                  }
                  onCreateNewRecord={handleCreateNewRecord}
                />
              </div>

              {/* Right Column (4 cols): System Controls & Governance Checklist */}
              <div className="space-y-3 lg:col-span-4">
                <div className="sticky top-4">
                  <FieldworkControlsRail
                    record={activeRecord}
                    isValid={validationResult.isValid}
                    onSaveDraft={handleSaveDraft}
                    onSubmitRecord={handleSubmitRecord}
                    onCreateNewRecord={handleCreateNewRecord}
                  />
                </div>
              </div>
            </div>

            {/* Bottom Action Footer Card */}
            <div className="rounded-lg border border-slate-200 bg-white p-3 shadow-sm flex flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-between">
              <div className="flex items-center gap-1.5 text-xs text-slate-500">
                <CheckCircle2 size={14} className="text-blue-600 shrink-0" />
                <span>
                  Fieldwork Record: <strong>{activeRecord.id} ({activeRecord.type})</strong> • Linked to <strong>{activeRecord.procedureId}</strong> &rarr; Feeds <strong>{activeRecord.workingPaperRef}</strong> • Governed under MDS-200 §7, §11 (Rules 11, 13)
                </span>
              </div>

              <div className="flex items-center gap-2 shrink-0">
                <Button
                  type="button"
                  variant="outline"
                  className="h-10 rounded-lg border-slate-200 px-3.5 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                  onClick={() =>
                    navigateTo(
                      `/admin/audit-engagement/${packageState.engagementNumber}/execution`,
                    )
                  }
                >
                  Return to Execution (SCR-226)
                </Button>

                <Button
                  type="button"
                  onClick={handleSubmitRecord}
                  disabled={!activeRecord || !validationResult.isValid || activeRecord?.status === "Submitted"}
                  className="h-10 rounded-lg px-4 text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white shadow-sm disabled:opacity-50 flex items-center gap-2 transition active:scale-[0.99] cursor-pointer"
                >
                  <span>{activeRecord?.status === "Submitted" ? "Record Submitted" : "Submit Fieldwork Record"}</span>
                  <ArrowRight className="h-4 w-4" />
                </Button>
              </div>
            </div>
          </>
        )}
      </div>
    </AdminLayout>
  );
}

export default AdminFieldworkRecordsPage;
