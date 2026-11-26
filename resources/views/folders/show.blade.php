@extends('layouts.app')

@section('title', $currentFolder ? $currentFolder->name : 'Explorador documental')

@section('breadcrumbs')
    <li>
        @if ($currentFolder)
            <a href="{{ route('home') }}">Inicio</a>
        @else
            <span>Inicio</span>
        @endif
    </li>
    <li><i data-lucide="chevron-right" class="ta-icon-xs"></i></li>
    @if (! $currentFolder)
        <li class="active" aria-current="page">Explorador</li>
    @else
        <li><a href="{{ route('home') }}">Explorador</a></li>
        @foreach ($breadcrumbs as $crumb)
            <li><i data-lucide="chevron-right" class="ta-icon-xs"></i></li>
            @if ($loop->last)
                <li class="active" aria-current="page">{{ $crumb['folder']->name }}</li>
            @elseif ($crumb['accessible'])
                <li><a href="{{ route('folders.show', $crumb['folder']) }}">{{ $crumb['folder']->name }}</a></li>
            @else
                <li><span>{{ $crumb['folder']->name }}</span></li>
            @endif
        @endforeach
    @endif
@endsection

@section('page-title', $currentFolder ? $currentFolder->name : 'Explorador documental')
@section('page-subtitle', $currentFolder ? 'Carpetas y documentos disponibles en esta ubicación.' : 'Carpetas corporativas autorizadas para tu cuenta.')

@section('page-actions')
    @if ($currentFolder && auth()->user()->can('managePermissions', $currentFolder))
        <a href="{{ route('folders.permissions', $currentFolder) }}" class="btn btn-outline-secondary">
            <i data-lucide="shield" class="ta-icon-sm"></i>
            <span>Permisos</span>
        </a>
    @endif

    @if ($currentFolder && auth()->user()->can('update', $currentFolder))
        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#moveRenameFolderModal">
            <i data-lucide="folder-pen" class="ta-icon-sm"></i>
            <span>Editar / Mover</span>
        </button>
    @endif

    @if ($currentFolder)
        @can('delete', $currentFolder)
            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteFolderModal">
                <i data-lucide="trash-2" class="ta-icon-sm"></i>
                <span>Eliminar</span>
            </button>
        @endcan
    @endif

    @if (($currentFolder && auth()->user()->can('create', [App\Models\Folder::class, $currentFolder])) || (! $currentFolder && auth()->user()->can('createRoot', App\Models\Folder::class)))
        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#newFolderModal">
            <i data-lucide="folder-plus" class="ta-icon-sm"></i>
            <span>Nueva carpeta</span>
        </button>
    @endif

    @if ($currentFolder && auth()->user()->can('create', [App\Models\Document::class, $currentFolder]))
        <button type="button"
                class="btn btn-primary"
                onclick="document.getElementById('sdr-file-input')?.click()">
            <i data-lucide="upload-cloud" class="ta-icon-sm"></i>
            <span>Subir archivos</span>
        </button>
    @endif
@endsection

