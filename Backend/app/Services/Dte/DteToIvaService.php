<?php

namespace App\Services\Dte;

use App\Models\Admin\Documento;
use App\Models\Compras\Compra;
use App\Models\Compras\Detalle as DetalleCompra;
use App\Models\Compras\Gastos\DetalleEgreso;
use App\Models\Compras\Gastos\Gasto;
use App\Models\Compras\Proveedores\Proveedor;
use App\Models\DteManagement\DteDocument;
use App\Models\DteManagement\DteTipoMapeo;
use App\Models\DteManagement\UserEmailAccount;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Producto;
use App\Services\Inventario\ProductoImportacionDteService;
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

    protected ProductoImportacionDteService $productoImportacionDteService;

    public function __construct(
        DteProductSearchService $productSearch,
        ProductoImportacionDteService $productoImportacionDteService
    ) {
        $this->productSearch = $productSearch;
        $this->productoImportacionDteService = $productoImportacionDteService;
    }

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

        $mapeo = DteTipoMapeo::getByCodigo($document->dte_type, $document->pais ?? 'SV');
        $destino = $document->destino ?? $mapeo?->destino ?? 'compra';
        $tipoDocumento = $mapeo?->tipo_documento ?? (self::$tipoDteMap[$document->dte_type] ?? 'Factura');

        if ($destino === 'compra' && $document->processing_status === 'pendiente_clasificacion') {
            return [
                'success' => true, 
                'skipped' => 'pendiente_clasificacion'
            ];
        }

        if ($document->processing_status === 'anulado') {
            return ['success' => false, 'skipped' => 'anulado'];
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

        $matchResult = $this->productSearch->resolveItems($cuerpoDocumento, $document->id_empresa);
        if (!$matchResult['all_matched'] && count($cuerpoDocumento) > 0) {
            $document->update(['processing_status' => 'pendiente_clasificacion']);

            return ['success' => true, 'skipped' => 'pendiente_clasificacion'];
        }

        $fecha = $identificacion['fecEmi'] ?? $document->emission_date?->format('Y-m-d') ?? now()->format('Y-m-d');
        $codigoGeneracion = $identificacion['codigoGeneracion'] ?? $document->dte_uuid;
        $numeroControl = $identificacion['numeroControl'] ?? $document->dte_number;
        $tipoDte = $identificacion['tipoDte'] ?? $document->dte_type;

        $referencia = $codigoGeneracion;
        $observaciones = '';
        $documentoRow = null;
        if ($idSucursal) {
            $documentoRow = Documento::withoutGlobalScopes()
                ->where('nombre', $tipoDocumento)
                ->where('id_sucursal', $idSucursal)
                ->first();
            if ($documentoRow && $documentoRow->correlativo !== null && trim((string) $documentoRow->correlativo) !== '') {
                $referencia = $documentoRow->correlativo;
                $observaciones = "Código generación MH: {$codigoGeneracion}";
            }
        }

        $total = (float) ($resumen['totalPagar'] ?? $resumen['montoTotalOperacion'] ?? $document->total_amount ?? 0);
        $subTotal = (float) ($resumen['subTotal'] ?? $resumen['subTotalVentas'] ?? $resumen['totalGravada'] ?? $total);
        $iva = $this->extractIvaFromResumen($resumen);
        $percepcion = (float) ($resumen['ivaPerci1'] ?? 0);
        $selloMh = DteJsonHelper::extractSelloRecibido($jsonData);

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
            $percepcion,
            $selloMh,
            $formaPago,
            $estado,
            $cuerpoDocumento,
            $matchResult,
            $codigoGeneracion,
            $numeroControl,
            $tipoDte,
            $observaciones,
            $documentoRow
        ) {
            $compra = Compra::withoutGlobalScopes()->create([
                'fecha' => $fecha,
                'fecha_pago' => $fecha,
                'estado' => $estado,
                'forma_pago' => $formaPago,
                'tipo_documento' => $tipoDocumento,
                'tipo_dte' => $tipoDte,
                'referencia' => $referencia,
                'id_proveedor' => $proveedor->id,
                'iva' => $iva,
                'sub_total' => $subTotal,
                'total' => $total,
                'percepcion' => $percepcion > 0 ? $percepcion : 0,
                'id_bodega' => $idBodega,
                'id_sucursal' => $idSucursal,
                'id_proyecto' => $document->id_proyecto,
                'tipo_costo_gasto' => $document->tipo_costo_gasto,
                'id_empresa' => $document->id_empresa,
                'id_usuario' => $idUsuario,
                'codigo_generacion' => $codigoGeneracion,
                'numero_control' => $numeroControl,
                'sello_mh' => $selloMh,
                'dte' => json_encode($jsonData),
                'cotizacion' => 0,
                'observaciones' => $observaciones,
            ]);

            foreach ($cuerpoDocumento as $index => $item) {
                $producto = $matchResult['resolved'][$index] ?? null;
                if (!$producto) {
                    continue;
                }

                $detalleData = $this->buildDetalleCompraFromItem($item, $producto);

                $detalle = DetalleCompra::withoutGlobalScopes()->create([
                    'id_compra' => $compra->id,
                    'id_producto' => $producto->id,
                    'cantidad' => $detalleData['cantidad'],
                    'costo' => $detalleData['costo'],
                    'subtotal' => $detalleData['subtotal'],
                    'total' => $detalleData['total'],
                    'iva' => $detalleData['iva'],
                    'no_sujeta' => $detalleData['no_sujeta'],
                    'exenta' => $detalleData['exenta'],
                    'descuento' => $detalleData['descuento'],
                ]);

                if ($actualizarInventario && $idBodega) {
                    $this->actualizarInventarioCompra($compra, $detalle, $producto);
                }
            }

            if ($documentoRow) {
                $documentoRow->increment('correlativo');
            }

            $document->update(['processing_status' => 'processed']);

            return ['success' => true, 'compra_id' => $compra->id];
        });
    }

    /**
     * Calcula línea de detalle como en importación JSON de compras.
     */
    protected function buildDetalleCompraFromItem(array $item, Producto $producto): array
    {
        $ventaGravada = (float) ($item['ventaGravada'] ?? 0);
        $ventaExenta = (float) ($item['ventaExenta'] ?? 0);
        $ventaNoSuj = (float) ($item['ventaNoSuj'] ?? 0);
        $totalCalculado = $ventaGravada + $ventaExenta + $ventaNoSuj;

        $cantidad = (float) ($item['cantidad'] ?? 0);
        $precioUni = (float) ($item['precioUni'] ?? 0);
        $descuento = (float) ($item['montoDescu'] ?? 0);
        $ventaTotal = (float) ($item['ventaTotal'] ?? 0);
        $totalFinal = $totalCalculado > 0 ? $totalCalculado : ($ventaTotal > 0 ? $ventaTotal : ($cantidad * $precioUni - $descuento));

        $ivaItem = $this->extractIvaFromItem($item);
        if ($ivaItem <= 0 && $ventaGravada > 0 && $totalFinal > $ventaGravada) {
            $ivaItem = $totalFinal - $ventaGravada - $ventaExenta - $ventaNoSuj;
        }

        return [
            'cantidad' => $cantidad,
            'costo' => $precioUni,
            'subtotal' => max(0, $totalFinal - $ivaItem),
            'total' => $totalFinal,
            'iva' => max(0, $ivaItem),
            'no_sujeta' => $ventaNoSuj,
            'exenta' => $ventaExenta,
            'descuento' => $descuento,
        ];
    }

    protected function actualizarInventarioCompra(Compra $compra, DetalleCompra $detalle, Producto $producto): void
    {
        if ($producto->tipo === 'Servicio' || !$compra->id_bodega) {
            return;
        }

        $producto = Producto::with('inventarios')->find($producto->id);
        if (!$producto) {
            return;
        }

        $stockAnterior = $producto->inventarios->sum('stock') ?? 0;
        $stockActual = (float) $detalle->cantidad;
        $stockTotal = $stockAnterior + $stockActual;

        if ($stockTotal > 0) {
            $costoPromedio = (($stockAnterior * $producto->costo) + ($stockActual * $detalle->costo)) / $stockTotal;
        } else {
            $costoPromedio = $detalle->costo;
        }

        $producto->costo_anterior = $producto->costo;
        $producto->costo = $detalle->costo;
        $producto->costo_promedio = $costoPromedio;
        $producto->save();

        $inventario = Inventario::firstOrCreate(
            [
                'id_producto' => $producto->id,
                'id_bodega' => $compra->id_bodega,
            ],
            ['stock' => 0, 'stock_minimo' => 0, 'stock_maximo' => 0]
        );
        $inventario->stock += $detalle->cantidad;
        $inventario->save();
        $inventario->kardex($compra, $detalle->cantidad);
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

        $total = round((float) ($resumen['totalPagar'] ?? $resumen['montoTotalOperacion'] ?? $document->total_amount ?? 0), 2);
        $subTotal = round((float) ($resumen['subTotal'] ?? $resumen['totalGravada'] ?? $total), 2);
        $iva = round($this->extractIvaFromResumen($resumen), 2);

        $formaPago = 'Efectivo';
        if (!empty($resumen['pagos'][0]['codigo'])) {
            $formaPago = self::$formaPagoMap[$resumen['pagos'][0]['codigo']] ?? 'Efectivo';
        }

        $estado = ($formaPago === 'Crédito') ? 'Pendiente' : 'Confirmado';
        $concepto = !empty($cuerpoDocumento)
            ? ($cuerpoDocumento[0]['descripcion'] ?? $document->issuer_name ?? 'DTE importado')
            : ($document->issuer_name ?? 'DTE importado');
        $tipo = $document->tipo_gasto ?: $this->determinarCategoriaGasto($cuerpoDocumento);
        $idCategoria = $document->id_categoria;
        $idProyecto = $document->id_proyecto;

        if (Gasto::withoutGlobalScopes()
            ->where('id_empresa', $document->id_empresa)
            ->where('codigo_generacion', $document->dte_uuid)
            ->exists()) {
            return ['success' => true, 'skipped' => 'duplicate'];
        }

        $selloMh = DteJsonHelper::extractSelloRecibido($jsonData);

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
            $cuerpoDocumento,
            $identificacion,
            $selloMh,
            $idCategoria,
            $idProyecto
        ) {
            $gasto = Gasto::withoutGlobalScopes()->create([
                'fecha' => $fecha,
                'referencia' => $referencia,
                'tipo_documento' => $tipoDocumento,
                'concepto' => $concepto,
                'tipo' => $tipo,
                'id_categoria' => $idCategoria,
                'estado' => $estado,
                'forma_pago' => $formaPago,
                'id_proveedor' => $proveedor->id,
                'sub_total' => $subTotal,
                'iva' => $iva,
                'total' => $total,
                'id_sucursal' => $idSucursal,
                'id_proyecto' => $idProyecto,
                'id_empresa' => $document->id_empresa,
                'id_usuario' => $idUsuario,
                'codigo_generacion' => $identificacion['codigoGeneracion'] ?? $document->dte_uuid,
                'numero_control' => $identificacion['numeroControl'] ?? $document->dte_number,
                'sello_mh' => $selloMh,
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
                    'id_categoria' => $idCategoria,
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
