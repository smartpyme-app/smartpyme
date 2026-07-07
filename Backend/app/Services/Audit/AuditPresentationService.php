<?php

namespace App\Services\Audit;

use App\Models\Inventario\Producto;

class AuditPresentationService
{
    /** @var array<int, string> */
    private array $productNames = [];

    /** @var array<string, string> */
    private array $documentReferences = [];

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
        'App\\Models\\Contabilidad\\Partidas\\Partida' => 'Partida contable',
        'App\\Models\\Planilla\\Planilla' => 'Planilla',
        'App\\Models\\Admin\\Sucursal' => 'Sucursal',
        'App\\Models\\Admin\\FormaDePago' => 'Forma de pago',
        'App\\Models\\Admin\\Impuesto' => 'Impuesto',
        'App\\Models\\Ventas\\Clientes\\Cliente' => 'Cliente',
        'App\\Models\\Compras\\Proveedores\\Proveedor' => 'Proveedor',
        'App\\Models\\Inventario\\Paquete' => 'Paquete',
        'App\\Models\\Restaurante\\PedidoRestaurante' => 'Pedido',
        'App\\Models\\Restaurante\\Comanda' => 'Comanda',
    ];

    private const PRODUCT_AWARE_TYPES = [
        'App\\Models\\Inventario\\Ajuste',
        'App\\Models\\Inventario\\Producto',
    ];

    /** @param array<int, string> $names */
    public function setProductNames(array $names): void
    {
        $this->productNames = $names;
    }

    /** @param array<string, string> $refs */
    public function setDocumentReferences(array $refs): void
    {
        $this->documentReferences = $refs;
    }

    public function describe(
        string $event,
        string $type,
        array $newValues,
        ?string $userName,
        array $oldValues = [],
        ?int $auditableId = null
    ): string {
        $action = self::EVENTS[$event] ?? $event;
        $label = self::TYPE_LABELS[$type] ?? class_basename($type);
        $who = $userName ?: 'Sistema';

        $productName = $this->resolveProductName($type, $newValues, $oldValues);
        if ($productName) {
            return sprintf('%s %s %s «%s»', $who, $action, $label, $productName);
        }

        $ref = $this->resolveReference($type, $newValues, $oldValues, $auditableId);

        return sprintf('%s %s %s #%s', $who, $action, $label, $ref);
    }

    private function resolveReference(string $type, array $newValues, array $oldValues, ?int $auditableId): string
    {
        $merged = array_merge($oldValues, $newValues);

        $fromAudit = $merged['correlativo']
            ?? $merged['referencia']
            ?? $merged['numero_comanda']
            ?? $merged['num_guia']
            ?? $merged['codigo']
            ?? $merged['concepto']
            ?? $merged['nombre']
            ?? null;

        if ($fromAudit !== null && $fromAudit !== '') {
            return (string) $fromAudit;
        }

        if ($auditableId !== null) {
            $key = "{$type}:{$auditableId}";
            if (isset($this->documentReferences[$key])) {
                return $this->documentReferences[$key];
            }
        }

        return (string) ($auditableId ?? '?');
    }

    private function resolveProductName(string $type, array $newValues, array $oldValues): ?string
    {
        if (! in_array($type, self::PRODUCT_AWARE_TYPES, true)) {
            return null;
        }

        if ($type === 'App\\Models\\Inventario\\Producto') {
            return $newValues['nombre'] ?? $oldValues['nombre'] ?? null;
        }

        $productId = $newValues['id_producto'] ?? $oldValues['id_producto'] ?? null;
        if (! $productId) {
            return null;
        }

        if (isset($this->productNames[$productId])) {
            return $this->productNames[$productId];
        }

        return Producto::withoutGlobalScopes()->where('id', $productId)->value('nombre');
    }
}
