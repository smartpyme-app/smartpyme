<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Admin\Documento;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

    public function listNombre()
    {
        $documentos = DB::table('documentos as d1')
            ->where('d1.activo', true)
            ->where('d1.id_empresa', Auth::user()->id_empresa) // Filtrar por empresa
            ->whereIn('d1.id', function($query) {
                $query->select(DB::raw('MIN(id)'))
                    ->from('documentos as d2')
                    ->where('d2.activo', true)
                    ->where('d2.id_empresa', Auth::user()->id_empresa) // Filtrar por empresa en subconsulta
                    ->groupBy('d2.nombre');
            })
            ->orderBy('d1.nombre')
            ->get();
        return Response()->json($documentos, 200);
    }


    public function read($id)
    {

        $documento = Documento::findOrFail($id);
        return Response()->json($documento, 200);
    }

    // public function store(Request $request)
    // {
    //     $request->validate([
    //         'nombre'        => 'required|max:255',
    //         'correlativo'   => 'required|max:255',
    //         'rangos'        => 'sometimes|max:255',
    //         'numero_autorizacion' => 'sometimes|max:255',
    //         'resolucion'    => 'sometimes|max:255',
    //         'nota'          => 'sometimes|max:1000',
    //         'id_empresa'    => 'required|numeric',
    //         'id_sucursal'   => 'required|numeric',
    //         'nuevaResolucion' => 'sometimes|boolean',
    //     ]);

    //     Log::info($request->all());

    //     try {
    //         if ($request->id) {
    //             $documento = Documento::findOrFail($request->id);

    //             if ($request->nuevaResolucion) {

    //                 $documento->update([
    //                     'activo' => false,
    //                     'predeterminado' => false
    //                 ]);


    //                 Documento::where('id_sucursal', $request->id_sucursal)
    //                     ->where('nombre', $request->nombre)
    //                     ->update([
    //                         'predeterminado' => false,
    //                         'activo' => false
    //                     ]);


    //                 $documento = new Documento;
    //                 $documento->fill($request->all());
    //                 $documento->save();
    //             } else {

    //                 $existe = Documento::where('id_sucursal', $request->id_sucursal)
    //                     ->where('nombre', $request->nombre)
    //                     ->where('id', '!=', $request->id)
    //                     ->first();

    //                 if ($existe) {
    //                     if ($request->change && $request->predeterminado == 1) {
    //                         Log::info('entra');
    //                         $documentos = Documento::where('id_sucursal', $documento->id_sucursal)
    //                             ->where('predeterminado', true)
    //                             ->where('id', '!=', $documento->id)
    //                             ->get();
    //                         foreach ($documentos as $doc) {
    //                             $doc->predeterminado = false;
    //                             $doc->save();
    //                         }
    //                     }


    //                     $documento->fill($request->all());
    //                     $documento->save();
    //                 }

    //                 $documento->fill($request->all());
    //                 $documento->save();
    //             }
    //         } else {

    //             Documento::where('id_sucursal', $request->id_sucursal)
    //                 ->where('nombre', $request->nombre)
    //                 ->update([
    //                     'predeterminado' => false,
    //                     'activo' => false
    //                 ]);


    //             $documento = new Documento;
    //             $documento->fill($request->all());
    //             $documento->save();
    //         }

    //         return response()->json($documento, 200);
    //     } catch (\Exception $e) {
    //         Log::error('Error al guardar documento: ' . $e->getMessage());
    //         return response()->json([
    //             'error' => 'Error al procesar la solicitud',
    //             'message' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|max:255',
            'correlativo' => 'required|max:255',
            'rangos' => 'sometimes|max:255',
            'numero_autorizacion' => 'sometimes|max:255',
            'resolucion' => 'sometimes|max:255',
            'nota' => 'sometimes|max:1000',
            'id_empresa' => 'required|numeric',
            'id_sucursal' => 'required|numeric',
            'nuevaResolucion' => 'sometimes|boolean',
            'predeterminado' => 'sometimes|boolean',
        ]);

        try {
            DB::beginTransaction();

            if ($request->id) {
                $documento = Documento::findOrFail($request->id);

                if ($request->nuevaResolucion) {

                    $this->deactivateDocument($documento);
                    $documento = $this->createNewResolution($request->all());
                } else {

                    if ($request->change && $request->predeterminado) {
                        $this->updatePredeterminado($documento->id_sucursal, $documento->id);
                    }

                    $existe = Documento::where('id_sucursal', $request->id_sucursal)
                        ->where('nombre', $request->nombre)
                        ->where('id', '!=', $request->id)
                        ->where('activo', true)
                        ->first();

                    if ($existe) {
                        return response()->json([
                            'error' => 'Ya existe un documento con el mismo nombre',
                            'message' => 'Ya existe un documento con el mismo nombre'
                        ], 500);
                    }


                    $documento->update($request->all());
                }
            } else {

                $this->deactivateExistingDocuments($request->id_sucursal, $request->nombre);
                $documento = Documento::create($request->all());
            }

            DB::commit();
            return response()->json($documento, 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al guardar documento: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al procesar la solicitud',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function deactivateDocument(Documento $documento)
    {
        $documento->update([
            'activo' => false,
            'predeterminado' => false
        ]);
    }

    private function updatePredeterminado($id_sucursal, $excludeId)
    {
        Documento::where('id_sucursal', $id_sucursal)
            ->where('predeterminado', true)
            ->where('id', '!=', $excludeId)
            ->update(['predeterminado' => false]);
    }

    private function deactivateExistingDocuments($id_sucursal, $nombre)
    {
        Documento::where('id_sucursal', $id_sucursal)
            ->where('nombre', $nombre)
            ->update([
                'predeterminado' => false,
                'activo' => false
            ]);
    }

    private function createNewResolution(array $data)
    {
        $this->deactivateExistingDocuments($data['id_sucursal'], $data['nombre']);
        return Documento::create($data);
    }

    public function delete($id)
    {
        $documento = Documento::findOrFail($id);
        $documento->delete();

        return Response()->json($documento, 201);
    }
}
