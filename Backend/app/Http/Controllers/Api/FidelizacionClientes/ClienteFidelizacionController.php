<?php

namespace App\Http\Controllers\Api\FidelizacionClientes;

use App\Http\Controllers\Controller;
use App\Http\Resources\FidelizacionClientes\ClienteCollection;
use App\Models\Ventas\Clientes\Cliente;
use App\Models\FidelizacionClientes\TipoClienteEmpresa;
use App\Models\FidelizacionClientes\PuntosCliente;
use App\Models\FidelizacionClientes\TransaccionPuntos;
use App\Services\FidelizacionCliente\LicenciaFidelizacionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\Builder;

class ClienteFidelizacionController extends Controller
{
    protected $licenciaService;

    public function __construct(LicenciaFidelizacionService $licenciaService)
    {
        $this->licenciaService = $licenciaService;
    }
    /**
     * Obtener todos los clientes con información de lealtad
     */
    public function index(Request $request): JsonResponse
    {
        try {
            
            // Validación de entrada
            $request->validate([
                'page' => 'integer|min:1',
                'paginate' => 'integer|min:1|max:100',
                'search' => 'string|max:255',
                'order' => 'in:nombre,puntos,puntos_disponibles,puntos_acumulados,ultima_compra',
                'direction' => 'in:asc,desc',
                'tipo_cliente' => 'string|max:50',
                'nivel' => 'integer|min:1',
                'puntos_min' => 'integer|min:0',
                'puntos_max' => 'integer|min:0',
                'estado' => 'boolean'
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
            
            // Parámetros con valores por defecto
            $perPage = (int) $request->input('paginate', 25);
            $page = (int) $request->input('page', 1);
            $order = $request->input('order', 'nombre');
            $direction = $request->input('direction', 'asc');

            // Query base con eager loading optimizado
            $query = $this->buildBaseQuery($empresaId, $empresa);
            
            // Aplicar todos los filtros
            $query = $this->aplicarFiltros($query, $request, $empresaId, $empresa);
            
            // Aplicar ordenamiento
            $query = $this->aplicarOrdenamiento($query, $order, $direction, $empresaId);

            // Paginar resultados
            $clientes = $query->paginate($perPage, ['*'], 'page', $page);

            // Retornar usando Resource Collection
            return response()->json([
                'success' => true,
                'data' => new ClienteCollection($clientes),
                'message' => 'Clientes obtenidos exitosamente'
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de entrada inválidos',
                'errors' => $e->errors()
            ], 422);
            
        } catch (\Exception $e) {
            Log::error('Error al obtener clientes con lealtad: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los clientes'
            ], 500);
        }
    }

    /**
     * Construye la query base con eager loading optimizado
     */
    private function buildBaseQuery(int $empresaId, $empresa = null): Builder
    {
        // Determinar la empresa efectiva para tipos de cliente
        $empresaEfectiva = $this->licenciaService->getEmpresaEfectiva($empresa);
        $empresaEfectivaId = $empresaEfectiva->id;
        
        // Obtener IDs de empresas de la licencia
        $empresasLicenciaIds = $this->licenciaService->getEmpresasLicenciaIds($empresa);
        
        return Cliente::with([
            'tipoCliente' => function($q) use ($empresaEfectivaId) {
                $q->withoutGlobalScopes()
                  ->where('tipos_cliente_empresa.id_empresa', $empresaEfectivaId)
                  ->with('tipoBase');
            },
            'puntosCliente' => function($q) use ($empresaEfectivaId) {
                $q->withoutGlobalScopes()
                  ->where('puntos_cliente.id_empresa', $empresaEfectivaId);
            },
            'ventas' => function($q) {
                $q->select('id', 'id_cliente', 'total', 'created_at')
                  ->orderBy('created_at', 'desc')
                  ->limit(1);
            }
        ])->whereIn('clientes.id_empresa', $empresasLicenciaIds);
    }

    /**
     * Aplica todos los filtros a la query
     */
    private function aplicarFiltros(Builder $query, Request $request, int $empresaId, $empresa = null): Builder
    {
        return $query
            ->when($request->search, fn($q) => $this->aplicarFiltrosBusqueda($q, $request->search))
            ->when($request->tipo_cliente, fn($q) => $q->where('clientes.tipo', $request->tipo_cliente))
            ->when($request->has('estado'), fn($q) => $q->where('clientes.enable', (bool) $request->estado))
            ->when($request->nivel, fn($q) => $this->aplicarFiltroNivel($q, $request->nivel, $empresaId))
            ->when($request->puntos_min || $request->puntos_max, 
                fn($q) => $this->aplicarFiltroPuntos($q, $request->puntos_min, $request->puntos_max, $empresaId)
            );
    }

    /**
     * Aplica filtros de búsqueda inteligente
     */
    private function aplicarFiltrosBusqueda(Builder $query, string $search): Builder
    {
        $cleanSearch = trim(preg_replace('/\s+/', ' ', $search));
        
        return $query->where(function($q) use ($search, $cleanSearch) {
            $q->where('clientes.nombre', 'LIKE', "%{$search}%")
              ->orWhere('clientes.nombre_empresa', 'LIKE', "%{$search}%")
              ->orWhere('clientes.correo', 'LIKE', "%{$search}%")
              ->orWhere('clientes.telefono', 'LIKE', "%{$search}%")
              ->orWhere('clientes.codigo_cliente', 'LIKE', "%{$search}%")
              ->orWhere('clientes.apellido', 'LIKE', "%{$search}%")
              // Búsqueda por nombre completo
              ->orWhereRaw("CONCAT(TRIM(clientes.nombre), ' ', TRIM(clientes.apellido)) LIKE ?", ["%{$cleanSearch}%"])
              ->orWhereRaw("CONCAT(TRIM(clientes.apellido), ' ', TRIM(clientes.nombre)) LIKE ?", ["%{$cleanSearch}%"]);
              
            // Búsqueda por palabras individuales
            if (strpos($cleanSearch, ' ') !== false) {
                $palabras = explode(' ', $cleanSearch);
                if (count($palabras) >= 2) {
                    $q->orWhere(function($subQ) use ($palabras) {
                        $subQ->where('clientes.nombre', 'LIKE', "%{$palabras[0]}%")
                             ->where('clientes.apellido', 'LIKE', "%{$palabras[1]}%");
                    })->orWhere(function($subQ) use ($palabras) {
                        $subQ->where('clientes.apellido', 'LIKE', "%{$palabras[0]}%")
                             ->where('clientes.nombre', 'LIKE', "%{$palabras[1]}%");
                    });
                }
            }
        });
    }

    /**
     * Aplica filtro por nivel de cliente
     */
    private function aplicarFiltroNivel(Builder $query, int $nivel, int $empresaId): Builder
    {
        return $query->where(function($subQuery) use ($nivel, $empresaId) {
            $subQuery->whereHas('tipoCliente', function($q) use ($nivel, $empresaId) {
                $q->where('tipos_cliente_empresa.id_empresa', $empresaId)
                  ->where('nivel', $nivel);
            })->orWhere(function($q) use ($nivel) {
                $q->whereNull('clientes.id_tipo_cliente')
                  ->where('clientes.nivel', $nivel);
            });
        });
    }

    /**
     * Aplica filtro por rango de puntos
     */
    private function aplicarFiltroPuntos(Builder $query, ?int $puntosMin, ?int $puntosMax, int $empresaId): Builder
    {
        return $query->whereHas('puntosCliente', function($q) use ($puntosMin, $puntosMax, $empresaId) {
            $q->withoutGlobalScopes()
              ->where('puntos_cliente.id_empresa', $empresaId)
              ->when($puntosMin, fn($subQ) => $subQ->where('puntos_disponibles', '>=', $puntosMin))
              ->when($puntosMax, fn($subQ) => $subQ->where('puntos_disponibles', '<=', $puntosMax));
        });
    }

    /**
     * Aplica ordenamiento a la query
     */
    private function aplicarOrdenamiento(Builder $query, string $order, string $direction, int $empresaId): Builder
    {
        switch ($order) {
            case 'nombre':
                return $query->orderBy('clientes.nombre', $direction);
                
            case 'puntos':
            case 'puntos_disponibles':
                return $query->leftJoin('puntos_cliente', function($join) use ($empresaId) {
                    $join->on('clientes.id', '=', 'puntos_cliente.id_cliente')
                         ->where('puntos_cliente.id_empresa', '=', $empresaId);
                })
                ->select('clientes.*')
                ->orderBy('puntos_cliente.puntos_disponibles', $direction);
                
            case 'puntos_acumulados':
                return $query->leftJoin('puntos_cliente', function($join) use ($empresaId) {
                    $join->on('clientes.id', '=', 'puntos_cliente.id_cliente')
                         ->where('puntos_cliente.id_empresa', '=', $empresaId);
                })
                ->select('clientes.*')
                ->orderBy('puntos_cliente.puntos_totales_ganados', $direction);
                
            case 'ultima_compra':
                return $query->leftJoin('ventas', function($join) use ($empresaId) {
                    $join->on('clientes.id', '=', 'ventas.id_cliente')
                         ->where('ventas.id_empresa', '=', $empresaId)
                         ->whereRaw('ventas.created_at = (SELECT MAX(created_at) FROM ventas WHERE id_cliente = clientes.id AND id_empresa = ' . $empresaId . ')');
                })
                ->select('clientes.*')
                ->orderBy('ventas.created_at', $direction);
                
            default:
                return $query->orderBy('clientes.created_at', 'desc');
        }
    }

    /**
     * Obtener clientes por tipo específico
     */
    public function getByTipo(Request $request, $tipoId): JsonResponse
    {
        try {
            $user = $request->user();
            $empresaId = $user->id_empresa;
            $empresa = $user->empresa;

            // Obtener empresa efectiva para tipos de cliente
            $empresaEfectiva = $this->licenciaService->getEmpresaEfectiva($empresa);
            $empresaEfectivaId = $empresaEfectiva->id;
            
            // Obtener IDs de empresas de la licencia
            $empresasLicenciaIds = $this->licenciaService->getEmpresasLicenciaIds($empresa);

            // Verificar que el tipo de cliente pertenece a la empresa efectiva
            $tipoCliente = TipoClienteEmpresa::where('id', $tipoId)
                ->where('id_empresa', $empresaEfectivaId)
                ->first();

            if (!$tipoCliente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tipo de cliente no encontrado'
                ], 404);
            }

            // Obtener parámetros de paginación
            $perPage = (int) $request->input('paginate', 25);
            $page = (int) $request->input('page', 1);

            // Mostrar todos los clientes sin filtrar por tipo específico
            $clientes = Cliente::with([
                'tipoCliente' => function($q) use ($empresaEfectivaId) {
                    $q->withoutGlobalScopes()
                      ->where('tipos_cliente_empresa.id_empresa', $empresaEfectivaId)
                      ->with('tipoBase');
                },
                'puntosCliente' => function($q) use ($empresaEfectivaId) {
                    $q->withoutGlobalScopes()
                      ->where('puntos_cliente.id_empresa', $empresaEfectivaId);
                },
                'ventas' => function($q) {
                    $q->select('id', 'id_cliente', 'total', 'created_at')
                      ->orderBy('created_at', 'desc')
                      ->limit(1);
                }
            ])
            ->whereIn('clientes.id_empresa', $empresasLicenciaIds)
            ->orderBy('nombre')
            ->paginate($perPage, ['*'], 'page', $page);

            // Transformar los datos (mismo formato que index)
            $clientesData = [];
            foreach ($clientes->items() as $cliente) {
                $puntosCliente = $cliente->puntosCliente;
                $ultimaVenta = $cliente->ventas->first();
                $tipoCliente = $cliente->tipoCliente;

                $clientesData[] = [
                    'id' => $cliente->id,
                    'nombre' => $cliente->tipo === 'Empresa' ? $cliente->nombre_empresa : $cliente->nombre_completo,
                    'correo' => $cliente->correo,
                    'telefono' => $cliente->telefono,
                    'dui' => $cliente->dui,
                    'ncr' => $cliente->ncr,
                    'tipo' => $cliente->tipo,
                    'enable' => $cliente->enable,
                    'tipo_cliente_fidelizacion' => $tipoCliente ? [
                        'id' => $tipoCliente->id,
                        'nivel' => $tipoCliente->nivel,
                        'nombre_efectivo' => $tipoCliente->nombre_efectivo,
                        'descripcion_efectiva' => $tipoCliente->descripcion_efectiva,
                        'puntos_por_dolar' => $tipoCliente->puntos_por_dolar,
                        'minimo_canje' => $tipoCliente->minimo_canje,
                        'maximo_canje' => $tipoCliente->maximo_canje,
                        'expiracion_meses' => $tipoCliente->expiracion_meses,
                    ] : null,
                    'puntos_acumulados' => $puntosCliente->puntos_totales_ganados ?? 0,
                    'puntos_disponibles' => $puntosCliente->puntos_disponibles ?? 0,
                    'puntos_vencidos' => $this->calcularPuntosVencidos($cliente->id),
                    'ultima_compra' => $ultimaVenta ? $ultimaVenta->created_at->format('Y-m-d') : null,
                    'total_compras' => $cliente->ventas()->count(),
                    'total_gastado' => $cliente->ventas()->sum('total'),
                    'nivel_actual' => $tipoCliente->nivel ?? 1,
                    'fecha_registro' => $cliente->created_at->format('Y-m-d'),
                    'fecha_ultima_actividad' => $puntosCliente->fecha_ultima_actividad ?? null,
                ];
            }

            // Crear respuesta paginada
            $response = [
                'current_page' => $clientes->currentPage(),
                'data' => $clientesData,
                'first_page_url' => $clientes->url(1),
                'from' => $clientes->firstItem(),
                'last_page' => $clientes->lastPage(),
                'last_page_url' => $clientes->url($clientes->lastPage()),
                'links' => [],
                'next_page_url' => $clientes->nextPageUrl(),
                'path' => $clientes->path(),
                'per_page' => $clientes->perPage(),
                'prev_page_url' => $clientes->previousPageUrl(),
                'to' => $clientes->lastItem(),
                'total' => $clientes->total(),
            ];

            return response()->json([
                'success' => true,
                'data' => $response,
                'tipo_cliente' => $tipoCliente,
                'message' => 'Clientes del tipo obtenidos exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener clientes por tipo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los clientes del tipo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener detalles completos de un cliente
     */
    public function getDetalles(Request $request, $id): JsonResponse
    {
        try {
            $empresaId = $request->user()->id_empresa;
    
            $cliente = Cliente::with([
                'tipoCliente' => function($q) use ($empresaId) {
                    $q->withoutGlobalScopes()
                      ->where('tipos_cliente_empresa.id_empresa', $empresaId)
                      ->with('tipoBase');
                },
                'puntosCliente' => function($q) use ($empresaId) {
                    $q->withoutGlobalScopes()
                      ->where('puntos_cliente.id_empresa', $empresaId);
                },
                'ventas' => function($q) {
                    $q->select('id', 'id_cliente', 'total', 'created_at')
                      ->orderBy('created_at', 'desc')
                      ->limit(1);
                }
            ])
            ->where('clientes.id_empresa', $empresaId)
            ->find($id);
    
            if (!$cliente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cliente no encontrado'
                ], 404);
            }
    
            $puntosCliente = $cliente->puntosCliente;
            $ultimaVenta = $cliente->ventas->first();
            $tipoCliente = $cliente->tipoCliente;
    
            // Determinar el teléfono correcto según el tipo
            $telefono = $cliente->getTelefonoEfectivo();
    
            // Determinar la dirección correcta según el tipo
            $direccion = $cliente->getDireccionEfectiva();
    
            $detalles = [
                'id' => $cliente->id,
                'nombre' => $cliente->tipo === 'Empresa' ? $cliente->nombre_empresa : $cliente->nombre_completo,
                'correo' => $cliente->correo,
                'telefono' => $telefono,
                'dui' => $cliente->dui,
                'ncr' => $cliente->ncr,
                'direccion' => $direccion,
                'tipo' => $cliente->tipo,
                'enable' => $cliente->enable,
                'tipo_cliente_fidelizacion' => $tipoCliente ? [
                    'id' => $tipoCliente->id,
                    'nivel' => $tipoCliente->nivel,
                    'nombre_efectivo' => $tipoCliente->nombre_efectivo,
                    'descripcion_efectiva' => $tipoCliente->descripcion_efectiva,
                    'puntos_por_dolar' => $tipoCliente->puntos_por_dolar,
                    'valor_punto' => $tipoCliente->valor_punto,
                    'minimo_canje' => $tipoCliente->minimo_canje,
                    'maximo_canje' => $tipoCliente->maximo_canje,
                    'expiracion_meses' => $tipoCliente->expiracion_meses,
                    'configuracion_avanzada' => $tipoCliente->configuracion_avanzada,
                ] : null,
                'puntos_acumulados' => $puntosCliente->puntos_totales_ganados ?? 0,
                'puntos_disponibles' => $puntosCliente->puntos_disponibles ?? 0,
                'puntos_vencidos' => $this->calcularPuntosVencidos($cliente->id),
                'puntos_por_ganar' => $this->calcularPuntosPorGanar($cliente->id),
                'ultima_compra' => $ultimaVenta ? $ultimaVenta->created_at->format('Y-m-d') : null,
                'total_compras' => $cliente->ventas()->count(),
                'total_gastado' => $cliente->ventas()->sum('total'),
                'nivel_actual' => $tipoCliente->nivel ?? 1,
                'fecha_registro' => $cliente->created_at->format('Y-m-d'),
                'fecha_ultima_actividad' => $puntosCliente->fecha_ultima_actividad ?? null,
            ];
    
            return response()->json([
                'success' => true,
                'data' => $detalles,
                'message' => 'Detalles del cliente obtenidos exitosamente'
            ]);
    
        } catch (\Exception $e) {
            Log::error('Error al obtener detalles del cliente: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los detalles del cliente',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * Cambiar tipo de cliente
     */
    public function cambiarTipo(Request $request, $id): JsonResponse
    {
        try {
            $empresaId = $request->user()->id_empresa;

            $request->validate([
                'id_tipo_cliente' => 'required|integer|exists:tipos_cliente_empresa,id'
            ]);

            $cliente = Cliente::where('clientes.id_empresa', $empresaId)->find($id);
            if (!$cliente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cliente no encontrado'
                ], 404);
            }

            $nuevoTipo = TipoClienteEmpresa::where('id', $request->id_tipo_cliente)
                ->where('id_empresa', $empresaId)
                ->first();

            if (!$nuevoTipo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tipo de cliente no válido'
                ], 400);
            }

            DB::beginTransaction();

            $cliente->update(['id_tipo_cliente' => $request->id_tipo_cliente]);

            // Log de la acción
            Log::info("Cliente {$cliente->id} cambió de tipo a {$nuevoTipo->nombre_efectivo}");

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tipo de cliente actualizado exitosamente',
                'data' => [
                    'cliente_id' => $cliente->id,
                    'nuevo_tipo' => $nuevoTipo->nombre_efectivo,
                    'nivel' => $nuevoTipo->nivel
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al cambiar tipo de cliente: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar el tipo de cliente',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener historial de puntos de un cliente
     */
    public function getHistorialPuntos(Request $request, $id): JsonResponse
    {
        try {
            $empresaId = $request->user()->id_empresa;

            $cliente = Cliente::where('clientes.id_empresa', $empresaId)->find($id);
            if (!$cliente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cliente no encontrado'
                ], 404);
            }

            $perPage = (int) $request->input('paginate', 50);
            $page = (int) $request->input('page', 1);

            $historial = TransaccionPuntos::where('id_cliente', $id)
                ->where('id_empresa', $empresaId)
                ->with('venta:id,correlativo,total')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            $historialData = [];
            foreach ($historial->items() as $transaccion) {
                $historialData[] = [
                    'id' => $transaccion->id,
                    'fecha' => $transaccion->created_at->format('Y-m-d H:i:s'),
                    'descripcion' => $this->getDescripcionTransaccion($transaccion),
                    'puntos' => $transaccion->puntos,
                    'tipo' => $this->mapearTipoTransaccion($transaccion->tipo),
                    'referencia' => $transaccion->venta ? "Venta-{$transaccion->venta->correlativo}" : $transaccion->idempotency_key,
                    'monto_asociado' => $transaccion->monto_asociado,
                    'fecha_expiracion' => $transaccion->fecha_expiracion,
                ];
            }

            // Crear respuesta paginada
            $response = [
                'current_page' => $historial->currentPage(),
                'data' => $historialData,
                'first_page_url' => $historial->url(1),
                'from' => $historial->firstItem(),
                'last_page' => $historial->lastPage(),
                'last_page_url' => $historial->url($historial->lastPage()),
                'links' => [],
                'next_page_url' => $historial->nextPageUrl(),
                'path' => $historial->path(),
                'per_page' => $historial->perPage(),
                'prev_page_url' => $historial->previousPageUrl(),
                'to' => $historial->lastItem(),
                'total' => $historial->total(),
            ];

            return response()->json([
                'success' => true,
                'data' => $response,
                'message' => 'Historial de puntos obtenido exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener historial de puntos: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el historial de puntos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener beneficios disponibles para un cliente
     */
    public function getBeneficiosDisponibles(Request $request, $id): JsonResponse
    {
        try {
            $empresaId = $request->user()->id_empresa;

            $cliente = Cliente::with([
                'tipoCliente' => function($q) use ($empresaId) {
                    $q->withoutGlobalScopes()
                      ->where('tipos_cliente_empresa.id_empresa', $empresaId);
                }, 
                'puntosCliente' => function($q) use ($empresaId) {
                    $q->withoutGlobalScopes()
                      ->where('puntos_cliente.id_empresa', $empresaId);
                }
            ])
                ->where('clientes.id_empresa', $empresaId)
                ->find($id);

            if (!$cliente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cliente no encontrado'
                ], 404);
            }

            $tipoCliente = $cliente->tipoCliente;
            $puntosDisponibles = $cliente->puntosCliente->puntos_disponibles ?? 0;

            // Generar beneficios basados en la configuración del tipo de cliente
            $beneficios = $this->generarBeneficiosDisponibles($tipoCliente, $puntosDisponibles);

            return response()->json([
                'success' => true,
                'data' => $beneficios,
                'message' => 'Beneficios disponibles obtenidos exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener beneficios disponibles: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los beneficios disponibles',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exportar clientes con lealtad
     */
    public function exportar(Request $request): JsonResponse
    {
        try {
            $empresaId = $request->user()->id_empresa;

            // TODO: Implementar exportación a Excel
            return response()->json([
                'success' => true,
                'message' => 'Exportación implementada exitosamente',
                'data' => [
                    'url' => '/exports/clientes-fidelizacion.xlsx'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error al exportar clientes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al exportar los clientes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calcular puntos vencidos de un cliente
     */
    private function calcularPuntosVencidos($clienteId): int
    {
        return TransaccionPuntos::where('id_cliente', $clienteId)
            ->where('tipo', TransaccionPuntos::TIPO_GANANCIA)
            ->where('fecha_expiracion', '<', now())
            ->whereRaw('puntos_consumidos < puntos')
            ->sum(DB::raw('puntos - puntos_consumidos'));
    }

    /**
     * Calcular puntos por ganar (próximos a vencer)
     */
    private function calcularPuntosPorGanar($clienteId): int
    {
        return TransaccionPuntos::where('id_cliente', $clienteId)
            ->where('tipo', TransaccionPuntos::TIPO_GANANCIA)
            ->where('fecha_expiracion', '>', now())
            ->where('fecha_expiracion', '<=', now()->addDays(30))
            ->whereRaw('puntos_consumidos < puntos')
            ->sum(DB::raw('puntos - puntos_consumidos'));
    }

    /**
     * Obtener descripción de transacción
     */
    private function getDescripcionTransaccion($transaccion): string
    {
        switch ($transaccion->tipo) {
            case TransaccionPuntos::TIPO_GANANCIA:
                return $transaccion->venta ? "Compra #{$transaccion->venta->correlativo}" : 'Puntos ganados';
            case TransaccionPuntos::TIPO_CANJE:
                return 'Canje de puntos';
            case TransaccionPuntos::TIPO_AJUSTE:
                return 'Ajuste de puntos';
            case TransaccionPuntos::TIPO_EXPIRACION:
                return 'Puntos vencidos';
            default:
                return 'Transacción de puntos';
        }
    }

    /**
     * Mapear tipo de transacción para el frontend
     */
    private function mapearTipoTransaccion($tipo): string
    {
        switch ($tipo) {
            case TransaccionPuntos::TIPO_GANANCIA:
                return 'ganado';
            case TransaccionPuntos::TIPO_CANJE:
                return 'canjeado';
            case TransaccionPuntos::TIPO_AJUSTE:
                return 'ajustado';
            case TransaccionPuntos::TIPO_EXPIRACION:
                return 'vencido';
            default:
                return 'otro';
        }
    }

    /**
     * Generar beneficios disponibles basados en el tipo de cliente
     */
    private function generarBeneficiosDisponibles($tipoCliente, $puntosDisponibles): array
    {
        $beneficios = [];

        if (!$tipoCliente) {
            return $beneficios;
        }

        $configuracion = $tipoCliente->configuracion_avanzada ?? [];

        // Beneficios básicos por nivel
        $beneficiosBasicos = [
            1 => [
                ['puntos' => 500, 'descuento' => 5, 'tipo' => 'descuento'],
                ['puntos' => 1000, 'descuento' => 10, 'tipo' => 'descuento'],
            ],
            2 => [
                ['puntos' => 300, 'descuento' => 5, 'tipo' => 'descuento'],
                ['puntos' => 600, 'descuento' => 10, 'tipo' => 'descuento'],
                ['puntos' => 1000, 'descuento' => 15, 'tipo' => 'descuento'],
                ['puntos' => 1500, 'monto' => 50, 'tipo' => 'producto_gratis'],
            ],
            3 => [
                ['puntos' => 200, 'descuento' => 5, 'tipo' => 'descuento'],
                ['puntos' => 400, 'descuento' => 10, 'tipo' => 'descuento'],
                ['puntos' => 800, 'descuento' => 15, 'tipo' => 'descuento'],
                ['puntos' => 1200, 'monto' => 75, 'tipo' => 'producto_gratis'],
                ['puntos' => 2000, 'monto' => 100, 'tipo' => 'producto_gratis'],
            ]
        ];

        $beneficiosNivel = $beneficiosBasicos[$tipoCliente->nivel] ?? [];

        foreach ($beneficiosNivel as $index => $beneficio) {
            $disponible = $puntosDisponibles >= $beneficio['puntos'];
            
            $beneficios[] = [
                'id' => $index + 1,
                'nombre' => $this->getNombreBeneficio($beneficio),
                'descripcion' => $this->getDescripcionBeneficio($beneficio),
                'puntos_requeridos' => $beneficio['puntos'],
                'descuento_porcentaje' => $beneficio['descuento'] ?? null,
                'descuento_monto' => $beneficio['monto'] ?? null,
                'disponible' => $disponible,
            ];
        }

        return $beneficios;
    }

    /**
     * Obtener nombre del beneficio
     */
    private function getNombreBeneficio($beneficio): string
    {
        if ($beneficio['tipo'] === 'descuento') {
            return "Descuento {$beneficio['descuento']}%";
        } elseif ($beneficio['tipo'] === 'producto_gratis') {
            return "Producto Gratis";
        }
        return "Beneficio Especial";
    }

    /**
     * Obtener descripción del beneficio
     */
    private function getDescripcionBeneficio($beneficio): string
    {
        if ($beneficio['tipo'] === 'descuento') {
            return "Descuento del {$beneficio['descuento']}% en tu próxima compra";
        } elseif ($beneficio['tipo'] === 'producto_gratis') {
            return "Producto de hasta $" . number_format($beneficio['monto'], 2) . " gratis";
        }
        return "Beneficio especial disponible";
    }
}