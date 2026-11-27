@php
    $currentUser = auth()->user();
    $userName = $currentUser?->name ?? 'Invitado';
    $shortName = \Illuminate\Support\Str::words($userName, 2, '');
    $userEmail = $currentUser?->email ?? null;
    $userRole = ($currentUser && method_exists($currentUser, 'getRoleNames'))
        ? ($currentUser->getRoleNames()->first() ?? 'Colaborador')
        : ($currentUser ? 'Colaborador' : 'Vista previa');

    $initials = $currentUser ? $currentUser->initials() : 'SD';

    $avatarUrl = ($currentUser && !empty($currentUser->avatar_path) && \Illuminate\Support\Facades\Route::has('users.avatar'))
        ? route('users.avatar', $currentUser).'?v='.($currentUser->updated_at?->timestamp ?? 0)
        : null;

    $homeHref = \Illuminate\Support\Facades\Route::has('home') ? route('home') : url('/');
    $profileHref = \Illuminate\Support\Facades\Route::has('profile.edit') ? route('profile.edit') : '#';
    $searchHref = \Illuminate\Support\Facades\Route::has('documents.search') ? route('documents.search') : '#';
    $unreadCountUrl = ($currentUser && \Illuminate\Support\Facades\Route::has('notifications.unread-count'))
        ? route('notifications.unread-count')
        : '';
    $initialUnreadCount = $currentUser
        ? \App\Models\Notification::query()->where('user_id', $currentUser->id)->whereNull('read_at')->count()
        : 0;
    $initialNotifications = $currentUser
        ? \App\Models\Notification::query()
            ->where('user_id', $currentUser->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'type', 'title', 'body', 'data', 'read_at', 'created_at'])
            ->map(static fn (\App\Models\Notification $n): array => [
                'id' => $n->id,
                'title' => $n->title,
                'body' => $n->body,
                'url' => $n->data['url'] ?? route('notifications.index'),
                'open_url' => \Illuminate\Support\Facades\Route::has('notifications.open')
                    ? route('notifications.open', $n)
                    : ($n->data['url'] ?? route('notifications.index')),
                'is_unread' => $n->read_at === null,
                'time_ago' => $n->created_at?->diffForHumans() ?? '',
            ])
            ->values()
            ->all()
        : [];
@endphp

