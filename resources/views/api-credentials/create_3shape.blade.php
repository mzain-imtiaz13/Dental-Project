@extends('layouts.app')

@section('title', 'Connect 3Shape')

@section('content')
<div class="container">
    <h2 class="mb-3">Connect 3Shape (PKCE OAuth)</h2>
    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('oauth.3shape.start') }}" class="row g-3">
                @csrf
                <div class="col-md-6">
                    <label class="form-label">Client ID</label>
                    <input type="text" name="client_id" class="form-control" required placeholder="Dentin.Staging">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Identity Base (optional)</label>
                    <input type="url" name="identity_base" class="form-control" value="{{ config('three_shape.identity_base') }}">
                    <small class="text-muted">e.g. https://staging-identity.3shape.com</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Resource Base (optional)</label>
                    <input type="url" name="resource_base" class="form-control" value="{{ config('three_shape.resource_base') }}">
                    <small class="text-muted">e.g. https://staging-eumetadata.3shapecommunicate.com</small>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary">
                        <i class="bi bi-key"></i> Continue to 3Shape Authorization
                    </button>
                    <a href="{{ route('api-credentials.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
