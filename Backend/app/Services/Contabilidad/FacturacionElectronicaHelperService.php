<?php

namespace App\Services\Contabilidad;

use App\Models\Admin\Empresa;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Devoluciones\Devolucion as DevolucionVenta;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class FacturacionElectronicaHelperService
{
    /**
     * Obtiene la empresa del usuario autenticado
     *
     * @return Empresa|null
     */
    public function obtenerEmpresa(): ?Empresa
    {
        return Auth::user()->empresa()->first();
    }

    /**
     * Verifica si la empresa tiene facturación electrónica habilitada
     *
     * @return bool
     */
    public function tieneFacturacionElectronica(): bool
    {
        $empresa = $this->obtenerEmpresa();
        return $empresa && $empresa->facturacion_electronica === true;
    }

    /**
     * Filtra ventas según si tienen facturación electrónica o no
     *
     * @param Collection $ventas
     * @return Collection
     */
    public function filtrarVentasPorFacturacionElectronica(Collection $ventas): Collection
    {
        if ($this->tieneFacturacionElectronica()) {
            // Con facturación electrónica: solo ventas con sello_mh
            $ventasSinSello = $ventas->filter(function ($venta) {
                return empty($venta->sello_mh);
            });

            if ($ventasSinSello->isNotEmpty()) {
                Log::warning('Se excluyeron ventas sin sello', [
                    'ventas' => $ventasSinSello->pluck('id'),
                ]);
            }

            return $ventas->reject(function ($venta) {
                return empty($venta->sello_mh);
            });
        } else {
            // Sin facturación electrónica: todas las ventas
            return $ventas;
        }
    }

    /**
     * Obtiene el código de generación o correlativo según facturación electrónica
     *
     * @param Venta $venta
     * @return string
     */
    public function obtenerCodigoGeneracion(Venta $venta): string
    {
        if ($this->tieneFacturacionElectronica() && $venta->sello_mh && isset($venta->dte['identificacion']['codigoGeneracion'])) {
            return $venta->dte['identificacion']['codigoGeneracion'];
        }
        return trim((string) $venta->correlativo);
    }

    /**
     * Obtiene el número de control según facturación electrónica
     *
     * @param Venta $venta
     * @return string
     */
    public function obtenerNumeroControl(Venta $venta): string
    {
        if ($this->tieneFacturacionElectronica() && $venta->sello_mh && isset($venta->dte['identificacion']['numeroControl'])) {
            return $venta->dte['identificacion']['numeroControl'];
        }
        return $venta->numero_control ?? trim((string) $venta->correlativo);
    }

    /**
     * Obtiene el sello según facturación electrónica
     *
     * @param Venta $venta
     * @return string
     */
    public function obtenerSello(Venta $venta): string
    {
        if ($this->tieneFacturacionElectronica() && isset($venta->dte['sello'])) {
            return $venta->dte['sello'];
        }
        return $venta->sello_mh ?? '';
    }

    /**
     * Obtiene la clase de documento (DTE o Impreso)
     *
     * @param Venta $venta
     * @return string
     */
    public function obtenerClaseDocumento(Venta $venta): string
    {
        if ($this->tieneFacturacionElectronica() && $venta->sello_mh) {
            return '4'; // DTE
        }
        return '1'; // Impreso
    }

    /**
     * Obtiene el código de generación para devoluciones
     *
     * @param DevolucionVenta $devolucion
     * @return string
     */
    public function obtenerCodigoGeneracionDevolucion(DevolucionVenta $devolucion): string
    {
        if ($this->tieneFacturacionElectronica()) {
            $dte = $devolucion->dte ?? [];
            if ($devolucion->codigo_generacion) {
                return $devolucion->codigo_generacion;
            }
            if (isset($dte['identificacion']['codigoGeneracion'])) {
                return $dte['identificacion']['codigoGeneracion'];
            }
        }
        return trim((string) $devolucion->correlativo);
    }

    /**
     * Obtiene el número de control para devoluciones
     *
     * @param DevolucionVenta $devolucion
     * @return string
     */
    public function obtenerNumeroControlDevolucion(DevolucionVenta $devolucion): string
    {
        if ($this->tieneFacturacionElectronica()) {
            $dte = $devolucion->dte ?? [];
            if ($devolucion->numero_control) {
                return $devolucion->numero_control;
            }
            if (isset($dte['identificacion']['numeroControl'])) {
                return $dte['identificacion']['numeroControl'];
            }
        }
        return trim((string) $devolucion->correlativo);
    }

    /**
     * Obtiene el sello para devoluciones
     *
     * @param DevolucionVenta $devolucion
     * @return string
     */
    public function obtenerSelloDevolucion(DevolucionVenta $devolucion): string
    {
        if ($this->tieneFacturacionElectronica()) {
            $dte = $devolucion->dte ?? [];
            if (isset($dte['sello'])) {
                return $dte['sello'];
            }
        }
        return $devolucion->sello_mh ?? '';
    }
}

