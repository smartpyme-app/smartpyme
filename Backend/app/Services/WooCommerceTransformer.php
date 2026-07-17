<?php

namespace App\Services;

use App\Constants\ShopifyConstant;
use App\Models\Admin\Empresa;
use App\Models\MH\Departamento;
use App\Models\MH\Municipio;

class WooCommerceTransformer
{
    /**
     * Transforma datos de cliente de WooCommerce al formato de tu sistema.
     * Soporta 'billing' (REST API) y 'billing_address' (algunos webhooks).
     */
    public function transformarCliente($wooData)
    {
        $billing = $wooData['billing'] ?? $wooData['billing_address'] ?? [];
        $shipping = $wooData['shipping'] ?? [];

        if (empty($billing['address_1']) && !empty($shipping['address_1'])) {
            $billing = array_merge($shipping, $billing);
        }

        $correo = $billing['email'] ?? $wooData['email'] ?? 'woocommerce-' . ($wooData['id'] ?? uniqid()) . '@cliente.temp';
        $direccionCompleta = $this->truncarTexto(trim(($billing['address_1'] ?? '') . ' ' . ($billing['address_2'] ?? '')), 500);
        $empresaId = $wooData['id_empresa'] ?? null;
        $stateCode = $billing['state'] ?? '';
        $codDepartamento = $this->obtenerCodigoDepartamento($stateCode, $empresaId);
        $nombreDepartamento = $this->truncarTexto($this->obtenerNombreDepartamento($codDepartamento, $stateCode), 255);
        $ciudad = $this->truncarTexto(trim((string) ($billing['city'] ?? '')), 255);
        $codMunicipio = $this->resolverCodigoMunicipio($ciudad, $codDepartamento);

        $dui = $this->extraerMeta($wooData, '_billing_dui_sv');
        $nit = $this->extraerMeta($wooData, '_billing_nit_sv');
        $ncr = $this->extraerMeta($wooData, '_billing_ncr_sv');
        $razonSocial = $this->extraerMeta($wooData, '_billing_nombre_razon_social_sv');
        $giro = $this->extraerMeta($wooData, '_billing_actividad_economica_sv');

        return [
            'nombre' => $billing['first_name'] ?? '',
            'apellido' => $billing['last_name'] ?? '',
            'nombre_empresa' => $razonSocial !== '' ? $razonSocial : ($billing['company'] ?? ''),
            'telefono' => $billing['phone'] ?? '',
            'correo' => $correo,
            'direccion' => $direccionCompleta,
            'pais' => $billing['country'] ?? '',
            'cod_pais' => $billing['country'] ?? '',
            'municipio' => $ciudad,
            'departamento' => $nombreDepartamento,
            'cod_municipio' => $codMunicipio,
            'cod_departamento' => $codDepartamento,
            'dui' => $dui !== '' ? $dui : null,
            'nit' => $nit !== '' ? $nit : null,
            'ncr' => $ncr !== '' ? $ncr : null,
            'giro' => $giro !== '' ? $giro : null,
            'tipo' => 'Persona',
            'empresa_telefono' => $billing['phone'] ?? '',
            'empresa_direccion' => $this->truncarTexto($direccionCompleta, 250),
            'enable' => 1,
            'id_empresa' => $wooData['id_empresa'],
            'id_usuario' => $wooData['id_usuario'],
        ];
    }

    /**
     * Transforma datos de venta de WooCommerce
     */
    public function transformarVenta($wooData, $clienteId, $documentoId, $correlativo)
    {
        $total = (float) ($wooData['total'] ?? 0);
        $totalTax = (float) ($wooData['total_tax'] ?? 0);
        $discountTotal = (float) ($wooData['discount_total'] ?? 0);
        $subtotalProductos = 0.0;

        foreach ($wooData['line_items'] ?? [] as $item) {
            $subtotalProductos += (float) ($item['total'] ?? 0);
        }

        return [
            'codigo_generacion' => null,
            'estado' => $this->mapearEstado($wooData['status'] ?? 'completed'),
            'forma_pago' => $this->mapearFormaPago($wooData),
            'observaciones' => $wooData['customer_note'] ?? '',
            'fecha' => $wooData['date_created'] ?? now()->toISOString(),
            'fecha_pago' => $wooData['date_paid'] ?? $wooData['date_created'] ?? now()->toISOString(),
            'total_costo' => 0,
            'total' => $total,
            'sub_total' => $subtotalProductos > 0 ? $subtotalProductos : $total,
            'gravada' => $total - $totalTax,
            'cuenta_a_terceros' => 0,
            'iva' => $totalTax,
            'iva_retenido' => 0,
            'iva_percibido' => 0,
            'descuento' => $discountTotal,
            'id_cliente' => $clienteId,
            'correlativo' => $correlativo,
            'id_documento' => $documentoId,
            'id_bodega' => $wooData['id_bodega'],
            'id_empresa' => $wooData['id_empresa'],
            'id_usuario' => $wooData['id_usuario'],
            'id_sucursal' => $wooData['id_sucursal'],
            'id_canal' => $wooData['id_canal'],
        ];
    }

