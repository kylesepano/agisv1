import { useCallback, useEffect, useState } from "react";
import { BookOpenCheck, Plus, RefreshCw } from "lucide-react";
import { useAuth } from "../../auth/auth-context";
import { hasPermission } from "../../config/navigation";
import { aemsClosureApi } from "../../services/api";

const initialForm = {
  categoryCode: "PLANNING",
  observation: "",
  impact: "",
  recommendedImprovement: "",
  confidentialityCode: "INTERNAL",
  targetDate: "",
};

const categories = [
  "PLANNING",
  "RESOURCE",
  "METHODOLOGY",
  "DATA_ACCESS",
  "AUDITEE_COORDINATION",
  "SUPERVISION",
  "DOCUMENTATION",
  "REPORTING",
  "SYSTEM",
  "TRAINING",
  "OTHER",
];

export default function AemsLessonsWorkspace({ engagementId }) {
  const { user } = useAuth();
  const [workspace, setWorkspace] = useState(null);
  const [form, setForm] = useState(initialForm);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");

  const load = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      setWorkspace(await aemsClosureApi.show(engagementId));
    } catch (reason) {
      setError(reason.message);
    } finally {
      setLoading(false);
    }
  }, [engagementId]);

  useEffect(() => {
    const timer = window.setTimeout(() => void load(), 0);
    return () => window.clearTimeout(timer);
  }, [load]);

  async function save() {
    setBusy(true);
    setError("");
    setNotice("");
    try {
      await aemsClosureApi.addLesson(engagementId, form);
      setNotice("Lesson learned recorded without changing issued results.");
      setForm(initialForm);
      await load();
    } catch (reason) {
      setError(reason.message);
    } finally {
      setBusy(false);
    }
  }

  if (loading) {
    return (
      <div
        className="grid min-h-72 place-items-center"
        data-testid="lessons-loading"
      >
        <RefreshCw className="animate-spin text-sky-700" size={28} />
      </div>
    );
  }

  const canCreate =
    hasPermission(user, "aems.closure.update") &&
    !workspace?.engagement?.isClosed;

  return (
    <div className="space-y-5" data-testid="lessons-workspace">
      <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div className="flex items-start gap-3">
          <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-violet-50 text-violet-700">
            <BookOpenCheck size={20} />
          </span>
          <div>
            <h3 className="font-bold text-slate-900">
              Lessons Learned and Improvement Actions
            </h3>
            <p className="mt-1 max-w-3xl text-sm leading-6 text-slate-500">
              Closure lessons inform future planning, QAIP, ARMIS competency
              planning, and analytics. They never alter Findings,
              Recommendations, or the issued Final Report.
            </p>
          </div>
        </div>
        {error && (
          <div className="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">
            {error}
          </div>
        )}
        {notice && (
          <div className="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
            {notice}
          </div>
        )}
      </section>

      {canCreate && (
        <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
          <div className="grid gap-4 md:grid-cols-2">
            <label>
              <span className="text-xs font-bold uppercase text-slate-500">
                Category
              </span>
              <select
                className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm"
                onChange={(event) =>
                  setForm((current) => ({
                    ...current,
                    categoryCode: event.target.value,
                  }))
                }
                value={form.categoryCode}
              >
                {categories.map((category) => (
                  <option key={category} value={category}>
                    {category.replaceAll("_", " ")}
                  </option>
                ))}
              </select>
            </label>
            <label>
              <span className="text-xs font-bold uppercase text-slate-500">
                Confidentiality
              </span>
              <input
                className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"
                onChange={(event) =>
                  setForm((current) => ({
                    ...current,
                    confidentialityCode: event.target.value,
                  }))
                }
                value={form.confidentialityCode}
              />
            </label>
            {[
              ["observation", "Observation"],
              ["impact", "Impact"],
              ["recommendedImprovement", "Recommended improvement"],
            ].map(([key, label]) => (
              <label
                className={
                  key === "recommendedImprovement" ? "md:col-span-2" : ""
                }
                key={key}
              >
                <span className="text-xs font-bold uppercase text-slate-500">
                  {label}
                </span>
                <textarea
                  className="mt-1 min-h-24 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"
                  onChange={(event) =>
                    setForm((current) => ({
                      ...current,
                      [key]: event.target.value,
                    }))
                  }
                  value={form[key]}
                />
              </label>
            ))}
          </div>
          <button
            className="mt-4 inline-flex min-h-10 items-center gap-2 rounded-lg bg-violet-700 px-4 text-sm font-bold text-white"
            disabled={
              busy ||
              !form.observation.trim() ||
              !form.impact.trim() ||
              !form.recommendedImprovement.trim()
            }
            onClick={() => void save()}
            type="button"
          >
            <Plus size={15} /> Add lesson learned
          </button>
        </section>
      )}

      <section className="grid gap-3 lg:grid-cols-2">
        {(workspace?.lessonsLearned ?? []).map((lesson) => (
          <article
            className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
            key={lesson.id}
          >
            <div className="flex flex-wrap justify-between gap-2">
              <span className="rounded-full bg-violet-100 px-2 py-1 text-xs font-bold text-violet-700">
                {lesson.categoryCode.replaceAll("_", " ")}
              </span>
              <span className="text-xs font-bold text-slate-400">
                {lesson.confidentialityCode}
              </span>
            </div>
            <h4 className="mt-3 text-sm font-bold text-slate-800">
              {lesson.observation}
            </h4>
            <p className="mt-2 text-sm leading-6 text-slate-600">
              <strong>Impact:</strong> {lesson.impact}
            </p>
            <p className="mt-2 text-sm leading-6 text-slate-600">
              <strong>Improvement:</strong> {lesson.recommendedImprovement}
            </p>
          </article>
        ))}
        {!workspace?.lessonsLearned?.length && (
          <div className="rounded-xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500 lg:col-span-2">
            No closure lessons have been recorded.
          </div>
        )}
      </section>
    </div>
  );
}
