@extends('layouts.app')

@section('title', 'Nuevo usuario')

@section('breadcrumbs')
    <li><a href="{{ route('home') }}">Inicio</a></li>
    <li><i data-lucide="chevron-right" class="ta-icon-xs"></i></li>
    <li><a href="{{ route('admin.users.index') }}">Usuarios y roles</a></li>
    <li><i data-lucide="chevron-right" class="ta-icon-xs"></i></li>
    <li class="active" aria-current="page">Nuevo usuario</li>
@endsection

@section('page-title', 'Nuevo usuario')
@section('page-subtitle', 'Crea una cuenta corporativa y asigna su rol inicial en el sistema.')

@section('page-actions')
    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">
        <i data-lucide="arrow-left" class="ta-icon-sm"></i>
        <span>Volver al listado</span>
    </a>
@endsection

@section('content')
    <div class="ta-card">
        <div class="ta-card-header">
            <h2 class="ta-card-title">Alta de usuario corporativo</h2>
            <p class="ta-card-subtitle">Completa los datos personales, define la contraseña inicial y asigna un rol.</p>
        </div>

        <form method="POST" action="{{ route('admin.users.store') }}" novalidate>
            @csrf

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
                        <h3 class="h6 fw-semibold mb-3" style="color: var(--ta-text-primary);">Datos de la cuenta</h3>

                        <div class="mb-3">
                            <label for="name" class="form-label">Nombre completo</label>
                            <input id="name"
                                   type="text"
                                   name="name"
                                   value="{{ old('name') }}"
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
                                   value="{{ old('email') }}"
                                   class="form-control @error('email') is-invalid @enderror"
                                   placeholder="maria.gonzalez@empresa.cl"
                                   required>
                        </div>

                        <div class="mb-3">
                            <label for="role" class="form-label">Rol asignado</label>
                            <select id="role"
                                    name="role"
                                    class="form-select @error('role') is-invalid @enderror"
                                    required>
                                @foreach ($roles as $roleOption)
                                    <option value="{{ $roleOption }}" @selected(old('role', 'Colaborador') === $roleOption)>
                                        {{ $roleOption }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <h3 class="h6 fw-semibold mb-3" style="color: var(--ta-text-primary);">Seguridad y acceso</h3>

                        <div class="mb-3">
                            <label for="password" class="form-label">Contraseña inicial</label>
                            <input id="password"
                                   type="password"
                                   name="password"
                                   class="form-control @error('password') is-invalid @enderror"
                                   placeholder="Mínimo 8 caracteres"
                                   required>
                        </div>

                        <div class="mb-3">
                            <label for="password_confirmation" class="form-label">Confirmar contraseña</label>
                            <input id="password_confirmation"
                                   type="password"
                                   name="password_confirmation"
                                   class="form-control"
                                   placeholder="Repite la contraseña inicial"
                                   required>
                        </div>

                        <div class="pt-2">
                            <input type="hidden" name="is_active" value="0">
                            <div class="form-check">
                                <input id="is_active"
                                       type="checkbox"
                                       name="is_active"
                                       value="1"
                                       class="form-check-input"
                                       @checked(old('is_active', '1') == '1')>
                                <label for="is_active" class="form-check-label">
                                    Cuenta activa (permitir inicio de sesión inmediato)
                                </label>
                            </div>
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
                    <span>Crear usuario</span>
                </button>
            </div>
        </form>
    </div>
@endsection
