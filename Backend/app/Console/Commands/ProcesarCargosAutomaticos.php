<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Suscripcion;
use App\Models\OrdenPago;
use App\Models\MetodoPago;
use App\Services\PaymentGateways\N1coGateway;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProcesarCargosAutomaticos extends Command
{
    protected $signature = 'suscripciones:procesar-cargos {--force : Forzar procesamiento sin importar la fecha}';
    protected $description = 'Procesa los cargos automáticos de las suscripciones activas';

    private $n1coGateway;
    private const MAX_INTENTOS = 3;

    public function __construct(N1coGateway $n1coGateway)
    {
        parent::__construct();
        $this->n1coGateway = $n1coGateway;
    }

    public function handle()
    {
        $this->info('Iniciando proceso de cargos automáticos...');
        Log::channel('n1co')->info('Iniciando proceso de cargos automáticos');

        try {
            $suscripciones = $this->obtenerSuscripcionesParaCobro();

            foreach ($suscripciones as $suscripcion) {
                $this->procesarCargo($suscripcion);
            }

            $this->info('Proceso de cargos automáticos completado');
            Log::channel('n1co')->info('Proceso de cargos automáticos completado');

        } catch (\Exception $e) {
            $this->error("Error en proceso de cargos: {$e->getMessage()}");
            Log::channel('n1co')->error("Error en proceso de cargos: {$e->getMessage()}");
        }
    }

    private function obtenerSuscripcionesParaCobro()
    {
        $query = Suscripcion::where('estado', config('constants.ESTADO_SUSCRIPCION_ACTIVO'))
            ->where('metodo_pago', config('constants.METODO_PAGO_N1CO'))
            ->where('intentos_cobro', '<', self::MAX_INTENTOS);

        if (!$this->option('force')) {
            $query->where('fecha_proximo_pago', '<=', now()->addDays(1));
        }

        return $query->get();
    }

    private function procesarCargo(Suscripcion $suscripcion)
    {
        $this->info("Procesando cargo para suscripción ID: {$suscripcion->id}");
        Log::channel('n1co')->info("Procesando cargo para suscripción ID: {$suscripcion->id}");

        try {
            // Verificar método de pago activo
            $metodoPago = MetodoPago::where('id_usuario', $suscripcion->usuario_id)
                ->where('esta_activo', true)
                ->where('es_predeterminado', true)
                ->first();

            if (!$metodoPago) {
                throw new \Exception('No se encontró método de pago activo');
            }

            // Crear orden de pago
            $ordenPago = $this->crearOrdenPago($suscripcion);

            // Preparar datos para el cargo
            $chargeData = $this->prepararDatosCargo($suscripcion, $metodoPago, $ordenPago);

            // Procesar el cargo
            $resultado = $this->n1coGateway->createCharge($chargeData);

            if ($resultado['success']) {
                $this->procesarCargoExitoso($suscripcion, $ordenPago, $resultado['data']);
            } else {
                $this->procesarCargoFallido($suscripcion, $ordenPago, $resultado['error']);
            }

        } catch (\Exception $e) {
            $this->manejarError($suscripcion, $e);
        }
    }

    private function crearOrdenPago(Suscripcion $suscripcion)
    {
        return OrdenPago::create([
            'id_orden' => 'ORD-' . time() . '-' . Str::random(8),
            'id_usuario' => $suscripcion->usuario_id,
            'id_plan' => $suscripcion->plan_id,
            'nombre_cliente' => $suscripcion->usuario->name,
            'email_cliente' => $suscripcion->usuario->email,
            'telefono_cliente' => $suscripcion->usuario->telefono,
            'plan' => $suscripcion->plan->nombre,
            'monto' => $suscripcion->monto,
            'estado' => config('constants.ESTADO_ORDEN_PAGO_PENDIENTE'),
        ]);
    }

    private function prepararDatosCargo(Suscripcion $suscripcion, MetodoPago $metodoPago, OrdenPago $ordenPago)
    {
        return [
            'customer' => [
                'name' => $suscripcion->usuario->name,
                'email' => $suscripcion->usuario->email,
                'phoneNumber' => $suscripcion->usuario->telefono
            ],
            'cardId' => $metodoPago->id_tarjeta,
            'order' => [
                'id' => $ordenPago->id_orden,
                'lineItems' => [
                    [
                        'product' => [
                            'name' => "Renovación {$suscripcion->plan->nombre}",
                            'price' => floatval($suscripcion->monto)
                        ],
                        'quantity' => 1
                    ]
                ],
                'description' => "Renovación automática plan {$suscripcion->plan->nombre}",
                'name' => "Renovación {$suscripcion->plan->nombre}"
            ],
            'billingInfo' => [
                'countryCode' => $metodoPago->codigo_pais,
                'stateCode' => $metodoPago->codigo_estado ?? 'SS',
                'zipCode' => $metodoPago->codigo_postal ?? '1101'
            ]
        ];
    }

    private function procesarCargoExitoso(Suscripcion $suscripcion, OrdenPago $ordenPago, array $data)
    {
        DB::transaction(function () use ($suscripcion, $ordenPago, $data) {
            // Actualizar orden de pago
            $ordenPago->update([
                'estado' => config('constants.ESTADO_ORDEN_PAGO_COMPLETADO'),
                'id_orden_n1co' => $data['order']['id'],
                'codigo_autorizacion' => $data['order']['authorizationCode'],
                'fecha_transaccion' => now()
            ]);

            // Actualizar suscripción
            $suscripcion->update([
                'fecha_ultimo_pago' => now(),
                'fecha_proximo_pago' => now()->addDays($suscripcion->plan->duracion_dias),
                'estado_ultimo_pago' => config('constants.ESTADO_ORDEN_PAGO_COMPLETADO'),
                'intentos_cobro' => 0
            ]);

            Log::channel('n1co')->info("Cargo exitoso para suscripción {$suscripcion->id}");
            
            // Notificar al usuario
            // $suscripcion->usuario->notify(new PagoExitosoNotification($ordenPago));
        });
    }

    private function procesarCargoFallido(Suscripcion $suscripcion, OrdenPago $ordenPago, $error)
    {
        DB::transaction(function () use ($suscripcion, $ordenPago, $error) {
            // Actualizar orden de pago
            $ordenPago->update([
                'estado' => config('constants.ESTADO_ORDEN_PAGO_FALLIDO'),
                'fecha_transaccion' => now()
            ]);

            // Incrementar contador de intentos
            $suscripcion->increment('intentos_cobro');
            $suscripcion->update([
                'estado_ultimo_pago' => config('constants.ESTADO_ORDEN_PAGO_FALLIDO'),
                'ultimo_intento_cobro' => now()
            ]);

            if ($suscripcion->intentos_cobro >= self::MAX_INTENTOS) {
                $suscripcion->update(['estado' => config('constants.ESTADO_SUSCRIPCION_PENDIENTE')]);
            }

            Log::channel('n1co')->error("Cargo fallido para suscripción {$suscripcion->id}: {$error}");
            
            // Notificar al usuario
            // $suscripcion->usuario->notify(new PagoFallidoNotification($ordenPago));
        });
    }

    private function manejarError(Suscripcion $suscripcion, \Exception $e)
    {
        Log::channel('n1co')->error("Error procesando cargo para suscripción {$suscripcion->id}: {$e->getMessage()}");
        
        $suscripcion->increment('intentos_cobro');
        $suscripcion->update([
            'ultimo_intento_cobro' => now(),
            'estado_ultimo_pago' => config('constants.ESTADO_ORDEN_PAGO_FALLIDO')
        ]);

        if ($suscripcion->intentos_cobro >= self::MAX_INTENTOS) {
            $suscripcion->update(['estado' => config('constants.ESTADO_SUSCRIPCION_PENDIENTE')]);
        }
    }
}