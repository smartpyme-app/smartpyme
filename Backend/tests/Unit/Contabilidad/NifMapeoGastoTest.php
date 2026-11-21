<?php

namespace Tests\Unit\Contabilidad;

use App\Models\Contabilidad\NifCategoria;
use App\Models\Contabilidad\NifMapeoGasto;
use Database\Seeders\NifMapeoSeeder;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NifMapeoGastoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        Schema::create('nif_categorias', function ($table) {
            $table->id();
            $table->string('codigo', 40)->unique();
            $table->string('nombre', 120);
            $table->boolean('afecta_resultados')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->string('norma', 30)->default('NIIF_PYMES');
            $table->timestamps();
        });

        Schema::create('nif_mapeo_gastos', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa')->default(0);
            $table->string('origen', 10);
            $table->string('clave', 120);
            $table->foreignId('id_nif_categoria')->constrained('nif_categorias');
            $table->boolean('revisar')->default(false);
            $table->timestamps();
        });

        (new NifMapeoSeeder)->run();
    }

    public function test_categoria_o_tipo_custom_sin_mapeo_cae_en_otros_gastos(): void
    {
        $resTipoCustom = NifMapeoGasto::resolver(10, 'Suscripciones Custom');
        $this->assertNotNull($resTipoCustom);
        $this->assertSame('OTROS_GASTOS', $resTipoCustom->categoria->codigo);

        $resCatCustom = NifMapeoGasto::resolver(10, '', 999);
        $this->assertNotNull($resCatCustom);
        $this->assertSame('OTROS_GASTOS', $resCatCustom->categoria->codigo);
    }

    public function test_respeta_mapeo_base_y_override_de_empresa(): void
    {
        $base = NifMapeoGasto::resolver(10, 'Alquiler');
        $this->assertSame('GASTOS_ADMIN', $base->categoria->codigo);

        $catVenta = NifCategoria::where('codigo', 'GASTOS_VENTA')->firstOrFail();
        NifMapeoGasto::create([
            'id_empresa' => 10,
            'origen' => 'categoria',
            'clave' => '77',
            'id_nif_categoria' => $catVenta->id,
            'revisar' => false,
        ]);

        $override = NifMapeoGasto::resolver(10, 'Alquiler', 77);
        $this->assertSame('GASTOS_VENTA', $override->categoria->codigo);
    }
}
