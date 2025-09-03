@extends('layouts.app')

@section('content')
    <div class="container">
        <h2 class="mb-4 d-flex justify-content-between align-items-center">
            Orders
            <div class="d-flex gap-2">
                <button class="btn btn-success" id="downloadBtn">Download Orders</button>
            </div>
        </h2>

        <div class="card mb-3">
            <div class="card-body">
                <form id="ordersFilter" class="row g-3">
                    <div class="col-12 col-md-4">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" id="search" class="form-control" placeholder="Search by patient, platform, status...">
                    </div>
                    <div class="col-6 col-md-3">
                        <label for="platform" class="form-label">Platform</label>
                        <select id="platform" class="form-select">
                            <option value="">All</option>
                            <option value="3Shape">3Shape</option>
                            <option value="DScore">DScore</option>
                            <option value="Meditlink">Meditlink</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select id="status" class="form-select">
                            <option value="">All</option>
                            <option>Admitted</option>
                            <option>Discharged</option>
                            <option>Under Observation</option>
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
                <table class="table table-striped table-bordered mb-0 compact align-middle datatable-table" id="ordersTable">
                    <thead class="table-primary datatable-head">
                        <tr>
                            <th>Order ID</th>
                            <th>Patient Name</th>
                            <th>Platform</th>
                            <th>Order Date</th>
                            <th>Status</th>
                            <th>Files</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="card-body d-flex justify-content-between align-items-center datatable-footer">
                <div class="small text-muted datatable-summary" id="tableSummary"></div>
                <nav>
                    <ul class="pagination mb-0 datatable-pagination" id="pagination"></ul>
                </nav>
            </div>
        </div>
    </div>

    <script>
        const rawData = [
            { id: '12345', patient: 'John Doe', platform: '3Shape', date: '2025-08-26', status: 'Admitted' },
            { id: '12346', patient: 'Jane Smith', platform: 'DScore', date: '2025-07-15', status: 'Under Observation' },
            { id: '12347', patient: 'Michael Brown', platform: 'Meditlink', date: '2025-08-01', status: 'Discharged' },
            { id: '12348', patient: 'Emily White', platform: '3Shape', date: '2025-07-30', status: 'Admitted' },
            { id: '12349', patient: 'David Clark', platform: 'DScore', date: '2025-06-12', status: 'Under Observation' },
            { id: '12350', patient: 'Chris Evans', platform: '3Shape', date: '2025-05-20', status: 'Admitted' },
            { id: '12351', patient: 'Mark Lee', platform: 'Meditlink', date: '2025-08-05', status: 'Discharged' },
            { id: '12352', patient: 'Sara Park', platform: 'DScore', date: '2025-07-18', status: 'Admitted' },
            { id: '12353', patient: 'Nina Gomez', platform: '3Shape', date: '2025-06-28', status: 'Under Observation' },
            { id: '12354', patient: 'Omar Ali', platform: 'Meditlink', date: '2025-06-10', status: 'Admitted' },
            { id: '12355', patient: 'Laura King', platform: '3Shape', date: '2025-08-10', status: 'Discharged' },
            { id: '12356', patient: 'Ravi Patel', platform: 'DScore', date: '2025-08-12', status: 'Admitted' },
        ];

        const state = { page: 1, pageSize: 5, search: '', platform: '', status: '' };

        const tbody = document.querySelector('#ordersTable tbody');
        const pagination = document.getElementById('pagination');
        const tableSummary = document.getElementById('tableSummary');

        function applyFilters() {
            let data = rawData.slice();
            if (state.search) {
                const q = state.search.toLowerCase();
                data = data.filter(r => `${r.id} ${r.patient} ${r.platform} ${r.status}`.toLowerCase().includes(q));
            }
            if (state.platform) {
                data = data.filter(r => r.platform === state.platform);
            }
            if (state.status) {
                data = data.filter(r => r.status === state.status);
            }
            return data;
        }

        function renderTable() {
            const data = applyFilters();
            const start = (state.page - 1) * state.pageSize;
            const pageData = data.slice(start, start + state.pageSize);
            tbody.innerHTML = pageData.map(r => `
                <tr>
                    <td>${r.id}</td>
                    <td>${r.patient}</td>
                    <td>${r.platform}</td>
                    <td>${r.date}</td>
                    <td>${renderStatus(r.status)}</td>
                    <td><a href="#" class="btn btn-link">Download</a></td>
                </tr>
            `).join('');
            renderPagination(data.length);
            tableSummary.textContent = `Showing ${pageData.length ? start + 1 : 0}–${start + pageData.length} of ${data.length}`;
        }

        function renderPagination(total) {
            const pages = Math.max(1, Math.ceil(total / state.pageSize));
            if (state.page > pages) state.page = pages;
            let html = '';
            html += `<li class="page-item ${state.page === 1 ? 'disabled' : ''}"><a class="page-link" href="#" data-page="prev">Previous</a></li>`;
            for (let p = 1; p <= pages; p++) {
                html += `<li class="page-item ${p === state.page ? 'active' : ''}"><a class="page-link" href="#" data-page="${p}">${p}</a></li>`;
            }
            html += `<li class="page-item ${state.page === pages ? 'disabled' : ''}"><a class="page-link" href="#" data-page="next">Next</a></li>`;
            pagination.innerHTML = html;
        }

        document.getElementById('search').addEventListener('input', (e) => { state.search = e.target.value; state.page = 1; renderTable(); });
        document.getElementById('platform').addEventListener('change', (e) => { state.platform = e.target.value; state.page = 1; renderTable(); });
        document.getElementById('status').addEventListener('change', (e) => { state.status = e.target.value; state.page = 1; renderTable(); });
        document.getElementById('resetFilters').addEventListener('click', () => {
            state.search = ''; state.platform = ''; state.status = ''; state.page = 1;
            document.getElementById('ordersFilter').reset();
            renderTable();
        });

        pagination.addEventListener('click', (e) => {
            if (e.target.tagName !== 'A') return;
            e.preventDefault();
            const target = e.target.getAttribute('data-page');
            const total = applyFilters().length;
            const pages = Math.max(1, Math.ceil(total / state.pageSize));
            if (target === 'prev' && state.page > 1) state.page--;
            else if (target === 'next' && state.page < pages) state.page++;
            else if (!isNaN(parseInt(target))) state.page = parseInt(target);
            renderTable();
        });

        function renderStatus(status) {
            const key = status.toLowerCase().replaceAll(' ', '-');
            const map = {
                'admitted': 'status-admitted',
                'discharged': 'status-discharged',
                'under-observation': 'status-under-observation'
            };
            const cls = map[key] || 'status-admitted';
            return `<span class="status-badge ${cls}">${status}</span>`;
        }

        renderTable();
    </script>
@endsection


