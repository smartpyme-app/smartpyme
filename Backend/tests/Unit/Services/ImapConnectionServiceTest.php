<?php

namespace Tests\Unit\Services;

use App\Services\Imap\ImapConnectionService;
use PHPUnit\Framework\TestCase;

class ImapConnectionServiceTest extends TestCase
{
    /**
     * Test that invalid config returns false.
     */
    public function testTestConnectionReturnsFalseForInvalidHost(): void
    {
        $service = new ImapConnectionService();
        $config = [
            'host' => 'invalid-host-that-does-not-exist.local',
            'port' => 993,
            'encryption' => 'ssl',
            'user' => 'test@example.com',
            'password' => 'secret',
        ];

        $result = $service->testConnection($config);

        $this->assertFalse($result);
    }

    /**
     * Test buildClientConfig produces correct structure.
     */
    public function testBuildClientConfigMapsEncryption(): void
    {
        $service = new ImapConnectionService();
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('buildClientConfig');
        $method->setAccessible(true);

        $config = [
            'host' => 'imap.example.com',
            'port' => 993,
            'encryption' => 'ssl',
            'user' => 'user@example.com',
            'password' => 'pass',
        ];

        $result = $method->invoke($service, $config);

        $this->assertEquals('imap.example.com', $result['host']);
        $this->assertEquals(993, $result['port']);
        $this->assertEquals('ssl', $result['encryption']);
        $this->assertEquals('user@example.com', $result['username']);
        $this->assertEquals('pass', $result['password']);
    }
}
