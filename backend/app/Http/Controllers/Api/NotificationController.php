<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $this->ensureRoleMatchesRoute($request, $user);

        $notifications = Notification::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get([
                'id',
                'type',
                'content',
                'url_id',
                'status',
                'read_at',
                'created_at',
            ]);

        return response()->json([
            'data' => $notifications,
        ]);
    }

    public function markAsRead(Request $request, Notification $notification)
    {
        $user = $request->user();
        $this->ensureRoleMatchesRoute($request, $user);

        if ($notification->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $notification->update([
            'status'  => 'read',
            'read_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    public function markAllAsRead(Request $request)
    {
        $user = $request->user();
        $this->ensureRoleMatchesRoute($request, $user);

        Notification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->update([
                'status'  => 'read',
                'read_at' => now(),
            ]);

        return response()->json(['success' => true]);
    }

    private function ensureRoleMatchesRoute(Request $request, $user): void
    {
        $path = $request->path(); // api/... 
        $isAdminRoute = str_contains($path, 'admin/notifications');

        if ($isAdminRoute && (string) ($user->role ?? '') !== 'admin') {
            abort(403, 'Forbidden');
        }

        if (! $isAdminRoute && (string) ($user->role ?? '') !== 'user') {
            abort(403, 'Forbidden');
        }
    }
}
