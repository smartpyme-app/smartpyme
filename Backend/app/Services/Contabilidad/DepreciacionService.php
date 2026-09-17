<?php

namespace App\Services\Contabilidad;

use App\Models\Compras\Gastos\Categoria;
use App\Models\Compras\Gastos\Gasto;
use App\Models\Contabilidad\Activo;
use App\Models\Contabilidad\ActivoConfiguracion;
use App\Models\Contabilidad\ActivoDepreciacion;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DepreciacionService
{
    private function decimales(int $idEmpresa): int
    {
        return (int) (ActivoConfiguracion::forEmpresa($idEmpresa)->redondeo_decimales ?? 2);
    }

    public function vidaUtilAnios(Activo $activo): float
    {
        if ((float) ($activo->vida_util ?? 0) > 0) {
            return (float) $activo->vida_util;
        }

        if (! $activo->relationLoaded('categoria')) {
            $activo->loadMissing('categoria');
        }

        return (float) ($activo->categoria?->vida_util_anios ?? 0);
    }

    public function ajustarPorBienUsado(Activo $activo): float
    {
        $valor = (float) $activo->valor_compra;

        if (! $activo->es_usado) {
            return $valor;
        }

        $pct = (float) ($activo->porcentaje_base_usado ?? 100);

        $dec = $this->decimales((int) $activo->id_empresa);

        return round($valor * ($pct / 100), $dec);
    }

    public function calcularCuotaMensual(Activo $activo): float
    {
        $base = $this->ajustarPorBienUsado($activo);
        $residual = (float) ($activo->valor_residual ?? 0);
        $depreciable = max(0, $base - $residual);
        $meses = $this->vidaUtilAnios($activo) * 12;
        $dec = $this->decimales((int) $activo->id_empresa);

        if ($meses <= 0) {
            return 0.0;
        }

        return round($depreciable / $meses, $dec);
    }

    public function generarCronograma(Activo $activo): Collection
    {
        if ($activo->estado_registro !== 'activo') {
            return collect();
        }

        $activo->loadMissing('categoria');

        if (ActivoDepreciacion::withoutGlobalScopes()
            ->where('id_activo', $activo->id)
            ->where('estado', 'aplicada')
            ->exists()) {
            return ActivoDepreciacion::withoutGlobalScopes()
                ->where('id_activo', $activo->id)
                ->orderBy('periodo')
                ->get();
        }

        ActivoDepreciacion::withoutGlobalScopes()
            ->where('id_activo', $activo->id)
            ->where('estado', 'pendiente')
            ->delete();

        $meses = (int) round($this->vidaUtilAnios($activo) * 12);
        $cuota = $this->calcularCuotaMensual($activo);
        $dec = $this->decimales((int) $activo->id_empresa);

        if ($meses <= 0 || $cuota <= 0) {
            return collect();
        }

        $base = $this->ajustarPorBienUsado($activo);
        $residual = (float) ($activo->valor_residual ?? 0);
        $depreciable = max(0, $base - $residual);
        $inicio = Carbon::parse($activo->fecha_inicio_depreciacion ?? $activo->fecha_compra)->startOfMonth();

        $lineas = collect();
        $acumulada = 0.0;

        for ($i = 0; $i < $meses; $i++) {
            $monto = ($i === $meses - 1)
                ? round($depreciable - ($cuota * ($meses - 1)), $dec)
                : $cuota;

            $acumulada = round($acumulada + $monto, $dec);
            $valorLibros = max($residual, round($base - $acumulada, $dec));

            $lineas->push(ActivoDepreciacion::withoutGlobalScopes()->create([
                'id_activo' => $activo->id,
                'id_empresa' => $activo->id_empresa,
                'periodo' => $inicio->copy()->addMonths($i)->format('Y-m'),
                'monto' => $monto,
                'depreciacion_acumulada' => $acumulada,
                'valor_en_libros' => $valorLibros,
                'estado' => 'pendiente',
            ]));
        }

        return $lineas;
    }

    public function previewCorrida(int $idEmpresa, string $periodo): array
    {
        $lineas = ActivoDepreciacion::withoutGlobalScopes()
            ->with(['activo:id,nombre,referencia'])
            ->where('id_empresa', $idEmpresa)
            ->where('periodo', $periodo)
            ->where('estado', 'pendiente')
            ->orderBy('id_activo')
            ->get();

        $yaAplicada = ActivoDepreciacion::withoutGlobalScopes()
            ->where('id_empresa', $idEmpresa)
            ->where('periodo', $periodo)
            ->where('estado', 'aplicada')
            ->exists();

        $dec = $this->decimales($idEmpresa);

        return [
            'periodo' => $periodo,
            'total' => round((float) $lineas->sum('monto'), $dec),
            'cantidad' => $lineas->count(),
            'ya_aplicada' => $yaAplicada,
            'lineas' => $lineas,
        ];
    }

    public function ejecutarCorrida(int $idEmpresa, string $periodo, int $idUsuario): array
    {
        return DB::transaction(function () use ($idEmpresa, $periodo, $idUsuario) {
            $preview = $this->previewCorrida($idEmpresa, $periodo);

            if ($preview['ya_aplicada']) {
                throw new RuntimeException("La depreciación del período {$periodo} ya fue aplicada.");
            }

            if ($preview['cantidad'] === 0) {
                throw new RuntimeException("No hay depreciaciones pendientes para {$periodo}.");
            }

            $categoria = Categoria::withoutGlobalScopes()
                ->where('id_empresa', $idEmpresa)
                ->where('nombre', 'Depreciaciones')
                ->first();

            if (! $categoria) {
                throw new RuntimeException('Configure la categoría de gasto "Depreciaciones" antes de ejecutar la corrida.');
            }

            $total = $preview['total'];
            $dec = $this->decimales($idEmpresa);
            $detalleActivos = collect($preview['lineas'])->map(function (ActivoDepreciacion $linea) use ($dec) {
                $nombre = $linea->activo?->nombre ?? "Activo #{$linea->id_activo}";

                return "{$nombre}: ".number_format((float) $linea->monto, $dec);
            })->implode('; ');

            $egreso = Gasto::create([
                'fecha' => Carbon::createFromFormat('Y-m', $periodo)->endOfMonth()->toDateString(),
                'fecha_pago' => Carbon::createFromFormat('Y-m', $periodo)->endOfMonth()->toDateString(),
                'tipo_documento' => 'Depreciación',
                'referencia' => "AF-DEP-{$periodo}",
                'concepto' => "Depreciación activos fijos — {$periodo}",
                'tipo' => 'Depreciaciones',
                'estado' => 'Pagado',
                'forma_pago' => 'Interno',
                'sub_total' => $total,
                'total' => $total,
                'id_categoria' => $categoria->id,
                'id_usuario' => $idUsuario,
                'id_empresa' => $idEmpresa,
                'nota' => $detalleActivos,
            ]);

            foreach ($preview['lineas'] as $linea) {
                $linea->estado = 'aplicada';
                $linea->id_egreso = $egreso->id;
                $linea->save();

                $activo = Activo::withoutGlobalScopes()->find($linea->id_activo);
                if (! $activo) {
                    continue;
                }

                $activo->depreciacion_acumulada = $linea->depreciacion_acumulada;
                $activo->valor_en_libros = $linea->valor_en_libros;
                $activo->recalcularValorEnLibros();
                $activo->save();
            }

            return [
                'periodo' => $periodo,
                'total' => $total,
                'cantidad' => $preview['cantidad'],
                'id_egreso' => $egreso->id,
            ];
        });
    }
}
