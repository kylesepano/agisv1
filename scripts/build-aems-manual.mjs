import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { chromium } from 'playwright';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const dir = path.join(root, 'docs', 'testing');
const fixtureDir = path.join(dir, 'fixtures');
await fs.mkdir(fixtureDir, {recursive:true});
const register = ['test_only,permit_id,transaction_number,transaction_date,assessment_php,receipt_reference,receipt_php,reconciliation_reference,secondary_reviewer'];
const sample = ['test_only,permit_id,expected_fee_php,assessment_php,receipt_php,assessment_pass,receipt_match_pass,reviewer_present'];
for(let i=1;i<=60;i++) {
  const id=String(i).padStart(3,'0');
  const fee=1000+100*i;
  const missing=[5,25,45].includes(i);
  register.push(`SYNTHETIC,DEMO-PERMIT-${id},${i},2026-08-${String(1+(i-1)%28).padStart(2,'0')},${fee},DEMO-OR-${id},${fee},DEMO-REC-${id},${missing?'':'Synthetic Reviewer A'}`);
  if(i%5===0) sample.push(`SYNTHETIC,DEMO-PERMIT-${id},${fee},${fee},${fee},YES,YES,${missing?'NO':'YES'}`);
}
await fs.writeFile(path.join(fixtureDir,'BPLD-UAT-permit-register.csv'),register.join('\r\n')+'\r\n');
await fs.writeFile(path.join(fixtureDir,'BPLD-UAT-sample-results.csv'),sample.join('\r\n')+'\r\n');
const escape = value => value.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;');
const inline = value => escape(value).replace(/`([^`]+)`/g, '<code>$1</code>').replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
const source = await fs.readFile(path.join(dir, 'AEMS_BPLD_MANUAL_TESTING_GUIDE.md'), 'utf8');
const lines = source.split(/\r?\n/);
const html = [];
const toc = [];
let table = false;
let list = false;
let headingIndex = 0;
for (const line of lines) {
  if (!line.startsWith('|') && table) { html.push('</tbody></table>'); table = false; }
  if (!/^\d+\. |^- /.test(line) && list) { html.push('</ul>'); list = false; }
  if (!line.trim()) continue;
  if (/^\|[\s:|-]+\|$/.test(line)) continue;
  if (line.startsWith('|')) {
    const cells = line.slice(1, -1).split('|').map(x => x.trim());
    if (!table) { html.push(`<table><thead><tr>${cells.map(x=>`<th>${inline(x)}</th>`).join('')}</tr></thead><tbody>`); table = true; }
    else html.push(`<tr>${cells.map(x=>`<td>${inline(x)}</td>`).join('')}</tr>`);
  } else if (/^#{1,3} /.test(line)) {
    const level = line.indexOf(' ');
    const title = line.slice(level + 1);
    const id = `section-${headingIndex++}`;
    if (level === 2) toc.push({id, title});
    html.push(`<h${level} id="${id}">${inline(title)}</h${level}>`);
  } else if (/^\d+\. |^- /.test(line)) {
    if (!list) {html.push('<ul>');list=true;}
    html.push(`<li>${inline(line.replace(/^- /,''))}</li>`);
  } else html.push(`<p>${inline(line)}</p>`);
}
if(table) html.push('</tbody></table>');
if(list) html.push('</ul>');
const document = `<!doctype html><html lang="en"><meta charset="utf-8"><title>AEMS BPLD Manual Testing Guide</title><style>
@page{size:A4;margin:16mm 15mm 19mm}*{box-sizing:border-box}body{font:9.5pt/1.35 'Segoe UI',Arial,sans-serif;color:#172b43;margin:0}h1{font-size:30pt;color:#075785;margin:20mm 0 8mm;line-height:1.12}h2{break-before:page;font-size:18pt;color:#075785;border-bottom:2px solid #08a2a6;padding-bottom:3mm;margin-top:0}h3{font-size:12pt;color:#08747b;margin:5mm 0 2mm}h1,h2,h3{break-after:avoid}p{margin:2mm 0 3mm;break-inside:avoid}table{border-collapse:collapse;width:100%;margin:3mm 0 4mm;font-size:8.8pt;table-layout:fixed}th,td{border:1px solid #cbd9e4;padding:1.5mm 2.5mm;vertical-align:top;overflow-wrap:anywhere}th{background:#e8f3f8;text-align:left;color:#075785}th:first-child,td:first-child{width:30%}tr{break-inside:avoid}thead{display:table-header-group}code{font:8.4pt Consolas,monospace;color:#064c72}li{margin:1.4mm 0}ul{padding-left:5mm}a{color:#075785;text-decoration:none}.toc{break-before:page}.toc ul{columns:2;column-gap:9mm;list-style:none;padding:0}.toc li{break-inside:avoid;margin-bottom:3mm}.cover-note{background:#e8f3f8;padding:6mm;border-left:4px solid #08a2a6}
</style><body>${html.join('\n').replace(/(<h2)/, `<nav class="toc"><h2>Contents</h2><ul>${toc.map(t=>`<li><a href="#${t.id}">${inline(t.title)}</a></li>`).join('')}</ul></nav>$1`)}</body></html>`;
const htmlPath=path.join(dir,'AEMS_BPLD_MANUAL_TESTING_GUIDE.html');
await fs.writeFile(htmlPath,document);
const browser = await chromium.launch({channel:'msedge'});
try {
  const page=await browser.newPage();
  await page.goto(pathToFileURL(htmlPath).href);
  await page.pdf({path:path.join(dir,'AEMS_BPLD_MANUAL_TESTING_GUIDE.pdf'),format:'A4',printBackground:true,displayHeaderFooter:true,headerTemplate:'<div></div>',footerTemplate:'<div style="font-size:8px;width:100%;padding:0 15mm;color:#54687b;display:flex;justify-content:space-between"><span>AGIS · BPLD AEMS manual testing · Synthetic test data</span><span><span class="pageNumber"></span> / <span class="totalPages"></span></span></div>'});
  await page.screenshot({path:path.join(dir,'guide-preview.png'),fullPage:false});
  console.log(JSON.stringify({sections:toc.length,rows:await page.locator('tbody tr').count(),pdf:path.join(dir,'AEMS_BPLD_MANUAL_TESTING_GUIDE.pdf')}));
} finally {await browser.close();}
