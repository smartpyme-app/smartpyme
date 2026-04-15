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
use App\Services\FacturacionElectronica\ElSalvador\ElSalvadorDteService;
use App\Services\FacturacionElectronica\FacturacionElectronicaCountryGate;
use App\Services\FacturacionElectronica\FacturacionElectronicaCountryResolver;
use App\Support\FacturacionElectronica\XmlRespuestaHaciendaCr;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class MHDTEController extends Controller
{
    public function __construct(
        private readonly ElSalvadorDteService $elSalvadorDte
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

        if ($guard = FacturacionElectronicaCountryGate::ensureSvDteOrFail($gasto->empresa)) {
            return $guard;
        }

        return $this->elSalvadorDte->generarDTESujetoExcluidoGasto($gasto);
    }

    public function generarDTESujetoExcluidoCompra(GenerarDTESujetoExcluidoCompraRequest $request)
    {
        $compra = Compra::where('id', $request->id)->with('detalles', 'proveedor', 'empresa')->firstOrFail();

        if ($guard = FacturacionElectronicaCountryGate::ensureSvDteOrFail($compra->empresa)) {
            return $guard;
        }

        return $this->elSalvadorDte->generarDTESujetoExcluidoCompra($compra);
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
        $id = (int) $id;
        $registro = null;
        $documento = [];
        $clave = '';
        $titulo = 'Comprobante electrónico';
        $tipoDteRuta = (string) $tipo;

        if (in_array($tipo, ['01', '04', '11'], true)) {
            $registro = Venta::query()->with(['empresa', 'cliente'])->findOrFail($id);
            $this->assertMismoEmpresaUsuario($registro);
            $dte = $registro->dte;
            if (! is_array($dte) || ($dte['pais'] ?? null) !== 'CR') {
                abort(404, 'No hay comprobante electrónico Costa Rica para este registro.');
            }
            $documento = is_array($dte['documento'] ?? null) ? $dte['documento'] : [];
            $clave = (string) ($dte['clave'] ?? $registro->codigo_generacion ?? '');
            $titulo = match ($tipo) {
                '04' => 'Tiquete electrónico',
                '11' => 'Factura de exportación',
                default => 'Factura electrónica',
            };
        } elseif ($tipo === '02') {
            $registro = Venta::query()->with(['empresa', 'cliente'])->findOrFail($id);
            $this->assertMismoEmpresaUsuario($registro);
            $dte = $registro->dte;
            if (! is_array($dte) || ($dte['pais'] ?? null) !== 'CR') {
                abort(404, 'No hay comprobante electrónico Costa Rica para este registro.');
            }
            $nd = $dte['cr']['nota_debito'] ?? null;
            if (! is_array($nd)) {
                abort(404, 'No hay nota de débito electrónica asociada.');
            }
            $documento = is_array($nd['documento'] ?? null) ? $nd['documento'] : [];
            $clave = (string) ($nd['clave'] ?? '');
            $titulo = 'Nota de débito electrónica';
        } elseif (in_array($tipo, ['03', '05', '06'], true)) {
            $registro = DevolucionVenta::query()->with(['empresa', 'cliente'])->findOrFail($id);
            $this->assertMismoEmpresaUsuario($registro);
            $dte = $registro->dte;
            if (! is_array($dte) || ($dte['pais'] ?? null) !== 'CR') {
                abort(404, 'No hay comprobante electrónico Costa Rica para esta devolución.');
            }
            $documento = is_array($dte['documento'] ?? null) ? $dte['documento'] : [];
            $clave = (string) ($dte['clave'] ?? $registro->codigo_generacion ?? '');
            $titulo = 'Nota de crédito electrónica';
        } elseif (($tipo === '08' && $request->query('tipo') === 'compra') || ($tipo === '14' && $request->query('tipo') === 'compra')) {
            $registro = Compra::query()->with(['empresa', 'proveedor'])->findOrFail($id);
            $this->assertMismoEmpresaUsuarioCompraGasto($registro);
            $dte = $registro->dte;
            if (! is_array($dte) || ($dte['pais'] ?? null) !== 'CR') {
                abort(404, 'No hay comprobante electrónico Costa Rica para esta compra.');
            }
            if ((string) ($dte['identificacion']['tipoDte'] ?? $registro->tipo_dte ?? '') !== '08') {
                abort(404, 'Esta compra no tiene factura electrónica de compras (FEC, tipo 08).');
            }
            $documento = is_array($dte['documento'] ?? null) ? $dte['documento'] : [];
            $clave = (string) ($dte['clave'] ?? $registro->codigo_generacion ?? '');
            $titulo = 'Factura electrónica de compra';
            $tipoDteRuta = '08';
        } elseif ($tipo === '08' && $request->query('tipo') === 'gasto') {
            $registro = Gasto::query()->with(['empresa', 'proveedor'])->findOrFail($id);
            $this->assertMismoEmpresaUsuarioCompraGasto($registro);
            $dte = $registro->dte;
            if (! is_array($dte) || ($dte['pais'] ?? null) !== 'CR') {
                abort(404, 'No hay comprobante electrónico Costa Rica para este egreso.');
            }
            if ((string) ($dte['identificacion']['tipoDte'] ?? $registro->tipo_dte ?? '') !== '08') {
                abort(404, 'Este egreso no tiene factura electrónica de compras (FEC, tipo 08).');
            }
            $documento = is_array($dte['documento'] ?? null) ? $dte['documento'] : [];
            $clave = (string) ($dte['clave'] ?? $registro->codigo_generacion ?? '');
            $titulo = 'Factura electrónica de compra';
            $tipoDteRuta = '08';
        } else {
            abort(400, 'Tipo de documento no disponible para PDF en FE Costa Rica.');
        }

        // En DGT la factura de exportación puede emitirse con tipo 01 en el XML; el PDF debe reflejar
        // el documento de venta (catálogo 11 — factura electrónica de exportación).
        if (
            $registro instanceof Venta
            && in_array($tipo, ['01', '11'], true)
            && trim((string) ($registro->nombre_documento ?? '')) === 'Factura de exportación'
        ) {
            $tipoDteRuta = '11';
            $titulo = 'Factura de exportación';
        }

        $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.FE-CR-Comprobante', [
            'registro' => $registro,
            'documento' => $documento,
            'clave' => $clave,
            'titulo' => $titulo,
            'tipoDteCodigo' => $tipoDteRuta,
        ]);
        $pdf->setPaper('US Letter', 'portrait');
        $nombre = $clave !== '' ? $clave : 'comprobante-cr';

        return $pdf->stream($nombre.'.pdf');
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
        if ($guard = FacturacionElectronicaCountryGate::ensureSvDteOrFail(Auth::user()?->empresa)) {
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
