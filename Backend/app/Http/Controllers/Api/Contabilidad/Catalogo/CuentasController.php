<?php

namespace App\Http\Controllers\Api\Contabilidad\Catalogo;

use App\Http\Controllers\Controller;
use App\Imports\CatalogoImport;
use Illuminate\Http\Request;
use App\Models\Contabilidad\Catalogo\Cuenta;
use App\Imports\Catalogo;
use Maatwebsite\Excel\Facades\Excel;

class CuentasController extends Controller
{


    public function index(Request $request) {
        $perPage = $request->get('paginate', 10); // Número de cuentas por página
        $page = $request->get('page', 1);

        // Obtener todas las cuentas y construir jerarquía completa
        $todasLasCuentas = Cuenta::orderBy('codigo')->get();
        $cuentasJerarquicas = $this->ordenarJerarquicamente($todasLasCuentas);

        // Paginar el resultado jerárquico final
        $total = count($cuentasJerarquicas);
        $offset = ($page - 1) * $perPage;
        $cuentasPaginadas = array_slice($cuentasJerarquicas, $offset, $perPage);

        // Preparar respuesta paginada
        $response = [
            'data' => $cuentasPaginadas,
            'current_page' => (int)$page,
            'per_page' => (int)$perPage,
            'total' => $total,
            'last_page' => ceil($total / $perPage),
            'from' => $offset + 1,
            'to' => $offset + count($cuentasPaginadas),
            'path' => request()->url()
        ];

        return response()->json($response, 200);
    }

    /**
     * Ordena las cuentas jerárquicamente en un array plano (padre seguido de sus hijos)
     */
    private function ordenarJerarquicamente($cuentas, $padreId = null, $nivel = 0)
    {
        $resultado = [];
        foreach ($cuentas as $cuenta) {
            if (
                ($padreId === null && ($cuenta->id_cuenta_padre === null || $cuenta->id_cuenta_padre == 0)) ||
                ($cuenta->id_cuenta_padre == $padreId && $padreId !== null)
            ) {
                $cuenta->nivel_visual = $nivel;
                $resultado[] = $cuenta;
                $hijos = $this->ordenarJerarquicamente($cuentas, $cuenta->id, $nivel + 1);
                foreach ($hijos as $hijo) {
                    $resultado[] = $hijo;
                }
            }
        }
        return $resultado;
    }

    public function list() {

        $cuentas = Cuenta::orderby('id')->get();
        return Response()->json($cuentas, 200);

    }

    public function read($id) {

        $cuenta = Cuenta::where('id', $id)->firstOrFail();
        return Response()->json($cuenta, 200);

    }

    public function store(Request $request)
    {
        $request->validate([
            'codigo'        => 'required|max:255',
            'nombre'        => 'required|max:255',
            'naturaleza'    => 'required|max:255',
            'id_cuenta_padre'   => 'required',
            'rubro'         => 'required|max:255',
            'nivel'         => 'required|numeric',
            'id_empresa'    => 'required|numeric',
        ]);

        if($request->id)
            $cuenta = Cuenta::findOrFail($request->id);
        else
            $cuenta = new Cuenta;

        $data = $request->all();

        // Buscar el id real de la cuenta padre si se envía un código en vez de un id
        if (!empty($data['id_cuenta_padre'])) {
            // Si el valor es numérico y existe como id, lo dejamos; si no, buscamos por código
            $cuentaPadre = Cuenta::where('id', $data['id_cuenta_padre'])
                ->where('id_empresa', $data['id_empresa'])
                ->first();

            if (!$cuentaPadre) {
                // Si no existe como id, intentamos buscarlo como código
                $cuentaPadre = Cuenta::where('codigo', $data['id_cuenta_padre'])
                    ->where('id_empresa', $data['id_empresa'])
                    ->first();
            }

            $data['id_cuenta_padre'] = $cuentaPadre ? $cuentaPadre->id : null;
        } else {
            $data['id_cuenta_padre'] = null;
        }

        $cuenta->fill($data);
        $cuenta->save();

        return Response()->json($cuenta, 200);

    }

    public function delete($id)
    {
        $cuenta = Cuenta::findOrFail($id);
        $cuenta->delete();

        return Response()->json($cuenta, 201);

    }

    public function importCuentas(Request $request){

        $request->validate([
            'file'          => 'required',
        ],[
            'file.required' => 'El documento es obligatorio.'
        ]);

        $import = new CatalogoImport();
        Excel::import($import, $request->file);

        return Response()->json($import->getRowCount(), 200);

    }
}
