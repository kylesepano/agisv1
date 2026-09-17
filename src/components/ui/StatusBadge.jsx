const styles = {
  active: "bg-emerald-100 text-emerald-700 ring-emerald-600/20",
  inactive: "bg-slate-100 text-slate-600 ring-slate-500/20",
  success: "bg-emerald-100 text-emerald-700 ring-emerald-600/20",
  warning: "bg-amber-100 text-amber-700 ring-amber-600/20",
  danger: "bg-red-100 text-red-700 ring-red-600/20",
  info: "bg-sky-100 text-sky-700 ring-sky-600/20",
};

export default function StatusBadge({ children, label, tone = "info" }) {
  return (
    <span
      className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold ring-1 ring-inset ${styles[tone] ?? styles.info}`}
    >
      {children ?? label}
    </span>
  );
}
