<?php

namespace App\Services\FacturacionElectronica\Implementations\ElSalvador;

use App\Services\FacturacionElectronica\Implementations\ElSalvador\ElSalvadorFE;
use App\Models\MH\Unidad;
use App\Models\Admin\Empresa;
use Ramsey\Uuid\Uuid;
use Luecano\NumeroALetras\NumeroALetras;
use Carbon\Carbon;

/**
 * Implementación de Factura de Exportación para El Salvador
 * 
 * Genera documentos tipo 11 (Factura de Exportación) según especificaciones de MH El Salvador
 * 
 * @package App\Services\FacturacionElectronica\Implementations\ElSalvador
 */
class ElSalvadorFacturaExportacion extends ElSalvadorFE
{
    protected $venta;
    protected $sucursal;
    protected $caja_codigo;

    /**
     * Genera el DTE de tipo Factura de Exportación (11)
     * 
     * @param mixed $documento Venta
     * @return array Estructura del DTE
     */
    public function generarDTE($documento): array
    {
        $this->venta = $documento;
        $this->empresa = $this->venta->empresa()->first();
        $this->sucursal = $this->venta->sucursal()->first();
        
        $this->setEmpresa($this->empresa);

        $this->caja_codigo = $this->sucursal->codigo_punto_venta ?? 'P001';
        $this->venta->tipo_dte = '11';
        $this->venta->numero_control = 'DTE-' . $this->venta->tipo_dte . '-' . $this->sucursal->cod_estable_mh . $this->caja_codigo . '-' . str_pad($this->venta->correlativo, 15, '0', STR_PAD_LEFT);

        if (!$this->venta->codigo_generacion) {
            $this->venta->codigo_generacion = strtoupper(Uuid::uuid4()->toString());
            $this->venta->save();
        }

        $this->venta->ambiente = $this->empresa->fe_ambiente ?? '00';
        $this->venta->tipoModelo = 1;
        $this->venta->tipoOperacion = 1;
        $this->venta->tipoContingencia = NULL;
        $this->venta->motivoContin = NULL;
        $this->venta->moneda = 'USD';
        $this->venta->version = 1;

        // Condición
        if ($this->venta->condicion == 'Crédito') {
            $this->venta->cod_condicion = 2;
        } else {
            $this->venta->cod_condicion = 1;
        }

        // Método de pago
        switch ($this->venta->metodo_pago) {
            case 'Efectivo':
                $this->venta->cod_metodo_pago = '01';
                break;
            case 'Tarjeta':
                $this->venta->cod_metodo_pago = '02';
                break;
            case 'Cheque':
                $this->venta->cod_metodo_pago = '04';
                break;
            case 'Transferencia':
                $this->venta->cod_metodo_pago = '05';
                break;
            case 'Vales':
                $this->venta->cod_metodo_pago = '06';
                break;
            case 'Chivo Wallet':
                $this->venta->cod_metodo_pago = '09';
                break;
            case 'Bitcoin':
                $this->venta->cod_metodo_pago = '11';
                break;
            default:
                $this->venta->cod_metodo_pago = '01';
                break;
        }

        // Total en letras
        $formatter = new NumeroALetras();
        $n = explode(".", number_format($this->venta->total, 2));
        
        $dolares = $formatter->toWords(floatval(str_replace(',', '', $n[0])));
        $centavos = $formatter->toWords($n[1]);

        $this->venta->total_en_letras = $dolares . ' DÓLARES CON ' . $centavos . ' CENTAVOS.';

        return $this->generarFactura();
    }

