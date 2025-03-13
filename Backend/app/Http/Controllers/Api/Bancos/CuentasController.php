<?php

namespace App\Http\Controllers\Api\Bancos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Bancos\Cuenta;

class CuentasController extends Controller
{
    public function index(Request $request)
    {
        $cuentas = Cuenta::when($request->buscador, function ($query) use ($request) {
            return $query->where('nombre_banco', 'like', '%' . $request->buscador . '%')
                ->orWhere('numero', 'like', '%' . $request->buscador . '%');
        })
            ->orderBy($request->orden ?: 'id', $request->direccion ?: 'desc')
            ->paginate($request->paginate);

        return response()->json($cuentas, 200);
    }

    public function list()
    {
        $cuentas = Cuenta::orderBy('numero')->get();

        return response()->json($cuentas, 200);
    }

    public function read($id)
    {
        $cuenta = Cuenta::where('id', $id)->firstOrFail();

        return response()->json($cuenta, 200);
    }

    public function store(Request $request)
    {
        $request->validate([
            'numero' => 'required_unless:tipo,"Efectivo"',
            'nombre_banco' => 'required|max:255',
            'tipo' => 'required|max:255',
            'saldo' => 'required|numeric',
            'id_empresa' => 'required|numeric',
        ]);

        if ($request->id) {
            $cuenta = Cuenta::findOrFail($request->id);
        } else {
            $cuenta = new Cuenta;
        }

        $cuenta->fill($request->all());
        $cuenta->save();

        return response()->json($cuenta, 200);
    }

    public function delete($id)
    {
        $cuenta = Cuenta::findOrFail($id);
        $cuenta->delete();

        return response()->json($cuenta, 201);
    }
}
