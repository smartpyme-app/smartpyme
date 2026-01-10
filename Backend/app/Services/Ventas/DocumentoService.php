<?php

namespace App\Services\Ventas;

use App\Models\Ventas\Venta;
use App\Models\Admin\Documento;
use App\Models\Admin\Empresa;
use App\Models\Ventas\Clientes\Cliente;
use Luecano\NumeroALetras\NumeroALetras;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Support\Facades\Auth;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class DocumentoService
{
    /**
     * Generar documento según el tipo
     *
     * @param int $ventaId
     * @return mixed
     */
    public function generarDocumento(int $ventaId)
    {
        /** @var \App\Models\User|null $user */
        $user = JWTAuth::parseToken()->authenticate();
        $empresa = $user->empresa()->first();

        // Si tiene facturación electrónica en producción
        if ($empresa->facturacion_electronica && $empresa->fe_ambiente == '01') {
            return $this->generarDTE($ventaId, $empresa);
        }

        $venta = Venta::where('id', $ventaId)->with('detalles', 'empresa')->firstOrFail();
        $documento = Documento::findOrfail($venta->id_documento);

        switch ($documento->nombre) {
            case 'Ticket':
            case 'Recibo':
                return $this->generarTicket($venta, $empresa, $documento);
            
            case 'Factura':
                return $this->generarFactura($venta, $empresa);
            
            case 'Sujeto excluido':
                return $this->generarSujetoExcluido($venta, $empresa);
            
            case 'Crédito fiscal':
                return $this->generarCreditoFiscal($venta, $empresa);
            
            default:
                throw new \Exception('No hay un formato para este tipo de documento de venta.');
        }
    }

    /**
     * Generar DTE (Documento Tributario Electrónico)
     *
     * @param int $ventaId
     * @param Empresa $empresa
     * @return mixed
     */
    public function generarDTE(int $ventaId, Empresa $empresa)
    {
        $venta = Venta::where('id', $ventaId)->with('detalles', 'cliente', 'empresa')->firstOrFail();
        $DTE = $venta->dte;

        if (!$DTE) {
            throw new \Exception('El documento no ha sido Emitido');
        }

        $venta->qr = 'https://admin.factura.gob.sv/consultaPublica?ambiente=' . 
            $DTE['identificacion']['ambiente'] . 
            '&codGen=' . $DTE['identificacion']['codigoGeneracion'] . 
            '&fechaEmi=' . $DTE['identificacion']['fecEmi'];

        $ticketEnPdf = isset($empresa->custom_empresa['configuraciones']['ticket_en_pdf']) &&
            $empresa->custom_empresa['configuraciones']['ticket_en_pdf'] == true;

        if ($ticketEnPdf) {
            $venta->pdf = true;
            $pdf = PDF::loadView('reportes.facturacion.DTE-Ticket', compact('venta', 'DTE'));
            
            // Calcular dimensiones para DTE (diferente a ticket normal)
            $alto_base = 220; // mm
            $alto_por_producto = 30; // mm por línea estimado
            $total_lineas = $venta->detalles()->count();
            $alto_total_mm = $alto_base + ($total_lineas * $alto_por_producto);
            $alto_total_pt = $alto_total_mm * 2.83465;
            $ancho_pt = 80 * 2.83465; // 80mm de ancho
            
            $pdf->setPaper([0, 0, $ancho_pt, $alto_total_pt]);
            return $pdf->stream($DTE['identificacion']['codigoGeneracion'] . '.pdf');
        } else {
            $venta->pdf = false;
            return view('reportes.facturacion.DTE-Ticket', compact('venta', 'DTE'));
        }
    }

    /**
     * Generar ticket o recibo
     *
     * @param Venta $venta
     * @param Empresa $empresa
     * @param Documento $documento
     * @return mixed
     */
    public function generarTicket(Venta $venta, Empresa $empresa, Documento $documento)
    {
        $ticketEnPdf = isset($empresa->custom_empresa['configuraciones']['ticket_en_pdf']) &&
            $empresa->custom_empresa['configuraciones']['ticket_en_pdf'] == true;

        if ($ticketEnPdf) {
            $venta->pdf = true;
            $pdf = PDF::loadView('reportes.facturacion.ticket', compact('venta', 'empresa', 'documento'));
            $pdf->setPaper($this->calcularDimensionesTicket($venta));
            return $pdf->stream('ticket.pdf');
        } else {
            $venta->pdf = false;
            return view('reportes.facturacion.ticket', compact('venta', 'empresa', 'documento'));
        }
    }

    /**
     * Generar factura PDF
     *
     * @param Venta $venta
     * @param Empresa $empresa
     * @return mixed
     */
    public function generarFactura(Venta $venta, Empresa $empresa)
    {
        $cliente = Cliente::withoutGlobalScope('empresa')->find($venta->id_cliente);
        $numeroALetras = $this->convertirNumeroALetras($venta->total);
        $dolares = $numeroALetras['dolares'];
        $centavos = $numeroALetras['centavos'];
        
        $idEmpresa = Auth::user()->id_empresa;
        $vista = $this->obtenerVistaFactura($idEmpresa);
        $configuracionPapel = $this->obtenerConfiguracionPapelFactura($idEmpresa);

        $pdf = PDF::loadView($vista, compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
        $pdf->setPaper($configuracionPapel['tipo'], $configuracionPapel['orientacion']);

        return $pdf->stream($empresa->nombre . '-factura-' . $venta->correlativo . '.pdf');
    }

    /**
     * Generar sujeto excluido PDF
     *
     * @param Venta $venta
     * @param Empresa $empresa
     * @return mixed
     */
    public function generarSujetoExcluido(Venta $venta, Empresa $empresa)
    {
        $cliente = Cliente::withoutGlobalScope('empresa')->find($venta->id_cliente);
        $numeroALetras = $this->convertirNumeroALetras($venta->total);
        $dolares = $numeroALetras['dolares'];
        $centavos = $numeroALetras['centavos'];
        
        $idEmpresa = Auth::user()->id_empresa;
        $vista = $this->obtenerVistaSujetoExcluido($idEmpresa);
        $configuracionPapel = $this->obtenerConfiguracionPapelSujetoExcluido($idEmpresa);

        $pdf = PDF::loadView($vista, compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
        $pdf->setPaper($configuracionPapel['tipo'], $configuracionPapel['orientacion']);

        return $pdf->stream($empresa->nombre . '-factura-' . $venta->correlativo . '.pdf');
    }

    /**
     * Generar crédito fiscal PDF
     *
     * @param Venta $venta
     * @param Empresa $empresa
     * @return mixed
     */
    public function generarCreditoFiscal(Venta $venta, Empresa $empresa)
    {
        $cliente = Cliente::withoutGlobalScope('empresa')->findOrfail($venta->id_cliente);
        $numeroALetras = $this->convertirNumeroALetras($venta->total);
        $dolares = $numeroALetras['dolares'];
        $centavos = $numeroALetras['centavos'];
        
        $idEmpresa = Auth::user()->id_empresa;
        $vista = $this->obtenerVistaCreditoFiscal($idEmpresa);
        $configuracionPapel = $this->obtenerConfiguracionPapelCreditoFiscal($idEmpresa);

        $pdf = PDF::loadView($vista, compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
        $pdf->setPaper($configuracionPapel['tipo'], $configuracionPapel['orientacion']);

        return $pdf->stream($empresa->nombre . '-credito-' . $venta->correlativo . '.pdf');
    }

    /**
     * Convertir número a letras
     *
     * @param float $total
     * @return array ['dolares' => string, 'centavos' => string]
     */
    protected function convertirNumeroALetras(float $total): array
    {
        $formatter = new NumeroALetras();
        $n = explode(".", number_format($total, 2));

        return [
            'dolares' => $formatter->toWords(floatval(str_replace(',', '', $n[0]))),
            'centavos' => $formatter->toWords($n[1])
        ];
    }

    /**
     * Calcular dimensiones para ticket PDF
     *
     * @param Venta $venta
     * @return array
     */
    protected function calcularDimensionesTicket(Venta $venta): array
    {
        $alto_base = 220; // mm
        $alto_por_producto = 7; // mm por línea
        $total_lineas = $venta->detalles()->count();
        $alto_total_mm = $alto_base + ($total_lineas * $alto_por_producto);
        
        // Convertir mm a puntos (1mm ≈ 2.83465 pt)
        $alto_total_pt = $alto_total_mm * 2.83465;
        $ancho_pt = 80 * 2.83465; // 80mm de ancho

        return [0, 0, $ancho_pt, $alto_total_pt];
    }

    /**
     * Obtener vista de factura según empresa
     *
     * @param int $idEmpresa
     * @return string
     */
    protected function obtenerVistaFactura(int $idEmpresa): string
    {
        $vistas = [
            38 => 'reportes.facturacion.formatos_empresas.velo',
            212 => 'reportes.facturacion.formatos_empresas.fotopro',
            62 => 'reportes.facturacion.formatos_empresas.hotel-eco',
            84 => 'reportes.facturacion.formatos_empresas.devetsa',
            75 => 'reportes.facturacion.formatos_empresas.Factura-Biovet',
            104 => 'reportes.facturacion.formatos_empresas.factura-Coloretes',
            11 => 'reportes.facturacion.formatos_empresas.Factura-organika',
            12 => 'reportes.facturacion.formatos_empresas.Factura-Ayakahuite',
            128 => 'reportes.facturacion.formatos_empresas.kiero-factura',
            135 => 'reportes.facturacion.formatos_empresas.Dentalkey-factura',
            136 => 'reportes.facturacion.formatos_empresas.Factura-Emerson',
            149 => 'reportes.facturacion.formatos_empresas.Factura-Natura',
            187 => 'reportes.facturacion.formatos_empresas.Express-Shopping',
            130 => 'reportes.facturacion.formatos_empresas.Factura-TecnoGadget',
            24 => 'reportes.facturacion.formatos_empresas.Factura-Via-del-Mar',
            174 => 'reportes.facturacion.formatos_empresas.Factura-Consultora-Raices',
            59 => 'reportes.facturacion.formatos_empresas.Factura-Smartpyme',
            244 => 'reportes.facturacion.formatos_empresas.Factura-keke',
            210 => 'reportes.facturacion.formatos_empresas.Factura-Arborea-desg',
            229 => 'reportes.facturacion.formatos_empresas.Factura-Norbin',
            50 => 'reportes.facturacion.formatos_empresas.RefriAcTotal-Factura',
            274 => 'reportes.facturacion.formatos_empresas.Factura-Flat-Speed-Cars',
            321 => 'reportes.facturacion.formatos_empresas.Factura-Importaciones-Blanco',
            346 => 'reportes.facturacion.formatos_empresas.Factura-Vape-Store',
            154 => 'reportes.facturacion.formatos_empresas.Factura-Estilos-Salon',
            397 => 'reportes.facturacion.formatos_empresas.Factura-Estilos-Salon',
            398 => 'reportes.facturacion.formatos_empresas.Factura-Estilos-Salon',
            396 => 'reportes.facturacion.formatos_empresas.Factura-Estilos-Salon-SA-CV',
            367 => 'reportes.facturacion.formatos_empresas.Factura-Clinica',
            250 => 'reportes.facturacion.formatos_empresas.Factura-Full-Solution',
            400 => 'reportes.facturacion.formatos_empresas.Factura-Zoe-Cosmetics',
            420 => 'reportes.facturacion.formatos_empresas.Factura-Inversiones-Andre',
            315 => 'reportes.facturacion.formatos_empresas.Factura-Sistemas-de-Impresion',
        ];

        return $vistas[$idEmpresa] ?? 'reportes.facturacion.formatos_empresas.factura';
    }

    /**
     * Obtener configuración de papel para factura
     *
     * @param int $idEmpresa
     * @return array
     */
    protected function obtenerConfiguracionPapelFactura(int $idEmpresa): array
    {
        $configuraciones = [
            11 => ['tipo' => [0, 0, 365.669, 566.929133858], 'orientacion' => 'portrait'],
            12 => ['tipo' => [0, 0, 365.669, 566.929133858], 'orientacion' => 'portrait'],
            128 => ['tipo' => [0, 0, 283.46, 765.35], 'orientacion' => 'portrait'],
            135 => ['tipo' => [0, 0, 609.45, 467.72], 'orientacion' => 'portrait'],
            130 => ['tipo' => 'Legal', 'orientacion' => 'landscape'],
            250 => ['tipo' => 'Legal', 'orientacion' => 'portrait'],
        ];

        $default = ['tipo' => 'US Letter', 'orientacion' => 'portrait'];
        return $configuraciones[$idEmpresa] ?? $default;
    }

    /**
     * Obtener vista de sujeto excluido según empresa
     *
     * @param int $idEmpresa
     * @return string
     */
    protected function obtenerVistaSujetoExcluido(int $idEmpresa): string
    {
        $vistas = [
            210 => 'reportes.facturacion.formatos_empresas.Sujeto-Excluido-fact-Arborea-desg',
            367 => 'reportes.facturacion.formatos_empresas.Sujeto-Excluido-Clinica',
            400 => 'reportes.facturacion.formatos_empresas.Sujeto-Zoe-Cosmetics',
        ];

        return $vistas[$idEmpresa] ?? 'reportes.facturacion.factura-sujeto-excluido';
    }

    /**
     * Obtener configuración de papel para sujeto excluido
     *
     * @param int $idEmpresa
     * @return array
     */
    protected function obtenerConfiguracionPapelSujetoExcluido(int $idEmpresa): array
    {
        $default = ['tipo' => 'US Letter', 'orientacion' => 'portrait'];
        return $default;
    }

    /**
     * Obtener vista de crédito fiscal según empresa
     *
     * @param int $idEmpresa
     * @return string
     */
    protected function obtenerVistaCreditoFiscal(int $idEmpresa): string
    {
        $vistas = [
            24 => 'reportes.facturacion.formatos_empresas.vetvia-ccf',
            212 => 'reportes.facturacion.formatos_empresas.CCF-FotoPro',
            38 => 'reportes.facturacion.formatos_empresas.velo-ccf',
            62 => 'reportes.facturacion.formatos_empresas.hotel-eco-ccf',
            128 => 'reportes.facturacion.formatos_empresas.kiero-ccf',
            135 => 'reportes.facturacion.formatos_empresas.Dentalkey-ccf',
            136 => 'reportes.facturacion.formatos_empresas.destroyesa-ccf',
            158 => 'reportes.facturacion.formatos_empresas.Guaca-Mix-ccf',
            177 => 'reportes.facturacion.formatos_empresas.CCF-Credicash',
            187 => 'reportes.facturacion.formatos_empresas.CCF-Express-Shopping',
            130 => 'reportes.facturacion.formatos_empresas.CCF-TecnoGadget',
            84 => 'reportes.facturacion.formatos_empresas.devetsa-cff',
            59 => 'reportes.facturacion.formatos_empresas.smartpyme-ccf',
            210 => 'reportes.facturacion.formatos_empresas.CCF-Arborea-Design',
            244 => 'reportes.facturacion.formatos_empresas.CCF-keke',
            229 => 'reportes.facturacion.formatos_empresas.CCF-Norbin',
            315 => 'reportes.facturacion.formatos_empresas.CCF-Sistema-Impresiones',
            313 => 'reportes.facturacion.formatos_empresas.CCF-American-Laundry',
            274 => 'reportes.facturacion.formatos_empresas.CCF-Flat-Speed-Cars',
            321 => 'reportes.facturacion.formatos_empresas.CCF-Importaciones-Blanco',
            290 => 'reportes.facturacion.formatos_empresas.CCF-Grupo-Lievano',
            367 => 'reportes.facturacion.formatos_empresas.CCF-Clinica',
            250 => 'reportes.facturacion.formatos_empresas.CCF-Full-Solution',
            400 => 'reportes.facturacion.formatos_empresas.CCF-Zoe-Cosmetics',
        ];

        return $vistas[$idEmpresa] ?? 'reportes.facturacion.formatos_empresas.credito';
    }

    /**
     * Obtener configuración de papel para crédito fiscal
     *
     * @param int $idEmpresa
     * @return array
     */
    protected function obtenerConfiguracionPapelCreditoFiscal(int $idEmpresa): array
    {
        $configuraciones = [
            128 => ['tipo' => [0, 0, 283, 765], 'orientacion' => 'portrait'],
            135 => ['tipo' => [0, 0, 609.45, 467.72], 'orientacion' => 'portrait'],
            136 => ['tipo' => [0, 0, 297.64, 382.68], 'orientacion' => 'portrait'],
            130 => ['tipo' => 'Legal', 'orientacion' => 'landscape'],
            250 => ['tipo' => 'Legal', 'orientacion' => 'portrait'],
        ];

        $default = ['tipo' => 'US Letter', 'orientacion' => 'portrait'];
        return $configuraciones[$idEmpresa] ?? $default;
    }
}
