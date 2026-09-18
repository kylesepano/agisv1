import { useCallback, useEffect, useMemo, useState } from "react";
import {
  AlertTriangle,
  Bot,
  CheckCircle2,
  Clock3,
  History,
  Play,
  RefreshCw,
  Settings2,
  ShieldAlert,
  XCircle,
} from "lucide-react";
import { Link } from "react-router";
import { useAuth } from "../../auth/auth-context";
import { hasPermission } from "../../config/navigation";
import FormField from "../../components/ui/FormField";
import Modal from "../../components/ui/Modal";
import RegistryHeader from "../../components/ui/RegistryHeader";
import StatusBadge from "../../components/ui/StatusBadge";
import { cmsApi } from "../../services/api";
import { useToast } from "../../ui/toast-context";

const inputClass =
  "mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100 disabled:bg-slate-100";

const tabs = [
  ["overview", "Overview"],
  ["rules", "Automation rules"],
  ["candidates", "Candidate review"],
  ["runs", "Run history"],
];

const defaultRule = {
  ruleCode: "",
  name: "",
  description: "",
  ruleType: "REMINDER",
  statusCode: "ACTIVE",
  scheduleCode: "DAILY",
  daysAhead: 7,
  overdueDays: 30,
  severityCode: "HIGH",
};

function itemsFromCollection(value) {
  if (Array.isArray(value)) return value;
  if (Array.isArray(value?.data)) return value.data;
  return [];
}

function displayDate(value, includeTime = true) {
  if (!value) return "Not available";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "Not available";
  return new Intl.DateTimeFormat("en-PH", {
    dateStyle: "medium",
    ...(includeTime ? { timeStyle: "short" } : {}),
  }).format(date);
}

function readable(value) {
  return String(value || "")
    .replaceAll("_", " ")
    .toLowerCase()
    .replace(/(^|\s)\S/g, (letter) => letter.toUpperCase());
}

function toneForStatus(status) {
  return {
    ACTIVE: "success",
    COMPLETED: "success",
    ACKNOWLEDGED: "info",
    RUNNING: "warning",
    OPEN: "warning",
    DISMISSED: "inactive",
    FAILED: "danger",
    INACTIVE: "inactive",
  }[status] || "info";
}

function StatCard({ label, value, icon: Icon, tone = "sky", note }) {
  const tones = {
    sky: "border-sky-200 bg-sky-50 text-sky-800",
    amber: "border-amber-200 bg-amber-50 text-amber-800",
    emerald: "border-emerald-200 bg-emerald-50 text-emerald-800",
    red: "border-red-200 bg-red-50 text-red-800",
  };
  return (
    <section className={`rounded-xl border p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md ${tones[tone] || tones.sky}`}>
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="text-xs font-bold uppercase tracking-wide opacity-75">{label}</p>
          <p className="mt-2 text-3xl font-bold">{value ?? 0}</p>
          {note && <p className="mt-1 text-xs opacity-75">{note}</p>}
        </div>
        <span className="grid h-10 w-10 place-items-center rounded-lg bg-white/80 shadow-sm"><Icon size={20} /></span>
      </div>
    </section>
  );
}

function ErrorState({ message, onRetry }) {
  return (
    <section className="rounded-2xl border border-red-200 bg-red-50 px-6 py-12 text-center">
      <AlertTriangle className="mx-auto text-red-600" size={34} />
      <h2 className="mt-3 font-bold text-red-900">Automation workspace unavailable</h2>
      <p className="mx-auto mt-2 max-w-xl text-sm text-red-700">{message}</p>
      <button className="mt-5 inline-flex h-10 items-center gap-2 rounded-lg bg-red-700 px-4 text-sm font-bold text-white hover:bg-red-800" onClick={onRetry} type="button">
        <RefreshCw size={16} /> Retry
      </button>
    </section>
  );
}

