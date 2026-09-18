import { useEffect, useMemo, useState } from "react";
import { ImageUp, MailCheck, Save, Settings } from "lucide-react";
import { useAuth } from "../../auth/auth-context";
import ConfirmDialog from "../../components/ui/ConfirmDialog";
import RegistryHeader from "../../components/ui/RegistryHeader";
import { hasPermission } from "../../config/navigation";
import { configurationApi } from "../../services/api";
import { useToast } from "../../ui/toast-context";

/**
 * Edits administrator-controlled runtime settings such as branding, security,
 * uploads, numbering, notifications, and IAP defaults.
 */
export default function SystemConfigurationPage() {
  const { user, refreshRuntimeConfiguration } = useAuth();
  const toast = useToast();
  const [configurations, setConfigurations] = useState([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [confirmOpen, setConfirmOpen] = useState(false);
  const [logoUploading, setLogoUploading] = useState(false);
  const [testRecipient, setTestRecipient] = useState(user?.email ?? "");
  const [testingEmail, setTestingEmail] = useState(false);
  const canManage = hasPermission(user, "system_configuration.manage");

  useEffect(() => {
    let active = true;
    configurationApi
      .list()
      .then((records) => active && setConfigurations(records))
      .catch((error) => active && toast.error(error.message))
      .finally(() => active && setLoading(false));
    return () => {
      active = false;
    };
  }, [toast]);

  const groups = useMemo(
    () =>
      configurations.reduce((result, configuration) => {
        const current = result[configuration.group] ?? [];
        return {
          ...result,
          [configuration.group]: [...current, configuration],
        };
      }, {}),
    [configurations],
  );

  function change(key, value) {
    setConfigurations((current) =>
      current.map((configuration) =>
        configuration.key === key ? { ...configuration, value } : configuration,
      ),
    );
  }

  async function save() {
    setSaving(true);
    try {
      await configurationApi.update(
        configurations.map(({ key, value }) => ({ key, value })),
      );
      await refreshRuntimeConfiguration();
      setConfirmOpen(false);
      toast.success("System configuration updated successfully.");
    } catch (error) {
      toast.error(error.message);
    } finally {
      setSaving(false);
    }
  }

  async function uploadLogo(file) {
    if (!file) return;
    setLogoUploading(true);
    try {
      const runtime = await configurationApi.uploadLogo(file);
      change("logo_url", runtime?.logoUrl ?? "");
      await refreshRuntimeConfiguration();
      toast.success("Runtime logo updated successfully.");
    } catch (error) {
      toast.error(error.message);
    } finally {
      setLogoUploading(false);
    }
  }

  async function testEmail() {
    setTestingEmail(true);
    try {
      await configurationApi.testEmail(testRecipient);
      toast.success("Test email sent successfully.");
    } catch (error) {
      toast.error(error.message);
    } finally {
      setTestingEmail(false);
    }
  }

  return (
    <div className="p-4 sm:p-6">
      <RegistryHeader
        actions={
          canManage && (
            <button
              className="flex h-11 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white"
              disabled={saving}
              onClick={() => setConfirmOpen(true)}
              type="button"
            >
              <Save size={17} /> {saving ? "Saving..." : "Save settings"}
            </button>
          )
        }
        description="Manage organization-wide display, regional, security, and platform defaults."
        icon={Settings}
        readOnly={!canManage}
        title="System Configuration"
      />
      <div className="grid gap-4 xl:grid-cols-2">
        {loading && (
          <div className="h-72 animate-pulse rounded-xl bg-white shadow-sm" />
        )}
        {!loading &&
          Object.entries(groups).map(([group, items]) => (
            <section
              className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
              key={group}
            >
              <h3 className="mb-4 text-sm font-bold uppercase tracking-wide text-slate-600">
                {group}
              </h3>
              <div className="grid gap-4">
                {items.map((configuration) => (
                  <label key={configuration.key}>
                    <span className="text-sm font-bold text-slate-700">
                      {configuration.name}
                    </span>
                    <small className="mt-0.5 block text-xs leading-5 text-slate-500">
                      {configuration.description}
                    </small>
                    {configuration.key === "logo_url" ? (
                      <div className="mt-2 flex flex-wrap items-center gap-3">
                        <img
                          alt="Current runtime logo"
                          className="h-16 w-16 rounded-lg border border-slate-200 object-contain p-1"
                          src={configuration.value || "/logo.png"}
                        />
                        {canManage && (
                          <label className="inline-flex h-11 cursor-pointer items-center gap-2 rounded-lg border border-sky-300 px-4 text-sm font-bold text-sky-700 hover:bg-sky-50">
                            <ImageUp size={17} />
                            {logoUploading ? "Uploading..." : "Replace logo"}
                            <input
                              accept=".png,.jpg,.jpeg,.webp"
                              className="hidden"
                              disabled={logoUploading}
                              onChange={(event) =>
                                uploadLogo(event.target.files?.[0])
                              }
                              type="file"
                            />
                          </label>
                        )}
                      </div>
                    ) : configuration.constraints?.options?.length ? (
                      <select
                        className="mt-2 h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm outline-none focus:border-sky-500 disabled:bg-slate-100"
                        disabled={!canManage}
                        onChange={(event) =>
                          change(configuration.key, event.target.value)
                        }
                        value={configuration.value}
                      >
                        {configuration.constraints.options.map((option) => (
                          <option
                            key={String(option?.value ?? option)}
                            value={String(option?.value ?? option)}
                          >
                            {option?.label ??
                              (option === true
                                ? "Enabled"
                                : option === false
                                  ? "Disabled"
                                  : option || "None")}
                          </option>
                        ))}
                      </select>
                    ) : (
                      <input
                        className="mt-2 h-11 w-full rounded-lg border border-slate-300 px-3 text-sm outline-none focus:border-sky-500 disabled:bg-slate-100"
                        disabled={!canManage}
                        max={configuration.constraints?.max}
                        min={configuration.constraints?.min}
                        onChange={(event) =>
                          change(configuration.key, event.target.value)
                        }
                        type={
                          configuration.type === "secret"
                            ? "password"
                            : configuration.type === "integer"
                              ? "number"
                              : "text"
                        }
                        value={configuration.value}
                      />
                    )}
                    {configuration.constraints?.runtimeEffect && (
                      <span className="mt-1.5 block text-[11px] font-medium text-emerald-700">
                        Applies immediately:{" "}
                        {configuration.constraints.runtimeEffect}.
                      </span>
                    )}
                  </label>
                ))}
              </div>
              {group === "email" && canManage && (
                <div className="mt-5 border-t border-slate-200 pt-4">
                  <label className="text-sm font-bold text-slate-700">
                    Send configuration test
                  </label>
                  <div className="mt-2 flex gap-2">
                    <input
                      className="h-11 min-w-0 flex-1 rounded-lg border border-slate-300 px-3 text-sm outline-none focus:border-sky-500"
                      onChange={(event) => setTestRecipient(event.target.value)}
                      placeholder="recipient@example.gov.ph"
                      type="email"
                      value={testRecipient}
                    />
                    <button
                      className="inline-flex h-11 items-center gap-2 rounded-lg bg-emerald-700 px-4 text-sm font-bold text-white disabled:opacity-60"
                      disabled={testingEmail || !testRecipient}
                      onClick={testEmail}
                      type="button"
                    >
                      <MailCheck size={17} />
                      {testingEmail ? "Sending..." : "Send test"}
                    </button>
                  </div>
                </div>
              )}
            </section>
          ))}
      </div>
      <ConfirmDialog
        busy={saving}
        confirmLabel="Save settings"
        description="These organization-wide settings may affect all AGIS users and modules."
        onCancel={() => setConfirmOpen(false)}
        onConfirm={save}
        open={confirmOpen}
        title="Update system configuration?"
        tone="warning"
      />
    </div>
  );
}
