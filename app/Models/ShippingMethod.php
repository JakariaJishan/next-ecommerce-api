<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'rate',
        'currency',
        'meta',
        'active',
    ];

    protected $casts = [
        'meta' => 'array',
        'active' => 'boolean',
        'rate' => 'decimal:2',
    ];
}


