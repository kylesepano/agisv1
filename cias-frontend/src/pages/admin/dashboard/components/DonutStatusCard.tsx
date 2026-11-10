import { Cell, Pie, PieChart, ResponsiveContainer, Tooltip } from "recharts";
import { DashboardPanel } from "./DashboardPanel";

type DonutDatum = {
  name: string;
  value: number;
  color: string;
};

type DonutStatusCardProps = {
  title: string;
  action: string;
  data: DonutDatum[];
  total: number;
};

function DonutTooltip({
  active,
  payload,
  total,
}: {
  active?: boolean;
  payload?: Array<{ payload: DonutDatum; value: number }>;
  total: number;
}) {
  if (!active || !payload?.length) {
    return null;
  }

  const item = payload[0];
  const value = item.value;
  const percent = Math.round((value / total) * 100);

  return (
    <div
      className="relative z-10 rounded-xl border border-slate-200 bg-white px-3 py-2 shadow-[0_12px_30px_rgba(15,23,42,0.12)]"
      style={{ backgroundColor: "#ffffff", opacity: 1 }}
    >
      <p className="text-[11px] font-semibold text-slate-800">
        {item.payload.name}
      </p>
      <p className="mt-0.5 text-[11px] text-slate-600">
        {value} ({percent}%)
      </p>
    </div>
  );
}

export function DonutStatusCard({
  title,
  action,
  data,
  total,
}: DonutStatusCardProps) {
  return (
    <DashboardPanel title={title} action={action}>
      <div className="flex h-full flex-col justify-center gap-3 sm:items-center lg:flex-row lg:items-center">
        <div className="relative mx-auto h-32 w-full max-w-[160px] lg:mx-0">
          <ResponsiveContainer width="100%" height="100%">
            <PieChart>
              <Pie
                data={data}
                dataKey="value"
                innerRadius={31}
                outerRadius={50}
                paddingAngle={1}
                strokeWidth={0}
              >
                {data.map((item) => (
                  <Cell key={item.name} fill={item.color} />
                ))}
              </Pie>
              <Tooltip
                content={<DonutTooltip total={total} />}
                cursor={false}
                wrapperStyle={{ zIndex: 30 }}
              />
            </PieChart>
          </ResponsiveContainer>
          <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
            <p className="text-[1.7rem] font-bold leading-none text-slate-800">{total}</p>
            <p className="text-[11px] text-slate-500">Total</p>
          </div>
        </div>

        <div className="flex-1 space-y-2.5">
          {data.map((item) => (
            <div key={item.name} className="flex items-center justify-between gap-3 text-[11px] sm:text-[12px]">
              <div className="flex items-center gap-2 text-slate-600">
                <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: item.color }} />
                <span>{item.name}</span>
              </div>
              <span className="font-semibold text-slate-700">
                {item.value} ({Math.round((item.value / total) * 100)}%)
              </span>
            </div>
          ))}
        </div>
      </div>
    </DashboardPanel>
  );
}
