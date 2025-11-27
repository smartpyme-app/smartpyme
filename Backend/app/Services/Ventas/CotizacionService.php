<?php

namespace App\Services\Ventas;

use App\Models\CotizacionVenta;
use App\Models\CotizacionVentaDetalle;
use App\Models\Admin\Documento;
use App\Models\Inventario\CustomFields\ProductCustomField;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CotizacionService
{
    /**
     * Crear o actualizar cotización
     *
     * @param array $data
     * @return CotizacionVenta
     */
    public function crearOActualizarCotizacion(array $data): CotizacionVenta
    {
        if (isset($data['id'])) {
            $cotizacion = CotizacionVenta::findOrFail($data['id']);
        } else {
            $cotizacion = new CotizacionVenta();
        }

        // Preparar datos para cotización
        $cotizacionData = $data;
        $cotizacionData['aplicar_retencion'] = $data['retencion'] ?? false;

        // Asegurar que id_empresa esté establecido
        if (!isset($cotizacionData['id_empresa'])) {
            $cotizacionData['id_empresa'] = Auth::user()->id_empresa;
        }

        // Excluir campos que no aplican a cotizaciones
        unset($cotizacionData['id_canal']);
        unset($cotizacionData['cotizacion']);

        $cotizacion->fill($cotizacionData);
        $cotizacion->save();

        return $cotizacion;
    }

    /**
     * Asignar correlativo a la cotización
     *
     * @param CotizacionVenta $cotizacion
     * @param int $idDocumento
     * @return void
     */
    public function asignarCorrelativo(CotizacionVenta $cotizacion, int $idDocumento): void
    {
        if (!$idDocumento) {
            return;
        }

        $documento = Documento::where('id', $idDocumento)
            ->lockForUpdate()
            ->firstOrFail();

        $cotizacion->correlativo = $documento->correlativo;
        $documento->increment('correlativo');
        $cotizacion->save();
    }

    /**
     * Guardar detalles de la cotización
     *
     * @param CotizacionVenta $cotizacion
     * @param array $detalles
     * @return void
     */
    public function guardarDetalles(CotizacionVenta $cotizacion, array $detalles): void
    {
        foreach ($detalles as $det) {
            if (isset($det['id'])) {
                $detalle = CotizacionVentaDetalle::findOrFail($det['id']);
            } else {
                $detalle = new CotizacionVentaDetalle();
            }

            // Mapear campos del detalle
            $detalleData = [
                'id_cotizacion_venta' => $cotizacion->id,
                'id_producto' => $det['id_producto'],
                'cantidad' => $det['cantidad'],
                'precio' => $det['precio'],
                'total' => $det['total'],
                'total_costo' => $det['total_costo'] ?? 0,
                'descuento' => $det['descuento'] ?? 0,
                'no_sujeta' => $det['no_sujeta'] ?? 0,
                'exenta' => $det['exenta'] ?? 0,
                'cuenta_a_terceros' => $det['cuenta_a_terceros'] ?? 0,
                'subtotal' => $det['subtotal'] ?? $det['total'],
                'gravada' => $det['gravada'] ?? 0,
                'iva' => $det['iva'] ?? 0,
                'descripcion' => $det['descripcion'] ?? $det['nombre_producto'] ?? '',
                'costo' => $det['costo'] ?? $det['total_costo'] ?? 0,
            ];

            if (isset($det['id_vendedor'])) {
                $detalleData['id_vendedor'] = $det['id_vendedor'];
            }

            $detalle->fill($detalleData);
            $detalle->save();

            // Guardar custom fields si existen
            $this->guardarCustomFields($detalle, $det);
        }
    }

    /**
     * Guardar custom fields de un detalle
     *
     * @param CotizacionVentaDetalle $detalle
     * @param array $detalleData
     * @return void
     */
    protected function guardarCustomFields(CotizacionVentaDetalle $detalle, array $detalleData): void
    {
        if (!isset($detalleData['custom_fields']) || !is_array($detalleData['custom_fields'])) {
            return;
        }

        foreach ($detalleData['custom_fields'] as $customField) {
            if (isset($customField['custom_field']['id'])) {
                ProductCustomField::updateOrCreate(
                    [
                        'cotizacion_venta_detalle_id' => $detalle->id,
                        'custom_field_id' => $customField['custom_field']['id']
                    ],
                    [
                        'custom_field_value_id' => $customField['custom_field_value']['id'] ?? null,
                        'valor' => $customField['valor'] ?? null
                    ]
                );
            }
        }
    }
}

