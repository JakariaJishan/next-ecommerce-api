<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentMethod extends Model
{
    use SoftDeletes;
    protected $table = 'payment_methods';

    protected $fillable = [
        'user_id',
        'payment_type',
        'card_number',
        'expiry_month',
        'expiry_year',
        'card_type',
        'card_holder_name',
        'full_name',
        'address',
        'city',
        'zip',
        'state',
        'set_as_default',
        'paypal_email',
        'bank_name',
        'account_number',
    ];

    protected $casts = [
        'set_as_default' => 'boolean',
        'card_number' => 'encrypted',
        'paypal_email' => 'encrypted',
        'account_number' => 'encrypted',
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
