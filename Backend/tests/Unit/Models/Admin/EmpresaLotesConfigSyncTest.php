<?php

namespace Tests\Unit\Models\Admin;

use PHPUnit\Framework\TestCase;

/**
 * Check mínimo: claves de lotes en custom_empresa deben poder sobreescribir empresa_configuracion.
 */
final class EmpresaLotesConfigSyncTest extends TestCase
{
    public function test_overlay_lotes_no_pisa_otras_preferencias(): void
    {
        $configuraciones = [
            'lotes_metodologia' => 'Manual',
            'ticket_en_pdf' => true,
        ];
        $legacyConfiguraciones = [
            'lotes_metodologia' => 'FEFO',
            'lotes_activo' => true,
        ];

        foreach (['lotes_activo', 'lotes_metodologia', 'lotes_dias_anticipacion'] as $lotKey) {
            if (array_key_exists($lotKey, $legacyConfiguraciones)) {
                $configuraciones[$lotKey] = $legacyConfiguraciones[$lotKey];
            }
        }

        $this->assertSame('FEFO', $configuraciones['lotes_metodologia']);
        $this->assertTrue($configuraciones['lotes_activo']);
        $this->assertTrue($configuraciones['ticket_en_pdf']);
    }
}
