import { useState, useRef, useEffect } from "react";
import { ArrowLeft, ChevronDown, X, MoreHorizontal, Edit2, Loader2 } from "lucide-react";
import { AdminLayout } from "../../../../layouts/admin/AdminLayout";
import { navigateTo } from "../../../../routes";
import { useToast } from "../../../../components/ui/toast";
import { localStore } from "../../../../lib/localStore";
import { preliminarySurveyService } from "../../../../services/preliminarySurveyService";

export type IcsPrincipleRow = {
  id: string;
  sectionId: string;
  no: number;
  principle: string;
  auditorChecklistQuestion: string;
  preliminaryView: string;
  basisSource: string;
  keyMatters: string;
  preliminaryAssessment: string;
  nextAction: string;
};

export type IcsAccordionSection = {
  id: "1.4.A" | "1.4.B" | "1.4.C" | "1.4.D" | "1.4.E";
  code: string;
  title: string;
};

export const ICS_SECTIONS: IcsAccordionSection[] = [
  { id: "1.4.A", code: "1.4.A", title: "Control Environment (Principles 1-5)" },
  { id: "1.4.B", code: "1.4.B", title: "Risk Assessment (Principles 6-8)" },
  { id: "1.4.C", code: "1.4.C", title: "Control Activities (Principles 9-11)" },
  { id: "1.4.D", code: "1.4.D", title: "Information and Communication (Principles 12-14)" },
  { id: "1.4.E", code: "1.4.E", title: "Monitoring (Principles 15-16)" },
];

