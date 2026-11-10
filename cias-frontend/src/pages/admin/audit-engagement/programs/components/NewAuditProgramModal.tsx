import { useState } from "react";
import { createPortal } from "react-dom";
import { Plus, X } from "lucide-react";
import { Button } from "../../../../../components/ui/button";
import type {
  AuditMethod,
  AuditProgram,
  DetailedProcedureItem,
} from "../programs-types";

type NewAuditProgramModalProps = {
  isOpen: boolean;
  onClose: () => void;
  engagementNumber: string;
  engagementTitle: string;
  office: string;
  defaultAuditType: string;
  availableAuditAreas: Array<{ id: string; name: string }>;
  availableAssignees: string[];
  existingProgramsCount: number;
  onSaveProgram: (program: AuditProgram) => void;
};

export function NewAuditProgramModal({
  isOpen,
  onClose,
  engagementNumber,
  engagementTitle,
  office,
  defaultAuditType,
  availableAuditAreas,
  availableAssignees,
  existingProgramsCount,
  onSaveProgram,
}: NewAuditProgramModalProps) {
  if (!isOpen) return null;

  const defaultArea =
    availableAuditAreas[0]?.name ||
    engagementTitle ||
    "Core Operational Controls";

  const [auditArea, setAuditArea] = useState(defaultArea);
  const [programTitle, setProgramTitle] = useState("");
  const [description, setDescription] = useState("");
  const [auditType, setAuditType] = useState(defaultAuditType || "Operations Audit");
  const [auditPeriod, setAuditPeriod] = useState("FY 2025 – Q1 2026");
  const [objectivesTrace, setObjectivesTrace] = useState(
    `AEP-${engagementNumber.replace("AEMS-", "")} Strategic Fieldwork Objectives`,
  );
  const [criteriaInput, setCriteriaInput] = useState(
    "LGU Operational Guidelines, COA Regulations, Statutory Mandate",
  );
  const [rmiInput, setRmiInput] = useState("RMI-2026-001, RMI-2026-002");

  // Initial Procedure State
  const [procTitle, setProcTitle] = useState("");
  const [procPurpose, setProcPurpose] = useState("");
  const [procMethod, setProcMethod] = useState<AuditMethod>("Inspection");
  const [procEvidence, setProcEvidence] = useState(
    "Disbursement vouchers, inspection reports, official registries",
  );
  const [procAssignee, setProcAssignee] = useState(
    availableAssignees[0] || "Lead Auditor",
  );
  const [procManDays, setProcManDays] = useState<number>(5);
  const [procDueDate, setProcDueDate] = useState(() => {
    const d = new Date();
    d.setDate(d.getDate() + 14);
    return d.toISOString().split("T")[0];
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!programTitle.trim() || !auditArea) return;

    const engSuffix = (engagementNumber || "").replace(/^AEMS-/, "") || "2026-020";
    const progNum = String(existingProgramsCount + 1).padStart(2, "0");
    const areaSuffix = auditArea.split(" ")[0].toUpperCase().replace(/[^A-Z]/g, "") || "OPS";
    const programId = `PROG-${engSuffix}-${progNum}-${areaSuffix}`;

    const procNum = "01";
    const procId = `PROC-${engSuffix}-${progNum}-${procNum}`;
    const wpRef = `WP-${engSuffix}-${progNum}-${areaSuffix}-${procNum}`;

    const parsedCriteria = criteriaInput
      .split(",")
      .map((c) => c.trim())
      .filter(Boolean);
    if (programTitle.trim().length < 5 || /^(asd|asdad|asdasd|test|sample)+$/i.test(programTitle.trim())) {
      alert("Please provide a genuine, descriptive Audit Program Title rather than placeholder test characters.");
      return;
    }

    if (procTitle.trim().length < 5 || /^(asd|asdad|asdasd|test|sample)+$/i.test(procTitle.trim())) {
      alert("Please provide a genuine, descriptive Procedure Title rather than placeholder test characters.");
      return;
    }

    const parsedRmis = rmiInput
      .split(",")
      .map((r) => r.trim())
      .filter(Boolean);

    const initialProcedure: DetailedProcedureItem = {
      id: procId,
      riskMatrixItemId: parsedRmis[0] || "RMI-2026-001",
      pfdStageRef: "S1: Fieldwork Verification & Sample Testing",
      procedureTitle:
        procTitle.trim() ||
        `${programTitle.trim()} — Substantive Compliance Testing`,
      purpose:
        procPurpose.trim() ||
        `Evaluate compliance and internal controls for ${auditArea}.`,
      auditMethod: procMethod,
      expectedEvidence: procEvidence.trim(),
      personResponsible: procAssignee,
      plannedManDays: Number(procManDays) || 1,
      workingPaperRef: wpRef,
      dueDate: procDueDate,
      status: "Pending Execution",
    };

    const newProgram: AuditProgram = {
      id: programId,
      auditArea,
      programTitle: programTitle.trim(),
      description:
        description.trim() ||
        `Structured audit program evaluating internal controls, regulatory compliance, and substantive records for ${auditArea} in ${office}.`,
      auditType,
      auditPeriod,
      linkedRmiIds: parsedRmis.length > 0 ? parsedRmis : ["RMI-2026-001"],
      objectivesTrace: objectivesTrace.trim(),
      criteriaFrameworkRefs:
        parsedCriteria.length > 0 ? parsedCriteria : ["COA Circulars", "LGU Mandates"],
      preparedBy: availableAssignees[0] || "Juan Dela Cruz (Lead Auditor)",
      reviewedBy: "Atty. Fernando M. Gomez, CPA (Supervising Auditor)",
      approvedBy: "Director Manuel C. Tan, CPA, CFE (Department Head)",
      effectiveDate: new Date().toISOString().split("T")[0],
      version: "v1.0",
      status: "Draft",
      procedures: [initialProcedure],
    };

    onSaveProgram(newProgram);
    onClose();
  };

  return createPortal(
    <div
      aria-modal="true"
      role="dialog"
      className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-xs overflow-y-auto"
      onClick={onClose}
    >
      <div
        className="relative w-full max-w-2xl rounded-xl border border-slate-200 bg-white shadow-2xl max-h-[90vh] flex flex-col animate-[page-enter_200ms_ease-out]"
        onClick={(e) => e.stopPropagation()}
      >
        {/* Header */}
        <div className="flex items-center justify-between border-b border-slate-100 px-5 py-3.5">
          <div>
            <div className="flex items-center gap-1.5">
              <span className="rounded bg-blue-50 px-1.5 py-0.2 font-mono text-[10px] font-bold text-blue-700">
                SCR-223
              </span>
              <h2 className="text-sm font-bold text-slate-900">
                Formulate New Audit Program (§7, Module Rule 35)
              </h2>
            </div>
            <p className="text-[11px] text-slate-500 mt-0.5">
              Create an audit program mapped to an applicable Audit Area for{" "}
              <strong>{engagementNumber}</strong> ({office}).
            </p>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 cursor-pointer"
          >
            <X size={16} />
          </button>
        </div>

        {/* Form Body */}
        <form
          onSubmit={handleSubmit}
          className="flex-1 overflow-y-auto p-5 space-y-4 text-xs"
        >
          {/* Section 1: Program Identification */}
          <div className="space-y-3">
            <h3 className="text-xs font-bold text-slate-800 border-b border-slate-100 pb-1.5 flex items-center justify-between">
              <span>1. Program Identity & Audit Area (OD-008 Mapping)</span>
              <span className="text-[10px] text-slate-400 font-normal">MDS-200 §7</span>
            </h3>

            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <div className="sm:col-span-2">
                <label className="block">
                  <span className="text-[11px] font-semibold text-slate-700 mb-1.5 block">
                    Applicable Audit Area <span className="text-rose-500">*</span>
                  </span>
                  <select
                    value={auditArea}
                    onChange={(e) => setAuditArea(e.target.value)}
                    required
                    className="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition"
                  >
                    {availableAuditAreas.map((area) => (
                      <option key={area.id} value={area.name}>
                        {area.name}
                      </option>
                    ))}
                  </select>
                </label>
              </div>

              <div className="sm:col-span-2">
                <label className="block">
                  <span className="text-[11px] font-semibold text-slate-700 mb-1.5 block">
                    Audit Program Title <span className="text-rose-500">*</span>
                  </span>
                  <input
                    type="text"
                    required
                    placeholder="e.g. Beneficiary Validation & Seed Subsidy Distribution Testing Program"
                    value={programTitle}
                    onChange={(e) => setProgramTitle(e.target.value)}
                    className="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs text-slate-900 placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition"
                  />
                </label>
              </div>

              <div>
                <label className="block">
                  <span className="text-[11px] font-semibold text-slate-700 mb-1.5 block">
                    Type of Audit
                  </span>
                  <select
                    value={auditType}
                    onChange={(e) => setAuditType(e.target.value)}
                    className="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition"
                  >
                    <option value="Operations Audit">Operations Audit</option>
                    <option value="Compliance Audit">Compliance Audit</option>
                    <option value="Financial Audit">Financial Audit</option>
                    <option value="Asset Audit">Asset Audit</option>
                    <option value="Information Systems Audit">
                      Information Systems Audit
                    </option>
                    <option value="Value for Money / Performance Audit">
                      Value for Money / Performance Audit
                    </option>
                  </select>
                </label>
              </div>

              <div>
                <label className="block">
                  <span className="text-[11px] font-semibold text-slate-700 mb-1.5 block">
                    Audit Period Covered
                  </span>
                  <input
                    type="text"
                    value={auditPeriod}
                    onChange={(e) => setAuditPeriod(e.target.value)}
                    placeholder="e.g. FY 2025 – Q1 2026"
                    className="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs text-slate-900 placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition"
                  />
                </label>
              </div>

              <div className="sm:col-span-2">
                <label className="block">
                  <span className="text-[11px] font-semibold text-slate-700 mb-1.5 block">
                    Program Scope & Testing Description
                  </span>
                  <textarea
                    rows={2}
                    placeholder="Describe testing objectives, sampling methodologies, and regulatory review scope..."
                    value={description}
                    onChange={(e) => setDescription(e.target.value)}
                    className="w-full rounded-lg border border-slate-200 bg-white p-3 text-xs text-slate-900 placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition"
                  />
                </label>
              </div>
            </div>
          </div>

          {/* Section 2: Traceability & Criteria */}
          <div className="space-y-3">
            <h3 className="text-xs font-bold text-slate-800 border-b border-slate-100 pb-1.5 flex items-center justify-between">
              <span>2. Traceability & Regulatory Criteria (SCR-221 / SCR-222)</span>
              <span className="text-[10px] text-slate-400 font-normal">AEP Alignment</span>
            </h3>

            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <div>
                <label className="block">
                  <span className="text-[11px] font-semibold text-slate-700 mb-1.5 block">
                    Objectives Alignment (AEP Reference)
                  </span>
                  <input
                    type="text"
                    value={objectivesTrace}
                    onChange={(e) => setObjectivesTrace(e.target.value)}
                    placeholder="e.g. AEP-2026-020 Strategic Fieldwork Objectives"
                    className="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs text-slate-900 placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition"
                  />
                </label>
              </div>

              <div>
                <label className="block">
                  <span className="text-[11px] font-semibold text-slate-700 mb-1.5 block">
                    Linked Risk Matrix Items (RMIs)
                  </span>
                  <input
                    type="text"
                    value={rmiInput}
                    onChange={(e) => setRmiInput(e.target.value)}
                    placeholder="e.g. RMI-2026-001, RMI-2026-002"
                    className="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs font-mono text-slate-900 placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition"
                  />
                </label>
              </div>

              <div className="sm:col-span-2">
                <label className="block">
                  <span className="text-[11px] font-semibold text-slate-700 mb-1.5 block">
                    Criteria Framework References
                  </span>
                  <input
                    type="text"
                    value={criteriaInput}
                    onChange={(e) => setCriteriaInput(e.target.value)}
                    placeholder="e.g. DA Guidelines, COA Circular 2024-002, LGU Operations Code"
                    className="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs text-slate-900 placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition"
                  />
                </label>
              </div>
            </div>
          </div>

          {/* Section 3: Initial Detailed Procedure (Rule 35 Mandatory Element) */}
          <div className="rounded-xl border border-blue-200 bg-blue-50/40 p-3.5 space-y-3">
            <div className="flex items-center justify-between">
              <h3 className="text-xs font-bold text-blue-950">
                3. Mandatory Detailed Audit Procedure (Rule 35)
              </h3>
              <span className="text-[10px] text-blue-700 font-mono font-semibold bg-blue-100/70 px-2 py-0.5 rounded">
                PROC-01
              </span>
            </div>

            <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
              <div className="sm:col-span-2">
                <label className="block">
                  <span className="text-[11px] font-semibold text-slate-700 mb-1.5 block">
                    Procedure Title & Testing Activity <span className="text-rose-500">*</span>
                  </span>
                  <input
                    type="text"
                    required
                    placeholder="e.g. Beneficiary Registry Cross-Check & Voucher Vouching"
                    value={procTitle}
                    onChange={(e) => setProcTitle(e.target.value)}
                    className="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs text-slate-900 placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition"
                  />
                </label>
              </div>

              <div>
                <label className="block">
                  <span className="text-[11px] font-semibold text-slate-700 mb-1.5 block">
                    Audit Testing Method
                  </span>
                  <select
                    value={procMethod}
                    onChange={(e) => setProcMethod(e.target.value as AuditMethod)}
                    className="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition"
                  >
                    <option value="Inspection">Inspection</option>
                    <option value="Sampling & Vouching">Sampling & Vouching</option>
                    <option value="Recalculation">Recalculation</option>
                    <option value="Confirmation">Confirmation</option>
                    <option value="Inquiry & Interview">Inquiry & Interview</option>
                    <option value="Analytical Review">Analytical Review</option>
                    <option value="Observation">Observation</option>
                  </select>
                </label>
              </div>

              <div className="sm:col-span-3">
                <label className="block">
                  <span className="text-[11px] font-semibold text-slate-700 mb-1.5 block">
                    Procedure Purpose & Scope
                  </span>
                  <input
                    type="text"
                    placeholder="e.g. Substantively verify physical input releases against endorsed farmer masterlists"
                    value={procPurpose}
                    onChange={(e) => setProcPurpose(e.target.value)}
                    className="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs text-slate-900 placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition"
                  />
                </label>
              </div>

              <div>
                <label className="block">
                  <span className="text-[11px] font-semibold text-slate-700 mb-1.5 block">
                    Assigned Auditor
                  </span>
                  <select
                    value={procAssignee}
                    onChange={(e) => setProcAssignee(e.target.value)}
                    className="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition"
                  >
                    {availableAssignees.map((name, idx) => (
                      <option key={idx} value={name}>
                        {name}
                      </option>
                    ))}
                  </select>
                </label>
              </div>

              <div>
                <label className="block">
                  <span className="text-[11px] font-semibold text-slate-700 mb-1.5 block">
                    Planned Man-Days
                  </span>
                  <input
                    type="number"
                    min={1}
                    max={60}
                    value={procManDays}
                    onChange={(e) => setProcManDays(Number(e.target.value))}
                    className="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition"
                  />
                </label>
              </div>

              <div>
                <label className="block">
                  <span className="text-[11px] font-semibold text-slate-700 mb-1.5 block">
                    Due Date
                  </span>
                  <input
                    type="date"
                    value={procDueDate}
                    onChange={(e) => setProcDueDate(e.target.value)}
                    className="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition"
                  />
                </label>
              </div>

              <div className="sm:col-span-3">
                <label className="block">
                  <span className="text-[11px] font-semibold text-slate-700 mb-1.5 block">
                    Expected Evidence & Source Records
                  </span>
                  <input
                    type="text"
                    value={procEvidence}
                    onChange={(e) => setProcEvidence(e.target.value)}
                    placeholder="e.g. Disbursement vouchers, delivery receipts, beneficiary attendance sheets"
                    className="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs text-slate-900 placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition"
                  />
                </label>
              </div>
            </div>
          </div>

          {/* Footer Actions */}
          <div className="flex items-center justify-end gap-2.5 pt-2.5 border-t border-slate-100">
            <Button
              type="button"
              variant="outline"
              onClick={onClose}
              className="h-9 px-3.5 rounded-lg text-xs font-semibold border-slate-200 text-slate-600 hover:bg-slate-100 cursor-pointer"
            >
              Cancel
            </Button>
            <Button
              type="submit"
              className="h-9 px-4 rounded-lg text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white shadow-sm flex items-center gap-1.5 cursor-pointer transition active:scale-[0.99]"
            >
              <Plus size={13} />
              <span>Create Audit Program</span>
            </Button>
          </div>
        </form>
      </div>
    </div>,
    document.body,
  );
}

