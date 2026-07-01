<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductGroup extends Model
{
    protected $fillable = [
        'name',
        'sort_order',
    ];

    public function categories()
    {
        return $this->hasMany(ProductCategory::class);
    }
}
