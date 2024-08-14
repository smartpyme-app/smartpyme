<?php

namespace App\Models\MH;

use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Facades\Http;
use App\Models\MH\Unidad;
use Luecano\NumeroALetras\NumeroALetras;

class MHContingencia extends Model
{

    public $empresa;
    
    public function generarDTE($empresa, $DTEs, $tipo = 3){
        $codigoGeneracion = strtoupper(Uuid::uuid4()->toString());
        $this->empresa = $empresa;

        $identificacion = [
            "version" => 3,
            "ambiente" => $DTEs[0]['identificacion']['ambiente'],
            "codigoGeneracion" => $codigoGeneracion,
            "fTransmision" => \Carbon\Carbon::now()->format('Y-m-d'),
            "hTransmision" => \Carbon\Carbon::now()->format('H:i:s'),
        ];

        $detalles = collect();

        foreach ($DTEs as $index => $DTE) {

            $detalles->push([
                "noItem" => $index + 1,
                "codigoGeneracion" => $DTE['identificacion']['codigoGeneracion'],
                "tipoDoc" => $DTE['identificacion']['tipoDte'],
            ]);
        }

        $tipos = [
            "No disponibilidad de sistema del MH",
            "No disponibilidad de sistema del emisor",
            "Falla en el suministro de servicio de Internet del Emisor",
            "Falla en el suministro de servicio de energía eléctrica del emisor, que impida la transmisión de los DTE",
        ];

        $motivo = [
            "fInicio" => \Carbon\Carbon::parse($DTEs[0]['identificacion']['fecEmi'])->format('Y-m-d'),
            "fFin" => \Carbon\Carbon::parse($DTEs[0]['identificacion']['fecEmi'])->format('Y-m-d'),
            "hInicio" => \Carbon\Carbon::parse($DTEs[0]['identificacion']['horEmi'])->format('H:i:s'),
            "hFin" => \Carbon\Carbon::parse($DTEs[0]['identificacion']['horEmi'])->format('H:i:s'),
            "tipoContingencia" => $tipo,
            "motivoContingencia" => 'No se pudieron emitir los DTEs.',
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
                $this->empresa->tipoEstablecimiento = '20';
                break;
        }


        $emisor = [
            "nit" => str_replace('-', '', $this->empresa->nit),
            "nombre" => $this->empresa->nombre,
            "nombreResponsable" => $this->empresa->nombre,
            "tipoDocResponsable" => '13',
            "numeroDocResponsable" => $this->empresa->nit,
            "tipoEstablecimiento" => $this->empresa->tipoEstablecimiento,
            "telefono" => $this->empresa->telefono,
            "correo" => $this->empresa->correo,
        ];

        return  
        [
            "identificacion" => $identificacion,
            "emisor" => $emisor,
            "detalleDTE" => $detalles,
            "motivo" => $motivo,
        ];

    }


}

