<?php

namespace Tests\Unit\Services;

use App\Services\Gmail\GmailOAuthService;
use PHPUnit\Framework\TestCase;

class GmailOAuthServiceTest extends TestCase
{
    /**
     * Test that invalid state throws InvalidArgumentException.
     */
    public function testHandleCallbackThrowsForInvalidState(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $service = new GmailOAuthService();
        $service->handleCallback('fake-code', 'invalid-base64-state');
    }

    /**
     * Test that malformed state (invalid json) throws.
     */
    public function testHandleCallbackThrowsForMalformedState(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $service = new GmailOAuthService();
        $state = base64_encode('not-valid-json');
        $service->handleCallback('code', $state);
    }

    /**
     * Test that state without user_id throws.
     */
    public function testHandleCallbackThrowsWhenStateMissingUserId(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $service = new GmailOAuthService();
        $state = base64_encode(json_encode(['id_empresa' => 1]));
        $service->handleCallback('code', $state);
    }
}
