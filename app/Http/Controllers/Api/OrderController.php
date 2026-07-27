<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductStatus;
use App\Events\NewNotificationEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Events\OrderCreated;

class OrderController extends Controller
{
    // ID User Owner - SILAKAN SESUAIKAN DENGAN ID OWNER DI TABEL USERS ANDA
    private const OWNER_ID = 1;

    public function index(Request $request)
    {
        $query = Order::with(['product', 'specification', 'statusHistory', 'latestStatus', 'user']);

        if (! $request->user()->isOwner()) {
            $query->where('user_id', $request->user()->id);
        }

        $orders = $query->latest()->get();

        return response()->json(['data' => $orders]);
    }

    public function show(Request $request, Order $order)
    {
        if (! $request->user()->isOwner() && $order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Tidak diizinkan'], 403);
        }

        // Pastikan latestStatus ikut dimuat
        $order->load(['product', 'specification', 'statusHistory', 'latestStatus', 'user']);

        return response()->json(['data' => $order]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'nullable|exists:products,id',
            'nama_custom' => 'nullable|string|max:150', // Menerima nama custom dari Flutter
            'jumlah' => 'required|integer|min:1',
            'ukuran' => 'nullable|string|max:100',
            'material' => 'nullable|string|max:100',
            'motif_ukiran' => 'nullable|string|max:100',
            'motif' => 'nullable|string|max:100',
            'catatan' => 'nullable|string',
            'biaya_tambahan' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $product = $request->filled('product_id') ? Product::find($request->product_id) : null;
        $estimasiHargaDasar = $product ? $product->estimasi_harga : 0;

        $biayaTambahan = $request->input('biaya_tambahan', 0);
        $estimasiBiaya = ($estimasiHargaDasar * $request->jumlah) + $biayaTambahan;

        $order = DB::transaction(function () use ($request, $product, $estimasiBiaya) {
            $order = Order::create([
                'user_id' => $request->user()->id,
                'product_id' => $product ? $product->id : null,
                'nama_custom' => $request->input('nama_custom'), // Simpan nama custom murni di sini
                'kode_pesanan' => 'ORD-'.strtoupper(Str::random(8)),
                'tanggal_pesanan' => now()->toDateString(),
                'jumlah' => $request->jumlah,
                'estimasi_biaya' => $estimasiBiaya,
                'estimasi_waktu' => 'Menunggu konfirmasi owner',
                'status_pesanan' => 'menunggu_konfirmasi',
                'catatan' => $request->catatan,
            ]);

            $order->specification()->create([
                'ukuran' => $request->ukuran,
                'material' => $request->material,
                'motif_ukiran' => $request->motif_ukiran,
                'motif' => $request->motif,
                'catatan' => $request->catatan,
                'estimasi_harga' => $estimasiBiaya,
            ]);

            $order->statusHistory()->create([
                'status' => 'persiapan',
                'keterangan' => 'Pesanan diterima dan menunggu konfirmasi harga & waktu oleh owner',
            ]);

            return $order;
        });

        $order->load(['product', 'specification', 'latestStatus', 'user']);

        // --- NOTIFIKASI KE OWNER ---
        $notifOwner = Notification::create([
            'user_id' => self::OWNER_ID,
            'order_id' => $order->id,
            'title' => 'Pesanan Baru Masuk!',
            'message' => 'Pelanggan ' . ($order->user->name ?? 'Seseorang') . ' membuat pesanan baru dengan kode ' . $order->kode_pesanan . '.',
            'is_read' => false,
        ]);
        broadcast(new NewNotificationEvent($notifOwner));

        // --- NOTIFIKASI KE PELANGGAN ---
        $notifCustomer = Notification::create([
            'user_id' => $request->user()->id,
            'order_id' => $order->id,
            'title' => 'Pesanan Berhasil Dibuat',
            'message' => 'Pesanan ' . $order->kode_pesanan . ' berhasil dibuat dan sedang menunggu konfirmasi owner.',
            'is_read' => false,
        ]);
        broadcast(new NewNotificationEvent($notifCustomer));

        event(new OrderCreated($order));

        return response()->json([
            'message' => 'Pesanan berhasil dibuat',
            'data' => $order,
        ], 201);
    }

    public function update(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status_pesanan' => 'required|in:menunggu_konfirmasi,diproses,dibatalkan,selesai',
            'estimasi_biaya' => 'nullable|numeric',
            'estimasi_waktu' => 'nullable|string|max:100',
            'catatan' => 'nullable|string',
            'status' => 'nullable|in:persiapan,pengukiran,finishing', // Ubah dari tahap_produksi menjadi 'status'
        ]);

        $order->update([
            'estimasi_biaya' => $validated['estimasi_biaya'] ?? $order->estimasi_biaya,
            'estimasi_waktu' => $validated['estimasi_waktu'] ?? $order->estimasi_waktu,
            'status_pesanan' => $validated['status_pesanan'],
            'catatan' => $validated['catatan'] ?? $order->catatan,
        ]);

        // Jika status tahap produksi dikirimkan melalui form edit
        if (!empty($validated['status'])) {
            if ($validated['status_pesanan'] !== 'diproses') {
                return response()->json([
                    'success' => false,
                    'message' => 'Tahapan produksi hanya dapat diperbarui jika status pesanan adalah Diproses.'
                ], 422);
            }

            // Simpan pembaruan tahap produksi baru ke tabel product_statuses
            ProductStatus::create([
                'order_id' => $order->id,
                'status' => $validated['status'],
                'keterangan' => $validated['catatan'] ?? 'Pembaruan tahap produksi',
                'tanggal_update' => now(),
            ]);
        }

        // --- NOTIFIKASI KE PELANGGAN ---
        $statusText = str_replace('_', ' ', $validated['status_pesanan']);
        $notifCustomer = Notification::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'title' => 'Pembaruan Status Pesanan',
            'message' => 'Status pesanan ' . $order->kode_pesanan . ' telah menjadi: ' . ucwords($statusText) . '.',
            'is_read' => false,
        ]);

        broadcast(new NewNotificationEvent($notifCustomer));
        broadcast(new OrderCreated($order->fresh()));

        return response()->json([
            'success' => true,
            'message' => 'Pesanan berhasil diperbarui.',
            'data' => $order->fresh()->load(['latestStatus', 'statusHistory']),
        ]);
    }
}
