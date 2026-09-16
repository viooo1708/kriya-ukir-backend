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

        if ($request->filled('tanggal_mulai') && $request->filled('tanggal_selesai')) {
            $query->whereBetween('tanggal_pesanan', [$request->tanggal_mulai, $request->tanggal_selesai]);
        }

        if ($request->filled('status')) {
            $query->where('status_pesanan', $request->status);
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->filled('pelanggan')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('nama', 'like', '%' . $request->pelanggan . '%');
            });
        }

        // Ambil data untuk pagination
        $orders = $query->latest()->paginate(10)->withQueryString();

        // Ambil seluruh data hasil filter (tanpa pagination) untuk kalkulasi ringkasan yang akurat
        $filteredOrders = (clone $query)->get();

        // Hitung total pendapatan dinamis sesuai aturan dashboard:
        // - Selesai / Lunas = 100% (estimasi_biaya)
        // - DP Sudah Dibayar = 40% (estimasi_biaya * 0.40)
        // - Dibatalkan / Status lain = 0
        $totalPendapatanEstimasi = $filteredOrders->sum(function($order) {
            $statusPesanan = strtolower($order->status_pesanan ?? '');
            $statusPembayaran = strtolower($order->status_pembayaran ?? '');
            $estimasiBiaya = (float) ($order->estimasi_biaya ?? 0);

            if ($statusPesanan === 'dibatalkan') {
                return 0;
            }

            if ($statusPesanan === 'selesai' || $statusPembayaran === 'lunas') {
                return $estimasiBiaya;
            }

            if ($statusPembayaran === 'dp_dibayar') {
                return $estimasiBiaya * 0.40;
            }

            return 0;
        });

        // Hitung ringkasan akurat berdasarkan query kloning agar tidak terpengaruh pagination
        $ringkasan = [
            'total_pesanan' => $orders->total(),
            'total_selesai' => (clone $query)->where('status_pesanan', 'selesai')->count(),
            'total_diproses' => (clone $query)->where('status_pesanan', 'diproses')->count(),
            'total_dibatalkan' => (clone $query)->where('status_pesanan', 'dibatalkan')->count(),
            'total_pendapatan_estimasi' => (float) $totalPendapatanEstimasi,
        ];

        Report::create([
            'owner_id' => $request->user()->id,
            'jenis_laporan' => 'ringkasan_pesanan',
            'periode' => $request->filled('tanggal_mulai') ? "{$request->tanggal_mulai} s/d {$request->tanggal_selesai}" : 'seluruh_periode',
        ]);

        return response()->json([
            'ringkasan' => $ringkasan,
            'data' => $orders,
        ]);
    }
}
