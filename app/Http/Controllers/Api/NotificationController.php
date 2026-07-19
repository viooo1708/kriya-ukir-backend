<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Notification;

class NotificationController extends Controller
{
    // Mengambil daftar notifikasi milik user yang sedang login
    public function index(Request $request)
    {
        $user = $request->user();

        $notifications = Notification::where('user_id', $user->id)->latest()->get();

        return response()->json([
            'status' => 'success',
            'data' => $notifications
        ]);
    }

    // Mengubah status notifikasi menjadi sudah dibaca
    public function markAsRead(Request $request, $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->update(['is_read' => true]);

        return response()->json([
            'status' => 'success',
            'message' => 'Notifikasi telah ditandai dibaca.'
        ]);
    }

    // Tambahkan fungsi ini agar error 500 hilang
    public function markAllAsRead(Request $request)
    {
        $request->user()->notifications()->where('is_read', false)->update(['is_read' => true]);

        return response()->json([
            'status' => 'success',
            'message' => 'Semua notifikasi telah ditandai dibaca.'
        ]);
    }
}
