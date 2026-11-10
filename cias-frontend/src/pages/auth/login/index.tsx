import { AuthLayout } from "../../../layouts/auth/AuthLayout";
import bg from "../../../assets/images/bg.png";
import ciasLogo from "../../../assets/images/cias-logo.png";
import rise from "../../../assets/images/rise.png";
import seal from "../../../assets/images/seal.png";
import { LoginForm } from "./components/LoginForm";

export function LoginPage() {
  return (
    <AuthLayout>
      <div className="relative min-h-screen overflow-hidden bg-[#0e3460] text-white">
        {/* Full-bleed background image wrapper */}
        <div className="absolute inset-0 z-0">
          <div
            className="absolute inset-0 bg-cover bg-center bg-no-repeat"
            style={{ backgroundImage: `url(${bg})` }}
          />
          {/* Rich blue gradient overlay across the full background */}
          <div className="absolute inset-0 bg-[linear-gradient(90deg,rgba(16,80,150,0.35)_0%,rgba(12,50,105,0.6)_50%,rgba(10,38,80,0.82)_100%)]" />
        </div>

        <div className="relative z-10 flex min-h-screen flex-col justify-between">
          {/* Logos pinned to the bottom-left of the viewport */}
          <div className="absolute bottom-[52px] left-6 z-20 hidden items-end gap-5 lg:flex xl:left-10">
            <img
              alt="City seal"
              className="h-36 w-36 object-contain drop-shadow-[0_4px_16px_rgba(0,0,0,0.3)]"
              src={seal}
            />
            <img
              alt="RISE logo"
              className="h-[115px] w-auto object-contain drop-shadow-[0_4px_16px_rgba(0,0,0,0.3)]"
              src={rise}
            />
          </div>

          <main className="flex flex-1 items-center justify-center px-5 py-4 sm:px-6 lg:px-16">
            <div className="mx-auto grid w-full max-w-[1230px] items-stretch gap-6 lg:grid-cols-[1.3fr_0.9fr]">
              {/* Left Section: Empty space in grid */}
              <section className="hidden lg:block min-h-[640px]" />

              {/* Right Section: Login Form Box */}
              <section className="flex items-center justify-center lg:justify-end">
                <div className="w-full max-w-[490px] rounded-[8px] border border-white/40 bg-[#0e528c]/80 px-12 py-11 shadow-[0_20px_50px_rgba(0,0,0,0.35)] backdrop-blur-md">
                  <div className="text-center">
                    <div className="mx-auto flex h-[175px] w-[175px] items-center justify-center overflow-hidden">
                      <img
                        alt="CIAS logo"
                        className="h-full w-full object-contain scale-[2.2]"
                        src={ciasLogo}
                      />
                    </div>
                    <h1 className="mx-auto mt-4 max-w-[400px] text-[1.25rem] font-bold tracking-tight text-white leading-snug">
                      Audit Governance Information System
                    </h1>
                    <div className="mx-auto mt-5 h-[1px] w-full bg-white/30" />
                  </div>

                  <div className="mt-8 text-center">
                    <h2 className="text-[1.45rem] font-normal tracking-wide text-white">
                      Sign in to your account
                    </h2>
                    <p className="mt-2 text-sm text-white/80">
                      Enter your credentials to access AGIS.
                    </p>
                  </div>

                  <LoginForm />
                </div>
              </section>
            </div>
          </main>

          <footer className="bg-[#122849] px-5 py-4 text-xs text-white/80 sm:px-6 lg:px-16">
            <div className="mx-auto flex max-w-[1230px] flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
              <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                <span>AGIS v1.0.0</span>
                <span>City Internal Audit Service (CIAS)</span>
                <span className="opacity-40">|</span>
                <span>Privacy Policy</span>
                <span className="opacity-40">|</span>
                <span>Terms of Use</span>
              </div>
              <span>
                © 2026 City Government of Cagayan de Oro. All rights reserved.
              </span>
            </div>
          </footer>
        </div>
      </div>
    </AuthLayout>
  );
}
