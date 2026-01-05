<?php

namespace App\Http\Controllers\Api\Ventas;

use App\Http\Controllers\Controller;
use App\Services\Ventas\DocumentoService;
use Illuminate\Http\Request;

class GenerarDocumentosController extends Controller
{
    protected $documentoService;

    public function __construct(DocumentoService $documentoService)
    {
        $this->documentoService = $documentoService;
    }

    public function generarDoc($id)
    {
        try {
            return $this->documentoService->generarDocumento($id);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function anularDoc()
    {
        return view('reportes.anulacion');
    }
}
