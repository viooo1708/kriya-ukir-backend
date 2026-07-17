<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'jenis_ukiran_id')) {
                // Hapus foreign key constraint dulu sebelum drop kolom
                $table->dropForeign('products_jenis_ukiran_id_foreign');
                $table->dropColumn('jenis_ukiran_id');
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'jenis_ukiran')) {
                $table->string('jenis_ukiran', 100)->nullable()->after('nama_product');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('jenis_ukiran');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('jenis_ukiran_id')->nullable()->after('nama_product')->constrained();
        });
    }
};
