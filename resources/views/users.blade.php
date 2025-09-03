@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Users</h2>
            <button class="btn bg-blue-primary text-white" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="bi bi-person-plus me-1"></i> Add User
            </button>
        </div>

        <div class="card datatable mb-3">
            <div class="card-body">
                <form id="usersFilter" class="row g-3">
                    <div class="col-12 col-md-6">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" id="search" class="form-control" placeholder="Search by name, email, role...">
                    </div>
                </form>
            </div>
        </div>

        <div class="card datatable">
            <div class="table-responsive table-sticky">
                <table class="table table-striped table-bordered mb-0 compact align-middle datatable-table" id="usersTable">
                    <thead class="table-primary datatable-head">
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Created</th>
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

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @include('partials.add_user_form')
                </div>
            </div>
        </div>
    </div>

    <script>
        const rawUsers = @json($users ?? []);

        const state = { page: 1, pageSize: 5, search: '' };
        const tbody = document.querySelector('#usersTable tbody');
        const pagination = document.getElementById('pagination');
        const tableSummary = document.getElementById('tableSummary');

        function applyFilters() {
            let data = rawUsers.slice();
            if (state.search) {
                const q = state.search.toLowerCase();
                data = data.filter(r => `${r.name} ${r.email} ${r.role}`.toLowerCase().includes(q));
            }
            return data;
        }

        function renderTable() {
            const data = applyFilters();
            const start = (state.page - 1) * state.pageSize;
            const pageData = data.slice(start, start + state.pageSize);
            tbody.innerHTML = pageData.map(r => `
                <tr>
                    <td>${r.name}</td>
                    <td>${r.email}</td>
                    <td>${r.role}</td>
                    <td>${r.created}</td>
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

        renderTable();
    </script>
@endsection


