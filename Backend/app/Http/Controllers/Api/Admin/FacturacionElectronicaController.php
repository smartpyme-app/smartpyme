<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

use App\Services\FacturacionElectronica\FacturacionElectronicaService;
use App\Models\Ventas\Venta;
use App\Models\Compras\Compra;
use App\Models\Ventas\Devoluciones\Devolucion as DevolucionVenta;
use App\Models\Compras\Gastos\Gasto;
use Barryvdh\DomPDF\Facade\Pdf as PDF;

use App\Http\Requests\MH\GenerarDTERequest;
use App\Http\Requests\MH\GenerarDTENotaCreditoRequest;
use App\Http\Requests\MH\GenerarDTESujetoExcluidoGastoRequest;
use App\Http\Requests\MH\GenerarDTESujetoExcluidoCompraRequest;
use App\Http\Requests\MH\GenerarContingenciaRequest;
use App\Http\Requests\MH\GenerarDTEAnuladoRequest;
use App\Http\Requests\MH\AnularDTERequest;
use App\Http\Requests\MH\AnularDTESujetoExcluidoRequest;
use App\Http\Requests\MH\EnviarDTERequest;
use App\Http\Requests\MH\ConsultarDTERequest;
use Carbon\Carbon;

/**
 * Controlador para Facturación Electrónica Multi-País
 * 
 * Reemplaza MHDTEController con soporte para múltiples países
 * 
 * @package App\Http\Controllers\Api\Admin
 */
class FacturacionElectronicaController extends Controller
{
    protected $feService;

    public function __construct(FacturacionElectronicaService $feService)
    {
        $this->feService = $feService;
    }

