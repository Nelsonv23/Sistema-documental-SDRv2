<?php

namespace App\Http\Controllers;

use App\Models\Folder;
use App\Models\User;
use App\Services\DocumentSearchService;
use App\Services\DocumentStorageService;
use App\Services\FolderPermissionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function __construct(
        private readonly DocumentSearchService $searchService,
        private readonly FolderPermissionService $permissions,
        private readonly DocumentStorageService $storage,
    ) {}

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'folder_id' => ['nullable', 'integer'],
            'include_subfolders' => ['nullable', 'boolean'],
            'extension' => ['nullable', 'string', 'max:20'],
            'created_by' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $filters = [
            'q' => $validated['q'] ?? '',
            'folder_id' => $validated['folder_id'] ?? null,
            'include_subfolders' => $request->has('include_subfolders')
                ? $request->boolean('include_subfolders')
                : true,
            'extension' => $validated['extension'] ?? '',
            'created_by' => $validated['created_by'] ?? null,
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
        ];

        $user = $request->user();
        $accessibleIds = $this->permissions->accessibleFolderIds($user);

        $folders = $accessibleIds === []
            ? collect()
            : Folder::query()
                ->whereIn('id', $accessibleIds)
                ->orderBy('path')
                ->get(['id', 'name', 'path']);

        $authors = User::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name']);

        $documents = $this->searchService->search($user, $filters);

        return view('documents.search', [
            'documents' => $documents,
            'filters' => $filters,
            'folders' => $folders,
            'authors' => $authors,
            'storage' => $this->storage,
        ]);
    }
}
