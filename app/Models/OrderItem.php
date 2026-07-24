<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'price',
        'status',
        'note',
        'paid_at',
        'production_status',
        'production_completed_quantity',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_PREPARED = 'prepared';
    public const STATUS_SERVED = 'served';

    public const PRODUCTION_PENDING = 'pending';

    public const PRODUCTION_PROGRESS = 'in_progress';

    public const PRODUCTION_DONE = 'done';

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    protected function casts(): array {
        return [
            'paid_at' => 'datetime',
            'production_completed_quantity' => 'integer',
            'production_printed_quantity' => 'integer',
        ];
    }
}
