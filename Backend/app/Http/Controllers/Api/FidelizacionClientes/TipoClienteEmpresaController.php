<?php

namespace App\Http\Controllers\Api\FidelizacionClientes;

use App\Http\Controllers\Controller;
use App\Models\FidelizacionClientes\TipoClienteEmpresa;
use App\Models\FidelizacionClientes\TipoClienteBase;
use App\Services\FidelizacionCliente\LicenciaFidelizacionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TipoClienteEmpresaController extends Controller
{
    protected $licenciaService;

    public function __construct(LicenciaFidelizacionService $licenciaService)
    {
        $this->licenciaService = $licenciaService;
    }
    /**
     * Obtener todos los tipos de cliente de la empresa
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $empresa = $user->empresa;
            $empresaId = $user->id_empresa;
            
            // Verificar que el usuario tenga una empresa asociada
            if (!$empresa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario sin empresa asociada'
                ], 400);
            }

            // Obtener parámetros de paginación y filtros
            $perPage = (int) $request->input('paginate', 25);
            $page = (int) $request->input('page', 1);

            $query = TipoClienteEmpresa::with(['tipoBase'])
                ->porEmpresaConLicencia($empresaId);

            // Buscador: nombre personalizado, nombre/code del tipo base, nivel
            if ($request->filled('search')) {
                $term = $request->search;
                $query->where(function ($q) use ($term) {
                    $q->where('nombre_personalizado', 'like', "%{$term}%")
                        ->orWhereHas('tipoBase', function ($sub) use ($term) {
                            $sub->where('nombre', 'like', "%{$term}%")
                                ->orWhere('code', 'like', "%{$term}%");
                        });
                    if (is_numeric($term)) {
                        $q->orWhere('nivel', (int) $term);
                    }
                });
            }

            // Filtro por estado (Activo/Inactivo)
            if ($request->filled('estado')) {
                if ($request->estado === 'Activo') {
                    $query->where('activo', true);
                } elseif ($request->estado === 'Inactivo') {
                    $query->where('activo', false);
                }
            }

            // Filtro por tipo (Personalizado/Basado)
            if ($request->filled('tipo')) {
                if ($request->tipo === 'Personalizado') {
                    $query->whereNull('id_tipo_base');
                } elseif ($request->tipo === 'Basado') {
                    $query->whereNotNull('id_tipo_base');
                }
            }

            $query->orderBy('nivel')->orderBy('created_at');

            $tiposCliente = $query->paginate($perPage, ['*'], 'page', $page);

            // Mapear los datos de la colección
            $tiposCliente->getCollection()->transform(function ($tipo) {
                return [
                    'id' => $tipo->id,
                    'id_tipo_base' => $tipo->id_tipo_base,
                    'nivel' => $tipo->nivel,
                    'nombre_efectivo' => $tipo->nombre_efectivo,
                    'descripcion_efectiva' => $tipo->descripcion_efectiva,
                    'code_efectivo' => $tipo->code_efectivo,
                    'activo' => $tipo->activo,
                    'tipo_base' => $tipo->tipoBase ? [
                        'id' => $tipo->tipoBase->id,
                        'code' => $tipo->tipoBase->code,
                        'nombre' => $tipo->tipoBase->nombre,
                        'descripcion' => $tipo->tipoBase->descripcion,
                        'orden' => $tipo->tipoBase->orden,
                    ] : null,
                    'puntos_por_dolar' => $tipo->puntos_por_dolar,
                    'valor_punto' => $tipo->valor_punto,
                    'minimo_canje' => $tipo->minimo_canje,
                    'maximo_canje' => $tipo->maximo_canje,
                    'expiracion_meses' => $tipo->expiracion_meses,
                    'is_default' => $tipo->is_default,
                    'is_personalizado' => $tipo->isPersonalizado(),
                    'nivel_nombre' => $tipo->getNivelNombre(),
                    'configuracion_avanzada' => $tipo->configuracion_avanzada,
                    'created_at' => $tipo->created_at,
                    'updated_at' => $tipo->updated_at,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $tiposCliente
            ]);
        } catch (\Exception $e) {
            Log::error('Error obteniendo tipos de cliente empresa', [
                'error' => $e->getMessage(),
                'empresa_id' => $request->user()->id_empresa ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los tipos de cliente: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener tipos de cliente base disponibles
     */
    public function getTiposBase(): JsonResponse
    {
        try {
            $tiposBase = TipoClienteBase::activos()
                ->ordenados()
                ->get();

            return response()->json([
                'success' => true,
                'data' => $tiposBase->map(function ($tipo) {
                    return [
                        'id' => $tipo->id,
                        'code' => $tipo->code,
                        'nombre' => $tipo->nombre,
                        'descripcion' => $tipo->descripcion,
                        'orden' => $tipo->orden,
                    ];
                })
            ]);
        } catch (\Exception $e) {
            Log::error('Error obteniendo tipos de cliente base', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los tipos base: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear nuevo tipo de cliente empresa
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id_tipo_base' => 'nullable|exists:tipos_cliente_base,id',
                'nivel' => 'required|integer|min:1|max:3',
                'nombre_personalizado' => 'nullable|string|max:255',
                'puntos_por_dolar' => 'required|numeric|min:0',
                'minimo_canje' => 'required|integer|min:1',
                'maximo_canje' => 'required|integer|min:1',
                'expiracion_meses' => 'required|integer|min:1',
                'is_default' => 'boolean',
                'configuracion_avanzada' => 'nullable|array',
            ]);

            $user = $request->user();
            $empresa = $user->empresa;
            $empresaId = $user->id_empresa;
            
            // Verificar que el usuario tenga una empresa asociada
            if (!$empresa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario sin empresa asociada'
                ], 400);
            }
            
            // Si la empresa tiene licencia, usar la empresa padre para las configuraciones
            $empresaEfectiva = $this->licenciaService->getEmpresaEfectiva($empresa);
            $empresaEfectivaId = $empresaEfectiva->id;

            // Validar consistencia nivel/orden cuando se usa tipo base
            if ($request->id_tipo_base) {
                $tipoBase = TipoClienteBase::find($request->id_tipo_base);
                if (!$tipoBase) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Tipo de cliente base no encontrado'
                    ], 400);
                }
                if (!$tipoBase->activo) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No se puede usar un tipo de cliente base desactivado'
                    ], 400);
                }
                if ((int) $request->nivel !== (int) $tipoBase->orden) {
                    return response()->json([
                        'success' => false,
                        'message' => "El nivel debe coincidir con el tipo base seleccionado ({$tipoBase->nombre} = nivel {$tipoBase->orden})"
                    ], 400);
                }
            }

            // Un solo default por empresa: desmarcar los demás antes de crear
            if ($request->is_default) {
                $this->quitarDefaultDeOtrosTipos($empresaEfectivaId, null);
            }

            DB::beginTransaction();

            $configAvanzada = $request->configuracion_avanzada ?? [];
            $valorPunto = isset($configAvanzada['valor_punto'])
                ? (float) $configAvanzada['valor_punto']
                : 0.01;

            $tipoCliente = TipoClienteEmpresa::create([
                'id_empresa' => $empresaEfectivaId,
                'id_tipo_base' => $request->id_tipo_base,
                'nivel' => $request->nivel,
                'nombre_personalizado' => $request->nombre_personalizado,
                'activo' => true,
                'puntos_por_dolar' => $request->puntos_por_dolar,
                'valor_punto' => $valorPunto,
                'minimo_canje' => $request->minimo_canje,
                'maximo_canje' => $request->maximo_canje,
                'expiracion_meses' => $request->expiracion_meses,
                'is_default' => $request->is_default ?? false,
                'configuracion_avanzada' => $configAvanzada,
            ]);

            if ($request->is_default) {
                $this->sincronizarNivelClientesSinTipo($empresa, $request->nivel);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tipo de cliente creado exitosamente',
                'data' => $tipoCliente->load('tipoBase')
            ], 201);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creando tipo de cliente empresa', [
                'error' => $e->getMessage(),
                'empresa_id' => $request->user()->id_empresa ?? null,
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear el tipo de cliente: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar tipo de cliente empresa
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $request->validate([
                'id_tipo_base' => 'nullable|exists:tipos_cliente_base,id',
                'nivel' => 'required|integer|min:1|max:3',
                'nombre_personalizado' => 'nullable|string|max:255',
                'activo' => 'boolean',
                'puntos_por_dolar' => 'required|numeric|min:0',
                'minimo_canje' => 'required|integer|min:1',
                'maximo_canje' => 'required|integer|min:1',
                'expiracion_meses' => 'required|integer|min:1',
                'is_default' => 'boolean',
                'configuracion_avanzada' => 'nullable|array',
            ]);

            $empresaId = $request->user()->id_empresa;

            $tipoCliente = TipoClienteEmpresa::porEmpresaConLicencia($empresaId)
                ->findOrFail($id);

            // Validar consistencia nivel/orden cuando se usa tipo base
            if ($request->id_tipo_base) {
                $tipoBase = TipoClienteBase::find($request->id_tipo_base);
                if (!$tipoBase) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Tipo de cliente base no encontrado'
                    ], 400);
                }
                if (!$tipoBase->activo) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No se puede usar un tipo de cliente base desactivado'
                    ], 400);
                }
                if ((int) $request->nivel !== (int) $tipoBase->orden) {
                    return response()->json([
                        'success' => false,
                        'message' => "El nivel debe coincidir con el tipo base seleccionado ({$tipoBase->nombre} = nivel {$tipoBase->orden})"
                    ], 400);
                }
            }

            // Un solo default por empresa: desmarcar los demás antes de actualizar
            if ($request->is_default && !$tipoCliente->is_default) {
                $this->quitarDefaultDeOtrosTipos($tipoCliente->id_empresa, $id);
            }

            DB::beginTransaction();

            $idTipoBase = $request->filled('id_tipo_base') ? $request->id_tipo_base : $tipoCliente->id_tipo_base;

            $configAvanzada = $request->configuracion_avanzada ?? $tipoCliente->configuracion_avanzada ?? [];

            $payload = [
                'id_tipo_base' => $idTipoBase,
                'nivel' => $request->nivel,
                'nombre_personalizado' => $request->nombre_personalizado,
                'activo' => $request->activo ?? $tipoCliente->activo,
                'puntos_por_dolar' => $request->puntos_por_dolar,
                'minimo_canje' => $request->minimo_canje,
                'maximo_canje' => $request->maximo_canje,
                'expiracion_meses' => $request->expiracion_meses,
                'is_default' => $request->is_default ?? $tipoCliente->is_default,
                'configuracion_avanzada' => $configAvanzada,
            ];

            // Mantener columna `valor_punto` alineada con la UI y con ConsumoPuntosService
            if (array_key_exists('valor_punto', $configAvanzada)) {
                $payload['valor_punto'] = (float) $configAvanzada['valor_punto'];
            }

            $tipoCliente->update($payload);

            if (($request->is_default ?? $tipoCliente->is_default)) {
                $empresa = $request->user()->empresa;
                if ($empresa) {
                    $this->sincronizarNivelClientesSinTipo($empresa, $request->nivel ?? $tipoCliente->nivel);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tipo de cliente actualizado exitosamente',
                'data' => $tipoCliente->load('tipoBase')
            ]);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error actualizando tipo de cliente empresa', [
                'error' => $e->getMessage(),
                'empresa_id' => $request->user()->id_empresa ?? null,
                'tipo_id' => $id,
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el tipo de cliente: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cambiar estado activo/inactivo
     */
    public function toggleStatus(Request $request, $id): JsonResponse
    {
        try {
            $empresaId = $request->user()->id_empresa;

            $tipoCliente = TipoClienteEmpresa::porEmpresaConLicencia($empresaId)
                ->findOrFail($id);

            $tipoCliente->update(['activo' => !$tipoCliente->activo]);

            return response()->json([
                'success' => true,
                'message' => 'Estado actualizado exitosamente',
                'data' => [
                    'id' => $tipoCliente->id,
                    'activo' => $tipoCliente->activo
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error cambiando estado tipo de cliente', [
                'error' => $e->getMessage(),
                'empresa_id' => $request->user()->id_empresa ?? null,
                'tipo_id' => $id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar el estado: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Quita is_default de todos los tipos de la empresa excepto el indicado.
     * Un solo tipo por defecto por empresa (sin importar nivel).
     *
     * @param int $empresaId ID de la empresa efectiva
     * @param int|null $excluirTipoId ID del tipo a excluir (el que será el nuevo default)
     */
    private function quitarDefaultDeOtrosTipos(int $empresaId, ?int $excluirTipoId): void
    {
        $query = TipoClienteEmpresa::where('id_empresa', $empresaId)
            ->where('is_default', true);

        if ($excluirTipoId !== null) {
            $query->where('id', '!=', $excluirTipoId);
        }

        $query->update(['is_default' => false]);
    }

    /**
     * Sincroniza clientes.nivel para clientes sin tipo asignado cuando cambia el default.
     */
    private function sincronizarNivelClientesSinTipo($empresa, int $nivel): void
    {
        $empresasLicenciaIds = $this->licenciaService->getEmpresasLicenciaIds($empresa);
        if (empty($empresasLicenciaIds)) {
            return;
        }

        DB::table('clientes')
            ->whereNull('id_tipo_cliente')
            ->whereIn('id_empresa', $empresasLicenciaIds)
            ->update(['nivel' => $nivel]);
    }

    /**
     * Obtener información de licencia para fidelización
     */
    public function getInfoLicencia(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $empresa = $user->empresa;
            
            // Verificar que el usuario tenga una empresa asociada
            if (!$empresa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario sin empresa asociada'
                ], 400);
            }
            
            $infoLicencia = $this->licenciaService->getInfoLicencia($empresa);
            
            return response()->json([
                'success' => true,
                'data' => $infoLicencia
            ]);
        } catch (\Exception $e) {
            Log::error('Error obteniendo información de licencia', [
                'error' => $e->getMessage(),
                'empresa_id' => $request->user()->id_empresa ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener información de licencia: ' . $e->getMessage()
            ], 500);
        }
    }
}
