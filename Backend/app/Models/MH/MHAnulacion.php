<?php

namespace App\Models\MH;

use App\Services\Dte\DteJsonHelper;
use App\Support\FacturacionElectronica\TipoIdentificacionReceptor;
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

        if (!is_array($DTE)) {
            $DTE = is_string($DTE) ? json_decode($DTE, true) : [];
        }
        $DTE = $DTE ?: [];

        $selloRecibido = DteJsonHelper::extractSelloRecibido($DTE);
        if ($selloRecibido === null && !empty($this->venta->sello_mh)) {
            $selloRecibido = (string) $this->venta->sello_mh;
        }
        if ($selloRecibido === null || $selloRecibido === '') {
            throw new \InvalidArgumentException(
                'No hay sello de recepción del MH para invalidar este documento. Verifique el DTE emitido o anule solo en el sistema.'
            );
        }

        $codigoGeneracionAnula = strtoupper(Uuid::uuid4()->toString());
        $tipoDte = $DTE['identificacion']['tipoDte'] ?? null;

        // CCF/NC/ND: la fecha del evento debe ser la de generación del DTE; FE/FEX/FSE admiten hasta 3 meses después.
        $fecEmi = in_array($tipoDte, ['03', '05', '06'], true)
            ? $DTE['identificacion']['fecEmi']
            : ($this->venta->fecha_anulacion ? \Carbon\Carbon::parse($this->venta->fecha_anulacion)->format('Y-m-d') : \Carbon\Carbon::now()->format('Y-m-d'));

        $identificacion = [
            "version" => 3,
            "ambiente" => $DTE['identificacion']['ambiente'],
            "codigoGeneracion" => $codigoGeneracionAnula,
            "fecEmi" => $fecEmi,
            "horEmi" => \Carbon\Carbon::now()->format('H:i:s'),
            "fusion" => NULL,
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


        if (($DTE['identificacion']['tipoDte'] ?? null) == '14') {
            $sujeto = $DTE['receptor'] ?? $DTE['sujetoExcluido'] ?? null;
            if (!$sujeto && method_exists($this->venta, 'proveedor') && $this->venta->proveedor) {
                $proveedor = $this->venta->proveedor;
                $tipoDoc = TipoIdentificacionReceptor::elSalvador(
                    $proveedor->tipo_documento,
                    $proveedor->nit,
                    $proveedor->dui
                );
                $numDoc = $tipoDoc
                    ? TipoIdentificacionReceptor::numeroElSalvador($tipoDoc, $proveedor->nit, $proveedor->dui)
                    : null;
                if (in_array($tipoDoc, ['13', '36'], true) && $numDoc) {
                    $numDoc = str_replace('-', '', $numDoc);
                }
                $sujeto = [
                    'tipoDocumento' => $tipoDoc,
                    'numDocumento' => $numDoc,
                    'nombre' => method_exists($this->venta, 'nombreProveedorFiscal')
                        ? $this->venta->nombreProveedorFiscal()
                        : ($proveedor->nombre ?? ''),
                    'correo' => $proveedor->correo ?? null,
                    'telefono' => $proveedor->telefono ?? null,
                ];
            }
            if ($sujeto) {
                $tipo_documento = $sujeto['tipoDocumento'] ?? null;
                $num_documento = $sujeto['numDocumento'] ?? null;
                $nombre = $sujeto['nombre'] ?? null;
                $correo = $sujeto['correo'] ?? null;
                $telefono = $sujeto['telefono'] ?? null;
            }
        }

        if ($DTE['identificacion']['tipoDte'] == '11') {
            $tipo_documento = $DTE['receptor']['tipoDocumento'];
            $num_documento = $DTE['receptor']['numDocumento'];
            $nombre = $DTE['receptor']['nombre'];
            $correo = $DTE['receptor']['correo'];
            $telefono = $DTE['receptor']['telefono'];
        }

        if (isset($DTE['receptor']) && in_array($tipoDte, ['03', '05', '06'], true)) {
            // NC/ND v4 identifican al receptor con tipoDocumento/numDocumento; CCF y documentos previos usan nit.
            $tipo_documento = $DTE['receptor']['tipoDocumento'] ?? '36';
            $num_documento = $DTE['receptor']['numDocumento'] ?? $DTE['receptor']['nit'];
            $nombre = $DTE['receptor']['nombre'];
            $correo = $DTE['receptor']['correo'];
            $telefono = $DTE['receptor']['telefono'];
        }

        // 1. Error en la Información del Documento Tributario Electrónico a invalidar.
        // 2. Rescindir de la operación realizada.
        // 3. Otro.

        // Usar valores directamente de la venta (ya están guardados)
        $tipoAnulacion = $this->venta->tipo_anulacion ? +$this->venta->tipo_anulacion : 2;

        $codigoGeneracionDoc = $DTE['identificacion']['codigoGeneracion']
            ?? $this->venta->codigo_generacion
            ?? null;
        if (!$codigoGeneracionDoc) {
            throw new \InvalidArgumentException(
                'Falta el código de generación del DTE a invalidar.'
            );
        }

        $documento = [
            "tipoDte" => $DTE['identificacion']['tipoDte'],
            "codigoGeneracion" => $codigoGeneracionDoc,
            "selloRecibido" => $selloRecibido,
            "numeroControl" => $DTE['identificacion']['numeroControl'],
            "fecEmi" => $DTE['identificacion']['fecEmi'],
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
            "numDocResponsable" => str_replace('-', '', (string) ($DTE['emisor']['nit'] ?? '')),
            "nombreSolicita" => $DTE['emisor']['nombre'],
            "tipDocSolicita" =>  '36',
            "numDocSolicita" => str_replace('-', '', (string) ($DTE['emisor']['nit'] ?? '')),
        ];

        // El emisor del evento debe coincidir con el del DTE: establecimiento y punto de venta salen de su número de control.
        $numeroControl = (string) ($DTE['identificacion']['numeroControl'] ?? '');
        $codEstableMH = strlen($numeroControl) === 31 ? substr($numeroControl, 7, 4) : ($this->sucursal->cod_estable_mh ?? NULL);
        $codPuntoVentaMH = strlen($numeroControl) === 31 ? substr($numeroControl, 11, 4) : ($this->sucursal->codigo_punto_venta ?? 'P001');

        $emisor = [
            "nit" => str_replace('-', '', $this->empresa->nit),
            "nombre" => $this->empresa->nombre,
            "codEstableMH" => $codEstableMH,
            "codEstable" => $codEstableMH,
            "codPuntoVentaMH" => $codPuntoVentaMH,
            "codPuntoVenta" => $codPuntoVentaMH,
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

