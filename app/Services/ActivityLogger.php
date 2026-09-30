<?php

namespace App\Services;

use App\Http\Middleware\EnsureFloorDevice;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Schreibt das Audit-Log.
 *
 * Benutzer, Gerät und IP werden beim Aufruf erfasst, geschrieben wird
 * erst nach dem Commit der umgebenden Transaktion: Zurückgerollte
 * Vorgänge erscheinen so nicht im Log, und ein Fehler beim
 * Protokollieren kann weder die Buchung abbrechen noch die Kasse
 * blockieren.
 */
class ActivityLogger
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function log(
        string $event,
        string $description,
        ?Model $subject = null,
        array $properties = [],
        ?int $userId = null,
    ): void {
        try {
            $attributes = [
                'event' => $event,
                'description' => mb_substr($description, 0, 255),
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'properties' => $properties ?: null,
                ...$this->actor($userId),
            ];
        } catch (Throwable $exception) {
            report($exception);

            return;
        }

        DB::afterCommit(function () use ($attributes): void {
            try {
                ActivityLog::create($attributes);
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function actor(?int $userId): array
    {
        $user = $userId !== null
            ? User::find($userId)
            : auth()->user();

        /*
         * Queue-Worker und Artisan haben keinen echten Request.
         */
        $request = app()->runningInConsole() && ! app()->runningUnitTests()
            ? null
            : request();

        $device = $request
            ? EnsureFloorDevice::resolveDevice($request)
            : null;

        return [
            'user_id' => $user?->getKey(),
            'user_name' => $user?->name,
            'device_id' => $device?->id,
            'device_name' => $device?->name,
            'ip_address' => $request?->ip(),
        ];
    }
}
