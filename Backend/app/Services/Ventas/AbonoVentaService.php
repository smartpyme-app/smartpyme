<?php

namespace App\Services\Ventas;

use App\Models\Ventas\Abono;
use App\Models\Ventas\Venta;
use App\Models\Inventario\Paquete;
use App\Services\Bancos\TransaccionesService;
use App\Services\Bancos\ChequesService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AbonoVentaService
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
        $query = Abono::with('venta');

        if (isset($filters['buscador']) && $filters['buscador']) {
            $query->where(function($q) use ($filters) {
                $q->where('id_venta', 'like', '%' . $filters['buscador'] . '%')
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

        if (isset($filters['id_cliente']) && $filters['id_cliente']) {
            $query->where('id_cliente', $filters['id_cliente']);
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

        if (isset($filters['id_documento']) && $filters['id_documento']) {
            $documento = \App\Models\Admin\Documento::find($filters['id_documento']);
            if ($documento) {
                $query->whereHas('venta.documento', function ($q) use ($documento) {
                    $q->whereRaw('LOWER(nombre) = LOWER(?)', [$documento->nombre]);
                });
            } else {
                $query->whereHas('venta', function ($q) use ($filters) {
                    $q->where('id_documento', $filters['id_documento']);
                });
            }
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
        DB::beginTransaction();

        try {
            $venta = Venta::find($data['id_venta'] ?? null);

            if (isset($data['id']) && $data['id']) {
                $abono = Abono::findOrFail($data['id']);
            } else {
                $abono = new Abono();
            }

            // Obtener el documento y asignar correlativo
            $documento = \App\Models\Admin\Documento::where('nombre', 'Abono de Venta')
                ->where('id_sucursal', $data['id_sucursal'] ?? null)
                ->lockForUpdate()
                ->first();

            $abono->fill($data);
            if ($documento) {
                $abono->id_documento = $documento->id;
                $abono->correlativo = $documento->correlativo;
                $documento->increment('correlativo');
            }
            $abono->save();

            // Actualizar estado de la venta y paquetes
            if ($venta) {
                $this->actualizarEstadoVenta($venta, $abono);
            }

            // Crear transacciones bancarias o cheques si es nuevo
            if (!isset($data['id']) || !$data['id']) {
                $this->crearTransaccionesBancarias($abono, $venta);
            }

            DB::commit();
            return $abono;

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error creando/actualizando abono de venta', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * Actualiza un abono existente
     *
     * @param array $data
     * @return Abono
     */
    public function actualizarAbono(array $data): Abono
    {
        DB::beginTransaction();

        try {
            $abono = Abono::findOrFail($data['id']);
            $venta = Venta::find($data['id_venta'] ?? null);

            $abono->fill($data);
            $abono->save();

            // Actualizar estado de la venta y paquetes
            if ($venta) {
                $this->actualizarEstadoVenta($venta, $abono);
            }

            DB::commit();
            return $abono;

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error actualizando abono de venta', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * Actualiza el estado de la venta según el saldo
     *
     * @param Venta $venta
     * @param Abono $abono
     * @return void
     */
    protected function actualizarEstadoVenta(Venta $venta, Abono $abono): void
    {
        if ($venta->saldo <= 0) {
            $venta->estado = 'Pagada';
            $venta->save();

            // Actualizar paquetes relacionados
            $paquetes = Paquete::where('id_venta', $venta->id)->get();
            foreach ($paquetes as $paquete) {
                $paquete->fecha = $abono->fecha;
                $paquete->estado = 'Facturado';
                $paquete->save();
            }
        } else {
            $venta->estado = 'Pendiente';
            $venta->save();

            // Actualizar paquetes relacionados
            $paquetes = Paquete::where('id_venta', $venta->id)->get();
            foreach ($paquetes as $paquete) {
                $paquete->fecha = $abono->fecha;
                $paquete->estado = 'Pendiente';
                $paquete->save();
            }
        }
    }

    /**
     * Crea transacciones bancarias o cheques según la forma de pago
     *
     * @param Abono $abono
     * @param Venta|null $venta
     * @return void
     */
    protected function crearTransaccionesBancarias(Abono $abono, ?Venta $venta): void
    {
        if (!$venta) {
            return;
        }

        // Crear transacción bancaria
        if ($abono->forma_pago != 'Efectivo' && $abono->forma_pago != 'Cheque') {
            $this->transaccionesService->crear(
                $abono,
                'Abono',
                'Abono de venta: ' . $venta->nombre_documento . ' #' . $venta->correlativo,
                'Abono de Venta'
            );
        }

        // Crear cheque
        if ($abono->forma_pago == 'Cheque') {
            $this->chequesService->crear(
                $abono,
                $venta->nombre_cliente,
                'Abono de venta: ' . $venta->nombre_documento . ' #' . $venta->correlativo,
                'Abono de Venta'
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
        $recibo = Abono::with('documento')->where('id', $id)->firstOrFail();
        $venta = Venta::with('empresa.currency')->where('id', $recibo->id_venta)->firstOrFail();

        return [
            'recibo' => $recibo,
            'venta' => $venta
        ];
    }
}
