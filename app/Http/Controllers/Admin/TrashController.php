<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Notification;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TrashController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function index(): View
    {
        $documents = Document::query()
            ->trashed()
            ->with(['folder', 'deletedBy', 'currentVersion'])
            ->orderByDesc('deleted_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.trash.index', [
            'documents' => $documents,
        ]);
    }

    public function restore(Request $request, Document $document): RedirectResponse
    {
        $this->authorize('restore', $document);

        if ($document->status !== Document::STATUS_TRASHED) {
            return back()->with('error', 'Solo se pueden restaurar documentos que se encuentran en la papelera.');
        }

        DB::transaction(function () use ($document): void {
            $document->forceFill([
                'status' => Document::STATUS_ACTIVE,
                'deleted_at' => null,
                'deleted_by' => null,
            ])->save();
        });

        $this->audit->log('document.restored', $document, [
            'folder_id' => $document->folder_id,
        ], $request->user());

        if ($document->created_by && (int) $document->created_by !== (int) $request->user()->id) {
            Notification::create([
                'user_id' => $document->created_by,
                'type' => 'document.restored',
                'title' => 'Documento restaurado',
                'body' => "El administrador {$request->user()->name} restauró el documento \"{$document->name}\".",
                'data' => [
                    'document_id' => $document->id,
                    'folder_id' => $document->folder_id,
                    'url' => route('documents.show', $document),
                ],
            ]);
        }

        return redirect()
            ->route('admin.trash.index')
            ->with('status', "Documento \"{$document->name}\" restaurado correctamente.");
    }
}
