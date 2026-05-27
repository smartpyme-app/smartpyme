<?php

namespace App\Http\Controllers\Api\Compras;

use App\Exceptions\Compras\DocumentoImportException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Compras\ImportarDocumentoRequest;
use App\Services\Compras\DocumentoImport\DocumentoImportService;
use App\Models\User;
use App\Services\Compras\Gastos\GastoImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Importación de documentos electrónicos recibidos (JSON SV / XML CR).
 */
class DocumentoImportController extends Controller
{
    public function __construct(
        private readonly DocumentoImportService $documentoImportService,
        private readonly GastoImportService $gastoImportService,
    ) {}

    /**
     * Preview de compra desde documento electrónico (sin guardar).
     */
    public function importarCompra(ImportarDocumentoRequest $request): JsonResponse
    {
        try {
            $result = $this->documentoImportService->importar(
                $request->contenidoDocumento()
            );

            return response()->json(array_merge(
                $result->toResponseArray(),
                ['mensaje' => 'Documento interpretado correctamente.']
            ));
        } catch (DocumentoImportException|\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('importarCompra documento', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Error al procesar el documento: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Preview de gasto desde documento electrónico (sin guardar).
     */
    public function importarGasto(ImportarDocumentoRequest $request): JsonResponse
    {
        if ($bloqueado = $this->respuestaGastosRestringidosSupervisorLimitado(auth()->user())) {
            return $bloqueado;
        }

        try {
            $result = $this->documentoImportService->importar(
                $request->contenidoDocumento()
            );

            $gasto = $this->gastoImportService->importarDesdeJson($result->dte);
            $gasto->tipo_documento = $result->tipoDocumentoNombre;

            return response()->json(array_merge(
                $result->toResponseArray(),
                [
                    'gasto' => $gasto,
                    'mensaje' => 'Documento importado exitosamente',
                ]
            ));
        } catch (DocumentoImportException|\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('importarGasto documento', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Error al procesar el documento: '.$e->getMessage(),
            ], 422);
        }
    }

    private function respuestaGastosRestringidosSupervisorLimitado(?User $user): ?JsonResponse
    {
        if (! $user || ($user->tipo ?? null) !== 'Supervisor Limitado') {
            return null;
        }

        $empresa = $user->empresa ?? null;
        if (! $empresa || ! ($empresa->restringir_gastos_supervisor_limitado ?? false)) {
            return null;
        }

        return response()->json([
            'error' => 'La empresa tiene activa la opción de restringir gastos para usuarios Supervisor limitado.',
        ], 403);
    }
}
