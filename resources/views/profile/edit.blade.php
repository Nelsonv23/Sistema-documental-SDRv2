@extends('layouts.app')

@section('title', 'Mi Perfil')

@section('breadcrumbs')
    <li><a href="{{ route('home') }}">Inicio</a></li>
    <li><i data-lucide="chevron-right" class="ta-icon-xs"></i></li>
    <li class="active" aria-current="page">Mi Perfil</li>
@endsection

@section('page-title', 'Mi Perfil')
@section('page-subtitle', 'Administra tu información personal, foto de perfil y contraseña de acceso.')

@section('content')
    @php
        $assignedRole = $user->getRoleNames()->first() ?? 'Sin rol';
        $avatarVersion = $user->updated_at?->timestamp ?? time();
    @endphp

    <div class="row g-4">
        <div class="col-xl-7 d-flex flex-column gap-4">
            {{-- Tarjeta 1: Información de la cuenta --}}
            <div class="ta-card">
                <div class="ta-card-header">
                    <h2 class="ta-card-title">Información de la cuenta</h2>
                    <p class="ta-card-subtitle">
                        Actualiza tu nombre visible en documentos, comentarios y registros de auditoría.
                    </p>
                </div>

                <form method="POST" action="{{ route('profile.update') }}">
                    @csrf
                    @method('PATCH')

                    <div class="ta-card-body">
                        @if ($errors->has('name'))
                            <div class="alert alert-danger border-0 small py-2 mb-3" role="alert">
                                {{ $errors->first('name') }}
                            </div>
                        @endif

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label">Nombre completo</label>
                                <input id="name"
                                       type="text"
                                       name="name"
                                       value="{{ old('name', $user->name) }}"
                                       class="form-control @error('name') is-invalid @enderror"
                                       placeholder="Tu nombre y apellido"
                                       required>
                            </div>

                            <div class="col-md-6">
                                <label for="email" class="form-label">Correo electrónico</label>
                                <input id="email"
                                       type="email"
                                       value="{{ $user->email }}"
                                       class="form-control"
                                       readonly
                                       disabled>
                            </div>

                            <div class="col-md-6">
                                <label for="role_readonly" class="form-label">Rol asignado</label>
                                <input id="role_readonly"
                                       type="text"
                                       value="{{ $assignedRole }}"
                                       class="form-control"
                                       readonly
                                       disabled>
                            </div>

                            <div class="col-md-6">
                                <label for="last_login_readonly" class="form-label">Último acceso</label>
                                <input id="last_login_readonly"
                                       type="text"
                                       value="{{ $user->last_login_at ? $user->last_login_at->format('d/m/Y H:i') : 'Sin ingresos previos' }}"
                                       class="form-control"
                                       readonly
                                       disabled>
                            </div>
                        </div>
                    </div>

                    <div class="ta-card-footer">
                        <button type="submit" class="btn btn-primary">
                            <i data-lucide="save" class="ta-icon-sm"></i>
                            <span>Guardar cambios</span>
                        </button>
                    </div>
                </form>
            </div>

            {{-- Tarjeta 2: Actualizar contraseña --}}
            <div class="ta-card">
                <div class="ta-card-header">
                    <h2 class="ta-card-title">Actualizar contraseña</h2>
                    <p class="ta-card-subtitle">
                        Por seguridad confirma tu contraseña actual antes de definir una nueva clave.
                    </p>
                </div>

                <form method="POST" action="{{ route('profile.password.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="ta-card-body">
                        @if ($errors->updatePassword->any())
                            <div class="alert alert-danger border-0 small py-2 mb-3" role="alert">
                                <ul class="mb-0 ps-3">
                                    @foreach ($errors->updatePassword->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="row g-3">
                            <div class="col-12">
                                <label for="current_password" class="form-label">Contraseña actual</label>
                                <input id="current_password"
                                       type="password"
                                       name="current_password"
                                       class="form-control @if($errors->updatePassword->has('current_password')) is-invalid @endif"
                                       placeholder="Ingresa tu contraseña actual"
                                       autocomplete="current-password"
                                       required>
                            </div>

                            <div class="col-md-6">
                                <label for="password" class="form-label">Nueva contraseña</label>
                                <input id="password"
                                       type="password"
                                       name="password"
                                       class="form-control @if($errors->updatePassword->has('password')) is-invalid @endif"
                                       placeholder="Mínimo 8 caracteres"
                                       autocomplete="new-password"
                                       required>
                            </div>

                            <div class="col-md-6">
                                <label for="password_confirmation" class="form-label">Confirmar nueva contraseña</label>
                                <input id="password_confirmation"
                                       type="password"
                                       name="password_confirmation"
                                       class="form-control"
                                       placeholder="Repite la nueva contraseña"
                                       autocomplete="new-password"
                                       required>
                            </div>
                        </div>
                    </div>

                    <div class="ta-card-footer">
                        <button type="submit" class="btn btn-primary">
                            <i data-lucide="key-round" class="ta-icon-sm"></i>
                            <span>Actualizar contraseña</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Tarjeta 3: Foto de perfil --}}
        <div class="col-xl-5">
            <div class="ta-card">
                <div class="ta-card-header">
                    <h2 class="ta-card-title">Foto de perfil</h2>
                    <p class="ta-card-subtitle">
                        Personaliza tu avatar corporativo. Tu imagen se resguarda en almacenamiento privado.
                    </p>
                </div>

                <div class="ta-card-body">
                    @if ($errors->has('avatar'))
                        <div class="alert alert-danger border-0 small py-2 mb-3" role="alert">
                            {{ $errors->first('avatar') }}
                        </div>
                    @endif

                    <div class="d-flex align-items-center gap-3 pb-4 mb-4" style="border-bottom: 1px solid var(--ta-border-light);">
                        <img src="{{ route('users.avatar', $user) }}?v={{ $avatarVersion }}"
                             alt="{{ $user->name }}"
                             class="rounded-circle"
                             style="width: 72px; height: 72px; object-fit: cover; border: 2px solid var(--ta-border-light); flex-shrink: 0;">
                        <div>
                            <div class="fw-semibold" style="color: var(--ta-text-primary); font-size: 0.95rem;">{{ $user->name }}</div>
                            <div class="mt-1">
                                <span class="ta-badge ta-badge-brand">{{ $assignedRole }}</span>
                            </div>
                            <div class="small mt-1" style="color: var(--ta-text-muted);">{{ $user->email }}</div>
                        </div>
                    </div>

                    <form method="POST"
                          action="{{ route('profile.avatar.update') }}"
                          enctype="multipart/form-data"
                          x-data="{ hasFile: false, clientError: '' }"
                          x-on:submit="if (!hasFile) { $event.preventDefault(); clientError = 'Selecciona una imagen válida antes de subirla.'; }">
                        @csrf
                        <div class="ta-dropzone mb-3">
                            <div class="ta-empty-state__icon mx-auto mb-2" style="width: 44px; height: 44px;">
                                <i data-lucide="image-plus" class="ta-icon"></i>
                            </div>
                            <label for="avatar" class="form-label d-block mb-2">Seleccionar nueva imagen</label>
                            <input id="avatar"
                                   type="file"
                                   name="avatar"
                                   accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                   class="form-control @error('avatar') is-invalid @enderror"
                                   x-on:change="hasFile = $event.target.files.length > 0 && $event.target.files[0].size > 0; clientError = ($event.target.files.length > 0 && $event.target.files[0].size === 0) ? 'El archivo seleccionado está vacío (0 bytes).' : '';"
                                   required>
                            <div class="form-text mt-2">JPG, PNG o WebP · Recomendado 400 × 400 px (1:1) · Máx. 2 MB</div>
                            <div class="text-danger small mt-1" x-show="clientError" x-text="clientError" x-cloak></div>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary w-100" x-bind:disabled="!hasFile">
                                <i data-lucide="upload" class="ta-icon-sm"></i>
                                <span>Subir foto</span>
                            </button>
                        </div>
                    </form>
                </div>

                @if ($user->avatar_path)
                    <div class="ta-card-footer justify-content-between">
                        <span class="small" style="color: var(--ta-text-muted);">Foto personalizada activa</span>
                        <form method="POST" action="{{ route('profile.avatar.destroy') }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i data-lucide="trash-2" class="ta-icon-xs"></i>
                                <span>Eliminar foto actual</span>
                            </button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
