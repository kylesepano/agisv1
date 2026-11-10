import { useState } from "react";
import { AdminLayout } from "../../../layouts/admin/AdminLayout";
import { categoryRecords } from "./categories-data";
import {
  CategoryFormModal,
  CategoriesHeader,
  CategoriesStats,
  CategoriesTable,
} from "./components";

export function AdminCategoriesPage() {
  const [isCategoryModalOpen, setIsCategoryModalOpen] = useState(false);

  return (
    <AdminLayout>
      <div className="space-y-3 overflow-x-hidden">
        <CategoriesHeader
          onAddCategory={() => setIsCategoryModalOpen(true)}
        />
        <CategoriesStats records={categoryRecords} />
        <CategoriesTable records={categoryRecords} />
        <CategoryFormModal
          isOpen={isCategoryModalOpen}
          onClose={() => setIsCategoryModalOpen(false)}
          onSubmit={() => setIsCategoryModalOpen(false)}
        />
      </div>
    </AdminLayout>
  );
}
