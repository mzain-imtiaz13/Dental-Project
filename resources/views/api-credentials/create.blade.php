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
                                    <option value="{{ $value }}" {{ ($apiName ?? '') == $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="client_id" class="form-label">Client ID</label>
                            <input type="text" class="form-control" id="client_id" name="client_id" required placeholder="e.g. Dentin.Staging">
                        </div>

                        {{-- Client Secret (hidden for 3Shape PKCE) --}}
                        <div class="mb-3" id="client_secret_wrap">
                            <label for="client_secret" class="form-label" id="client_secret_label">Client Secret</label>
                            <input type="password" class="form-control" id="client_secret" name="client_secret" placeholder="Required for Medit/DS Core">
                        </div>

                        {{-- Base URL becomes "Identity Base" for 3Shape --}}
                        <div class="mb-3">
                            <label for="base_url" class="form-label" id="base_url_label">Base URL (Optional)</label>
                            <input type="url" class="form-control" id="base_url" name="base_url"
                                   placeholder="Medit Auth Base e.g. https://stage-openapi-auth.meditlink.com">
                            <small class="text-muted" id="base_url_help">
                                For Medit: provide the AUTH base (optional). For 3Shape: this is the Identity Base (optional).
                            </small>
                        </div>

                        {{-- 3Shape: Resource Base --}}
                        <div class="mb-3 d-none" id="resource_base_wrap">
                            <label for="resource_base" class="form-label">3Shape Resource Base (Optional)</label>
                            <input type="url" class="form-control" id="resource_base" name="resource_base"
                                   placeholder="e.g. https://staging-eumetadata.3shapecommunicate.com">
                            <small class="text-muted">If omitted, default from config('three_shape.resource_base') is used.</small>
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
                            <button type="submit" class="btn btn-primary" id="submitBtn">Save & Connect</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function(){
    const apiSel = document.getElementById('api_name');
    const csWrap = document.getElementById('client_secret_wrap');
    const csInput= document.getElementById('client_secret');

    const baseLabel = document.getElementById('base_url_label');
    const baseHelp  = document.getElementById('base_url_help');

    const resourceWrap = document.getElementById('resource_base_wrap');

    function applyApiUi() {
        const api = apiSel.value;
        if (api === '3shape') {
            // PKCE → no client secret
            csWrap.classList.add('d-none');
            csInput.removeAttribute('required');

            // Identity base + resource base
            baseLabel.textContent = '3Shape Identity Base (Optional)';
            baseHelp.textContent  = 'e.g. https://staging-identity.3shape.com (if omitted, config is used)';
            resourceWrap.classList.remove('d-none');
        } else {
            csWrap.classList.remove('d-none');
            csInput.setAttribute('required','required');

            baseLabel.textContent = 'Base URL (Optional)';
            baseHelp.textContent  = 'Medit Auth Base e.g. https://stage-openapi-auth.meditlink.com';
            resourceWrap.classList.add('d-none');
        }
    }
    applyApiUi();
    apiSel.addEventListener('change', applyApiUi);

    // Smart submit: 3Shape → oauth.3shape.start; else → api-credentials.store
    const form = document.getElementById('credentialsForm');
    form.addEventListener('submit', async function(e){
        e.preventDefault();

        const api = apiSel.value;
        const fd  = new FormData(form);

        if (api === '3shape') {
            const payload = new FormData();
            payload.append('client_id', fd.get('client_id') || '');
            if (fd.get('base_url'))      payload.append('identity_base',  fd.get('base_url'));
            if (fd.get('resource_base')) payload.append('resource_base',  fd.get('resource_base'));
            if (fd.get('is_active'))     payload.append('is_active',      fd.get('is_active'));

            try {
                const res = await fetch('{{ route('oauth.3shape.start') }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: payload
                });
                if (res.redirected) { window.location.href = res.url; return; }
                const loc = res.headers.get('Location');
                if (loc) { window.location.href = loc; return; }
                // fallback: navigate to start (GET)
                window.location.href = '{{ route('oauth.3shape.start') }}';
            } catch (err) {
                alert('Network error starting 3Shape OAuth.');
            }
            return;
        }

        // Default (Medit / DS Core)
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: fd,
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                }
            });
            const result = await response.json();
            if (result.success) {
                window.location.href = result.redirect_url;
            } else {
                alert('Error: ' + (result.message || 'Failed to save credentials'));
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error saving credentials. Please try again.');
        }
    });
})();
</script>
@endpush
@endsection
