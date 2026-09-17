import {
  AlertTriangle,
  CheckCircle2,
  CircleDot,
  Clock3,
  FilePenLine,
  RotateCcw,
} from "lucide-react";
import StatusBadge from "../ui/StatusBadge";
import { labelFor } from "./cms-format";

export function CmsStatusBadge({ status }) {
  const completed = ["CLOSED", "ACCEPTED_RISK"].includes(status);
  const cancelled = status === "CANCELLED";
  const Icon = completed ? CheckCircle2 : cancelled ? AlertTriangle : CircleDot;

  return (
    <StatusBadge tone={completed ? "success" : cancelled ? "danger" : "info"}>
      <Icon className="mr-1" size={13} aria-hidden="true" />
      {labelFor(status, "Unknown")}
    </StatusBadge>
  );
}

export function CmsRiskBadge({ risk }) {
  const code = risk?.code?.toUpperCase();
  const tone =
    code === "CRITICAL" || code === "VERY_HIGH"
      ? "danger"
      : code === "HIGH"
        ? "warning"
        : code === "LOW"
          ? "success"
          : "info";

  return <StatusBadge tone={tone}>{risk?.label || labelFor(code)}</StatusBadge>;
}

export function CmsOverdueBadge({ overdue }) {
  if (!overdue) return null;

  return (
    <StatusBadge tone="danger">
      <AlertTriangle className="mr-1" size={13} aria-hidden="true" />
      Overdue
    </StatusBadge>
  );
}

export function CmsActionPlanStatusBadge({ status }) {
  const appearances = {
    DRAFT: { icon: FilePenLine, tone: "info" },
    SUBMITTED: { icon: Clock3, tone: "warning" },
    UNDER_REVIEW: { icon: CircleDot, tone: "warning" },
    RETURNED: { icon: RotateCcw, tone: "danger" },
    ACCEPTED: { icon: CheckCircle2, tone: "success" },
  };
  const appearance = appearances[status] ?? {
    icon: CircleDot,
    tone: "info",
  };
  const Icon = appearance.icon;

  return (
    <StatusBadge tone={appearance.tone}>
      <Icon aria-hidden="true" className="mr-1" size={13} />
      {labelFor(status, "Unknown")}
    </StatusBadge>
  );
}

export function CmsProgressStatusBadge({ status }) {
  const appearances = {
    DRAFT: { icon: FilePenLine, tone: "info", label: "Draft" },
    SUBMITTED: { icon: Clock3, tone: "warning", label: "Submitted" },
    UNDER_REVIEW: { icon: CircleDot, tone: "warning", label: "Under review" },
    RETURNED: { icon: RotateCcw, tone: "danger", label: "Returned" },
    RECORDED: {
      icon: CheckCircle2,
      tone: "success",
      label: "Recorded for monitoring",
    },
  };
  const appearance = appearances[status] ?? {
    icon: CircleDot,
    tone: "info",
    label: labelFor(status, "Unknown"),
  };
  const Icon = appearance.icon;

  return (
    <StatusBadge tone={appearance.tone}>
      <Icon aria-hidden="true" className="mr-1" size={13} />
      {appearance.label}
    </StatusBadge>
  );
}

export function CmsValidationStatusBadge({ status }) {
  const appearances = {
    DRAFT: { icon: FilePenLine, tone: "info", label: "Draft" },
    SUBMITTED: { icon: Clock3, tone: "warning", label: "Submitted" },
    UNDER_REVIEW: { icon: CircleDot, tone: "warning", label: "Under review" },
    RETURNED: { icon: RotateCcw, tone: "danger", label: "Returned" },
    FINALIZED: { icon: CheckCircle2, tone: "success", label: "Finalized" },
  };
  const appearance = appearances[status] ?? {
    icon: CircleDot,
    tone: "info",
    label: labelFor(status, "Unknown"),
  };
  const Icon = appearance.icon;
  return (
    <StatusBadge tone={appearance.tone}>
      <Icon aria-hidden="true" className="mr-1" size={13} />
      {appearance.label}
    </StatusBadge>
  );
}

export function CmsValidationConclusionBadge({ conclusion }) {
  const appearances = {
    NOT_IMPLEMENTED: ["warning", "Not Implemented"],
    PARTIALLY_IMPLEMENTED: ["info", "Partially Implemented"],
    IMPLEMENTED: ["success", "Implemented"],
    INADEQUATE_BASIS: ["danger", "Inadequate Basis"],
  };
  const [tone, label] = appearances[conclusion] ?? [
    "inactive",
    labelFor(conclusion, "Not concluded"),
  ];
  return <StatusBadge tone={tone}>{label}</StatusBadge>;
}

export function CmsValidationItemConclusionBadge({ conclusion }) {
  const appearances = {
    SATISFIED: ["success", "Satisfied"],
    PARTIALLY_SATISFIED: ["info", "Partially Satisfied"],
    NOT_SATISFIED: ["danger", "Not Satisfied"],
    INADEQUATE_BASIS: ["warning", "Inadequate Basis"],
    NOT_APPLICABLE: ["inactive", "Not Applicable"],
  };
  const [tone, label] = appearances[conclusion] ?? [
    "inactive",
    labelFor(conclusion, "Not concluded"),
  ];
  return <StatusBadge tone={tone}>{label}</StatusBadge>;
}
