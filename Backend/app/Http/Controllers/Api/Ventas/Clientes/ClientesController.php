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
use App\Exports\ClientesPersonasPlantillaExport;
use App\Exports\ClientesEmpresasPlantillaExport;
use App\Exports\ClientesExtranjerosPlantillaExport;
use App\Imports\ClientesExtranjeros;
use App\Models\Ventas\Clientes\ContactoCliente;
use Maatwebsite\Excel\Facades\Excel;
use Auth;
use Illuminate\Support\Facades\Log;
use PgSql\Lob;
use App\Http\Requests\Ventas\Clientes\StoreClienteRequest;
use App\Http\Requests\Ventas\Clientes\UpdateClienteRequest;
use App\Http\Requests\Ventas\Clientes\ImportClientesRequest;
use App\Services\Ventas\ClienteService;

class ClientesController extends Controller
{
    protected $clienteService;

    public function __construct(ClienteService $clienteService)
    {
        $this->clienteService = $clienteService;
    }

    public function index(Request $request)
    {
        $clientes = $this->clienteService->listarClientes($request);
        return Response()->json($clientes, 200);
    }

    public function list()
    {
        $clientes = $this->clienteService->listarClientesActivos();
        return Response()->json($clientes, 200);
    }

    public function searchClientes(Request $request)
    {
        $term = $request->get('q', '');
        $limit = $request->get('limit', 50);
        
        $clientes = $this->clienteService->buscarClientes($term, $limit);
        return response()->json($clientes, 200);
    }

    public function search($txt)
    {
        $clientes = $this->clienteService->buscarClientesPorTexto($txt);
        return response()->json($clientes, 200);
    }

    public function read($id)
    {

        $cliente = Cliente::with('contactos')->findOrFail($id);

        return Response()->json($cliente, 200);
    }

    public function store(StoreClienteRequest $request)
    {
        $cliente = $this->clienteService->crearOActualizarCliente($request->all());
        return Response()->json($cliente, 200);
    }

    public function update(UpdateClienteRequest $request)
    {
        $cliente = $this->clienteService->crearOActualizarCliente($request->all());
        return Response()->json($cliente, 200);
    }

    public function storeContacto(Request $request)
    {
        $contacto = $this->clienteService->crearOActualizarContacto($request->all());
        return Response()->json($contacto, 200);
    }

    public function deleteContacto($id)
    {
        $contacto = $this->clienteService->eliminarContacto($id);
        return Response()->json($contacto, 201);
    }

    public function delete($id)
    {
        try {
            $cliente = $this->clienteService->eliminarCliente($id);
            return Response()->json($cliente, 201);
        } catch (\Exception $e) {
            return Response()->json([
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function ventas($id)
    {
        $ventas = $this->clienteService->obtenerVentasCliente($id);
        return Response()->json($ventas, 200);
    }

    public function creditos($id)
    {
        $creditos = $this->clienteService->obtenerCreditosCliente($id);
        return Response()->json($creditos, 200);
    }

    public function ventasFilter(Request $request)
    {
        $filtros = [
            'estado' => $request->estado,
            'metodo_pago' => $request->metodo_pago,
            'paginate' => $request->paginate ?? 100000
        ];
        
        $ventas = $this->clienteService->obtenerVentasCliente($request->id, $filtros);
        return Response()->json($ventas, 200);
    }

    public function cxc()
    {
        $clientes = $this->clienteService->obtenerClientesConCxC();
        return Response()->json($clientes, 200);
    }

    public function cxcBuscar($txt)
    {
        $clientes = $this->clienteService->buscarClientesConCxC($txt);
        return Response()->json($clientes, 200);
    }

    public function estadoCuenta($id)
    {

        $cliente = Cliente::where('id', $id)->with('empresa')->firstOrFail();
        $cliente->ventas = $cliente->ventas()->where('estado', 'Pendiente')->get();
        // return $cliente;
        $reportes = \PDF::loadView('reportes.clientes.estado-cuenta', compact('cliente'))->setPaper('letter', 'landscape');
        return $reportes->stream();
    }

    public function dash(Request $request)
    {
        $datos = $this->clienteService->obtenerDatosDashboard();
        return Response()->json($datos, 200);
    }

    public function importPersonas(ImportClientesRequest $request)
    {

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

    public function importEmpresas(ImportClientesRequest $request)
    {

        $import = new ClientesEmpresas();
        Excel::import($import, $request->file);

        return Response()->json($import->getRowCount(), 200);
    }

    public function importExtranjeros(ImportClientesRequest $request)
    {
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

    public function downloadPlantillaPersonas()
    {
        $export = new ClientesPersonasPlantillaExport();
        // Generar plantilla vacía con solo los encabezados
        return Excel::download($export, 'plantilla_clientes-personas.xlsx');
    }

    public function downloadPlantillaEmpresas()
    {
        $export = new ClientesEmpresasPlantillaExport();
        // Generar plantilla vacía con solo los encabezados
        return Excel::download($export, 'plantilla_clientes-empresas.xlsx');
    }

    public function downloadPlantillaExtranjeros()
    {
        $export = new ClientesExtranjerosPlantillaExport();
        // Generar plantilla vacía con solo los encabezados
        return Excel::download($export, 'plantilla_clientes-extranjeros.xlsx');
    }


    public function datos(Request $request)
    {
        $datos = $this->clienteService->obtenerDatosCliente(
            $request->id,
            $request->inicio,
            $request->fin
        );

        $cliente = $datos['cliente'];
        $cliente->total_ventas_pagadas = $datos['total_ventas_pagadas'];
        $cliente->total_ventas_pendientes = $datos['total_ventas_pendientes'];
        $cliente->total_balance = $datos['total_balance'];

        return Response()->json($cliente, 200);
    }
}
