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
        $query = Order::with(['product', 'specification', 'statusHistory', 'latestStatus', 'user', 'orderItems.product']);

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
        $order->load(['product', 'specification', 'statusHistory', 'latestStatus', 'user', 'orderItems.product']);

        return response()->json(['data' => $order]);
    }

    public function store(Request $request)
{
    $validator = Validator::make($request->all(), [
        'items' => 'required|array|min:1',
        'items.*.product_id' => 'nullable|exists:products,id',
        'items.*.nama_custom' => 'nullable|string|max:150',
        'items.*.jumlah' => 'required|integer|min:1',
        'items.*.ukuran' => 'nullable|string|max:100',
        'items.*.material' => 'nullable|string|max:100',
        'items.*.motif_ukiran' => 'nullable|string|max:100',
        'items.*.motif' => 'nullable|string|max:100',
        'items.*.catatan' => 'nullable|string',
        'biaya_tambahan' => 'nullable|numeric|min:0',
        'jumlah_dp' => 'nullable|numeric|min:0',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $biayaTambahan = $request->input('biaya_tambahan', 0);
    $jumlahDp = $request->input('jumlah_dp', 0);

    $order = DB::transaction(function () use ($request, $biayaTambahan, $jumlahDp) {
        // 1. Hitung total estimasi biaya dari seluruh item di keranjang
        $totalEstimasiBiaya = 0;
        $processedItems = [];

        foreach ($request->input('items') as $itemData) {
            $product = isset($itemData['product_id']) ? Product::find($itemData['product_id']) : null;
            $hargaDasar = $product ? $product->estimasi_harga : 0;
            $jumlah = $itemData['jumlah'];
            $subtotal = ($hargaDasar * $jumlah);

            $totalEstimasiBiaya += $subtotal;

            $processedItems[] = [
                'product_id' => $product ? $product->id : null,
                'nama_custom' => $itemData['nama_custom'] ?? null,
                'jumlah' => $jumlah,
                'ukuran' => $itemData['ukuran'] ?? null,
                'material' => $itemData['material'] ?? null,
                'motif_ukiran' => $itemData['motif_ukiran'] ?? null,
                'motif' => $itemData['motif'] ?? null,
                'catatan' => $itemData['catatan'] ?? null,
                'subtotal' => $subtotal,
            ];
        }

        $totalEstimasiBiaya += $biayaTambahan;

        // 2. Generate Kode Pesanan Unik Berbasis Tanggal
        $today = now()->format('Ymd');
        $latestOrderToday = Order::whereDate('created_at', today())->count();
        $nextNumber = str_pad($latestOrderToday + 1, 4, '0', STR_PAD_LEFT);
        $kodePesanan = "ORD-{$today}-{$nextNumber}";

        // 3. Simpan ke tabel Induk (Orders)
        $order = Order::create([
            'user_id' => $request->user()->id,
            'kode_pesanan' => $kodePesanan,
            'tanggal_pesanan' => now(),
            'jumlah' => collect($processedItems)->sum('jumlah'), // Total seluruh kuantitas barang
            'estimasi_biaya' => $totalEstimasiBiaya,
            'jumlah_dp' => $jumlahDp,
            'status_pembayaran' => $jumlahDp > 0 ? 'dp_dibayar' : 'belum_bayar',
            'estimasi_waktu' => 'Menunggu konfirmasi owner',
            'status_pesanan' => 'menunggu_konfirmasi',
            'catatan' => $request->input('catatan', 'Pesanan via keranjang belanja'),
        ]);

        // 4. Simpan masing-masing produk ke tabel Detail (OrderItems)
        foreach ($processedItems as $item) {
            $order->orderItems()->create($item);
        }

        // 5. Catat history status awal
        $order->statusHistory()->create([
            'status' => 'persiapan',
            'keterangan' => 'Pesanan diterima dan menunggu konfirmasi harga & waktu oleh owner',
        ]);

        return $order;
    });

    // Load relasi agar data lengkap dikembalikan ke Flutter
    $order->load(['orderItems.product', 'latestStatus', 'user']);

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
    // 1. Cek apakah user yang login adalah Owner atau Pemilik Pesanan tersebut
    if (! $request->user()->isOwner() && $order->user_id !== $request->user()->id) {
        return response()->json(['message' => 'Tidak diizinkan'], 403);
    }

    // 2. Gunakan 'sometimes' agar field yang tidak dikirim dari Flutter tidak divalidasi ketat
    $validated = $request->validate([
        'status_pesanan' => 'sometimes|required|in:menunggu_konfirmasi,diproses,dibatalkan,selesai',
        'estimasi_biaya' => 'nullable|numeric',
        'jumlah_dp' => 'nullable|numeric|min:0',
        'status_pembayaran' => 'nullable|in:belum_bayar,dp_dibayar,lunas',
        'estimasi_waktu' => 'nullable|string|max:100',
        'catatan' => 'nullable|string',
        'status' => 'nullable|in:persiapan,pengukiran,finishing',
    ]);

    // 3. Jika yang melakukan request adalah PELANGGAN (bukan owner)
    if (! $request->user()->isOwner()) {
        // Pastikan status_pesanan yang dikirim adalah 'dibatalkan'
        if (isset($validated['status_pesanan']) && $validated['status_pesanan'] !== 'dibatalkan') {
            return response()->json([
                'success' => false,
                'message' => 'Anda hanya diizinkan untuk membatalkan pesanan.'
            ], 403);
        }

        // Pastikan status saat ini masih menunggu_konfirmasi
        if ($order->status_pesanan !== 'menunggu_konfirmasi') {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan tidak dapat dibatalkan karena sudah diproses oleh owner.'
            ], 422);
        }
    }

    // 4. Proses update data pesanan secara aman
    $order->update([
        'estimasi_biaya' => $request->user()->isOwner() ? ($validated['estimasi_biaya'] ?? $order->estimasi_biaya) : $order->estimasi_biaya,
        'jumlah_dp' => $request->user()->isOwner() ? ($validated['jumlah_dp'] ?? $order->jumlah_dp) : $order->jumlah_dp,
        'status_pembayaran' => $request->user()->isOwner() ? ($validated['status_pembayaran'] ?? $order->status_pembayaran) : $order->status_pembayaran,
        'estimasi_waktu' => $request->user()->isOwner() ? ($validated['estimasi_waktu'] ?? $order->estimasi_waktu) : $order->estimasi_waktu,
        'status_pesanan' => $validated['status_pesanan'] ?? $order->status_pesanan,
        'catatan' => $validated['catatan'] ?? $order->catatan,
    ]);

    // Jika tahap produksi dikirim oleh owner
    if ($request->user()->isOwner() && !empty($validated['status'])) {
        if (($validated['status_pesanan'] ?? $order->status_pesanan) !== 'diproses') {
            return response()->json([
                'success' => false,
                'message' => 'Tahapan produksi hanya dapat diperbarui jika status pesanan adalah Diproses.'
            ], 422);
        }

        ProductStatus::create([
            'order_id' => $order->id,
            'status' => $validated['status'],
            'keterangan' => $validated['catatan'] ?? 'Pembaruan tahap produksi',
            'tanggal_update' => now(),
        ]);
    }

    // --- NOTIFIKASI ---
    $statusPesananBaru = $validated['status_pesanan'] ?? $order->status_pesanan;
    $statusText = str_replace('_', ' ', $statusPesananBaru);

    $targetUserId = $request->user()->isOwner() ? $order->user_id : self::OWNER_ID;
    $notifTitle = $request->user()->isOwner() ? 'Pembaruan Status Pesanan' : 'Pesanan Dibatalkan Pelanggan';
    $notifMessage = $request->user()->isOwner()
        ? 'Status pesanan ' . $order->kode_pesanan . ' telah menjadi: ' . ucwords($statusText) . '.'
        : 'Pelanggan membatalkan pesanan ' . $order->kode_pesanan . '.';

    $notification = Notification::create([
        'user_id' => $targetUserId,
        'order_id' => $order->id,
        'title' => $notifTitle,
        'message' => $notifMessage,
        'is_read' => false,
    ]);

    broadcast(new NewNotificationEvent($notification));
    broadcast(new OrderCreated($order->fresh()));

    return response()->json([
        'success' => true,
        'message' => 'Pesanan berhasil diperbarui.',
        'data' => $order->fresh()->load(['latestStatus', 'statusHistory']),
    ]);
}
}
