<?php

namespace App\Services;

use App\Constants\FacturacionElectronica\FEConstants;
use App\Models\Admin\Documento;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Detalle;
use App\Models\MH\MHFactura;
use App\Models\MH\MHCCF;
use App\Models\MH\MHFacturaExportacion;
use App\Models\MH\MHNotaCredito;
use App\Models\MH\MHNotaDebito;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Ramsey\Uuid\Uuid;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Auth;

class MHPruebasMasivasService
{
    protected $baseVenta;
    protected $empresa;
    protected $sucursal;
    protected $ambiente;
    protected $simulationMode = true; // No afecta realmente los correlativos

    public function __construct() {}

    /**
     * Inicializar datos del usuario y empresa
     */
    protected function inicializarDatosUsuario()
    {
        // Comprobar si ya tenemos la empresa
        if (!$this->empresa) {
            $usuario = Auth::user();

            if (!$usuario) {
                throw new \Exception('No se pudo obtener el usuario autenticado');
            }

            $this->empresa = $usuario->empresa;

            if (!$this->empresa) {
                throw new \Exception('No se pudo obtener la empresa del usuario');
            }

            $this->ambiente = $this->empresa->fe_ambiente;
        }
    }

    /**
     * Obtener estadísticas de las pruebas realizadas
     */
    public function obtenerEstadisticas()
    {
        $this->inicializarDatosUsuario();

        // Obtener conteos de la base de datos para DTEs marcados como pruebas masivas
        $stats = [
            'facturas' => [
                'emitidas' => Venta::where('tipo_dte', FEConstants::TIPO_DTE_FACTURA_CONSUMIDOR_FINAL)
                    // ->where('prueba_masiva', true)
                    ->where('sello_mh', '!=', null)
                    ->where('dte', '!=', null)
                    ->where('id_empresa', $this->empresa->id)
                    ->count(),
                'requeridas' => 90
            ],
            'creditosFiscales' => [
                'emitidas' => Venta::where('tipo_dte', FEConstants::TIPO_DTE_COMPROBANTE_DE_CREDITO_FISCAL)
                    // ->where('prueba_masiva', true)
                    ->where('sello_mh', '!=', null)
                    ->where('dte', '!=', null)
                    ->where('id_empresa', $this->empresa->id)
                    ->count(),
                'requeridas' => 75
            ],
            // 'notasCredito' => [
            //     'emitidas' => Venta::where('tipo_dte', FEConstants::TIPO_DTE_NOTA_DE_CREDITO)
            //         // ->where('prueba_masiva', true)
            //         ->where('sello_mh', '!=', null)
            //         ->where('dte', '!=', null)
            //         ->where('id_empresa', $this->empresa->id)
            //         ->count(),
            //     'requeridas' => 10
            // ],
            // 'notasDebito' => [
            //     'emitidas' => Venta::where('tipo_dte', FEConstants::TIPO_DTE_NOTA_DE_DEBITO)
            //         // ->where('prueba_masiva', true)
            //         ->where('sello_mh', '!=', null)
            //         ->where('dte', '!=', null)
            //         ->where('id_empresa', $this->empresa->id)
            //         ->count(),
            //     'requeridas' => 10
            // ],
            // 'facturasExportacion' => [
            //     'emitidas' => Venta::where('tipo_dte', FEConstants::TIPO_DTE_FACTURAS_DE_EXPORTACION)
            //         // ->where('prueba_masiva', true)
            //         ->where('sello_mh', '!=', null)
            //         ->where('dte', '!=', null)
            //         ->where('id_empresa', $this->empresa->id)
            //         ->count(),
            //     'requeridas' => 5
            // ],
            // 'sujetoExcluido' => [
            //     'emitidas' => Venta::where('tipo_dte', FEConstants::TIPO_DTE_FACTURA_DE_SUJETO_EXCLUIDO)
            //         // ->where('prueba_masiva', true)
            //         ->where('sello_mh', '!=', null)
            //         ->where('dte', '!=', null)
            //         ->where('id_empresa', $this->empresa->id)
            //         ->count(),
            //     'requeridas' => 5
            // ],
        ];

        return $stats;
    }

