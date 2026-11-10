import { api } from "./api";
import type { Department } from "../types/Department";

export const departmentService = {
  async list(): Promise<Department[]> {
    const data = await api.get<Department[] | { offices: Department[] }>("/api/offices");
    if (Array.isArray(data)) return data;
    if (data && Array.isArray((data as { offices: Department[] }).offices)) {
      return (data as { offices: Department[] }).offices;
    }
    return [];
  },

  async get(id: number | string): Promise<Department> {
    return api.get<Department>(`/api/offices/${id}`);
  },

  async create(payload: unknown): Promise<Department> {
    return api.post<Department>("/api/offices", payload, { csrf: true });
  },

  async update(id: number | string, payload: unknown): Promise<Department> {
    return api.put<Department>(`/api/offices/${id}`, payload, { csrf: true });
  },

  async delete(id: number | string): Promise<void> {
    await api.delete(`/api/offices/${id}`, { csrf: true });
  },
};
