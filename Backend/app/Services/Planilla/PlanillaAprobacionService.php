<?php

namespace App\Services\Planilla;

use App\Constants\PlanillaConstants;
use App\Models\Planilla\Planilla;
use App\Models\Planilla\PlanillaDetalle;
use App\Models\Compras\Gastos\Categoria;
use App\Models\Compras\Gastos\Gasto;
use App\Models\Compras\Proveedores\Proveedor;
use App\Mail\BoletaPagoMailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PlanillaAprobacionService
{
    /**
     * Aprobar una planilla
     */
    public function aprobar($id)
    {
        DB::beginTransaction();
        try {
            $planilla = Planilla::with('detalles')->findOrFail($id);

            if ($planilla->estado != PlanillaConstants::PLANILLA_BORRADOR) {
                throw new \Exception('Solo se pueden aprobar planillas en estado borrador');
            }

            // Actualizar el estado de la planilla principal
            $planilla->estado = PlanillaConstants::PLANILLA_APROBADA;
            $planilla->save();

            // Actualizar el estado de todos los detalles activos
            $detallesActualizados = 0;
            foreach ($planilla->detalles as $detalle) {
                if ($detalle->estado == PlanillaConstants::PLANILLA_BORRADOR ||
                    $detalle->estado == PlanillaConstants::PLANILLA_ACTIVA) {
                    $detalle->estado = PlanillaConstants::PLANILLA_APROBADA;
                    $detalle->save();
                    $detallesActualizados++;
                }
            }

            DB::commit();

            return [
                'detalles_actualizados' => $detallesActualizados
            ];
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error al aprobar la planilla', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Revertir una planilla aprobada
     */
    public function revertir($id)
    {
        DB::beginTransaction();
        try {
            $planilla = Planilla::with('detalles')->findOrFail($id);

            if ($planilla->estado != PlanillaConstants::PLANILLA_APROBADA) {
                throw new \Exception('Solo se pueden revertir planillas en estado aprobado');
            }

            // Actualizar el estado de la planilla principal
            $planilla->estado = PlanillaConstants::PLANILLA_BORRADOR;
            $planilla->save();

            // Actualizar el estado de todos los detalles aprobados
            $detallesActualizados = 0;
            foreach ($planilla->detalles as $detalle) {
                if ($detalle->estado == PlanillaConstants::PLANILLA_APROBADA) {
                    $detalle->estado = PlanillaConstants::PLANILLA_BORRADOR;
                    $detalle->save();
                    $detallesActualizados++;
                }
            }

            DB::commit();

            return [
                'detalles_actualizados' => $detallesActualizados
            ];
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error al revertir la planilla', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Procesar pago de una planilla
     */
    public function procesarPago($id)
    {
        DB::beginTransaction();
        try {
            $planilla = Planilla::with(['detalles.empleado', 'empresa'])->findOrFail($id);

            if ($planilla->estado != PlanillaConstants::PLANILLA_APROBADA) {
                throw new \Exception('Solo se pueden pagar planillas aprobadas');
            }

            // Verificar configuración de correo
            $this->verificarConfiguracionCorreo();

            // Registrar gastos en contabilidad
            $this->registrarGastosPlanilla($planilla);

            // Actualizar estado de la planilla y sus detalles
            $planilla->estado = PlanillaConstants::PLANILLA_PAGADA;
            $planilla->save();

            PlanillaDetalle::where('id_planilla', $planilla->id)
                ->whereIn('estado', [PlanillaConstants::PLANILLA_BORRADOR, PlanillaConstants::PLANILLA_APROBADA])
                ->update(['estado' => PlanillaConstants::PLANILLA_PAGADA]);

            // Enviar correos
            $planilla = $planilla->fresh(['detalles.empleado', 'empresa']);
            $resultadoEmails = $this->enviarBoletasPorCorreo($planilla);

            DB::commit();

            return [
                'emails_enviados' => $resultadoEmails['emails_enviados'],
                'detalles_procesados' => $resultadoEmails['detalles_procesados'],
                'empleados_sin_email' => $resultadoEmails['empleados_sin_email'],
                'empleados_inactivos' => $resultadoEmails['empleados_inactivos'],
                'errores' => $resultadoEmails['errores'],
                'estadisticas' => [
                    'total_detalles' => $planilla->detalles->count(),
                    'detalles_procesados' => $resultadoEmails['detalles_procesados'],
                    'emails_enviados' => $resultadoEmails['emails_enviados'],
                    'empleados_sin_email' => $resultadoEmails['empleados_sin_email'],
                    'empleados_inactivos' => $resultadoEmails['empleados_inactivos']
                ]
            ];
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error al procesar pago de planilla', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Registrar gastos de planilla en contabilidad
     */
    private function registrarGastosPlanilla(Planilla $planilla)
    {
        try {
            // Obtener o crear la categoría de gastos de planilla
            $categoria = Categoria::firstOrCreate(
                [
                    'nombre' => 'Gastos de Planilla',
                    'id_empresa' => $planilla->id_empresa
                ]
            );

            // Obtener o crear el proveedor para planillas
            $proveedor = Proveedor::firstOrCreate(
                [
                    'tipo' => 'Empresa',
                    'nombre_empresa' => 'Planillas - Empleados',
                    'id_empresa' => $planilla->id_empresa,
                    'id_usuario' => auth()->user()->id
                ],
                [
                    'tipo_contribuyente' => 'Otros',
                    'estado' => 'Activo',
                    'id_sucursal' => $planilla->id_sucursal
                ]
            );

            $fecha_pago = now();
            $totalISSS_Patronal = 0;
            $totalAFP_Patronal = 0;

            foreach ($planilla->detalles as $detalle) {
                if ($detalle->estado == 1 || $detalle->estado == 2 || $detalle->estado == 4) {
                    if (!isset($detalle->empleado)) {
                        Log::warning('Detalle sin empleado asociado', ['detalle_id' => $detalle->id]);
                        continue;
                    }

                    $nombreEmpleado = $detalle->empleado->nombres . ' ' . $detalle->empleado->apellidos;
                    $sueldoNeto = round(floatval($detalle->sueldo_neto ?? 0), 2);

                    $isssPatronal = round(floatval($detalle->isss_patronal ?? 0), 2);
                    $afpPatronal = round(floatval($detalle->afp_patronal ?? 0), 2);

                    $totalISSS_Patronal += $isssPatronal;
                    $totalAFP_Patronal += $afpPatronal;

                    if ($sueldoNeto > 0) {
                        Gasto::create([
                            'fecha' => $fecha_pago,
                            'fecha_pago' => $fecha_pago,
                            'tipo_documento' => 'Planilla',
                            'referencia' => $planilla->codigo,
                            'concepto' => "Salario neto - {$nombreEmpleado}",
                            'tipo' => 'Sueldos y Salarios',
                            'estado' => 'Pagado',
                            'forma_pago' => 'Transferencia',
                            'total' => $sueldoNeto,
                            'id_proveedor' => $proveedor->id,
                            'id_categoria' => $categoria->id,
                            'id_usuario' => auth()->id(),
                            'id_empresa' => $planilla->id_empresa,
                            'id_sucursal' => $planilla->id_sucursal,
                            'nota' => "Pago de salario neto a {$nombreEmpleado} - Planilla {$planilla->codigo} - Período {$planilla->fecha_inicio} al {$planilla->fecha_fin}"
                        ]);
                    }
                }
            }

            // Crear gasto para ISSS patronal
            if ($totalISSS_Patronal > 0) {
                Gasto::create([
                    'fecha' => $fecha_pago,
                    'fecha_pago' => $fecha_pago,
                    'tipo_documento' => 'Planilla',
                    'referencia' => $planilla->codigo,
                    'concepto' => "Aporte patronal ISSS - Planilla {$planilla->codigo}",
                    'tipo' => 'ISSS Patronal',
                    'estado' => 'Pagado',
                    'forma_pago' => 'Transferencia',
                    'total' => $totalISSS_Patronal,
                    'id_proveedor' => $proveedor->id,
                    'id_categoria' => $categoria->id,
                    'id_usuario' => auth()->id(),
                    'id_empresa' => $planilla->id_empresa,
                    'id_sucursal' => $planilla->id_sucursal,
                    'nota' => "Aporte patronal total ISSS - Planilla {$planilla->codigo} - Período {$planilla->fecha_inicio} al {$planilla->fecha_fin}"
                ]);
            }

            // Crear gasto para AFP patronal
            if ($totalAFP_Patronal > 0) {
                Gasto::create([
                    'fecha' => $fecha_pago,
                    'fecha_pago' => $fecha_pago,
                    'tipo_documento' => 'Planilla',
                    'referencia' => $planilla->codigo,
                    'concepto' => "Aporte patronal AFP - Planilla {$planilla->codigo}",
                    'tipo' => 'AFP Patronal',
                    'estado' => 'Pagado',
                    'forma_pago' => 'Transferencia',
                    'total' => $totalAFP_Patronal,
                    'id_proveedor' => $proveedor->id,
                    'id_categoria' => $categoria->id,
                    'id_usuario' => auth()->id(),
                    'id_empresa' => $planilla->id_empresa,
                    'id_sucursal' => $planilla->id_sucursal,
                    'nota' => "Aporte patronal total AFP - Planilla {$planilla->codigo} - Período {$planilla->fecha_inicio} al {$planilla->fecha_fin}"
                ]);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Error registrando gastos de planilla', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Enviar boletas por correo
     */
    private function enviarBoletasPorCorreo(Planilla $planilla)
    {
        $emailsEnviados = 0;
        $errores = [];
        $detallesProcesados = 0;
        $empleadosSinEmail = 0;
        $empleadosInactivos = 0;

        foreach ($planilla->detalles as $detalle) {
            $detallesProcesados++;

            if (!isset($detalle->empleado)) {
                Log::warning('Detalle sin empleado asociado', ['detalle_id' => $detalle->id]);
                $errores[] = "Detalle ID {$detalle->id} no tiene empleado asociado";
                continue;
            }

            if ($detalle->estado == PlanillaConstants::ESTADO_INACTIVO) {
                $empleadosInactivos++;
                continue;
            }

            if (empty($detalle->empleado->email)) {
                $empleadosSinEmail++;
                Log::warning('Empleado sin email', [
                    'empleado_id' => $detalle->empleado->id,
                    'nombre' => $detalle->empleado->nombres . ' ' . $detalle->empleado->apellidos
                ]);
                $errores[] = "Empleado {$detalle->empleado->nombres} {$detalle->empleado->apellidos} no tiene correo electrónico";
                continue;
            }

            $periodo = [
                'inicio' => $planilla->fecha_inicio,
                'fin' => $planilla->fecha_fin
            ];

            try {
                Mail::to($detalle->empleado->email)
                    ->send(new BoletaPagoMailable(
                        $detalle,
                        $planilla,
                        $planilla->empresa,
                        $periodo
                    ));

                $emailsEnviados++;
            } catch (\Exception $e) {
                Log::error('Error enviando correo', [
                    'empleado_email' => $detalle->empleado->email,
                    'empleado_id' => $detalle->empleado->id,
                    'error' => $e->getMessage()
                ]);
                $errores[] = "Error enviando correo a {$detalle->empleado->email}: {$e->getMessage()}";
            }
        }

        return [
            'emails_enviados' => $emailsEnviados,
            'detalles_procesados' => $detallesProcesados,
            'empleados_sin_email' => $empleadosSinEmail,
            'empleados_inactivos' => $empleadosInactivos,
            'errores' => $errores
        ];
    }

    /**
     * Verificar configuración de correo
     */
    private function verificarConfiguracionCorreo()
    {
        $config = [
            'MAIL_MAILER' => config('mail.default'),
            'MAIL_HOST' => config('mail.mailers.smtp.host'),
            'MAIL_PORT' => config('mail.mailers.smtp.port'),
            'MAIL_USERNAME' => config('mail.mailers.smtp.username'),
            'MAIL_ENCRYPTION' => config('mail.mailers.smtp.encryption'),
            'MAIL_FROM_ADDRESS' => config('mail.from.address'),
        ];

        $faltantes = [];
        foreach ($config as $key => $value) {
            if (empty($value)) {
                $faltantes[] = $key;
            }
        }

        if (!empty($faltantes)) {
            Log::warning('Configuración de correo incompleta', [
                'faltantes' => $faltantes
            ]);
            throw new \Exception('Configuración de correo incompleta. Falta: ' . implode(', ', $faltantes));
        }
    }
}

