<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Inicio') — {{ config('app.name', 'SDR v2') }}</title>

    <script>
        (function () {
            try {
                const saved = localStorage.getItem('sdr_theme');
                if (saved === 'dark' || saved === 'light') {
                    document.documentElement.setAttribute('data-theme', saved);
                    document.documentElement.setAttribute('data-bs-theme', saved);
                }
            } catch (_) {}
        })();
    </script>

    {{-- Google Fonts: Outfit --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    {{-- Bootstrap 5.3 CSS + Sistema de diseño TailAdmin --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="{{ asset('css/tailadmin-sdr.css') }}">
    <link rel="shortcut icon" href="{{ asset('assets/purple/images/favicon.png') }}">

    @stack('styles')
</head>
<body x-data="sdrShell()" x-init="init()" :class="{ 'ta-sidebar-collapsed': sidebarCollapsed }">
    {{-- Banner global de conexión offline/online --}}
    <div x-cloak
         x-show="!online"
         x-transition.opacity
         role="alert"
         class="ta-network-banner ta-network-banner--offline">
        <i data-lucide="wifi-off" class="ta-icon-sm"></i>
        <span>Sin conexión a internet. Verifica tu red antes de subir o descargar documentos.</span>
    </div>

    <div x-cloak
         x-show="online && justReconnected"
         x-transition.opacity
         role="status"
         class="ta-network-banner ta-network-banner--online">
        <i data-lucide="wifi" class="ta-icon-sm"></i>
        <span>Conexión restablecida.</span>
    </div>

    {{-- Backdrop móvil --}}
    <div x-cloak
         x-show="mobileSidebarOpen"
         @click="mobileSidebarOpen = false"
         class="ta-sidebar-backdrop d-lg-none"></div>

    <div class="ta-shell">
        @include('partials.sidebar')

        <div class="ta-main-area">
            @include('partials.navbar')

            <main class="ta-content">
                @if (session('status'))
                    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4 d-flex align-items-center gap-2" role="alert">
                        <i data-lucide="check-circle-2" class="ta-icon-sm"></i>
                        <span>{{ session('status') }}</span>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                    </div>
                @endif

                @if (session('error'))
                    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4 d-flex align-items-center gap-2" role="alert">
                        <i data-lucide="alert-circle" class="ta-icon-sm"></i>
                        <span>{{ session('error') }}</span>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                    </div>
                @endif

                {{-- Breadcrumb discreto + Command Header estilo Orchid --}}
                @if (View::hasSection('page-title') || View::hasSection('breadcrumbs') || View::hasSection('page-actions'))
                    @hasSection('breadcrumbs')
                        <nav aria-label="breadcrumb" class="mb-2">
                            <ol class="ta-breadcrumb">
                                @yield('breadcrumbs')
                            </ol>
                        </nav>
                    @endif

                    @if (View::hasSection('page-title') || View::hasSection('page-actions'))
                        <div class="ta-page-header ta-command-header">
                            <div>
                                @hasSection('page-title')
                                    <h1 class="ta-page-title">@yield('page-title')</h1>
                                @endif
                                @hasSection('page-subtitle')
                                    <p class="ta-page-subtitle">@yield('page-subtitle')</p>
                                @endif
                            </div>

                            @hasSection('page-actions')
                                <div class="ta-page-actions">
                                    @yield('page-actions')
                                </div>
                            @endif
                        </div>
                    @endif
                @endif

                @yield('content')
            </main>

            @include('partials.footer')
        </div>
    </div>

    {{-- Bootstrap 5.3 Bundle + Lucide Icons + Alpine.js --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script>
        window.sdrRefreshIcons = function () {
            if (window.lucide) {
                window.lucide.createIcons({ attrs: { 'stroke-width': 1.75 } });
            }
        };

        document.addEventListener('DOMContentLoaded', () => {
            window.sdrRefreshIcons();

            const observer = new MutationObserver((mutations) => {
                for (const m of mutations) {
                    for (const node of m.addedNodes) {
                        if (node.nodeType === 1 && (node.hasAttribute?.('data-lucide') || node.querySelector?.('[data-lucide]'))) {
                            window.requestAnimationFrame(window.sdrRefreshIcons);
                            return;
                        }
                    }
                }
            });
            observer.observe(document.body, { childList: true, subtree: true });
        });

        function sdrNetworkMonitor() {
            return {
                online: typeof navigator !== 'undefined' ? navigator.onLine : true,
                justReconnected: false,
                reconnectTimer: null,
                initNetwork() {
                    window.addEventListener('offline', () => {
                        this.online = false;
                        this.justReconnected = false;
                        if (this.reconnectTimer) {
                            clearTimeout(this.reconnectTimer);
                            this.reconnectTimer = null;
                        }
                    });

                    window.addEventListener('online', () => {
                        this.online = true;
                        this.justReconnected = true;
                        if (this.reconnectTimer) {
                            clearTimeout(this.reconnectTimer);
                        }
                        this.reconnectTimer = setTimeout(() => {
                            this.justReconnected = false;
                        }, 4000);
                    });
                },
            };
        }

        function sdrShell() {
            return {
                ...sdrNetworkMonitor(),
                sidebarCollapsed: false,
                mobileSidebarOpen: false,
                theme: document.documentElement.getAttribute('data-theme') || 'light',

                init() {
                    this.initNetwork();
                    try {
                        this.sidebarCollapsed = localStorage.getItem('sdr_sidebar_collapsed') === '1';
                    } catch (_) {}

                    window.addEventListener('keydown', (event) => {
                        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
                            const input = document.getElementById('ta-global-search');
                            if (input) {
                                event.preventDefault();
                                input.focus();
                            }
                        }
                    });
                },

                toggleSidebar() {
                    if (window.innerWidth < 992) {
                        this.mobileSidebarOpen = !this.mobileSidebarOpen;
                        return;
                    }
                    this.sidebarCollapsed = !this.sidebarCollapsed;
                    try {
                        localStorage.setItem('sdr_sidebar_collapsed', this.sidebarCollapsed ? '1' : '0');
                    } catch (_) {}
                },

                toggleTheme() {
                    this.theme = this.theme === 'dark' ? 'light' : 'dark';
                    document.documentElement.setAttribute('data-theme', this.theme);
                    document.documentElement.setAttribute('data-bs-theme', this.theme);
                    try {
                        localStorage.setItem('sdr_theme', this.theme);
                    } catch (_) {}
                },
            };
        }
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>

    @stack('scripts')
</body>
</html>
