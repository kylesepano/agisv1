import {
  CheckCircle2,
  Info,
  TriangleAlert,
  X,
  type LucideIcon,
} from "lucide-react";
import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useRef,
  useState,
  type ReactNode,
} from "react";
import { cn } from "../../../utils/helpers";

type ToastTone = "info" | "success" | "warning";

type ToastInput = {
  title: string;
  description: string;
  tone?: ToastTone;
};

type ToastItem = ToastInput & {
  id: number;
};

type ToastContextValue = {
  showToast: (toast: ToastInput) => void;
};

const toastToneClasses: Record<
  ToastTone,
  {
    icon: LucideIcon;
    iconWrap: string;
  }
> = {
  info: {
    icon: Info,
    iconWrap: "bg-blue-100 text-blue-700",
  },
  success: {
    icon: CheckCircle2,
    iconWrap: "bg-emerald-100 text-emerald-700",
  },
  warning: {
    icon: TriangleAlert,
    iconWrap: "bg-amber-100 text-amber-700",
  },
};

const ToastContext = createContext<ToastContextValue | null>(null);

export function ToastProvider({ children }: { children: ReactNode }) {
  const [toasts, setToasts] = useState<ToastItem[]>([]);
  const idRef = useRef(0);

  const removeToast = useCallback((id: number) => {
    setToasts((current) => current.filter((toast) => toast.id !== id));
  }, []);

  const showToast = useCallback((toast: ToastInput) => {
    const id = ++idRef.current;
    const nextToast: ToastItem = {
      id,
      tone: toast.tone ?? "info",
      ...toast,
    };

    setToasts((current) => [...current.slice(-2), nextToast]);
  }, [removeToast]);

  const value = useMemo(() => ({ showToast }), [showToast]);

  return (
    <ToastContext.Provider value={value}>
      {children}
      <ToastViewport toasts={toasts} onClose={removeToast} />
    </ToastContext.Provider>
  );
}

export function useToast() {
  const context = useContext(ToastContext);

  if (!context) {
    throw new Error("useToast must be used within a ToastProvider.");
  }

  return context;
}

function ToastViewport({
  toasts,
  onClose,
}: {
  toasts: ToastItem[];
  onClose: (id: number) => void;
}) {
  return (
    <div className="pointer-events-none fixed right-4 top-4 z-[100] flex w-[min(92vw,360px)] flex-col gap-3">
      {toasts.map((toast) => (
        <Toast key={toast.id} toast={toast} onClose={() => onClose(toast.id)} />
      ))}
    </div>
  );
}

function Toast({
  toast,
  onClose,
}: {
  toast: ToastItem;
  onClose: () => void;
}) {
  const tone = toast.tone ?? "info";
  const { icon: Icon, iconWrap } = toastToneClasses[tone];
  const [isLeaving, setIsLeaving] = useState(false);
  const closeTimeoutRef = useRef<number | null>(null);
  const removeTimeoutRef = useRef<number | null>(null);

  const handleClose = useCallback(() => {
    setIsLeaving(true);

    if (removeTimeoutRef.current) {
      window.clearTimeout(removeTimeoutRef.current);
    }

    removeTimeoutRef.current = window.setTimeout(() => {
      onClose();
    }, 220);
  }, [onClose]);

  useEffect(() => {
    closeTimeoutRef.current = window.setTimeout(() => {
      handleClose();
    }, 3600);

    return () => {
      if (closeTimeoutRef.current) {
        window.clearTimeout(closeTimeoutRef.current);
      }

      if (removeTimeoutRef.current) {
        window.clearTimeout(removeTimeoutRef.current);
      }
    };
  }, [handleClose]);

  return (
    <div
      className={cn(
        "pointer-events-auto rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-[0_18px_48px_rgba(15,23,42,0.18)] backdrop-blur",
        isLeaving
          ? "animate-[toast-slide-out_220ms_ease-in_forwards]"
          : "animate-[toast-slide-in_240ms_ease-out]",
      )}
    >
      <div className="flex items-start gap-3">
        <div className={cn("flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl", iconWrap)}>
          <Icon size={18} strokeWidth={2.2} />
        </div>

        <div className="min-w-0 flex-1">
          <p className="text-sm font-semibold text-slate-900">{toast.title}</p>
          <p className="mt-1 text-sm leading-5 text-slate-600">{toast.description}</p>
        </div>

        <button
          aria-label="Dismiss toast"
          className="grid h-8 w-8 shrink-0 place-items-center rounded-xl text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
          type="button"
          onClick={handleClose}
        >
          <X size={16} />
        </button>
      </div>
    </div>
  );
}
