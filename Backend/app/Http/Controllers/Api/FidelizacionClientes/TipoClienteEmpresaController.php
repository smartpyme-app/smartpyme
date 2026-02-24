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

            // Obtener parámetros de paginación
            $perPage = (int) $request->input('paginate', 25);
            $page = (int) $request->input('page', 1);

            $query = TipoClienteEmpresa::with(['tipoBase'])
                ->porEmpresaConLicencia($empresaId)
                ->orderBy('nivel')
                ->orderBy('created_at');

            $tiposCliente = $query->paginate($perPage, ['*'], 'page', $page);

            // Mapear los datos de la colección
            $tiposCliente->getCollection()->transform(function ($tipo) {
                return [
                    'id' => $tipo->id,
                    'nivel' => $tipo->nivel,
                    'nombre_efectivo' => $tipo->nombre_efectivo,
                    'descripcion_efectiva' => $tipo->descripcion_efectiva,
                    'code_efectivo' => $tipo->code_efectivo,
                    'activo' => $tipo->activo,
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

            // Validar que no exista otro tipo con el mismo nivel como default
            if ($request->is_default) {
                $existingDefault = TipoClienteEmpresa::porEmpresa($empresaEfectivaId)
                    ->porNivel($request->nivel)
                    ->where('is_default', true)
                    ->first();

                if ($existingDefault) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Ya existe un tipo de cliente por defecto para el nivel ' . $request->nivel
                    ], 400);
                }
            }

            DB::beginTransaction();

            $tipoCliente = TipoClienteEmpresa::create([
                'id_empresa' => $empresaEfectivaId,
                'id_tipo_base' => $request->id_tipo_base,
                'nivel' => $request->nivel,
                'nombre_personalizado' => $request->nombre_personalizado,
                'activo' => true,
                'puntos_por_dolar' => $request->puntos_por_dolar,
                'minimo_canje' => $request->minimo_canje,
                'maximo_canje' => $request->maximo_canje,
                'expiracion_meses' => $request->expiracion_meses,
                'is_default' => $request->is_default ?? false,
                'configuracion_avanzada' => $request->configuracion_avanzada ?? [],
            ]);

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

            // Validar que no exista otro tipo con el mismo nivel como default
            if ($request->is_default && !$tipoCliente->is_default) {
                $existingDefault = TipoClienteEmpresa::porEmpresa($empresaId)
                    ->porNivel($request->nivel)
                    ->where('is_default', true)
                    ->where('id', '!=', $id)
                    ->first();

                if ($existingDefault) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Ya existe un tipo de cliente por defecto para el nivel ' . $request->nivel
                    ], 400);
                }
            }

            DB::beginTransaction();

            $tipoCliente->update([
                'id_tipo_base' => $request->id_tipo_base,
                'nivel' => $request->nivel,
                'nombre_personalizado' => $request->nombre_personalizado,
                'activo' => $request->activo ?? $tipoCliente->activo,
                'puntos_por_dolar' => $request->puntos_por_dolar,
                'minimo_canje' => $request->minimo_canje,
                'maximo_canje' => $request->maximo_canje,
                'expiracion_meses' => $request->expiracion_meses,
                'is_default' => $request->is_default ?? $tipoCliente->is_default,
                'configuracion_avanzada' => $request->configuracion_avanzada ?? $tipoCliente->configuracion_avanzada,
            ]);

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
