export const planningActivities = [
  { label: "Preliminary Survey", path: "preliminary-survey", description: "Obtain sufficient preliminary understanding of the activity and identify relevant risks, controls, information gaps, and matters requiring further engagement planning." },
  { label: "Determination of Audit Objectives, Scope, Criteria & Evidence", path: "aep" },
  { label: "Determination of Audit Methodology", path: "planning-package", section: "overview" },
  { label: "Determination of Required Resources and Target Milestones", path: "planning-package", section: "overview" },
  { label: "Development of the Audit Engagement Plan", path: "aep" },
  { label: "Determination of Audit Engagement KPIs", path: "planning-package", section: "kpis" },
  { label: "Review and Approval of AEP and KPIs", path: "planning-package", section: "readiness" },
  { label: "Preparation for Entrance Conference", path: "entry-conferences" },
];

export const surveySections = [
  ["meeting", "1.1", "Preliminary Meeting"],
  ["auditee", "1.2", "Understanding of the Auditee"],
  ["ppsp", "1.3", "Understanding of the Program, Project, System or Process under Audit"],
  ["ics", "1.4", "Initial Assessment of the ICS"],
];

export const ppspPanels = [
  ["profile", "1.3.A", "Activity Profile and Purpose"],
  ["steps", "1.3.B", "High-Level Operating Steps"],
  ["sources", "1.3.C", "Sources Consulted"],
  ["questions", "1.3.D", "Unresolved Questions and Document Requests"],
  ["flowchart", "1.3.E", "Process Flowchart"],
  ["risks", "1.3.F", "Risk and Control Matrix"],
];

export const icsPanels = [
  ["environment", "1.4.A", "Control Environment (Principles 1-5)", 1, 5],
  ["risk", "1.4.B", "Risk Assessment (Principles 6-8)", 6, 8],
  ["activities", "1.4.C", "Control Activities (Principles 9-11)", 9, 11],
  ["information", "1.4.D", "Information and Communication (Principles 12-14)", 12, 14],
  ["monitoring", "1.4.E", "Monitoring (Principles 15-16)", 15, 16],
];

export const principles = [
  ["Integrity and ethical values", "Are integrity and ethical values established and demonstrated?"],
  ["Tone at the top", "Does leadership visibly support controls and expect others to follow?"],
  ["Organizational structure", "Is the structure appropriate to achieve objectives?"],
  ["Commitment to competence", "Are personnel competent for their responsibilities?"],
  ["Human resource policies and practices", "Are HR policies and practices aligned with objectives?"],
  ["Clear Objectives and Risk Tolerance", "Are objectives and risk tolerance clearly defined?"],
  ["Risk Identification and Assessment", "Are risks to achieving objectives identified and assessed?"],
  ["Risk Response", "Are appropriate responses to identified risks established?"],
  ["Appropriate Control Activities", "Are control activities appropriate to address identified risks?"],
  ["Policies and Procedures", "Are policies and procedures documented and implemented?"],
  ["Information Technology Controls", "Are relevant information technology controls in place?"],
  ["Reliable and Relevant Information", "Is reliable and relevant information available for decision-making?"],
  ["Internal Communication", "Are internal control responsibilities communicated within the office?"],
  ["External Communication", "Are relevant matters communicated to external parties?"],
  ["Monitoring of Internal Controls", "Are internal controls monitored on an ongoing basis?"],
  ["Action on Audit and Review Findings", "Are audit and review findings addressed promptly?"],
];

export const tables = {
  programs: { title: "Program or Project", add: "Add Program or Project", fields: [["title", "Title"], ["objectives", "Objectives"], ["beneficiaries", "Beneficiaries"], ["fundingSource", "Funding Source"], ["budget", "Budget", "number"], ["implementationPeriod", "Implementation Period"], ["collaborators", "Collaborators"], ["status", "Status", ["Not Started", "Ongoing", "Completed", "Suspended"]]] },
  steps: { title: "Activity / Decision", add: "Add Activity / Decision", fields: [["activity", "Activity / Decision"], ["responsible", "Responsible"], ["inputOutput", "Input - Output"], ["controlRecord", "Reported Control / Record"], ["source", "Source"]] },
  sources: { title: "Sources Consulted", add: "Add Item to List", fields: [["documentPersonMethod", "Document / Person / Method"], ["dateCustodian", "Date / Custodian"], ["supportsLimitation", "What it supports / Limitation"]] },
  questions: { title: "Unresolved Questions and Document Requests", add: "Add Item to List", fields: [["informationGap", "Information gap / contradiction"], ["verificationNeeded", "Verification / document needed"], ["ownerDue", "Owner / due"], ["taskLink", "DRL / task link"]] },
  risks: { title: "Risk and Control Matrix", add: "Add Item to List", fields: [["riskDescription", "Risk Description"], ["riskCategory", "Risk Category"], ["likelihood", "Likelihood", [1, 2, 3, 4, 5]], ["likelihoodJustification", "Justification (Likelihood)"], ["impact", "Impact", [1, 2, 3, 4, 5]], ["impactJustification", "Justification (Impact)"], ["riskRating", "Risk Rating", "readonly"], ["controlActivity", "Control Activity in Place"], ["controlType", "Control Type", ["Preventive", "Detective", "Corrective", "Directive"]], ["controlOwner", "Control Owner"], ["controlFrequency", "Control Frequency"], ["operatingEffectiveness", "Operating Effectiveness Status"], ["residualRiskRating", "Residual Risk Rating"]] },
  assessments: { title: "Initial Assessment of the ICS", fields: [["principle", "Principle", "readonly"], ["question", "Auditor Checklist Question", "readonly"], ["preliminaryView", "Preliminary View", ["No Apparent Concern", "Potential Concern", "More Information Needed", "Not Applicable"]], ["basisSource", "Basis / Source"], ["keyMatters", "Key Matter(s)"], ["preliminaryAssessment", "Preliminary Assessment"], ["nextAction", "Next Action"]] },
};

export const meetingFields = [["proposedAt", "Proposed Date & Time", "datetime-local"], ["venue", "Venue / Online Link"], ["focalPerson", "Auditee Contact / Focal Person"], ["ciasParticipants", "CIAS Participants"], ["auditeeParticipants", "Auditee Participants, if known"], ["mattersToDiscuss", "Key Matters to Discuss", "textarea"], ["documentsNeeded", "Initial Information / Documents Needed", "textarea"], ["actualDate", "Actual Meeting Date", "date"], ["mattersDiscussed", "Key Matters Discussed", "textarea"]];
export const profileFields = [["mandate", "Mandate / Basis"], ["responsibleUnit", "Responsible Unit"], ["purpose", "Purpose / Need"], ["targetUsers", "Target Users"], ["scope", "Scope / Coverage"], ["resources", "Resources"], ["expectedResults", "Expected Results"], ["measures", "Measures / Targets"]];
