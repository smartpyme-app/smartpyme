<?php

namespace App\Services\Inventario;

use App\Models\Admin\Empresa;
use App\Constants\ConsignaMovimientoTipoConstants;
use App\Models\Inventario\ConsignaCustodia;
use App\Models\Inventario\ConsignaMovimiento;
use App\Models\Ventas\Clientes\Cliente;
use App\Models\Ventas\Venta;
use Illuminate\Http\Request;

/**
 * Reporte «Productos en consigna + historial de movimientos» por cliente (estilo Wellmeds).
 */
class ConsignaSeguimientoVentasService
{
    public function __construct(
        private ConsignaHistorialService $historial,
        private ConsignaProductosVentaListadoService $listadoVentas,
    ) {}

    /**
     * @return list<int>
     */
    public function idsClientesConConsigna(int $idEmpresa, Empresa $empresa): array
    {
        $ids = Venta::query()
            ->where('id_empresa', $idEmpresa)
            ->where('estado', 'Consigna')
            ->where('cotizacion', 0)
            ->whereNotNull('id_cliente')
            ->distinct()
            ->pluck('id_cliente');

        if ($empresa->isModuloConsignacionesLedgerActivo()) {
            $ids = $ids->merge(
                ConsignaCustodia::query()
                    ->forEmpresa($idEmpresa)
                    ->where('cantidad', '>', 0.0001)
                    ->whereNotNull('id_cliente')
                    ->distinct()
                    ->pluck('id_cliente')
            );
        }

        return $ids->unique()->filter()->map(fn ($id) => (int) $id)->sort()->values()->all();
    }

    /**
     * @return array{secciones: list<array{cliente_id: int, cliente_nombre: string, payload: array<string, mixed>}>}
     */
    public function secciones(Request $request, int $idEmpresa, Empresa $empresa): array
    {
        if ($request->filled('id_cliente')) {
            $id = (int) $request->id_cliente;

            return [
                'secciones' => [$this->seccionCliente($request, $idEmpresa, $empresa, $id)],
            ];
        }

        $secciones = [];
        foreach ($this->idsClientesConConsigna($idEmpresa, $empresa) as $id) {
            $secciones[] = $this->seccionCliente($request, $idEmpresa, $empresa, $id);
        }

        return ['secciones' => $secciones];
    }

    /**
     * Hojas de toma física: solo saldo en consigna; columnas toma/facturar las llena el cliente en papel.
     *
     * @return array{secciones: list<array{cliente_id: int, cliente_nombre: string, payload: array<string, mixed>}>}
     */
    public function seccionesTomaInventario(Request $request, int $idEmpresa, Empresa $empresa): array
    {
        // ponytail: no usa historial/ledger completo (era lento); solo remisiones abiertas + custodia ligera
        $idsCliente = $request->filled('id_cliente')
            ? [(int) $request->id_cliente]
            : $this->idsClientesConConsigna($idEmpresa, $empresa);

        $incluirLote = $this->listadoVentas->agruparPorProductoYLote($empresa);
        $secciones = [];

        foreach ($idsCliente as $idCliente) {
            $filas = $this->listadoVentas->filasTomaInventarioPorCliente($idCliente, $idEmpresa, $empresa);

            if ($filas === [] && $empresa->isModuloConsignacionesLedgerActivo()) {
                $filas = $this->filasTomaDesdeCustodia($idEmpresa, $idCliente, $incluirLote);
                $filas = $this->adjuntarCorrelativoConsignaEnLote($filas, $idEmpresa, $idCliente);
            }

            if ($filas === []) {
                continue;
            }

            $cliente = Cliente::withoutGlobalScopes()->find($idCliente);
            $secciones[] = [
                'cliente_id' => $idCliente,
                'cliente_nombre' => $this->nombreCliente($idCliente),
                'cliente_codigo' => trim((string) ($cliente?->codigo_cliente ?? '')) ?: (string) $idCliente,
                'payload' => [
                    'incluir_lote' => $incluirLote,
                    'resumen' => $filas,
                ],
            ];
        }

        return ['secciones' => $secciones];
    }

    /**
     * @return array<string, mixed>
     */
    public function payloadUnCliente(Request $request, int $idEmpresa, Empresa $empresa): array
    {
        $id = (int) $request->id_cliente;

        return $this->seccionCliente($request, $idEmpresa, $empresa, $id)['payload'];
    }

