<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    /**
     * Pelanggan: melihat riwayat pesanan miliknya.
     * Owner: melihat seluruh data pesanan masuk.
     */
    public function index(Request $request)
    {
        $query = Order::with(['product', 'specification', 'latestStatus', 'user']);

        if (! $request->user()->isOwner()) {
            $query->where('user_id', $request->user()->id);
        }

        $orders = $query->latest()->get();

        return response()->json(['data' => $orders]);
    }

    /**
     * Menampilkan detail satu pesanan beserta spesifikasi & riwayat status.
     */
    public function show(Request $request, Order $order)
    {
        if (! $request->user()->isOwner() && $order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Tidak diizinkan'], 403);
        }

        $order->load(['product', 'specification', 'statusHistory', 'user']);

        return response()->json(['data' => $order]);
    }

    /**
     * Pelanggan membuat pesanan baru beserta spesifikasi teknis.
     * Estimasi biaya dihitung dari harga dasar produk + biaya tambahan spesifikasi.
     */
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

        return response()->json([
            'message' => 'Pesanan berhasil dibuat',
            'data' => $order,
        ], 201);
    }

    /**
     * Owner memperbarui data/status pesanan (konfirmasi, proses, batalkan, selesai).
     */
    public function update(Request $request, Order $order)
{
    $validator = Validator::make($request->all(), [

        'status_pesanan' =>
            'required|in:menunggu_konfirmasi,diproses,dibatalkan,selesai',

        'estimasi_biaya' =>
            'nullable|numeric|min:0',

        'estimasi_waktu' =>
            'nullable|string|max:100',

        'catatan' =>
            'nullable|string',

    ]);


    if ($validator->fails()) {

        return response()->json([
            'errors' => $validator->errors()
        ], 422);

    }



    DB::transaction(function () use ($request, $order) {


        // Update data pesanan
        $order->update([

            'status_pesanan' => $request->status_pesanan,

            'estimasi_biaya' => $request->estimasi_biaya,

            'estimasi_waktu' => $request->estimasi_waktu,

            'catatan' => $request->catatan,

        ]);



        // Keterangan otomatis berdasarkan status
        $keterangan = match ($request->status_pesanan) {


            'menunggu_konfirmasi' =>
                'Pesanan menunggu konfirmasi dari owner.',


            'diproses' =>
                'Pesanan sedang dalam proses produksi.',


            'selesai' =>
                'Pesanan telah selesai diproduksi dan siap dikirim.',


            'dibatalkan' =>
                'Pesanan dibatalkan oleh owner.',


            default =>
                'Status pesanan diperbarui.'

        };



        // Simpan riwayat status
        $order->statusHistory()->create([

            'status' => $request->status_pesanan,

            'keterangan' => $keterangan,

        ]);


    });



    Notification::create([

        'user_id' => $order->user_id,

        'order_id' => $order->id,

        'title' => 'Status pesanan diperbarui',

        'message' =>
            "Pesanan {$order->kode_pesanan} kini berstatus {$order->status_pesanan}.",

    ]);



    return response()->json([

        'message' => 'Pesanan berhasil diperbarui',

        'data' => $order->fresh([
            'product',
            'specification',
            'statusHistory'
        ])

    ]);
}
}
