@php
    $homeHref = \Illuminate\Support\Facades\Route::has('home') ? route('home') : url('/');
    $foldersHref = \Illuminate\Support\Facades\Route::has('folders.index') ? route('folders.index') : $homeHref;
    $searchHref = \Illuminate\Support\Facades\Route::has('documents.search') ? route('documents.search') : '#';
    $profileHref = \Illuminate\Support\Facades\Route::has('profile.edit') ? route('profile.edit') : '#';
    $usersHref = \Illuminate\Support\Facades\Route::has('admin.users.index') ? route('admin.users.index') : '#';
    $trashHref = \Illuminate\Support\Facades\Route::has('admin.trash.index') ? route('admin.trash.index') : '#';
    $auditHref = \Illuminate\Support\Facades\Route::has('admin.audit-logs.index') ? route('admin.audit-logs.index') : '#';
@endphp

<aside class="ta-sidebar" :class="{ 'is-open': mobileSidebarOpen }" aria-label="Menú principal">
    <div class="ta-sidebar-header">
        <a class="ta-brand-link w-100 justify-content-center px-2" href="{{ $homeHref }}" style="max-width: 100%;" title="SDR v2">
            <img src="{{ asset('images/brand/logo-version-azul.png') }}"
                 alt="SDR v2"
                 style="max-height: 38px; max-width: 100%; width: auto; height: auto; object-fit: contain; display: block;">
        </a>
    </div>

    <nav class="ta-sidebar-nav">
        <div class="ta-nav-group-label">Menú</div>

        <a class="ta-nav-link {{ request()->routeIs('home') || request()->routeIs('folders.*') || request()->routeIs('documents.show') ? 'active' : '' }}"
           href="{{ $foldersHref }}"
           title="Explorador">
            <i data-lucide="folder-open" class="ta-icon"></i>
            <span class="ta-nav-link-text">Explorador</span>
        </a>

        <a class="ta-nav-link {{ request()->routeIs('documents.search') ? 'active' : '' }}"
           href="{{ $searchHref }}"
           title="Buscar documentos">
            <i data-lucide="search" class="ta-icon"></i>
            <span class="ta-nav-link-text">Buscar documentos</span>
        </a>

        @role('Administrador')
            <div class="ta-nav-group-label">Administración</div>

            <a class="ta-nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}"
               href="{{ $usersHref }}"
               title="Usuarios y roles">
                <i data-lucide="users" class="ta-icon"></i>
                <span class="ta-nav-link-text">Usuarios y roles</span>
            </a>

            <a class="ta-nav-link {{ request()->routeIs('admin.trash.*') ? 'active' : '' }}"
               href="{{ $trashHref }}"
               title="Papelera">
                <i data-lucide="trash-2" class="ta-icon"></i>
                <span class="ta-nav-link-text">Papelera</span>
            </a>

            <a class="ta-nav-link {{ request()->routeIs('admin.audit-logs.*') ? 'active' : '' }}"
               href="{{ $auditHref }}"
               title="Auditoría">
                <i data-lucide="shield-check" class="ta-icon"></i>
                <span class="ta-nav-link-text">Auditoría</span>
            </a>
        @endrole

        <div class="ta-nav-group-label">Cuenta</div>

        <a class="ta-nav-link {{ request()->routeIs('profile.*') ? 'active' : '' }}"
           href="{{ $profileHref }}"
           title="Mi Perfil">
            <i data-lucide="user-cog" class="ta-icon"></i>
            <span class="ta-nav-link-text">Mi Perfil</span>
        </a>
    </nav>
</aside>
