import { useState } from "react";
import { AdminLayout } from "../../../layouts/admin/AdminLayout";
import { riskLevelRecords } from "./risk-management-data";
import {
  RiskLevelFormModal,
  RiskLevelsFilters,
  RiskLevelsHeader,
  RiskLevelsInfoCard,
  RiskLevelsStats,
  RiskLevelsTable,
} from "./components";

export function AdminRiskManagementPage() {
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState("All Statuses");
  const [isRiskLevelModalOpen, setIsRiskLevelModalOpen] = useState(false);

  const filteredRecords = riskLevelRecords.filter((record) => {
    const searchValue = search.trim().toLowerCase();
    const matchesSearch =
      searchValue.length === 0 ||
      `${record.code} ${record.name} ${record.description}`
        .toLowerCase()
        .includes(searchValue);
    const matchesStatus =
      statusFilter === "All Statuses" || record.status === statusFilter;

    return matchesSearch && matchesStatus;
  });

  return (
    <AdminLayout>
      <div className="space-y-3 overflow-x-hidden">
        <RiskLevelsHeader
          onAddRiskLevel={() => setIsRiskLevelModalOpen(true)}
        />
        <RiskLevelsStats records={riskLevelRecords} />
        <div className="space-y-0 rounded-2xl border border-slate-200 bg-white/95 shadow-sm">
          <RiskLevelsFilters
            search={search}
            statusFilter={statusFilter}
            onSearchChange={setSearch}
            onStatusChange={setStatusFilter}
          />
          <RiskLevelsTable records={filteredRecords} />
        </div>
        <RiskLevelsInfoCard />
        <RiskLevelFormModal
          isOpen={isRiskLevelModalOpen}
          onClose={() => setIsRiskLevelModalOpen(false)}
          onSubmit={() => setIsRiskLevelModalOpen(false)}
        />
      </div>
    </AdminLayout>
  );
}
