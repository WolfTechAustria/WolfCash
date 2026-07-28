<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;



class Payment extends Model
{
    public const CASH = 'cash';

    public const CARD = 'card';

    public const VOUCHER = 'voucher';

    public const INVOICE = 'invoice';

    public const HOUSE = 'house';

    protected $fillable = [

        'order_id',

        'amount',

        'payment_method',

        'device_id',

        'user_id',

    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function cancellations(): HasMany
    {
        return $this->hasMany(
            OrderItemCancellation::class
        );
    }


}
