<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasFactory;

    const UPDATED_AT = null;
    const CREATED_AT = null;

    protected $fillable = [
        'owner_id',
        'order_id',
        'jenis_laporan',
        'periode',
        'tanggal_cetak',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_cetak' => 'datetime',
        ];
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
