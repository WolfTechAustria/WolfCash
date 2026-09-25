<?php

namespace Tests\Feature;

use App\Enums\DeviceStatus;
use App\Http\Middleware\EnsureFloorDevice;
use App\Models\Device;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FloorDeviceGateTest extends TestCase
{
    use RefreshDatabase;

    private function device(DeviceStatus $status): Device
    {
        return Device::create([
            'uuid' => (string) Str::uuid(),
            'fingerprint' => (string) Str::uuid(),
            'name' => 'Browser Test',
            'platform' => 'web',
            'status' => $status,
        ]);
    }

    public function test_unknown_browser_sees_gate(): void
    {
        $this->get('/pos')
            ->assertForbidden()
            ->assertSee('Gerät wartet auf Freigabe');
    }

    public function test_pending_browser_sees_gate(): void
    {
        $device = $this->device(DeviceStatus::Pending);

        $this->withUnencryptedCookie(EnsureFloorDevice::COOKIE, $device->fingerprint)
            ->get('/pos')
            ->assertForbidden()
            ->assertSee('Gerät wartet auf Freigabe');
    }

    public function test_blocked_browser_sees_blocked_page(): void
    {
        $device = $this->device(DeviceStatus::Blocked);

        $this->withUnencryptedCookie(EnsureFloorDevice::COOKIE, $device->fingerprint)
            ->get('/production')
            ->assertForbidden()
            ->assertSee('Zugriff gesperrt');
    }

    public function test_approved_browser_gets_access(): void
    {
        $device = $this->device(DeviceStatus::Approved);
        $stationary = Table::create(['number' => 'ST-1', 'name' => 'Kasse 1', 'is_stationary' => true]);

        $this->withUnencryptedCookie(EnsureFloorDevice::COOKIE, $device->fingerprint)
            ->get('/pos')
            ->assertOk();

        $this->withUnencryptedCookie(EnsureFloorDevice::COOKIE, $device->fingerprint)
            ->get('/pos/stationary/'.$stationary->id)
            ->assertOk();

        $this->assertNotNull($device->fresh()->last_seen_at);
    }

    public function test_mobile_app_session_gets_access_until_blocked(): void
    {
        $device = $this->device(DeviceStatus::Approved);

        $this->withSession(['mobile_device_id' => $device->id])
            ->get('/pos')
            ->assertOk();

        $device->update(['status' => DeviceStatus::Blocked]);

        $this->withSession(['mobile_device_id' => $device->id])
            ->get('/pos')
            ->assertForbidden()
            ->assertSee('Zugriff gesperrt')
            ->assertDontSee('/api/device/register');
    }

    public function test_logged_in_admin_bypasses_device_check(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/pos')
            ->assertOk();
    }

    public function test_livewire_requests_are_rejected_with_locked_status(): void
    {
        $this->withHeader('X-Livewire', 'true')
            ->get('/pos')
            ->assertStatus(EnsureFloorDevice::LIVEWIRE_STATUS)
            ->assertJson(['status' => 'unknown']);
    }

    public function test_self_order_stays_public(): void
    {
        $this->get('/o/unbekannt')->assertNotFound();
    }
}
