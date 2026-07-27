<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade'); // Terikat langsung ke pesanan tertentu
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade'); // Pengirim pesan (bisa pelanggan / owner)
            $table->foreignId('receiver_id')->constrained('users')->onDelete('cascade'); // Penerima pesan
            $table->text('message'); // Isi pesan
            $table->boolean('is_read')->default(false); // Status sudah dibaca atau belum
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chats');
    }
};
