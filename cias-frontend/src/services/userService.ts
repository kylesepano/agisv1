import { api } from "./api";
import type { User } from "../types/User";

export const userService = {
  async list(params?: Record<string, unknown>): Promise<User[]> {
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

    const data = await api.get<User[] | { users: User[] }>(`/api/users${query}`);
    if (Array.isArray(data)) return data;
    if (data && Array.isArray((data as { users: User[] }).users)) {
      return (data as { users: User[] }).users;
    }
    return [];
  },

  async get(id: number | string): Promise<User> {
    return api.get<User>(`/api/users/${id}`);
  },

  async create(payload: unknown): Promise<User> {
    return api.post<User>("/api/users", payload, { csrf: true });
  },

  async update(id: number | string, payload: unknown): Promise<User> {
    return api.put<User>(`/api/users/${id}`, payload, { csrf: true });
  },

  async archive(id: number | string): Promise<void> {
    await api.delete(`/api/users/${id}`, { csrf: true });
  },

  async activate(id: number | string): Promise<void> {
    await api.post(`/api/users/${id}/activate`, {}, { csrf: true });
  },

  async disable(id: number | string): Promise<void> {
    await api.post(`/api/users/${id}/disable`, {}, { csrf: true });
  },

  async lock(id: number | string): Promise<void> {
    await api.post(`/api/users/${id}/lock`, {}, { csrf: true });
  },

  async unlock(id: number | string): Promise<void> {
    await api.post(`/api/users/${id}/unlock`, {}, { csrf: true });
  },
};
