<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Ubah tabel orders agar product_id & spesifikasi tunggal bersifat opsional/dihapus karena dipindah ke order_items
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->change(); // Buat nullable jika sebelumnya wajib
        });

        // 2. Buat tabel order_items untuk menampung banyak produk dalam 1 transaksi order
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignId('product_id')->nullable()->constrained('products')->onDelete('set null');
            $table->string('nama_custom', 150)->nullable();
            $table->unsignedInteger('jumlah')->default(1);
            $table->string('ukuran', 100)->nullable();
            $table->string('material', 100)->nullable();
            $table->string('motif_ukiran', 100)->nullable();
            $table->string('motif', 100)->nullable();
            $table->text('catatan')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