    /**
     * Genera un DTE (Documento Tributario Electrónico)
     * 
     * @param GenerarDTERequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generarDTE(GenerarDTERequest $request)
    {
        try {
            $venta = Venta::where('id', $request->id)
                ->with('detalles', 'cliente', 'empresa', 'sucursal')
                ->firstOrFail();

            // Validar configuración de sucursal
            if (!$venta->sucursal || !$venta->sucursal->cod_estable_mh) {
                return response()->json([
                    'error' => 'Falta configurar los datos de la sucursal.'
                ], 400);
            }

            // Validar que la empresa tenga FE configurada
            if (!$venta->empresa->tieneFacturacionElectronica()) {
                return response()->json([
                    'error' => 'La empresa no tiene facturación electrónica configurada.'
                ], 400);
            }

            // Generar DTE usando el servicio
            $DTE = $this->feService->generarDTE($venta);

            return response()->json($DTE, 200);

        } catch (\Exception $e) {
            Log::error('Error al generar DTE', [
                'venta_id' => $request->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Error al generar DTE: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Genera un DTE de Nota de Crédito o Nota de Débito
     * 
     * @param GenerarDTENotaCreditoRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generarDTENotaCredito(GenerarDTENotaCreditoRequest $request)
    {
        try {
            $devolucion = DevolucionVenta::where('id', $request->id)
                ->with('detalles', 'cliente', 'empresa', 'venta', 'usuario.sucursal')
                ->firstOrFail();

            if (!$devolucion->venta) {
                return response()->json([
                    'error' => 'La devolución no tiene una venta asignada.'
                ], 400);
            }

            // Validar que la empresa tenga FE configurada
            if (!$devolucion->empresa->tieneFacturacionElectronica()) {
                return response()->json([
                    'error' => 'La empresa no tiene facturación electrónica configurada.'
                ], 400);
            }

            // Validar tipo de documento
            if (!in_array($devolucion->nombre_documento, ['Nota de crédito', 'Nota de débito'])) {
                return response()->json([
                    'error' => 'Tipo de documento no válido, debe ser Nota de crédito o nota de débito.'
                ], 400);
            }

            // Generar DTE usando el servicio
            $DTE = $this->feService->generarDTE($devolucion);

            return response()->json($DTE, 200);

        } catch (\Exception $e) {
            Log::error('Error al generar DTE Nota de Crédito/Débito', [
                'devolucion_id' => $request->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Error al generar DTE: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Genera DTE de Sujeto Excluido (Gasto)
     * 
     * @param GenerarDTESujetoExcluidoGastoRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generarDTESujetoExcluidoGasto(GenerarDTESujetoExcluidoGastoRequest $request)
    {
        try {
            $gasto = Gasto::where('id', $request->id)
                ->with('proveedor', 'empresa')
                ->firstOrFail();

            // TODO: Implementar cuando se migren los modelos de Sujeto Excluido
            // Por ahora mantenemos compatibilidad con el modelo antiguo
            return response()->json([
                'error' => 'Funcionalidad de Sujeto Excluido en proceso de migración.'
            ], 501);

        } catch (\Exception $e) {
            Log::error('Error al generar DTE Sujeto Excluido Gasto', [
                'gasto_id' => $request->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Error al generar DTE: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Genera DTE de Sujeto Excluido (Compra)
     * 
     * @param GenerarDTESujetoExcluidoCompraRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generarDTESujetoExcluidoCompra(GenerarDTESujetoExcluidoCompraRequest $request)
    {
        try {
            $compra = Compra::where('id', $request->id)
                ->with('detalles', 'proveedor', 'empresa')
                ->firstOrFail();

            // TODO: Implementar cuando se migren los modelos de Sujeto Excluido
            return response()->json([
                'error' => 'Funcionalidad de Sujeto Excluido en proceso de migración.'
            ], 501);

        } catch (\Exception $e) {
            Log::error('Error al generar DTE Sujeto Excluido Compra', [
                'compra_id' => $request->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Error al generar DTE: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Anula un DTE
     * 
     * @param AnularDTERequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function anularDTE(AnularDTERequest $request)
    {
        try {
            $venta = Venta::where('id', $request->id)
                ->with('empresa')
                ->firstOrFail();

            $DTE = is_string($venta->dte) ? json_decode($venta->dte, true) : $venta->dte;

            if (!$DTE) {
                return response()->json([
                    'error' => 'El documento no tiene DTE para anular.'
                ], 400);
            }

            // Anular usando el servicio
            $resultado = $this->feService->anularDTE($DTE, $venta);

            // Si la anulación fue exitosa, actualizar el estado
            if (isset($resultado['estado']) && $resultado['estado'] == 'PROCESADO' && isset($resultado['selloRecibido'])) {
                $venta->estado = 'Anulada';
                $venta->dte_invalidacion = $resultado;
                $venta->save();

                return response()->json($resultado, 200);
            }

            return response()->json($resultado, 500);

        } catch (\Exception $e) {
            Log::error('Error al anular DTE', [
                'venta_id' => $request->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Error al anular DTE: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Consulta el estado de un DTE
     * 
     * @param ConsultarDTERequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function consultarDTE(ConsultarDTERequest $request)
    {
        try {
            // Obtener empresa desde el documento
            $venta = Venta::where('id', $request->id ?? null)
                ->with('empresa')
                ->first();

            if (!$venta) {
                // Consulta pública (sin empresa)
                $pais = $request->pais ?? 'SV';
                $config = config("facturacion_electronica.paises.{$pais}", []);
                $urlConsulta = $config['consulta_publica'] ?? null;

                if (!$urlConsulta) {
                    return response()->json([
                        'error' => 'Consulta pública no disponible para este país.'
                    ], 400);
                }

                $response = Http::get($urlConsulta, [
                    'codigoGeneracion' => $request->codigoGeneracion,
                    'fechaEmi' => $request->fechaEmi,
                    'ambiente' => $request->ambiente,
                ]);

                return response()->json($response->json(), $response->status());
            }

            // Consulta usando el servicio (con autenticación)
            $resultado = $this->feService->consultarDTE(
                $request->codigoGeneracion,
                $request->tipoDte ?? $venta->tipo_dte ?? '01',
                $venta
            );

            return response()->json($resultado, 200);

        } catch (\Exception $e) {
            Log::error('Error al consultar DTE', [
                'codigo_generacion' => $request->codigoGeneracion,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Error al consultar DTE: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Genera PDF del DTE
     * 
     * @param int $id
     * @param string $tipo
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function generarDTEPDF($id, $tipo, Request $request)
    {
        try {
            $registro = $this->obtenerRegistroPorTipo($id, $tipo, $request);

            if (!$registro) {
                return response()->json([
                    'error' => 'No se encontró el registro correspondiente.'
                ], 404);
            }

            $DTE = $registro->dte;

            if (!$DTE) {
                return response()->json([
                    'error' => 'El registro no tiene DTE.'
                ], 404);
            }

            // Obtener URL de consulta pública desde configuración
            $empresa = $registro->empresa ?? $registro->empresa()->first();
            $pais = $empresa->getFePais() ?? 'SV';
            $config = config("facturacion_electronica.paises.{$pais}", []);
            $urlConsultaPublica = $config['consulta_publica'] ?? 'https://admin.factura.gob.sv/consultaPublica';

            $registro->qr = $urlConsultaPublica . '?ambiente=' . $DTE['identificacion']['ambiente'] . 
                           '&codGen=' . $DTE['identificacion']['codigoGeneracion'] . 
                           '&fechaEmi=' . $DTE['identificacion']['fecEmi'];

            // Si está anulado
            if ($registro->dte_invalidacion) {
                $DTE = $registro->dte_invalidacion;
                $pdf = PDF::loadView('reportes.facturacion.DTE-Anulado', compact('registro', 'DTE'));
                $pdf->setPaper('US Letter', 'portrait');
                return $pdf->stream($DTE['identificacion']['codigoGeneracion'] . '.pdf');
            }

            // Generar PDF según tipo de documento
            $vista = $this->obtenerVistaPDF($DTE['identificacion']['tipoDte']);
            
            if (!$vista) {
                return response()->json([
                    'error' => 'Tipo de documento no soportado para PDF.'
                ], 400);
            }

            $pdf = PDF::loadView($vista, compact('registro', 'DTE'));
            $pdf->setPaper('US Letter', 'portrait');

            return $pdf->stream($DTE['identificacion']['codigoGeneracion'] . '.pdf');

        } catch (\Exception $e) {
            Log::error('Error al generar PDF DTE', [
                'id' => $id,
                'tipo' => $tipo,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Error al generar PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Genera JSON del DTE
     * 
     * @param int $id
     * @param string $tipo
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generarDTEJSON($id, $tipo, Request $request)
    {
        try {
            $registro = $this->obtenerRegistroPorTipo($id, $tipo, $request);

            if (!$registro) {
                return response()->json([
                    'error' => 'No se encontró el registro correspondiente.'
                ], 404);
            }

            $DTE = $registro->dte_invalidacion ?? $registro->dte;

            return response()->json($DTE, 200);

        } catch (\Exception $e) {
            Log::error('Error al generar JSON DTE', [
                'id' => $id,
                'tipo' => $tipo,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Error al generar JSON: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Envía DTE por correo electrónico
     * 
     * @param EnviarDTERequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function enviarDTE(EnviarDTERequest $request)
    {
        try {
            $registro = $this->obtenerRegistroPorTipoEnvio($request);
            $correo = $this->obtenerCorreoDestino($registro, $request);

            if (!$registro) {
                return response()->json([
                    'error' => 'No se encontró el registro correspondiente.'
                ], 404);
            }

            $DTE = $registro->dte;

            if (!$DTE) {
                return response()->json([
                    'error' => 'El registro no tiene DTE.'
                ], 404);
            }

            // Obtener URL de consulta pública desde configuración
            $empresa = $registro->empresa ?? $registro->empresa()->first();
            $pais = $empresa->getFePais() ?? 'SV';
            $config = config("facturacion_electronica.paises.{$pais}", []);
            $urlConsultaPublica = $config['consulta_publica'] ?? 'https://admin.factura.gob.sv/consultaPublica';

            $registro->qr = $urlConsultaPublica . '?ambiente=' . $DTE['identificacion']['ambiente'] . 
                           '&codGen=' . $DTE['identificacion']['codigoGeneracion'] . 
                           '&fechaEmi=' . $DTE['identificacion']['fecEmi'];

            // Si está anulado
            if ($registro->dte_invalidacion) {
                $DTE = $registro->dte_invalidacion;
                $nombre = $DTE['documento']['nombre'] ?? $DTE['receptor']['nombre'] ?? 'Cliente';

                $pdf = PDF::loadView('reportes.facturacion.DTE-Anulado', compact('registro', 'DTE'));
                $pdfContent = $pdf->output();

                if ($correo) {
                    Mail::send('mails.DTE-Anulado', ['DTE' => $DTE, 'nombre' => $nombre], function ($m) use ($pdfContent, $DTE, $correo, $nombre, $empresa) {
                        $m->from('noreply@smartpyme.sv', $empresa->nombre ?? $DTE['emisor']['nombre'])
                            ->to($correo, $nombre)
                            ->attachData($pdfContent, $DTE['identificacion']['codigoGeneracion'] . '.pdf', [
                                'mime' => 'application/pdf',
                            ])
                            ->attachData(json_encode($DTE), $DTE['identificacion']['codigoGeneracion'] . '.json', [
                                'mime' => 'application/json',
                            ])
                            ->subject('Documento Tributario Electrónico Anulado');
                    });

                    return response()->json($DTE, 200);
                }

                return response()->json(['error' => 'El cliente no tiene correo'], 400);
            }

            // Generar PDF según tipo
            $vista = $this->obtenerVistaPDF($DTE['identificacion']['tipoDte']);
            
            if (!$vista) {
                return response()->json([
                    'error' => 'Tipo de documento no soportado.'
                ], 400);
            }

            $pdf = PDF::loadView($vista, compact('registro', 'DTE'));
            $pdfContent = $pdf->output();

            // Obtener nombre del receptor
            $nombre = $this->obtenerNombreReceptor($DTE);

            if ($correo) {
                Mail::send('mails.DTE', ['DTE' => $DTE, 'nombre' => $nombre], function ($m) use ($pdfContent, $DTE, $correo, $nombre, $empresa) {
                    $m->from('noreply@smartpyme.sv', $empresa->nombre ?? $DTE['emisor']['nombre'])
                        ->to($correo, $nombre)
                        ->attachData($pdfContent, $DTE['identificacion']['codigoGeneracion'] . '.pdf', [
                            'mime' => 'application/pdf',
                        ])
                        ->attachData(json_encode($DTE), $DTE['identificacion']['codigoGeneracion'] . '.json', [
                            'mime' => 'application/json',
                        ])
                        ->subject('Documento Tributario Electrónico');
                });

                return response()->json($DTE, 200);
            }

            return response()->json(['error' => 'Registro sin correo electrónico'], 400);

        } catch (\Exception $e) {
            Log::error('Error al enviar DTE por correo', [
                'request' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Error al enviar DTE: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene el registro según el tipo de documento
     * 
     * @param int $id
     * @param string $tipo
     * @param Request $request
     * @return mixed
     */
    private function obtenerRegistroPorTipo($id, $tipo, Request $request)
    {
        if (in_array($tipo, ['01', '03', '11'])) {
            return Venta::findOrFail($id);
        }

        if (in_array($tipo, ['05', '06'])) {
            return DevolucionVenta::findOrFail($id);
        }

        if ($tipo == '14') {
            if ($request->tipo == 'compra') {
                return Compra::findOrFail($id);
            }
            if ($request->tipo == 'gasto') {
                return Gasto::findOrFail($id);
            }
        }

        return null;
    }

