import { useEffect, useMemo, useState } from "react";
import {
  ArrowLeft,
  ArrowRight,
  CheckCircle2,
  Plus,
  RotateCcw,
  ShieldCheck,
} from "lucide-react";
import { AdminLayout } from "../../../../layouts/admin/AdminLayout";
import { Button } from "../../../../components/ui/button";
import { useToast } from "../../../../components/ui/toast";
import { navigateTo } from "../../../../routes";
import {
  EvidenceControlsRail,
  EvidenceCustodyLogSection,
  EvidenceFormSection,
  EvidenceIdentityBanner,
  EvidenceRelatedArtifactsSection,
  EvidenceRosterSection,
} from "./components";
import { defaultEvidencePackageState } from "./evidence-data";
import { auditEvidenceSchema } from "./evidence-schema";
import type {
  AuditEvidenceItem,
  EvidenceCustodyEvent,
  EvidencePackageState,
} from "./evidence-types";
import { evaluateEngagementGating } from "../../../../lib/engagementPhaseGating";
import { EngagementPhaseGate } from "../components/EngagementPhaseGate";
import { localStore } from "../../../../lib/localStore";

export function AdminAuditEvidencePage() {
  const { showToast } = useToast();

  const currentPath = window.location.pathname;
  const pathParts = currentPath.split("/").filter(Boolean);
  // e.g. ["admin", "audit-engagement", "AEMS-2026-010", "execution", "evidence", "EVD-2026-003"]
  const engagementId = pathParts[2] || "AEMS-2026-010";

  // Check if last segment is a specific Evidence ID
  const lastPart = pathParts[pathParts.length - 1];
  const urlEvidenceId =
    lastPart && lastPart.startsWith("EVD-") ? lastPart : null;

  // Resolve matching engagement from localStore
  const existingEngagement = useMemo(() => {
    return localStore.getEngagement(engagementId);
  }, [engagementId]);

  const engagementNumber = existingEngagement?.code || engagementId;

  const [packageState, setPackageState] = useState<EvidencePackageState>(() => {
    const saved = localStore.getEvidenceState(engagementId);
    if (saved) {
      if (urlEvidenceId && saved.evidenceItems.some((e) => e.id.toLowerCase() === urlEvidenceId.toLowerCase())) {
        return { ...saved, activeEvidenceId: urlEvidenceId };
      }
      return saved;
    }

    const isSeeded = engagementNumber === "AEMS-2026-010";
    if (isSeeded) {
      const baseState = { ...defaultEvidencePackageState };
      if (existingEngagement) {
        baseState.engagementNumber = existingEngagement.code;
        baseState.engagementTitle =
          existingEngagement.auditArea ||
          existingEngagement.subject ||
          defaultEvidencePackageState.engagementTitle;
        baseState.office =
          existingEngagement.office || defaultEvidencePackageState.office;
      }
      if (urlEvidenceId && baseState.evidenceItems.some((e) => e.id.toLowerCase() === urlEvidenceId.toLowerCase())) {
        baseState.activeEvidenceId = urlEvidenceId;
      }
      return baseState;
    }

    return {
      engagementNumber,
      engagementTitle:
        existingEngagement?.auditArea ||
        existingEngagement?.subject ||
        "",
      office: existingEngagement?.office || "",
      activeEvidenceId: "",
      evidenceItems: [],
    };
  });

  // Enforce Phase Gating (MDS-200 Module Rule 11)
  const gate = evaluateEngagementGating(engagementNumber);

  // Sync if URL param changes
  useEffect(() => {
    if (urlEvidenceId) {
      const found = packageState.evidenceItems.find(
        (evd) => evd.id.toLowerCase() === urlEvidenceId.toLowerCase(),
      );
      if (found && found.id !== packageState.activeEvidenceId) {
        setPackageState((prev) => ({ ...prev, activeEvidenceId: found.id }));
      }
    }
  }, [urlEvidenceId, packageState.evidenceItems, packageState.activeEvidenceId]);

  const activeEvidence = useMemo(() => {
    const found = packageState.evidenceItems.find(
      (evd) => evd.id === packageState.activeEvidenceId,
    );
    return found || packageState.evidenceItems[0];
  }, [packageState.evidenceItems, packageState.activeEvidenceId]);

  // Validation
  const validationResult = useMemo(() => {
    if (!activeEvidence) {
      return { isValid: false, errors: {} };
    }
    const parsed = auditEvidenceSchema.safeParse(activeEvidence);
    return {
      isValid: parsed.success,
      errors: parsed.success
        ? {}
        : parsed.error.issues.reduce<Record<string, string>>((acc, issue) => {
            acc[issue.path.join(".")] = issue.message;
            return acc;
          }, {}),
    };
  }, [activeEvidence]);

  // Handlers
  const handleFieldChange = (field: keyof AuditEvidenceItem, value: any) => {
    setPackageState((prev) => ({
      ...prev,
      evidenceItems: prev.evidenceItems.map((evd) =>
        evd.id === prev.activeEvidenceId
          ? { ...evd, [field]: value, updatedAt: "2026-03-16 11:00" }
          : evd,
      ),
    }));
  };

  const handleSelectEvidence = (id: string) => {
    setPackageState((prev) => ({ ...prev, activeEvidenceId: id }));
  };

  const handleAddCustodyEvent = (event: EvidenceCustodyEvent) => {
    setPackageState((prev) => ({
      ...prev,
      evidenceItems: prev.evidenceItems.map((evd) =>
        evd.id === prev.activeEvidenceId
          ? {
              ...evd,
              custodyLog: [...evd.custodyLog, event],
              updatedAt: event.date,
            }
          : evd,
      ),
    }));

    showToast({
      title: "Chain of Custody Recorded",
      description: `Logged '${event.action}' for ${activeEvidence.id}.`,
      tone: "info",
    });
  };

  const handleRemoveCustodyEvent = (id: string) => {
    setPackageState((prev) => ({
      ...prev,
      evidenceItems: prev.evidenceItems.map((evd) =>
        evd.id === prev.activeEvidenceId
          ? {
              ...evd,
              custodyLog: evd.custodyLog.filter((c) => c.id !== id),
            }
          : evd,
      ),
    }));
  };

  const handleCreateNewEvidence = () => {
    const engSuffix =
      (packageState.engagementNumber || "").replace(/^AEMS-/, "") || "2026-020";
    const nextNum = packageState.evidenceItems.length + 1;
    const newId = `EVD-${engSuffix}-${String(nextNum).padStart(3, "0")}`;
    const newEvd: AuditEvidenceItem = {
      id: newId,
      title: "Substantive Fieldwork Audit Evidence Artifact",
      engagementNumber: packageState.engagementNumber,
      evidenceType: "Scanned Document / Voucher",
      auditProcedureMethod: "Inspection",
      source: packageState.office || "Auditee Operations Office",
      sourceOrigin: "Internal",
      acquisitionMode: "Direct (Auditor-Observed/Gathered)",
      physicalForm: "Scanned Copy",
      custodian: "Juan Dela Cruz (Lead Auditor)",
      dateReceived: new Date().toISOString().split("T")[0],
      fileReference: `vault/raw-uploads/${packageState.engagementNumber}/${newId}.pdf`,
      hashAlgorithm: "SHA-256",
      hashChecksum: "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
      relatedCriteria: "Statutory & Operational Compliance Standards",
      sufficiencyContribution: "Direct substantive audit evidence for sample verification",
      appropriateness: "High",
      relevance: "Direct factual corroboration of auditee records",
      reliability: "High — Verified against official registry",
      accuracyCompletenessProcedures: "Recalculation and official stamp verification",
      corroboration: "Corroborates operational audit procedures",
      contradictoryEvidence: "",
      authenticity: "Certified true copy on file",
      confidentiality: "Internal Audit Confidential",
      limitation: "",
      accessRestriction: "Engagement Team & Reviewers",
      legalHold: false,
      archivalCondition: "Active Repository",
      assessmentResult: "Under assessment for working paper substantiation",
      relatedArtifacts: [],
      custodyLog: [
        {
          id: "CST-001",
          date: new Date().toISOString().replace("T", " ").substring(0, 16),
          action: "Ingestion and SHA-256 Cryptographic Hashing",
          person: "Juan Dela Cruz",
          role: "Lead Auditor",
          notes: `Initial evidence registration into ${packageState.engagementNumber} vault.`,
        },
      ],
      status: "Registered",
      version: "v1.0",
      createdAt: new Date().toISOString().replace("T", " ").substring(0, 16),
      updatedAt: new Date().toISOString().replace("T", " ").substring(0, 16),
    };

    setPackageState((prev) => ({
      ...prev,
      activeEvidenceId: newId,
      evidenceItems: [newEvd, ...prev.evidenceItems],
    }));

    showToast({
      title: "Evidence Item Registered",
      description: `Draft evidence item ${newId} initialized.`,
      tone: "info",
    });
  };

  const handleSaveDraft = () => {
    localStore.saveEvidenceState(packageState.engagementNumber, packageState);
    showToast({
      title: "Evidence Record Saved",
      description: activeEvidence
        ? `Changes for ${activeEvidence.id} updated in vault.`
        : "Evidence vault state saved.",
      tone: "info",
    });
  };

  const handleAcceptEvidence = () => {
    if (!activeEvidence) return;

    if (!validationResult.isValid) {
      showToast({
        title: "Validation Incomplete",
        description: "Please complete all mandatory assessment attributes before acceptance.",
        tone: "warning",
      });
      return;
    }

    const confirmed = window.confirm(
      `Accept Evidence ${activeEvidence.id} (${activeEvidence.title}) for formal audit working paper use?`,
    );

    if (!confirmed) return;

    const updatedItems = packageState.evidenceItems.map((e) =>
      e.id === activeEvidence.id ? { ...e, status: "Accepted" as const } : e,
    );
    const updatedState: EvidencePackageState = {
      ...packageState,
      evidenceItems: updatedItems,
    };
    setPackageState(updatedState);
    localStore.saveEvidenceState(packageState.engagementNumber, updatedState);

    showToast({
      title: "Evidence Formally Accepted",
      description: `${activeEvidence.id} accepted per MDS-200 §10.6.`,
      tone: "success",
    });
  };

  const handleReset = () => {
    setPackageState(defaultEvidencePackageState);
    showToast({
      title: "Evidence Vault Reset",
      description: "Reverted evidence repository to default baseline.",
      tone: "info",
    });
  };

  return (
    <AdminLayout>
      <div className="space-y-2.5 pb-8">
        {/* Top Header Card */}
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
                Audit Evidence & Custody (SCR-229)
              </span>
            </div>
            <span className="text-[10px] font-mono text-slate-400 hidden sm:inline">
              MDS-200 v0.8 §7, §10.6 · UID-200 v0.5 §8.3 · Cryptographic Vault
            </span>
          </div>

          {/* Title and Actions */}
          <div className="flex flex-col gap-2 pt-0.5 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex items-center gap-2.5">
              <button
                type="button"
                onClick={() =>
                  navigateTo(
                    `/admin/audit-engagement/${packageState.engagementNumber}/execution`,
                  )
                }
                className="grid h-8 w-8 shrink-0 place-items-center rounded-md border border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition cursor-pointer"
                title="Back to Execution Workspace Hub"
              >
                <ArrowLeft className="h-4 w-4" />
              </button>
              <div>
                <div className="flex items-center gap-1.5">
                  <span className="font-mono text-[10px] font-bold text-emerald-800 bg-emerald-50 px-1.5 py-0.2 rounded border border-emerald-200">
                    SCR-229
                  </span>
                  <h1 className="text-[1.1rem] font-bold tracking-tight text-slate-900 leading-snug">
                    Audit Evidence Repository & Chain of Custody
                  </h1>
                </div>
                <p className="text-[11px] text-slate-500">
                  Store electronic evidence files, scanned vouchers, chain of custody logs, and cryptographic hash integrity for <strong>{packageState.engagementNumber}</strong>.
                </p>
              </div>
            </div>

            {/* Header Actions */}
            <div className="flex items-center gap-2 shrink-0">
              <Button
                type="button"
                variant="outline"
                className="h-10 rounded-lg border-slate-200 px-3.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition active:scale-[0.99] cursor-pointer"
                onClick={() =>
                  navigateTo(
                    `/admin/audit-engagement/${packageState.engagementNumber}/execution`,
                  )
                }
              >
                Return to Execution
              </Button>

              <Button
                type="button"
                variant="outline"
                className="h-10 rounded-lg border-slate-200 px-3.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 flex items-center gap-1.5 transition active:scale-[0.99] cursor-pointer"
                onClick={handleReset}
              >
                <RotateCcw className="h-3.5 w-3.5" />
                <span>Reset</span>
              </Button>

              <Button
                type="button"
                className="h-10 rounded-lg px-4 text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm disabled:opacity-50 flex items-center gap-2 transition active:scale-[0.99] cursor-pointer"
                disabled={!validationResult.isValid || activeEvidence?.status === "Accepted"}
                onClick={handleAcceptEvidence}
              >
                <CheckCircle2 className="h-4 w-4" />
                <span>
                  {activeEvidence?.status === "Accepted"
                    ? "Evidence Accepted"
                    : "Accept for Audit Use"}
                </span>
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
            screenTitle="Audit Evidence Vault & Chain of Custody (SCR-229)"
          />
        ) : !activeEvidence || packageState.evidenceItems.length === 0 ? (
          <div className="rounded-xl border border-dashed border-slate-300 bg-white p-12 text-center space-y-4 shadow-sm">
            <div className="grid h-12 w-12 place-items-center rounded-full bg-blue-50 text-blue-600 mx-auto">
              <ShieldCheck className="h-6 w-6" />
            </div>
            <div className="space-y-1 max-w-md mx-auto">
              <h3 className="text-sm font-bold text-slate-900">
                No Audit Evidence Items Registered Yet
              </h3>
              <p className="text-xs text-slate-500">
                Register and cryptographically hash substantive evidence artifacts, vouchers, and confirmation records for <strong>{packageState.engagementNumber}</strong> ({packageState.office}).
              </p>
            </div>
            <Button
              type="button"
              onClick={handleCreateNewEvidence}
              className="h-9 px-4 rounded-lg text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white shadow-sm flex items-center gap-2 mx-auto cursor-pointer transition active:scale-[0.99]"
            >
              <Plus className="h-4 w-4" />
              <span>Register First Evidence Item</span>
            </Button>
          </div>
        ) : (
          <>
            {/* 2-Column Main Layout Grid */}
            <div className="grid grid-cols-1 gap-3 lg:grid-cols-12">
              {/* Left Column (8 cols): Controlled Evidence Artifacts */}
              <div className="space-y-3 lg:col-span-8">
                {/* 1. Evidence Identity Banner */}
                <EvidenceIdentityBanner
                  evidence={activeEvidence}
                  engagementTitle={packageState.engagementTitle}
                  office={packageState.office}
                />

                {/* 2. Downstream Cross-Referenced Artifacts */}
                <EvidenceRelatedArtifactsSection
                  evidence={activeEvidence}
                  engagementNumber={packageState.engagementNumber}
                />

                {/* 3. Comprehensive Form Section (§7 & §10.6) */}
                <EvidenceFormSection
                  evidence={activeEvidence}
                  onChangeField={handleFieldChange}
                />

                {/* 4. Chain of Custody Audit Trail Log (UID §8.3) */}
                <EvidenceCustodyLogSection
                  evidence={activeEvidence}
                  onAddCustodyEvent={handleAddCustodyEvent}
                  onRemoveCustodyEvent={handleRemoveCustodyEvent}
                />

                {/* 5. Master Evidence Repository Roster */}
                <EvidenceRosterSection
                  evidenceItems={packageState.evidenceItems}
                  activeEvidenceId={packageState.activeEvidenceId}
                  onSelectEvidence={handleSelectEvidence}
                  onCreateNewEvidence={handleCreateNewEvidence}
                />
              </div>

              {/* Right Column (4 cols): Evidence Controls & Governance Checklist */}
              <div className="space-y-3 lg:col-span-4">
                <div className="sticky top-4">
                  <EvidenceControlsRail
                    evidence={activeEvidence}
                    isValid={validationResult.isValid}
                    onSaveDraft={handleSaveDraft}
                    onAcceptEvidence={handleAcceptEvidence}
                    onCreateNewEvidence={handleCreateNewEvidence}
                  />
                </div>
              </div>
            </div>

            {/* Bottom Action Footer Card */}
            <div className="rounded-lg border border-slate-200 bg-white p-3 shadow-sm flex flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-between">
              <div className="flex items-center gap-1.5 text-xs text-slate-500">
                <CheckCircle2 size={14} className="text-emerald-700 shrink-0" />
                <span>
                  Evidence: <strong>{activeEvidence.id} ({activeEvidence.evidenceType})</strong> • Status: <strong>{activeEvidence.status}</strong> • Custodian: <strong>{activeEvidence.custodian}</strong> • Governed under MDS-200 §7 & §10.6
                </span>
              </div>

              <div className="flex items-center gap-2 shrink-0">
                <Button
                  type="button"
                  variant="outline"
                  className="h-10 rounded-lg border-slate-200 px-3.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 cursor-pointer"
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
                  variant="outline"
                  className="h-10 rounded-lg border-slate-200 px-3.5 text-xs font-semibold text-purple-700 hover:bg-purple-50 cursor-pointer"
                  onClick={() =>
                    navigateTo(
                      `/admin/audit-engagement/${packageState.engagementNumber}/execution/workpapers`,
                    )
                  }
                >
                  Working Papers (SCR-228)
                </Button>

                <Button
                  type="button"
                  onClick={handleAcceptEvidence}
                  disabled={!validationResult.isValid || activeEvidence.status === "Accepted"}
                  className="h-10 rounded-lg px-4 text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm disabled:opacity-50 flex items-center gap-2 transition active:scale-[0.99] cursor-pointer"
                >
                  <span>
                    {activeEvidence.status === "Accepted"
                      ? "Evidence Accepted"
                      : "Accept for Audit Use"}
                  </span>
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

export default AdminAuditEvidencePage;
