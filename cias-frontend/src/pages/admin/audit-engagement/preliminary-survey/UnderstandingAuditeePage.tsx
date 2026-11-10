import { useState, useRef, useEffect } from "react";
import {
  ArrowLeft,
  ChevronDown,
  X,
  Plus,
  MoreHorizontal,
  Trash2,
  Edit2,
  Loader2,
} from "lucide-react";
import { AdminLayout } from "../../../../layouts/admin/AdminLayout";
import { navigateTo } from "../../../../routes";
import { useToast } from "../../../../components/ui/toast";
import { localStore } from "../../../../lib/localStore";
import { preliminarySurveyService } from "../../../../services/preliminarySurveyService";

export type ProgramProjectItem = {
  id: string;
  title: string;
  objectives: string;
  beneficiaries: string;
  fundingSource: string;
  budget: string;
  implementationPeriod: string;
  collaborators: string;
  status: string;
};

export type UnderstandingAuditeeFormState = {
  mandate: string;
  mission: string;
  vision: string;
  objectives: string;
  strategicGoals: string;
  totalOfficeAnnualBudget: string;
  personnelServicesBudget: string;
  mooeBudget: string;
  capitalOutlayBudget: string;
  sppBudget: string;
  attachedBudgetName?: string;
  budgetDocumentVersionId?: number | null;
  programsProjects: ProgramProjectItem[];
};

export const defaultUnderstandingAuditeeState: UnderstandingAuditeeFormState = {
  mandate: "",
  mission: "",
  vision: "",
  objectives: "",
  strategicGoals: "",
  totalOfficeAnnualBudget: "",
  personnelServicesBudget: "",
  mooeBudget: "",
  capitalOutlayBudget: "",
  sppBudget: "",
  attachedBudgetName: "",
  programsProjects: [
    {
      id: "row-1",
      title: "",
      objectives: "",
      beneficiaries: "",
      fundingSource: "",
      budget: "",
      implementationPeriod: "",
      collaborators: "",
      status: "",
    },
    {
      id: "row-2",
      title: "",
      objectives: "",
      beneficiaries: "",
      fundingSource: "",
      budget: "",
      implementationPeriod: "",
      collaborators: "",
      status: "",
    },
    {
      id: "row-3",
      title: "",
      objectives: "",
      beneficiaries: "",
      fundingSource: "",
      budget: "",
      implementationPeriod: "",
      collaborators: "",
      status: "",
    },
    {
      id: "row-4",
      title: "",
      objectives: "",
      beneficiaries: "",
      fundingSource: "",
      budget: "",
      implementationPeriod: "",
      collaborators: "",
      status: "",
    },
    {
      id: "row-5",
      title: "",
      objectives: "",
      beneficiaries: "",
      fundingSource: "",
      budget: "",
      implementationPeriod: "",
      collaborators: "",
      status: "",
    },
  ],
};

