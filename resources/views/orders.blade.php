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
        let rawData = []; // Will be populated from API

        const state = { page: 1, pageSize: 5, search: '', platform: '', status: '' };

        async function fetchOrders() {
            try {
                const response = await fetch('{{ route("orders.index") }}', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const result = await response.json();
                
                if (result.success) {
                    // Transform API data to match table structure
                    rawData = result.data.orders.map(order => ({
                        id: order.id,
                        patient: order.patient?.name || 'N/A',
                        platform: order.source_api,
                        date: new Date(order.created_at).toLocaleDateString(),
                        status: order.status,
                        files: order.case_info?.files || []
                    }));
                    renderTable();

                    // Show API status
                    const statusHtml = Object.entries(result.data.api_statuses)
                        .map(([api, status]) => `
                            <div class="alert alert-${status.status === 'error' ? 'danger' : 'success'} mb-2">
                                <strong>${api}:</strong> ${status.message}
                            </div>
                        `).join('');
                    document.querySelector('.container h2').insertAdjacentHTML('afterend', statusHtml);
                } else {
                    throw new Error(result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                document.querySelector('.container h2').insertAdjacentHTML('afterend', `
                    <div class="alert alert-danger">
                        Failed to fetch orders: ${error.message}
                    </div>
                `);
            }
        }

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
                    <td>
                        ${r.files.length ? 
                            `<a href="#" class="btn btn-link" onclick="downloadFiles('${r.id}')">
                                Download (${r.files.length})
                            </a>` : 
                            'No files'
                        }
                    </td>
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

        function downloadFiles(orderId) {
            // Implement file download logic here
            console.log('Downloading files for order:', orderId);
        }

        // Remove the dummy data and fetch real data when page loads
        document.addEventListener('DOMContentLoaded', fetchOrders);
    </script>
@endsection