export const DEFAULT_ICS_ROWS: IcsPrincipleRow[] = [
  // 1.4.A Control Environment (Principles 1-5) matching user screenshot 2
  {
    id: "ics-1",
    sectionId: "1.4.A",
    no: 1,
    principle: "Integrity and ethical values",
    auditorChecklistQuestion: "Are integrity and ethical values established and demonstrated?",
    preliminaryView: "No Apparent Concern",
    basisSource: "Code of Conduct; interview",
    keyMatters: "-",
    preliminaryAssessment: "-",
    nextAction: "None",
  },
  {
    id: "ics-2",
    sectionId: "1.4.A",
    no: 2,
    principle: "Tone at the top",
    auditorChecklistQuestion: "Does leadership visibly support controls and expects others to follow?",
    preliminaryView: "No Apparent Concern",
    basisSource: "Office orders; interview",
    keyMatters: "-",
    preliminaryAssessment: "-",
    nextAction: "None",
  },
  {
    id: "ics-3",
    sectionId: "1.4.A",
    no: 3,
    principle: "Organizational structure",
    auditorChecklistQuestion: "Is the structure appropriate to achieve objectives?",
    preliminaryView: "No Apparent Concern",
    basisSource: "Approved org chart",
    keyMatters: "-",
    preliminaryAssessment: "-",
    nextAction: "None",
  },
  {
    id: "ics-4",
    sectionId: "1.4.A",
    no: 4,
    principle: "Commitment to competence",
    auditorChecklistQuestion: "Are personnel competent for their responsibilities?",
    preliminaryView: "More Information Needed",
    basisSource: "Plantilla; position descriptions",
    keyMatters: "Training records for key personnel not yet available",
    preliminaryAssessment: "-",
    nextAction: "Add to DRL",
  },
  {
    id: "ics-5",
    sectionId: "1.4.A",
    no: 5,
    principle: "Human resource policies and practices",
    auditorChecklistQuestion: "Are HR policies and practices aligned with objectives?",
    preliminaryView: "No Apparent Concern",
    basisSource: "HR manual; personnel records",
    keyMatters: "-",
    preliminaryAssessment: "-",
    nextAction: "None",
  },

  // 1.4.B Risk Assessment (Principles 6-8) matching user screenshot 3
  {
    id: "ics-6",
    sectionId: "1.4.B",
    no: 6,
    principle: "Clear Objectives and Risk Tolerance",
    auditorChecklistQuestion: "Are integrity and ethical values established and demonstrated?",
    preliminaryView: "-",
    basisSource: "-",
    keyMatters: "-",
    preliminaryAssessment: "-",
    nextAction: "-",
  },
  {
    id: "ics-7",
    sectionId: "1.4.B",
    no: 7,
    principle: "Risk Identification and Assessment",
    auditorChecklistQuestion: "Does leadership visibly support controls and expects others to follow?",
    preliminaryView: "-",
    basisSource: "-",
    keyMatters: "-",
    preliminaryAssessment: "-",
    nextAction: "-",
  },
  {
    id: "ics-8",
    sectionId: "1.4.B",
    no: 8,
    principle: "Risk Response",
    auditorChecklistQuestion: "Is the structure appropriate to achieve objectives?",
    preliminaryView: "-",
    basisSource: "-",
    keyMatters: "-",
    preliminaryAssessment: "-",
    nextAction: "-",
  },

  // 1.4.C Control Activities (Principles 9-11) matching user screenshot 4
  {
    id: "ics-9",
    sectionId: "1.4.C",
    no: 9,
    principle: "Appropriate Control Activities",
    auditorChecklistQuestion: "Are integrity and ethical values established and demonstrated?",
    preliminaryView: "-",
    basisSource: "-",
    keyMatters: "-",
    preliminaryAssessment: "-",
    nextAction: "-",
  },
  {
    id: "ics-10",
    sectionId: "1.4.C",
    no: 10,
    principle: "Policies and Procedures",
    auditorChecklistQuestion: "Does leadership visibly support controls and expects others to follow?",
    preliminaryView: "-",
    basisSource: "-",
    keyMatters: "-",
    preliminaryAssessment: "-",
    nextAction: "-",
  },
  {
    id: "ics-11",
    sectionId: "1.4.C",
    no: 11,
    principle: "Information Technology Controls",
    auditorChecklistQuestion: "Is the structure appropriate to achieve objectives?",
    preliminaryView: "-",
    basisSource: "-",
    keyMatters: "-",
    preliminaryAssessment: "-",
    nextAction: "-",
  },

  // 1.4.D Information and Communication (Principles 12-14) matching user screenshot 5
  {
    id: "ics-12",
    sectionId: "1.4.D",
    no: 12,
    principle: "Reliable and Relevant Information",
    auditorChecklistQuestion: "Are integrity and ethical values established and demonstrated?",
    preliminaryView: "-",
    basisSource: "-",
    keyMatters: "-",
    preliminaryAssessment: "-",
    nextAction: "-",
  },
  {
    id: "ics-13",
    sectionId: "1.4.D",
    no: 13,
    principle: "Internal Communication",
    auditorChecklistQuestion: "Does leadership visibly support controls and expects others to follow?",
    preliminaryView: "-",
    basisSource: "-",
    keyMatters: "-",
    preliminaryAssessment: "-",
    nextAction: "-",
  },
  {
    id: "ics-14",
    sectionId: "1.4.D",
    no: 14,
    principle: "External Communication",
    auditorChecklistQuestion: "Is the structure appropriate to achieve objectives?",
    preliminaryView: "-",
    basisSource: "-",
    keyMatters: "-",
    preliminaryAssessment: "-",
    nextAction: "-",
  },

  // 1.4.E Monitoring (Principles 15-16) matching user screenshot
  {
    id: "ics-15",
    sectionId: "1.4.E",
    no: 15,
    principle: "Monitoring of Internal Controls",
    auditorChecklistQuestion: "Are integrity and ethical values established and demonstrated?",
    preliminaryView: "-",
    basisSource: "-",
    keyMatters: "-",
    preliminaryAssessment: "-",
    nextAction: "-",
  },
  {
    id: "ics-16",
    sectionId: "1.4.E",
    no: 16,
    principle: "Action on Audit and Review Findings",
    auditorChecklistQuestion: "Does leadership visibly support controls and expects others to follow?",
    preliminaryView: "-",
    basisSource: "-",
    keyMatters: "-",
    preliminaryAssessment: "-",
    nextAction: "-",
  },
];

