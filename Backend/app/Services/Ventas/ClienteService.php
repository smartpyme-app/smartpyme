<?php

namespace App\Services\Ventas;

use App\Models\Ventas\Clientes\Cliente;
use App\Models\Ventas\Clientes\ContactoCliente;
use App\Models\Ventas\Venta;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ClienteService
{
    /**
     * Construye query base para listar clientes con filtros
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function construirQueryConFiltros($request)
    {
        $query = Cliente::with('contactos')
            ->where('id', '!=', 1)
            ->withSum('ventas', 'total');

        // Búsqueda general
        if ($request->buscador) {
            $query->where(function ($q) use ($request) {
                $q->where('nombre', 'like', '%' . $request->buscador . '%')
                    ->orWhere('apellido', 'like', '%' . $request->buscador . '%')
                    ->orWhere('nombre_empresa', 'like', '%' . $request->buscador . '%')
                    ->orWhere('nit', 'like', '%' . $request->buscador . '%')
                    ->orWhere('giro', 'like', '%' . $request->buscador . '%')
                    ->orWhere('telefono', 'like', '%' . $request->buscador . '%')
                    ->orWhere('red_social', 'like', '%' . $request->buscador . '%')
                    ->orWhere('ncr', 'like', '%' . $request->buscador . '%')
                    ->orWhere('correo', 'like', '%' . $request->buscador . '%')
                    ->orWhere('dui', 'like', '%' . $request->buscador . '%');
            });
        }

        // Filtros específicos
        $filtros = [
            'nombre',
            'apellido',
            'tipo',
            'fecha_cumpleanos',
            'tipo_contribuyente'
        ];

        foreach ($filtros as $filtro) {
            if ($request->has($filtro)) {
                $query->where($filtro, $request->$filtro);
            }
        }

        // Filtro de estado
        if ($request->estado !== null) {
            $query->where('enable', !!$request->estado);
        }

        // Ordenamiento
        $orden = $request->orden ?? 'id';
        $direccion = $request->direccion ?? 'desc';
        $query->orderBy($orden, $direccion);

        return $query;
    }

    /**
     * Lista clientes con paginación
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function listarClientes($request)
    {
        $query = $this->construirQueryConFiltros($request);
        return $query->paginate($request->paginate ?? 10);
    }

    /**
     * Obtiene lista simple de clientes activos
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function listarClientesActivos()
    {
        return Cliente::orderBy('nombre', 'asc')
            ->where('enable', true)
            ->get();
    }

    /**
     * Busca clientes por término (para autocompletado)
     *
     * @param string $term
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function buscarClientes(string $term, int $limit = 50)
    {
        if (strlen($term) < 2) {
            return collect([]);
        }

        return Cliente::where('enable', true)
            ->where(function ($query) use ($term) {
                $query->where('nombre', 'LIKE', "%{$term}%")
                    ->orWhere('nombre_empresa', 'LIKE', "%{$term}%")
                    ->orWhere('correo', 'LIKE', "%{$term}%")
                    ->orWhere('telefono', 'LIKE', "%{$term}%")
                    ->orWhereRaw("CONCAT(nombre, ' ', apellido) LIKE ?", ["%{$term}%"]);
            })
            ->orderByRaw("
                CASE 
                    WHEN nombre LIKE '{$term}%' THEN 1
                    WHEN nombre_empresa LIKE '{$term}%' THEN 2
                    WHEN CONCAT(nombre, ' ', apellido) LIKE '{$term}%' THEN 3
                    ELSE 4
                END
            ")
            ->orderBy('nombre', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Busca clientes por texto (búsqueda simple)
     *
     * @param string $txt
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function buscarClientesPorTexto(string $txt)
    {
        $txtClean = str_replace('-', '', $txt);

        return Cliente::where(function ($query) use ($txt, $txtClean) {
            $query->where('nombre', 'like', '%' . $txt . '%')
                ->orWhere('apellido', 'like', $txt . '%')
                ->orWhere('nombre_empresa', 'like', $txt . '%')
                ->orWhere('telefono', 'like', $txt . '%')
                ->orWhere('empresa_telefono', 'like', $txt . '%')
                ->orWhere('red_social', 'like', $txt . '%')
                ->orWhere('etiquetas', 'like', $txt . '%')
                ->orWhere('codigo_cliente', 'like', $txt . '%')
                ->orWhereRaw('REPLACE(ncr, "-", "") like ?', [$txtClean . '%'])
                ->orWhereRaw('REPLACE(nit, "-", "") like ?', [$txtClean . '%'])
                ->orWhereRaw('REPLACE(dui, "-", "") like ?', [$txtClean . '%'])
                ->orWhereRaw("CONCAT(nombre, ' ', apellido) like ?", ['%' . $txt . '%']);
        })
        ->where('enable', true)
        ->orderBy('nombre', 'asc')
        ->take(10)
        ->get();
    }

    /**
     * Crea o actualiza un cliente con sus contactos
     *
     * @param array $data
     * @return Cliente
     */
    public function crearOActualizarCliente(array $data): Cliente
    {
        if (isset($data['id'])) {
            $cliente = Cliente::findOrFail($data['id']);
        } else {
            $cliente = new Cliente;
        }

        $contactos = $data['contactos'] ?? null;
        unset($data['contactos']);
        $cliente->fill($data);
        $cliente->save();

        // Manejar contactos si es empresa
        if (isset($data['contactos']) && is_array($data['contactos']) && $data['tipo'] == 'Empresa') {
            $this->sincronizarContactos($cliente, $data['contactos'], isset($data['id']));
        }

        return $cliente->fresh('contactos');
    }

    /**
     * Sincroniza contactos de un cliente
     *
     * @param Cliente $cliente
     * @param array $contactosData
     * @param bool $esActualizacion
     * @return void
     */
    public function sincronizarContactos(Cliente $cliente, array $contactosData, bool $esActualizacion = false): void
    {
        // Si es actualización, eliminar contactos existentes
        if ($esActualizacion) {
            ContactoCliente::where('id_cliente', $cliente->id)->delete();
        }

        // Crear nuevos contactos
        foreach ($contactosData as $contactoData) {
            // Validar que al menos tenga nombre o correo
            if (empty($contactoData['nombre']) && empty($contactoData['name']) &&
                empty($contactoData['correo']) && empty($contactoData['email'])) {
                continue; // Saltar contactos vacíos
            }

            ContactoCliente::create([
                'id_cliente' => $cliente->id,
                'nombre' => $contactoData['nombre'] ?? $contactoData['name'] ?? null,
                'apellido' => $contactoData['apellido'] ?? $contactoData['lastname'] ?? null,
                'correo' => $contactoData['correo'] ?? $contactoData['email'] ?? null,
                'telefono' => $contactoData['telefono'] ?? null,
                'cargo' => $contactoData['cargo'] ?? null,
                'sexo' => $contactoData['sexo'] ?? null,
                'red_social' => $contactoData['red_social'] ?? null,
                'fecha_nacimiento' => $contactoData['fecha_nacimiento'] ?? null,
                'nota' => $contactoData['nota'] ?? null
            ]);
        }
    }

    /**
     * Elimina un cliente
     *
     * @param int $id
     * @return Cliente
     * @throws \Exception
     */
    public function eliminarCliente(int $id): Cliente
    {
        $cliente = Cliente::findOrFail($id);

        // Validar que no tenga ventas asociadas
        $tieneVentas = Venta::where('id_cliente', $id)->exists();
        if ($tieneVentas) {
            throw new \Exception('No se puede eliminar un cliente que tiene ventas asociadas');
        }

        $cliente->delete();
        return $cliente;
    }

    /**
     * Obtiene ventas de un cliente
     *
     * @param int $clienteId
     * @param array $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function obtenerVentasCliente(int $clienteId, array $filtros = [])
    {
        $query = Venta::where('id_cliente', $clienteId);

        // Si no se especifica estado o no es 'Anulada', excluir anuladas
        if (!isset($filtros['estado']) || $filtros['estado'] != 'Anulada') {
            $query->where('estado', '!=', 'Anulada');
        }

        // Aplicar filtros
        if (isset($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }

        if (isset($filtros['metodo_pago'])) {
            $query->where('metodo_pago', $filtros['metodo_pago']);
        }

        return $query->withAccessorRelations()
            ->orderBy('id', 'desc')
            ->paginate($filtros['paginate'] ?? 10);
    }

    /**
     * Obtiene créditos de un cliente
     *
     * @param int $clienteId
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function obtenerCreditosCliente(int $clienteId)
    {
        // El modelo Credito puede no existir, usar la relación del modelo Cliente
        $cliente = Cliente::findOrFail($clienteId);
        return $cliente->creditos()->orderBy('id', 'desc')->paginate(10);
    }

    /**
     * Obtiene clientes con cuentas por cobrar
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function obtenerClientesConCxC()
    {
        $clientes = Cliente::where('id', '!=', 1)
            ->whereRaw('clientes.id in (select id_cliente from ventas where estado = ?)', ['Pendiente'])
            ->paginate(10);

        foreach ($clientes as $cliente) {
            $ventasPendientes = $cliente->ventas()->where('estado', 'Pendiente')->get();
            $cliente->num_ventas_pendientes = $ventasPendientes->count();
            $cliente->pago_pendiente = $ventasPendientes->sum('total');
        }

        return $clientes;
    }

    /**
     * Busca clientes con cuentas por cobrar
     *
     * @param string $txt
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function buscarClientesConCxC(string $txt)
    {
        return Cliente::where('id', '!=', 1)
            ->where(function ($q) use ($txt) {
                $q->where('nombre', 'like', '%' . $txt . '%')
                    ->orWhere('registro', 'like', $txt . '%')
                    ->orWhereRaw('REPLACE(registro, "-", "") like "' . $txt . '"');
            })
            ->whereRaw('clientes.id in (select id_cliente from ventas where estado = ?)', ['Pendiente'])
            ->paginate(10);
    }

    /**
     * Obtiene datos estadísticos de un cliente en un rango de fechas
     *
     * @param int $clienteId
     * @param string $fechaInicio
     * @param string $fechaFin
     * @return array
     */
    public function obtenerDatosCliente(int $clienteId, string $fechaInicio, string $fechaFin): array
    {
        $cliente = Cliente::where('id', $clienteId)->firstOrFail();
        $ventas = $cliente->ventas()->whereBetween('fecha', [$fechaInicio, $fechaFin])->get();

        $totalVentasPagadas = $ventas->where('estado', 'Pagada')->sum('total');
        $totalVentasPendientes = $ventas->where('estado', 'Pendiente')->sum('total');
        $totalBalance = $totalVentasPagadas - $totalVentasPendientes;

        return [
            'cliente' => $cliente,
            'total_ventas_pagadas' => $totalVentasPagadas,
            'total_ventas_pendientes' => $totalVentasPendientes,
            'total_balance' => $totalBalance
        ];
    }

    /**
     * Obtiene datos para dashboard de clientes
     *
     * @return \stdClass
     */
    public function obtenerDatosDashboard()
    {
        $datos = new \stdClass();

        // Top 5 clientes por número de ventas
        $datos->ventas = Venta::selectRaw('count(id) AS total, id_cliente, (select nombre from clientes where id_cliente = id) as nombre')
            ->groupBy('id_cliente')
            ->orderBy('total', 'desc')
            ->take(5)
            ->get();

        // Top 5 municipios
        $datos->municipios = Cliente::selectRaw('count(id) AS total, municipio')
            ->groupBy('municipio')
            ->orderBy('total', 'desc')
            ->take(5)
            ->get();

        return $datos;
    }

    /**
     * Crea o actualiza un contacto de cliente
     *
     * @param array $data
     * @return ContactoCliente
     */
    public function crearOActualizarContacto(array $data): ContactoCliente
    {
        return ContactoCliente::updateOrCreate(
            ['id' => $data['id'] ?? null],
            $data
        );
    }

    /**
     * Elimina un contacto de cliente
     *
     * @param int $id
     * @return ContactoCliente
     */
    public function eliminarContacto(int $id): ContactoCliente
    {
        $contacto = ContactoCliente::findOrFail($id);
        $contacto->delete();
        return $contacto;
    }
}
