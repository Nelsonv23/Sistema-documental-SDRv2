@extends('layouts.app')

@section('title', 'Notificaciones')

@section('breadcrumbs')
    <li><a href="{{ route('home') }}">Inicio</a></li>
    <li><i data-lucide="chevron-right" class="ta-icon-xs"></i></li>
    <li class="active" aria-current="page">Notificaciones</li>
@endsection

@section('page-title', 'Notificaciones')
@section('page-subtitle', 'Mantente al día con comentarios y novedades relevantes en tus documentos autorizados.')

@section('page-actions')
    <form method="POST" action="{{ route('notifications.mark-read') }}">
        @csrf
        <button type="submit" class="btn btn-outline-secondary">
            <i data-lucide="check-check" class="ta-icon-sm"></i>
            <span>Marcar todas como leídas</span>
        </button>
    </form>
@endsection

@section('content')
    <div class="ta-card">
        <div class="ta-card-header d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
            <div class="d-inline-flex align-items-center gap-2" role="group" aria-label="Filtrar notificaciones">
                <a href="{{ route('notifications.index') }}"
                   class="btn btn-sm {{ $filter === 'all' ? 'btn-primary' : 'btn-outline-secondary' }}">
                    Todas
                </a>
                <a href="{{ route('notifications.index', ['filter' => 'unread']) }}"
                   class="btn btn-sm {{ $filter === 'unread' ? 'btn-primary' : 'btn-outline-secondary' }}">
                    Sin leer
                </a>
            </div>

            <div class="d-inline-flex align-items-center gap-2">
                <span class="ta-badge ta-badge-neutral">
                    {{ $totalCount }} {{ $totalCount === 1 ? 'aviso' : 'avisos' }}
                </span>
                @if ($unreadCount > 0)
                    <span class="ta-badge ta-badge-danger">
                        {{ $unreadCount }} sin leer
                    </span>
                @endif
            </div>
        </div>

        @if ($notifications->isEmpty())
            <div class="ta-empty-state">
                <div class="ta-empty-state__icon">
                    <i data-lucide="bell" class="ta-icon"></i>
                </div>
                <h5 class="mb-1">
                    {{ $filter === 'unread' ? 'Sin notificaciones pendientes' : 'Sin notificaciones' }}
                </h5>
                <p class="small mb-0" style="color: var(--ta-text-muted);">
                    {{ $filter === 'unread' ? 'Ya revisaste todos los avisos recientes.' : 'Aquí aparecerán los avisos de actividad relevante para tu cuenta.' }}
                </p>
            </div>
        @else
            <div>
                @foreach ($notifications as $notification)
                    <div class="p-4" style="border-bottom: 1px solid var(--sdr-border-color); {{ $notification->read_at === null ? 'background-color: var(--sdr-surface-subtle);' : '' }}">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                            <div style="min-width: 0; flex: 1;">
                                <div class="d-flex align-items-center gap-2">
                                    @if ($notification->read_at === null)
                                        <span class="sdr-notif-unread-dot" aria-hidden="true"></span>
                                    @endif
                                    <span class="fw-semibold" style="color: var(--ta-text-primary);">{{ $notification->title }}</span>
                                </div>
                                @if ($notification->body)
                                    <p class="small mb-1 mt-1" style="color: var(--ta-text-body);">{{ $notification->body }}</p>
                                @endif
                                <div style="font-size: 0.75rem; color: var(--ta-text-muted);">
                                    {{ $notification->created_at?->format('d/m/Y H:i') }}
                                </div>
                            </div>

                            <div class="d-flex gap-2 flex-shrink-0">
                                @if (! empty($notification->data['url']))
                                    <a href="{{ route('notifications.open', $notification) }}" class="btn btn-sm btn-outline-primary">
                                        Abrir
                                    </a>
                                @endif
                                @if ($notification->read_at === null)
                                    <form method="POST" action="{{ route('notifications.mark-read') }}">
                                        @csrf
                                        <input type="hidden" name="notification_id" value="{{ $notification->id }}">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                                            Marcar leída
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($notifications->hasPages())
                <div class="ta-card-footer">
                    {{ $notifications->links() }}
                </div>
            @endif
        @endif
    </div>
@endsection
