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

        <!-- API Integration Section -->
        <div class="row g-3 mb-4">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-plug me-2"></i>API Integrations
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">Manage API credentials for external services to fetch orders and patient data.</p>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="card border-primary h-100">
                                    <div class="card-body text-center">
                                        <i class="bi bi-link-45deg fa-2x text-primary mb-3"></i>
                                        <h6 class="card-title">Medit Link</h6>
                                        <p class="card-text small text-muted">Dental case management and patient data</p>
                                        <a href="{{ route('api-credentials.create', ['api' => 'medit_link']) }}" class="btn btn-primary btn-sm">
                                            <i class="bi bi-plus"></i> Add Credentials
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-success h-100">
                                    <div class="card-body text-center">
                                        <i class="bi bi-cpu fa-2x text-success mb-3"></i>
                                        <h6 class="card-title">DS Core</h6>
                                        <p class="card-text small text-muted">Scan analysis and case processing</p>
                                        <a href="{{ route('api-credentials.create', ['api' => 'ds_core']) }}" class="btn btn-success btn-sm">
                                            <i class="bi bi-plus"></i> Add Credentials
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-info h-100">
                                    <div class="card-body text-center">
                                        <i class="bi bi-box fa-2x text-info mb-3"></i>
                                        <h6 class="card-title">3Shape</h6>
                                        <p class="card-text small text-muted">CAD/CAM design and manufacturing</p>
                                        <a href="{{ route('api-credentials.create', ['api' => '3shape']) }}" class="btn btn-info btn-sm">
                                            <i class="bi bi-plus"></i> Add Credentials
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="text-center mt-3">
                            <a href="{{ route('api-credentials.index') }}" class="btn btn-outline-secondary">
                                <i class="bi bi-gear"></i> Manage All API Credentials
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection
