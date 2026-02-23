<?php

namespace App\Models\MH;

use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Uuid;

class MHAnulacion extends Model
{

    public $venta;
    public $caja;
    public $caja_codigo;
    public $empresa;
    public $sucursal;
    

    public function generarDTE($venta, $DTE){
        $this->venta = $venta;
        $this->empresa = $this->venta->empresa()->first();
        $this->sucursal = $this->venta->sucursal()->first();

        $codigoGeneracion = strtoupper(Uuid::uuid4()->toString());
        $this->caja_codigo = 'P001';

        $identificacion = [
            "version" => 2,
            "ambiente" => $DTE['identificacion']['ambiente'],
            "codigoGeneracion" => $codigoGeneracion,
            "fecAnula" => $this->venta->fecha_anulacion ? \Carbon\Carbon::parse($this->venta->fecha_anulacion)->format('Y-m-d') : \Carbon\Carbon::now()->format('Y-m-d'),
            "horAnula" => \Carbon\Carbon::now()->format('H:i:s'),
        ];

        $tipo_documento = NULL;
        $num_documento = NULL;
        $nombre = NULL;
        $correo = NULL;
        $telefono = NULL;


        if (isset($DTE['receptor']) && $DTE['identificacion']['tipoDte'] == '01') {
            $tipo_documento = $DTE['receptor']['tipoDocumento'];
            $num_documento = $DTE['receptor']['numDocumento'];
            $nombre = $DTE['receptor']['nombre'];
            $correo = $DTE['receptor']['correo'];
            $telefono = $DTE['receptor']['telefono'];
        }


        if (isset($DTE['sujetoExcluido']) && $DTE['identificacion']['tipoDte'] == '14') {
            $tipo_documento = $DTE['sujetoExcluido']['tipoDocumento'];
            $num_documento = $DTE['sujetoExcluido']['numDocumento'];
            $nombre = $DTE['sujetoExcluido']['nombre'];
            $correo = $DTE['sujetoExcluido']['correo'];
            $telefono = $DTE['sujetoExcluido']['telefono'];
        }

        if (isset($DTE['receptor']) && (($DTE['identificacion']['tipoDte'] == '03') || $DTE['identificacion']['tipoDte'] == '05') || $DTE['identificacion']['tipoDte'] == '06') {
            $tipo_documento = '36';
            $num_documento = $DTE['receptor']['nit'];
            $nombre = $DTE['receptor']['nombre'];
            $correo = $DTE['receptor']['correo'];
            $telefono = $DTE['receptor']['telefono'];
        }

        // 1. Error en la Información del Documento Tributario Electrónico a invalidar.
        // 2. Rescindir de la operación realizada.
        // 3. Otro.

        // Usar valores directamente de la venta (ya están guardados)
        $tipoAnulacion = $this->venta->tipo_anulacion ? +$this->venta->tipo_anulacion : 2;

        $documento = [
            "tipoDte" => $DTE['identificacion']['tipoDte'],
            "codigoGeneracion" => $DTE['identificacion']['codigoGeneracion'],
            "selloRecibido" => $DTE['sello'],
            "numeroControl" => $DTE['identificacion']['numeroControl'],
            "fecEmi" => $DTE['identificacion']['fecEmi'],
            "montoIva" => isset($DTE['resumen']['totalIva']) ? $DTE['resumen']['totalIva'] : NULL,
            "codigoGeneracionR" => ($tipoAnulacion == 1 || $tipoAnulacion == 3) && $this->venta->codigo_generacion_remplazo 
                ? $this->venta->codigo_generacion_remplazo 
                : NULL, // Solo si el motivo es error (1) u otro (3), hay que mandar el que sustituye
            "tipoDocumento" => $tipo_documento,
            "numDocumento" => $num_documento,
            "nombre" => $nombre,
            "correo" => $correo,
            "telefono" => $telefono,
        ];
        
        // Si la venta tiene motivo_anulacion guardado, usarlo directamente
        // Si no, usar el texto predeterminado según el tipo
        if ($this->venta->motivo_anulacion) {
            $motivoTexto = $this->venta->motivo_anulacion;
        } else {
            // Textos predeterminados según el tipo de anulación
            switch ($tipoAnulacion) {
                case 1:
                    $motivoTexto = 'Error en la Información del Documento Tributario Electrónico a invalidar.';
                    break;
                case 2:
                    $motivoTexto = 'Se rescinde la operación.';
                    break;
                case 3:
                    $motivoTexto = 'Otro.';
                    break;
                default:
                    $motivoTexto = 'Se rescinde la operación.';
                    break;
            }
        }

        $motivo = [
            "tipoAnulacion" => $tipoAnulacion,
            "motivoAnulacion" => $motivoTexto,
            "nombreResponsable" => $DTE['emisor']['nombre'],
            "tipDocResponsable" => '36',
            "numDocResponsable" => $DTE['emisor']['nit'],
            "nombreSolicita" => $DTE['emisor']['nombre'],
            "tipDocSolicita" =>  '36',
            "numDocSolicita" => $DTE['emisor']['nit'],
        ];

        switch ($this->empresa->tipo_establecimiento) {
            case 'Sucursal':
                $this->empresa->tipoEstablecimiento = '01';
                break;
            case 'Casa matriz':
                $this->empresa->tipoEstablecimiento = '02';
                break;
            case 'Bodega':
                $this->empresa->tipoEstablecimiento = '04';
                break;
            default:
                $this->empresa->tipoEstablecimiento = '02';
                break;
        }


        $emisor = [
            "nit" => str_replace('-', '', $this->empresa->nit),
            "nombre" => $this->empresa->nombre,
            "tipoEstablecimiento" => $this->sucursal->tipo_establecimiento,
            "nomEstablecimiento" => $this->empresa->nombre,
            "codEstable" => $this->sucursal->cod_estable_mh ? $this->sucursal->cod_estable_mh : NULL,
            "codPuntoVenta" => $this->caja_codigo ? $this->caja_codigo : NULL,
            "telefono" => $this->empresa->telefono,
            "correo" => $this->empresa->correo,
        ];

        return  
            [
                "identificacion" => $identificacion,
                "emisor" => $emisor,
                "documento" => $documento,
                "motivo" => $motivo,
            ];

    }


}

