<?php

namespace App\Services\Ventas;

use App\Models\Admin\Documento;
use App\Models\Admin\Empresa;
use App\Models\User;
use App\Models\Ventas\DetalleVentaProgramada;
use App\Models\Ventas\Venta;
use App\Models\Ventas\VentaProgramada;
use App\Models\Ventas\VentaProgramadaEjecucion;
use App\Services\FacturacionElectronica\FacturacionElectronicaCountryGate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Cron de facturas programadas. Crea la venta con FacturacionService::procesar (igual que recurrentes)
 * y reutiliza la API pública de GenerarVentasRecurrentesService (emitir, enviarFacturaAlCliente, ejecutarComoUsuario).
 */
class EjecutarVentasProgramadasService
{
    public function __construct(
        private readonly FacturacionService $facturacion,
        private readonly GenerarVentasRecurrentesService $recurrentes,
    ) {
    }

    /** @return int filas procesadas */
    public function ejecutar(Carbon $now): int
    {
        $now = $now->copy()->timezone(config('app.timezone'));

        $ids = VentaProgramada::query()
            ->where('activo', true)
            ->where('proxima_ejecucion', '<=', $now)
            ->orderBy('proxima_ejecucion')
            ->pluck('id');

        foreach ($ids as $id) {
            $programada = VentaProgramada::with(['detalles'])->find($id);
            if (!$programada || !$programada->activo || $programada->proxima_ejecucion->greaterThan($now)) {
                continue;
            }
            try {
                $this->procesar($programada, $now);
            } catch (\Throwable $e) {
                Log::channel('facturacion')->error('Ventas programadas: error inesperado', [
                    'programada_id' => $id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $ids->count();
    }

    private function procesar(VentaProgramada $programada, Carbon $now): void
    {
        if ($this->maximoAlcanzado($programada)) {
            $programada->update(['activo' => false]);

            return;
        }

        $programadaPara = $programada->proxima_ejecucion->copy();
        $periodo = ProgramacionCalendario::periodo($programada->frecuencia, $programadaPara);

        // Claim atómico: avanza proxima_ejecucion ANTES de crear la venta. Si otro proceso ya la tomó (0 filas), se omite.
        // Tras el claim, cualquier fallo deja historial y no reprocesa el slot cada 15 min.
        if (!$this->reclamar($programada, $programadaPara, $now)) {
            return;
        }
        $programada->refresh();
        $estado = 'ok';
        $modo = 'borrador';
        $idVenta = null;
        $mensaje = null;

        if (VentaProgramadaEjecucion::where('id_venta_programada', $programada->id)
            ->where('periodo', $periodo)->where('estado', 'ok')->exists()) {
            $estado = 'omitida';
            $mensaje = 'Ya existe una ejecución ok para el periodo '.$periodo.'.';
        } else {
            try {
                [$venta, $modo, $mensaje] = $this->crearVenta($programada, $programadaPara, $periodo, $idVenta);
            } catch (\Throwable $e) {
                $estado = 'error';
                $mensaje = $e->getMessage();
                Log::channel('facturacion')->error('Ventas programadas: ejecución fallida', [
                    'programada_id' => $programada->id,
                    'error' => $mensaje,
                ]);
            }
        }

        VentaProgramadaEjecucion::create([
            'id_venta_programada' => $programada->id,
            'id_venta' => $idVenta,
            'periodo' => $periodo,
            'programada_para' => $programadaPara,
            'ejecutada_en' => $now,
            'estado' => $estado,
            'modo' => $estado === 'ok' ? $modo : null,
            'mensaje_error' => $mensaje,
        ]);

        $programada->ejecuciones_count += $estado === 'ok' ? 1 : 0;
        if ($estado === 'ok' && $this->maximoAlcanzado($programada)) {
            $programada->activo = false;
        }
        $programada->save();
    }

    /**
     * UPDATE condicional: avanza proxima_ejecucion (siempre, también si luego falla; sin backfill de periodos vencidos)
     * solo si sigue igual al valor leído. @return bool true si este proceso ganó el slot.
     */
    public function reclamar(VentaProgramada $programada, Carbon $programadaPara, Carbon $now): bool
    {
        $proxima = $programadaPara->copy();
        do {
            $proxima = ProgramacionCalendario::siguienteTrasEjecucion(
                $programada->frecuencia,
                (int) $programada->dia_ejecucion,
                (string) $programada->hora_ejecucion,
                $proxima
            );
        } while ($proxima->lessThanOrEqualTo($now));

        return VentaProgramada::query()
            ->where('id', $programada->id)
            ->where('activo', true)
            ->where('proxima_ejecucion', $programada->getRawOriginal('proxima_ejecucion'))
            ->update(['proxima_ejecucion' => $proxima]) === 1;
    }

    private function maximoAlcanzado(VentaProgramada $p): bool
    {
        return $p->max_ejecuciones !== null && $p->ejecuciones_count >= $p->max_ejecuciones;
    }

    /** @return array{0: Venta, 1: string, 2: ?string} venta, modo (emitida|borrador), aviso */
    private function crearVenta(VentaProgramada $p, Carbon $programadaPara, string $periodo, ?int &$idVenta): array
    {
        $empresa = Empresa::findOrFail($p->id_empresa);
        $usuario = User::withoutGlobalScopes()
            ->where('id', $p->id_usuario)->where('id_empresa', $p->id_empresa)->first();
        if (!$usuario) {
            throw new \RuntimeException('La factura programada no tiene un usuario válido de la empresa.');
        }
        $documento = Documento::withoutGlobalScopes()
            ->where('id', $p->id_documento)->where('id_empresa', $p->id_empresa)->first();
        if (!$documento || in_array($documento->nombre, Venta::DOCUMENTOS_NO_CONTABLES, true)) {
            throw new \RuntimeException('No hay documento fiscal válido en la factura programada.');
        }

        return $this->comoUsuario($usuario, function () use ($p, $empresa, $usuario, $documento, $programadaPara, $periodo, &$idVenta) {
            $request = Request::create('/internal/ventas-programadas', 'POST',
                $this->payload($p, $documento, $programadaPara, $periodo));
            $request->setUserResolver(static fn () => $usuario);
            $request->attributes->set(FacturacionService::ATTR_VENTAS_RECURRENTES_CRON, true);

            $this->facturacion->assertReglasNegocio($usuario, $request);
            $venta = $this->facturacion->procesar($usuario, $request);
            $idVenta = $venta->id; // queda en historial aunque falle la emisión

            if (!$p->emitir_automatico) {
                return [$venta, 'borrador', null];
            }
            if (!$empresa->facturacion_electronica) {
                return [$venta, 'borrador', 'Empresa sin facturación electrónica: venta creada sin emitir.'];
            }
            if (FacturacionElectronicaCountryGate::ensureSvDteOrFail($empresa)) {
                return [$venta, 'borrador', 'La emisión automática de DTE solo aplica en El Salvador: venta creada sin emitir.'];
            }

            // Si emitir falla, la excepción sube y queda estado=error (la venta queda creada como Pendiente).
            $this->recurrentes->emitir($venta, $empresa);
            $venta->refresh();

            $aviso = null;
            if ($p->enviar_email) { // correo al cliente SOLO si emitió y enviar_email
                try {
                    $this->recurrentes->enviarFacturaAlCliente($venta);
                } catch (\Throwable $e) {
                    $aviso = 'DTE emitido pero no se envió al cliente: '.$e->getMessage();
                }
            }

            return [$venta, 'emitida', $aviso];
        });
    }

    private function payload(VentaProgramada $p, Documento $documento, Carbon $programadaPara, string $periodo): array
    {
        $fecha = $programadaPara->toDateString();

        return [
            'fecha' => $fecha,
            'fecha_pago' => $fecha,
            'estado' => 'Pendiente', // ponytail: cobro posterior; no hay método de pago en la plantilla
            'cotizacion' => 0,
            'recurrente' => '0',
            'id_venta_programada' => $p->id,
            'observaciones' => trim(($p->observaciones ? $p->observaciones.' — ' : '').'Factura programada '.$periodo),
            'puntos_ganados' => 0,
            'puntos_canjeados' => 0,
            'descuento_puntos' => 0,
            'id_documento' => $documento->id,
            'forma_pago' => $p->forma_pago,
            'condicion' => $p->condicion,
            'iva' => $p->iva ?? 0,
            'descuento' => $p->descuento ?? 0,
            'sub_total' => $p->sub_total ?? 0,
            'no_sujeta' => $p->no_sujeta ?? 0,
            'exenta' => $p->exenta ?? 0,
            'gravada' => $p->gravada ?? 0,
            'total' => $p->total ?? 0,
            'id_bodega' => $p->id_bodega,
            'id_cliente' => $p->id_cliente,
            'id_usuario' => $p->id_usuario,
            'id_vendedor' => $p->id_vendedor,
            'id_empresa' => $p->id_empresa,
            'id_sucursal' => $p->id_sucursal,
            'detalles' => $p->detalles->map(fn (DetalleVentaProgramada $d) => [
                'id_producto' => $d->id_producto,
                'id_presentacion' => $d->id_presentacion,
                'descripcion' => $d->descripcion,
                'cantidad' => $d->cantidad,
                'precio' => $d->precio,
                'precio_sin_iva' => $d->precio_sin_iva,
                'precio_con_iva' => $d->precio_con_iva,
                'costo' => $d->costo,
                'descuento' => $d->descuento ?? 0,
                'sub_total' => $d->sub_total,
                'tipo_gravado' => $d->tipo_gravado,
                'no_sujeta' => $d->no_sujeta,
                'exenta' => $d->exenta,
                'gravada' => $d->gravada,
                'cuenta_a_terceros' => $d->cuenta_a_terceros,
                'total_costo' => $d->total_costo,
                'total' => $d->total,
                'id_vendedor' => $d->id_vendedor,
                'iva' => $d->iva,
                'porcentaje_impuesto' => $d->porcentaje_impuesto,
            ])->values()->all(),
        ];
    }

    private function comoUsuario(User $usuario, callable $callback): mixed
    {
        return $this->recurrentes->ejecutarComoUsuario($usuario, $callback);
    }
}
