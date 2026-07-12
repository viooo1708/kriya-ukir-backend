<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id',
        'kode_pesanan',
        'tanggal_pesanan',
        'jumlah',
        'estimasi_biaya',
        'estimasi_waktu',
        'status_pesanan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_pesanan' => 'date',
            'estimasi_biaya' => 'decimal:2',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function specification()
    {
        return $this->hasOne(ProductSpecification::class);
    }

    public function statusHistory()
    {
        return $this->hasMany(ProductStatus::class)->orderByDesc('tanggal_update');
    }

    public function latestStatus()
    {
        return $this->hasOne(ProductStatus::class)->latestOfMany('tanggal_update');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }
}
