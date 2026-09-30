<?php

namespace App\Livewire\Admin\Devices;

use App\Enums\DeviceStatus;
use App\Models\ActivityLog;
use App\Models\Device;
use App\Services\ActivityLogger;
use Illuminate\Support\Str;
use Livewire\Component;

class Index extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public function startEditing(int $id): void
    {
        $device = Device::findOrFail($id);

        $this->editingId = $device->id;
        $this->name = $device->name;

        $this->resetValidation();
    }

    public function cancelEditing(): void
    {
        $this->editingId = null;
        $this->name = '';

        $this->resetValidation();
    }

    public function saveName(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $device = Device::findOrFail($this->editingId);
        $oldName = $device->name;

        $device->update([
            'name' => $validated['name'],
        ]);

        if ($oldName !== $device->name) {
            app(ActivityLogger::class)->log(
                ActivityLog::DEVICE_RENAMED,
                "Gerät „{$oldName}“ umbenannt in „{$device->name}“",
                $device,
                ['old' => $oldName, 'new' => $device->name],
            );
        }

        $this->cancelEditing();
    }

    public function approve(int $id): void
    {
        $device = Device::findOrFail($id);

        $device->update([
            'status' => DeviceStatus::Approved,
            'approved_at' => now(),
            'approved_by' => auth()->id(),

            /*
             * Token nur erzeugen, wenn noch keiner vorhanden ist.
             */
            'api_token' => $device->api_token
                ?: Str::random(80),
        ]);

        app(ActivityLogger::class)->log(
            ActivityLog::DEVICE_APPROVED,
            "Gerät „{$device->name}“ freigegeben",
            $device,
        );
    }

    public function block(int $id): void
    {
        $device = Device::findOrFail($id);

        $device->update([
            'status' => DeviceStatus::Blocked,
        ]);

        app(ActivityLogger::class)->log(
            ActivityLog::DEVICE_BLOCKED,
            "Gerät „{$device->name}“ gesperrt",
            $device,
        );
    }

    public function render()
    {
        return view(
            'livewire.admin.devices.index',
            [
                'devices' => Device::latest()->get(),
            ]
        )->layout('components.layouts.app');
    }
}
