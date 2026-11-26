@extends('layouts.app')

@section('title', 'Auditoría del sistema')

@section('breadcrumbs')
    <li><a href="{{ route('home') }}">Inicio</a></li>
    <li><i data-lucide="chevron-right" class="ta-icon-xs"></i></li>
    <li class="active" aria-current="page">Auditoría</li>
@endsection

@section('page-title', 'Registro de auditoría')
@section('page-subtitle', 'Bitácora inmutable de accesos, permisos, documentos y cambios administrativos.')

@section('content')
    <div class="ta-card mb-4">
        <div class="ta-card-header">
            <h2 class="ta-card-title">Filtros de auditoría</h2>
        </div>
        <div class="ta-card-body">
            <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="q" class="form-label">Buscar</label>
                    <input id="q"
                           type="text"
                           name="q"
                           value="{{ $filters['q'] }}"
                           class="form-control"
                           placeholder="Usuario, correo o recurso">
                </div>

                <div class="col-md-3">
                    <label for="action" class="form-label">Acción</label>
                    <select id="action" name="action" class="form-select">
                        <option value="">Todas las acciones</option>
                        @foreach ($availableActions as $actionOption)
                            <option value="{{ $actionOption }}" @selected($filters['action'] === $actionOption)>
                                {{ $actionOption }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="actor_id" class="form-label">Usuario</label>
                    <select id="actor_id" name="actor_id" class="form-select">
                        <option value="">Todos</option>
                        @foreach ($actors as $actor)
                            <option value="{{ $actor->id }}" @selected((string) $filters['actor_id'] === (string) $actor->id)>
                                {{ $actor->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="date_from" class="form-label">Desde</label>
                    <input id="date_from" type="date" name="date_from" value="{{ $filters['date_from'] }}" class="form-control">
                </div>

                <div class="col-md-2">
                    <label for="date_to" class="form-label">Hasta</label>
                    <input id="date_to" type="date" name="date_to" value="{{ $filters['date_to'] }}" class="form-control">
                </div>

                <div class="col-12 d-flex justify-content-end gap-2">
                    @if ($filters['action'] !== '' || !empty($filters['actor_id']) || !empty($filters['date_from']) || !empty($filters['date_to']) || $filters['q'] !== '')
                        <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-outline-secondary btn-sm">
                            Limpiar filtros
                        </a>
                    @endif
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i data-lucide="filter" class="ta-icon-xs"></i>
                        <span>Filtrar registros</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="ta-card">
        <div class="ta-card-header d-flex justify-content-between align-items-center">
            <div>
                <h2 class="ta-card-title">Eventos registrados</h2>
                <p class="ta-card-subtitle">Trazabilidad inmutable de autenticación, archivos y permisos.</p>
            </div>
            <span class="ta-badge ta-badge-brand">{{ $logs->total() }} {{ $logs->total() === 1 ? 'registro' : 'registros' }}</span>
        </div>

        @if ($logs->isEmpty())
            <div class="ta-empty-state">
                <div class="ta-empty-state__icon">
                    <i data-lucide="shield-alert" class="ta-icon"></i>
                </div>
                <h5 class="mb-1">Sin eventos coincidentes</h5>
                <p class="small mb-0" style="color: var(--ta-text-muted);">No se encontraron registros con los filtros aplicados.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table ta-table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Actor</th>
                            <th>Acción</th>
                            <th>Recurso</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr>
                                <td class="text-nowrap" style="color: var(--ta-text-muted);">
                                    {{ $log->created_at?->format('d/m/Y H:i:s') }}
                                </td>
                                <td>
                                    <div class="fw-medium" style="color: var(--ta-text-primary);">
                                        {{ $log->actor_name ?? 'Sistema' }}
                                    </div>
                                    @if ($log->actor_email)
                                        <div style="font-size: 0.75rem; color: var(--ta-text-muted);">{{ $log->actor_email }}</div>
                                    @endif
                                </td>
                                <td>
                                    <span class="ta-badge ta-badge-info">{{ $log->action }}</span>
                                </td>
                                <td>
                                    @if ($log->target_label)
                                        <span class="fw-medium" style="color: var(--ta-text-primary);">{{ $log->target_label }}</span>
                                    @else
                                        <span style="color: var(--ta-text-muted);">—</span>
                                    @endif
                                </td>
                                <td style="color: var(--ta-text-muted);">{{ $log->ip_address ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($logs->hasPages())
                <div class="ta-card-footer">
                    {{ $logs->links() }}
                </div>
            @endif
        @endif
    </div>
@endsection
