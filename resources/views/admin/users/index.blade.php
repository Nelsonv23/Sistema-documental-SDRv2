@extends('layouts.app')

@section('title', 'Usuarios y roles')

@section('breadcrumbs')
    <li><a href="{{ route('home') }}">Inicio</a></li>
    <li><i data-lucide="chevron-right" class="ta-icon-xs"></i></li>
    <li class="active" aria-current="page">Usuarios y roles</li>
@endsection

@section('page-title', 'Usuarios y roles')
@section('page-subtitle', 'Administra las cuentas corporativas, asigna roles y controla el acceso al sistema.')

@section('page-actions')
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
        <i data-lucide="user-plus" class="ta-icon-sm"></i>
        <span>Nuevo usuario</span>
    </a>
@endsection

@section('content')
    <div class="ta-card mb-4">
        <div class="ta-card-header">
            <h2 class="ta-card-title">Filtros de búsqueda</h2>
        </div>
        <div class="ta-card-body">
            <form method="GET" action="{{ route('admin.users.index') }}" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label for="q" class="form-label">Buscar usuario</label>
                    <input id="q"
                           type="text"
                           name="q"
                           value="{{ $filters['q'] }}"
                           class="form-control"
                           placeholder="Nombre o correo electrónico">
                </div>

                <div class="col-md-3">
                    <label for="role" class="form-label">Rol</label>
                    <select id="role" name="role" class="form-select">
                        <option value="">Todos los roles</option>
                        @foreach ($roles as $roleOption)
                            <option value="{{ $roleOption }}" @selected($filters['role'] === $roleOption)>
                                {{ $roleOption }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="status" class="form-label">Estado</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">Todos</option>
                        <option value="active" @selected($filters['status'] === 'active')>Activos</option>
                        <option value="inactive" @selected($filters['status'] === 'inactive')>Inactivos</option>
                    </select>
                </div>

                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i data-lucide="search" class="ta-icon-sm"></i>
                        <span>Filtrar</span>
                    </button>
                    @if ($filters['q'] !== '' || $filters['role'] !== '' || $filters['status'] !== '')
                        <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary" title="Limpiar filtros">
                            <i data-lucide="x" class="ta-icon-sm"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="ta-card">
        <div class="ta-card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <h2 class="ta-card-title">Cuentas registradas</h2>
                <p class="ta-card-subtitle">Gestiona roles de acceso y estado operativo de cada colaborador.</p>
            </div>
            <span class="ta-badge ta-badge-brand">{{ $users->total() }} {{ $users->total() === 1 ? 'usuario' : 'usuarios' }}</span>
        </div>

        @if ($users->isEmpty())
            <div class="ta-empty-state">
                <div class="ta-empty-state__icon">
                    <i data-lucide="user-search" class="ta-icon"></i>
                </div>
                <h5 class="mb-1">No se encontraron usuarios</h5>
                <p class="small mb-3" style="color: var(--ta-text-muted);">
                    Ajusta los filtros de búsqueda o crea una nueva cuenta corporativa.
                </p>
                <a href="{{ route('admin.users.create') }}" class="btn btn-primary btn-sm">
                    <i data-lucide="user-plus" class="ta-icon-xs"></i>
                    <span>Crear usuario</span>
                </a>
            </div>
        @else
            <div class="table-responsive">
                <table class="table ta-table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th>Último acceso</th>
                            <th>Estado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $listedUser)
                            @php
                                $listedRole = $listedUser->getRoleNames()->first() ?? 'Sin rol';
                                $isSelf = $listedUser->is(auth()->user());
                            @endphp
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="ta-avatar-initials ta-avatar-initials--sm">{{ $listedUser->initials() }}</span>
                                        <div>
                                            <div class="fw-semibold" style="color: var(--ta-text-primary);">
                                                {{ $listedUser->name }}
                                                @if ($isSelf)
                                                    <span class="ta-badge ta-badge-brand ms-1">Tú</span>
                                                @endif
                                            </div>
                                            <div class="small" style="color: var(--ta-text-muted);">{{ $listedUser->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('admin.users.update-role', $listedUser) }}" class="d-flex align-items-center gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <select name="role"
                                                class="form-select form-select-sm"
                                                style="min-width: 155px;"
                                                aria-label="Rol de {{ $listedUser->name }}"
                                                @disabled($isSelf)>
                                            @foreach ($roles as $roleOption)
                                                <option value="{{ $roleOption }}" @selected($listedRole === $roleOption)>
                                                    {{ $roleOption }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @unless ($isSelf)
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                Guardar
                                            </button>
                                        @endunless
                                    </form>
                                </td>
                                <td style="color: var(--ta-text-muted);">
                                    {{ $listedUser->last_login_at ? $listedUser->last_login_at->format('d/m/Y H:i') : 'Sin ingresos' }}
                                </td>
                                <td>
                                    @if ($listedUser->is_active)
                                        <span class="ta-badge ta-badge-success">Activo</span>
                                    @else
                                        <span class="ta-badge ta-badge-neutral">Inactivo</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if ($isSelf)
                                        <span class="small" style="color: var(--ta-text-muted);">Cuenta actual protegida</span>
                                    @else
                                        <form method="POST" action="{{ route('admin.users.toggle-status', $listedUser) }}" class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            @if ($listedUser->is_active)
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i data-lucide="user-x" class="ta-icon-xs"></i>
                                                    <span>Desactivar</span>
                                                </button>
                                            @else
                                                <button type="submit" class="btn btn-sm btn-outline-success">
                                                    <i data-lucide="user-check" class="ta-icon-xs"></i>
                                                    <span>Activar</span>
                                                </button>
                                            @endif
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($users->hasPages())
                <div class="ta-card-footer">
                    {{ $users->links() }}
                </div>
            @endif
        @endif
    </div>
@endsection
