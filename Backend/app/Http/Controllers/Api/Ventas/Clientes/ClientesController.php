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
        // Optimización: Remover withSum que es muy lento y usar lazy loading si es necesario
        $clientes = Cliente::select([
                'id', 'nombre', 'apellido', 'nombre_empresa', 'tipo', 'tipo_contribuyente',
                'nit', 'dui', 'ncr', 'giro', 'telefono', 'correo', 'direccion',
                'red_social', 'enable', 'fecha_cumpleanos', 'created_at', 'updated_at', 'id_empresa'
            ])
            ->with(['contactos' => function($query) {
                $query->select('id', 'id_cliente', 'nombre', 'telefono', 'correo', 'estado');
            }])
            ->where('id', '!=', 1)
            ->when($request->buscador, function ($query) use ($request) {
                $searchTerm = '%' . $request->buscador . '%';
                return $query->where(function($q) use ($searchTerm) {
                    $q->where('nombre', 'like', $searchTerm)
                      ->orWhere('apellido', 'like', $searchTerm)
                      ->orWhere('nombre_empresa', 'like', $searchTerm)
                      ->orWhere('nit', 'like', $searchTerm)
                      ->orWhere('giro', 'like', $searchTerm)
                      ->orWhere('telefono', 'like', $searchTerm)
                      ->orWhere('red_social', 'like', $searchTerm)
                      ->orWhere('ncr', 'like', $searchTerm)
                      ->orWhere('dui', 'like', $searchTerm);
                });
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

        // Si necesitas el total de ventas, puedes agregarlo como un endpoint separado
        // o calcularlo solo cuando sea necesario para clientes específicos

        return Response()->json($clientes, 200);
    }

    public function list()
    {
        $clientes = Cliente::select(['id', 'nombre', 'apellido', 'nombre_empresa', 'tipo'])
            ->where('enable', true)
            ->orderBy('nombre', 'asc')
            ->get();

        return Response()->json($clientes, 200);
    }

    public function searchClientes(Request $request)
    {
        $term = $request->get('q', ''); // Término de búsqueda
        $limit = $request->get('limit', 50); // Límite de resultados (default 50)

        if (strlen($term) < 2) {
            return response()->json([], 200);
        }

        $clientes = Cliente::select(['id', 'nombre', 'apellido', 'nombre_empresa', 'tipo', 'correo', 'telefono'])
            ->where('enable', true)
            ->where(function ($query) use ($term) {
                $query->where('nombre', 'LIKE', "%{$term}%")
                ->orWhere('nombre_empresa', 'LIKE', "%{$term}%")
                ->orWhere('correo', 'LIKE', "%{$term}%")
                ->orWhere('telefono', 'LIKE', "%{$term}%")
                ->orWhereRaw("CONCAT(nombre, ' ', apellido) LIKE ?", ["%{$term}%"]);
            })
            ->orderByRaw("
                CASE
                    WHEN nombre LIKE '{$term}%' THEN 1
                    WHEN nombre_empresa LIKE '{$term}%' THEN 2
                    WHEN CONCAT(nombre, ' ', apellido) LIKE '{$term}%' THEN 3
                    ELSE 4
                END
            ")
            ->orderBy('nombre', 'asc')
            ->limit($limit)
            ->get();

        return response()->json($clientes, 200);
    }

    public function search($txt)
    {
        $txtClean = str_replace('-', '', $txt);

        $clientes = Cliente::where(function ($query) use ($txt, $txtClean) {
                $query->where('nombre', 'like', '%' . $txt . '%')
                      ->orWhere('apellido', 'like', $txt . '%')
                      ->orWhere('nombre_empresa', 'like', $txt . '%')
                      ->orWhere('telefono', 'like', $txt . '%')
                      ->orWhere('empresa_telefono', 'like', $txt . '%')
                      ->orWhere('red_social', 'like', $txt . '%')
                      ->orWhere('etiquetas', 'like', $txt . '%')
                      ->orWhere('codigo_cliente', 'like', $txt . '%')
                      ->orWhereRaw('REPLACE(ncr, "-", "") like ?', [$txtClean . '%'])
                      ->orWhereRaw('REPLACE(nit, "-", "") like ?', [$txtClean . '%'])
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

    /**
     * Obtener el total de ventas de un cliente específico
     */
    public function totalVentas($id)
    {
        $totalVentas = Cliente::where('id', $id)
            ->withSum('ventas', 'total')
            ->first()
            ->ventas_sum_total ?? 0;

        return Response()->json(['total_ventas' => $totalVentas], 200);
    }

    public function saldoPendiente($id)
    {
        $ventasPendientes = \App\Models\Ventas\Venta::where('id_cliente', $id)
            ->where('estado', 'Pendiente')
            ->where(function ($q) {
                $q->where('cotizacion', 0)->orWhereNull('cotizacion');
            })
            ->withSum(['abonos' => fn ($q) => $q->where('estado', 'Confirmado')], 'total')
            ->withSum(['devoluciones' => fn ($q) => $q->where('enable', 1)], 'total')
            ->get();

        $saldoPendiente = $ventasPendientes->sum(function ($v) {
            $abonos = $v->abonos_sum_total ?? 0;
            $devoluciones = $v->devoluciones_sum_total ?? 0;
            return round($v->total - $abonos - $devoluciones, 2);
        });

        return response()->json(['saldo_pendiente' => $saldoPendiente], 200);
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

        if ($this->puedeEditarCreditoCliente() && !empty($request->habilita_credito)) {
            $rules['dias_credito'] = 'required|in:3,8,10,15,30,45,60';
        }

        $request->validate($rules, [
            'nombre.required_if' => 'El campo nombre es obligatorio.',
            'nombre_empresa.required_if' => 'El campo empresa es obligatorio.',
            'dias_credito.required' => 'Los días de crédito son obligatorios cuando el cliente tiene crédito habilitado.',
        ]);

        if ($request->id)
            $cliente = Cliente::findOrFail($request->id);
        else
            $cliente = new Cliente;

        $data = $request->except('contactos');

        // Solo Admin y Supervisores pueden modificar campos de crédito
        if (!$this->puedeEditarCreditoCliente()) {
            unset($data['habilita_credito'], $data['dias_credito'], $data['limite_credito']);
        }

        $cliente->fill($data);
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

        if ($this->puedeEditarCreditoCliente() && !empty($request->habilita_credito)) {
            $rules['dias_credito'] = 'required|in:3,8,10,15,30,45,60';
        }

        $request->validate($rules, [
            'nombre.required_if' => 'El campo nombre es obligatorio.',
            'nombre_empresa.required_if' => 'El campo empresa es obligatorio.',
            'id_empresa.required' => 'El campo empresa es obligatorio.',
            'id_empresa.exists' => 'La empresa seleccionada no es válida.',
            'dias_credito.required' => 'Los días de crédito son obligatorios cuando el cliente tiene crédito habilitado.',
        ]);

        $data = $request->except('contactos');

        if (!$this->puedeEditarCreditoCliente()) {
            unset($data['habilita_credito'], $data['dias_credito'], $data['limite_credito']);
        }

        $cliente->fill($data);
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
        // return $cliente;
        $reportes = app('dompdf.wrapper')->loadView('reportes.clientes.estado-cuenta', compact('cliente'))->setPaper('letter', 'landscape');
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
            'file' => 'required',
        ]);

        try {
            $import = new ClientesPersonas();
            Excel::import($import, $request->file);

            $errores = $import->getErrores();
            $clientesProcesados = $import->getClientesProcesados();

            if ($clientesProcesados > 0 && count($errores) > 0) {
                $mensajeExito = "✅ Se procesaron correctamente {$clientesProcesados} clientes.";
                $mensajeFalla = "❌ No se pudieron procesar " . count($errores) . " clientes debido a errores.";

                // Separar errores por tipo para mejor análisis
                $erroresDuiDuplicado = array_filter($errores, function($error) {
                    return strpos($error, 'Ya existe un cliente con el DUI') !== false;
                });
                $erroresFormato = array_filter($errores, function($error) {
                    return strpos($error, 'DUI con formato inválido') !== false;
                });

                return response()->json([
                    'message' => $mensajeExito . " " . $mensajeFalla,
                    'procesados' => $clientesProcesados,
                    'fallidos' => count($errores),
                    'resumen_errores' => [
                        'dui_duplicados' => count($erroresDuiDuplicado),
                        'formato_invalido' => count($erroresFormato)
                    ],
                    'errores' => $errores
                ], 200);
            } else if ($clientesProcesados > 0) {
                return response()->json([
                    'message' => "¡Importación completada con éxito! Se procesaron {$clientesProcesados} clientes correctamente.",
                    'procesados' => $clientesProcesados,
                    'fallidos' => 0
                ], 200);
            } else {
                return response()->json([
                    'error' => 'No se pudo procesar ningún cliente. ' . implode("\n", $errores)
                ], 400);
            }
        } catch (\Exception $e) {
            Log::error("Error en importación de clientes personas: " . $e->getMessage());
            return response()->json([
                'error' => 'Error al importar clientes: ' . $e->getMessage()
            ], 500);
        }
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

        $cliente->total_ventas_pagadas = $ventas->where('estado', 'Pagada')->sum('total');
        $cliente->total_ventas_pendientes = $ventas->where('estado', 'Pendiente')->sum('total');

        $cliente->total_balance = $cliente->total_ventas_pagadas - $cliente->total_ventas_pendientes;


        return Response()->json($cliente, 200);
    }

    /**
     * Solo Administrador, Supervisor y Supervisor Limitado pueden editar crédito del cliente.
     */
    private function puedeEditarCreditoCliente(): bool
    {
        if (!Auth::check()) {
            return false;
        }
        $tipo = Auth::user()->tipo ?? '';
        return in_array($tipo, ['Administrador', 'Supervisor', 'Supervisor Limitado'], true);
    }
}
