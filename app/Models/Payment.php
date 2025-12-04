<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'provider',
        'status',
        'amount',
        'currency',
        'provider_payment_id',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
        'amount' => 'decimal:2',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}


