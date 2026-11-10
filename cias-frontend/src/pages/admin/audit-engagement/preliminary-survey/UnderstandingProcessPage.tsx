import { useState, useRef, useEffect } from "react";
import {
  ArrowLeft,
  ChevronDown,
  X,
  Plus,
  MoreHorizontal,
  Edit2,
  Trash2,
  Loader2,
} from "lucide-react";
import { AdminLayout } from "../../../../layouts/admin/AdminLayout";
import { navigateTo } from "../../../../routes";
import { useToast } from "../../../../components/ui/toast";
import { localStore } from "../../../../lib/localStore";
import { preliminarySurveyService } from "../../../../services/preliminarySurveyService";

export type OperatingStepItem = {
  id: string;
  no: number;
  activityDecision: string;
  responsible: string;
  inputOutput: string;
  reportedControlRecord: string;
  source: string;
};

export type SourceConsultedItem = {
  id: string;
  no: number;
  documentPersonMethod: string;
  dateCustodian: string;
  whatItSupportsLimitation: string;
};

export type UnresolvedQuestionItem = {
  id: string;
  no: number;
  informationGapContradiction: string;
  verificationDocumentNeeded: string;
  ownerDue: string;
  drlTaskLink: string;
};

export type RiskControlMatrixItem = {
  id: string;
  no: number;
  riskDescription: string;
  riskCategory: string;
  likelihood: string;
  justificationLikelihood: string;
  impact: string;
  justificationImpact: string;
  riskRating: string;
  controlActivityInPlace: string;
  controlType: string;
  controlOwner: string;
  controlFrequency: string;
  operatingEffectivenessStatus: string;
  residualRiskRating: string;
};

export type UnderstandingProcessFormState = {
  // 1.3.A Activity Profile and Purpose (Exact 8 fields)
  mandateBasis: string;
  responsibleUnit: string;
  purposeNeed: string;
  targetUsers: string;
  scopeCoverage: string;
  resources: string;
  expectedResults: string;
  measuresTargets: string;

  // 1.3.B High-Level Operating Steps (Table & Add/Edit Activity)
  operatingSteps: OperatingStepItem[];

  // 1.3.C Sources Consulted (Table & Add/Edit Item)
  sourcesConsulted: SourceConsultedItem[];

  // 1.3.D Unresolved Questions and Document Requests (Table & Add/Edit Item)
  unresolvedQuestionsList: UnresolvedQuestionItem[];

  // 1.3.E Process Flowchart
  flowchartDescription: string;
  attachedFlowchartName?: string;
  flowchartDocumentVersionId?: number | null;

  // 1.3.F Risk and Control Matrix (Table & Add/Edit Item)
  riskControlMatrix: RiskControlMatrixItem[];
  identifiedRisks: string;
  existingControls: string;
  preliminaryAuditImplications: string;
};

export const defaultUnderstandingProcessState: UnderstandingProcessFormState = {
  mandateBasis: "",
  responsibleUnit: "",
  purposeNeed: "",
  targetUsers: "",
  scopeCoverage: "",
  resources: "",
  expectedResults: "",
  measuresTargets: "",

  operatingSteps: [
    {
      id: "step-1",
      no: 1,
      activityDecision: "",
      responsible: "",
      inputOutput: "",
      reportedControlRecord: "",
      source: "",
    },
    {
      id: "step-2",
      no: 2,
      activityDecision: "",
      responsible: "",
      inputOutput: "",
      reportedControlRecord: "",
      source: "",
    },
    {
      id: "step-3",
      no: 3,
      activityDecision: "",
      responsible: "",
      inputOutput: "",
      reportedControlRecord: "",
      source: "",
    },
    {
      id: "step-4",
      no: 4,
      activityDecision: "",
      responsible: "",
      inputOutput: "",
      reportedControlRecord: "",
      source: "",
    },
  ],

  sourcesConsulted: [
    {
      id: "source-1",
      no: 1,
      documentPersonMethod: "",
      dateCustodian: "",
      whatItSupportsLimitation: "",
    },
    {
      id: "source-2",
      no: 2,
      documentPersonMethod: "",
      dateCustodian: "",
      whatItSupportsLimitation: "",
    },
    {
      id: "source-3",
      no: 3,
      documentPersonMethod: "",
      dateCustodian: "",
      whatItSupportsLimitation: "",
    },
    {
      id: "source-4",
      no: 4,
      documentPersonMethod: "",
      dateCustodian: "",
      whatItSupportsLimitation: "",
    },
  ],

  unresolvedQuestionsList: [
    {
      id: "unresolved-1",
      no: 1,
      informationGapContradiction: "",
      verificationDocumentNeeded: "",
      ownerDue: "",
      drlTaskLink: "",
    },
    {
      id: "unresolved-2",
      no: 2,
      informationGapContradiction: "",
      verificationDocumentNeeded: "",
      ownerDue: "",
      drlTaskLink: "",
    },
    {
      id: "unresolved-3",
      no: 3,
      informationGapContradiction: "",
      verificationDocumentNeeded: "",
      ownerDue: "",
      drlTaskLink: "",
    },
    {
      id: "unresolved-4",
      no: 4,
      informationGapContradiction: "",
      verificationDocumentNeeded: "",
      ownerDue: "",
      drlTaskLink: "",
    },
  ],

  riskControlMatrix: [
    {
      id: "rcm-1",
      no: 1,
      riskDescription: "",
      riskCategory: "",
      likelihood: "",
      justificationLikelihood: "",
      impact: "",
      justificationImpact: "",
      riskRating: "",
      controlActivityInPlace: "",
      controlType: "",
      controlOwner: "",
      controlFrequency: "",
      operatingEffectivenessStatus: "",
      residualRiskRating: "",
    },
    {
      id: "rcm-2",
      no: 2,
      riskDescription: "",
      riskCategory: "",
      likelihood: "",
      justificationLikelihood: "",
      impact: "",
      justificationImpact: "",
      riskRating: "",
      controlActivityInPlace: "",
      controlType: "",
      controlOwner: "",
      controlFrequency: "",
      operatingEffectivenessStatus: "",
      residualRiskRating: "",
    },
    {
      id: "rcm-3",
      no: 3,
      riskDescription: "",
      riskCategory: "",
      likelihood: "",
      justificationLikelihood: "",
      impact: "",
      justificationImpact: "",
      riskRating: "",
      controlActivityInPlace: "",
      controlType: "",
      controlOwner: "",
      controlFrequency: "",
      operatingEffectivenessStatus: "",
      residualRiskRating: "",
    },
    {
      id: "rcm-4",
      no: 4,
      riskDescription: "",
      riskCategory: "",
      likelihood: "",
      justificationLikelihood: "",
      impact: "",
      justificationImpact: "",
      riskRating: "",
      controlActivityInPlace: "",
      controlType: "",
      controlOwner: "",
      controlFrequency: "",
      operatingEffectivenessStatus: "",
      residualRiskRating: "",
    },
  ],

  flowchartDescription: "",
  attachedFlowchartName: "",
  flowchartDocumentVersionId: null,

  identifiedRisks: "",
  existingControls: "",
  preliminaryAuditImplications: "",
};

type AccordionSection = {
  id: "1.3.A" | "1.3.B" | "1.3.C" | "1.3.D" | "1.3.E" | "1.3.F";
  code: string;
  title: string;
};

const SECTIONS: AccordionSection[] = [
  { id: "1.3.A", code: "1.3.A", title: "Activity Profile and Purpose" },
  { id: "1.3.B", code: "1.3.B", title: "High-Level Operating Steps" },
  { id: "1.3.C", code: "1.3.C", title: "Sources Consulted" },
  { id: "1.3.D", code: "1.3.D", title: "Unresolved Questions and Document Requests" },
  { id: "1.3.E", code: "1.3.E", title: "Process Flowchart" },
  { id: "1.3.F", code: "1.3.F", title: "Risk and Control Matrix" },
];

