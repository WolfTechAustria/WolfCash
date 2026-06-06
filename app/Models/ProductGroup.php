<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductGroup extends Model
{
    protected $fillable = [
        'name',
    ];

    public function categories()
    {
        return $this->hasMany(ProductCategory::class);
    }
}
