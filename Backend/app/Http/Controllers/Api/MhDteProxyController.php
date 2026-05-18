<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin\Empresa;
use App\Services\MhGovSvGatewayService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MhDteProxyController extends Controller
{
    protected $gateway;

    public function __construct(MhGovSvGatewayService $gateway)
    {
        $this->gateway = $gateway;
    }

    protected function empresaAutenticada(): Empresa
    {
        $user = auth()->user();

        return Empresa::findOrFail($user->id_empresa);
    }

    /**
     * Proxy hacia POST /fesv/recepciondte (emisión estándar).
     */
    public function enviar(Request $request)
    {
        return $this->proxy($request, '/fesv/recepciondte');
    }

    /**
     * Proxy hacia POST /fesv/recepcion/consultadte.
     */
    public function consultar(Request $request)
    {
        return $this->proxy($request, '/fesv/recepcion/consultadte');
    }

    /**
     * Proxy hacia POST /fesv/contingencia.
     */
    public function contingencia(Request $request)
    {
        return $this->proxy($request, '/fesv/contingencia');
    }

    /**
     * Proxy hacia POST /fesv/anulardte (anulación del JSON enviado al MH; distinto del flujo interno /anularDTE).
     */
    public function anular(Request $request)
    {
        return $this->proxy($request, '/fesv/anulardte');
    }

    /**
     * Valida credenciales MH vía /seguridad/auth y devuelve el mismo JSON que expone el MH.
     */
    public function authTest(Request $request)
    {
        $empresa = $this->empresaAutenticada();
        $t0 = microtime(true);

        try {
            $auth = $this->gateway->fetchAuthToken($empresa);
        } catch (ConnectionException $e) {
            Log::warning('MH auth-test sin conexión', [
                'empresa_id' => $empresa->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'ERROR',
                'body' => [
                    'descripcionMsg' => 'Sin conexión con el Ministerio de Hacienda.',
                ],
            ], 503);
        }

        $durationMs = (int) round((microtime(true) - $t0) * 1000);
        Log::info('MH auth-test', [
            'empresa_id' => $empresa->id,
            'duration_ms' => $durationMs,
            'mh_http_status' => $auth['status'],
        ]);

        $decoded = $auth['body'] ?? json_decode($auth['raw_body'], true);
        if (!is_array($decoded)) {
            return response()->json([
                'status' => 'ERROR',
                'body' => ['descripcionMsg' => 'Respuesta inválida del Ministerio de Hacienda.'],
            ], 502);
        }

        $httpStatus = $auth['status'] >= 400 ? $auth['status'] : 200;

        return response()->json($decoded, $httpStatus);
    }

    protected function proxy(Request $request, string $path)
    {
        $empresa = $this->empresaAutenticada();
        $payload = $request->all();

        try {
            $result = $this->gateway->postJson($empresa, $path, $payload);
        } catch (ConnectionException $e) {
            Log::error('MH proxy sin conexión', [
                'path' => $path,
                'empresa_id' => $empresa->id,
                'payload' => $payload,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'descripcionMsg' => 'No se pudo conectar con el Ministerio de Hacienda.',
                'detalle' => config('app.debug') ? $e->getMessage() : null,
            ], 503);
        }

        return response()->json($result['body'], $result['status']);
    }
}
