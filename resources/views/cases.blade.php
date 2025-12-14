@extends('layouts.app')

@section('title', 'Cases')

@section('content')
<div class="container">
    <h2 class="mb-3">Cases</h2>

    {{-- Tabs --}}
    <ul class="nav nav-tabs mb-3" id="sourceTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" data-source="" data-bs-toggle="tab" type="button" role="tab">All</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-source="Meditlink" data-bs-toggle="tab" type="button" role="tab">Medit Link</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-source="3Shape" data-bs-toggle="tab" type="button" role="tab">3Shape</button>
        </li>
    </ul>

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
                    <th>Source</th>
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
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title d-flex align-items-center gap-2">
                    <i class="bi bi-folder2-open"></i>
                    <span id="caseModalTitle">Case Details</span>
                    <span class="badge bg-primary" id="caseModalStatus"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-0">
                <ul class="nav nav-tabs px-3 pt-3" id="caseDetailTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="summary-tab" data-bs-toggle="tab" data-bs-target="#summary-pane" type="button" role="tab">
                            Summary
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="actors-tab" data-bs-toggle="tab" data-bs-target="#actors-pane" type="button" role="tab">
                            Actors
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="attachments-tab" data-bs-toggle="tab" data-bs-target="#attachments-pane" type="button" role="tab">
                            Attachments / Scans
                        </button>
                    </li>
                    <!-- <li class="nav-item" role="presentation">
                        <button class="nav-link" id="raw-tab" data-bs-toggle="tab" data-bs-target="#raw-pane" type="button" role="tab">
                            Raw JSON
                        </button>
                    </li> -->
                </ul>

                <div class="tab-content p-3" id="caseDetailTabContent">
                    {{-- SUMMARY --}}
                    <div class="tab-pane fade show active" id="summary-pane" role="tabpanel" aria-labelledby="summary-tab">
                        <div id="caseSummaryContent" class="row g-3">
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-header">Case Info</div>
                                    <div class="card-body">
                                        <dl class="row mb-0 small" id="summaryCaseInfo"></dl>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-header">Patient</div>
                                    <div class="card-body">
                                        <dl class="row mb-0 small" id="summaryPatientInfo"></dl>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="card h-100">
                                    <div class="card-header">Group / Source</div>
                                    <div class="card-body">
                                        <dl class="row mb-0 small" id="summaryGroupInfo"></dl>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ACTORS --}}
                    <div class="tab-pane fade" id="actors-pane" role="tabpanel" aria-labelledby="actors-tab">
                        <div id="actorsContent" class="table-responsive">
                            <table class="table table-sm table-striped align-middle mb-0">
                                <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Roles</th>
                                    <th>Id</th>
                                </tr>
                                </thead>
                                <tbody id="actorsBody"></tbody>
                            </table>
                        </div>
                        <div class="text-muted small mt-2" id="actorsNote"></div>
                    </div>

                    {{-- ATTACHMENTS / SCANS --}}
                    <div class="tab-pane fade" id="attachments-pane" role="tabpanel" aria-labelledby="attachments-tab">
                        <h6>Attachments</h6>
                        <div class="table-responsive mb-4">
                            <table class="table table-sm table-striped align-middle mb-0">
                                <thead class="table-light">
                                <tr>
                                    <th>File Name</th>
                                    <th>Type</th>
                                    <th>Created</th>
                                    <th>Download</th>
                                </tr>
                                </thead>
                                <tbody id="attachmentsBody"></tbody>
                            </table>
                        </div>

                        <h6>Scans</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle mb-0">
                                <thead class="table-light">
                                <tr>
                                    <th>Jaw</th>
                                    <th>Type</th>
                                    <th>FileType</th>
                                    <th>Href</th>
                                </tr>
                                </thead>
                                <tbody id="scansBody"></tbody>
                            </table>
                        </div>
                    </div>

                    {{-- RAW JSON --}}
                    <div class="tab-pane fade" id="raw-pane" role="tabpanel" aria-labelledby="raw-tab">
                        <pre class="bg-light p-3 rounded border small" style="max-height: 400px; overflow:auto;"
                             id="rawJsonBox">{}</pre>
                        <div class="text-muted small mt-2" id="rawSourceNote"></div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
let rawCases = [];
let byUuid = new Map();

const threeshapeFileProxy = '{{ route("threeshape.file") }}';

const state = {
    page: 1,
    pageSize: 10,
    search: '',
    status: '',
    gtype: '',
    source: ''
};

