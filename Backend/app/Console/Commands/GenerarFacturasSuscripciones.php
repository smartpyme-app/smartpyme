<?php

namespace App\Console\Commands;

use App\Models\Admin\Canal;
use App\Models\Admin\Documento;
use App\Models\Admin\Empresa;
use App\Models\Inventario\Producto;
use App\Models\MH\MHCCF;
use App\Models\MH\MHFactura;
use App\Models\MH\MHFacturaExportacion;
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
use Illuminate\Support\Facades\Mail;
use App\Constants\FacturacionElectronica\FEConstants;

class GenerarFacturasSuscripciones extends Command
{
    protected $signature = 'facturas:generar-suscripciones {empresa? : ID de la empresa para ejecutar una prueba específica}';

    protected $description = 'Emite DTE de suscripciones activas con pago por transferencia: planes mensuales el día 3 de cada mes; planes anuales en la fecha de renovación. Excluye n1co (otro flujo).';

    protected MhGovSvGatewayService $mhGateway;

    public function __construct(MhGovSvGatewayService $mhGateway)
    {
        parent::__construct();
        $this->mhGateway = $mhGateway;
    }

public function handle()
{
    $inicio = microtime(true);
    
    $this->info('Iniciando la generación de facturas de suscripciones...');
    Log::channel('facturacion')->info('Iniciando la generación de facturas de suscripciones...');

    try {
        $empresaId = $this->argument('empresa');
        $esModoPrueba = $empresaId !== null && $empresaId !== '';

        if ($esModoPrueba) {
            $msgPrueba = "Modo de prueba activado: Procesando únicamente las suscripciones de la empresa ID: {$empresaId}";
            $this->info($msgPrueba);
            Log::channel('facturacion')->info($msgPrueba);
        }

        $metodoTransferencia = config('constants.METODO_PAGO_TRANSFERENCIA');
        $query = Suscripcion::where('estado', 'activo')
            ->where('metodo_pago', $metodoTransferencia);

        if ($esModoPrueba) {
            $query->where('empresa_id', $empresaId);
        }

        $hoy = Carbon::now();
        $esDiaUno = $hoy->day === 3;

        // ✅ Cargar relaciones para evitar N+1 queries
        $suscripciones = $query->with(['empresa', 'plan'])->get();

        if (!$esModoPrueba && !$esDiaUno) {
            $msgDia = 'Planes mensuales solo se emiten el día 3 del mes. Ejecutando solo planes anuales que venzan este mes.';
            $this->info($msgDia);
            Log::channel('facturacion')->info($msgDia, ['fecha' => $hoy->toDateString()]);
        }

        $total = $suscripciones->count();
        $msg = "Se encontraron {$total} suscripciones activas con método de pago «{$metodoTransferencia}» para evaluar.";
        $this->info($msg);
        Log::channel('facturacion')->info($msg);

        $procesadas = 0;
        $omitidas = 0;
        $errores = 0;
        $exitosas = [];
        $fallidas = [];

        foreach ($suscripciones as $index => $suscripcion) {
            $esAnual = mb_strtolower((string) $suscripcion->tipo_plan) === 'anual';
            
            if (!$esAnual) {
                if (!$esModoPrueba && !$esDiaUno) {
                    $omitidas++;
                    continue;
                }
            } else {
                if ($suscripcion->fecha_proximo_pago) {
                    $fechaProximoPago = Carbon::parse($suscripcion->fecha_proximo_pago);
                    
                    if (!($hoy->year === $fechaProximoPago->year && $hoy->month === $fechaProximoPago->month)) {
                        $omitidas++;
                        continue;
                    }
                } else {
                    $this->registrarFalloSuscripcion(
                        $suscripcion,
                        'validación',
                        new \RuntimeException('fecha_proximo_pago no configurada para plan anual.')
                    );
                    $errores++;
                    $fallidas[] = [
                        'suscripcion_id' => $suscripcion->id,
                        'empresa_id' => $suscripcion->empresa_id,
                        'empresa_nombre' => $suscripcion->empresa->nombre ?? 'N/A',
                        'tipo_plan' => $suscripcion->tipo_plan,
                        'error' => 'fecha_proximo_pago no configurada',
                    ];
                    continue;
                }
            }

            $progress = ($index + 1) . '/' . $total;
            $msg = "[{$progress}] Procesando suscripción ID: {$suscripcion->id} (Empresa: {$suscripcion->empresa_id})";
            $this->info($msg);
            Log::channel('facturacion')->info($msg);

            try {
                $venta = $this->emitirFactura($suscripcion);
                $procesadas++;
                $exitosas[] = [
                    'suscripcion_id' => $suscripcion->id,
                    'empresa_id' => $suscripcion->empresa_id,
                    'empresa_nombre' => $suscripcion->empresa->nombre ?? 'N/A',
                    'tipo_plan' => $suscripcion->tipo_plan,
                    'monto' => $suscripcion->monto,
                    'venta_id' => $venta?->id,
                ];
            } catch (\Throwable $e) {
                $this->registrarFalloSuscripcion($suscripcion, 'emisión', $e);
                $errores++;
                $fallidas[] = [
                    'suscripcion_id' => $suscripcion->id,
                    'empresa_id' => $suscripcion->empresa_id,
                    'empresa_nombre' => $suscripcion->empresa->nombre ?? 'N/A',
                    'tipo_plan' => $suscripcion->tipo_plan,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $tiempoTotal = round(microtime(true) - $inicio, 2);
        $resumen = "Generación completada en {$tiempoTotal}s. Procesadas: {$procesadas}, Omitidas: {$omitidas}, Errores: {$errores}";
        $this->info($resumen);
        Log::channel('facturacion')->info($resumen);

        if (count($exitosas) > 0) {
            Log::channel('facturacion')->info('=== EMPRESAS FACTURADAS EXITOSAMENTE ===', ['total' => count($exitosas)]);
            foreach ($exitosas as $ok) {
                Log::channel('facturacion')->info("Empresa: {$ok['empresa_nombre']} (ID: {$ok['empresa_id']}) | Suscripcion: {$ok['suscripcion_id']} | Venta: {$ok['venta_id']} | Plan: {$ok['tipo_plan']} | Monto: {$ok['monto']}");
            }
        }

        if (count($fallidas) > 0) {
            Log::channel('facturacion')->error('=== EMPRESAS CON ERROR EN FACTURACION ===', ['total' => count($fallidas)]);
            foreach ($fallidas as $fail) {
                Log::channel('facturacion')->error("Empresa: {$fail['empresa_nombre']} (ID: {$fail['empresa_id']}) | Suscripcion: {$fail['suscripcion_id']} | Plan: {$fail['tipo_plan']} | Error: {$fail['error']}");
            }
        }

        $this->enviarCorreoResumen($procesadas, $errores, $tiempoTotal, $exitosas, $fallidas);

        return $errores > 0 ? 1 : 0;
    } catch (\Exception $e) {
        $this->error('Error durante la generación de facturas: ' . $e->getMessage());
        Log::channel('facturacion')->error('Error durante la generación de facturas de suscripciones: ' . $e->getMessage(), [
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        return 1;
    }
}

    private function determinarTipoDte(Cliente $cliente, ?string $tipoFacturaConfigurado): string
    {
        if ($tipoFacturaConfigurado !== null && $tipoFacturaConfigurado !== '') {
            $tipo = str_pad(trim((string) $tipoFacturaConfigurado), 2, '0', STR_PAD_LEFT);
            if (in_array($tipo, ['01', '03', '11'], true)) {
                return $tipo;
            }
        }

        if (!empty($cliente->cod_pais) && strtoupper($cliente->cod_pais) !== 'SV') {
            return FEConstants::TIPO_DTE_FACTURAS_DE_EXPORTACION;
        }

        if (!empty($cliente->ncr)) {
            return FEConstants::TIPO_DTE_COMPROBANTE_DE_CREDITO_FISCAL;
        }

        if (!empty($cliente->nit) && $cliente->tipo_documento === '36') {
            return FEConstants::TIPO_DTE_COMPROBANTE_DE_CREDITO_FISCAL;
        }

        return FEConstants::TIPO_DTE_FACTURA_CONSUMIDOR_FINAL;
    }

    private function emitirFactura(Suscripcion $suscripcion): ?Venta
    {
        $suscripcion->loadMissing(['empresa', 'plan']);

        // Empresa emisora (Super Admin) - quien emite la factura
        $empresaEmisora = Empresa::find(2);
        if (!$empresaEmisora instanceof Empresa) {
            throw new \RuntimeException('Empresa emisora (Super Admin ID 2) no encontrada en el sistema.');
        }

        // Empresa cliente - quien recibe la factura
        $empresaCliente = $suscripcion->empresa;
        
        if (empty($empresaCliente->id_cliente)) {
            throw new \RuntimeException('id_cliente no configurado en la empresa cliente (ID: ' . $empresaCliente->id . ').');
        }

        $cliente = Cliente::withoutGlobalScopes()->find($empresaCliente->id_cliente);
        if (!$cliente) {
            throw new \RuntimeException('Cliente receptor no encontrado (id_cliente: ' . $empresaCliente->id_cliente . ').');
        }

        // Validaciones de la empresa emisora (Super Admin)
        if (!$empresaEmisora->facturacion_electronica) {
            throw new \RuntimeException('La empresa emisora no tiene activada la facturación electrónica.');
        }

        if (empty($empresaEmisora->mh_usuario) || empty($empresaEmisora->mh_contrasena)) {
            throw new \RuntimeException('Faltan mh_usuario o mh_contrasena para la API de Hacienda.');
        }

        if (empty($empresaEmisora->mh_pwd_certificado)) {
            throw new \RuntimeException('Falta mh_pwd_certificado (contraseña del certificado) en la empresa emisora.');
        }

        $tipoDte = $this->determinarTipoDte($cliente, $suscripcion->tipo_factura);

        $venta = null;
        $dteJson = null;

        Log::channel('facturacion')->info("Paso 1: Armar JSON DTE para suscripción {$suscripcion->id} (tipo: {$tipoDte})");
        [$venta, $dteJson] = $this->armarJsonDteSuscripcion($suscripcion, $empresaEmisora, $cliente, $tipoDte);
        Log::channel('facturacion')->info("Venta creada ID: {$venta->id} para suscripción {$suscripcion->id}");

        $documentoFirmado = null;
        Log::channel('facturacion')->info("Paso 2: Firmar JSON DTE externamente para venta {$venta->id}");
        $documentoFirmado = $this->firmarJsonDteExterno($dteJson, $empresaEmisora);
        Log::channel('facturacion')->info("JSON firmado exitosamente para venta {$venta->id}");

        $respuestaHacienda = null;
        Log::channel('facturacion')->info("Paso 3: Enviar DTE a recepción Hacienda para venta {$venta->id}");
        $respuestaHacienda = $this->enviarDteRecepcionHacienda($venta, $dteJson, $documentoFirmado, $empresaEmisora);
        Log::channel('facturacion')->info("Respuesta recibida de Hacienda para venta {$venta->id}", ['respuesta' => $respuestaHacienda]);

        $estado = is_array($respuestaHacienda) ? ($respuestaHacienda['estado'] ?? null) : null;
        $sello = is_array($respuestaHacienda) ? ($respuestaHacienda['selloRecibido'] ?? null) : null;

        if ($estado !== 'PROCESADO' || empty($sello)) {
            $detalle = is_array($respuestaHacienda) ? json_encode($respuestaHacienda, JSON_UNESCAPED_UNICODE) : (string) $respuestaHacienda;
            throw new \RuntimeException('Respuesta de Hacienda no indica PROCESADO o falta selloRecibido: ' . $detalle);
        }

        Log::channel('facturacion')->info("Paso 4: Persistir venta DTE emitido para venta {$venta->id}");
        $this->persistirVentaDteEmitido($venta, $dteJson, $documentoFirmado, $sello, $respuestaHacienda);
        Log::channel('facturacion')->info("Venta {$venta->id} persistida con sello MH.");

        // Paso 5: Enviar correo con el DTE
        Log::channel('facturacion')->info("Paso 5: Enviar correo con DTE para venta {$venta->id}");
        try {
            $this->enviarCorreoDTE($venta);
            Log::channel('facturacion')->info("Correo enviado exitosamente para venta {$venta->id}");
        } catch (\Throwable $e) {
            Log::channel('facturacion')->warning("No se pudo enviar el correo para venta {$venta->id}: " . $e->getMessage());
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
            'empresa_id' => $empresaEmisora->id,
            'tipo_dte' => $tipoDte,
            'sello_mh' => $sello,
        ]);

        return $venta;
    }

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

            $nombreDocumento = match ($tipoDte) {
                '03' => 'Crédito fiscal',
                '11' => 'Factura de exportación',
                default => 'Factura',
            };

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
                throw new \RuntimeException('No se pudo determinar id_usuario para la venta.');
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
                throw new \RuntimeException('No existe ningún producto tipo «Servicio» en la empresa.');
            }

            $total = (float) ($suscripcion->monto ?? 0);
            if ($total <= 0) {
                throw new \RuntimeException('El monto de la suscripción debe ser mayor a cero.');
            }

            $cobraIva = $tipoDte !== '11' && ($empresa->cobra_iva === 'Si' || $empresa->cobra_iva === '1' || $empresa->cobra_iva === 1);
            
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
            $documento->correlativo += 1;
            $documento->save();
            
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

            $dteJson = match ($tipoDte) {
                '03' => (new MHCCF)->generarDTE($venta),
                '11' => (new MHFacturaExportacion)->generarDTE($venta),
                default => (new MHFactura)->generarDTE($venta),
            };

            $venta->refresh();

            return [$venta, $dteJson];
        });
    }

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
        $venta->save();
    }

    /**
     * Envía el correo con el DTE al cliente
     */
    private function enviarCorreoDTE(Venta $venta): void
    {
        $venta->load('cliente');
        
        if (!$venta->cliente || empty($venta->cliente->correo)) {
            throw new \RuntimeException('El cliente no tiene correo electrónico configurado.');
        }

        $DTE = $venta->dte;
        if (!$DTE) {
            throw new \RuntimeException('La venta no tiene DTE generado.');
        }

        $tipoDte = $DTE['identificacion']['tipoDte'] ?? null;
        
        // Generar PDF según el tipo de DTE
        $vistaPdf = match ($tipoDte) {
            '01' => 'reportes.facturacion.DTE-Factura',
            '03' => 'reportes.facturacion.DTE-CCF',
            '11' => 'reportes.facturacion.DTE-Factura-Exportacion',
            default => throw new \RuntimeException("Tipo de DTE no soportado para envío de correo: {$tipoDte}"),
        };

        $pdf = app('dompdf.wrapper')->loadView($vistaPdf, compact('venta', 'DTE'));
        $pdfContent = $pdf->output();

        $correo = $venta->cliente->correo;
        $nombre = $DTE['receptor']['nombre'] ?? $venta->cliente->nombre_completo;

        Mail::send('mails.DTE', ['DTE' => $DTE, 'nombre' => $nombre], function ($m) use ($pdfContent, $DTE, $correo, $nombre) {
            $m->from('noreply@smartpyme.sv', $DTE['emisor']['nombre'])
                ->to($correo, $nombre)
                ->attachData($pdfContent, $DTE['identificacion']['codigoGeneracion'] . '.pdf', [
                    'mime' => 'application/pdf',
                ])
                ->attachData(json_encode($DTE), $DTE['identificacion']['codigoGeneracion'] . '.json', [
                    'mime' => 'application/json',
                ])
                ->subject('Documento Tributario Electrónico - Suscripción SmartPyme');
        });
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

    /**
     * Envía un correo con el resumen del proceso de facturación mensual al equipo.
     */
    private function enviarCorreoResumen(int $procesadas, int $errores, float $tiempoTotal, array $exitosas, array $fallidas): void
    {
        if (count($exitosas) === 0 && count($fallidas) === 0) {
            return;
        }

        $destinatarios = config('constants.CORREO_FACTURACION_MENSUAL', []);
        if (empty($destinatarios)) {
            $this->warn('No se envió el correo de resumen porque no hay destinatarios configurados en CORREO_FACTURACION_MENSUAL.');
            return;
        }

        $asunto = '[SmartPyme] Resumen de Facturación Mensual de Suscripciones - ' . Carbon::now()->format('d/m/Y');
        try {
            Mail::send('mails.reporte-facturacion-mensual', [
                'procesadas' => $procesadas,
                'errores' => $errores,
                'tiempoTotal' => $tiempoTotal,
                'exitosas' => $exitosas,
                'fallidas' => $fallidas,
                'generado' => Carbon::now()->format('d/m/Y H:i:s'),
            ], function ($message) use ($destinatarios, $asunto) {
                $message->to($destinatarios)->subject($asunto);
            });
            
            $this->info('Correo de resumen enviado exitosamente.');
            Log::channel('facturacion')->info('Correo de resumen enviado exitosamente a: ' . implode(', ', $destinatarios));
        } catch (\Throwable $e) {
            $this->error('Error al enviar el correo de resumen: ' . $e->getMessage());
            Log::channel('facturacion')->error('Error al enviar el correo de resumen', [
                'error' => $e->getMessage()
            ]);
        }
    }
}