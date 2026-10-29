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
                ->get(['clave', 'nombre', 'rubro', 'tipo']),
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
        ]);
        $empresa = $this->empresaId();
        $now = now();
        $where = ['id_empresa' => $empresa, 'clave' => $data['clave']];
        $values = [
            'nombre' => $data['nombre'],
            'rubro' => $data['rubro'],
            'tipo' => $data['tipo'] ?? null,
            'updated_at' => $now,
        ];
        if (DB::table('modelado_clasificaciones')->where($where)->exists()) {
            DB::table('modelado_clasificaciones')->where($where)->update($values);
        } else {
            DB::table('modelado_clasificaciones')->insert($where + $values + ['created_at' => $now]);
        }

        return response()->json(['ok' => true]);
    }

    public function borrarClasificacion(Request $request)
    {
        $data = $request->validate(['clave' => 'required|string|max:120']);
        DB::table('modelado_clasificaciones')
            ->where('id_empresa', $this->empresaId())
            ->where('clave', $data['clave'])
            ->delete();

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
        $n = DB::table('modelado_calendario')
            ->where('id', $id)
            ->where('id_empresa', $this->empresaId())
            ->where('origen', 'manual')
            ->update([
                'clave' => $data['clave'] ?? null,
                'nombre' => $data['nombre'],
                'detalle' => $data['detalle'] ?? null,
                'monto' => $data['monto'],
                'frecuencia' => $data['frecuencia'],
                'dia' => $data['dia'],
                'updated_at' => now(),
            ]);
        if ($n === 0) {
            return response()->json(['message' => 'No se encontró la regla'], 404);
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
        });

        return response()->json(['ok' => true]);
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