    /**
     * Obtiene el registro para envío por correo
     * 
     * @param Request $request
     * @return mixed
     */
    private function obtenerRegistroPorTipoEnvio(Request $request)
    {
        $tipoDte = $request->tipo_dte;

        if (in_array($tipoDte, ['01', '03', '11'])) {
            return Venta::with('cliente')->where('id', $request->id)->first();
        }

        if (in_array($tipoDte, ['05', '06'])) {
            return DevolucionVenta::with('cliente')->where('id', $request->id)->first();
        }

        if ($tipoDte == '14') {
            if ($request->tipo == 'compra') {
                return Compra::with('proveedor')->where('id', $request->id)->first();
            }
            if ($request->tipo == 'gasto') {
                return Gasto::with('proveedor')->where('id', $request->id)->first();
            }
        }

        return null;
    }

    /**
     * Obtiene el correo destino según el tipo de registro
     * 
     * @param mixed $registro
     * @param Request $request
     * @return string|null
     */
    private function obtenerCorreoDestino($registro, Request $request)
    {
        if (!$registro) {
            return null;
        }

        $tipoDte = $request->tipo_dte;

        if (in_array($tipoDte, ['01', '03', '11', '05', '06'])) {
            return $registro->cliente ? $registro->cliente->correo : null;
        }

        if ($tipoDte == '14') {
            $proveedor = $registro->proveedor ?? $registro->proveedor()->first();
            return $proveedor ? $proveedor->correo : null;
        }

        return null;
    }

