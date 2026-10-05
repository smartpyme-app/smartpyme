<?php

namespace App\Services\Ventas;

use App\Exceptions\FacturacionException;
use App\Models\Admin\Documento;
use App\Models\Admin\Empresa;
use App\Models\MH\MHCCF;
use App\Models\MH\MHFactura;
use App\Models\MH\MHFacturaExportacion;
use App\Models\User;
use App\Models\Ventas\Detalle;
use App\Models\Ventas\Venta;
use App\Services\FacturacionElectronica\FacturacionElectronicaCountryGate;
use App\Services\MhGovSvGatewayService;
use App\Services\Moneda\MonedaPaisService;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class GenerarVentasRecurrentesService
{
    public function __construct(
        private readonly FacturacionService $facturacion,
        private readonly MhGovSvGatewayService $mhGateway,
    ) {
    }

    public function ejecutar(Carbon $hoy): void
    {
        $fecha = $hoy->copy()->timezone('America/El_Salvador')->toDateString();

        $idsEmpresas = Venta::withoutGlobalScopes()
            ->whereNull('id_venta_plantilla')
            ->whereIn('frecuencia_recurrencia', ['mensual', 'anual'])
            ->where('recurrencia_pausada', false)
            ->where('estado', '!=', 'Anulada')
            ->distinct()
            ->pluck('id_empresa');

        foreach ($idsEmpresas as $idEmpresa) {
            $empresa = Empresa::find($idEmpresa);
            if (!$empresa || !VentasRecurrentesEmpresaConfig::activo($empresa)) {
                continue;
            }
            if (!VentasRecurrentesEmpresaConfig::generacionActiva($empresa)) {
                continue;
            }
            $this->procesarEmpresa($empresa, $fecha);
        }
    }

    private function procesarEmpresa(Empresa $empresa, string $fecha): void
    {

        $plantillas = Venta::withoutGlobalScopes()
            ->with(['detalles.composiciones', 'impuestos'])
            ->where('id_empresa', $empresa->id)
            ->whereNull('id_venta_plantilla')
            ->where('recurrencia_pausada', false)
            ->whereIn('frecuencia_recurrencia', ['mensual', 'anual'])
            ->where('estado', '!=', 'Anulada')
            ->get();

        $emitidas = [];
        $fallidas = [];

        foreach ($plantillas as $plantilla) {
            if (!$plantilla->fecha || !RecurrenciaCalendario::corresponde(
                $plantilla->frecuencia_recurrencia,
                (string) $plantilla->fecha,
                $fecha,
                $plantilla->dia_generacion_recurrencia !== null ? (int) $plantilla->dia_generacion_recurrencia : null,
            )) {
                continue;
            }

            $periodo = RecurrenciaCalendario::periodo($fecha);
            if ($this->yaGenerada($plantilla->id, $periodo)) {
                continue;
            }

            try {
                $resultado = $this->generar($plantilla, $empresa, $fecha, $periodo);
            } catch (\Throwable $e) {
                Log::channel('facturacion')->error('Ventas recurrentes: error inesperado en plantilla', [
                    'plantilla_id' => $plantilla->id,
                    'empresa_id' => $empresa->id,
                    'error' => $e->getMessage(),
                ]);
                $fallidas[] = 'Plantilla #'.$plantilla->correlativo.' (id '.$plantilla->id.'): '.$e->getMessage();

                continue;
            }

            if ($resultado['linea'] === '') {
                continue;
            }
            if ($resultado['ok']) {
                $emitidas[] = $resultado['linea'];
            } else {
                $fallidas[] = $resultado['linea'];
            }
        }

        if ($emitidas === [] && $fallidas === []) {
            return;
        }

        $this->enviarResumen($empresa, $fecha, $emitidas, $fallidas);
    }

    private function yaGenerada(int $idPlantilla, string $periodo): bool
    {
        return Venta::withoutGlobalScopes()
            ->where('id_venta_plantilla', $idPlantilla)
            ->where('periodo_recurrencia', $periodo)
            ->where('estado', '!=', 'Anulada')
            ->exists();
    }

    /** Libera el índice único (plantilla + periodo) si la única copia existente está anulada. */
    private function liberarPeriodoCopiaAnulada(int $idPlantilla, string $periodo): void
    {
        Venta::withoutGlobalScopes()
            ->where('id_venta_plantilla', $idPlantilla)
            ->where('periodo_recurrencia', $periodo)
            ->where('estado', 'Anulada')
            ->update(['periodo_recurrencia' => null]);
    }

    /**
     * @return array{ok: bool, linea: string}
     */
    private function generar(Venta $plantilla, Empresa $empresa, string $fecha, string $periodo): array
    {
        $etiqueta = 'Plantilla #'.$plantilla->correlativo.' (id '.$plantilla->id.')';

        $usuario = User::withoutGlobalScopes()
            ->where('id', $plantilla->id_usuario)
            ->where('id_empresa', $plantilla->id_empresa)
            ->first();
        if (!$usuario) {
            return ['ok' => false, 'linea' => $etiqueta.': no hay usuario de la misma empresa para facturar.'];
        }

        $documento = $this->resolverDocumentoPlantilla($plantilla);
        if (!$documento) {
            return [
                'ok' => false,
                'linea' => $etiqueta.': no hay documento fiscal válido (revise id_documento o el predeterminado de la sucursal).',
            ];
        }

        $this->liberarPeriodoCopiaAnulada($plantilla->id, $periodo);

        return $this->ejecutarComoUsuario($usuario, function () use ($plantilla, $empresa, $usuario, $fecha, $periodo, $documento, $etiqueta) {
                try {
                    [$venta, $requestFacturacion] = $this->clonarInterno($plantilla, $empresa, $usuario, $fecha, $periodo, $documento);
                    $this->marcarCopiaNoRecurrente($venta);
                    $this->postProcesoGiftCardsTrasClon($venta, $requestFacturacion);
                } catch (FacturacionException $e) {
                    if (str_contains($e->getMessage(), 'venta_recurrencia_periodo_unique')) {
                        if ($this->yaGenerada($plantilla->id, $periodo)) {
                            return ['ok' => true, 'linea' => ''];
                        }

                        return ['ok' => false, 'linea' => $etiqueta.': conflicto de periodo de recurrencia (venta activa duplicada).'];
                    }

                    $detalleDoc = 'documento '.$documento->id.' (empresa '.$plantilla->id_empresa.')';
                    $msg = $e->getMessage();
                    if (str_contains($msg, 'Documento')) {
                        $msg = $detalleDoc.'. '.$msg;
                    }

                    return ['ok' => false, 'linea' => $etiqueta.': no se pudo crear la venta. '.$msg];
                }

                if (!$empresa->facturacion_electronica) {
                    return [
                        'ok' => true,
                        'linea' => 'Venta #'.$venta->correlativo.' (id '.$venta->id.') generada (sin facturación electrónica).',
                    ];
                }

                if (FacturacionElectronicaCountryGate::ensureSvDteOrFail($empresa)) {
                    return [
                        'ok' => true,
                        'linea' => 'Venta #'.$venta->correlativo.' (id '.$venta->id.') generada; la emisión automática de DTE solo aplica en El Salvador.',
                    ];
                }

                try {
                    $this->emitir($venta, $empresa);
                    $venta->refresh();
                } catch (\Throwable $e) {
                    Log::channel('facturacion')->error('Ventas recurrentes: emisión fallida', [
                        'venta_id' => $venta->id,
                        'plantilla_id' => $plantilla->id,
                        'error' => $e->getMessage(),
                    ]);

                    return [
                        'ok' => false,
                        'linea' => 'Venta #'.$venta->correlativo.' (id '.$venta->id.') quedó pendiente de emitir a mano. '.$e->getMessage(),
                    ];
                }

                $avisoCorreoCliente = '';
                try {
                    $this->enviarFacturaAlCliente($venta);
                } catch (\Throwable $e) {
                    Log::channel('facturacion')->warning('Ventas recurrentes: correo DTE al cliente falló', [
                        'venta_id' => $venta->id,
                        'error' => $e->getMessage(),
                    ]);
                    $avisoCorreoCliente = ' El DTE no se envió al cliente: '.$e->getMessage();
                }

                return [
                    'ok' => true,
                    'linea' => 'Venta #'.$venta->correlativo.' (id '.$venta->id.') emitida.'.$avisoCorreoCliente,
                ];
        });
    }

    /**
     * @return array{0: Venta, 1: Request}
     */
    private function clonarInterno(
        Venta $plantilla,
        Empresa $empresa,
        User $usuario,
        string $fecha,
        string $periodo,
        Documento $documento,
    ): array {
        $payload = $this->payload($plantilla, $empresa, $fecha, $periodo, $documento);
        $payload['id_documento'] = (int) $documento->id;
        $payload['id_empresa'] = (int) $plantilla->id_empresa;
        $request = Request::create('/internal/ventas-recurrentes', 'POST', $payload);
        $request->setUserResolver(static fn () => $usuario);
        $request->attributes->set(FacturacionService::ATTR_VENTAS_RECURRENTES_CRON, true);

        $this->facturacion->assertReglasNegocio($usuario, $request);

        return [$this->facturacion->procesar($usuario, $request), $request];
    }

    /** Gift cards fuera de FacturacionService::procesar para evitar scopes Auth en consola; aquí Auth está impersonado. */
    private function postProcesoGiftCardsTrasClon(Venta $venta, Request $request): void
    {
        if ($venta->estado !== 'Pagada') {
            return;
        }

        try {
            $venta->loadMissing(['detalles.producto', 'metodos_de_pago']);
            app(\App\Services\GiftCards\GiftCardRedeemService::class)->redeemDesdeVenta($venta, $request);
        } catch (\Throwable $e) {
            Log::error('ventas-recurrentes: gift-cards redimir', [
                'venta_id' => $venta->id,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            app(\App\Services\GiftCards\GiftCardEmitService::class)->emitirDesdeVenta($venta);
        } catch (\Throwable $e) {
            Log::error('ventas-recurrentes: gift-cards emitir', [
                'venta_id' => $venta->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Impersona al usuario de la plantilla (setUser, no Login) en web y api — solo cron recurrentes / facturación interna.
     */
    private function ejecutarComoUsuario(User $usuario, callable $callback): mixed
    {
        $web = Auth::guard('web');
        $api = Auth::guard('api');
        $anteriorWeb = $web->user();
        $anteriorApi = $api->user();
        $web->setUser($usuario);
        $api->setUser($usuario);

        try {
            return $callback();
        } finally {
            if ($anteriorWeb) {
                $web->setUser($anteriorWeb);
            } else {
                $web->forgetUser();
            }
            if ($anteriorApi) {
                $api->setUser($anteriorApi);
            } else {
                $api->forgetUser();
            }
        }
    }

    private function resolverDocumentoPlantilla(Venta $plantilla): ?Documento
    {
        $empresaId = (int) $plantilla->id_empresa;

        if ($plantilla->id_documento) {
            $documento = Documento::withoutGlobalScopes()
                ->where('id_empresa', $empresaId)
                ->where('id', $plantilla->id_documento)
                ->first();
            if ($documento && $this->documentoAptoFacturacion($documento)) {
                return $documento;
            }
        }

        $query = Documento::withoutGlobalScopes()
            ->where('id_empresa', $empresaId)
            ->where('activo', '1');

        if ($plantilla->id_sucursal) {
            $porSucursal = (clone $query)
                ->where('id_sucursal', $plantilla->id_sucursal)
                ->orderByRaw("CASE WHEN predeterminado = '1' THEN 0 ELSE 1 END")
                ->orderBy('id')
                ->get()
                ->first(fn (Documento $doc) => $this->documentoAptoFacturacion($doc));
            if ($porSucursal) {
                return $porSucursal;
            }
        }

        return $query->orderBy('id')->get()
            ->first(fn (Documento $doc) => $this->documentoAptoFacturacion($doc));
    }

    private function documentoAptoFacturacion(Documento $documento): bool
    {
        return !in_array($documento->nombre, Venta::DOCUMENTOS_NO_CONTABLES, true);
    }

    private function payload(Venta $plantilla, Empresa $empresa, string $fecha, string $periodo, Documento $documento): array
    {
        $origen = Carbon::parse($plantilla->fecha)->startOfDay();
        $pago = $plantilla->fecha_pago ? Carbon::parse($plantilla->fecha_pago)->startOfDay() : $origen->copy();
        $dias = (int) $origen->diffInDays($pago, false);
        $fechaPago = Carbon::parse($fecha)->addDays(max(0, $dias))->toDateString();

        $data = [
            'fecha' => $fecha,
            'fecha_pago' => $fechaPago,
            'estado' => $plantilla->estado ?: 'Pagada',
            'cotizacion' => 0,
            'recurrente' => '0',
            'frecuencia_recurrencia' => null,
            'recurrencia_pausada' => false,
            'id_venta_plantilla' => $plantilla->id,
            'periodo_recurrencia' => $periodo,
            'observaciones' => $this->observacionesCopiaRecurrente($fecha, $plantilla->frecuencia_recurrencia),
            'puntos_ganados' => 0,
            'puntos_canjeados' => 0,
            'descuento_puntos' => 0,
            'id_canal' => $plantilla->id_canal,
            'id_documento' => $documento->id,
            'forma_pago' => $plantilla->forma_pago,
            'iva_percibido' => $plantilla->iva_percibido ?? 0,
            'iva_retenido' => $plantilla->iva_retenido ?? 0,
            'renta_retenida' => $plantilla->renta_retenida ?? 0,
            'iva' => $plantilla->iva ?? 0,
            'total_costo' => $plantilla->total_costo ?? 0,
            'descuento' => $plantilla->descuento ?? 0,
            'sub_total' => $plantilla->sub_total ?? 0,
            'no_sujeta' => $plantilla->no_sujeta ?? 0,
            'exenta' => $plantilla->exenta ?? 0,
            'gravada' => $plantilla->gravada ?? 0,
            'cuenta_a_terceros' => $plantilla->cuenta_a_terceros ?? 0,
            'total' => $plantilla->total ?? 0,
            'propina' => $plantilla->propina ?? 0,
            'id_bodega' => $plantilla->id_bodega,
            'id_cliente' => $plantilla->id_cliente,
            'id_usuario' => $plantilla->id_usuario,
            'id_vendedor' => $plantilla->id_vendedor,
            'id_empresa' => $plantilla->id_empresa,
            'id_sucursal' => $plantilla->id_sucursal,
            'descripcion_personalizada' => $plantilla->descripcion_personalizada,
            'descripcion_impresion' => $plantilla->descripcion_impresion,
            'num_identificacion' => $plantilla->num_identificacion,
            'detalles' => $plantilla->detalles->map(fn (Detalle $detalle) => $this->detallePayload($detalle))->values()->all(),
        ];

        $impuestos = $plantilla->impuestos->map(fn ($impuesto) => [
            'id_impuesto' => $impuesto->id_impuesto,
            'monto' => $impuesto->monto,
        ])->values()->all();

        if ($impuestos !== []) {
            $data['impuestos'] = $impuestos;
        }

        $detalles = $data['detalles'];
        $impuestosPayload = $data['impuestos'] ?? null;
        unset($data['detalles'], $data['impuestos']);

        $data = $this->filtrarAtributosVenta($data);
        $data['detalles'] = $detalles;
        if ($impuestosPayload !== null) {
            $data['impuestos'] = $impuestosPayload;
        }

        $data = array_merge($data, $this->monedaCamposClon($empresa, $plantilla, $fecha));

        return $data;
    }

    /** Misma lógica que facturación: moneda funcional o permitida para el país (evita HNL en empresa USD-only). */
    private function monedaCamposClon(Empresa $empresa, Venta $plantilla, string $fecha): array
    {
        /** @var MonedaPaisService $monedaService */
        $monedaService = app(MonedaPaisService::class);
        $cfg = $monedaService->configForEmpresa($empresa);
        $funcional = strtoupper((string) ($cfg['moneda_funcional'] ?? 'USD'));
        $monedas = array_map('strtoupper', $cfg['monedas_documento'] ?? [$funcional]);

        $currencyCode = strtoupper(trim((string) ($plantilla->currency_code ?: $funcional)));
        if (! $empresa->tieneFuncionalidadMultimoneda()) {
            $currencyCode = $funcional;
        } elseif (! in_array($currencyCode, $monedas, true)) {
            $currencyCode = $funcional;
        }

        $input = [
            'currency_code' => $currencyCode,
            'total' => (float) ($plantilla->total ?? 0),
            'iva' => (float) ($plantilla->iva ?? 0),
        ];
        if ($currencyCode !== $funcional && $plantilla->exchange_rate) {
            $input['exchange_rate'] = $plantilla->exchange_rate;
        }

        $allowManual = $currencyCode !== $funcional
            && $empresa->tieneFuncionalidadMultimoneda()
            && (bool) ($cfg['permitir_editar'] ?? false);

        return $monedaService->resolveDocumento(
            $empresa,
            $input,
            Carbon::parse($fecha),
            $allowManual
        );
    }

    /** Solo columnas que existen en `ventas` (el modelo fillable puede incluir campos legacy). */
    private function filtrarAtributosVenta(array $attrs): array
    {
        static $columnas = null;
        $columnas ??= array_flip(Schema::getColumnListing('ventas'));

        $filtrado = [];
        foreach ($attrs as $clave => $valor) {
            if (isset($columnas[$clave])) {
                $filtrado[$clave] = $valor;
            }
        }

        $filtrado['recurrente'] = '0';
        if (isset($columnas['frecuencia_recurrencia'])) {
            $filtrado['frecuencia_recurrencia'] = null;
        }
        if (isset($columnas['recurrencia_pausada'])) {
            $filtrado['recurrencia_pausada'] = false;
        }

        return $filtrado;
    }

    private function marcarCopiaNoRecurrente(Venta $venta): void
    {
        $dirty = false;
        if ($venta->recurrente !== '0' && $venta->recurrente !== 0 && $venta->recurrente !== false) {
            $venta->recurrente = '0';
            $dirty = true;
        }
        if ($venta->frecuencia_recurrencia !== null) {
            $venta->frecuencia_recurrencia = null;
            $dirty = true;
        }
        if ($venta->recurrencia_pausada) {
            $venta->recurrencia_pausada = false;
            $dirty = true;
        }
        if ($dirty) {
            $venta->save();
        }
    }

    private function detallePayload(Detalle $detalle): array
    {
        $data = [
            'id_producto' => $detalle->id_producto,
            'id_presentacion' => $detalle->id_presentacion,
            'lote_id' => $detalle->lote_id,
            'origen_stock' => $detalle->origen_stock,
            'descripcion' => $detalle->getAttributes()['descripcion'] ?? null,
            'cantidad' => $detalle->cantidad,
            'precio' => $detalle->precio,
            'precio_sin_iva' => $detalle->precio_sin_iva,
            'precio_con_iva' => $detalle->precio_con_iva,
            'costo' => $detalle->costo,
            'descuento' => $detalle->descuento ?? 0,
            'sub_total' => $detalle->sub_total,
            'tipo_gravado' => $detalle->tipo_gravado,
            'no_sujeta' => $detalle->no_sujeta,
            'exenta' => $detalle->exenta,
            'gravada' => $detalle->gravada,
            'cuenta_a_terceros' => $detalle->cuenta_a_terceros,
            'total_costo' => $detalle->total_costo,
            'total' => $detalle->total,
            'id_vendedor' => $detalle->id_vendedor,
            'iva' => $detalle->iva,
            'porcentaje_impuesto' => $detalle->porcentaje_impuesto,
        ];

        $composiciones = $detalle->composiciones->map(fn ($compuesto) => [
            'id_compuesto' => $compuesto->id_producto,
            'cantidad' => $compuesto->cantidad,
        ])->values()->all();

        if ($composiciones !== []) {
            $data['composiciones'] = $composiciones;
        }

        return $data;
    }

    private function emitir(Venta $venta, Empresa $empresa): void
    {
        $this->validarRequisitosEmisionFe($empresa, $venta);

        Log::channel('facturacion')->info('Ventas recurrentes: generar JSON DTE', ['venta_id' => $venta->id]);
        $dteJson = $this->generarJsonDte($venta);
        $venta->refresh();

        Log::channel('facturacion')->info('Ventas recurrentes: firmar DTE', ['venta_id' => $venta->id]);
        $firmado = $this->firmarJsonDte($dteJson, $empresa);

        Log::channel('facturacion')->info('Ventas recurrentes: recepción MH', ['venta_id' => $venta->id]);
        $hacienda = $this->enviarDteRecepcionHacienda($venta, $dteJson, $firmado, $empresa);

        $estado = $hacienda['estado'] ?? null;
        $sello = $hacienda['selloRecibido'] ?? null;
        if ($estado !== 'PROCESADO' || empty($sello)) {
            throw new \RuntimeException(
                'Respuesta de Hacienda no indica PROCESADO o falta selloRecibido: '
                .json_encode($hacienda, JSON_UNESCAPED_UNICODE)
            );
        }

        Log::channel('facturacion')->info('Ventas recurrentes: persistir DTE', ['venta_id' => $venta->id]);
        $this->persistirVentaDteEmitido($venta, $dteJson, $firmado, $sello, $hacienda);
    }

    /** Mismas validaciones que facturas:generar-suscripciones antes de firmar/enviar. */
    private function validarRequisitosEmisionFe(Empresa $empresa, Venta $venta): void
    {
        if (empty($empresa->mh_usuario) || empty($empresa->mh_contrasena)) {
            throw new \RuntimeException('Faltan mh_usuario o mh_contrasena para la API de Hacienda.');
        }
        if (empty($empresa->mh_pwd_certificado)) {
            throw new \RuntimeException('Falta mh_pwd_certificado (contraseña del certificado).');
        }
        $venta->loadMissing([
            'sucursal' => fn ($q) => $q->withoutGlobalScope('empresa'),
            'cliente' => fn ($q) => $q->withoutGlobalScope('empresa'),
        ]);
        if (empty($venta->sucursal?->cod_estable_mh)) {
            throw new \RuntimeException('Falta configurar cod_estable_mh en la sucursal de la venta.');
        }
        if (!$venta->id_cliente || !$venta->cliente) {
            throw new \RuntimeException('La venta no tiene cliente para emitir DTE.');
        }
    }

    private function observacionesCopiaRecurrente(string $fecha, ?string $frecuencia): string
    {
        $carbon = Carbon::parse($fecha)->locale('es');
        if ($frecuencia === 'anual') {
            return 'Venta generada para el año '.$carbon->year;
        }

        return 'Venta generada para el mes '.ucfirst($carbon->translatedFormat('F'));
    }

    /** Igual que suscripciones: MHFactura/MHCCF y refresh para codigo_generacion / tipo_dte en BD. */
    private function generarJsonDte(Venta $venta): array
    {
        $venta = Venta::withoutGlobalScopes()->findOrFail($venta->id);
        $venta->load([
            'detalles' => fn ($q) => $q->with(['producto' => fn ($pq) => $pq->withoutGlobalScopes()]),
            'cliente' => fn ($q) => $q->withoutGlobalScope('empresa'),
            'empresa',
            'sucursal' => fn ($q) => $q->withoutGlobalScope('empresa'),
            'documento' => fn ($q) => $q->withoutGlobalScope('empresa'),
            'impuestos' => fn ($q) => $q->with([
                'impuesto' => fn ($iq) => $iq->withoutGlobalScope('empresa'),
            ]),
        ]);

        $dteJson = match ($venta->nombre_documento) {
            'Crédito fiscal' => (new MHCCF)->generarDTE($venta),
            'Factura de exportación' => (new MHFacturaExportacion)->generarDTE($venta),
            'Factura' => (new MHFactura)->generarDTE($venta),
            default => throw new \RuntimeException(
                'El tipo de documento no puede emitirse automáticamente: '.($venta->nombre_documento ?: 'desconocido')
            ),
        };

        $venta->refresh();

        if (!is_array($dteJson)) {
            throw new \RuntimeException('El DTE generado no es válido.');
        }

        return $dteJson;
    }

    private function persistirVentaDteEmitido(
        Venta $venta,
        array $dteJson,
        mixed $documentoFirmado,
        string $sello,
        array $respuestaHacienda,
    ): void {
        $firma = $documentoFirmado;
        if (is_string($firma)) {
            $decoded = json_decode($firma, true);
            $firma = json_last_error() === JSON_ERROR_NONE ? $decoded : $firma;
        }

        $dteJson['firmaElectronica'] = $firma;
        $dteJson['sello'] = $sello;
        $dteJson['selloRecibido'] = $sello;

        if (!empty($respuestaHacienda['numeroControl'])) {
            $venta->numero_control = $respuestaHacienda['numeroControl'];
        }
        if (!empty($respuestaHacienda['codigoGeneracion'])) {
            $venta->codigo_generacion = $respuestaHacienda['codigoGeneracion'];
        }

        $venta->dte = $dteJson;
        $venta->sello_mh = $sello;
        $venta->tipo_dte = $dteJson['identificacion']['tipoDte'] ?? $venta->tipo_dte;

        $this->guardarVentaSoloColumnasReales($venta);
    }

    /** MHFactura/MHCCF asignan ambiente, cod_condicion, etc. solo para armar el JSON; no son columnas en prod. */
    private function guardarVentaSoloColumnasReales(Venta $venta): void
    {
        static $columnas = null;
        $columnas ??= array_flip(Schema::getColumnListing('ventas'));

        foreach (array_keys($venta->getAttributes()) as $clave) {
            if (!isset($columnas[$clave])) {
                $venta->offsetUnset($clave);
            }
        }

        $venta->save();
    }

    private function firmarJsonDte(array $dteJson, Empresa $empresa): array|string
    {
        $nit = str_replace('-', '', (string) $empresa->nit);
        if ($nit === '') {
            throw new \RuntimeException('NIT de empresa vacío para el firmador.');
        }

        $response = Http::timeout((int) config('mh.timeout_seconds', 120))
            ->withOptions([
                'verify' => (bool) config('mh.verify_ssl', true),
                'http_errors' => false,
                'connect_timeout' => (int) config('mh.connect_timeout_seconds', 30),
            ])
            ->acceptJson()
            ->asJson()
            ->post(config('app.mh_url_firmado', 'https://facturadtesv.com:8443/firmardocumento/'), [
                'nit' => $nit,
                'activo' => true,
                'passwordPri' => (string) $empresa->mh_pwd_certificado,
                'dteJson' => $dteJson,
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Firmador HTTP '.$response->status().': '.$response->body());
        }

        $decoded = $response->json();
        if (!is_array($decoded)) {
            throw new \RuntimeException('Respuesta del firmador no es JSON válido: '.$response->body());
        }
        if (($decoded['status'] ?? null) === 'ERROR') {
            $msg = $decoded['body']['mensaje'] ?? json_encode($decoded['body'] ?? $decoded, JSON_UNESCAPED_UNICODE);
            throw new \RuntimeException('Firmador devolvió ERROR: '.$msg);
        }
        if (array_key_exists('body', $decoded) && $decoded['body'] !== null && $decoded['body'] !== '') {
            return $decoded['body'];
        }

        return $decoded;
    }

    private function enviarDteRecepcionHacienda(Venta $venta, array $dteJson, mixed $documentoFirmado, Empresa $empresa): array
    {
        $ident = $dteJson['identificacion'] ?? [];
        $tipoDte = (string) ($ident['tipoDte'] ?? $venta->tipo_dte ?? '');
        if ($tipoDte === '') {
            throw new \RuntimeException('Falta tipoDte en el JSON del DTE (revisar nombre_documento y generación MH).');
        }
        $codigoGeneracion = (string) ($ident['codigoGeneracion'] ?? $venta->codigo_generacion ?? '');
        if ($codigoGeneracion === '') {
            throw new \RuntimeException('Falta codigoGeneracion en el JSON del DTE.');
        }

        $payload = [
            'ambiente' => $ident['ambiente'] ?? $empresa->fe_ambiente,
            'idEnvio' => $venta->id,
            'version' => $ident['version'] ?? ($tipoDte === '03' ? 3 : 1),
            'tipoDte' => $tipoDte,
            'documento' => $documentoFirmado,
            'codigoGeneracion' => $codigoGeneracion,
        ];

        try {
            $result = $this->mhGateway->postJson($empresa, '/fesv/recepciondte', $payload);
        } catch (ConnectionException $e) {
            throw new \RuntimeException('Sin conexión con Hacienda al enviar DTE: '.$e->getMessage(), 0, $e);
        }

        $body = $result['body'] ?? null;
        if (!is_array($body)) {
            throw new \RuntimeException('Respuesta inválida de Hacienda (no JSON): '.($result['raw_body'] ?? ''));
        }

        return $body;
    }

    /** Mismo criterio que facturas:generar-suscripciones (sin HTTP ni scopes Auth en consola). */
    private function enviarFacturaAlCliente(Venta $venta): void
    {
        $venta = Venta::withoutGlobalScopes()
            ->with(['cliente' => fn ($q) => $q->withoutGlobalScope('empresa')])
            ->findOrFail($venta->id);

        if (!$venta->cliente || trim((string) $venta->cliente->correo) === '') {
            throw new \RuntimeException('El cliente no tiene correo electrónico configurado.');
        }

        $dte = $venta->dte;
        if (!is_array($dte) || $dte === []) {
            throw new \RuntimeException('La venta no tiene DTE guardado.');
        }

        $tipoDte = (string) ($dte['identificacion']['tipoDte'] ?? $venta->tipo_dte ?? '');
        $vistaPdf = match ($tipoDte) {
            '01' => 'reportes.facturacion.DTE-Factura',
            '03' => 'reportes.facturacion.DTE-CCF',
            '11' => 'reportes.facturacion.DTE-Factura-Exportacion',
            default => throw new \RuntimeException('Tipo de DTE no soportado para correo: '.$tipoDte),
        };

        $venta->qr = 'https://admin.factura.gob.sv/consultaPublica?ambiente='
            .$dte['identificacion']['ambiente']
            .'&codGen='.$dte['identificacion']['codigoGeneracion']
            .'&fechaEmi='.$dte['identificacion']['fecEmi'];

        $pdfContent = app('dompdf.wrapper')
            ->loadView($vistaPdf, ['registro' => $venta, 'DTE' => $dte])
            ->output();

        $correo = trim((string) $venta->cliente->correo);
        $nombre = $dte['receptor']['nombre'] ?? $venta->cliente->nombre_completo ?? $venta->cliente->nombre;
        $fromAddress = config('mail.from.address') ?: 'noreply@smartpyme.sv';
        $fromName = $dte['emisor']['nombre'] ?? config('mail.from.name') ?: 'SmartPyme';

        Mail::send('mails.DTE', ['DTE' => $dte, 'nombre' => $nombre], function ($m) use ($pdfContent, $dte, $correo, $nombre, $fromAddress, $fromName) {
            $m->from($fromAddress, $fromName)
                ->to($correo, $nombre)
                ->attachData($pdfContent, $dte['identificacion']['codigoGeneracion'].'.pdf', [
                    'mime' => 'application/pdf',
                ])
                ->attachData(json_encode($dte), $dte['identificacion']['codigoGeneracion'].'.json', [
                    'mime' => 'application/json',
                ])
                ->subject('Documento Tributario Electrónico');
        });
    }

    private function enviarResumen(Empresa $empresa, string $fecha, array $emitidas, array $fallidas): void
    {
        $correo = VentasRecurrentesEmpresaConfig::correoResumen($empresa);
        if ($correo === '') {
            Log::warning('Ventas recurrentes: empresa sin correo de resumen', ['empresa_id' => $empresa->id]);

            return;
        }

        $fromAddress = config('mail.from.address') ?: 'noreply@smartpyme.sv';
        $fromName = config('mail.from.name') ?: 'SmartPyme';
        $fechaEtiqueta = Carbon::parse($fecha)->format('d/m/Y');
        $asunto = '[SmartPyme] Ventas recurrentes — '.$empresa->nombre.' — '.$fechaEtiqueta;

        try {
            Mail::send('mails.ventas-recurrentes-resumen', [
                'empresaNombre' => $empresa->nombre,
                'fechaEtiqueta' => $fechaEtiqueta,
                'emitidas' => $emitidas,
                'fallidas' => $fallidas,
                'generado' => Carbon::now('America/El_Salvador')->format('d/m/Y H:i:s'),
            ], function ($mensaje) use ($correo, $fromAddress, $fromName, $asunto) {
                $mensaje->from($fromAddress, $fromName)
                    ->to($correo)
                    ->subject($asunto);
            });
            Log::channel('facturacion')->info('Ventas recurrentes: resumen enviado', [
                'empresa_id' => $empresa->id,
                'correo' => $correo,
                'emitidas' => count($emitidas),
                'fallidas' => count($fallidas),
            ]);
        } catch (\Throwable $e) {
            Log::channel('facturacion')->error('Ventas recurrentes: no se envió el resumen', [
                'empresa_id' => $empresa->id,
                'correo' => $correo,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
