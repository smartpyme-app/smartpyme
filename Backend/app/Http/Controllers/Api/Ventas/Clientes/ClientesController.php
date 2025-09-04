<?php

namespace App\Http\Controllers\Api\Ventas\Clientes;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Ventas\Clientes\Cliente;
use App\Models\Ventas\Clientes\Anticipo;
use App\Models\Ventas\Venta;
use App\Models\Creditos\Credito;

use App\Imports\ClientesPersonas;
use App\Imports\ClientesEmpresas;
use App\Exports\ClientesPersonasExport;
use App\Exports\ClientesEmpresasExport;
use App\Exports\ClientesExtranjerosExport;
use App\Imports\ClientesExtranjeros;
use App\Models\Ventas\Clientes\ContactoCliente;
use Maatwebsite\Excel\Facades\Excel;
use Auth;
use Illuminate\Support\Facades\Log;
use PgSql\Lob;

class ClientesController extends Controller
{


    public function index(Request $request)
    {

        $clientes = Cliente::with('contactos')->where('id', '!=', 1)->withSum('ventas', 'total')
            ->when($request->buscador, function ($query) use ($request) {
                return $query->where('nombre', 'like', '%' . $request->buscador . '%')
                    ->orwhere('apellido', 'like',  '%' . $request->buscador . '%')
                    ->orwhere('nombre_empresa', 'like',  '%' . $request->buscador . '%')
                    ->orwhere('nit', 'like',  '%' . $request->buscador . '%')
                    ->orwhere('giro', 'like',  '%' . $request->buscador . '%')
                    ->orwhere('telefono', 'like',  '%' . $request->buscador . '%')
                    ->orwhere('red_social', 'like',  '%' . $request->buscador . '%')
                    ->orwhere('ncr', 'like',  '%' . $request->buscador . '%')
                    ->orwhere('correo', 'like',  '%' . $request->buscador . '%')
                    ->orwhere('dui', 'like',  '%' . $request->buscador . '%');
            })
            ->when($request->nombre, function ($q) use ($request) {
                $q->where('nombre', $request->nombre);
            })
            ->when($request->apellido, function ($q) use ($request) {
                $q->where('apellido', $request->apellido);
            })
            ->when($request->tipo, function ($q) use ($request) {
                $q->where('tipo', $request->tipo);
            })
            ->when($request->fecha_cumpleanos, function ($q) use ($request) {
                $q->where('fecha_cumpleanos', $request->fecha_cumpleanos);
            })
            ->when($request->tipo_contribuyente, function ($q) use ($request) {
                $q->where('tipo_contribuyente', $request->tipo_contribuyente);
            })
            ->when($request->estado !== null, function ($q) use ($request) {
                $q->where('enable', !!$request->estado);
            })
            ->orderBy($request->orden ? $request->orden : 'id', $request->direccion ? $request->direccion : 'desc')
            ->paginate($request->paginate);

        return Response()->json($clientes, 200);
    }

    public function list()
    {

        $clientes = Cliente::orderBy('nombre', 'asc')
            ->where('enable', true)
            ->get();

        return Response()->json($clientes, 200);
    }

    public function search($txt)
    {
        $txtClean = str_replace('-', '', $txt);

        $clientes = Cliente::where(function ($query) use ($txt, $txtClean) {
                $query->where('nombre', 'like', '%' . $txt . '%')
                      ->orWhere('apellido', 'like', $txt . '%')
                      ->orWhere('nombre_empresa', 'like', $txt . '%')
                      ->orWhere('nit', 'like', $txt . '%')
                      ->orWhere('dui', 'like', $txt . '%')
                      ->orWhere('telefono', 'like', $txt . '%')
                      ->orWhere('empresa_telefono', 'like', $txt . '%')
                      ->orWhere('red_social', 'like', $txt . '%')
                      ->orWhere('etiquetas', 'like', $txt . '%')
                      ->orWhere('codigo_cliente', 'like', $txt . '%')
                      ->orWhereRaw('REPLACE(ncr, "-", "") like ?', [$txtClean . '%'])
                      ->orWhereRaw('REPLACE(dui, "-", "") like ?', [$txtClean . '%'])
                      ->orWhereRaw("CONCAT(nombre, ' ', apellido) like ?", ['%' . $txt . '%']);
            })
            ->where('enable', true)
            ->orderBy('nombre', 'asc')
            ->take(10)
            ->get();

        return response()->json($clientes, 200);
    }