    /**
     * Obtiene la vista PDF según el tipo de DTE
     * 
     * @param string $tipoDte
     * @return string|null
     */
    private function obtenerVistaPDF($tipoDte)
    {
        $vistas = [
            '01' => 'reportes.facturacion.DTE-Factura',
            '03' => 'reportes.facturacion.DTE-CCF',
            '05' => 'reportes.facturacion.DTE-Nota-Credito',
            '06' => 'reportes.facturacion.DTE-Nota-Debito',
            '11' => 'reportes.facturacion.DTE-Factura-Exportacion',
            '14' => 'reportes.facturacion.DTE-Sujeto-Excluido',
        ];

        return $vistas[$tipoDte] ?? null;
    }

    /**
     * Obtiene el nombre del receptor desde el DTE
     * 
     * @param array $DTE
     * @return string
     */
    private function obtenerNombreReceptor(array $DTE)
    {
        $tipoDte = $DTE['identificacion']['tipoDte'] ?? '01';

        if (in_array($tipoDte, ['01', '03', '05', '06', '11'])) {
            return $DTE['receptor']['nombre'] ?? 'Cliente';
        }

        if ($tipoDte == '14') {
            return $DTE['sujetoExcluido']['nombre'] ?? 'Cliente';
        }

        return 'Cliente';
    }

