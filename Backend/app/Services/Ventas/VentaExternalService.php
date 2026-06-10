<?php

namespace App\Services\Ventas;

use App\Exceptions\FacturacionException;
use App\Http\Requests\Ventas\FacturacionRequest;
use App\Models\Inventario\Bodega;
use App\Models\Admin\Canal;
use App\Models\Admin\Documento;
use App\Models\Admin\Empresa;
use App\Models\Inventario\Producto;
use App\Models\User;
use App\Models\Ventas\Clientes\Cliente;
use App\Models\Ventas\Detalle;
use App\Models\Ventas\Venta;
use App\Services\Paquetes\PaqueteExternalImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class VentaExternalService
{
    private const ESTADOS_CREACION = ['Pagada', 'Pendiente', 'Cotizacion', 'Completada'];
    private const ESTADOS_ACTUALIZABLES = ['Pendiente', 'Cotizacion', 'Pre-venta'];

    public function __construct(
        private FacturacionService $facturacionService,
        private PaqueteExternalImportService $importHelpers
    ) {
    }

    /**
     * @return array{ok: bool, venta?: Venta, idempotent?: bool, error?: string, status?: int, details?: mixed}
     */
    public function create(int $empresaId, array $payload): array
    {
        $referencia = $this->referenciaExterna($payload);
        if ($referencia) {
            $existente = Venta::withoutGlobalScopes()
                ->where('id_empresa', $empresaId)
                ->where('referencia', $referencia)
                ->with(['detalles'])
                ->first();

            if ($existente) {
                return [
                    'ok' => true,
                    'venta' => $existente,
                    'idempotent' => true,
                ];
            }
        }

        return $this->process($empresaId, $payload, null);
    }

    /**
     * @return array{ok: bool, venta?: Venta, error?: string, status?: int, details?: mixed}
     */
    public function update(int $empresaId, int $ventaId, array $payload): array
    {
        $venta = Venta::withoutGlobalScopes()
            ->where('id_empresa', $empresaId)
            ->where('id', $ventaId)
            ->with('detalles')
            ->first();

        if (!$venta) {
            return ['ok' => false, 'error' => 'Venta no encontrada', 'status' => 404];
        }

        if ($venta->estado === 'Anulada') {
            return ['ok' => false, 'error' => 'No se puede actualizar una venta anulada', 'status' => 422];
        }

        if (!in_array($venta->estado, self::ESTADOS_ACTUALIZABLES, true)) {
            return [
                'ok' => false,
                'error' => 'Solo se pueden actualizar ventas en estado Pendiente, Cotizacion o Pre-venta',
                'status' => 422,
            ];
        }

        if ($this->ventaTieneDocumentoFiscal($venta)) {
            return [
                'ok' => false,
                'error' => 'No se puede actualizar una venta con documento fiscal emitido',
                'status' => 422,
            ];
        }

        return $this->process($empresaId, $payload, $venta);
    }

    /**
     * @return array{ok: bool, venta?: Venta, idempotent?: bool, error?: string, status?: int, details?: mixed}
     */
    private function process(int $empresaId, array $payload, ?Venta $ventaExistente): array
    {
        $userId = $this->importHelpers->resolveSystemUserIdForEmpresa($empresaId);
        if (!$userId) {
            return [
                'ok' => false,
                'error' => 'La empresa no tiene un usuario activo para registrar ventas',
                'status' => 422,
            ];
        }

        $user = User::find($userId);
        if (!$user) {
            return ['ok' => false, 'error' => 'Usuario del sistema no encontrado', 'status' => 422];
        }

        try {
            $facturacionPayload = $this->buildFacturacionPayload($empresaId, $user, $payload, $ventaExistente);
            FacturacionRequest::validatePayload($facturacionPayload);
        } catch (ValidationException $e) {
            return [
                'ok' => false,
                'error' => 'Solicitud inválida',
                'status' => 422,
                'details' => $e->errors(),
            ];
        }

        Auth::login($user);

        try {
            $request = Request::create('/api/venta/facturacion', 'POST', $facturacionPayload);
            $request->setUserResolver(fn () => $user);

            $this->facturacionService->assertReglasNegocio($user, $request);
            $venta = $this->facturacionService->procesar($user, $request);

            return ['ok' => true, 'venta' => $venta];
        } catch (FacturacionException $e) {
            Log::warning('API externa: error en facturación', [
                'empresa_id' => $empresaId,
                'venta_id' => $ventaExistente?->id,
                'status' => $e->httpStatus,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'error' => $e->getMessage(),
                'status' => $e->httpStatus,
                'details' => $e->details,
            ];
        } finally {
            Auth::logout();
        }
    }

    /**
     * @throws ValidationException
     */
    private function buildFacturacionPayload(int $empresaId, User $user, array $payload, ?Venta $ventaExistente): array
    {
        $empresa = Empresa::findOrFail($empresaId);
        $estadoRaw = $payload['estado'] ?? ($ventaExistente?->estado ?? 'Pagada');
        $cotizacion = (int) ($payload['cotizacion'] ?? ($ventaExistente?->cotizacion ?? 0));
        if ($estadoRaw === 'Cotizacion') {
            $cotizacion = 1;
        }
        $estado = $this->mapEstadoExterno((string) $estadoRaw, $cotizacion === 1);

        if ($ventaExistente === null && !in_array($estadoRaw, self::ESTADOS_CREACION, true)) {
            throw ValidationException::withMessages([
                'estado' => ['Estado no permitido para creación. Use Pagada, Pendiente o Cotizacion.'],
            ]);
        }

        $idSucursalInput = isset($payload['id_sucursal']) ? (int) $payload['id_sucursal'] : null;
        $nombreSucursal = $payload['sucursal'] ?? null;
        $resSuc = $this->importHelpers->resolveSucursalId(
            $empresaId,
            $idSucursalInput ?: null,
            $nombreSucursal
        );
        if (!$resSuc['ok']) {
            throw ValidationException::withMessages(['id_sucursal' => [$resSuc['error']]]);
        }
        $idSucursal = $resSuc['id'];

        $idBodega = (int) ($payload['id_bodega'] ?? ($ventaExistente?->id_bodega ?? 0));
        if ($idBodega <= 0) {
            throw ValidationException::withMessages(['id_bodega' => ['El campo id_bodega es obligatorio.']]);
        }
        $this->assertBodegaEmpresa($empresaId, $idBodega);

        $idDocumento = (int) ($payload['id_documento'] ?? ($ventaExistente?->id_documento ?? 0));
        if ($idDocumento <= 0) {
            throw ValidationException::withMessages(['id_documento' => ['El campo id_documento es obligatorio.']]);
        }
        $documento = $this->resolveDocumento($empresaId, $idDocumento, $idSucursal);

        $idCanal = (int) ($payload['id_canal'] ?? ($ventaExistente?->id_canal ?? 0));
        if ($cotizacion !== 1) {
            if ($idCanal <= 0) {
                throw ValidationException::withMessages(['id_canal' => ['El campo id_canal es obligatorio.']]);
            }
            $this->assertCanalEmpresa($empresaId, $idCanal);
        }

        $idCliente = isset($payload['id_cliente']) ? (int) $payload['id_cliente'] : ($ventaExistente?->id_cliente);
        if ($estado === 'Pendiente' && empty($idCliente)) {
            throw ValidationException::withMessages(['id_cliente' => ['El cliente es obligatorio para ventas pendientes.']]);
        }
        if ($idCliente) {
            $this->assertClienteEmpresa($empresaId, (int) $idCliente);
        }

        $detallesInput = $payload['detalles'] ?? null;
        if ($detallesInput === null) {
            if (!$ventaExistente) {
                throw ValidationException::withMessages(['detalles' => ['Debe incluir al menos un producto en detalles.']]);
            }
            $detallesInput = $ventaExistente->detalles->map(function (Detalle $d) {
                $precioConIva = (float) ($d->precio_con_iva ?? 0);
                if ($precioConIva <= 0 && (float) $d->cantidad > 0) {
                    $lineTotal = (float) $d->gravada + (float) $d->iva + (float) $d->exenta;
                    $precioConIva = round($lineTotal / (float) $d->cantidad, 4);
                }

                return [
                    'id' => $d->id,
                    'id_producto' => $d->id_producto,
                    'cantidad' => $d->cantidad,
                    'precio' => $precioConIva,
                    'descuento' => $d->descuento,
                    'id_presentacion' => $d->id_presentacion,
                    'porcentaje_impuesto' => $d->porcentaje_impuesto,
                ];
            })->all();
        }
        $detallesExistentes = $ventaExistente
            ? $ventaExistente->detalles->keyBy('id_producto')
            : collect();

        $detalles = [];
        foreach ($detallesInput as $index => $line) {
            $producto = $this->resolveProducto($empresaId, $line);
            $detalleId = null;
            if ($ventaExistente && isset($line['id'])) {
                $detalleId = (int) $line['id'];
            } elseif ($ventaExistente && $detallesExistentes->has($producto->id)) {
                $detalleId = (int) $detallesExistentes->get($producto->id)->id;
            }

            try {
                $detalles[] = $this->buildDetalleLinea($line, $producto, $empresa, $detalleId);
            } catch (\InvalidArgumentException $e) {
                throw ValidationException::withMessages(["detalles.{$index}" => [$e->getMessage()]]);
            }
        }

        $totales = $this->calcularTotalesVenta($detalles);

        $referencia = $this->referenciaExterna($payload);

        $data = [
            'fecha' => $payload['fecha'] ?? ($ventaExistente?->fecha ?? now()->toDateString()),
            'estado' => $estado,
            'correlativo' => $ventaExistente?->correlativo ?? $documento->correlativo,
            'id_documento' => $documento->id,
            'id_canal' => $idCanal ?: ($ventaExistente?->id_canal ?? 0),
            'id_cliente' => $idCliente,
            'detalles' => $detalles,
            'iva' => $totales['iva'],
            'total_costo' => $totales['total_costo'],
            'sub_total' => $totales['sub_total'],
            'descuento' => $totales['descuento'],
            'total' => $totales['total'],
            'gravada' => $totales['gravada'],
            'exenta' => $totales['exenta'],
            'no_sujeta' => $totales['no_sujeta'],
            'id_usuario' => $user->id,
            'id_bodega' => $idBodega,
            'id_sucursal' => $idSucursal,
            'id_empresa' => $empresaId,
            'cotizacion' => $cotizacion,
            'forma_pago' => $payload['forma_pago'] ?? ($ventaExistente?->forma_pago ?? 'Efectivo'),
            'observaciones' => $payload['observaciones'] ?? ($ventaExistente?->observaciones ?? null),
            'monto_pago' => $payload['monto_pago'] ?? ($estado === 'Pagada' ? $totales['total'] : null),
            'cambio' => $payload['cambio'] ?? 0,
            'referencia' => $referencia ?? ($ventaExistente?->referencia),
        ];

        if ($cotizacion === 1) {
            $data['fecha_expiracion'] = $payload['fecha_expiracion']
                ?? ($ventaExistente?->fecha_expiracion ?? now()->addDays(15)->toDateString());
        }

        if ($ventaExistente) {
            $data['id'] = $ventaExistente->id;
        }

        return $data;
    }

    private function referenciaExterna(array $payload): ?string
    {
        $ref = $payload['referencia_externa'] ?? $payload['referencia'] ?? null;
        if ($ref === null || $ref === '') {
            return null;
        }

        return mb_substr(trim((string) $ref), 0, 255);
    }

    private function mapEstadoExterno(string $estado, bool $esCotizacion = false): string
    {
        $estado = trim($estado);

        if ($estado === 'Completada') {
            return 'Pagada';
        }

        if ($estado === 'Cotizacion' || $esCotizacion) {
            return 'Pre-venta';
        }

        return $estado;
    }

    private function ventaTieneDocumentoFiscal(Venta $venta): bool
    {
        return !empty($venta->sello_mh)
            || !empty($venta->codigo_generacion)
            || !empty($venta->dte);
    }

    private function assertBodegaEmpresa(int $empresaId, int $idBodega): void
    {
        $exists = Bodega::withoutGlobalScopes()
            ->where('id_empresa', $empresaId)
            ->where('id', $idBodega)
            ->exists();

        if (!$exists) {
            throw ValidationException::withMessages([
                'id_bodega' => ['La bodega no existe o no pertenece a la empresa.'],
            ]);
        }
    }

    private function resolveDocumento(int $empresaId, int $idDocumento, int $idSucursal): Documento
    {
        $documento = Documento::withoutGlobalScopes()
            ->where('id_empresa', $empresaId)
            ->where('id', $idDocumento)
            ->first();

        if (!$documento) {
            throw ValidationException::withMessages([
                'id_documento' => ['El documento no existe o no pertenece a la empresa.'],
            ]);
        }

        if ((int) $documento->id_sucursal !== $idSucursal) {
            throw ValidationException::withMessages([
                'id_documento' => ['El documento no corresponde a la sucursal indicada.'],
            ]);
        }

        return $documento;
    }

    private function assertCanalEmpresa(int $empresaId, int $idCanal): void
    {
        $exists = Canal::withoutGlobalScopes()
            ->where('id_empresa', $empresaId)
            ->where('id', $idCanal)
            ->exists();

        if (!$exists) {
            throw ValidationException::withMessages([
                'id_canal' => ['El canal no existe o no pertenece a la empresa.'],
            ]);
        }
    }

    private function assertClienteEmpresa(int $empresaId, int $idCliente): void
    {
        $exists = Cliente::withoutGlobalScopes()
            ->where('id_empresa', $empresaId)
            ->where('id', $idCliente)
            ->exists();

        if (!$exists) {
            throw ValidationException::withMessages([
                'id_cliente' => ['El cliente no existe o no pertenece a la empresa.'],
            ]);
        }
    }

    private function resolveProducto(int $empresaId, array $line): Producto
    {
        if (!empty($line['id_producto'])) {
            $producto = Producto::withoutGlobalScopes()
                ->where('id_empresa', $empresaId)
                ->where('id', (int) $line['id_producto'])
                ->first();
        } elseif (!empty($line['codigo_producto'])) {
            $codigo = trim((string) $line['codigo_producto']);
            $producto = Producto::withoutGlobalScopes()
                ->where('id_empresa', $empresaId)
                ->where(function ($q) use ($codigo) {
                    $q->where('codigo', $codigo)->orWhere('barcode', $codigo);
                })
                ->first();
        } else {
            throw new \InvalidArgumentException('Cada línea debe incluir id_producto o codigo_producto.');
        }

        if (!$producto) {
            throw new \InvalidArgumentException('Producto no encontrado en el catálogo de la empresa.');
        }

        return $producto;
    }

    private function buildDetalleLinea(array $line, Producto $producto, Empresa $empresa, ?int $detalleId = null): array
    {
        $cantidad = (float) ($line['cantidad'] ?? 0);
        if ($cantidad <= 0) {
            throw new \InvalidArgumentException('La cantidad debe ser mayor a cero.');
        }

        $precioConIva = isset($line['precio']) ? (float) $line['precio'] : (float) $producto->precio;
        $descuento = round((float) ($line['descuento'] ?? 0), 2);
        $pct = (float) ($line['porcentaje_impuesto'] ?? $producto->porcentaje_impuesto ?? $empresa->iva ?? 13);

        $totalConIva = round(($precioConIva * $cantidad) - $descuento, 2);
        if ($totalConIva < 0) {
            throw new \InvalidArgumentException('El total de la línea no puede ser negativo.');
        }

        if ($pct > 0) {
            $subtotal = round($totalConIva / (1 + ($pct / 100)), 2);
            $iva = round($totalConIva - $subtotal, 2);
            $gravada = $subtotal;
            $exenta = 0;
        } else {
            $subtotal = $totalConIva;
            $iva = 0;
            $gravada = 0;
            $exenta = $totalConIva;
        }

        $costo = (float) ($producto->costo ?? 0);
        $precioSinIva = $cantidad > 0 ? round($subtotal / $cantidad, 4) : 0;

        $det = [
            'id_producto' => $producto->id,
            'cantidad' => $cantidad,
            'precio' => $precioSinIva,
            'precio_sin_iva' => $precioSinIva,
            'precio_con_iva' => $cantidad > 0 ? round($totalConIva / $cantidad, 4) : $precioConIva,
            'costo' => $costo,
            'total_costo' => round($costo * $cantidad, 2),
            'descuento' => $descuento,
            'subtotal' => $subtotal,
            'gravada' => $gravada,
            'exenta' => $exenta,
            'no_sujeta' => 0,
            'cuenta_a_terceros' => 0,
            'iva' => $iva,
            'total' => $subtotal,
            'porcentaje_impuesto' => $pct,
        ];

        if ($detalleId) {
            $det['id'] = $detalleId;
        }

        if (!empty($line['id_presentacion'])) {
            $det['id_presentacion'] = (int) $line['id_presentacion'];
        }

        return $det;
    }

    /**
     * @param  array<int, array<string, mixed>>  $detalles
     * @return array{sub_total: float, iva: float, total: float, total_costo: float, descuento: float, gravada: float, exenta: float, no_sujeta: float}
     */
    private function calcularTotalesVenta(array $detalles): array
    {
        $subTotal = 0.0;
        $iva = 0.0;
        $totalCosto = 0.0;
        $descuento = 0.0;
        $gravada = 0.0;
        $exenta = 0.0;

        foreach ($detalles as $det) {
            $subTotal += (float) $det['subtotal'];
            $iva += (float) $det['iva'];
            $totalCosto += (float) $det['total_costo'];
            $descuento += (float) $det['descuento'];
            $gravada += (float) $det['gravada'];
            $exenta += (float) $det['exenta'];
        }

        return [
            'sub_total' => round($subTotal, 2),
            'iva' => round($iva, 2),
            'total' => round($subTotal + $iva, 2),
            'total_costo' => round($totalCosto, 2),
            'descuento' => round($descuento, 2),
            'gravada' => round($gravada, 2),
            'exenta' => round($exenta, 2),
            'no_sujeta' => 0,
        ];
    }
}
