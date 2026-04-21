<?php

namespace App\Services\FacturacionElectronica\CostaRica;

use App\Http\Requests\MH\EnviarDTERequest;
use App\Mail\FeCrComprobanteClienteMailable;
use App\Models\Admin\Empresa;
use App\Models\Compras\Compra;
use App\Models\Compras\Gastos\Gasto;
use App\Models\Ventas\Devoluciones\Devolucion as DevolucionVenta;
use App\Models\Ventas\Venta;
use App\Support\FacturacionElectronica\XmlRespuestaHaciendaCr;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Envío por correo del comprobante electrónico Costa Rica (PDF + XML con clave + XML respuesta Hacienda).
 */
final class CostaRicaFeCorreoService
{
    public function __construct(
        private readonly CostaRicaFeComprobantePdfService $pdfService,
    ) {}

    public function enviarComprobante(EnviarDTERequest $request, Empresa $empresa, int $idEmpresaUsuario): JsonResponse
    {
        $tipoDte = $request->tipo_dte;
        $id = (int) $request->id;

        [$correo, $nombre, $registroModelo] = $this->resolverDestinatarioYRegistro($request);

        if (! is_string($correo) || trim($correo) === '' || ! filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['error' => 'Registro sin correo electrónico válido.'], 400);
        }

        [$tipoPdf, $queryTipo] = $this->resolverRutaPdf($tipoDte, $request->tipo);

        try {
            $datosPdf = $this->pdfService->datosVistaParaPdf($id, $tipoPdf, $queryTipo, $idEmpresaUsuario);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }

        $dte = $registroModelo->dte ?? null;
        if (! is_array($dte) || ($dte['pais'] ?? null) !== 'CR') {
            return response()->json(['error' => 'El registro no tiene comprobante electrónico Costa Rica.'], 404);
        }

        [$xmlFirmado, $xmlRespuesta] = $this->extraerXmlsParaAdjuntos($dte, $tipoDte);

        if (! is_string($xmlFirmado) || trim($xmlFirmado) === '') {
            return response()->json([
                'error' => 'No hay XML firmado del comprobante archivado. Vuelva a emitir el documento o contacte soporte (se requiere para adjuntar el XML con la clave de Hacienda).',
            ], 422);
        }

        if (! is_string($xmlRespuesta) || trim($xmlRespuesta) === '') {
            return response()->json([
                'error' => 'No hay XML de respuesta de Hacienda guardado. Use «Consultar estado en Hacienda» en el sistema y vuelva a enviar el correo.',
            ], 422);
        }

        $xmlRespuesta = XmlRespuestaHaciendaCr::normalizar($xmlRespuesta);

        $clave = (string) ($datosPdf['clave'] ?? '');
        $claveDigitos = preg_replace('/\D/', '', $clave) ?? '';
        $nombreBase = strlen($claveDigitos) >= 40 ? $claveDigitos : ($clave !== '' ? $clave : 'comprobante-'.$id);

        $pdfBin = $this->pdfService->pdfBinary($datosPdf);

        $documento = is_array($dte['documento'] ?? null) ? $dte['documento'] : [];
        $sum = is_array($documento['summary'] ?? null) ? $documento['summary'] : [];
        $total = isset($sum['total']) ? (float) $sum['total'] : (float) ($registroModelo->total ?? 0);
        $monedaCod = strtoupper((string) (($documento['currency']['currency_code'] ?? null) ?: 'CRC'));
        $simboloMonto = $monedaCod === 'USD' ? 'USD' : '₡';
        $montoTxt = $simboloMonto === 'USD'
            ? 'USD '.number_format($total, 2, '.', ',')
            : '₡ '.number_format($total, 2, '.', ',');

        $fechaEmi = (string) ($documento['date'] ?? '');
        $fechaFmt = '—';
        if ($fechaEmi !== '') {
            try {
                $fechaFmt = Carbon::parse($fechaEmi)->timezone('America/Costa_Rica')->format('j/n/Y H:i:s');
            } catch (\Throwable) {
                $fechaFmt = $fechaEmi;
            }
        }

        $tipoNombre = $this->etiquetaTipoDocumento((string) $datosPdf['tipoDteCodigo'], (string) ($datosPdf['titulo'] ?? ''));
        $consecutivo = $this->formatoConsecutivo($documento);

        $emisorNombre = (string) (($documento['issuer']['name'] ?? null) ?: $empresa->nombre);
        $emisorId = (string) (($documento['issuer']['identification_number'] ?? null) ?: ($empresa->nit ?? ''));

        $estadoTxt = 'Aceptado por el Ministerio de Hacienda';
        $est = is_array($dte['cr']['estado_consulta'] ?? null) ? $dte['cr']['estado_consulta'] : [];
        if (($est['success'] ?? null) === false) {
            $estadoTxt = (string) (($est['messages'] ?? '') ?: 'Estado en Hacienda no disponible');
        }

        $filas = [
            ['etiqueta' => 'Se ha generado un documento electrónico del tipo '.$tipoNombre.':', 'valor' => $consecutivo !== '' ? $consecutivo : '—'],
            ['etiqueta' => 'Clave:', 'valor' => $clave],
            ['etiqueta' => 'Emitido por:', 'valor' => trim($emisorId.' '.$emisorNombre)],
            ['etiqueta' => 'Fecha:', 'valor' => $fechaFmt],
            ['etiqueta' => 'Por un monto total de:', 'valor' => $montoTxt],
            ['etiqueta' => 'El comprobante se encuentra en estado:', 'valor' => $estadoTxt],
        ];

        $asunto = 'Comprobante electrónico — '.$tipoNombre.($consecutivo !== '' ? ' — '.$consecutivo : '');

