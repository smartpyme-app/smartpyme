<?php

namespace App\Http\Controllers\Api\DteManagement;

use App\Http\Controllers\Controller;
use App\Models\DteManagement\DteDocument;
use App\Models\DteManagement\UserEmailAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
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

        $payload = $document->toArray();
        $payload['line_items'] = $this->parseLineItems($document);

        return response()->json($payload);
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

        try {
            $this->applyDocumentMetadata($document, $request);
            $document->save();
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (QueryException $e) {
            return $this->metadataSaveErrorResponse($e);
        }

        return response()->json(['success' => true, 'document' => $document->fresh()]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function parseLineItems(DteDocument $document): array
    {
        if (!$document->json_path) {
            return [];
        }

        try {
            if (!Storage::disk('dtes')->exists($document->json_path)) {
                return [];
            }

            $jsonData = json_decode(Storage::disk('dtes')->get($document->json_path), true);
        } catch (\Throwable $e) {
            Log::warning('DteDocumentController: no se pudo leer JSON para line_items', [
                'dte_id' => $document->id,
                'json_path' => $document->json_path,
                'error' => $e->getMessage(),
            ]);
            return [];
        }

        if (!is_array($jsonData)) {
            return [];
        }

        $items = $jsonData['cuerpoDocumento'] ?? [];
        $lines = [];

        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $cantidad = (float) ($item['cantidad'] ?? 0);
            $precioUni = (float) ($item['precioUni'] ?? 0);
            $ventaGravada = (float) ($item['ventaGravada'] ?? 0);
            $ventaExenta = (float) ($item['ventaExenta'] ?? 0);
            $ventaNoSuj = (float) ($item['ventaNoSuj'] ?? 0);
            $subtotalLinea = $ventaGravada + $ventaExenta + $ventaNoSuj;
            $ventaTotal = (float) ($item['ventaTotal'] ?? 0);
            $total = $subtotalLinea > 0 ? $subtotalLinea : ($ventaTotal > 0 ? $ventaTotal : max(0, $cantidad * $precioUni));

            $lines[] = [
                'numero' => $index + 1,
                'codigo' => $item['codigo'] ?? $item['codTrib'] ?? '',
                'descripcion' => $item['descripcion'] ?? '',
                'cantidad' => $cantidad,
                'precio_unitario' => $precioUni,
                'total' => round($total, 2),
            ];
        }

        return $lines;
    }

    /**
     * Manually process DTE into Compra or Gasto (for pending/pendiente_clasificacion).
     */
    public function procesar(Request $request, int $id): JsonResponse
    {
        set_time_limit(300);

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

        try {
            $this->applyDocumentMetadata($document, $request);
            $document->save();
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (QueryException $e) {
            return $this->metadataSaveErrorResponse($e);
        }

        $document->refresh();
        $document->load('userEmailAccount');

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

    protected function applyDocumentMetadata(DteDocument $document, Request $request): void
    {
        if ($request->filled('destino')) {
            $destino = $request->destino;
            if (!in_array($destino, ['compra', 'gasto'], true)) {
                throw new \InvalidArgumentException('Destino inválido');
            }
            $document->destino = $destino;
        }

        if ($request->has('id_proyecto')) {
            $document->id_proyecto = $request->input('id_proyecto') ?: null;
        }

        if ($request->has('id_categoria')) {
            $document->id_categoria = $request->input('id_categoria') ?: null;
        }

        if ($request->has('tipo_gasto')) {
            $document->tipo_gasto = $request->input('tipo_gasto') ?: null;
        }

        if ($request->has('tipo_costo_gasto')) {
            $document->tipo_costo_gasto = $request->input('tipo_costo_gasto') ?: null;
        }
    }

    protected function metadataSaveErrorResponse(QueryException $e): JsonResponse
    {
        if (str_contains($e->getMessage(), 'Unknown column')) {
            return response()->json([
                'error' => 'Faltan migraciones en el servidor. Ejecute php artisan migrate en producción.',
            ], 503);
        }

        throw $e;
    }
}
