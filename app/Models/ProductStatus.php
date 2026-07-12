<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductStatus extends Model
{
    use HasFactory;

    protected $table = 'product_status';

    const UPDATED_AT = null;

    protected $fillable = [
        'order_id',
        'status',
        'keterangan',
        'tanggal_update',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_update' => 'datetime',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