    public function read($id)
    {

        $cliente = Cliente::with('contactos')->findOrFail($id);

        return Response()->json($cliente, 200);
    }

    public function store(Request $request)
    {
        $rules = [
            'nombre'         => 'required_if:tipo,"Persona"',
            'apellido'       => 'required_if:tipo,"Persona"',
            'nombre_empresa' => 'required_if:tipo,"Empresa"',
            'id_empresa'     => 'required|numeric|exists:empresas,id',
        ];

        // Si es creación (no hay id), id_usuario es requerido
        if (!$request->id) {
            $rules['id_usuario'] = 'required|numeric';
        } else {
            // Si es edición (hay id), id_usuario debe existir y ser numérico si se envía
            $rules['id_usuario'] = 'sometimes';
        }

        $request->validate($rules, [
            'nombre.required_if' => 'El campo nombre es obligatorio.',
            'nombre_empresa.required_if' => 'El campo empresa es obligatorio.'
        ]);

        if ($request->id)
            $cliente = Cliente::findOrFail($request->id);
        else
            $cliente = new Cliente;

        $cliente->fill($request->except('contactos'));
        $cliente->save();


        if ($request->has('contactos') && is_array($request->contactos) && $request->tipo == 'Empresa') {
            if ($request->id) {
                ContactoCliente::where('id_cliente', $cliente->id)->delete();
            }

            foreach ($request->contactos as $contactoData) {
                ContactoCliente::create([
                    'id_cliente' => $cliente->id,
                    'nombre' => $contactoData['nombre'] ?? $contactoData['name'] ?? null,
                    'apellido' => $contactoData['apellido'] ?? $contactoData['lastname'] ?? null,
                    'correo' => $contactoData['correo'] ?? $contactoData['email'] ?? null,
                    'telefono' => $contactoData['telefono'] ?? null,
                    'cargo' => $contactoData['cargo'] ?? null,
                    'sexo' => $contactoData['sexo'] ?? null,
                    'red_social' => $contactoData['red_social'] ?? null,
                    'fecha_nacimiento' => $contactoData['fecha_nacimiento'] ?? null,
                    'nota' => $contactoData['nota'] ?? null
                ]);
            }
        }

        ///return Response()->json($cliente, 200);
        $cliente = Cliente::with('contactos')->findOrFail($cliente->id);
        return Response()->json($cliente, 200);
    }

    public function update(Request $request)
    {
        $cliente = Cliente::findOrFail($request->id);
        
        $rules = [
            'nombre'         => 'required_if:tipo,"Persona"',
            'apellido'       => 'required_if:tipo,"Persona"',
            'nombre_empresa' => 'required_if:tipo,"Empresa"',
            'id_empresa'     => 'required|numeric|exists:empresas,id',
        ];
        
        $request->validate($rules, [
            'nombre.required_if' => 'El campo nombre es obligatorio.',
            'nombre_empresa.required_if' => 'El campo empresa es obligatorio.',
            'id_empresa.required' => 'El campo empresa es obligatorio.',
            'id_empresa.exists' => 'La empresa seleccionada no es válida.',
        ]);
        
        $cliente->fill($request->except('contactos'));
        $cliente->save();
        
        if ($request->has('contactos') && is_array($request->contactos) && $request->tipo == 'Empresa') {
            ContactoCliente::where('id_cliente', $cliente->id)->delete();
            
            // Crear nuevos contactos
            foreach ($request->contactos as $contactoData) {
                // Validar que al menos tenga nombre o correo
                if (empty($contactoData['nombre']) && empty($contactoData['name']) && 
                    empty($contactoData['correo']) && empty($contactoData['email'])) {
                    continue; // Saltar contactos vacíos
                }
                
                ContactoCliente::create([
                    'id_cliente' => $cliente->id,
                    'nombre' => $contactoData['nombre'] ?? $contactoData['name'] ?? null,
                    'apellido' => $contactoData['apellido'] ?? $contactoData['lastname'] ?? null,
                    'correo' => $contactoData['correo'] ?? $contactoData['email'] ?? null,
                    'telefono' => $contactoData['telefono'] ?? null,
                    'cargo' => $contactoData['cargo'] ?? null,
                    'sexo' => $contactoData['sexo'] ?? null,
                    'red_social' => $contactoData['red_social'] ?? null,
                    'fecha_nacimiento' => $contactoData['fecha_nacimiento'] ?? null,
                    'nota' => $contactoData['nota'] ?? null
                ]);
            }
        }
        
        // Retornar el cliente actualizado con sus contactos
        $cliente = Cliente::with('contactos')->findOrFail($cliente->id);
        
        return Response()->json($cliente, 200);
    }

