import { useState, useRef, useEffect } from "react";
import {
  ArrowLeft,
  Calendar,
  ChevronDown,
  Loader2,
  X,
} from "lucide-react";
import { AdminLayout } from "../../../../layouts/admin/AdminLayout";
import { navigateTo } from "../../../../routes";
import { useToast } from "../../../../components/ui/toast";
import { localStore } from "../../../../lib/localStore";
import { preliminarySurveyService } from "../../../../services/preliminarySurveyService";
import {
  defaultPreliminaryMeetingState,
  type PreliminaryMeetingFormState,
} from "./preliminary-survey-types";

export function PreliminaryMeetingPage() {
  const { showToast } = useToast();
  const fileInputRef = useRef<HTMLInputElement>(null);
  const moreActionsRef = useRef<HTMLDivElement>(null);
  const [moreActionsOpen, setMoreActionsOpen] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [isSaving, setIsSaving] = useState(false);
  const [isUploading, setIsUploading] = useState(false);
  const [lockVersion, setLockVersion] = useState<number>(1);
  const [participantOptions, setParticipantOptions] = useState<Array<{ id: number; name: string }>>([]);

  // Extract engagement ID from URL
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

  const [formState, setFormState] = useState<PreliminaryMeetingFormState>(
    defaultPreliminaryMeetingState
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
        if (ws?.participantOptions) setParticipantOptions(ws.participantOptions);
        if (ws?.sectionReviews?.meeting) setReviewStatus(ws.sectionReviews.meeting);

        const meeting = ws?.survey?.meeting;
        if (meeting) {
          const noticeFile = ws?.attachments?.find(
            (a) => a.id === meeting.noticeDocumentVersionId
          );
          setFormState({
            proposedDate: meeting.proposedAt || "",
            venueOnlineLink: meeting.venue || "",
            auditeeContactPerson: meeting.focalPerson || "",
            ciasParticipants: meeting.ciasParticipants || "",
            auditeeParticipants: meeting.auditeeParticipants || "",
            keyMattersToDiscuss: meeting.mattersToDiscuss || "",
            initialDocumentsNeeded: meeting.documentsNeeded || "",
            actualMeetingDate: meeting.actualDate || "",
            keyMattersDiscussed: meeting.mattersDiscussed || "",
            noticeFileName: noticeFile?.fileName || "",
            noticeDocumentVersionId: meeting.noticeDocumentVersionId || null,
          });
        }
      })
      .catch((err) => {
        console.warn("Could not load preliminary survey meeting from backend:", err);
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
    }
    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, []);

  const handleChange = (
    field: keyof PreliminaryMeetingFormState,
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

  const formatDateForBackend = (val?: string): string | null => {
    if (!val) return null;
    const trimmed = val.trim();
    if (/^\d{4}-\d{2}-\d{2}$/.test(trimmed)) return trimmed;
    const parsed = Date.parse(trimmed);
    if (!isNaN(parsed)) {
      return new Date(parsed).toISOString().split("T")[0];
    }
    return null;
  };

  const handleSaveDraft = async () => {
    setIsSaving(true);
    try {
      const payload = {
        proposedAt: formatDateForBackend(formState.proposedDate),
        venue: formState.venueOnlineLink || null,
        focalPerson: formState.auditeeContactPerson || null,
        ciasParticipants: formState.ciasParticipants || null,
        auditeeParticipants: formState.auditeeParticipants || null,
        mattersToDiscuss: formState.keyMattersToDiscuss || null,
        documentsNeeded: formState.initialDocumentsNeeded || null,
        noticeDocumentVersionId: formState.noticeDocumentVersionId || null,
        actualDate: formatDateForBackend(formState.actualMeetingDate),
        mattersDiscussed: formState.keyMattersDiscussed || null,
      };

      const updated = await preliminarySurveyService.saveSection(
        engagementId,
        "meeting",
        payload,
        lockVersion
      );

      if (updated?.lockVersion) {
        setLockVersion(updated.lockVersion);
      }

      showToast({
        title: "Draft Saved",
        description: "Preliminary Meeting details saved successfully to the backend.",
        tone: "success",
      });
    } catch (err: any) {
      showToast({
        title: "Save Failed",
        description: err?.message || "Could not save preliminary meeting details.",
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
        noticeFileName: uploaded.fileName || file.name,
        noticeDocumentVersionId: uploaded.id,
      }));
      showToast({
        title: "File Attached",
        description: `Uploaded "${file.name}" successfully.`,
        tone: "success",
      });
    } catch (err: any) {
      showToast({
        title: "Upload Failed",
        description: err?.message || "Could not upload meeting notice document.",
        tone: "warning",
      });
    } finally {
      setIsUploading(false);
    }
  };

  const handleDownloadTemplate = async () => {
    try {
      showToast({
        title: "Downloading Template",
        description: "Notice of PrelimMeeting_Template.docx download initiated...",
        tone: "info",
      });
      await preliminarySurveyService.downloadTemplate(engagementId);
    } catch (err: any) {
      showToast({
        title: "Download Failed",
        description: err?.message || "Could not download meeting template.",
        tone: "warning",
      });
    }
  };

  const handleReviewAction = async (action: "SUBMIT" | "APPROVE" | "RETURN") => {
    try {
      await handleSaveDraft();
      await preliminarySurveyService.reviewSection(
        engagementId,
        "meeting",
        action,
        lockVersion
      );
      showToast({
        title: `Section ${action === "SUBMIT" ? "Submitted" : action === "APPROVE" ? "Approved" : "Returned"}`,
        description: `1.1 Preliminary Meeting has been updated.`,
        tone: "success",
      });
      setMoreActionsOpen(false);
    } catch (err: any) {
      showToast({
        title: "Action Failed",
        description: err?.message || `Could not complete review action.`,
        tone: "warning",
      });
    }
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
          <span className="font-semibold text-[#1877f2]">Preliminary Meeting</span>
        </div>

        {/* Outer Frame Card - Saturated Blue Background matching screenshot */}
        <div className="w-full overflow-hidden rounded-md border border-[#4485bb] bg-[#dbebf9] shadow-xs">
          {/* Top Header Bar */}
          <div className="flex items-center justify-between border-b border-[#7eaed5] bg-[#c7e0f6] px-4 py-2 text-[12px] font-semibold text-[#103565]">
            <span className="truncate">
              {engagementCode} - Planning - Preliminary Survey -{" "}
              <span className="font-bold text-[#103565]">Preliminary Meeting</span>
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
            {/* Title & Progress Header Row (spans wide) */}
            <div className="flex items-center justify-between gap-3">
              <div className="flex items-center gap-3">
                {/* Meeting Chat Bubbles + Attendees SVG matching screenshot */}
                <div className="flex h-10 w-10 shrink-0 items-center justify-center text-[#103565]">
                  <svg
                    viewBox="0 0 48 48"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2.3"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    className="h-9 w-9"
                  >
                    {/* Left chat bubble */}
                    <rect x="5" y="6" width="18" height="13" rx="2.5" />
                    <line x1="9" y1="10" x2="19" y2="10" />
                    <line x1="9" y1="14" x2="15" y2="14" />
                    <polyline points="8,19 8,23 13,19" />
                    {/* Right chat bubble */}
                    <rect x="21" y="9" width="16" height="12" rx="2.5" />
                    <line x1="25" y1="13" x2="33" y2="13" />
                    <line x1="25" y1="17" x2="30" y2="17" />
                    <polyline points="33,21 36,24 36,21" />
                    {/* 3 attendees */}
                    <circle cx="11" cy="30" r="3.2" />
                    <path d="M5 40 c0 -3.2 2.5 -5.5 6 -5.5 s6 2.3 6 5.5" />
                    <circle cx="23" cy="28.5" r="3.7" />
                    <path d="M16.5 40 c0 -3.8 2.8 -6.5 6.5 -6.5 s6.5 2.7 6.5 6.5" />
                    <circle cx="35" cy="30" r="3.2" />
                    <path d="M29 40 c0 -3.2 2.5 -5.5 6 -5.5 s6 2.3 6 5.5" />
                  </svg>
                </div>
                <h1 className="text-lg sm:text-[20px] font-bold tracking-tight text-[#103565] flex items-center gap-2">
                  <span>1.1 Preliminary Meeting</span>
                  {isLoading && <Loader2 size={14} className="animate-spin text-[#1877f2]" />}
                </h1>
              </div>

              <div className="text-[12px] font-medium text-slate-500">
                2 of 11 items completed
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

            {/* Centered Form Fields Container with reduced side space */}
            <div className="mx-auto w-full max-w-[1000px] pt-0.5">
              <div className="grid grid-cols-1 gap-x-8 gap-y-2.5 lg:grid-cols-2">
                {/* Row 1: Proposed Date & Time + Venue / Online Link immediately side-by-side */}
                <div className="col-span-full flex flex-wrap items-start gap-4">
                  <div className="w-[155px] shrink-0">
                    <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                      Proposed Date &amp; Time
                    </label>
                    <div className="relative flex items-center">
                      <input
                        type="text"
                        value={formState.proposedDate}
                        onChange={(e) => handleChange("proposedDate", e.target.value)}
                        placeholder="08 Sep 2026"
                        className="h-8 w-full rounded-[5px] border border-[#7eaed5] bg-white px-2.5 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                      />
                      <div className="pointer-events-none absolute right-2 text-slate-500">
                        <Calendar size={15} strokeWidth={2} />
                      </div>
                    </div>
                  </div>

                  <div className="w-[380px] sm:w-[450px]">
                    <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                      Venue / Online Link
                    </label>
                    <input
                      type="text"
                      value={formState.venueOnlineLink}
                      onChange={(e) => handleChange("venueOnlineLink", e.target.value)}
                      className="h-8 w-full rounded-[5px] border border-[#7eaed5] bg-white px-3 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                    />
                  </div>
                </div>

                {/* Row 2: Auditee Contact / Focal Person & CIAS Participants */}
                <div>
                  <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                    Auditee Contact / Focal Person
                  </label>
                  <input
                    type="text"
                    value={formState.auditeeContactPerson}
                    onChange={(e) => handleChange("auditeeContactPerson", e.target.value)}
                    className="h-8 w-full max-w-[460px] rounded-[5px] border border-[#7eaed5] bg-white px-3 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                  />
                </div>

                <div>
                  <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                    CIAS Participants
                  </label>
                  <input
                    type="text"
                    list="cias-participants-options"
                    value={formState.ciasParticipants}
                    onChange={(e) => handleChange("ciasParticipants", e.target.value)}
                    className="h-8 w-full max-w-[460px] rounded-[5px] border border-[#7eaed5] bg-white px-3 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                  />
                  <datalist id="cias-participants-options">
                    {participantOptions.map((p) => (
                      <option key={p.id} value={p.name} />
                    ))}
                  </datalist>
                </div>

                {/* Row 3: Auditee Participants & Key Matters to Discuss */}
                <div>
                  <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                    Auditee Participants, if known
                  </label>
                  <input
                    type="text"
                    value={formState.auditeeParticipants}
                    onChange={(e) => handleChange("auditeeParticipants", e.target.value)}
                    className="h-8 w-full max-w-[460px] rounded-[5px] border border-[#7eaed5] bg-white px-3 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                  />
                </div>

                <div>
                  <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                    Key Matters to Discuss
                  </label>
                  <textarea
                    rows={2}
                    value={formState.keyMattersToDiscuss}
                    onChange={(e) => handleChange("keyMattersToDiscuss", e.target.value)}
                    className="h-[52px] w-full max-w-[460px] rounded-[5px] border border-[#7eaed5] bg-white px-3 py-1.5 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2] resize-none"
                  />
                </div>

                {/* Row 4: Initial Information / Documents Needed & Notice of Preliminary Meeting */}
                <div>
                  <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                    Initial Information / Documents Needed
                  </label>
                  <textarea
                    rows={2}
                    value={formState.initialDocumentsNeeded}
                    onChange={(e) => handleChange("initialDocumentsNeeded", e.target.value)}
                    className="h-[52px] w-full max-w-[460px] rounded-[5px] border border-[#7eaed5] bg-white px-3 py-1.5 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2] resize-none"
                  />
                </div>

                <div>
                  <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                    Notice of Preliminary Meeting
                  </label>
                  <input
                    type="file"
                    ref={fileInputRef}
                    onChange={handleFileUpload}
                    className="hidden"
                    accept=".pdf,.doc,.docx"
                  />
                  {/* Dashed dropzone */}
                  <div
                    onClick={() => fileInputRef.current?.click()}
                    className="w-full max-w-[460px] cursor-pointer rounded-[5px] border border-dashed border-[#6ea0cd] bg-[#edf5fc] px-3 py-1.5 text-center transition-colors hover:bg-[#e4eff9]"
                  >
                    <p className="text-[11px] text-[#4d7ea8]">
                      {formState.noticeFileName ? (
                        <span className="font-semibold text-[#103565]">
                          Selected: {formState.noticeFileName}
                        </span>
                      ) : (
                        "Click to upload or drag and drop PDF, DOC, DOCX (Max 10 MB)"
                      )}
                    </p>
                  </div>

                  <div className="mt-1 flex w-full max-w-[460px] flex-wrap items-center justify-between gap-1 text-[11px]">
                    <div className="text-slate-600">
                      Download Template:{" "}
                      <button
                        type="button"
                        onClick={handleDownloadTemplate}
                        className="cursor-pointer font-medium text-[#1877f2] hover:underline"
                      >
                        [ Notice of PrelimMeeting_Template.docx ]
                      </button>
                    </div>
                    <button
                      type="button"
                      onClick={() => fileInputRef.current?.click()}
                      disabled={isUploading}
                      className="h-6 cursor-pointer rounded border border-slate-300 bg-white px-2.5 font-semibold text-slate-700 shadow-2xs hover:bg-slate-50 transition disabled:opacity-50"
                    >
                      {isUploading ? "Uploading..." : "Upload File"}
                    </button>
                  </div>
                </div>

                {/* Row 5: Actual Meeting Date (Compact ~155px) */}
                <div>
                  <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                    Actual Meeting Date
                  </label>
                  <div className="relative flex w-[155px] items-center">
                    <input
                      type="text"
                      value={formState.actualMeetingDate}
                      onChange={(e) => handleChange("actualMeetingDate", e.target.value)}
                      placeholder="08 Sep 2026"
                      className="h-8 w-full rounded-[5px] border border-[#7eaed5] bg-white px-2.5 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                    />
                    <div className="pointer-events-none absolute right-2 text-slate-500">
                      <Calendar size={15} strokeWidth={2} />
                    </div>
                  </div>
                </div>

                {/* Empty spacer for alignment */}
                <div className="hidden lg:block" />

                {/* Row 6: Key Matters Discussed */}
                <div className="lg:col-span-1">
                  <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                    Key Matters Discussed
                  </label>
                  <textarea
                    rows={2}
                    value={formState.keyMattersDiscussed}
                    onChange={(e) => handleChange("keyMattersDiscussed", e.target.value)}
                    className="h-[52px] w-full max-w-[460px] rounded-[5px] border border-[#7eaed5] bg-white px-3 py-1.5 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2] resize-none"
                  />
                </div>
              </div>
            </div>

            {/* Bottom Enclosed Action Panel matching screenshot */}
            <div className="mx-2 mt-4 flex flex-wrap items-center justify-end gap-2.5 rounded-[6px] border border-[#7eaed5] bg-[#dbebf9] px-4 py-2.5">
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
                className="cursor-pointer rounded-[5px] border border-slate-300 bg-white px-4 py-1.5 text-xs font-medium text-slate-700 shadow-2xs hover:border-[#2478bf] hover:bg-[#d8ecff] hover:text-[#0b4d91] transition flex items-center gap-1.5 disabled:opacity-50"
              >
                {isSaving && <Loader2 size={13} className="animate-spin" />}
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
                          description: "Generating meeting minutes report...",
                          tone: "info",
                        });
                        setMoreActionsOpen(false);
                      }}
                      className="w-full text-left px-3 py-1.5 hover:bg-[#d8ecff] hover:text-[#0b4d91] transition"
                    >
                      Export Minutes (PDF)
                    </button>
                    <div className="my-1 border-t border-slate-100" />
                    <button
                      type="button"
                      onClick={() => handleReviewAction("SUBMIT")}
                      className="w-full text-left px-3 py-1.5 text-blue-700 hover:bg-blue-50 transition"
                    >
                      Submit for Review
                    </button>
                    <button
                      type="button"
                      onClick={() => handleReviewAction("APPROVE")}
                      className="w-full text-left px-3 py-1.5 text-emerald-700 hover:bg-emerald-50 transition"
                    >
                      Approve Section
                    </button>
                    <button
                      type="button"
                      onClick={() => handleReviewAction("RETURN")}
                      className="w-full text-left px-3 py-1.5 text-amber-700 hover:bg-amber-50 transition"
                    >
                      Return for Revision
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
