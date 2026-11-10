import {
  useEffect,
  useMemo,
  useState,
  type Dispatch,
  type DragEvent,
  type ReactNode,
  type SetStateAction,
} from "react";
import {
  ArrowLeft,
  ChevronDown,
  Info,
  Loader2,
  MessageSquareText,
  ShieldCheck,
  Target,
  X,
} from "lucide-react";
import type { LucideIcon } from "lucide-react";
import { AdminLayout } from "../../../../layouts/admin/AdminLayout";
import { Button } from "../../../../components/ui/button";
import {
  Select2,
  Select2Multi,
  type Select2Option,
} from "../../../../components/ui/select2";
import { useToast } from "../../../../components/ui/toast";
import { useAuth } from "../../../../hooks/useAuth";
import { navigateTo } from "../../../../routes";
import { ApiError } from "../../../../services/api";
import { cn } from "../../../../utils/helpers";
import {
  createSpecialEngagement,
  fetchCreateEngagementBootstrap,
  importIapEngagement,
  type BackendAuditArea,
  type BackendAuditTypeOption,
  type BackendIapImportOption,
  type BackendOffice,
  type BackendUser,
} from "./create-engagement-service";

const inputClassName =
  "h-8 w-full min-w-0 rounded border border-[#a9c8dc] bg-white px-2.5 text-xs text-slate-700 shadow-sm outline-none transition focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500";

const select2ClassName =
  "h-8 rounded border-[#a9c8dc] bg-white px-2.5 text-xs font-normal text-slate-700 shadow-sm hover:bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20";

const ENGAGEMENT_SOURCE_OPTIONS: Select2Option[] = [
  { value: "IAP", label: "Internal Audit Planning (IAP)" },
  { value: "UNPLANNED", label: "Authorized Unplanned Engagement" },
];

const AUTHORITY_TYPE_OPTIONS: Select2Option[] = [
  { value: "WRITTEN_INSTRUCTION", label: "Written Instruction" },
  { value: "AUTHORIZED_REQUEST", label: "Authorized Request" },
  { value: "OTHER_AUTHORIZED_BASIS", label: "Other Authorized Basis" },
];

function RedNumberBadge({ number }: { number: number }) {
  return (
    <span className="inline-flex h-[17px] w-[17px] shrink-0 items-center justify-center rounded-full bg-red-600 text-[9px] font-bold leading-none text-white">
      {number}
    </span>
  );
}

type SectionCardProps = {
  title: string;
  icon: LucideIcon;
  children: ReactNode;
  className?: string;
};

function SectionCard({ title, icon: Icon, children, className }: SectionCardProps) {
  return (
    <section
      className={cn(
        "min-w-0 overflow-visible rounded-md border border-[#8eb9d0] bg-[#dceafd] shadow-sm",
        className,
      )}
    >
      <div className="flex min-h-10 items-center gap-2 border-b border-[#8eb9d0] bg-[#d2e8f2] px-3.5 py-2 text-sm font-semibold text-[#17396c]">
        <Icon className="h-[18px] w-[18px] shrink-0 text-[#1d348f]" strokeWidth={2} />
        <h2>{title}</h2>
      </div>
      <div className="space-y-2.5 p-3.5 sm:p-4">{children}</div>
    </section>
  );
}

type FieldRowProps = {
  label: string;
  marker?: number;
  children: ReactNode;
  alignTop?: boolean;
};

function FieldRow({ label, marker, children, alignTop = false }: FieldRowProps) {
  return (
    <div
      className={cn(
        "grid min-w-0 grid-cols-1 gap-1.5 sm:grid-cols-[118px_minmax(0,1fr)] sm:gap-3",
        alignTop ? "sm:items-start" : "sm:items-center",
      )}
    >
      <span
        className={cn(
          "text-xs font-medium text-[#27466f] sm:text-right",
          alignTop && "sm:pt-1.5",
        )}
      >
        {label}
      </span>
      <div className="flex min-w-0 items-start gap-2">
        <div className="min-w-0 flex-1">{children}</div>
        {marker !== undefined && <RedNumberBadge number={marker} />}
      </div>
    </div>
  );
}

function inclusiveDays(start: string, end: string) {
  if (!start || !end) return 0;
  const startTime = new Date(`${start}T00:00:00`).getTime();
  const endTime = new Date(`${end}T00:00:00`).getTime();
  if (!Number.isFinite(startTime) || !Number.isFinite(endTime) || endTime < startTime) {
    return 0;
  }
  return Math.floor((endTime - startTime) / 86_400_000) + 1;
}

function formatDateDisplay(isoString?: string | null): string {
  if (!isoString) return "—";
  const parsed = new Date(isoString);
  if (Number.isNaN(parsed.getTime())) return isoString;
  return parsed.toLocaleDateString("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
  });
}

function todayIsoDate(): string {
  return new Date().toISOString().split("T")[0];
}

