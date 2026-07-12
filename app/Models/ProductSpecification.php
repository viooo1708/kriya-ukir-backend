<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductSpecification extends Model
{
    use HasFactory;

    protected $table = 'product_specifications';

    protected $fillable = [
        'order_id',
        'ukuran',
        'material',
        'motif_ukiran',
        'motif',
        'finishing',
        'catatan',
        'estimasi_harga',
    ];

    protected function casts(): array
    {
        return [
            'estimasi_harga' => 'decimal:2',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
