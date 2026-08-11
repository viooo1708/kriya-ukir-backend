<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Notification;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        // 1. Ambil ringkasan pesanan
        $ringkasanRaw = Order::selectRaw("
            COUNT(*) as total_pesanan,
            SUM(CASE WHEN status_pesanan = 'diproses' THEN 1 ELSE 0 END) as total_diproses,
            SUM(CASE WHEN status_pesanan = 'selesai' THEN 1 ELSE 0 END) as total_selesai,
            SUM(CASE WHEN status_pesanan = 'dibatalkan' THEN 1 ELSE 0 END) as total_dibatalkan,
            SUM(CASE WHEN status_pesanan != 'dibatalkan' THEN estimasi_biaya ELSE 0 END) as total_pendapatan_estimasi
        ")->first();

        $totalDiproses = (int) ($ringkasanRaw->total_diproses ?? 0);

        $ringkasan = [
            'total_pesanan' => (int) ($ringkasanRaw->total_pesanan ?? 0),
            'total_diproses' => $totalDiproses,
            'total_selesai' => (int) ($ringkasanRaw->total_selesai ?? 0),
            'total_dibatalkan' => (int) ($ringkasanRaw->total_dibatalkan ?? 0),
            'total_pendapatan_estimasi' => (float) ($ringkasanRaw->total_pendapatan_estimasi ?? 0),
        ];

        // 2. Hitung tahapan progres produksi
        $tahapan = \App\Models\ProductStatus::whereIn('order_id', function ($query) {
                $query->select('id')->from('orders')->where('status_pesanan', 'diproses');
            })
            ->selectRaw("status, COUNT(*) as total")
            ->groupBy('status')
            ->pluck('total', 'status');

        $tahapPersiapan = $tahapan['persiapan'] ?? 0;
        $tahapPengukiran = $tahapan['pengukiran'] ?? 0;
        $tahapFinishing = $tahapan['finishing'] ?? 0;

        $progressProduksi = [
            ['name' => 'Persiapan', 'value' => $totalDiproses > 0 ? round(($tahapPersiapan / $totalDiproses) * 100) : 0],
            ['name' => 'Pengukiran', 'value' => $totalDiproses > 0 ? round(($tahapPengukiran / $totalDiproses) * 100) : 0],
            ['name' => 'Finishing', 'value' => $totalDiproses > 0 ? round(($tahapFinishing / $totalDiproses) * 100) : 0],
        ];

        // 3. AMBIL 10 PESANAN TERBARU
        $orders = Order::with(['user:id,nama,name', 'orderItems.product:id,nama_product'])
            ->latest('id')
            ->take(10) // <-- Diubah menjadi 10
            ->get();

        // 4. Ambil aktivitas workshop terbaru
        $aktivitas = Notification::where('user_id', 1)
            ->latest('id')
            ->take(5)
            ->get();

        return view('dashboard.index', compact('ringkasan', 'progressProduksi', 'orders', 'aktivitas'));
    }
}
