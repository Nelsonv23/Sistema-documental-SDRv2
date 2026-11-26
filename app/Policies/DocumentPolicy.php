<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\Folder;
use App\Models\User;
use App\Services\FolderPermissionService;

class DocumentPolicy
{
    public function __construct(
        private readonly FolderPermissionService $permissions,
    ) {}

    public function view(User $user, Document $document): bool
    {
        if ($document->status !== Document::STATUS_ACTIVE || $document->deleted_at !== null) {
            return false;
        }

        $folder = $document->folder;

        return $folder !== null && $this->permissions->canAccess($user, $folder);
    }

    public function download(User $user, Document $document): bool
    {
        return $this->view($user, $document);
    }

    public function create(User $user, Folder $folder): bool
    {
        return $this->permissions->canAccess($user, $folder);
    }

    public function delete(User $user, Document $document): bool
    {
        if (! $this->view($user, $document)) {
            return false;
        }

        if ($user->hasRole(['Administrador', 'Jefatura'])) {
            return true;
        }

        return (int) $document->created_by === (int) $user->id;
    }

    public function restore(User $user, Document $document): bool
    {
        return $user->is_active !== false && $user->hasRole('Administrador');
    }
}
