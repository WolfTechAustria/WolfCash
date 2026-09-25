<?php

namespace App\Models;

use App\Services\StockService;
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

    protected static function booted(): void
    {
        /*
         * Jede Bestandsänderung (Admin, Abbuchung, Storno)
         * live an die Kassen melden.
         */
        static::saved(function (Product $product): void {
            if ($product->wasRecentlyCreated || $product->wasChanged('available_quantity')) {
                app(StockService::class)->broadcast([$product->id]);
            }
        });
    }

    public function category()
    {
        return $this->belongsTo(ProductCategory::class,'product_category_id');
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function reservations()
    {
        return $this->hasMany(ProductReservation::class);
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
