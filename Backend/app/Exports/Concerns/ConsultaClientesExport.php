<?php

namespace App\Exports\Concerns;

use App\Models\Ventas\Clientes\Cliente;
use Illuminate\Database\Eloquent\Builder;

/**
 * Consulta común de los exports de clientes.
 *
 * Se usa con FromQuery: Laravel Excel la lee por partes (1000 filas por defecto) en lugar de
 * cargar todos los clientes en memoria con ->get(). Con 16 mil clientes el ->get() superaba
 * el memory_limit de 128 MB del sitio.
 */
trait ConsultaClientesExport
{
    protected function consultaClientes(?string $tipo): Builder
    {
        $request = $this->request;
        $buscador = $request->buscador;

        $columnasOrden = ['nombre', 'apellido', 'nombre_empresa', 'nombre_comercial', 'codigo_cliente',
            'tipo', 'nit', 'dui', 'ncr', 'telefono', 'correo', 'enable', 'created_at', 'updated_at', 'id'];
        $orden = in_array($request->orden, $columnasOrden, true) ? $request->orden : 'nombre';
        $direccion = strtolower((string) $request->direccion) === 'desc' ? 'desc' : 'asc';

        return Cliente::query()
            ->where('clientes.id', '!=', 1)
            // Agrupado: sin el paréntesis, los orWhere del buscador anulaban los filtros de tipo/estado.
            ->when($buscador, function ($query) use ($buscador) {
                $query->where(function ($q) use ($buscador) {
                    $q->where('nombre', 'like', '%' . $buscador . '%')
                        ->orWhere('nombre_empresa', 'like', '%' . $buscador . '%')
                        ->orWhere('nombre_comercial', 'like', '%' . $buscador . '%')
                        ->orWhere('nit', 'like', '%' . $buscador . '%')
                        ->orWhere('giro', 'like', '%' . $buscador . '%')
                        ->orWhere('telefono', 'like', '%' . $buscador . '%')
                        ->orWhere('ncr', 'like', '%' . $buscador . '%')
                        ->orWhere('dui', 'like', '%' . $buscador . '%');
                });
            })
            ->when($request->estado !== null, function ($q) use ($request) {
                $q->where('enable', (bool) $request->estado);
            })
            ->when($tipo !== null, fn ($q) => $q->where('tipo', $tipo))
            ->orderBy($orden, $direccion)
            // Desempate estable: la lectura por partes pagina con offset y sin esto podría repetir u omitir filas.
            ->orderBy('clientes.id', 'asc');
    }
}
