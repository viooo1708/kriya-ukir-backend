<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['motif', 'jenis_ukiran', 'bahan', 'ukuran']);
            $table->string('value', 100);
            $table->timestamps();

            $table->unique(['type', 'value']); // cegah duplikat per kategori
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attributes');
    }
};
