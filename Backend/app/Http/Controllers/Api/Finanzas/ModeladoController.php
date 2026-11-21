<?php

namespace App\Http\Controllers\Api\Finanzas;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use JWTAuth;

class ModeladoController extends Controller
{
    private const RUBROS_GASTO = 'personal,local,ventas_marketing,admin,financieros,costo,otros';

    private const RUBROS_PRESUPUESTO = 'ventas,costo,personal,local,marketing,admin,financieros';

    public function show(Request $request)
    {
        $empresa = $this->empresaId();
        $anio = (int) $request->input('anio', date('Y'));

        return response()->json([
            'anio' => $anio,
            'clasificaciones' => DB::table('modelado_clasificaciones')
                ->where('id_empresa', $empresa)
                ->orderBy('nombre')
                ->get(['clave', 'nombre', 'rubro', 'tipo', 'monto', 'pct_ventas', 'dia_pago', 'proveedor', 'sucursal', 'suspendido']),
            'calendario' => DB::table('modelado_calendario')
                ->where('id_empresa', $empresa)
                ->orderBy('id')
                ->get(['id', 'clave', 'nombre', 'detalle', 'monto', 'frecuencia', 'dia', 'origen']),
            'presupuesto' => [
                'reparto' => DB::table('modelado_presupuesto_repartos')
                    ->where('id_empresa', $empresa)
                    ->where('anio', $anio)
                    ->value('reparto'),
                'filas' => DB::table('modelado_presupuesto_lineas')
                    ->where('id_empresa', $empresa)
                    ->where('anio', $anio)
                    ->get(['rubro', 'monto_anual']),
                'estacionalidad' => DB::table('modelado_estacionalidad')
                    ->where('id_empresa', $empresa)
                    ->where('anio', $anio)
                    ->orderBy('mes')
                    ->pluck('peso', 'mes'),
            ],
            'pronostico' => DB::table('modelado_pronosticos')
                ->where('id_empresa', $empresa)
                ->first([
                    'efectivo',
                    'efectivo_fecha',
                    'colchon',
                    'costo_capital_pct',
                    'escenario_conservador_pct',
                    'escenario_optimista_pct',
                ]),
        ]);
    }

    public function guardarClasificacion(Request $request)
    {
        $data = $request->validate([
            'clave' => 'required|string|max:120',
            'nombre' => 'required|string|max:200',
            'rubro' => 'required|in:'.self::RUBROS_GASTO,
            'tipo' => 'nullable|in:fijo,variable',
            'monto' => 'nullable|numeric|min:0',
            'pctVentas' => 'nullable|numeric|min:0|max:100',
            'diaPago' => 'nullable|string|max:32',
            'proveedor' => 'nullable|string|max:191',
            'sucursal' => 'nullable|string|max:191',
            'suspendido' => 'nullable|boolean',
        ]);
        $empresa = $this->empresaId();
        $now = now();
        $where = ['id_empresa' => $empresa, 'clave' => $data['clave']];
        $values = [
            'nombre' => $data['nombre'],
            'rubro' => $data['rubro'],
            'tipo' => $data['tipo'] ?? null,
            'monto' => $data['monto'] ?? null,
            'pct_ventas' => $data['pctVentas'] ?? null,
            'dia_pago' => $data['diaPago'] ?? null,
            'proveedor' => $data['proveedor'] ?? null,
            'sucursal' => $data['sucursal'] ?? null,
            'updated_at' => $now,
        ];
        if (array_key_exists('suspendido', $data) && $data['suspendido'] !== null) {
            $values['suspendido'] = (bool) $data['suspendido'];
        }
        DB::transaction(function () use ($where, $values, $empresa, $data, $now) {
            if (DB::table('modelado_clasificaciones')->where($where)->exists()) {
                DB::table('modelado_clasificaciones')->where($where)->update($values);
            } else {
                DB::table('modelado_clasificaciones')->insert($where + $values + [
                    'suspendido' => $values['suspendido'] ?? false,
                    'created_at' => $now,
                ]);
            }

            if (! empty($values['suspendido'])) {
                DB::table('modelado_calendario')
                    ->where('id_empresa', $empresa)
                    ->where('clave', $data['clave'])
                    ->where('origen', 'manual')
                    ->delete();
            }
        });

        return response()->json(['ok' => true]);
    }

