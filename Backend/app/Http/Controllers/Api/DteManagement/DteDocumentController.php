<?php

namespace App\Http\Controllers\Api\DteManagement;

use App\Http\Controllers\Controller;
use App\Models\DteManagement\DteDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DteDocumentController extends Controller
{
    /**
     * List DTE documents with filters and pagination.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = DteDocument::with('userEmailAccount:id,email,provider')
            ->orderBy('emission_date', 'desc')
            ->orderBy('id', 'desc');

        if ($request->filled('validation_status')) {
            $query->where('validation_status', $request->validation_status);
        }

        if ($request->filled('processing_status')) {
            $query->where('processing_status', $request->processing_status);
        }

        if ($request->filled('dte_type')) {
            $query->where('dte_type', $request->dte_type);
        }

        if ($request->filled('inicio')) {
            $query->where('emission_date', '>=', $request->inicio);
        }

        if ($request->filled('fin')) {
            $query->where('emission_date', '<=', $request->fin);
        }

        if ($request->filled('buscador')) {
            $term = $request->buscador;
            $query->where(function ($q) use ($term) {
                $q->where('dte_number', 'like', "%{$term}%")
                    ->orWhere('issuer_nit', 'like', "%{$term}%")
                    ->orWhere('issuer_name', 'like', "%{$term}%")
                    ->orWhere('dte_uuid', 'like', "%{$term}%");
            });
        }

        $perPage = min((int) ($request->per_page ?? 15), 100);
        $documents = $query->paginate($perPage);

        return response()->json($documents);
    }

    /**
     * Get a single DTE document by ID.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $document = DteDocument::with('userEmailAccount:id,email,provider,id_sucursal,id_bodega')
            ->findOrFail($id);

        if ($document->id_empresa !== auth()->user()->id_empresa) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        return response()->json($document);
    }

    /**
     * Download JSON file for a DTE.
     *
     * @param int $id
     * @return \Symfony\Component\HttpFoundation\StreamedResponse|JsonResponse
     */
    public function downloadJson(int $id)
    {
        $document = DteDocument::findOrFail($id);

        if ($document->id_empresa !== auth()->user()->id_empresa || !$document->json_path) {
            return response()->json(['error' => 'No autorizado o archivo no encontrado'], 403);
        }

        if (!Storage::disk('dtes')->exists($document->json_path)) {
            return response()->json(['error' => 'Archivo no encontrado'], 404);
        }

        return response()->streamDownload(function () use ($document) {
            echo Storage::disk('dtes')->get($document->json_path);
        }, $document->dte_uuid . '.json', ['Content-Type' => 'application/json']);
    }

    /**
     * Update DTE document (e.g. destino compra/gasto).
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $document = DteDocument::findOrFail($id);

        if ($document->id_empresa !== auth()->user()->id_empresa) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        if ($document->processing_status === 'processed') {
            return response()->json(['error' => 'El DTE ya fue procesado'], 422);
        }

        if ($request->filled('destino')) {
            $destino = $request->destino;
            if (!in_array($destino, ['compra', 'gasto'], true)) {
                return response()->json(['error' => 'Destino inválido'], 422);
            }
            $document->update(['destino' => $destino]);
        }

        return response()->json(['success' => true, 'document' => $document->fresh()]);
    }

    /**
     * Manually process DTE into Compra or Gasto (for pending/pendiente_clasificacion).
     *
     * @param int $id
     * @return JsonResponse
     */
    public function procesar(int $id): JsonResponse
    {
        $document = DteDocument::with('userEmailAccount')->findOrFail($id);

        if ($document->id_empresa !== auth()->user()->id_empresa) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        if ($document->processing_status === 'processed') {
            return response()->json(['success' => true, 'message' => 'Ya procesado', 'document' => $document->fresh()]);
        }

        if ($document->validation_status !== 'valid') {
            return response()->json(['error' => 'Solo se pueden procesar DTEs válidos'], 422);
        }

        $dteToIva = app(\App\Services\Dte\DteToIvaService::class);
        $result = $dteToIva->insertFromDteDocument($document);

        if (!empty($result['skipped'])) {
            if ($result['skipped'] === 'duplicate') {
                $document->update(['processing_status' => 'processed']);
                return response()->json([
                    'success' => true,
                    'message' => 'Este DTE ya fue procesado anteriormente (existe en Compras o Gastos)',
                    'document' => $document->fresh(),
                ]);
            }
            return response()->json([
                'success' => false,
                'error' => 'No se pudo procesar',
                'reason' => $result['skipped'],
            ], 422);
        }

        if ($result['success']) {
            $document->refresh();
            return response()->json([
                'success' => true,
                'message' => isset($result['compra_id']) ? 'Compra creada' : 'Gasto creado',
                'compra_id' => $result['compra_id'] ?? null,
                'gasto_id' => $result['gasto_id'] ?? null,
                'document' => $document,
            ]);
        }

        return response()->json(['error' => 'Error al procesar el DTE'], 500);
    }

    /**
     * Download PDF file for a DTE.
     *
     * @param int $id
     * @return \Symfony\Component\HttpFoundation\StreamedResponse|JsonResponse
     */
    public function downloadPdf(int $id)
    {
        $document = DteDocument::findOrFail($id);

        if ($document->id_empresa !== auth()->user()->id_empresa || !$document->pdf_path) {
            return response()->json(['error' => 'No autorizado o archivo no encontrado'], 403);
        }

        if (!Storage::disk('dtes')->exists($document->pdf_path)) {
            return response()->json(['error' => 'Archivo no encontrado'], 404);
        }

        return response()->streamDownload(function () use ($document) {
            echo Storage::disk('dtes')->get($document->pdf_path);
        }, $document->dte_uuid . '.pdf', ['Content-Type' => 'application/pdf']);
    }
}
