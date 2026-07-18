<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\DeviceStatus;

class Device extends Model
{
    protected $fillable = [
        'uuid',
        'name',
        'fingerprint',
        'platform',
        'app_version',
        'last_seen_at',
        'approved_at',
        'approved_by',
        'status',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'approved_at' => 'datetime',
        'status' => DeviceStatus::class,
    ];


    protected static function booted(): void
    {
        static::creating(function (Device $device): void {
            if (! $device->uuid) {
                $device->uuid = (string) Str::uuid();
            }
        });
    }
    public function approver(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'approved_by'
        );
    }
}
