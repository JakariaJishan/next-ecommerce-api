<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'type', 'address_line1',
        'address_line2', 'city', 'state', 'postal_code',
        'country', 'phone',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function billingOrders()
    {
        return $this->hasMany(Order::class, 'billing_address_id');
    }

    public function shippingOrders()
    {
        return $this->hasMany(Order::class, 'shipping_address_id');
    }
}