function RuleTypeHelp({ type }) {
  const descriptions = {
    REMINDER: "Sends deduplicated target-date reminders to authorized recipients.",
    CLOSURE_READINESS: "Identifies cases that pass every formal closure-readiness gate.",
    ESCALATION_CANDIDATE: "Prepares an overdue escalation candidate for professional review; no notice is issued.",
  };
  return <p className="mt-1 text-xs leading-5 text-slate-500">{descriptions[type]}</p>;
}

function ruleFormFrom(rule) {
  const configuration = rule?.configuration || rule?.currentVersion?.configuration || {};
  return {
    ruleCode: rule?.ruleCode || "",
    name: rule?.name || "",
    description: rule?.description || "",
    ruleType: rule?.ruleType || "REMINDER",
    statusCode: rule?.statusCode || "ACTIVE",
    scheduleCode: rule?.scheduleCode || "DAILY",
    daysAhead: configuration.daysAhead ?? 7,
    overdueDays: configuration.overdueDays ?? 30,
    severityCode: configuration.severityCode || "HIGH",
  };
}

function CandidateCard({ candidate, type, canReview, canDismiss, onReview }) {
  const isClosure = type === "closure";
  const checks = candidate.readiness?.checklist || [];
  const status = candidate.statusCode || "OPEN";
  return (
    <article className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-sky-300 hover:shadow-md">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="flex min-w-0 items-start gap-3">
          <span className={`grid h-10 w-10 shrink-0 place-items-center rounded-lg ${isClosure ? "bg-emerald-100 text-emerald-700" : "bg-amber-100 text-amber-700"}`}>
            {isClosure ? <CheckCircle2 size={20} /> : <ShieldAlert size={20} />}
          </span>
          <div className="min-w-0">
            <Link className="font-bold text-sky-800 hover:underline" to={`/compliance-management/recommendations/${candidate.case?.id}`}>
              {candidate.case?.code || "Recommendation"}
            </Link>
            <p className="mt-1 text-xs text-slate-500">{candidate.case?.responsibleOffice?.name || "Responsible office unavailable"}</p>
          </div>
        </div>
        <StatusBadge tone={toneForStatus(status)}>{readable(status)}</StatusBadge>
      </div>

      <div className="mt-4 grid gap-3 text-sm sm:grid-cols-2">
        <div className="rounded-lg bg-slate-50 p-3"><p className="text-xs font-bold uppercase tracking-wide text-slate-500">Detected</p><p className="mt-1 text-slate-800">{displayDate(candidate.detectedAt)}</p></div>
        <div className="rounded-lg bg-slate-50 p-3"><p className="text-xs font-bold uppercase tracking-wide text-slate-500">{isClosure ? "Readiness" : "Trigger"}</p><p className="mt-1 text-slate-800">{isClosure ? (candidate.readiness?.eligible ? "All gates passed" : "Blocked") : `${readable(candidate.triggerCode)} · ${readable(candidate.severityCode)}`}</p></div>
      </div>

      {isClosure && checks.length > 0 && (
        <div className="mt-4 rounded-lg border border-slate-200 p-3">
          <p className="text-xs font-bold uppercase tracking-wide text-slate-500">Readiness checklist</p>
          <div className="mt-2 grid gap-1.5">
            {checks.slice(0, 4).map((check) => <p className={`flex items-center gap-2 text-xs ${check.passed ? "text-emerald-700" : "text-red-700"}`} key={check.code}>{check.passed ? <CheckCircle2 size={14} /> : <XCircle size={14} />}{check.label}</p>)}
          </div>
        </div>
      )}

      {!isClosure && <p className="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm leading-5 text-amber-900">{candidate.reason || "Automation identified a reviewable escalation condition."}</p>}

      <div className="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3">
        <Link className="text-sm font-bold text-sky-700 hover:underline" to={`/compliance-management/recommendations/${candidate.case?.id}`}>Open recommendation</Link>
        {status !== "DISMISSED" && canReview && <div className="flex flex-wrap gap-2"><button className="inline-flex h-9 items-center gap-2 rounded-lg border border-sky-300 bg-sky-50 px-3 text-xs font-bold text-sky-800 hover:bg-sky-100" onClick={() => onReview(candidate, type, "ACKNOWLEDGE")} type="button"><CheckCircle2 size={14} /> Acknowledge</button>{canDismiss && <button className="inline-flex h-9 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-700 hover:bg-slate-50" onClick={() => onReview(candidate, type, "DISMISS")} type="button"><XCircle size={14} /> Dismiss</button>}</div>}
      </div>
    </article>
  );
}

