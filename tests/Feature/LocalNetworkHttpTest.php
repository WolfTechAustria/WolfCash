<?php

namespace Tests\Feature;

use App\Enums\DeviceStatus;
use App\Models\Device;
use App\Models\MobileSessionCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

/**
 * Die Mobile-App lädt die Kasse im lokalen Netz über
 * http://<private IP>, ohne HTTPS.
 */
class LocalNetworkHttpTest extends TestCase
{
    use RefreshDatabase;

    private const CSP = 'upgrade-insecure-requests';

    protected function setUp(): void
    {
        parent::setUp();

        // Wie auf dem Live-Server hinter dem HTTPS-Proxy.
        config(['session.secure' => true]);
    }

    private function sessionCode(): string
    {
        $device = Device::create([
            'uuid' => (string) Str::uuid(),
            'fingerprint' => (string) Str::uuid(),
            'name' => 'Tablet',
            'platform' => 'android',
            'status' => DeviceStatus::Approved,
        ]);

        return MobileSessionCode::create([
            'device_id' => $device->id,
            'code' => Str::random(64),
            'expires_at' => now()->addMinutes(2),
        ])->code;
    }

    private function sessionCookie($response): Cookie
    {
        return collect($response->headers->getCookies())
            ->firstOrFail(fn (Cookie $cookie) => $cookie->getName() === config('session.cookie'));
    }

    public function test_app_session_cookie_is_not_secure_on_local_ip(): void
    {
        $response = $this->get('http://10.91.10.5/mobile/session/'.$this->sessionCode())
            ->assertRedirect('/pos');

        $this->assertFalse($this->sessionCookie($response)->isSecure());
    }

    public function test_app_session_cookie_stays_secure_on_public_host(): void
    {
        $response = $this->get('https://kassa.example.com/mobile/session/'.$this->sessionCode())
            ->assertRedirect('/pos');

        $this->assertTrue($this->sessionCookie($response)->isSecure());
    }

    public function test_app_reaches_pos_over_local_http(): void
    {
        $this->get('http://10.91.10.5/mobile/session/'.$this->sessionCode());

        $this->get('http://10.91.10.5/pos')
            ->assertOk()
            ->assertDontSee(self::CSP, false);
    }

    public function test_gate_does_not_force_https_on_local_ip(): void
    {
        $this->get('http://192.168.1.20/pos')
            ->assertForbidden()
            ->assertSee('Gerät wartet auf Freigabe')
            ->assertDontSee(self::CSP, false);
    }

    public function test_public_host_still_forces_https(): void
    {
        $this->get('https://kassa.example.com/pos')
            ->assertForbidden()
            ->assertSee(self::CSP, false);
    }
}
