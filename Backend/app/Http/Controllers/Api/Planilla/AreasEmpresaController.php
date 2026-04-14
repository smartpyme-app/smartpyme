<?php

namespace App\Http\Controllers\Api\Planilla;

use App\Constants\PlanillaConstants;
use App\Http\Controllers\Controller;
use App\Models\Compras\Gastos\AreaEmpresa;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\AreasEmpresaExport;
use App\Models\Planilla\DepartamentoEmpresa;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\Planilla\StoreAreaEmpresaRequest;
use App\Http\Requests\Planilla\CambiarEstadoMultipleAreasRequest;

class AreasEmpresaController extends Controller
{
    public $usuario;

    public function __construct()
    {
        // El middleware jwt.auth ya está aplicado en la ruta
        // Solo obtenemos el usuario en un middleware closure
        $this->middleware(function ($request, $next) {
            $this->usuario = auth()->user();
            return $next($request);
        });
    }
    
    public function index(Request $request)
    {
        $query = AreaEmpresa::with(['departamento.sucursal', 'departamento.empresa'])
            ->whereHas('departamento', function($q) {
                $q->where('id_empresa', $this->usuario->id_empresa);
            });

        // Filtro por buscador
        if ($request->has('buscador') && !empty($request->buscador)) {
            $query->where(function($q) use ($request) {
                $q->where('nombre', 'LIKE', "%{$request->buscador}%")
                  ->orWhere('descripcion', 'LIKE', "%{$request->buscador}%");
            });
        }

        // Filtro por estado/activo
        if ($request->filled('estado')) {
            $query->where('activo', $request->estado);
        }

        // Filtro por sucursal específica
        if ($request->has('id_sucursal') && !empty($request->id_sucursal)) {
            $query->whereHas('departamento', function($q) use ($request) {
                $q->where('id_sucursal', $request->id_sucursal);
            });
        }

        // Filtro por departamento específico
        if ($request->has('id_departamento') && !empty($request->id_departamento)) {
            $query->where('id_departamento', $request->id_departamento);
        }

        // Filtro por rango de fechas
        if ($request->has('inicio') && !empty($request->inicio)) {
            $query->whereDate('created_at', '>=', $request->inicio);
        }

        if ($request->has('fin') && !empty($request->fin)) {
            $query->whereDate('created_at', '<=', $request->fin);
        }

        // Ordenamiento
        $orden = $request->orden ?? 'created_at';
        $direccion = $request->direccion ?? 'desc';
        
        $columnasPermitidas = ['id', 'nombre', 'created_at', 'updated_at', 'activo'];
        if (in_array($orden, $columnasPermitidas)) {
            $query->orderBy($orden, $direccion);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return $query->paginate($request->get('paginate', 10));
    }

    public function store(StoreAreaEmpresaRequest $request)
    {

        // Verificar que el departamento pertenezca a la empresa del usuario
        $departamento = DepartamentoEmpresa::where('id', $request->id_departamento)
            ->where('id_empresa', $this->usuario->id_empresa)
            ->first();

        if (!$departamento) {
            return response()->json(['message' => 'Departamento no encontrado'], 404);
        }

        // Convertir activo a boolean
        $activo = $request->activo;
        if (is_string($activo)) {
            $activo = $activo === '1' || $activo === 'true';
        }

        $data = [
            'nombre' => $request->nombre,
            'descripcion' => $request->descripcion,
            'id_departamento' => $request->id_departamento,
            'activo' => $activo ?? true,
            'estado' => $request->estado ?? 1,
        ];

        $area = AreaEmpresa::updateOrCreate(
            ['id' => $request->id],
            $data
        );

        $area->load(['departamento.sucursal', 'departamento.empresa']);

        return response()->json($area);
    }

    public function show($id)
    {
        $area = AreaEmpresa::with(['departamento.sucursal', 'departamento.empresa'])
            ->whereHas('departamento', function($q) {
                $q->where('id_empresa', $this->usuario->id_empresa);
            })
            ->findOrFail($id);

        return response()->json($area);
    }

    public function destroy($id)
    {
        $area = AreaEmpresa::whereHas('departamento', function($q) {
                $q->where('id_empresa', $this->usuario->id_empresa);
            })
            ->findOrFail($id);

        $area->delete();

        return response()->json([
            'message' => 'Área eliminada exitosamente',
            'id' => $id
        ]);
    }

    public function list(Request $request)
    {
        $query = AreaEmpresa::whereHas('departamento', function($q) {
                $q->where('id_empresa', $this->usuario->id_empresa);
            })
            ->where('activo', true)
            ->where('estado', 1);

        // Filtrar por departamento si se especifica
        if ($request->has('id_departamento') && !empty($request->id_departamento)) {
            $query->where('id_departamento', $request->id_departamento);
        }

        // Filtrar por sucursal a través del departamento
        if ($request->has('id_sucursal') && !empty($request->id_sucursal)) {
            $query->whereHas('departamento', function($q) use ($request) {
                $q->where('id_sucursal', $request->id_sucursal);
            });
        }

        return $query->orderBy('nombre')
            ->with('departamento:id,nombre,id_sucursal')
            ->select('id', 'nombre', 'descripcion', 'id_departamento')
            ->get();
    }

    public function list_departamentos(Request $request)
    {
        $query = DepartamentoEmpresa::where('id_empresa', $this->usuario->id_empresa)
            ->where('activo', true);

        // Filtrar por sucursal si se especifica
        if ($request->has('id_sucursal') && !empty($request->id_sucursal)) {
            $query->where('id_sucursal', $request->id_sucursal);
        }
        
        return $query->orderBy('nombre')
            ->select('id', 'nombre', 'descripcion', 'id_sucursal')
            ->get();
    }

    public function exportar(Request $request)
    {
        $query = AreaEmpresa::with(['departamento.sucursal', 'departamento.empresa'])
            ->whereHas('departamento', function($q) {
                $q->where('id_empresa', $this->usuario->id_empresa);
            });

        if (!$this->usuario->role('Administrador')) {
            $query->whereHas('departamento', function($q) {
                $q->where('id_sucursal', $this->usuario->id_sucursal);
            });
        }

        if ($request->has('buscador') && !empty($request->buscador)) {
            $query->where(function($q) use ($request) {
                $q->where('nombre', 'LIKE', "%{$request->buscador}%")
                  ->orWhere('descripcion', 'LIKE', "%{$request->buscador}%");
            });
        }

        if ($request->filled('estado')) {
            $query->where('activo', $request->estado);
        }

        if ($request->has('id_departamento') && !empty($request->id_departamento)) {
            $query->where('id_departamento', $request->id_departamento);
        }

        if ($request->has('id_sucursal') && !empty($request->id_sucursal)) {
            $query->whereHas('departamento', function($q) use ($request) {
                $q->where('id_sucursal', $request->id_sucursal);
            });
        }

        if ($request->has('inicio') && !empty($request->inicio)) {
            $query->whereDate('created_at', '>=', $request->inicio);
        }

        if ($request->has('fin') && !empty($request->fin)) {
            $query->whereDate('created_at', '<=', $request->fin);
        }

        $orden = $request->orden ?? 'created_at';
        $direccion = $request->direccion ?? 'desc';
        
        $columnasPermitidas = ['id', 'nombre', 'created_at', 'updated_at', 'activo'];
        if (in_array($orden, $columnasPermitidas)) {
            $query->orderBy($orden, $direccion);
        }

        $areas = $query->get();

        return Excel::download(new AreasEmpresaExport($areas), 'areas-empresa.xlsx');
    }

    public function cambiarEstadoMultiple(CambiarEstadoMultipleAreasRequest $request)
    {

        $activo = $request->activo;
        if (is_string($activo)) {
            $activo = $activo === '1' || $activo === 'true';
        }

        $updated = AreaEmpresa::whereHas('departamento', function($q) {
                $q->where('id_empresa', $this->usuario->id_empresa);
            })
            ->whereIn('id', $request->ids)
            ->update(['activo' => $activo]);

        return response()->json([
            'message' => "Se actualizaron {$updated} áreas exitosamente",
            'updated_count' => $updated
        ]);
    }
}