export default function CmsAutomationPage() {
  const { user } = useAuth();
  const toast = useToast();
  const canManage = hasPermission(user, "cms.automation.manage");
  const canRun = hasPermission(user, "cms.automation.run");
  const canReview = hasPermission(user, "cms.automation.review");
  const canDismiss = hasPermission(user, "cms.automation.dismiss");
  const [tab, setTab] = useState("overview");
  const [dashboard, setDashboard] = useState(null);
  const [rules, setRules] = useState([]);
  const [runs, setRuns] = useState([]);
  const [closureCandidates, setClosureCandidates] = useState([]);
  const [escalationCandidates, setEscalationCandidates] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [saving, setSaving] = useState(false);
  const [ruleModal, setRuleModal] = useState(null);
  const [ruleForm, setRuleForm] = useState(defaultRule);
  const [reviewModal, setReviewModal] = useState(null);
  const [reviewNote, setReviewNote] = useState("");

  const load = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const [summary, ruleData, runData, candidateData] = await Promise.all([
        cmsApi.getAutomationDashboard(),
        cmsApi.getAutomationRules(),
        cmsApi.getAutomationRuns(),
        cmsApi.getAutomationCandidates(),
      ]);
      setDashboard(summary || {});
      setRules(itemsFromCollection(ruleData));
      setRuns(itemsFromCollection(runData));
      setClosureCandidates(itemsFromCollection(candidateData?.closureCandidates));
      setEscalationCandidates(itemsFromCollection(candidateData?.escalationCandidates));
    } catch (requestError) {
      setError(requestError.message || "The CMS automation workspace could not be loaded.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    const timer = window.setTimeout(() => {
      load().catch(() => undefined);
    }, 0);
    return () => window.clearTimeout(timer);
  }, [load]);

  const openCandidates = useMemo(() => [...closureCandidates, ...escalationCandidates].filter((candidate) => ["OPEN", "ACKNOWLEDGED"].includes(candidate.statusCode)).length, [closureCandidates, escalationCandidates]);

  async function runAutomation() {
    setSaving(true);
    try {
      const result = await cmsApi.runAutomation();
      toast.success(`Automation completed. ${result?.createdCount ?? 0} action(s) created.`);
      await load();
    } catch (requestError) {
      toast.error(requestError.message || "Automation could not be run.");
    } finally {
      setSaving(false);
    }
  }

  function openRule(rule = null) {
    setRuleForm(ruleFormFrom(rule));
    setRuleModal(rule || { isNew: true });
  }

  function updateRuleForm(name, value) {
    setRuleForm((current) => ({ ...current, [name]: value }));
  }

  async function saveRule() {
    setSaving(true);
    try {
      const configuration = ruleForm.ruleType === "REMINDER"
        ? { daysAhead: Number(ruleForm.daysAhead) }
        : ruleForm.ruleType === "ESCALATION_CANDIDATE"
          ? { overdueDays: Number(ruleForm.overdueDays), severityCode: ruleForm.severityCode }
          : {};
      const payload = { ruleCode: ruleForm.ruleCode.trim(), name: ruleForm.name.trim(), description: ruleForm.description.trim() || null, ruleType: ruleForm.ruleType, statusCode: ruleForm.statusCode, scheduleCode: ruleForm.scheduleCode, configuration };
      if (ruleModal?.id) await cmsApi.updateAutomationRule(ruleModal.id, payload);
      else await cmsApi.createAutomationRule(payload);
      toast.success(ruleModal?.id ? "Automation rule version saved." : "Automation rule created.");
      setRuleModal(null);
      await load();
    } catch (requestError) {
      toast.error(requestError.message || "The automation rule could not be saved.");
    } finally {
      setSaving(false);
    }
  }

  function openReview(candidate, type, action) {
    setReviewNote("");
    setReviewModal({ candidate, type, action });
  }

  async function reviewCandidate() {
    if (!reviewModal) return;
    setSaving(true);
    try {
      const payload = { reviewNote: reviewNote.trim() || null };
      if (reviewModal.type === "closure") await cmsApi.reviewClosureCandidate(reviewModal.candidate.id, reviewModal.action, payload);
      else await cmsApi.reviewEscalationCandidate(reviewModal.candidate.id, reviewModal.action, payload);
      toast.success(`Candidate ${reviewModal.action === "DISMISS" ? "dismissed" : "acknowledged"}.`);
      setReviewModal(null);
      await load();
    } catch (requestError) {
      toast.error(requestError.message || "The candidate could not be reviewed.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <main className="min-w-0 p-4 sm:p-5 lg:p-6">
      <RegistryHeader
        actions={<><button aria-label="Refresh automation workspace" className="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700 hover:bg-slate-50" disabled={loading} onClick={load} type="button"><RefreshCw className={loading ? "animate-spin" : ""} size={16} /> Refresh</button>{canRun && <button className="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white hover:bg-sky-800 disabled:opacity-60" disabled={saving} onClick={runAutomation} type="button"><Play size={16} /> Run automation</button>}{canManage && <button className="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-slate-800 px-4 text-sm font-bold text-white hover:bg-slate-900" onClick={() => openRule()} type="button"><Settings2 size={16} /> New rule</button>}</>}
        description="Monitor reminders and review automation-generated candidates without delegating professional decisions to automation."
        icon={Bot}
        title="CMS Automation & Candidate Review"
      />

      {loading && !dashboard ? <div aria-label="Loading CMS automation workspace" className="grid gap-4"><div className="h-28 animate-pulse rounded-xl bg-slate-200" /><div className="h-80 animate-pulse rounded-xl bg-slate-200" /></div> : error && !dashboard ? <ErrorState message={error} onRetry={load} /> : <div className="grid gap-5">
        {error && <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">Refresh failed. Showing the last successfully loaded workspace.</div>}
        <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Automation summary">
          <StatCard icon={Settings2} label="Active rules" value={dashboard?.activeRules} note="Versioned configuration" />
          <StatCard icon={CheckCircle2} label="Closure candidates" value={dashboard?.openClosureCandidates} tone="emerald" note="Awaiting professional review" />
          <StatCard icon={ShieldAlert} label="Escalation candidates" value={dashboard?.openEscalationCandidates} tone="amber" note="No notice issued automatically" />
          <StatCard icon={Clock3} label="Reminders (7 days)" value={dashboard?.recentReminderCount} tone="sky" note={dashboard?.lastRunAt ? `Last run ${displayDate(dashboard.lastRunAt)}` : "No completed run yet"} />
        </section>

        <div className="flex overflow-x-auto rounded-xl border border-slate-200 bg-white p-1 shadow-sm" role="tablist" aria-label="Automation workspace sections">
          {tabs.map(([key, label]) => <button aria-selected={tab === key} className={`min-w-max flex-1 rounded-lg px-4 py-2.5 text-sm font-bold transition ${tab === key ? "bg-sky-700 text-white" : "text-slate-600 hover:bg-slate-50"}`} key={key} onClick={() => setTab(key)} role="tab" type="button">{label}{key === "candidates" && openCandidates > 0 ? ` (${openCandidates})` : ""}</button>)}
        </div>

        {tab === "overview" && <div className="grid gap-4 lg:grid-cols-2">
          <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><div className="flex items-start gap-3"><span className="grid h-10 w-10 place-items-center rounded-lg bg-sky-100 text-sky-700"><Bot size={20} /></span><div><h2 className="font-bold text-slate-800">Automation boundary</h2><p className="mt-1 text-sm leading-6 text-slate-600">Automation identifies readiness, sends reminders, and prepares reviewable drafts. It never approves dispositions, closes recommendations, reopens cases, or issues escalation notices.</p></div></div><div className="mt-4 grid gap-2 text-sm text-slate-700"><p className="flex items-center gap-2"><CheckCircle2 className="text-emerald-600" size={16} /> Scope and confidentiality filtering is enforced by the backend.</p><p className="flex items-center gap-2"><CheckCircle2 className="text-emerald-600" size={16} /> Runs and actions are idempotent and auditable.</p><p className="flex items-center gap-2"><CheckCircle2 className="text-emerald-600" size={16} /> Final decisions remain in existing CMS review workflows.</p></div></section>
          <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><div className="flex items-center justify-between gap-3"><div><h2 className="font-bold text-slate-800">Review queue</h2><p className="mt-1 text-sm text-slate-500">Candidates detected in your authorized scope.</p></div><button className="text-sm font-bold text-sky-700 hover:underline" onClick={() => setTab("candidates")} type="button">Open queue</button></div><div className="mt-4 grid gap-3 sm:grid-cols-2"><div className="rounded-lg border border-emerald-200 bg-emerald-50 p-4"><p className="text-xs font-bold uppercase tracking-wide text-emerald-700">Closure readiness</p><p className="mt-1 text-2xl font-bold text-emerald-900">{dashboard?.openClosureCandidates ?? 0}</p></div><div className="rounded-lg border border-amber-200 bg-amber-50 p-4"><p className="text-xs font-bold uppercase tracking-wide text-amber-700">Escalation review</p><p className="mt-1 text-2xl font-bold text-amber-900">{dashboard?.openEscalationCandidates ?? 0}</p></div></div></section>
        </div>}

        {tab === "rules" && <section className="rounded-xl border border-slate-200 bg-white shadow-sm"><header className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4"><div><h2 className="font-bold text-slate-800">Automation rules</h2><p className="mt-1 text-sm text-slate-500">Every change creates a new immutable rule version.</p></div>{canManage && <button className="inline-flex h-9 items-center gap-2 rounded-lg bg-slate-800 px-3 text-xs font-bold text-white hover:bg-slate-900" onClick={() => openRule()} type="button"><Settings2 size={14} /> New rule</button>}</header><div className="divide-y divide-slate-100">{rules.length === 0 ? <p className="px-5 py-12 text-center text-sm text-slate-500">No automation rules are available.</p> : rules.map((rule) => <div className="flex flex-wrap items-start justify-between gap-4 px-5 py-4" key={rule.id}><div className="min-w-0"><div className="flex flex-wrap items-center gap-2"><strong className="text-slate-800">{rule.name}</strong><StatusBadge tone={toneForStatus(rule.statusCode)}>{readable(rule.statusCode)}</StatusBadge></div><p className="mt-1 text-xs font-bold uppercase tracking-wide text-sky-700">{rule.ruleCode} · {readable(rule.ruleType)} · {readable(rule.scheduleCode)}</p><p className="mt-1 max-w-2xl text-sm text-slate-600">{rule.description || "No description provided."}</p><p className="mt-2 text-xs text-slate-500">Version {rule.currentVersion?.versionNumber || 1} · Effective {displayDate(rule.currentVersion?.effectiveFrom)}</p></div>{canManage && <button className="inline-flex h-9 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-700 hover:bg-slate-50" onClick={() => openRule(rule)} type="button"><Settings2 size={14} /> Configure</button>}</div>)}</div></section>}

        {tab === "candidates" && <div className="grid gap-5 lg:grid-cols-2"><section><div className="mb-3 flex items-center gap-2"><CheckCircle2 className="text-emerald-600" size={20} /><div><h2 className="font-bold text-slate-800">Closure-readiness candidates</h2><p className="text-xs text-slate-500">The existing CMS closure workflow remains required.</p></div></div><div className="grid gap-3">{closureCandidates.length === 0 ? <div className="rounded-xl border border-dashed border-slate-300 bg-white px-5 py-12 text-center text-sm text-slate-500">No closure-readiness candidates.</div> : closureCandidates.map((candidate) => <CandidateCard canDismiss={canDismiss} canReview={canReview} candidate={candidate} key={candidate.id} onReview={openReview} type="closure" />)}</div></section><section><div className="mb-3 flex items-center gap-2"><ShieldAlert className="text-amber-600" size={20} /><div><h2 className="font-bold text-slate-800">Escalation candidates</h2><p className="text-xs text-slate-500">Reviewable drafts only; notices are never issued automatically.</p></div></div><div className="grid gap-3">{escalationCandidates.length === 0 ? <div className="rounded-xl border border-dashed border-slate-300 bg-white px-5 py-12 text-center text-sm text-slate-500">No escalation candidates.</div> : escalationCandidates.map((candidate) => <CandidateCard canDismiss={canDismiss} canReview={canReview} candidate={candidate} key={candidate.id} onReview={openReview} type="escalation" />)}</div></section></div>}

        {tab === "runs" && <section className="rounded-xl border border-slate-200 bg-white shadow-sm"><header className="border-b border-slate-200 px-5 py-4"><div className="flex items-center gap-2"><History className="text-sky-700" size={20} /><div><h2 className="font-bold text-slate-800">Automation run history</h2><p className="mt-1 text-sm text-slate-500">Runs are retained for replay-safe auditability.</p></div></div></header>{runs.length === 0 ? <p className="px-5 py-12 text-center text-sm text-slate-500">No automation runs have been recorded.</p> : <div className="overflow-x-auto"><table className="w-full min-w-[720px] text-left text-sm"><thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th className="px-5 py-3">Rule</th><th className="px-5 py-3">Status</th><th className="px-5 py-3">Started</th><th className="px-5 py-3">Scanned</th><th className="px-5 py-3">Created</th><th className="px-5 py-3">Errors</th></tr></thead><tbody className="divide-y divide-slate-100">{runs.map((run) => <tr key={run.id}><td className="px-5 py-3 font-semibold text-slate-800">{run.ruleCode || "Unknown rule"}</td><td className="px-5 py-3"><StatusBadge tone={toneForStatus(run.statusCode)}>{readable(run.statusCode)}</StatusBadge></td><td className="px-5 py-3 text-slate-600">{displayDate(run.startedAt)}</td><td className="px-5 py-3 text-slate-600">{run.scannedCount ?? 0}</td><td className="px-5 py-3 text-slate-600">{run.createdCount ?? 0}</td><td className="px-5 py-3 text-slate-600">{run.errorCount ?? 0}</td></tr>)}</tbody></table></div>}</section>}
      </div>}

      <Modal description="Configure a reminder or candidate-detection rule. Saving creates an immutable version." footer={<><button className="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700 hover:bg-slate-50" disabled={saving} onClick={() => setRuleModal(null)} type="button">Cancel</button><button className="inline-flex h-10 items-center rounded-lg bg-sky-700 px-4 text-sm font-bold text-white hover:bg-sky-800 disabled:opacity-60" disabled={saving} onClick={saveRule} type="button">{saving ? "Saving..." : "Save version"}</button></>} onClose={() => !saving && setRuleModal(null)} open={ruleModal !== null} size="lg" title={ruleModal?.id ? "Configure automation rule" : "Create automation rule"}>
        <div className="grid gap-4 sm:grid-cols-2"><FormField htmlFor="automation-rule-code" label="Rule code" required><input className={inputClass} disabled={Boolean(ruleModal?.id)} id="automation-rule-code" onChange={(event) => updateRuleForm("ruleCode", event.target.value.toUpperCase())} value={ruleForm.ruleCode} /></FormField><FormField htmlFor="automation-rule-name" label="Name" required><input className={inputClass} id="automation-rule-name" onChange={(event) => updateRuleForm("name", event.target.value)} value={ruleForm.name} /></FormField><FormField htmlFor="automation-rule-type" label="Rule type" required><select className={inputClass} disabled={Boolean(ruleModal?.id)} id="automation-rule-type" onChange={(event) => updateRuleForm("ruleType", event.target.value)} value={ruleForm.ruleType}><option value="REMINDER">Target-date reminder</option><option value="CLOSURE_READINESS">Closure-readiness candidate</option><option value="ESCALATION_CANDIDATE">Escalation candidate</option></select><RuleTypeHelp type={ruleForm.ruleType} /></FormField><FormField htmlFor="automation-rule-schedule" label="Schedule" required><select className={inputClass} id="automation-rule-schedule" onChange={(event) => updateRuleForm("scheduleCode", event.target.value)} value={ruleForm.scheduleCode}><option value="DAILY">Daily</option><option value="HOURLY">Hourly</option><option value="MANUAL">Manual only</option></select></FormField><FormField htmlFor="automation-rule-status" label="Status" required><select className={inputClass} id="automation-rule-status" onChange={(event) => updateRuleForm("statusCode", event.target.value)} value={ruleForm.statusCode}><option value="ACTIVE">Active</option><option value="INACTIVE">Inactive</option></select></FormField><div className="sm:col-span-2"><FormField htmlFor="automation-rule-description" label="Description"><textarea className={`${inputClass} min-h-24`} id="automation-rule-description" onChange={(event) => updateRuleForm("description", event.target.value)} value={ruleForm.description} /></FormField></div>{ruleForm.ruleType === "REMINDER" && <FormField htmlFor="automation-days-ahead" label="Days ahead" hint="Target dates within this window receive reminders."><input className={inputClass} id="automation-days-ahead" min="0" onChange={(event) => updateRuleForm("daysAhead", event.target.value)} type="number" value={ruleForm.daysAhead} /></FormField>}{ruleForm.ruleType === "ESCALATION_CANDIDATE" && <><FormField htmlFor="automation-overdue-days" label="Overdue days"><input className={inputClass} id="automation-overdue-days" min="1" onChange={(event) => updateRuleForm("overdueDays", event.target.value)} type="number" value={ruleForm.overdueDays} /></FormField><FormField htmlFor="automation-severity" label="Candidate severity"><select className={inputClass} id="automation-severity" onChange={(event) => updateRuleForm("severityCode", event.target.value)} value={ruleForm.severityCode}><option value="LOW">Low</option><option value="MEDIUM">Medium</option><option value="HIGH">High</option><option value="CRITICAL">Critical</option></select></FormField></>}</div>
      </Modal>

      <Modal description="Record a review note. This action only resolves the automation candidate; it does not make a professional decision." footer={<><button className="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700 hover:bg-slate-50" disabled={saving} onClick={() => setReviewModal(null)} type="button">Cancel</button><button className={`inline-flex h-10 items-center rounded-lg px-4 text-sm font-bold text-white disabled:opacity-60 ${reviewModal?.action === "DISMISS" ? "bg-slate-700 hover:bg-slate-800" : "bg-sky-700 hover:bg-sky-800"}`} disabled={saving} onClick={reviewCandidate} type="button">{saving ? "Saving..." : `${reviewModal?.action === "DISMISS" ? "Dismiss" : "Acknowledge"} candidate`}</button></>} onClose={() => !saving && setReviewModal(null)} open={reviewModal !== null} size="md" title={`${reviewModal?.action === "DISMISS" ? "Dismiss" : "Acknowledge"} automation candidate`}>
        <p className="text-sm leading-6 text-slate-600">{reviewModal?.candidate?.case?.code || "This candidate"} will be marked as {reviewModal?.action === "DISMISS" ? "dismissed" : "acknowledged"}. The recommendation case status remains unchanged.</p><FormField htmlFor="automation-review-note" label="Review note" hint="Record the reason for this review action."><textarea className={`${inputClass} min-h-28`} id="automation-review-note" onChange={(event) => setReviewNote(event.target.value)} value={reviewNote} /></FormField>
      </Modal>
    </main>
  );
}
