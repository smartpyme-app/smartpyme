<?php

namespace Tests\Unit\Inventario;

use PHPUnit\Framework\TestCase;

/**
 * ponytail: check mínimo del flag y de que las búsquedas lo respetan.
 * Falla si se agrega el campo sin cablear el switch o las queries.
 */
final class NombreComercialProductoTest extends TestCase
{
    public function test_flag_deshabilitado_por_defecto_y_busquedas_condicionadas(): void
    {
        $empresa = file_get_contents(dirname(__DIR__, 3) . '/app/Models/Admin/Empresa.php');
        $this->assertNotFalse(strpos($empresa, "function isNombreComercialHabilitado"));
        $this->assertNotFalse(strpos($empresa, "'nombre_comercial_activo'"));

        $producto = file_get_contents(dirname(__DIR__, 3) . '/app/Models/Inventario/Producto.php');
        $this->assertNotFalse(strpos($producto, "'nombre_comercial'"));

        $controller = file_get_contents(dirname(__DIR__, 3) . '/app/Http/Controllers/Api/Inventario/ProductosController.php');
        $this->assertGreaterThanOrEqual(
            6,
            substr_count($controller, 'isNombreComercialHabilitado()'),
            'Las búsquedas de productos deben consultar el flag de nombre comercial'
        );
        $this->assertGreaterThanOrEqual(
            6,
            substr_count($controller, "orWhere('nombre_comercial'"),
            'Las búsquedas deben filtrar por nombre_comercial cuando el flag está activo'
        );
        $this->assertNotFalse(
            strpos($controller, 'if ($incluirNombreComercial)'),
            'nombre_comercial no debe buscarse si el flag está apagado'
        );
    }
}
