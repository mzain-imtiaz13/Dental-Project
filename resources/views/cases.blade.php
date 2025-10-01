@extends('layouts.app')

@section('title', 'Cases')

@section('content')
<div class="container">
    <h2 class="mb-4">Cases</h2>

    <div class="card mb-3">
        <div class="card-body">
            <form id="casesFilter" class="row g-3">
                <div class="col-12 col-md-5">
                    <label class="form-label" for="search">Search</label>
                    <input class="form-control" id="search" placeholder="Search by UUID, name, patient, status...">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="status">Status</label>
                    <select id="status" class="form-select">
                        <option value="">All</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label" for="groupType">Group Type</label>
                    <select id="groupType" class="form-select">
                        <option value="">All</option>
                        <option value="LAB">LAB</option>
                        <option value="CLINIC">CLINIC</option>
                    </select>
                </div>
                <div class="col-12 col-md-2 d-flex align-items-end">
                    <button type="button" id="resetFilters" class="btn btn-outline-secondary w-100">Reset</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card datatable">
        <div class="table-responsive table-sticky">
            <table class="table table-striped table-bordered mb-0 compact align-middle" id="casesTable">
                <thead class="table-primary datatable-head">
                    <tr>
                        <th>UUID</th>
                        <th>Name</th>
                        <th>Patient</th>
                        <th>Status</th>
                        <th>Group</th>
                        <th>Created</th>
                        <th>Updated</th>
                        <th style="width: 90px;">Action</th> {{-- NEW --}}
                    </tr>
                </thead>
                <tbody id="tbody"></tbody>
            </table>
        </div>
        <div class="card-body d-flex justify-content-between align-items-center datatable-footer">
            <div class="small text-muted" id="summary"></div>
            <ul class="pagination mb-0" id="pagination"></ul>
        </div>
    </div>
</div>

{{-- CASE DETAILS MODAL (NEW) --}}
<div class="modal fade" id="caseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-folder2-open me-2"></i>
                    Case Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="caseModalBody">
                    <div class="text-muted">Loading…</div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
let raw = [];
let rawById = new Map(); // NEW: for quick lookup by UUID
const state = { page: 1, pageSize: 10, search: '', status: '', gtype: '' };

async function fetchCases() {
    const res = await fetch('{{ route("cases.index") }}', { headers: { 'Accept': 'application/json' }});
    const json = await res.json();
    if (!json.success) {
        document.querySelector('.container h2').insertAdjacentHTML('afterend',
            `<div class="alert alert-danger">Failed to load cases: ${json.message || 'Unknown error'}</div>`);
        return;
    }

    // Keep a flat row for the table + a details blob for the modal
    raw = json.data.cases.map(c => ({
        uuid: c.uuid,
        name: c.name || '—',
        patient: c.patient?.name || '—',
        status: c.status || '—',
        group: c.group?.name ? `${c.group.name} (${c.group.type || '—'})` : '—',
        gtype: c.group?.type || '',
        created: c.dateCreated ? new Date(c.dateCreated).toLocaleDateString() : '—',
        updated: c.dateUpdated ? new Date(c.dateUpdated).toLocaleDateString() : '—',
        details: c.details || {} // NEW
    }));

    rawById = new Map(raw.map(r => [String(r.uuid), r]));

    // Fill Status filter
    const statuses = Array.from(new Set(raw.map(r => r.status).filter(Boolean))).sort();
    document.getElementById('status').innerHTML = `<option value="">All</option>` + statuses.map(s => `<option>${s}</option>`).join('');

    render();
}

function applyFilters() {
    let data = raw.slice();
    if (state.search) {
        const q = state.search.toLowerCase();
        data = data.filter(r => `${r.uuid} ${r.name} ${r.patient} ${r.status} ${r.group}`.toLowerCase().includes(q));
    }
    if (state.status) data = data.filter(r => r.status === state.status);
    if (state.gtype)  data = data.filter(r => r.gtype === state.gtype);
    return data;
}

const tbody = document.getElementById('tbody');
const summary = document.getElementById('summary');
const pagination = document.getElementById('pagination');

function render() {
    const data = applyFilters();
    const start = (state.page - 1) * state.pageSize;
    const page = data.slice(start, start + state.pageSize);
    tbody.innerHTML = page.map(r => `
        <tr>
            <td class="text-break">${r.uuid}</td>
            <td>${r.name}</td>
            <td>${r.patient}</td>
            <td>${r.status}</td>
            <td>${r.group}</td>
            <td>${r.created}</td>
            <td>${r.updated}</td>
            <td>
                <button class="btn btn-sm btn-primary view-case" data-id="${r.uuid}">
                    <i class="bi bi-eye"></i> View
                </button>
            </td>
        </tr>
    `).join('');
    renderPager(data.length);
    summary.textContent = `Showing ${page.length ? start + 1 : 0}–${start + page.length} of ${data.length}`;
}

