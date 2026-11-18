<?php

namespace Database\Seeders;

use App\Models\Contabilidad\NifCategoria;
use App\Models\Contabilidad\NifMapeoGasto;
use Illuminate\Database\Seeder;

class NifMapeoSeeder extends Seeder
{
    /** codigo => [nombre, afecta_resultados] */
    public const CATALOGO = [
        'COSTO_VENTAS' => ['Costo de ventas', true],
        'GASTOS_VENTA' => ['Gastos de venta', true],
        'GASTOS_ADMIN' => ['Gastos administrativos', true],
        'GASTOS_FINANCIEROS' => ['Gastos financieros', true],
        'OTROS_GASTOS' => ['Otros gastos', true],
        'IMPUESTO_RENTA' => ['Impuesto sobre la renta', true],
        'NO_GASTO' => ['No es gasto del periodo (activo fijo)', false],
    ];

    /**
     * Tipo de gasto de SmartPyme => [categoría NIIF, revisar].
     * revisar = true cuando la función real depende de quién genera el gasto
     * (el sueldo de ventas es gasto de venta, el de oficina es administrativo).
     */
    public const TIPOS = [
        'Costo de venta' => ['COSTO_VENTAS', false],
        'Materia Prima' => ['COSTO_VENTAS', false],
        'Insumos' => ['COSTO_VENTAS', false],
        'Compra de Mercadería' => ['COSTO_VENTAS', false],
        'Marketing' => ['GASTOS_VENTA', false],
        'Publicidad' => ['GASTOS_VENTA', false],
        'Pago comisión' => ['GASTOS_VENTA', false],
        'Combustible' => ['GASTOS_VENTA', true],
        'Planilla' => ['GASTOS_ADMIN', true],
        'Sueldos y Salarios' => ['GASTOS_ADMIN', true],
        'ISSS Patronal' => ['GASTOS_ADMIN', true],
        'AFP Patronal' => ['GASTOS_ADMIN', true],
        'Alquiler' => ['GASTOS_ADMIN', true],
        'Mantenimiento' => ['GASTOS_ADMIN', true],
        'Gastos varios' => ['GASTOS_ADMIN', true],
        'Impuestos' => ['GASTOS_ADMIN', true],
        'Servicios' => ['GASTOS_ADMIN', false],
        'Energía eléctrica' => ['GASTOS_ADMIN', false],
        'Agua' => ['GASTOS_ADMIN', false],
        'Insumos de oficina' => ['GASTOS_ADMIN', false],
        'Gastos Administrativos' => ['GASTOS_ADMIN', false],
        'Préstamos' => ['GASTOS_FINANCIEROS', true],
        'Activo Fijo' => ['NO_GASTO', false],
    ];

    public function run()
    {
        $orden = 0;
        $ids = [];
        foreach (self::CATALOGO as $codigo => [$nombre, $afecta]) {
            $ids[$codigo] = NifCategoria::updateOrCreate(
                ['codigo' => $codigo],
                ['nombre' => $nombre, 'afecta_resultados' => $afecta, 'orden' => ++$orden, 'norma' => 'NIIF_PYMES']
            )->id;
        }

        foreach (self::TIPOS as $tipo => [$codigo, $revisar]) {
            NifMapeoGasto::updateOrCreate(
                ['id_empresa' => 0, 'origen' => 'tipo', 'clave' => $tipo],
                ['id_nif_categoria' => $ids[$codigo], 'revisar' => $revisar]
            );
        }
    }
}
