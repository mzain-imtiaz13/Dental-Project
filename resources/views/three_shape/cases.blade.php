@extends('layouts.app')

@section('title', '3Shape Cases')

@section('content')
<div class="container">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">3Shape Cases</h2>
        <div>
            <button id="syncBtn" class="btn btn-primary">
                <i class="bi bi-arrow-repeat"></i> Sync from 3Shape
            </button>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form class="row g-3" id="filters">
                <div class="col-md-4">
                    <label class="form-label">Patient</label>
                    <input id="qPatient" class="form-control" placeholder="Search patient">
                </div>
                <div class="col-md-3">
                    <label class="form-label">State</label>
                    <input id="qState" class="form-control" placeholder="e.g. Scanned">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="button" id="applyBtn" class="btn btn-outline-secondary w-100">Apply</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card datatable">
        <div class="table-responsive">
            <table class="table table-striped table-bordered mb-0">
                <thead class="table-primary">
                <tr>
                    <th>UUID</th>
                    <th>Patient</th>
                    <th>State</th>
                    <th>Created</th>
                    <th>Delivery</th>
                </tr>
                </thead>
                <tbody id="tbody"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
async function loadCases() {
    const p = new URLSearchParams();
    const patient = document.getElementById('qPatient').value;
    const state   = document.getElementById('qState').value;
    if (patient) p.set('patient', patient);
    if (state)   p.set('state', state);

    const res  = await fetch('{{ route('threeshape.cases.list') }}' + (p.toString() ? '?' + p.toString() : ''), {
        headers:{Accept:'application/json'}
    });
    const json = await res.json();

    const rows = (json.cases || []).map(c => `
        <tr>
            <td class="text-break">${c.id ?? '-'}</td>
            <td>${c.patient_name ?? '-'}</td>
            <td>${c.state ?? '-'}</td>
            <td>${c.created_at_3s ? new Date(c.created_at_3s).toLocaleString() : '-'}</td>
            <td>${c.delivery_date ? new Date(c.delivery_date).toLocaleString() : '-'}</td>
        </tr>
    `).join('');

    document.getElementById('tbody').innerHTML =
        rows || `<tr><td colspan="5" class="text-center text-muted">No cases</td></tr>`;
}

document.getElementById('applyBtn').addEventListener('click', loadCases);

document.getElementById('syncBtn').addEventListener('click', async () => {
    const btn = document.getElementById('syncBtn');
    const old = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Syncing...';

    try {
        const res = await fetch('{{ route('threeshape.cases.sync') }}', {
            headers:{Accept:'application/json'}
        });
        const j = await res.json();
        if (!j.success) {
            alert(j.message || 'Sync failed');
        } else {
            alert('Synced ' + j.count + ' cases');
        }
        await loadCases();
    } catch (e) {
        alert('Network error while syncing');
    }

    btn.disabled = false;
    btn.innerHTML = old;
});

document.addEventListener('DOMContentLoaded', loadCases);
</script>
@endsection
