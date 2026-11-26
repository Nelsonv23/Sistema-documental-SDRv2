<?php

namespace App\Policies;

use App\Models\Folder;
use App\Models\User;
use App\Services\FolderPermissionService;

class FolderPolicy
{
    public function __construct(
        private readonly FolderPermissionService $permissions,
    ) {}

    public function viewAny(User $user): bool
    {
        return $user->is_active !== false;
    }

    public function view(User $user, Folder $folder): bool
    {
        return $this->permissions->canAccess($user, $folder);
    }

    public function createRoot(User $user): bool
    {
        return $user->is_active !== false && $user->hasAnyRole(['Administrador', 'Jefatura']);
    }

    public function create(User $user, ?Folder $parent = null): bool
    {
        if ($parent === null) {
            return $this->createRoot($user);
        }

        return $this->permissions->canAccess($user, $parent);
    }

    public function update(User $user, Folder $folder): bool
    {
        if ($user->is_active === false) {
            return false;
        }

        if ($user->hasRole('Administrador')) {
            return true;
        }

        return $user->hasRole('Jefatura') && $this->permissions->canAccess($user, $folder);
    }

    public function managePermissions(User $user, Folder $folder): bool
    {
        return $this->update($user, $folder);
    }

    public function delete(User $user, Folder $folder): bool
    {
        if ($user->is_active === false) {
            return false;
        }

        if ($user->hasRole('Administrador')) {
            return true;
        }

        if ($user->hasRole('Jefatura')) {
            return $folder->parent_id !== null && $this->permissions->canAccess($user, $folder);
        }

        return false;
    }

    public function upload(User $user, Folder $folder): bool
    {
        return $this->permissions->canAccess($user, $folder);
    }
}
