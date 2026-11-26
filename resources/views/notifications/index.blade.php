@extends('layouts.app')

@section('title', 'Notificaciones')

@section('breadcrumbs')
    <li><a href="{{ route('home') }}">Inicio</a></li>
    <li><i data-lucide="chevron-right" class="ta-icon-xs"></i></li>
    <li class="active" aria-current="page">Notificaciones</li>
@endsection

@section('page-title', 'Notificaciones')
@section('page-subtitle', 'Avisos recientes sobre comentarios y restauraciones en tus documentos.')

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
        <div class="ta-card-header d-flex justify-content-between align-items-center">
            <div>
                <h2 class="ta-card-title">Historial de notificaciones</h2>
                <p class="ta-card-subtitle">Actividad reciente relacionada con tus documentos.</p>
            </div>
            <span class="ta-badge ta-badge-brand">{{ $notifications->total() }} {{ $notifications->total() === 1 ? 'notificación' : 'notificaciones' }}</span>
        </div>

        @if ($notifications->isEmpty())
            <div class="ta-empty-state">
                <div class="ta-empty-state__icon">
                    <i data-lucide="bell" class="ta-icon"></i>
                </div>
                <h5 class="mb-1">Sin notificaciones</h5>
                <p class="small mb-0" style="color: var(--ta-text-muted);">Aquí aparecerán los avisos de actividad relevante para tu cuenta.</p>
            </div>
        @else
            <div class="divide-y">
                @foreach ($notifications as $notification)
                    <div class="p-4" style="border-bottom: 1px solid var(--ta-border-light); {{ $notification->read_at === null ? 'background-color: var(--ta-bg-subtle);' : '' }}">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                            <div>
                                <div class="d-flex align-items-center gap-2">
                                    @if ($notification->read_at === null)
                                        <span class="ta-badge ta-badge-info">Nueva</span>
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

                            <div class="d-flex gap-2">
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
