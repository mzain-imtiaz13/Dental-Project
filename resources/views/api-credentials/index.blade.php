@extends('layouts.app')

@section('title', 'API Credentials Management')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">API Credentials Management</h2>
            <p class="text-muted mb-0">Manage API credentials for external services</p>
        </div>
    </div>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-white border-bottom">
                    <div class="d-flex justify-content-end">
                        <div class="btn-group" role="group">
                            <a href="{{ route('api-credentials.create', ['api' => 'medit_link']) }}" 
                               class="btn btn-primary btn-sm">
                                <i class="bi bi-plus-circle me-1"></i> Medit Link
                            </a>
                            <a href="{{ route('api-credentials.create', ['api' => 'ds_core']) }}" 
                               class="btn btn-success btn-sm">
                                <i class="bi bi-plus-circle me-1"></i> DS Core
                            </a>
                            <a href="{{ route('api-credentials.create', ['api' => '3shape']) }}" 
                               class="btn btn-info btn-sm">
                                <i class="bi bi-plus-circle me-1"></i> 3Shape
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    @if($credentials->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th class="fw-semibold text-white small text-uppercase border-0">API Name</th>
                                        <th class="fw-semibold text-white small text-uppercase border-0">Client ID</th>
                                        <th class="fw-semibold text-white small text-uppercase border-0">Base URL</th>
                                        <th class="fw-semibold text-white small text-uppercase border-0">Status</th>
                                        <th class="fw-semibold text-white small text-uppercase border-0">Token Status</th>
                                        <th class="fw-semibold text-white small text-uppercase border-0">Created</th>
                                        <th class="fw-semibold text-white small text-uppercase text-center border-0">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($credentials as $credential)
                                        <tr class="border-0">
                                            <td class="py-3">
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill">
                                                    <i class="bi bi-key me-1"></i>{{ $credential->api_display_name }}
                                                </span>
                                            </td>
                                            <td class="py-3">
                                                <code class="bg-light px-3 py-2 rounded-2 text-dark fw-medium">{{ Str::limit($credential->client_id, 20) }}</code>
                                            </td>
                                            <td class="py-3">
                                                @if($credential->base_url)
                                                    <a href="{{ $credential->base_url }}" target="_blank" class="text-decoration-none text-primary fw-medium">
                                                        <i class="bi bi-link-45deg me-1"></i>{{ Str::limit($credential->base_url, 30) }}
                                                    </a>
                                                @else
                                                    <span class="text-muted fw-medium"><i class="bi bi-dash-circle me-1"></i>Not set</span>
                                                @endif
                                            </td>
                                            <td class="py-3">
                                                @if($credential->is_active)
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-2">
                                                        <i class="bi bi-check-circle-fill me-1"></i>Active
                                                    </span>
                                                @else
                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-3 py-2">
                                                        <i class="bi bi-pause-circle-fill me-1"></i>Inactive
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="py-3">
                                                @if($credential->access_token)
                                                    @if($credential->isTokenExpired())
                                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-3 py-2">
                                                            <i class="bi bi-exclamation-triangle-fill me-1"></i>Expired
                                                        </span>
                                                    @else
                                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-2">
                                                            <i class="bi bi-check-circle-fill me-1"></i>Valid
                                                        </span>
                                                    @endif
                                                @else
                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-3 py-2">
                                                        <i class="bi bi-dash-circle-fill me-1"></i>No Token
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="py-3">
                                                <span class="text-muted fw-medium">{{ $credential->created_at->format('M d, Y') }}</span>
                                            </td>
                                            <td class="text-center py-3">
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="{{ route('api-credentials.show', $credential) }}" 
                                                       class="btn btn-info btn-sm rounded-2" 
                                                       title="View Details"
                                                       data-bs-toggle="tooltip">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="{{ route('api-credentials.edit', $credential) }}" 
                                                       class="btn btn-primary btn-sm rounded-2" 
                                                       title="Edit Credentials"
                                                       data-bs-toggle="tooltip">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                                                            <button type="button" 
                                                class="btn btn-success btn-sm rounded-2 test-api-btn" 
                                                title="Test API Connection"
                                                data-bs-toggle="tooltip"
                                                data-credential-id="{{ $credential->id }}">
                                            <i class="bi bi-wifi"></i>
                                        </button>
                                        
                                        @if($credential->api_name === 'medit_link')
                                            @if($credential->access_token)
                                                <button type="button" 
                                                        class="btn btn-info btn-sm rounded-2 fetch-data-btn" 
                                                        title="Fetch Data"
                                                        data-bs-toggle="tooltip"
                                                        data-credential-id="{{ $credential->id }}">
                                                    <i class="bi bi-download"></i>
                                                </button>
                                                <button type="button" 
                                                        class="btn btn-warning btn-sm rounded-2 refresh-token-btn" 
                                                        title="Refresh Token"
                                                        data-bs-toggle="tooltip"
                                                        data-credential-id="{{ $credential->id }}">
                                                    <i class="bi bi-arrow-clockwise"></i>
                                                </button>
                                            @else
                                                <a href="{{ route('oauth.authorize', ['api' => $credential->api_name]) }}" 
                                                   class="btn btn-primary btn-sm rounded-2" 
                                                   title="Authorize API"
                                                   data-bs-toggle="tooltip">
                                                    <i class="bi bi-key"></i>
                                                </a>
                                            @endif
                                        @endif
                                                    <form action="{{ route('api-credentials.toggle', $credential) }}" method="POST" class="d-inline">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit" 
                                                                class="btn btn-{{ $credential->is_active ? 'warning' : 'success' }} btn-sm rounded-2" 
                                                                title="{{ $credential->is_active ? 'Deactivate' : 'Activate' }}"
                                                                data-bs-toggle="tooltip">
                                                            <i class="bi bi-{{ $credential->is_active ? 'pause' : 'play' }}"></i>
                                                        </button>
                                                    </form>
                                                    <form action="{{ route('api-credentials.destroy', $credential) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete these credentials?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" 
                                                                class="btn btn-danger btn-sm rounded-2" 
                                                                title="Delete Credentials"
                                                                data-bs-toggle="tooltip">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-5">
                            <div class="mb-4">
                                <i class="bi bi-key-fill text-muted" style="font-size: 4rem;"></i>
                            </div>
                            <h4 class="text-muted mb-2">No API Credentials Found</h4>
                            <p class="text-muted mb-4">Get started by adding credentials for your APIs to integrate with external services.</p>
                            <div class="btn-group" role="group">
                                <a href="{{ route('api-credentials.create', ['api' => 'medit_link']) }}" 
                                   class="btn btn-primary">
                                    <i class="bi bi-plus-circle me-1"></i> Add Medit Link
                                </a>
                                <a href="{{ route('api-credentials.create', ['api' => 'ds_core']) }}" 
                                   class="btn btn-success">
                                    <i class="bi bi-plus-circle me-1"></i> Add DS Core
                                </a>
                                <a href="{{ route('api-credentials.create', ['api' => '3shape']) }}" 
                                   class="btn btn-info">
                                    <i class="bi bi-plus-circle me-1"></i> Add 3Shape
                                </a>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Handle API test buttons
    document.querySelectorAll('.test-api-btn').forEach(function(button) {
        button.addEventListener('click', function() {
            const credentialId = this.getAttribute('data-credential-id');
            testApiConnection(credentialId, this);
        });
    });

    // Handle fetch data buttons
    document.querySelectorAll('.fetch-data-btn').forEach(function(button) {
        button.addEventListener('click', function() {
            const credentialId = this.getAttribute('data-credential-id');
            fetchApiData(credentialId, this);
        });
    });

    // Handle refresh token buttons
    document.querySelectorAll('.refresh-token-btn').forEach(function(button) {
        button.addEventListener('click', function() {
            const credentialId = this.getAttribute('data-credential-id');
            refreshToken(credentialId, this);
        });
    });

    function testApiConnection(credentialId, button) {
        // Disable button and show loading state
        const originalContent = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<i class="bi bi-hourglass-split"></i>';
        button.classList.remove('btn-success');
        button.classList.add('btn-secondary');

        // Make API test request
        fetch(`/api-credentials/${credentialId}/test`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            // Show results in a modal or alert
            showTestResults(data, credentialId);
        })
        .catch(error => {
            console.error('Error testing API:', error);
            showTestResults({
                success: false,
                message: 'Network error occurred while testing API',
                results: {}
            }, credentialId);
        })
        .finally(() => {
            // Restore button state
            button.disabled = false;
            button.innerHTML = originalContent;
            button.classList.remove('btn-secondary');
            button.classList.add('btn-success');
        });
    }

    function showTestResults(data, credentialId) {
        // Create modal for test results
        const modalHtml = `
            <div class="modal fade" id="testResultsModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="bi bi-${data.success ? 'check-circle text-success' : 'x-circle text-danger'}"></i>
                                API Test Results
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-${data.success ? 'success' : 'danger'}">
                                <strong>${data.message}</strong>
                            </div>
                            
                            ${data.results ? `
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Credential Format</h6>
                                        <div class="card">
                                            <div class="card-body">
                                                <span class="badge bg-${data.results.credential_format && data.results.credential_format.valid ? 'success' : 'danger'}">
                                                    ${data.results.credential_format && data.results.credential_format.valid ? 'Valid' : 'Invalid'}
                                                </span>
                                                ${data.results.credential_format && data.results.credential_format.errors ? `
                                                    <ul class="mt-2 mb-0">
                                                        ${data.results.credential_format.errors.map(error => `<li class="text-danger small">${error}</li>`).join('')}
                                                    </ul>
                                                ` : ''}
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <h6>OAuth Endpoint</h6>
                                        <div class="card">
                                            <div class="card-body">
                                                <span class="badge bg-${data.results.oauth_endpoint && data.results.oauth_endpoint.accessible ? 'success' : 'danger'}">
                                                    ${data.results.oauth_endpoint && data.results.oauth_endpoint.accessible ? 'Accessible' : 'Not Accessible'}
                                                </span>
                                                ${data.results.oauth_endpoint && data.results.oauth_endpoint.status ? `
                                                    <div class="mt-2">
                                                        <small class="text-muted">Status: ${data.results.oauth_endpoint.status}</small>
                                                    </div>
                                                ` : ''}
                                                ${data.results.oauth_endpoint && data.results.oauth_endpoint.note ? `
                                                    <div class="mt-2">
                                                        <small class="text-info">${data.results.oauth_endpoint.note}</small>
                                                    </div>
                                                ` : ''}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                ${data.results.api_connectivity ? `
                                    <div class="mt-3">
                                        <h6>API Connectivity</h6>
                                        <div class="card">
                                            <div class="card-body">
                                                <span class="badge bg-${data.results.api_connectivity && data.results.api_connectivity.successful ? 'success' : 'danger'}">
                                                    ${data.results.api_connectivity && data.results.api_connectivity.successful ? 'Connected' : 'Failed'}
                                                </span>
                                                ${data.results.api_connectivity && data.results.api_connectivity.status ? `
                                                    <div class="mt-2">
                                                        <small class="text-muted">Status: ${data.results.api_connectivity.status}</small>
                                                    </div>
                                                ` : ''}
                                                ${data.results.api_connectivity && data.results.api_connectivity.response ? `
                                                    <div class="mt-2">
                                                        <pre class="small bg-light p-2 rounded">${JSON.stringify(data.results.api_connectivity.response, null, 2)}</pre>
                                                    </div>
                                                ` : ''}
                                            </div>
                                        </div>
                                    </div>
                                ` : ''}
                            ` : ''}
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Remove existing modal if any
        const existingModal = document.getElementById('testResultsModal');
        if (existingModal) {
            existingModal.remove();
        }

        // Add modal to page
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('testResultsModal'));
        modal.show();
    }

    function fetchApiData(credentialId, button) {
        // Disable button and show loading state
        const originalContent = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<i class="bi bi-hourglass-split"></i>';

        fetch(`/oauth/${credentialId}/fetch-data?type=orders`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            showDataResults(data, credentialId, 'orders');
        })
        .catch(error => {
            console.error('Error fetching data:', error);
            showDataResults({ success: false, message: 'Network error occurred while fetching data' }, credentialId, 'orders');
        })
        .finally(() => {
            // Restore button state
            button.disabled = false;
            button.innerHTML = originalContent;
        });
    }

    function refreshToken(credentialId, button) {
        // Disable button and show loading state
        const originalContent = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<i class="bi bi-hourglass-split"></i>';

        fetch(`/oauth/${credentialId}/refresh`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Reload page to show updated token status
                location.reload();
            } else {
                alert('Token refresh failed: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error refreshing token:', error);
            alert('Network error occurred while refreshing token');
        })
        .finally(() => {
            // Restore button state
            button.disabled = false;
            button.innerHTML = originalContent;
        });
    }

    function showDataResults(data, credentialId, type) {
        // Create modal for data results
        const modalHtml = `
            <div class="modal fade" id="dataResultsModal" tabindex="-1">
                <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="bi bi-${data.success ? 'check-circle text-success' : 'x-circle text-danger'}"></i>
                                API Data Results - ${type.charAt(0).toUpperCase() + type.slice(1)}
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-${data.success ? 'success' : 'danger'}">
                                <strong>${data.message}</strong>
                            </div>
                            
                            ${data.success && data.data ? `
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>Field</th>
                                                <th>Value</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${Object.entries(data.data).map(([key, value]) => `
                                                <tr>
                                                    <td><strong>${key}</strong></td>
                                                    <td>
                                                        ${typeof value === 'object' ? 
                                                            `<pre class="mb-0 small">${JSON.stringify(value, null, 2)}</pre>` : 
                                                            String(value)
                                                        }
                                                    </td>
                                                </tr>
                                            `).join('')}
                                        </tbody>
                                    </table>
                                </div>
                            ` : ''}
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Remove existing modal if any
        const existingModal = document.getElementById('dataResultsModal');
        if (existingModal) {
            existingModal.remove();
        }

        // Add modal to page
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('dataResultsModal'));
        modal.show();
    }
});
</script>
@endsection
