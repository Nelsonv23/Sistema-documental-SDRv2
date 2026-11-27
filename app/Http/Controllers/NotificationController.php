<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function unreadCount(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;

        // Liberar bloqueo de sesión temprano en polling periódico.
        $request->session()->save();

        $unreadCount = Notification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();

        $recent = Notification::query()
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'type', 'title', 'body', 'data', 'read_at', 'created_at'])
            ->map(static fn (Notification $notification): array => [
                'id' => $notification->id,
                'title' => $notification->title,
                'body' => $notification->body,
                'url' => $notification->data['url'] ?? route('notifications.index'),
                'open_url' => route('notifications.open', $notification),
                'is_unread' => $notification->read_at === null,
                'time_ago' => $notification->created_at?->diffForHumans() ?? '',
            ])
            ->all();

        return response()->json([
            'unread_count' => $unreadCount,
            'notifications' => $recent,
        ]);
    }

    public function index(Request $request): View
    {
        $userId = (int) $request->user()->id;
        $filter = $request->string('filter')->toString() === 'unread' ? 'unread' : 'all';

        $unreadCount = Notification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();

        $totalCount = Notification::query()
            ->where('user_id', $userId)
            ->count();

        $notifications = Notification::query()
            ->where('user_id', $userId)
            ->when($filter === 'unread', static fn ($query) => $query->whereNull('read_at'))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('notifications.index', [
            'notifications' => $notifications,
            'filter' => $filter,
            'unreadCount' => $unreadCount,
            'totalCount' => $totalCount,
        ]);
    }

    public function open(Request $request, Notification $notification): RedirectResponse
    {
        if ((int) $notification->user_id !== (int) $request->user()->id) {
            abort(403);
        }

        if ($notification->read_at === null) {
            $notification->forceFill([
                'read_at' => now(),
            ])->save();
        }

        $targetUrl = $notification->data['url'] ?? route('notifications.index');

        return redirect()->to($targetUrl);
    }

    public function markRead(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'notification_id' => ['nullable', 'integer'],
        ]);

        $query = Notification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at');

        if (! empty($validated['notification_id'])) {
            $query->where('id', (int) $validated['notification_id']);
        }

        $query->update(['read_at' => now()]);

        if ($request->expectsJson()) {
            $remaining = Notification::query()
                ->where('user_id', $request->user()->id)
                ->whereNull('read_at')
                ->count();

            return response()->json(['unread_count' => $remaining]);
        }

        return back()->with('status', 'Notificaciones marcadas como leídas.');
    }
}
