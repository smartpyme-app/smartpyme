<?php

namespace App\Services\Ventas;

use App\Models\Ventas\Venta;
use App\Models\Admin\Documento;
use App\Models\Admin\Empresa;
use App\Models\Ventas\Clientes\Cliente;
use Luecano\NumeroALetras\NumeroALetras;
use Barryvdh\DomPDF\Facade as PDF;
use Illuminate\Support\Facades\Auth;

class DocumentoService
{
    /**
     * Generar documento PDF según el tipo
     *
     * @param int $ventaId
     * @return mixed
     */
    public function generarDocumento(int $ventaId)
    {
        $venta = Venta::where('id', $ventaId)
            ->with('detalles', 'empresa')
            ->firstOrFail();

        $documento = Documento::findOrfail($venta->id_documento);
        $empresa = Empresa::findOrfail(Auth::user()->id_empresa);

        if ($documento->nombre == 'Ticket') {
            return view('reportes.facturacion.ticket', compact('venta', 'empresa', 'documento'));
        }

        if ($documento->nombre == 'Factura') {
            return $this->generarFactura($venta, $empresa);
        }

        if ($documento->nombre == 'Crédito fiscal') {
            return $this->generarCreditoFiscal($venta, $empresa);
        }

        throw new \Exception('Tipo de documento no soportado');
    }

    /**
     * Generar factura PDF
     *
     * @param Venta $venta
     * @param Empresa $empresa
     * @return mixed
     */
    protected function generarFactura(Venta $venta, Empresa $empresa)
    {
        $cliente = Cliente::withoutGlobalScope('empresa')->find($venta->id_cliente);
        $formatter = new NumeroALetras();
        $n = explode(".", number_format($venta->total, 2));

        $dolares = $formatter->toWords(floatval(str_replace(',', '', $n[0])));
        $centavos = $formatter->toWords($n[1]);

        $idEmpresa = Auth::user()->id_empresa;
        $vista = $this->obtenerVistaFactura($idEmpresa);
        $configuracionPapel = $this->obtenerConfiguracionPapelFactura($idEmpresa);

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
    protected function generarCreditoFiscal(Venta $venta, Empresa $empresa)
    {
        $cliente = Cliente::withoutGlobalScope('empresa')->findOrfail($venta->id_cliente);
        $formatter = new NumeroALetras();
        $n = explode(".", number_format($venta->total, 2));

        $dolares = $formatter->toWords(floatval(str_replace(',', '', $n[0])));
        $centavos = $formatter->toWords($n[1]);

        $idEmpresa = Auth::user()->id_empresa;
        $vista = $this->obtenerVistaCreditoFiscal($idEmpresa);
        $configuracionPapel = $this->obtenerConfiguracionPapelCreditoFiscal($idEmpresa);

        $pdf = PDF::loadView($vista, compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
        $pdf->setPaper($configuracionPapel['tipo'], $configuracionPapel['orientacion']);

        return $pdf->stream($empresa->nombre . '-credito-' . $venta->correlativo . '.pdf');
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
            177 => 'reportes.facturacion.formatos_empresas.Factura-TecnoGadget',
            24 => 'reportes.facturacion.formatos_empresas.Factura-Via-del-Mar',
            174 => 'reportes.facturacion.formatos_empresas.Factura-Consultora-Raices',
            59 => 'reportes.facturacion.formatos_empresas.Factura-Smartpyme',
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
            136 => ['tipo' => [0, 0, 365.669, 609.4488], 'orientacion' => 'portrait'],
        ];

        $default = ['tipo' => 'US Letter', 'orientacion' => 'portrait'];
        return $configuraciones[$idEmpresa] ?? $default;
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
            84 => 'reportes.facturacion.formatos_empresas.devetsa-cff',
            59 => 'reportes.facturacion.formatos_empresas.smartpyme-ccf',
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
            177 => ['tipo' => 'Legal', 'orientacion' => 'portrait'],
        ];

        $default = ['tipo' => 'US Letter', 'orientacion' => 'portrait'];
        return $configuraciones[$idEmpresa] ?? $default;
    }
}


