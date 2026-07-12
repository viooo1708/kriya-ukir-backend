<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('restrict');
            $table->string('kode_pesanan', 50)->unique();
            $table->date('tanggal_pesanan');
            $table->unsignedInteger('jumlah')->default(1);
            $table->decimal('estimasi_biaya', 12, 2)->default(0);
            $table->string('estimasi_waktu', 100)->nullable();
            $table->enum('status_pesanan', ['menunggu_konfirmasi', 'diproses', 'dibatalkan', 'selesai'])
                  ->default('menunggu_konfirmasi');
            $table->timestamps();

            $table->index('user_id');
            $table->index('status_pesanan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
