<?php

namespace App\Livewire\Admin\Devices;

use Livewire\Component;
use App\Models\Device;

class Index extends Component
{
    public function approve($id)
    {
        $device = Device::findOrFail($id);

        $device->update([
            'status' => 'approved',
            'approved_at' => now(),
        ]);
    }

    public function block($id)
    {
        $device = Device::findOrFail($id);

        $device->update([
            'status' => 'blocked',
        ]);
    }

    public function render()
    {
        return view('livewire.admin.devices.index', [
            'devices' => Device::latest()->get(),
        ])->layout('components.layouts.app');
    }
}
