<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\NormalizeIncomingTraceHeaders;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class NormalizeIncomingTraceHeadersTest extends TestCase
{
    public function test_flattens_baggage_header_values_to_single_string(): void
    {
        $request = Request::create('/api/login', 'POST');
        $request->headers->set('baggage', ['a=1', 'b=2'], false);

        $middleware = new NormalizeIncomingTraceHeaders();
        $middleware->handle($request, static fn (Request $r) => new \Symfony\Component\HttpFoundation\Response('ok'));

        $this->assertSame('a=1,b=2', $request->headers->get('baggage'));
    }

    public function test_flattens_authorization_for_bearer_token_extraction(): void
    {
        $request = Request::create('/api/municipios', 'GET');
        $request->headers->set('Authorization', ['Bearer eyJ.test.token'], false);

        $middleware = new NormalizeIncomingTraceHeaders();
        $middleware->handle($request, static fn (Request $r) => new \Symfony\Component\HttpFoundation\Response('ok'));

        $this->assertSame('Bearer eyJ.test.token', $request->headers->get('Authorization'));
        $this->assertSame('eyJ.test.token', $request->bearerToken());
    }
}
