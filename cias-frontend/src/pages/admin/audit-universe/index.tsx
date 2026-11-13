import { useState } from "react";
import { DetailsDrawer } from "../../../components/shared/DetailsDrawer";
import { AdminLayout } from "../../../layouts/admin/AdminLayout";
import { auditUniverseRecords } from "./audit-universe-data";
import {
  AuditUniverseDetailsContent,
  AuditUniverseFilters,
  AuditUniverseHeader,
  AuditUniverseInsights,
  AuditUniverseStats,
  AuditUniverseTable,
} from "./components";

function parseAuditDate(value: string) {
  return new Date(`${value}T00:00:00`);
}

export function AdminAuditUniversePage() {
  const [search, setSearch] = useState("");
  const [categoryFilter, setCategoryFilter] = useState("All Categories");
  const [departmentFilter, setDepartmentFilter] = useState("All Offices");
  const [riskFilter, setRiskFilter] = useState("All Risk Levels");
  const [statusFilter, setStatusFilter] = useState("All Statuses");
  const [selectedAuditCode, setSelectedAuditCode] = useState<string | null>(
    null,
  );

  const filteredRecords = auditUniverseRecords.filter((record) => {
    const matchesSearch =
      search.trim().length === 0 ||
      `${record.code} ${record.name}`
        .toLowerCase()
        .includes(search.toLowerCase());
    const matchesCategory =
      categoryFilter === "All Categories" || record.category === categoryFilter;
    const matchesDepartment =
      departmentFilter === "All Offices" ||
      record.department === departmentFilter;
    const matchesRisk =
      riskFilter === "All Risk Levels" || record.riskLevel === riskFilter;
    const matchesStatus =
      statusFilter === "All Statuses" || record.status === statusFilter;

    return (
      matchesSearch &&
      matchesCategory &&
      matchesDepartment &&
      matchesRisk &&
      matchesStatus
    );
  });

  const selectedAudit =
    filteredRecords.find((record) => record.code === selectedAuditCode) ??
    auditUniverseRecords.find((record) => record.code === selectedAuditCode) ??
    null;

  const clearFilters = () => {
    setSearch("");
    setCategoryFilter("All Categories");
    setDepartmentFilter("All Offices");
    setRiskFilter("All Risk Levels");
    setStatusFilter("All Statuses");
  };

  const categoryCounts = filteredRecords.reduce<Record<string, number>>(
    (counts, record) => {
      counts[record.category] = (counts[record.category] ?? 0) + 1;
      return counts;
    },
    {},
  );

  const totalRecords = filteredRecords.length;
  const highRiskCount = filteredRecords.filter(
    (record) => record.riskLevel === "High",
  ).length;
  const mediumRiskCount = filteredRecords.filter(
    (record) => record.riskLevel === "Medium",
  ).length;
  const lowRiskCount = filteredRecords.filter(
    (record) => record.riskLevel === "Low",
  ).length;
  const systemsCount = filteredRecords.filter(
    (record) => record.category === "System",
  ).length;
  const officeCount = new Set(
    filteredRecords.map((record) => record.department),
  ).size;
  const now = new Date();
  const nextYear = new Date(now);
  nextYear.setFullYear(now.getFullYear() + 1);
  const upcomingAudits = [...filteredRecords]
    .filter((record) => {
      const nextAuditDate = parseAuditDate(record.nextAudit);
      return nextAuditDate >= now && nextAuditDate <= nextYear;
    })
    .sort(
      (left, right) =>
        parseAuditDate(left.nextAudit).getTime() -
        parseAuditDate(right.nextAudit).getTime(),
    );

  const statCards = [
    {
      title: "Total Auditable Units",
      value: String(totalRecords),
      subtitle: "All active records",
      change: "+12%",
      tone: "text-emerald-600",
      iconClassName: "bg-blue-100 text-blue-600",
    },
    {
      title: "Departments",
      value: String(officeCount),
      subtitle: "Active departments",
      change: "+5%",
      tone: "text-emerald-600",
      iconClassName: "bg-emerald-100 text-emerald-600",
    },
    {
      title: "Systems",
      value: String(systemsCount),
      subtitle: "Active systems",
      change: "+8%",
      tone: "text-emerald-600",
      iconClassName: "bg-violet-100 text-violet-600",
    },
    {
      title: "High Risk Items",
      value: String(highRiskCount),
      subtitle: "High risk auditable units",
      change: "+8%",
      tone: "text-rose-600",
      iconClassName: "bg-rose-100 text-rose-600",
    },
    {
      title: "Upcoming Audits (Next 12 Mos.)",
      value: String(upcomingAudits.length),
      subtitle: "Scheduled for audit",
      change: "This year",
      tone: "text-amber-600",
      iconClassName: "bg-amber-100 text-amber-600",
    },
  ];

  return (
    <AdminLayout>
      <div className="space-y-3 overflow-x-hidden">
        <AuditUniverseHeader />
        <AuditUniverseStats stats={statCards} />
        <div className="grid gap-3 xl:grid-cols-[minmax(0,1.7fr)_minmax(260px,0.68fr)]">
          <div className="min-w-0 space-y-3">
            <AuditUniverseFilters
              search={search}
              categoryFilter={categoryFilter}
              departmentFilter={departmentFilter}
              riskFilter={riskFilter}
              statusFilter={statusFilter}
              totalRecords={totalRecords}
              highRiskCount={highRiskCount}
              mediumRiskCount={mediumRiskCount}
              lowRiskCount={lowRiskCount}
              onSearchChange={setSearch}
              onCategoryChange={setCategoryFilter}
              onDepartmentChange={setDepartmentFilter}
              onRiskChange={setRiskFilter}
              onStatusChange={setStatusFilter}
              onClear={clearFilters}
            />
            <AuditUniverseTable
              records={filteredRecords}
              selectedAuditCode={selectedAuditCode}
              onView={setSelectedAuditCode}
            />
          </div>

          <div className="min-w-0">
            <AuditUniverseInsights
              totalRecords={totalRecords}
              categoryCounts={categoryCounts}
              upcomingAudits={upcomingAudits}
            />
          </div>
        </div>

        <DetailsDrawer
          className="max-w-[640px]"
          title="Audit Universe Details"
          isOpen={Boolean(selectedAudit)}
          onClose={() => setSelectedAuditCode(null)}
        >
          {selectedAudit ? (
            <AuditUniverseDetailsContent audit={selectedAudit} />
          ) : null}
        </DetailsDrawer>
      </div>
    </AdminLayout>
  );
}
