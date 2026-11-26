<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Document;
use App\Models\Notification;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\FolderPermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly FolderPermissionService $permissions,
    ) {}

    public function store(Request $request, Document $document): RedirectResponse
    {
        $this->authorize('view', $document);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $user = $request->user();
        $document->loadMissing('folder');

        $comment = Comment::create([
            'document_id' => $document->id,
            'user_id' => $user->id,
            'body' => trim($validated['body']),
        ]);

        $this->audit->log('comment.created', $comment, [
            'document_id' => $document->id,
            'document_name' => $document->name,
        ], $user);

        if ($document->folder !== null) {
            $recipients = User::query()
                ->active()
                ->whereKeyNot($user->id)
                ->with('roles')
                ->get();

            $targetUrl = route('documents.show', $document).'#comments';

            foreach ($recipients as $recipient) {
                if (! $this->permissions->canAccess($recipient, $document->folder)) {
                    continue;
                }

                $isOwner = $document->created_by !== null && (int) $recipient->id === (int) $document->created_by;

                Notification::create([
                    'user_id' => $recipient->id,
                    'type' => 'comment.created',
                    'title' => $isOwner ? 'Nuevo comentario en tu documento' : 'Nuevo comentario en un documento',
                    'body' => "{$user->name} comentó en \"{$document->name}\".",
                    'data' => [
                        'document_id' => $document->id,
                        'document_name' => $document->name,
                        'comment_id' => $comment->id,
                        'actor_name' => $user->name,
                        'url' => $targetUrl,
                    ],
                ]);
            }
        }

        return redirect()
            ->route('documents.show', $document)
            ->with('status', 'Comentario publicado.');
    }

    public function destroy(Request $request, Comment $comment): RedirectResponse
    {
        $comment->loadMissing('document.folder');

        if (! $comment->document) {
            abort(404);
        }

        $this->authorize('view', $comment->document);

        $user = $request->user();
        if ((int) $comment->user_id !== (int) $user->id && ! $user->hasRole('Administrador')) {
            abort(403, 'No tienes permiso para eliminar este comentario.');
        }

        $documentId = $comment->document_id;

        $this->audit->log('comment.deleted', $comment, [
            'document_id' => $documentId,
            'document_name' => $comment->document->name,
        ], $user);

        $comment->delete();

        return redirect()
            ->route('documents.show', $documentId)
            ->with('status', 'Comentario eliminado.');
    }
}
