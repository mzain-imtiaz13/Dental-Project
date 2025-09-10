@extends('layouts.app')

@section('title', 'Add API Credentials')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Add API Credentials</h2>
            <p class="text-muted mb-0">Configure credentials for external API integration</p>
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
                    @if($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    
                    <form id="credentialsForm" action="{{ route('api-credentials.store') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label for="api_name" class="form-label">API Name</label>
                            <select name="api_name" id="api_name" class="form-control" required>
                                @foreach($apiNames as $value => $label)
                                    <option value="{{ $value }}" {{ $apiName == $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="client_id" class="form-label">Client ID</label>
                            <input type="text" class="form-control" id="client_id" name="client_id" required>
                        </div>

                        <div class="mb-3">
                            <label for="client_secret" class="form-label">Client Secret</label>
                            <input type="password" class="form-control" id="client_secret" name="client_secret" required>
                        </div>

                        <div class="mb-3">
                            <label for="base_url" class="form-label">Base URL (Optional)</label>
                            <input type="url" class="form-control" id="base_url" name="base_url" 
                                   placeholder="https://dev-openapi-auth.meditlink.com">
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input type="hidden" name="is_active" value="0">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                       value="1" checked>
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Save & Connect</button>
                        </div>
                    </form>

                    @push('scripts')
                    <script>
                    document.getElementById('credentialsForm').addEventListener('submit', async function(e) {
                        e.preventDefault();
                        
                        try {
                            const response = await fetch(this.action, {
                                method: 'POST',
                                body: new FormData(this),
                                headers: {
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                    'Accept': 'application/json'
                                }
                            });

                            const result = await response.json();
                            
                            if (result.success) {
                                window.location.href = result.redirect_url;
                            } else {
                                alert('Error: ' + result.message);
                            }
                        } catch (error) {
                            console.error('Error:', error);
                            alert('Error saving credentials. Please try again.');
                        }
                    });
                    </script>
                    @endpush
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
