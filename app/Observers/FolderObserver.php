<?php

namespace App\Observers;

use App\Models\Folder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FolderObserver
{
    public function created(Folder $folder): void
    {
        DB::transaction(function () use ($folder): void {
            $path = $this->buildPath($folder);

            $folder->forceFill(['path' => $path])->saveQuietly();
        });
    }

    public function updating(Folder $folder): void
    {
        if (! $folder->isDirty('parent_id')) {
            return;
        }

        if ($folder->parent_id === null) {
            return;
        }

        if ((int) $folder->parent_id === (int) $folder->id) {
            throw new InvalidArgumentException('Una carpeta no puede moverse dentro de sí misma.');
        }

        $targetParent = Folder::find($folder->parent_id);

        if (! $targetParent) {
            throw new InvalidArgumentException('La carpeta destino no existe.');
        }

        $currentPath = (string) ($folder->getOriginal('path') ?: $folder->path);

        if ($currentPath !== '' && str_starts_with((string) $targetParent->path, $currentPath)) {
            throw new InvalidArgumentException('No se puede mover una carpeta dentro de una de sus subcarpetas.');
        }
    }

    public function updated(Folder $folder): void
    {
        if (! $folder->wasChanged('parent_id')) {
            return;
        }

        DB::transaction(function () use ($folder): void {
            $oldPath = (string) $folder->getOriginal('path');
            $newPath = $this->buildPath($folder);

            $folder->forceFill(['path' => $newPath])->saveQuietly();

            if ($oldPath === '' || $oldPath === $newPath) {
                return;
            }

            $descendants = Folder::query()
                ->where('path', 'like', $oldPath.'%')
                ->where('id', '!=', $folder->id)
                ->lockForUpdate()
                ->get(['id', 'path']);

            $oldPathLength = strlen($oldPath);

            foreach ($descendants as $descendant) {
                $updatedPath = $newPath.substr((string) $descendant->path, $oldPathLength);
                Folder::whereKey($descendant->id)->update(['path' => $updatedPath]);
            }
        });
    }

    private function buildPath(Folder $folder): string
    {
        if ($folder->parent_id === null) {
            return '/'.$folder->id.'/';
        }

        $parentPath = (string) Folder::whereKey($folder->parent_id)->value('path');

        if ($parentPath === '') {
            return '/'.$folder->parent_id.'/'.$folder->id.'/';
        }

        return rtrim($parentPath, '/').'/'.$folder->id.'/';
    }
}