export function InitialAssessmentIcsPage() {
  const { showToast } = useToast();
  const moreActionsRef = useRef<HTMLDivElement>(null);
  const [moreActionsOpen, setMoreActionsOpen] = useState(false);

  // Accordion open states (default: all collapsed as shown in initial view)
  const [openSections, setOpenSections] = useState<Record<string, boolean>>({
    "1.4.A": false,
    "1.4.B": false,
    "1.4.C": false,
    "1.4.D": false,
    "1.4.E": false,
  });

  const toggleSection = (id: string) => {
    setOpenSections((prev) => ({
      ...prev,
      [id]: !prev[id],
    }));
  };

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

  const [isLoading, setIsLoading] = useState(true);
  const [isSaving, setIsSaving] = useState(false);
  const [lockVersion, setLockVersion] = useState<number>(1);

  const [rows, setRows] = useState<IcsPrincipleRow[]>(DEFAULT_ICS_ROWS);

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
        if (ws?.sectionReviews?.ics) setReviewStatus(ws.sectionReviews.ics);

        const assessments = ws?.survey?.ics?.assessments;
        if (Array.isArray(assessments) && assessments.length > 0) {
          setRows((prev) =>
            prev.map((r) => {
              const found = assessments.find((a) => a.principleId === r.no);
              if (!found) return r;
              return {
                ...r,
                preliminaryView: found.preliminaryView || "-",
                basisSource: found.basisSource || "-",
                keyMatters: found.keyMatters || "-",
                preliminaryAssessment: found.preliminaryAssessment || "-",
                nextAction: found.nextAction || "-",
              };
            })
          );
        }
      })
      .catch((err) => {
        console.warn("Could not load ICS section from backend:", err);
      })
      .finally(() => {
        if (isMounted) setIsLoading(false);
      });

    return () => {
      isMounted = false;
    };
  }, [engagementId]);

  // Editor Panel State (matching user screenshot 1)
  const [isEditorOpen, setIsEditorOpen] = useState(false);
  const [editingRow, setEditingRow] = useState<IcsPrincipleRow>({
    id: "ics-1",
    sectionId: "1.4.A",
    no: 1,
    principle: "",
    auditorChecklistQuestion: "",
    preliminaryView: "",
    basisSource: "",
    keyMatters: "",
    preliminaryAssessment: "",
    nextAction: "",
  });

  const [activeMenuId, setActiveMenuId] = useState<string | null>(null);

  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (
        moreActionsRef.current &&
        !moreActionsRef.current.contains(event.target as Node)
      ) {
        setMoreActionsOpen(false);
      }
      if (!(event.target as HTMLElement).closest(".ics-row-action-menu")) {
        setActiveMenuId(null);
      }
    }
    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, []);

  const handleOpenEditRow = (row: IcsPrincipleRow) => {
    setEditingRow({ ...row });
    setIsEditorOpen(true);
    setActiveMenuId(null);
  };

  const handleSaveRowToTable = () => {
    setRows((prev) =>
      prev.map((r) => (r.id === editingRow.id ? editingRow : r))
    );
    setIsEditorOpen(false);
    showToast({
      title: "Updated to Table",
      description: `Saved Principle ${editingRow.no}: ${editingRow.principle}`,
      tone: "success",
    });
  };

  const handleClearRow = (id: string) => {
    setRows((prev) =>
      prev.map((r) =>
        r.id === id
          ? {
              ...r,
              preliminaryView: "-",
              basisSource: "-",
              keyMatters: "-",
              preliminaryAssessment: "-",
              nextAction: "-",
            }
          : r
      )
    );
    setActiveMenuId(null);
    showToast({
      title: "Row Cleared",
      description: "Row contents reset to initial state.",
      tone: "info",
    });
  };

  const handleBack = () => {
    navigateTo(`/admin/audit-engagement/${engagementId}/planning`);
  };

  const handleSaveDraft = async () => {
    setIsSaving(true);
    try {
      const assessments = rows.map((r) => ({
        principleId: r.no,
        preliminaryView: r.preliminaryView !== "-" ? r.preliminaryView : null,
        basisSource: r.basisSource !== "-" ? r.basisSource : null,
        keyMatters: r.keyMatters !== "-" ? r.keyMatters : null,
        preliminaryAssessment:
          r.preliminaryAssessment !== "-" ? r.preliminaryAssessment : null,
        nextAction: r.nextAction !== "-" ? r.nextAction : null,
      }));

      const updated = await preliminarySurveyService.saveSection(
        engagementId,
        "ics",
        { assessments },
        lockVersion
      );

      if (updated?.lockVersion) setLockVersion(updated.lockVersion);

      showToast({
        title: "Draft Saved",
        description: "Initial Assessment of the ICS saved to backend.",
        tone: "success",
      });
    } catch (err: any) {
      showToast({
        title: "Save Failed",
        description: err?.message || "Could not save ICS assessment.",
        tone: "warning",
      });
    } finally {
      setIsSaving(false);
    }
  };

  const handleReviewAction = async (action: "SUBMIT" | "APPROVE" | "RETURN") => {
    try {
      await handleSaveDraft();
      await preliminarySurveyService.reviewSection(
        engagementId,
        "ics",
        action,
        lockVersion
      );
      showToast({
        title: `Section ${action === "SUBMIT" ? "Submitted" : action === "APPROVE" ? "Approved" : "Returned"}`,
        description: `1.4 Initial Assessment of the ICS has been updated.`,
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

  // Count completed items out of 16 (matching "2 of 16 items completed")
  const totalPrinciples = 16;
  const completedCount = rows.filter(
    (r) =>
      (r.preliminaryView && r.preliminaryView !== "-") ||
      (r.basisSource && r.basisSource !== "-") ||
      (r.keyMatters && r.keyMatters !== "-")
  ).length;

  return (
    <AdminLayout>
      <div className="w-full font-sans text-[#103565] space-y-1.5 px-3 pb-1">
        {/* Breadcrumb Row */}
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
          <span className="font-semibold text-[#1877f2]">
            Initial Assessment of the ICS
          </span>
        </div>

        {/* Outer Frame Card */}
        <div className="w-full overflow-hidden rounded-md border border-[#4485bb] bg-[#dbebf9] shadow-xs">
          {/* Top Header Bar with Close X */}
          <div className="flex items-center justify-between border-b border-[#7eaed5] bg-[#c7e0f6] px-4 py-2 text-[12px] font-semibold text-[#103565]">
            <span className="truncate">
              {engagementCode} - Planning - Preliminary Survey -{" "}
              <span className="font-bold text-[#103565]">
                Initial Assessment of the ICS
              </span>
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

          {/* Form Body Container */}
          <div className="px-5 py-3.5 sm:px-7 sm:py-4 lg:px-8 space-y-3">
            {/* Title & Progress Header Row matching screenshot */}
            <div className="flex items-start justify-between gap-3">
              <div className="flex items-start gap-3.5">
                {/* Custom Internal Control / Governance SVG Icon matching screenshot */}
                <div className="flex h-11 w-11 shrink-0 items-center justify-center text-[#103565]">
                  <svg
                    viewBox="0 0 48 48"
                    fill="none"
                    stroke="#103565"
                    strokeWidth="2.2"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    className="h-10 w-10 text-[#103565]"
                  >
                    {/* Roof / Pediment */}
                    <path d="M8 20 L24 8 L40 20" />
                    <path d="M6 20 h36" />
                    {/* Columns with slider controls */}
                    <path d="M12 20 v18" />
                    <circle cx="12" cy="27" r="2" fill="currentColor" />
                    <path d="M20 20 v18" />
                    <circle cx="20" cy="32" r="2" fill="currentColor" />
                    <path d="M28 20 v9" />
                    {/* Bottom Base */}
                    <path d="M6 38 h22" />
                    {/* Gear outline on bottom right */}
                    <circle cx="36" cy="34" r="5" strokeWidth="2" />
                    <path d="M36 27 v2 M36 39 v2 M29 34 h2 M41 34 h2 M31 29 l1.5 1.5 M39 37 l1.5 1.5 M31 39 l1.5 -1.5 M39 31 l1.5 -1.5" />
                    {/* Checkmark in gear */}
                    <path d="M33.5 34 l1.8 1.8 l3.5 -3.5" strokeWidth="2.2" />
                  </svg>
                </div>
                <div>
                  <h1 className="text-lg sm:text-[19px] font-bold tracking-tight text-[#103565] flex items-center gap-2">
                    <span>1.4 Initial Assessment of the ICS (Internal Control System)</span>
                    {isLoading && <Loader2 size={14} className="animate-spin text-[#1877f2]" />}
                  </h1>
                  <p className="mt-0.5 text-[11px] sm:text-[12px] text-[#35618f]">
                    Assess the auditee's internal control system using the 5
                    ICSPPS components and 16 principles. This is a preliminary
                    understanding, not a formal effectiveness assessment.
                  </p>
                </div>
              </div>

              <div className="shrink-0 text-right text-[12px] font-medium text-slate-500 pt-0.5">
                {completedCount} of {totalPrinciples} items completed
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

            {/* Either show the Add/Edit Panel OR the Accordion list matching screenshot 1 */}
            {isEditorOpen ? (
              /* Add / Edit / View Panel matching screenshot 1 */
              <div className="overflow-hidden rounded-[6px] border border-[#7eaed5] bg-[#dbebf9] shadow-xs">
                {/* Panel Header */}
                <div className="flex items-center justify-between border-b border-[#7eaed5] bg-[#c7e0f6] px-3.5 py-1.5 text-[11px] font-semibold text-[#103565]">
                  <span>Add/Edit/View Initial Assessment of the ICS</span>
                  <button
                    type="button"
                    onClick={() => setIsEditorOpen(false)}
                    aria-label="Close"
                    className="cursor-pointer rounded p-0.5 text-slate-600 hover:bg-[#b0d2ef] hover:text-slate-900 transition"
                  >
                    <X size={15} strokeWidth={2.4} />
                  </button>
                </div>

                {/* Form Inputs stacked vertically matching screenshot 1 */}
                <div className="p-5 sm:p-6 space-y-3.5">
                  <div className="w-full max-w-[380px] sm:max-w-[420px]">
                    <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                      Principle
                    </label>
                    <input
                      type="text"
                      value={editingRow.principle}
                      onChange={(e) =>
                        setEditingRow({ ...editingRow, principle: e.target.value })
                      }
                      className="h-8 w-full rounded-[5px] border border-[#7eaed5] bg-white px-3 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                    />
                  </div>

                  <div className="w-full max-w-[380px] sm:max-w-[420px]">
                    <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                      Auditor Checklist Question
                    </label>
                    <input
                      type="text"
                      value={editingRow.auditorChecklistQuestion}
                      onChange={(e) =>
                        setEditingRow({
                          ...editingRow,
                          auditorChecklistQuestion: e.target.value,
                        })
                      }
                      className="h-8 w-full rounded-[5px] border border-[#7eaed5] bg-white px-3 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                    />
                  </div>

                  <div className="w-full max-w-[380px] sm:max-w-[420px]">
                    <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                      Preliminary View
                    </label>
                    <input
                      type="text"
                      value={editingRow.preliminaryView}
                      onChange={(e) =>
                        setEditingRow({
                          ...editingRow,
                          preliminaryView: e.target.value,
                        })
                      }
                      className="h-8 w-full rounded-[5px] border border-[#7eaed5] bg-white px-3 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                    />
                  </div>

                  <div className="w-full max-w-[380px] sm:max-w-[420px]">
                    <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                      Basis / Source
                    </label>
                    <input
                      type="text"
                      value={editingRow.basisSource}
                      onChange={(e) =>
                        setEditingRow({
                          ...editingRow,
                          basisSource: e.target.value,
                        })
                      }
                      className="h-8 w-full rounded-[5px] border border-[#7eaed5] bg-white px-3 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                    />
                  </div>

                  <div className="w-full max-w-[380px] sm:max-w-[420px]">
                    <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                      Key Matter(s)
                    </label>
                    <input
                      type="text"
                      value={editingRow.keyMatters}
                      onChange={(e) =>
                        setEditingRow({
                          ...editingRow,
                          keyMatters: e.target.value,
                        })
                      }
                      className="h-8 w-full rounded-[5px] border border-[#7eaed5] bg-white px-3 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                    />
                  </div>

                  <div className="w-full max-w-[380px] sm:max-w-[420px]">
                    <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                      Preliminary Assessment
                    </label>
                    <input
                      type="text"
                      value={editingRow.preliminaryAssessment}
                      onChange={(e) =>
                        setEditingRow({
                          ...editingRow,
                          preliminaryAssessment: e.target.value,
                        })
                      }
                      className="h-8 w-full rounded-[5px] border border-[#7eaed5] bg-white px-3 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                    />
                  </div>

                  <div className="w-full max-w-[380px] sm:max-w-[420px]">
                    <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                      Next Action
                    </label>
                    <input
                      type="text"
                      value={editingRow.nextAction}
                      onChange={(e) =>
                        setEditingRow({
                          ...editingRow,
                          nextAction: e.target.value,
                        })
                      }
                      className="h-8 w-full rounded-[5px] border border-[#7eaed5] bg-white px-3 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                    />
                  </div>

                  {/* Action buttons inside panel */}
                  <div className="flex items-center justify-end gap-2.5 pt-3">
                    <button
                      type="button"
                      onClick={() => setIsEditorOpen(false)}
                      className="flex cursor-pointer items-center gap-1.5 rounded-[5px] border border-slate-300 bg-white px-3.5 py-1.5 text-xs font-medium text-slate-700 shadow-2xs hover:bg-slate-50 transition"
                    >
                      <ArrowLeft size={13} />
                      <span>Back to 1.4 workspace</span>
                    </button>
                    <button
                      type="button"
                      onClick={handleSaveRowToTable}
                      className="cursor-pointer rounded-[5px] border border-slate-300 bg-white px-4 py-1.5 text-xs font-semibold text-slate-700 shadow-2xs hover:bg-slate-50 transition"
                    >
                      Update to Table
                    </button>
                  </div>
                </div>
              </div>
            ) : (
              /* Accordion Stack Container */
              <div className="mx-auto w-full max-w-[1000px] space-y-2 pt-1">
                {ICS_SECTIONS.map((section) => {
                  const isOpen = !!openSections[section.id];
                  const sectionRows = rows.filter(
                    (r) => r.sectionId === section.id
                  );

                  return (
                    <div key={section.id} className="space-y-2">
                      {/* Accordion Header Button with Outline Pill */}
                      <button
                        type="button"
                        onClick={() => toggleSection(section.id)}
                        className={`flex w-full cursor-pointer items-center justify-between rounded-[6px] border border-[#6ea0cd] px-3.5 py-2 text-left text-[12px] font-semibold text-[#103565] shadow-2xs transition-all duration-200 ${
                          isOpen
                            ? "bg-white/90 border-[#4485bb]"
                            : "bg-white/70 hover:bg-white hover:border-[#1877f2]"
                        }`}
                      >
                        <div className="flex items-center gap-3">
                          <span className="rounded-[4px] border border-[#7eaed5] bg-white/80 px-2 py-0.5 text-[11px] font-bold text-[#103565]">
                            {section.code}
                          </span>
                          <span className="font-semibold text-[#103565]">
                            {section.title}
                          </span>
                        </div>
                        <div
                          className={`text-[#3b75ab] transition-transform duration-300 ease-in-out ${
                            isOpen
                              ? "rotate-0 text-[#1877f2]"
                              : "-rotate-90 text-slate-500"
                          }`}
                        >
                          <ChevronDown size={17} strokeWidth={2.4} />
                        </div>
                      </button>

                      {/* Smooth Collapsible Content Area */}
                      <div
                        className={`grid transition-[grid-template-rows,opacity] duration-300 ease-in-out ${
                          isOpen
                            ? "grid-rows-[1fr] opacity-100"
                            : "grid-rows-[0fr] opacity-0"
                        }`}
                      >
                        <div className="overflow-hidden">
                          <div className="pt-1 pb-2 px-1">
                            {/* Table matching screenshots 2, 3, 4, 5 */}
                            <div className="overflow-x-auto rounded-[4px] border border-[#7eaed5] bg-white">
                              <table className="w-full min-w-[950px] border-collapse text-left text-[11px]">
                                <thead>
                                  <tr className="border-b border-[#7eaed5] bg-[#dbebf9] text-[#1a3d6d]">
                                    <th className="border-r border-[#7eaed5] px-3 py-1.5 font-semibold text-center w-[4%]">
                                      No.
                                    </th>
                                    <th className="border-r border-[#7eaed5] px-2.5 py-1.5 font-semibold w-[14%]">
                                      Principle
                                    </th>
                                    <th className="border-r border-[#7eaed5] px-2.5 py-1.5 font-semibold w-[22%]">
                                      Auditor Checklist Question
                                    </th>
                                    <th className="border-r border-[#7eaed5] px-2.5 py-1.5 font-semibold w-[12%]">
                                      Preliminary View
                                    </th>
                                    <th className="border-r border-[#7eaed5] px-2.5 py-1.5 font-semibold w-[14%]">
                                      Basis / Source
                                    </th>
                                    <th className="border-r border-[#7eaed5] px-2.5 py-1.5 font-semibold w-[14%]">
                                      Key Matter(s)
                                    </th>
                                    <th className="border-r border-[#7eaed5] px-2.5 py-1.5 font-semibold w-[12%]">
                                      Preliminary Assessment
                                    </th>
                                    <th className="border-r border-[#7eaed5] px-2.5 py-1.5 font-semibold w-[8%]">
                                      Next Action
                                    </th>
                                    <th className="px-1.5 py-1.5 text-center font-semibold w-[4%]">
                                      {/* Options */}
                                    </th>
                                  </tr>
                                </thead>
                                <tbody>
                                  {sectionRows.map((row, idx) => {
                                    const isNearBottom = idx >= Math.max(1, sectionRows.length - 2);
                                    return (
                                    <tr
                                      key={row.id}
                                      className="border-b border-[#c7e0f6] last:border-b-0 hover:bg-[#f3f8fd] transition-colors h-[28px]"
                                    >
                                      <td className="border-r border-[#c7e0f6] px-3 py-1 text-center font-medium text-slate-600">
                                        {row.no}
                                      </td>
                                      <td className="border-r border-[#c7e0f6] px-2.5 py-1 font-medium text-slate-800">
                                        {row.principle}
                                      </td>
                                      <td className="border-r border-[#c7e0f6] px-2.5 py-1 text-slate-700">
                                        {row.auditorChecklistQuestion}
                                      </td>
                                      <td className="border-r border-[#c7e0f6] px-2.5 py-1 text-slate-800">
                                        {row.preliminaryView}
                                      </td>
                                      <td className="border-r border-[#c7e0f6] px-2.5 py-1 text-slate-800">
                                        {row.basisSource}
                                      </td>
                                      <td className="border-r border-[#c7e0f6] px-2.5 py-1 text-slate-800">
                                        {row.keyMatters}
                                      </td>
                                      <td className="border-r border-[#c7e0f6] px-2.5 py-1 text-slate-800">
                                        {row.preliminaryAssessment}
                                      </td>
                                      <td className="border-r border-[#c7e0f6] px-2.5 py-1 text-slate-800">
                                        {row.nextAction}
                                      </td>
                                      <td className="relative px-1 py-1 text-center">
                                        <button
                                          type="button"
                                          onClick={() =>
                                            setActiveMenuId(
                                              activeMenuId === row.id ? null : row.id
                                            )
                                          }
                                          aria-label="Row actions"
                                          className="cursor-pointer text-slate-500 hover:text-slate-800 p-0.5 rounded transition"
                                        >
                                          <MoreHorizontal size={14} />
                                        </button>

                                        {activeMenuId === row.id && (
                                          <div
                                            className={`ics-row-action-menu absolute right-2 z-50 w-28 rounded-md border border-slate-200 bg-white py-1 shadow-md text-[11px] text-left ${
                                              isNearBottom ? "bottom-full mb-1" : "top-full mt-1"
                                            }`}
                                          >
                                            <button
                                              type="button"
                                              onClick={() => handleOpenEditRow(row)}
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
                                          </div>
                                        )}
                                      </td>
                                    </tr>
                                  );
                                })}
                                  {sectionRows.length < 3 &&
                                    Array.from({ length: 3 - sectionRows.length }).map((_, idx) => (
                                      <tr
                                        key={`blank-${idx}`}
                                        className="border-b border-[#c7e0f6] last:border-b-0 h-[28px]"
                                      >
                                        <td className="border-r border-[#c7e0f6] px-3 py-1 text-center font-medium text-slate-600">
                                          &nbsp;
                                        </td>
                                        <td className="border-r border-[#c7e0f6] px-2.5 py-1">&nbsp;</td>
                                        <td className="border-r border-[#c7e0f6] px-2.5 py-1">&nbsp;</td>
                                        <td className="border-r border-[#c7e0f6] px-2.5 py-1">&nbsp;</td>
                                        <td className="border-r border-[#c7e0f6] px-2.5 py-1">&nbsp;</td>
                                        <td className="border-r border-[#c7e0f6] px-2.5 py-1">&nbsp;</td>
                                        <td className="border-r border-[#c7e0f6] px-2.5 py-1">&nbsp;</td>
                                        <td className="border-r border-[#c7e0f6] px-2.5 py-1">&nbsp;</td>
                                        <td className="px-1.5 py-1">&nbsp;</td>
                                      </tr>
                                    ))}
                                </tbody>
                              </table>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  );
                })}
              </div>
            )}

            {/* Bottom Enclosed Action Panel */}
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
                          description: "Generating ICS Assessment report...",
                          tone: "info",
                        });
                        setMoreActionsOpen(false);
                      }}
                      className="w-full text-left px-3 py-1.5 hover:bg-[#d8ecff] hover:text-[#0b4d91] transition"
                    >
                      Export Summary (PDF)
                    </button>
                    <div className="my-1 border-t border-slate-100" />
                    <button
                      type="button"
                      onClick={() => {
                        handleSaveDraft();
                        showToast({
                          title: "Marked Complete",
                          description:
                            "1.4 Initial Assessment of the ICS marked as complete.",
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
    </AdminLayout>
  );
}
