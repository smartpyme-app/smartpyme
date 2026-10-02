<?php

namespace App\Services\Ventas;

use App\Exceptions\FacturacionException;
use App\Http\Requests\MH\EnviarDTERequest;
use App\Models\Admin\Documento;
use App\Models\Admin\Empresa;
use App\Models\User;
use App\Models\Ventas\Detalle;
use App\Models\Ventas\Venta;
use App\Services\FacturacionElectronica\ElSalvador\ElSalvadorDteService;
use App\Services\FacturacionElectronica\FacturacionElectronicaCountryGate;
use App\Services\MhGovSvGatewayService;
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
        private readonly ElSalvadorDteService $dte,
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
            if (!$plantilla->fecha || !RecurrenciaCalendario::corresponde($plantilla->frecuencia_recurrencia, (string) $plantilla->fecha, $fecha)) {
                continue;
            }

            $periodo = RecurrenciaCalendario::periodo($fecha);
            if ($this->yaGenerada($plantilla->id, $periodo)) {
                continue;
            }

            $resultado = $this->generar($plantilla, $empresa, $fecha, $periodo);
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

        try {
            $venta = $this->clonar($plantilla, $usuario, $fecha, $periodo, $documento);
            $this->marcarCopiaNoRecurrente($venta);
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
        } catch (\Throwable $e) {
            Log::error('Ventas recurrentes: emisión fallida', [
                'venta_id' => $venta->id,
                'plantilla_id' => $plantilla->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'linea' => 'Venta #'.$venta->correlativo.' (id '.$venta->id.') quedó pendiente de emitir a mano. '.$e->getMessage(),
            ];
        }

        $avisoCorreo = '';
        try {
            $this->enviarFacturaAlCliente($venta);
        } catch (\Throwable $e) {
            $avisoCorreo = ' El DTE se emitió, pero no se envió al cliente: '.$e->getMessage();
        }

        return ['ok' => true, 'linea' => 'Venta #'.$venta->correlativo.' (id '.$venta->id.') emitida.'.$avisoCorreo];
    }

    private function clonar(
        Venta $plantilla,
        User $usuario,
        string $fecha,
        string $periodo,
        Documento $documento,
    ): Venta {
        $guard = Auth::guard();
        $anterior = $guard->user();
        // ponytail: setUser evita Login/Logout y el listener que escribe ultimo_logout (columna ausente en algunos entornos)
        $guard->setUser($usuario);

        try {
            $payload = $this->payload($plantilla, $fecha, $periodo, $documento);
            $payload['id_documento'] = (int) $documento->id;
            $payload['id_empresa'] = (int) $plantilla->id_empresa;
            $request = Request::create('/internal/ventas-recurrentes', 'POST', $payload);
            $request->setUserResolver(static fn () => $usuario);

            $this->facturacion->assertReglasNegocio($usuario, $request);

            return $this->facturacion->procesar($usuario, $request);
        } finally {
            if ($anterior) {
                $guard->setUser($anterior);
            } else {
                $guard->forgetUser();
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

    private function payload(Venta $plantilla, string $fecha, string $periodo, Documento $documento): array
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
            'observaciones' => 'Generada automáticamente desde la venta #'.$plantilla->correlativo,
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
            'currency_code' => $plantilla->currency_code,
            'exchange_rate' => $plantilla->exchange_rate,
            'exchange_rate_date' => $plantilla->exchange_rate_date,
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

        return $data;
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
        $venta->load(['detalles.producto', 'cliente', 'empresa', 'sucursal', 'documento']);

        $respuesta = $this->dte->generarDTE($venta);
        if ($respuesta->getStatusCode() >= 400) {
            throw new \RuntimeException($this->mensaje($respuesta->getData(true)));
        }

        $dteJson = $respuesta->getData(true);
        if (!is_array($dteJson)) {
            throw new \RuntimeException('El DTE generado no es válido.');
        }

        $firmado = $this->firmar($dteJson, $empresa);
        $hacienda = $this->enviarHacienda($venta, $dteJson, $firmado, $empresa);
        $estado = $hacienda['estado'] ?? null;
        $sello = $hacienda['selloRecibido'] ?? null;

        if ($estado !== 'PROCESADO' || empty($sello)) {
            throw new \RuntimeException('Hacienda no procesó el DTE: '.json_encode($hacienda, JSON_UNESCAPED_UNICODE));
        }

        $dteJson['firmaElectronica'] = $firmado;
        $dteJson['sello'] = $sello;
        $dteJson['selloRecibido'] = $sello;
        $venta->dte = $dteJson;
        $venta->sello_mh = $sello;
        if (!empty($hacienda['numeroControl'])) {
            $venta->numero_control = $hacienda['numeroControl'];
        }
        if (!empty($hacienda['codigoGeneracion'])) {
            $venta->codigo_generacion = $hacienda['codigoGeneracion'];
        }
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

    private function firmar(array $dteJson, Empresa $empresa): mixed
    {
        $nit = str_replace('-', '', (string) $empresa->nit);
        if ($nit === '' || empty($empresa->mh_pwd_certificado)) {
            throw new \RuntimeException('Faltan el NIT o la contraseña del certificado.');
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
            throw new \RuntimeException('Respuesta del firmador no es JSON válido.');
        }
        if (($decoded['status'] ?? null) === 'ERROR') {
            $msg = $decoded['body']['mensaje'] ?? json_encode($decoded, JSON_UNESCAPED_UNICODE);
            throw new \RuntimeException('Firmador: '.$msg);
        }

        return $decoded['body'] ?? $decoded;
    }

    private function enviarHacienda(Venta $venta, array $dteJson, mixed $firmado, Empresa $empresa): array
    {
        $payload = [
            'ambiente' => $dteJson['identificacion']['ambiente'] ?? $empresa->fe_ambiente,
            'idEnvio' => $venta->id,
            'version' => $dteJson['identificacion']['version'] ?? 1,
            'tipoDte' => $venta->tipo_dte,
            'documento' => $firmado,
            'codigoGeneracion' => $venta->codigo_generacion,
        ];

        try {
            $result = $this->mhGateway->postJson($empresa, '/fesv/recepciondte', $payload);
        } catch (ConnectionException $e) {
            throw new \RuntimeException('Sin conexión con Hacienda: '.$e->getMessage(), 0, $e);
        }

        $body = $result['body'] ?? null;
        if (!is_array($body)) {
            throw new \RuntimeException('Respuesta inválida de Hacienda.');
        }

        return $body;
    }

    private function enviarFacturaAlCliente(Venta $venta): void
    {
        $request = EnviarDTERequest::create('/api/enviarDTE', 'POST', [
            'id' => $venta->id,
            'tipo_dte' => $venta->tipo_dte,
        ]);
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->validateResolved();

        $respuesta = $this->dte->enviarDTE($request);
        if ($respuesta->getStatusCode() >= 400) {
            throw new \RuntimeException($this->mensaje($respuesta->getData(true)));
        }
    }

    private function enviarResumen(Empresa $empresa, string $fecha, array $emitidas, array $fallidas): void
    {
        $correo = VentasRecurrentesEmpresaConfig::correoResumen($empresa);
        if ($correo === '') {
            Log::warning('Ventas recurrentes: empresa sin correo de resumen', ['empresa_id' => $empresa->id]);

            return;
        }

        $lineas = ["Resumen de ventas recurrentes del {$fecha}.", ''];
        if ($emitidas !== []) {
            $lineas[] = 'Emitidas:';
            foreach ($emitidas as $linea) {
                $lineas[] = '- '.$linea;
            }
            $lineas[] = '';
        }
        if ($fallidas !== []) {
            $lineas[] = 'Con error:';
            foreach ($fallidas as $linea) {
                $lineas[] = '- '.$linea;
            }
        }

        try {
            Mail::raw(implode("\n", $lineas), function ($mensaje) use ($correo) {
                $mensaje->from('noreply@smartpyme.sv', 'SmartPyme')
                    ->to($correo)
                    ->subject('Resumen de ventas recurrentes');
            });
        } catch (\Throwable $e) {
            Log::error('Ventas recurrentes: no se envió el resumen', [
                'empresa_id' => $empresa->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function mensaje(mixed $data): string
    {
        if (!is_array($data)) {
            return 'No se pudo completar la emisión.';
        }

        $error = $data['error'] ?? $data['descripcionMsg'] ?? null;
        if (is_string($error) && $error !== '') {
            return $error;
        }

        return json_encode($data, JSON_UNESCAPED_UNICODE) ?: 'No se pudo completar la emisión.';
    }
}
