<?php

namespace Tests\Unit\Seguridad;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Support\GuardiaBaseDePruebas;
use Tests\TestCase;

/**
 * Si esta prueba falla, las pruebas podrían borrar una base con datos de clientes.
 */
class GuardiaBaseDePruebasTest extends TestCase
{
    public function test_las_pruebas_corren_en_sqlite_en_memoria_sin_config_cacheada(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertFalse(app()->configurationIsCached());
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
    }

    public function test_reconoce_bases_de_pruebas(): void
    {
        $this->assertTrue(GuardiaBaseDePruebas::esBaseDePruebas('sqlite', ':memory:'));
        $this->assertTrue(GuardiaBaseDePruebas::esBaseDePruebas('mysql', 'smartpyme_testing'));
        $this->assertTrue(GuardiaBaseDePruebas::esBaseDePruebas('mariadb', 'smartpyme_test'));
        $this->assertTrue(GuardiaBaseDePruebas::esBaseDePruebas('mysql', 'smartpyme_pruebas'));

        $this->assertFalse(GuardiaBaseDePruebas::esBaseDePruebas('mysql', 'smartpyme_prod'));
        $this->assertFalse(GuardiaBaseDePruebas::esBaseDePruebas('mysql', 'smartpyme'));
        $this->assertFalse(GuardiaBaseDePruebas::esBaseDePruebas('mysql', 'smartpyme_analitica'));
        $this->assertFalse(GuardiaBaseDePruebas::esBaseDePruebas('mysql', 'testing_prod'));
        $this->assertFalse(GuardiaBaseDePruebas::esBaseDePruebas('mysql', ''));
        $this->assertFalse(GuardiaBaseDePruebas::esBaseDePruebas('sqlite', '/var/data/database.sqlite'));
    }

    public function test_bloquea_consultas_a_una_base_real_aunque_la_conexion_se_abra_despues(): void
    {
        // Puerto 1: si la guardia fallara, la conexión no llegaría a ningún servidor.
        config(['database.connections.prod_falsa' => [
            'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 1,
            'database' => 'smartpyme_prod', 'username' => 'nadie', 'password' => 'nada',
        ]]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Consulta bloqueada');

        DB::connection('prod_falsa')->statement('DROP TABLE empresas');
    }
}