    //storeContacto

    public function storeContacto(Request $request)
    {
        //$contacto = ContactoCliente::create($request->all()); actualizar o crear
        $contacto = ContactoCliente::updateOrCreate(
            ['id' => $request->id],
            $request->all()
        );
        return Response()->json($contacto, 200);
    }

    public function deleteContacto($id)
    {
        $contacto = ContactoCliente::findOrFail($id);
        $contacto->delete();

        return Response()->json($contacto, 201);
    }

    public function delete($id)
    {
        $cliente = Cliente::findOrFail($id);
        $cliente->delete();

        return Response()->json($cliente, 201);
    }

    public function ventas($id)
    {

        $ventas = Venta::where('id_cliente', $id)
            ->where('estado', '!=', 'Anulada')
            ->orderBy('id', 'desc')
            ->paginate(10);
        return Response()->json($ventas, 200);
    }

    public function creditos($id)
    {

        $creditos = Credito::where('id_cliente', $id)
            ->orderBy('id', 'desc')
            ->paginate(10);
        return Response()->json($creditos, 200);
    }

    public function ventasFilter(Request $request)
    {

        if ($request->estado == 'Anulada') {
            $ventas = Venta::where('id_cliente', $request->id)
                ->when($request->estado, function ($query) use ($request) {
                    return $query->where('estado', $request->estado);
                })
                ->when($request->metodo_pago, function ($query) use ($request) {
                    return $query->where('metodo_pago', $request->metodo_pago);
                })
                ->orderBy('id', 'desc')->paginate(100000);
        } else {

            $ventas = Venta::where('id_cliente', $request->id)
                ->where('estado', '!=', 'Anulada')
                ->when($request->estado, function ($query) use ($request) {
                    return $query->where('estado', $request->estado);
                })
                ->when($request->metodo_pago, function ($query) use ($request) {
                    return $query->where('metodo_pago', $request->metodo_pago);
                })
                ->orderBy('id', 'desc')->paginate(100000);
        }

        return Response()->json($ventas, 200);
    }

    public function cxc()
    {

        $clientes = Cliente::where('id', '!=', 1)
            ->whereRaw('clientes.id in (select id_cliente from ventas where estado = ?)', ['Pendiente'])
            ->paginate(10);

        foreach ($clientes as $cliente) {
            $cliente->num_ventas_pendientes = $cliente->ventasPendientes->count();
            $cliente->pago_pendiente = $cliente->ventasPendientes->sum('total');
        }

        return Response()->json($clientes, 200);
    }

    public function cxcBuscar($txt)
    {

        $clientes = Cliente::where('id', '!=', 1)->where('nombre', 'like', '%' . $txt . '%')
            ->orWhere('registro', 'like', $txt . '%')
            ->orWhereRaw('REPLACE(registro, "-", "") like "' . $txt . '"')
            ->whereRaw('clientes.id in (select id_cliente from ventas where estado = ?)', ['Pendiente'])
            ->paginate(10);

        return Response()->json($clientes, 200);
    }

