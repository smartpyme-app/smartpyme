<?php

namespace App\Services\Compras;

use App\Models\Compras\Abono;
use App\Models\Compras\Compra;
use App\Services\Bancos\TransaccionesService;
use App\Services\Bancos\ChequesService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AbonoCompraService
{
    protected $transaccionesService;
    protected $chequesService;

    public function __construct(TransaccionesService $transaccionesService, ChequesService $chequesService)
    {
        $this->transaccionesService = $transaccionesService;
        $this->chequesService = $chequesService;
    }

    /**
     * Lista abonos con filtros
     *
     * @param array $filters
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function listarAbonos(array $filters)
    {
        $query = Abono::with('compra');

        if (isset($filters['buscador']) && $filters['buscador']) {
            $query->where(function($q) use ($filters) {
                $q->where('id_compra', 'like', '%' . $filters['buscador'] . '%')
                  ->orWhere('concepto', 'like', '%' . $filters['buscador'] . '%')
                  ->orWhere('nombre_de', 'like', '%' . $filters['buscador'] . '%');
            });
        }

        if (isset($filters['inicio']) && $filters['inicio']) {
            $query->where('fecha', '>=', $filters['inicio']);
        }

        if (isset($filters['fin']) && $filters['fin']) {
            $query->where('fecha', '<=', $filters['fin']);
        }

        if (isset($filters['id_sucursal']) && $filters['id_sucursal']) {
            $query->where('id_sucursal', $filters['id_sucursal']);
        }

        if (isset($filters['id_usuario']) && $filters['id_usuario']) {
            $query->where('id_usuario', $filters['id_usuario']);
        }

        if (isset($filters['id_proveedor']) && $filters['id_proveedor']) {
            $query->where('id_proveedor', $filters['id_proveedor']);
        }

        if (isset($filters['forma_pago']) && $filters['forma_pago']) {
            $query->where('forma_pago', $filters['forma_pago']);
        }

        if (isset($filters['estado']) && $filters['estado']) {
            $query->where('estado', $filters['estado']);
        }

        if (isset($filters['metodo_pago']) && $filters['metodo_pago']) {
            $query->where('metodo_pago', $filters['metodo_pago']);
        }

        $orden = $filters['orden'] ?? 'id';
        $direccion = $filters['direccion'] ?? 'desc';
        $paginate = $filters['paginate'] ?? 15;

        return $query->orderBy($orden, $direccion)
            ->orderBy('id', 'desc')
            ->paginate($paginate);
    }

    /**
     * Obtiene un abono por ID
     *
     * @param int $id
     * @return Abono
     */
    public function obtenerAbono(int $id): Abono
    {
        return Abono::findOrFail($id);
    }

    /**
     * Crea o actualiza un abono
     *
     * @param array $data
     * @return Abono
     */
    public function crearOActualizarAbono(array $data): Abono
    {
        $compra = Compra::find($data['id_compra'] ?? null);

        if (isset($data['id']) && $data['id']) {
            $abono = Abono::findOrFail($data['id']);
        } else {
            $abono = new Abono();
        }

        $abono->fill($data);
        $abono->save();

        // Actualizar estado de la compra
        if ($compra) {
            $this->actualizarEstadoCompra($compra);
        }

        // Crear transacciones bancarias o cheques si es nuevo
        if (!isset($data['id']) || !$data['id']) {
            $this->crearTransaccionesBancarias($abono, $compra);
        }

        return $abono;
    }

    /**
     * Cambia el estado de un abono
     *
     * @param int $id
     * @param string $estado
     * @return Abono
     */
    public function cambiarEstado(int $id, string $estado): Abono
    {
        DB::beginTransaction();

        try {
            $abono = Abono::findOrFail($id);
            $abono->estado = $estado;
            $abono->save();

            DB::commit();
            return $abono;

        } catch (\Throwable $e) {
            DB::rollback();
            Log::error('Error cambiando estado de abono de compra', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'id' => $id,
                'estado' => $estado
            ]);
            throw $e;
        }
    }

    /**
     * Actualiza el estado de la compra según el saldo
     *
     * @param Compra $compra
     * @return void
     */
    protected function actualizarEstadoCompra(Compra $compra): void
    {
        if ($compra->saldo <= 0) {
            $compra->estado = 'Pagada';
            $compra->save();
        } else {
            $compra->estado = 'Pendiente';
            $compra->save();
        }
    }

    /**
     * Crea transacciones bancarias o cheques según la forma de pago
     *
     * @param Abono $abono
     * @param Compra|null $compra
     * @return void
     */
    protected function crearTransaccionesBancarias(Abono $abono, ?Compra $compra): void
    {
        if (!$compra) {
            return;
        }

        // Crear transacción bancaria
        if ($abono->forma_pago != 'Efectivo' && $abono->forma_pago != 'Cheque') {
            $this->transaccionesService->crear(
                $abono,
                'Abono',
                'Abono de compra: ' . $compra->tipo_documento . ' #' . ($compra->referencia ? $compra->referencia : ''),
                'Abono de Compra'
            );
        }

        // Crear cheque
        if ($abono->forma_pago == 'Cheque') {
            $this->chequesService->crear(
                $abono,
                $compra->nombre_proveedor,
                'Abono de compra: ' . $compra->tipo_documento . ' #' . ($compra->referencia ? $compra->referencia : ''),
                'Abono de Compra'
            );
        }
    }

    /**
     * Elimina un abono
     *
     * @param int $id
     * @return Abono
     */
    public function eliminarAbono(int $id): Abono
    {
        $abono = Abono::findOrFail($id);
        $abono->delete();
        return $abono;
    }

    /**
     * Obtiene datos para imprimir recibo
     *
     * @param int $id
     * @return array
     */
    public function obtenerDatosRecibo(int $id): array
    {
        $recibo = Abono::where('id', $id)->firstOrFail();
        $compra = Compra::where('id', $recibo->id_compra)->firstOrFail();

        return [
            'recibo' => $recibo,
            'compra' => $compra
        ];
    }

    /**
     * Verifica si debe usar plantilla especial de recibo
     *
     * @param int $idEmpresa
     * @return bool
     */
    public function usarPlantillaEspecial(int $idEmpresa): bool
    {
        return $idEmpresa == 38;
    }
}
