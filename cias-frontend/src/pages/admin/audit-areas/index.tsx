import { useMemo, useState } from "react";
import { AdminLayout } from "../../../layouts/admin/AdminLayout";
import { useToast } from "../../../components/ui/toast";
import { localStore } from "../../../lib/localStore";
import type { AuditAreaRecord } from "./audit-areas-types";
import {
  AuditAreaDeleteConfirmModal,
  AuditAreaFormModal,
  type AuditAreaFormValues,
  AuditAreasFilters,
  AuditAreasHeader,
  AuditAreasStats,
  AuditAreasTable,
} from "./components";

export function AdminAuditAreasPage() {
  const { showToast } = useToast();
  const [refreshTick, setRefreshTick] = useState(0);

  // Modals state
  const [isAreaModalOpen, setIsAreaModalOpen] = useState(false);
  const [editingArea, setEditingArea] = useState<AuditAreaRecord | null>(null);

  const [deletingArea, setDeletingArea] = useState<AuditAreaRecord | null>(null);
  const [referencingEngagements, setReferencingEngagements] = useState<string[]>([]);
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);

  // Selected area for right-rail detail panel
  const [selectedAreaId, setSelectedAreaId] = useState<string | null>(null);

  // Filters
  const [search, setSearch] = useState("");
  const [officeFilter, setOfficeFilter] = useState("All Offices");
  const [categoryFilter, setCategoryFilter] = useState("All Categories");
  const [statusFilter, setStatusFilter] = useState("All Statuses");

  // Read live from localStore
  const offices = useMemo(() => {
    return localStore.getOffices();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [refreshTick]);

  const auditAreas = useMemo(() => {
    return localStore.getAuditAreas();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [refreshTick]);

  const filteredRecords = useMemo(() => {
    const normalizedSearch = search.trim().toLowerCase();

    return auditAreas.filter((record) => {
      const areaId = record.auditAreaId || record.code || "";
      const areaName = record.auditAreaName || record.name || "";
      const officeName = record.officeName || "";
      const description = record.description || "";
      const category = record.auditAreaCategory || "";

      const matchesSearch =
        normalizedSearch.length === 0 ||
        [areaId, areaName, officeName, description, category].some((val) =>
          val.toLowerCase().includes(normalizedSearch),
        );

      const matchesOffice =
        officeFilter === "All Offices" ||
        record.officeId === officeFilter ||
        record.officeName === officeFilter;

      const matchesCategory =
        categoryFilter === "All Categories" || record.auditAreaCategory === categoryFilter;

      const matchesStatus =
        statusFilter === "All Statuses" || record.status === statusFilter;

      return matchesSearch && matchesOffice && matchesCategory && matchesStatus;
    });
  }, [auditAreas, categoryFilter, officeFilter, search, statusFilter]);

  // Handlers
  const handleOpenAdd = () => {
    setEditingArea(null);
    setIsAreaModalOpen(true);
  };

  const handleOpenEdit = (area: AuditAreaRecord) => {
    setEditingArea(area);
    setIsAreaModalOpen(true);
  };

  const handleSubmitArea = (values: AuditAreaFormValues): { success: boolean; error?: string } => {
    try {
      const saved = localStore.saveAuditArea({
        ...values,
        auditAreaId: editingArea?.auditAreaId || editingArea?.code || values.auditAreaId,
      });

      setRefreshTick((t) => t + 1);
      setIsAreaModalOpen(false);
      setEditingArea(null);

      showToast({
        title: editingArea ? "Audit Area Updated" : "Audit Area Registered",
        description: `${saved.auditAreaName} (${saved.auditAreaId}) registered under ${saved.officeName}.`,
        tone: "success",
      });

      return { success: true };
    } catch (err) {
      console.error(err);
      return { success: false, error: "Failed to save audit area record." };
    }
  };

  const handleOpenDeactivate = (area: AuditAreaRecord) => {
    if (area.status === "Inactive") {
      // Reactivate
      localStore.saveAuditArea({
        ...area,
        status: "Active",
      });
      setRefreshTick((t) => t + 1);
      showToast({
        title: "Audit Area Reactivated",
        description: `${area.auditAreaName || area.name} is now Active and available for engagement scoping.`,
        tone: "success",
      });
      return;
    }

    // Check references in active engagements
    const targetNames = [
      (area.auditAreaName || area.name || "").toLowerCase().trim(),
      (area.auditAreaId || area.code || "").toLowerCase().trim(),
    ].filter(Boolean);

    const engagements = localStore.getEngagements();
    const referencing = engagements.filter((e) => {
      const areaMatch = targetNames.includes((e.auditArea || "").toLowerCase().trim());
      const subjectMatch = targetNames.includes((e.subject || "").toLowerCase().trim());
      return areaMatch || subjectMatch;
    });
    const referencingCodes = referencing.map((e) => `${e.code} — ${e.auditArea || e.subject}`);

    setDeletingArea(area);
    setReferencingEngagements(referencingCodes);
    setIsDeleteModalOpen(true);
  };

  const handleConfirmDeactivate = () => {
    if (!deletingArea) return;

    localStore.deleteAuditArea(deletingArea.auditAreaId || deletingArea.code || "", "soft");
    setRefreshTick((t) => t + 1);
    setIsDeleteModalOpen(false);
    setDeletingArea(null);

    showToast({
      title: "Audit Area Deactivated",
      description: `${deletingArea.auditAreaName || deletingArea.name} has been set to Inactive (soft deleted).`,
      tone: "info",
    });
  };

  const handleResetDefaults = () => {
    const confirmed = window.confirm(
      "Reset the Audit Area Registry back to master seed defaults? Any custom audit areas will be replaced.",
    );
    if (!confirmed) return;

    localStore.resetAuditAreasToDefault();
    setRefreshTick((t) => t + 1);
    setSelectedAreaId(null);
    showToast({
      title: "Audit Area Registry Reset",
      description: "Restored all 43 canonical CDO audit areas with verified unique IDs.",
      tone: "success",
    });
  };

  const handleResetFilters = () => {
    setSearch("");
    setOfficeFilter("All Offices");
    setCategoryFilter("All Categories");
    setStatusFilter("All Statuses");
  };

  return (
    <AdminLayout>
      <div className="space-y-2.5 pb-8 overflow-x-hidden">
        {/* Page Header */}
        <AuditAreasHeader
          onAddArea={handleOpenAdd}
          onResetDefaults={handleResetDefaults}
        />

        {/* 4 Tinted Metric Stat Cards */}
        <AuditAreasStats records={auditAreas} />

        {/* Clean Filter Toolbar */}
        <AuditAreasFilters
          search={search}
          officeFilter={officeFilter}
          categoryFilter={categoryFilter}
          statusFilter={statusFilter}
          offices={offices}
          onSearchChange={setSearch}
          onOfficeChange={setOfficeFilter}
          onCategoryChange={setCategoryFilter}
          onStatusChange={setStatusFilter}
          onResetFilters={handleResetFilters}
        />

        {/* Master Table + Right-Rail Details Panel */}
        <AuditAreasTable
          records={filteredRecords}
          selectedAreaId={selectedAreaId}
          onSelectArea={(area) =>
            setSelectedAreaId(area.auditAreaId || area.code || null)
          }
          onEdit={handleOpenEdit}
          onDeactivate={handleOpenDeactivate}
        />

        {/* Create / Edit Modal */}
        <AuditAreaFormModal
          isOpen={isAreaModalOpen}
          isEditing={Boolean(editingArea)}
          defaultValues={editingArea}
          existingAreas={auditAreas}
          offices={offices}
          onClose={() => {
            setIsAreaModalOpen(false);
            setEditingArea(null);
          }}
          onSubmit={handleSubmitArea}
        />

        {/* Soft Delete & Reference Warning Modal */}
        <AuditAreaDeleteConfirmModal
          isOpen={isDeleteModalOpen}
          area={deletingArea}
          referencingEngagements={referencingEngagements}
          onClose={() => {
            setIsDeleteModalOpen(false);
            setDeletingArea(null);
          }}
          onConfirmDeactivate={handleConfirmDeactivate}
        />
      </div>
    </AdminLayout>
  );
}

export default AdminAuditAreasPage;
