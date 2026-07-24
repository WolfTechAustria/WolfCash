<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Printer extends Model
{
    public const CONNECTION_NETWORK = 'network';

    public const PRINT_TRIGGER_IMMEDIATE = 'immediate';
    public const PRINT_TRIGGER_ON_ITEM_COMPLETE = 'on_item_complete';
    public const PRINT_TRIGGER_ON_JOB_COMPLETE = 'on_job_complete';

    protected $fillable = [
        'name',
        'connection_type',
        'host',
        'ip_address',
        'port',
        'characters_per_line',
        'is_active',
        'print_trigger',
    ];

    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'characters_per_line' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function printJobs(): HasMany
    {
        return $this->hasMany(PrintJob::class);
    }

    public function printsImmediately(): bool
    {
        return $this->print_trigger === self::PRINT_TRIGGER_IMMEDIATE;
    }

    public function printsWhenJobCompletes(): bool
    {
        return $this->print_trigger === self::PRINT_TRIGGER_ON_JOB_COMPLETE;
    }
}