    public function borrarClasificacion(Request $request)
    {
        $data = $request->validate(['clave' => 'required|string|max:120']);
        $empresa = $this->empresaId();
        DB::transaction(function () use ($empresa, $data) {
            DB::table('modelado_clasificaciones')
                ->where('id_empresa', $empresa)
                ->where('clave', $data['clave'])
                ->delete();

            DB::table('modelado_calendario')
                ->where('id_empresa', $empresa)
                ->where('clave', $data['clave'])
                ->where('origen', 'manual')
                ->delete();
        });

        return response()->json(['ok' => true]);
    }

    public function crearCalendario(Request $request)
    {
        $data = $this->validarCalendario($request);
        $id = DB::table('modelado_calendario')->insertGetId([
            'id_empresa' => $this->empresaId(),
            'clave' => $data['clave'] ?? null,
            'nombre' => $data['nombre'],
            'detalle' => $data['detalle'] ?? null,
            'monto' => $data['monto'],
            'frecuencia' => $data['frecuencia'],
            'dia' => $data['dia'],
            'origen' => 'manual',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['id' => $id], 201);
    }

    public function actualizarCalendario(Request $request, int $id)
    {
        $data = $this->validarCalendario($request);
        $empresa = $this->empresaId();
        $now = now();
        $n = DB::table('modelado_calendario')
            ->where('id', $id)
            ->where('id_empresa', $empresa)
            ->where('origen', 'manual')
            ->update([
                'clave' => $data['clave'] ?? null,
                'nombre' => $data['nombre'],
                'detalle' => $data['detalle'] ?? null,
                'monto' => $data['monto'],
                'frecuencia' => $data['frecuencia'],
                'dia' => $data['dia'],
                'updated_at' => $now,
            ]);
        if ($n === 0) {
            return response()->json(['message' => 'No se encontró la regla'], 404);
        }
        if (! empty($data['clave'])) {
            DB::table('modelado_clasificaciones')
                ->where('id_empresa', $empresa)
                ->where('clave', $data['clave'])
                ->update([
                    'dia_pago' => $data['dia'],
                    'updated_at' => $now,
                ]);
        }

        return response()->json(['ok' => true]);
    }

    public function borrarCalendario(int $id)
    {
        $n = DB::table('modelado_calendario')
            ->where('id', $id)
            ->where('id_empresa', $this->empresaId())
            ->where('origen', 'manual')
            ->delete();
        if ($n === 0) {
            return response()->json(['message' => 'No se encontró la regla'], 404);
        }

        return response()->json(['ok' => true]);
    }

    public function guardarPresupuesto(Request $request)
    {
        $data = $request->validate([
            'anio' => 'required|integer|min:2000|max:2100',
            'reparto' => 'required|in:igual,estacionalidad',
            'filas' => 'required|array|min:1',
            'filas.*.rubro' => 'required|in:'.self::RUBROS_PRESUPUESTO,
            'filas.*.anual' => 'required|numeric|min:0',
            'estacionalidad' => 'nullable|array|size:12',
            'estacionalidad.*' => 'numeric|min:0',
            'sucursales' => 'nullable|array',
            'sucursales.*.idSucursal' => 'required|integer|min:1',
            'sucursales.*.filas' => 'required|array|min:1',
            'sucursales.*.filas.*.rubro' => 'required|in:'.self::RUBROS_PRESUPUESTO,
            'sucursales.*.filas.*.anual' => 'required|numeric|min:0',
            'areas' => 'nullable|array',
            'areas.*.idArea' => 'required|integer|min:1',
            'areas.*.filas' => 'required|array|min:1',
            'areas.*.filas.*.rubro' => 'required|in:'.self::RUBROS_PRESUPUESTO,
            'areas.*.filas.*.anual' => 'required|numeric|min:0',
        ]);
        if ($data['reparto'] === 'estacionalidad' && empty($data['estacionalidad'])) {
            return response()->json(['message' => 'estacionalidad requiere 12 pesos'], 422);
        }

        $empresa = $this->empresaId();
        $anio = (int) $data['anio'];
        $now = now();

        DB::transaction(function () use ($empresa, $anio, $data, $now) {
            $repartoWhere = ['id_empresa' => $empresa, 'anio' => $anio];
            $repartoValues = ['reparto' => $data['reparto'], 'updated_at' => $now];
            if (DB::table('modelado_presupuesto_repartos')->where($repartoWhere)->exists()) {
                DB::table('modelado_presupuesto_repartos')->where($repartoWhere)->update($repartoValues);
            } else {
                DB::table('modelado_presupuesto_repartos')->insert($repartoWhere + $repartoValues + ['created_at' => $now]);
            }
            DB::table('modelado_presupuesto_lineas')
                ->where('id_empresa', $empresa)
                ->where('anio', $anio)
                ->delete();
            foreach ($data['filas'] as $fila) {
                DB::table('modelado_presupuesto_lineas')->insert([
                    'id_empresa' => $empresa,
                    'anio' => $anio,
                    'rubro' => $fila['rubro'],
                    'monto_anual' => $fila['anual'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            DB::table('modelado_estacionalidad')
                ->where('id_empresa', $empresa)
                ->where('anio', $anio)
                ->delete();
            if ($data['reparto'] === 'estacionalidad') {
                foreach (array_values($data['estacionalidad']) as $i => $peso) {
                    DB::table('modelado_estacionalidad')->insert([
                        'id_empresa' => $empresa,
                        'anio' => $anio,
                        'mes' => $i + 1,
                        'peso' => $peso,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
            if (! empty($data['sucursales'])) {
                $this->guardarLineasPresupuestoPorEntidad(
                    'modelado_presupuesto_sucursal_lineas',
                    'id_sucursal',
                    'idSucursal',
                    $empresa,
                    $anio,
                    $data['sucursales'],
                    $now
                );
            }
            if (! empty($data['areas'])) {
                $this->guardarLineasPresupuestoPorEntidad(
                    'modelado_presupuesto_area_lineas',
                    'id_area_empresa',
                    'idArea',
                    $empresa,
                    $anio,
                    $data['areas'],
                    $now
                );
            }
            $this->crearPresupuestoFinanzasSiFalta($empresa, $anio, $data['filas'], $now);
        });

        return response()->json(['ok' => true]);
    }

    private function guardarLineasPresupuestoPorEntidad(
        string $tabla,
        string $colId,
        string $claveId,
        int $empresa,
        int $anio,
        array $bloques,
        $now
    ): void {
        DB::table($tabla)
            ->where('id_empresa', $empresa)
            ->where('anio', $anio)
            ->delete();
        foreach ($bloques as $bloque) {
            $entidadId = (int) $bloque[$claveId];
            foreach ($bloque['filas'] as $fila) {
                DB::table($tabla)->insert([
                    'id_empresa' => $empresa,
                    'anio' => $anio,
                    $colId => $entidadId,
                    'rubro' => $fila['rubro'],
                    'monto_anual' => $fila['anual'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    // Copia el anual de la suite a presupuestos de Finanzas solo si la empresa
    // no tiene ya uno vigente ese año. No pisa el detalle de uno importado.
    private function crearPresupuestoFinanzasSiFalta(int $empresa, int $anio, array $filas, $now): void
    {
        $desde = sprintf('%d-01-01', $anio);
        $hasta = sprintf('%d-12-31', $anio);
        $existe = DB::table('presupuestos')
            ->where('id_empresa', $empresa)
            ->where('enable', 1)
            ->where(function ($q) {
                $q->whereNull('id_proyecto')->orWhere('id_proyecto', 0);
            })
            ->where('fecha_inicio', '<=', $hasta)
            ->where('fecha_fin', '>=', $desde)
            ->exists();
        if ($existe) {
            return;
        }
        $anual = [];
        foreach ($filas as $fila) {
            $anual[$fila['rubro']] = (float) $fila['anual'];
        }
        $egresos = ($anual['costo'] ?? 0) + ($anual['personal'] ?? 0) + ($anual['local'] ?? 0)
            + ($anual['marketing'] ?? 0) + ($anual['admin'] ?? 0) + ($anual['financieros'] ?? 0);
        $ingresos = $anual['ventas'] ?? 0;
        try {
            DB::table('presupuestos')->insert([
                'id_empresa' => $empresa,
                'titulo' => 'Presupuesto '.$anio,
                'fecha_inicio' => $desde,
                'fecha_fin' => $hasta,
                'ingresos' => $ingresos,
                'egresos' => $egresos,
                'compras' => 0,
                'utilidad' => $ingresos - $egresos,
                'margen' => $ingresos > 0 ? round(($ingresos - $egresos) / $ingresos * 100, 2) : 0,
                'costo_de_venta' => $anual['costo'] ?? 0,
                'planilla' => $anual['personal'] ?? 0,
                'alquiler' => $anual['local'] ?? 0,
                'marketing' => $anual['marketing'] ?? 0,
                'gastos_administrativos' => $anual['admin'] ?? 0,
                'prestamos' => $anual['financieros'] ?? 0,
                'enable' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (\Throwable $e) {
            // El guardado de la suite ya quedó. Finanzas no recibió la copia.
        }
    }

    public function guardarPronostico(Request $request)
    {
        $data = $request->validate([
            'efectivo' => 'nullable|numeric',
            'efectivoFecha' => 'nullable|date',
            'colchon' => 'nullable|numeric|min:0',
            'costoCapitalPctMes' => 'nullable|numeric',
            'escenarioConservadorPct' => 'nullable|numeric',
            'escenarioOptimistaPct' => 'nullable|numeric',
        ]);
        $empresa = $this->empresaId();
        $now = now();
        $prev = DB::table('modelado_pronosticos')->where('id_empresa', $empresa)->first();
        $values = [
            'efectivo' => $data['efectivo'] ?? null,
            'efectivo_fecha' => $data['efectivoFecha'] ?? null,
            'colchon' => $data['colchon'] ?? null,
            'costo_capital_pct' => $data['costoCapitalPctMes'] ?? null,
            'escenario_conservador_pct' => $data['escenarioConservadorPct'] ?? ($prev->escenario_conservador_pct ?? -10),
            'escenario_optimista_pct' => $data['escenarioOptimistaPct'] ?? ($prev->escenario_optimista_pct ?? 12),
            'updated_at' => $now,
        ];
        if ($prev) {
            DB::table('modelado_pronosticos')->where('id_empresa', $empresa)->update($values);
        } else {
            DB::table('modelado_pronosticos')->insert($values + [
                'id_empresa' => $empresa,
                'created_at' => $now,
            ]);
        }

        return response()->json(['ok' => true]);
    }

    private function validarCalendario(Request $request): array
    {
        return $request->validate([
            'clave' => 'nullable|string|max:120',
            'nombre' => 'required|string|max:200',
            'detalle' => 'nullable|string|max:200',
            'monto' => 'required|numeric|min:0',
            'frecuencia' => 'required|in:quincenal,mensual,anual',
            'dia' => 'required|string|max:32',
        ]);
    }

    private function empresaId(): int
    {
        return (int) JWTAuth::parseToken()->authenticate()->id_empresa;
    }
}
