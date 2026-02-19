<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class RecuperarVentasPerdidasService
{
    protected $fechaInicio;
    protected $fechaFin;

    public function __construct(string $fechaInicio, string $fechaFin)
    {
        $this->fechaInicio = $fechaInicio;
        $this->fechaFin = $fechaFin;
    }

    /**
     * Obtiene ventas que existen en sp_nova pero no en vps.
     * Criterio: fecha + correlativo + id_empresa + id_sucursal
     */
    public function getVentasPerdidas(): array
    {
        $ventasNova = DB::connection('mysql_sp_nova')
            ->table('ventas')
            ->whereBetween('fecha', [$this->fechaInicio, $this->fechaFin])
            ->where('cotizacion', 0)
            ->where(function ($q) {
                $q->where('estado', '!=', 'Anulada')->orWhereNull('estado');
            })
            ->get();

        $ventasPerdidas = [];
        foreach ($ventasNova as $venta) {
            $existeEnVps = DB::connection('mysql')
                ->table('ventas')
                ->where('fecha', $venta->fecha)
                ->where('id_empresa', $venta->id_empresa)
                ->where('id_sucursal', $venta->id_sucursal)
                ->where(function ($q) use ($venta) {
                    if ($venta->correlativo !== null) {
                        $q->where('correlativo', $venta->correlativo);
                    } else {
                        $q->whereNull('correlativo')
                            ->where('total', round($venta->total, 2))
                            ->where('id_cliente', $venta->id_cliente);
                    }
                })
                ->exists();

            if (!$existeEnVps) {
                $ventasPerdidas[] = $venta;
            }
        }

        return $ventasPerdidas;
    }

    /**
     * Obtiene clientes que están en ventas perdidas y no existen en vps.
     * Compara por identificadores de negocio (nit, dui, codigo_cliente, nombre)
     * en lugar de ID, ya que los IDs pueden duplicarse entre bases.
     */
    public function getClientesPerdidos(array $ventasPerdidas): array
    {
        $idsCliente = array_unique(array_filter(array_column($ventasPerdidas, 'id_cliente')));

        if (empty($idsCliente)) {
            return [];
        }

        $clientesNova = DB::connection('mysql_sp_nova')
            ->table('clientes')
            ->whereIn('id', $idsCliente)
            ->get()
            ->keyBy('id');

        $clientesPerdidos = [];
        foreach ($idsCliente as $idCliente) {
            if (empty($idCliente) || !isset($clientesNova[$idCliente])) {
                continue;
            }

            $cliente = $clientesNova[$idCliente];
            if (!$this->clienteExisteEnVps($cliente)) {
                $clientesPerdidos[] = $cliente;
            }
        }

        return array_values($clientesPerdidos);
    }

    /**
     * Verifica si un cliente de sp_nova existe en vps usando identificadores de negocio.
     * Orden: nit, dui, codigo_cliente, nombre_empresa o nombre+apellido.
     */
    protected function clienteExisteEnVps(object $cliente): bool
    {
        $idEmpresa = $cliente->id_empresa ?? null;
        if (empty($idEmpresa)) {
            return false;
        }

        $query = DB::connection('mysql')
            ->table('clientes')
            ->where('id_empresa', $idEmpresa);

        $nit = $this->normalizarIdentificador($cliente->nit ?? '');
        $dui = $this->normalizarIdentificador($cliente->dui ?? '');
        $codigoCliente = trim($cliente->codigo_cliente ?? '');
        $nombreEmpresa = trim($cliente->nombre_empresa ?? '');
        $nombreCompleto = trim(($cliente->nombre ?? '') . ' ' . ($cliente->apellido ?? ''));

        if (!empty($nit)) {
            $existe = (clone $query)->whereRaw('REPLACE(REPLACE(REPLACE(REPLACE(TRIM(nit), " ", ""), "-", ""), ".", ""), ",", "") = ?', [$nit])->exists();
            if ($existe) return true;
        }

        if (!empty($dui)) {
            $existe = (clone $query)->whereRaw('REPLACE(REPLACE(REPLACE(REPLACE(TRIM(dui), " ", ""), "-", ""), ".", ""), ",", "") = ?', [$dui])->exists();
            if ($existe) return true;
        }

        if (!empty($codigoCliente)) {
            $existe = (clone $query)->where('codigo_cliente', $codigoCliente)->exists();
            if ($existe) return true;
        }

        if (!empty($nombreEmpresa) && ($cliente->tipo ?? '') === 'Empresa') {
            $existe = (clone $query)->where('nombre_empresa', $nombreEmpresa)->exists();
            if ($existe) return true;
        }

        if (!empty($nombreCompleto)) {
            $existe = (clone $query)
                ->where('nombre', trim($cliente->nombre ?? ''))
                ->where('apellido', trim($cliente->apellido ?? ''))
                ->exists();
            if ($existe) return true;
        }

        return false;
    }

    /**
     * Normaliza NIT/DUI: quita espacios, guiones, puntos, comas.
     */
    protected function normalizarIdentificador(string $valor): string
    {
        return preg_replace('/[\s\-.,]+/', '', trim($valor));
    }

    /**
     * Obtiene el id del cliente en vps por identificadores de negocio.
     * Retorna null si no existe.
     */
    public function getIdClienteEnVps(object $cliente): ?int
    {
        $idEmpresa = $cliente->id_empresa ?? null;
        if (empty($idEmpresa)) {
            return null;
        }

        $query = DB::connection('mysql')
            ->table('clientes')
            ->where('id_empresa', $idEmpresa);

        $nit = $this->normalizarIdentificador($cliente->nit ?? '');
        $dui = $this->normalizarIdentificador($cliente->dui ?? '');
        $codigoCliente = trim($cliente->codigo_cliente ?? '');
        $nombreEmpresa = trim($cliente->nombre_empresa ?? '');
        $nombreCompleto = trim(($cliente->nombre ?? '') . ' ' . ($cliente->apellido ?? ''));

        if (!empty($nit)) {
            $clienteVps = (clone $query)->whereRaw('REPLACE(REPLACE(REPLACE(REPLACE(TRIM(nit), " ", ""), "-", ""), ".", ""), ",", "") = ?', [$nit])->first();
            if ($clienteVps) return (int) $clienteVps->id;
        }

        if (!empty($dui)) {
            $clienteVps = (clone $query)->whereRaw('REPLACE(REPLACE(REPLACE(REPLACE(TRIM(dui), " ", ""), "-", ""), ".", ""), ",", "") = ?', [$dui])->first();
            if ($clienteVps) return (int) $clienteVps->id;
        }

        if (!empty($codigoCliente)) {
            $clienteVps = (clone $query)->where('codigo_cliente', $codigoCliente)->first();
            if ($clienteVps) return (int) $clienteVps->id;
        }

        if (!empty($nombreEmpresa) && ($cliente->tipo ?? '') === 'Empresa') {
            $clienteVps = (clone $query)->where('nombre_empresa', $nombreEmpresa)->first();
            if ($clienteVps) return (int) $clienteVps->id;
        }

        if (!empty($nombreCompleto)) {
            $clienteVps = (clone $query)
                ->where('nombre', trim($cliente->nombre ?? ''))
                ->where('apellido', trim($cliente->apellido ?? ''))
                ->first();
            if ($clienteVps) return (int) $clienteVps->id;
        }

        return null;
    }

    /**
     * Obtiene datos para exportar a archivos PHP (importación en prod sin sp_nova).
     * Resuelve id_cliente: para clientes perdidos usa 'nova_X', para existentes usa id vps.
     */
    public function getDatosParaExportar(): array
    {
        $ventasPerdidas = $this->getVentasPerdidas();
        $clientesPerdidos = $this->getClientesPerdidos($ventasPerdidas);
        $detallesPorVenta = $this->getDetallesVentasPerdidas($ventasPerdidas);

        $clientesNova = DB::connection('mysql_sp_nova')
            ->table('clientes')
            ->whereIn('id', array_filter(array_unique(array_column($ventasPerdidas, 'id_cliente'))))
            ->get()
            ->keyBy('id');

        $idsClientePerdido = array_column($clientesPerdidos, 'id');

        $clientesExport = [];
        foreach ($clientesPerdidos as $c) {
            $clientesExport[] = [
                'id_nova' => $c->id,
                'data' => (array) $c,
            ];
        }

        $ventasExport = [];
        foreach ($ventasPerdidas as $v) {
            $ventaArr = (array) $v;
            $idClienteNova = $v->id_cliente ?? null;
            if (empty($idClienteNova)) {
                $ventaArr['id_cliente_ref'] = null;
            } elseif (in_array($idClienteNova, $idsClientePerdido)) {
                $ventaArr['id_cliente_ref'] = 'nova_' . $idClienteNova;
            } else {
                $clienteNova = $clientesNova[$idClienteNova] ?? null;
                $ventaArr['id_cliente_ref'] = $clienteNova ? $this->getIdClienteEnVps($clienteNova) : null;
            }
            unset($ventaArr['id_cliente']);
            $ventasExport[] = [
                'id_nova' => $v->id,
                'data' => $ventaArr,
            ];
        }

        $detallesExport = [];
        foreach ($ventasPerdidas as $v) {
            $detalles = $detallesPorVenta[$v->id] ?? [];
            foreach ($detalles as $d) {
                $detallesExport[] = [
                    'id_venta_ref' => 'nova_' . $v->id,
                    'data' => (array) $d,
                ];
            }
        }

        return [
            'metadata' => [
                'fecha_export' => date('Y-m-d H:i:s'),
                'fecha_inicio' => $this->fechaInicio,
                'fecha_fin' => $this->fechaFin,
            ],
            'clientes' => $clientesExport,
            'ventas' => $ventasExport,
            'detalles_venta' => $detallesExport,
        ];
    }

    /**
     * Obtiene detalles_venta para las ventas perdidas.
     */
    public function getDetallesVentasPerdidas(array $ventasPerdidas): array
    {
        if (empty($ventasPerdidas)) {
            return [];
        }

        $idsVenta = array_column($ventasPerdidas, 'id');

        return DB::connection('mysql_sp_nova')
            ->table('detalles_venta')
            ->whereIn('id_venta', $idsVenta)
            ->get()
            ->groupBy('id_venta')
            ->toArray();
    }

    /**
     * Obtiene datos completos: ventas perdidas, clientes perdidos y detalles.
     * Agrupado por cliente para la presentación.
     */
    public function getDatosCompletos(): array
    {
        $ventasPerdidas = $this->getVentasPerdidas();
        $clientesPerdidos = $this->getClientesPerdidos($ventasPerdidas);
        $detallesPorVenta = $this->getDetallesVentasPerdidas($ventasPerdidas);

        $clientesNova = DB::connection('mysql_sp_nova')
            ->table('clientes')
            ->whereIn('id', array_filter(array_unique(array_column($ventasPerdidas, 'id_cliente'))))
            ->get()
            ->keyBy('id');

        $empresas = DB::connection('mysql_sp_nova')->table('empresas')->get()->keyBy('id');
        $sucursales = DB::connection('mysql_sp_nova')->table('sucursales')->get()->keyBy('id');

        $ventasPorCliente = [];
        foreach ($ventasPerdidas as $venta) {
            $idCliente = $venta->id_cliente ?? 0;
            $nombreCliente = 'Consumidor Final';
            if ($idCliente && isset($clientesNova[$idCliente])) {
                $c = $clientesNova[$idCliente];
                $nombreCliente = $c->tipo === 'Empresa'
                    ? ($c->nombre_empresa ?? $c->nombre . ' ' . $c->apellido)
                    : trim(($c->nombre ?? '') . ' ' . ($c->apellido ?? ''));
            }
            $venta->nombre_empresa = $empresas[$venta->id_empresa]->nombre ?? '';
            $venta->nombre_sucursal = $sucursales[$venta->id_sucursal]->nombre ?? '';

            $ventasPorCliente[$idCliente]['id_cliente'] = $idCliente;
            $ventasPorCliente[$idCliente]['nombre_cliente'] = $nombreCliente ?: 'Sin nombre';
            $ventasPorCliente[$idCliente]['ventas'][] = [
                'venta' => $venta,
                'detalles' => $detallesPorVenta[$venta->id] ?? [],
            ];
        }

        ksort($ventasPorCliente);

        return [
            'ventas_perdidas' => $ventasPerdidas,
            'clientes_perdidos' => $clientesPerdidos,
            'ventas_por_cliente' => array_values($ventasPorCliente),
            'detalles_por_venta' => $detallesPorVenta,
        ];
    }
}
