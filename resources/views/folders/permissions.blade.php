@extends('layouts.app')

@section('title', 'Permisos — '.$folder->name)

@section('breadcrumbs')
    <li><a href="{{ route('home') }}">Inicio</a></li>
    @foreach ($breadcrumbs as $crumb)
        <li><i data-lucide="chevron-right" class="ta-icon-xs"></i></li>
        <li>
            @if ($crumb['accessible'])
                <a href="{{ route('folders.show', $crumb['folder']) }}">{{ $crumb['folder']->name }}</a>
            @else
                <span>{{ $crumb['folder']->name }}</span>
            @endif
        </li>
    @endforeach
    <li><i data-lucide="chevron-right" class="ta-icon-xs"></i></li>
    <li class="active" aria-current="page">Permisos</li>
@endsection

@section('page-title', 'Permisos de carpeta: '.$folder->name)
@section('page-subtitle', 'Regla de seguridad: un DENY en cualquier nivel superior bloquea el acceso hacia abajo sin excepciones.')

@section('page-actions')
    <a href="{{ route('folders.show', $folder) }}" class="btn btn-outline-secondary">
        <i data-lucide="arrow-left" class="ta-icon-sm"></i>
        <span>Volver a la carpeta</span>
    </a>
@endsection

@section('content')
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="ta-card">
                <div class="ta-card-header">
                    <h2 class="ta-card-title">Asignar regla de acceso</h2>
                    <p class="ta-card-subtitle">Define permisos explícitos por rol o usuario.</p>
                </div>
                <form method="POST"
                      action="{{ route('folders.permissions.store', $folder) }}"
                      x-data="{ subjectType: 'role' }">
                    @csrf

                    <div class="ta-card-body">
                        <div class="mb-3">
                            <label for="subject_type" class="form-label">Aplicar a</label>
                            <select id="subject_type"
                                    name="subject_type"
                                    class="form-select"
                                    x-model="subjectType">
                                <option value="role">Rol corporativo</option>
                                <option value="user">Usuario específico</option>
                            </select>
                        </div>

                        <div class="mb-3" x-show="subjectType === 'role'">
                            <label for="role_subject_id" class="form-label">Rol</label>
                            <select id="role_subject_id"
                                    :name="subjectType === 'role' ? 'subject_id' : null"
                                    class="form-select"
                                    :disabled="subjectType !== 'role'">
                                @foreach ($roles->where('name', '!=', 'Administrador') as $role)
                                    <option value="{{ $role->id }}">{{ $role->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3" x-cloak x-show="subjectType === 'user'">
                            <label for="user_subject_id" class="form-label">Usuario</label>
                            <select id="user_subject_id"
                                    :name="subjectType === 'user' ? 'subject_id' : null"
                                    class="form-select"
                                    :disabled="subjectType !== 'user'">
                                @foreach ($users as $listedUser)
                                    <option value="{{ $listedUser->id }}">{{ $listedUser->name }} ({{ $listedUser->email }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="access" class="form-label">Tipo de regla</label>
                            <select id="access" name="access" class="form-select">
                                <option value="allow">Permitir (ALLOW)</option>
                                <option value="deny">Denegar (DENY absoluto hacia abajo)</option>
                            </select>
                        </div>
                    </div>

                    <div class="ta-card-footer">
                        <button type="submit" class="btn btn-primary w-100">
                            <i data-lucide="save" class="ta-icon-sm"></i>
                            <span>Guardar regla</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-8 d-flex flex-column gap-4">
            <div class="ta-card">
                <div class="ta-card-header d-flex justify-content-between align-items-center">
                    <h2 class="ta-card-title">Reglas directas en esta carpeta</h2>
                    <span class="ta-badge ta-badge-neutral">{{ $directPermissions->count() }}</span>
                </div>
                @if ($directPermissions->isEmpty())
                    <div class="ta-card-body small" style="color: var(--ta-text-muted);">
                        No hay reglas directas configuradas en esta carpeta. Se aplicará la herencia de carpetas superiores o denegación por defecto.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table ta-table align-middle">
                            <thead>
                                <tr>
                                    <th>Tipo</th>
                                    <th>Sujeto</th>
                                    <th>Regla</th>
                                    <th class="text-end">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($directPermissions as $perm)
                                    @php
                                        $subjectLabel = $perm->subject_type === 'role'
                                            ? ($rolesById[$perm->subject_id]->name ?? 'Rol #'.$perm->subject_id)
                                            : ($usersById[$perm->subject_id]->name ?? 'Usuario #'.$perm->subject_id);
                                    @endphp
                                    <tr>
                                        <td style="color: var(--ta-text-muted);">{{ $perm->subject_type === 'role' ? 'Rol' : 'Usuario' }}</td>
                                        <td class="fw-medium" style="color: var(--ta-text-primary);">{{ $subjectLabel }}</td>
                                        <td>
                                            @if ($perm->access === 'allow')
                                                <span class="ta-badge ta-badge-success">ALLOW</span>
                                            @else
                                                <span class="ta-badge ta-badge-danger">DENY</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <form method="POST" action="{{ route('folders.permissions.destroy', [$folder, $perm]) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i data-lucide="trash-2" class="ta-icon-xs"></i>
                                                    <span>Revocar</span>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="ta-card">
                <div class="ta-card-header">
                    <h2 class="ta-card-title">Reglas heredadas desde carpetas superiores</h2>
                    <p class="ta-card-subtitle">Cualquier DENY heredado bloquea el acceso hacia abajo sin excepciones.</p>
                </div>
                @if ($inheritedPermissions->isEmpty())
                    <div class="ta-card-body small" style="color: var(--ta-text-muted);">
                        No existen reglas heredadas desde ancestros.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table ta-table align-middle">
                            <thead>
                                <tr>
                                    <th>Carpeta origen</th>
                                    <th>Sujeto</th>
                                    <th>Regla</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($inheritedPermissions as $perm)
                                    @php
                                        $subjectLabel = $perm->subject_type === 'role'
                                            ? ($rolesById[$perm->subject_id]->name ?? 'Rol #'.$perm->subject_id)
                                            : ($usersById[$perm->subject_id]->name ?? 'Usuario #'.$perm->subject_id);
                                    @endphp
                                    <tr>
                                        <td>{{ $perm->folder?->name ?? 'Carpeta' }}</td>
                                        <td class="fw-medium" style="color: var(--ta-text-primary);">{{ $subjectLabel }} ({{ $perm->subject_type === 'role' ? 'Rol' : 'Usuario' }})</td>
                                        <td>
                                            @if ($perm->access === 'allow')
                                                <span class="ta-badge ta-badge-success">ALLOW</span>
                                            @else
                                                <span class="ta-badge ta-badge-danger">DENY absoluto</span>
                                            @endif
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
@endsection
