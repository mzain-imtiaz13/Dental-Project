<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Dental Lab</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="/bootstrap-5.3.8-dist/css/bootstrap.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/index.css" />
</head>

<body class="bg-light">


    <div class="container-fluid">
        <div class="row">
            {{-- Sidebar --}}
            <aside class="col-12 col-md-3 col-lg-2 p-3">
                <div class="card shadow-sm side-bar-height">
                    <div class="card-header bg-blue-secondary text-white d-flex align-items-center">
                        <span class="logo-placeholder me-2">
                            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
                                <path fill="#ffffff" d="M12 2c-3.9 0-7 3.1-7 7 0 1.7.6 3.2 1.6 4.4.5.6 1.3.9 2 .7.9-.2 1.6-.9 1.8-1.8.1-.4.5-.7.9-.7s.8.3.9.7c.2.9.9 1.6 1.8 1.8.7.2 1.5-.1 2-.7C18.4 12.2 19 10.7 19 9c0-3.9-3.1-7-7-7zm-2.3 18.8c-.3.7-1.3.7-1.6 0l-1.2-3c-.3-.7.2-1.5 1-1.6.7-.1 1.4.5 1.4 1.2v3.4zm6.2-3l-1.2 3c-.3.7-1.3.7-1.6 0l-.4-1.2c-.2-.6-.3-1.3-.3-2v-.2c0-.7.6-1.3 1.3-1.2.8.1 1.3.9 1 1.6l-.1.2.3-.8c.2-.7.9-1.2 1.6-1 .7.2 1.1 1 .8 1.6z"/>
                            </svg>
                        </span>
                        <strong>Dental Lab</strong>
                    </div>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">
                            <a href="{{ url('/dashboard') }}"
                                class="text-decoration-none {{ Request::is('dashboard') ? 'active-link' : 'inactive-link' }}">
                                <i class="bi bi-speedometer2 me-1"></i> Dashboard
                            </a>
                        </li>
                        <li class="list-group-item">
                            <a href="{{ url('/orders') }}"
                                class="text-decoration-none  {{ Request::is('orders') ? 'active-link' : 'inactive-link' }}">
                                <i class="bi bi-card-checklist me-1"></i> Orders
                            </a>
                        </li>
                        <li class="list-group-item">
                            <a href="{{ url('/users') }}"
                                class="text-decoration-none  {{ Request::is('users') ? 'active-link' : 'inactive-link' }}">
                                <i class="bi bi-people me-1"></i> Users
                            </a>
                        </li>
                       
                        {{-- Add more links as needed --}}
                    </ul>
                </div>
            </aside>

            {{-- Main Content --}}
            <main class="col-12 col-md-9 col-lg-10 py-4">
                @yield('content')
            </main>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="/bootstrap-5.3.8-dist/js/bootstrap.js"></script>
</body>

</html>