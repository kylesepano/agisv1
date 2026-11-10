import type {
  ConferenceFormState,
  ConferenceParticipant,
  FollowUpActionItem,
  InfoRequestItem,
} from "./conference-types";

export const defaultParticipants: ConferenceParticipant[] = [
  // Audit Team (from SCR-213)
  {
    id: "PART-01",
    name: "Juan Dela Cruz",
    office: "City Internal Audit Service (CIAS)",
    position: "Lead Auditor",
    role: "Lead Auditor / Presiding Speaker",
    attendanceStatus: "Attended",
    mode: "In-person",
  },
  {
    id: "PART-02",
    name: "Kristine Joy B. Ramos",
    office: "City Internal Audit Service (CIAS)",
    position: "Internal Auditor II / IS Specialist",
    role: "Auditor / Recorder",
    attendanceStatus: "Attended",
    mode: "In-person",
  },
  {
    id: "PART-03",
    name: "Engr. Daniel L. Torres",
    office: "City Internal Audit Service (CIAS)",
    position: "Technical Specialist",
    role: "Technical Specialist",
    attendanceStatus: "Attended",
    mode: "In-person",
  },
  {
    id: "PART-04",
    name: "Atty. Fernando M. Gomez, CPA",
    office: "City Internal Audit Service (CIAS)",
    position: "Supervising Auditor",
    role: "Supervising Auditor",
    attendanceStatus: "Attended",
    mode: "In-person",
  },
  // Auditee Team (from SCR-214 Recipients / CGSO)
  {
    id: "PART-05",
    name: "Mr. Rolando V. Santos",
    office: "City General Services Office (CGSO)",
    position: "Department Head / General Services Officer",
    role: "Auditee Executive Lead",
    attendanceStatus: "Attended",
    mode: "In-person",
  },
  {
    id: "PART-06",
    name: "Ms. Maricor E. Villanueva",
    office: "Bids and Awards Committee (BAC) Secretariat",
    position: "Head, BAC Secretariat",
    role: "Auditee Focal Person (Procurement)",
    attendanceStatus: "Attended",
    mode: "In-person",
  },
  {
    id: "PART-07",
    name: "Mr. Eduardo S. Pineda",
    office: "CGSO Central Warehouse Division",
    position: "Supply & Property Custodian",
    role: "Auditee Focal Person (Inventory/Custody)",
    attendanceStatus: "Attended",
    mode: "In-person",
  },
  {
    id: "PART-08",
    name: "Ms. Teresa C. Mendoza",
    office: "City Accounting Office",
    position: "Disbursement Processing Officer",
    role: "Inter-Office Liaison (Vouchers)",
    attendanceStatus: "Confirmed",
    mode: "Virtual",
  },
];

export const defaultInfoRequests: InfoRequestItem[] = [
  {
    id: "REQ-01",
    documentTitle: "FY 2025 Approved Annual Procurement Plan (APP-CSE & Non-CSE) + Supplements",
    targetOffice: "BAC Secretariat",
    focalPerson: "Ms. Maricor E. Villanueva",
    deadline: "2026-02-23",
    status: "Pending",
  },
  {
    id: "REQ-02",
    documentTitle: "Complete Bidding Folders for 5 High-Value Goods Contracts (> PHP 1.0M)",
    targetOffice: "BAC Secretariat",
    focalPerson: "Ms. Maricor E. Villanueva",
    deadline: "2026-02-25",
    status: "Pending",
  },
  {
    id: "REQ-03",
    documentTitle: "CGSO Central Warehouse Consumable Stock Cards & Q4 2025 Physical Count Sheets",
    targetOffice: "CGSO Warehouse Division",
    focalPerson: "Mr. Eduardo S. Pineda",
    deadline: "2026-02-24",
    status: "Pending",
  },
  {
    id: "REQ-04",
    documentTitle: "Summary of Alternative Methods of Procurement (SVP/Shopping) PR/PO logbooks",
    targetOffice: "CGSO Procurement Unit",
    focalPerson: "Mr. Rolando V. Santos",
    deadline: "2026-02-26",
    status: "Pending",
  },
];

export const defaultFollowUpActions: FollowUpActionItem[] = [
  {
    id: "ACT-01",
    actionItem: "Designate official auditee liaison officer and secure shared drive access credentials.",
    responsiblePerson: "Mr. Rolando V. Santos (CGSO)",
    targetDate: "2026-02-20",
  },
  {
    id: "ACT-02",
    actionItem: "Set up workspace for audit team in CGSO 3rd Floor Conference Room for sample inspection.",
    responsiblePerson: "Mr. Eduardo S. Pineda (CGSO)",
    targetDate: "2026-02-21",
  },
  {
    id: "ACT-03",
    actionItem: "Transmit formal confirmation letter for scheduled warehouse inventory physical sampling count.",
    responsiblePerson: "Juan Dela Cruz (Lead Auditor)",
    targetDate: "2026-02-24",
  },
];

