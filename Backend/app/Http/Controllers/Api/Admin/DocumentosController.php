<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Admin\Documento;
use Illuminate\Support\Facades\Log;

class DocumentosController extends Controller
{


    public function index()
    {

        $documentos = Documento::orderBy('id_sucursal', 'asc')->get();

        return Response()->json($documentos, 200);
    }
    //historial
    public function historial(Request $request)
    {

        $documentos = Documento::where('nombre', $request->nombre)
            ->where('activo', false)

            ->orderBy('id_sucursal', 'asc')
            ->paginate($request->paginate);


        return Response()->json($documentos, 200);
    }

    public function list()
    {

        $documentos = Documento::where('activo', true)->get();

        return Response()->json($documentos, 200);
    }


    public function read($id)
    {

        $documento = Documento::findOrFail($id);
        return Response()->json($documento, 200);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre'        => 'required|max:255',
            'correlativo'   => 'required|max:255',
            'rangos'        => 'sometimes|max:255',
            'numero_autorizacion' => 'sometimes|max:255',
            'resolucion'    => 'sometimes|max:255',
            'nota'          => 'sometimes|max:500',
            'id_empresa'    => 'required|numeric',
            'id_sucursal'   => 'required|numeric',
            'nuevaResolucion' => 'sometimes|boolean',
        ]);

        try {
            if ($request->id) {
                $documento = Documento::findOrFail($request->id);

                if ($request->nuevaResolucion) {

                    $documento->update([
                        'activo' => false,
                        'predeterminado' => false
                    ]);


                    Documento::where('id_sucursal', $request->id_sucursal)
                        ->where('nombre', $request->nombre)
                        ->update([
                            'predeterminado' => false,
                            'activo' => false
                        ]);


                    $documento = new Documento;
                    $documento->fill($request->all());
                    $documento->save();
                } else {

                    $existe = Documento::where('id_sucursal', $request->id_sucursal)
                        ->where('nombre', $request->nombre)
                        ->where('id', '!=', $request->id)
                        ->first();

                    if ($existe) {
                        return response()->json([
                            'error' => 'Ya ha sido agregado el documento',
                            'code' => 400
                        ], 400);
                    }

                    $documento->fill($request->all());
                    $documento->save();
                }
            } else {

                Documento::where('id_sucursal', $request->id_sucursal)
                    ->where('nombre', $request->nombre)
                    ->update([
                        'predeterminado' => false,
                        'activo' => false
                    ]);


                $documento = new Documento;
                $documento->fill($request->all());
                $documento->save();
            }

            return response()->json($documento, 200);
        } catch (\Exception $e) {
            Log::error('Error al guardar documento: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al procesar la solicitud',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function delete($id)
    {
        $documento = Documento::findOrFail($id);
        $documento->delete();

        return Response()->json($documento, 201);
    }
}
