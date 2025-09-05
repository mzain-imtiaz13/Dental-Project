@extends('layouts.app')

@section('title', 'Edit API Credentials')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Edit API Credentials</h2>
            <p class="text-muted mb-0">Update credentials for external API integration</p>
        </div>
        <a href="{{ route('api-credentials.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to List
        </a>
    </div>
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white border-bottom">
                    <h3 class="card-title mb-0">API Credentials Form</h3>
                </div>
                <div class="card-body">
                    <form action="{{ route('api-credentials.update', $apiCredential) }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="api_name" class="form-label">API Name <span class="text-danger">*</span></label>
                                    <select class="form-select @error('api_name') is-invalid @enderror" id="api_name" name="api_name" required>
                                        <option value="">Select API</option>
                                        @foreach($apiNames as $key => $name)
                                            <option value="{{ $key }}" {{ old('api_name', $apiCredential->api_name) == $key ? 'selected' : '' }}>
                                                {{ $name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('api_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="client_id" class="form-label">Client ID <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('client_id') is-invalid @enderror" 
                                           id="client_id" name="client_id" value="{{ old('client_id', $apiCredential->client_id) }}" required>
                                    @error('client_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="client_secret" class="form-label">Client Secret <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control @error('client_secret') is-invalid @enderror" 
                                       id="client_secret" name="client_secret" value="{{ old('client_secret', $apiCredential->client_secret) }}" required>
                                <button class="btn btn-outline-secondary" type="button" id="toggleClientSecret">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            @error('client_secret')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="base_url" class="form-label">Base URL</label>
                            <input type="url" class="form-control @error('base_url') is-invalid @enderror" 
                                   id="base_url" name="base_url" value="{{ old('base_url', $apiCredential->base_url) }}" 
                                   placeholder="https://api.example.com">
                            @error('base_url')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Optional: Base URL for the API endpoint</div>
                        </div>

                        <div class="mb-3">
                            <label for="additional_config" class="form-label">Additional Configuration</label>
                            <textarea class="form-control @error('additional_config') is-invalid @enderror" 
                                      id="additional_config" name="additional_config" rows="4" 
                                      placeholder='{"key": "value"}'>{{ old('additional_config', $apiCredential->additional_config ? json_encode($apiCredential->additional_config, JSON_PRETTY_PRINT) : '') }}</textarea>
                            @error('additional_config')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Optional: JSON configuration for API-specific settings</div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                       {{ old('is_active', $apiCredential->is_active) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_active">
                                    Active
                                </label>
                            </div>
                            <div class="form-text">Check to make these credentials active</div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                            <a href="{{ route('api-credentials.index') }}" class="btn btn-outline-secondary me-md-2">
                                <i class="bi bi-x-circle me-1"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-1"></i> Update Credentials
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggleButton = document.getElementById('toggleClientSecret');
    const clientSecretInput = document.getElementById('client_secret');
    const icon = toggleButton.querySelector('i');
    
    toggleButton.addEventListener('click', function() {
        if (clientSecretInput.type === 'password') {
            clientSecretInput.type = 'text';
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        } else {
            clientSecretInput.type = 'password';
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    });
});
</script>
@endsection
