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
        // 1. Cek apakah status pesanan saat ini adalah 'diproses'
        // Jika statusnya masih 'menunggu_konfirmasi', 'dibatalkan', atau 'selesai', blokir aksinya.
        if ($order->status_pesanan !== 'diproses') {
            return response()->json([
                'message' => 'Gagal memperbarui progres. Tahapan produksi hanya bisa diperbarui jika status pesanan adalah "diproses".'
            ], 422);
        }

        // 2. Jalankan validasi input seperti biasa
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:persiapan,pengukiran,finishing',
            'keterangan' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // 3. Simpan tahapan produksi baru
        $status = $order->statusHistory()->create([
            'status' => $request->status,
            'keterangan' => $request->keterangan,
            'tanggal_update' => now(),
        ]);

        // 4. Jika owner memilih tahapan produksi 'selesai',
        // otomatis ubah juga status utama pesanannya menjadi 'selesai'
        if ($request->status === 'selesai') {
            $order->update(['status_pesanan' => 'selesai']);
        }

        // 5. Kirim Notifikasi
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
