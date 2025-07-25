<?php

namespace App\Http\Controllers\Api\Planilla;

use App\Models\EmpresaConfiguracionPlanilla;
use App\Services\Planilla\ConfiguracionPlanillaService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\Controller;
use App\Services\Planilla\PlanillaTemplatesService;

class ConfiguracionPlanillaController extends Controller
{
    protected $configuracionService;

    public function __construct(ConfiguracionPlanillaService $configuracionService)
    {
        $this->configuracionService = $configuracionService;
    }

    /**
     * Obtener configuración actual de la empresa
     */
    public function show(Request $request): JsonResponse
    {
        try {
            $empresaId = $request->user()->id_empresa;
            
            $configuracion = EmpresaConfiguracionPlanilla::obtenerConfiguracion($empresaId);
            
            if (!$configuracion) {
                // Crear configuración por defecto si no existe
                $configuracion = EmpresaConfiguracionPlanilla::obtenerOCrearConfiguracion($empresaId);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $configuracion->id,
                    'empresa_id' => $configuracion->empresa_id,
                    'cod_pais' => $configuracion->cod_pais,
                    'configuracion' => $configuracion->configuracion,
                    'activo' => $configuracion->activo,
                    'fecha_vigencia_desde' => $configuracion->fecha_vigencia_desde,
                    'fecha_vigencia_hasta' => $configuracion->fecha_vigencia_hasta,
                    'conceptos' => $configuracion->getConceptos(),
                    'configuraciones_generales' => $configuracion->getConfiguracionesGenerales(),
                    'deducciones' => $configuracion->getDeducciones(),
                    'ingresos' => $configuracion->getIngresos()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo configuración de planilla', [
                'error' => $e->getMessage(),
                'empresa_id' => $request->user()->id_empresa ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la configuración: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar configuración de la empresa
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'configuracion' => 'required|array',
            'configuracion.conceptos' => 'required|array',
            'cod_pais' => 'sometimes|string|max:3',
            'fecha_vigencia_desde' => 'sometimes|date'
        ]);

        try {
            DB::beginTransaction();

            $empresaId = $request->user()->id_empresa;
            $nuevaConfiguracion = $request->input('configuracion');

            // Validar estructura de conceptos
            $this->validarEstructuraConceptos($nuevaConfiguracion['conceptos'] ?? []);

            $configuracionActual = EmpresaConfiguracionPlanilla::obtenerConfiguracion($empresaId);

            if ($configuracionActual) {
                $configuracionActual->update([
                    'configuracion' => $nuevaConfiguracion,
                    'cod_pais' => $request->input('cod_pais', $configuracionActual->cod_pais),
                    'updated_at' => now()
                ]);
                
                $nuevaConfiguracionModel = $configuracionActual;
            } else {
                $nuevaConfiguracionModel = EmpresaConfiguracionPlanilla::create([
                    'empresa_id' => $empresaId,
                    'cod_pais' => $request->input('cod_pais', 'SV'),
                    'configuracion' => $nuevaConfiguracion,
                    'activo' => true,
                    'fecha_vigencia_desde' => $request->input('fecha_vigencia_desde', now()),
                    'fecha_vigencia_hasta' => null
                ]);
            }

            $validacion = $this->configuracionService->validarConfiguracion($empresaId);
            
            if (!$validacion['valida']) {
                throw new \Exception('Configuración inválida: ' . $validacion['mensaje']);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Configuración actualizada exitosamente',
                'data' => [
                    'id' => $nuevaConfiguracionModel->id,
                    'configuracion' => $nuevaConfiguracionModel->configuracion,
                    'fecha_vigencia_desde' => $nuevaConfiguracionModel->fecha_vigencia_desde
                ]
            ]);

        } catch (ValidationException $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error actualizando configuración de planilla', [
                'error' => $e->getMessage(),
                'empresa_id' => $request->user()->id_empresa ?? null,
                'configuracion' => $request->input('configuracion')
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la configuración: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener plantillas de configuración por país
     */

    public function obtenerPlantillas(Request $request): JsonResponse
    {
        try {
            $plantillas = [
                'SV' => [
                    'nombre' => 'El Salvador',
                    'configuracion' => PlanillaTemplatesService::getConfiguracionPorPais('SV')
                ],
                'GT' => [
                    'nombre' => 'Guatemala',
                    'configuracion' => PlanillaTemplatesService::getConfiguracionPorPais('GT')
                ],
                'HN' => [
                    'nombre' => 'Honduras',
                    'configuracion' => PlanillaTemplatesService::getConfiguracionPorPais('HN')
                ],
                'NI' => [
                    'nombre' => 'Nicaragua',
                    'configuracion' => PlanillaTemplatesService::getConfiguracionPorPais('NI')
                ],
                'CR' => [
                    'nombre' => 'Costa Rica',
                    'configuracion' => PlanillaTemplatesService::getConfiguracionPorPais('CR')
                ]
            ];
    
            return response()->json([
                'success' => true,
                'data' => $plantillas
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    

    /**
     * Obtener conceptos disponibles y sus tipos
     */
    public function obtenerTiposConceptos(): JsonResponse
    {
        try {
            $tipos = [
                'porcentaje' => [
                    'nombre' => 'Porcentaje',
                    'descripcion' => 'Se calcula como porcentaje de la base',
                    'campos_requeridos' => ['valor', 'base_calculo'],
                    'campos_opcionales' => ['tope_maximo']
                ],
                'monto_fijo' => [
                    'nombre' => 'Monto Fijo',
                    'descripcion' => 'Valor fijo sin importar el salario',
                    'campos_requeridos' => ['valor']
                ],
                'tabla_progresiva' => [
                    'nombre' => 'Tabla Progresiva',
                    'descripcion' => 'Calcula según tramos progresivos',
                    'campos_requeridos' => ['tabla'],
                    'estructura_tabla' => ['desde', 'hasta', 'porcentaje', 'cuota_fija']
                ],
                'sistema_existente' => [
                    'nombre' => 'Sistema Existente',
                    'descripcion' => 'Usa el sistema de cálculo ya implementado (ej: Renta de El Salvador)',
                    'campos_requeridos' => []
                ],
                'escala_antiguedad' => [
                    'nombre' => 'Escala por Antigüedad',
                    'descripcion' => 'Varía según años de servicio del empleado',
                    'campos_requeridos' => ['escala']
                ],
                'dias_fijos' => [
                    'nombre' => 'Días Fijos',
                    'descripcion' => 'Calcula basado en días específicos',
                    'campos_requeridos' => ['dias']
                ]
            ];

            $basesCalculo = [
                'salario_base' => 'Salario Base',
                'salario_devengado' => 'Salario Devengado',
                'salario_gravable' => 'Salario Gravable (después de seguridad social)',
                'salario_hora' => 'Valor por Hora',
                'total_ingresos' => 'Total de Ingresos'
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'tipos_conceptos' => $tipos,
                    'bases_calculo' => $basesCalculo
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener tipos de conceptos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Probar cálculo con configuración actual
     */
    public function probarCalculo(Request $request): JsonResponse
    {
        $request->validate([
            'salario_base' => 'required|numeric|min:0',
            'dias_laborados' => 'sometimes|integer|min:1|max:31',
            'tipo_planilla' => 'sometimes|in:mensual,quincenal,semanal',
            'tipo_contrato' => 'sometimes|integer'
        ]);

        try {
            $empresaId = $request->user()->id_empresa;
            
            $datosEmpleado = [
                'salario_base' => $request->input('salario_base'),
                'salario_devengado' => $request->input('salario_base'), // Simplificado para prueba
                'dias_laborados' => $request->input('dias_laborados', 30),
                'horas_extra' => $request->input('horas_extra', 0),
                'monto_horas_extra' => $request->input('monto_horas_extra', 0),
                'comisiones' => $request->input('comisiones', 0),
                'bonificaciones' => $request->input('bonificaciones', 0),
                'otros_ingresos' => $request->input('otros_ingresos', 0),
                'prestamos' => $request->input('prestamos', 0),
                'anticipos' => $request->input('anticipos', 0),
                'otros_descuentos' => $request->input('otros_descuentos', 0),
                'descuentos_judiciales' => $request->input('descuentos_judiciales', 0),
                'tipo_contrato' => $request->input('tipo_contrato')
            ];

            $tipoPlanilla = $request->input('tipo_planilla', 'mensual');

            $resultado = $this->configuracionService->calcularConceptos(
                $datosEmpleado, 
                $empresaId, 
                $tipoPlanilla
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'datos_entrada' => $datosEmpleado,
                    'tipo_planilla' => $tipoPlanilla,
                    'resultados' => $resultado
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error probando cálculo de planilla', [
                'error' => $e->getMessage(),
                'datos' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al probar el cálculo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener historial de configuraciones
     */
    public function historial(Request $request): JsonResponse
    {
        try {
            $empresaId = $request->user()->id_empresa;
            
            $configuraciones = EmpresaConfiguracionPlanilla::porEmpresa($empresaId)
                ->orderBy('fecha_vigencia_desde', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $configuraciones->map(function($config) {
                    return [
                        'id' => $config->id,
                        'cod_pais' => $config->cod_pais,
                        'activo' => $config->activo,
                        'fecha_vigencia_desde' => $config->fecha_vigencia_desde,
                        'fecha_vigencia_hasta' => $config->fecha_vigencia_hasta,
                        'total_conceptos' => count($config->getConceptos()),
                        'created_at' => $config->created_at
                    ];
                })
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener historial: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==========================================
    // MÉTODOS PRIVADOS - VALIDACIONES Y PLANTILLAS
    // ==========================================

    /**
     * Validar estructura de conceptos
     */
    private function validarEstructuraConceptos(array $conceptos): void
    {
        foreach ($conceptos as $codigo => $concepto) {
            if (!isset($concepto['nombre']) || !isset($concepto['tipo']) || !isset($concepto['es_deduccion'])) {
                throw new \Exception("El concepto '{$codigo}' debe tener nombre, tipo y es_deduccion");
            }

            $tiposValidos = ['porcentaje', 'monto_fijo', 'tabla_progresiva', 'sistema_existente', 'escala_antiguedad', 'dias_fijos'];
            if (!in_array($concepto['tipo'], $tiposValidos)) {
                throw new \Exception("El concepto '{$codigo}' tiene un tipo inválido: {$concepto['tipo']}");
            }
        }
    }
}