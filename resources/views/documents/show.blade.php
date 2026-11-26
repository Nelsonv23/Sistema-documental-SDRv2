@extends('layouts.app')

@section('title', $document->name)

@section('breadcrumbs')
    <li><a href="{{ route('home') }}">Inicio</a></li>
    @if ($document->folder)
        <li><i data-lucide="chevron-right" class="ta-icon-xs"></i></li>
        <li><a href="{{ route('folders.show', $document->folder) }}">{{ $document->folder->name }}</a></li>
    @endif
    <li><i data-lucide="chevron-right" class="ta-icon-xs"></i></li>
    <li class="active" aria-current="page">{{ $document->name }}</li>
@endsection

@section('page-title', $document->name)
@section('page-subtitle', 'Detalle del documento, vista previa y comentarios del equipo.')

@section('page-actions')
    @if ($isPreviewable)
        <a href="{{ route('documents.preview', $document) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary">
            <i data-lucide="external-link" class="ta-icon-sm"></i>
            <span>Abrir vista previa</span>
        </a>
    @endif

    <a href="{{ route('documents.download', $document) }}" class="btn btn-primary">
        <i data-lucide="download" class="ta-icon-sm"></i>
        <span>Descargar</span>
    </a>

    @can('delete', $document)
        <form method="POST"
              action="{{ route('documents.destroy', $document) }}"
              onsubmit="return confirm('¿Enviar este documento a la papelera?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger">
                <i data-lucide="trash-2" class="ta-icon-sm"></i>
                <span>Eliminar</span>
            </button>
        </form>
    @endcan
@endsection

@section('content')
    @php
        $sizeBytes = $document->currentVersion?->size ?? 0;
        $sizeLabel = $sizeBytes >= 1048576
            ? number_format($sizeBytes / 1048576, 2).' MB'
            : number_format(max(1, (int) ceil($sizeBytes / 1024))).' KB';
    @endphp

    <div class="row g-4">
        <div class="col-lg-7 d-flex flex-column gap-4">
            <div class="ta-card">
                <div class="ta-card-header d-flex justify-content-between align-items-center">
                    <h2 class="ta-card-title">Información del documento</h2>
                    <span class="ta-badge ta-badge-brand text-uppercase">{{ $document->extension ?: 'archivo' }}</span>
                </div>
                <div class="ta-card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-sm-4" style="color: var(--ta-text-muted);">Carpeta</dt>
                        <dd class="col-sm-8">
                            @if ($document->folder)
                                <a href="{{ route('folders.show', $document->folder) }}">{{ $document->folder->name }}</a>
                            @endif
                        </dd>

                        <dt class="col-sm-4" style="color: var(--ta-text-muted);">Subido por</dt>
                        <dd class="col-sm-8" style="color: var(--ta-text-primary);">{{ $document->creator?->name ?? 'Sistema' }}</dd>

                        <dt class="col-sm-4" style="color: var(--ta-text-muted);">Tamaño</dt>
                        <dd class="col-sm-8" style="color: var(--ta-text-primary);">{{ $sizeLabel }}</dd>

                        <dt class="col-sm-4 mb-0" style="color: var(--ta-text-muted);">Última actualización</dt>
                        <dd class="col-sm-8 mb-0" style="color: var(--ta-text-primary);">{{ $document->updated_at?->format('d/m/Y H:i') }}</dd>
                    </dl>
                </div>
            </div>

            @if ($isPreviewable)
                <div class="ta-card">
                    <div class="ta-card-header">
                        <h2 class="ta-card-title">Vista previa</h2>
                    </div>
                    <div class="ta-card-body text-center" style="background-color: var(--ta-bg-subtle);">
                        @if ($isImage)
                            <img src="{{ route('documents.preview', $document) }}"
                                 alt="{{ $document->name }}"
                                 class="img-fluid rounded-3 border bg-white"
                                 style="max-height: 520px;">
                        @else
                            <iframe src="{{ route('documents.preview', $document) }}"
                                    title="Vista previa de {{ $document->name }}"
                                    class="w-100 border rounded-3 bg-white"
                                    style="height: 520px;"></iframe>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-5">
            <div class="ta-card">
                <div class="ta-card-header d-flex justify-content-between align-items-center">
                    <h2 class="ta-card-title">Comentarios</h2>
                    <span class="ta-badge ta-badge-neutral">{{ $document->comments->count() }}</span>
                </div>
                <div class="ta-card-body">
                    @if ($errors->has('body'))
                        <div class="alert alert-danger border-0 small py-2 mb-3">
                            {{ $errors->first('body') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('comments.store', $document) }}" class="mb-4">
                        @csrf
                        <label for="body" class="form-label">Agregar comentario</label>
                        <textarea id="body"
                                  name="body"
                                  rows="3"
                                  class="form-control mb-3 @error('body') is-invalid @enderror"
                                  placeholder="Escribe una nota o indicación sobre este documento..."
                                  required>{{ old('body') }}</textarea>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i data-lucide="send" class="ta-icon-xs"></i>
                                <span>Publicar comentario</span>
                            </button>
                        </div>
                    </form>

                    @if ($document->comments->isEmpty())
                        <p class="small text-center py-3 mb-0" style="color: var(--ta-text-muted);">
                            Aún no hay comentarios en este documento.
                        </p>
                    @else
                        <div class="d-flex flex-column gap-3">
                            @foreach ($document->comments as $comment)
                                <div class="rounded-3 p-3" style="border: 1px solid var(--ta-border-light); background-color: var(--ta-bg-subtle);">
                                    <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                                        <div>
                                            <span class="fw-semibold small" style="color: var(--ta-text-primary);">
                                                {{ $comment->user?->name ?? 'Usuario' }}
                                            </span>
                                            <span class="ms-2" style="font-size: 0.75rem; color: var(--ta-text-muted);">
                                                {{ $comment->created_at?->format('d/m/Y H:i') }}
                                            </span>
                                        </div>

                                        @if ((int) $comment->user_id === (int) auth()->id() || auth()->user()->hasRole('Administrador'))
                                            <form method="POST" action="{{ route('comments.destroy', $comment) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-link text-danger p-0" style="height: auto;" title="Eliminar comentario">
                                                    <i data-lucide="trash-2" class="ta-icon-xs"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                    <p class="small mb-0" style="color: var(--ta-text-body); white-space: pre-line;">{{ $comment->body }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
