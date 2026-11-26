<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class DocumentSearchService
{
    public function __construct(
        private readonly FolderPermissionService $permissions,
    ) {}

    /**
     * @param  array{
     *     q?: string|null,
     *     folder_id?: int|string|null,
     *     include_subfolders?: bool,
     *     extension?: string|null,
     *     created_by?: int|string|null,
     *     date_from?: string|null,
     *     date_to?: string|null,
     * }  $filters
     */
    public function search(User $user, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $accessibleIds = $this->permissions->accessibleFolderIds($user);

        if ($accessibleIds === []) {
            return Document::query()->whereRaw('1 = 0')->paginate($perPage);
        }

        $folderIds = $accessibleIds;

        if (! empty($filters['folder_id'])) {
            $targetFolderId = (int) $filters['folder_id'];

            if (! in_array($targetFolderId, $accessibleIds, true)) {
                return Document::query()->whereRaw('1 = 0')->paginate($perPage);
            }

            $includeSubfolders = array_key_exists('include_subfolders', $filters)
                ? (bool) $filters['include_subfolders']
                : true;

            if ($includeSubfolders) {
                $targetFolder = Folder::find($targetFolderId);
                if (! $targetFolder) {
                    return Document::query()->whereRaw('1 = 0')->paginate($perPage);
                }

                $subtreeIds = Folder::query()
                    ->where('path', 'like', $targetFolder->path.'%')
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->all();

                $folderIds = array_values(array_intersect($accessibleIds, $subtreeIds));

                if ($folderIds === []) {
                    return Document::query()->whereRaw('1 = 0')->paginate($perPage);
                }
            } else {
                $folderIds = [$targetFolderId];
            }
        }

        $query = Document::query()
            ->active()
            ->with(['folder', 'currentVersion', 'creator'])
            ->whereIn('documents.folder_id', $folderIds);

        $searchTerm = trim((string) ($filters['q'] ?? ''));
        if ($searchTerm !== '') {
            $tokens = array_values(array_filter(
                preg_split('/\s+/u', $searchTerm) ?: [],
                fn (string $token): bool => $token !== ''
            ));

            $booleanTokens = [];
            foreach ($tokens as $token) {
                $clean = preg_replace('/[+\-><()~*"@]+/u', '', $token) ?? '';
                if (mb_strlen($clean) >= 3) {
                    $booleanTokens[] = '+'.$clean.'*';
                }
            }
            $booleanQuery = implode(' ', $booleanTokens);

            $query->where(function (Builder $subQuery) use ($booleanQuery, $tokens): void {
                if ($booleanQuery !== '') {
                    $subQuery->whereRaw('MATCH(documents.name) AGAINST(? IN BOOLEAN MODE)', [$booleanQuery]);
                }

                $subQuery->orWhere(function (Builder $likeQuery) use ($tokens): void {
                    foreach ($tokens as $token) {
                        $likeQuery->where('documents.name', 'like', '%'.$this->escapeLike($token).'%');
                    }
                });
            });
        }

        $extension = ltrim(strtolower(trim((string) ($filters['extension'] ?? ''))), '.');
        if ($extension !== '') {
            $query->where('documents.extension', $extension);
        }

        if (! empty($filters['created_by'])) {
            $query->where('documents.created_by', (int) $filters['created_by']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('documents.created_at', '>=', (string) $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('documents.created_at', '<=', (string) $filters['date_to']);
        }

        return $query
            ->orderByDesc('documents.updated_at')
            ->orderBy('documents.name')
            ->paginate($perPage)
            ->withQueryString();
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
