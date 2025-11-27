<?php

namespace App\Services\Ventas;

use App\Models\Ventas\Venta;
use App\Models\Admin\Documento;
use Illuminate\Support\Facades\Log;

class VentaQueryService
{
    /**
     * Construir query para listar ventas con filtros
     *
     * @param array $filtros
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function construirQueryConFiltros(array $filtros)
    {
        $query = Venta::query();

        // Filtros de fecha
        if (isset($filtros['inicio'])) {
            $query->where('fecha', '>=', $filtros['inicio']);
        }

        if (isset($filtros['fin'])) {
            $query->where('fecha', '<=', $filtros['fin']);
        }

        // Filtro de recurrente
        if (isset($filtros['recurrente']) && $filtros['recurrente'] !== null) {
            $query->where('recurrente', !!$filtros['recurrente']);
        }

        // Filtro de número de identificación
        if (isset($filtros['num_identificacion'])) {
            $query->where('num_identificacion', $filtros['num_identificacion']);
        }

        // Filtros de relaciones
        if (isset($filtros['id_sucursal'])) {
            $query->where('id_sucursal', $filtros['id_sucursal']);
        }

        if (isset($filtros['id_bodega'])) {
            $query->where('id_bodega', $filtros['id_bodega']);
        }

        if (isset($filtros['id_cliente'])) {
            $query->where('id_cliente', $filtros['id_cliente']);
        }

        if (isset($filtros['id_usuario'])) {
            $query->where('id_usuario', $filtros['id_usuario']);
        }

        // Filtro de forma de pago
        if (isset($filtros['forma_pago'])) {
            $query->where(function ($q) use ($filtros) {
                $q->where('forma_pago', $filtros['forma_pago'])
                    ->orWhereHas('metodos_de_pago', function ($query) use ($filtros) {
                        $query->where('nombre', $filtros['forma_pago']);
                    });
            });
        }

        // Filtro de vendedor
        if (isset($filtros['id_vendedor'])) {
            $query->where(function ($q) use ($filtros) {
                $q->where('id_vendedor', $filtros['id_vendedor'])
                    ->orWhereHas('detalles', function ($query) use ($filtros) {
                        $query->where('id_vendedor', $filtros['id_vendedor']);
                    });
            });
        }

        // Filtro de canal
        if (isset($filtros['id_canal'])) {
            $query->where('id_canal', $filtros['id_canal']);
        }

        // Filtro de proyecto
        if (isset($filtros['id_proyecto'])) {
            $query->where('id_proyecto', $filtros['id_proyecto']);
        }

        // Filtro de documento
        if (isset($filtros['id_documento'])) {
            $documento = Documento::find($filtros['id_documento']);
            if ($documento) {
                $query->whereHas('documento', function ($q) use ($documento) {
                    $q->whereRaw('LOWER(nombre) = LOWER(?)', [$documento->nombre]);
                });
            } else {
                $query->where('id_documento', $filtros['id_documento']);
            }
        }

        // Filtro de estado
        if (isset($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }

        // Filtro de método de pago
        if (isset($filtros['metodo_pago'])) {
            $query->where('metodo_pago', $filtros['metodo_pago']);
        }

        // Filtro de tipo de documento
        if (isset($filtros['tipo_documento'])) {
            Log::info('Filtrando por tipo_documento:', ['tipo_documento' => $filtros['tipo_documento']]);
            $query->whereHas('documento', function ($q) use ($filtros) {
                $q->where('nombre', $filtros['tipo_documento']);
            });
        }

        // Filtro de DTE
        if (isset($filtros['dte'])) {
            if ($filtros['dte'] == 1) {
                $query->whereNull('sello_mh');
            } elseif ($filtros['dte'] == 2) {
                $query->whereNotNull('sello_mh');
            }
        }

        // Excluir cotizaciones
        $query->where('cotizacion', 0);

        // Filtro de búsqueda general
        if (isset($filtros['buscador'])) {
            $buscador = '%' . $filtros['buscador'] . '%';
            $query->where(function ($q) use ($buscador) {
                $q->whereHas('cliente', function ($qCliente) use ($buscador) {
                    $qCliente->where('nombre', 'like', $buscador)
                        ->orWhere('nombre_empresa', 'like', $buscador)
                        ->orWhere('ncr', 'like', $buscador)
                        ->orWhere('nit', 'like', $buscador);
                })
                    ->orWhere('correlativo', 'like', $buscador)
                    ->orWhere('estado', 'like', $buscador)
                    ->orWhere('observaciones', 'like', $buscador)
                    ->orWhere('forma_pago', 'like', $buscador);
            });
        }

        // Agregar relaciones y sumas
        $query->withSum(['abonos' => function ($query) {
            $query->where('estado', 'Confirmado');
        }], 'total')
            ->withSum(['devoluciones' => function ($query) {
                $query->where('enable', 1);
            }], 'total');

        // Ordenamiento
        $orden = $filtros['orden'] ?? 'id';
        $direccion = $filtros['direccion'] ?? 'desc';
        $query->orderBy($orden, $direccion)
            ->orderBy('id', 'desc');

        return $query;
    }

    /**
     * Obtener ventas paginadas
     *
     * @param array $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function obtenerVentasPaginadas(array $filtros)
    {
        $query = $this->construirQueryConFiltros($filtros);
        $paginate = $filtros['paginate'] ?? 15;

        return $query->paginate($paginate);
    }
}


