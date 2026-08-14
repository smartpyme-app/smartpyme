<?php

namespace Tests\Feature\Contabilidad\Honduras;

use App\Services\Contabilidad\LibrosIva\LibroIvaPaisResolver;
use Tests\TestCase;

class LibrosIvaHdCountryGuardTest extends TestCase
{
    public function test_empresa_no_hn_recibe_403_en_listados_hd(): void
    {
        $resolver = $this->createMock(LibroIvaPaisResolver::class);
        $resolver->method('tipo')->willReturn(LibroIvaPaisResolver::TIPO_SV);
        $this->app->instance(LibroIvaPaisResolver::class, $resolver);

        $this->withoutMiddleware();

        foreach (['compras', 'consumidores', 'contribuyentes'] as $libro) {
            // El Handler del proyecto serializa abort() como {code, error}, no {message}.
            $this->getJson("/api/libro-iva-hd/{$libro}?inicio=2026-07-01&fin=2026-07-31")
                ->assertForbidden()
                ->assertJsonFragment(['error' => 'Esta operación solo está disponible para empresas de Honduras.']);
        }
    }
}
