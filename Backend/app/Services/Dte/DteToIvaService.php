<?php

namespace App\Services\Dte;

use App\Models\Compras\Compra;
use App\Models\Compras\Detalle as DetalleCompra;
use App\Models\Compras\Gastos\DetalleEgreso;
use App\Models\Compras\Gastos\Gasto;
use App\Models\Compras\Proveedores\Proveedor;
use App\Models\DteManagement\DteDocument;
use App\Models\DteManagement\DteTipoMapeo;
use App\Models\DteManagement\UserEmailAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Creates Compra or Gasto from a validated DTE document.
 * Runs in queue/listener context without auth - receives id_empresa, id_usuario explicitly.
 */
class DteToIvaService
{
    protected DteProductSearchService $productSearch;

    protected static array $tipoDteMap = [
        '01' => 'Factura',
        '03' => 'Crédito fiscal',
        '04' => 'Nota de Remisión',
        '05' => 'Nota de crédito',
        '06' => 'Nota de débito',
        '11' => 'Factura de exportación',
        '14' => 'Sujeto excluido',
    ];

    protected static array $formaPagoMap = [
        '01' => 'Efectivo',
        '02' => 'Tarjeta de Crédito',
        '03' => 'Tarjeta de Débito',
        '04' => 'Cheque',
        '05' => 'Transferencia',
        '06' => 'Crédito',
        '07' => 'Tarjeta de regalo',
        '08' => 'Dinero electrónico',
        '99' => 'Otros',
    ];

    public function __construct(DteProductSearchService $productSearch)
    {
        $this->productSearch = $productSearch;
    }

    /**
     * Insert DTE into Compra or Gasto according to DteTipoMapeo.destino.
     * Skips if compra type and processing_status is pendiente_clasificacion.
     *
     * @return array{success: bool, compra_id?: int, gasto_id?: int, skipped?: string}
     */
    public function insertFromDteDocument(DteDocument $document): array
    {
        $account = $document->userEmailAccount;
        if (!$account) {
            Log::warning('DteToIvaService: DteDocument has no user_email_account');
            return ['success' => false];
        }

        $mapeo = DteTipoMapeo::getByCodigo($document->dte_type);
        $destino = $document->destino ?? $mapeo?->destino ?? 'compra';
        $tipoDocumento = $mapeo?->tipo_documento ?? (self::$tipoDteMap[$document->dte_type] ?? 'Factura');

        if ($destino === 'compra' && $document->processing_status === 'pendiente_clasificacion') {
            return [
                'success' => true, 
                'skipped' => 'pendiente_clasificacion'
            ];
        }

        $jsonContent = $document->json_path
            ? Storage::disk('dtes')->get($document->json_path)
            : null;

        if (!$jsonContent) {
            Log::warning('DteToIvaService: No JSON path for DTE', ['dte_uuid' => $document->dte_uuid]);
            return ['success' => false];
        }

        $jsonData = json_decode($jsonContent, true);
        if (!$jsonData) {
            return ['success' => false];
        }

        $proveedor = $this->findOrCreateProveedor(
            $jsonData['emisor'] ?? [],
            $document->id_empresa,
            $account->user_id ?? $account->id
        );

        if (!$proveedor) {
            Log::warning('DteToIvaService: Could not find/create provider', ['dte_uuid' => $document->dte_uuid]);
            return ['success' => false];
        }

        $idSucursal = $account->id_sucursal ?? $this->getDefaultSucursalId($document->id_empresa);
        $idBodega = $account->id_bodega ?? $this->getDefaultBodegaId($document->id_empresa);
        $actualizarInventario = (bool) ($account->actualizar_inventario ?? false);
        $idUsuario = $account->user_id ?? 1;

        if ($destino === 'compra') {
            return $this->createCompra(
                $document,
                $jsonData,
                $proveedor,
                $tipoDocumento,
                $idSucursal,
                $idBodega,
                $idUsuario,
                $actualizarInventario
            );
        }

        return $this->createGasto(
            $document,
            $jsonData,
            $proveedor,
            $tipoDocumento,
            $idSucursal,
            $idUsuario
        );
    }