    /**
     * Genera la estructura completa de la factura de exportación
     * 
     * @return array
     */
    protected function generarFactura(): array
    {
        $tributos = NULL;

        if ($this->venta->ambiente == '00') {
            $totalGravada = 0;
            foreach ($this->venta->detalles as $detalle) {
                $totalGravada += $detalle->total;
            }
        } else {
            $totalGravada = $this->venta->total;
        }

        return [
            "identificacion" => $this->identificador(),
            "emisor" => $this->emisor(),
            "receptor" => $this->receptor(),
            "otrosDocumentos" => NULL,
            "ventaTercero" => NULL,
            "cuerpoDocumento" => $this->detalles(),
            "resumen" => [
                "totalGravada" => floatval(number_format($totalGravada, 2, '.', '')),
                "descuento" => floatval(number_format(0, 2, '.', '')),
                "porcentajeDescuento" => 0,
                "totalDescu" => floatval(number_format(0, 2, '.', '')),
                "seguro" => floatval(number_format($this->venta->seguro ?? 0, 2, '.', '')),
                "flete" => floatval(number_format($this->venta->flete ?? 0, 2, '.', '')),
                "montoTotalOperacion" => floatval(number_format($totalGravada, 2, '.', '')),
                "totalNoGravado" => 0,
                "totalPagar" => floatval(number_format($totalGravada, 2, '.', '')),
                "totalLetras" => $this->venta->total_en_letras,
                "condicionOperacion" => $this->venta->cod_condicion,
                "pagos" => [
                    [
                        "codigo" => $this->venta->cod_metodo_pago,
                        "montoPago" => floatval(number_format($totalGravada, 2, '.', '')),
                        "referencia" => NULL,
                        "plazo" => $this->venta->cod_condicion == 2 ? $this->obtenerPlazo($this->venta->dias_credito) : NULL,
                        "periodo" => $this->venta->cod_condicion == 2 ? Carbon::parse($this->venta->fecha)->diffInDays(Carbon::parse($this->venta->fecha_pago), false) : NULL
                    ]
                ],
                "numPagoElectronico" => "",
                "codIncoterms" => $this->venta->cod_incoterm ?? NULL,
                "descIncoterms" => $this->venta->incoterm ?? NULL,
                "observaciones" => NULL,
            ],
            "apendice" => [
                [
                    "campo" => "empleado",
                    "etiqueta" => "nombre",
                    "valor" => $this->venta->nombre_usuario
                ]
            ]
        ];
    }

    /**
     * Genera el identificador del DTE
     * 
     * @return array
     */
    protected function identificador(): array
    {
        return [
            "version" => $this->venta->version,
            "ambiente" => $this->venta->ambiente,
            "tipoDte" => $this->venta->tipo_dte,
            "numeroControl" => $this->venta->numero_control,
            "codigoGeneracion" => $this->venta->codigo_generacion,
            "tipoModelo" => $this->venta->tipoModelo,
            "tipoOperacion" => $this->venta->tipoOperacion,
            "tipoContingencia" => $this->venta->tipoContingencia,
            "motivoContigencia" => NULL,
            "fecEmi" => Carbon::parse($this->venta->fecha)->format('Y-m-d'),
            "horEmi" => Carbon::parse($this->venta->created_at)->format('H:i:s'),
            "tipoMoneda" => $this->venta->moneda,
        ];
    }

    /**
     * Genera los datos del emisor (con campos adicionales para exportación)
     * 
     * @return array
     */
    protected function emisor(): array
    {
        return [
            "nit" => str_replace('-', '', $this->empresa->nit),
            "nrc" => str_replace('-', '', $this->empresa->ncr),
            "nombre" => $this->empresa->nombre,
            "codActividad" => $this->empresa->cod_actividad_economica,
            "descActividad" => $this->empresa->giro,
            "nombreComercial" => $this->empresa->nombre_comercial,
            "tipoEstablecimiento" => $this->sucursal->tipo_establecimiento,
            "direccion" => [
                "departamento" => $this->empresa->cod_departamento,
                "municipio" => $this->empresa->cod_municipio,
                "complemento" => $this->empresa->direccion,
            ],
            "telefono" => $this->empresa->telefono,
            "codEstableMH" => $this->empresa->cod_estable_mh ? $this->empresa->cod_estable_mh : NULL,
            "codEstable" => $this->empresa->cod_estable ? $this->empresa->cod_estable : NULL,
            "codPuntoVentaMH" => $this->caja_codigo ? $this->caja_codigo : NULL,
            "codPuntoVenta" => $this->caja_codigo ? $this->caja_codigo : NULL,
            "correo" => $this->empresa->correo,
            "tipoItemExpor" => $this->venta->detalles()->first()->producto()->pluck('tipo')->first() == 'Servicio' ? 2 : 1,
            "recintoFiscal" => $this->venta->recinto_fiscal ?? NULL,
            "regimen" => $this->venta->regimen ?? NULL,
        ];
    }

