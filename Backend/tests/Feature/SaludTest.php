<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * /api/salud es lo que revisa el despliegue a producción antes de dar por buena una versión.
 */
final class SaludTest extends TestCase
{
    public function test_responde_ok_y_no_requiere_sesion(): void
    {
        $this->getJson('/api/salud')
            ->assertOk()
            ->assertJson(['ok' => true])
            ->assertJsonStructure(['ok', 'version'])
            ->assertHeader('Cache-Control', 'no-store, private');
    }
}
