import { useMemo, useState } from "react";
import { AdminLayout } from "../../../layouts/admin/AdminLayout";
import { useToast } from "../../../components/ui/toast";
import { localStore } from "../../../lib/localStore";
import type { AuditFocusRecord } from "./audit-focuses-types";
import {
  AuditFocusDeleteConfirmModal,
  AuditFocusFormModal,
  type AuditFocusFormValues,
  AuditFocusesFilters,
  AuditFocusesHeader,
  AuditFocusesStats,
  AuditFocusesTable,
} from "./components";

export function AdminAuditFocusesPage() {
  const { showToast } = useToast();
  const [refreshTick, setRefreshTick] = useState(0);

  // Modals state
  const [isFocusModalOpen, setIsFocusModalOpen] = useState(false);
  const [editingFocus, setEditingFocus] = useState<AuditFocusRecord | null>(null);

  const [deletingFocus, setDeletingFocus] = useState<AuditFocusRecord | null>(null);
  const [referencingEngagements, setReferencingEngagements] = useState<string[]>([]);
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);

  // Selected focus for right-rail detail panel
  const [selectedFocusId, setSelectedFocusId] = useState<string | null>(null);

  // Filters
  const [search, setSearch] = useState("");
  const [officeFilter, setOfficeFilter] = useState("All Offices");
  const [areaFilter, setAreaFilter] = useState("All Areas");
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

  const auditFocuses = useMemo(() => {
    return localStore.getAuditFocuses();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [refreshTick]);

  const filteredRecords = useMemo(() => {
    const normalizedSearch = search.trim().toLowerCase();

    return auditFocuses.filter((record) => {
      const focusId = record.auditFocusId || record.code || "";
      const focusName = record.auditFocusName || record.name || "";
      const areaName = record.auditAreaName || "";
      const areaId = record.auditAreaId || "";
      const officeName = record.officeName || "";
      const description = record.description || "";

      const matchesSearch =
        normalizedSearch.length === 0 ||
        [focusId, focusName, areaName, areaId, officeName, description].some((val) =>
          val.toLowerCase().includes(normalizedSearch),
        );

      const matchesOffice =
        officeFilter === "All Offices" ||
        record.officeId === officeFilter ||
        record.officeName === officeFilter;

      const matchesArea =
        areaFilter === "All Areas" ||
        record.auditAreaId === areaFilter ||
        record.auditAreaName === areaFilter;

      const matchesStatus =
        statusFilter === "All Statuses" || record.status === statusFilter;

      return matchesSearch && matchesOffice && matchesArea && matchesStatus;
    });
  }, [areaFilter, auditFocuses, officeFilter, search, statusFilter]);

  // Handlers
  const handleOpenAdd = () => {
    setEditingFocus(null);
    setIsFocusModalOpen(true);
  };

  const handleOpenEdit = (focus: AuditFocusRecord) => {
    setEditingFocus(focus);
    setIsFocusModalOpen(true);
  };

  const handleSubmitFocus = (values: AuditFocusFormValues): { success: boolean; error?: string } => {
    try {
      const saved = localStore.saveAuditFocus({
        ...values,
        auditFocusId: editingFocus?.auditFocusId || editingFocus?.code || values.auditFocusId,
      });

      setRefreshTick((t) => t + 1);
      setIsFocusModalOpen(false);
      setEditingFocus(null);

      showToast({
        title: editingFocus ? "Audit Focus Updated" : "Audit Focus Registered",
        description: `${saved.auditFocusName} (${saved.auditFocusId}) registered under ${saved.auditAreaName}.`,
        tone: "success",
      });

      return { success: true };
    } catch (err) {
      console.error(err);
      return { success: false, error: "Failed to save audit focus record." };
    }
  };

  const handleOpenDeactivate = (focus: AuditFocusRecord) => {
    if (focus.status === "Inactive") {
      // Reactivate
      localStore.saveAuditFocus({
        ...focus,
        status: "Active",
      });
      setRefreshTick((t) => t + 1);
      showToast({
        title: "Audit Focus Reactivated",
        description: `${focus.auditFocusName || focus.name} is now Active and available for engagement scoping.`,
        tone: "success",
      });
      return;
    }

    // Check references in active scope states
    const targetCodes = [
      (focus.auditFocusId || focus.code || "").toLowerCase().trim(),
      (focus.auditFocusName || focus.name || "").toLowerCase().trim(),
    ].filter(Boolean);

    const engagements = localStore.getEngagements();
    const referencing: string[] = [];

    engagements.forEach((e) => {
      const scope = localStore.getScopeState(e.code);
      if (scope?.selectedAuditFocusIds?.some((fId: string) => targetCodes.includes(fId.toLowerCase()))) {
        referencing.push(`${e.code} — ${e.auditArea || e.subject}`);
      }
    });

    setDeletingFocus(focus);
    setReferencingEngagements(referencing);
    setIsDeleteModalOpen(true);
  };

  const handleConfirmDeactivate = () => {
    if (!deletingFocus) return;

    localStore.deleteAuditFocus(deletingFocus.auditFocusId || deletingFocus.code || "", "soft");
    setRefreshTick((t) => t + 1);
    setIsDeleteModalOpen(false);
    setDeletingFocus(null);

    showToast({
      title: "Audit Focus Deactivated",
      description: `${deletingFocus.auditFocusName || deletingFocus.name} has been set to Inactive (soft deleted).`,
      tone: "info",
    });
  };

  const handleResetDefaults = () => {
    const confirmed = window.confirm(
      "Reset the Audit Focus Registry back to master seed defaults? Any custom audit focuses will be replaced.",
    );
    if (!confirmed) return;

    localStore.resetAuditFocusesToDefault();
    setRefreshTick((t) => t + 1);
    setSelectedFocusId(null);
    showToast({
      title: "Audit Focus Registry Reset",
      description: "Restored all 38 canonical CDO audit focuses with preserved AF-011 and AF-012.",
      tone: "success",
    });
  };

  const handleResetFilters = () => {
    setSearch("");
    setOfficeFilter("All Offices");
    setAreaFilter("All Areas");
    setStatusFilter("All Statuses");
  };

  return (
    <AdminLayout>
      <div className="space-y-2.5 pb-8 overflow-x-hidden">
        {/* Page Header */}
        <AuditFocusesHeader
          onAddFocus={handleOpenAdd}
          onResetDefaults={handleResetDefaults}
        />

        {/* 4 Tinted Metric Stat Cards */}
        <AuditFocusesStats records={auditFocuses} />

        {/* Clean Cascading Filter Toolbar */}
        <AuditFocusesFilters
          search={search}
          officeFilter={officeFilter}
          areaFilter={areaFilter}
          statusFilter={statusFilter}
          offices={offices}
          auditAreas={auditAreas}
          onSearchChange={setSearch}
          onOfficeChange={(office) => {
            setOfficeFilter(office);
            setAreaFilter("All Areas"); // reset area when office changes
          }}
          onAreaChange={setAreaFilter}
          onStatusChange={setStatusFilter}
          onResetFilters={handleResetFilters}
        />

        {/* Master Table + Right-Rail Details Panel */}
        <AuditFocusesTable
          records={filteredRecords}
          selectedFocusId={selectedFocusId}
          onSelectFocus={(focus) =>
            setSelectedFocusId(focus.auditFocusId || focus.code || null)
          }
          onEdit={handleOpenEdit}
          onDeactivate={handleOpenDeactivate}
        />

        {/* Create / Edit Modal */}
        <AuditFocusFormModal
          isOpen={isFocusModalOpen}
          isEditing={Boolean(editingFocus)}
          defaultValues={editingFocus}
          existingFocuses={auditFocuses}
          auditAreas={auditAreas}
          onClose={() => {
            setIsFocusModalOpen(false);
            setEditingFocus(null);
          }}
          onSubmit={handleSubmitFocus}
        />

        {/* Soft Delete & Reference Warning Modal */}
        <AuditFocusDeleteConfirmModal
          isOpen={isDeleteModalOpen}
          focus={deletingFocus}
          referencingEngagements={referencingEngagements}
          onClose={() => {
            setIsDeleteModalOpen(false);
            setDeletingFocus(null);
          }}
          onConfirmDeactivate={handleConfirmDeactivate}
        />
      </div>
    </AdminLayout>
  );
}

export default AdminAuditFocusesPage;
