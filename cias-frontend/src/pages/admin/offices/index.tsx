import { useMemo, useState } from "react";
import { AdminLayout } from "../../../layouts/admin/AdminLayout";
import { useToast } from "../../../components/ui/toast";
import { localStore } from "../../../lib/localStore";
import type { OfficeRecord } from "./offices-types";
import {
  OfficeDeleteConfirmModal,
  OfficeFormModal,
  type OfficeFormValues,
  OfficesFilters,
  OfficesHeader,
  OfficesStats,
  OfficesTable,
} from "./components";

export function AdminOfficesPage() {
  const { showToast } = useToast();
  const [refreshTick, setRefreshTick] = useState(0);

  // Modals state
  const [isOfficeModalOpen, setIsOfficeModalOpen] = useState(false);
  const [editingOffice, setEditingOffice] = useState<OfficeRecord | null>(null);

  const [deletingOffice, setDeletingOffice] = useState<OfficeRecord | null>(null);
  const [referencingEngagements, setReferencingEngagements] = useState<string[]>([]);
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);

  // Selected office for right-rail detail panel
  const [selectedOfficeId, setSelectedOfficeId] = useState<string | null>(null);

  // Filters
  const [search, setSearch] = useState("");
  const [categoryFilter, setCategoryFilter] = useState("All Categories");
  const [statusFilter, setStatusFilter] = useState("All Statuses");

  // Read live from localStore
  const offices = useMemo(() => {
    // dependency on refreshTick ensures live re-renders
    return localStore.getOffices();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [refreshTick]);

  const filteredRecords = useMemo(() => {
    const normalizedSearch = search.trim().toLowerCase();

    return offices.filter((record) => {
      const officeId = record.officeId || record.code || "";
      const officeName = record.officeName || record.name || "";
      const officeAcronym = record.officeAcronym || record.shortName || "";
      const head = record.head || "";
      const email = record.contactEmail || "";

      const matchesSearch =
        normalizedSearch.length === 0 ||
        [officeId, officeName, officeAcronym, head, email].some((val) =>
          val.toLowerCase().includes(normalizedSearch),
        );

      const matchesCategory =
        categoryFilter === "All Categories" || record.officeCategory === categoryFilter;

      const matchesStatus =
        statusFilter === "All Statuses" || record.status === statusFilter;

      return matchesSearch && matchesCategory && matchesStatus;
    });
  }, [categoryFilter, offices, search, statusFilter]);

  // Handlers
  const handleOpenAdd = () => {
    setEditingOffice(null);
    setIsOfficeModalOpen(true);
  };

  const handleOpenEdit = (office: OfficeRecord) => {
    setEditingOffice(office);
    setIsOfficeModalOpen(true);
  };

  const handleSubmitOffice = (values: OfficeFormValues): { success: boolean; error?: string } => {
    try {
      const saved = localStore.saveOffice({
        ...values,
        officeId: editingOffice?.officeId || editingOffice?.code || values.officeId,
      });

      setRefreshTick((t) => t + 1);
      setIsOfficeModalOpen(false);
      setEditingOffice(null);

      showToast({
        title: editingOffice ? "Office Profile Updated" : "Auditee Office Registered",
        description: `${saved.officeName} (${saved.officeAcronym}) saved to Office Registry.`,
        tone: "success",
      });

      return { success: true };
    } catch (err) {
      console.error(err);
      return { success: false, error: "Failed to save office record." };
    }
  };

  const handleOpenDeactivate = (office: OfficeRecord) => {
    if (office.status === "Inactive") {
      // Direct reactivate
      localStore.saveOffice({
        ...office,
        status: "Active",
      });
      setRefreshTick((t) => t + 1);
      showToast({
        title: "Office Reactivated",
        description: `${office.officeName || office.name} is now Active and available in audit selectors.`,
        tone: "success",
      });
      return;
    }

    // Check references in active engagements
    const targetNames = [
      (office.officeName || office.name || "").toLowerCase().trim(),
      (office.officeAcronym || office.shortName || "").toLowerCase().trim(),
    ].filter(Boolean);

    const engagements = localStore.getEngagements();
    const referencing = engagements.filter((e) =>
      targetNames.includes((e.office || "").toLowerCase().trim()),
    );
    const referencingCodes = referencing.map((e) => `${e.code} — ${e.auditArea || e.subject}`);

    setDeletingOffice(office);
    setReferencingEngagements(referencingCodes);
    setIsDeleteModalOpen(true);
  };

  const handleConfirmDeactivate = () => {
    if (!deletingOffice) return;

    localStore.deleteOffice(deletingOffice.officeId || deletingOffice.code || "", "soft");
    setRefreshTick((t) => t + 1);
    setIsDeleteModalOpen(false);
    setDeletingOffice(null);

    showToast({
      title: "Auditee Office Deactivated",
      description: `${deletingOffice.officeName || deletingOffice.name} has been set to Inactive (soft deleted).`,
      tone: "info",
    });
  };

  const handleResetDefaults = () => {
    const confirmed = window.confirm(
      "Reset the Office Registry back to master seed defaults? Any custom offices will be replaced.",
    );
    if (!confirmed) return;

    localStore.resetOfficesToDefault();
    setRefreshTick((t) => t + 1);
    setSelectedOfficeId(null);
    showToast({
      title: "Office Registry Reset",
      description: "Restored all 36 canonical CDO City Government departments and offices.",
      tone: "success",
    });
  };

  const handleResetFilters = () => {
    setSearch("");
    setCategoryFilter("All Categories");
    setStatusFilter("All Statuses");
  };

  return (
    <AdminLayout>
      <div className="space-y-2.5 pb-8 overflow-x-hidden">
        {/* Page Header matching SCR-210 */}
        <OfficesHeader
          onAddOffice={handleOpenAdd}
          onResetDefaults={handleResetDefaults}
        />

        {/* 4 Tinted Metric Stat Cards */}
        <OfficesStats records={offices} />

        {/* Clean Filter Toolbar */}
        <OfficesFilters
          search={search}
          categoryFilter={categoryFilter}
          statusFilter={statusFilter}
          onSearchChange={setSearch}
          onCategoryChange={setCategoryFilter}
          onStatusChange={setStatusFilter}
          onResetFilters={handleResetFilters}
        />

        {/* Master Table + Right-Rail Details Panel */}
        <OfficesTable
          records={filteredRecords}
          selectedOfficeId={selectedOfficeId}
          onSelectOffice={(office) =>
            setSelectedOfficeId(office.officeId || office.code || null)
          }
          onEdit={handleOpenEdit}
          onDeactivate={handleOpenDeactivate}
        />

        {/* Create / Edit Modal */}
        <OfficeFormModal
          isOpen={isOfficeModalOpen}
          isEditing={Boolean(editingOffice)}
          defaultValues={editingOffice}
          existingOffices={offices}
          onClose={() => {
            setIsOfficeModalOpen(false);
            setEditingOffice(null);
          }}
          onSubmit={handleSubmitOffice}
        />

        {/* Soft Delete & Reference Warning Modal */}
        <OfficeDeleteConfirmModal
          isOpen={isDeleteModalOpen}
          office={deletingOffice}
          referencingEngagements={referencingEngagements}
          onClose={() => {
            setIsDeleteModalOpen(false);
            setDeletingOffice(null);
          }}
          onConfirmDeactivate={handleConfirmDeactivate}
        />
      </div>
    </AdminLayout>
  );
}

export default AdminOfficesPage;
