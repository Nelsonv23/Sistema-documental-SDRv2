<?php

namespace App\Http\Controllers;

use App\Exceptions\DuplicateDocumentException;
use App\Models\Document;
use App\Models\Folder;
use App\Services\AuditLogger;
use App\Services\DocumentStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function __construct(
        private readonly DocumentStorageService $storage,
        private readonly AuditLogger $audit,
    ) {}

    public function show(Request $request, Document $document): View
    {
        $this->authorize('view', $document);

        $document->load([
            'folder',
            'currentVersion',
            'creator',
            'comments' => fn ($query) => $query->with('user')->latest(),
        ]);

        return view('documents.show', [
            'document' => $document,
            'isPreviewable' => $this->storage->isPreviewable($document->extension),
            'isImage' => in_array(strtolower((string) $document->extension), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true),
        ]);
    }

    public function checkDuplicate(Request $request, Folder $folder): JsonResponse
    {
        $this->authorize('create', [Document::class, $folder]);
        $request->session()->save();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        return response()->json(
            $this->storage->checkDuplicate($folder, $validated['name'])
        );
    }

    public function store(Request $request, Folder $folder): JsonResponse|RedirectResponse
    {
        $this->authorize('create', [Document::class, $folder]);

        $user = $request->user();

        // Liberar bloqueo de sesión temprano para permitir subidas concurrentes sin contención.
        $request->session()->save();

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:61440'],
            'conflict_strategy' => ['nullable', 'string', 'in:reject,replace,keep_both'],
        ]);

        $strategy = $validated['conflict_strategy'] ?? DocumentStorageService::CONFLICT_REJECT;

        try {
            $document = $this->storage->storeUpload(
                $folder,
                $request->file('file'),
                $user,
                $strategy
            );
        } catch (DuplicateDocumentException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'duplicate' => true,
                    'message' => $e->getMessage(),
                ], 409);
            }

            return back()->with('error', $e->getMessage());
        }

        $versionNumber = $document->currentVersion?->version_number ?? 1;
        $action = $versionNumber > 1 ? 'document.replaced' : 'document.uploaded';

        $this->audit->log($action, $document, [
            'folder_id' => $folder->id,
            'version_number' => $versionNumber,
            'sha256' => $document->currentVersion?->sha256,
            'size' => $document->currentVersion?->size,
        ], $user);

        if ($request->expectsJson()) {
            return response()->json([
                'id' => $document->id,
                'name' => $document->name,
                'extension' => $document->extension,
                'version_number' => $versionNumber,
                'size' => $document->currentVersion?->size ?? 0,
                'previewable' => $this->storage->isPreviewable($document->extension),
                'preview_url' => route('documents.preview', $document),
                'download_url' => route('documents.download', $document),
            ], 201);
        }

        return redirect()
            ->route('folders.show', $folder)
            ->with('status', "Documento \"{$document->name}\" subido correctamente.");
    }

    public function preview(Request $request, Document $document): StreamedResponse
    {
        $this->authorize('view', $document);
        $user = $request->user();
        $request->session()->save();

        $document->loadMissing('currentVersion');

        $isInline = $this->storage->isPreviewable($document->extension);
        $this->audit->log($isInline ? 'document.viewed' : 'document.downloaded', $document, [
            'folder_id' => $document->folder_id,
        ], $user);

        return $this->storage->streamResponse($document, inline: true);
    }

    public function download(Request $request, Document $document): StreamedResponse
    {
        $this->authorize('download', $document);
        $user = $request->user();
        $request->session()->save();

        $document->loadMissing('currentVersion');

        $this->audit->log('document.downloaded', $document, [
            'folder_id' => $document->folder_id,
        ], $user);

        return $this->storage->streamResponse($document, inline: false);
    }

    public function destroy(Request $request, Document $document): RedirectResponse
    {
        $this->authorize('delete', $document);

        $document->forceFill([
            'status' => Document::STATUS_TRASHED,
            'deleted_at' => now(),
            'deleted_by' => $request->user()->id,
        ])->save();

        $this->audit->log('document.deleted', $document, [
            'folder_id' => $document->folder_id,
        ], $request->user());

        return redirect()
            ->route('folders.show', $document->folder_id)
            ->with('status', "Documento \"{$document->name}\" enviado a la papelera.");
    }
}
