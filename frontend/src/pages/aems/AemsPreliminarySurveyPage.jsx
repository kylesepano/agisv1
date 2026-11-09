import { useEffect, useRef, useState } from "react";
import { ArrowLeft, Building2, ChevronDown, ChevronRight, MessageSquare, MoreHorizontal, ShieldCheck, Workflow, X } from "lucide-react";
import { Link, useSearchParams } from "react-router";
import { useAuth } from "../../auth/auth-context";
import { hasPermission } from "../../config/navigation";
import { aemsEngagementApi, aemsPlanningPackageApi, documentApi } from "../../services/api";
import SearchableSelect from "../../components/ui/SearchableSelect";
import { icsPanels, meetingFields, ppspPanels, principles, profileFields, surveySections, tables } from "../../components/aems/surveyDefinition";
import "./aems-survey.css";

function Field({ field, value, onChange, disabled }) {
  const [key, title, type = "text"] = field;
  return <label className={`survey-field survey-field-${key}`}><span>{title}</span>{Array.isArray(type) ? <SearchableSelect disabled={disabled} options={type.map((item) => ({ value: String(item), label: String(item) }))} value={value == null ? "" : String(value)} placeholder={`Select ${title.toLowerCase()}`} onChange={onChange} /> : type === "textarea" ? <textarea disabled={disabled} value={value ?? ""} onChange={(event) => onChange(event.target.value)} /> : <input type={type === "readonly" ? "text" : type} min={type === "number" ? 0 : undefined} step={type === "number" ? "0.01" : undefined} readOnly={type === "readonly"} disabled={disabled} value={value ?? ""} onChange={(event) => onChange(event.target.value)} />}</label>;
}

function Upload({ title, fileId, attachments, disabled, onUpload, onDownload }) {
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);
  async function upload(file) {
    if (!file || disabled || busy) return;
    if (file.size > 10 * 1024 * 1024 || !/\.(pdf|doc|docx)$/i.test(file.name)) { setError("Choose a PDF, DOC or DOCX file up to 10 MB."); return; }
    setBusy(true); setError("");
    try { await onUpload(file); } catch (reason) { setError(reason.message); } finally { setBusy(false); }
  }
  const file = attachments.find((item) => String(item.id) === String(fileId));
  return <div className="survey-upload"><span>{title}</span><label onDragOver={(event) => event.preventDefault()} onDrop={(event) => { event.preventDefault(); upload(event.dataTransfer.files[0]); }}><span>{busy ? "Uploading…" : file?.fileName ?? "Click to upload or drag and drop PDF, DOC, DOCX (Max 10 MB)"}</span><span className="survey-button">Upload File</span><input aria-label={title} type="file" accept=".pdf,.doc,.docx" disabled={disabled || busy} onChange={(event) => { upload(event.target.files[0]); event.target.value = ""; }} /></label>{file && <button type="button" className="survey-text-link" onClick={() => onDownload(file)}>Download {file.fileName}</button>}{error && <p role="alert" className="survey-error">{error}</p>}</div>;
}