export const defaultConferenceState: ConferenceFormState = {
  conferenceId: "CONF-2026-010-ENTRY",
  engagementNumber: "AEMS-2026-010",
  engagementTitle: "Procurement Process & Supply Chain Compliance Audit",
  office: "City General Services Office",
  type: "Entry Conference",
  status: "Conducted",

  scheduledDate: "2026-02-18",
  scheduledTime: "09:30 AM",
  venueOrLink: "CGSO Conference Room, 3rd Floor City Hall (Hybrid MS Teams Link provided)",
  mode: "Hybrid",

  briefingPaper: {
    aeoRef: "AEO-2026-001-CGSO (Issued Feb 12, 2026)",
    authorityLegalBasis:
      "Executive Order No. 2024-002, RA 7160 Local Government Code, and Approved Internal Audit Plan FY 2026 (IAP-2026-001).",
    assignedTeamSummary:
      "Lead Auditor: Juan Dela Cruz; Auditors: Kristine Joy B. Ramos, Engr. Daniel L. Torres; Supervising Auditor: Atty. Fernando M. Gomez, CPA.",
    objectivesScopeSummary:
      "Assess statutory compliance with RA 9184 and local procurement controls across all public bidding, AMP, and warehouse inventory transactions from Jan 1 – Dec 31, 2025.",
    fieldworkTimeline:
      "Fieldwork Window: February 23, 2026 – March 27, 2026; Reporting Target: April 30, 2026.",
  },

  agendaText:
    "1. Opening Remarks and Introduction of Audit Team & Executive Mandate (AEO-2026-001-CGSO)\n2. Presentation of Audit Objectives, Scope Parameters, and Criteria Framework\n3. Walkthrough of Audit Methodology, Working Paper Requirements & Fieldwork Schedule\n4. Designation of Auditee Focal Persons and Communication Protocol\n5. Review of Initial Document Requests & Open Floor for Questions/Clarifications",

  minutesAndNotes:
    "The Entry Conference for engagement AEMS-2026-010 was convened at 09:30 AM on February 18, 2026. Lead Auditor Juan Dela Cruz presented the Audit Engagement Order (AEO-2026-001-CGSO) and outlined the audit scope covering FY 2025 procurement transactions and CGSO central warehouse inventory custody. Department Head Rolando V. Santos welcomed the audit team and expressed full cooperation. Ms. Maricor Villanueva was designated as the primary BAC focal person, and Mr. Eduardo Pineda as warehouse custody liaison. The protocol for document requests (48-hour turnaround) and weekly Monday status briefings was mutually affirmed. The conference was adjourned at 11:15 AM.",

  keyAgreements:
    "1. Document Request Protocol: Auditee focal persons agree to furnish requested vouchers and bidding files within 48 to 72 hours of written request.\n2. Workstation Allocation: CGSO will provide a dedicated desk and network port for the on-site audit team at the 3rd floor.\n3. Weekly Touchpoints: Brief 15-minute coordination meetings will occur every Monday at 9:00 AM.\n4. Confidentiality: All working papers and draft findings remain strictly confidential pursuant to CIAS internal audit policies.",

  participants: defaultParticipants,
  informationRequests: defaultInfoRequests,
  followUpActions: defaultFollowUpActions,

  waiver: {
    isWaiverActive: false,
    waiverReason: "",
    alternativeProcedure: "",
    approvingAuthority: "Director Manuel C. Tan, CPA, CFE",
    approvalDate: "2026-02-18",
    isApproved: false,
  },
};

