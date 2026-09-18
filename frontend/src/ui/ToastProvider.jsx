import { useCallback, useMemo, useState } from "react";
import {
  CheckCircle2,
  CircleAlert,
  Info,
  TriangleAlert,
  X,
} from "lucide-react";
import { ToastContext } from "./toast-context";

const appearances = {
  success: {
    icon: CheckCircle2,
    className: "border-emerald-200 bg-emerald-50 text-emerald-900",
  },
  error: {
    icon: CircleAlert,
    className: "border-red-200 bg-red-50 text-red-900",
  },
  warning: {
    icon: TriangleAlert,
    className: "border-amber-200 bg-amber-50 text-amber-900",
  },
  info: {
    icon: Info,
    className: "border-sky-200 bg-sky-50 text-sky-900",
  },
};

export default function ToastProvider({ children }) {
  const [toasts, setToasts] = useState([]);

  const remove = useCallback((id) => {
    setToasts((current) => current.filter((toast) => toast.id !== id));
  }, []);

  const notify = useCallback(
    (message, type = "info") => {
      const id = crypto.randomUUID();
      setToasts((current) => [...current, { id, message, type }]);
      window.setTimeout(() => remove(id), 4200);
      return id;
    },
    [remove],
  );

  const value = useMemo(
    () => ({
      notify,
      success: (message) => notify(message, "success"),
      error: (message) => notify(message, "error"),
      warning: (message) => notify(message, "warning"),
      info: (message) => notify(message, "info"),
    }),
    [notify],
  );

  return (
    <ToastContext.Provider value={value}>
      {children}
      <div
        aria-live="polite"
        className="pointer-events-none fixed bottom-5 right-5 z-[120] grid w-[min(24rem,calc(100vw-2rem))] gap-2"
      >
        {toasts.map((toast) => {
          const appearance = appearances[toast.type] ?? appearances.info;
          const ToastIcon = appearance.icon;

          return (
            <div
              className={`pointer-events-auto flex items-start gap-3 rounded-xl border px-4 py-3 text-sm shadow-xl ${appearance.className}`}
              key={toast.id}
              role="status"
            >
              <ToastIcon className="mt-0.5 shrink-0" size={19} />
              <span className="min-w-0 flex-1 leading-5">{toast.message}</span>
              <button
                aria-label="Dismiss notification"
                className="grid h-6 w-6 shrink-0 place-items-center rounded transition hover:bg-black/5"
                onClick={() => remove(toast.id)}
                type="button"
              >
                <X size={15} />
              </button>
            </div>
          );
        })}
      </div>
    </ToastContext.Provider>
  );
}
