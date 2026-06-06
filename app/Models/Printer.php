<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Printer extends Model
{
    protected $fillable = [
        'name',
        'ip_address',
        'is_active',
    ];

    public function categories()
    {
        return $this->hasMany(ProductCategory::class);
    }
}
