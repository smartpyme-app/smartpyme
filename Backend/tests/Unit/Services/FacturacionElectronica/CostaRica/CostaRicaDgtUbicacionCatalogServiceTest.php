<?php

namespace Tests\Unit\Services\FacturacionElectronica\CostaRica;

use App\Services\FacturacionElectronica\CostaRica\CostaRicaDgtUbicacionCatalogService;
use Tests\TestCase;

final class CostaRicaDgtUbicacionCatalogServiceTest extends TestCase
{
    private CostaRicaDgtUbicacionCatalogService $catalog;

    protected function setUp(): void
    {
        parent::setUp();
        $distPath = dirname(__DIR__, 5).'/vendor/dazza-dev/dgt-xml-generator/src/Data/distritos.json';
        if (! is_readable($distPath)) {
            $this->markTestSkipped('Catálogo DGT no instalado (composer install).');
        }
        $this->catalog = new CostaRicaDgtUbicacionCatalogService;
    }

    public function test_codigo_5_digitos_desde_provincia_canton_distrito_xml(): void
    {
        self::assertSame('40101', $this->catalog->codigoDistritoInec5DesdeUbicacionXml('4', '01', '01'));
    }

    public function test_etiquetas_pdf_heredia_desde_codigos_xml(): void
    {
        $labels = $this->catalog->etiquetasUbicacionParaRepresentacionGrafica([
            'province' => ['code' => '4'],
            'canton' => '01',
            'district' => '01',
        ]);

        self::assertSame('Heredia', $labels['province']);
        self::assertSame('Heredia', $labels['canton']);
        self::assertSame('Heredia', $labels['district']);
    }
}
