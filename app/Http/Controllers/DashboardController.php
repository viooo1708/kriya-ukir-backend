<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        // Total pesanan yang sedang diproses (sebagai pembagi 100%)
        $totalDiproses = \App\Models\Order::where('status_pesanan', 'diproses')->count();

        // Hitung per tahapan berdasarkan status terakhir (latestStatus)
        // Pastikan string 'persiapan', 'pengukiran', 'finishing' sama persis dengan di database
        $tahapPersiapan = \App\Models\Order::where('status_pesanan', 'diproses')
                            ->whereHas('latestStatus', fn($q) => $q->where('status', 'persiapan'))->count();

        $tahapPengukiran = \App\Models\Order::where('status_pesanan', 'diproses')
                            ->whereHas('latestStatus', fn($q) => $q->where('status', 'pengukiran'))->count();

        $tahapFinishing = \App\Models\Order::where('status_pesanan', 'diproses')
                            ->whereHas('latestStatus', fn($q) => $q->where('status', 'finishing'))->count();

        $progressProduksi = [
            ['name' => 'Persiapan', 'value' => $totalDiproses > 0 ? round(($tahapPersiapan / $totalDiproses) * 100) : 0],
            ['name' => 'Pengukiran', 'value' => $totalDiproses > 0 ? round(($tahapPengukiran / $totalDiproses) * 100) : 0],
            ['name' => 'Finishing', 'value' => $totalDiproses > 0 ? round(($tahapFinishing / $totalDiproses) * 100) : 0],
        ];

        $orders = \App\Models\Order::with(['user', 'product'])->latest()->take(5)->get();

        // Hitung ringkasan
        $ringkasan = [
            'total_pesanan' => \App\Models\Order::count(),
            'total_diproses' => $totalDiproses,
            'total_selesai' => \App\Models\Order::where('status_pesanan', 'selesai')->count(),
            'total_dibatalkan' => \App\Models\Order::where('status_pesanan', 'dibatalkan')->count(),
            'total_pendapatan_estimasi' => \App\Models\Order::sum('estimasi_biaya'),
        ];

        return view('dashboard.index', compact('ringkasan', 'progressProduksi', 'orders'));
    }
}
