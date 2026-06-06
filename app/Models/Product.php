<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    public const PRINT_GROUPED = 'grouped';
    public const PRINT_SPLIT = 'split';

    public const PRINT_NONE = 'no_print';

    protected $fillable = [
        'name',
        'price',
        'category',
        'available_quantity',
        'is_active',
        'product_category_id',
        'print_mode',
    ];

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
}
