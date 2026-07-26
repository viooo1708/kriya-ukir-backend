<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductStatus;
use App\Events\NewNotificationEvent;
use Illuminate\Http\Request;
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
        $query = Order::with(['product', 'specification', 'latestStatus', 'user']);

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
            'product_id' => 'required|exists:products,id',
            'jumlah' => 'required|integer|min:1',
            'ukuran' => 'nullable|string|max:100',
            'material' => 'nullable|string|max:100',
            'motif_ukiran' => 'nullable|string|max:100',
            'motif' => 'nullable|string|max:100',
            'finishing' => 'nullable|string|max:100',
            'catatan' => 'nullable|string',
            'biaya_tambahan' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $product = Product::findOrFail($request->product_id);
        $biayaTambahan = $request->input('biaya_tambahan', 0);
        $estimasiBiaya = ($product->estimasi_harga * $request->jumlah) + $biayaTambahan;

        $order = DB::transaction(function () use ($request, $product, $estimasiBiaya) {
            $order = Order::create([
                'user_id' => $request->user()->id,
                'product_id' => $product->id,
                'kode_pesanan' => 'ORD-'.strtoupper(Str::random(8)),
                'tanggal_pesanan' => now()->toDateString(),
                'jumlah' => $request->jumlah,
                'estimasi_biaya' => $estimasiBiaya,
                'estimasi_waktu' => '3-7 hari kerja',
                'status_pesanan' => 'menunggu_konfirmasi',
            ]);

            $order->specification()->create([
                'ukuran' => $request->ukuran,
                'material' => $request->material,
                'motif_ukiran' => $request->motif_ukiran,
                'motif' => $request->motif,
                'finishing' => $request->finishing,
                'catatan' => $request->catatan,
                'estimasi_harga' => $estimasiBiaya,
            ]);

            $order->statusHistory()->create([
                'status' => 'persiapan',
                'keterangan' => 'Pesanan diterima dan menunggu konfirmasi owner',
            ]);

            return $order;
        });

        $order->load(['product', 'specification', 'latestStatus']);

        event(new OrderCreated($order));

        // --- NOTIFIKASI KE OWNER (Ada pesanan baru) ---
        $notifOwner = Notification::create([
            'user_id' => self::OWNER_ID,
            'title' => 'Pesanan Baru Masuk!',
            'message' => 'Pesanan baru dengan kode ' . $order->kode_pesanan . ' menunggu konfirmasi.',
            'is_read' => false,
        ]);
        broadcast(new NewNotificationEvent($notifOwner));

        // --- NOTIFIKASI KE PELANGGAN (Konfirmasi pesanan diterima) ---
        $notifCustomer = Notification::create([
            'user_id' => $order->user_id,
            'title' => 'Pesanan Berhasil Dibuat',
            'message' => 'Pesanan ' . $order->kode_pesanan . ' telah diterima sistem.',
            'is_read' => false,
        ]);
        broadcast(new NewNotificationEvent($notifCustomer));

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
            'tahap_produksi' => 'nullable|in:persiapan,pengukiran,finishing,selesai',
        ]);

        $order->update([
            'estimasi_biaya' => $validated['estimasi_biaya'],
            'estimasi_waktu' => $validated['estimasi_waktu'],
            'status_pesanan' => $validated['status_pesanan'],
            'catatan' => $validated['catatan'],
        ]);

        if (!empty($validated['tahap_produksi'])) {
            if ($validated['status_pesanan'] !== 'diproses') {
                return response()->json([
                    'success' => false,
                    'message' => 'Tahapan produksi hanya dapat diperbarui jika status pesanan adalah Diproses.'
                ], 422);
            }

            ProductStatus::create([
                'order_id' => $order->id,
                'status' => $validated['tahap_produksi'],
                'keterangan' => $validated['catatan'],
                'tanggal_update' => now(),
            ]);
        }

        // --- NOTIFIKASI KE PELANGGAN (Status diperbarui) ---
        $statusText = str_replace('_', ' ', $validated['status_pesanan']);
        $notifCustomer = Notification::create([
            'user_id' => $order->user_id,
            'title' => 'Pembaruan Status Pesanan',
            'message' => 'Status pesanan ' . $order->kode_pesanan . ' telah menjadi: ' . ucwords($statusText) . '.',
            'is_read' => false,
        ]);
        broadcast(new NewNotificationEvent($notifCustomer));
        broadcast(new OrderCreated($order->fresh()));

        return response()->json([
            'success' => true,
            'message' => 'Pesanan berhasil diperbarui.',
            'data' => $order->fresh()->load('latestStatus'),
        ]);
    }
}