    /**
     * Genera los datos del receptor (con campos adicionales para exportación)
     * 
     * @return array
     */
    protected function receptor(): array
    {
        if (!$this->venta->id_cliente) {
            return [
                "tipoDocumento" => NULL,
                "numDocumento" => NULL,
                "nrc" => NULL,
                "nombre" => 'Consumidor Final',
                "codActividad" => NULL,
                "descActividad" => NULL,
                "direccion" => NULL,
                "telefono" => NULL,
                "correo" => NULL
            ];
        }

        return [
            "tipoDocumento" => $this->venta->cliente->tipo_documento ?? '36',
            "numDocumento" => $this->venta->cliente->dui ?? str_replace('-', '', $this->venta->cliente->nit),
            "nombre" => $this->venta->nombre_cliente,
            "nombreComercial" => $this->venta->cliente->nombre_empresa,
            "descActividad" => $this->venta->cliente->giro ? $this->venta->cliente->giro : NULL,
            "codPais" => $this->venta->cliente->cod_pais,
            "nombrePais" => $this->venta->cliente->pais,
            "complemento" => $this->venta->cliente->direccion ? $this->venta->cliente->direccion : $this->venta->cliente->empresa_direccion,
            "tipoPersona" => ($this->venta->cliente->tipo_persona == 'Persona Natural') ? 1 : 2,
            "telefono" => $this->venta->cliente->telefono,
            "correo" => $this->venta->cliente->correo ?: ($this->venta->ambiente == '00' ? "prueba@ejemplo.com" : NULL)
        ];
    }

    /**
     * Genera los detalles de productos/servicios
     * 
     * @return \Illuminate\Support\Collection
     */
    protected function detalles()
    {
        $detalles = collect();

        foreach ($this->venta->detalles as $index => $detalle) {
            $cod = Unidad::where('nombre', ucfirst($detalle->unidad))->pluck('cod')->first();
            if ($cod) {
                $detalle->cod_medida = $cod;
            } else {
                $detalle->cod_medida = 59;
            }

            // Tipo Item
            if ($detalle->producto()->pluck('tipo')->first() == 'Servicio') {
                $detalle->tipo_item = 2;
            } else {
                $detalle->tipo_item = 1;
            }

            $tributos = NULL;
            $detalle->codTributo = NULL;
            $detalle->gravada = $detalle->total;

            $detalles->push([
                "numItem" => $index + 1,
                "codigo" => $detalle->codigo,
                "descripcion" => $detalle->nombre_producto,
                "cantidad" => floatval($detalle->cantidad),
                "uniMedida" => $detalle->cod_medida,
                "precioUni" => floatval(number_format($detalle->precio, 2, '.', '')),
                "montoDescu" => floatval(number_format($detalle->descuento, 2, '.', '')),
                "ventaGravada" => floatval(number_format($detalle->gravada, 2, '.', '')),
                "tributos" => $tributos,
                "noGravado" => 0,
            ]);
        }

        return $detalles;
    }

    /**
     * Obtiene el plazo según días de crédito
     * 
     * @param int $dias_credito
     * @return string
     */
    private function obtenerPlazo($dias_credito): string
    {
        if ($dias_credito <= 30) {
            return "01"; // Corto plazo
        } elseif ($dias_credito <= 60) {
            return "02"; // Mediano plazo
        } else {
            return "03"; // Largo plazo
        }
    }

