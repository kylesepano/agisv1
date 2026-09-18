/* global process */

import fs from "node:fs";
import path from "node:path";
import { expect, test } from "@playwright/test";
import { aemsContextualScreens, aemsPages } from "../../src/config/navigation.js";
import { aemsRoleNavigationMatrix } from "./fixtures/aems-role-navigation-matrix.js";

const root = path.resolve(process.cwd());

test.describe("AEMS-G10E final governance and acceptance contract", () => {
  test("governance decisions, status map, rules, and role matrix are published", () => {
    const governance = fs.readFileSync(path.join(root, "docs/AEMS_GOVERNANCE_AND_ACCEPTANCE.md"), "utf8");
    const acceptance = governance;
    const backendAcceptance = fs.readFileSync(path.join(root, "backend/tests/Feature/Api/AemsG10EAcceptanceTest.php"), "utf8");
    expect(governance).toContain("G0-14");
    expect(governance).toContain("Status compatibility map");
    expect(acceptance).toContain("35/35");
    expect(acceptance).toContain("32/32");
    expect(new Set(backendAcceptance.match(/'Rule \d{2}'/g) ?? []).size).toBe(35);
    expect(aemsRoleNavigationMatrix).toHaveLength(6);
  });

  test("canonical AEMS screens remain unique and explicit operational pages are protected", () => {
    const app = fs.readFileSync(path.join(root, "src/App.jsx"), "utf8");
    const navigation = fs.readFileSync(path.join(root, "src/config/navigation.js"), "utf8");
    const ids = aemsPages.map((page) => page.scrId).filter((id) => id?.startsWith("SCR-")).concat(
      aemsContextualScreens.map((screen) => screen.id).filter((id) => id?.startsWith("SCR-")),
    );
    expect(ids).toHaveLength(32);
    expect(new Set(ids).size).toBe(32);
    for (const page of aemsPages) {
      const route = page.path.replace(/^\//, "").replace(/\?.*$/, "");
      expect((app.match(new RegExp('path="' + route + '"', "g")) ?? [])).toHaveLength(1);
      expect(app.includes('"' + page.path + '"')).toBe(true);
    }
    expect(navigation).toContain("AEMS-RECORDS-CLOSURE");
    expect(app).toContain("/audit-engagement-management/records-closure");
    expect(app).toContain("implementedCorePaths");
  });

  test("full responsive acceptance projects remain configured", () => {
    const config = fs.readFileSync(path.join(root, "playwright.config.js"), "utf8");
    expect(config).toContain('name: "desktop-chrome"');
    expect(config).toContain('name: "mobile-chrome"');
    expect(fs.existsSync(path.join(root, "tests/e2e/aems-responsive.spec.js"))).toBe(true);
    expect(fs.existsSync(path.join(root, "tests/e2e/aems-g10d-records-closure.spec.js"))).toBe(true);
  });
});
