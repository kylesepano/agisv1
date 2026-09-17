import { AlertTriangle, Archive, CheckCircle2, LogOut } from "lucide-react";
import Modal from "./Modal";

const tones = {
  danger: {
    icon: Archive,
    iconClass: "bg-red-100 text-red-700",
    buttonClass: "bg-red-600 hover:bg-red-700",
  },
  warning: {
    icon: AlertTriangle,
    iconClass: "bg-amber-100 text-amber-700",
    buttonClass: "bg-amber-600 hover:bg-amber-700",
  },
  logout: {
    icon: LogOut,
    iconClass: "bg-slate-200 text-slate-700",
    buttonClass: "bg-slate-800 hover:bg-slate-900",
  },
  primary: {
    icon: CheckCircle2,
    iconClass: "bg-sky-100 text-sky-700",
    buttonClass: "bg-sky-700 hover:bg-sky-800",
  },
};

export default function ConfirmDialog({
  open,
  title,
  description,
  confirmLabel = "Confirm",
  cancelLabel = "Cancel",
  tone = "primary",
  busy = false,
  children,
  onCancel,
  onConfirm,
}) {
  const style = tones[tone] ?? tones.primary;
  const Icon = style.icon;

  return (
    <Modal
      footer={
        <>
          <button
            className="h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700 transition hover:bg-slate-100 disabled:opacity-60"
            disabled={busy}
            onClick={onCancel}
            type="button"
          >
            {cancelLabel}
          </button>
          <button
            className={`h-10 rounded-lg px-5 text-sm font-bold text-white transition disabled:cursor-not-allowed disabled:opacity-60 ${style.buttonClass}`}
            disabled={busy}
            onClick={onConfirm}
            type="button"
          >
            {busy ? "Please wait..." : confirmLabel}
          </button>
        </>
      }
      onClose={() => !busy && onCancel()}
      open={open}
      size="sm"
      title={title}
    >
      <div className="flex items-start gap-3">
        <span
          className={`grid h-11 w-11 shrink-0 place-items-center rounded-xl ${style.iconClass}`}
        >
          <Icon size={21} />
        </span>
        <div className="min-w-0 text-sm leading-6 text-slate-600">
          {description && <p>{description}</p>}
          {children}
        </div>
      </div>
    </Modal>
  );
}
