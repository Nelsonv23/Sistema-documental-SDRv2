<?php

namespace App\Services;

use App\Models\Folder;
use App\Models\FolderPermission;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

class FolderPermissionService
{
    public function canAccess(User $user, Folder $folder): bool
    {
        if ($user->is_active === false) {
            return false;
        }

        if ($user->hasRole('Administrador')) {
            return true;
        }

        $ancestorIds = $this->extractAncestorIds($folder);
        $roleIds = $this->resolveUserRoleIds($user);

        $accessValues = FolderPermission::query()
            ->whereIn('folder_id', $ancestorIds)
            ->where(fn (Builder $query) => $this->scopeUserOrRoles($query, $user->id, $roleIds))
            ->pluck('access');

        if ($accessValues->contains(FolderPermission::ACCESS_DENY)) {
            return false;
        }

        return $accessValues->contains(FolderPermission::ACCESS_ALLOW);
    }

    /**
     * @return array<int, int>
     */
    public function accessibleFolderIds(User $user): array
    {
        if ($user->is_active === false) {
            return [];
        }

        if ($user->hasRole('Administrador')) {
            return Folder::query()
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
        }

        $roleIds = $this->resolveUserRoleIds($user);

        $rules = FolderPermission::query()
            ->join('folders', 'folders.id', '=', 'folder_permissions.folder_id')
            ->where(fn (Builder $query) => $this->scopeUserOrRoles(
                $query,
                $user->id,
                $roleIds,
                'folder_permissions.'
            ))
            ->get(['folder_permissions.access', 'folders.path']);

        $allowPaths = $rules
            ->where('access', FolderPermission::ACCESS_ALLOW)
            ->pluck('path')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($allowPaths === []) {
            return [];
        }

        $denyPaths = $rules
            ->where('access', FolderPermission::ACCESS_DENY)
            ->pluck('path')
            ->filter()
            ->unique()
            ->values()
            ->all();

        return Folder::query()
            ->where(function (Builder $query) use ($allowPaths): void {
                foreach ($allowPaths as $allowPath) {
                    $query->orWhere('path', 'like', $allowPath.'%');
                }
            })
            ->when($denyPaths !== [], function (Builder $query) use ($denyPaths): void {
                foreach ($denyPaths as $denyPath) {
                    $query->where('path', 'not like', $denyPath.'%');
                }
            })
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public function grantAccess(Folder $folder, User|Role|string $subject, ?User $actor = null): FolderPermission
    {
        return $this->setAccess($folder, $subject, FolderPermission::ACCESS_ALLOW, $actor);
    }

    public function denyAccess(Folder $folder, User|Role|string $subject, ?User $actor = null): FolderPermission
    {
        return $this->setAccess($folder, $subject, FolderPermission::ACCESS_DENY, $actor);
    }

    public function revokeAccess(Folder $folder, User|Role|string $subject): int
    {
        [$subjectType, $subjectId] = $this->resolveSubject($subject);

        return FolderPermission::query()
            ->where('folder_id', $folder->id)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->delete();
    }

    private function setAccess(Folder $folder, User|Role|string $subject, string $access, ?User $actor = null): FolderPermission
    {
        [$subjectType, $subjectId] = $this->resolveSubject($subject);

        return FolderPermission::updateOrCreate(
            [
                'folder_id' => $folder->id,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
            ],
            [
                'access' => $access,
                'created_by' => $actor?->id,
            ]
        );
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function resolveSubject(User|Role|string $subject): array
    {
        if ($subject instanceof User) {
            return [FolderPermission::SUBJECT_USER, (int) $subject->id];
        }

        if ($subject instanceof Role) {
            return [FolderPermission::SUBJECT_ROLE, (int) $subject->id];
        }

        $role = Role::findByName($subject, 'web');

        return [FolderPermission::SUBJECT_ROLE, (int) $role->id];
    }

    /**
     * @return array<int, int>
     */
    private function resolveUserRoleIds(User $user): array
    {
        if ($user->relationLoaded('roles')) {
            return $user->roles->pluck('id')->map(fn ($id): int => (int) $id)->all();
        }

        return $user->roles()->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    /**
     * @return array<int, int>
     */
    private function extractAncestorIds(Folder $folder): array
    {
        $path = trim((string) $folder->path, '/');

        if ($path !== '') {
            return array_values(array_map('intval', explode('/', $path)));
        }

        return [(int) $folder->id];
    }

    /**
     * @param  array<int, int>  $roleIds
     */
    private function scopeUserOrRoles(Builder $query, int $userId, array $roleIds, string $prefix = ''): void
    {
        $query->where(function (Builder $q) use ($userId, $prefix): void {
            $q->where($prefix.'subject_type', FolderPermission::SUBJECT_USER)
                ->where($prefix.'subject_id', $userId);
        });

        if ($roleIds !== []) {
            $query->orWhere(function (Builder $q) use ($roleIds, $prefix): void {
                $q->where($prefix.'subject_type', FolderPermission::SUBJECT_ROLE)
                    ->whereIn($prefix.'subject_id', $roleIds);
            });
        }
    }
}
