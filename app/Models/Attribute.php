<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attribute extends Model
{
    protected $fillable = ['type', 'value'];

    public function scopeType($query, $type)
    {
        return $query->where('type', $type);
    }
}
