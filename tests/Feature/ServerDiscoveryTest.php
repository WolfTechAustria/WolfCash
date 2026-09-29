<?php

namespace Tests\Feature;

use App\Enums\DeviceStatus;
use App\Models\Device;
use App\Services\ServerIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ServerDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    private string $serverId = '0b8c0f0e-3f3a-4d7e-9a57-6a3a0f6f3b21';

    private string $publicKey;

    protected function setUp(): void
    {
        parent::setUp();

        $keyPair = ServerIdentity::generateKeyPair();

        $this->publicKey = $keyPair['public_key'];

        config([
            'discovery.server_id' => $this->serverId,
            'discovery.server_name' => 'Testkasse',
            'discovery.secret_key' => $keyPair['secret_key'],
            'app.mobile_web_url' => 'https://kassa.example.com',
        ]);
    }

    public function test_identify_returns_verifiable_signature(): void
    {
        $nonce = Str::random(32);

        $response = $this->postJson('/api/discovery/identify', [
            'nonce' => $nonce,
        ])->assertOk()
            ->assertJson([
                'server_id' => $this->serverId,
                'name' => 'Testkasse',
                'public_key' => $this->publicKey,
            ]);

        $this->assertTrue(
            sodium_crypto_sign_verify_detached(
                base64_decode($response->json('signature')),
                ServerIdentity::message($this->serverId, $nonce),
                base64_decode($this->publicKey)
            )
        );
    }

    public function test_identify_rejects_invalid_nonce(): void
    {
        $this->postJson('/api/discovery/identify', [
            'nonce' => 'kurz',
        ])->assertUnprocessable();
    }

    public function test_identify_without_identity_is_unavailable(): void
    {
        config(['discovery.secret_key' => null]);

        $this->postJson('/api/discovery/identify', [
            'nonce' => Str::random(32),
        ])->assertServiceUnavailable();
    }

    public function test_mobile_session_url_stays_local_for_private_ip(): void
    {
        $token = $this->approvedDeviceToken();

        $this->withToken($token)
            ->postJson('http://10.91.10.132/api/mobile/session')
            ->assertOk()
            ->assertJsonPath('url', fn (string $url) => str_starts_with(
                $url,
                'http://10.91.10.132/mobile/session/'
            ));
    }

    public function test_mobile_session_url_uses_public_url_otherwise(): void
    {
        $token = $this->approvedDeviceToken();

        $this->withToken($token)
            ->postJson('https://kassa.example.com/api/mobile/session')
            ->assertOk()
            ->assertJsonPath('url', fn (string $url) => str_starts_with(
                $url,
                'https://kassa.example.com/mobile/session/'
            ));
    }

    private function approvedDeviceToken(): string
    {
        $token = Str::random(64);

        Device::create([
            'uuid' => (string) Str::uuid(),
            'fingerprint' => (string) Str::uuid(),
            'name' => 'Tablet',
            'platform' => 'android',
            'status' => DeviceStatus::Approved,
            'api_token' => $token,
        ]);

        return $token;
    }
}