@section('content')
    {{-- Tarjetas de resumen superior estilo Orchid --}}
    <div class="row g-3 g-md-4 mb-4">
        <div class="col-sm-6 col-xl-4">
            <div class="ta-card ta-kpi-card h-100">
                <div class="ta-card-body d-flex align-items-center justify-content-between">
                    <div>
                        <div class="small fw-medium" style="color: var(--ta-text-muted);">Carpetas visibles</div>
                        <div class="mt-1 fw-semibold" style="font-size: 1.5rem; color: var(--ta-text-primary); line-height: 1.2;">
                            {{ $subfolders->count() }}
                        </div>
                    </div>
                    <span class="ta-stat-icon ta-stat-icon--brand">
                        <i data-lucide="folder" class="ta-icon"></i>
                    </span>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-4">
            <div class="ta-card ta-kpi-card h-100">
                <div class="ta-card-body d-flex align-items-center justify-content-between">
                    <div>
                        <div class="small fw-medium" style="color: var(--ta-text-muted);">
                            {{ $currentFolder ? 'Documentos en carpeta' : 'Ubicación actual' }}
                        </div>
                        <div class="mt-1 fw-semibold" style="font-size: 1.5rem; color: var(--ta-text-primary); line-height: 1.2;">
                            {{ $currentFolder ? $documents->total() : 'Raíz' }}
                        </div>
                    </div>
                    <span class="ta-stat-icon ta-stat-icon--brand">
                        <i data-lucide="files" class="ta-icon"></i>
                    </span>
                </div>
            </div>
        </div>

        <div class="col-sm-12 col-xl-4">
            <div class="ta-card ta-kpi-card h-100">
                <div class="ta-card-body d-flex align-items-center justify-content-between">
                    <div>
                        <div class="small fw-medium" style="color: var(--ta-text-muted);">Almacenamiento privado</div>
                        <div class="mt-1 fw-semibold" style="font-size: 1.15rem; color: var(--ta-text-primary); line-height: 1.3;">
                            Máx. 60 MB / archivo
                        </div>
                    </div>
                    <span class="ta-badge ta-badge-success">Protegido</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Zona de subida múltiple Alpine.js (máx. 2 concurrentes, 1 archivo por petición, reintentos silenciosos) --}}
    @if ($currentFolder && auth()->user()->can('create', [App\Models\Document::class, $currentFolder]))
        <div class="ta-card mb-4"
             x-data="sdrUploader({
                 checkUrl: @js(route('documents.check-duplicate', $currentFolder)),
                 uploadUrl: @js(route('documents.store', $currentFolder)),
                 csrfToken: @js(csrf_token())
             })">
            <div class="ta-card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h2 class="ta-card-title">Subir documentos</h2>
                    <p class="ta-card-subtitle">Cola de hasta 2 cargas simultáneas con reintento automático ante fallos de red.</p>
                </div>
                <form method="GET" action="{{ route('documents.search') }}" class="d-flex gap-2">
                    <input type="hidden" name="folder_id" value="{{ $currentFolder->id }}">
                    <input type="text" name="q" class="form-control form-control-sm" placeholder="Buscar en esta carpeta..." style="width: 220px;">
                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                        <i data-lucide="search" class="ta-icon-xs"></i>
                    </button>
                </form>
            </div>

            <div class="ta-card-body">
                <div class="ta-dropzone"
                     :class="{ 'is-dragging': isDragging }"
                     @dragover.prevent="isDragging = true"
                     @dragleave.prevent="isDragging = false"
                     @drop.prevent="handleDrop($event)">
                    <input id="sdr-file-input"
                           type="file"
                           multiple
                           class="d-none"
                           @change="handleFileSelect($event)">
                    <div class="ta-empty-state__icon mx-auto mb-2" style="width: 48px; height: 48px;">
                        <i data-lucide="upload-cloud" class="ta-icon"></i>
                    </div>
                    <div class="fw-semibold" style="color: var(--ta-text-primary);">
                        Arrastra tus archivos aquí o
                        <button type="button"
                                class="btn btn-link p-0 align-baseline fw-semibold"
                                onclick="document.getElementById('sdr-file-input').click()">
                            selecciónalos desde tu equipo
                        </button>
                    </div>
                    <div class="small mt-1" style="color: var(--ta-text-muted);">
                        Se procesa un archivo por petición HTTP hasta 60 MB.
                    </div>
                </div>

                {{-- Cola de subidas --}}
                <div x-cloak x-show="items.length > 0" class="mt-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                        <div class="small fw-semibold" style="color: var(--ta-text-primary);">
                            Cola de subida:
                            <span x-text="completedCount"></span>/<span x-text="items.length"></span> completados
                            <span x-show="activeCount > 0" class="fw-normal ms-1" style="color: var(--ta-text-muted);">
                                (<span x-text="activeCount"></span> en progreso)
                            </span>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button"
                                    x-show="completedCount > 0"
                                    class="btn btn-sm btn-outline-primary"
                                    @click="window.location.reload()">
                                <i data-lucide="refresh-cw" class="ta-icon-xs"></i>
                                <span>Actualizar lista</span>
                            </button>
                            <button type="button"
                                    x-show="activeCount === 0"
                                    class="btn btn-sm btn-outline-secondary"
                                    @click="clearFinished()">
                                Limpiar completados
                            </button>
                        </div>
                    </div>

                    <div class="border rounded-3 overflow-hidden" style="border-color: var(--ta-border-light) !important;">
                        <template x-for="item in items" :key="item.uid">
                            <div class="p-3" style="border-bottom: 1px solid var(--ta-border-light);">
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                    <div class="me-auto" style="min-width: 220px;">
                                        <div class="fw-medium small" style="color: var(--ta-text-primary);" x-text="item.name"></div>
                                        <div style="font-size: 0.75rem; color: var(--ta-text-muted);">
                                            <span x-text="formatBytes(item.size)"></span>
                                            <span x-show="item.statusMessage" class="ms-2" x-text="item.statusMessage"></span>
                                        </div>
                                    </div>

                                    <div class="d-flex align-items-center gap-2">
                                        <span x-show="item.status === 'queued'" class="ta-badge ta-badge-neutral">En cola</span>
                                        <span x-show="item.status === 'checking'" class="ta-badge ta-badge-info">Verificando</span>
                                        <span x-show="item.status === 'uploading'" class="ta-badge ta-badge-brand" x-text="item.progress + '%'"></span>
                                        <span x-show="item.status === 'retrying'" class="ta-badge ta-badge-warning" x-text="'Reintentando (' + item.retries + '/2)'"></span>
                                        <span x-show="item.status === 'done'" class="ta-badge ta-badge-success">Subido</span>
                                        <span x-show="item.status === 'cancelled'" class="ta-badge ta-badge-neutral">Cancelado</span>

                                        <template x-if="item.status === 'duplicate'">
                                            <div class="d-flex flex-wrap gap-1">
                                                <button type="button" class="btn btn-sm btn-primary" @click="resolveDuplicate(item, 'replace')">
                                                    Reemplazar
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" @click="resolveDuplicate(item, 'keep_both')">
                                                    Conservar ambos
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" @click="cancelItem(item)">
                                                    Cancelar
                                                </button>
                                            </div>
                                        </template>

                                        <template x-if="item.status === 'error'">
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="ta-badge ta-badge-danger">Error</span>
                                                <button type="button" class="btn btn-sm btn-outline-danger" @click="retryItem(item)">
                                                    Reintentar
                                                </button>
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                <div x-show="item.status === 'uploading' || item.status === 'retrying'" class="progress mt-2" style="height: 5px;">
                                    <div class="progress-bar" style="background-color: #465FFF;" role="progressbar" :style="'width: ' + item.progress + '%; background-color: #465FFF;'"></div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Tarjeta unificada del Explorador: Subcarpetas + Documentos --}}
    <div class="ta-card">
        <div class="ta-card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h2 class="ta-card-title">Contenido de la carpeta</h2>
                <p class="ta-card-subtitle">Subcarpetas y archivos activos con acceso autorizado.</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="ta-badge ta-badge-neutral">{{ $subfolders->count() }} carpetas</span>
                @if ($currentFolder)
                    <span class="ta-badge ta-badge-brand">{{ $documents->total() }} documentos</span>
                @endif
            </div>
        </div>

        @if ($subfolders->isEmpty() && (! $currentFolder || $documents->isEmpty()))
            <div class="ta-empty-state">
                <div class="ta-empty-state__icon">
                    <i data-lucide="folder-open" class="ta-icon"></i>
                </div>
                <h5 class="mb-1">Ubicación vacía</h5>
                <p class="small mb-0" style="color: var(--ta-text-muted);">
                    {{ $currentFolder ? 'Crea una subcarpeta o sube archivos usando los botones superiores.' : 'Aún no hay carpetas habilitadas para tu cuenta.' }}
                </p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table ta-table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Tipo / Tamaño</th>
                            <th>Autor</th>
                            <th>Actualizado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($subfolders as $subfolder)
                            <tr>
                                <td>
                                    <a href="{{ route('folders.show', $subfolder) }}" class="fw-semibold d-inline-flex align-items-center gap-2" style="color: var(--ta-text-primary);">
                                        <i data-lucide="folder" class="ta-icon-sm" style="color: var(--ta-brand-500);"></i>
                                        <span>{{ $subfolder->name }}</span>
                                    </a>
                                </td>
                                <td><span class="ta-badge ta-badge-brand">Carpeta</span></td>
                                <td style="color: var(--ta-text-muted);">—</td>
                                <td style="color: var(--ta-text-muted);">{{ $subfolder->updated_at?->format('d/m/Y H:i') }}</td>
                                <td class="text-end">
                                    <a href="{{ route('folders.show', $subfolder) }}" class="btn btn-sm btn-outline-secondary">
                                        <i data-lucide="arrow-right" class="ta-icon-xs"></i>
                                        <span>Abrir</span>
                                    </a>
                                </td>
                            </tr>
                        @endforeach

                        @if ($currentFolder)
                            @foreach ($documents as $doc)
                                @php
                                    $sizeBytes = $doc->currentVersion?->size ?? 0;
                                    $sizeLabel = $sizeBytes >= 1048576
                                        ? number_format($sizeBytes / 1048576, 2).' MB'
                                        : number_format(max(1, (int) ceil($sizeBytes / 1024))).' KB';
                                @endphp
                                <tr>
                                    <td>
                                        <a href="{{ route('documents.show', $doc) }}" class="fw-medium d-inline-flex align-items-center gap-2" style="color: var(--ta-text-primary);">
                                            <i data-lucide="file-text" class="ta-icon-sm" style="color: var(--ta-text-muted);"></i>
                                            <span>{{ $doc->name }}</span>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="ta-badge ta-badge-neutral text-uppercase me-1">{{ $doc->extension ?: 'DOC' }}</span>
                                        <span style="color: var(--ta-text-muted);">{{ $sizeLabel }}</span>
                                    </td>
                                    <td style="color: var(--ta-text-muted);">{{ $doc->creator?->name ?? 'Sistema' }}</td>
                                    <td style="color: var(--ta-text-muted);">{{ $doc->updated_at?->format('d/m/Y H:i') }}</td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-1">
                                            <a href="{{ route('documents.show', $doc) }}" class="btn btn-sm btn-outline-secondary" title="Detalle y comentarios">
                                                <i data-lucide="message-square" class="ta-icon-xs"></i>
                                            </a>
                                            @if ($storage->isPreviewable($doc->extension))
                                                <a href="{{ route('documents.preview', $doc) }}"
                                                   target="_blank"
                                                   rel="noopener"
                                                   class="btn btn-sm btn-outline-primary">
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
                        @endif
                    </tbody>
                </table>
            </div>

            @if ($currentFolder && $documents->hasPages())
                <div class="ta-card-footer">
                    {{ $documents->links() }}
                </div>
            @endif
        @endif
    </div>

    {{-- Modal: Nueva carpeta --}}
    @if (($currentFolder && auth()->user()->can('create', [App\Models\Folder::class, $currentFolder])) || (! $currentFolder && auth()->user()->can('createRoot', App\Models\Folder::class)))
        <div class="modal fade" id="newFolderModal" tabindex="-1" aria-labelledby="newFolderModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('folders.store') }}" class="modal-content">
                    @csrf
                    @if ($currentFolder)
                        <input type="hidden" name="parent_id" value="{{ $currentFolder->id }}">
                    @endif
                    <div class="modal-header">
                        <h5 class="modal-title fw-semibold" id="newFolderModalLabel">
                            {{ $currentFolder ? 'Nueva subcarpeta' : 'Nueva carpeta raíz' }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <label for="new_folder_name" class="form-label">Nombre de la carpeta</label>
                        <input id="new_folder_name"
                               type="text"
                               name="name"
                               class="form-control"
                               placeholder="Ej. Contratos 2026"
                               required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Crear carpeta</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Modal: Editar / Mover carpeta --}}
    @if ($currentFolder && auth()->user()->can('update', $currentFolder))
        <div class="modal fade" id="moveRenameFolderModal" tabindex="-1" aria-labelledby="moveRenameFolderModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('folders.update', $currentFolder) }}" class="modal-content">
                    @csrf
                    @method('PATCH')
                    <div class="modal-header">
                        <h5 class="modal-title fw-semibold" id="moveRenameFolderModalLabel">Editar o mover carpeta</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_folder_name" class="form-label">Nombre</label>
                            <input id="edit_folder_name"
                                   type="text"
                                   name="name"
                                   value="{{ $currentFolder->name }}"
                                   class="form-control"
                                   required>
                        </div>

                        <div>
                            <label for="edit_folder_parent" class="form-label">Carpeta contenedora</label>
                            <select id="edit_folder_parent" name="parent_id" class="form-select">
                                @can('createRoot', App\Models\Folder::class)
                                    <option value="" @selected($currentFolder->parent_id === null)>Raíz (sin carpeta padre)</option>
                                @endcan
                                @foreach ($movableFolders as $candidate)
                                    <option value="{{ $candidate->id }}" @selected((int) $currentFolder->parent_id === (int) $candidate->id)>
                                        {{ $candidate->name }} ({{ $candidate->path }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar cambios</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Modal: Eliminar carpeta vacía --}}
    @if ($currentFolder)
        @can('delete', $currentFolder)
            <div class="modal fade" id="deleteFolderModal" tabindex="-1" aria-labelledby="deleteFolderModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    @if ($deleteBlockReasons !== [])
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title fw-semibold" id="deleteFolderModalLabel">No se puede eliminar la carpeta</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                            </div>
                            <div class="modal-body">
                                <div class="alert alert-warning border-0 mb-0">
                                    <div class="fw-semibold mb-1">La carpeta "{{ $currentFolder->name }}" no está vacía</div>
                                    <p class="small mb-2">
                                        Para eliminar una carpeta de forma segura, primero debe vaciarse por completo.
                                    </p>
                                    <ul class="small mb-0 ps-3">
                                        @foreach ($deleteBlockReasons as $reason)
                                            <li>{{ $reason }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Entendido</button>
                            </div>
                        </div>
                    @else
                        <form method="POST" action="{{ route('folders.destroy', $currentFolder) }}" class="modal-content">
                            @csrf
                            @method('DELETE')
                            <div class="modal-header">
                                <h5 class="modal-title fw-semibold" id="deleteFolderModalLabel">Eliminar carpeta vacía</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                            </div>
                            <div class="modal-body">
                                <p class="mb-3" style="color: var(--ta-text-body);">
                                    ¿Confirmas que deseas eliminar la carpeta <strong>{{ $currentFolder->name }}</strong>?
                                </p>
                                <div class="alert alert-danger border-0 small mb-0">
                                    Esta acción eliminará la carpeta y sus permisos asociados, y quedará registrada en el historial de auditoría.
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-danger">
                                    <i data-lucide="trash-2" class="ta-icon-sm"></i>
                                    <span>Confirmar eliminación</span>
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        @endcan
    @endif
@endsection

@push('scripts')
<script>
function sdrUploader(config) {
    return {
        isDragging: false,
        items: [],
        maxConcurrent: 2,
        nextUid: 1,

        get activeCount() {
            return this.items.filter((item) => ['checking', 'uploading', 'retrying'].includes(item.status)).length;
        },

        get completedCount() {
            return this.items.filter((item) => item.status === 'done').length;
        },

        handleFileSelect(event) {
            const files = Array.from(event.target.files || []);
            this.enqueueFiles(files);
            event.target.value = '';
        },

        handleDrop(event) {
            this.isDragging = false;
            const files = Array.from(event.dataTransfer?.files || []);
            this.enqueueFiles(files);
        },

        enqueueFiles(files) {
            for (const file of files) {
                this.items.push({
                    uid: this.nextUid++,
                    file,
                    name: file.name,
                    size: file.size,
                    status: 'queued',
                    progress: 0,
                    retries: 0,
                    conflictStrategy: 'reject',
                    statusMessage: '',
                });
            }
            this.pumpQueue();
        },

        pumpQueue() {
            while (this.activeCount < this.maxConcurrent) {
                const nextItem = this.items.find((item) => item.status === 'queued');
                if (!nextItem) {
                    break;
                }
                this.processItem(nextItem);
            }
        },

        async processItem(item) {
            if (item.conflictStrategy === 'reject') {
                item.status = 'checking';
                item.statusMessage = '';
                try {
                    const checkResponse = await fetch(config.checkUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': config.csrfToken,
                        },
                        body: JSON.stringify({ name: item.name }),
                    });

                    if (checkResponse.ok) {
                        const data = await checkResponse.json();
                        if (data.duplicate) {
                            item.status = 'duplicate';
                            item.statusMessage = 'Ya existe un archivo con este nombre.';
                            this.pumpQueue();
                            return;
                        }
                    }
                } catch (_) {}
            }

            this.uploadSingle(item);
        },

        uploadSingle(item) {
            item.status = 'uploading';
            item.progress = 0;
            item.statusMessage = '';

            const formData = new FormData();
            formData.append('file', item.file);
            formData.append('conflict_strategy', item.conflictStrategy);

            const xhr = new XMLHttpRequest();
            xhr.open('POST', config.uploadUrl, true);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-CSRF-TOKEN', config.csrfToken);

            xhr.upload.addEventListener('progress', (event) => {
                if (event.lengthComputable) {
                    item.progress = Math.round((event.loaded / event.total) * 100);
                }
            });

            xhr.addEventListener('load', () => {
                if (xhr.status >= 200 && xhr.status < 300) {
                    item.status = 'done';
                    item.progress = 100;
                    item.statusMessage = 'Guardado correctamente.';
                    this.pumpQueue();
                    return;
                }

                if (xhr.status === 409) {
                    item.status = 'duplicate';
                    item.statusMessage = 'Ya existe un archivo con este nombre.';
                    this.pumpQueue();
                    return;
                }

                if (xhr.status >= 500) {
                    this.handleTransientFailure(item, 'Error del servidor (' + xhr.status + ').');
                    return;
                }

                let message = 'Error de validación (' + xhr.status + ').';
                try {
                    const parsed = JSON.parse(xhr.responseText);
                    message = parsed.message || message;
                } catch (_) {}

                item.status = 'error';
                item.statusMessage = message;
                this.pumpQueue();
            });

            xhr.addEventListener('error', () => {
                this.handleTransientFailure(item, 'Fallo de red durante la subida.');
            });

            xhr.send(formData);
        },

        handleTransientFailure(item, reason) {
            if (item.retries < 2) {
                item.retries += 1;
                item.status = 'retrying';
                item.statusMessage = reason;
                const delayMs = item.retries * 900;
                setTimeout(() => {
                    if (item.status === 'retrying') {
                        this.uploadSingle(item);
                    }
                }, delayMs);
                return;
            }

            item.status = 'error';
            item.statusMessage = reason + ' Reintentos automáticos agotados.';
            this.pumpQueue();
        },

        resolveDuplicate(item, strategy) {
            item.conflictStrategy = strategy;
            item.retries = 0;
            item.status = 'queued';
            item.statusMessage = '';
            this.pumpQueue();
        },

        retryItem(item) {
            item.retries = 0;
            item.status = 'queued';
            item.statusMessage = '';
            this.pumpQueue();
        },

        cancelItem(item) {
            item.status = 'cancelled';
            item.statusMessage = 'Omitido por el usuario.';
            this.pumpQueue();
        },

        clearFinished() {
            this.items = this.items.filter((item) => !['done', 'cancelled'].includes(item.status));
        },

        formatBytes(bytes) {
            if (bytes >= 1048576) {
                return (bytes / 1048576).toFixed(2) + ' MB';
            }
            return Math.max(1, Math.ceil(bytes / 1024)) + ' KB';
        },
    };
}
</script>
@endpush
