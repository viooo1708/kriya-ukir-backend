<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Menampilkan seluruh notifikasi milik user yang login.
     */
    public function index(Request $request)
{
    $notifications = $request->user()
        ->notifications()
        ->latest()
        ->get()
        ->map(function ($n) {
            return [
                'id' => $n->id,
                'message' => $n->message ?? 'Ada notifikasi baru', // Pastikan nama key-nya 'message'
                'is_read' => (bool) $n->is_read, // Pastikan boolean
                'created_at' => $n->created_at->diffForHumans()
            ];
        });

    return response()->json(['data' => $notifications]);
}

    /**
     * Menandai satu notifikasi sebagai sudah dibaca.
     */
    public function markAsRead(Request $request, $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->update(['is_read' => true]);

        return response()->json(['message' => 'Notifikasi ditandai sudah dibaca']);
    }

    /**
     * Menandai seluruh notifikasi sebagai sudah dibaca.
     */
    public function markAllAsRead(Request $request)
    {
        $request->user()->notifications()->where('is_read', false)->update(['is_read' => true]);

        return response()->json(['message' => 'Semua notifikasi ditandai sudah dibaca']);
    }
}
