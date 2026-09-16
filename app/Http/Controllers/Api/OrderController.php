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
use Kreait\Laravel\Firebase\Facades\Firebase;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    // ID User Owner
    private const OWNER_ID = 1;

    /**
     * Helper untuk mengirim Push Notification via FCM ke User tertentu
     */
    private function sendFcmNotification($user, $title, $message, $orderId = null)
    {
        if (!$user || !$user->fcm_token) {
            return;
        }

        try {
            $messaging = Firebase::messaging();

            $cloudMessage = CloudMessage::new()
                ->withToken($user->fcm_token)
                ->withNotification(
                    FirebaseNotification::create($title, $message)
                )
                ->withData([
                    'order_id' => (string) ($orderId ?? ''),
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                ]);

            $messaging->send($cloudMessage);
        } catch (\Exception $e) {
            Log::error(
                "Gagal mengirim FCM Push Notification: " . $e->getMessage()
            );
        }
    }

    public function index(Request $request)
    {
        $query = Order::with([
            'product',
            'specification',
            'statusHistory',
            'latestStatus',
            'user',
            'orderItems.product'
        ]);

        if (! $request->user()->isOwner()) {
            $query->where('user_id', $request->user()->id);
        }

        $orders = $query->latest()->get();

        // Transformasi path gambar agar menjadi URL lengkap
        $orders->each(function ($order) {
            if ($order->gambar && !str_starts_with($order->gambar, 'http')) {
                $order->gambar = asset('storage/' . $order->gambar);
            }
            if ($order->bukti_pembayaran && !str_starts_with($order->bukti_pembayaran, 'http')) {
                $order->bukti_pembayaran = asset('storage/' . $order->bukti_pembayaran);
            }

            foreach ($order->orderItems as $item) {
                if ($item->product && $item->product->gambar && !str_starts_with($item->product->gambar, 'http')) {
                    $item->product->gambar = asset('storage/' . $item->product->gambar);
                }
            }
        });

        return response()->json([
            'data' => $orders
        ]);
    }

    public function show(Request $request, Order $order)
    {
        if (! $request->user()->isOwner() && $order->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Tidak diizinkan'
            ], 403);
        }

        $order->load([
            'product',
            'specification',
            'statusHistory',
            'latestStatus',
            'user',
            'orderItems.product'
        ]);

        if ($order->gambar && !str_starts_with($order->gambar, 'http')) {
            $order->gambar = asset('storage/' . $order->gambar);
        }
        if ($order->bukti_pembayaran && !str_starts_with($order->bukti_pembayaran, 'http')) {
            $order->bukti_pembayaran = asset('storage/' . $order->bukti_pembayaran);
        }

        foreach ($order->orderItems as $item) {
            if ($item->product && $item->product->gambar && !str_starts_with($item->product->gambar, 'http')) {
                $item->product->gambar = asset('storage/' . $item->product->gambar);
            }
        }

        return response()->json([
            'data' => $order
        ]);
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
            'items.*.catatan' => 'nullable|string',
            'biaya_tambahan' => 'nullable|numeric|min:0',
            'gambar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $gambarPath = null;
        if ($request->hasFile('gambar')) {
            $gambarPath = $request->file('gambar')->store('orders', 'public');
        }

        $biayaTambahan = $request->input('biaya_tambahan', 0);

        $order = DB::transaction(function () use ($request, $biayaTambahan, $gambarPath) {
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
                    'catatan' => $itemData['catatan'] ?? null,
                    'subtotal' => $subtotal,
                ];
            }

            $totalEstimasiBiaya += $biayaTambahan;
            $today = now()->format('Ymd');
            $latestOrderToday = Order::whereDate('created_at', today())->count();
            $nextNumber = str_pad($latestOrderToday + 1, 4, '0', STR_PAD_LEFT);
            $kodePesanan = "ORD-{$today}-{$nextNumber}";

            $jumlahDp = round($totalEstimasiBiaya * 0.40, 2);

            $order = Order::create([
                'user_id' => $request->user()->id,
                'kode_pesanan' => $kodePesanan,
                'tanggal_pesanan' => now(),
                'jumlah' => collect($processedItems)->sum('jumlah'),
                'estimasi_biaya' => $totalEstimasiBiaya,
                'jumlah_dp' => $jumlahDp,
                'biaya_dikonfirmasi' => true,
                'status_pembayaran' => 'menunggu_pembayaran_dp',
                'estimasi_waktu' => 'Menunggu konfirmasi owner',
                'status_pesanan' => 'menunggu_konfirmasi',
                'catatan' => $request->input('catatan', 'Pesanan via keranjang belanja'),
                'gambar' => $gambarPath,
            ]);

            foreach ($processedItems as $item) {
                $order->orderItems()->create($item);
            }

            $order->statusHistory()->create([
                'status' => 'persiapan',
                'keterangan' => 'Pesanan diterima dan menunggu konfirmasi harga & waktu oleh owner',
            ]);

            return $order;
        });

        $order->load(['orderItems.product', 'latestStatus', 'user']);

        // Notifikasi Owner
        $owner = \App\Models\User::find(self::OWNER_ID);
        $ownerTitle = 'Pesanan Baru Masuk!';
        $ownerMessage = 'Pelanggan ' . ($order->user->name ?? 'Seseorang') . ' membuat pesanan baru dengan kode ' . $order->kode_pesanan . '.';

        $notifOwner = Notification::create([
            'user_id' => self::OWNER_ID,
            'order_id' => $order->id,
            'title' => $ownerTitle,
            'message' => $ownerMessage,
            'is_read' => false,
        ]);

        broadcast(new NewNotificationEvent($notifOwner));
        $this->sendFcmNotification($owner, $ownerTitle, $ownerMessage, $order->id);

        // Notifikasi Customer
        $customer = $request->user();
        $customerTitle = 'Pesanan Berhasil Dibuat';
        $customerMessage = 'Pesanan ' . $order->kode_pesanan . ' berhasil dibuat dan sedang menunggu konfirmasi owner.';

        $notifCustomer = Notification::create([
            'user_id' => $customer->id,
            'order_id' => $order->id,
            'title' => $customerTitle,
            'message' => $customerMessage,
            'is_read' => false,
        ]);

        broadcast(new NewNotificationEvent($notifCustomer));
        $this->sendFcmNotification($customer, $customerTitle, $customerMessage, $order->id);
        event(new OrderCreated($order));

        return response()->json([
            'message' => 'Pesanan berhasil dibuat',
            'data' => $order,
        ], 201);
    }

    public function update(Request $request, Order $order)
    {
        $user = $request->user();
        $isOwner = $user->isOwner();

        if (!$isOwner && $order->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak diizinkan.'
            ], 403);
        }

        if ($order->status_pesanan === 'dibatalkan') {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan yang sudah dibatalkan tidak dapat diubah kembali.'
            ], 422);
        }

        $validated = $request->validate([
            'status_pesanan' => 'sometimes|required|in:menunggu_konfirmasi,diproses,dibatalkan,selesai',
            'estimasi_biaya' => 'nullable|numeric|min:0',
            'estimasi_waktu' => 'nullable|string|max:100',
            'estimasi_selesai' => 'nullable|date',
            'catatan' => 'nullable|string',
            'status' => 'nullable|in:persiapan,pengukiran,finishing,selesai',
            'action' => 'nullable|string|in:konfirmasi_biaya,konfirmasi_bayar_dp,verifikasi_bayar_dp,tolak_bayar_dp,konfirmasi_bayar_lunas,verifikasi_bayar_lunas,tolak_bayar_lunas',
            'bukti_pembayaran' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        /*
        |--------------------------------------------------------------------------
        | AKSI PELANGGAN
        |--------------------------------------------------------------------------
        */
        if (!$isOwner && isset($validated['action'])) {
            $action = $validated['action'];

            // 1. PELANGGAN MENYETUJUI ESTIMASI BIAYA
            if ($action === 'konfirmasi_biaya') {
                if ($order->status_pembayaran !== 'menunggu_konfirmasi_biaya') {
                    return response()->json(['success' => false, 'message' => 'Estimasi biaya tidak sedang menunggu persetujuan.'], 422);
                }
                if (!$order->estimasi_biaya || $order->estimasi_biaya <= 0) {
                    return response()->json(['success' => false, 'message' => 'Estimasi biaya belum ditentukan oleh owner.'], 422);
                }

                $jumlahDp = round((float) $order->estimasi_biaya * 0.40, 2);

                $order->update([
                    'biaya_dikonfirmasi' => true,
                    'jumlah_dp' => $jumlahDp,
                    'status_pembayaran' => 'menunggu_pembayaran_dp',
                ]);

                $owner = \App\Models\User::find(self::OWNER_ID);
                $title = 'Biaya Pesanan Disetujui';
                $message = 'Pelanggan menyetujui estimasi biaya pesanan ' . $order->kode_pesanan . '. DP sebesar Rp ' . number_format($jumlahDp, 0, ',', '.') . ' dapat dibayarkan.';

                $notification = Notification::create(['user_id' => self::OWNER_ID, 'order_id' => $order->id, 'title' => $title, 'message' => $message, 'is_read' => false]);
                broadcast(new NewNotificationEvent($notification));
                $this->sendFcmNotification($owner, $title, $message, $order->id);

                return response()->json(['success' => true, 'message' => 'Estimasi biaya disetujui. Silakan lakukan pembayaran DP.', 'data' => $order->fresh()->load(['latestStatus', 'statusHistory'])]);
            }

            // 2. PELANGGAN MENEKAN "SUDAH BAYAR DP"
            if ($action === 'konfirmasi_bayar_dp') {
                if ($order->status_pembayaran !== 'menunggu_pembayaran_dp') {
                    return response()->json(['success' => false, 'message' => 'Pesanan belum berada pada tahap pembayaran DP.'], 422);
                }

                $updateData = ['status_pembayaran' => 'menunggu_verifikasi_dp'];
                if ($request->hasFile('bukti_pembayaran')) {
                    $updateData['bukti_pembayaran'] = $request->file('bukti_pembayaran')->store('payments', 'public');
                }
                $order->update($updateData);

                // --- DICATAT KE RIWAYAT STATUS PRODUKSI ---
                $order->statusHistory()->create([
                    'status' => 'persiapan',
                    'keterangan' => 'Pelanggan telah mengunggah bukti pembayaran DP. Menunggu verifikasi owner.',
                    'tanggal_update' => now(),
                ]);

                $owner = \App\Models\User::find(self::OWNER_ID);
                $title = 'Menunggu Verifikasi Pembayaran DP';
                $message = 'Pelanggan telah mengonfirmasi pembayaran DP untuk pesanan ' . $order->kode_pesanan . '. Silakan periksa pembayaran.';

                $notification = Notification::create(['user_id' => self::OWNER_ID, 'order_id' => $order->id, 'title' => $title, 'message' => $message, 'is_read' => false]);
                broadcast(new NewNotificationEvent($notification));
                $this->sendFcmNotification($owner, $title, $message, $order->id);

                return response()->json(['success' => true, 'message' => 'Konfirmasi pembayaran DP berhasil dikirim.', 'data' => $order->fresh()->load(['latestStatus', 'statusHistory'])]);
            }

            // 3. PELANGGAN MENEKAN "SUDAH BAYAR LUNAS"
            if ($action === 'konfirmasi_bayar_lunas') {
                if ($order->status_pembayaran !== 'dp_dibayar') {
                    return response()->json(['success' => false, 'message' => 'Pelunasan belum dapat dilakukan.'], 422);
                }

                $updateData = ['status_pembayaran' => 'menunggu_verifikasi_lunas'];
                if ($request->hasFile('bukti_pembayaran')) {
                    $updateData['bukti_pembayaran'] = $request->file('bukti_pembayaran')->store('payments', 'public');
                }
                $order->update($updateData);

                // --- DICATAT KE RIWAYAT STATUS PRODUKSI ---
                $order->statusHistory()->create([
                    'status' => 'finishing',
                    'keterangan' => 'Pelanggan telah mengunggah bukti pelunasan. Menunggu verifikasi owner.',
                    'tanggal_update' => now(),
                ]);

                $owner = \App\Models\User::find(self::OWNER_ID);
                $title = 'Menunggu Verifikasi Pelunasan';
                $message = 'Pelanggan telah mengonfirmasi pelunasan pesanan ' . $order->kode_pesanan . '. Silakan periksa pembayaran.';

                $notification = Notification::create(['user_id' => self::OWNER_ID, 'order_id' => $order->id, 'title' => $title, 'message' => $message, 'is_read' => false]);
                broadcast(new NewNotificationEvent($notification));
                $this->sendFcmNotification($owner, $title, $message, $order->id);

                return response()->json(['success' => true, 'message' => 'Konfirmasi pelunasan berhasil dikirim.', 'data' => $order->fresh()->load(['latestStatus', 'statusHistory'])]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | AKSI VERIFIKASI OLEH OWNER
        |--------------------------------------------------------------------------
        */
        if ($isOwner && isset($validated['action'])) {
            $action = $validated['action'];
            $customer = $order->user;

            // 4. OWNER VERIFIKASI PEMBAYARAN DP
            if ($action === 'verifikasi_bayar_dp') {
                if ($order->status_pembayaran !== 'menunggu_verifikasi_dp') {
                    return response()->json(['success' => false, 'message' => 'Tidak ada pembayaran DP yang menunggu verifikasi.'], 422);
                }

                $order->update(['status_pembayaran' => 'dp_dibayar']);

                $order->statusHistory()->create([
                    'status' => 'persiapan',
                    'keterangan' => 'Pembayaran DP telah diverifikasi owner. Pesanan siap diproses.',
                    'tanggal_update' => now(),
                ]);

                if ($order->status_pesanan === 'menunggu_konfirmasi') {
                    $order->update(['status_pesanan' => 'diproses']);
                }

                $title = 'Pembayaran DP Diverifikasi';
                $message = 'Pembayaran DP pesanan ' . $order->kode_pesanan . ' telah diverifikasi oleh owner.';

                $notification = Notification::create(['user_id' => $order->user_id, 'order_id' => $order->id, 'title' => $title, 'message' => $message, 'is_read' => false]);
                broadcast(new NewNotificationEvent($notification));
                $this->sendFcmNotification($customer, $title, $message, $order->id);

                // --- TAMBAHAN UNTUK REDIRECT WEB ADMIN ---
                if (!request()->expectsJson()) {
                    return back()->with('success', 'Pembayaran DP berhasil diverifikasi.');
                }
                // ------------------------------------------

                return response()->json(['success' => true, 'message' => 'Pembayaran DP berhasil diverifikasi.', 'data' => $order->fresh()->load(['latestStatus', 'statusHistory'])]);
            }

            // TOLAK PEMBAYARAN DP
            if ($action === 'tolak_bayar_dp') {
                if ($order->status_pembayaran !== 'menunggu_verifikasi_dp') {
                    return response()->json(['success' => false, 'message' => 'Tidak ada pembayaran DP yang menunggu verifikasi.'], 422);
                }

                $order->update(['status_pembayaran' => 'menunggu_pembayaran_dp']);

                $order->statusHistory()->create([
                    'status' => 'persiapan',
                    'keterangan' => 'Pembayaran DP ditolak oleh owner. Silakan unggah ulang bukti pembayaran.',
                    'tanggal_update' => now(),
                ]);

                $title = 'Pembayaran DP Ditolak';
                $message = 'Pembayaran DP pesanan ' . $order->kode_pesanan . ' ditolak (Bukti/Nominal tidak valid). Silakan unggah ulang.';

                $notification = Notification::create(['user_id' => $order->user_id, 'order_id' => $order->id, 'title' => $title, 'message' => $message, 'is_read' => false]);
                broadcast(new NewNotificationEvent($notification));
                $this->sendFcmNotification($customer, $title, $message, $order->id);

                if (!request()->expectsJson()) {
                    return back()->with('success', 'Pembayaran DP ditolak.');
                }

                return response()->json(['success' => true, 'message' => 'Pembayaran DP ditolak.', 'data' => $order->fresh()->load(['latestStatus', 'statusHistory'])]);
            }

            // 5. OWNER VERIFIKASI PELUNASAN
            if ($action === 'verifikasi_bayar_lunas') {
                if ($order->status_pembayaran !== 'menunggu_verifikasi_lunas') {
                    return response()->json(['success' => false, 'message' => 'Tidak ada pelunasan yang menunggu verifikasi.'], 422);
                }

                $order->update([
                    'status_pembayaran' => 'lunas',
                    'status_pesanan' => 'selesai',
                ]);

                // --- DICATAT KE RIWAYAT STATUS PRODUKSI ---
                $order->statusHistory()->create([
                    'status' => 'selesai',
                    'keterangan' => 'Pelunasan diverifikasi owner. Pesanan selesai.',
                    'tanggal_update' => now(),
                ]);

                $title = 'Pelunasan Diverifikasi';
                $message = 'Pelunasan pesanan ' . $order->kode_pesanan . ' telah diverifikasi oleh owner. Pembayaran lunas.';

                $notification = Notification::create(['user_id' => $order->user_id, 'order_id' => $order->id, 'title' => $title, 'message' => $message, 'is_read' => false]);
                broadcast(new NewNotificationEvent($notification));
                $this->sendFcmNotification($customer, $title, $message, $order->id);

                if (!request()->expectsJson()) {
                    return back()->with('success', 'Pelunasan berhasil diverifikasi.');
                }

                return response()->json(['success' => true, 'message' => 'Pelunasan berhasil diverifikasi.', 'data' => $order->fresh()->load(['latestStatus', 'statusHistory'])]);
            }

            // TOLAK PELUNASAN
            if ($action === 'tolak_bayar_lunas') {
                if ($order->status_pembayaran !== 'menunggu_verifikasi_lunas') {
                    return response()->json(['success' => false, 'message' => 'Tidak ada pelunasan yang menunggu verifikasi.'], 422);
                }

                $order->update(['status_pembayaran' => 'dp_dibayar']);

                $order->statusHistory()->create([
                    'status' => 'finishing',
                    'keterangan' => 'Pelunasan ditolak oleh owner. Silakan unggah ulang bukti pelunasan.',
                    'tanggal_update' => now(),
                ]);

                $title = 'Pelunasan Ditolak';
                $message = 'Pelunasan pesanan ' . $order->kode_pesanan . ' ditolak (Bukti/Nominal tidak valid). Silakan unggah ulang.';

                $notification = Notification::create(['user_id' => $order->user_id, 'order_id' => $order->id, 'title' => $title, 'message' => $message, 'is_read' => false]);
                broadcast(new NewNotificationEvent($notification));
                $this->sendFcmNotification($customer, $title, $message, $order->id);

                if (!request()->expectsJson()) {
                    return back()->with('success', 'Pelunasan ditolak.');
                }

                return response()->json(['success' => true, 'message' => 'Pelunasan ditolak.', 'data' => $order->fresh()->load(['latestStatus', 'statusHistory'])]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | CUSTOMER: HANYA BOLEH MEMBATALKAN PESANAN
        |--------------------------------------------------------------------------
        */
        if (!$isOwner) {
            if (isset($validated['status_pesanan']) && $validated['status_pesanan'] !== 'dibatalkan') {
                return response()->json(['success' => false, 'message' => 'Pelanggan hanya diizinkan untuk membatalkan pesanan.'], 403);
            }
            if (isset($validated['status_pesanan']) && $order->status_pesanan !== 'menunggu_konfirmasi') {
                return response()->json(['success' => false, 'message' => 'Pesanan tidak dapat dibatalkan karena sudah diproses.'], 422);
            }

            if (isset($validated['status_pesanan'])) {
                $order->update([
                    'status_pesanan' => 'dibatalkan',
                    'catatan' => $validated['catatan'] ?? $order->catatan,
                ]);

                $owner = \App\Models\User::find(self::OWNER_ID);
                $title = 'Pesanan Dibatalkan Pelanggan';
                $message = 'Pelanggan membatalkan pesanan ' . $order->kode_pesanan . '.';

                $notification = Notification::create(['user_id' => self::OWNER_ID, 'order_id' => $order->id, 'title' => $title, 'message' => $message, 'is_read' => false]);
                broadcast(new NewNotificationEvent($notification));
                $this->sendFcmNotification($owner, $title, $message, $order->id);

                return response()->json(['success' => true, 'message' => 'Pesanan berhasil dibatalkan.', 'data' => $order->fresh()->load(['latestStatus', 'statusHistory'])]);
            }

            return response()->json(['success' => false, 'message' => 'Tidak ada perubahan yang dapat dilakukan.'], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | UPDATE KHUSUS OWNER
        |--------------------------------------------------------------------------
        */
        $updateData = [];

        if (!empty($validated['status'])) {
            $statusPesananAkanDigunakan = $validated['status_pesanan'] ?? $order->status_pesanan;
            if ($statusPesananAkanDigunakan !== 'diproses') {
                return response()->json(['success' => false, 'message' => 'Tahapan produksi hanya dapat diperbarui jika status pesanan adalah Diproses.'], 422);
            }
        }

        if (array_key_exists('estimasi_biaya', $validated)) {
            $estimasiBiayaBaru = $validated['estimasi_biaya'];
            $biayaBerubah = (float) $estimasiBiayaBaru !== (float) $order->estimasi_biaya;

            $updateData['estimasi_biaya'] = $estimasiBiayaBaru;
            $updateData['jumlah_dp'] = round((float) $estimasiBiayaBaru * 0.40, 2);

            if ($biayaBerubah) {
                $updateData['biaya_dikonfirmasi'] = false;
                $updateData['status_pembayaran'] = 'menunggu_konfirmasi_biaya';
            }
        }

        if (array_key_exists('estimasi_waktu', $validated)) $updateData['estimasi_waktu'] = $validated['estimasi_waktu'];
        if (array_key_exists('estimasi_selesai', $validated)) $updateData['estimasi_selesai'] = $validated['estimasi_selesai'];
        if (array_key_exists('status_pesanan', $validated)) $updateData['status_pesanan'] = $validated['status_pesanan'];
        if (array_key_exists('catatan', $validated)) $updateData['catatan'] = $validated['catatan'];

        $order->update($updateData);

        if (!empty($validated['status'])) {
            ProductStatus::create([
                'order_id' => $order->id,
                'status' => $validated['status'],
                'keterangan' => $validated['catatan'] ?? 'Pembaruan tahap produksi',
                'tanggal_update' => now(),
            ]);
        }

        $order->refresh();
        $customer = $order->user;
        $notifTitle = 'Pembaruan Pesanan';
        $notifMessage = 'Pesanan ' . $order->kode_pesanan . ' telah diperbarui oleh owner.';

        $notification = Notification::create(['user_id' => $order->user_id, 'order_id' => $order->id, 'title' => $notifTitle, 'message' => $notifMessage, 'is_read' => false]);
        broadcast(new NewNotificationEvent($notification));
        broadcast(new OrderCreated($order));
        $this->sendFcmNotification($customer, $notifTitle, $notifMessage, $order->id);

        if (!request()->expectsJson()) {
            return back()->with('success', 'Pesanan berhasil diperbarui.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Pesanan berhasil diperbarui.',
            'data' => $order->fresh()->load(['latestStatus', 'statusHistory', 'orderItems.product', 'user', 'specification']),
        ]);
    }
}
