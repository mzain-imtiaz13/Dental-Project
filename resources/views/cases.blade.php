@extends('layouts.app')

@section('title', 'Medit Link Cases')

@section('content')
<div class="container">
    <h2 class="mb-4">Medit Link Cases</h2>

    <div class="card shadow-sm">
        <div class="card-body">
            <div id="cases-table">
                <p class="text-muted">Loading cases...</p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    fetch("{{ route('cases.index') }}", {
        headers: {
            "Accept": "application/json"
        }
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) {
            document.getElementById("cases-table").innerHTML = 
                `<div class="alert alert-danger">Failed to load cases: ${data.message}</div>`;
            return;
        }

        const cases = data.data.cases || [];
        if (cases.length === 0) {
            document.getElementById("cases-table").innerHTML = 
                `<div class="alert alert-warning">No cases found</div>`;
            return;
        }

        let rows = cases.map(c => `
            <tr>
                <td>${c.uuid}</td>
                <td>${c.name}</td>
                <td>${c.patient?.name || '-'}</td>
                <td>${c.status}</td>
                <td>${c.dateCreated}</td>
                <td>${c.dateUpdated}</td>
            </tr>
        `).join("");

        document.getElementById("cases-table").innerHTML = `
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>UUID</th>
                            <th>Name</th>
                            <th>Patient</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Updated</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>`;
    })
    .catch(err => {
        console.error(err);
        document.getElementById("cases-table").innerHTML = 
            `<div class="alert alert-danger">Error loading cases</div>`;
    });
});
</script>
@endsection
