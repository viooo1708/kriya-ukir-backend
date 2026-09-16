<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Tambahkan kolom penanda apakah biaya sudah dikonfirmasi pelanggan
            $table->boolean('biaya_dikonfirmasi')->default(false)->after('estimasi_biaya');

            // Catatan: status_pembayaran string bawaan Anda diperluas opsinya
            // menjadi: 'belum_bayar', 'menunggu_verifikasi_dp', 'dp_dibayar', 'menunggu_verifikasi_lunas', 'lunas'
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('biaya_dikonfirmasi');
        });
    }
};
