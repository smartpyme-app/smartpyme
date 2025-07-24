<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Contabilidad\CierreMesService;
use App\Models\Admin\Empresa;
use App\Models\User;
use Carbon\Carbon;

class CerrarMesContable extends Command
{
    protected $signature = 'contabilidad:cerrar-mes
                            {--empresa=* : IDs de las empresas a procesar}
                            {--mes= : Mes a cerrar (1-12)}
                            {--anio= : Año a cerrar}
                            {--usuario= : ID del usuario que ejecuta el cierre}
                            {--forzar : Forzar cierre aunque haya validaciones}
                            {--dry-run : Simular el cierre sin ejecutar}';

    protected $description = 'Ejecuta el cierre de mes contable para una o múltiples empresas';

    public function handle()
    {
        $this->info('🚀 Iniciando proceso de cierre de mes contable...');

        // Obtener parámetros
        $empresas = $this->option('empresa');
        $mes = $this->option('mes') ?? Carbon::now()->month;
        $anio = $this->option('anio') ?? Carbon::now()->year;
        $usuarioId = $this->option('usuario') ?? 1;
        $forzar = $this->option('forzar');
        $dryRun = $this->option('dry-run');

        // Validar parámetros
        if (!$this->validarParametros($mes, $anio, $usuarioId)) {
            return 1;
        }

        // Obtener empresas a procesar
        $empresasAProcesar = $this->obtenerEmpresas($empresas);

        if ($empresasAProcesar->isEmpty()) {
            $this->error('❌ No se encontraron empresas para procesar');
            return 1;
        }

        // Mostrar resumen
        $this->mostrarResumen($empresasAProcesar, $mes, $anio, $dryRun);

        // Confirmar ejecución
        if (!$dryRun && !$this->confirm('¿Desea continuar con el cierre de mes?')) {
            $this->info('Operación cancelada por el usuario');
            return 0;
        }

        // Procesar cada empresa
        $resultados = [];
        $cierreMesService = new CierreMesService();

        foreach ($empresasAProcesar as $empresa) {
            $this->info("\n📊 Procesando empresa: {$empresa->nombre_empresa}");

            try {
                if ($dryRun) {
                    $resultado = $this->simularCierre($cierreMesService, $anio, $mes, $empresa->id);
                } else {
                    $resultado = $cierreMesService->cerrarMes($anio, $mes, $usuarioId, $empresa->id);
                }

                $resultados[] = [
                    'empresa' => $empresa->nombre_empresa,
                    'status' => 'success',
                    'resultado' => $resultado
                ];

                $this->info("✅ Cierre exitoso para {$empresa->nombre_empresa}");

            } catch (\Exception $e) {
                $resultados[] = [
                    'empresa' => $empresa->nombre_empresa,
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];

                $this->error("❌ Error en {$empresa->nombre_empresa}: {$e->getMessage()}");

                if (!$forzar) {
                    $this->error('Deteniendo proceso por error. Use --forzar para continuar');
                    break;
                }
            }
        }

        // Mostrar resumen final
        $this->mostrarResumenFinal($resultados, $dryRun);

        return 0;
    }

    private function validarParametros($mes, $anio, $usuarioId)
    {
        if ($mes < 1 || $mes > 12) {
            $this->error('❌ Mes inválido. Debe estar entre 1 y 12');
            return false;
        }

        if ($anio < 2000 || $anio > Carbon::now()->year + 1) {
            $this->error('❌ Año inválido');
            return false;
        }

        if (!User::find($usuarioId)) {
            $this->error('❌ Usuario no encontrado');
            return false;
        }

        return true;
    }

    private function obtenerEmpresas($empresasIds)
    {
        if (empty($empresasIds)) {
            return Empresa::where('activo', true)->get();
        }

        return Empresa::whereIn('id', $empresasIds)->where('activo', true)->get();
    }

    private function mostrarResumen($empresas, $mes, $anio, $dryRun)
    {
        $this->info("\n📋 RESUMEN DE OPERACIÓN");
        $this->info("Período: {$mes}/{$anio}");
        $this->info("Empresas a procesar: {$empresas->count()}");
        $this->info("Modo: " . ($dryRun ? 'SIMULACIÓN' : 'EJECUCIÓN REAL'));

        $this->table(
            ['ID', 'Empresa', 'Estado'],
            $empresas->map(function ($empresa) {
                return [
                    $empresa->id,
                    $empresa->nombre_empresa,
                    $empresa->activo ? 'Activo' : 'Inactivo'
                ];
            })
        );
    }

    private function simularCierre($service, $anio, $mes, $empresaId)
    {
        $this->info("🔍 Simulando cierre...");

        // Verificar validaciones sin ejecutar
        try {
            // Simular las validaciones principales
            $cerrado = $service->estaPeriodoCerrado($anio, $mes, $empresaId);

            if ($cerrado) {
                throw new \Exception("El período {$mes}/{$anio} ya está cerrado");
            }

            $this->info("✅ Validaciones pasadas");

            return [
                'simulacion' => true,
                'periodo' => "{$mes}/{$anio}",
                'validaciones' => 'OK'
            ];

        } catch (\Exception $e) {
            throw new \Exception("Simulación falló: " . $e->getMessage());
        }
    }

    private function mostrarResumenFinal($resultados, $dryRun)
    {
        $exitosos = collect($resultados)->where('status', 'success')->count();
        $errores = collect($resultados)->where('status', 'error')->count();

        $this->info("\n📊 RESUMEN FINAL");
        $this->info("Empresas procesadas exitosamente: {$exitosos}");
        $this->info("Empresas con errores: {$errores}");

        if ($errores > 0) {
            $this->error("\n❌ ERRORES ENCONTRADOS:");
            collect($resultados)->where('status', 'error')->each(function ($resultado) {
                $this->error("- {$resultado['empresa']}: {$resultado['error']}");
            });
        }

        if ($exitosos > 0) {
            $this->info("\n✅ CIERRES EXITOSOS:");
            collect($resultados)->where('status', 'success')->each(function ($resultado) {
                $empresa = $resultado['empresa'];
                if (!$dryRun) {
                    $cuentas = $resultado['resultado']['cuentas_procesadas'] ?? 0;
                    $this->info("- {$empresa}: {$cuentas} cuentas procesadas");
                } else {
                    $this->info("- {$empresa}: Simulación exitosa");
                }
            });
        }

        if ($dryRun) {
            $this->warn("\n⚠️  ESTO FUE UNA SIMULACIÓN - No se realizaron cambios reales");
        }
    }
}