        try {
            Mail::to($correo, $nombre)->send(new FeCrComprobanteClienteMailable(
                $empresa,
                $nombre,
                $filas,
                $asunto,
                $pdfBin,
                $nombreBase.'.pdf',
                $xmlFirmado,
                $nombreBase.'.xml',
                $xmlRespuesta,
                'respuesta-hacienda-'.$nombreBase.'.xml'
            ));
        } catch (\Throwable $e) {
            Log::error('FE CR correo cliente', ['error' => $e->getMessage(), 'id' => $id, 'tipo_dte' => $tipoDte]);

            return response()->json(['error' => 'No se pudo enviar el correo. Intente de nuevo o revise la configuración SMTP.'], 500);
        }

        return response()->json(['message' => 'Correo enviado.', 'dte' => $dte], 200);
    }

    /**
     * @return array{0: ?string, 1: Venta|DevolucionVenta|Compra|Gasto}
     */
    private function resolverDestinatarioYRegistro(EnviarDTERequest $request): array
    {
        $tipoDte = $request->tipo_dte;
        $id = (int) $request->id;

        if (in_array($tipoDte, ['01', '02', '04', '11'], true)) {
            $v = Venta::with('cliente')->where('id', $id)->firstOrFail();
            $c = $v->cliente;
            $nombre = $c ? trim((string) ($c->nombre ?? '')) : 'Cliente';
            $correo = $c ? $c->correo : null;

            return [$correo, $nombre !== '' ? $nombre : 'Cliente', $v];
        }

        if (in_array($tipoDte, ['03', '05', '06'], true)) {
            $d = DevolucionVenta::with('cliente')->where('id', $id)->firstOrFail();
            $c = $d->cliente;
            $nombre = $c ? trim((string) ($c->nombre ?? '')) : 'Cliente';
            $correo = $c ? $c->correo : null;

            return [$correo, $nombre !== '' ? $nombre : 'Cliente', $d];
        }

        if ($tipoDte === '14' || $tipoDte === '08') {
            if ($request->tipo === 'compra') {
                $c = Compra::with('proveedor')->where('id', $id)->firstOrFail();
                $p = $c->proveedor()->first();
                $nombre = $p ? trim((string) ($p->nombre ?? '')) : 'Proveedor';
                $correo = $p ? $p->correo : null;

                return [$correo, $nombre !== '' ? $nombre : 'Proveedor', $c];
            }
            if ($request->tipo === 'gasto') {
                $g = Gasto::with('proveedor')->where('id', $id)->firstOrFail();
                $p = $g->proveedor()->first();
                $nombre = $p ? trim((string) ($p->nombre ?? '')) : 'Proveedor';
                $correo = $p ? $p->correo : null;

                return [$correo, $nombre !== '' ? $nombre : 'Proveedor', $g];
            }
        }

        throw new RuntimeException('Tipo de documento no soportado para envío de correo FE Costa Rica.');
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function resolverRutaPdf(string $tipoDte, ?string $tipoCompraGasto): array
    {
        if (in_array($tipoDte, ['01', '02', '04', '11', '03', '05', '06'], true)) {
            return [$tipoDte, null];
        }
        if ($tipoDte === '14' || $tipoDte === '08') {
            $tipoPdf = '14';
            if ($tipoDte === '08') {
                $tipoPdf = '08';
            }

            return [$tipoPdf, $tipoCompraGasto];
        }

        return [$tipoDte, null];
    }

    /**
     * @param  array<string, mixed>  $dte
     * @return array{0: ?string, 1: ?string}
     */
    private function extraerXmlsParaAdjuntos(array $dte, string $tipoDte): array
    {
        $cr = is_array($dte['cr'] ?? null) ? $dte['cr'] : [];

        if ($tipoDte === '02') {
            $nd = is_array($cr['nota_debito'] ?? null) ? $cr['nota_debito'] : [];
            $xmlFirm = isset($nd['xml_comprobante_firmado']) && is_string($nd['xml_comprobante_firmado']) ? $nd['xml_comprobante_firmado'] : null;
            $est = is_array($nd['estado_consulta'] ?? null) ? $nd['estado_consulta'] : [];
            $xmlResp = isset($est['response_xml']) && is_string($est['response_xml']) ? $est['response_xml'] : null;

            return [$xmlFirm, $xmlResp];
        }

        $xmlFirm = isset($cr['xml_comprobante_firmado']) && is_string($cr['xml_comprobante_firmado']) ? $cr['xml_comprobante_firmado'] : null;
        $est = is_array($cr['estado_consulta'] ?? null) ? $cr['estado_consulta'] : [];
        $xmlResp = isset($est['response_xml']) && is_string($est['response_xml']) ? $est['response_xml'] : null;

        return [$xmlFirm, $xmlResp];
    }

    private function etiquetaTipoDocumento(string $tipoDteCodigo, string $tituloPdf): string
    {
        $m = [
            '01' => 'Factura electrónica',
            '04' => 'Tiquete electrónico',
            '11' => 'Factura electrónica de exportación',
            '02' => 'Nota de débito electrónica',
            '03' => 'Nota de crédito electrónica',
            '08' => 'Factura electrónica de compra',
        ];

        return $m[$tipoDteCodigo] ?? ($tituloPdf !== '' ? $tituloPdf : 'Comprobante electrónico');
    }

    /**
     * @param  array<string, mixed>  $documento
     */
    private function formatoConsecutivo(array $documento): string
    {
        $est = (string) ($documento['establishment'] ?? '');
        $punto = (string) ($documento['emission_point'] ?? '');
        $seq = (string) ($documento['sequential'] ?? '');
        $parts = array_filter([$est, $punto, $seq], static fn ($x) => $x !== '' && $x !== null);

        return implode('-', $parts);
    }
}
