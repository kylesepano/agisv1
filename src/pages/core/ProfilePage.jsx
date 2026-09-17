import { useEffect, useState } from "react";
import { KeyRound, Save, UserRound } from "lucide-react";
import { useAuth } from "../../auth/auth-context";
import ConfirmDialog from "../../components/ui/ConfirmDialog";
import FormField from "../../components/ui/FormField";
import RegistryHeader from "../../components/ui/RegistryHeader";
import SearchableSelect from "../../components/ui/SearchableSelect";
import { ApiError, profileApi } from "../../services/api";
import { useToast } from "../../ui/toast-context";

const inputClass =
  "h-11 w-full rounded-lg border border-slate-300 px-3 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100";

/**
 * Lets the signed-in user maintain normalized personal and employment details
 * and change credentials subject to server-side security policies.
 */
export default function ProfilePage() {
  const { refreshUser, runtimeConfig } = useAuth();
  const toast = useToast();
  const [profile, setProfile] = useState(null);
  const [passwords, setPasswords] = useState({
    currentPassword: "",
    password: "",
    password_confirmation: "",
  });
  const [errors, setErrors] = useState({});
  const [saving, setSaving] = useState(false);
  const [confirmAction, setConfirmAction] = useState(null);

  useEffect(() => {
    let active = true;
    profileApi
      .show()
      .then((record) => active && setProfile(record))
      .catch((error) => active && toast.error(error.message));
    return () => {
      active = false;
    };
  }, [toast]);

  function saveProfile(event) {
    event.preventDefault();
    setConfirmAction("profile");
  }

  async function persistProfile() {
    setSaving(true);
    setErrors({});
    try {
      const data = await profileApi.update(profile);
      setProfile(data.profile);
      await refreshUser();
      setConfirmAction(null);
      toast.success("Profile updated successfully.");
    } catch (error) {
      if (error instanceof ApiError && error.status === 422)
        setErrors(error.errors);
      toast.error(error.message);
    } finally {
      setSaving(false);
    }
  }

  function changePassword(event) {
    event.preventDefault();
    setConfirmAction("password");
  }

  async function persistPassword() {
    setSaving(true);
    setErrors({});
    try {
      await profileApi.changePassword(passwords);
      setPasswords({
        currentPassword: "",
        password: "",
        password_confirmation: "",
      });
      setConfirmAction(null);
      toast.success("Password changed successfully.");
    } catch (error) {
      if (error instanceof ApiError && error.status === 422)
        setErrors(error.errors);
      toast.error(error.message);
    } finally {
      setSaving(false);
    }
  }

  if (!profile) {
    return (
      <div className="grid min-h-[60vh] place-items-center text-sm font-semibold text-slate-500">
        Loading profile...
      </div>
    );
  }

  return (
    <div className="p-4 sm:p-6">
      <RegistryHeader
        description="Update your personal information and protect your account password."
        icon={UserRound}
        title="My Profile"
      />
      <div className="grid gap-4 xl:grid-cols-[1.2fr_.8fr]">
        <form
          className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
          onSubmit={saveProfile}
        >
          <div className="mb-5 flex items-center gap-4 border-b pb-5">
            <span className="grid h-16 w-16 place-items-center rounded-full bg-sky-700 text-xl font-bold text-white">
              {profile.initials}
            </span>
            <div>
              <h3 className="text-lg font-bold text-slate-800">
                {profile.name}
              </h3>
              <p className="text-sm text-slate-500">
                {profile.role} · {profile.officeCode}
              </p>
              <span className="text-xs text-slate-400">
                {profile.employeeId || "No employee ID"} ·{" "}
                {profile.position || "No position"}
              </span>
            </div>
          </div>
          <div className="grid gap-4 sm:grid-cols-2">
            {[
              ["employeeId", "Employee ID", "text"],
              ["firstName", "First name", "text"],
              ["middleName", "Middle name", "text"],
              ["lastName", "Last name", "text"],
              ["extension", "Name extension", "text"],
              ["email", "Email", "email"],
              ["contactNumber", "Contact number", "text"],
              ["birthDate", "Birth date", "date"],
            ].map(([name, label, type]) => (
              <FormField
                error={errors[name]?.[0]}
                htmlFor={`profile-${name}`}
                key={name}
                label={label}
                required={["employeeId", "firstName", "lastName"].includes(
                  name,
                )}
              >
                <input
                  className={inputClass}
                  id={`profile-${name}`}
                  onChange={(event) =>
                    setProfile({ ...profile, [name]: event.target.value })
                  }
                  required={["employeeId", "firstName", "lastName"].includes(
                    name,
                  )}
                  type={type}
                  value={profile[name] ?? ""}
                />
              </FormField>
            ))}
            <FormField
              error={errors.position?.[0]}
              label="Position"
              hint="Search the position catalogue or enter an authorized custom title."
            >
              <SearchableSelect
                allowCustom
                onChange={(position) => setProfile({ ...profile, position })}
                options={profile.positionOptions ?? []}
                placeholder="Select or type a position"
                searchPlaceholder="Search or type a position..."
                value={profile.position}
              />
            </FormField>
            <FormField
              error={errors.employmentType?.[0]}
              label="Government employment type"
            >
              <SearchableSelect
                onChange={(employmentType) =>
                  setProfile({ ...profile, employmentType })
                }
                options={profile.employmentTypeOptions ?? []}
                placeholder="Select employment type"
                searchPlaceholder="Search employment types..."
                value={profile.employmentType}
              />
            </FormField>
          </div>
          <button
            className="mt-5 flex h-11 items-center gap-2 rounded-lg bg-sky-700 px-5 text-sm font-bold text-white"
            disabled={saving}
            type="submit"
          >
            <Save size={17} /> Save profile
          </button>
        </form>

        <form
          className="self-start rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
          onSubmit={changePassword}
        >
          <h3 className="flex items-center gap-2 text-lg font-bold text-slate-800">
            <KeyRound size={20} /> Change password
          </h3>
          <p className="mt-1 text-xs leading-5 text-slate-500">
            Demo accounts initially use <strong>lala</strong>. Enter your
            current password before selecting a new one with at least{" "}
            <strong>{runtimeConfig.passwordMinLength} characters</strong>.
          </p>
          <div className="mt-5 grid gap-4">
            {[
              ["currentPassword", "Current password"],
              ["password", "New password"],
              ["password_confirmation", "Confirm new password"],
            ].map(([name, label]) => (
              <FormField
                error={errors[name]?.[0]}
                htmlFor={`password-${name}`}
                key={name}
                label={label}
              >
                <input
                  className={inputClass}
                  id={`password-${name}`}
                  minLength={
                    name === "currentPassword"
                      ? undefined
                      : runtimeConfig.passwordMinLength
                  }
                  onChange={(event) =>
                    setPasswords({
                      ...passwords,
                      [name]: event.target.value,
                    })
                  }
                  type="password"
                  value={passwords[name]}
                />
              </FormField>
            ))}
          </div>
          <button
            className="mt-5 h-11 rounded-lg bg-slate-800 px-5 text-sm font-bold text-white"
            disabled={saving}
            type="submit"
          >
            Change password
          </button>
        </form>
      </div>
      <ConfirmDialog
        busy={saving}
        confirmLabel={
          confirmAction === "password" ? "Change password" : "Update profile"
        }
        description={
          confirmAction === "password"
            ? "Your current password will stop working immediately."
            : "Save these changes to your AGIS profile?"
        }
        onCancel={() => setConfirmAction(null)}
        onConfirm={
          confirmAction === "password" ? persistPassword : persistProfile
        }
        open={Boolean(confirmAction)}
        title={
          confirmAction === "password"
            ? "Confirm password change"
            : "Confirm profile update"
        }
        tone={confirmAction === "password" ? "warning" : "primary"}
      />
    </div>
  );
}
