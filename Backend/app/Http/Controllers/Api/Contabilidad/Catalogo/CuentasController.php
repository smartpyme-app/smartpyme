<?php

namespace App\Http\Controllers\Api\Contabilidad\Catalogo;

use App\Http\Controllers\Controller;
use App\Imports\CatalogoImport;
use Illuminate\Http\Request;
use App\Models\Contabilidad\Catalogo\Cuenta;
use App\Imports\Catalogo;
use App\Exports\CatalogoCuentasPlantillaExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Requests\Contabilidad\Catalogo\StoreCuentaRequest;
use App\Http\Requests\Contabilidad\Catalogo\ImportCuentasRequest;

class CuentasController extends Controller
{


    public function index(Request $request) {
        $perPage = max(1, min(500, (int) $request->get('paginate', 10)));
        $page = max(1, (int) $request->get('page', 1));
        $buscador = trim((string) $request->get('buscador', ''));

        $todasLasCuentas = Cuenta::orderBy('codigo')->get();

        if ($buscador !== '') {
            $todasLasCuentas = $this->filtrarCuentasPorBuscador($todasLasCuentas, $buscador);
        }

        $cuentasJerarquicas = $this->ordenarJerarquicamente($todasLasCuentas);

        $total = count($cuentasJerarquicas);
        $offset = ($page - 1) * $perPage;
        $cuentasPaginadas = array_slice($cuentasJerarquicas, $offset, $perPage);

        $lastPage = $total > 0 ? max(1, (int) ceil($total / $perPage)) : 1;
        $from = $total > 0 ? $offset + 1 : 0;
        $to = $total > 0 ? $offset + count($cuentasPaginadas) : 0;

        $response = [
            'data' => $cuentasPaginadas,
            'current_page' => $total > 0 ? $page : 1,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => $lastPage,
            'from' => $from,
            'to' => $to,
            'path' => request()->url()
        ];

        return response()->json($response, 200);
    }

    /**
     * Reduce el conjunto a cuentas que coinciden con el texto y sus ancestros (árbol legible).
     *
     * @param \Illuminate\Support\Collection<int, Cuenta> $todasLasCuentas
     * @return \Illuminate\Support\Collection<int, Cuenta>
     */
    private function filtrarCuentasPorBuscador($todasLasCuentas, string $buscador)
    {
        $buscadorNorm = mb_strtolower($buscador, 'UTF-8');
        $byId = $todasLasCuentas->keyBy('id');
        $includeIds = [];

        foreach ($todasLasCuentas as $cuenta) {
            if (!$this->cuentaCoincideBuscador($cuenta, $buscadorNorm)) {
                continue;
            }
            $actual = $cuenta;
            while ($actual) {
                $includeIds[$actual->id] = true;
                $pid = $actual->id_cuenta_padre;
                $actual = ($pid && isset($byId[$pid])) ? $byId[$pid] : null;
            }
        }

        return $todasLasCuentas->filter(function ($c) use ($includeIds) {
            return isset($includeIds[$c->id]);
        })->values();
    }

    private function cuentaCoincideBuscador(Cuenta $cuenta, string $buscadorNorm): bool
    {
        $campos = [
            (string) $cuenta->codigo,
            (string) $cuenta->nombre,
            (string) $cuenta->rubro,
            (string) $cuenta->naturaleza,
        ];
        foreach ($campos as $valor) {
            if ($valor !== '' && mb_strpos(mb_strtolower($valor, 'UTF-8'), $buscadorNorm, 0, 'UTF-8') !== false) {
                return true;
            }
        }

        return false;
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

    public function store(StoreCuentaRequest $request)
    {
        $empresa_id = $request->id_empresa ?? auth()->user()->id_empresa;

        if($request->id)
            $cuenta = Cuenta::findOrFail($request->id);
        else
            $cuenta = new Cuenta;

        $data = $request->all();

        // ✅ SEGURO: Buscar el id real de la cuenta padre SOLO en la empresa actual
        if (!empty($data['id_cuenta_padre'])) {
            // Si el valor es numérico y existe como id, lo dejamos; si no, buscamos por código
            $cuentaPadre = Cuenta::where('id', $data['id_cuenta_padre'])
                ->where('id_empresa', $empresa_id) // ✅ FILTRO CRÍTICO
                ->first();

            if (!$cuentaPadre) {
                // Si no existe como id, intentamos buscarlo como código
                $cuentaPadre = Cuenta::where('codigo', $data['id_cuenta_padre'])
                    ->where('id_empresa', $empresa_id) // ✅ FILTRO CRÍTICO
                    ->first();
            }

            if (!$cuentaPadre) {
                return response()->json([
                    'success' => false,
                    'message' => 'La cuenta padre especificada no existe en esta empresa'
                ], 422);
            }

            $data['id_cuenta_padre'] = $cuentaPadre->id;
        } else {
            $data['id_cuenta_padre'] = null;
        }

        // ✅ SEGURO: Asegurar que siempre se asigne la empresa correcta
        $data['id_empresa'] = $empresa_id;

        $cuenta->fill($data);
        $cuenta->save();

        return Response()->json([
            'success' => true,
            'message' => $request->id ? 'Cuenta actualizada exitosamente' : 'Cuenta creada exitosamente',
            'data' => $cuenta
        ], 200);
    }

    public function delete($id)
    {
        $cuenta = Cuenta::findOrFail($id);
        $cuenta->delete();

        return Response()->json($cuenta, 201);

    }

    public function importCuentas(ImportCuentasRequest $request)
    {

        try {
            $import = new CatalogoImport();
            Excel::import($import, $request->file);

            return response()->json([
                'success' => true,
                'message' => 'Catálogo importado exitosamente',
                'filas_procesadas' => $import->getRowCount(),
                'empresa_id' => auth()->user()->id_empresa
            ], 200);

        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            // Errores de validación de Laravel Excel
            $failures = $e->failures();
            $errores_detallados = [];

            foreach ($failures as $failure) {
                $errores_detallados[] = [
                    'fila' => $failure->row(),
                    'columna' => $failure->attribute(),
                    'errores' => $failure->errors(),
                    'valores' => $failure->values()
                ];
            }

            return response()->json([
                'success' => false,
                'message' => 'Errores de validación en el archivo',
                'errores' => $errores_detallados
            ], 422);

        } catch (\Exception $e) {
            // Otros errores (duplicados, cuenta padre no encontrada, etc.)
            return response()->json([
                'success' => false,
                'message' => 'Error al importar catálogo',
                'error' => $e->getMessage(),
                'errores_adicionales' => method_exists($import, 'getErrores') ? $import->getErrores() : []
            ], 400);
        }
    }

    public function downloadPlantilla()
    {
        $export = new CatalogoCuentasPlantillaExport();
        // Generar plantilla vacía con solo los encabezados
        return Excel::download($export, 'plantilla_catalogo-cuentas.xlsx');
    }
}
