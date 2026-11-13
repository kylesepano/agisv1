import { useMemo, useState } from "react";
import {
  ArrowLeft,
  ArrowRight,
  CheckCircle2,
  RotateCcw,
  Send,
} from "lucide-react";
import { AdminLayout } from "../../../../layouts/admin/AdminLayout";
import { Button } from "../../../../components/ui/button";
import { useToast } from "../../../../components/ui/toast";
import { navigateTo } from "../../../../routes";
import {
  AuthorizedWaiverSection,
  BriefingPaperSummaryCard,
  ConferenceControlsRail,
  ConferenceIdentityCard,
  ConferenceMinutesAgreementsSection,
  ConferenceParticipantsSection,
  ConferenceScheduleAgendaSection,
} from "./components";
import {
  buildInitialConferenceState,
  defaultConferenceState,
} from "./conference-data";
import { conferenceSchema } from "./conference-schema";
import type {
  ConferenceFormState,
  ConferenceParticipant,
  FollowUpActionItem,
  InfoRequestItem,
} from "./conference-types";
import { evaluateEngagementGating } from "../../../../lib/engagementPhaseGating";
import { EngagementPhaseGate } from "../components/EngagementPhaseGate";
import { localStore } from "../../../../lib/localStore";

export function AdminConferencePage() {
  const { showToast } = useToast();

  const currentPath = window.location.pathname;
  const pathParts = currentPath.split("/");
  const engagementId = pathParts[3] || "AEMS-2026-010";

  // Resolve matching engagement from localStore
  const existingEngagement = useMemo(() => {
    return localStore.getEngagement(engagementId);
  }, [engagementId]);

  const engagementNumber = existingEngagement?.code || engagementId;

  const [formState, setFormState] = useState<ConferenceFormState>(() => {
    const savedConf = localStore.getConferenceState(engagementNumber, "entry");
    if (savedConf) {
      return savedConf;
    }

    if (existingEngagement) {
      const isSeeded = engagementNumber === "AEMS-2026-010";
      if (isSeeded) {
        return defaultConferenceState;
      }
      return buildInitialConferenceState(existingEngagement, "entry");
    }
    return defaultConferenceState;
  });

  // Enforce Phase Gating (Phase 1 AEO required for Entry Conference)
  const gate = evaluateEngagementGating(engagementNumber);

  // Validation
  const validationResult = useMemo(() => {
    const parsed = conferenceSchema.safeParse(formState);
    return {
      isValid: parsed.success,
      errors: parsed.success
        ? {}
        : parsed.error.issues.reduce<Record<string, string>>((acc, issue) => {
            acc[issue.path.join(".")] = issue.message;
            return acc;
          }, {}),
    };
  }, [formState]);

  // Handler for toggle waiver mode
  const handleToggleWaiverMode = (isWaiverActive: boolean) => {
    setFormState((prev) => ({
      ...prev,
      waiver: {
        ...prev.waiver,
        isWaiverActive,
      },
      status: isWaiverActive ? "Waived" : "Conducted",
    }));
  };

  // Participant Handlers
  const handleUpdateParticipant = (
    id: string,
    updates: Partial<ConferenceParticipant>,
  ) => {
    setFormState((prev) => ({
      ...prev,
      participants: prev.participants.map((p) =>
        p.id === id ? { ...p, ...updates } : p,
      ),
    }));
  };

  const handleAddParticipant = () => {
    const newId = `PART-0${formState.participants.length + 1}`;
    const newP: ConferenceParticipant = {
      id: newId,
      name: "New Auditee Representative",
      office: formState.office,
      position: "Division Representative",
      role: "Auditee Participant",
      attendanceStatus: "Confirmed",
      mode: "In-person",
    };
    setFormState((prev) => ({
      ...prev,
      participants: [...prev.participants, newP],
    }));
  };

  const handleRemoveParticipant = (id: string) => {
    if (formState.participants.length <= 2) return;
    setFormState((prev) => ({
      ...prev,
      participants: prev.participants.filter((p) => p.id !== id),
    }));
  };

  // Info Request Handlers
  const handleUpdateInfoRequest = (
    id: string,
    updates: Partial<InfoRequestItem>,
  ) => {
    setFormState((prev) => ({
      ...prev,
      informationRequests: prev.informationRequests.map((req) =>
        req.id === id ? { ...req, ...updates } : req,
      ),
    }));
  };

  const handleAddInfoRequest = () => {
    const newId = `REQ-0${formState.informationRequests.length + 1}`;
    const newReq: InfoRequestItem = {
      id: newId,
      documentTitle: "Additional Procurement Logbook or Voucher Batch",
      targetOffice: formState.office,
      focalPerson: "Designated Focal Person",
      deadline: "2026-02-27",
      status: "Pending",
    };
    setFormState((prev) => ({
      ...prev,
      informationRequests: [...prev.informationRequests, newReq],
    }));
  };

  const handleRemoveInfoRequest = (id: string) => {
    if (formState.informationRequests.length <= 1) return;
    setFormState((prev) => ({
      ...prev,
      informationRequests: prev.informationRequests.filter(
        (req) => req.id !== id,
      ),
    }));
  };

  // Follow-up Action Handlers
  const handleUpdateFollowUp = (
    id: string,
    updates: Partial<FollowUpActionItem>,
  ) => {
    setFormState((prev) => ({
      ...prev,
      followUpActions: prev.followUpActions.map((act) =>
        act.id === id ? { ...act, ...updates } : act,
      ),
    }));
  };

  const handleAddFollowUp = () => {
    const newId = `ACT-0${formState.followUpActions.length + 1}`;
    const newAct: FollowUpActionItem = {
      id: newId,
      actionItem: "Coordinate on-site working paper transmittal schedule",
      responsiblePerson: "Juan Dela Cruz (Lead Auditor)",
      targetDate: "2026-02-25",
    };
    setFormState((prev) => ({
      ...prev,
      followUpActions: [...prev.followUpActions, newAct],
    }));
  };

  const handleRemoveFollowUp = (id: string) => {
    if (formState.followUpActions.length <= 1) return;
    setFormState((prev) => ({
      ...prev,
      followUpActions: prev.followUpActions.filter((act) => act.id !== id),
    }));
  };

  const handleSaveDraft = () => {
    localStore.saveConferenceState(formState.engagementNumber, formState, "entry");
    showToast({
      title: "Conference Draft Saved",
      description: `Draft documentation for ${formState.conferenceId} saved successfully.`,
      tone: "info",
    });
  };

  const handleSubmitFinalize = () => {
    if (!validationResult.isValid) {
      showToast({
        title: "Validation Error",
        description: "Please complete all mandatory conference details and required fields.",
        tone: "warning",
      });
      return;
    }

    const targetStatus = formState.waiver.isWaiverActive ? "Waived" : "Documented";
    const updatedState: ConferenceFormState = {
      ...formState,
      status: targetStatus,
    };
    setFormState(updatedState);
    localStore.saveConferenceState(formState.engagementNumber, updatedState, "entry");

    // Advance engagement progress in master localStore
    const freshEngagement = localStore.getEngagement(formState.engagementNumber) || existingEngagement;
    if (freshEngagement) {
      localStore.saveEngagement({
        ...freshEngagement,
        progress: Math.max(freshEngagement.progress || 0, 40),
        lastUpdated: new Date().toISOString().replace("T", " ").substring(0, 16),
      });
    }

    // Verify real resulting gating state from localStore
    const verifiedGate = evaluateEngagementGating(formState.engagementNumber);

    if (verifiedGate.isPhase3Complete) {
      showToast({
        title: formState.waiver.isWaiverActive
          ? "Authorized Waiver Affirmed"
          : "Entry Conference Documented",
        description: "Phase 3 milestone verified. Ready to proceed to Audit Execution (Phase 4).",
        tone: "success",
      });
    } else {
      showToast({
        title: "Conference Updated",
        description: `Entry conference status updated to ${targetStatus}.`,
        tone: "info",
      });
    }

    setTimeout(() => {
      navigateTo(`/admin/audit-engagement/${formState.engagementNumber}/execution`);
    }, 400);
  };

  const handleReset = () => {
    setFormState(defaultConferenceState);
    showToast({
      title: "Conference Reset",
      description: "Reverted to default conference baseline.",
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
                    `/admin/audit-engagement/${formState.engagementNumber}`,
                  )
                }
                className="hover:text-blue-600 transition"
              >
                {formState.engagementNumber}
              </button>
              <span>/</span>
              <button
                type="button"
                onClick={() =>
                  navigateTo(
                    `/admin/audit-engagement/${formState.engagementNumber}/planning`,
                  )
                }
                className="hover:text-blue-600 transition"
              >
                Planning Workspace (SCR-221)
              </button>
              <span>/</span>
              <span className="text-slate-700 font-semibold">
                Entry Conference (SCR-225)
              </span>
            </div>
            <span className="text-[10px] font-mono text-slate-400 hidden sm:inline">
              MDS-200 v0.8 §7, §11 (Rule 11) · UID-200 v0.5 §8.3 · Phase 3 Conference · Controlled Event
            </span>
          </div>

          {/* Main Title and Action Group */}
          <div className="flex flex-col gap-2 pt-0.5 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex items-center gap-2.5">
              <button
                type="button"
                onClick={() =>
                  navigateTo(
                    `/admin/audit-engagement/${formState.engagementNumber}/planning`,
                  )
                }
                className="grid h-8 w-8 shrink-0 place-items-center rounded-md border border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition"
                title="Back to Planning Workspace"
              >
                <ArrowLeft className="h-4 w-4" />
              </button>
              <div>
                <div className="flex items-center gap-1.5">
                  <span className="font-mono text-[10px] font-bold text-blue-700 bg-blue-50 px-1.5 py-0.2 rounded border border-blue-200">
                    SCR-225
                  </span>
                  <h1 className="text-[1.1rem] font-bold tracking-tight text-slate-900 leading-snug">
                    Conference Management & Fieldwork Commencement
                  </h1>
                </div>
                <p className="text-[11px] text-slate-500">
                  Schedule and document Entry or Exit Conferences, briefing papers, agendas, participant rosters, minutes, and authorized commencement waivers for <strong>{formState.engagementNumber}</strong>.
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
                    `/admin/audit-engagement/${formState.engagementNumber}/planning`,
                  )
                }
              >
                Cancel
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
                disabled={!validationResult.isValid}
                onClick={handleSubmitFinalize}
              >
                <Send className="h-4 w-4" />
                <span>{formState.waiver.isWaiverActive ? "Affirm Authorized Waiver" : "Finalize Conference"}</span>
                <ArrowRight className="h-4 w-4" />
              </Button>
            </div>
          </div>
        </div>

        {/* Main Content or Gated View */}
        {!gate.isPhase1Complete ? (
          <EngagementPhaseGate
            engagementNumber={formState.engagementNumber}
            requiredPhaseName="Phase 3: Entry Conference"
            requiredPhaseNumber={3}
            screenTitle="Entry Conference Protocol (SCR-225)"
          />
        ) : (
          <>
            {/* 2-Column Main Form Grid */}
            <div className="grid grid-cols-1 gap-3 lg:grid-cols-12">
              {/* Left Column (8 cols): Controlled Conference Sections */}
              <div className="space-y-3 lg:col-span-8">
                {/* 1. Top Identity Banner */}
                <ConferenceIdentityCard
                  conferenceId={formState.conferenceId}
                  engagementNumber={formState.engagementNumber}
                  engagementTitle={formState.engagementTitle}
                  office={formState.office}
                  type={formState.type}
                  status={formState.status}
                  isWaiver={formState.waiver.isWaiverActive}
                />

                {/* 2. Synthesized Briefing Paper Presentation Card */}
                <BriefingPaperSummaryCard briefingPaper={formState.briefingPaper} />

                {/* Dual Path Rendering: Waiver vs Standard Conference */}
                {formState.waiver.isWaiverActive ? (
                  <AuthorizedWaiverSection
                    waiver={formState.waiver}
                    onChangeWaiver={(updates) =>
                      setFormState((prev) => ({
                        ...prev,
                        waiver: { ...prev.waiver, ...updates },
                      }))
                    }
                  />
                ) : (
                  <>
                    {/* 3. Schedule, Logistics & Agenda */}
                    <ConferenceScheduleAgendaSection
                      type={formState.type}
                      status={formState.status}
                      scheduledDate={formState.scheduledDate}
                      scheduledTime={formState.scheduledTime}
                      venueOrLink={formState.venueOrLink}
                      mode={formState.mode}
                      agendaText={formState.agendaText}
                      onChangeType={(val) =>
                        setFormState((prev) => ({ ...prev, type: val }))
                      }
                      onChangeStatus={(val) =>
                        setFormState((prev) => ({ ...prev, status: val }))
                      }
                      onChangeDate={(val) =>
                        setFormState((prev) => ({ ...prev, scheduledDate: val }))
                      }
                      onChangeTime={(val) =>
                        setFormState((prev) => ({ ...prev, scheduledTime: val }))
                      }
                      onChangeVenue={(val) =>
                        setFormState((prev) => ({ ...prev, venueOrLink: val }))
                      }
                      onChangeMode={(val) =>
                        setFormState((prev) => ({ ...prev, mode: val }))
                      }
                      onChangeAgenda={(val) =>
                        setFormState((prev) => ({ ...prev, agendaText: val }))
                      }
                    />

                    {/* 4. Participants Roster */}
                    <ConferenceParticipantsSection
                      participants={formState.participants}
                      onUpdateParticipant={handleUpdateParticipant}
                      onAddParticipant={handleAddParticipant}
                      onRemoveParticipant={handleRemoveParticipant}
                    />

                    {/* 5. Minutes, Agreements, Requests & Actions */}
                    <ConferenceMinutesAgreementsSection
                      minutesAndNotes={formState.minutesAndNotes}
                      keyAgreements={formState.keyAgreements}
                      informationRequests={formState.informationRequests}
                      followUpActions={formState.followUpActions}
                      onChangeMinutes={(val) =>
                        setFormState((prev) => ({ ...prev, minutesAndNotes: val }))
                      }
                      onChangeAgreements={(val) =>
                        setFormState((prev) => ({ ...prev, keyAgreements: val }))
                      }
                      onUpdateInfoRequest={handleUpdateInfoRequest}
                      onAddInfoRequest={handleAddInfoRequest}
                      onRemoveInfoRequest={handleRemoveInfoRequest}
                      onUpdateFollowUp={handleUpdateFollowUp}
                      onAddFollowUp={handleAddFollowUp}
                      onRemoveFollowUp={handleRemoveFollowUp}
                    />
                  </>
                )}
              </div>

              {/* Right Column (4 cols): System Controls & Readiness Checklist */}
              <div className="space-y-3 lg:col-span-4">
                <div className="sticky top-4">
                  <ConferenceControlsRail
                    formState={formState}
                    isReady={validationResult.isValid}
                    onToggleWaiverMode={handleToggleWaiverMode}
                    onSaveDraft={handleSaveDraft}
                    onSubmitFinalize={handleSubmitFinalize}
                  />
                </div>
              </div>
            </div>

            {/* Bottom Action Footer Card */}
            <div className="rounded-lg border border-slate-200 bg-white p-3 shadow-sm flex flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-between">
              <div className="flex items-center gap-1.5 text-xs text-slate-500">
                <CheckCircle2 size={14} className="text-blue-600 shrink-0" />
                <span>
                  Conference Protocol: <strong>{formState.type} ({formState.status})</strong> • Phase 3 Milestone • Governed under MDS-200 §7, §11 (Rule 11)
                </span>
              </div>

              <div className="flex items-center gap-2 shrink-0">
                <Button
                  type="button"
                  variant="outline"
                  className="h-10 rounded-lg border-slate-200 px-3.5 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                  onClick={() =>
                    navigateTo(
                      `/admin/audit-engagement/${formState.engagementNumber}/planning`,
                    )
                  }
                >
                  Return to Planning (SCR-221)
                </Button>

                <Button
                  type="button"
                  onClick={handleSubmitFinalize}
                  disabled={!validationResult.isValid}
                  className="h-10 rounded-lg px-4 text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white shadow-sm disabled:opacity-50 flex items-center gap-2 transition active:scale-[0.99] cursor-pointer"
                >
                  <span>{formState.waiver.isWaiverActive ? "Affirm Authorized Waiver" : "Finalize Entry Conference"}</span>
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

export default AdminConferencePage;
