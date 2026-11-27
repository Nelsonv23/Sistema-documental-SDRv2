@extends('layouts.app')

@php
    $currentUser = auth()->user();
    $primaryRole = ($currentUser && method_exists($currentUser, 'getRoleNames'))
        ? ($currentUser->getRoleNames()->first() ?? 'Colaborador')
        : 'Colaborador';
@endphp

@section('title', 'Explorador documental')

@section('breadcrumbs')
    <li><span>Inicio</span></li>
    <li><i data-lucide="chevron-right" class="ta-icon-xs"></i></li>
    <li class="active" aria-current="page">Explorador</li>
@endsection

@section('page-title')
    <span class="d-inline-flex flex-wrap align-items-center gap-2">
        <span>Bienvenido/a, {{ $currentUser?->name }}</span>
        <span class="ta-badge ta-badge-brand">{{ $primaryRole }}</span>
    </span>
@endsection

@section('page-subtitle', 'Explorador documental · Carpetas y archivos corporativos autorizados para tu cuenta.')

@section('page-actions')
    @can('create', App\Models\Folder::class)
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newFolderModal">
            <i data-lucide="folder-plus" class="ta-icon-sm"></i>
            <span>Nueva carpeta</span>
        </button>
    @endcan
@endsection

@section('content')
    {{-- 4 KPI Cards dinámicas por rol estilo Orchid --}}
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="ta-card ta-kpi-card h-100">
                <div class="ta-card-body d-flex flex-column justify-content-between h-100">
                    <div class="d-flex align-items-start justify-content-between gap-2">
                        <div>
                            <div class="small fw-medium" style="color: var(--ta-text-muted);">Carpetas disponibles</div>
                            <div class="mt-1 fw-semibold" style="font-size: 1.65rem; color: var(--ta-text-primary); line-height: 1.2;">
                                {{ $accessibleFoldersCount }}
                            </div>
                        </div>
                        <span class="ta-stat-icon ta-stat-icon--brand">
                            <i data-lucide="folder" class="ta-icon"></i>
                        </span>
                    </div>
                    <div class="mt-3 pt-2 small" style="border-top: 1px solid var(--ta-border-divider); color: var(--ta-text-muted);">
                        {{ $rootFoldersCount }} en nivel raíz
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="ta-card ta-kpi-card h-100">
                <div class="ta-card-body d-flex flex-column justify-content-between h-100">
                    <div class="d-flex align-items-start justify-content-between gap-2">
                        <div>
                            <div class="small fw-medium" style="color: var(--ta-text-muted);">Documentos activos</div>
                            <div class="mt-1 fw-semibold" style="font-size: 1.65rem; color: var(--ta-text-primary); line-height: 1.2;">
                                {{ $accessibleDocumentsCount }}
                            </div>
                        </div>
                        <span class="ta-stat-icon ta-stat-icon--brand">
                            <i data-lucide="files" class="ta-icon"></i>
                        </span>
                    </div>
                    <div class="mt-3 pt-2 small" style="border-top: 1px solid var(--ta-border-divider); color: var(--ta-text-muted);">
                        {{ $isAdmin ? 'Almacenamiento: '.$totalStorageFormatted : 'En tus carpetas autorizadas' }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="ta-card ta-kpi-card h-100">
                <div class="ta-card-body d-flex flex-column justify-content-between h-100">
                    <div class="d-flex align-items-start justify-content-between gap-2">
                        <div>
                            <div class="small fw-medium" style="color: var(--ta-text-muted);">Novedades (7 días)</div>
                            <div class="mt-1 fw-semibold" style="font-size: 1.65rem; color: var(--ta-text-primary); line-height: 1.2;">
                                {{ $recentDocumentsCount }}
                            </div>
                        </div>
                        <span class="ta-stat-icon ta-stat-icon--brand">
                            <i data-lucide="clock-3" class="ta-icon"></i>
                        </span>
                    </div>
                    <div class="mt-3 pt-2 small" style="border-top: 1px solid var(--ta-border-divider); color: var(--ta-text-muted);">
                        Subidos o actualizados recientemente
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="ta-card ta-kpi-card h-100">
                <div class="ta-card-body d-flex flex-column justify-content-between h-100">
                    @if ($isAdmin)
                        <div class="d-flex align-items-start justify-content-between gap-2">
                            <div>
                                <div class="small fw-medium" style="color: var(--ta-text-muted);">En Papelera</div>
                                <div class="mt-1 fw-semibold" style="font-size: 1.65rem; color: var(--ta-text-primary); line-height: 1.2;">
                                    {{ $trashedDocumentsCount }}
                                </div>
                            </div>
                            <span class="ta-stat-icon ta-stat-icon--brand">
                                <i data-lucide="trash-2" class="ta-icon"></i>
                            </span>
                        </div>
                        <div class="mt-3 pt-2 small" style="border-top: 1px solid var(--ta-border-divider);">
                            <a href="{{ route('admin.trash.index') }}" class="fw-medium" style="color: #1d4ed8;">
                                Gestionar papelera →
                            </a>
                        </div>
                    @else
                        <div class="d-flex align-items-start justify-content-between gap-2">
                            <div>
                                <div class="small fw-medium" style="color: var(--ta-text-muted);">Mis documentos</div>
                                <div class="mt-1 fw-semibold" style="font-size: 1.65rem; color: var(--ta-text-primary); line-height: 1.2;">
                                    {{ $myDocumentsCount }}
                                </div>
                            </div>
                            <span class="ta-stat-icon ta-stat-icon--brand">
                                <i data-lucide="file-check-2" class="ta-icon"></i>
                            </span>
                        </div>
                        <div class="mt-3 pt-2 small" style="border-top: 1px solid var(--ta-border-divider); color: var(--ta-text-muted);">
                            Archivos subidos por ti
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Explorador de dos paneles: Árbol jerárquico izquierda + Contenido raíz derecha --}}
    <div class="row g-3">
        <div class="col-12 col-lg-4 col-xl-3">
            @include('folders.partials.tree-panel', [
                'folderTree' => $folderTree,
                'currentFolder' => null,
                'expandedFolderIds' => $expandedFolderIds,
            ])
        </div>

        <div class="col-12 col-lg-8 col-xl-9">
            <div class="ta-card">
                <div class="ta-card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h2 class="ta-card-title">Carpetas autorizadas</h2>
                        <p class="ta-card-subtitle">Accede directamente a los espacios documentales habilitados para tu cuenta.</p>
                    </div>
                    <span class="ta-badge ta-badge-neutral">{{ $subfolders->count() }} {{ $subfolders->count() === 1 ? 'carpeta' : 'carpetas' }}</span>
                </div>

                @if ($subfolders->isEmpty())
                    <div class="ta-empty-state">
                        <div class="ta-empty-state__icon">
                            <i data-lucide="folder-open" class="ta-icon"></i>
                        </div>
                        <h5 class="mb-1">Ubicación vacía</h5>
                        <p class="small mb-0" style="color: var(--ta-text-muted);">
                            Aún no hay carpetas habilitadas para tu cuenta.
                        </p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table ta-table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Contenido</th>
                                    <th>Actualizado</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($subfolders as $subfolder)
                                    @php
                                        $childFoldersCount = (int) ($subfolder->accessible_children_count ?? 0);
                                        $childDocsCount = (int) ($subfolder->active_documents_count ?? 0);
                                    @endphp
                                    <tr>
                                        <td>
                                            <a href="{{ route('folders.show', $subfolder) }}" class="fw-semibold d-inline-flex align-items-center gap-2" style="color: var(--ta-text-primary);">
                                                <i data-lucide="folder" class="ta-icon-sm" style="color: var(--ta-brand-500);"></i>
                                                <span>{{ $subfolder->name }}</span>
                                            </a>
                                        </td>
                                        <td>
                                            <span class="ta-badge ta-badge-brand me-1">
                                                {{ $childFoldersCount }} {{ $childFoldersCount === 1 ? 'subcarpeta' : 'subcarpetas' }}
                                            </span>
                                            <span class="ta-badge ta-badge-neutral">
                                                {{ $childDocsCount }} {{ $childDocsCount === 1 ? 'archivo' : 'archivos' }}
                                            </span>
                                        </td>
                                        <td style="color: var(--ta-text-muted);">{{ $subfolder->updated_at?->format('d/m/Y H:i') }}</td>
                                        <td class="text-end">
                                            <a href="{{ route('folders.show', $subfolder) }}" class="btn btn-sm btn-outline-secondary">
                                                <i data-lucide="arrow-right" class="ta-icon-xs"></i>
                                                <span>Abrir</span>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Modal: Nueva carpeta raíz --}}
    @can('create', App\Models\Folder::class)
        <div class="modal fade" id="newFolderModal" tabindex="-1" aria-labelledby="newFolderModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('folders.store') }}" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title fw-semibold" id="newFolderModalLabel">Nueva carpeta raíz</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="rounded-3 px-3 py-2 mb-3 small" style="background-color: var(--sdr-surface-subtle); border: 1px solid var(--sdr-border-color); color: var(--ta-text-body);">
                            <span class="fw-semibold" style="color: var(--ta-text-primary);">Ubicación:</span> Raíz
                        </div>
                        <label for="folder_name" class="form-label">Nombre de la carpeta</label>
                        <input id="folder_name"
                               type="text"
                               name="name"
                               class="form-control"
                               placeholder="Ej. Recursos Humanos"
                               required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Crear carpeta</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan
@endsection
