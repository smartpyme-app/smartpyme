<?php

namespace App\Services\Ventas;

use App\Models\Ventas\Venta;
use App\Models\Admin\Caja;
use Carbon\Carbon;
use JWTAuth;

class CorteService
{
    /**
     * Obtener ventas del corte
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function obtenerVentasCorte()
    {
        $usuario = JWTAuth::parseToken()->authenticate();
        $caja = Caja::where('id', $usuario->id_caja)
            ->with('corte')
            ->firstOrFail();
        
        $corte = $caja->corte;
        
        return $corte->ventas()
            ->orderBy('id', 'desc')
            ->paginate(30);
    }

    /**
     * Obtener ventas pendientes
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function obtenerVentasPendientes()
    {
        $usuario = JWTAuth::parseToken()->authenticate();
        $caja = Caja::where('id', $usuario->id_caja)
            ->with('corte')
            ->firstOrFail();
        
        $corte = $caja->corte;

        if ($corte) {
            if (!$corte->cierre) {
                $corte->cierre = Carbon::now()->toDateTimeString();
            }

            return $corte->ventas()
                ->where('estado', 'En Proceso')
                ->orderBy('id', 'desc')
                ->paginate(5000);
        }

        return Venta::where('estado', 'En Proceso')
            ->orderBy('id', 'desc')
            ->paginate(5000);
    }

    /**
     * Obtener ventas del vendedor
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function obtenerVentasVendedor()
    {
        $usuario = JWTAuth::parseToken()->authenticate();
        
        return Venta::where('estado', 'En Proceso')
            ->where('id_usuario', $usuario->id)
            ->orderBy('id', 'desc')
            ->paginate(5000);
    }
}


