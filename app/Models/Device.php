<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    protected $fillable = [
        'name',
        'fingerprint',
        'platform',
        'app_version',
        'approved_at',
        'active',
        'api_token',
    ];
}
