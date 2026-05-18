<?php

namespace App\Console\Commands;

use App\Models\Admin\Canal;
use App\Models\Admin\Documento;
use App\Models\Admin\Empresa;
use App\Models\Inventario\Producto;
use App\Models\MH\MHCCF;
use App\Models\MH\MHFactura;
use App\Models\Suscripcion;
use App\Models\User;
use App\Models\Ventas\Clientes\Cliente;
use App\Models\Ventas\Detalle;
use App\Models\Ventas\Venta;
use App\Services\MhGovSvGatewayService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GenerarFacturasSuscripciones extends Command
{
    protected $signature = 'facturas:generar-suscripciones {empresa? : ID de la empresa para ejecutar una prueba específica}';

    protected $description = 'Emite DTE de suscripciones activas con pago por transferencia: planes mensuales el día 1 de cada mes; planes anuales en la fecha de renovación. Excluye n1co (otro flujo).';

    protected MhGovSvGatewayService $mhGateway;

    public function __construct(MhGovSvGatewayService $mhGateway)
    {
        parent::__construct();
        $this->mhGateway = $mhGateway;
    }

    public function handle()
    {
        $this->info('Iniciando la generación de facturas de suscripciones...');
        Log::channel('facturacion')->info('Iniciando la generación de facturas de suscripciones...');

        try {
            $empresaId = $this->argument('empresa');

            if ($empresaId !== null && $empresaId !== '') {
                $msgPrueba = "Modo de prueba/específico activado: Procesando únicamente las suscripciones de la empresa ID: {$empresaId}";
                $this->info($msgPrueba);
                Log::channel('facturacion')->info($msgPrueba);
            }

            // Solo clientes que pagan por transferencia; n1co tiene flujo aparte y no se factura aquí.
            $metodoTransferencia = config('constants.METODO_PAGO_TRANSFERENCIA');
            $query = Suscripcion::where('estado', 'activo')
                ->where('metodo_pago', $metodoTransferencia);

            if ($empresaId !== null && $empresaId !== '') {
                $query->where('empresa_id', $empresaId);
            }

            $hoy = Carbon::now();

            $suscripciones = $query->get();

            $permitirMensualFueraDiaUno = $empresaId !== null && $empresaId !== '';
            if (!$permitirMensualFueraDiaUno && $hoy->day !== 1) {
                $msgDia = 'Planes no anuales solo se emiten el día 1 del mes; hoy no aplica (use argumento empresa para prueba).';
                $this->info($msgDia);
                Log::channel('facturacion')->info($msgDia, ['fecha' => $hoy->toDateString()]);
            }

            $total = $suscripciones->count();
            $msg = "Se encontraron {$total} suscripciones activas con método de pago «{$metodoTransferencia}» para evaluar.";
            $this->info($msg);
            Log::channel('facturacion')->info($msg);

            foreach ($suscripciones as $index => $suscripcion) {
                $esAnual = mb_strtolower((string) $suscripcion->tipo_plan) === 'anual';
                if (!$esAnual && !$permitirMensualFueraDiaUno && $hoy->day !== 1) {
                    continue;
                }

                $progress = ($index + 1) . '/' . $total;
                $msg = "[{$progress}] Procesando suscripción ID: {$suscripcion->id} (Empresa: {$suscripcion->empresa_id})";
                $this->info($msg);
                Log::channel('facturacion')->info($msg);

                if ($esAnual) {
                    if ($suscripcion->fecha_proximo_pago) {
                        $fechaProximoPago = Carbon::parse($suscripcion->fecha_proximo_pago);

                        if ($hoy->year === $fechaProximoPago->year && $hoy->month === $fechaProximoPago->month) {
                            $this->emitirFactura($suscripcion);
                        }
                    }
                } else {
                    $this->emitirFactura($suscripcion);
                }
            }

            $this->info('Generación de facturas de suscripciones completada exitosamente.');
            Log::channel('facturacion')->info('Generación de facturas de suscripciones completada exitosamente.');

            return 0;
        } catch (\Exception $e) {
            $this->error('Error durante la generación de facturas: ' . $e->getMessage());
            Log::channel('facturacion')->error('Error durante la generación de facturas de suscripciones: ' . $e->getMessage());

            return 1;
        }
    }

    /**
     * Emite la factura DTE (armado, firma externa, recepción MH) para la suscripción dada.
     */
    private function emitirFactura(Suscripcion $suscripcion): void
    {
        $suscripcion->loadMissing(['empresa', 'plan']);

        $empresa = Empresa::find(2); // Empresa administradora emisora
        if (!$empresa instanceof Empresa) {
            $this->registrarFalloSuscripcion($suscripcion, 'armado', new \RuntimeException('Empresa emisora (Super Admin ID 2) no encontrada en el sistema.'));

            return;
        }

        $tipoDte = $suscripcion->tipo_factura !== null && $suscripcion->tipo_factura !== ''
            ? str_pad(trim((string) $suscripcion->tipo_factura), 2, '0', STR_PAD_LEFT)
            : null;

        if (!in_array($tipoDte, ['01', '03'], true)) {
            $this->registrarFalloSuscripcion(
                $suscripcion,
                'armado',
                new \RuntimeException("tipo_factura inválido o ausente (esperado 01 o 03): " . var_export($suscripcion->tipo_factura, true))
            );

            return;
        }

        if (empty($suscripcion->id_cliente)) {
            $this->registrarFalloSuscripcion($suscripcion, 'armado', new \RuntimeException('id_cliente no configurado en la suscripción.'));

            return;
        }

        $cliente = Cliente::withoutGlobalScopes()->find($suscripcion->id_cliente);
        if (!$cliente) {
            $this->registrarFalloSuscripcion($suscripcion, 'armado', new \RuntimeException('Cliente receptor no encontrado (id_cliente).'));

            return;
        }

        if (!$empresa->facturacion_electronica) {
            $this->registrarFalloSuscripcion($suscripcion, 'armado', new \RuntimeException('La empresa no tiene activada la facturación electrónica.'));

            return;
        }

        if (empty($empresa->mh_usuario) || empty($empresa->mh_contrasena)) {
            $this->registrarFalloSuscripcion($suscripcion, 'armado', new \RuntimeException('Faltan mh_usuario o mh_contrasena para la API de Hacienda.'));

            return;
        }

        if (empty($empresa->mh_pwd_certificado)) {
            $this->registrarFalloSuscripcion($suscripcion, 'firmado', new \RuntimeException('Falta mh_pwd_certificado (contraseña del certificado) en la empresa.'));

            return;
        }

        $venta = null;
        $dteJson = null;

        Log::channel('facturacion')->info("Paso 1: Armar JSON DTE para suscripción {$suscripcion->id}");
        try {
            [$venta, $dteJson] = $this->armarJsonDteSuscripcion($suscripcion, $empresa, $cliente, $tipoDte);
            Log::channel('facturacion')->info("Venta creada ID: {$venta->id} para suscripción {$suscripcion->id}");
        } catch (\Throwable $e) {
            $this->registrarFalloSuscripcion($suscripcion, 'armado', $e);

            return;
        }

        $documentoFirmado = null;
        Log::channel('facturacion')->info("Paso 2: Firmar JSON DTE externamente para venta {$venta->id}");
        try {
            $documentoFirmado = $this->firmarJsonDteExterno($dteJson, $empresa);
            Log::channel('facturacion')->info("JSON firmado exitosamente para venta {$venta->id}");
        } catch (\Throwable $e) {
            $this->registrarFalloSuscripcion($suscripcion, 'firmado', $e, $venta);

            return;
        }

        $respuestaHacienda = null;
        Log::channel('facturacion')->info("Paso 3: Enviar DTE a recepción Hacienda para venta {$venta->id}");
        try {
            $respuestaHacienda = $this->enviarDteRecepcionHacienda($venta, $dteJson, $documentoFirmado, $empresa);
            Log::channel('facturacion')->info("Respuesta recibida de Hacienda para venta {$venta->id}", ['respuesta' => $respuestaHacienda]);
        } catch (\Throwable $e) {
            $this->registrarFalloSuscripcion($suscripcion, 'envío MH', $e, $venta);

            return;
        }

        $estado = is_array($respuestaHacienda) ? ($respuestaHacienda['estado'] ?? null) : null;
        $sello = is_array($respuestaHacienda) ? ($respuestaHacienda['selloRecibido'] ?? null) : null;

        if ($estado !== 'PROCESADO' || empty($sello)) {
            $detalle = is_array($respuestaHacienda) ? json_encode($respuestaHacienda, JSON_UNESCAPED_UNICODE) : (string) $respuestaHacienda;
            $this->registrarFalloSuscripcion(
                $suscripcion,
                'envío MH',
                new \RuntimeException('Respuesta de Hacienda no indica PROCESADO o falta selloRecibido: ' . $detalle),
                $venta
            );

            return;
        }

        try {
            Log::channel('facturacion')->info("Paso 4: Persistir venta DTE emitido para venta {$venta->id}");
            $this->persistirVentaDteEmitido($venta, $dteJson, $documentoFirmado, $sello, $respuestaHacienda);
            Log::channel('facturacion')->info("Venta {$venta->id} persistida como Pagada con sello MH.");
        } catch (\Throwable $e) {
            $this->registrarFalloSuscripcion($suscripcion, 'persistencia', $e, $venta);

            return;
        }

        $msg = sprintf(
            'DTE suscripción emitido. suscripcion_id=%d venta_id=%d tipoDte=%s selloRecibido=%s',
            $suscripcion->id,
            $venta->id,
            $tipoDte,
            $sello
        );
        $this->info($msg);
        Log::channel('facturacion')->info($msg, [
            'suscripcion_id' => $suscripcion->id,
            'venta_id' => $venta->id,
            'empresa_id' => $empresa->id,
            'tipo_dte' => $tipoDte,
            'sello_mh' => $sello,
        ]);
    }

    /**
     * Paso 1: crea venta + detalle mínimo y genera el JSON DTE con MHFactura o MHCCF.
     *
     * @return array{0: Venta, 1: array}
     */
    private function armarJsonDteSuscripcion(
        Suscripcion $suscripcion,
        Empresa $empresa,
        Cliente $cliente,
        string $tipoDte
    ): array {
        return DB::transaction(function () use ($suscripcion, $empresa, $cliente, $tipoDte) {
            $sucursal = $empresa->sucursales()
                ->whereNotNull('cod_estable_mh')
                ->where('cod_estable_mh', '!=', '')
                ->orderBy('id')
                ->first();

            if (!$sucursal) {
                $sucursal = $empresa->sucursales()->orderBy('id')->first();
            }

            if (!$sucursal || empty($sucursal->cod_estable_mh)) {
                throw new \RuntimeException('No hay sucursal con cod_estable_mh configurado para la empresa.');
            }

            $nombreDocumento = $tipoDte === '03' ? 'Crédito fiscal' : 'Factura';
            $documento = Documento::withoutGlobalScopes()
                ->where('id_empresa', $empresa->id)
                ->where('id_sucursal', $sucursal->id)
                ->where('nombre', $nombreDocumento)
                ->lockForUpdate()
                ->first();

            if (!$documento) {
                throw new \RuntimeException("No existe documento «{$nombreDocumento}» para la sucursal ID {$sucursal->id}.");
            }

            $canal = Canal::withoutGlobalScopes()
                ->where('id_empresa', $empresa->id)
                ->orderBy('id')
                ->first();

            if (!$canal) {
                throw new \RuntimeException('No hay canal de ventas configurado para la empresa.');
            }

            $bodega = $empresa->bodegas()->orderBy('id')->first();
            if (!$bodega) {
                throw new \RuntimeException('No hay bodega configurada para la empresa.');
            }

            $idUsuario = $suscripcion->usuario_id
                ?: User::withoutGlobalScopes()->where('id_empresa', $empresa->id)->orderBy('id')->value('id');

            if (!$idUsuario) {
                throw new \RuntimeException('No se pudo determinar id_usuario para la venta (configure usuario_id en la suscripción o cree un usuario en la empresa).');
            }

            $producto = Producto::withoutGlobalScopes()
                ->where('id_empresa', $empresa->id)
                ->where('tipo', 'Servicio')
                ->where(function ($q) {
                    $q->whereNull('enable')->orWhere('enable', '!=', '0');
                })
                ->orderBy('id')
                ->first();

            if (!$producto) {
                throw new \RuntimeException('No existe ningún producto tipo «Servicio» en la empresa para armar el detalle del DTE.');
            }

            $total = (float) ($suscripcion->monto ?? 0);
            if ($total <= 0) {
                throw new \RuntimeException('El monto de la suscripción debe ser mayor a cero.');
            }

            $cobraIva = $empresa->cobra_iva === 'Si' || $empresa->cobra_iva === '1' || $empresa->cobra_iva === 1;
            if ($cobraIva) {
                $subTotal = round($total / 1.13, 4);
                $iva = round($total - $subTotal, 2);
            } else {
                $subTotal = round($total, 2);
                $iva = 0.0;
            }

            $fecha = Carbon::now()->toDateString();
            $planNombre = $suscripcion->plan ? $suscripcion->plan->nombre : 'Suscripción SmartPyme';
            $descripcionImpresion = 'Cuota de suscripción: ' . $planNombre . ' — ' . Carbon::now()->translatedFormat('F Y');

            $venta = new Venta;
            $venta->fill([
                'fecha' => $fecha,
                'fecha_pago' => $fecha,
                'estado' => 'Pendiente',
                'cotizacion' => 0,
                'id_canal' => $canal->id,
                'id_documento' => $documento->id,
                'id_cliente' => $cliente->id,
                'id_usuario' => $idUsuario,
                'id_vendedor' => $idUsuario,
                'id_bodega' => $bodega->id,
                'id_sucursal' => $sucursal->id,
                'id_empresa' => $empresa->id,
                'condicion' => 'Contado',
                'forma_pago' => 'Transferencia',
                'sub_total' => $subTotal,
                'iva' => $iva,
                'descuento' => 0,
                'no_sujeta' => 0,
                'exenta' => 0,
                'gravada' => 0,
                'cuenta_a_terceros' => 0,
                'iva_percibido' => 0,
                'iva_retenido' => 0,
                'renta_retenida' => 0,
                'total' => $total,
                'total_costo' => 0,
                'observaciones' => 'Facturación automática de suscripción #' . $suscripcion->id,
                'descripcion_personalizada' => 1,
                'descripcion_impresion' => $descripcionImpresion,
            ]);

            $venta->correlativo = $documento->correlativo;
            $documento->increment('correlativo');
            $venta->save();

            $detalle = new Detalle;
            $detalle->id_producto = $producto->id;
            $detalle->id_venta = $venta->id;
            $detalle->cantidad = 1;
            $detalle->precio = $subTotal;
            $detalle->total = $subTotal;
            $detalle->descuento = 0;
            $detalle->save();

            $venta->setRelation('cliente', $cliente);
            $venta->load(['detalles' => function ($q) {
                $q->with(['producto' => function ($pq) {
                    $pq->withoutGlobalScopes();
                }]);
            }]);

            if ($tipoDte === '03') {
                $mh = new MHCCF;
                $dteJson = $mh->generarDTE($venta);
            } else {
                $mh = new MHFactura;
                $dteJson = $mh->generarDTE($venta);
            }

            $venta->refresh();

            return [$venta, $dteJson];
        });
    }

    /**
     * Paso 2: firma electrónica vía servicio externo (mismo cuerpo que usa el frontend).
     *
     * @return array|string Decoded JSON firmado enviado como «documento» a Hacienda
     */
    private function firmarJsonDteExterno(array $dteJson, Empresa $empresa): array|string
    {
        $nit = str_replace('-', '', (string) $empresa->nit);
        if ($nit === '') {
            throw new \RuntimeException('NIT de empresa vacío para el firmador.');
        }

        $payload = [
            'nit' => $nit,
            'activo' => true,
            'passwordPri' => (string) $empresa->mh_pwd_certificado,
            'dteJson' => $dteJson,
        ];

        $urlFirmador = 'https://facturadtesv.com:8443/firmardocumento/';

        $response = Http::timeout((int) config('mh.timeout_seconds', 120))
            ->withOptions([
                'verify' => (bool) config('mh.verify_ssl', true),
                'http_errors' => false,
                'connect_timeout' => (int) config('mh.connect_timeout_seconds', 30),
            ])
            ->acceptJson()
            ->asJson()
            ->post($urlFirmador, $payload);

        if (!$response->successful()) {
            throw new \RuntimeException('Firmador HTTP ' . $response->status() . ': ' . $response->body());
        }

        $decoded = $response->json();
        if (!is_array($decoded)) {
            throw new \RuntimeException('Respuesta del firmador no es JSON válido: ' . $response->body());
        }

        if (($decoded['status'] ?? null) === 'ERROR') {
            $msg = $decoded['body']['mensaje'] ?? json_encode($decoded['body'] ?? $decoded, JSON_UNESCAPED_UNICODE);
            throw new \RuntimeException('Firmador devolvió ERROR: ' . $msg);
        }

        if (array_key_exists('body', $decoded) && $decoded['body'] !== null && $decoded['body'] !== '') {
            return $decoded['body'];
        }

        return $decoded;
    }

    /**
     * Paso 3: recepción DTE en Ministerio de Hacienda.
     *
     * @param array|string $documentoFirmado
     * @return array<string, mixed>
     */
    private function enviarDteRecepcionHacienda(Venta $venta, array $dteJson, $documentoFirmado, Empresa $empresa): array
    {
        $payload = [
            'ambiente' => $dteJson['identificacion']['ambiente'] ?? $empresa->fe_ambiente,
            'idEnvio' => $venta->id,
            'version' => $dteJson['identificacion']['version'] ?? ($venta->tipo_dte === '03' ? 3 : 1),
            'tipoDte' => $venta->tipo_dte,
            'documento' => $documentoFirmado,
            'codigoGeneracion' => $venta->codigo_generacion,
        ];

        try {
            $result = $this->mhGateway->postJson($empresa, '/fesv/recepciondte', $payload);
        } catch (ConnectionException $e) {
            throw new \RuntimeException('Sin conexión con Hacienda al enviar DTE: ' . $e->getMessage(), 0, $e);
        }

        $body = $result['body'] ?? null;
        if (!is_array($body)) {
            throw new \RuntimeException('Respuesta inválida de Hacienda (no JSON): ' . ($result['raw_body'] ?? ''));
        }

        return $body;
    }

    /**
     * @param array|string $documentoFirmado
     * @param array<string, mixed> $respuestaHacienda
     */
    private function persistirVentaDteEmitido(
        Venta $venta,
        array $dteJson,
        $documentoFirmado,
        string $sello,
        array $respuestaHacienda
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
        $venta->estado = 'Pagada';
        $venta->save();
    }

    private function registrarFalloSuscripcion(
        Suscripcion $suscripcion,
        string $paso,
        \Throwable $e,
        ?Venta $venta = null
    ): void {
        $mensaje = sprintf(
            '[Suscripción %d | paso: %s] %s',
            $suscripcion->id,
            $paso,
            $e->getMessage()
        );

        $contexto = [
            'suscripcion_id' => $suscripcion->id,
            'empresa_id' => $suscripcion->empresa_id,
            'paso' => $paso,
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ];

        if ($venta && $venta->id) {
            $contexto['venta_id'] = $venta->id;
        }

        $this->error($mensaje);
        Log::channel('facturacion')->error($mensaje, $contexto);

        if ($venta && $venta->id) {
            try {
                $nota = 'Error emisión DTE suscripción (' . $paso . '): ' . $e->getMessage();
                Venta::withoutGlobalScopes()->where('id', $venta->id)->update([
                    'observaciones' => mb_substr($nota, 0, 65000),
                ]);
            } catch (\Throwable $ignore) {
            }
        }
    }
}
