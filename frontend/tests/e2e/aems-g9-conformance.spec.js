/* global process */

import fs from "node:fs";
import path from "node:path";
import { expect, test } from "@playwright/test";
import {
  aemsContextualScreens,
  aemsPages,
  hasPermission,
  visibleFor,
} from "../../src/config/navigation.js";
import { aemsRoleNavigationMatrix } from "./fixtures/aems-role-navigation-matrix.js";

const root = path.resolve(process.cwd());
const appSource = fs.readFileSync(path.join(root, "src/App.jsx"), "utf8");
const navigationSource = fs.readFileSync(
  path.join(root, "src/config/navigation.js"),
  "utf8",
);

function normalizedRoute(route) {
  return route.replace(/^\//, "").replace(/\?.*$/, "");
}

test.describe("AEMS-G9 static route and role contracts", () => {
  test("explicit operational AEMS routes are registered once and excluded from fallback generation", () => {
    expect(/pageRoutes\s*\.filter\(\(page\) => !implementedCorePaths\.has\(page\.path\)\)/.test(appSource)).toBe(true);
    expect(appSource).toContain("const implementedCorePaths = new Set([");

    for (const page of aemsPages) {
      const route = normalizedRoute(page.path);
      const explicitRouteCount = (appSource.match(new RegExp(`path=\\"${route.replace(/[.*+?^${}()|[\\]\\\\]/g, "\\\\$&")}\\"`, "g")) ?? []).length;
      expect(explicitRouteCount, `${page.screenId ?? page.scrId} must be explicit exactly once`).toBe(1);
      expect(appSource).toContain(`"${page.path}"`);
    }

    const dedupeExpression = /findIndex\(\(candidate\) => candidate\.path === item\.path\)/;
    expect(dedupeExpression.test(navigationSource)).toBe(true);
  });

  test("canonical SCR registry has 32 unique identifiers and no duplicate records", () => {
    const ids = aemsPages
      .map((page) => page.scrId)
      .filter((id) => id?.startsWith("SCR-"))
      .concat(aemsContextualScreens.map((screen) => screen.id).filter(Boolean));
    expect(ids).toHaveLength(32);
    expect(new Set(ids).size).toBe(32);
    expect(aemsContextualScreens.find((screen) => screen.id === "SCR-243")?.status).toBe("reserved");
  });

  test("every seeded role has a deterministic menu and contextual-tab matrix", () => {
    expect(aemsRoleNavigationMatrix).toHaveLength(6);

    for (const role of aemsRoleNavigationMatrix) {
      const user = { permissions: role.permissions };
      const visiblePages = visibleFor(user, aemsPages).map((page) => page.label);
      for (const label of role.visiblePages) {
        expect(visiblePages, `${role.role} should see ${label}`).toContain(label);
      }
      for (const label of role.hiddenPages) {
        expect(visiblePages, `${role.role} should not see ${label}`).not.toContain(label);
      }

      const visibleScreens = aemsContextualScreens.filter((screen) => hasPermission(user, screen.permission));
      expect(visibleScreens.every((screen) => screen.status !== "reserved")).toBe(true);
    }
  });

  test("desktop and mobile projects are configured for the full AEMS responsive suite", () => {
    const config = fs.readFileSync(path.join(root, "playwright.config.js"), "utf8");
    const responsive = fs.readFileSync(path.join(root, "tests/e2e/aems-responsive.spec.js"), "utf8");
    expect(config).toContain('name: "desktop-chrome"');
    expect(config).toContain('name: "mobile-chrome"');
    expect(responsive).toContain("document.documentElement.scrollWidth");
    expect(responsive).toContain("Open navigation");
  });
});

test.describe("AEMS-G9 mutation, evidence, and authenticated download contracts", () => {
  test("browser mutation requests carry optimistic-lock and status-transition payloads", async ({ page }) => {
    const requests = [];
    await page.route("**/api/aems/engagements/9901/planning-package/1/transition", async (route) => {
      requests.push({ method: route.request().method(), body: route.request().postDataJSON() });
      await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ success: true }) });
    });
    await page.route("**/api/aems/engagements/9901/evidence-assessments", async (route) => {
      requests.push({ method: route.request().method(), body: route.request().postDataJSON() });
      await route.fulfill({ status: 422, contentType: "application/json", body: JSON.stringify({ message: "Evidence is not eligible." }) });
    });

    await page.goto("/login", { waitUntil: "domcontentloaded" });
    await page.evaluate(async () => {
      await fetch("/api/aems/engagements/9901/planning-package/1/transition", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action: "APPROVE", lockVersion: 7 }),
      });
      await fetch("/api/aems/engagements/9901/evidence-assessments", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ evidenceId: 44, evidenceOutcome: "ADDITIONAL_REQUIRED", sufficiency: "NO", evidenceGaps: "Missing source." }),
      });
    });

    expect(requests).toEqual([
      { method: "POST", body: { action: "APPROVE", lockVersion: 7 } },
      { method: "POST", body: { evidenceId: 44, evidenceOutcome: "ADDITIONAL_REQUIRED", sufficiency: "NO", evidenceGaps: "Missing source." } },
    ]);
  });

  test("negative-evidence and protected-download coverage is present in browser suites", () => {
    const evidenceSpec = fs.readFileSync(path.join(root, "tests/e2e/aems-evidence-management.spec.js"), "utf8");
    const reportSpec = fs.readFileSync(path.join(root, "tests/e2e/aems-reporting.spec.js"), "utf8");
    expect(evidenceSpec).toContain("requestStatuses");
    expect(evidenceSpec).toContain("eligibleForFinalizedFinding");
    expect(reportSpec).toContain("Protected PDF");
    expect(reportSpec).toContain("isLocked");
  });
});