    protected function createCompra(
        DteDocument $document,
        array $jsonData,
        Proveedor $proveedor,
        string $tipoDocumento,
        ?int $idSucursal,
        ?int $idBodega,
        int $idUsuario,
        bool $actualizarInventario
    ): array {
        $identificacion = $jsonData['identificacion'] ?? [];
        $resumen = $jsonData['resumen'] ?? [];
        $cuerpoDocumento = $jsonData['cuerpoDocumento'] ?? [];

        $referencia = $identificacion['numeroControl'] ?? $document->dte_number ?? $document->dte_uuid;
        $fecha = $identificacion['fecEmi'] ?? $document->emission_date?->format('Y-m-d') ?? now()->format('Y-m-d');

        $total = (float) ($resumen['totalPagar'] ?? $resumen['montoTotalOperacion'] ?? $document->total_amount ?? 0);
        $subTotal = (float) ($resumen['subTotal'] ?? $resumen['totalGravada'] ?? $total);
        $iva = $this->extractIvaFromResumen($resumen);

        $formaPago = 'Efectivo';
        if (!empty($resumen['pagos'][0]['codigo'])) {
            $formaPago = self::$formaPagoMap[$resumen['pagos'][0]['codigo']] ?? 'Efectivo';
        }

        $estado = ($formaPago === 'Crédito') ? 'Pendiente' : 'Pagada';

        if (Compra::withoutGlobalScopes()
            ->where('id_empresa', $document->id_empresa)
            ->where('codigo_generacion', $document->dte_uuid)
            ->exists()) {
            return ['success' => true, 'skipped' => 'duplicate'];
        }

        return DB::transaction(function () use (
            $document,
            $jsonData,
            $proveedor,
            $tipoDocumento,
            $idSucursal,
            $idBodega,
            $idUsuario,
            $actualizarInventario,
            $referencia,
            $fecha,
            $total,
            $subTotal,
            $iva,
            $formaPago,
            $estado,
            $cuerpoDocumento
        ) {
            $compra = Compra::withoutGlobalScopes()->create([
                'fecha' => $fecha,
                'estado' => $estado,
                'forma_pago' => $formaPago,
                'tipo_documento' => $tipoDocumento,
                'referencia' => $referencia,
                'id_proveedor' => $proveedor->id,
                'iva' => $iva,
                'sub_total' => $subTotal,
                'total' => $total,
                'id_bodega' => $idBodega,
                'id_sucursal' => $idSucursal,
                'id_empresa' => $document->id_empresa,
                'id_usuario' => $idUsuario,
                'codigo_generacion' => $document->dte_uuid,
                'numero_control' => $document->dte_number,
                'dte' => json_encode($jsonData),
                'cotizacion' => 0,
            ]);

            foreach ($cuerpoDocumento as $index => $item) {
                $descripcion = $item['descripcion'] ?? '';
                $productId = $this->productSearch->findProductByDescription($descripcion, $document->id_empresa);

                if (!$productId) {
                    continue;
                }

                $cantidad = (float) ($item['cantidad'] ?? 0);
                $precioUni = (float) ($item['precioUni'] ?? 0);
                $ventaTotal = (float) ($item['ventaTotal'] ?? $cantidad * $precioUni);

                $ivaItem = $this->extractIvaFromItem($item);
                $subtotalItem = $ventaTotal - $ivaItem;

                DetalleCompra::withoutGlobalScopes()->create([
                    'id_compra' => $compra->id,
                    'id_producto' => $productId,
                    'cantidad' => $cantidad,
                    'costo' => $precioUni,
                    'subtotal' => $subtotalItem,
                    'total' => $ventaTotal,
                    'iva' => $ivaItem,
                    'no_sujeta' => 0,
                    'exenta' => 0,
                ]);
            }

            $document->update(['processing_status' => 'processed']);

            return ['success' => true, 'compra_id' => $compra->id];
        });
    }

