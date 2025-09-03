@extends('layouts.guest')

@section('content')


<div class="vh-100 vw-100 d-flex align-items-center justify-content-center loginBackgroundImage">

    <div class="mx-3 container bg-white bg-opacity-75 p-5 rounded shadow" style="max-width: 500px;">
        <form action="{{ route('login.submit') }}" method="POST" id="loginForm">
            @csrf

            

            <div class="mb-3">
                <label for="emailInput" class="form-label">Email address</label>
                <input type="email" class="form-control @error('email') is-invalid @enderror" id="emailInput" name="email" placeholder="name@example.com" value="{{ old('email') }}" required>
                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <label for="passwordInput" class="form-label">Password</label>
            <input type="password" id="passwordInput" name="password" class="form-control @error('password') is-invalid @enderror" aria-describedby="passwordHelpBlock" required>
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror

            <div class="mt-4">
                <button type="submit" class="btn bg-blue-primary text-white mb-3 w-100" id="loginButton" disabled>Login</button>
            </div>
        </form>
    </div>

</div>

<script>
    const emailInput = document.getElementById('emailInput');
    const passwordInput = document.getElementById('passwordInput');
    const loginButton = document.getElementById('loginButton');
    const loginForm = document.getElementById('loginForm');

    function toggleButton() {
        if (emailInput.value.trim() !== '' && passwordInput.value.trim() !== '') {
            loginButton.disabled = false;
        } else {
            loginButton.disabled = true;
        }
    }

    emailInput.addEventListener('input', toggleButton);
    passwordInput.addEventListener('input', toggleButton);

    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        // clear previous errors
        document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        const existingAlerts = document.querySelectorAll('.alert.alert-danger');
        existingAlerts.forEach(a => a.remove());

        loginButton.disabled = true;
        const formData = new FormData(loginForm);
        const payload = new URLSearchParams(formData);

        try {
            const res = await fetch(loginForm.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('input[name=_token]').value
                },
                body: payload
            });

            if (res.ok) {
                const data = await res.json();
                window.location.href = data.redirect || '{{ url('/dashboard') }}';
                return;
            }

            if (res.status === 422) {
                const data = await res.json();
                // field errors only
                if (data.errors) {
                    if (data.errors.email) {
                        emailInput.classList.add('is-invalid');
                        let fb = emailInput.nextElementSibling;
                        if (!fb || !fb.classList.contains('invalid-feedback')) {
                            fb = document.createElement('div');
                            fb.className = 'invalid-feedback';
                            emailInput.after(fb);
                        }
                        fb.textContent = data.errors.email[0];
                    }
                    if (data.errors.password) {
                        passwordInput.classList.add('is-invalid');
                        let fp = passwordInput.nextElementSibling;
                        if (!fp || !fp.classList.contains('invalid-feedback')) {
                            fp = document.createElement('div');
                            fp.className = 'invalid-feedback';
                            passwordInput.after(fp);
                        }
                        fp.textContent = data.errors.password[0];
                    }
                }
            } else if (res.status >= 500 || res.status === 419 || res.status === 401) {
                // server or unexpected auth/csrf errors -> top-level alert only
                const container = loginForm.parentElement;
                const errorDiv = document.createElement('div');
                errorDiv.className = 'alert alert-danger';
                errorDiv.role = 'alert';
                try {
                    const data = await res.json();
                    errorDiv.textContent = data.message || 'Something went wrong. Please try again.';
                } catch (_) {
                    errorDiv.textContent = 'Something went wrong. Please try again.';
                }
                container.insertBefore(errorDiv, loginForm);
            }
        } catch (err) {
            const container = loginForm.parentElement;
            const errorDiv = document.createElement('div');
            errorDiv.className = 'alert alert-danger';
            errorDiv.role = 'alert';
            errorDiv.textContent = 'Network error. Please check your connection.';
            container.insertBefore(errorDiv, loginForm);
        } finally {
            toggleButton();
        }
    });
</script>

@endsection
