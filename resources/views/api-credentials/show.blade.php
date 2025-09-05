@extends('layouts.app')

@section('title', 'View API Credentials')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">View API Credentials</h2>
            <p class="text-muted mb-0">View detailed information about API credentials</p>
        </div>
        <a href="{{ route('api-credentials.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to List
        </a>
    </div>
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white border-bottom">
                    <h3 class="card-title mb-0">API Credentials Details</h3>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold text-muted small text-uppercase">API Name</label>
                                <div>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill">
                                        <i class="bi bi-key me-1"></i>{{ $apiCredential->api_display_name }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold text-muted small text-uppercase">Status</label>
                                <div>
                                    @if($apiCredential->is_active)
                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-2">
                                            <i class="bi bi-check-circle-fill me-1"></i>Active
                                        </span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-3 py-2">
                                            <i class="bi bi-pause-circle-fill me-1"></i>Inactive
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold text-muted small text-uppercase">Client ID</label>
                                <div class="p-3 bg-light rounded-2">
                                    <code class="text-dark fw-medium">{{ $apiCredential->client_id }}</code>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold text-muted small text-uppercase">Token Status</label>
                                <div>
                                    @if($apiCredential->access_token)
                                        @if($apiCredential->isTokenExpired())
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
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-muted small text-uppercase">Client Secret</label>
                        <div class="input-group">
                            <input type="password" class="form-control form-control-lg" id="client_secret_display" 
                                   value="{{ $apiCredential->client_secret }}" readonly>
                            <button class="btn btn-outline-secondary" type="button" id="toggleClientSecret">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    @if($apiCredential->base_url)
                        <div class="mb-4">
                            <label class="form-label fw-bold text-muted small text-uppercase">Base URL</label>
                            <div class="p-3 bg-light rounded-2">
                                <a href="{{ $apiCredential->base_url }}" target="_blank" class="text-decoration-none text-primary fw-medium">
                                    <i class="bi bi-link-45deg me-1"></i>{{ $apiCredential->base_url }}
                                </a>
                            </div>
                        </div>
                    @endif

                    @if($apiCredential->access_token)
                        <div class="mb-4">
                            <label class="form-label fw-bold text-muted small text-uppercase">Access Token</label>
                            <div class="input-group">
                                <input type="password" class="form-control form-control-lg" id="access_token_display" 
                                       value="{{ $apiCredential->access_token }}" readonly>
                                <button class="btn btn-outline-secondary" type="button" id="toggleAccessToken">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                    @endif

                    @if($apiCredential->refresh_token)
                        <div class="mb-4">
                            <label class="form-label fw-bold text-muted small text-uppercase">Refresh Token</label>
                            <div class="input-group">
                                <input type="password" class="form-control form-control-lg" id="refresh_token_display" 
                                       value="{{ $apiCredential->refresh_token }}" readonly>
                                <button class="btn btn-outline-secondary" type="button" id="toggleRefreshToken">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                    @endif

                    @if($apiCredential->token_expiry)
                        <div class="mb-4">
                            <label class="form-label fw-bold text-muted small text-uppercase">Token Expiry</label>
                            <div class="p-3 bg-light rounded-2">
                                <span class="fw-medium">{{ $apiCredential->token_expiry->format('M d, Y H:i:s') }}</span>
                                @if($apiCredential->isTokenExpired())
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill ms-2">
                                        <i class="bi bi-exclamation-triangle-fill me-1"></i>Expired
                                    </span>
                                @else
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill ms-2">
                                        <i class="bi bi-check-circle-fill me-1"></i>Valid
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endif

                    @if($apiCredential->additional_config)
                        <div class="mb-4">
                            <label class="form-label fw-bold text-muted small text-uppercase">Additional Configuration</label>
                            <div class="p-3 bg-light rounded-2">
                                <pre class="mb-0"><code class="text-dark">{{ json_encode($apiCredential->additional_config, JSON_PRETTY_PRINT) }}</code></pre>
                            </div>
                        </div>
                    @endif

                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold text-muted small text-uppercase">Created At</label>
                                <div class="p-3 bg-light rounded-2">
                                    <span class="text-muted fw-medium">{{ $apiCredential->created_at->format('M d, Y H:i:s') }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold text-muted small text-uppercase">Updated At</label>
                                <div class="p-3 bg-light rounded-2">
                                    <span class="text-muted fw-medium">{{ $apiCredential->updated_at->format('M d, Y H:i:s') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4 pt-4 border-top">
                        <a href="{{ route('api-credentials.index') }}" class="btn btn-outline-secondary me-md-2">
                            <i class="bi bi-arrow-left me-1"></i> Back to List
                        </a>
                        <a href="{{ route('api-credentials.edit', $apiCredential) }}" class="btn btn-primary">
                            <i class="bi bi-pencil me-1"></i> Edit Credentials
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle client secret visibility
    const toggleClientSecret = document.getElementById('toggleClientSecret');
    const clientSecretInput = document.getElementById('client_secret_display');
    const clientSecretIcon = toggleClientSecret.querySelector('i');
    
    toggleClientSecret.addEventListener('click', function() {
        if (clientSecretInput.type === 'password') {
            clientSecretInput.type = 'text';
            clientSecretIcon.classList.remove('bi-eye');
            clientSecretIcon.classList.add('bi-eye-slash');
        } else {
            clientSecretInput.type = 'password';
            clientSecretIcon.classList.remove('bi-eye-slash');
            clientSecretIcon.classList.add('bi-eye');
        }
    });

    // Toggle access token visibility
    const toggleAccessToken = document.getElementById('toggleAccessToken');
    if (toggleAccessToken) {
        const accessTokenInput = document.getElementById('access_token_display');
        const accessTokenIcon = toggleAccessToken.querySelector('i');
        
        toggleAccessToken.addEventListener('click', function() {
            if (accessTokenInput.type === 'password') {
                accessTokenInput.type = 'text';
                accessTokenIcon.classList.remove('bi-eye');
                accessTokenIcon.classList.add('bi-eye-slash');
            } else {
                accessTokenInput.type = 'password';
                accessTokenIcon.classList.remove('bi-eye-slash');
                accessTokenIcon.classList.add('bi-eye');
            }
        });
    }

    // Toggle refresh token visibility
    const toggleRefreshToken = document.getElementById('toggleRefreshToken');
    if (toggleRefreshToken) {
        const refreshTokenInput = document.getElementById('refresh_token_display');
        const refreshTokenIcon = toggleRefreshToken.querySelector('i');
        
        toggleRefreshToken.addEventListener('click', function() {
            if (refreshTokenInput.type === 'password') {
                refreshTokenInput.type = 'text';
                refreshTokenIcon.classList.remove('bi-eye');
                refreshTokenIcon.classList.add('bi-eye-slash');
            } else {
                refreshTokenInput.type = 'password';
                refreshTokenIcon.classList.remove('bi-eye-slash');
                refreshTokenIcon.classList.add('bi-eye');
            }
        });
    }
});
</script>
@endsection
