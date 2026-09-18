import { LockKeyhole } from "lucide-react";
import { Link } from "react-router";
import {
  getAemsWorkspaceGate,
  statusLabel,
  workspaceActionPath,
} from "./aemsPhaseGates";

/**
 * Consistent phase-lock explanation for standalone AEMS workspaces.
 * Backend authorization remains authoritative; this is the user-facing
 * explanation and next-action affordance.
 */
export default function AemsWorkspaceLockNotice({ gate, engagementId }) {
  if (!gate || gate.unlocked || gate.pending) return null;

  return (
    <section
      className="rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-950 shadow-sm"
      data-testid="aems-workspace-lock"
    >
      <div className="flex items-start gap-3">
        <span className="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-amber-100 text-amber-800">
          <LockKeyhole size={18} />
        </span>
        <div className="min-w-0">
          <h2 className="font-bold">{gate.title}</h2>
          <p className="mt-1 leading-6">{gate.reason}</p>
          <div className="mt-3 flex flex-wrap gap-2 text-xs font-semibold text-amber-800">
            <span className="rounded-full bg-white/70 px-2.5 py-1 ring-1 ring-amber-200">
              Current phase: {statusLabel(gate.currentStatusLabel)}
            </span>
            <span className="rounded-full bg-white/70 px-2.5 py-1 ring-1 ring-amber-200">
              Unlocks at: {statusLabel(gate.minimumStatus)}
            </span>
          </div>
          {engagementId && (
            <Link
              className="mt-4 inline-flex min-h-10 items-center rounded-lg bg-sky-700 px-3 py-2 text-xs font-bold text-white hover:bg-sky-800"
              to={workspaceActionPath(gate.action, engagementId)}
            >
              {gate.actionLabel}
            </Link>
          )}
        </div>
      </div>
    </section>
  );
}

export function AemsWorkspaceGate({ workspace, keyName, engagementId }) {
  const gate = getAemsWorkspaceGate(keyName, workspace?.engagement);
  return <AemsWorkspaceLockNotice engagementId={engagementId} gate={gate} />;
}
