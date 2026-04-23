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
use App\Services\FacturacionElectronica\ElSalvador\ElSalvadorDteService;
use App\Services\FacturacionElectronica\FacturacionElectronicaCountryGate;
use App\Services\FacturacionElectronica\FacturacionElectronicaCountryResolver;
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
    ) {}

    public function generarDTE(GenerarDTERequest $request)
    {
        $venta = Venta::where('id', $request->id)->with('detalles', 'cliente', 'empresa')->firstOrFail();

        if ($guard = FacturacionElectronicaCountryGate::ensureSvDteOrFail($venta->empresa)) {
            return $guard;
        }

        return $this->elSalvadorDte->generarDTE($venta);
    }

    public function generarDTENotaCredito(GenerarDTENotaCreditoRequest $request)
    {
        $devolucion = DevolucionVenta::where('id', $request->id)->with('detalles', 'cliente', 'empresa', 'venta')->firstOrFail();

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

    //xLa emisión de sujeto excluido debe reflejar retención y totales del documento el cliente envía el registro completo en el POST, pero históricamente solo se usaba el id
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
     * PDF resumen del comprobante CR (payload guardado en BD), misma ruta que SV (/reporte/dte/...).
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
     * XML devuelto por Hacienda al consultar estado (si está almacenado en dte.cr).
     */
    public function generarDteXmlCostaRica(Request $request, int|string $id, string $tipo): JsonResponse|Response
    {
        $empresa = Auth::user()?->empresa;
        if (FacturacionElectronicaCountryResolver::codPais($empresa) !== FacturacionElectronicaCountryResolver::CODIGO_COSTA_RICA) {
            return response()->json(['error' => 'Solo aplica a empresas con facturación Costa Rica.'], 400);
        }

        $id = (int) $id;
        $xml = null;

        if (in_array($tipo, ['01', '04', '11'], true)) {
            $registro = Venta::findOrFail($id);
            $this->assertMismoEmpresaUsuario($registro);
            $dte = $registro->dte;
            if (! is_array($dte) || ($dte['pais'] ?? null) !== 'CR') {
                return response()->json(['error' => 'No hay comprobante electrónico Costa Rica para este registro.'], 404);
            }
            $xml = $dte['cr']['estado_consulta']['response_xml'] ?? null;
        } elseif ($tipo === '02') {
            $registro = Venta::findOrFail($id);
            $this->assertMismoEmpresaUsuario($registro);
            $dte = $registro->dte;
            if (! is_array($dte) || ($dte['pais'] ?? null) !== 'CR') {
                return response()->json(['error' => 'No hay comprobante electrónico Costa Rica para este registro.'], 404);
            }
            $nd = $dte['cr']['nota_debito'] ?? null;
            $xml = is_array($nd) ? ($nd['estado_consulta']['response_xml'] ?? null) : null;
        } elseif (in_array($tipo, ['03', '05', '06'], true)) {
            $registro = DevolucionVenta::findOrFail($id);
            $this->assertMismoEmpresaUsuario($registro);
            $dte = $registro->dte;
            if (! is_array($dte) || ($dte['pais'] ?? null) !== 'CR') {
                return response()->json(['error' => 'No hay comprobante electrónico Costa Rica para esta devolución.'], 404);
            }
            $xml = $dte['cr']['estado_consulta']['response_xml'] ?? null;
        } elseif (($tipo === '08' && $request->query('tipo') === 'compra') || ($tipo === '14' && $request->query('tipo') === 'compra')) {
            $registro = Compra::findOrFail($id);
            $this->assertMismoEmpresaUsuarioCompraGasto($registro);
            $dte = $registro->dte;
            if (! is_array($dte) || ($dte['pais'] ?? null) !== 'CR') {
                return response()->json(['error' => 'No hay comprobante electrónico Costa Rica para esta compra.'], 404);
            }
            if ((string) ($dte['identificacion']['tipoDte'] ?? $registro->tipo_dte ?? '') !== '08') {
                return response()->json(['error' => 'Esta compra no tiene FEC (08).'], 404);
            }
            $xml = $dte['cr']['estado_consulta']['response_xml'] ?? null;
        } elseif ($tipo === '08' && $request->query('tipo') === 'gasto') {
            $registro = Gasto::findOrFail($id);
            $this->assertMismoEmpresaUsuarioCompraGasto($registro);
            $dte = $registro->dte;
            if (! is_array($dte) || ($dte['pais'] ?? null) !== 'CR') {
                return response()->json(['error' => 'No hay comprobante electrónico Costa Rica para este egreso.'], 404);
            }
            if ((string) ($dte['identificacion']['tipoDte'] ?? $registro->tipo_dte ?? '') !== '08') {
                return response()->json(['error' => 'Este egreso no tiene FEC (08).'], 404);
            }
            $xml = $dte['cr']['estado_consulta']['response_xml'] ?? null;
        } else {
            return response()->json(['error' => 'Tipo de documento no disponible.'], 400);
        }

        if (! is_string($xml) || trim($xml) === '') {
            return response()->json([
                'error' => 'No hay XML de respuesta de Hacienda guardado. Use «Consultar estado en Hacienda» y vuelva a intentar.',
            ], 404);
        }

        $xml = XmlRespuestaHaciendaCr::normalizar($xml);

        $claveArchivo = (string) $id;
        if ($registro instanceof Venta) {
            $claveArchivo = (string) ($registro->codigo_generacion ?: $id);
        } elseif ($registro instanceof DevolucionVenta) {
            $claveArchivo = (string) ($registro->codigo_generacion ?: $id);
        } elseif ($registro instanceof Compra || $registro instanceof Gasto) {
            $claveArchivo = (string) ($registro->codigo_generacion ?: $id);
        }
        $fname = 'respuesta-hacienda-'.preg_replace('/\W+/', '-', $claveArchivo).'.xml';

        // text/plain evita que Chrome/Firefox ejecuten el validador XML del navegador, que falla con
        // respuestas de Hacienda aunque el XML sea usable (p. ej. segunda <?xml o PI mezclados).
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
     * Misma ruta que El Salvador (/reporte/dte-json/...): devuelve el JSON del comprobante guardado en BD.
     * El payload enviado a DGT queda en dte.documento (venta / devolución) o en dte.cr.nota_debito.documento.
     */
    private function generarDteJsonCostaRica(int|string $id, string $tipo, GenerarDTEJSONRequest $request): JsonResponse
    {
        $id = (int) $id;

        // Factura / tiquete / exportación: venta
        if (in_array($tipo, ['01', '04', '11'], true)) {
            $registro = Venta::findOrFail($id);
            $dte = $registro->dte;
            if (! is_array($dte) || ($dte['pais'] ?? null) !== 'CR') {
                return response()->json(['error' => 'No hay comprobante electrónico Costa Rica para este registro.'], 404);
            }
            $payload = $dte['documento'] ?? $dte;

            return response()->json($payload, 200);
        }

        // Nota de débito (02): misma venta factura, datos en dte.cr.nota_debito
        if ($tipo === '02') {
            $venta = Venta::findOrFail($id);
            $dte = $venta->dte;
            if (! is_array($dte) || ($dte['pais'] ?? null) !== 'CR') {
                return response()->json(['error' => 'No hay comprobante electrónico Costa Rica para este registro.'], 404);
            }
            $nd = $dte['cr']['nota_debito'] ?? null;
            if (! is_array($nd)) {
                return response()->json(['error' => 'No hay nota de débito electrónica asociada.'], 404);
            }
            $payload = $nd['documento'] ?? $nd;

            return response()->json($payload, 200);
        }

        // Nota de crédito (devolución): 03 (tipo_dte CR), 05/06 (misma convención que pantalla devoluciones SV)
        if (in_array($tipo, ['03', '05', '06'], true)) {
            $registro = DevolucionVenta::findOrFail($id);
            $dte = $registro->dte;
            if (! is_array($dte) || ($dte['pais'] ?? null) !== 'CR') {
                return response()->json(['error' => 'No hay comprobante electrónico Costa Rica para esta devolución.'], 404);
            }
            $payload = $dte['documento'] ?? $dte;

            return response()->json($payload, 200);
        }

        // FEC 08 — factura electrónica de compra (misma ruta que SV usa 14+sujeto excluido para compras)
        if (($tipo === '08' && $request->query('tipo') === 'compra') || ($tipo === '14' && $request->query('tipo') === 'compra')) {
            $registro = Compra::findOrFail($id);
            $dte = $registro->dte;
            if (! is_array($dte) || ($dte['pais'] ?? null) !== 'CR') {
                return response()->json(['error' => 'No hay comprobante electrónico Costa Rica para esta compra.'], 404);
            }
            if ((string) ($dte['identificacion']['tipoDte'] ?? $registro->tipo_dte ?? '') !== '08') {
                return response()->json(['error' => 'Esta compra no tiene FEC (08).'], 404);
            }
            $payload = $dte['documento'] ?? [];

            return response()->json(is_array($payload) ? $payload : [], 200);
        }

        if ($tipo === '08' && $request->query('tipo') === 'gasto') {
            $registro = Gasto::findOrFail($id);
            $dte = $registro->dte;
            if (! is_array($dte) || ($dte['pais'] ?? null) !== 'CR') {
                return response()->json(['error' => 'No hay comprobante electrónico Costa Rica para este egreso.'], 404);
            }
            if ((string) ($dte['identificacion']['tipoDte'] ?? $registro->tipo_dte ?? '') !== '08') {
                return response()->json(['error' => 'Este egreso no tiene FEC (08).'], 404);
            }
            $payload = $dte['documento'] ?? [];

            return response()->json(is_array($payload) ? $payload : [], 200);
        }

        return response()->json(['error' => 'Tipo de documento no disponible para descarga JSON en FE Costa Rica.'], 400);
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