    public function ejecutarPruebasMasivas($tipo, $cantidad, $idDocumentoBase = null)
    {
        $this->inicializarDatosUsuario();

        // Validar que estamos en ambiente de pruebas
        if ($this->ambiente !== '00') {
            return [
                'success' => false,
                'message' => 'Las pruebas masivas solo pueden ejecutarse en ambiente de pruebas'
            ];
        }

        // Obtener documento base
        if ($idDocumentoBase) {
            $this->baseVenta = Venta::with(['detalles', 'cliente', 'empresa', 'sucursal', 'documento'])
                ->findOrFail($idDocumentoBase);
        } else {
            // Buscar el último documento del tipo seleccionado que fue emitido exitosamente
            $this->baseVenta = Venta::with(['detalles', 'cliente', 'empresa', 'sucursal', 'documento'])
                ->where('tipo_dte', $tipo)
                ->where('sello_mh', '!=', null)
                ->where('id_empresa', $this->empresa->id)
                ->latest()
                ->first();
        }

        if (!$this->baseVenta) {
            return [
                'success' => false,
                'message' => 'No se encontró un documento base para generar las pruebas'
            ];
        }

        $this->empresa = $this->baseVenta->empresa;
        $this->sucursal = $this->baseVenta->sucursal;

        // Resultados del proceso
        $resultados = [
            'exitosos' => 0,
            'fallidos' => 0,
            'detalles' => []
        ];

        // Guardar el correlativo original para restaurarlo después
        $documento = $this->baseVenta->documento;
        $correlativoOriginal = $documento->correlativo;

        // Buscar el correlativo más alto existente para evitar duplicados
        $ultimoCorrelativo = Venta::where('tipo_dte', $tipo)
            ->where('id_empresa', $this->empresa->id)
            ->where('id_documento', $this->baseVenta->id_documento)
            ->max('correlativo');

        // Utilizar el máximo entre el correlativo del documento y el último encontrado
        $startCorrelativo = max($correlativoOriginal, $ultimoCorrelativo + 1);

        DB::beginTransaction();
        try {
            // Emitir documentos en secuencia
            for ($i = 0; $i < $cantidad; $i++) {
                try {
                    // Incrementar el correlativo para el documento
                    $newCorrelativo = $startCorrelativo + $i;

                    // Crear nueva venta solo con los campos permitidos
                    $nuevaVenta = new Venta();
                    $nuevaVenta->fill([
                        'tipo_dte' => $tipo,
                        'correlativo' => $newCorrelativo,
                        'numero_control' => 'DTE-' . $tipo . '-' . $this->sucursal->cod_estable_mh . '0001-' . str_pad($newCorrelativo, 15, '0', STR_PAD_LEFT),
                        'codigo_generacion' => strtoupper(Uuid::uuid4()->toString()),
                        'fecha' => Carbon::now()->format('Y-m-d'),
                        'fecha_pago' => Carbon::now()->format('Y-m-d'),
                        'prueba_masiva' => true,
                        'estado' => 'Pendiente',
                        'forma_pago' => $this->baseVenta->forma_pago,
                        // 'condicion' => $this->baseVenta->condicion ?? 'Contado',
                        'observaciones' => 'Documento de prueba generado automáticamente',
                        'sub_total' => $this->baseVenta->sub_total,
                        'descuento' => $this->baseVenta->descuento,
                        'iva' => $this->baseVenta->iva,
                        'iva_retenido' => $this->baseVenta->iva_retenido,
                        'iva_percibido' => $this->baseVenta->iva_percibido,
                        'gravada' => $this->baseVenta->gravada,
                        'exenta' => $this->baseVenta->exenta,
                        'no_sujeta' => $this->baseVenta->no_sujeta,
                        'cuenta_a_terceros' => $this->baseVenta->cuenta_a_terceros,
                        'total' => $this->baseVenta->total,
                        'id_cliente' => $this->baseVenta->id_cliente,
                        'id_usuario' => $this->baseVenta->id_usuario,
                        'id_vendedor' => $this->baseVenta->id_vendedor,
                        'id_empresa' => $this->empresa->id,
                        'id_sucursal' => $this->sucursal->id,
                        'id_documento' => $this->baseVenta->id_documento,
                        'id_bodega' => $this->baseVenta->id_bodega,
                        'id_canal' => $this->baseVenta->id_canal
                    ]);

                    // Guardar la venta con solo campos permitidos
                    $nuevaVenta->save();

                    // Duplicar los detalles - solo usando el método create que respeta fillable
                    foreach ($this->baseVenta->detalles as $detalleOriginal) {
                        $nuevaVenta->detalles()->create([
                            'id_producto' => $detalleOriginal->id_producto,
                            'cantidad' => $detalleOriginal->cantidad,
                            'precio' => $detalleOriginal->precio,
                            'costo' => $detalleOriginal->costo,
                            'descuento' => $detalleOriginal->descuento,
                            'total' => $detalleOriginal->total,
                            'total_costo' => $detalleOriginal->total_costo,
                            'descripcion' => $detalleOriginal->descripcion ?? 'Producto'
                        ]);
                    }

                    // Volver a cargar la venta con sus detalles para el DTE
                    $nuevaVenta = Venta::with(['detalles', 'cliente', 'empresa', 'sucursal'])->find($nuevaVenta->id);

                    // Generar el DTE con el objeto
                    $dte = $this->generarDTE($nuevaVenta);

                    // Firmar y enviar DTE
                    $resultado = $this->firmarYEmitirDTE($nuevaVenta, $dte);

                    if ($resultado['success']) {
                        $resultados['exitosos']++;
                        $resultados['detalles'][] = [
                            'correlativo' => $nuevaVenta->correlativo,
                            'status' => 'Éxito',
                            'message' => 'DTE emitido correctamente'
                        ];

                        // Usar actualización específica en lugar de save() para evitar campos no existentes
                        Venta::where('id', $nuevaVenta->id)
                            ->update([
                                'dte' => $dte,
                                'sello_mh' => $resultado['selloRecibido'] ?? null,
                                'estado' => 'Prueba'
                            ]);
                    } else {
                        $resultados['fallidos']++;
                        $resultados['detalles'][] = [
                            'correlativo' => $nuevaVenta->correlativo,
                            'status' => 'Error',
                            'message' => $resultado['message']
                        ];

                        // Eliminar la venta si falló
                        $nuevaVenta->delete();
                    }
                } catch (\Exception $e) {
                    Log::error('Error en prueba masiva: ' . $e->getMessage());
                    $resultados['fallidos']++;
                    $resultados['detalles'][] = [
                        'correlativo' => isset($nuevaVenta) ? $nuevaVenta->correlativo : 'N/A',
                        'status' => 'Error',
                        'message' => 'Excepción: ' . $e->getMessage()
                    ];

                    // Eliminar venta si hubo excepción
                    if (isset($nuevaVenta) && $nuevaVenta->id) {
                        $nuevaVenta->delete();
                    }
                }
            }

            // Restaurar el correlativo original
            $this->restaurarCorrelativo($documento, $correlativoOriginal);

            DB::commit();

            return [
                'success' => true,
                'message' => "Proceso completado: {$resultados['exitosos']} documentos emitidos, {$resultados['fallidos']} fallidos",
                'resultados' => $resultados
            ];
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error general en pruebas masivas: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Error en el proceso: ' . $e->getMessage()
            ];
        }
    }

    protected function duplicarDocumento($ventaBase, $nuevoCorrelativo)
    {
        // Crear una nueva instancia persistiendo solo en memoria
        $nuevaVenta = new Venta();

        // Copiar propiedades básicas
        $nuevaVenta->id_empresa = $ventaBase->id_empresa;
        $nuevaVenta->id_sucursal = $ventaBase->id_sucursal;
        $nuevaVenta->id_documento = $ventaBase->id_documento;
        $nuevaVenta->id_cliente = $ventaBase->id_cliente;
        $nuevaVenta->id_bodega = $ventaBase->id_bodega;
        $nuevaVenta->id_usuario = $ventaBase->id_usuario;
        $nuevaVenta->id_vendedor = $ventaBase->id_vendedor;
        $nuevaVenta->id_canal = $ventaBase->id_canal;
        $nuevaVenta->fecha = Carbon::now()->format('Y-m-d');
        $nuevaVenta->fecha_pago = Carbon::now()->format('Y-m-d');
        $nuevaVenta->estado = $ventaBase->estado;
        $nuevaVenta->observaciones = 'Documento de prueba generado automáticamente';
        $nuevaVenta->forma_pago = $ventaBase->forma_pago;
        $nuevaVenta->sub_total = $ventaBase->sub_total;
        $nuevaVenta->iva = $ventaBase->iva;
        $nuevaVenta->iva_retenido = $ventaBase->iva_retenido;
        $nuevaVenta->iva_percibido = $ventaBase->iva_percibido;
        $nuevaVenta->descuento = $ventaBase->descuento;
        $nuevaVenta->total = $ventaBase->total;
        $nuevaVenta->exenta = $ventaBase->exenta ?? 0;
        $nuevaVenta->gravada = $ventaBase->gravada ?? 0;
        $nuevaVenta->no_sujeta = $ventaBase->no_sujeta ?? 0;
        $nuevaVenta->cuenta_a_terceros = $ventaBase->cuenta_a_terceros ?? 0;

        // Campos específicos para facturas de exportación
        if ($ventaBase->tipo_dte == '11') {
            $nuevaVenta->tipo_item_export = $ventaBase->tipo_item_export;
            $nuevaVenta->cod_incoterm = $ventaBase->cod_incoterm;
            $nuevaVenta->incoterm = $ventaBase->incoterm;
            $nuevaVenta->recinto_fiscal = $ventaBase->recinto_fiscal;
            $nuevaVenta->regimen = $ventaBase->regimen;
            $nuevaVenta->seguro = $ventaBase->seguro ?? 0;
            $nuevaVenta->flete = $ventaBase->flete ?? 0;
        }

        // Asignar datos de FE
        $nuevaVenta->tipo_dte = $ventaBase->tipo_dte;
        $nuevaVenta->correlativo = $nuevoCorrelativo;
        $nuevaVenta->codigo_generacion = strtoupper(Uuid::uuid4()->toString());
        $nuevaVenta->numero_control = 'DTE-' . $ventaBase->tipo_dte . '-' .
            $this->sucursal->cod_estable_mh . '0001-' .
            str_pad($nuevoCorrelativo, 15, '0', STR_PAD_LEFT);

        // Marcar como prueba masiva
        $nuevaVenta->prueba_masiva = true;

        // Guardar la referencia a los detalles originales para usarlos en la generación del DTE
        // Esta propiedad no se guardará en la base de datos pero estará disponible en memoria
        $this->detallesOriginales = $ventaBase->detalles;

        // Si es en modo simulación, no guardamos en la BD
        if (!$this->simulationMode) {
            $nuevaVenta->save();

            // Duplicar los detalles
            if ($ventaBase->detalles && count($ventaBase->detalles) > 0) {
                foreach ($ventaBase->detalles as $detalle) {
                    $nuevoDetalle = new Detalle();
                    $nuevoDetalle->id_venta = $nuevaVenta->id;
                    $nuevoDetalle->id_producto = $detalle->id_producto;
                    $nuevoDetalle->codigo = $detalle->codigo;
                    $nuevoDetalle->nombre_producto = $detalle->nombre_producto;
                    $nuevoDetalle->descripcion = $detalle->descripcion;
                    $nuevoDetalle->unidad = $detalle->unidad;
                    $nuevoDetalle->cantidad = $detalle->cantidad;
                    $nuevoDetalle->precio = $detalle->precio;
                    $nuevoDetalle->costo = $detalle->costo;
                    $nuevoDetalle->descuento = $detalle->descuento;
                    $nuevoDetalle->total = $detalle->total;
                    $nuevoDetalle->total_costo = $detalle->total_costo;
                    $nuevoDetalle->exenta = $detalle->exenta ?? 0;
                    $nuevoDetalle->gravada = $detalle->gravada ?? 0;
                    $nuevoDetalle->no_sujeta = $detalle->no_sujeta ?? 0;
                    $nuevoDetalle->cuenta_a_terceros = $detalle->cuenta_a_terceros ?? 0;
                    $nuevoDetalle->save();
                }
            }
        }

        // Actualizar el correlativo en la tabla de documentos
        $documento = Documento::find($ventaBase->id_documento);
        if ($documento) {
            $documento->correlativo = $nuevoCorrelativo;
            $documento->save();
        }

        return $nuevaVenta;
    }

    /**
     * Generar DTE según el tipo de documento
     */
    protected function generarDTE($venta)
    {
        // Si estamos en modo simulación, necesitamos crear una venta temporal
        // con los detalles ya guardados en la relación
        if ($this->simulationMode && isset($this->detallesOriginales)) {
            // Creamos una colección temporal para los detalles
            $detallesTmp = collect();

            // Copiamos cada detalle como un objeto que simulará ser un modelo Eloquent
            foreach ($this->detallesOriginales as $detalle) {
                $nuevoDetalle = new \stdClass();

                // Copiamos todas las propiedades
                foreach ((array)$detalle as $key => $value) {
                    $nuevoDetalle->$key = $value;
                }

                // Añadimos métodos necesarios usando closures
                $nuevoDetalle->producto = function () use ($detalle) {
                    $producto = new \stdClass();
                    $producto->tipo = $detalle->tipo_item == 2 ? 'Servicio' : 'Producto';

                    // Simulamos el comportamiento de pluck
                    $producto->pluck = function ($field) use ($producto) {
                        if ($field == 'tipo') {
                            return collect([$producto->tipo]);
                        }
                        return collect([]);
                    };

                    // Simulamos first() para devolver el objeto
                    $producto->first = function () use ($producto) {
                        return $producto;
                    };

                    return $producto;
                };

                // Añadimos el detalle a la colección
                $detallesTmp->push($nuevoDetalle);
            }

            // Reemplazamos temporalmente los detalles de la venta
            // Guardamos los detalles originales para restaurarlos después
            $detallesOriginales = $venta->detalles;
            $venta->detalles = $detallesTmp;

            // Aseguramos que la venta no se guarde
            $saveMethod = $venta->save;
            $venta->save = function () {
                return true;
            };
        }

        // Generamos el DTE con la clase correspondiente
        $resultado = null;
        switch ($venta->tipo_dte) {
            case '01':
                $mh = new MHFactura();
                $resultado = $mh->generarDTE($venta);
                break;
            case '03':
                $mh = new MHCCF();
                $resultado = $mh->generarDTE($venta);
                break;
            case '11':
                $mh = new MHFacturaExportacion();
                $resultado = $mh->generarDTE($venta);
                break;
            case '05':
                $mh = new MHNotaCredito();
                $resultado = $mh->generarDTE($venta);
                break;
            case '06':
                $mh = new MHNotaDebito();
                $resultado = $mh->generarDTE($venta);
                break;
            default:
                throw new \Exception("Tipo de documento no soportado para pruebas masivas: {$venta->tipo_dte}");
        }

        // Si estábamos en modo simulación, restauramos los detalles originales
        if ($this->simulationMode && isset($detallesOriginales)) {
            $venta->detalles = $detallesOriginales;
            $venta->save = $saveMethod;
        }

        return $resultado;
    }
    /**
     * Firmar y emitir el DTE al Ministerio de Hacienda
     */
    protected function firmarYEmitirDTE($venta, $dte)
    {
        // Preparar datos para firma
        $datosParaFirma = [
            'nit' => str_replace('-', '', $this->empresa->nit),
            'activo' => true,
            'passwordPri' => $this->empresa->mh_pwd_certificado,
            'dteJson' => $dte
        ];

        try {
            // Firmar DTE usando el servicio externo
            $client = new Client();

            $responseFirma = $client->post(config('app.mh_url_firmado', 'https://facturadtesv.com:8443/firmardocumento/'), [
                'json' => $datosParaFirma,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ],
                'verify' => false // Para entornos de desarrollo
            ]);

            $dteFirmado = json_decode($responseFirma->getBody()->getContents(), true);

            if (isset($dteFirmado['status']) && $dteFirmado['status'] === 'ERROR') {
                return [
                    'success' => false,
                    'message' => 'Error al firmar DTE: ' . ($dteFirmado['body']['mensaje'] ?? 'Error desconocido')
                ];
            }

            // Autenticar con MH
            $responseAuth = $client->post(config('app.mh_url_auth', 'https://apitest.dtes.mh.gob.sv/seguridad/auth'), [
                'form_params' => [
                    'user' => str_replace('-', '', $this->empresa->mh_usuario),
                    'pwd' => $this->empresa->mh_contrasena
                ],
                'verify' => false // Para entornos de desarrollo
            ]);

            $authData = json_decode($responseAuth->getBody()->getContents(), true);

            if (isset($authData['body']['token'])) {
                $token = $authData['body']['token'];

                // Preparar datos para envío al MH
                $datosMH = [
                    'ambiente' => $this->empresa->fe_ambiente,
                    'idEnvio' => $venta->id ?? uniqid(),
                    'version' => $dte['identificacion']['version'],
                    'tipoDte' => $venta->tipo_dte,
                    'documento' => isset($dteFirmado['body']) ? $dteFirmado['body'] : $dteFirmado,
                    'codigoGeneracion' => $venta->codigo_generacion
                ];

                // Enviar DTE firmado
                $responseEnvio = $client->post(config('app.mh_url_recepcion', 'https://apitest.dtes.mh.gob.sv/fesv/recepciondte'), [
                    'json' => $datosMH,
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => $token
                    ],
                    'verify' => false // Para entornos de desarrollo
                ]);

                $respuestaMH = json_decode($responseEnvio->getBody()->getContents(), true);
                
                if (isset($respuestaMH['estado']) && $respuestaMH['estado'] === 'PROCESADO') {
                    // Registrar el documento como exitoso si no estamos en modo simulación
                    if (!$this->simulationMode) {
                        $this->registrarDocumentoExitoso($venta, $dte, $dteFirmado, $respuestaMH);
                    }

                    $this->eliminarCorretivo();

                    return [
                        'success' => true,
                        'message' => 'DTE emitido correctamente',
                        'selloRecibido' => $respuestaMH['selloRecibido'] ?? null
                    ];
                } else {
                    return [
                        'success' => false,
                        'message' => 'Error al procesar DTE: ' . json_encode($respuestaMH)
                    ];
                }
            } else {
                return [
                    'success' => false,
                    'message' => 'Error de autenticación con MH: ' . json_encode($authData)
                ];
            }
        } catch (RequestException $e) {
            $errorMessage = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
            return [
                'success' => false,
                'message' => 'Error de conexión: ' . $errorMessage
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Excepción al procesar DTE: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Registrar un documento emitido exitosamente
     */
    protected function registrarDocumentoExitoso($venta, $dte, $dteFirmado, $respuestaMH)
    {
        // Guardar en BD solo si no es modo simulación
        $venta->dte = $dte;
        $venta->firmaElectronica = $dteFirmado['body'] ?? $dteFirmado;
        $venta->sello_mh = $respuestaMH['selloRecibido'];
        $venta->save();

        return true;
    }

    /**
     * Restaurar el correlativo original del documento
     */
    protected function restaurarCorrelativo($documento, $correlativoOriginal)
    {
        // Restaurar correlativo
        if ($documento) {
            $documento->correlativo = $correlativoOriginal;
            $documento->save();
            return true;
        }

        return false;
    }

    protected function eliminarCorretivo(){

      $ventasToDelete = Venta::where('prueba_masiva', true)
      ->where('id_empresa', Auth::user()->id_empresa)->get();

      foreach ($ventasToDelete as $venta) {
        $venta->delete();
      }

      return true;
    }
}
