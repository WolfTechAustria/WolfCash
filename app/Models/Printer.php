<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Printer extends Model
{
    public const CONNECTION_NETWORK = 'network';

    protected $fillable = [
        'name',
        'connection_type',
        'host',
        'ip_address',
        'port',
        'characters_per_line',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'characters_per_line' => 'integer',
            'is_enabled' => 'boolean',
        ];
    }

    public function printJobs(): HasMany
    {
        return $this->hasMany(PrintJob::class);
    }
}
