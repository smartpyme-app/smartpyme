<?php

namespace App\Http\Controllers\Api\DteManagement;

use App\Http\Controllers\Controller;
use App\Models\DteManagement\DteDocument;
use App\Models\DteManagement\UserEmailAccount;
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
     * Resumen para alerta al ingresar: DTEs válidos pendientes de revisión/procesamiento.
     */
    public function pendingReviewAlert(): JsonResponse
    {
        $userId = auth()->id();
        $idEmpresa = auth()->user()->id_empresa;

        $isRecipient = UserEmailAccount::withoutGlobalScopes()
            ->where('id_empresa', $idEmpresa)
            ->where('is_active', true)
            ->where('notification_user_id', $userId)
            ->exists();

        if (!$isRecipient) {
            return response()->json([
                'show_alert' => false,
                'pending_count' => 0,
            ]);
        }

        $pendingCount = DteDocument::withoutGlobalScopes()
            ->where('id_empresa', $idEmpresa)
            ->where('validation_status', 'valid')
            ->whereIn('processing_status', ['pending', 'pendiente_clasificacion', 'failed'])
            ->count();

        return response()->json([
            'show_alert' => $pendingCount > 0,
            'pending_count' => $pendingCount,
        ]);
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

        if ($document->processing_status === 'anulado') {
            return response()->json(['error' => 'El DTE está anulado'], 422);
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

        if ($document->processing_status === 'anulado') {
            return response()->json(['error' => 'No se puede procesar un DTE anulado'], 422);
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
     * Marcar DTE como anulado (descartado de la bandeja de revisión).
     */
    public function anular(int $id): JsonResponse
    {
        $document = DteDocument::findOrFail($id);

        if ($document->id_empresa !== auth()->user()->id_empresa) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        if ($document->processing_status === 'processed') {
            return response()->json(['error' => 'No se puede anular un DTE ya procesado'], 422);
        }

        if ($document->processing_status === 'anulado') {
            return response()->json([
                'success' => true,
                'message' => 'El DTE ya estaba anulado',
                'document' => $document->fresh(),
            ]);
        }

        $document->update(['processing_status' => 'anulado']);

        return response()->json([
            'success' => true,
            'message' => 'DTE anulado correctamente',
            'document' => $document->fresh(),
        ]);
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
