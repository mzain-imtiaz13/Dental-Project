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
                    <div class="w-100 d-flex gap-2">
                        <button type="button" id="resetFilters" class="btn btn-outline-secondary w-100">Reset</button>
                        <div class="btn-group">
                            <button type="button" id="exportCsv" class="btn btn-outline-primary">
                                <i class="bi bi-download"></i> Export CSV
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card datatable">
        <div class="table-responsive table-sticky">
            <table class="table table-striped table-bordered mb-0 compact align-middle">
                <thead class="table-primary datatable-head">
                    <tr>
                        <th>UUID</th>
                        <th>Name</th>
                        <th>Patient</th>
                        <th>Status</th>
                        <th>Group</th>
                        <th>Created</th>
                        <th>Updated</th>
                        <th style="width: 90px;">Action</th>
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

{{-- CASE DETAILS MODAL --}}
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
let byUuid = new Map(); // quick lookup for modal

const state = { page: 1, pageSize: 10, search: '', status: '', gtype: '' };

async function fetchCases() {
    // GET JSON cases (existing flow)
    const params = new URLSearchParams(window.location.search);
    const url = '{{ route("cases.index") }}' + '?' + params.toString();

    const res = await fetch('{{ route("cases.index") }}', { headers: { 'Accept': 'application/json' }});
    const json = await res.json();
    if (!json.success) {
        document.querySelector('.container h2').insertAdjacentHTML('afterend',
            `<div class="alert alert-danger">Failed to load cases: ${json.message || 'Unknown error'}</div>`);
        return;
    }

    // Keep both flattened fields (for table) and the original case object (for modal).
    raw = json.data.cases.map(c => ({
        uuid: c.uuid,
        name: c.name || '—',
        patient: c.patient?.name || '—',
        status: c.status || '—',
        group: c.group?.name ? `${c.group.name} (${c.group.type || '—'})` : '—',
        gtype: c.group?.type || '',
        created: c.dateCreated ? new Date(c.dateCreated).toLocaleDateString() : '—',
        updated: c.dateUpdated ? new Date(c.dateUpdated).toLocaleDateString() : '—',
        _detail: c, // <-- original payload from controller for modal
    }));

    byUuid = new Map(raw.map(r => [String(r.uuid), r]));

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
                <button class="btn btn-sm btn-primary view-case" data-uuid="${r.uuid}">
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

// Action column: open modal
document.addEventListener('click', (e) => {
    const btn = e.target.closest('.view-case');
    if (!btn) return;
    const uuid = btn.getAttribute('data-uuid');
    const row = byUuid.get(String(uuid));
    if (!row) return;
    openCaseModal(row);
});

function fmtDt(iso) {
    if (!iso) return '—';
    try { return new Date(iso).toLocaleString(); } catch { return iso; }
}

function openCaseModal(row) {
    const d = row._detail || {};

    const patientName = d?.patient?.name || row.patient || '—';
    const patientCode = d?.patient?.code ? ` (${d.patient.code})` : '';
    const group = d?.group || {};
    const body = document.getElementById('caseModalBody');

    body.innerHTML = `
        <div class="mb-3">
            <h5 class="mb-1">Case: ${d.name || row.name || '—'}</h5>
            <div class="small text-muted">UUID: <span class="text-break">${d.uuid || row.uuid}</span></div>
            <span class="badge bg-primary mt-2">${d.status || row.status || '—'}</span>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">Timestamps</div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-5">Created</dt><dd class="col-7">${fmtDt(d.dateCreated)}</dd>
                            <dt class="col-5">Updated</dt><dd class="col-7">${fmtDt(d.dateUpdated)}</dd>
                            <dt class="col-5">Scanned</dt><dd class="col-7">${fmtDt(d.dateScanned)}</dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">Patient</div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-5">Name</dt><dd class="col-7">${patientName}${patientCode}</dd>
                            <dt class="col-5">Source</dt><dd class="col-7">${d.source_api || '—'}</dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card h-100">
                    <div class="card-header">Group</div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-3">UUID</dt><dd class="col-9">${group.uuid || '—'}</dd>
                            <dt class="col-3">Name</dt><dd class="col-9">${group.name || '—'}</dd>
                            <dt class="col-3">Type</dt><dd class="col-9">${group.type || '—'}</dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <details>
                    <summary class="mb-2">Raw JSON</summary>
                    <pre class="bg-light p-3 rounded border" style="max-height: 300px; overflow:auto;">${JSON.stringify(d, null, 2)}</pre>
                </details>
            </div>
        </div>
    `;

    const modal = new bootstrap.Modal(document.getElementById('caseModal'));
    modal.show();
}

document.addEventListener('DOMContentLoaded', fetchCases);

/**
 * Export CSV (server-side). Builds URL with current filters and opens as a download.
 * Server endpoint: /cases?export=csv
 */
document.getElementById('exportCsv').addEventListener('click', () => {
    const searchVal = document.getElementById('search').value || '';
    const statusVal = document.getElementById('status').value || '';
    const groupTypeVal = document.getElementById('groupType').value || '';

    const params = new URLSearchParams();
    if (searchVal) params.set('patient', searchVal); // preserve server filter param if you use 'patient'
    if (statusVal) params.set('status', statusVal);
    if (groupTypeVal) params.set('groupType', groupTypeVal);
    params.set('export', 'csv');

    // open in new tab so download starts without losing the current page
    window.open('{{ route("cases.index") }}' + '?' + params.toString(), '_blank');
});
</script>
@endsection
