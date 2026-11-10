import { useEffect, useMemo, useState } from "react";
import { AdminLayout } from "../../../layouts/admin/AdminLayout";
import { userRecords as defaultUsers } from "./users-data";
import { userService } from "../../../services/userService";
import { isAuthenticated, isDemoSession } from "../../../lib/auth";
import type { UserRecord, UserRole, UserStatus } from "./users-types";
import {
  UsersFilters,
  UsersHeader,
  UsersStats,
  UsersTable,
} from "./components";

function mapBackendUserToRecord(u: any): UserRecord {
  const status: UserStatus = u.isActive === false || u.isLocked ? "Inactive" : "Active";
  const roleName = u.role || u.roles?.[0]?.name || "Auditor";
  const departmentName = u.office?.name || u.office || "City Internal Audit Office";

  return {
    id: String(u.id),
    name: u.name || `${u.firstName || ""} ${u.lastName || ""}`.trim() || "User",
    title: u.position || "Internal Auditor",
    username: u.employeeId || u.username || "USR",
    email: u.email || `${(u.employeeId || "user").toLowerCase()}@cagayandeoro.gov.ph`,
    role: (roleName as UserRole) || "Auditor",
    department: departmentName,
    status,
    lastLoginDate: u.lastLoginAt
      ? new Date(u.lastLoginAt).toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" })
      : "Never",
    lastLoginTime: u.lastLoginAt
      ? new Date(u.lastLoginAt).toLocaleTimeString("en-US", { hour: "2-digit", minute: "2-digit" })
      : "",
  };
}

export function AdminUsersPage() {
  const [users, setUsers] = useState<UserRecord[]>(() => defaultUsers);
  const [search, setSearch] = useState("");
  const [roleFilter, setRoleFilter] = useState("All Roles");
  const [departmentFilter, setDepartmentFilter] = useState("All Departments");
  const [statusFilter, setStatusFilter] = useState("All Statuses");

  useEffect(() => {
    if (!isAuthenticated() || isDemoSession()) {
      return;
    }

    let isMounted = true;
    userService
      .list()
      .then((raw) => {
        if (!isMounted) return;
        const list = Array.isArray(raw) ? raw : (raw as any)?.users || [];
        if (Array.isArray(list) && list.length > 0) {
          setUsers(list.map(mapBackendUserToRecord));
        }
      })
      .catch((err) => {
        console.warn("Could not load backend users, using local cache:", err);
      });

    return () => {
      isMounted = false;
    };
  }, []);

  const roleOptions = useMemo(
    () => [
      "All Roles",
      ...Array.from(new Set(users.map((record) => record.role))),
    ],
    [users],
  );

  const departmentOptions = useMemo(
    () => [
      "All Departments",
      ...Array.from(new Set(users.map((record) => record.department))),
    ],
    [users],
  );

  const statusOptions = ["All Statuses", "Active", "Inactive"];

  const filteredUsers = users.filter((record) => {
    const searchValue = search.trim().toLowerCase();
    const matchesSearch =
      searchValue.length === 0 ||
      `${record.name} ${record.username} ${record.email}`
        .toLowerCase()
        .includes(searchValue);
    const matchesRole =
      roleFilter === "All Roles" || record.role === roleFilter;
    const matchesDepartment =
      departmentFilter === "All Departments" ||
      record.department === departmentFilter;
    const matchesStatus =
      statusFilter === "All Statuses" || record.status === statusFilter;

    return (
      matchesSearch && matchesRole && matchesDepartment && matchesStatus
    );
  });

  const clearFilters = () => {
    setSearch("");
    setRoleFilter("All Roles");
    setDepartmentFilter("All Departments");
    setStatusFilter("All Statuses");
  };

  return (
    <AdminLayout>
      <div className="space-y-4">
        <UsersHeader />
        <UsersStats records={users} />
        <UsersFilters
          search={search}
          roleFilter={roleFilter}
          departmentFilter={departmentFilter}
          statusFilter={statusFilter}
          roleOptions={roleOptions}
          departmentOptions={departmentOptions}
          statusOptions={statusOptions}
          onSearchChange={setSearch}
          onRoleChange={setRoleFilter}
          onDepartmentChange={setDepartmentFilter}
          onStatusChange={setStatusFilter}
          onClear={clearFilters}
        />
        <UsersTable records={filteredUsers} />
      </div>
    </AdminLayout>
  );
}
