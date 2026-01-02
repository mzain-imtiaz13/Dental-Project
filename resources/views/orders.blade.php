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

        {{-- Platform Tabs --}}
        <ul class="nav nav-tabs mb-3" id="ordersTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-source="all" type="button" role="tab">
                    <i class="bi bi-collection"></i> All Orders
                    <span class="badge bg-secondary ms-1" id="allCount">0</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="medit-tab" data-bs-toggle="tab" data-source="medit" type="button" role="tab">
                    <i class="bi bi-box"></i> Medit Link
                    <span class="badge bg-primary ms-1" id="meditCount">0</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="dscore-tab" data-bs-toggle="tab" data-source="dscore" type="button" role="tab">
                    <i class="bi bi-box-seam"></i> DS Core
                    <span class="badge bg-info ms-1" id="dscoreCount">0</span>
                </button>
            </li>
        </ul>

        <div class="card mb-3">
            <div class="card-body">
                <form id="ordersFilter" class="row g-3">
                    <div class="col-12 col-md-5">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" id="search" class="form-control" placeholder="Search by patient, order #, status...">
                    </div>
                    <div class="col-6 col-md-4">
                        <label for="status" class="form-label">Status</label>
                        <select id="status" class="form-select">
                            <option value="">All</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-3 d-flex align-items-end">
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
                            <th>Type</th>
                            <th>Order Date</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Practice/Buyer</th>
                            <th>Lab/Seller</th>
                            <th style="width: 90px;">Action</th>
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

        const state = { page: 1, pageSize: 10, search: '', status: '', source: 'all' };

        async function fetchOrders(source = 'all') {
            try {
                const url = new URL('{{ route("orders.index") }}', window.location.origin);
                url.searchParams.set('source', source);

                const res = await fetch(url, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const result = await res.json();
                if (!result.success) throw new Error(result.message || 'Unknown error');

                // Update tab counts
                document.getElementById('allCount').textContent = result.data.total_count || 0;
                document.getElementById('meditCount').textContent = result.data.medit_count || 0;
                document.getElementById('dscoreCount').textContent = result.data.dscore_count || 0;

                rawData = result.data.orders.map(o => ({
                    id: o.id,
                    order_id: o.order_id || o.id,
                    order_number: o.order_number || o.id,
                    patient: o.patient?.name || '—',
                    platform: o.source_api || 'Unknown',
                    order_type: o.order_type || o.details?.order_type || '—',
                    date: o.created_at ? new Date(o.created_at).toLocaleDateString() : '—',
                    due_date: o.due_date ? new Date(o.due_date).toLocaleDateString() : (o.details?.due_date ? new Date(o.details.due_date).toLocaleDateString() : '—'),
                    status: o.status || '—',
                    buyer: o.buyer || '—',
                    seller: o.seller || '—',
                    details: o.details || {}
                }));

                rawById = new Map(rawData.map(r => [String(r.id), r]));

                // Populate Status filter dynamically
                const statuses = Array.from(new Set(rawData.map(r => r.status).filter(Boolean).filter(s => s !== '—'))).sort();
                const statusSel = document.getElementById('status');
                statusSel.innerHTML = `<option value="">All</option>` + statuses.map(s => `<option>${s}</option>`).join('');

                renderTable();

                // Remove existing alerts
                document.querySelectorAll('.container .alert').forEach(el => el.remove());

                const sourceLabel = source === 'all' ? 'all sources' : (source === 'medit' ? 'Medit Link' : 'DS Core');
                document.querySelector('.container h2').insertAdjacentHTML('afterend',
                    `<div class="alert alert-success mb-3 alert-dismissible fade show">
                        Loaded ${rawData.length} orders from ${sourceLabel}.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>`);
            } catch (e) {
                console.error(e);
                document.querySelectorAll('.container .alert').forEach(el => el.remove());
                document.querySelector('.container h2').insertAdjacentHTML('afterend',
                    `<div class="alert alert-danger alert-dismissible fade show">
                        Failed to fetch orders: ${e.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>`);
            }
        }

        const tbody = document.querySelector('#ordersTable tbody');
        const pagination = document.getElementById('pagination');
        const tableSummary = document.getElementById('tableSummary');

        function applyFilters() {
            let data = rawData.slice();
            if (state.search) {
                const q = state.search.toLowerCase();
                data = data.filter(r => `${r.id} ${r.order_number} ${r.patient} ${r.platform} ${r.status} ${r.buyer} ${r.seller} ${r.order_type}`.toLowerCase().includes(q));
            }
            if (state.status) data = data.filter(r => r.status === state.status);
            return data;
        }

        function renderTable() {
            const data = applyFilters();
            const start = (state.page - 1) * state.pageSize;
            const pageData = data.slice(start, start + state.pageSize);
            tbody.innerHTML = pageData.map(r => {
                const platformBadge = r.platform === 'DS Core' 
                    ? '<span class="badge bg-info">DS Core</span>' 
                    : (r.platform === 'Meditlink' ? '<span class="badge bg-primary">Medit</span>' : `<span class="badge bg-secondary">${r.platform}</span>`);
                const statusBadge = getStatusBadge(r.status);
                return `
                <tr>
                    <td><code>${r.order_number || r.id}</code></td>
                    <td>${r.patient}</td>
                    <td>${platformBadge}</td>
                    <td><small>${r.order_type}</small></td>
                    <td>${r.date}</td>
                    <td>${r.due_date}</td>
                    <td>${statusBadge}</td>
                    <td><small>${r.buyer}</small></td>
                    <td><small>${r.seller}</small></td>
                    <td>
                        <button class="btn btn-sm btn-primary view-order" data-id="${r.id}">
                            <i class="bi bi-eye"></i>
                        </button>
                    </td>
                </tr>
            `}).join('');
            renderPagination(data.length);
            tableSummary.textContent = `Showing ${pageData.length ? start + 1 : 0}–${start + pageData.length} of ${data.length}`;
        }

        function getStatusBadge(status) {
            const statusColors = {
                'REQUESTED': 'warning',
                'DRAFT': 'secondary',
                'ACCEPTED': 'info',
                'IN_PROGRESS': 'primary',
                'COMPLETED': 'success',
                'SHIPPED': 'success',
                'CANCELLED': 'danger',
                'REJECTED': 'danger'
            };
            const color = statusColors[status?.toUpperCase()] || 'secondary';
            return `<span class="badge bg-${color}">${status || '—'}</span>`;
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
        document.getElementById('status').addEventListener('change', (e) => { state.status = e.target.value; state.page = 1; renderTable(); });
        document.getElementById('resetFilters').addEventListener('click', () => {
            state.search = ''; state.status = ''; state.page = 1;
            document.getElementById('ordersFilter').reset();
            renderTable();
        });

        // Tab switching
        document.querySelectorAll('#ordersTabs button[data-source]').forEach(tab => {
            tab.addEventListener('click', function() {
                // Update active tab
                document.querySelectorAll('#ordersTabs button').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                // Fetch orders for selected source
                state.source = this.dataset.source;
                state.page = 1;
                fetchOrders(state.source);
            });
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
            const isDsCore = row.platform === 'DS Core';

            // Make safe helpers
            const buyer = d.buyer || {};
            const seller = d.seller || {};
            const kase  = d.case || {};
            const cred  = d.credential || {};
            const patient = d.patient || {};
            const practice = d.practice || {};
            const lab = d.lab || {};

            // Build content based on platform
            let platformSpecificContent = '';
            
            if (isDsCore) {
                platformSpecificContent = `
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header"><i class="bi bi-person"></i> Patient</div>
                            <div class="card-body">
                                <dl class="row mb-0">
                                    <dt class="col-5">Name</dt><dd class="col-7">${patient.name || row.patient || '—'}</dd>
                                    <dt class="col-5">ID</dt><dd class="col-7">${patient.id || '—'}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header"><i class="bi bi-building"></i> Practice</div>
                            <div class="card-body">
                                <dl class="row mb-0">
                                    <dt class="col-5">Name</dt><dd class="col-7">${practice.name || row.buyer || '—'}</dd>
                                    <dt class="col-5">ID</dt><dd class="col-7"><small>${practice.id || '—'}</small></dd>
                                </dl>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header"><i class="bi bi-gear"></i> Lab</div>
                            <div class="card-body">
                                <dl class="row mb-0">
                                    <dt class="col-5">Name</dt><dd class="col-7">${lab.name || row.seller || '—'}</dd>
                                    <dt class="col-5">ID</dt><dd class="col-7"><small>${lab.id || '—'}</small></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                // Medit Link format
                platformSpecificContent = `
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header">Buyer Group</div>
                            <div class="card-body">
                                <dl class="row mb-0">
                                    <dt class="col-5">UUID</dt><dd class="col-7"><small>${buyer.uuid || '—'}</small></dd>
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
                                    <dt class="col-5">UUID</dt><dd class="col-7"><small>${seller.uuid || '—'}</small></dd>
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
                                    <dt class="col-3">UUID</dt><dd class="col-9"><small>${kase.uuid || '—'}</small></dd>
                                    <dt class="col-3">Name</dt><dd class="col-9">${kase.name || '—'}</dd>
                                    <dt class="col-3">Status</dt><dd class="col-9">${kase.status || '—'}</dd>
                                    <dt class="col-3">Patient</dt><dd class="col-9">${(kase.patient_name || row.patient || '—')} ${kase.patient_code ? '(' + kase.patient_code + ')' : ''}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                `;
            }

            body.innerHTML = `
                <div class="mb-3 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-1">Order #${d.order_number || row.order_number || row.id}</h5>
                        <small class="text-muted">${d.order_id || row.order_id || ''}</small>
                    </div>
                    <div>
                        ${getStatusBadge(row.status || d.status)}
                        ${isDsCore ? '<span class="badge bg-info ms-1">DS Core</span>' : '<span class="badge bg-primary ms-1">Medit</span>'}
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header"><i class="bi bi-clock"></i> Timestamps</div>
                            <div class="card-body">
                                <dl class="row mb-0">
                                    <dt class="col-5">Created</dt><dd class="col-7">${fmt(d.date_created || row.date)}</dd>
                                    <dt class="col-5">Due Date</dt><dd class="col-7">${fmt(d.due_date || d.date_desired_delivery)}</dd>
                                    ${isDsCore ? `<dt class="col-5">Shipped</dt><dd class="col-7">${fmt(d.shipped_date)}</dd>` : `<dt class="col-5">Updated</dt><dd class="col-7">${fmt(d.date_updated)}</dd>`}
                                </dl>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header"><i class="bi bi-info-circle"></i> Order Info</div>
                            <div class="card-body">
                                <dl class="row mb-0">
                                    <dt class="col-5">Type</dt><dd class="col-7">${d.order_type || row.order_type || '—'}</dd>
                                    <dt class="col-5">Source</dt><dd class="col-7">${row.platform}</dd>
                                    <dt class="col-5">Credential</dt><dd class="col-7">${cred.name || cred.api || '—'}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>

                    ${platformSpecificContent}

                    <div class="col-12">
                        <details>
                            <summary class="mb-2"><i class="bi bi-code-slash"></i> Raw JSON</summary>
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
