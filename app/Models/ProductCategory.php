<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\ProductionStation;

class ProductCategory extends Model
{
    protected $fillable = [
        'product_group_id',
        'printer_id',
        'production_station_id',
        'name',
    ];

    public function group()
    {
        return $this->belongsTo(ProductGroup::class, 'product_group_id');
    }

    public function printer()
    {
        return $this->belongsTo(Printer::class);
    }

    public function productionStation()
    {
        return $this->belongsTo(ProductionStation::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
