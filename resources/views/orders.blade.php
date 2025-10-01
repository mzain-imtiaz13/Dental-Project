@extends('layouts.app')

@section('content')
    <div class="container">
        <h2 class="mb-4 d-flex justify-content-between align-items-center">
            Orders
            <div class="d-flex gap-2">
                <a class="btn btn-outline-secondary" href="{{ route('api-credentials.index') }}">
                    <i class="bi bi-gear"></i> Sync / Manage APIs
                </a>
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
                            <option value="Meditlink">Meditlink</option>
                            <option value="3Shape">3Shape</option>
                            <option value="DS Core">DS Core</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select id="status" class="form-select">
                            <option value="">All</option>
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
                            <th>Order #</th>
                            <th>Patient</th>
                            <th>Platform</th>
                            <th>Order Date</th>
                            <th>Status</th>
                            <th>Buyer</th>
                            <th>Seller</th>
                            <th style="width: 90px;">Action</th> {{-- NEW --}}
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

    {{-- ORDER DETAILS MODAL --}}
    <div class="modal fade" id="orderModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-receipt-cutoff me-2"></i>
                        Order Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="orderModalBody">
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
        let rawData = [];
        let rawById = new Map(); // for quick lookup when clicking View

        const state = { page: 1, pageSize: 10, search: '', platform: '', status: '' };

        async function fetchOrders() {
            try {
                const res = await fetch('{{ route("orders.index") }}', {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const result = await res.json();
                if (!result.success) throw new Error(result.message || 'Unknown error');

                rawData = result.data.orders.map(o => ({
                    id: o.id,
                    patient: o.patient?.name || '—',
                    platform: o.source_api || 'Meditlink',
                    date: o.created_at ? new Date(o.created_at).toLocaleDateString() : '—',
                    status: o.status || '—',
                    buyer: o.buyer || '—',
                    seller: o.seller || '—',
                    details: o.details || {}   // NEW: full details from DB
                }));

                rawById = new Map(rawData.map(r => [String(r.id), r]));

                // Populate Status filter dynamically
                const statuses = Array.from(new Set(rawData.map(r => r.status).filter(Boolean))).sort();
                const statusSel = document.getElementById('status');
                statusSel.innerHTML = `<option value="">All</option>` + statuses.map(s => `<option>${s}</option>`).join('');

                renderTable();

                document.querySelector('.container h2').insertAdjacentHTML('afterend',
                    `<div class="alert alert-success mb-3">Loaded ${rawData.length} orders from database.</div>`);
            } catch (e) {
                console.error(e);
                document.querySelector('.container h2').insertAdjacentHTML('afterend',
                    `<div class="alert alert-danger">Failed to fetch orders: ${e.message}</div>`);
            }
        }

        const tbody = document.querySelector('#ordersTable tbody');
        const pagination = document.getElementById('pagination');
        const tableSummary = document.getElementById('tableSummary');

        function applyFilters() {
            let data = rawData.slice();
            if (state.search) {
                const q = state.search.toLowerCase();
                data = data.filter(r => `${r.id} ${r.patient} ${r.platform} ${r.status} ${r.buyer} ${r.seller}`.toLowerCase().includes(q));
            }
            if (state.platform) data = data.filter(r => r.platform === state.platform);
            if (state.status)   data = data.filter(r => r.status === state.status);
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
                    <td>${r.status}</td>
                    <td>${r.buyer}</td>
                    <td>${r.seller}</td>
                    <td>
                        <button class="btn btn-sm btn-primary view-order" data-id="${r.id}">
                            <i class="bi bi-eye"></i> View
                        </button>
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

        // Filters + pagination
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

        // View button → open modal with full DB details
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.view-order');
            if (!btn) return;
            const id = btn.dataset.id;
            const row = rawById.get(String(id));
            if (!row) return;

            openOrderModal(row);
        });

        function fmt(d) {
            if (!d) return '—';
            try { return new Date(d).toLocaleString(); } catch { return d; }
        }

        function openOrderModal(row) {
            const d = row.details || {};
            const body = document.getElementById('orderModalBody');

            // Make safe helpers
            const buyer = d.buyer || {};
            const seller = d.seller || {};
            const kase  = d.case || {};
            const cred  = d.credential || {};

            body.innerHTML = `
                <div class="mb-3">
                    <h5 class="mb-1">Order #${row.id}</h5>
                    <span class="badge bg-primary">${row.status || d.status || '—'}</span>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header">Timestamps</div>
                            <div class="card-body">
                                <dl class="row mb-0">
                                    <dt class="col-5">Created</dt><dd class="col-7">${fmt(d.date_created || row.created_at)}</dd>
                                    <dt class="col-5">Updated</dt><dd class="col-7">${fmt(d.date_updated || row.updated_at)}</dd>
                                    <dt class="col-5">Desired Delivery</dt><dd class="col-7">${fmt(d.date_desired_delivery)}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header">Credential</div>
                            <div class="card-body">
                                <dl class="row mb-0">
                                    <dt class="col-5">Source</dt><dd class="col-7">${row.platform}</dd>
                                    <dt class="col-5">API</dt><dd class="col-7">${cred.api || '—'}</dd>
                                    <dt class="col-5">Credential ID</dt><dd class="col-7">${cred.id ?? '—'}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header">Buyer Group</div>
                            <div class="card-body">
                                <dl class="row mb-0">
                                    <dt class="col-5">UUID</dt><dd class="col-7">${buyer.uuid || '—'}</dd>
                                    <dt class="col-5">Name</dt><dd class="col-7">${buyer.name || row.buyer || '—'}</dd>
                                    <dt class="col-5">Type</dt><dd class="col-7">${buyer.type || '—'}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header">Seller Group</div>
                            <div class="card-body">
                                <dl class="row mb-0">
                                    <dt class="col-5">UUID</dt><dd class="col-7">${seller.uuid || '—'}</dd>
                                    <dt class="col-5">Name</dt><dd class="col-7">${seller.name || row.seller || '—'}</dd>
                                    <dt class="col-5">Type</dt><dd class="col-7">${seller.type || '—'}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">Case</div>
                            <div class="card-body">
                                <dl class="row mb-0">
                                    <dt class="col-3">UUID</dt><dd class="col-9">${kase.uuid || '—'}</dd>
                                    <dt class="col-3">Name</dt><dd class="col-9">${kase.name || '—'}</dd>
                                    <dt class="col-3">Status</dt><dd class="col-9">${kase.status || '—'}</dd>
                                    <dt class="col-3">Patient</dt><dd class="col-9">${(kase.patient_name || row.patient || '—')} ${kase.patient_code ? '(' + kase.patient_code + ')' : ''}</dd>
                                </dl>
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

            const modal = new bootstrap.Modal(document.getElementById('orderModal'));
            modal.show();
        }

        document.addEventListener('DOMContentLoaded', fetchOrders);
    </script>
@endsection
