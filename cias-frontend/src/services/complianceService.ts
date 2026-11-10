import { api } from "./api";
import type { Compliance } from "../types/Compliance";

export const complianceService = {
  async list(params?: Record<string, unknown>): Promise<Compliance[]> {
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

    const data = await api.get<Compliance[] | { recommendations: Compliance[] }>(
      `/api/cms/recommendations${query}`
    );
    if (Array.isArray(data)) return data;
    if (
      data &&
      Array.isArray((data as { recommendations: Compliance[] }).recommendations)
    ) {
      return (data as { recommendations: Compliance[] }).recommendations;
    }
    return [];
  },

  async get(id: number | string): Promise<Compliance> {
    return api.get<Compliance>(`/api/cms/recommendations/${id}`);
  },

  async actionPlans(_params?: Record<string, unknown>): Promise<unknown[]> {
    const data = await api.get<unknown[]>("/api/cms/action-plans");
    return Array.isArray(data) ? data : [];
  },
};
