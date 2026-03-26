<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    /**
     * Listar notificaciones del usuario autenticado
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        $perPage = min((int) $request->get('per_page', 20), 50);
        $filter = $request->get('filter', 'all'); // all, unread, read

        $query = $user->notifications();

        if ($filter === 'unread') {
            $query->whereNull('read_at');
        } elseif ($filter === 'read') {
            $query->whereNotNull('read_at');
        }

        $notifications = $query->latest()->paginate($perPage);

        $unreadCount = $user->unreadNotifications()->count();

        return response()->json([
            'success' => true,
            'data' => $notifications->items(),
            'unread_count' => $unreadCount,
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    /**
     * Marcar una notificación como leída
     */
    public function markAsRead(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->where('id', $id)->first();

        if (!$notification) {
            return response()->json(['success' => false, 'message' => 'Notificación no encontrada'], 404);
        }

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        return response()->json([
            'success' => true,
            'message' => 'Notificación marcada como leída',
        ]);
    }

    /**
     * Marcar todas como leídas
     */
    public function markAllAsRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Todas las notificaciones marcadas como leídas',
        ]);
    }

    /**
     * Eliminar una notificación
     */
    public function destroy(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->where('id', $id)->first();

        if (!$notification) {
            return response()->json(['success' => false, 'message' => 'Notificación no encontrada'], 404);
        }

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notificación eliminada',
        ]);
    }

    /**
     * Eliminar todas las notificaciones leídas
     */
    public function clearRead(Request $request)
    {
        $request->user()->notifications()->whereNotNull('read_at')->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notificaciones leídas eliminadas',
        ]);
    }

    /**
     * Obtener contador de no leídas (ligero)
     */
    public function unreadCount(Request $request)
    {
        $count = $request->user()->unreadNotifications()->count();

        return response()->json([
            'success' => true,
            'unread_count' => $count,
        ]);
    }
}
