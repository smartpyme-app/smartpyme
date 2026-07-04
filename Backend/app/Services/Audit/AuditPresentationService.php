<?php

namespace App\Services\Audit;

class AuditPresentationService
{
    private const EVENTS = [
        'created' => 'creó',
        'updated' => 'actualizó',
        'deleted' => 'eliminó',
        'restored' => 'restauró',
    ];

    private const TYPE_LABELS = [
        'App\\Models\\Ventas\\Venta' => 'Venta',
        'App\\Models\\Compras\\Compra' => 'Compra',
        'App\\Models\\CotizacionVenta' => 'Cotización',
        'App\\Models\\Ventas\\Orden_Produccion\\OrdenProduccion' => 'Orden de producción',
        'App\\Models\\OrdenCompra' => 'Orden de compra',
        'App\\Models\\Compras\\Gastos\\Gasto' => 'Gasto',
        'App\\Models\\Inventario\\Entradas\\Entrada' => 'Entrada de inventario',
        'App\\Models\\Inventario\\Salidas\\Salida' => 'Salida de inventario',
        'App\\Models\\Inventario\\Ajuste' => 'Ajuste de inventario',
        'App\\Models\\Inventario\\Traslados\\Traslado' => 'Traslado',
        'App\\Models\\Inventario\\Producto' => 'Producto',
    ];

    public function describe(string $event, string $type, array $newValues, ?string $userName): string
    {
        $action = self::EVENTS[$event] ?? $event;
        $label = self::TYPE_LABELS[$type] ?? class_basename($type);
        $ref = $newValues['correlativo']
            ?? $newValues['codigo']
            ?? $newValues['nombre']
            ?? $newValues['id']
            ?? '?';
        $who = $userName ?: 'Sistema';

        return sprintf('%s %s %s #%s', $who, $action, $label, $ref);
    }
}
