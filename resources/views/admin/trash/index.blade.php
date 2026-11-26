@extends('layouts.app')

@section('title', 'Papelera')

@section('breadcrumbs')
    <li><a href="{{ route('home') }}">Inicio</a></li>
    <li><i data-lucide="chevron-right" class="ta-icon-xs"></i></li>
    <li class="active" aria-current="page">Papelera</li>
@endsection

@section('page-title', 'Papelera de documentos')
@section('page-subtitle', 'Los documentos eliminados permanecen 60 días antes de su purga automática definitiva. Solo el Administrador puede restaurarlos.')

@section('content')
    <div class="ta-card">
        <div class="ta-card-header d-flex justify-content-between align-items-center">
            <div>
                <h2 class="ta-card-title">Documentos en papelera</h2>
                <p class="ta-card-subtitle">Retención máxima de 60 días antes de la purga automática de binarios.</p>
            </div>
            <span class="ta-badge ta-badge-warning">{{ $documents->total() }} {{ $documents->total() === 1 ? 'documento' : 'documentos' }}</span>
        </div>

        @if ($documents->isEmpty())
            <div class="ta-empty-state">
                <div class="ta-empty-state__icon">
                    <i data-lucide="trash-2" class="ta-icon"></i>
                </div>
                <h5 class="mb-1">Papelera vacía</h5>
                <p class="small mb-0" style="color: var(--ta-text-muted);">No hay documentos pendientes de restauración o purga.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table ta-table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Documento</th>
                            <th>Carpeta origen</th>
                            <th>Eliminado por</th>
                            <th>Fecha eliminación</th>
                            <th>Retención restante</th>
                            <th class="text-end">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($documents as $doc)
                            @php
                                $daysElapsed = $doc->deleted_at ? (int) $doc->deleted_at->diffInDays(now()) : 0;
                                $daysLeft = max(0, 60 - $daysElapsed);
                            @endphp
                            <tr>
                                <td class="fw-medium" style="color: var(--ta-text-primary);">
                                    <span class="d-inline-flex align-items-center gap-2">
                                        <i data-lucide="file-text" class="ta-icon-sm" style="color: var(--ta-text-muted);"></i>
                                        <span>{{ $doc->name }}</span>
                                    </span>
                                </td>
                                <td style="color: var(--ta-text-muted);">{{ $doc->folder?->name ?? 'Sin carpeta' }}</td>
                                <td style="color: var(--ta-text-muted);">{{ $doc->deletedBy?->name ?? 'Sistema' }}</td>
                                <td style="color: var(--ta-text-muted);">{{ $doc->deleted_at?->format('d/m/Y H:i') }}</td>
                                <td>
                                    <span class="ta-badge {{ $daysLeft <= 7 ? 'ta-badge-danger' : 'ta-badge-warning' }}">
                                        {{ $daysLeft }} {{ $daysLeft === 1 ? 'día' : 'días' }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('admin.trash.restore', $doc) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-primary">
                                            <i data-lucide="rotate-ccw" class="ta-icon-xs"></i>
                                            <span>Restaurar</span>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($documents->hasPages())
                <div class="ta-card-footer">
                    {{ $documents->links() }}
                </div>
            @endif
        @endif
    </div>
@endsection
