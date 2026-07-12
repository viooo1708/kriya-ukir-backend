<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductStatusController extends Controller
{
    /**
     * Melihat riwayat status produksi suatu pesanan (pelanggan & owner).
     */
    public function index(Request $request, Order $order)
    {
        if (! $request->user()->isOwner() && $order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Tidak diizinkan'], 403);
        }

        return response()->json(['data' => $order->statusHistory]);
    }

    /**
     * Owner memperbarui status produksi pesanan.
     * Otomatis mengirim notifikasi ke pelanggan terkait.
     */
    public function store(Request $request, Order $order)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:persiapan,pengukiran,finishing,selesai',
            'keterangan' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $status = $order->statusHistory()->create([
            'status' => $request->status,
            'keterangan' => $request->keterangan,
            'tanggal_update' => now(),
        ]);

        if ($request->status === 'selesai') {
            $order->update(['status_pesanan' => 'selesai']);
        }

        Notification::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'title' => 'Progres produksi diperbarui',
            'message' => "Pesanan {$order->kode_pesanan} kini pada tahap {$request->status}.",
        ]);

        return response()->json([
            'message' => 'Status produksi berhasil diperbarui',
            'data' => $status,
        ], 201);
    }
}
