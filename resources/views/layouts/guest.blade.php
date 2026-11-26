<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Acceso') — {{ config('app.name', 'SDR v2') }}</title>

    <script>
        (function () {
            try {
                const saved = localStorage.getItem('sdr_theme');
                if (saved === 'dark' || saved === 'light') {
                    document.documentElement.setAttribute('data-theme', saved);
                }
            } catch (_) {}
        })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="{{ asset('css/tailadmin-sdr.css') }}">
    <link rel="shortcut icon" href="{{ asset('assets/purple/images/favicon.png') }}">
</head>
<body>
    <main class="ta-auth-shell row g-0">
        <div class="col-lg-6 d-flex flex-column justify-content-center align-items-center p-4 p-md-5">
            <div class="ta-auth-card">
                <div class="mb-4">
                    <div class="d-flex justify-content-center align-items-center text-center mb-4">
                        <img src="{{ asset('images/brand/logo-version-azul.png') }}"
                             alt="SDR Servicios"
                             style="width: 350px; max-width: 100%; height: auto; object-fit: contain; display: block;">
                    </div>
                    <h1 class="ta-page-title mb-1" style="font-size: 1.5rem;">@yield('auth-title')</h1>
                    <p class="ta-page-subtitle mb-0">@yield('auth-subtitle')</p>
                </div>

                @if (session('status'))
                    <div class="alert alert-success border-0 small py-2 mb-3 d-flex align-items-center gap-2" role="alert">
                        <i data-lucide="check-circle-2" class="ta-icon-sm"></i>
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger border-0 small py-2 mb-3" role="alert">
                        <ul class="mb-0 ps-3">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </div>
        </div>

        <div class="col-lg-6 d-none d-lg-flex flex-column justify-content-center align-items-center p-5 ta-auth-panel text-center"
             style="background-image: linear-gradient(135deg, rgba(16, 24, 40, 0.85), rgba(23, 31, 46, 0.78)), url('{{ asset('images/brand/background-login-derecho.webp') }}'); background-size: cover; background-position: center; background-repeat: no-repeat;">
            <div style="max-width: 420px;">
                <div class="mb-4">
                    <img src="{{ asset('images/brand/logo-sdr-blanco.png') }}"
                         alt="SDR"
                         style="max-height: 76px; width: auto; object-fit: contain;">
                </div>
                <h2 class="text-white fw-semibold mb-3" style="font-size: 1.75rem; letter-spacing: -0.02em;">
                    Sistema de Gestión Documental
                </h2>
                <p class="mb-0" style="color: #D0D5DD; font-size: 0.95rem; line-height: 1.6;">
                    Repositorio documental corporativo SDR.
                </p>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (window.lucide) {
                window.lucide.createIcons({ attrs: { 'stroke-width': 1.75 } });
            }
        });
    </script>
</body>
</html>
