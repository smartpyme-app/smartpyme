<?php

namespace Tests\Unit\Support;

use App\Models\Compras\Compra;
use App\Support\ComprasListQuery;
use Illuminate\Http\Request;
use Tests\TestCase;

final class ComprasListQueryRetaceoTest extends TestCase
{
    public function test_filtro_retaceo_solo_cuando_es_true(): void
    {
        $this->assertFalse(ComprasListQuery::filtroRetaceoActivo(new Request));
        $this->assertFalse(ComprasListQuery::filtroRetaceoActivo(new Request(['es_retaceo' => 'false'])));
        $this->assertFalse(ComprasListQuery::filtroRetaceoActivo(new Request(['es_retaceo' => '0'])));
        $this->assertTrue(ComprasListQuery::filtroRetaceoActivo(new Request(['es_retaceo' => 'true'])));
        $this->assertTrue(ComprasListQuery::filtroRetaceoActivo(new Request(['es_retaceo' => '1'])));
    }

    public function test_apply_agrega_condiciones_retaceo_en_sql(): void
    {
        $sql = ComprasListQuery::apply(Compra::query(), new Request(['es_retaceo' => 'true']))->toSql();
        $this->assertStringContainsString('`es_retaceo`', $sql);
    }
}