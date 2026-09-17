import { defineConfig, devices } from "@playwright/test";
import { env } from "node:process";

const backendPort = env.PLAYWRIGHT_BACKEND_PORT || "8000";
const frontendPort = env.PLAYWRIGHT_FRONTEND_PORT || "5173";
const backendUrl = `http://127.0.0.1:${backendPort}`;
const frontendUrl = `http://127.0.0.1:${frontendPort}`;

export default defineConfig({
  testDir: "./tests/e2e",
  timeout: 75_000,
  expect: {
    timeout: 10_000,
  },
  fullyParallel: false,
  workers: 1,
  retries: 0,
  reporter: "line",
  use: {
    baseURL: frontendUrl,
    channel: "chrome",
    screenshot: "only-on-failure",
    trace: "retain-on-failure",
  },
  projects: [
    {
      name: "desktop-chrome",
      use: {
        ...devices["Desktop Chrome"],
        viewport: { width: 1440, height: 900 },
      },
    },
    {
      name: "mobile-chrome",
      use: {
        ...devices["Pixel 5"],
        channel: "chrome",
      },
    },
  ],
  webServer: [
    {
      command: `php artisan serve --host=127.0.0.1 --port=${backendPort}`,
      cwd: "./backend",
      env: {
        ...env,
        APP_ENV: "testing",
        AGIS_E2E_BROWSER: "true",
        APP_URL: backendUrl,
        SANCTUM_STATEFUL_DOMAINS: `127.0.0.1:${frontendPort},localhost:${frontendPort},127.0.0.1,localhost`,
      },
      url: `${backendUrl}/health`,
      reuseExistingServer: true,
      timeout: 120_000,
    },
    {
      command: `npm.cmd run dev -- --host=127.0.0.1 --port=${frontendPort}`,
      env: {
        ...env,
        VITE_API_PROXY_TARGET: backendUrl,
      },
      url: `${frontendUrl}/login`,
      reuseExistingServer: true,
      timeout: 120_000,
    },
  ],
});
