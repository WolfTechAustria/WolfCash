<?php

namespace App\Livewire\Admin\Devices;

use App\Enums\DeviceStatus;
use App\Models\Device;
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

        Device::findOrFail($this->editingId)->update([
            'name' => $validated['name'],
        ]);

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
    }

    public function block(int $id): void
    {
        $device = Device::findOrFail($id);

        $device->update([
            'status' => DeviceStatus::Blocked,
        ]);
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
