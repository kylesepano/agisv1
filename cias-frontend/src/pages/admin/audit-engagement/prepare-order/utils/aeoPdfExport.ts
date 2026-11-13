import type { PrepareAeoFormState } from "../prepare-order-types";

/**
 * MDS-200 v0.8 §18 Compliant AEO PDF Export & Print Generator
 * Faithfully reproduces the official CIAS Audit Engagement Order layout.
 */
export function exportAeoToPdf(formState: PrepareAeoFormState): void {
  const printWindow = window.open("", "_blank", "width=900,height=1100");
  if (!printWindow) {
    alert("Popup blocker prevented opening the print document. Please allow popups for this site.");
    return;
  }

  const classification = formState.confidentialityClassification || "Normal";
  const classificationText =
    classification === "Confidential"
      ? "CONFIDENTIAL – EXECUTIVE & CIAS LEADERSHIP ONLY"
      : classification === "Restricted"
        ? "RESTRICTED – DESIGNATED AUDIT TEAM & OFFICE HEAD ONLY"
        : "NORMAL – STANDARD DEPARTMENTAL ACCESS";

  const aeoNumber = formState.aeoNumber;
  const version = formState.version || "v1.0";
  const status = (formState.status || "Issued").toUpperCase();

  // Format Dates nicely
  const formatDateString = (dateStr?: string) => {
    if (!dateStr) return new Date().toLocaleDateString("en-US", { month: "long", day: "numeric", year: "numeric" });
    try {
      const d = new Date(dateStr);
      if (isNaN(d.getTime())) return dateStr;
      return d.toLocaleDateString("en-US", { month: "long", day: "numeric", year: "numeric" });
    } catch {
      return dateStr;
    }
  };

  const formatShortDate = (dateStr?: string) => {
    if (!dateStr) return new Date().toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" });
    try {
      const d = new Date(dateStr);
      if (isNaN(d.getTime())) return dateStr;
      return d.toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" });
    } catch {
      return dateStr;
    }
  };

  const issueDateFormatted = formatDateString(formState.effectiveDate || formState.approvalDate);
  const approvalDateShort = formatShortDate(formState.approvalDate || formState.effectiveDate);
  const reviewDateShort = formatShortDate(formState.reviewDate || formState.approvalDate);

  const office = formState.baseline?.office || "City Treasurer's Office";
  const auditType = formState.baseline?.auditType || "Operations";
  const auditArea =
    formState.baseline?.auditAreas?.[0] ||
    formState.baseline?.engagementTitle ||
    "Cash and Revenue Collection Management";

  const authorityRef =
    formState.baseline?.authorityReference ||
    "Special Audit Directive / Executive Order No. 2024-002";
  const directingAuth =
    formState.baseline?.directingAuthority ||
    "Special Executive / Mayor Directive";

  // Format numbered directives (4.1, 4.2, ...)
  const rawDirectives = formState.engagementDirectives || "";
  const directiveItems = rawDirectives
    .split("\n")
    .map((line) => line.trim())
    .filter(Boolean)
    .map((line) => line.replace(/^\d+[\.\)]\s*/, ""));

  const directivesHtml =
    directiveItems.length > 0
      ? directiveItems.map((item, idx) => `<p class="sub-item"><strong>4.${idx + 1}</strong> ${item}</p>`).join("")
      : `<p class="sub-item"><strong>4.1</strong> Full cooperation and unhindered access to all relevant files, accounts, facilities, personnel, and electronic systems shall be extended to the designated Audit Team.</p>
         <p class="sub-item"><strong>4.2</strong> A primary liaison officer shall be designated within two (2) working days of receipt of this Order.</p>
         <p class="sub-item"><strong>4.3</strong> An Entry Conference shall be scheduled pursuant to CIAS protocol.</p>`;

  // Format numbered deliverables (6.1, 6.2, ...)
  const rawDeliverables = formState.targetDeliverables || "";
  const deliverableItems = rawDeliverables
    .split("\n")
    .map((line) => line.trim())
    .filter(Boolean)
    .map((line) => line.replace(/^\d+[\.\)]\s*/, ""));

  const deliverablesHtml =
    deliverableItems.length > 0
      ? deliverableItems.map((item, idx) => `<p class="sub-item"><strong>6.${idx + 1}</strong> ${item}</p>`).join("")
      : `<p class="sub-item"><strong>6.1</strong> Audit Engagement Plan (AEP) and Testing Programs</p>
         <p class="sub-item"><strong>6.2</strong> Interim Audit Observations and Management Feedback</p>
         <p class="sub-item"><strong>6.3</strong> Final Audit Report and Corrective Action Plan (CAP)</p>`;

  // Recipients Table Rows
  const recipients = formState.recipients && formState.recipients.length > 0
    ? formState.recipients
    : [
        {
          id: "REC-01",
          name: "Department Head / Head of Office",
          title: "Head of Office",
          office: office,
          email: "",
          deliveryMethod: "Digital Notification" as const,
          distributionStatus: "Acknowledged" as const,
        },
        {
          id: "REC-02",
          name: "Atty. Roy Hilario P. Raagas",
          title: "City Administrator",
          office: "Office of the City Administrator",
          email: "",
          deliveryMethod: "Digital Notification" as const,
          distributionStatus: "Acknowledged" as const,
        },
        {
          id: "REC-03",
          name: "Atty. Kirsten Kate F. Calvo, CPA",
          title: "City Accountant",
          office: "City Accounting Department",
          email: "",
          deliveryMethod: "Digital Notification" as const,
          distributionStatus: "Acknowledged" as const,
        },
        {
          id: "REC-04",
          name: "Rolando A. Uy",
          title: "City Mayor / Directing Authority",
          office: "Office of the City Mayor",
          email: "",
          deliveryMethod: "Official Courier" as const,
          distributionStatus: "Acknowledged" as const,
        },
      ];

  const recipientsTableHtml = recipients
    .map(
      (r) => `
      <tr>
        <td style="font-weight: 500;">
          ${r.name}${r.title && !r.name.includes(r.title) ? `, ${r.title}` : ""}
        </td>
        <td>${r.office}</td>
        <td>${r.deliveryMethod || "Digital Notification"}</td>
        <td style="text-align: center;">${r.distributionStatus || "Acknowledged"}</td>
      </tr>
    `,
    )
    .join("");

  const footerText = `${aeoNumber} · ${version} · ${status} — Controlled Document. Superseded copies are void upon reissuance.`;

  const htmlContent = `
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>${aeoNumber} — ${version} (${status})</title>
  <style>
    @page {
      size: A4 portrait;
      margin: 18mm 20mm 18mm 20mm;
    }
    * {
      box-sizing: border-box;
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
    }
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      font-size: 11px;
      line-height: 1.5;
      color: #111827;
      background: #e2e8f0;
      margin: 0;
      padding: 20px 0;
    }

    .sheet {
      background: #ffffff;
      width: 210mm;
      min-height: 297mm;
      margin: 0 auto 20px auto;
      padding: 22mm 22mm 20mm 22mm;
      position: relative;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      overflow: hidden;
    }

    /* Diagonal Background Watermark */
    .watermark {
      position: absolute;
      top: 48%;
      left: 50%;
      transform: translate(-50%, -50%) rotate(-35deg);
      font-size: 68px;
      font-weight: 800;
      color: rgba(15, 23, 42, 0.038);
      white-space: nowrap;
      pointer-events: none;
      z-index: 0;
      letter-spacing: 2px;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      text-transform: uppercase;
    }

    .page-content {
      position: relative;
      z-index: 1;
    }

    /* Header */
    .header-center {
      text-align: center;
      margin-bottom: 12px;
    }
    .header-agency {
      font-family: Georgia, "Times New Roman", Times, serif;
      font-size: 13px;
      font-weight: 700;
      color: #0f172a;
      letter-spacing: 0.5px;
      text-transform: uppercase;
      margin-bottom: 2px;
    }
    .header-subtitle {
      font-size: 9.5px;
      color: #475569;
      letter-spacing: 0.2px;
    }
    .header-line {
      border: none;
      border-top: 1px solid #111827;
      margin: 8px 0 10px 0;
    }

    /* Confidentiality Banner */
    .confidentiality-bar {
      background-color: #8b5e20;
      color: #ffffff;
      text-align: center;
      padding: 3.5px 6px;
      font-size: 9.5px;
      font-weight: 700;
      letter-spacing: 0.8px;
      text-transform: uppercase;
      margin-bottom: 16px;
    }

    /* Title */
    .aeo-title-block {
      text-align: center;
      margin-bottom: 14px;
    }
    .aeo-main-title {
      font-family: Georgia, "Times New Roman", Times, serif;
      font-size: 15.5px;
      font-weight: 800;
      color: #0f172a;
      letter-spacing: 0.5px;
      margin: 0 0 3px 0;
      text-transform: uppercase;
    }
    .aeo-mandate-sub {
      font-size: 9.5px;
      color: #475569;
      margin: 0;
    }

    /* Metadata Table */
    .meta-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 14px;
      border: 1px solid #cbd5e1;
    }
    .meta-table th, .meta-table td {
      border: 1px solid #cbd5e1;
      padding: 5px 8px;
      text-align: left;
      font-size: 10px;
    }
    .meta-table th {
      background-color: #ffffff;
      color: #64748b;
      font-weight: 600;
      text-transform: uppercase;
      font-size: 8.5px;
      letter-spacing: 0.3px;
    }
    .meta-table td {
      font-weight: 700;
      color: #0f172a;
    }
    .meta-table td.status-issued {
      color: #15803d;
      font-weight: 800;
    }

    /* Memo Headers (TO / RE / AUTHORITY) */
    .memo-block {
      margin-bottom: 14px;
      font-size: 10.5px;
      line-height: 1.6;
    }
    .memo-line {
      margin-bottom: 4px;
      color: #1f2937;
    }
    .memo-label {
      font-weight: 700;
      color: #111827;
      display: inline-block;
      min-width: 85px;
    }

    /* Numbered Sections */
    .section-title {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      font-size: 10.5px;
      font-weight: 800;
      color: #0f172a;
      text-transform: uppercase;
      margin: 11px 0 3px 0;
      letter-spacing: 0.2px;
    }
    .section-p {
      font-size: 10.5px;
      color: #1f2937;
      text-align: justify;
      margin: 0 0 6px 0;
      line-height: 1.45;
    }
    .sub-item {
      font-size: 10.5px;
      color: #1f2937;
      margin: 2.5px 0;
      line-height: 1.4;
      padding-left: 2px;
    }

    /* Page 2: Sign-Off Block */
    .signoff-container {
      display: flex;
      justify-content: space-between;
      gap: 30px;
      margin-top: 15px;
      margin-bottom: 30px;
    }
    .signoff-box {
      flex: 1;
    }
    .signoff-header {
      font-size: 10px;
      font-weight: 800;
      text-transform: uppercase;
      color: #0f172a;
      margin-bottom: 40px;
    }
    .signoff-rule {
      border: none;
      border-top: 1.5px solid #0f172a;
      margin: 0 0 6px 0;
    }
    .signoff-name {
      font-size: 11px;
      font-weight: 800;
      color: #0f172a;
      margin-bottom: 2px;
    }
    .signoff-role {
      font-size: 9.5px;
      color: #4b5563;
      margin-bottom: 4px;
    }
    .signoff-status {
      font-size: 9px;
      color: #4b5563;
    }

    /* Distribution List Table */
    .dist-title {
      font-size: 10.5px;
      font-weight: 800;
      color: #0f172a;
      text-transform: uppercase;
      margin: 0 0 8px 0;
      letter-spacing: 0.3px;
    }
    .dist-table {
      width: 100%;
      border-collapse: collapse;
      border: 1px solid #cbd5e1;
      font-size: 10px;
    }
    .dist-table th, .dist-table td {
      border: 1px solid #cbd5e1;
      padding: 6px 9px;
      text-align: left;
      vertical-align: top;
    }
    .dist-table th {
      background-color: #f8fafc;
      font-weight: 700;
      color: #334155;
    }
    .dist-table td {
      color: #1f2937;
    }

    /* Footers */
    .sheet-footer {
      border-top: 1px solid #e2e8f0;
      padding-top: 6px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 8.5px;
      font-style: italic;
      color: #64748b;
      position: relative;
      z-index: 1;
    }

    /* Print Control Buttons (Screen Only) */
    .print-bar {
      position: fixed;
      top: 14px;
      right: 18px;
      background: #0f172a;
      padding: 8px 14px;
      border-radius: 8px;
      box-shadow: 0 8px 24px rgba(0,0,0,0.25);
      display: flex;
      gap: 10px;
      z-index: 9999;
    }
    .print-btn {
      background: #2563eb;
      color: white;
      border: none;
      padding: 6px 14px;
      font-size: 11.5px;
      font-weight: 700;
      border-radius: 5px;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 6px;
      transition: background 0.15s;
    }
    .print-btn:hover {
      background: #1d4ed8;
    }
    .close-btn {
      background: #334155;
      color: #e2e8f0;
      border: none;
      padding: 6px 12px;
      font-size: 11.5px;
      font-weight: 600;
      border-radius: 5px;
      cursor: pointer;
    }
    .close-btn:hover {
      background: #475569;
    }

    @media print {
      body {
        background: transparent !important;
        padding: 0 !important;
      }
      .sheet {
        margin: 0 !important;
        box-shadow: none !important;
        page-break-after: always !important;
        width: 100% !important;
        min-height: auto !important;
        height: 100vh !important;
        padding: 0 !important;
      }
      .sheet:last-child {
        page-break-after: avoid !important;
      }
      .print-bar {
        display: none !important;
      }
    }
  </style>
</head>
<body>

  <div class="print-bar">
    <button class="print-btn" onclick="window.print()">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
      Print / Save as PDF
    </button>
    <button class="close-btn" onclick="window.close()">Close</button>
  </div>

  <!-- PAGE 1 -->
  <div class="sheet">
    <div class="watermark">AEO ${version} — ${status}</div>

    <div class="page-content">
      <div class="header-center">
        <div class="header-agency">CITY GOVERNMENT OF CAGAYAN DE ORO</div>
        <div class="header-subtitle">City Internal Audit Service · Audit Governance Information System (AGIS)</div>
      </div>
      <hr class="header-line" />

      <div class="confidentiality-bar">
        ${classificationText}
      </div>

      <div class="aeo-title-block">
        <h1 class="aeo-main-title">AUDIT ENGAGEMENT ORDER</h1>
        <p class="aeo-mandate-sub">Issued pursuant to MDS-200 §5.1, §7, §9.1 (AEM-FR-010)</p>
      </div>

      <table class="meta-table">
        <thead>
          <tr>
            <th style="width: 25%;">AEO NUMBER</th>
            <th style="width: 20%;">VERSION</th>
            <th style="width: 25%;">STATUS</th>
            <th style="width: 30%;">ISSUE DATE</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>${aeoNumber}</td>
            <td>${version}</td>
            <td class="status-issued">${status}</td>
            <td>${issueDateFormatted}</td>
          </tr>
        </tbody>
      </table>

      <div class="memo-block">
        <div class="memo-line">
          <span class="memo-label">TO:</span>
          <span>Department Head / Head of Office, ${office}</span>
        </div>
        <div class="memo-line">
          <span class="memo-label">RE:</span>
          <span>${auditType} Audit — ${auditArea}</span>
        </div>
        <div class="memo-line">
          <span class="memo-label">AUTHORITY:</span>
          <span>${authorityRef} (Directing: ${directingAuth})</span>
        </div>
      </div>

        <div class="section-title">1. STATUTORY AND LEGAL MANDATE</div>
        <p class="section-p">${
          formState.legalMandate ||
          (formState.baseline?.originType === "Unplanned"
            ? "Pursuant to the powers vested in the City Internal Audit Service (CIAS) under Executive Order No. 2024-002, Republic Act No. 7160 (Local Government Code of 1991), and by virtue of the Authorization Decision approving this unplanned engagement independent of the Annual Internal Audit Plan, this Audit Engagement Order is hereby issued."
            : "Pursuant to the powers vested in the City Internal Audit Service (CIAS) under Executive Order No. 2024-002, Republic Act No. 7160 (Local Government Code of 1991), and the Approved Annual Internal Audit Plan, this Audit Engagement Order is hereby issued.")
        }</p>

        <div class="section-title">2. AUDIT OBJECTIVES</div>
        <p class="section-p">${formState.formalObjectives || `To assess statutory compliance and the adequacy of internal controls over cash and revenue collection processes within the ${office}, and to determine whether such processes are conducted in accordance with applicable laws, regulations, and City policy.`}</p>

        <div class="section-title">3. SCOPE SUMMARY</div>
        <p class="section-p">${formState.formalScopeSummary || `This engagement shall cover the ${auditArea} audit area, including designated operations, transactions, and accountable records, for the period ${formState.baseline?.periodStart || "February 10, 2025"} to ${formState.baseline?.periodEnd || "March 21, 2025"}.`}</p>

        <div class="section-title">4. SPECIFIC DIRECTIVES TO AUDITEE OFFICE</div>
        ${directivesHtml}

        <div class="section-title">5. SPECIAL INSTRUCTIONS AND SECURITY CONTROLS</div>
        <p class="section-p">${formState.specialInstructions || "All working papers, data extracts, interview records, and audit observations generated during this engagement are confidential and subject to the Data Privacy Act of 2012 and CIAS Information Security Protocols."}</p>

        <div class="section-title">6. TARGET OUTPUTS AND DELIVERABLES</div>
        ${deliverablesHtml}
      </div>
    </div>

    <div class="sheet-footer">
      <span>${footerText}</span>
      <span>Page 1</span>
    </div>
  </div>

  <!-- PAGE 2 -->
  <div class="sheet">
    <div class="watermark">AEO ${version} — ${status}</div>

    <div class="page-content">
      <div class="signoff-container">
        <div class="signoff-box">
          <div class="signoff-header">REVIEWED BY</div>
          <hr class="signoff-rule" />
          <div class="signoff-name">${formState.reviewedBy || "Atty. Fernando M. Gomez, CPA"}</div>
          <div class="signoff-role">${formState.reviewedRole || "Engagement Manager / Supervising Auditor"}</div>
          <div class="signoff-status">Reviewed & recommended for approval — ${reviewDateShort}</div>
        </div>

        <div class="signoff-box">
          <div class="signoff-header">APPROVED BY</div>
          <hr class="signoff-rule" />
          <div class="signoff-name">${formState.approvedBy || "Director Manuel C. Tan, CPA, CFE"}</div>
          <div class="signoff-role">${formState.approverRole || "Department Head, City Internal Audit Service"}</div>
          <div class="signoff-status">Approved & Issued — ${approvalDateShort}</div>
        </div>
      </div>

      <div style="margin-top: 35px;">
        <div class="dist-title">DISTRIBUTION LIST</div>
        <table class="dist-table">
          <thead>
            <tr>
              <th style="width: 32%;">Recipient</th>
              <th style="width: 30%;">Office</th>
              <th style="width: 20%;">Method</th>
              <th style="width: 18%; text-align: center;">Status</th>
            </tr>
          </thead>
          <tbody>
            ${recipientsTableHtml}
          </tbody>
        </table>
      </div>
    </div>

    <div class="sheet-footer">
      <span>${footerText}</span>
      <span>Page 2</span>
    </div>
  </div>

  <script>
    window.onload = function() {
      setTimeout(function() {
        window.print();
      }, 350);
    };
  </script>
</body>
</html>
  `;

  printWindow.document.open();
  printWindow.document.write(htmlContent);
  printWindow.document.close();
}
