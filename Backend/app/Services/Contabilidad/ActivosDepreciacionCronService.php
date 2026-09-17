<?php

namespace App\Services\Contabilidad;

use App\Models\Admin\Empresa;
use App\Models\Contabilidad\ActivoConfiguracion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ActivosDepreciacionCronService
{
    public function __construct(
        private DepreciacionService $depreciacionService
    ) {}

    public static function periodoAutomatico(?Carbon $fecha = null): string
    {
        return ($fecha ?? Carbon::today())->copy()->subMonth()->format('Y-m');
    }

    public static function esDiaDeCorte(int $diaCorte, ?Carbon $fecha = null): bool
    {
        $fecha = ($fecha ?? Carbon::today())->copy()->startOfDay();
        $diaEfectivo = min(max(1, $diaCorte), $fecha->daysInMonth);

        return $fecha->day === $diaEfectivo;
    }

    public function empresasElegibles(?int $idEmpresa = null): Collection
    {
        return Empresa::query()
            ->where('activo', true)
            ->when($idEmpresa, fn ($q) => $q->where('id', $idEmpresa))
            ->whereHas('empresaFuncionalidad', fn ($q) => $q
                ->where('activo', true)
                ->whereHas('funcionalidad', fn ($f) => $f->where('slug', 'contabilidad')))
            ->get();
    }

    /**
     * @return array{procesadas: int, omitidas: int, errores: int, detalle: list<array<string, mixed>>}
     */
    public function ejecutar(
        ?int $idEmpresa = null,
        ?string $periodo = null,
        bool $dryRun = false,
        bool $forzar = false,
        ?Carbon $fecha = null
    ): array {
        $fecha = ($fecha ?? Carbon::today())->copy()->startOfDay();
        $periodo = $periodo ?? static::periodoAutomatico($fecha);
        $empresas = $this->empresasElegibles($idEmpresa);

        $resumen = [
            'procesadas' => 0,
            'omitidas' => 0,
            'errores' => 0,
            'detalle' => [],
        ];

        foreach ($empresas as $empresa) {
            $config = ActivoConfiguracion::forEmpresa($empresa->id);

            if ($config->frecuencia !== 'mensual') {
                $resumen['omitidas']++;
                $resumen['detalle'][] = $this->fila($empresa, 'omitida', 'Frecuencia distinta a mensual');
                continue;
            }

            if (! $forzar && ! static::esDiaDeCorte((int) $config->dia_corte, $fecha)) {
                $resumen['omitidas']++;
                $resumen['detalle'][] = $this->fila(
                    $empresa,
                    'omitida',
                    "Hoy ({$fecha->toDateString()}) no es día de corte ({$config->dia_corte})"
                );
                continue;
            }

            $idUsuario = User::withoutGlobalScopes()
                ->where('id_empresa', $empresa->id)
                ->orderBy('id')
                ->value('id');

            if (! $idUsuario) {
                $resumen['errores']++;
                $msg = 'Sin usuarios en la empresa';
                $resumen['detalle'][] = $this->fila($empresa, 'error', $msg);
                Log::warning('activos:depreciacion-mensual', [
                    'empresa_id' => $empresa->id,
                    'periodo' => $periodo,
                    'error' => $msg,
                ]);
                continue;
            }

            try {
                if ($dryRun) {
                    $preview = $this->depreciacionService->previewCorrida($empresa->id, $periodo);
                    $resumen['procesadas']++;
                    $resumen['detalle'][] = $this->fila($empresa, 'dry-run', null, [
                        'periodo' => $periodo,
                        'cantidad' => $preview['cantidad'],
                        'total' => $preview['total'],
                        'ya_aplicada' => $preview['ya_aplicada'],
                    ]);
                    continue;
                }

                $resultado = $this->depreciacionService->ejecutarCorrida($empresa->id, $periodo, (int) $idUsuario);
                $resumen['procesadas']++;
                $resumen['detalle'][] = $this->fila($empresa, 'ok', null, $resultado);

                Log::info('activos:depreciacion-mensual', [
                    'empresa_id' => $empresa->id,
                    'periodo' => $periodo,
                    'resultado' => $resultado,
                ]);
            } catch (RuntimeException $e) {
                $resumen['errores']++;
                $resumen['detalle'][] = $this->fila($empresa, 'error', $e->getMessage());
                Log::warning('activos:depreciacion-mensual', [
                    'empresa_id' => $empresa->id,
                    'periodo' => $periodo,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $resumen;
    }

    private function fila(Empresa $empresa, string $estado, ?string $mensaje = null, array $extra = []): array
    {
        return array_merge([
            'empresa_id' => $empresa->id,
            'empresa' => $empresa->nombre_empresa ?? $empresa->nombre ?? "Empresa #{$empresa->id}",
            'estado' => $estado,
            'mensaje' => $mensaje,
        ], $extra);
    }
}