export function UnderstandingProcessPage() {
  const { showToast } = useToast();
  const fileInputRef = useRef<HTMLInputElement>(null);
  const moreActionsRef = useRef<HTMLDivElement>(null);
  const [moreActionsOpen, setMoreActionsOpen] = useState(false);

  // Accordion open states
  const [openSections, setOpenSections] = useState<Record<string, boolean>>({
    "1.3.A": false,
    "1.3.B": false,
    "1.3.C": false,
    "1.3.D": true,
    "1.3.E": false,
    "1.3.F": false,
  });

  // 1.3.B Step editor state
  const [isStepEditorOpen, setIsStepEditorOpen] = useState(false);
  const [editingStep, setEditingStep] = useState<OperatingStepItem>({
    id: "step-1",
    no: 1,
    activityDecision: "",
    responsible: "",
    inputOutput: "",
    reportedControlRecord: "",
    source: "",
  });
  const [activeStepMenu, setActiveStepMenu] = useState<string | null>(null);

  // 1.3.C Source editor state
  const [isSourceEditorOpen, setIsSourceEditorOpen] = useState(false);
  const [editingSource, setEditingSource] = useState<SourceConsultedItem>({
    id: "source-1",
    no: 1,
    documentPersonMethod: "",
    dateCustodian: "",
    whatItSupportsLimitation: "",
  });
  const [activeSourceMenu, setActiveSourceMenu] = useState<string | null>(null);

  // 1.3.D Unresolved Questions editor state
  const [isUnresolvedEditorOpen, setIsUnresolvedEditorOpen] = useState(false);
  const [editingUnresolved, setEditingUnresolved] = useState<UnresolvedQuestionItem>({
    id: "unresolved-1",
    no: 1,
    informationGapContradiction: "",
    verificationDocumentNeeded: "",
    ownerDue: "",
    drlTaskLink: "",
  });
  const [activeUnresolvedMenu, setActiveUnresolvedMenu] = useState<string | null>(null);
  const [isDraggingFlowchart, setIsDraggingFlowchart] = useState(false);

  // 1.3.F Risk and Control Matrix editor state
  const [isRcmEditorOpen, setIsRcmEditorOpen] = useState(false);
  const [editingRcm, setEditingRcm] = useState<RiskControlMatrixItem>({
    id: "rcm-1",
    no: 1,
    riskDescription: "",
    riskCategory: "",
    likelihood: "",
    justificationLikelihood: "",
    impact: "",
    justificationImpact: "",
    riskRating: "",
    controlActivityInPlace: "",
    controlType: "",
    controlOwner: "",
    controlFrequency: "",
    operatingEffectivenessStatus: "",
    residualRiskRating: "",
  });
  const [activeRcmMenu, setActiveRcmMenu] = useState<string | null>(null);

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
  const [isUploading, setIsUploading] = useState(false);
  const [lockVersion, setLockVersion] = useState<number>(1);

  const [formState, setFormState] = useState<UnderstandingProcessFormState>(
    defaultUnderstandingProcessState
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
        if (ws?.sectionReviews?.ppsp) setReviewStatus(ws.sectionReviews.ppsp);

        const ppsp = ws?.survey?.ppsp;
        if (ppsp) {
          const flowchartFile = ws?.attachments?.find(
            (a) => a.id === ppsp.flowchartDocumentVersionId
          );

          setFormState({
            mandateBasis: ppsp.mandate || "",
            responsibleUnit: ppsp.responsibleUnit || "",
            purposeNeed: ppsp.purpose || "",
            targetUsers: ppsp.targetUsers || "",
            scopeCoverage: ppsp.scope || "",
            resources: ppsp.resources || "",
            expectedResults: ppsp.expectedResults || "",
            measuresTargets: ppsp.measures || "",

            flowchartDescription: "",
            flowchartDocumentVersionId: ppsp.flowchartDocumentVersionId || null,
            attachedFlowchartName: flowchartFile?.fileName || "",

            operatingSteps:
              Array.isArray(ppsp.steps) && ppsp.steps.length > 0
                ? ppsp.steps.map((st, i) => ({
                    id: `step-${i + 1}`,
                    no: i + 1,
                    activityDecision: st.activity || "",
                    responsible: st.responsible || "",
                    inputOutput: st.inputOutput || "",
                    reportedControlRecord: st.controlRecord || "",
                    source: st.source || "",
                  }))
                : defaultUnderstandingProcessState.operatingSteps,

            sourcesConsulted:
              Array.isArray(ppsp.sources) && ppsp.sources.length > 0
                ? ppsp.sources.map((sc, i) => ({
                    id: `source-${i + 1}`,
                    no: i + 1,
                    documentPersonMethod: sc.documentPersonMethod || "",
                    dateCustodian: sc.dateCustodian || "",
                    whatItSupportsLimitation: sc.supportsLimitation || "",
                  }))
                : defaultUnderstandingProcessState.sourcesConsulted,

            unresolvedQuestionsList:
              Array.isArray(ppsp.questions) && ppsp.questions.length > 0
                ? ppsp.questions.map((q, i) => ({
                    id: `unresolved-${i + 1}`,
                    no: i + 1,
                    informationGapContradiction: q.informationGap || "",
                    verificationDocumentNeeded: q.verificationNeeded || "",
                    ownerDue: q.ownerDue || "",
                    drlTaskLink: q.taskLink || "",
                  }))
                : defaultUnderstandingProcessState.unresolvedQuestionsList,

            riskControlMatrix:
              Array.isArray(ppsp.risks) && ppsp.risks.length > 0
                ? ppsp.risks.map((r, i) => ({
                    id: `rcm-${i + 1}`,
                    no: i + 1,
                    riskDescription: r.riskDescription || "",
                    riskCategory: r.riskCategory || "",
                    likelihood: r.likelihood != null ? String(r.likelihood) : "",
                    justificationLikelihood: r.likelihoodJustification || "",
                    impact: r.impact != null ? String(r.impact) : "",
                    justificationImpact: r.impactJustification || "",
                    riskRating:
                      r.riskRating != null
                        ? String(r.riskRating)
                        : r.likelihood && r.impact
                        ? String(Number(r.likelihood) * Number(r.impact))
                        : "",
                    controlActivityInPlace: r.controlActivity || "",
                    controlType: r.controlType || "",
                    controlOwner: r.controlOwner || "",
                    controlFrequency: r.controlFrequency || "",
                    operatingEffectivenessStatus: r.operatingEffectiveness || "",
                    residualRiskRating: r.residualRiskRating || "",
                  }))
                : defaultUnderstandingProcessState.riskControlMatrix,

            identifiedRisks: "",
            existingControls: "",
            preliminaryAuditImplications: "",
          });
        }
      })
      .catch((err) => {
        console.warn("Could not load PPSP section from backend:", err);
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
      if (!(event.target as HTMLElement).closest(".step-action-menu")) {
        setActiveStepMenu(null);
      }
      if (!(event.target as HTMLElement).closest(".source-action-menu")) {
        setActiveSourceMenu(null);
      }
      if (!(event.target as HTMLElement).closest(".unresolved-action-menu")) {
        setActiveUnresolvedMenu(null);
      }
      if (!(event.target as HTMLElement).closest(".rcm-action-menu")) {
        setActiveRcmMenu(null);
      }
    }
    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, []);

  const handleChange = (
    field: keyof UnderstandingProcessFormState,
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
        mandate: formState.mandateBasis || null,
        responsibleUnit: formState.responsibleUnit || null,
        purpose: formState.purposeNeed || null,
        targetUsers: formState.targetUsers || null,
        scope: formState.scopeCoverage || null,
        resources: formState.resources || null,
        expectedResults: formState.expectedResults || null,
        measures: formState.measuresTargets || null,
        flowchartDocumentVersionId: formState.flowchartDocumentVersionId || null,
        steps: formState.operatingSteps
          .filter((s) => s.activityDecision || s.responsible || s.inputOutput)
          .map((s) => ({
            activity: s.activityDecision || null,
            responsible: s.responsible || null,
            inputOutput: s.inputOutput || null,
            controlRecord: s.reportedControlRecord || null,
            source: s.source || null,
          })),
        sources: formState.sourcesConsulted
          .filter((s) => s.documentPersonMethod || s.dateCustodian)
          .map((s) => ({
            documentPersonMethod: s.documentPersonMethod || null,
            dateCustodian: s.dateCustodian || null,
            supportsLimitation: s.whatItSupportsLimitation || null,
          })),
        questions: formState.unresolvedQuestionsList
          .filter((q) => q.informationGapContradiction || q.verificationDocumentNeeded)
          .map((q) => ({
            informationGap: q.informationGapContradiction || null,
            verificationNeeded: q.verificationDocumentNeeded || null,
            ownerDue: q.ownerDue || null,
            taskLink: q.drlTaskLink || null,
          })),
        risks: formState.riskControlMatrix
          .filter((r) => r.riskDescription || r.controlActivityInPlace)
          .map((r) => ({
            riskDescription: r.riskDescription || null,
            riskCategory: r.riskCategory || null,
            likelihood: r.likelihood ? parseInt(r.likelihood, 10) : null,
            likelihoodJustification: r.justificationLikelihood || null,
            impact: r.impact ? parseInt(r.impact, 10) : null,
            impactJustification: r.justificationImpact || null,
            controlActivity: r.controlActivityInPlace || null,
            controlType: r.controlType || null,
            controlOwner: r.controlOwner || null,
            controlFrequency: r.controlFrequency || null,
            operatingEffectiveness: r.operatingEffectivenessStatus || null,
            residualRiskRating: r.residualRiskRating || null,
          })),
      };

      const updated = await preliminarySurveyService.saveSection(
        engagementId,
        "ppsp",
        payload,
        lockVersion
      );

      if (updated?.lockVersion) setLockVersion(updated.lockVersion);

      showToast({
        title: "Draft Saved",
        description: "Understanding of Program / Process details saved to backend.",
        tone: "success",
      });
    } catch (err: any) {
      showToast({
        title: "Save Failed",
        description: err?.message || "Could not save process details.",
        tone: "warning",
      });
    } finally {
      setIsSaving(false);
    }
  };

  const uploadFile = async (file: File) => {
    setIsUploading(true);
    try {
      const uploaded = await preliminarySurveyService.uploadAttachment(engagementId, file);
      setFormState((prev) => ({
        ...prev,
        attachedFlowchartName: uploaded.fileName || file.name,
        flowchartDocumentVersionId: uploaded.id,
      }));
      showToast({
        title: "Flowchart Attached",
        description: `Uploaded "${file.name}" successfully.`,
        tone: "success",
      });
    } catch (err: any) {
      showToast({
        title: "Upload Failed",
        description: err?.message || "Could not upload flowchart document.",
        tone: "warning",
      });
    } finally {
      setIsUploading(false);
    }
  };

  const handleFileUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      uploadFile(file);
    }
  };

  const handleReviewAction = async (action: "SUBMIT" | "APPROVE" | "RETURN") => {
    try {
      await handleSaveDraft();
      await preliminarySurveyService.reviewSection(
        engagementId,
        "ppsp",
        action,
        lockVersion
      );
      showToast({
        title: `Section ${action === "SUBMIT" ? "Submitted" : action === "APPROVE" ? "Approved" : "Returned"}`,
        description: `1.3 Understanding of Program/Process has been updated.`,
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

  // 1.3.B Operating Step actions
  const handleOpenAddStep = () => {
    const nextNo =
      formState.operatingSteps.length > 0
        ? Math.max(...formState.operatingSteps.map((s) => s.no || 0)) + 1
        : 1;

    setEditingStep({
      id: `step-${Date.now()}`,
      no: nextNo,
      activityDecision: "",
      responsible: "",
      inputOutput: "",
      reportedControlRecord: "",
      source: "",
    });
    setIsStepEditorOpen(true);
  };

  const handleOpenEditStep = (step: OperatingStepItem) => {
    setEditingStep({ ...step });
    setIsStepEditorOpen(true);
    setActiveStepMenu(null);
  };

  const handleSaveStepToTable = () => {
    setFormState((prev) => {
      const exists = prev.operatingSteps.some((s) => s.id === editingStep.id);
      let updatedSteps: OperatingStepItem[];
      if (exists) {
        updatedSteps = prev.operatingSteps.map((s) =>
          s.id === editingStep.id ? editingStep : s
        );
      } else {
        const firstBlankIndex = prev.operatingSteps.findIndex(
          (s) =>
            !s.activityDecision &&
            !s.responsible &&
            !s.inputOutput &&
            !s.reportedControlRecord &&
            !s.source
        );
        if (firstBlankIndex >= 0) {
          updatedSteps = [...prev.operatingSteps];
          updatedSteps[firstBlankIndex] = {
            ...editingStep,
            no: updatedSteps[firstBlankIndex].no,
          };
        } else {
          updatedSteps = [...prev.operatingSteps, editingStep];
        }
      }
      return {
        ...prev,
        operatingSteps: updatedSteps,
      };
    });

    setIsStepEditorOpen(false);
    showToast({
      title: "Updated to Table",
      description: editingStep.activityDecision
        ? `Saved "${editingStep.activityDecision}".`
        : "Operating step updated to table.",
      tone: "success",
    });
  };

  const handleClearStep = (id: string) => {
    setFormState((prev) => ({
      ...prev,
      operatingSteps: prev.operatingSteps.map((s) =>
        s.id === id
          ? {
              ...s,
              activityDecision: "",
              responsible: "",
              inputOutput: "",
              reportedControlRecord: "",
              source: "",
            }
          : s
      ),
    }));
    setActiveStepMenu(null);
    showToast({
      title: "Row Cleared",
      description: "Row contents cleared.",
      tone: "info",
    });
  };

  const handleDeleteStep = (id: string) => {
    setFormState((prev) => {
      const remaining = prev.operatingSteps.filter((s) => s.id !== id);
      const renumbered = remaining.map((s, idx) => ({ ...s, no: idx + 1 }));
      return {
        ...prev,
        operatingSteps: renumbered,
      };
    });
    setActiveStepMenu(null);
    showToast({
      title: "Row Removed",
      description: "Operating step removed from table.",
      tone: "info",
    });
  };

  // 1.3.C Source Consulted actions
  const handleOpenAddSource = () => {
    const nextNo =
      formState.sourcesConsulted.length > 0
        ? Math.max(...formState.sourcesConsulted.map((s) => s.no || 0)) + 1
        : 1;

    setEditingSource({
      id: `source-${Date.now()}`,
      no: nextNo,
      documentPersonMethod: "",
      dateCustodian: "",
      whatItSupportsLimitation: "",
    });
    setIsSourceEditorOpen(true);
  };

  const handleOpenEditSource = (source: SourceConsultedItem) => {
    setEditingSource({ ...source });
    setIsSourceEditorOpen(true);
    setActiveSourceMenu(null);
  };

  const handleSaveSourceToTable = () => {
    setFormState((prev) => {
      const exists = prev.sourcesConsulted.some((s) => s.id === editingSource.id);
      let updatedSources: SourceConsultedItem[];
      if (exists) {
        updatedSources = prev.sourcesConsulted.map((s) =>
          s.id === editingSource.id ? editingSource : s
        );
      } else {
        const firstBlankIndex = prev.sourcesConsulted.findIndex(
          (s) =>
            !s.documentPersonMethod &&
            !s.dateCustodian &&
            !s.whatItSupportsLimitation
        );
        if (firstBlankIndex >= 0) {
          updatedSources = [...prev.sourcesConsulted];
          updatedSources[firstBlankIndex] = {
            ...editingSource,
            no: updatedSources[firstBlankIndex].no,
          };
        } else {
          updatedSources = [...prev.sourcesConsulted, editingSource];
        }
      }
      return {
        ...prev,
        sourcesConsulted: updatedSources,
      };
    });

    setIsSourceEditorOpen(false);
    showToast({
      title: "Updated to Table",
      description: editingSource.documentPersonMethod
        ? `Saved "${editingSource.documentPersonMethod}".`
        : "Source item updated to table.",
      tone: "success",
    });
  };

  const handleClearSource = (id: string) => {
    setFormState((prev) => ({
      ...prev,
      sourcesConsulted: prev.sourcesConsulted.map((s) =>
        s.id === id
          ? {
              ...s,
              documentPersonMethod: "",
              dateCustodian: "",
              whatItSupportsLimitation: "",
            }
          : s
      ),
    }));
    setActiveSourceMenu(null);
    showToast({
      title: "Row Cleared",
      description: "Row contents cleared.",
      tone: "info",
    });
  };

  const handleDeleteSource = (id: string) => {
    setFormState((prev) => {
      const remaining = prev.sourcesConsulted.filter((s) => s.id !== id);
      const renumbered = remaining.map((s, idx) => ({ ...s, no: idx + 1 }));
      return {
        ...prev,
        sourcesConsulted: renumbered,
      };
    });
    setActiveSourceMenu(null);
    showToast({
      title: "Row Removed",
      description: "Source item removed from table.",
      tone: "info",
    });
  };

  // 1.3.D Unresolved Questions actions
  const handleOpenAddUnresolved = () => {
    const nextNo =
      formState.unresolvedQuestionsList.length > 0
        ? Math.max(...formState.unresolvedQuestionsList.map((s) => s.no || 0)) + 1
        : 1;

    setEditingUnresolved({
      id: `unresolved-${Date.now()}`,
      no: nextNo,
      informationGapContradiction: "",
      verificationDocumentNeeded: "",
      ownerDue: "",
      drlTaskLink: "",
    });
    setIsUnresolvedEditorOpen(true);
  };

  const handleOpenEditUnresolved = (item: UnresolvedQuestionItem) => {
    setEditingUnresolved({ ...item });
    setIsUnresolvedEditorOpen(true);
    setActiveUnresolvedMenu(null);
  };

  const handleSaveUnresolvedToTable = () => {
    setFormState((prev) => {
      const exists = prev.unresolvedQuestionsList.some(
        (s) => s.id === editingUnresolved.id
      );
      let updatedList: UnresolvedQuestionItem[];
      if (exists) {
        updatedList = prev.unresolvedQuestionsList.map((s) =>
          s.id === editingUnresolved.id ? editingUnresolved : s
        );
      } else {
        const firstBlankIndex = prev.unresolvedQuestionsList.findIndex(
          (s) =>
            !s.informationGapContradiction &&
            !s.verificationDocumentNeeded &&
            !s.ownerDue &&
            !s.drlTaskLink
        );
        if (firstBlankIndex >= 0) {
          updatedList = [...prev.unresolvedQuestionsList];
          updatedList[firstBlankIndex] = {
            ...editingUnresolved,
            no: updatedList[firstBlankIndex].no,
          };
        } else {
          updatedList = [...prev.unresolvedQuestionsList, editingUnresolved];
        }
      }
      return {
        ...prev,
        unresolvedQuestionsList: updatedList,
      };
    });

    setIsUnresolvedEditorOpen(false);
    showToast({
      title: "Updated to Table",
      description: editingUnresolved.informationGapContradiction
        ? `Saved "${editingUnresolved.informationGapContradiction}".`
        : "Unresolved item updated to table.",
      tone: "success",
    });
  };

  const handleClearUnresolved = (id: string) => {
    setFormState((prev) => ({
      ...prev,
      unresolvedQuestionsList: prev.unresolvedQuestionsList.map((s) =>
        s.id === id
          ? {
              ...s,
              informationGapContradiction: "",
              verificationDocumentNeeded: "",
              ownerDue: "",
              drlTaskLink: "",
            }
          : s
      ),
    }));
    setActiveUnresolvedMenu(null);
    showToast({
      title: "Row Cleared",
      description: "Row contents cleared.",
      tone: "info",
    });
  };

  const handleDeleteUnresolved = (id: string) => {
    setFormState((prev) => {
      const remaining = prev.unresolvedQuestionsList.filter((s) => s.id !== id);
      const renumbered = remaining.map((s, idx) => ({ ...s, no: idx + 1 }));
      return {
        ...prev,
        unresolvedQuestionsList: renumbered,
      };
    });
    setActiveUnresolvedMenu(null);
    showToast({
      title: "Row Removed",
      description: "Unresolved item removed from table.",
      tone: "info",
    });
  };

  // 1.3.F Risk and Control Matrix actions
  const handleOpenAddRcm = () => {
    const nextNo =
      formState.riskControlMatrix.length > 0
        ? Math.max(...formState.riskControlMatrix.map((s) => s.no || 0)) + 1
        : 1;

    setEditingRcm({
      id: `rcm-${Date.now()}`,
      no: nextNo,
      riskDescription: "",
      riskCategory: "",
      likelihood: "",
      justificationLikelihood: "",
      impact: "",
      justificationImpact: "",
      riskRating: "",
      controlActivityInPlace: "",
      controlType: "",
      controlOwner: "",
      controlFrequency: "",
      operatingEffectivenessStatus: "",
      residualRiskRating: "",
    });
    setIsRcmEditorOpen(true);
  };

  const handleOpenEditRcm = (item: RiskControlMatrixItem) => {
    setEditingRcm({ ...item });
    setIsRcmEditorOpen(true);
    setActiveRcmMenu(null);
  };

  const handleSaveRcmToTable = () => {
    setFormState((prev) => {
      const exists = prev.riskControlMatrix.some((s) => s.id === editingRcm.id);
      let updatedList: RiskControlMatrixItem[];
      if (exists) {
        updatedList = prev.riskControlMatrix.map((s) =>
          s.id === editingRcm.id ? editingRcm : s
        );
      } else {
        const firstBlankIndex = prev.riskControlMatrix.findIndex(
          (s) =>
            !s.riskDescription &&
            !s.riskCategory &&
            !s.likelihood &&
            !s.controlActivityInPlace
        );
        if (firstBlankIndex >= 0) {
          updatedList = [...prev.riskControlMatrix];
          updatedList[firstBlankIndex] = {
            ...editingRcm,
            no: updatedList[firstBlankIndex].no,
          };
        } else {
          updatedList = [...prev.riskControlMatrix, editingRcm];
        }
      }
      return {
        ...prev,
        riskControlMatrix: updatedList,
      };
    });

    setIsRcmEditorOpen(false);
    showToast({
      title: "Updated to Table",
      description: editingRcm.riskDescription
        ? `Saved "${editingRcm.riskDescription}".`
        : "Risk and control item updated to table.",
      tone: "success",
    });
  };

  const handleClearRcm = (id: string) => {
    setFormState((prev) => ({
      ...prev,
      riskControlMatrix: prev.riskControlMatrix.map((s) =>
        s.id === id
          ? {
              ...s,
              riskDescription: "",
              riskCategory: "",
              likelihood: "",
              justificationLikelihood: "",
              impact: "",
              justificationImpact: "",
              riskRating: "",
              controlActivityInPlace: "",
              controlType: "",
              controlOwner: "",
              controlFrequency: "",
              operatingEffectivenessStatus: "",
              residualRiskRating: "",
            }
          : s
      ),
    }));
    setActiveRcmMenu(null);
    showToast({
      title: "Row Cleared",
      description: "Row contents cleared.",
      tone: "info",
    });
  };

  const handleDeleteRcm = (id: string) => {
    setFormState((prev) => {
      const remaining = prev.riskControlMatrix.filter((s) => s.id !== id);
      const renumbered = remaining.map((s, idx) => ({ ...s, no: idx + 1 }));
      return {
        ...prev,
        riskControlMatrix: renumbered,
      };
    });
    setActiveRcmMenu(null);
    showToast({
      title: "Row Removed",
      description: "Risk and control item removed from table.",
      tone: "info",
    });
  };

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
            Understanding of the Program...
          </span>
        </div>

        {/* Outer Frame Card - Saturated Blue Background */}
        <div className="w-full overflow-hidden rounded-md border border-[#4485bb] bg-[#dbebf9] shadow-xs">
          {/* Top Header Bar */}
          <div className="flex items-center justify-between border-b border-[#7eaed5] bg-[#c7e0f6] px-4 py-2 text-[12px] font-semibold text-[#103565]">
            <span className="truncate">
              {engagementCode} - Planning - Preliminary Survey -{" "}
              <span className="font-bold text-[#103565]">
                Understanding of the Program...
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

          {/* Form Content Body */}
          <div className="px-5 py-3.5 sm:px-7 sm:py-4 lg:px-8 space-y-3.5">
            {/* Title Row with Process Flowchart SVG Icon */}
            <div className="flex items-center gap-3">
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
                  <rect x="25" y="8" width="10" height="9" rx="1.5" />
                  <path d="M19 12.5h5" />
                  <path d="M22 10l3 2.5-3 2.5" />
                  <path d="M19 12.5H14a6 6 0 0 0-6 6v0a6 6 0 0 1-6 6H16a6 6 0 0 0-6 6v0a6 6 0 0 0 6 6h14" />
                  <circle cx="9" cy="36.5" r="3" fill="currentColor" />
                  <rect x="22" y="32" width="10" height="9" rx="1.5" />
                </svg>
              </div>

              <h1 className="text-base sm:text-lg font-bold tracking-tight text-[#103565] flex items-center gap-2">
                <span>1.3 Understanding of the Program, Project, System or Process under Audit</span>
                {isLoading && <Loader2 size={14} className="animate-spin text-[#1877f2]" />}
              </h1>
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

            {/* Accordion List Container matching screenshot */}
            <div className="mx-auto w-full max-w-[1000px] pt-1 space-y-2">
              {SECTIONS.map((section) => {
                const isOpen = openSections[section.id];

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
                          isOpen ? "rotate-0 text-[#1877f2]" : "-rotate-90 text-slate-500"
                        }`}
                      >
                        <ChevronDown size={17} strokeWidth={2.4} />
                      </div>
                    </button>

                    {/* Smooth Collapsible Content Area */}
                    <div
                      className={`grid transition-[grid-template-rows,opacity] duration-300 ease-in-out ${
                        isOpen ? "grid-rows-[1fr] opacity-100" : "grid-rows-[0fr] opacity-0"
                      }`}
                    >
                      <div className="overflow-hidden">
                        <div className="pt-1 pb-2 px-1">
                          {/* 1.3.A: Activity Profile and Purpose */}
                          {section.id === "1.3.A" && (
                            <div className="grid grid-cols-1 gap-x-10 gap-y-2.5 lg:grid-cols-2 text-xs">
                              <div>
                                <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                                  Mandate / Basis
                                </label>
                                <input
                                  type="text"
                                  value={formState.mandateBasis}
                                  onChange={(e) =>
                                    handleChange("mandateBasis", e.target.value)
                                  }
                                  placeholder="Instructions or guide on what to input in this field or input box"
                                  className="h-8 w-full rounded-[5px] border border-[#7eaed5] bg-white px-3 text-[12px] text-slate-800 placeholder:italic placeholder:text-[#8baecf] placeholder:text-[11px] shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                                />
                              </div>

                              <div>
                                <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                                  Responsible Unit
                                </label>
                                <input
                                  type="text"
                                  value={formState.responsibleUnit}
                                  onChange={(e) =>
                                    handleChange("responsibleUnit", e.target.value)
                                  }
                                  className="h-8 w-full rounded-[5px] border border-[#7eaed5] bg-white px-3 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                                />
                              </div>

                              <div>
                                <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                                  Purpose / Need
                                </label>
                                <input
                                  type="text"
                                  value={formState.purposeNeed}
                                  onChange={(e) =>
                                    handleChange("purposeNeed", e.target.value)
                                  }
                                  className="h-8 w-full rounded-[5px] border border-[#7eaed5] bg-white px-3 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                                />
                              </div>

                              <div>
                                <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                                  Target Users
                                </label>
                                <input
                                  type="text"
                                  value={formState.targetUsers}
                                  onChange={(e) =>
                                    handleChange("targetUsers", e.target.value)
                                  }
                                  className="h-8 w-full rounded-[5px] border border-[#7eaed5] bg-white px-3 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                                />
                              </div>

                              <div>
                                <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                                  Scope / Coverage
                                </label>
                                <input
                                  type="text"
                                  value={formState.scopeCoverage}
                                  onChange={(e) =>
                                    handleChange("scopeCoverage", e.target.value)
                                  }
                                  className="h-8 w-full rounded-[5px] border border-[#7eaed5] bg-white px-3 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                                />
                              </div>

                              <div>
                                <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                                  Resources
                                </label>
                                <input
                                  type="text"
                                  value={formState.resources}
                                  onChange={(e) =>
                                    handleChange("resources", e.target.value)
                                  }
                                  className="h-8 w-full rounded-[5px] border border-[#7eaed5] bg-white px-3 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                                />
                              </div>

                              <div>
                                <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                                  Expected Results
                                </label>
                                <input
                                  type="text"
                                  value={formState.expectedResults}
                                  onChange={(e) =>
                                    handleChange("expectedResults", e.target.value)
                                  }
                                  className="h-8 w-full rounded-[5px] border border-[#7eaed5] bg-white px-3 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                                />
                              </div>

                              <div>
                                <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                                  Measures / Targets
                                </label>
                                <input
                                  type="text"
                                  value={formState.measuresTargets}
                                  onChange={(e) =>
                                    handleChange("measuresTargets", e.target.value)
                                  }
                                  className="h-8 w-full rounded-[5px] border border-[#7eaed5] bg-white px-3 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                                />
                              </div>
                            </div>
                          )}

                          {/* 1.3.B: High-Level Operating Steps */}
                          {section.id === "1.3.B" && (
                            <div className="space-y-3">
                              <div className="flex items-center justify-end">
                                <button
                                  type="button"
                                  onClick={handleOpenAddStep}
                                  className="flex cursor-pointer items-center gap-1.5 rounded-[5px] border border-slate-300 bg-white px-3 py-1.5 text-[12px] font-medium text-slate-700 shadow-2xs hover:bg-slate-50 transition"
                                >
                                  <Plus size={13} strokeWidth={2.4} />
                                  <span>Add Activity / Decision</span>
                                </button>
                              </div>

                              {isStepEditorOpen ? (
                                <div className="overflow-hidden rounded-[6px] border border-[#7eaed5] bg-[#dbebf9] shadow-xs">
                                  <div className="flex items-center justify-between border-b border-[#7eaed5] bg-[#c7e0f6] px-3.5 py-1.5 text-[11px] font-semibold text-[#103565]">
                                    <span>
                                      1.3.B. High-Level Operating Steps - Add / Edit Activity or Design
                                    </span>
                                    <button
                                      type="button"
                                      onClick={() => setIsStepEditorOpen(false)}
                                      className="cursor-pointer rounded p-0.5 text-slate-600 hover:bg-[#b0d2ef] hover:text-slate-900 transition"
                                    >
                                      <X size={15} strokeWidth={2.4} />
                                    </button>
                                  </div>

                                  <div className="p-5 sm:p-6 space-y-3.5">
                                    <div className="w-full max-w-[380px] sm:max-w-[420px]">
                                      <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                                        Activity / Decision
                                      </label>
                                      <input
                                        type="text"
                                        value={editingStep.activityDecision}
                                        onChange={(e) =>
                                          setEditingStep({
                                            ...editingStep,
                                            activityDecision: e.target.value,
                                          })
                                        }
                                        className="h-8 w-full rounded-[5px] border border-[#7eaed5] bg-white px-3 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                                      />
                                    </div>

                                    <div className="w-full max-w-[380px] sm:max-w-[420px]">
                                      <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                                        Responsible
                                      </label>
                                      <input
                                        type="text"
                                        value={editingStep.responsible}
                                        onChange={(e) =>
                                          setEditingStep({
                                            ...editingStep,
                                            responsible: e.target.value,
                                          })
                                        }
                                        className="h-8 w-full rounded-[5px] border border-[#7eaed5] bg-white px-3 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                                      />
                                    </div>

                                    <div className="w-full max-w-[380px] sm:max-w-[420px]">
                                      <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                                        Input - Output
                                      </label>
                                      <input
                                        type="text"
                                        value={editingStep.inputOutput}
                                        onChange={(e) =>
                                          setEditingStep({
                                            ...editingStep,
                                            inputOutput: e.target.value,
                                          })
                                        }
                                        className="h-8 w-full rounded-[5px] border border-[#7eaed5] bg-white px-3 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                                      />
                                    </div>

                                    <div className="w-full max-w-[380px] sm:max-w-[420px]">
                                      <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                                        Reported Control / Record
                                      </label>
                                      <input
                                        type="text"
                                        value={editingStep.reportedControlRecord}
                                        onChange={(e) =>
                                          setEditingStep({
                                            ...editingStep,
                                            reportedControlRecord: e.target.value,
                                          })
                                        }
                                        className="h-8 w-full rounded-[5px] border border-[#7eaed5] bg-white px-3 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                                      />
                                    </div>

                                    <div className="w-full max-w-[380px] sm:max-w-[420px]">
                                      <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                                        Source
                                      </label>
                                      <input
                                        type="text"
                                        value={editingStep.source}
                                        onChange={(e) =>
                                          setEditingStep({
                                            ...editingStep,
                                            source: e.target.value,
                                          })
                                        }
                                        className="h-8 w-full rounded-[5px] border border-[#7eaed5] bg-white px-3 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                                      />
                                    </div>

                                    <div className="flex items-center justify-end gap-2.5 pt-4">
                                      <button
                                        type="button"
                                        onClick={() => setIsStepEditorOpen(false)}
                                        className="flex cursor-pointer items-center gap-1.5 rounded-[5px] border border-slate-300 bg-white px-3.5 py-1.5 text-xs font-medium text-slate-700 shadow-2xs hover:bg-slate-50 transition"
                                      >
                                        <ArrowLeft size={13} />
                                        <span>Back to 1.3 workspace</span>
                                      </button>
                                      <button
                                        type="button"
                                        onClick={handleSaveStepToTable}
                                        className="cursor-pointer rounded-[5px] border border-slate-300 bg-white px-4 py-1.5 text-xs font-semibold text-slate-700 shadow-2xs hover:bg-slate-50 transition"
                                      >
                                        Update to Table
                                      </button>
                                    </div>
                                  </div>
                                </div>
                              ) : (
                                <div className="overflow-x-auto rounded-[4px] border border-[#7eaed5] bg-white">
                                  <table className="w-full border-collapse text-left text-[11px]">
                                    <thead>
                                      <tr className="border-b border-[#7eaed5] bg-[#dbebf9] text-[#1a3d6d]">
                                        <th className="border-r border-[#7eaed5] px-3 py-1.5 font-semibold text-center w-[5%]">
                                          No.
                                        </th>
                                        <th className="border-r border-[#7eaed5] px-2.5 py-1.5 font-semibold w-[24%]">
                                          Activity / decision
                                        </th>
                                        <th className="border-r border-[#7eaed5] px-2.5 py-1.5 font-semibold w-[18%]">
                                          Responsible
                                        </th>
                                        <th className="border-r border-[#7eaed5] px-2.5 py-1.5 font-semibold w-[18%]">
                                          Input - Output
                                        </th>
                                        <th className="border-r border-[#7eaed5] px-2.5 py-1.5 font-semibold w-[20%]">
                                          Reported control / record
                                        </th>
                                        <th className="border-r border-[#7eaed5] px-2.5 py-1.5 font-semibold w-[12%]">
                                          Source
                                        </th>
                                        <th className="px-1.5 py-1.5 text-center font-semibold w-[3%]">
                                          {/* Options */}
                                        </th>
                                      </tr>
                                    </thead>
                                    <tbody>
                                      {formState.operatingSteps.map((row, idx) => {
                                      const isNearBottom = idx >= Math.max(1, formState.operatingSteps.length - 2);
                                      return (
                                        <tr
                                          key={row.id}
                                          className="border-b border-[#c7e0f6] last:border-b-0 hover:bg-[#f3f8fd] transition-colors h-[26px]"
                                        >
                                          <td className="border-r border-[#c7e0f6] px-3 py-1 text-center font-medium text-slate-600">
                                            {row.no}
                                          </td>
                                          <td className="border-r border-[#c7e0f6] px-2.5 py-1 text-slate-800 truncate">
                                            {row.activityDecision || "\u00A0"}
                                          </td>
                                          <td className="border-r border-[#c7e0f6] px-2.5 py-1 text-slate-800 truncate">
                                            {row.responsible}
                                          </td>
                                          <td className="border-r border-[#c7e0f6] px-2.5 py-1 text-slate-800 truncate">
                                            {row.inputOutput}
                                          </td>
                                          <td className="border-r border-[#c7e0f6] px-2.5 py-1 text-slate-800 truncate">
                                            {row.reportedControlRecord}
                                          </td>
                                          <td className="border-r border-[#c7e0f6] px-2.5 py-1 text-slate-800 truncate">
                                            {row.source}
                                          </td>
                                          <td className="relative px-1 py-1 text-center">
                                            <button
                                              type="button"
                                              onClick={() =>
                                                setActiveStepMenu(
                                                  activeStepMenu === row.id ? null : row.id
                                                )
                                              }
                                              aria-label="Row actions"
                                              className="cursor-pointer text-slate-500 hover:text-slate-800 p-0.5 rounded transition"
                                            >
                                              <MoreHorizontal size={14} />
                                            </button>

                                            {activeStepMenu === row.id && (
                                              <div className={`step-action-menu absolute right-2 z-50 w-28 rounded-md border border-slate-200 bg-white py-1 shadow-md text-[11px] text-left ${isNearBottom ? "bottom-full mb-1" : "top-full mt-1"}`}>
                                                <button
                                                  type="button"
                                                  onClick={() => handleOpenEditStep(row)}
                                                  className="flex w-full items-center gap-1.5 px-2.5 py-1 hover:bg-[#d8ecff] text-slate-700 transition"
                                                >
                                                  <Edit2 size={11} /> Edit Row
                                                </button>
                                                <button
                                                  type="button"
                                                  onClick={() => handleClearStep(row.id)}
                                                  className="flex w-full items-center gap-1.5 px-2.5 py-1 hover:bg-slate-50 text-slate-600 transition"
                                                >
                                                  Clear
                                                </button>
                                                {formState.operatingSteps.length > 1 && (
                                                  <button
                                                    type="button"
                                                    onClick={() => handleDeleteStep(row.id)}
                                                    className="flex w-full items-center gap-1.5 px-2.5 py-1 hover:bg-rose-50 text-rose-600 transition"
                                                  >
                                                    <Trash2 size={11} /> Delete
                                                  </button>
                                                )}
                                              </div>
                                            )}
                                          </td>
                                        </tr>
                                      );
                                    })}
                                    </tbody>
                                  </table>
                                </div>
                              )}
                            </div>
                          )}

                          {/* 1.3.C: Sources Consulted */}
                          {section.id === "1.3.C" && (
                            <div className="space-y-3">
                              <div className="flex items-center justify-end">
                                <button
                                  type="button"
                                  onClick={handleOpenAddSource}
                                  className="flex cursor-pointer items-center gap-1.5 rounded-[5px] border border-slate-300 bg-white px-3 py-1.5 text-[12px] font-medium text-slate-700 shadow-2xs hover:bg-slate-50 transition"
                                >
                                  <Plus size={13} strokeWidth={2.4} />
                                  <span>Add Item to List</span>
                                </button>
                              </div>

                              {isSourceEditorOpen ? (
                                <div className="overflow-hidden rounded-[6px] border border-[#7eaed5] bg-[#dbebf9] shadow-xs">
                                  <div className="flex items-center justify-between border-b border-[#7eaed5] bg-[#c7e0f6] px-3.5 py-1.5 text-[11px] font-semibold text-[#103565]">
                                    <span>
                                      1.3.C. Sources Consulted - Add / Edit Item
                                    </span>
                                    <button
                                      type="button"
                                      onClick={() => setIsSourceEditorOpen(false)}
                                      className="cursor-pointer rounded p-0.5 text-slate-600 hover:bg-[#b0d2ef] hover:text-slate-900 transition"
                                    >
                                      <X size={15} strokeWidth={2.4} />
                                    </button>
                                  </div>

                                  <div className="p-5 sm:p-6 space-y-3.5">
                                    <div className="w-full max-w-[380px] sm:max-w-[420px]">
                                      <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                                        Document / Person / Method
                                      </label>
                                      <input
                                        type="text"
                                        value={editingSource.documentPersonMethod}
                                        onChange={(e) =>
                                          setEditingSource({
                                            ...editingSource,
                                            documentPersonMethod: e.target.value,
                                          })
                                        }
                                        className="h-8 w-full rounded-[5px] border border-[#7eaed5] bg-white px-3 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                                      />
                                    </div>

                                    <div className="w-full max-w-[380px] sm:max-w-[420px]">
                                      <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                                        Date / Custodian
                                      </label>
                                      <input
                                        type="text"
                                        value={editingSource.dateCustodian}
                                        onChange={(e) =>
                                          setEditingSource({
                                            ...editingSource,
                                            dateCustodian: e.target.value,
                                          })
                                        }
                                        className="h-8 w-full rounded-[5px] border border-[#7eaed5] bg-white px-3 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                                      />
                                    </div>

                                    <div className="w-full max-w-[380px] sm:max-w-[420px]">
                                      <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                                        What is supports / limitation
                                      </label>
                                      <input
                                        type="text"
                                        value={editingSource.whatItSupportsLimitation}
                                        onChange={(e) =>
                                          setEditingSource({
                                            ...editingSource,
                                            whatItSupportsLimitation: e.target.value,
                                          })
                                        }
                                        className="h-8 w-full rounded-[5px] border border-[#7eaed5] bg-white px-3 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                                      />
                                    </div>

                                    <div className="flex items-center justify-end gap-2.5 pt-4">
                                      <button
                                        type="button"
                                        onClick={() => setIsSourceEditorOpen(false)}
                                        className="flex cursor-pointer items-center gap-1.5 rounded-[5px] border border-slate-300 bg-white px-3.5 py-1.5 text-xs font-medium text-slate-700 shadow-2xs hover:bg-slate-50 transition"
                                      >
                                        <ArrowLeft size={13} />
                                        <span>Back to 1.3 workspace</span>
                                      </button>
                                      <button
                                        type="button"
                                        onClick={handleSaveSourceToTable}
                                        className="cursor-pointer rounded-[5px] border border-slate-300 bg-white px-4 py-1.5 text-xs font-semibold text-slate-700 shadow-2xs hover:bg-slate-50 transition"
                                      >
                                        Update to Table
                                      </button>
                                    </div>
                                  </div>
                                </div>
                              ) : (
                                <div className="overflow-x-auto rounded-[4px] border border-[#7eaed5] bg-white">
                                  <table className="w-full border-collapse text-left text-[11px]">
                                    <thead>
                                      <tr className="border-b border-[#7eaed5] bg-[#dbebf9] text-[#1a3d6d]">
                                        <th className="border-r border-[#7eaed5] px-3 py-1.5 font-semibold text-center w-[5%]">
                                          No.
                                        </th>
                                        <th className="border-r border-[#7eaed5] px-2.5 py-1.5 font-semibold w-[35%]">
                                          Document / Person / Method
                                        </th>
                                        <th className="border-r border-[#7eaed5] px-2.5 py-1.5 font-semibold w-[28%]">
                                          Date / Custodian
                                        </th>
                                        <th className="border-r border-[#7eaed5] px-2.5 py-1.5 font-semibold w-[28%]">
                                          What it supports / limitation
                                        </th>
                                        <th className="px-1.5 py-1.5 text-center font-semibold w-[4%]">
                                          {/* Options */}
                                        </th>
                                      </tr>
                                    </thead>
                                    <tbody>
                                      {formState.sourcesConsulted.map((row, idx) => {
                                      const isNearBottom = idx >= Math.max(1, formState.sourcesConsulted.length - 2);
                                      return (
                                        <tr
                                          key={row.id}
                                          className="border-b border-[#c7e0f6] last:border-b-0 hover:bg-[#f3f8fd] transition-colors h-[26px]"
                                        >
                                          <td className="border-r border-[#c7e0f6] px-3 py-1 text-center font-medium text-slate-600">
                                            {row.no}
                                          </td>
                                          <td className="border-r border-[#c7e0f6] px-2.5 py-1 text-slate-800 truncate">
                                            {row.documentPersonMethod || "\u00A0"}
                                          </td>
                                          <td className="border-r border-[#c7e0f6] px-2.5 py-1 text-slate-800 truncate">
                                            {row.dateCustodian}
                                          </td>
                                          <td className="border-r border-[#c7e0f6] px-2.5 py-1 text-slate-800 truncate">
                                            {row.whatItSupportsLimitation}
                                          </td>
                                          <td className="relative px-1 py-1 text-center">
                                            <button
                                              type="button"
                                              onClick={() =>
                                                setActiveSourceMenu(
                                                  activeSourceMenu === row.id ? null : row.id
                                                )
                                              }
                                              aria-label="Row actions"
                                              className="cursor-pointer text-slate-500 hover:text-slate-800 p-0.5 rounded transition"
                                            >
                                              <MoreHorizontal size={14} />
                                            </button>

                                            {activeSourceMenu === row.id && (
                                              <div className={`source-action-menu absolute right-2 z-50 w-28 rounded-md border border-slate-200 bg-white py-1 shadow-md text-[11px] text-left ${isNearBottom ? "bottom-full mb-1" : "top-full mt-1"}`}>
                                                <button
                                                  type="button"
                                                  onClick={() => handleOpenEditSource(row)}
                                                  className="flex w-full items-center gap-1.5 px-2.5 py-1 hover:bg-[#d8ecff] text-slate-700 transition"
                                                >
                                                  <Edit2 size={11} /> Edit Row
                                                </button>
                                                <button
                                                  type="button"
                                                  onClick={() => handleClearSource(row.id)}
                                                  className="flex w-full items-center gap-1.5 px-2.5 py-1 hover:bg-slate-50 text-slate-600 transition"
                                                >
                                                  Clear
                                                </button>
                                                {formState.sourcesConsulted.length > 1 && (
                                                  <button
                                                    type="button"
                                                    onClick={() => handleDeleteSource(row.id)}
                                                    className="flex w-full items-center gap-1.5 px-2.5 py-1 hover:bg-rose-50 text-rose-600 transition"
                                                  >
                                                    <Trash2 size={11} /> Delete
                                                  </button>
                                                )}
                                              </div>
                                            )}
                                          </td>
                                        </tr>
                                      );
                                    })}
                                    </tbody>
                                  </table>
                                </div>
                              )}
                            </div>
                          )}

                          {/* 1.3.D: Unresolved Questions and Document Requests matching user screenshots */}
                          {section.id === "1.3.D" && (
                            <div className="space-y-3">
                              {/* Top right: + Add Item to List button */}
                              <div className="flex items-center justify-end">
                                <button
                                  type="button"
                                  onClick={handleOpenAddUnresolved}
                                  className="flex cursor-pointer items-center gap-1.5 rounded-[5px] border border-slate-300 bg-white px-3 py-1.5 text-[12px] font-medium text-slate-700 shadow-2xs hover:bg-slate-50 transition"
                                >
                                  <Plus size={13} strokeWidth={2.4} />
                                  <span>Add Item to List</span>
                                </button>
                              </div>

                              {/* Either show Add/Edit Card Panel OR Table */}
                              {isUnresolvedEditorOpen ? (
                                /* Add / Edit Form Panel matching screenshot 2 */
                                <div className="overflow-hidden rounded-[6px] border border-[#7eaed5] bg-[#dbebf9] shadow-xs">
                                  {/* Top header bar */}
                                  <div className="flex items-center justify-between border-b border-[#7eaed5] bg-[#c7e0f6] px-3.5 py-1.5 text-[11px] font-semibold text-[#103565]">
                                    <span>
                                      1.3.D. Unresolved Questions and Document Requests - Add / Edit Item
                                    </span>
                                    <button
                                      type="button"
                                      onClick={() => setIsUnresolvedEditorOpen(false)}
                                      className="cursor-pointer rounded p-0.5 text-slate-600 hover:bg-[#b0d2ef] hover:text-slate-900 transition"
                                    >
                                      <X size={15} strokeWidth={2.4} />
                                    </button>
                                  </div>

                                  {/* Form inputs stacked vertically */}
                                  <div className="p-5 sm:p-6 space-y-3.5">
                                    <div className="w-full max-w-[380px] sm:max-w-[420px]">
                                      <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                                        Information gap / contradiction
                                      </label>
                                      <input
                                        type="text"
                                        value={editingUnresolved.informationGapContradiction}
                                        onChange={(e) =>
                                          setEditingUnresolved({
                                            ...editingUnresolved,
                                            informationGapContradiction: e.target.value,
                                          })
                                        }
                                        className="h-8 w-full rounded-[5px] border border-[#7eaed5] bg-white px-3 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                                      />
                                    </div>

                                    <div className="w-full max-w-[380px] sm:max-w-[420px]">
                                      <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                                        Verification / document needed
                                      </label>
                                      <input
                                        type="text"
                                        value={editingUnresolved.verificationDocumentNeeded}
                                        onChange={(e) =>
                                          setEditingUnresolved({
                                            ...editingUnresolved,
                                            verificationDocumentNeeded: e.target.value,
                                          })
                                        }
                                        className="h-8 w-full rounded-[5px] border border-[#7eaed5] bg-white px-3 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                                      />
                                    </div>

                                    <div className="w-full max-w-[380px] sm:max-w-[420px]">
                                      <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                                        Owner / due
                                      </label>
                                      <input
                                        type="text"
                                        value={editingUnresolved.ownerDue}
                                        onChange={(e) =>
                                          setEditingUnresolved({
                                            ...editingUnresolved,
                                            ownerDue: e.target.value,
                                          })
                                        }
                                        className="h-8 w-full rounded-[5px] border border-[#7eaed5] bg-white px-3 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                                      />
                                    </div>

                                    <div className="w-full max-w-[380px] sm:max-w-[420px]">
                                      <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                                        DRL / task link
                                      </label>
                                      <input
                                        type="text"
                                        value={editingUnresolved.drlTaskLink}
                                        onChange={(e) =>
                                          setEditingUnresolved({
                                            ...editingUnresolved,
                                            drlTaskLink: e.target.value,
                                          })
                                        }
                                        className="h-8 w-full rounded-[5px] border border-[#7eaed5] bg-white px-3 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                                      />
                                    </div>

                                    {/* Action buttons inside panel */}
                                    <div className="flex items-center justify-end gap-2.5 pt-4">
                                      <button
                                        type="button"
                                        onClick={() => setIsUnresolvedEditorOpen(false)}
                                        className="flex cursor-pointer items-center gap-1.5 rounded-[5px] border border-slate-300 bg-white px-3.5 py-1.5 text-xs font-medium text-slate-700 shadow-2xs hover:bg-slate-50 transition"
                                      >
                                        <ArrowLeft size={13} />
                                        <span>Back to 1.3 workspace</span>
                                      </button>
                                      <button
                                        type="button"
                                        onClick={handleSaveUnresolvedToTable}
                                        className="cursor-pointer rounded-[5px] border border-slate-300 bg-white px-4 py-1.5 text-xs font-semibold text-slate-700 shadow-2xs hover:bg-slate-50 transition"
                                      >
                                        Update to Table
                                      </button>
                                    </div>
                                  </div>
                                </div>
                              ) : (
                                /* Table matching screenshot 1 */
                                <div className="overflow-x-auto rounded-[4px] border border-[#7eaed5] bg-white">
                                  <table className="w-full border-collapse text-left text-[11px]">
                                    <thead>
                                      <tr className="border-b border-[#7eaed5] bg-[#dbebf9] text-[#1a3d6d]">
                                        <th className="border-r border-[#7eaed5] px-3 py-1.5 font-semibold text-center w-[5%]">
                                          No.
                                        </th>
                                        <th className="border-r border-[#7eaed5] px-2.5 py-1.5 font-semibold w-[28%]">
                                          Information gap / contradiction
                                        </th>
                                        <th className="border-r border-[#7eaed5] px-2.5 py-1.5 font-semibold w-[25%]">
                                          Verification / document needed
                                        </th>
                                        <th className="border-r border-[#7eaed5] px-2.5 py-1.5 font-semibold w-[18%]">
                                          Owner / due
                                        </th>
                                        <th className="border-r border-[#7eaed5] px-2.5 py-1.5 font-semibold w-[20%]">
                                          DRL / task link
                                        </th>
                                        <th className="px-1.5 py-1.5 text-center font-semibold w-[4%]">
                                          {/* Options */}
                                        </th>
                                      </tr>
                                    </thead>
                                    <tbody>
                                      {formState.unresolvedQuestionsList.map((row, idx) => {
                                      const isNearBottom = idx >= Math.max(1, formState.unresolvedQuestionsList.length - 2);
                                      return (
                                        <tr
                                          key={row.id}
                                          className="border-b border-[#c7e0f6] last:border-b-0 hover:bg-[#f3f8fd] transition-colors h-[26px]"
                                        >
                                          <td className="border-r border-[#c7e0f6] px-3 py-1 text-center font-medium text-slate-600">
                                            {row.no}
                                          </td>
                                          <td className="border-r border-[#c7e0f6] px-2.5 py-1 text-slate-800 truncate">
                                            {row.informationGapContradiction || "\u00A0"}
                                          </td>
                                          <td className="border-r border-[#c7e0f6] px-2.5 py-1 text-slate-800 truncate">
                                            {row.verificationDocumentNeeded}
                                          </td>
                                          <td className="border-r border-[#c7e0f6] px-2.5 py-1 text-slate-800 truncate">
                                            {row.ownerDue}
                                          </td>
                                          <td className="border-r border-[#c7e0f6] px-2.5 py-1 text-slate-800 truncate">
                                            {row.drlTaskLink}
                                          </td>
                                          <td className="relative px-1 py-1 text-center">
                                            <button
                                              type="button"
                                              onClick={() =>
                                                setActiveUnresolvedMenu(
                                                  activeUnresolvedMenu === row.id
                                                    ? null
                                                    : row.id
                                                )
                                              }
                                              aria-label="Row actions"
                                              className="cursor-pointer text-slate-500 hover:text-slate-800 p-0.5 rounded transition"
                                            >
                                              <MoreHorizontal size={14} />
                                            </button>

                                            {activeUnresolvedMenu === row.id && (
                                              <div className={`unresolved-action-menu absolute right-2 z-50 w-28 rounded-md border border-slate-200 bg-white py-1 shadow-md text-[11px] text-left ${isNearBottom ? "bottom-full mb-1" : "top-full mt-1"}`}>
                                                <button
                                                  type="button"
                                                  onClick={() => handleOpenEditUnresolved(row)}
                                                  className="flex w-full items-center gap-1.5 px-2.5 py-1 hover:bg-[#d8ecff] text-slate-700 transition"
                                                >
                                                  <Edit2 size={11} /> Edit Row
                                                </button>
                                                <button
                                                  type="button"
                                                  onClick={() => handleClearUnresolved(row.id)}
                                                  className="flex w-full items-center gap-1.5 px-2.5 py-1 hover:bg-slate-50 text-slate-600 transition"
                                                >
                                                  Clear
                                                </button>
                                                {formState.unresolvedQuestionsList.length > 1 && (
                                                  <button
                                                    type="button"
                                                    onClick={() =>
                                                      handleDeleteUnresolved(row.id)
                                                    }
                                                    className="flex w-full items-center gap-1.5 px-2.5 py-1 hover:bg-rose-50 text-rose-600 transition"
                                                  >
                                                    <Trash2 size={11} /> Delete
                                                  </button>
                                                )}
                                              </div>
                                            )}
                                          </td>
                                        </tr>
                                      );
                                    })}
                                    </tbody>
                                  </table>
                                </div>
                              )}
                            </div>
                          )}

                          {/* 1.3.E: Process Flowchart matching user screenshot */}
                          {section.id === "1.3.E" && (
                            <div className="flex justify-center py-5 sm:py-7">
                              <div className="w-full max-w-[540px]">
                                <label className="mb-1.5 block text-[12px] font-semibold text-[#1a3d6d]">
                                  Process Flowchart
                                </label>
                                <input
                                  type="file"
                                  ref={fileInputRef}
                                  onChange={handleFileUpload}
                                  className="hidden"
                                  accept=".pdf,.doc,.docx"
                                />
                                <div className="flex items-center gap-2.5">
                                  <div
                                    onClick={() => fileInputRef.current?.click()}
                                    onDragOver={(e) => {
                                      e.preventDefault();
                                      setIsDraggingFlowchart(true);
                                    }}
                                    onDragLeave={() => setIsDraggingFlowchart(false)}
                                    onDrop={(e) => {
                                      e.preventDefault();
                                      setIsDraggingFlowchart(false);
                                      const file = e.dataTransfer.files?.[0];
                                      if (file) {
                                        uploadFile(file);
                                      }
                                    }}
                                    className={`flex h-8 flex-1 cursor-pointer items-center justify-between rounded-[5px] border border-dashed px-3.5 transition-colors ${
                                      isDraggingFlowchart
                                        ? "border-[#1877f2] bg-[#dbebf9]"
                                        : "border-[#7eaed5] bg-[#edf5fc] hover:bg-[#e4eff9]"
                                    }`}
                                  >
                                    <span className="truncate text-[12px] text-[#4d7ea8]">
                                      {isUploading ? (
                                        <span className="flex items-center gap-1.5 text-[#1877f2]">
                                          <Loader2 size={13} className="animate-spin" /> Uploading flowchart...
                                        </span>
                                      ) : formState.attachedFlowchartName ? (
                                        <span className="font-semibold text-[#103565]">
                                          {formState.attachedFlowchartName}
                                        </span>
                                      ) : (
                                        "Click to upload or drag and drop PDF, DOC, DOCX (Max 10 MB)"
                                      )}
                                    </span>
                                    {formState.attachedFlowchartName && !isUploading && (
                                      <button
                                        type="button"
                                        onClick={(e) => {
                                          e.stopPropagation();
                                          setFormState((prev) => ({
                                            ...prev,
                                            attachedFlowchartName: "",
                                            flowchartDocumentVersionId: null,
                                          }));
                                        }}
                                        className="ml-2 text-slate-400 hover:text-rose-600 transition"
                                        title="Remove file"
                                      >
                                        <X size={13} />
                                      </button>
                                    )}
                                  </div>
                                  <button
                                    type="button"
                                    onClick={() => fileInputRef.current?.click()}
                                    disabled={isUploading}
                                    className="h-8 shrink-0 cursor-pointer rounded-[5px] border border-slate-300 bg-white px-3.5 text-[12px] font-medium text-slate-700 shadow-2xs hover:bg-slate-50 transition disabled:opacity-50"
                                  >
                                    {isUploading ? "Uploading..." : "Upload File"}
                                  </button>
                                </div>
                              </div>
                            </div>
                          )}

                          {/* 1.3.F: Risk and Control Matrix matching user screenshots */}
                          {section.id === "1.3.F" && (
                            <div className="space-y-3">
                              {/* Top right: + Add Item to List button */}
                              <div className="flex items-center justify-end">
                                <button
                                  type="button"
                                  onClick={handleOpenAddRcm}
                                  className="flex cursor-pointer items-center gap-1.5 rounded-[5px] border border-slate-300 bg-white px-3 py-1.5 text-[12px] font-medium text-slate-700 shadow-2xs hover:bg-slate-50 transition"
                                >
                                  <Plus size={13} strokeWidth={2.4} />
                                  <span>Add Item to List</span>
                                </button>
                              </div>

                              {/* Either show Add/Edit Card Panel OR Table */}
                              {isRcmEditorOpen ? (
                                /* Add / Edit Form Panel matching screenshot 2 */
                                <div className="overflow-hidden rounded-[6px] border border-[#7eaed5] bg-[#dbebf9] shadow-xs">
                                  {/* Top header bar */}
                                  <div className="flex items-center justify-between border-b border-[#7eaed5] bg-[#c7e0f6] px-3.5 py-1.5 text-[11px] font-semibold text-[#103565]">
                                    <span>
                                      1.3.F. Risk and Control Matrix - Add/Edit Item
                                    </span>
                                    <button
                                      type="button"
                                      onClick={() => setIsRcmEditorOpen(false)}
                                      aria-label="Close"
                                      className="cursor-pointer rounded p-0.5 text-slate-600 hover:bg-[#b0d2ef] hover:text-slate-900 transition"
                                    >
                                      <X size={15} strokeWidth={2.4} />
                                    </button>
                                  </div>

                                  {/* Form inputs in 2 columns matching screenshot 2 */}
                                  <div className="p-5 sm:p-6 space-y-4">
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-x-8 lg:gap-x-12 gap-y-3.5">
                                      {/* Left Column (7 fields) */}
                                      <div className="space-y-3.5">
                                        <div>
                                          <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                                            Risk Description
                                          </label>
                                          <input
                                            type="text"
                                            value={editingRcm.riskDescription}
                                            onChange={(e) =>
                                              setEditingRcm({
                                                ...editingRcm,
                                                riskDescription: e.target.value,
                                              })
                                            }
                                            className="h-8 w-full rounded-[5px] border border-[#7eaed5] bg-white px-3 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                                          />
                                        </div>

                                        <div>
                                          <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                                            Risk Category
                                          </label>
                                          <input
                                            type="text"
                                            value={editingRcm.riskCategory}
                                            onChange={(e) =>
                                              setEditingRcm({
                                                ...editingRcm,
                                                riskCategory: e.target.value,
                                              })
                                            }
                                            className="h-8 w-full rounded-[5px] border border-[#7eaed5] bg-white px-3 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                                          />
                                        </div>

                                        <div>
                                          <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                                            Likelihood
                                          </label>
                                          <div className="relative">
                                            <select
                                              value={editingRcm.likelihood}
                                              onChange={(e) =>
                                                setEditingRcm({
                                                  ...editingRcm,
                                                  likelihood: e.target.value,
                                                })
                                              }
                                              className="h-8 w-full appearance-none rounded-[5px] border border-[#7eaed5] bg-white px-3 pr-8 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                                            >
                                              <option value=""></option>
                                              <option value="Low">Low</option>
                                              <option value="Medium">Medium</option>
                                              <option value="High">High</option>
                                            </select>
                                            <ChevronDown
                                              size={14}
                                              className="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-500"
                                            />
                                          </div>
                                        </div>

                                        <div>
                                          <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                                            Justification (Likelihood)
                                          </label>
                                          <input
                                            type="text"
                                            value={editingRcm.justificationLikelihood}
                                            onChange={(e) =>
                                              setEditingRcm({
                                                ...editingRcm,
                                                justificationLikelihood: e.target.value,
                                              })
                                            }
                                            className="h-8 w-full rounded-[5px] border border-[#7eaed5] bg-white px-3 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                                          />
                                        </div>

                                        <div>
                                          <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                                            Impact
                                          </label>
                                          <div className="relative">
                                            <select
                                              value={editingRcm.impact}
                                              onChange={(e) =>
                                                setEditingRcm({
                                                  ...editingRcm,
                                                  impact: e.target.value,
                                                })
                                              }
                                              className="h-8 w-full appearance-none rounded-[5px] border border-[#7eaed5] bg-white px-3 pr-8 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                                            >
                                              <option value=""></option>
                                              <option value="Low">Low</option>
                                              <option value="Medium">Medium</option>
                                              <option value="High">High</option>
                                            </select>
                                            <ChevronDown
                                              size={14}
                                              className="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-500"
                                            />
                                          </div>
                                        </div>

                                        <div>
                                          <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                                            Justification (Impact)
                                          </label>
                                          <input
                                            type="text"
                                            value={editingRcm.justificationImpact}
                                            onChange={(e) =>
                                              setEditingRcm({
                                                ...editingRcm,
                                                justificationImpact: e.target.value,
                                              })
                                            }
                                            className="h-8 w-full rounded-[5px] border border-[#7eaed5] bg-white px-3 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                                          />
                                        </div>

                                        <div>
                                          <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                                            Risk Rating
                                          </label>
                                          <input
                                            type="text"
                                            value={editingRcm.riskRating}
                                            onChange={(e) =>
                                              setEditingRcm({
                                                ...editingRcm,
                                                riskRating: e.target.value,
                                              })
                                            }
                                            className="h-8 w-full rounded-[5px] border border-[#7eaed5] bg-white px-3 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                                          />
                                        </div>
                                      </div>

                                      {/* Right Column (6 fields) */}
                                      <div className="space-y-3.5">
                                        <div>
                                          <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                                            Control Activity in Place
                                          </label>
                                          <input
                                            type="text"
                                            value={editingRcm.controlActivityInPlace}
                                            onChange={(e) =>
                                              setEditingRcm({
                                                ...editingRcm,
                                                controlActivityInPlace: e.target.value,
                                              })
                                            }
                                            className="h-8 w-full rounded-[5px] border border-[#7eaed5] bg-white px-3 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                                          />
                                        </div>

                                        <div>
                                          <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                                            Control Type
                                          </label>
                                          <div className="relative">
                                            <select
                                              value={editingRcm.controlType}
                                              onChange={(e) =>
                                                setEditingRcm({
                                                  ...editingRcm,
                                                  controlType: e.target.value,
                                                })
                                              }
                                              className="h-8 w-full appearance-none rounded-[5px] border border-[#7eaed5] bg-white px-3 pr-8 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                                            >
                                              <option value=""></option>
                                              <option value="Preventive">Preventive</option>
                                              <option value="Detective">Detective</option>
                                              <option value="Corrective">Corrective</option>
                                            </select>
                                            <ChevronDown
                                              size={14}
                                              className="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-500"
                                            />
                                          </div>
                                        </div>

                                        <div>
                                          <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                                            Control Owner
                                          </label>
                                          <input
                                            type="text"
                                            value={editingRcm.controlOwner}
                                            onChange={(e) =>
                                              setEditingRcm({
                                                ...editingRcm,
                                                controlOwner: e.target.value,
                                              })
                                            }
                                            className="h-8 w-full rounded-[5px] border border-[#7eaed5] bg-white px-3 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                                          />
                                        </div>

                                        <div>
                                          <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                                            Control Frequency
                                          </label>
                                          <input
                                            type="text"
                                            value={editingRcm.controlFrequency}
                                            onChange={(e) =>
                                              setEditingRcm({
                                                ...editingRcm,
                                                controlFrequency: e.target.value,
                                              })
                                            }
                                            className="h-8 w-full rounded-[5px] border border-[#7eaed5] bg-white px-3 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                                          />
                                        </div>

                                        <div>
                                          <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                                            Operating Effectiveness Status
                                          </label>
                                          <input
                                            type="text"
                                            value={editingRcm.operatingEffectivenessStatus}
                                            onChange={(e) =>
                                              setEditingRcm({
                                                ...editingRcm,
                                                operatingEffectivenessStatus: e.target.value,
                                              })
                                            }
                                            className="h-8 w-full rounded-[5px] border border-[#7eaed5] bg-white px-3 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                                          />
                                        </div>

                                        <div>
                                          <label className="mb-1 block text-[12px] font-semibold text-[#1a3d6d]">
                                            Residual Risk Rating
                                          </label>
                                          <input
                                            type="text"
                                            value={editingRcm.residualRiskRating}
                                            onChange={(e) =>
                                              setEditingRcm({
                                                ...editingRcm,
                                                residualRiskRating: e.target.value,
                                              })
                                            }
                                            className="h-8 w-full rounded-[5px] border border-[#7eaed5] bg-white px-3 text-[12px] text-slate-800 shadow-2xs outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2]"
                                          />
                                        </div>
                                      </div>
                                    </div>

                                    {/* Action buttons inside panel */}
                                    <div className="flex items-center justify-end gap-2.5 pt-3">
                                      <button
                                        type="button"
                                        onClick={() => setIsRcmEditorOpen(false)}
                                        className="flex cursor-pointer items-center gap-1.5 rounded-[5px] border border-slate-300 bg-white px-3.5 py-1.5 text-xs font-medium text-slate-700 shadow-2xs hover:bg-slate-50 transition"
                                      >
                                        <ArrowLeft size={13} />
                                        <span>Back to 1.3 workspace</span>
                                      </button>
                                      <button
                                        type="button"
                                        onClick={handleSaveRcmToTable}
                                        className="cursor-pointer rounded-[5px] border border-slate-300 bg-white px-4 py-1.5 text-xs font-semibold text-slate-700 shadow-2xs hover:bg-slate-50 transition"
                                      >
                                        Update to Table
                                      </button>
                                    </div>
                                  </div>
                                </div>
                              ) : (
                                /* Table matching screenshot 1 */
                                <div className="overflow-x-auto rounded-[4px] border border-[#7eaed5] bg-white">
                                  <table className="w-full min-w-[1250px] border-collapse text-left text-[11px]">
                                    <thead>
                                      <tr className="border-b border-[#7eaed5] bg-[#dbebf9] text-[#1a3d6d]">
                                        <th className="border-r border-[#7eaed5] px-3 py-1.5 font-semibold text-center w-[3%]">
                                          No.
                                        </th>
                                        <th className="border-r border-[#7eaed5] px-2.5 py-1.5 font-semibold min-w-[130px]">
                                          Risk Description
                                        </th>
                                        <th className="border-r border-[#7eaed5] px-2.5 py-1.5 font-semibold min-w-[100px]">
                                          Risk Category
                                        </th>
                                        <th className="border-r border-[#7eaed5] px-2.5 py-1.5 font-semibold min-w-[75px]">
                                          Likelihood
                                        </th>
                                        <th className="border-r border-[#7eaed5] px-2.5 py-1.5 font-semibold min-w-[125px]">
                                          Justification (Likelihood)
                                        </th>
                                        <th className="border-r border-[#7eaed5] px-2.5 py-1.5 font-semibold min-w-[65px]">
                                          Impact
                                        </th>
                                        <th className="border-r border-[#7eaed5] px-2.5 py-1.5 font-semibold min-w-[120px]">
                                          Justification (Impact)
                                        </th>
                                        <th className="border-r border-[#7eaed5] px-2.5 py-1.5 font-semibold min-w-[80px]">
                                          Risk Rating
                                        </th>
                                        <th className="border-r border-[#7eaed5] px-2.5 py-1.5 font-semibold min-w-[130px]">
                                          Control Activity in Place
                                        </th>
                                        <th className="border-r border-[#7eaed5] px-2.5 py-1.5 font-semibold min-w-[85px]">
                                          Control Type
                                        </th>
                                        <th className="border-r border-[#7eaed5] px-2.5 py-1.5 font-semibold min-w-[95px]">
                                          Control Owner
                                        </th>
                                        <th className="border-r border-[#7eaed5] px-2.5 py-1.5 font-semibold min-w-[90px] truncate">
                                          Control Freq...
                                        </th>
                                        <th className="border-r border-[#7eaed5] px-2.5 py-1.5 font-semibold min-w-[130px]">
                                          Operating Effectiveness Status
                                        </th>
                                        <th className="border-r border-[#7eaed5] px-2.5 py-1.5 font-semibold min-w-[95px]">
                                          Residual Risk Rating
                                        </th>
                                        <th className="px-1.5 py-1.5 text-center font-semibold w-[3%]">
                                          {/* Options */}
                                        </th>
                                      </tr>
                                    </thead>
                                    <tbody>
                                      {formState.riskControlMatrix.map((row, idx) => {
                                      const isNearBottom = idx >= Math.max(1, formState.riskControlMatrix.length - 2);
                                      return (
                                        <tr
                                          key={row.id}
                                          className="border-b border-[#c7e0f6] last:border-b-0 hover:bg-[#f3f8fd] transition-colors h-[26px]"
                                        >
                                          <td className="border-r border-[#c7e0f6] px-3 py-1 text-center font-medium text-slate-600">
                                            {row.no}
                                          </td>
                                          <td className="border-r border-[#c7e0f6] px-2.5 py-1 text-slate-800 truncate">
                                            {row.riskDescription || "\u00A0"}
                                          </td>
                                          <td className="border-r border-[#c7e0f6] px-2.5 py-1 text-slate-800 truncate">
                                            {row.riskCategory}
                                          </td>
                                          <td className="border-r border-[#c7e0f6] px-2.5 py-1 text-slate-800 truncate">
                                            {row.likelihood}
                                          </td>
                                          <td className="border-r border-[#c7e0f6] px-2.5 py-1 text-slate-800 truncate">
                                            {row.justificationLikelihood}
                                          </td>
                                          <td className="border-r border-[#c7e0f6] px-2.5 py-1 text-slate-800 truncate">
                                            {row.impact}
                                          </td>
                                          <td className="border-r border-[#c7e0f6] px-2.5 py-1 text-slate-800 truncate">
                                            {row.justificationImpact}
                                          </td>
                                          <td className="border-r border-[#c7e0f6] px-2.5 py-1 text-slate-800 truncate">
                                            {row.riskRating}
                                          </td>
                                          <td className="border-r border-[#c7e0f6] px-2.5 py-1 text-slate-800 truncate">
                                            {row.controlActivityInPlace}
                                          </td>
                                          <td className="border-r border-[#c7e0f6] px-2.5 py-1 text-slate-800 truncate">
                                            {row.controlType}
                                          </td>
                                          <td className="border-r border-[#c7e0f6] px-2.5 py-1 text-slate-800 truncate">
                                            {row.controlOwner}
                                          </td>
                                          <td className="border-r border-[#c7e0f6] px-2.5 py-1 text-slate-800 truncate">
                                            {row.controlFrequency}
                                          </td>
                                          <td className="border-r border-[#c7e0f6] px-2.5 py-1 text-slate-800 truncate">
                                            {row.operatingEffectivenessStatus}
                                          </td>
                                          <td className="border-r border-[#c7e0f6] px-2.5 py-1 text-slate-800 truncate">
                                            {row.residualRiskRating}
                                          </td>
                                          <td className="relative px-1 py-1 text-center">
                                            <button
                                              type="button"
                                              onClick={() =>
                                                setActiveRcmMenu(
                                                  activeRcmMenu === row.id
                                                    ? null
                                                    : row.id
                                                )
                                              }
                                              aria-label="Row actions"
                                              className="cursor-pointer text-slate-500 hover:text-slate-800 p-0.5 rounded transition"
                                            >
                                              <MoreHorizontal size={14} />
                                            </button>

                                            {activeRcmMenu === row.id && (
                                              <div className={`rcm-action-menu absolute right-2 z-50 w-28 rounded-md border border-slate-200 bg-white py-1 shadow-md text-[11px] text-left ${isNearBottom ? "bottom-full mb-1" : "top-full mt-1"}`}>
                                                <button
                                                  type="button"
                                                  onClick={() =>
                                                    handleOpenEditRcm(row)
                                                  }
                                                  className="flex w-full items-center gap-1.5 px-2.5 py-1 hover:bg-[#d8ecff] text-slate-700 transition"
                                                >
                                                  <Edit2 size={11} /> Edit Row
                                                </button>
                                                <button
                                                  type="button"
                                                  onClick={() =>
                                                    handleClearRcm(row.id)
                                                  }
                                                  className="flex w-full items-center gap-1.5 px-2.5 py-1 hover:bg-slate-50 text-slate-600 transition"
                                                >
                                                  Clear
                                                </button>
                                                {formState.riskControlMatrix
                                                  .length > 1 && (
                                                  <button
                                                    type="button"
                                                    onClick={() =>
                                                      handleDeleteRcm(row.id)
                                                    }
                                                    className="flex w-full items-center gap-1.5 px-2.5 py-1 hover:bg-rose-50 text-rose-600 transition"
                                                  >
                                                    <Trash2 size={11} /> Delete
                                                  </button>
                                                )}
                                              </div>
                                            )}
                                          </td>
                                        </tr>
                                      );
                                    })}
                                    </tbody>
                                  </table>
                                </div>
                              )}
                            </div>
                          )}
                        </div>
                      </div>
                    </div>
                  </div>
                );
              })}
            </div>

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
                          description: "Generating Process Understanding report...",
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
                          description: "1.3 Understanding of Program/Process marked as complete.",
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
