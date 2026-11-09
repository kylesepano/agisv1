import { defineConfig, devices } from "@playwright/test";

export default defineConfig({
  testDir: "./tests/e2e",
  testMatch: "aems-reference-survey.spec.js",
  fullyParallel: false,
  workers: 1,
  timeout: 60000,
  reporter: "line",
  use: { baseURL: "http://127.0.0.1:5184", channel: "chrome", screenshot: "only-on-failure" },
  projects: [
    { name: "survey-desktop", use: { viewport: { width: 1720, height: 1100 } } },
    { name: "survey-mobile", use: { ...devices["Pixel 5"], channel: "chrome" } },
  ],
  webServer: { command: "npm.cmd run dev -- --host=127.0.0.1 --port=5184", url: "http://127.0.0.1:5184", reuseExistingServer: true },
});
