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
            $ventasSinComprobante = $ventas->filter(function ($venta) {
                return ! $this->ventaTieneComprobanteElectronicoValido($venta);
            });

            if ($ventasSinComprobante->isNotEmpty()) {
                Log::warning('Se excluyeron ventas sin comprobante electrónico válido', [
                    'ventas' => $ventasSinComprobante->pluck('id'),
                ]);
            }

            return $ventas->reject(function ($venta) {
                return ! $this->ventaTieneComprobanteElectronicoValido($venta);
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
        if ($this->tieneFacturacionElectronica()) {
            if ($this->ventaFeCrConClave($venta)) {
                return (string) $venta->codigo_generacion;
            }
            if ($venta->sello_mh && isset($venta->dte['identificacion']['codigoGeneracion'])) {
                return $venta->dte['identificacion']['codigoGeneracion'];
            }
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
        if ($this->tieneFacturacionElectronica()) {
            if ($this->ventaFeCrConClave($venta)) {
                return (string) $venta->codigo_generacion;
            }
            if ($venta->sello_mh && isset($venta->dte['identificacion']['numeroControl'])) {
                return $venta->dte['identificacion']['numeroControl'];
            }
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
        if ($this->tieneFacturacionElectronica() && $this->ventaTieneComprobanteElectronicoValido($venta)) {
            return '4'; // DTE / comprobante electrónico
        }
        return '1'; // Impreso
    }

    private function ventaTieneComprobanteElectronicoValido(Venta $venta): bool
    {
        if ($this->ventaFeCrConClave($venta)) {
            return true;
        }

        if ($this->ventaFeCrAceptada($venta)) {
            return true;
        }

        return ! empty($venta->sello_mh);
    }

    /** FE CR: clave y sello_mh se guardan al emitir (como correlativo “sellado” en la práctica). */
    private function ventaFeCrConClave(Venta $venta): bool
    {
        $dte = $venta->dte;

        return is_array($dte)
            && ($dte['pais'] ?? null) === 'CR'
            && trim((string) ($venta->codigo_generacion ?? '')) !== '';
    }

    private function ventaFeCrAceptada(Venta $venta): bool
    {
        $dte = $venta->dte;

        return is_array($dte)
            && ($dte['pais'] ?? null) === 'CR'
            && ! empty($dte['cr']['aceptada'])
            && ! empty($venta->codigo_generacion);
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

