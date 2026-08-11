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
        'nama_custom',
        'kode_pesanan',
        'tanggal_pesanan',
        'jumlah',
        'estimasi_biaya',
        'jumlah_dp',
        'status_pembayaran',
        'estimasi_waktu',
        'estimasi_selesai',
        'status_pesanan',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_pesanan' => 'datetime:Y-m-d H:i:s',
            'estimasi_biaya' => 'decimal:2',
            'jumlah_dp' => 'decimal:2',
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

    // --- TAMBAHKAN INI AGAR RELASI KE TABEL DETAIL ITEM AKTIF ---
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
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
        return $this->hasOne(ProductStatus::class, 'order_id')->latest('id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function chats()
    {
        return $this->hasMany(Chat::class);
    }
}
