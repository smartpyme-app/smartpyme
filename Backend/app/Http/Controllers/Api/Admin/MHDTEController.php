<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MH\AnularDTERequest;
use App\Http\Requests\MH\AnularDTESujetoExcluidoRequest;
use App\Http\Requests\MH\ConsultarDTERequest;
use App\Http\Requests\MH\EnviarDTERequest;
use App\Http\Requests\MH\GenerarContingenciaRequest;
use App\Http\Requests\MH\GenerarDTEAnuladoRequest;
use App\Http\Requests\MH\GenerarDTENotaCreditoRequest;
use App\Http\Requests\MH\GenerarDTERequest;
use App\Http\Requests\MH\GenerarDTESujetoExcluidoCompraRequest;
use App\Http\Requests\MH\GenerarDTESujetoExcluidoGastoRequest;
use App\Http\Requests\MH\GenerarDTEJSONRequest;
use App\Http\Requests\MH\GenerarDTEPDFRequest;
use App\Models\Compras\Compra;
use App\Models\Compras\Gastos\Gasto;
use App\Models\Ventas\Devoluciones\Devolucion as DevolucionVenta;
use App\Models\Ventas\Venta;
use App\Services\FacturacionElectronica\CostaRica\CostaRicaFeComprobantePdfService;
use App\Services\FacturacionElectronica\CostaRica\CostaRicaFeCorreoService;
use App\Services\FacturacionElectronica\CostaRica\CostaRicaFeEmitService;
use App\Services\FacturacionElectronica\ElSalvador\ElSalvadorDteService;
use App\Services\FacturacionElectronica\FacturacionElectronicaCountryGate;
use App\Services\FacturacionElectronica\FacturacionElectronicaCountryResolver;
use App\Support\FacturacionElectronica\CostaRicaFeDteDocumento;
use App\Support\FacturacionElectronica\XmlRespuestaHaciendaCr;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class MHDTEController extends Controller
{
    public function __construct(
        private readonly ElSalvadorDteService $elSalvadorDte,
        private readonly CostaRicaFeComprobantePdfService $costaRicaFePdf,
        private readonly CostaRicaFeCorreoService $costaRicaFeCorreo,
        private readonly CostaRicaFeEmitService $costaRicaFeEmit,
    ) {}

    public function generarDTE(GenerarDTERequest $request)
    {
        $venta = Venta::where('id', $request->id)
            ->with('detalles.producto.impuestos', 'impuestos.impuesto', 'cliente', 'empresa')
            ->firstOrFail();

        if ($guard = FacturacionElectronicaCountryGate::ensureSvDteOrFail($venta->empresa)) {
            return $guard;
        }

        return $this->elSalvadorDte->generarDTE($venta);
    }

    public function generarDTENotaCredito(GenerarDTENotaCreditoRequest $request)
    {
        $devolucion = DevolucionVenta::where('id', $request->id)
            ->with('detalles.producto.impuestos', 'impuestos.impuesto', 'cliente', 'empresa', 'venta')
            ->firstOrFail();

        if (!$devolucion->venta) {
            return response()->json(['error' => 'La devolución no tiene una venta asignada.'], 400);
        }

        if ($guard = FacturacionElectronicaCountryGate::ensureSvDteOrFail($devolucion->empresa)) {
            return $guard;
        }

        return $this->elSalvadorDte->generarDTENotaCredito($devolucion);
    }

    public function generarDTESujetoExcluidoGasto(GenerarDTESujetoExcluidoGastoRequest $request)
    {
        $gasto = Gasto::where('id', $request->id)->with('proveedor', 'empresa')->firstOrFail();
        $this->aplicarMontosSujetoExcluidoDesdeRequest($gasto, $request, [
            'renta_retenida', 'sub_total', 'iva', 'descuento', 'total', 'iva_percibido',
        ]);
        if ($guard = FacturacionElectronicaCountryGate::ensureSvDteOrFail($gasto->empresa)) {
            return $guard;
        }

        return $this->elSalvadorDte->generarDTESujetoExcluidoGasto($gasto);
    }

    public function generarDTESujetoExcluidoCompra(GenerarDTESujetoExcluidoCompraRequest $request)
    {
        $compra = Compra::where('id', $request->id)->with('detalles', 'proveedor', 'empresa')->firstOrFail();
        $this->aplicarMontosSujetoExcluidoDesdeRequest($compra, $request, [
            'renta_retenida', 'iva_retenido', 'sub_total', 'iva', 'descuento', 'total',
        ]);
        if ($guard = FacturacionElectronicaCountryGate::ensureSvDteOrFail($compra->empresa)) {
            return $guard;
        }

        return $this->elSalvadorDte->generarDTESujetoExcluidoCompra($compra);
    }


    private function aplicarMontosSujetoExcluidoDesdeRequest($registro, Request $request, array $campos): void
    {
        foreach ($campos as $campo) {
            if (!$request->exists($campo)) {
                continue;
            }
            $valor = $request->input($campo);
            if ($valor === null || $valor === '') {
                continue;
            }
            if ($campo === 'renta_retenida' && is_numeric($valor) && (float) $valor === 0.0) {
                $yaPersistido = (float) ($registro->renta_retenida ?? 0);
                if ($yaPersistido > 0) {
                    continue;
                }
            }
            $registro->{$campo} = is_numeric($valor) ? $valor + 0 : $valor;
        }
    }

    public function generarContingencia(GenerarContingenciaRequest $request)
    {
        $ventas = Venta::whereIn('id', [$request->id])
            ->withAccessorRelations()
            ->with('detalles', 'empresa')
            ->get();
        $empresa = $ventas[0]->empresa;

        if ($guard = FacturacionElectronicaCountryGate::ensureSvDteOrFail($empresa)) {
            return $guard;
        }

        return $this->elSalvadorDte->generarContingencia($request);
    }

    public function generarDTEAnulado(GenerarDTEAnuladoRequest $request)
    {
        if ($request->tipo_dte == '05' || $request->tipo_dte == '06') {
            $registro = DevolucionVenta::where('id', $request->id)->with('empresa')->firstOrFail();
        } else {
            $registro = Venta::where('id', $request->id)->with('empresa')->firstOrFail();
        }

        if ($guard = FacturacionElectronicaCountryGate::ensureSvDteOrFail($registro->empresa)) {
            return $guard;
        }

        return $this->elSalvadorDte->generarDTEAnulado($request);
    }

    public function generarDTEAnuladoSujetoExcluidoCompra(GenerarDTESujetoExcluidoCompraRequest $request)
    {
        $compra = Compra::where('id', $request->id)->with('empresa')->firstOrFail();

        if ($guard = FacturacionElectronicaCountryGate::ensureSvDteOrFail($compra->empresa)) {
            return $guard;
        }

        return $this->elSalvadorDte->generarDTEAnuladoSujetoExcluidoCompra($compra);
    }

    public function generarDTEAnuladoSujetoExcluidoGasto(GenerarDTESujetoExcluidoGastoRequest $request)
    {
        $gasto = Gasto::where('id', $request->id)->with('empresa')->firstOrFail();

        if ($guard = FacturacionElectronicaCountryGate::ensureSvDteOrFail($gasto->empresa)) {
            return $guard;
        }

        return $this->elSalvadorDte->generarDTEAnuladoSujetoExcluidoGasto($gasto);
    }

    public function generarTicket($id)
    {
        $venta = Venta::where('id', $id)->with('detalles', 'cliente', 'empresa')->firstOrFail();

        if ($guard = FacturacionElectronicaCountryGate::ensureSvDteOrFail($venta->empresa)) {
            return $guard;
        }

        return $this->elSalvadorDte->generarTicket($venta);
    }

    public function anularDTE(AnularDTERequest $request)
    {
        $venta = Venta::where('id', $request->id)->with('empresa')->firstOrFail();

        if ($guard = FacturacionElectronicaCountryGate::ensureSvDteOrFail($venta->empresa)) {
            return $guard;
        }

        return $this->elSalvadorDte->anularDTE($request);
    }

    public function anularDTESujetoExcluido(AnularDTESujetoExcluidoRequest $request)
    {
        $gasto = Gasto::where('id', $request->id)->with('empresa')->firstOrFail();

        if ($guard = FacturacionElectronicaCountryGate::ensureSvDteOrFail($gasto->empresa)) {
            return $guard;
        }

        return $this->elSalvadorDte->anularDTESujetoExcluido($request);
    }

    public function generarDTEPDF($id, $tipo, GenerarDTEPDFRequest $request)
    {
        $empresa = Auth::user()?->empresa;
        if (FacturacionElectronicaCountryResolver::codPais($empresa) === FacturacionElectronicaCountryResolver::CODIGO_COSTA_RICA) {
            return $this->generarDtePdfCostaRica($id, (string) $tipo, $request);
        }

        if ($guard = FacturacionElectronicaCountryGate::ensureSvDteOrFail($empresa)) {
            return $guard;
        }

        return $this->elSalvadorDte->generarDTEPDF($id, $tipo, $request);
    }

    /**
     * PDF resumen del comprobante CR (XML firmado en dte.documento, parseado al generar el PDF).
     */
    private function generarDtePdfCostaRica(int|string $id, string $tipo, GenerarDTEPDFRequest $request): Response
    {
        $uid = Auth::user()?->id_empresa;
        if ($uid === null) {
            abort(403);
        }
        try {
            $datos = $this->costaRicaFePdf->datosVistaParaPdf(
                (int) $id,
                (string) $tipo,
                $request->query('tipo'),
                (int) $uid
            );
        } catch (RuntimeException $e) {
            $msg = $e->getMessage();
            abort(str_contains($msg, 'No autorizado') ? 403 : 404, $msg);
        }

        return $this->costaRicaFePdf->pdfStreamResponse($datos);
    }

    /**
     * XML devuelto por Hacienda al consultar estado (legado en dte.cr o consulta en vivo).
     */
    public function generarDteXmlCostaRica(Request $request, int|string $id, string $tipo): JsonResponse|Response
    {
        $empresa = Auth::user()?->empresa;
        if (FacturacionElectronicaCountryResolver::codPais($empresa) !== FacturacionElectronicaCountryResolver::CODIGO_COSTA_RICA) {
            return response()->json(['error' => 'Solo aplica a empresas con facturación Costa Rica.'], 400);
        }

        $id = (int) $id;
        $contexto = $this->resolverContextoDteCostaRica($id, (string) $tipo, $request->query('tipo'));
        if ($contexto === null) {
            return response()->json(['error' => 'Tipo de documento no disponible.'], 400);
        }

        [$registro, $dte, $clave, $bloqueNd] = $contexto;
        if (! CostaRicaFeDteDocumento::tieneComprobanteCr($dte) && ! is_array($bloqueNd)) {
            return response()->json(['error' => 'No hay comprobante electrónico Costa Rica para este registro.'], 404);
        }

        $dteConsulta = is_array($bloqueNd) ? ['pais' => 'CR', 'cr' => $bloqueNd] : $dte;
        $xml = $this->costaRicaFeEmit->obtenerRespuestaHaciendaXml($empresa, $clave, $dteConsulta);

        if (! is_string($xml) || trim($xml) === '') {
            return response()->json([
                'error' => 'No se pudo obtener el XML de respuesta de Hacienda.',
            ], 404);
        }

        $xml = XmlRespuestaHaciendaCr::normalizar($xml);

        $claveArchivo = $clave !== '' ? $clave : (string) $id;
        $fname = 'respuesta-hacienda-'.preg_replace('/\W+/', '-', $claveArchivo).'.xml';

        return response()->make($xml, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="'.$fname.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function assertMismoEmpresaUsuario(Venta|DevolucionVenta $registro): void
    {
        $uid = Auth::user()?->id_empresa;
        if ($uid === null || (int) $registro->id_empresa !== (int) $uid) {
            abort(403);
        }
    }

    private function assertMismoEmpresaUsuarioCompraGasto(Compra|Gasto $registro): void
    {
        $uid = Auth::user()?->id_empresa;
        if ($uid === null || (int) $registro->id_empresa !== (int) $uid) {
            abort(403);
        }
    }

    public function generarDTEJSON($id, $tipo, GenerarDTEJSONRequest $request)
    {
        $empresa = Auth::user()?->empresa;
        if (FacturacionElectronicaCountryResolver::codPais($empresa) === FacturacionElectronicaCountryResolver::CODIGO_COSTA_RICA) {
            return $this->generarDteJsonCostaRica($id, (string) $tipo, $request);
        }

        if ($guard = FacturacionElectronicaCountryGate::ensureSvDteOrFail($empresa)) {
            return $guard;
        }

        return $this->elSalvadorDte->generarDTEJSON($id, $tipo, $request);
    }

    /**
     * Misma ruta que El Salvador (/reporte/dte-json/...): devuelve JSON parseado del XML guardado en dte.
     */
    private function generarDteJsonCostaRica(int|string $id, string $tipo, GenerarDTEJSONRequest $request): JsonResponse
    {
        $id = (int) $id;
        try {
            $contexto = $this->resolverContextoDteCostaRica($id, $tipo, $request->query('tipo'));
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }
        if ($contexto === null) {
            return response()->json(['error' => 'Tipo de documento no disponible para descarga JSON en FE Costa Rica.'], 400);
        }

        [$registro, $dte, , $bloqueNd] = $contexto;
        if (! CostaRicaFeDteDocumento::tieneComprobanteCr($dte) && ! is_array($bloqueNd)) {
            return response()->json(['error' => 'No hay comprobante electrónico Costa Rica para este registro.'], 404);
        }

        $payload = CostaRicaFeDteDocumento::payloadInternoJson(
            CostaRicaFeDteDocumento::tieneComprobanteCr($dte) ? $dte : ['pais' => 'CR'],
            is_array($bloqueNd) ? $bloqueNd : null
        );

        return response()->json($payload, 200);
    }

    /**
     * @return array{0: Venta|DevolucionVenta|Compra|Gasto, 1: mixed, 2: string, 3: ?array}|null
     */
    private function resolverContextoDteCostaRica(int $id, string $tipo, ?string $queryTipo): ?array
    {
        $bloqueNd = null;

        if (in_array($tipo, ['01', '04', '11'], true)) {
            $registro = Venta::findOrFail($id);
            $this->assertMismoEmpresaUsuario($registro);

            return [$registro, $registro->dte, (string) ($registro->codigo_generacion ?? ''), null];
        }

        if ($tipo === '02') {
            $venta = Venta::findOrFail($id);
            $this->assertMismoEmpresaUsuario($venta);
            $devNd = DevolucionVenta::query()
                ->where('id_venta', $venta->id)
                ->whereNotNull('codigo_generacion')
                ->orderByDesc('id')
                ->first();
            if ($devNd !== null) {
                return [$devNd, $devNd->dte, (string) ($devNd->codigo_generacion ?? ''), null];
            }
            $ventaDte = $venta->dte;
            $bloqueNd = is_array($ventaDte) ? ($ventaDte['cr']['nota_debito'] ?? null) : null;

            return [$venta, $ventaDte, '', is_array($bloqueNd) ? $bloqueNd : null];
        }

        if (in_array($tipo, ['03', '05', '06'], true)) {
            $registro = DevolucionVenta::findOrFail($id);
            $this->assertMismoEmpresaUsuario($registro);

            return [$registro, $registro->dte, (string) ($registro->codigo_generacion ?? ''), null];
        }

        if (($tipo === '08' && $queryTipo === 'compra') || ($tipo === '14' && $queryTipo === 'compra')) {
            $registro = Compra::findOrFail($id);
            $this->assertMismoEmpresaUsuarioCompraGasto($registro);
            if ((string) ($registro->tipo_dte ?? '') !== '08') {
                throw new RuntimeException('Esta compra no tiene FEC (08).');
            }

            return [$registro, $registro->dte, (string) ($registro->codigo_generacion ?? ''), null];
        }

        if ($tipo === '08' && $queryTipo === 'gasto') {
            $registro = Gasto::findOrFail($id);
            $this->assertMismoEmpresaUsuarioCompraGasto($registro);
            if ((string) ($registro->tipo_dte ?? '') !== '08') {
                throw new RuntimeException('Este egreso no tiene FEC (08).');
            }

            return [$registro, $registro->dte, (string) ($registro->codigo_generacion ?? ''), null];
        }

        return null;
    }

    public function enviarDTE(EnviarDTERequest $request)
    {
        $empresa = Auth::user()?->empresa;
        if (FacturacionElectronicaCountryResolver::codPais($empresa) === FacturacionElectronicaCountryResolver::CODIGO_COSTA_RICA) {
            $uid = Auth::user()?->id_empresa;
            if ($uid === null) {
                return response()->json(['error' => 'No autenticado.'], 401);
            }

            return $this->costaRicaFeCorreo->enviarComprobante($request, $empresa, (int) $uid);
        }

        if ($guard = FacturacionElectronicaCountryGate::ensureSvDteOrFail($empresa)) {
            return $guard;
        }

        return $this->elSalvadorDte->enviarDTE($request);
    }

    public function consultarDTE(ConsultarDTERequest $request)
    {
        if ($guard = FacturacionElectronicaCountryGate::ensureSvDteOrFail(Auth::user()?->empresa)) {
            return $guard;
        }

        return response()->json($this->elSalvadorDte->consultarDTE($request));
    }
}
