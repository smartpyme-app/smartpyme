<?php

namespace Tests\Unit\Services;

use App\Services\BoxFul\BoxFulService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BoxFulServiceCacheTest extends TestCase
{
    public function test_forget_empresa_cache_limpia_token_y_states(): void
    {
        Cache::put('boxful_access_token_empresa_99', 'tok-viejo', 60);
        Cache::put('boxful_states_empresa_99', ['states' => ['San Salvador']], 60);

        BoxFulService::forgetEmpresaCache(99);

        $this->assertFalse(Cache::has('boxful_access_token_empresa_99'));
        $this->assertFalse(Cache::has('boxful_states_empresa_99'));
    }

    public function test_authenticate_with_credentials_falla_sin_persistir(): void
    {
        Http::fake([
            '*/auth/v2/client' => Http::response(['message' => 'Invalid credentials'], 401),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Error de autenticación con Boxful');

        (new BoxFulService())->authenticateWithCredentials('malo@test.com', 'wrong');
    }
}