    protected function createGasto(
        DteDocument $document,
        array $jsonData,
        Proveedor $proveedor,
        string $tipoDocumento,
        ?int $idSucursal,
        int $idUsuario
    ): array {
        $identificacion = $jsonData['identificacion'] ?? [];
        $resumen = $jsonData['resumen'] ?? [];
        $cuerpoDocumento = $jsonData['cuerpoDocumento'] ?? [];

        $referencia = $identificacion['numeroControl'] ?? $document->dte_number ?? $document->dte_uuid;
        $fecha = $identificacion['fecEmi'] ?? $document->emission_date?->format('Y-m-d') ?? now()->format('Y-m-d');

        $total = (float) ($resumen['totalPagar'] ?? $resumen['montoTotalOperacion'] ?? $document->total_amount ?? 0);
        $subTotal = (float) ($resumen['subTotal'] ?? $resumen['totalGravada'] ?? $total);
        $iva = $this->extractIvaFromResumen($resumen);

        $formaPago = 'Efectivo';
        if (!empty($resumen['pagos'][0]['codigo'])) {
            $formaPago = self::$formaPagoMap[$resumen['pagos'][0]['codigo']] ?? 'Efectivo';
        }

        $estado = ($formaPago === 'Crédito') ? 'Pendiente' : 'Confirmado';
        $concepto = !empty($cuerpoDocumento)
            ? ($cuerpoDocumento[0]['descripcion'] ?? $document->issuer_name ?? 'DTE importado')
            : ($document->issuer_name ?? 'DTE importado');
        $tipo = $this->determinarCategoriaGasto($cuerpoDocumento);

        if (Gasto::withoutGlobalScopes()
            ->where('id_empresa', $document->id_empresa)
            ->where('codigo_generacion', $document->dte_uuid)
            ->exists()) {
            return ['success' => true, 'skipped' => 'duplicate'];
        }

        return DB::transaction(function () use (
            $document,
            $jsonData,
            $proveedor,
            $tipoDocumento,
            $idSucursal,
            $idUsuario,
            $referencia,
            $fecha,
            $total,
            $subTotal,
            $iva,
            $formaPago,
            $estado,
            $concepto,
            $tipo,
            $cuerpoDocumento
        ) {
            $gasto = Gasto::withoutGlobalScopes()->create([
                'fecha' => $fecha,
                'referencia' => $referencia,
                'tipo_documento' => $tipoDocumento,
                'concepto' => $concepto,
                'tipo' => $tipo,
                'estado' => $estado,
                'forma_pago' => $formaPago,
                'id_proveedor' => $proveedor->id,
                'sub_total' => $subTotal,
                'iva' => $iva,
                'total' => $total,
                'id_sucursal' => $idSucursal,
                'id_empresa' => $document->id_empresa,
                'id_usuario' => $idUsuario,
                'codigo_generacion' => $document->dte_uuid,
                'numero_control' => $document->dte_number,
                'dte' => json_encode($jsonData),
            ]);

            foreach ($cuerpoDocumento as $numeroItem => $item) {
                $ventaTotal = (float) ($item['ventaTotal'] ?? 0);
                $ivaItem = $this->extractIvaFromItem($item);

                DetalleEgreso::create([
                    'id_egreso' => $gasto->id,
                    'numero_item' => $numeroItem + 1,
                    'concepto' => $item['descripcion'] ?? '',
                    'tipo' => $tipo,
                    'cantidad' => (float) ($item['cantidad'] ?? 0),
                    'precio_unitario' => (float) ($item['precioUni'] ?? 0),
                    'sub_total' => $ventaTotal - $ivaItem,
                    'iva' => $ivaItem,
                    'total' => $ventaTotal,
                ]);
            }

            $document->update(['processing_status' => 'processed']);

            return ['success' => true, 'gasto_id' => $gasto->id];
        });
    }

    protected function findOrCreateProveedor(array $emisorData, int $idEmpresa, int $idUsuario): ?Proveedor
    {
        $nit = $emisorData['nit'] ?? $emisorData['numDocumento'] ?? null;
        if (empty($nit)) {
            return null;
        }

        $proveedor = Proveedor::withoutGlobalScopes()
            ->where('id_empresa', $idEmpresa)
            ->where('nit', $nit)
            ->first();

        if ($proveedor) {
            return $proveedor;
        }

        $proveedor = new Proveedor();
        $proveedor->tipo = 'Empresa';
        $proveedor->nombre_empresa = $emisorData['nombre'] ?? $emisorData['nombreComercial'] ?? 'Proveedor';
        $proveedor->nit = $nit;
        $proveedor->ncr = $emisorData['nrc'] ?? '';
        $proveedor->telefono = $emisorData['telefono'] ?? '';
        $proveedor->correo = $emisorData['correo'] ?? '';
        $proveedor->direccion = $emisorData['direccion']['complemento'] ?? 'No especificada';
        $proveedor->id_empresa = $idEmpresa;
        $proveedor->id_usuario = $idUsuario;
        $proveedor->save();

        return $proveedor;
    }

    protected function getDefaultSucursalId(int $idEmpresa): ?int
    {
        $sucursal = \App\Models\Admin\Sucursal::withoutGlobalScopes()
            ->where('id_empresa', $idEmpresa)
            ->where('activo', 1)
            ->orderBy('id')
            ->first();

        return $sucursal?->id;
    }

    protected function getDefaultBodegaId(int $idEmpresa): ?int
    {
        $bodega = \App\Models\Inventario\Bodega::withoutGlobalScopes()
            ->where('id_empresa', $idEmpresa)
            ->where('activo', '1')
            ->orderBy('id')
            ->first();

        return $bodega?->id;
    }

    protected function extractIvaFromResumen(array $resumen): float
    {
        if (isset($resumen['tributos']) && is_array($resumen['tributos'])) {
            foreach ($resumen['tributos'] as $tributo) {
                if (($tributo['codigo'] ?? '') === '20') {
                    return (float) ($tributo['valor'] ?? 0);
                }
            }
        }
        return 0;
    }

    protected function extractIvaFromItem(array $item): float
    {
        return (float) ($item['iva'] ?? 0);
    }

    protected function determinarCategoriaGasto(array $items): string
    {
        $keywords = [
            'Alquiler' => ['alquiler', 'renta', 'arrendamiento'],
            'Combustible' => ['combustible', 'gasolina', 'diesel'],
            'Servicios' => ['servicio', 'electricidad', 'agua', 'teléfono'],
            'Gastos varios' => [],
        ];

        $text = strtolower(implode(' ', array_column($items, 'descripcion')));

        foreach ($keywords as $categoria => $words) {
            foreach ($words as $w) {
                if (str_contains($text, $w)) {
                    return $categoria;
                }
            }
        }

        return 'Gastos varios';
    }
}
