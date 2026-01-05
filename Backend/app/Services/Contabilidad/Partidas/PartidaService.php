<?php

namespace App\Services\Contabilidad\Partidas;

use App\Models\Contabilidad\Partidas\Partida;
use App\Models\Contabilidad\Partidas\Detalle;
use App\Models\Contabilidad\Catalogo\Cuenta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class PartidaService
{
    /**
     * Crea o actualiza una partida con sus detalles
     *
     * @param array $data
     * @return Partida
     */
    public function crearOActualizar(array $data): Partida
    {
        DB::beginTransaction();

        $startTime = microtime(true);

        Log::info('=== INICIO crearOActualizar partida ===', [
            'partida_id' => $data['id'] ?? null,
            'total_detalles_recibidos' => count($data['detalles'] ?? []),
            'memoria_inicial_mb' => round(memory_get_usage(true) / 1024 / 1024, 2)
        ]);

        try {
            if (isset($data['id'])) {
                $partida = Partida::findOrFail($data['id']);
            } else {
                $partida = new Partida;
            }

            $estadoOriginal = $partida->estado;

            $partida->fill($data);
            $partida->save();

            Log::info('Partida guardada', [
                'partida_id' => $partida->id,
                'tiempo_desde_inicio' => microtime(true) - $startTime
            ]);

            // Manejar cambios de estado y correlativos
            if (isset($data['estado'])) {
                $estadoNuevo = $data['estado'];

                // Si cambió a "Anulada", quitar correlativo
                if ($estadoOriginal !== 'Anulada' && $estadoNuevo === 'Anulada') {
                    $partida->correlativo = null;
                    $partida->save();

                    // Reordenar automáticamente ese mes/tipo
                    $año = date('Y', strtotime($partida->fecha));
                    $mes = date('m', strtotime($partida->fecha));
                    Partida::reordenarCorrelativos($año, $mes, $partida->tipo, $partida->id_empresa);
                }

                // Si cambió de "Anulada" a otro estado, regenerar correlativo
                if ($estadoOriginal === 'Anulada' && $estadoNuevo !== 'Anulada') {
                    // Regenerar correlativo
                    $partida->correlativo = $partida->generarCorrelativo();
                    $partida->save();

                    // Reordenar automáticamente ese mes/tipo
                    $año = date('Y', strtotime($partida->fecha));
                    $mes = date('m', strtotime($partida->fecha));
                    Partida::reordenarCorrelativos($año, $mes, $partida->tipo, $partida->id_empresa);
                }
            }

            // Procesar detalles
            if (isset($data['detalles'])) {
                // Si se está editando una partida existente, eliminar detalles antiguos que no están en el request
                if (isset($data['id'])) {
                    // Obtener IDs de los detalles que vienen en el request
                    $idsDetallesRequest = collect($data['detalles'])->pluck('id')->filter()->toArray();
                    
                    // Eliminar detalles que no están en el request
                    Detalle::where('id_partida', $partida->id)
                        ->whereNotIn('id', $idsDetallesRequest)
                        ->delete();
                }

                $totalDetalles = count($data['detalles']);
                $detallesProcesados = 0;

                Log::info('Iniciando procesamiento de detalles', [
                    'partida_id' => $partida->id,
                    'total_detalles_a_procesar' => $totalDetalles,
                    'tiempo_desde_inicio' => microtime(true) - $startTime
                ]);

                foreach ($data['detalles'] as $index => $det) {
                    $detallesProcesados++;

                    // Log cada 100 detalles para monitorear progreso
                    if ($detallesProcesados % 100 == 0) {
                        Log::info('Procesando detalles', [
                            'progreso' => "$detallesProcesados/$totalDetalles",
                            'tiempo_desde_inicio' => round(microtime(true) - $startTime, 2)
                        ]);
                    }

                    if (isset($det['id'])) {
                        $detalle = Detalle::findOrFail($det['id']);
                        $cuenta = Cuenta::findOrFail($det['id_cuenta']);
                    } else {
                        $detalle = new Detalle;
                        $cuenta = Cuenta::findOrFail($det['id_cuenta']);
                    }

                    // Normalizar valores decimales ANTES de guardar (convertir comas a puntos)
                    if (isset($det['debe']) && $det['debe'] !== null && $det['debe'] !== '') {
                        $det['debe'] = $this->normalizarDecimal($det['debe']);
                    }
                    if (isset($det['haber']) && $det['haber'] !== null && $det['haber'] !== '') {
                        $det['haber'] = $this->normalizarDecimal($det['haber']);
                    }

                    $detalle['id_partida'] = $partida->id;
                    $detalle->fill($det);
                    $detalle['codigo'] = $cuenta->codigo;
                    $detalle['nombre_cuenta'] = $cuenta->nombre;
                    $detalle->save();
                }
            }

            Log::info('Todos los detalles procesados, haciendo commit', [
                'partida_id' => $partida->id,
                'total_detalles_procesados' => $detallesProcesados ?? 0,
                'tiempo_desde_inicio' => round(microtime(true) - $startTime, 2),
                'memoria_mb' => round(memory_get_usage(true) / 1024 / 1024, 2)
            ]);

            DB::commit();

            Log::info('=== FIN crearOActualizar partida (EXITOSO) ===', [
                'partida_id' => $partida->id,
                'tiempo_total_segundos' => round(microtime(true) - $startTime, 2),
                'memoria_final_mb' => round(memory_get_usage(true) / 1024 / 1024, 2)
            ]);

            return $partida;

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('=== ERROR crearOActualizar partida (Exception) ===', [
                'partida_id' => $data['id'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'tiempo_hasta_error' => round(microtime(true) - $startTime, 2)
            ]);
            throw $e;
        } catch (\Throwable $e) {
            DB::rollback();
            Log::error('=== ERROR crearOActualizar partida (Throwable) ===', [
                'partida_id' => $data['id'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'tiempo_hasta_error' => round(microtime(true) - $startTime, 2)
            ]);
            throw $e;
        }
    }

    /**
     * Normalizar valores decimales: convertir comas a puntos
     * Para evitar errores de sintaxis SQL con formatos de números europeos
     *
     * @param mixed $value
     * @return string
     */
    public function normalizarDecimal($value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }

        // Convertir a string y reemplazar comas por puntos
        $normalized = str_replace(',', '.', (string)$value);

        // Convertir a float y luego formatear con 2 decimales usando punto
        return number_format((float)$normalized, 2, '.', '');
    }

    /**
     * Calcular totales generales con los mismos filtros aplicados
     *
     * @param Request $request
     * @param int $idEmpresa
     * @return object
     */
    public function calcularTotalesGenerales(Request $request, int $idEmpresa): object
    {
        $query = DB::table('partidas')
            ->leftJoin('partida_detalles', 'partidas.id', '=', 'partida_detalles.id_partida')
            ->where('partidas.id_empresa', $idEmpresa);

        if (!$request->has('incluir_anuladas') ||
            $request->incluir_anuladas === false ||
            $request->incluir_anuladas === 'false' ||
            $request->incluir_anuladas === '0') {
            $query->where('partidas.estado', '!=', 'Anulada');
        }

        // Aplicar los mismos filtros que en index
        $query->when($request->buscador, function($q) use ($request){
            return $q->where(function($subQ) use ($request) {
                $subQ->where('partidas.concepto', 'like' ,'%' . $request->buscador . '%')
                     ->orWhere('partidas.tipo', 'like' ,'%' . $request->buscador . '%')
                     ->orWhere('partidas.correlativo', 'like' ,'%' . $request->buscador . '%');
            });
        })
        ->when($request->inicio, function($q) use ($request){
            return $q->where('partidas.fecha', '>=', $request->inicio);
        })
        ->when($request->fin, function($q) use ($request){
            return $q->where('partidas.fecha', '<=', $request->fin);
        })
        ->when($request->estado, function($q) use ($request){
            return $q->where('partidas.estado', $request->estado);
        })
        ->when($request->tipo, function($q) use ($request){
            return $q->where('partidas.tipo', $request->tipo);
        });

        return $query->selectRaw('
            COALESCE(SUM(partida_detalles.debe), 0) as gran_total_debe,
            COALESCE(SUM(partida_detalles.haber), 0) as gran_total_haber,
            COUNT(DISTINCT partidas.id) as total_registros_filtrados
        ')->first();
    }
}