<header class="ta-header">
    {{-- Izquierda: Botón colapsar sidebar + Búsqueda rápida TailAdmin --}}
    <div class="d-flex align-items-center gap-3 flex-grow-1" style="min-width: 0;">
        <button type="button"
                class="ta-square-btn"
                @click="toggleSidebar()"
                aria-label="Alternar barra lateral"
                title="Alternar menú">
            <i data-lucide="menu" class="ta-icon"></i>
        </button>

        <a href="{{ $homeHref }}" class="d-inline-flex d-lg-none align-items-center gap-2 text-decoration-none">
            <span class="ta-brand-icon" style="width: 34px; height: 34px;">
                <i data-lucide="folder-lock" class="ta-icon-sm"></i>
            </span>
            <span class="ta-brand-name" style="font-size: 18px;">SDR</span>
        </a>

        <form method="GET" action="{{ $searchHref }}" class="ta-search-box d-none d-md-block">
            <i data-lucide="search" class="ta-icon-sm ta-search-box__icon"></i>
            <input id="ta-global-search"
                   type="search"
                   name="q"
                   value="{{ request()->routeIs('documents.search') ? request('q') : '' }}"
                   class="ta-search-box__input"
                   placeholder="Buscar documentos o códigos..."
                   aria-label="Buscar documentos">
            <span class="ta-search-box__kbd">Ctrl K</span>
        </form>
    </div>

    {{-- Derecha: Dark Mode Toggle + Campana + Único Perfil TailAdmin --}}
    <div class="d-flex align-items-center gap-2 gap-sm-3">
        <button type="button"
                class="ta-circle-btn"
                @click="toggleTheme()"
                aria-label="Cambiar tema claro u oscuro"
                title="Cambiar tema">
            <span x-show="theme !== 'dark'"><i data-lucide="moon" class="ta-icon"></i></span>
            <span x-cloak x-show="theme === 'dark'"><i data-lucide="sun" class="ta-icon"></i></span>
        </button>

        {{-- Campana de notificaciones --}}
        <div class="dropdown"
             x-data="sdrNotificationBell(@js($unreadCountUrl), {{ $initialUnreadCount }}, @js($initialNotifications))"
             x-init="init()">
            <button type="button"
                    class="ta-circle-btn"
                    id="notificationDropdown"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                    aria-label="Notificaciones">
                <i data-lucide="bell" class="ta-icon"></i>
                <span x-cloak
                      x-show="unreadCount > 0"
                      class="sdr-notif-badge sdr-notif-badge--bell"
                      x-text="unreadCount > 99 ? '99+' : unreadCount"></span>
            </button>

            <div class="dropdown-menu dropdown-menu-end sdr-notif-dropdown" aria-labelledby="notificationDropdown">
                {{-- Zona 1: Cabecera --}}
                <div class="sdr-notif-header">
                    <div class="d-flex align-items-center gap-2">
                        <span class="sdr-notif-header-title">Notificaciones</span>
                        <span x-cloak x-show="unreadCount > 0" class="sdr-notif-pill" x-text="unreadCount"></span>
                    </div>

                    @if (\Illuminate\Support\Facades\Route::has('notifications.mark-read'))
                        <form x-cloak
                              x-show="unreadCount > 0"
                              method="POST"
                              action="{{ route('notifications.mark-read') }}"
                              class="m-0"
                              @submit.prevent="markAllRead()">
                            @csrf
                            <button type="submit" class="btn btn-link p-0 border-0 sdr-notif-mark-btn">
                                Marcar leídas
                            </button>
                        </form>
                    @endif
                </div>

                {{-- Zona 2: Cuerpo / Lista --}}
                <div class="sdr-notif-body">
                    <template x-if="notifications.length === 0">
                        <div class="p-4 text-center small" style="color: var(--ta-text-muted);">
                            <i data-lucide="bell-off" class="ta-icon d-block mx-auto mb-2"></i>
                            <span>No tienes notificaciones recientes.</span>
                        </div>
                    </template>

                    <template x-for="item in notifications" :key="item.id">
                        <a :href="item.open_url"
                           class="sdr-notif-item"
                           :class="{ 'sdr-notif-item--unread': item.is_unread }">
                            <div class="sdr-notif-item-title">
                                <span x-show="item.is_unread" class="sdr-notif-unread-dot" aria-hidden="true"></span>
                                <span x-text="item.title"></span>
                            </div>
                            <p class="sdr-notif-item-desc" x-show="item.body" x-text="item.body"></p>
                            <div class="sdr-notif-item-meta">
                                <span class="sdr-notif-item-time" x-text="item.time_ago"></span>
                                <span class="sdr-notif-item-action">Abrir →</span>
                            </div>
                        </a>
                    </template>
                </div>

                {{-- Zona 3: Pie fijo --}}
                @if (\Illuminate\Support\Facades\Route::has('notifications.index'))
                    <div class="sdr-notif-footer">
                        <a href="{{ route('notifications.index') }}">
                            Ver todas las notificaciones
                        </a>
                    </div>
                @endif
            </div>
        </div>
        @once
            <script>
                function sdrNotificationBell(endpoint, initialCount, initialNotifications) {
                    return {
                        unreadCount: initialCount || 0,
                        notifications: Array.isArray(initialNotifications) ? initialNotifications : [],
                        endpoint,
                        markReadUrl: @js(\Illuminate\Support\Facades\Route::has('notifications.mark-read') ? route('notifications.mark-read') : ''),
                        csrfToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                        lastActivityAt: Date.now(),
                        pollIntervalId: null,
                        idleThresholdMs: 180000,
                        pollIntervalMs: 50000,

                        init() {
                            if (!this.endpoint) {
                                return;
                            }

                            const markActive = () => {
                                const wasIdle = (Date.now() - this.lastActivityAt) >= this.idleThresholdMs;
                                this.lastActivityAt = Date.now();
                                if (wasIdle && !document.hidden) {
                                    this.fetchUnreadCount();
                                }
                            };

                            ['mousemove', 'keydown', 'click', 'scroll'].forEach((evt) => {
                                window.addEventListener(evt, markActive, { passive: true });
                            });

                            document.addEventListener('visibilitychange', () => {
                                if (!document.hidden && (Date.now() - this.lastActivityAt) < this.idleThresholdMs) {
                                    this.fetchUnreadCount();
                                }
                            });

                            this.pollIntervalId = setInterval(() => {
                                const isIdle = (Date.now() - this.lastActivityAt) >= this.idleThresholdMs;
                                if (document.hidden || isIdle || !navigator.onLine) {
                                    return;
                                }
                                this.fetchUnreadCount();
                            }, this.pollIntervalMs);
                        },

                        async markAllRead() {
                            if (!this.markReadUrl) {
                                return;
                            }

                            try {
                                const response = await fetch(this.markReadUrl, {
                                    method: 'POST',
                                    headers: {
                                        'Accept': 'application/json',
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': this.csrfToken,
                                    },
                                    credentials: 'same-origin',
                                    body: JSON.stringify({}),
                                });

                                if (response.ok) {
                                    const data = await response.json();
                                    this.unreadCount = typeof data.unread_count === 'number' ? data.unread_count : 0;
                                    this.notifications = this.notifications.map((item) => ({
                                        ...item,
                                        is_unread: false,
                                    }));
                                }
                            } catch (_) {}
                        },

                        async fetchUnreadCount() {
                            try {
                                const response = await fetch(this.endpoint, {
                                    headers: { 'Accept': 'application/json' },
                                    credentials: 'same-origin',
                                });
                                if (response.ok) {
                                    const data = await response.json();
                                    if (typeof data.unread_count === 'number') {
                                        this.unreadCount = data.unread_count;
                                    }
                                    if (Array.isArray(data.notifications)) {
                                        this.notifications = data.notifications;
                                    }
                                }
                            } catch (_) {}
                        },
                    };
                }
            </script>
        @endonce

        {{-- Único bloque de usuario (Header derecha) --}}
        <div class="dropdown">
            <button type="button"
                    class="ta-profile-btn dropdown-toggle"
                    id="profileDropdown"
                    data-bs-toggle="dropdown"
                    aria-expanded="false">
                @if ($avatarUrl)
                    <img src="{{ $avatarUrl }}" alt="{{ $userName }}" class="ta-avatar">
                @else
                    <span class="ta-avatar-initials">{{ $initials }}</span>
                @endif
                <span class="d-none d-sm-inline" style="font-size: 14px; font-weight: 500; color: var(--ta-text-primary);">
                    {{ $shortName }}
                </span>
                <i data-lucide="chevron-down" class="ta-icon-xs d-none d-sm-inline" style="color: var(--ta-text-muted);"></i>
            </button>

            <div class="dropdown-menu dropdown-menu-end ta-dropdown-menu" aria-labelledby="profileDropdown">
                @auth
                    <div class="ta-dropdown-header">
                        <div class="d-flex align-items-center justify-content-between gap-2">
                            <span class="fw-semibold" style="color: var(--ta-text-primary); font-size: 0.9rem;">{{ $userName }}</span>
                            <span class="ta-badge ta-badge-brand">{{ $userRole }}</span>
                        </div>
                        @if ($userEmail)
                            <div class="mt-1" style="color: var(--ta-text-muted); font-size: 0.78rem; word-break: break-word;">
                                {{ $userEmail }}
                            </div>
                        @endif
                    </div>

                    <div class="py-1">
                        <a class="ta-dropdown-item" href="{{ $profileHref }}">
                            <i data-lucide="user-circle" class="ta-icon-sm"></i>
                            <span>Mi Perfil</span>
                        </a>

                        @if (\Illuminate\Support\Facades\Route::has('notifications.index'))
                            <a class="ta-dropdown-item justify-content-between" href="{{ route('notifications.index') }}">
                                <span class="d-inline-flex align-items-center gap-2">
                                    <i data-lucide="bell" class="ta-icon-sm"></i>
                                    <span>Notificaciones</span>
                                </span>
                                @if ($initialUnreadCount > 0)
                                    <span class="ta-badge ta-badge-brand">{{ $initialUnreadCount }}</span>
                                @endif
                            </a>
                        @endif
                    </div>

                    @if (\Illuminate\Support\Facades\Route::has('logout'))
                        <div class="ta-dropdown-divider"></div>
                        <div class="py-1">
                            <form method="POST" action="{{ route('logout') }}" class="m-0">
                                @csrf
                                <button type="submit" class="ta-dropdown-item ta-dropdown-item--danger">
                                    <i data-lucide="log-out" class="ta-icon-sm"></i>
                                    <span>Cerrar sesión</span>
                                </button>
                            </form>
                        </div>
                    @endif
                @else
                    <div class="ta-dropdown-header">
                        <div class="fw-semibold" style="color: var(--ta-text-primary);">Sesión no iniciada</div>
                    </div>
                @endauth
            </div>
        </div>
    </div>
</header>