function renderPager(total) {
    const pages = Math.max(1, Math.ceil(total / state.pageSize));
    if (state.page > pages) state.page = pages;
    let html = `<li class="page-item ${state.page===1?'disabled':''}"><a class="page-link" href="#" data-p="prev">Previous</a></li>`;
    for (let i=1;i<=pages;i++) html += `<li class="page-item ${i===state.page?'active':''}"><a class="page-link" href="#" data-p="${i}">${i}</a></li>`;
    html += `<li class="page-item ${state.page===pages?'disabled':''}"><a class="page-link" href="#" data-p="next">Next</a></li>`;
    pagination.innerHTML = html;
}

document.getElementById('search').addEventListener('input', e => { state.search=e.target.value; state.page=1; render(); });
document.getElementById('status').addEventListener('change', e => { state.status=e.target.value; state.page=1; render(); });
document.getElementById('groupType').addEventListener('change', e => { state.gtype=e.target.value; state.page=1; render(); });
document.getElementById('resetFilters').addEventListener('click', () => {
    state.search=''; state.status=''; state.gtype=''; state.page=1;
    document.getElementById('casesFilter').reset(); render();
});
pagination.addEventListener('click', e => {
    if (e.target.tagName!=='A') return; e.preventDefault();
    const p = e.target.getAttribute('data-p');
    const total = applyFilters().length;
    const pages = Math.max(1, Math.ceil(total / state.pageSize));
    if (p==='prev' && state.page>1) state.page--;
    else if (p==='next' && state.page<pages) state.page++;
    else if (!isNaN(parseInt(p))) state.page=parseInt(p);
    render();
});

// NEW: handle View → open modal
document.addEventListener('click', (e) => {
    const btn = e.target.closest('.view-case');
    if (!btn) return;
    const id = btn.dataset.id;
    const row = rawById.get(String(id));
    if (!row) return;
    openCaseModal(row);
});

function fmt(d) {
    if (!d) return '—';
    try { return new Date(d).toLocaleString(); } catch { return d; }
}

function openCaseModal(row) {
    const d = row.details || {};
    const body = document.getElementById('caseModalBody');

    const group = d.group || {};
    const cred  = d.credential || {};
    const patient = d.patient || {};
    const tags = Array.isArray(d.tags) ? d.tags : (d.tags ? [String(d.tags)] : []);

    body.innerHTML = `
        <div class="mb-3">
            <h5 class="mb-1">Case ${row.uuid}</h5>
            <span class="badge bg-primary">${row.status || d.status || '—'}</span>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">Timestamps</div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-5">Created</dt><dd class="col-7">${fmt(d.date_created || d.dateCreated)}</dd>
                            <dt class="col-5">Updated</dt><dd class="col-7">${fmt(d.date_updated || d.dateUpdated)}</dd>
                            <dt class="col-5">Scanned</dt><dd class="col-7">${fmt(d.date_scanned || d.dateScanned)}</dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">Credential</div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-5">Source</dt><dd class="col-7">${d.source_api || 'Meditlink'}</dd>
                            <dt class="col-5">API</dt><dd class="col-7">${cred.api || '—'}</dd>
                            <dt class="col-5">Credential ID</dt><dd class="col-7">${cred.id ?? '—'}</dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">Group</div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-5">UUID</dt><dd class="col-7">${group.uuid || '—'}</dd>
                            <dt class="col-5">Name</dt><dd class="col-7">${group.name || row.group || '—'}</dd>
                            <dt class="col-5">Type</dt><dd class="col-7">${group.type || '—'}</dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">Patient</div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-5">Name</dt><dd class="col-7">${patient.name || row.patient || '—'}</dd>
                            <dt class="col-5">Code</dt><dd class="col-7">${patient.code || '—'}</dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card h-100">
                    <div class="card-header">Tags</div>
                    <div class="card-body">
                        ${tags.length ? tags.map(t => `<span class="badge bg-light text-dark border me-1 mb-1">${t}</span>`).join('') : '<span class="text-muted">—</span>'}
                    </div>
                </div>
            </div>

            <div class="col-12">
                <details>
                    <summary class="mb-2">Raw JSON</summary>
                    <pre class="bg-light p-3 rounded border" style="max-height: 300px; overflow:auto;">${JSON.stringify(d.raw || {}, null, 2)}</pre>
                </details>
            </div>
        </div>
    `;

    const modal = new bootstrap.Modal(document.getElementById('caseModal'));
    modal.show();
}

document.addEventListener('DOMContentLoaded', fetchCases);
</script>
@endsection
