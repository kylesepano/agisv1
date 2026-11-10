import { api } from "./api";
import type { Audit } from "../types/Audit";

export const auditService = {
  async list(params?: Record<string, unknown>): Promise<Audit[]> {
    const query = params
      ? "?" +
        new URLSearchParams(
          Object.entries(params).reduce((acc, [k, v]) => {
            if (v !== undefined && v !== null && v !== "") {
              acc[k] = String(v);
            }
            return acc;
          }, {} as Record<string, string>)
        ).toString()
      : "";

    const data = await api.get<Audit[] | { engagements: Audit[] }>(
      `/api/aems/engagements${query}`
    );
    if (Array.isArray(data)) return data;
    if (data && Array.isArray((data as { engagements: Audit[] }).engagements)) {
      return (data as { engagements: Audit[] }).engagements;
    }
    return [];
  },

  async get(id: number | string): Promise<Audit> {
    return api.get<Audit>(`/api/aems/engagements/${id}`);
  },

  async create(payload: unknown): Promise<Audit> {
    return api.post<Audit>("/api/aems/engagements", payload, { csrf: true });
  },

  async update(id: number | string, payload: unknown): Promise<Audit> {
    return api.put<Audit>(`/api/aems/engagements/${id}`, payload, { csrf: true });
  },

  async archive(id: number | string): Promise<void> {
    await api.delete(`/api/aems/engagements/${id}`, { csrf: true });
  },
};