export function UnderstandingAuditeePage() {
  const { showToast } = useToast();
  const fileInputRef = useRef<HTMLInputElement>(null);
  const moreActionsRef = useRef<HTMLDivElement>(null);
  const [moreActionsOpen, setMoreActionsOpen] = useState(false);
  const [activeRowMenu, setActiveRowMenu] = useState<string | null>(null);
  const [editingRow, setEditingRow] = useState<ProgramProjectItem | null>(null);
  const [modalOpen, setModalOpen] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [isSaving, setIsSaving] = useState(false);
  const [isUploading, setIsUploading] = useState(false);
  const [lockVersion, setLockVersion] = useState<number>(1);

  const segments = window.location.pathname.split("/").filter(Boolean);
  const engagementIndex = segments.findIndex(
    (p) => p === "audit-engagement" || p === "audit-engagements"
  );
  const engagementId =
    (engagementIndex >= 0 && segments[engagementIndex + 1]) || "1";

  const [liveEngagement, setLiveEngagement] = useState<any>(null);
  const [reviewStatus, setReviewStatus] = useState<any>(null);

  const engagement = localStore.getEngagement(engagementId);
  const engagementCode =
    liveEngagement?.engagementCode ||
    liveEngagement?.code ||
    engagement?.code ||
    (String(engagementId).startsWith("AEMS-") ? engagementId : `AEMS-${engagementId}`);

  const [formState, setFormState] = useState<UnderstandingAuditeeFormState>(
    defaultUnderstandingAuditeeState
  );

  useEffect(() => {
    let isMounted = true;
    setIsLoading(true);

    Promise.all([
      preliminarySurveyService.getEngagement(engagementId),
      preliminarySurveyService.getWorkspace(engagementId),
    ])
      .then(([eng, ws]) => {
        if (!isMounted) return;
        if (eng) setLiveEngagement(eng);
        if (ws?.lockVersion) setLockVersion(ws.lockVersion);
        if (ws?.sectionReviews?.auditee) setReviewStatus(ws.sectionReviews.auditee);

        const auditee = ws?.survey?.auditee;
        if (auditee) {
          const budgetFile = ws?.attachments?.find(
            (a) => a.id === auditee.budgetDocumentVersionId
          );
          setFormState({
            mandate: auditee.mandate || "",
            mission: auditee.mission || "",
            vision: auditee.vision || "",
            objectives: auditee.objectives || "",
            strategicGoals: auditee.strategicGoals || "",
            totalOfficeAnnualBudget:
              auditee.annualBudget != null ? String(auditee.annualBudget) : "",
            personnelServicesBudget:
              auditee.personnelBudget != null ? String(auditee.personnelBudget) : "",
            mooeBudget: auditee.mooeBudget != null ? String(auditee.mooeBudget) : "",
            capitalOutlayBudget:
              auditee.capitalBudget != null ? String(auditee.capitalBudget) : "",
            sppBudget: auditee.sppBudget != null ? String(auditee.sppBudget) : "",
            budgetDocumentVersionId: auditee.budgetDocumentVersionId || null,
            attachedBudgetName: budgetFile?.fileName || "",
            programsProjects:
              Array.isArray(auditee.programs) && auditee.programs.length > 0
                ? auditee.programs.map((p, i) => ({
                    id: `row-${i + 1}`,
                    title: p.title || "",
                    objectives: p.objectives || "",
                    beneficiaries: p.beneficiaries || "",
                    fundingSource: p.fundingSource || "",
                    budget: p.budget != null ? String(p.budget) : "",
                    implementationPeriod: p.implementationPeriod || "",
                    collaborators: p.collaborators || "",
                    status: p.status || "Planned",
                  }))
                : defaultUnderstandingAuditeeState.programsProjects,
          });
        }
      })
      .catch((err) => {
        console.warn("Could not load auditee section from backend:", err);
      })
      .finally(() => {
        if (isMounted) setIsLoading(false);
      });

    return () => {
      isMounted = false;
    };
  }, [engagementId]);

  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (
        moreActionsRef.current &&
        !moreActionsRef.current.contains(event.target as Node)
      ) {
        setMoreActionsOpen(false);
      }
      if (
        !(event.target as HTMLElement).closest(".row-action-menu")
      ) {
        setActiveRowMenu(null);
      }
    }
    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, []);

  const handleChange = (
    field: keyof UnderstandingAuditeeFormState,
    value: string
  ) => {
    setFormState((prev) => ({
      ...prev,
      [field]: value,
    }));
  };

  const handleBack = () => {
    navigateTo(`/admin/audit-engagement/${engagementId}/planning`);
  };

  const handleSaveDraft = async () => {
    setIsSaving(true);
    try {
      const payload = {
        mandate: formState.mandate || null,
        mission: formState.mission || null,
        vision: formState.vision || null,
        objectives: formState.objectives || null,
        strategicGoals: formState.strategicGoals || null,
        annualBudget: formState.totalOfficeAnnualBudget
          ? parseFloat(formState.totalOfficeAnnualBudget)
          : null,
        personnelBudget: formState.personnelServicesBudget
          ? parseFloat(formState.personnelServicesBudget)
          : null,
        mooeBudget: formState.mooeBudget
          ? parseFloat(formState.mooeBudget)
          : null,
        capitalBudget: formState.capitalOutlayBudget
          ? parseFloat(formState.capitalOutlayBudget)
          : null,
        sppBudget: formState.sppBudget
          ? parseFloat(formState.sppBudget)
          : null,
        budgetDocumentVersionId: formState.budgetDocumentVersionId || null,
        programs: formState.programsProjects
          .filter((p) => p.title.trim())
          .map((p) => ({
            title: p.title || null,
            objectives: p.objectives || null,
            beneficiaries: p.beneficiaries || null,
            fundingSource: p.fundingSource || null,
            budget: p.budget ? parseFloat(p.budget) : null,
            implementationPeriod: p.implementationPeriod || null,
            collaborators: p.collaborators || null,
            status: p.status || null,
          })),
      };

      const updated = await preliminarySurveyService.saveSection(
        engagementId,
        "auditee",
        payload,
        lockVersion
      );

      if (updated?.lockVersion) setLockVersion(updated.lockVersion);

      showToast({
        title: "Draft Saved",
        description: "Understanding of the Auditee details saved to backend.",
        tone: "success",
      });
    } catch (err: any) {
      showToast({
        title: "Save Failed",
        description: err?.message || "Could not save auditee details.",
        tone: "warning",
      });
    } finally {
      setIsSaving(false);
    }
  };

  const handleFileUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    setIsUploading(true);
    try {
      const uploaded = await preliminarySurveyService.uploadAttachment(engagementId, file);
      setFormState((prev) => ({
        ...prev,
        attachedBudgetName: uploaded.fileName || file.name,
        budgetDocumentVersionId: uploaded.id,
      }));
      showToast({
        title: "File Attached",
        description: `Uploaded "${file.name}" successfully.`,
        tone: "success",
      });
    } catch (err: any) {
      showToast({
        title: "Upload Failed",
        description: err?.message || "Could not upload budget document.",
        tone: "warning",
      });
    } finally {
      setIsUploading(false);
    }
  };

  const handleReviewAction = async (action: "SUBMIT" | "APPROVE" | "RETURN") => {
    try {
      await handleSaveDraft();
      await preliminarySurveyService.reviewSection(
        engagementId,
        "auditee",
        action,
        lockVersion
      );
      showToast({
        title: `Section ${action === "SUBMIT" ? "Submitted" : action === "APPROVE" ? "Approved" : "Returned"}`,
        description: `1.2 Understanding of the Auditee has been updated.`,
        tone: "success",
      });
      setMoreActionsOpen(false);
    } catch (err: any) {
      showToast({
        title: "Action Failed",
        description: err?.message || "Could not complete review action.",
        tone: "warning",
      });
    }
  };

  // Program / Project table handlers
  const handleOpenAddModal = () => {
    setEditingRow({
      id: `row-${Date.now()}`,
      title: "",
      objectives: "",
      beneficiaries: "",
      fundingSource: "",
      budget: "",
      implementationPeriod: "",
      collaborators: "",
      status: "Planned",
    });
    setModalOpen(true);
  };

  const handleSaveProgram = (item: ProgramProjectItem) => {
    setFormState((prev) => {
      const exists = prev.programsProjects.some((p) => p.id === item.id);
      if (exists) {
        return {
          ...prev,
          programsProjects: prev.programsProjects.map((p) =>
            p.id === item.id ? item : p
          ),
        };
      }
      return {
        ...prev,
        programsProjects: [...prev.programsProjects, item],
      };
    });
    setModalOpen(false);
    setEditingRow(null);
    showToast({
      title: "Program / Project Updated",
      description: item.title ? `Saved "${item.title}".` : "Entry updated successfully.",
      tone: "success",
    });
  };

  const handleDeleteRow = (id: string) => {
    setFormState((prev) => ({
      ...prev,
      programsProjects: prev.programsProjects.filter((p) => p.id !== id),
    }));
    setActiveRowMenu(null);
    showToast({
      title: "Row Removed",
      description: "Program or project row removed.",
      tone: "info",
    });
  };

  const handleClearRow = (id: string) => {
    setFormState((prev) => ({
      ...prev,
      programsProjects: prev.programsProjects.map((p) =>
        p.id === id
          ? {
              id,
              title: "",
              objectives: "",
              beneficiaries: "",
              fundingSource: "",
              budget: "",
              implementationPeriod: "",
              collaborators: "",
              status: "",
            }
          : p
      ),
    }));
    setActiveRowMenu(null);
    showToast({
      title: "Row Cleared",
      description: "Row contents cleared.",
      tone: "info",
    });
  };

  return (
    <AdminLayout>
      <div className="w-full font-sans text-[#103565] space-y-1.5 px-3 pb-1">
        {/* Breadcrumb Row matching screenshot */}
        <div className="flex flex-wrap items-center gap-1.5 text-[11px] text-[#2b5a92] px-0.5">
          <button
            type="button"
            onClick={() => navigateTo("/admin/dashboard")}
            className="cursor-pointer hover:text-blue-600 transition"
          >
            Home
          </button>
          <span>&gt;</span>
          <button
            type="button"
            onClick={() => navigateTo("/admin/audit-engagements")}
            className="cursor-pointer hover:text-blue-600 transition"
          >
            Audit Engagements
          </button>
          <span>&gt;</span>
          <button
            type="button"
            onClick={() => navigateTo(`/admin/audit-engagement/${engagementId}`)}
            className="cursor-pointer hover:text-blue-600 transition"
          >
            Audit Engagement Details
          </button>
          <span>&gt;</span>
          <button
            type="button"
            onClick={handleBack}
            className="cursor-pointer hover:text-blue-600 transition"
          >
            Planning
          </button>
          <span>&gt;</span>
          <button
            type="button"
            onClick={handleBack}
            className="cursor-pointer hover:text-blue-600 transition"
          >
            Preliminary Survey
          </button>
          <span>&gt;</span>
          <span className="font-semibold text-[#1877f2]">Understanding of the Auditee</span>
        </div>

        {/* Outer Frame Card - Saturated Blue Background matching screenshot */}
        <div className="w-full overflow-hidden rounded-md border border-[#4485bb] bg-[#dbebf9] shadow-xs">
          {/* Top Header Bar */}
          <div className="flex items-center justify-between border-b border-[#7eaed5] bg-[#c7e0f6] px-4 py-2 text-[12px] font-semibold text-[#103565]">
            <span className="truncate">
              {engagementCode} - Planning - Preliminary Survey -{" "}
              <span className="font-bold text-[#103565]">Understanding of the Auditee</span>
            </span>
            <button
              type="button"
              onClick={handleBack}
              aria-label="Close"
              className="cursor-pointer rounded p-0.5 text-slate-600 hover:bg-[#b0d2ef] hover:text-slate-900 transition"
            >
              <X size={16} strokeWidth={2.4} />
            </button>
          </div>

          {/* Form Content Body */}
          <div className="px-5 py-3.5 sm:px-7 sm:py-4 lg:px-8 space-y-3">
            {/* Title & Progress Header Row */}
            <div className="flex items-center justify-between gap-3">
              <div className="flex items-center gap-3">
                {/* Building / Office Complex SVG Icon matching screenshot */}
                <div className="flex h-10 w-10 shrink-0 items-center justify-center text-[#103565]">
                  <svg
                    viewBox="0 0 48 48"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2.3"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    className="h-10 w-10"
                  >
                    {/* Baseline */}
                    <line x1="5" y1="42" x2="43" y2="42" />

                    {/* Left Wing Building */}
                    <path d="M8 42V22h8v20" />
                    <line x1="12" y1="27" x2="12" y2="36" />

                    {/* Center Tower with Slanted Roof */}
                    <path d="M16 42V8l16 8v26" />
                    {/* Horizontal window slits in center */}
                    <line x1="21" y1="20" x2="27" y2="20" />
                    <line x1="21" y1="26" x2="27" y2="26" />
                    <line x1="21" y1="32" x2="27" y2="32" />
                    {/* Entrance door */}
                    <path d="M21 42v-5h6v5" />

                    {/* Right Wing Building */}
                    <path d="M32 27h8v15" />
                    <line x1="36" y1="32" x2="36" y2="37" />
                  </svg>
                </div>
                <h1 className="text-lg sm:text-[20px] font-bold tracking-tight text-[#103565] flex items-center gap-2">
                  <span>1.2 Understanding of the Auditee</span>
                  {isLoading && <Loader2 size={14} className="animate-spin text-[#1877f2]" />}
                </h1>
              </div>

              <div className="text-[12px] font-medium text-slate-500">
                2 of 7 items completed
              </div>
            </div>

            {/* Review Status Banner */}
            {reviewStatus && (
              <div className="mx-auto w-full max-w-[1000px] mb-2 flex flex-wrap items-center gap-3 rounded-md border border-[#aacde5] bg-[#edf6ff] px-3.5 py-1.5 text-xs text-[#103565]">
                <span
                  className={`rounded px-2 py-0.5 font-bold ${
                    reviewStatus.status === "APPROVED"
                      ? "bg-emerald-100 text-emerald-800"
                      : reviewStatus.status === "PENDING_REVIEW"
                      ? "bg-amber-100 text-amber-800"
                      : reviewStatus.status === "RETURNED_FOR_REVISION"
                      ? "bg-rose-100 text-rose-800"
                      : "bg-slate-200 text-slate-700"
                  }`}
                >
                  {reviewStatus.status === "APPROVED"
                    ? "Approved"
                    : reviewStatus.status === "PENDING_REVIEW"
                    ? "Pending Review"
                    : reviewStatus.status === "RETURNED_FOR_REVISION"
                    ? "Returned for Revision"
                    : "Draft"}
                </span>
                {reviewStatus.lastSavedBy && (
                  <span className="text-slate-600">
                    Last saved by {reviewStatus.lastSavedBy.name}
                  </span>
                )}
                {reviewStatus.submittedBy && (
                  <span className="text-slate-600">
                    Submitted by {reviewStatus.submittedBy.name}
                  </span>
                )}
                {reviewStatus.approvedBy && (
                  <span className="text-emerald-700 font-medium">
                    Approved by {reviewStatus.approvedBy.name}
                  </span>
                )}
                {reviewStatus.returnComment && (
                  <p className="w-full text-rose-700 font-medium mt-1">
                    Returned by {reviewStatus.returnedBy?.name}: {reviewStatus.returnComment}
                  </p>
                )}
              </div>
            )}

            {/* Centered Form Fields Container */}
            <div className="mx-auto w-full max-w-[1000px] pt-0.5 space-y-2.5">
              {/* Row 1: Mandate */}
              <div className="grid grid-cols-1 gap-x-8 lg:grid-cols-2">
                <div>
                  <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                    Mandate
                  </label>
                  <input
                    type="text"
                    value={formState.mandate}
                    onChange={(e) => handleChange("mandate", e.target.value)}
                    className="h-8 w-full max-w-[460px] rounded-[5px] border border-[#7eaed5] bg-white px-3 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                  />
                </div>
                <div className="hidden lg:block" />
              </div>

              {/* Row 2: Mission & Vision */}
              <div className="grid grid-cols-1 gap-x-8 gap-y-2.5 lg:grid-cols-2">
                <div>
                  <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                    Mission
                  </label>
                  <textarea
                    rows={2}
                    value={formState.mission}
                    onChange={(e) => handleChange("mission", e.target.value)}
                    className="h-[52px] w-full max-w-[460px] rounded-[5px] border border-[#7eaed5] bg-white px-3 py-1.5 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2] resize-none"
                  />
                </div>

                <div>
                  <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                    Vision
                  </label>
                  <textarea
                    rows={2}
                    value={formState.vision}
                    onChange={(e) => handleChange("vision", e.target.value)}
                    className="h-[52px] w-full max-w-[460px] rounded-[5px] border border-[#7eaed5] bg-white px-3 py-1.5 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2] resize-none"
                  />
                </div>
              </div>

              {/* Row 3: Objectives & Strategic Goals, if any */}
              <div className="grid grid-cols-1 gap-x-8 gap-y-2.5 lg:grid-cols-2">
                <div>
                  <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                    Objectives
                  </label>
                  <textarea
                    rows={2}
                    value={formState.objectives}
                    onChange={(e) => handleChange("objectives", e.target.value)}
                    className="h-[52px] w-full max-w-[460px] rounded-[5px] border border-[#7eaed5] bg-white px-3 py-1.5 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2] resize-none"
                  />
                </div>

                <div>
                  <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                    Strategic Goals, if any
                  </label>
                  <textarea
                    rows={2}
                    value={formState.strategicGoals}
                    onChange={(e) => handleChange("strategicGoals", e.target.value)}
                    className="h-[52px] w-full max-w-[460px] rounded-[5px] border border-[#7eaed5] bg-white px-3 py-1.5 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2] resize-none"
                  />
                </div>
              </div>

              {/* Row 4: Five Budget inputs spanning full row */}
              <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4 pt-1">
                <div>
                  <label
                    className="mb-1 block text-[11px] sm:text-[12px] font-semibold text-[#1a3d6d] truncate"
                    title="Total Office Annual Budget"
                  >
                    Total Office Annual Budget
                  </label>
                  <input
                    type="text"
                    value={formState.totalOfficeAnnualBudget}
                    onChange={(e) => handleChange("totalOfficeAnnualBudget", e.target.value)}
                    className="h-8 w-full rounded-[5px] border border-[#7eaed5] bg-white px-2.5 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                  />
                </div>

                <div>
                  <label
                    className="mb-1 block text-[11px] sm:text-[12px] font-semibold text-[#1a3d6d] truncate"
                    title="Personnel Services Budget"
                  >
                    Personnel Services Budget
                  </label>
                  <input
                    type="text"
                    value={formState.personnelServicesBudget}
                    onChange={(e) => handleChange("personnelServicesBudget", e.target.value)}
                    className="h-8 w-full rounded-[5px] border border-[#7eaed5] bg-white px-2.5 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                  />
                </div>

                <div>
                  <label
                    className="mb-1 block text-[11px] sm:text-[12px] font-semibold text-[#1a3d6d] truncate"
                    title="MOOE Budget"
                  >
                    MOOE Budget
                  </label>
                  <input
                    type="text"
                    value={formState.mooeBudget}
                    onChange={(e) => handleChange("mooeBudget", e.target.value)}
                    className="h-8 w-full rounded-[5px] border border-[#7eaed5] bg-white px-2.5 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                  />
                </div>

                <div>
                  <label
                    className="mb-1 block text-[11px] sm:text-[12px] font-semibold text-[#1a3d6d] truncate"
                    title="Capital Outlay Budget"
                  >
                    Capital Outlay Budget
                  </label>
                  <input
                    type="text"
                    value={formState.capitalOutlayBudget}
                    onChange={(e) => handleChange("capitalOutlayBudget", e.target.value)}
                    className="h-8 w-full rounded-[5px] border border-[#7eaed5] bg-white px-2.5 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                  />
                </div>

                <div>
                  <label
                    className="mb-1 block text-[11px] sm:text-[12px] font-semibold text-[#1a3d6d] truncate"
                    title="SPP Budget"
                  >
                    SPP Budget
                  </label>
                  <input
                    type="text"
                    value={formState.sppBudget}
                    onChange={(e) => handleChange("sppBudget", e.target.value)}
                    className="h-8 w-full rounded-[5px] border border-[#7eaed5] bg-white px-2.5 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                  />
                </div>
              </div>

              {/* Row 5: Attach Office Annual Budget */}
              <div className="pt-0.5">
                <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                  Attach Office Annual Budget
                </label>
                <input
                  type="file"
                  ref={fileInputRef}
                  onChange={handleFileUpload}
                  className="hidden"
                  accept=".pdf,.doc,.docx"
                />
                <div className="flex flex-wrap items-center gap-2.5">
                  {/* Dashed dropzone matching screenshot */}
                  <div
                    onClick={() => fileInputRef.current?.click()}
                    className="w-full max-w-[370px] sm:max-w-[420px] cursor-pointer rounded-[5px] border border-dashed border-[#6ea0cd] bg-[#edf5fc] px-3 py-1.5 text-center transition-colors hover:bg-[#e4eff9]"
                  >
                    <p className="text-[11px] text-[#4d7ea8] truncate">
                      {formState.attachedBudgetName ? (
                        <span className="font-semibold text-[#103565]">
                          Selected: {formState.attachedBudgetName}
                        </span>
                      ) : (
                        "Click to upload or drag and drop PDF, DOC, DOCX (Max 10 MB)"
                      )}
                    </p>
                  </div>
                  <button
                    type="button"
                    onClick={() => fileInputRef.current?.click()}
                    disabled={isUploading}
                    className="h-7 cursor-pointer rounded border border-slate-300 bg-white px-3 text-[11px] font-semibold text-slate-700 shadow-2xs hover:bg-slate-50 transition disabled:opacity-50"
                  >
                    {isUploading ? "Uploading..." : "Upload File"}
                  </button>
                </div>
              </div>

              {/* Row 6: Program / Project Review Header and Table */}
              <div className="pt-1.5 space-y-1.5">
                <div className="flex items-center justify-between">
                  <h3 className="text-[12px] font-semibold text-[#1a3d6d]">
                    Program / Project Review
                  </h3>
                  <button
                    type="button"
                    onClick={handleOpenAddModal}
                    className="flex cursor-pointer items-center gap-1 rounded border border-slate-300 bg-white px-3 py-1 text-[11px] font-semibold text-slate-700 shadow-2xs hover:bg-slate-50 transition"
                  >
                    <Plus size={12} strokeWidth={2.4} />
                    <span>Add Program or Project</span>
                  </button>
                </div>

                {/* Table matching screenshot */}
                <div className="overflow-x-auto rounded-[4px] border border-[#7eaed5] bg-white">
                  <table className="w-full border-collapse text-left text-[11px]">
                    <thead>
                      <tr className="border-b border-[#7eaed5] bg-[#dbebf9] text-[#1a3d6d]">
                        <th className="border-r border-[#7eaed5] px-2.5 py-1.5 font-semibold w-[14%]">
                          Title
                        </th>
                        <th className="border-r border-[#7eaed5] px-2.5 py-1.5 font-semibold w-[14%]">
                          Objectives
                        </th>
                        <th className="border-r border-[#7eaed5] px-2.5 py-1.5 font-semibold w-[10%]">
                          Beneficiaries
                        </th>
                        <th className="border-r border-[#7eaed5] px-2.5 py-1.5 font-semibold w-[11%]">
                          Funding Source
                        </th>
                        <th className="border-r border-[#7eaed5] px-2.5 py-1.5 font-semibold w-[9%]">
                          Budget
                        </th>
                        <th className="border-r border-[#7eaed5] px-2.5 py-1.5 font-semibold w-[15%]">
                          Implementation Period
                        </th>
                        <th className="border-r border-[#7eaed5] px-2.5 py-1.5 font-semibold w-[15%]">
                          Collaborators
                        </th>
                        <th className="border-r border-[#7eaed5] px-2.5 py-1.5 font-semibold w-[9%]">
                          Status
                        </th>
                        <th className="px-2 py-1.5 text-center font-semibold w-[3%]">
                          {/* Options column */}
                        </th>
                      </tr>
                    </thead>
                    <tbody>
                      {formState.programsProjects.map((row, index) => (
                        <tr
                          key={row.id || index}
                          className="border-b border-[#c7e0f6] last:border-b-0 hover:bg-[#f3f8fd] transition-colors h-[26px]"
                        >
                          <td className="border-r border-[#c7e0f6] px-2.5 py-1 text-slate-800 truncate">
                            {row.title || "\u00A0"}
                          </td>
                          <td className="border-r border-[#c7e0f6] px-2.5 py-1 text-slate-800 truncate">
                            {row.objectives}
                          </td>
                          <td className="border-r border-[#c7e0f6] px-2.5 py-1 text-slate-800 truncate">
                            {row.beneficiaries}
                          </td>
                          <td className="border-r border-[#c7e0f6] px-2.5 py-1 text-slate-800 truncate">
                            {row.fundingSource}
                          </td>
                          <td className="border-r border-[#c7e0f6] px-2.5 py-1 text-slate-800 truncate">
                            {row.budget}
                          </td>
                          <td className="border-r border-[#c7e0f6] px-2.5 py-1 text-slate-800 truncate">
                            {row.implementationPeriod}
                          </td>
                          <td className="border-r border-[#c7e0f6] px-2.5 py-1 text-slate-800 truncate">
                            {row.collaborators}
                          </td>
                          <td className="border-r border-[#c7e0f6] px-2.5 py-1 text-slate-800 truncate">
                            {row.status}
                          </td>
                          <td className="relative px-1 py-1 text-center">
                            <button
                              type="button"
                              onClick={() =>
                                setActiveRowMenu(
                                  activeRowMenu === row.id ? null : row.id
                                )
                              }
                              aria-label="Row actions"
                              className="cursor-pointer text-slate-500 hover:text-slate-800 p-0.5 rounded transition"
                            >
                              <MoreHorizontal size={14} />
                            </button>

                            {/* Dropdown menu for row */}
                            {activeRowMenu === row.id && (
                              <div className="row-action-menu absolute right-2 top-full mt-1 z-50 w-28 rounded-md border border-slate-200 bg-white py-1 shadow-md text-[11px] text-left">
                                <button
                                  type="button"
                                  onClick={() => {
                                    setEditingRow(row);
                                    setModalOpen(true);
                                    setActiveRowMenu(null);
                                  }}
                                  className="flex w-full items-center gap-1.5 px-2.5 py-1 hover:bg-[#d8ecff] text-slate-700 transition"
                                >
                                  <Edit2 size={11} /> Edit Row
                                </button>
                                <button
                                  type="button"
                                  onClick={() => handleClearRow(row.id)}
                                  className="flex w-full items-center gap-1.5 px-2.5 py-1 hover:bg-slate-50 text-slate-600 transition"
                                >
                                  Clear
                                </button>
                                {formState.programsProjects.length > 1 && (
                                  <button
                                    type="button"
                                    onClick={() => handleDeleteRow(row.id)}
                                    className="flex w-full items-center gap-1.5 px-2.5 py-1 hover:bg-rose-50 text-rose-600 transition"
                                  >
                                    <Trash2 size={11} /> Delete
                                  </button>
                                )}
                              </div>
                            )}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            {/* Bottom Enclosed Action Panel matching screenshot */}
            <div className="mx-1 mt-4 flex flex-wrap items-center justify-end gap-2.5 rounded-[6px] border border-[#7eaed5] bg-[#dbebf9] px-4 py-2.5">
              <button
                type="button"
                onClick={handleBack}
                className="flex cursor-pointer items-center gap-1.5 rounded-[5px] border border-slate-300 bg-white px-3.5 py-1.5 text-xs font-medium text-slate-700 shadow-2xs hover:border-[#2478bf] hover:bg-[#d8ecff] hover:text-[#0b4d91] transition"
              >
                <ArrowLeft size={13} /> Back to Planning Workspace
              </button>

              <button
                type="button"
                onClick={handleSaveDraft}
                disabled={isSaving}
                className="flex cursor-pointer items-center gap-1.5 rounded-[5px] border border-slate-300 bg-white px-4 py-1.5 text-xs font-medium text-slate-700 shadow-2xs hover:border-[#2478bf] hover:bg-[#d8ecff] hover:text-[#0b4d91] transition disabled:opacity-50"
              >
                {isSaving && <Loader2 size={13} className="animate-spin text-[#1877f2]" />}
                <span>{isSaving ? "Saving..." : "Save Draft"}</span>
              </button>

              <div className="relative" ref={moreActionsRef}>
                <button
                  type="button"
                  onClick={() => setMoreActionsOpen(!moreActionsOpen)}
                  className="flex cursor-pointer items-center gap-1 rounded-[5px] bg-[#1877f2] px-3.5 py-1.5 text-xs font-semibold text-white shadow-2xs hover:bg-[#0f5fc9] transition"
                >
                  <span>More Actions</span>
                  <ChevronDown size={14} />
                </button>

                {moreActionsOpen && (
                  <div className="absolute right-0 bottom-full mb-1 w-48 rounded-md border border-slate-200 bg-white py-1 shadow-lg z-50 text-xs text-slate-700 animate-in fade-in-50 zoom-in-95">
                    <button
                      type="button"
                      onClick={() => handleReviewAction("SUBMIT")}
                      className="w-full text-left px-3 py-1.5 font-medium text-[#1877f2] hover:bg-[#d8ecff] hover:text-[#0b4d91] transition"
                    >
                      Submit for Review
                    </button>
                    <button
                      type="button"
                      onClick={() => {
                        window.print();
                        setMoreActionsOpen(false);
                      }}
                      className="w-full text-left px-3 py-1.5 hover:bg-[#d8ecff] hover:text-[#0b4d91] transition"
                    >
                      Print Summary
                    </button>
                    <button
                      type="button"
                      onClick={() => {
                        showToast({
                          title: "Exporting PDF",
                          description: "Generating Auditee Profile report...",
                          tone: "info",
                        });
                        setMoreActionsOpen(false);
                      }}
                      className="w-full text-left px-3 py-1.5 hover:bg-[#d8ecff] hover:text-[#0b4d91] transition"
                    >
                      Export Profile (PDF)
                    </button>
                    <div className="my-1 border-t border-slate-100" />
                    <button
                      type="button"
                      onClick={() => {
                        handleSaveDraft();
                        showToast({
                          title: "Marked Complete",
                          description: "1.2 Understanding of the Auditee marked as complete.",
                          tone: "success",
                        });
                        setMoreActionsOpen(false);
                      }}
                      className="w-full text-left px-3 py-1.5 text-emerald-700 hover:bg-emerald-50 transition"
                    >
                      Mark as Completed
                    </button>
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Modal for Adding / Editing Program or Project */}
      {modalOpen && editingRow && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-2xs p-4 animate-in fade-in-50">
          <div className="w-full max-w-lg rounded-lg border border-[#7eaed5] bg-[#dbebf9] p-5 shadow-xl text-[#103565] space-y-3">
            <div className="flex items-center justify-between border-b border-[#7eaed5] pb-2">
              <h4 className="text-sm font-bold text-[#103565]">
                {editingRow.title ? "Edit Program / Project" : "Add Program / Project"}
              </h4>
              <button
                type="button"
                onClick={() => setModalOpen(false)}
                className="cursor-pointer rounded p-1 text-slate-500 hover:bg-[#c7e0f6] transition"
              >
                <X size={16} />
              </button>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
              <div className="sm:col-span-2">
                <label className="mb-1 block font-semibold text-[#1a3d6d]">Title</label>
                <input
                  type="text"
                  value={editingRow.title}
                  onChange={(e) =>
                    setEditingRow({ ...editingRow, title: e.target.value })
                  }
                  placeholder="e.g. Modernized Inventory Tracking System"
                  className="h-8 w-full rounded border border-[#7eaed5] bg-white px-2.5 text-xs text-slate-800 outline-none focus:border-[#1877f2]"
                />
              </div>

              <div>
                <label className="mb-1 block font-semibold text-[#1a3d6d]">Objectives</label>
                <input
                  type="text"
                  value={editingRow.objectives}
                  onChange={(e) =>
                    setEditingRow({ ...editingRow, objectives: e.target.value })
                  }
                  placeholder="e.g. Automate warehouse tracking"
                  className="h-8 w-full rounded border border-[#7eaed5] bg-white px-2.5 text-xs text-slate-800 outline-none focus:border-[#1877f2]"
                />
              </div>

              <div>
                <label className="mb-1 block font-semibold text-[#1a3d6d]">Beneficiaries</label>
                <input
                  type="text"
                  value={editingRow.beneficiaries}
                  onChange={(e) =>
                    setEditingRow({ ...editingRow, beneficiaries: e.target.value })
                  }
                  placeholder="e.g. City departments"
                  className="h-8 w-full rounded border border-[#7eaed5] bg-white px-2.5 text-xs text-slate-800 outline-none focus:border-[#1877f2]"
                />
              </div>

              <div>
                <label className="mb-1 block font-semibold text-[#1a3d6d]">Funding Source</label>
                <input
                  type="text"
                  value={editingRow.fundingSource}
                  onChange={(e) =>
                    setEditingRow({ ...editingRow, fundingSource: e.target.value })
                  }
                  placeholder="e.g. General Fund"
                  className="h-8 w-full rounded border border-[#7eaed5] bg-white px-2.5 text-xs text-slate-800 outline-none focus:border-[#1877f2]"
                />
              </div>

              <div>
                <label className="mb-1 block font-semibold text-[#1a3d6d]">Budget</label>
                <input
                  type="text"
                  value={editingRow.budget}
                  onChange={(e) =>
                    setEditingRow({ ...editingRow, budget: e.target.value })
                  }
                  placeholder="e.g. ₱ 1,500,000"
                  className="h-8 w-full rounded border border-[#7eaed5] bg-white px-2.5 text-xs text-slate-800 outline-none focus:border-[#1877f2]"
                />
              </div>

              <div>
                <label className="mb-1 block font-semibold text-[#1a3d6d]">Implementation Period</label>
                <input
                  type="text"
                  value={editingRow.implementationPeriod}
                  onChange={(e) =>
                    setEditingRow({ ...editingRow, implementationPeriod: e.target.value })
                  }
                  placeholder="e.g. Q1 - Q3 2026"
                  className="h-8 w-full rounded border border-[#7eaed5] bg-white px-2.5 text-xs text-slate-800 outline-none focus:border-[#1877f2]"
                />
              </div>

              <div>
                <label className="mb-1 block font-semibold text-[#1a3d6d]">Collaborators</label>
                <input
                  type="text"
                  value={editingRow.collaborators}
                  onChange={(e) =>
                    setEditingRow({ ...editingRow, collaborators: e.target.value })
                  }
                  placeholder="e.g. City IT Office"
                  className="h-8 w-full rounded border border-[#7eaed5] bg-white px-2.5 text-xs text-slate-800 outline-none focus:border-[#1877f2]"
                />
              </div>

              <div className="sm:col-span-2">
                <label className="mb-1 block font-semibold text-[#1a3d6d]">Status</label>
                <select
                  value={editingRow.status}
                  onChange={(e) =>
                    setEditingRow({ ...editingRow, status: e.target.value })
                  }
                  className="h-8 w-full rounded border border-[#7eaed5] bg-white px-2.5 text-xs text-slate-800 outline-none focus:border-[#1877f2]"
                >
                  <option value="Planned">Planned</option>
                  <option value="Ongoing">Ongoing</option>
                  <option value="Completed">Completed</option>
                  <option value="Deferred">Deferred</option>
                </select>
              </div>
            </div>

            <div className="flex items-center justify-end gap-2 pt-2 border-t border-[#7eaed5]">
              <button
                type="button"
                onClick={() => setModalOpen(false)}
                className="cursor-pointer rounded border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 transition"
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={() => handleSaveProgram(editingRow)}
                className="cursor-pointer rounded bg-[#1877f2] px-4 py-1.5 text-xs font-semibold text-white shadow-2xs hover:bg-[#0f5fc9] transition"
              >
                Save
              </button>
            </div>
          </div>
        </div>
      )}
    </AdminLayout>
  );
}
