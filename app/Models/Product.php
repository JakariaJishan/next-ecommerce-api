<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'category_id',
        'name',
        'slug',
        'description',
        'price',
        'sale_price',
        'currency',
        'stock',
        'status',
        'images',
        'attributes',
    ];

    protected $casts = [
        'images' => 'array',
        'attributes' => 'array',
        'price' => 'decimal:2',
        'sale_price' => 'decimal:2',
    ];

    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }
}