// fetch summary list (Medit + 3Shape merged) from backend
async function fetchCases() {
    const res = await fetch('{{ route("cases.index") }}', {
        headers: { 'Accept': 'application/json' }
    });
    const json = await res.json();

    if (!json.success) {
        document.querySelector('.container h2').insertAdjacentHTML('afterend',
            `<div class="alert alert-danger">Failed to load cases: ${json.message || 'Unknown error'}</div>`);
        return;
    }

    rawCases = json.data.cases.map(c => ({
        uuid: c.uuid,
        name: c.name || '—',
        patient: c.patient?.name || '—',
        status: c.status || '—',
        source: c.source_api || '—',         // "Meditlink" / "3Shape"
        group: c.group?.name ? `${c.group.name} (${c.group.type || '—'})` : '—',
        gtype: c.group?.type || '',
        created: c.dateCreated ? new Date(c.dateCreated).toLocaleString() : '—',
        updated: c.dateUpdated ? new Date(c.dateUpdated).toLocaleString() : '—',

        // keep full detail from DB summary to populate first tab
        _detail: c,
    }));

    byUuid = new Map(rawCases.map(r => [String(r.uuid), r]));

    // Fill status dropdown based on loaded data
    const statuses = Array.from(new Set(rawCases.map(r => r.status).filter(Boolean))).sort();
    document.getElementById('status').innerHTML =
        `<option value="">All</option>` +
        statuses.map(s => `<option>${s}</option>`).join('');

    render();
}

