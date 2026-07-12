<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama_product',
        'jenis_ukiran',
        'ukuran',
        'bahan',
        'motif',
        'deskripsi',
        'gambar',
        'estimasi_harga',
    ];

    protected function casts(): array
    {
        return [
            'estimasi_harga' => 'decimal:2',
        ];
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
