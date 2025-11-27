<?php

namespace App\Services\Ventas;

use App\Models\Ventas\Venta;

class CxcService
{
    /**
     * Obtener cuentas por cobrar
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function obtenerCxc()
    {
        return Venta::where('estado', 'Pendiente')
            ->orderBy('fecha', 'desc')
            ->paginate(10);
    }

    /**
     * Buscar cuentas por cobrar
     *
     * @param string $texto
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function buscarCxc(string $texto)
    {
        return Venta::where('estado', 'Pendiente')
            ->whereHas('cliente', function ($query) use ($texto) {
                $query->where('nombre', 'like', '%' . $texto . '%');
            })
            ->orderBy('fecha', 'desc')
            ->paginate(10);
    }
}