export function buildInitialConferenceState(
  engagement: {
    code: string;
    auditArea?: string;
    subject?: string;
    office?: string;
    year?: string;
    startDate?: string;
    endDate?: string;
  },
  confType: "entry" | "exit" = "entry",
): ConferenceFormState {
  const codeNum = engagement.code.split("-")[2] || "000";
  const year = engagement.year || "2026";
  const engCode = engagement.code;
  const office = engagement.office || "Auditee Operations Office";
  const area =
    engagement.auditArea ||
    engagement.subject ||
    "Internal Control & Compliance Audit";
  const typeLabel =
    confType === "exit" ? "Exit Conference" : "Entry Conference";
  const typeSuffix = confType === "exit" ? "EXIT" : "ENTRY";

  const participants: ConferenceParticipant[] = [
    {
      id: "PART-01",
      name: "Juan Dela Cruz",
      office: "City Internal Audit Service (CIAS)",
      position: "Lead Auditor",
      role: "Lead Auditor / Presiding Speaker",
      attendanceStatus: "Attended",
      mode: "In-person",
    },
    {
      id: "PART-02",
      name: "Kristine Joy B. Ramos",
      office: "City Internal Audit Service (CIAS)",
      position: "Internal Auditor II / IS Specialist",
      role: "Auditor / Recorder",
      attendanceStatus: "Attended",
      mode: "In-person",
    },
    {
      id: "PART-03",
      name: "Engr. Daniel L. Torres",
      office: "City Internal Audit Service (CIAS)",
      position: "Technical Specialist",
      role: "Technical Specialist",
      attendanceStatus: "Attended",
      mode: "In-person",
    },
    {
      id: "PART-04",
      name: "Atty. Fernando M. Gomez, CPA",
      office: "City Internal Audit Service (CIAS)",
      position: "Supervising Auditor",
      role: "Supervising Auditor",
      attendanceStatus: "Attended",
      mode: "In-person",
    },
    {
      id: "PART-05",
      name: `Head of Office / Officer-in-Charge`,
      office: office,
      position: "Department Head / OIC",
      role: "Auditee Executive Lead",
      attendanceStatus: "Attended",
      mode: "In-person",
    },
    {
      id: "PART-06",
      name: `Designated Operations Focal Person`,
      office: office,
      position: "Operations Supervisor / Unit Head",
      role: "Auditee Focal Person",
      attendanceStatus: "Attended",
      mode: "In-person",
    },
  ];

  const confDate = engagement.startDate || `${year}-08-25`;
  const fwStart = engagement.startDate || `${year}-08-18`;
  const fwEnd = engagement.endDate || `${year}-10-30`;

  const infoRequests: InfoRequestItem[] = [
    {
      id: "REQ-01",
      documentTitle: `FY ${year} Operational Masterlists, Standard Operating Procedures & Program Guidelines`,
      targetOffice: office,
      focalPerson: "Designated Operations Focal Person",
      deadline: confDate,
      status: "Pending",
    },
    {
      id: "REQ-02",
      documentTitle: `Summary of Sample Disbursement Vouchers, Transmittals, and Official Authorization Logs`,
      targetOffice: office,
      focalPerson: "Designated Operations Focal Person",
      deadline: confDate,
      status: "Pending",
    },
  ];

  const followUpActions: FollowUpActionItem[] = [
    {
      id: "ACT-01",
      actionItem: `Designate official auditee liaison officer and secure workspace credentials for the audit team at ${office}.`,
      responsiblePerson: `Department Head / Focal Person (${office})`,
      targetDate: confDate,
    },
    {
      id: "ACT-02",
      actionItem: `Transmit initial batch of requested operational records and disbursement transmittal files.`,
      responsiblePerson: `Designated Operations Focal Person (${office})`,
      targetDate: confDate,
    },
  ];

  return {
    conferenceId: `CONF-${year}-${codeNum}-${typeSuffix}`,
    engagementNumber: engCode,
    engagementTitle: area,
    office: office,
    type: typeLabel,
    status: "Scheduled",

    scheduledDate: confDate,
    scheduledTime: "09:30 AM",
    venueOrLink: `${office} Conference Room, City Government Compound (Hybrid Link provided)`,
    mode: "Hybrid",

    briefingPaper: {
      aeoRef: `AEO-${year}-${codeNum} (Issued for ${office})`,
      authorityLegalBasis:
        "Executive Order No. 2024-002, RA 7160 Local Government Code, and Approved Internal Audit Plan.",
      assignedTeamSummary:
        "Lead Auditor: Juan Dela Cruz; Auditors: Kristine Joy B. Ramos, Engr. Daniel L. Torres; Supervising Auditor: Atty. Fernando M. Gomez, CPA.",
      objectivesScopeSummary: `Evaluate operational controls, regulatory compliance, and transaction integrity for ${area} under ${office}.`,
      fieldworkTimeline: `Fieldwork Window: ${fwStart} – ${fwEnd}; Reporting Target: ${fwEnd}.`,
    },

    agendaText: `1. Opening Remarks and Introduction of CIAS Audit Team & Executive Mandate\n2. Presentation of Audit Objectives, Scope Parameters, and Criteria Framework for ${area}\n3. Walkthrough of Audit Methodology, Fieldwork Schedules, and Working Paper Requirements\n4. Designation of Auditee Focal Persons and Communication Protocols\n5. Review of Initial Information Requests and Open Floor Discussion`,

    minutesAndNotes: `The ${typeLabel} for engagement ${engCode} was convened on-site at ${office}. Lead Auditor Juan Dela Cruz presented the Audit Engagement Order and outlined the audit scope covering ${area}. The ${office} leadership welcomed the audit team and designated the operations focal person. Communication protocols and document submission timelines were established.`,

    keyAgreements: `1. Document Request Protocol: ${office} focal persons agree to furnish requested vouchers and operational files within 48 to 72 hours.\n2. Workstation Allocation: ${office} will provide desk space for on-site audit staff.\n3. Weekly Touchpoints: Regular coordination briefings will occur every Monday morning.\n4. Confidentiality: All working papers and draft findings remain strictly confidential pursuant to CIAS policies.`,

    participants: participants,
    informationRequests: infoRequests,
    followUpActions: followUpActions,

    waiver: {
      isWaiverActive: false,
      waiverReason: "",
      alternativeProcedure: "",
      approvingAuthority: "Director Manuel C. Tan, CPA, CFE",
      approvalDate: confDate,
      isApproved: false,
    },
  };
}
