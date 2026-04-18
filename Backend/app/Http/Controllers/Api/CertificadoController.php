<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubirCertificadoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CertificadoController extends Controller
{
    public function subir(SubirCertificadoRequest $request): JsonResponse
    {
        $archivo = $request->file('archivo');
        $nitDigits = (string) $request->validated('nit');
        $extension = strtolower((string) $archivo->getClientOriginalExtension());
        $nombreFinal = $nitDigits . '.' . $extension;

        try {
            $contenido = $archivo->getContent();
            Storage::disk('uploads_ec2')->put($nombreFinal, $contenido);
        } catch (Throwable $e) {
            Log::error('Error SFTP al subir certificado electrónico', [
                'filename' => $nombreFinal,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            $mensaje = config('app.debug')
                ? 'Error al subir por SFTP: ' . $e->getMessage()
                : 'No se pudo subir el certificado. Verifique la conexión SFTP, la llave .pem y las variables EC2_* en el servidor.';

            return response()->json([
                'success' => false,
                'message' => $mensaje,
                'filename' => null,
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Certificado guardado correctamente en el servidor de firmado.',
            'filename' => $nombreFinal,
        ]);
    }
}
