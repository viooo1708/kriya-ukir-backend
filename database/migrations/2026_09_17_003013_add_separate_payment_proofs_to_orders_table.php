<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            // Ganti/tambahkan kolom baru khusus DP dan Lunas
            $table->string('bukti_pembayaran_dp')->nullable()->after('status_pembayaran');
            $table->string('bukti_pembayaran_lunas')->nullable()->after('bukti_pembayaran_dp');

            // Opsional: Jika kolom 'bukti_pembayaran' yang lama sudah tidak dipakai lagi, bisa dihapus:
            // $table->dropColumn('bukti_pembayaran');
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['bukti_pembayaran_dp', 'bukti_pembayaran_lunas']);
        });
    }
};
