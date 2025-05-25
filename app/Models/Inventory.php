<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'stock_quantity',
        'low_stock_threshold',
    ];

    /**
     * Get the product associated with the inventory.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
