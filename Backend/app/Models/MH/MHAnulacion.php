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
        $this->caja_codigo = '0001';

        $identificacion = [
            "version" => 2,
            "ambiente" => $DTE['identificacion']['ambiente'],
            "codigoGeneracion" => $codigoGeneracion,
            "fecAnula" => \Carbon\Carbon::now()->format('Y-m-d'),
            "horAnula" => \Carbon\Carbon::now()->format('H:i:s'),
        ];

        $tipo_documento = NULL;
        $num_documento = NULL;


        if ($DTE['receptor'] && $DTE['identificacion']['tipoDte'] == '01') {
            $tipo_documento = $DTE['receptor']['tipoDocumento'];
            $num_documento = $DTE['receptor']['numDocumento'];
        }

        if ($DTE['receptor'] && (($DTE['identificacion']['tipoDte'] == '03') || $DTE['identificacion']['tipoDte'] == '05') || $DTE['identificacion']['tipoDte'] == '06') {
            $tipo_documento = '36';
            $num_documento = $DTE['receptor']['nit'];
        }

        $documento = [
            "tipoDte" => $DTE['identificacion']['tipoDte'],
            "codigoGeneracion" => $DTE['identificacion']['codigoGeneracion'],
            "selloRecibido" => $DTE['sello'],
            "numeroControl" => $DTE['identificacion']['numeroControl'],
            "fecEmi" => $DTE['identificacion']['fecEmi'],
            "montoIva" => isset($DTE['resumen']['totalIva']) ? $DTE['resumen']['totalIva'] : NULL,
            "codigoGeneracionR" => NULL, // Solo si el motivo es error, hay que mandar el que sustituye
            "tipoDocumento" => $tipo_documento,
            "numDocumento" => $num_documento,
            "nombre" => $DTE['receptor'] ? $DTE['receptor']['nombre'] : NULL,
            "correo" => $DTE['receptor'] ? $DTE['receptor']['correo'] : NULL,
            "telefono" => $DTE['receptor'] ? $DTE['receptor']['telefono'] : NULL,
        ];

        // 1. Error en la Información del Documento Tributario Electrónico a invalidar.
        // 2. Rescindir de la operación realizada.
        // 3. Otro.

        $motivo = [
            "tipoAnulacion" => 2,
            "motivoAnulacion" => 'Se rescinde la operación.',
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