    /**
     * Genera el documento de anulación
     * 
     * @param array $dte Documento original
     * @param mixed $documento Documento original
     * @return array Documento de anulación
     */
    protected function generarDTEAnulado(array $dte, $documento): array
    {
        $this->venta = $documento;
        $this->empresa = $this->venta->empresa()->first();
        $this->sucursal = $this->venta->sucursal()->first();
        
        $codigoGeneracion = strtoupper(Uuid::uuid4()->toString());
        $this->caja_codigo = $this->sucursal->codigo_punto_venta ?? 'P001';

        $identificacion = [
            "version" => 2,
            "ambiente" => $dte['identificacion']['ambiente'],
            "codigoGeneracion" => $codigoGeneracion,
            "fecAnula" => $this->venta->fecha_anulacion 
                ? Carbon::parse($this->venta->fecha_anulacion)->format('Y-m-d')
                : Carbon::now()->format('Y-m-d'),
            "horAnula" => Carbon::now()->format('H:i:s'),
        ];

        // Para Factura de Exportación, el receptor tiene estructura diferente
        $tipo_documento = $dte['receptor']['tipoDocumento'] ?? '36';
        $num_documento = $dte['receptor']['numDocumento'] ?? null;
        $nombre = $dte['receptor']['nombre'] ?? null;
        $correo = $dte['receptor']['correo'] ?? null;
        $telefono = $dte['receptor']['telefono'] ?? null;

        $documentoAnular = [
            "tipoDte" => $dte['identificacion']['tipoDte'],
            "codigoGeneracion" => $dte['identificacion']['codigoGeneracion'],
            "selloRecibido" => $dte['sello'] ?? $dte['selloRecibido'] ?? null,
            "numeroControl" => $dte['identificacion']['numeroControl'],
            "fecEmi" => $dte['identificacion']['fecEmi'],
            "montoIva" => NULL, // Facturas de exportación no tienen IVA
            "codigoGeneracionR" => ($this->venta->tipo_anulacion == 1 || $this->venta->tipo_anulacion == 3) && $this->venta->codigo_generacion_remplazo 
                ? $this->venta->codigo_generacion_remplazo 
                : NULL,
            "tipoDocumento" => $tipo_documento,
            "numDocumento" => $num_documento,
            "nombre" => $nombre,
            "correo" => $correo,
            "telefono" => $telefono,
        ];

        $tipoAnulacion = $this->venta->tipo_anulacion ?? 2;
        $motivoTexto = $this->venta->motivo_anulacion ?? 'Se rescinde la operación.';

        $motivo = [
            "tipoAnulacion" => $tipoAnulacion,
            "motivoAnulacion" => $motivoTexto,
            "nombreResponsable" => $dte['emisor']['nombre'],
            "tipDocResponsable" => '36',
            "numDocResponsable" => $dte['emisor']['nit'],
            "nombreSolicita" => $dte['emisor']['nombre'],
            "tipDocSolicita" => '36',
            "numDocSolicita" => $dte['emisor']['nit'],
        ];

        $emisor = [
            "nit" => str_replace('-', '', $this->empresa->nit),
            "nombre" => $this->empresa->nombre,
            "tipoEstablecimiento" => $this->sucursal->tipo_establecimiento,
            "nomEstablecimiento" => $this->empresa->nombre_comercial,
            "codEstable" => $this->sucursal->cod_estable_mh ? $this->sucursal->cod_estable_mh : NULL,
            "codPuntoVenta" => $this->caja_codigo ? $this->caja_codigo : NULL,
            "telefono" => $this->empresa->telefono,
            "correo" => $this->empresa->correo,
        ];

        return [
            "identificacion" => $identificacion,
            "emisor" => $emisor,
            "documento" => $documentoAnular,
            "motivo" => $motivo,
        ];
    }
}
