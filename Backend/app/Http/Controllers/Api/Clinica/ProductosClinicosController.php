<?php

namespace App\Http\Controllers\Api\Clinica;

use App\Http\Controllers\Controller;
use App\Services\Clinica\ProductoClinicoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ProductosClinicosController extends Controller
{
    public function __construct(private ProductoClinicoService $productosClinicos)
    {
    }

    public function index(Request $request)
    {
        $lista = $this->productosClinicos->buscarMedicamentos(
            (int) $request->user()->id_empresa,
            $request->input('q'),
            (int) $request->input('limit', 25),
        );

        return response()->json(['data' => $lista]);
    }

    public function show(Request $request, int $idProducto)
    {
        $perfil = $this->productosClinicos->obtenerPerfil(
            (int) $request->user()->id_empresa,
            $idProducto,
        );

        if ($perfil === null) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => $perfil]);
    }

    public function upsert(Request $request, int $idProducto)
    {
        try {
            $perfil = $this->productosClinicos->guardarPerfil(
                (int) $request->user()->id_empresa,
                $idProducto,
                $request->all(),
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('clinica.medicamentos: no se pudo guardar perfil');

            return response()->json(['message' => 'No se pudo guardar el perfil clínico del producto'], 500);
        }

        return response()->json(['data' => $perfil]);
    }
}
