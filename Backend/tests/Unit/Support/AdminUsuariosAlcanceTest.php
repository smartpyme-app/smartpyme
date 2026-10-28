<?php

namespace Tests\Unit\Support;

use App\Models\User;
use App\Support\AdminUsuariosAlcance;
use Illuminate\Http\Request;
use Tests\TestCase;

class AdminUsuariosAlcanceTest extends TestCase
{
    public function test_buscador_agrupado_no_rompe_where_in(): void
    {
        $query = User::query()->whereIn('id_empresa', [10, 20]);
        AdminUsuariosAlcance::aplicarBuscador($query, 'juan');
        $sql = $query->toSql();

        $this->assertStringContainsString('id_empresa', $sql);
        $this->assertMatchesRegularExpression('/\(\s*.*name.*or.*email/si', $sql);
    }

    public function test_filtro_empresa_no_permitida_devuelve_vacio(): void
    {
        $actor = new User(['id_empresa' => 1]);

        $request = Request::create('/', 'GET', ['id_empresa' => 99999]);
        $query = User::query();
        AdminUsuariosAlcance::aplicarAlQuery($query, $actor, $request);

        $this->assertStringContainsString('1 = 0', $query->toSql());
    }
}
