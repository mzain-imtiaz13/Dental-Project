@extends('layouts.app')

@section('content')
    <div class="container">
        <h2 class="mb-4">Dashboard</h2>

        <!-- Stat Cards -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-md-6 col-lg-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="text-muted">Total Orders</div>
                                <div class="fs-3 fw-bold">1,248</div>
                            </div>
                            <span class="badge bg-blue-secondary">All</span>
                        </div>
                        <div class="mt-2 small text-muted">Last 30 days: 312</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="text-muted">Patients</div>
                                <div class="fs-3 fw-bold">789</div>
                            </div>
                            <span class="badge bg-blue-secondary">Active</span>
                        </div>
                        <div class="mt-2 small text-muted">New this month: 42</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted">3Shape Cases</div>
                        <div class="fs-3 fw-bold">420</div>
                        <div class="mt-2 small text-muted">Awaiting review: 18</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted">DScore Scans</div>
                        <div class="fs-3 fw-bold">265</div>
                        <div class="mt-2 small text-muted">Flagged: 6</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted">Meditlink Cases</div>
                        <div class="fs-3 fw-bold">198</div>
                        <div class="mt-2 small text-muted">With attachments: 54</div>
                    </div>
                </div>
            </div>
        </div>

        

    </div>
@endsection
