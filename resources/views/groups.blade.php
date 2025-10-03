@extends('layouts.app')

@section('title', 'Groups')

@section('content')
<div class="container">
    <h2 class="mb-4">Groups</h2>

    <div class="card datatable">
        <div class="table-responsive">
            <table class="table table-striped table-bordered mb-0 align-middle">
                <thead class="table-primary">
                    <tr>
                        <th>UUID</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Profiles</th>
                        <th>Cases</th>
                        <th>Orders (Buy)</th>
                        <th>Orders (Sell)</th>
                        <th>Created</th>
                        <th>Updated</th>
                    </tr>
                </thead>
                <tbody id="tbody"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
async function loadGroups() {
    const res = await fetch('{{ route("groups.index") }}', { headers: { 'Accept': 'application/json' }});
    const json = await res.json();
    if (!json.success) {
        document.querySelector('.container h2').insertAdjacentHTML('afterend',
            `<div class="alert alert-danger">Failed to load groups: ${json.message || 'Unknown error'}</div>`);
        return;
    }
    const rows = (json.data.groups || []).map(g => `
        <tr>
            <td class="text-break">${g.uuid}</td>
            <td>${g.name || '—'}</td>
            <td>${g.type || '—'}</td>
            <td>${g.profiles}</td>
            <td>${g.cases}</td>
            <td>${g.orders_buy}</td>
            <td>${g.orders_sell}</td>
            <td>${g.created_at ? new Date(g.created_at).toLocaleDateString() : '—'}</td>
            <td>${g.updated_at ? new Date(g.updated_at).toLocaleDateString() : '—'}</td>
        </tr>
    `).join('');
    document.getElementById('tbody').innerHTML = rows || `<tr><td colspan="9" class="text-center text-muted">No groups</td></tr>`;
}
document.addEventListener('DOMContentLoaded', loadGroups);
</script>
@endsection
