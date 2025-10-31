@extends('layouts.app')

@section('title', 'Profiles')

@section('content')
<div class="container">
    <h2 class="mb-4">Profiles</h2>

    <div class="card datatable">
        <div class="table-responsive">
            <table class="table table-striped table-bordered mb-0 align-middle" id="profilesTable">
                <thead class="table-primary">
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Group</th>
                    <th>API</th>
                    <th>Created</th>
                    <th>Updated</th>
                    <th style="width: 90px;">Action</th>
                </tr>
                </thead>
                <tbody id="tbody"></tbody>
            </table>
        </div>
    </div>
</div>

{{-- PROFILE DETAILS MODAL --}}
<div class="modal fade" id="profileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-person-badge me-2"></i>
                    Profile Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="profileModalBody">
                <div class="text-muted">Loading…</div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
let rowsById = new Map();

function fmt(d) {
    if (!d) return '—';
    try { return new Date(d).toLocaleString(); } catch { return d; }
}

async function loadProfiles() {
    const res  = await fetch('{{ route("profiles.index") }}', {
        headers: { 'Accept': 'application/json' }
    });

    const json = await res.json();
    if (!json.success) {
        document.querySelector('.container h2').insertAdjacentHTML('afterend',
            `<div class="alert alert-danger">Failed to load profiles: ${json.message || 'Unknown error'}</div>`);
        return;
    }

    const profiles = json.data.profiles || [];
    rowsById = new Map(profiles.map(p => [String(p.id), p]));

    const rows = profiles.map(p => `
        <tr>
            <td>${p.name || '—'}</td>
            <td>${p.email || '—'}</td>
            <td>${p.group_name ? `${p.group_name} (${p.group_type || '—'})` : '—'}</td>
            <td>${p.api || '—'}</td>
            <td>${p.created_at ? new Date(p.created_at).toLocaleDateString() : '—'}</td>
            <td>${p.updated_at ? new Date(p.updated_at).toLocaleDateString() : '—'}</td>
            <td>
                <button class="btn btn-sm btn-primary view-profile" data-id="${p.id}">
                    <i class="bi bi-eye"></i> View
                </button>
            </td>
        </tr>
    `).join('');

    document.getElementById('tbody').innerHTML =
        rows || `<tr><td colspan="7" class="text-center text-muted">No profiles</td></tr>`;
}

document.addEventListener('click', (e) => {
    const btn = e.target.closest('.view-profile');
    if (!btn) return;
    const id = btn.dataset.id;
    const row = rowsById.get(String(id));
    if (!row) return;
    openProfileModal(row);
});

function openProfileModal(row) {
    const d    = row.details || {};
    const g    = d.group || {};
    const cred = d.credential || {};
    const img  = d.profile_image;

    const body = document.getElementById('profileModalBody');
    body.innerHTML = `
        <div class="row g-3">
            <div class="col-md-8">
                <h5 class="mb-1">${row.name || d.name || '—'}</h5>
                <div class="text-muted">${row.email || d.email || '—'}</div>
                <div class="mt-2">
                    <span class="badge bg-light text-dark border">${row.api || cred.name || cred.api || '—'}</span>
                </div>
            </div>
            <div class="col-md-4 text-md-end">
                ${img && img.url ? `<img src="${img.url}" alt="Profile Image" class="img-fluid rounded border">` : ''}
            </div>

            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">Group</div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-5">UUID</dt><dd class="col-7">${g.uuid || row.group_uuid || '—'}</dd>
                            <dt class="col-5">Name</dt><dd class="col-7">${row.group_name || g.name || '—'}</dd>
                            <dt class="col-5">Type</dt><dd class="col-7">${row.group_type || g.type || '—'}</dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">Credential</div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-5">ID</dt><dd class="col-7">${cred.id ?? '—'}</dd>
                            <dt class="col-5">API</dt><dd class="col-7">${cred.api || row.api || '—'}</dd>
                            <dt class="col-5">Name</dt><dd class="col-7">${cred.name || row.api || '—'}</dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">Timestamps</div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-3">Created</dt><dd class="col-9">${fmt(d.date_created)}</dd>
                            <dt class="col-3">Updated</dt><dd class="col-9">${fmt(d.date_updated)}</dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <details>
                    <summary class="mb-2">Raw JSON</summary>
                    <pre class="bg-light p-3 rounded border" style="max-height: 300px; overflow:auto;">${
                        JSON.stringify(d.raw || {}, null, 2)
                    }</pre>
                </details>
            </div>
        </div>
    `;

    new bootstrap.Modal(document.getElementById('profileModal')).show();
}

document.addEventListener('DOMContentLoaded', loadProfiles);
</script>
@endsection
