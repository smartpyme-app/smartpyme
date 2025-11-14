<?php

namespace App\Http\Controllers\Api\Contabilidad\Catalogo;

use App\Http\Controllers\Controller;
use App\Imports\CatalogoImport;
use Illuminate\Http\Request;
use App\Models\Contabilidad\Catalogo\Cuenta;
use App\Imports\Catalogo;
use App\Exports\CatalogoCuentasPlantillaExport;
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
        $empresa_id = $request->id_empresa ?? auth()->user()->id_empresa;

        // ✅ VALIDACIÓN MEJORADA: Incluir unicidad por empresa
        $rules = [
            'codigo' => [
                'required',
                'max:50',
                'unique:catalogo_cuentas,codigo,' . ($request->id ?? 'NULL') . ',id,id_empresa,' . $empresa_id
            ],
            'nombre' => 'required|max:255',
            'naturaleza' => 'required|max:255|in:Deudor,Acreedor',
            'id_cuenta_padre' => 'nullable',
            'rubro' => 'required|max:255',
            'nivel' => 'required|numeric|min:0|max:10',
            'id_empresa' => 'required|numeric',
        ];

        $messages = [
            'codigo.required' => 'El código es obligatorio',
            'codigo.unique' => 'Ya existe una cuenta con este código en la empresa',
            'codigo.max' => 'El código no puede exceder 50 caracteres',
            'nombre.required' => 'El nombre es obligatorio',
            'naturaleza.required' => 'La naturaleza es obligatoria',
            'naturaleza.in' => 'La naturaleza debe ser Deudor o Acreedor',
            'rubro.required' => 'El rubro es obligatorio',
            'nivel.required' => 'El nivel es obligatorio',
            'nivel.numeric' => 'El nivel debe ser numérico',
            'nivel.min' => 'El nivel no puede ser menor a 0',
            'nivel.max' => 'El nivel no puede exceder 10',
            'id_empresa.required' => 'La empresa es obligatoria',
        ];

        $request->validate($rules, $messages);

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

    public function importCuentas(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240', // Máximo 10MB
        ], [
            'file.required' => 'El archivo es obligatorio.',
            'file.file' => 'Debe ser un archivo válido.',
            'file.mimes' => 'El archivo debe ser Excel (.xlsx, .xls) o CSV.',
            'file.max' => 'El archivo no puede exceder 10MB.'
        ]);

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