    public function estadoCuenta($id)
    {

        $cliente = Cliente::where('id', $id)->with('empresa')->firstOrFail();
        $cliente->ventas = $cliente->ventas()->where('estado', 'Pendiente')->get();
        $cliente->fletes = $cliente->fletes()->where('estado', 'Pendiente')->get();
        // return $cliente;
        $reportes = \PDF::loadView('reportes.clientes.estado-cuenta', compact('cliente'))->setPaper('letter', 'landscape');
        return $reportes->stream();
    }

    public function dash(Request $request)
    {

        $datos = new \stdClass();

        $datos->ventas   = \App\Models\Ventas\Venta::selectRaw('count(id) AS total, id_cliente, (select nombre from clientes where id_cliente = id) as nombre')
            ->groupBy('id_cliente')
            // ->when('sucursal', function($q) use($request){
            //     $q->where('id_sucursal', $request->id_sucursal);
            // })
            // ->when('sucursal', function($q) use($request){
            //     $q->where('id_sucursal', $request->id_sucursal);
            // })
            ->orderBy('total', 'desc')
            ->take(5)
            ->get();

        $datos->municipios   = Cliente::selectRaw('count(id) AS total, municipio')
            ->groupBy('municipio')
            // ->when('sucursal', function($q) use($request){
            //     $q->where('id_sucursal', $request->id_sucursal);
            // })
            // ->when('sucursal', function($q) use($request){
            //     $q->where('id_sucursal', $request->id_sucursal);
            // })
            ->orderBy('total', 'desc')
            ->take(5)
            ->get();


        return Response()->json($datos, 200);
    }

    public function importPersonas(Request $request)
    {

        $request->validate([
            'file'          => 'required',
        ]);

        $import = new ClientesPersonas();
        Excel::import($import, $request->file);

        return Response()->json($import->getRowCount(), 200);
    }

    public function importEmpresas(Request $request)
    {

        $request->validate([
            'file'          => 'required',
        ]);

        $import = new ClientesEmpresas();
        Excel::import($import, $request->file);

        return Response()->json($import->getRowCount(), 200);
    }

    public function importExtranjeros(Request $request)
    {
        $request->validate([
            'file' => 'required'
        ]);
        $import = new ClientesExtranjeros();

        Excel::import($import, $request->file);

        return response()->json($import->getRowCount(), 200);
    }

    public function exportPersonas(Request $request)
    {

        $clientes = new ClientesPersonasExport();
        $clientes->filter($request);

        return Excel::download($clientes, 'clientes-personas.xlsx');
    }

    public function exportEmpresas(Request $request)
    {

        $clientes = new ClientesEmpresasExport();
        $clientes->filter($request);

        return Excel::download($clientes, 'clientes-empresas.xlsx');
    }

    public function exportExtranjeros(Request $request)
    {

        $clientes = new ClientesExtranjerosExport();
        $clientes->filter($request);

        return Excel::download($clientes, 'clientes-extranjeros.xlsx');
    }


    public function datos(Request $request)
    {

        $cliente = Cliente::where('id', $request->id)->firstOrFail();

        $ventas = $cliente->ventas()->whereBetween('fecha', [$request->inicio, $request->fin])->get();
        $fletes = $cliente->fletes()->whereBetween('fecha', [$request->inicio, $request->fin])->get();

        $cliente->total_ventas_pagadas = $ventas->where('estado', 'Pagada')->sum('total');
        $cliente->total_ventas_pendientes = $ventas->where('estado', 'Pendiente')->sum('total');

        $cliente->total_fletes_pagados = $fletes->where('estado', 'Pagado')->sum('total');
        $cliente->total_fletes_pendientes = $fletes->where('estado', 'Pendiente')->sum('total');

        $cliente->total_balance = $cliente->total_ventas_pagadas - $cliente->total_ventas_pendientes - $cliente->total_fletes_pendientes;


        return Response()->json($cliente, 200);
    }
}