    /**
     * Transforma líneas de items a detalles de venta
     */
    public function transformarDetallesVenta($lineItem, $ventaId)
    {
        $subtotal = (float) ($lineItem['subtotal'] ?? 0);
        $total = (float) ($lineItem['total'] ?? 0);
        $totalTax = (float) ($lineItem['total_tax'] ?? 0);

        return [
            'cantidad' => (float) ($lineItem['quantity'] ?? 0),
            'costo' => 0,
            'precio' => (float) ($lineItem['price'] ?? 0),
            'total' => $total,
            'total_costo' => 0,
            'descuento' => max(0, $subtotal - $total),
            'no_sujeta' => 0,
            'exenta' => 0,
            'cuenta_a_terceros' => 0,
            'subtotal' => $subtotal,
            'gravada' => $total,
            'iva' => $totalTax,
            'descripcion' => $lineItem['name'] ?? '',
            'id_producto' => null,
            'id_venta' => $ventaId
        ];
    }

    /**
     * Actualiza el inventario
     */
    public function actualizarInventario($productoId, $cantidad, $bodegaId)
    {
        return [
            'id_producto' => $productoId,
            'id_bodega' => $bodegaId,
            'stock' => ['decrement' => $cantidad],
            'updated_at' => now()
        ];
    }

    public function mapearEstado($wooStatus)
    {
        $mapeo = [
            'pending' => 'Pendiente',
            'processing' => 'En Proceso',
            'on-hold' => 'Pendiente',
            'completed' => 'Pagada',
            'cancelled' => 'Anulada',
            'refunded' => 'Reembolsada',
            'failed' => 'Fallida',
        ];

        return $mapeo[$wooStatus] ?? 'Pagada';
    }

    public function mapearFormaPago($wooData)
    {
        $method = strtolower(trim((string) ($wooData['payment_method'] ?? '')));
        $title = strtolower(trim((string) ($wooData['payment_method_title'] ?? '')));

        $mapeo = [
            'cod' => 'Efectivo',
            'paypal' => 'PayPal',
            'bacs' => 'Transferencia bancaria',
            'cheque' => 'Cheque',
            'wompi_payment' => 'Tarjeta de crédito/débito',
            'stripe' => 'Tarjeta de crédito/débito',
        ];

        if (isset($mapeo[$method])) {
            return $mapeo[$method];
        }

        if (str_contains($title, 'paypal')) {
            return 'PayPal';
        }
        if (str_contains($title, 'efectivo') || str_contains($title, 'contra entrega')) {
            return 'Efectivo';
        }
        if (str_contains($title, 'transferencia')) {
            return 'Transferencia bancaria';
        }
        if (str_contains($title, 'wompi') || str_contains($title, 'tarjeta')) {
            return 'Tarjeta de crédito/débito';
        }

        return 'Tarjeta de crédito/débito';
    }

    public function transformarProducto($wooData, $id_empresa, $id_usuario, $id_sucursal)
    {
        return [
            'barcode' => $wooData['sku'],
            'nombre' => $wooData['name'],
            'descripcion' => isset($wooData['description']) ? $wooData['description'] : '',
            'id_empresa' => $id_empresa,
            'id_usuario' => $id_usuario,
            'id_sucursal' => $id_sucursal,
            'costo' => $wooData['price'],
            'precio' => $wooData['price'],
        ];
    }

    private function extraerMeta($wooData, string $key): string
    {
        foreach ($wooData['meta_data'] ?? [] as $meta) {
            if (($meta['key'] ?? '') === $key) {
                return trim((string) ($meta['value'] ?? ''));
            }
        }

        return '';
    }

    private function obtenerCodigoDepartamento(?string $stateCode, $empresaId): ?string
    {
        if (empty($stateCode)) {
            return null;
        }

        if ($empresaId) {
            $empresa = Empresa::find($empresaId);
            if ($empresa && $empresa->facturacion_electronica) {
                $codigo = ShopifyConstant::obtenerCodigoDepartamento($stateCode);

                return $codigo ?? substr($stateCode, 0, 10);
            }
        }

        return substr($stateCode, 0, 10);
    }

    private function obtenerNombreDepartamento(?string $codDepartamento, ?string $stateCode): string
    {
        if (!empty($codDepartamento)) {
            $nombre = Departamento::where('cod', $codDepartamento)->value('nombre');
            if ($nombre) {
                return $nombre;
            }
        }

        return (string) ($stateCode ?? '');
    }

    private function resolverCodigoMunicipio(?string $nombreCiudad, ?string $codDepartamento): ?string
    {
        if (empty($nombreCiudad)) {
            return null;
        }

        $nombreNormalizado = strtolower(trim($nombreCiudad));

        $query = Municipio::whereRaw('LOWER(TRIM(nombre)) = ?', [$nombreNormalizado]);
        if (!empty($codDepartamento)) {
            $query->where('cod_departamento', $codDepartamento);
        }

        $municipio = $query->first();
        if ($municipio) {
            return $municipio->cod;
        }

        $queryParcial = Municipio::whereRaw('LOWER(nombre) LIKE ?', ['%' . $nombreNormalizado . '%']);
        if (!empty($codDepartamento)) {
            $queryParcial->where('cod_departamento', $codDepartamento);
        }

        $municipioParcial = $queryParcial->first();

        return $municipioParcial?->cod;
    }

    private function truncarTexto(string $value, int $max): string
    {
        if ($max <= 0 || $value === '') {
            return $value;
        }

        return mb_strlen($value) > $max ? mb_substr($value, 0, $max) : $value;
    }
}
