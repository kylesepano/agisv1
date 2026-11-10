import {
  CalendarDays,
  ChartColumnIncreasing,
  CheckCheck,
  SearchCheck,
  ShieldCheck,
  UserRound,
} from "lucide-react";

export const moduleCards = [
  {
    title: "Internal Audit Planning (IAP)",
    value: "1",
    caption: "Active plans",
    action: "Open IAP",
    accent: "text-blue-700",
    iconWrap: "bg-blue-50 text-blue-600",
    surface: "from-[#d9e8fb] to-[#edf4ff]",
    border: "border-blue-100",
    icon: CalendarDays,
  },
  {
    title: "Audit Engagement Management (AEM)",
    value: "3",
    caption: "Active engagements",
    action: "Open AEM",
    accent: "text-emerald-700",
    iconWrap: "bg-emerald-50 text-emerald-600",
    surface: "from-[#dcf5e4] to-[#f3fff6]",
    border: "border-emerald-100",
    icon: ShieldCheck,
  },
  {
    title: "Audit Finding & Recommendation (AFR)",
    value: "0",
    caption: "Engagements reporting findings",
    action: "Open AFR",
    accent: "text-orange-700",
    iconWrap: "bg-orange-50 text-orange-600",
    surface: "from-[#ffe3c6] to-[#fff5ea]",
    border: "border-orange-100",
    icon: SearchCheck,
  },
  {
    title: "Compliance Management (CMS)",
    value: "0",
    caption: "Visible recommendations",
    action: "Open CMS",
    accent: "text-violet-700",
    iconWrap: "bg-violet-50 text-violet-600",
    surface: "from-[#ece4fb] to-[#f8f4ff]",
    border: "border-violet-100",
    icon: CheckCheck,
  },
  {
    title: "Audit Resource Management (ARMS)",
    value: "11",
    caption: "Active resources",
    action: "Open ARMIS",
    accent: "text-cyan-700",
    iconWrap: "bg-cyan-50 text-cyan-700",
    surface: "from-[#e1f5fb] to-[#f3fbff]",
    border: "border-cyan-100",
    icon: UserRound,
  },
  {
    title: "Audit Intelligence System (AIS)",
    value: "—",
    caption: "Not enabled",
    action: "Open AIS",
    accent: "text-amber-700",
    iconWrap: "bg-amber-50 text-amber-700",
    surface: "from-[#fff1b8] to-[#fffbe8]",
    border: "border-amber-100",
    icon: ChartColumnIncreasing,
  },
];

export const tasks = [
  ["Review activity and audit logs", "CORE-LOGS", "Open"],
  ["Review administrative reports", "CORE-REPORTS", "Open"],
] as const;

export const upcomingActivities = [
  ["Entrance Conference", "AEMS-2026-003 - BPLD", "Upcoming", "10:00 AM"],
  ["Exit Conference", "AEMS-2026-002 - BPLD", "Upcoming", "2:00 PM"],
] as const;

export const engagements = [
  ["AEMS-2026-003", "Business Permits and Licensing Division", "Planning", "25%", "Medium"],
  ["AEMS-2026-002", "Business Permits and Licensing Division", "Planning", "25%", "Medium"],
  ["AEMS-2026-001", "Business Permits and Licensing Division", "Draft", "15%", "Medium"],
] as const;

export const engagementStatus = [
  { name: "Draft", value: 1, color: "#3b82f6" },
  { name: "Planning", value: 2, color: "#22c55e" },
];

export const recommendationStatus = [
  { name: "None", value: 0, color: "#cbd5e1" },
];

export const overdueRecommendations: Array<[string, string, string]> = [];

export const quickActions = [
  ["New Audit Engagement", "bg-emerald-100 text-emerald-700"],
  ["New Finding", "bg-orange-100 text-orange-700"],
  ["Search Documents", "bg-blue-100 text-blue-700"],
  ["Generate Report", "bg-cyan-100 text-cyan-700"],
] as const;