export function CreateAuditEngagementPage() {
  const { showToast } = useToast();
  const { user: currentUser, runtimeConfig } = useAuth();
  const defaultFiscalYear = String(runtimeConfig?.currentFiscalYear || new Date().getFullYear());
  const userPermissions = Array.isArray(currentUser?.permissions) ? currentUser.permissions : [];
  const canCreateHint =
    userPermissions.length === 0 || userPermissions.includes("aems.engagement.create");

  // Bootstrap real DB data
  const [isLoadingBootstrap, setIsLoadingBootstrap] = useState(true);
  const [canCreateEngagement, setCanCreateEngagement] = useState(canCreateHint);
  const [iapEngagements, setIapEngagements] = useState<BackendIapImportOption[]>([]);
  const [offices, setOffices] = useState<BackendOffice[]>([]);
  const [auditAreasRegistry, setAuditAreasRegistry] = useState<BackendAuditArea[]>([]);
  const [users, setUsers] = useState<BackendUser[]>([]);
  const [auditTypeOptions, setAuditTypeOptions] = useState<BackendAuditTypeOption[]>([]);
  const [existingCount, setExistingCount] = useState(0);

  // Source & Authorization state
  const [engagementSource, setEngagementSource] = useState<"IAP" | "UNPLANNED">("IAP");
  const [selectedIapEngagementId, setSelectedIapEngagementId] = useState<string>("");

  const [authorityTypeCode, setAuthorityTypeCode] = useState("WRITTEN_INSTRUCTION");
  const [authorityReference, setAuthorityReference] = useState("");
  const [authorityDate, setAuthorityDate] = useState(todayIsoDate());
  const [approvingAuthorityId, setApprovingAuthorityId] = useState<string>("");
  const [requestingOfficeId, setRequestingOfficeId] = useState<string>("");
  const [dateReceived, setDateReceived] = useState(todayIsoDate());
  const [supportingFile, setSupportingFile] = useState<File | null>(null);
  const [supportingFileName, setSupportingFileName] = useState<string | null>(null);

  // Identity & Direction state
  const [engagementTitle, setEngagementTitle] = useState("");
  const [selectedOfficeId, setSelectedOfficeId] = useState<string>("");
  const [selectedAuditTypeIds, setSelectedAuditTypeIds] = useState<string[]>([]);
  const [auditYear, setAuditYear] = useState(defaultFiscalYear);

  const [selectedAuditAreaIds, setSelectedAuditAreaIds] = useState<string[]>([]);
  const [isAddingArea, setIsAddingArea] = useState(false);
  const [areaToAddId, setAreaToAddId] = useState("");
  const [initialObjective, setInitialObjective] = useState("");
  const [periodStart, setPeriodStart] = useState(`${defaultFiscalYear}-01-01`);
  const [periodEnd, setPeriodEnd] = useState(`${defaultFiscalYear}-12-31`);
  const [startDate, setStartDate] = useState(todayIsoDate());
  const [endDate, setEndDate] = useState("");

  // Team & AEO state
  const [departmentHeadId, setDepartmentHeadId] = useState<string>("");
  const [teamLeaderId, setTeamLeaderId] = useState<string>("");
  const [teamMemberIds, setTeamMemberIds] = useState<string[]>([]);
  const [supportStaffIds, setSupportStaffIds] = useState<string[]>([]);
  const [aeoDate, setAeoDate] = useState(todayIsoDate());
  const [aeoFileName, setAeoFileName] = useState<string | null>(null);

  const [isMoreActionsOpen, setIsMoreActionsOpen] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const isIAP = engagementSource === "IAP";

  const selectedIapOption = useMemo(
    () => iapEngagements.find((item) => String(item.id) === selectedIapEngagementId) || null,
    [iapEngagements, selectedIapEngagementId],
  );

  // Helper to prefill form fields when an IAP engagement option is selected
  const applyIapOptionToForm = (option: BackendIapImportOption) => {
    setSelectedIapEngagementId(String(option.id));
    setEngagementTitle(option.title || "");
    const firstOffice = option.offices?.[0];
    setSelectedOfficeId(firstOffice ? String(firstOffice.id) : "");
    setSelectedAuditTypeIds(option.auditType?.id ? [String(option.auditType.id)] : []);
    setAuditYear(String(option.plan?.fiscalYear || defaultFiscalYear));
    setSelectedAuditAreaIds(option.auditAreas.map((a) => String(a.id)));
    setInitialObjective(option.objectives || "");
    if (option.plan?.periodStart) setPeriodStart(option.plan.periodStart);
    if (option.plan?.periodEnd) setPeriodEnd(option.plan.periodEnd);
    if (option.plannedStartDate) setStartDate(option.plannedStartDate);
    if (option.plannedEndDate) setEndDate(option.plannedEndDate);
  };

  useEffect(() => {
    let mounted = true;
    setIsLoadingBootstrap(true);

    fetchCreateEngagementBootstrap({ canCreateHint })
      .then((data) => {
        if (!mounted) return;
        setCanCreateEngagement(data.canCreateEngagement);
        setIapEngagements(data.iapEngagements);
        setOffices(data.offices);
        setAuditAreasRegistry(data.auditAreas);
        setUsers(data.users);
        setAuditTypeOptions(data.auditTypes);
        setExistingCount(data.existingCount);

        if (data.iapEngagements.length > 0) {
          setEngagementSource("IAP");
          applyIapOptionToForm(data.iapEngagements[0]);
        } else {
          // If no pending IAP items remain, ensure initial offices are populated cleanly
          if (data.offices.length > 0) {
            const firstOfficeId = String(data.offices[0].id);
            setRequestingOfficeId(firstOfficeId);
            setSelectedOfficeId(firstOfficeId);
          }
        }
      })
      .catch((err) => {
        if (!mounted) return;
        showToast({
          title: "Failed to load form options",
          description:
            err instanceof Error
              ? err.message
              : "Could not fetch backend registries for offices, users, and plans.",
          tone: "warning",
        });
      })
      .finally(() => {
        if (mounted) setIsLoadingBootstrap(false);
      });

    return () => {
      mounted = false;
    };
  }, [canCreateHint]);

  // When switching between IAP and UNPLANNED modes
  const handleSourceChange = (nextSource: "IAP" | "UNPLANNED") => {
    setEngagementSource(nextSource);
    if (nextSource === "IAP") {
      if (iapEngagements.length > 0) {
        applyIapOptionToForm(selectedIapOption || iapEngagements[0]);
      } else {
        setSelectedIapEngagementId("");
        setEngagementTitle("");
        setSelectedAuditAreaIds([]);
        setSelectedAuditTypeIds([]);
        setInitialObjective("");
      }
    } else {
      // Switching to Unplanned: ensure clean state for manual entry
      if (selectedIapOption) {
        setEngagementTitle("");
        setInitialObjective("");
        setSelectedAuditAreaIds([]);
        setSelectedAuditTypeIds([]);
      }
      if (!selectedOfficeId && offices.length > 0) {
        setSelectedOfficeId(String(offices[0].id));
      }
      if (!requestingOfficeId && offices.length > 0) {
        setRequestingOfficeId(String(offices[0].id));
      }
    }
  };

  // Audit areas valid for the selected Office (since backend enforces audit_area_office link)
  const officeLinkedAuditAreas = useMemo(() => {
    if (!selectedOfficeId) return auditAreasRegistry;
    const officeIdNum = Number(selectedOfficeId);
    const officeObj = offices.find((o) => o.id === officeIdNum);
    const officeDirectAreaIds = new Set((officeObj?.auditAreas || []).map((a) => a.id));

    return auditAreasRegistry.filter(
      (area) =>
        officeDirectAreaIds.has(area.id) ||
        area.offices?.some((o) => o.id === officeIdNum),
    );
  }, [auditAreasRegistry, offices, selectedOfficeId]);

  const handleOfficeChange = (nextOfficeId: string) => {
    setSelectedOfficeId(nextOfficeId);
    if (!isIAP) {
      const nextOfficeNum = Number(nextOfficeId);
      const officeObj = offices.find((o) => o.id === nextOfficeNum);
      const allowedIds = new Set([
        ...(officeObj?.auditAreas || []).map((a) => String(a.id)),
        ...auditAreasRegistry
          .filter((a) => a.offices?.some((o) => o.id === nextOfficeNum))
          .map((a) => String(a.id)),
      ]);
      setSelectedAuditAreaIds((prev) => prev.filter((id) => allowedIds.has(id)));
      setAreaToAddId("");
      setIsAddingArea(false);
    }
  };

  // Options formatted for Select2 & Select2Multi
  const formatUserLabel = (u: BackendUser) =>
    `${u.name}${u.position ? ` — ${u.position}` : u.role?.name ? ` (${u.role.name})` : ""}`;

  const iapSelectOptions = useMemo<Select2Option[]>(
    () =>
      iapEngagements.map((item) => ({
        value: String(item.id),
        label: `${item.plan.planCode} (${item.engagementCode}) — ${item.title}`,
      })),
    [iapEngagements],
  );

  const officeSelectOptions = useMemo<Select2Option[]>(
    () =>
      offices.map((o) => ({
        value: String(o.id),
        label: `${o.name} (${o.code})`,
      })),
    [offices],
  );

  const auditTypeSelectOptions = useMemo<Select2Option[]>(
    () =>
      auditTypeOptions.map((item) => ({
        value: String(item.id),
        label: item.label,
      })),
    [auditTypeOptions],
  );

  // Exclude currently logged-in creator from Approving Authority (backend rule)
  const approvingAuthoritySelectOptions = useMemo<Select2Option[]>(() => {
    const currentId = currentUser?.id ? Number(currentUser.id) : null;
    return users
      .filter((u) => (currentId ? u.id !== currentId : true))
      .map((u) => ({
        value: String(u.id),
        label: formatUserLabel(u),
      }));
  }, [users, currentUser?.id]);

  // Enforce unique person per team role (AemsTeamService rule)
  const departmentHeadSelectOptions = useMemo<Select2Option[]>(
    () =>
      users
        .filter(
          (u) =>
            String(u.id) !== teamLeaderId &&
            !teamMemberIds.includes(String(u.id)) &&
            !supportStaffIds.includes(String(u.id)),
        )
        .map((u) => ({
          value: String(u.id),
          label: formatUserLabel(u),
        })),
    [users, teamLeaderId, teamMemberIds, supportStaffIds],
  );

  const teamLeaderSelectOptions = useMemo<Select2Option[]>(
    () =>
      users
        .filter(
          (u) =>
            String(u.id) !== departmentHeadId &&
            !teamMemberIds.includes(String(u.id)) &&
            !supportStaffIds.includes(String(u.id)),
        )
        .map((u) => ({
          value: String(u.id),
          label: formatUserLabel(u),
        })),
    [users, departmentHeadId, teamMemberIds, supportStaffIds],
  );

  const teamMemberSelectOptions = useMemo<Select2Option[]>(
    () =>
      users.map((u) => {
        const uid = String(u.id);
        const takenElsewhere =
          uid === departmentHeadId ||
          uid === teamLeaderId ||
          supportStaffIds.includes(uid);
        return {
          value: uid,
          label: formatUserLabel(u),
          disabled: takenElsewhere,
        };
      }),
    [users, departmentHeadId, teamLeaderId, supportStaffIds],
  );

  const supportStaffSelectOptions = useMemo<Select2Option[]>(
    () =>
      users.map((u) => {
        const uid = String(u.id);
        const takenElsewhere =
          uid === departmentHeadId ||
          uid === teamLeaderId ||
          teamMemberIds.includes(uid);
        return {
          value: uid,
          label: formatUserLabel(u),
          disabled: takenElsewhere,
        };
      }),
    [users, departmentHeadId, teamLeaderId, teamMemberIds],
  );

  const availableAreasToAdd = useMemo(
    () =>
      officeLinkedAuditAreas.filter(
        (area) => !selectedAuditAreaIds.includes(String(area.id)),
      ),
    [officeLinkedAuditAreas, selectedAuditAreaIds],
  );

  const areaAddSelectOptions = useMemo<Select2Option[]>(
    () =>
      availableAreasToAdd.map((area) => ({
        value: String(area.id),
        label: `${area.name} (${area.code})`,
      })),
    [availableAreasToAdd],
  );

  const auditAreaLabelMap = useMemo(() => {
    const map = new Map<string, string>();
    auditAreasRegistry.forEach((a) => map.set(String(a.id), a.name));
    iapEngagements.forEach((iap) =>
      iap.auditAreas.forEach((a) => map.set(String(a.id), a.name)),
    );
    return map;
  }, [auditAreasRegistry, iapEngagements]);

  const aeoNumber = `AEO-${auditYear || defaultFiscalYear}-${String(existingCount + 1).padStart(3, "0")}`;
  const plannedDurationDays = inclusiveDays(startDate, endDate);

  const handleAddSelectedAuditArea = () => {
    if (areaToAddId && !selectedAuditAreaIds.includes(areaToAddId)) {
      setSelectedAuditAreaIds((prev) => [...prev, areaToAddId]);
    }
    setAreaToAddId("");
    setIsAddingArea(false);
  };

  const handleFile = (
    file: File | undefined,
    setFileName: Dispatch<SetStateAction<string | null>>,
    setFileObj?: Dispatch<SetStateAction<File | null>>,
  ) => {
    if (!file) return;
    const validType = /\.(pdf|doc|docx)$/i.test(file.name);
    if (!validType) {
      showToast({
        title: "Unsupported file",
        description: "Choose a PDF, DOC, or DOCX file.",
        tone: "warning",
      });
      return;
    }
    if (file.size > 10 * 1024 * 1024) {
      showToast({
        title: "File too large",
        description: "The document must be 10 MB or smaller.",
        tone: "warning",
      });
      return;
    }
    setFileName(file.name);
    if (setFileObj) setFileObj(file);
    showToast({
      title: "File attached",
      description: `${file.name} is ready to include with the engagement.`,
      tone: "success",
    });
  };

  const handleDrop = (
    event: DragEvent<HTMLDivElement>,
    setFileName: Dispatch<SetStateAction<string | null>>,
    setFileObj?: Dispatch<SetStateAction<File | null>>,
  ) => {
    event.preventDefault();
    handleFile(event.dataTransfer.files[0], setFileName, setFileObj);
  };

  const buildInitialTeamPlan = () => {
    const plan: Record<string, unknown> = {};
    if (departmentHeadId) plan.departmentHeadId = Number(departmentHeadId);
    if (teamLeaderId) plan.teamLeaderId = Number(teamLeaderId);
    if (teamMemberIds.length > 0) plan.teamMemberIds = teamMemberIds.map(Number);
    if (supportStaffIds.length > 0) plan.supportStaffIds = supportStaffIds.map(Number);
    if (aeoNumber) plan.aeoReference = aeoNumber;
    if (aeoDate) plan.aeoDate = aeoDate;
    return Object.keys(plan).length > 0 ? plan : undefined;
  };

  const handleSaveEngagement = async () => {
    if (!canCreateEngagement) {
      showToast({
        title: "Permission Required",
        description: `Your current role (${currentUser?.role || "Current Account"}) is view-only for engagement creation. Switch to CIAS Management, Platform Administrator, or an authorized Lead Auditor account.`,
        tone: "warning",
      });
      return;
    }

    const initialTeamPlan = buildInitialTeamPlan();

    if (isIAP) {
      if (!selectedIapEngagementId) {
        showToast({
          title: "Approved IAP Item Required",
          description:
            "Select an eligible approved IAP engagement reference, or switch Engagement Source to Authorized Unplanned Engagement.",
          tone: "warning",
        });
        return;
      }

      setIsSubmitting(true);
      try {
        const created = await importIapEngagement({
          iapPlanEngagementId: Number(selectedIapEngagementId),
          initialTeamPlan,
        });
        showToast({
          title: "Engagement Imported",
          description: `Engagement ${created.engagementCode || ""} created from the approved IAP.`,
          tone: "success",
        });
        navigateTo(`/admin/audit-engagement/${created.id}`);
      } catch (err) {
        showToast({
          title: "Failed to Import IAP Engagement",
          description:
            err instanceof ApiError || err instanceof Error
              ? err.message
              : "Could not import the selected IAP engagement.",
          tone: "warning",
        });
      } finally {
        setIsSubmitting(false);
      }
      return;
    }

    // Validations for Special / Unplanned Engagement
    if (!authorityReference.trim()) {
      showToast({
        title: "Reference No. Required",
        description: "Please enter the authority reference number (e.g., MO-2026-015).",
        tone: "warning",
      });
      return;
    }
    if (!authorityDate) {
      showToast({
        title: "Authority Date Required",
        description: "Please enter the authority issuance date.",
        tone: "warning",
      });
      return;
    }
    if (!approvingAuthorityId) {
      showToast({
        title: "Directing Authority Required",
        description: "Please select the directing/approving authority.",
        tone: "warning",
      });
      return;
    }
    if (!engagementTitle.trim()) {
      showToast({
        title: "Engagement Title Required",
        description: "Please enter an engagement title.",
        tone: "warning",
      });
      return;
    }
    if (!startDate || !endDate || plannedDurationDays <= 0) {
      showToast({
        title: "Valid Schedule Required",
        description: "Please provide valid Start and End dates with positive duration.",
        tone: "warning",
      });
      return;
    }
    if (selectedOfficeId && selectedAuditAreaIds.length === 0) {
      showToast({
        title: "Audit Area Required",
        description:
          "When specifying an Engagement Office directly, please add at least one Audit Area linked to that office (or clear the office selection to configure scope later).",
        tone: "warning",
      });
      return;
    }

    setIsSubmitting(true);
    try {
      const created = await createSpecialEngagement(
        {
          title: engagementTitle.trim(),
          specialAuthorityReference: authorityReference.trim(),
          specialAuthorityTypeCode: authorityTypeCode,
          specialAuthorityClass: "SPECIAL",
          specialAuthorityDate: authorityDate,
          specialAuthorityReceivedDate: dateReceived || undefined,
          specialAuthorityApprovedBy: Number(approvingAuthorityId),
          auditTypeId: selectedAuditTypeIds[0] ? Number(selectedAuditTypeIds[0]) : undefined,
          auditTypeIds:
            selectedAuditTypeIds.length > 0 ? selectedAuditTypeIds.map(Number) : undefined,
          requestingOfficeId: requestingOfficeId ? Number(requestingOfficeId) : undefined,
          auditYear: Number(auditYear) || undefined,
          objectives: initialObjective.trim() || undefined,
          periodCoveredStartDate: periodStart || undefined,
          periodCoveredEndDate: periodEnd || undefined,
          plannedStartDate: startDate,
          plannedEndDate: endDate,
          plannedPersonDays: plannedDurationDays,
          officeIds:
            selectedOfficeId && selectedAuditAreaIds.length > 0
              ? [Number(selectedOfficeId)]
              : undefined,
          auditAreaIds:
            selectedOfficeId && selectedAuditAreaIds.length > 0
              ? selectedAuditAreaIds.map(Number)
              : undefined,
          initialTeamPlan,
        },
        supportingFile,
      );

      showToast({
        title: "Engagement Created",
        description: `Special engagement ${created.engagementCode || ""} saved to the database.`,
        tone: "success",
      });
      navigateTo(`/admin/audit-engagement/${created.id}`);
    } catch (err) {
      showToast({
        title: "Could Not Create Engagement",
        description:
          err instanceof ApiError || err instanceof Error
            ? err.message
            : "Validation or server error occurred while saving engagement.",
        tone: "warning",
      });
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <AdminLayout>
      <div className="mx-auto w-full max-w-[1480px] space-y-3 pb-8">
        <nav
          aria-label="Breadcrumb"
          className="flex flex-wrap items-center gap-2 text-xs text-[#698099]"
        >
          <button
            onClick={() => navigateTo("/admin/dashboard")}
            className="hover:text-blue-700"
            type="button"
          >
            Home
          </button>
          <span aria-hidden="true">&gt;</span>
          <button
            onClick={() => navigateTo("/admin/audit-engagements")}
            className="hover:text-blue-700"
            type="button"
          >
            Audit Engagements
          </button>
          <span aria-hidden="true">&gt;</span>
          <span className="text-[#52708c]">Create Audit Engagement</span>
        </nav>

        <header className="flex flex-col gap-3 border-b border-[#c7d7e4] pb-3 xl:flex-row xl:items-center xl:justify-between">
          <div className="min-w-0">
            <div className="flex flex-wrap items-center gap-x-2.5 gap-y-1">
              <h1 className="text-[25px] font-semibold leading-tight tracking-tight text-[#193b8c] sm:text-[28px]">
                Create Audit Engagement
              </h1>
              <span className="text-[10px] font-medium text-red-500">
                [WF-SCR-200-{isIAP ? "02A" : "02B"}]
              </span>
            </div>
            <p className="mt-1 text-xs text-[#62788e]">
              Encode the basic information to create a new audit engagement. Detailed planning
              information will be completed in the Planning workspace.
            </p>
          </div>
          <div className="flex flex-wrap items-center gap-2 xl:shrink-0">
            <Button
              variant="outline"
              disabled={isSubmitting}
              onClick={() => navigateTo("/admin/audit-engagements")}
              className="h-8 border-slate-300 bg-white px-3 text-xs font-medium text-slate-700"
            >
              <ArrowLeft className="mr-1.5 h-3.5 w-3.5" /> Back to AEM Workspace
            </Button>
            <Button
              variant="outline"
              disabled={isSubmitting || isLoadingBootstrap}
              onClick={handleSaveEngagement}
              className="h-8 border-slate-300 bg-white px-4 text-xs font-medium text-slate-700"
            >
              {isSubmitting ? (
                <>
                  <Loader2 className="mr-1.5 h-3.5 w-3.5 animate-spin" />
                  Saving...
                </>
              ) : (
                "Save Draft"
              )}
            </Button>
            <Button
              disabled={isSubmitting || isLoadingBootstrap}
              onClick={handleSaveEngagement}
              className="h-8 bg-[#0868d7] px-4 text-xs font-semibold text-white hover:bg-[#0759b9]"
            >
              {isSubmitting ? (
                <>
                  <Loader2 className="mr-1.5 h-3.5 w-3.5 animate-spin" />
                  Creating...
                </>
              ) : (
                "Create Audit Engagement"
              )}
            </Button>
            <div className="relative">
              <Button
                disabled={isSubmitting}
                onClick={() => setIsMoreActionsOpen((open) => !open)}
                aria-expanded={isMoreActionsOpen}
                className="h-8 bg-[#2585e5] px-3 text-xs font-semibold text-white hover:bg-[#1674d4]"
              >
                More Actions{" "}
                <ChevronDown
                  className={cn(
                    "ml-1 h-3.5 w-3.5 transition-transform",
                    isMoreActionsOpen && "rotate-180",
                  )}
                />
              </Button>
              {isMoreActionsOpen && (
                <div className="absolute right-0 top-full z-40 mt-1 w-52 rounded-md border border-slate-200 bg-white py-1 text-xs shadow-lg">
                  <button
                    type="button"
                    onClick={() => {
                      setIsMoreActionsOpen(false);
                      handleSourceChange("IAP");
                    }}
                    className="w-full px-3 py-2 text-left text-slate-700 hover:bg-blue-50"
                  >
                    Switch to Approved IAP
                  </button>
                  <button
                    type="button"
                    onClick={() => {
                      setIsMoreActionsOpen(false);
                      handleSourceChange("UNPLANNED");
                    }}
                    className="w-full px-3 py-2 text-left text-slate-700 hover:bg-blue-50"
                  >
                    Switch to Unplanned Authority
                  </button>
                  <button
                    type="button"
                    onClick={() => navigateTo("/admin/audit-engagements")}
                    className="w-full border-t border-slate-100 px-3 py-2 text-left text-rose-600 hover:bg-rose-50"
                  >
                    Discard Changes
                  </button>
                </div>
              )}
            </div>
          </div>
        </header>

        {!isLoadingBootstrap && !canCreateEngagement && (
          <div className="flex items-start gap-2.5 rounded-md border border-amber-300 bg-amber-50 px-3.5 py-2.5 text-xs text-amber-900">
            <Info className="mt-0.5 h-4 w-4 shrink-0 text-amber-600" />
            <div>
              <span className="font-semibold">View-Only Access for Current Role:</span>{" "}
              Your current account role ({currentUser?.role || "Administrator"}) does not include{" "}
              <code className="rounded bg-amber-100 px-1 py-0.5 text-[11px]">aems.engagement.create</code>.
              You can inspect live office, user, and audit type options below, or sign in with CIAS Management / Platform Administrator / Lead Auditor to create engagements.
            </div>
          </div>
        )}

        {isLoadingBootstrap ? (
          <div className="grid grid-cols-1 gap-3.5 lg:grid-cols-2">
            {[1, 2, 3, 4].map((card) => (
              <div
                key={card}
                className="h-64 animate-pulse rounded-md border border-[#8eb9d0] bg-[#dceafd]/70 p-4"
              >
                <div className="mb-4 h-6 w-48 rounded bg-[#b8d4ea]" />
                <div className="space-y-3">
                  <div className="h-8 w-full rounded bg-white/70" />
                  <div className="h-8 w-5/6 rounded bg-white/70" />
                  <div className="h-8 w-4/5 rounded bg-white/70" />
                </div>
              </div>
            ))}
          </div>
        ) : (
          <div className="grid grid-cols-1 items-start gap-3.5 lg:grid-cols-2 lg:items-stretch">
            {/* Section 1: Engagement Source & Authorization */}
            <SectionCard title="Engagement Source & Authorization" icon={Info}>
              <FieldRow label="Engagement Source" marker={1}>
                <Select2
                  ariaLabel="Engagement Source"
                  value={engagementSource}
                  options={ENGAGEMENT_SOURCE_OPTIONS}
                  searchable={false}
                  onChange={(val) => handleSourceChange(val as "IAP" | "UNPLANNED")}
                  className={select2ClassName}
                />
              </FieldRow>

              {isIAP ? (
                <>
                  <FieldRow label="Reference No." marker={2} alignTop={iapEngagements.length === 0}>
                    {iapEngagements.length > 0 ? (
                      <Select2
                        ariaLabel="Plan Reference"
                        value={selectedIapEngagementId}
                        options={iapSelectOptions}
                        placeholder="Select approved IAP item..."
                        searchPlaceholder="Search plan code or title..."
                        onChange={(val) => {
                          const found = iapEngagements.find((item) => String(item.id) === val);
                          if (found) applyIapOptionToForm(found);
                        }}
                        className={select2ClassName}
                      />
                    ) : (
                      <div className="space-y-1.5">
                        <Select2
                          disabled
                          value=""
                          options={[]}
                          placeholder="No eligible approved IAP engagements found"
                          searchable={false}
                          className={select2ClassName}
                        />
                        <p className="text-[11px] leading-snug text-amber-800">
                          All approved IAP items are already imported or none are approved yet. Switch{" "}
                          <button
                            type="button"
                            onClick={() => handleSourceChange("UNPLANNED")}
                            className="font-semibold text-blue-700 underline"
                          >
                            Engagement Source to Authorized Unplanned
                          </button>{" "}
                          to create a new engagement directly.
                        </p>
                      </div>
                    )}
                  </FieldRow>
                  <FieldRow label="Approved By" marker={3}>
                    <input
                      aria-label="Approved By"
                      readOnly
                      value={selectedIapOption?.plan?.approvedBy?.name || "—"}
                      className={cn(inputClassName, "bg-slate-100 text-slate-500")}
                    />
                  </FieldRow>
                  <FieldRow label="Approval Date">
                    <input
                      aria-label="Approval Date"
                      readOnly
                      value={formatDateDisplay(selectedIapOption?.plan?.approvedAt)}
                      className={cn(inputClassName, "max-w-[170px] bg-slate-100 text-slate-500")}
                    />
                  </FieldRow>
                </>
              ) : (
                <>
                  <FieldRow label="Authority Type" marker={2}>
                    <Select2
                      ariaLabel="Authority Type"
                      value={authorityTypeCode}
                      options={AUTHORITY_TYPE_OPTIONS}
                      searchable={false}
                      onChange={(val) => setAuthorityTypeCode(val)}
                      className={select2ClassName}
                    />
                  </FieldRow>
                  <FieldRow label="Reference No." marker={3}>
                    <input
                      aria-label="Authority Reference"
                      placeholder="e.g., MO-2026-015"
                      value={authorityReference}
                      onChange={(event) => setAuthorityReference(event.target.value)}
                      className={inputClassName}
                    />
                  </FieldRow>
                  <FieldRow label="Authority Date">
                    <input
                      aria-label="Authority Date"
                      type="date"
                      value={authorityDate}
                      onChange={(event) => setAuthorityDate(event.target.value)}
                      className={cn(inputClassName, "max-w-[170px]")}
                    />
                  </FieldRow>
                  <FieldRow label="Directing Authority" marker={4}>
                    <Select2
                      ariaLabel="Directing Authority"
                      value={approvingAuthorityId}
                      options={approvingAuthoritySelectOptions}
                      placeholder="Select Approving Authority..."
                      searchPlaceholder="Search official or role..."
                      allowClear
                      onChange={(val) => setApprovingAuthorityId(val)}
                      className={select2ClassName}
                    />
                  </FieldRow>
                  <FieldRow label="Requesting Office" marker={5}>
                    <Select2
                      ariaLabel="Requesting Office"
                      value={requestingOfficeId}
                      options={officeSelectOptions}
                      placeholder="Select Requesting Office..."
                      searchPlaceholder="Search office name or code..."
                      allowClear
                      onChange={(val) => setRequestingOfficeId(val)}
                      className={select2ClassName}
                    />
                  </FieldRow>
                  <FieldRow label="Date Received">
                    <input
                      aria-label="Date Received"
                      type="date"
                      value={dateReceived}
                      onChange={(event) => setDateReceived(event.target.value)}
                      className={cn(inputClassName, "max-w-[170px]")}
                    />
                  </FieldRow>
                  <FieldRow label="Supporting Doc" alignTop>
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                      <div
                        onDragOver={(event) => event.preventDefault()}
                        onDrop={(event) =>
                          handleDrop(event, setSupportingFileName, setSupportingFile)
                        }
                        className="flex min-h-9 flex-1 flex-col items-center justify-center rounded border border-dashed border-[#a9c8dc] bg-[#eef7fc] px-2 py-1.5 text-center text-[10px] leading-tight text-[#7394ad]"
                      >
                        <span className="max-w-full truncate font-medium text-slate-600">
                          {supportingFileName || "Click to upload or drag and drop"}
                        </span>
                        <span>PDF, DOC, DOCX (Max 10 MB)</span>
                      </div>
                      <label className="inline-flex h-7 shrink-0 cursor-pointer items-center rounded border border-[#9eb7c9] bg-[#f8fbfd] px-2.5 text-[11px] font-medium text-slate-700 hover:bg-white">
                        Upload File
                        <input
                          type="file"
                          accept=".pdf,.doc,.docx"
                          className="sr-only"
                          onChange={(event) =>
                            handleFile(
                              event.target.files?.[0],
                              setSupportingFileName,
                              setSupportingFile,
                            )
                          }
                        />
                      </label>
                    </div>
                  </FieldRow>
                </>
              )}
            </SectionCard>

            {/* Section 2: Engagement Identity */}
            <SectionCard title="Engagement Identity" icon={ShieldCheck}>
              <FieldRow label="Engagement Title" marker={isIAP ? 4 : 6}>
                <input
                  aria-label="Engagement Title"
                  readOnly={isIAP}
                  placeholder={isIAP ? "Auto-filled from selected IAP item" : "Enter engagement title"}
                  value={engagementTitle}
                  onChange={(event) => setEngagementTitle(event.target.value)}
                  className={cn(
                    inputClassName,
                    isIAP && "bg-slate-100 text-slate-500",
                  )}
                />
              </FieldRow>
              <FieldRow label="Office">
                <Select2
                  ariaLabel="Office"
                  disabled={isIAP}
                  value={selectedOfficeId}
                  options={officeSelectOptions}
                  placeholder={
                    isIAP ? "No office selected" : "Select Target Office (optional for draft)..."
                  }
                  searchPlaceholder="Search office name or code..."
                  allowClear={!isIAP}
                  onChange={(val) => handleOfficeChange(val)}
                  className={select2ClassName}
                />
              </FieldRow>
              <FieldRow label="Audit Type(s)" marker={isIAP ? 5 : 7}>
                <Select2Multi
                  selected={selectedAuditTypeIds}
                  options={auditTypeSelectOptions}
                  onChange={setSelectedAuditTypeIds}
                  placeholder="Select audit types"
                  searchPlaceholder="Search audit types..."
                  ariaLabel="Choose audit types"
                  disabled={isIAP}
                />
              </FieldRow>
              <FieldRow label="Audit Year">
                <input
                  aria-label="Audit Year"
                  readOnly={isIAP}
                  type="number"
                  value={auditYear}
                  onChange={(event) => setAuditYear(event.target.value)}
                  className={cn(
                    inputClassName,
                    "max-w-[140px]",
                    isIAP && "bg-slate-100 text-slate-500",
                  )}
                />
              </FieldRow>
            </SectionCard>

            {/* Section 3: Initial Engagement Direction */}
            <SectionCard title="Initial Engagement Direction" icon={Target}>
              <FieldRow label="Audit Area(s)" marker={isIAP ? 6 : 8} alignTop>
                <div className="space-y-1.5">
                  {!isIAP && (
                    <div className="flex min-h-4 items-center justify-end">
                      {isAddingArea ? (
                        <div className="flex w-full items-center gap-1.5">
                          <div className="min-w-0 flex-1">
                            <Select2
                              ariaLabel="Select audit area to add"
                              value={areaToAddId}
                              options={areaAddSelectOptions}
                              placeholder={
                                availableAreasToAdd.length > 0
                                  ? "Select linked audit area..."
                                  : "No remaining linked audit areas for this office"
                              }
                              searchPlaceholder="Search audit area..."
                              onChange={(val) => setAreaToAddId(val)}
                              className="h-7 rounded border-[#a9c8dc] bg-white px-2 text-xs font-normal text-slate-700 shadow-sm hover:bg-white"
                            />
                          </div>
                          <Button
                            type="button"
                            disabled={!areaToAddId}
                            onClick={handleAddSelectedAuditArea}
                            className="h-7 shrink-0 px-2.5 text-xs"
                          >
                            Add
                          </Button>
                          <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                              setIsAddingArea(false);
                              setAreaToAddId("");
                            }}
                            className="h-7 shrink-0 px-2.5 text-xs"
                          >
                            Cancel
                          </Button>
                        </div>
                      ) : (
                        <button
                          type="button"
                          onClick={() => setIsAddingArea(true)}
                          className="text-[11px] font-medium text-blue-700 hover:underline"
                        >
                          [ Add Audit Area ]
                        </button>
                      )}
                    </div>
                  )}
                  <div
                    className={cn(
                      "flex min-h-8 flex-wrap items-center gap-1.5 rounded border border-[#a9c8dc] p-1.5 shadow-sm",
                      isIAP ? "bg-slate-100" : "bg-white",
                    )}
                  >
                    {selectedAuditAreaIds.map((areaId) => (
                      <span
                        key={areaId}
                        className="inline-flex max-w-full items-center gap-1 rounded-sm border border-[#c5dced] bg-[#e4f2fc] px-1.5 py-1 text-[11px] leading-tight text-slate-700"
                      >
                        <span className="truncate">
                          {auditAreaLabelMap.get(areaId) || `Area #${areaId}`}
                        </span>
                        {!isIAP && (
                          <button
                            type="button"
                            aria-label={`Remove ${auditAreaLabelMap.get(areaId) || areaId}`}
                            onClick={() =>
                              setSelectedAuditAreaIds((current) =>
                                current.filter((id) => id !== areaId),
                              )
                            }
                            className="text-slate-500 hover:text-red-600"
                          >
                            <X className="h-3 w-3" />
                          </button>
                        )}
                      </span>
                    ))}
                    {selectedAuditAreaIds.length === 0 && (
                      <span className="px-1 text-xs text-slate-400">
                        {isIAP
                          ? "Pre-filled from approved IAP"
                          : selectedOfficeId
                          ? "Click [ Add Audit Area ] to select areas linked to this office"
                          : "Select a Target Office first or configure in Scope later"}
                      </span>
                    )}
                  </div>
                </div>
              </FieldRow>

              <FieldRow label="Initial Objective" marker={isIAP ? 7 : 9} alignTop>
                <textarea
                  aria-label="Initial Objective"
                  rows={2}
                  readOnly={isIAP}
                  placeholder={isIAP ? "Auto-filled from approved IAP" : "State the primary objective of the audit engagement..."}
                  value={initialObjective}
                  onChange={(event) => setInitialObjective(event.target.value)}
                  className={cn(
                    "w-full resize-y rounded border border-[#a9c8dc] px-2.5 py-2 text-xs leading-snug shadow-sm outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20",
                    isIAP ? "bg-slate-100 text-slate-500" : "bg-white text-slate-700",
                  )}
                />
              </FieldRow>

              <FieldRow label="Period Covered" alignTop>
                <div className="flex flex-wrap items-center gap-1.5">
                  <input
                    aria-label="Period covered start"
                    type="date"
                    readOnly={isIAP}
                    value={periodStart}
                    onChange={(event) => setPeriodStart(event.target.value)}
                    className={cn(
                      inputClassName,
                      "w-[155px]",
                      isIAP && "bg-slate-100 text-slate-500",
                    )}
                  />
                  <span className="text-xs text-slate-500">to</span>
                  <input
                    aria-label="Period covered end"
                    type="date"
                    readOnly={isIAP}
                    value={periodEnd}
                    onChange={(event) => setPeriodEnd(event.target.value)}
                    className={cn(
                      inputClassName,
                      "w-[155px]",
                      isIAP && "bg-slate-100 text-slate-500",
                    )}
                  />
                </div>
              </FieldRow>

              <div className="relative pt-3">
                <span className="absolute left-0 top-3 z-10 hidden w-[118px] -translate-y-1/2 bg-[#dceafd] pr-1 text-right text-xs font-medium text-[#27466f] sm:block">
                  Initial Schedule
                </span>
                <span className="mb-1 block text-xs font-medium text-[#27466f] sm:hidden">
                  Initial Schedule
                </span>
                <fieldset
                  aria-label="Initial Schedule"
                  className="ml-0 w-full max-w-[242px] rounded border border-[#a9c8dc] bg-[#d6e7f6] px-2.5 py-2 sm:ml-[54px]"
                >
                  <legend className="sr-only">Initial Schedule</legend>
                  <div className="space-y-1.5">
                    <label className="grid grid-cols-[62px_126px] items-center gap-2 text-[11px] text-[#49637d]">
                      <span className="text-right">Start Date</span>
                      <input
                        aria-label="Initial schedule start date"
                        type="date"
                        readOnly={isIAP}
                        value={startDate}
                        onChange={(event) => setStartDate(event.target.value)}
                        className={cn(
                          inputClassName,
                          "h-7 w-[126px] px-2",
                          isIAP && "bg-slate-100 text-slate-500",
                        )}
                      />
                    </label>
                    <label className="grid grid-cols-[62px_126px] items-center gap-2 text-[11px] text-[#49637d]">
                      <span className="text-right">End Date</span>
                      <input
                        aria-label="Initial schedule end date"
                        type="date"
                        readOnly={isIAP}
                        value={endDate}
                        onChange={(event) => setEndDate(event.target.value)}
                        className={cn(
                          inputClassName,
                          "h-7 w-[126px] px-2",
                          isIAP && "bg-slate-100 text-slate-500",
                        )}
                      />
                    </label>
                    <label className="grid grid-cols-[62px_126px] items-center gap-2 text-[11px] text-[#49637d]">
                      <span className="text-right">Duration</span>
                      <input
                        aria-label="Planned duration"
                        readOnly
                        value={
                          plannedDurationDays
                            ? `${plannedDurationDays} days`
                            : "Select dates"
                        }
                        className={cn(
                          inputClassName,
                          "h-7 w-[126px] px-2 bg-slate-100 text-slate-500",
                        )}
                      />
                    </label>
                  </div>
                </fieldset>
              </div>
            </SectionCard>

            {/* Section 4: Audit Team & Office Order */}
            <SectionCard title="Audit Team & Office Order" icon={MessageSquareText}>
              <FieldRow label="AEO No.">
                <input
                  aria-label="AEO Number"
                  readOnly
                  value={aeoNumber}
                  className={cn(inputClassName, "max-w-[190px] bg-slate-100 text-slate-500")}
                />
              </FieldRow>
              <FieldRow label="AEO Date">
                <input
                  aria-label="AEO Date"
                  type="date"
                  value={aeoDate}
                  onChange={(event) => setAeoDate(event.target.value)}
                  className={cn(inputClassName, "max-w-[190px]")}
                />
              </FieldRow>
              <FieldRow label="Department Head">
                <Select2
                  ariaLabel="Department Head"
                  value={departmentHeadId}
                  options={departmentHeadSelectOptions}
                  placeholder="[ Select Department Head ]"
                  searchPlaceholder="Search department head..."
                  allowClear
                  onChange={(val) => setDepartmentHeadId(val)}
                  className={select2ClassName}
                />
              </FieldRow>
              <FieldRow label="Team Leader">
                <Select2
                  ariaLabel="Team Leader"
                  value={teamLeaderId}
                  options={teamLeaderSelectOptions}
                  placeholder="[ Select Team Leader ]"
                  searchPlaceholder="Search team leader..."
                  allowClear
                  onChange={(val) => setTeamLeaderId(val)}
                  className={select2ClassName}
                />
              </FieldRow>
              <FieldRow label="Team Members" alignTop>
                <Select2Multi
                  selected={teamMemberIds}
                  options={teamMemberSelectOptions}
                  onChange={setTeamMemberIds}
                  placeholder="Select team members"
                  searchPlaceholder="Search team members..."
                  ariaLabel="Choose team members"
                />
              </FieldRow>
              <FieldRow label="Support Staff" alignTop>
                <Select2Multi
                  selected={supportStaffIds}
                  options={supportStaffSelectOptions}
                  onChange={setSupportStaffIds}
                  placeholder="Select support staff"
                  searchPlaceholder="Search support staff..."
                  ariaLabel="Choose support staff"
                />
              </FieldRow>
              <FieldRow label="AEO Document" alignTop>
                <div className="space-y-1.5">
                  <div className="flex items-center gap-2">
                    <label className="inline-flex h-6 cursor-pointer items-center rounded border border-[#a9b8c5] bg-[#f7f8f9] px-2.5 text-[11px] font-medium text-slate-700 shadow-sm hover:bg-white">
                      Upload File
                      <input
                        type="file"
                        accept=".pdf,.doc,.docx"
                        className="sr-only"
                        onChange={(event) =>
                          handleFile(event.target.files?.[0], setAeoFileName)
                        }
                      />
                    </label>
                    {aeoFileName && (
                      <span className="max-w-full truncate text-[11px] font-medium text-emerald-700">
                        {aeoFileName}
                      </span>
                    )}
                  </div>
                  <div
                    onDragOver={(event) => event.preventDefault()}
                    onDrop={(event) => handleDrop(event, setAeoFileName)}
                    className="flex min-h-[34px] flex-col items-center justify-center rounded border border-dashed border-[#a9c8dc] bg-[#eef7fc] px-2 py-1 text-center text-[10px] leading-[11px] text-[#7394ad]"
                  >
                    <span>{aeoFileName || "Click to upload or drag and drop"}</span>
                    <span>PDF, DOC, DOCX (Max 10 MB)</span>
                  </div>
                  <div className="flex flex-wrap items-center gap-2 pt-0.5">
                    <span className="text-[11px] text-[#27466f]">Download Template:</span>
                    <Button
                      type="button"
                      variant="outline"
                      onClick={() =>
                        showToast({
                          title: "Managed via AEO Workspace",
                          description:
                            "AEO order documents and official PDFs are generated and downloaded from the engagement's Prepare AEO Order workspace.",
                          tone: "info",
                        })
                      }
                      className="h-7 border-[#a9b8c5] bg-[#f7f8f9] px-3 text-[11px] font-normal text-slate-700 shadow-sm hover:bg-white"
                    >
                      AEO_Template.docx
                    </Button>
                  </div>
                </div>
              </FieldRow>
            </SectionCard>
          </div>
        )}
      </div>
    </AdminLayout>
  );
}

export default CreateAuditEngagementPage;
