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
        'description',
        'price',
        'available_quantity',
        'is_active',
        'product_category_id',
        'print_mode',
        'sort_order',

    ];

    public function category()
    {
        return $this->belongsTo(ProductCategory::class,'product_category_id');
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function isUnlimited(): bool
    {
        return $this->available_quantity === -1;
    }

    public function isSoldOut(): bool
    {
        return $this->available_quantity === 0;
    }

    public function selfOrderItems()
    {
        return $this->hasMany(
            SelfOrderItem::class
        );
    }
}
