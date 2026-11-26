@extends('layouts.app')

@section('title', 'Buscar documentos')

@section('breadcrumbs')
    <li><a href="{{ route('home') }}">Inicio</a></li>
    <li><i data-lucide="chevron-right" class="ta-icon-xs"></i></li>
    <li class="active" aria-current="page">Buscar documentos</li>
@endsection

@section('page-title', 'Buscar documentos')
@section('page-subtitle', 'Búsqueda híbrida por nombre, código, carpeta, extensión, autor y fecha dentro de tus carpetas autorizadas.')

@section('content')
    <div class="ta-card mb-4">
        <div class="ta-card-header">
            <h2 class="ta-card-title">Filtros de búsqueda</h2>
        </div>
        <div class="ta-card-body">
            <form method="GET" action="{{ route('documents.search') }}" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="q" class="form-label">Nombre o código</label>
                    <input id="q"
                           type="text"
                           name="q"
                           value="{{ $filters['q'] }}"
                           class="form-control"
                           placeholder="Ej. Contrato 2026 o R-01">
                </div>

                <div class="col-md-3">
                    <label for="folder_id" class="form-label">Carpeta</label>
                    <select id="folder_id" name="folder_id" class="form-select">
                        <option value="">Todas las autorizadas</option>
                        @foreach ($folders as $folderOption)
                            <option value="{{ $folderOption->id }}" @selected((string) $filters['folder_id'] === (string) $folderOption->id)>
                                {{ $folderOption->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="extension" class="form-label">Extensión</label>
                    <input id="extension"
                           type="text"
                           name="extension"
                           value="{{ $filters['extension'] }}"
                           class="form-control"
                           placeholder="pdf, xlsx...">
                </div>

                <div class="col-md-3">
                    <label for="created_by" class="form-label">Autor</label>
                    <select id="created_by" name="created_by" class="form-select">
                        <option value="">Cualquier autor</option>
                        @foreach ($authors as $author)
                            <option value="{{ $author->id }}" @selected((string) $filters['created_by'] === (string) $author->id)>
                                {{ $author->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="date_from" class="form-label">Desde</label>
                    <input id="date_from" type="date" name="date_from" value="{{ $filters['date_from'] }}" class="form-control">
                </div>

                <div class="col-md-3">
                    <label for="date_to" class="form-label">Hasta</label>
                    <input id="date_to" type="date" name="date_to" value="{{ $filters['date_to'] }}" class="form-control">
                </div>

                <div class="col-md-3">
                    <input type="hidden" name="include_subfolders" value="0">
                    <div class="form-check pb-2">
                        <input id="include_subfolders"
                               type="checkbox"
                               name="include_subfolders"
                               value="1"
                               class="form-check-input"
                               @checked($filters['include_subfolders'])>
                        <label for="include_subfolders" class="form-check-label small">
                            Incluir subcarpetas
                        </label>
                    </div>
                </div>

                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i data-lucide="search" class="ta-icon-sm"></i>
                        <span>Buscar</span>
                    </button>
                    <a href="{{ route('documents.search') }}" class="btn btn-outline-secondary">
                        Limpiar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="ta-card">
        <div class="ta-card-header d-flex justify-content-between align-items-center">
            <div>
                <h2 class="ta-card-title">Resultados</h2>
                <p class="ta-card-subtitle">Documentos coincidentes dentro de tus carpetas autorizadas.</p>
            </div>
            <span class="ta-badge ta-badge-brand">{{ $documents->total() }} {{ $documents->total() === 1 ? 'documento' : 'documentos' }}</span>
        </div>

        @if ($documents->isEmpty())
            <div class="ta-empty-state">
                <div class="ta-empty-state__icon">
                    <i data-lucide="file-search" class="ta-icon"></i>
                </div>
                <h5 class="mb-1">Sin resultados</h5>
                <p class="small mb-0" style="color: var(--ta-text-muted);">
                    No se encontraron documentos que coincidan con tus filtros dentro de tus carpetas accesibles.
                </p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table ta-table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Documento</th>
                            <th>Carpeta</th>
                            <th>Autor</th>
                            <th>Fecha</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($documents as $doc)
                            <tr>
                                <td class="fw-medium">
                                    <a href="{{ route('documents.show', $doc) }}" class="d-inline-flex align-items-center gap-2" style="color: var(--ta-text-primary);">
                                        <i data-lucide="file-text" class="ta-icon-sm" style="color: var(--ta-brand-500);"></i>
                                        <span>{{ $doc->name }}</span>
                                    </a>
                                </td>
                                <td>
                                    @if ($doc->folder)
                                        <a href="{{ route('folders.show', $doc->folder) }}">{{ $doc->folder->name }}</a>
                                    @endif
                                </td>
                                <td style="color: var(--ta-text-muted);">{{ $doc->creator?->name ?? 'Sistema' }}</td>
                                <td style="color: var(--ta-text-muted);">{{ $doc->created_at?->format('d/m/Y H:i') }}</td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        @if ($storage->isPreviewable($doc->extension))
                                            <a href="{{ route('documents.preview', $doc) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
                                                <i data-lucide="eye" class="ta-icon-xs"></i>
                                                <span>Ver</span>
                                            </a>
                                        @endif
                                        <a href="{{ route('documents.download', $doc) }}" class="btn btn-sm btn-outline-secondary">
                                            <i data-lucide="download" class="ta-icon-xs"></i>
                                            <span>Descargar</span>
                                        </a>
                                        @can('delete', $doc)
                                            <form method="POST"
                                                  action="{{ route('documents.destroy', $doc) }}"
                                                  onsubmit="return confirm('¿Eliminar este documento? El archivo irá a la papelera de seguridad por 60 días y solo podrá ser restaurado por un Administrador.')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Enviar a papelera">
                                                    <i data-lucide="trash-2" class="ta-icon-xs"></i>
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
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
