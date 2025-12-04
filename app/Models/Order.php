<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'status',
        'payment_status',
        'currency',
        'subtotal',
        'discount_total',
        'shipping_total',
        'tax_total',
        'grand_total',
        'billing_address',
        'shipping_address',
        'notes',
    ];

    protected $casts = [
        'billing_address' => 'array',
        'shipping_address' => 'array',
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'shipping_total' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'grand_total' => 'decimal:2',
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}


