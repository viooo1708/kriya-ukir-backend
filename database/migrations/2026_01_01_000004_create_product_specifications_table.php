<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_specifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->string('ukuran', 100)->nullable();
            $table->string('material', 100)->nullable();
            $table->string('motif_ukiran', 100)->nullable();
            $table->string('motif', 100)->nullable();
            $table->string('finishing', 100)->nullable();
            $table->text('catatan')->nullable();
            $table->decimal('estimasi_harga', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_specifications');
    }
};
