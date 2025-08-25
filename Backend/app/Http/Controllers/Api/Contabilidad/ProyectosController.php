<?php

namespace App\Http\Controllers\Api\Contabilidad;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Contabilidad\Proyecto;
use Illuminate\Support\Facades\Crypt;
use JWTAuth;

class ProyectosController extends Controller
{


    public function index(Request $request)
    {

        $proyectos = Proyecto::when($request->buscador, function ($query) use ($request) {
            $txt = $request->buscador;
            $query->where('nombre', 'like', "%$txt%")
                ->orWhereHas('cliente', function ($q) use ($txt) {
                    $q->where('nombre', 'like', "%$txt%")
                        ->orWhere('apellido', 'like', "%$txt%")
                        ->orWhere('nombre_empresa', 'like', "%$txt%");
                });
        })
            ->when($request->inicio, function ($query) use ($request) {
                return $query->where('fecha_inicio', '>=', $request->inicio);
            })
            ->when($request->fin, function ($query) use ($request) {
                return $query->where('fecha_fin', '<=', $request->fin);
            })
            ->when($request->id_cliente, function ($query) use ($request) {
                return $query->where('id_cliente', $request->id_cliente);
            })
            ->when($request->id_usuario, function ($query) use ($request) {
                return $query->where('id_usuario', $request->id_usuario);
            })
            ->when($request->estado, function ($query) use ($request) {
                return $query->where('estado', $request->estado);
            })
            ->when($request->estado !== null, function ($q) use ($request) {
                $q->where('enable', !!$request->estado);
            })
            ->with('cotizaciones', 'presupuesto')
            ->orderBy($request->orden, $request->direccion)
            ->orderBy('id', 'desc')
            ->paginate($request->paginate);

        return Response()->json($proyectos, 200);
    }

    public function list()
    {

        $proyectos = Proyecto::orderBy('nombre', 'asc')
            ->where('enable', true)
            ->get();

        return Response()->json($proyectos, 200);
    }

    public function read($id)
    {

        $proyecto = Proyecto::where('id', $id)->with('compras', 'ventas.abonos', 'cotizaciones', 'gastos', 'presupuestos')->firstOrFail();
        return Response()->json($proyecto, 200);
    }


    public function store(Request $request)
    {
        $request->validate([
            'nombre'        => 'required|max:255',
            'fecha_inicio'  => 'required|date',
            'fecha_fin'     => 'required|date',
            'estado'        => 'required|max:255',
            'id_cliente'   => 'required|numeric',
            'id_usuario'   => 'required|numeric',
            'id_sucursal'   => 'required|numeric',
            'id_empresa'   => 'required|numeric',
        ], [
            'id_cliente.required' => 'El cliente es requerido.'
        ]);

        if ($request->id)
            $proyecto = Proyecto::findOrFail($request->id);
        else
            $proyecto = new Proyecto;

        $proyecto->fill($request->all());
        $proyecto->save();

        return Response()->json($proyecto, 200);
    }

    public function delete($id)
    {

        $proyecto = Proyecto::findOrFail($id);
        $proyecto->delete();

        return Response()->json($proyecto, 201);
    }
}