function applyFilters() {
    let data = rawCases.slice();
    if (state.source) {
        data = data.filter(r => r.source === state.source);
    }
    if (state.search) {
        const q = state.search.toLowerCase();
        data = data.filter(r =>
            `${r.uuid} ${r.name} ${r.patient} ${r.status} ${r.group} ${r.source}`
                .toLowerCase()
                .includes(q)
        );
    }
    if (state.status) {
        data = data.filter(r => r.status === state.status);
    }
    if (state.gtype) {
        data = data.filter(r => r.gtype === state.gtype);
    }
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
            <td>${r.source}</td>
            <td>${r.group}</td>
            <td>${r.created}</td>
            <td>${r.updated}</td>
            <td>
                <button
                    class="btn btn-sm btn-primary view-case"
                    data-uuid="${r.uuid}"
                    data-source="${r.source}">
                    <i class="bi bi-eye"></i> View
                </button>
            </td>
        </tr>
    `).join('');

    renderPager(data.length);

    const shown = page.length ? `${start + 1}–${start + page.length}` : '0–0';
    summary.textContent = `Showing ${shown} of ${data.length}`;
}

function renderPager(total) {
    const pages = Math.max(1, Math.ceil(total / state.pageSize));
    if (state.page > pages) state.page = pages;

    let html = `
        <li class="page-item ${state.page===1?'disabled':''}">
            <a class="page-link" href="#" data-p="prev">Previous</a>
        </li>
    `;
    for (let i = 1; i <= pages; i++) {
        html += `
            <li class="page-item ${i===state.page?'active':''}">
                <a class="page-link" href="#" data-p="${i}">${i}</a>
            </li>`;
    }
    html += `
        <li class="page-item ${state.page===pages?'disabled':''}">
            <a class="page-link" href="#" data-p="next">Next</a>
        </li>`;
    pagination.innerHTML = html;
}

// filters / pagination handlers
document.getElementById('search').addEventListener('input', e => {
    state.search = e.target.value;
    state.page = 1;
    render();
});
document.getElementById('status').addEventListener('change', e => {
    state.status = e.target.value;
    state.page = 1;
    render();
});
document.getElementById('groupType').addEventListener('change', e => {
    state.gtype = e.target.value;
    state.page = 1;
    render();
});
document.getElementById('resetFilters').addEventListener('click', () => {
    state.search = '';
    state.status = '';
    state.gtype = '';
    state.page = 1;
    document.getElementById('casesFilter').reset();
    render();
});
pagination.addEventListener('click', e => {
    if (e.target.tagName !== 'A') return;
    e.preventDefault();
    const p = e.target.getAttribute('data-p');

    const total = applyFilters().length;
    const pages = Math.max(1, Math.ceil(total / state.pageSize));

    if (p === 'prev' && state.page > 1) {
        state.page--;
    } else if (p === 'next' && state.page < pages) {
        state.page++;
    } else if (!isNaN(parseInt(p))) {
        state.page = parseInt(p);
    }
    render();
});

// source tabs
document.getElementById('sourceTabs').addEventListener('click', e => {
    const btn = e.target.closest('button[data-source]');
    if (!btn) return;
    state.source = btn.getAttribute('data-source') || '';
    state.page = 1;
    render();
});

/**
 * UTIL: safe get
 */
function safe(v, fallback='—') {
    if (v === null || v === undefined || v === '') return fallback;
    return v;
}
function fmtDateTime(iso) {
    if (!iso) return '—';
    try { return new Date(iso).toLocaleString(); }
    catch { return iso; }
}

// click "View" -> open modal
document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.view-case');
    if (!btn) return;

    const uuid = btn.getAttribute('data-uuid');
    const source = btn.getAttribute('data-source'); // "Meditlink" or "3Shape"

    const summaryRow = byUuid.get(String(uuid));
    const modalEl = document.getElementById('caseModal');
    const modal = new bootstrap.Modal(modalEl);

    // reset modal
    document.getElementById('caseModalTitle').textContent = 'Case Details';
    document.getElementById('caseModalStatus').textContent = '';
    document.getElementById('summaryCaseInfo').innerHTML = '<div class="text-muted">Loading…</div>';
    document.getElementById('summaryPatientInfo').innerHTML = '';
    document.getElementById('summaryGroupInfo').innerHTML = '';
    document.getElementById('actorsBody').innerHTML = '';
    document.getElementById('actorsNote').textContent = '';
    document.getElementById('attachmentsBody').innerHTML = '';
    document.getElementById('scansBody').innerHTML = '';
    document.getElementById('rawJsonBox').textContent = '{}';
    document.getElementById('rawSourceNote').textContent = '';

    modal.show();

    // CASE A: Medit link case → we already have full detail in _detail from DB.
    // CASE B: 3Shape → we need to call /threeshape/cases/{uuid}/detail to get
    //          Actors, Scans, Attachments, etc.
    let fullDetail = summaryRow?._detail || {};
    let detailSource = 'summary';

    if (source === '3Shape') {
        try {
            const resp = await fetch(`{{ url('/threeshape/cases') }}/${encodeURIComponent(uuid)}/detail`, {
                headers: { 'Accept': 'application/json' }
            });
            const js = await resp.json();
            if (js.success && js.case) {
                fullDetail = js.case;
                detailSource = js.source || 'live';
            }
        } catch (err) {
            console.warn('3Shape detail fetch failed', err);
        }
    }

    // Fill Summary tab
    // ---- Basic Case Info
    document.getElementById('caseModalTitle').textContent =
        safe(fullDetail.Name || fullDetail.name || summaryRow?.name || 'Case');

    document.getElementById('caseModalStatus').textContent =
        safe(fullDetail.State || fullDetail.status || summaryRow?.status || '—');

    const summaryCaseInfo = document.getElementById('summaryCaseInfo');
    summaryCaseInfo.innerHTML = `
        <dt class="col-4">UUID</dt>
        <dd class="col-8 text-break">${safe(fullDetail.Id || fullDetail.uuid || summaryRow?.uuid)}</dd>

        <dt class="col-4">Version</dt>
        <dd class="col-8">${safe(fullDetail.Version)}</dd>

        <dt class="col-4">Order No</dt>
        <dd class="col-8">${safe(fullDetail.OrderNo)}</dd>

        <dt class="col-4">3Shape Order</dt>
        <dd class="col-8">${safe(fullDetail.ThreeShapeOrderNo)}</dd>

        <dt class="col-4">Scan Source</dt>
        <dd class="col-8">${safe(fullDetail.ScanSource)}</dd>

        <dt class="col-4">Application</dt>
        <dd class="col-8">${safe(fullDetail.Application)}</dd>

        <dt class="col-4">Created</dt>
        <dd class="col-8">${fmtDateTime(fullDetail.Created || fullDetail.dateCreated)}</dd>

        <dt class="col-4">Updated</dt>
        <dd class="col-8">${fmtDateTime(fullDetail.UpdatedOn || fullDetail.dateUpdated)}</dd>

        <dt class="col-4">Received</dt>
        <dd class="col-8">${fmtDateTime(fullDetail.ReceivedOn)}</dd>

        <dt class="col-4">Delivery</dt>
        <dd class="col-8">${fmtDateTime(fullDetail.DeliveryDate || fullDetail.deliveryDate)}</dd>
    `;

    // ---- Patient Info
    const patFirst = fullDetail.Patient?.FirstName;
    const patLast  = fullDetail.Patient?.LastName;
    const patName =
        fullDetail.PatientName ||
        [patFirst, patLast].filter(Boolean).join(' ') ||
        summaryRow?.patient ||
        '—';

    document.getElementById('summaryPatientInfo').innerHTML = `
        <dt class="col-4">Name</dt>
        <dd class="col-8">${safe(patName)}</dd>

        <dt class="col-4">External ID</dt>
        <dd class="col-8">${safe(fullDetail.Patient?.ExternalId)}</dd>

        <dt class="col-4">Ref No</dt>
        <dd class="col-8">${safe(fullDetail.Patient?.PatientRefNo)}</dd>

        <dt class="col-4">Creator (User ID)</dt>
        <dd class="col-8">${safe(fullDetail.CreatorId)}</dd>

        <dt class="col-4">Operator (User ID)</dt>
        <dd class="col-8">${safe(fullDetail.OperatorId)}</dd>
    `;

    // ---- Group / Source
    const groupBlock = summaryRow?._detail?.group || {};
    const srcBlock   = summaryRow?._detail || {};
    document.getElementById('summaryGroupInfo').innerHTML = `
        <dt class="col-4">Group UUID</dt>
        <dd class="col-8">${safe(groupBlock.uuid)}</dd>

        <dt class="col-4">Group Name</dt>
        <dd class="col-8">${safe(groupBlock.name)}</dd>

        <dt class="col-4">Group Type</dt>
        <dd class="col-8">${safe(groupBlock.type)}</dd>

        <dt class="col-4">Source</dt>
        <dd class="col-8">${safe(srcBlock.source_api || summaryRow?.source)}</dd>
    `;

    // Fill Actors tab
    const actorsBody = document.getElementById('actorsBody');
    const actorsNote = document.getElementById('actorsNote');
    const actors = Array.isArray(fullDetail.Actors) ? fullDetail.Actors : [];
    if (actors.length === 0) {
        actorsBody.innerHTML = `
            <tr><td colspan="4" class="text-muted text-center small">No actors info</td></tr>
        `;
        actorsNote.textContent = '';
    } else {
        actorsBody.innerHTML = actors.map(a => `
            <tr>
                <td>${safe(a.Name)}</td>
                <td>${safe(a.Email)}</td>
                <td>${Array.isArray(a.Roles) ? a.Roles.join(', ') : safe(a.Roles)}</td>
                <td class="text-break">${safe(a.Id)}</td>
            </tr>
        `).join('');
        actorsNote.textContent = `${actors.length} actor(s)`;
    }

    // Fill Attachments/Scans tab
    const atBody = document.getElementById('attachmentsBody');
    const scansBody = document.getElementById('scansBody');

    const attachments = Array.isArray(fullDetail.Attachments) ? fullDetail.Attachments : [];
    if (attachments.length === 0) {
        atBody.innerHTML = `
            <tr><td colspan="4" class="text-muted text-center small">No attachments</td></tr>
        `;
    } else {
    atBody.innerHTML = attachments.map(att => {
        const originalHref = att.Href || att.href || null;
        // Always go through our proxy so Authorization header is added
        const downloadHref = originalHref
            ? `${threeshapeFileProxy}?href=${encodeURIComponent(originalHref)}`
            : null;

        return `
            <tr>
                <td class="text-break">${safe(att.Name)}</td>
                <td>${safe(att.Type || att.FileType)}</td>
                <td>${fmtDateTime(att.Created)}</td>
                <td>
                    ${downloadHref ? `
                        <a href="${downloadHref}" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-download"></i> Open
                        </a>` : '—'}
                </td>
            </tr>
        `;
    }).join('');
}

    const scans = Array.isArray(fullDetail.Scans) ? fullDetail.Scans : [];
    if (scans.length === 0) {
        scansBody.innerHTML = `
            <tr><td colspan="4" class="text-muted text-center small">No scans</td></tr>
        `;
    } else {
    scansBody.innerHTML = scans.map(sc => {
        const originalHref = sc.Href || sc.href || null;
        const scanHref = originalHref
            ? `${threeshapeFileProxy}?href=${encodeURIComponent(originalHref)}`
            : null;

        return `
            <tr>
                <td>${safe(sc.JawType)}</td>
                <td>${safe(sc.Type)}</td>
                <td>${safe(sc.FileType)}</td>
                <td class="text-break">
                    ${scanHref ? `<a href="${scanHref}" target="_blank">${scanHref}</a>` : '—'}
                </td>
            </tr>
        `;
    }).join('');
}

    // RAW JSON tab
    document.getElementById('rawJsonBox').textContent = JSON.stringify(fullDetail, null, 2);
    document.getElementById('rawSourceNote').textContent = `Source: ${detailSource}`;
});

// Export CSV button
document.getElementById('exportCsv').addEventListener('click', () => {
    const statusVal = document.getElementById('status').value || '';
    const groupTypeVal = document.getElementById('groupType').value || '';

    const params = new URLSearchParams();
    if (statusVal) params.set('status', statusVal);
    if (groupTypeVal) params.set('groupType', groupTypeVal);
    params.set('export', 'csv');

    window.open('{{ route("cases.index") }}' + '?' + params.toString(), '_blank');
});

document.addEventListener('DOMContentLoaded', fetchCases);
</script>
@endsection
