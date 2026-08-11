<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\User;

class ChatController extends Controller
{
    private const OWNER_ID = 1; // Sesuaikan dengan ID user Owner Anda
    private const OWNER_PHONE = '6283815535218'; // Ganti dengan nomor WhatsApp Owner (format 62...)

    /**
     * Generate URL WhatsApp untuk pesanan tertentu.
     */
    public function getWhatsAppUrl(Request $request, Order $order)
    {
        // 1. Validasi hak akses (Hanya owner atau pembuat pesanan yang bisa akses)
        if (! $request->user()->isOwner() && $order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Tidak diizinkan'], 403);
        }

        // 2. Tentukan nomor penerima
        if ($request->user()->isOwner()) {
            // Jika Owner yang klik -> kirim ke Pelanggan
            $targetUser = $order->user;
            $rawPhone = $targetUser->no_hp ?? $targetUser->phone ?? null;
        } else {
            // Jika Pelanggan yang klik -> kirim ke Owner
            $rawPhone = self::OWNER_PHONE;
        }

        if (! $rawPhone) {
            return response()->json([
                'message' => 'Nomor WhatsApp tujuan tidak ditemukan.'
            ], 422);
        }

        // 3. Normalisasi format nomor (ubah awalan 0 / +62 menjadi 62)
        $cleanPhone = preg_replace('/[^0-9]/', '', $rawPhone);
        if (str_starts_with($cleanPhone, '0')) {
            $cleanPhone = '62' . substr($cleanPhone, 1);
        }

        // 4. Susun pesan default otomatis
        $namaPengirim = $request->user()->name ?? 'Pelanggan';
        $kodeOrder = $order->kode_pesanan;
        $totalBiaya = number_format($order->estimasi_biaya, 0, ',', '.');

        if ($request->user()->isOwner()) {
            $text = "Halo {$order->user->name}, saya Owner Adi Ukiran. Terkait pesanan Anda *{$kodeOrder}*...";
        } else {
            $text = "Halo Owner Adi Ukiran, saya *{$namaPengirim}*. Saya ingin menanyakan tentang pesanan saya dengan nomor *{$kodeOrder}* (Total: Rp {$totalBiaya}).";
        }

        $whatsappUrl = "https://wa.me/{$cleanPhone}?text=" . urlencode($text);

        return response()->json([
            'success' => true,
            'data' => [
                'order_id' => $order->id,
                'kode_pesanan' => $order->kode_pesanan,
                'target_phone' => $cleanPhone,
                'whatsapp_url' => $whatsappUrl,
            ],
        ]);
    }
}
