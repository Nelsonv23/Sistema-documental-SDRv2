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

        return response()->json([
            'unread_count' => $unreadCount,
        ]);
    }

    public function index(Request $request): View
    {
        $notifications = Notification::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('notifications.index', [
            'notifications' => $notifications,
        ]);
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