export default function AemsPreliminarySurveyPage() {
  const [params, setParams] = useSearchParams();
  const engagementId = params.get("engagementId");
  const requestedSection = params.get("section");
  const section = surveySections.some(([key]) => key === requestedSection) ? requestedSection : "meeting";
  const panel = params.get("panel") ?? "";
  const { user } = useAuth();
  const [engagement, setEngagement] = useState(null);
  const [workspace, setWorkspace] = useState(null);
  const [survey, setSurvey] = useState({});
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [dirty, setDirty] = useState(false);
  const [editor, setEditor] = useState(null);
  const editorRef = useRef(null);
  const editorKey = editor?.key;

  useEffect(() => {
    if (editorKey) editorRef.current?.scrollIntoView({ behavior: "smooth", block: "center" });
  }, [editorKey]);

  useEffect(() => {
    let active = true;
    if (!engagementId) return;
    Promise.all([aemsEngagementApi.show(engagementId), aemsPlanningPackageApi.survey(engagementId)]).then(([record, data]) => {
      if (!active) return;
      setEngagement(record); setWorkspace(data); setSurvey(data.survey ?? {});
    }).catch((reason) => active && setError(reason.message)).finally(() => active && setLoading(false));
    return () => { active = false; };
  }, [engagementId]);

  useEffect(() => {
    if (!dirty) return;
    const prevent = (event) => { event.preventDefault(); event.returnValue = ""; };
    window.addEventListener("beforeunload", prevent);
    return () => window.removeEventListener("beforeunload", prevent);
  }, [dirty]);

  const current = survey[section] ?? {};
  const editable = workspace?.canEdit && hasPermission(user, workspace?.lockVersion ? "aems.planning-package.update" : "aems.planning-package.create");
  const definition = surveySections.find(([key]) => key === section);
  const Icon = { meeting: MessageSquare, auditee: Building2, ppsp: Workflow, ics: ShieldCheck }[section];
  const planningUrl = `/audit-engagement-management/${engagementId}?tab=planning&activity=0`;

  function setField(key, value) { setSurvey((previous) => ({ ...previous, [section]: { ...previous[section], [key]: value } })); setDirty(true); setNotice(""); }
  function changeView(nextSection, nextPanel = "") {
    if (nextSection !== section && dirty && !window.confirm("Leave this section without saving the draft?")) return;
    if (nextSection !== section) { setSurvey(workspace.survey ?? {}); setDirty(false); }
    setParams({ engagementId, section: nextSection, ...(nextPanel ? { panel: nextPanel } : {}) }); setEditor(null); setNotice("");
  }
  async function save(data = current) {
    setSaving(true); setError(""); setNotice("");
    try {
      const result = await aemsPlanningPackageApi.saveSurvey(engagementId, { section, data, lockVersion: workspace.lockVersion ?? null });
      setWorkspace(result); setSurvey(result.survey ?? {}); setDirty(false); setNotice("Draft saved successfully."); return true;
    } catch (reason) { setError(reason.message); return false; } finally { setSaving(false); }
  }
  async function upload(key, file) {
    const attachment = await aemsPlanningPackageApi.uploadSurveyFile(engagementId, file);
    setWorkspace((previous) => ({ ...previous, attachments: [...(previous.attachments ?? []), attachment] }));
    setField(key, attachment.id);
  }
  async function download(file) {
    try { await documentApi.downloadFile(`/api/aems/engagements/${engagementId}/preliminary-survey/attachments/${file.id}`, file.fileName); } catch (reason) { setError(reason.message); }
  }
  function attachment(title, key) { return <Upload title={title} fileId={current[key]} attachments={workspace?.attachments ?? []} disabled={!editable || saving} onUpload={(file) => upload(key, file)} onDownload={download} />; }
  function fields(items, values = current, update = setField, disabled = !editable || saving) { return items.map((field) => <Field key={field[0]} field={field} value={values[field[0]]} disabled={disabled} onChange={(value) => update(field[0], value)} />); }

  function renderTable(key, rows = current[key] ?? [], firstNumber = 1) {
    const config = tables[key];
    function openEditor(event, index, row, view) {
      event.currentTarget.closest("details").open = false;
      setEditor({ key, index, row: { ...row }, view });
    }
    return <div className="survey-table-section">{config.add && <div className="survey-table-toolbar"><button type="button" className="survey-button" disabled={!editable || saving} onClick={() => setEditor({ key, index: null, row: {}, view: false })}>+ {config.add}</button></div>}<div className="survey-table-scroll"><table><thead><tr>{key !== "programs" && <th>No.</th>}{config.fields.map(([name, title]) => <th key={name}>{title}</th>)}<th><span className="sr-only">Actions</span></th></tr></thead><tbody>{Array.from({ length: Math.max(rows.length, key === "assessments" ? rows.length : 4) }, (_, index) => {
      const row = rows[index];
      return <tr key={row?.principleId ?? index}>{key !== "programs" && <td>{firstNumber + index}</td>}{config.fields.map(([name]) => <td key={name}>{row?.[name] ?? ""}</td>)}<td>{row && <details className="survey-row-menu"><summary aria-label={`Actions for row ${index + firstNumber}`}><MoreHorizontal size={22} /></summary><div><button type="button" onClick={(event) => openEditor(event, index, row, true)}>View</button><button type="button" disabled={!editable || saving} onClick={(event) => openEditor(event, index, row, false)}>Edit</button>{key !== "assessments" && <button type="button" disabled={!editable || saving} onClick={() => { if (window.confirm("Remove this item from the draft?")) { const next = rows.filter((_, at) => at !== index); setField(key, next); } }}>Remove</button>}</div></details>}</td></tr>;
    })}</tbody></table></div></div>;
  }
  function assessmentRows(start, end) {
    return principles.slice(start - 1, end).map(([principle, question], index) => ({ ...(current.assessments ?? []).find((item) => item.principleId === start + index), principleId: start + index, principle, question }));
  }
  async function updateRow() {
    let row = { ...editor.row };
    if (editor.key === "risks") row.riskRating = row.likelihood && row.impact ? Number(row.likelihood) * Number(row.impact) : "";
    let rows = [...(current[editor.key] ?? [])];
    if (editor.key === "assessments") {
      delete row.principle; delete row.question;
      rows = rows.filter((item) => item.principleId !== row.principleId); rows.push(row);
    } else if (editor.index == null) rows.push(row); else rows[editor.index] = row;
    const firstEditable = tables[editor.key].fields.find(([, , type]) => type !== "readonly");
    if (!row[firstEditable[0]]?.toString().trim()) { setError(`Please enter ${firstEditable[1].toLowerCase()}.`); return; }
    if (await save({ ...current, [editor.key]: rows })) setEditor(null);
  }

  if (!engagementId) return <main className="survey-page"><p>Select an audit engagement to open Preliminary Survey.</p><Link to="/audit-engagement-management">Back to AEM Workspace</Link></main>;
  if (loading) return <main className="survey-page" aria-busy="true"><div className="survey-loading">Loading Preliminary Survey…</div></main>;
  if (!engagement || !workspace) return <main className="survey-page"><p role="alert">{error || "Unable to load this engagement."}</p><Link to="/audit-engagement-management">Back to AEM Workspace</Link></main>;

  const completionValues = section === "meeting" ? [...meetingFields.map(([key]) => key === "ciasParticipants" && Array.isArray(current.ciasParticipantIds) ? (current.ciasParticipantIds.length ? true : null) : current[key]), current.noticeDocumentVersionId] : section === "auditee" ? [current.mandate, current.mission, current.vision, current.objectives, current.strategicGoals, current.annualBudget, current.programs?.length] : (current.assessments ?? []).filter((item) => item.preliminaryView && item.basisSource);
  const completed = completionValues.filter((value) => value != null && String(value).trim() !== "").length;
  return <main className="survey-page">
    <nav aria-label="Breadcrumb" className="survey-breadcrumb"><Link to="/dashboard">Home</Link><span>›</span><Link to="/audit-engagement-management">Audit Engagements</Link><span>›</span><Link to={`/audit-engagement-management/${engagementId}`}>Audit Engagement Details</Link><span>›</span><Link to={planningUrl}>Planning</Link><span>›</span><Link to={planningUrl}>Preliminary Survey</Link><span>›</span><span aria-current="page">{definition[2]}</span></nav>
    <section className="survey-window">
      <header className="survey-window-bar"><span>{engagement.engagementCode} - Planning - Preliminary Survey - <strong>{definition[2]}</strong></span><Link to={planningUrl} aria-label="Close Preliminary Survey"><X size={20} /></Link></header>
      <div className={`survey-window-body ${editor ? `has-editor editor-${editor.key}` : ""}`}>
        <div className="survey-heading"><Icon size={75} strokeWidth={1.3} /><div><h1>{definition[1]} {definition[2]}{section === "ics" && " (Internal Control System)"}</h1>{section === "ics" && <p>Assess the auditee’s internal control system using the 5 ICSPPS components and 16 principles. This is a preliminary understanding, not a formal effectiveness assessment.</p>}</div>{section !== "ppsp" && <span>{completed} of {section === "ics" ? 16 : completionValues.length} items completed</span>}</div>
        {!editable && <p className="survey-notice">This planning package is read-only at its current stage or with your current permissions.</p>}
        {error && <p role="alert" className="survey-error">{error}</p>}{notice && <p role="status" className="survey-notice">{notice}</p>}
        {section === "meeting" && <div className="survey-meeting-form">
          <div className="survey-meeting-top">{fields(meetingFields.filter(([key]) => ["proposedAt", "venue"].includes(key)))}</div>
          <div className="survey-meeting-columns">
            <div className="survey-meeting-column">{fields(meetingFields.filter(([key]) => ["focalPerson", "auditeeParticipants", "documentsNeeded", "actualDate", "mattersDiscussed"].includes(key)))}</div>
            <div className="survey-meeting-column">
              <div className="survey-field"><span id="cias-participants-label">CIAS Participants</span><div role="group" aria-labelledby="cias-participants-label"><SearchableSelect multiple disabled={!editable || saving} options={(workspace.participantOptions ?? []).map((person) => ({ value: person.id, label: person.name, description: person.position || person.employeeId, keywords: `${person.employeeId ?? ""} ${person.position ?? ""}` }))} value={current.ciasParticipantIds ?? []} onChange={(value) => setField("ciasParticipantIds", value)} placeholder="Search and select CIAS participants" searchPlaceholder="Search CIAS participants..." emptyMessage="No active CIAS participants are available." /></div>{!Array.isArray(current.ciasParticipantIds) && current.ciasParticipants && <small className="text-slate-600">Previously recorded: {current.ciasParticipants}</small>}</div>
              {fields(meetingFields.filter(([key]) => key === "mattersToDiscuss"))}
              <div className="survey-meeting-notice">{attachment("Notice of Preliminary Meeting", "noticeDocumentVersionId")}<button className="survey-text-link" type="button" onClick={() => download({ id: "template", fileName: "Notice_of_Preliminary_Meeting_Template.docx" })}>Download Template: Notice of Preliminary Meeting</button></div>
            </div>
          </div>
        </div>}
        {section === "auditee" && <div className="survey-auditee"><div className="survey-form-grid">{fields([["mandate", "Mandate"], ["mission", "Mission", "textarea"], ["vision", "Vision", "textarea"], ["objectives", "Objectives", "textarea"], ["strategicGoals", "Strategic Goals, if any", "textarea"]])}</div><div className="survey-budget-grid">{fields([["annualBudget", "Total Office Annual Budget", "number"], ["personnelBudget", "Personnel Services Budget", "number"], ["mooeBudget", "MOOE Budget", "number"], ["capitalBudget", "Capital Outlay Budget", "number"], ["sppBudget", "SPP Budget", "number"]])}</div>{attachment("Attach Office Annual Budget", "budgetDocumentVersionId")}<h2 className="survey-subheading">Program / Project Review</h2>{renderTable("programs")}</div>}
        {section === "ppsp" && <div className="survey-accordions">{ppspPanels.map(([key, number, title]) => <section key={key}><button aria-expanded={panel === key} type="button" className="survey-accordion" onClick={() => changeView(section, panel === key ? "" : key)}><strong>{number}</strong><span>{title}</span>{panel === key ? <ChevronDown size={20} /> : <ChevronRight size={20} />}</button>{panel === key && <div className="survey-accordion-content">{key === "profile" ? <div className="survey-form-grid">{fields(profileFields)}</div> : key === "flowchart" ? <div className="survey-flow-upload">{attachment("Process Flowchart", "flowchartDocumentVersionId")}</div> : renderTable(key)}</div>}</section>)}</div>}
        {section === "ics" && <div className="survey-accordions">{icsPanels.map(([key, number, title, start, end]) => <section key={key}><button aria-expanded={panel === key} type="button" className="survey-accordion" onClick={() => changeView(section, panel === key ? "" : key)}><strong>{number}</strong><span>{title}</span>{panel === key ? <ChevronDown size={20} /> : <ChevronRight size={20} />}</button>{panel === key && renderTable("assessments", assessmentRows(start, end), start)}</section>)}</div>}
        {editor && <section ref={editorRef} className="survey-editor" aria-label={`${editor.view ? "View" : "Add/Edit"} ${tables[editor.key].title}`}><header className="survey-window-bar"><span>{editor.view ? "View" : "Add/Edit"} {tables[editor.key].title}</span><button type="button" aria-label="Close item editor" onClick={() => setEditor(null)}><X size={20} /></button></header>{error && <p role="alert" className="survey-error">{error}</p>}<div className={`survey-editor-fields ${editor.key === "risks" ? "survey-form-grid" : ""}`}>{fields(tables[editor.key].fields, { ...editor.row, ...(editor.key === "risks" ? { riskRating: editor.row.likelihood && editor.row.impact ? Number(editor.row.likelihood) * Number(editor.row.impact) : "" } : {}) }, (key, value) => setEditor((previous) => ({ ...previous, row: { ...previous.row, [key]: value } })), editor.view || !editable || saving)}</div><footer><button type="button" className="survey-button" onClick={() => setEditor(null)}><ArrowLeft size={18} /> Back to {definition[1]} workspace</button>{!editor.view && <button type="button" className="survey-button" disabled={saving} onClick={updateRow}>{saving ? "Saving…" : "Update to Table"}</button>}</footer></section>}
        <footer className="survey-footer"><Link className="survey-button" to={planningUrl} onClick={(event) => { if (dirty && !window.confirm("Leave without saving your draft?")) event.preventDefault(); }}><ArrowLeft size={18} /> Back to Planning Workspace</Link><button type="button" className="survey-button" disabled={!editable || saving || !!editor} onClick={() => save()}>{saving ? "Saving…" : "Save Draft"}</button><details className="survey-more"><summary className="survey-button survey-primary">More Actions <ChevronDown size={16} /></summary><div>{surveySections.map(([key, number, title]) => <button type="button" key={key} onClick={(event) => { changeView(key); event.currentTarget.closest("details").open = false; }}>{number} {title}</button>)}<Link to={`/audit-engagement-management/planning-package?engagementId=${engagementId}&section=readiness`}>Planning Review & Approval</Link></div></details></footer>
      </div>
    </section>
  </main>;
}
