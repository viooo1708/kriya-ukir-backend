<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Chat;
use App\Models\Order;
use App\Models\Notification;
use App\Events\MessageSent;
use App\Events\NewNotificationEvent;
use Illuminate\Support\Facades\Validator;

class ChatController extends Controller
{
    private const OWNER_ID = 1; // Sesuaikan dengan ID owner Anda

    // Ambil semua riwayat chat berdasarkan order_id
    public function index(Request $request, Order $order)
    {
        if (! $request->user()->isOwner() && $order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Tidak diizinkan'], 403);
        }

        $chats = $order->chats()->with(['sender', 'receiver'])->oldest()->get();

        // Tandai pesan yang diterima sebagai sudah dibaca
        $order->chats()
            ->where('receiver_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['data' => $chats]);
    }

    // Kirim pesan baru
    public function store(Request $request, Order $order)
    {
        if (! $request->user()->isOwner() && $order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Tidak diizinkan'], 403);
        }

        $validator = Validator::make($request->all(), [
            'message' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Tentukan penerima: Jika pengirim owner, maka penerima adalah user pemilik pesanan, dan sebaliknya.
        $receiverId = ($request->user()->id === self::OWNER_ID) ? $order->user_id : self::OWNER_ID;

        $chat = Chat::create([
            'order_id' => $order->id,
            'sender_id' => $request->user()->id,
            'receiver_id' => $receiverId,
            'message' => $request->message,
            'is_read' => false,
        ]);

        $chat->load(['sender', 'receiver']);

        // Broadcast pesan secara real-time
        broadcast(new MessageSent($chat))->toOthers();

        // Kirim Notifikasi ke Penerima
        $notif = Notification::create([
            'user_id' => $receiverId,
            'order_id' => $order->id,
            'title' => 'Pesan Baru dari ' . ($request->user()->name ?? 'Seseorang'),
            'message' => 'Ada pesan pada pesanan ' . $order->kode_pesanan,
            'is_read' => false,
        ]);
        broadcast(new NewNotificationEvent($notif));

        return response()->json([
            'message' => 'Pesan berhasil dikirim',
            'data' => $chat,
        ], 201);
    }
}
