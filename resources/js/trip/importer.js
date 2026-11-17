/*
 * Excel import for the shopping list. Choosing a file uploads it for a check
 * (nothing saved), the review sheet shows what will happen row by row, and
 * "Import" sends it again with confirm=1.
 */

import { S } from './store.js';
import { $, esc, toast } from './util.js';
import { showLoader } from './loader.js';

const MAX_BYTES = 2 * 1024 * 1024;
const SHOW_ROWS = 40;
const ACTION_LABEL = { create: 'New', update: 'Update', unchanged: 'Same', error: 'Problem' };

let apiBase = '/api';
let selectedFile = null;
let afterImport = async () => {};

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

async function upload(file, confirm) {
    const body = new FormData();
    body.append('file', file);
    if (confirm) body.append('confirm', '1');

    let response;
    try {
        response = await fetch(`${apiBase}/trips/${S.trip.id}/shopping-import`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
            body,
        });
    } catch {
        throw new Error('You’re offline. Importing needs an internet connection.');
    }
    const data = await response.json().catch(() => null);
    if (!response.ok) {
        const message = data?.errors?.file?.[0] || data?.message || 'The file could not be imported.';
        throw new Error(message);
    }
    return data;
}

function checkFileLocally(file) {
    if (!/\.xlsx$/i.test(file.name)) return 'Only Excel files (.xlsx) can be imported. Save the sheet as .xlsx and try again.';
    if (file.size > MAX_BYTES) return 'The file is bigger than 2 MB.';
    if (!navigator.onLine) return 'You’re offline. Importing needs an internet connection.';
    return null;
}

function renderReview(file, result) {
    const s = result.summary;
    const ready = s.create + s.update;
    $('importFileName').innerHTML = `<svg class="i" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/></svg><span>${esc(file.name)}</span>`;

    const cell = (count, label, kind) => `<div class="is-cell ${kind}"><strong class="tnum">${count}</strong><span>${label}</span></div>`;
    $('importSummary').innerHTML = cell(s.create, 'new', 'create') + cell(s.update, 'updated', 'update')
        + cell(s.unchanged, 'same', 'unchanged') + cell(s.error, s.error === 1 ? 'problem' : 'problems', 'error');

    const problems = result.rows.filter((r) => r.action === 'error');
    $('importProblems').innerHTML = problems.length ? `
        <div class="import-problems" role="alert">
            <h3>Fix in Excel and upload again, or import the rest</h3>
            <ul>${problems.map((r) => `<li><span class="tnum">Row ${r.row}</span>${esc(r.message)}${r.name ? ` <em>(${esc(r.name)})</em>` : ''}</li>`).join('')}</ul>
        </div>` : '';

    const changes = result.rows.filter((r) => r.action === 'create' || r.action === 'update');
    const shown = changes.slice(0, SHOW_ROWS);
    $('importRows').innerHTML = changes.length ? `
        <h3 class="import-h">Will be imported</h3>
        <ul class="import-list">${shown.map((r) => `<li>
            <span class="ir-tag ${r.action}">${ACTION_LABEL[r.action]}</span>
            <span class="ir-name">${esc(r.name)}${r.details ? `<small>${esc(r.details)}</small>` : ''}</span>
            ${r.buyer ? `<span class="ir-buyer">${esc(r.buyer)}</span>` : ''}
        </li>`).join('')}</ul>
        ${changes.length > shown.length ? `<p class="muted">…and ${changes.length - shown.length} more.</p>` : ''}` : '';

    const confirm = $('importConfirm');
    confirm.disabled = ready === 0;
    confirm.textContent = ready === 0 ? 'Nothing to import' : `Import ${ready} item${ready === 1 ? '' : 's'}`;
}

async function onFileChosen(event) {
    const file = event.target.files?.[0];
    event.target.value = ''; // let the same file be chosen again after fixing it
    if (!file) return;

    const problem = checkFileLocally(file);
    if (problem) {
        toast(problem);
        return;
    }

    const loader = showLoader('Checking');
    try {
        const result = await upload(file, false);
        await loader.finish();
        selectedFile = file;
        renderReview(file, result);
        $('importDlg').showModal();
    } catch (error) {
        await loader.finish();
        toast(error.message);
    }
}

async function onConfirm() {
    if (!selectedFile) return;
    $('importDlg').close();
    const loader = showLoader('Importing');
    try {
        const result = await upload(selectedFile, true);
        await afterImport();
        await loader.finish();
        const s = result.summary;
        const parts = [];
        if (s.create) parts.push(`${s.create} new`);
        if (s.update) parts.push(`${s.update} updated`);
        if (s.error) parts.push(`${s.error} skipped`);
        toast(`Imported: ${parts.join(', ') || 'nothing changed'}.`);
    } catch (error) {
        await loader.finish();
        toast(error.message);
    } finally {
        selectedFile = null;
    }
}

/* ---------- Template download ---------- */

const TEMPLATE_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
let templateFile = null;

/** Installed app (home screen icon), where a plain download would leave the app with no way back. */
const isInstalledApp = () => window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

async function loadTemplate(link) {
    if (templateFile) return templateFile;
    const res = await fetch(link.href, { credentials: 'same-origin' });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    templateFile = new File([await res.blob()], link.getAttribute('download'), { type: TEMPLATE_TYPE });
    return templateFile;
}

/** Share sheet in the installed app (Save to Files, Sheets, WhatsApp…), so the app stays open. */
async function onTemplateClick(e) {
    const link = e.currentTarget;
    if (!isInstalledApp() || typeof navigator.share !== 'function') return; // normal download
    e.preventDefault();
    try {
        const file = await loadTemplate(link);
        if (navigator.canShare && !navigator.canShare({ files: [file] })) throw new Error('cannot share files');
        await navigator.share({ files: [file], title: 'E-Itinerary shopping template' });
    } catch (err) {
        if (err?.name === 'AbortError') return; // closed the share sheet
        if (err?.name === 'NotAllowedError' && templateFile) {
            toast('Template ready. Tap Download template again.');
            return;
        }
        toast('Couldn’t open the template here. Try again when you’re online.');
    }
}

function bindTemplate() {
    const link = $('templateLink');
    if (!link) return;
    link.addEventListener('click', onTemplateClick);
    // Fetch ahead so the share sheet opens straight from the tap (iOS needs that).
    if (isInstalledApp()) {
        const prefetch = () => loadTemplate(link).catch(() => {});
        setTimeout(prefetch, 4000);
        link.addEventListener('pointerdown', prefetch);
    }
}

export function bindImport({ base, onImported }) {
    apiBase = base.replace(/\/$/, '');
    afterImport = onImported;
    $('importFile').addEventListener('change', onFileChosen);
    $('importConfirm').addEventListener('click', onConfirm);
    bindTemplate();
}
