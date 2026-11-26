@extends('layouts.app')

@section('title', 'Editar usuario')

@section('breadcrumbs')
    <li><a href="{{ route('home') }}">Inicio</a></li>
    <li><i data-lucide="chevron-right" class="ta-icon-xs"></i></li>
    <li><a href="{{ route('admin.users.index') }}">Usuarios y roles</a></li>
    <li><i data-lucide="chevron-right" class="ta-icon-xs"></i></li>
    <li class="active" aria-current="page">Editar usuario</li>
@endsection

@section('page-title', 'Editar usuario')
@section('page-subtitle', 'Actualiza el nombre, el correo electrónico o asigna una nueva contraseña para la cuenta seleccionada.')

@section('page-actions')
    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">
        <i data-lucide="arrow-left" class="ta-icon-sm"></i>
        <span>Volver al listado</span>
    </a>
@endsection

@section('content')
    <div class="ta-card">
        <div class="ta-card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <h2 class="ta-card-title">Edición de cuenta corporativa</h2>
                <p class="ta-card-subtitle">
                    Rol actual: {{ $editedUser->getRoleNames()->first() ?? 'Sin rol' }} ·
                    Estado: {{ $editedUser->is_active ? 'Activo' : 'Inactivo' }}
                </p>
            </div>
            @if ($editedUser->is(auth()->user()))
                <span class="ta-badge ta-badge-brand">Tu cuenta</span>
            @endif
        </div>

        <form method="POST" action="{{ route('admin.users.update', $editedUser) }}" novalidate>
            @csrf
            @method('PATCH')

            <div class="ta-card-body">
                @if ($errors->any())
                    <div class="alert alert-danger border-0 mb-4" role="alert">
                        <div class="fw-semibold mb-1">Revisa los campos del formulario:</div>
                        <ul class="mb-0 ps-3 small">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="row g-4">
                    <div class="col-md-6">
                        <h3 class="h6 fw-semibold mb-3" style="color: var(--ta-text-primary);">Datos básicos</h3>

                        <div class="mb-3">
                            <label for="name" class="form-label">Nombre completo</label>
                            <input id="name"
                                   type="text"
                                   name="name"
                                   value="{{ old('name', $editedUser->name) }}"
                                   class="form-control @error('name') is-invalid @enderror"
                                   placeholder="Ej. María González"
                                   required
                                   autofocus>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Correo electrónico</label>
                            <input id="email"
                                   type="email"
                                   name="email"
                                   value="{{ old('email', $editedUser->email) }}"
                                   class="form-control @error('email') is-invalid @enderror"
                                   placeholder="maria.gonzalez@empresa.cl"
                                   required>
                            <div class="form-text">
                                Si modificas el correo o la contraseña, se regenerará el token de sesión persistente del usuario.
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <h3 class="h6 fw-semibold mb-3" style="color: var(--ta-text-primary);">Cambio de contraseña (opcional)</h3>

                        <div class="mb-3">
                            <label for="password" class="form-label">Nueva contraseña</label>
                            <input id="password"
                                   type="password"
                                   name="password"
                                   class="form-control @error('password') is-invalid @enderror"
                                   placeholder="Déjalo vacío para mantener la actual"
                                   autocomplete="new-password">
                        </div>

                        <div class="mb-3">
                            <label for="password_confirmation" class="form-label">Confirmar nueva contraseña</label>
                            <input id="password_confirmation"
                                   type="password"
                                   name="password_confirmation"
                                   class="form-control"
                                   placeholder="Repite la nueva contraseña"
                                   autocomplete="new-password">
                        </div>
                    </div>
                </div>
            </div>

            <div class="ta-card-footer">
                <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">
                    Cancelar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="save" class="ta-icon-sm"></i>
                    <span>Guardar cambios</span>
                </button>
            </div>
        </form>
    </div>
@endsection
