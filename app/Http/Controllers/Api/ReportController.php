<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Report;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Menampilkan ringkasan laporan pesanan & produksi (khusus owner).
     * Dapat difilter dengan query ?dari=YYYY-MM-DD&sampai=YYYY-MM-DD
     */
    public function summary(Request $request)
    {
        $query = Order::with(['product', 'user', 'latestStatus']);

        if ($request->filled('dari') && $request->filled('sampai')) {
            $query->whereBetween('tanggal_pesanan', [$request->dari, $request->sampai]);
        }

        $orders = $query->latest()->get();

        $ringkasan = [
            'total_pesanan' => $orders->count(),
            'total_selesai' => $orders->where('status_pesanan', 'selesai')->count(),
            'total_diproses' => $orders->where('status_pesanan', 'diproses')->count(),
            'total_dibatalkan' => $orders->where('status_pesanan', 'dibatalkan')->count(),
            'total_pendapatan_estimasi' => $orders->where('status_pesanan', 'selesai')->sum('estimasi_biaya'),
        ];

        Report::create([
            'owner_id' => $request->user()->id,
            'jenis_laporan' => 'ringkasan_pesanan',
            'periode' => $request->filled('dari') ? "{$request->dari} s/d {$request->sampai}" : 'seluruh_periode',
        ]);

        return response()->json([
            'ringkasan' => $ringkasan,
            'data' => $orders,
        ]);
    }
}