    /**
     * Genera documento de anulación (sin anular)
     * 
     * @param GenerarDTEAnuladoRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generarDTEAnulado(GenerarDTEAnuladoRequest $request)
    {
        try {
            if ($request->tipo_dte == '05' || $request->tipo_dte == '06') {
                $documento = DevolucionVenta::where('id', $request->id)->firstOrFail();
            } else {
                $documento = Venta::where('id', $request->id)->firstOrFail();
            }
            
            $dte = is_string($documento->dte) ? json_decode($documento->dte, true) : $documento->dte;
            
            if (!$dte) {
                return response()->json([
                    'error' => 'El documento no tiene DTE para anular.'
                ], 400);
            }

            $dteAnular = $this->feService->generarDTEAnulado($dte, $documento);

            return response()->json($dteAnular, 200);

        } catch (\Exception $e) {
            Log::error('Error al generar DTE anulado', [
                'id' => $request->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Error al generar DTE anulado: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Genera documento de anulación para sujeto excluido (compra)
     * 
     * @param GenerarDTESujetoExcluidoCompraRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generarDTEAnuladoSujetoExcluidoCompra(GenerarDTESujetoExcluidoCompraRequest $request)
    {
        try {
            $compra = Compra::where('id', $request->id)->firstOrFail();
            
            $dte = is_string($compra->dte) ? json_decode($compra->dte, true) : $compra->dte;
            
            if (!$dte) {
                return response()->json([
                    'error' => 'La compra no tiene DTE para anular.'
                ], 400);
            }

            $dteAnular = $this->feService->generarDTEAnulado($dte, $compra);

            return response()->json($dteAnular, 200);

        } catch (\Exception $e) {
            Log::error('Error al generar DTE anulado sujeto excluido compra', [
                'id' => $request->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Error al generar DTE anulado: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Genera documento de anulación para sujeto excluido (gasto)
     * 
     * @param GenerarDTESujetoExcluidoGastoRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generarDTEAnuladoSujetoExcluidoGasto(GenerarDTESujetoExcluidoGastoRequest $request)
    {
        try {
            $gasto = Gasto::where('id', $request->id)->firstOrFail();
            
            $dte = is_string($gasto->dte) ? json_decode($gasto->dte, true) : $gasto->dte;
            
            if (!$dte) {
                return response()->json([
                    'error' => 'El gasto no tiene DTE para anular.'
                ], 400);
            }

            $dteAnular = $this->feService->generarDTEAnulado($dte, $gasto);

            return response()->json($dteAnular, 200);

        } catch (\Exception $e) {
            Log::error('Error al generar DTE anulado sujeto excluido gasto', [
                'id' => $request->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Error al generar DTE anulado: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Genera documento de contingencia
     * 
     * @param GenerarContingenciaRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generarContingencia(GenerarContingenciaRequest $request)
    {
        try {
            $ventas = Venta::whereIn('id', [$request->id])
                ->with('detalles', 'empresa')
                ->get();

            if ($ventas->isEmpty()) {
                return response()->json([
                    'error' => 'No se encontraron ventas.'
                ], 404);
            }

            $empresa = $ventas[0]->empresa;
            $dtes = collect();

            foreach ($ventas as $venta) {
                if (!$venta->dte) {
                    continue;
                }
                
                $dte = is_string($venta->dte) ? json_decode($venta->dte, true) : $venta->dte;
                if ($dte) {
                    $dtes->push($dte);
                }
            }

            if ($dtes->isEmpty()) {
                return response()->json([
                    'error' => 'No se generó ningún DTE para la contingencia.'
                ], 400);
            }

            $contingencia = $this->feService->generarContingencia($dtes->toArray(), $empresa, 3);

            return response()->json($contingencia, 200);

        } catch (\Exception $e) {
            Log::error('Error al generar contingencia', [
                'id' => $request->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Error al generar contingencia: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Genera ticket de DTE
     * 
     * @param int $id
     * @return \Illuminate\View\View
     */
    public function generarTicket($id)
    {
        try {
            $venta = Venta::where('id', $id)
                ->with('detalles', 'cliente', 'empresa')
                ->firstOrFail();

            $dte = is_string($venta->dte) ? json_decode($venta->dte, true) : $venta->dte;

            if (!$dte) {
                abort(404, 'El documento no tiene DTE.');
            }

            // Obtener URL de consulta pública desde configuración
            $empresa = $venta->empresa;
            $pais = $empresa->getFePais() ?? 'SV';
            $config = config("facturacion_electronica.paises.{$pais}", []);
            $urlConsultaPublica = $config['consulta_publica'] ?? 'https://admin.factura.gob.sv/consultaPublica';

            $venta->qr = $urlConsultaPublica . '?ambiente=' . $dte['identificacion']['ambiente'] . 
                       '&codGen=' . $dte['identificacion']['codigoGeneracion'] . 
                       '&fechaEmi=' . $dte['identificacion']['fecEmi'];

            $DTE = $dte;
            return view('reportes.DTE-Ticket', compact('venta', 'DTE'));

        } catch (\Exception $e) {
            Log::error('Error al generar ticket', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            abort(500, 'Error al generar ticket: ' . $e->getMessage());
        }
    }
}