    /**
     * @return array{cliente_id: int, cliente_nombre: string, payload: array<string, mixed>}
     */
    private function seccionCliente(Request $request, int $idEmpresa, Empresa $empresa, int $idCliente): array
    {
        $sub = Request::create('/', 'GET', array_merge($request->all(), [
            'alcance' => 'ventas',
            'id_cliente' => $idCliente,
        ]));

        $nombre = $this->nombreCliente($idCliente);

        if ($empresa->isModuloConsignacionesLedgerActivo()) {
            $payload = $this->historial->reporte($sub, $idEmpresa, $empresa);
        } else {
            $incluirLote = $this->listadoVentas->agruparPorProductoYLote($empresa);
            $payload = [
                'alcance' => 'ventas',
                'incluir_lote' => $incluirLote,
                'tercero' => ['id' => $idCliente, 'nombre' => $nombre],
                'resumen' => $this->listadoVentas->resumenFilasCliente($idCliente, $empresa),
                'movimientos' => [],
            ];
        }

        if (empty($payload['tercero']['nombre'])) {
            $payload['tercero'] = ['id' => $idCliente, 'nombre' => $nombre];
        }

        $cliente = Cliente::withoutGlobalScopes()->find($idCliente);

        return [
            'cliente_id' => $idCliente,
            'cliente_nombre' => $nombre,
            'cliente_codigo' => trim((string) ($cliente?->codigo_cliente ?? '')) ?: (string) $idCliente,
            'payload' => $payload,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function filasTomaDesdeCustodia(int $idEmpresa, int $idCliente, bool $incluirLote): array
    {
        $custodias = ConsignaCustodia::query()
            ->forEmpresa($idEmpresa)
            ->where('id_cliente', $idCliente)
            ->where('cantidad', '>', 0.0001)
            ->with([
                'producto:id,nombre,codigo',
                'bodega:id,nombre',
            ])
            ->orderBy('id_producto')
            ->get();

        $filas = [];
        foreach ($custodias as $c) {
            $filas[] = [
                'id_producto' => (int) $c->id_producto,
                'codigo' => $c->producto?->codigo,
                'producto' => $c->producto?->nombre,
                'bodega' => $c->bodega?->nombre,
                'disponible' => round((float) $c->cantidad, 4),
                'precio_unitario' => 0.0,
                'lote_id' => null,
                'lote' => null,
                'vence' => null,
                'numero_consigna' => null,
            ];
        }

        return $filas;
    }

    /**
     * Un solo query de movimientos para todas las filas (evita N+1).
     *
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    private function adjuntarCorrelativoConsignaEnLote(array $filas, int $idEmpresa, int $idCliente): array
    {
        if ($filas === []) {
            return [];
        }

        $idsProducto = collect($filas)->pluck('id_producto')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
        if ($idsProducto === []) {
            return $filas;
        }

        $tiposEntrega = [
            ConsignaMovimientoTipoConstants::ENTREGA_CONSIGNA_PROPIO,
            ConsignaMovimientoTipoConstants::ENTREGA_CONSIGNA_PROVEEDOR,
        ];

        $porClave = ConsignaMovimiento::query()
            ->forEmpresa($idEmpresa)
            ->where('id_cliente', $idCliente)
            ->whereIn('id_producto', $idsProducto)
            ->whereIn('tipo', $tiposEntrega)
            ->whereNotNull('id_venta')
            ->with('venta:id,correlativo')
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get()
            ->groupBy(fn (ConsignaMovimiento $m) => (int) $m->id_producto . ':' . (int) ($m->lote_id ?? 0));

        foreach ($filas as &$fila) {
            if (! empty($fila['numero_consigna'])) {
                continue;
            }
            $clave = (int) ($fila['id_producto'] ?? 0) . ':' . (int) ($fila['lote_id'] ?? 0);
            $mov = $porClave->get($clave)?->first();
            $corr = $mov?->venta?->correlativo;
            if ($corr !== null && $corr !== '') {
                $fila['numero_consigna'] = $corr;
            }
        }
        unset($fila);

        return $filas;
    }

    private function nombreCliente(int $idCliente): string
    {
        $cliente = Cliente::withoutGlobalScopes()->find($idCliente);
        if (! $cliente) {
            return 'Cliente #' . $idCliente;
        }

        $nombre = trim((string) ($cliente->nombre_comercial ?: $cliente->nombre_empresa ?: ''));
        if ($nombre !== '') {
            return $nombre;
        }

        return trim($cliente->nombre . ' ' . $cliente->apellido) ?: 'Cliente #' . $idCliente;
    }
}