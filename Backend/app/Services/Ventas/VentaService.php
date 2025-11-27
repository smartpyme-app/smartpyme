<?php

namespace App\Services\Ventas;

use App\Models\Ventas\Venta;
use App\Models\Ventas\Detalle;
use App\Models\Ventas\DetalleCompuesto;
use App\Models\Ventas\Impuesto;
use App\Models\Ventas\MetodoDePago;
use App\Models\Admin\Documento;
use App\Models\Inventario\Paquete;
use App\Models\Eventos\Evento;
use App\Models\Contabilidad\Proyecto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VentaService
{
    /**
     * Crear o actualizar una venta
     *
     * @param array $data
     * @return Venta
     */
    public function crearOActualizarVenta(array $data): Venta
    {
        if (isset($data['id'])) {
            $venta = Venta::findOrFail($data['id']);
        } else {
            $venta = new Venta();
        }

        $venta->fill($data);
        $venta->save();

        return $venta;
    }

    /**
     * Asignar correlativo a la venta
     *
     * @param Venta $venta
     * @param int $idDocumento
     * @return void
     */
    public function asignarCorrelativo(Venta $venta, int $idDocumento): void
    {
        $documento = Documento::where('id', $idDocumento)
            ->lockForUpdate()
            ->firstOrFail();

        $venta->correlativo = $documento->correlativo;
        $documento->increment('correlativo');
        $venta->save();
    }

    /**
     * Guardar detalles de la venta
     *
     * @param Venta $venta
     * @param array $detalles
     * @return void
     */
    public function guardarDetalles(Venta $venta, array $detalles): void
    {
        foreach ($detalles as $det) {
            if (isset($det['id'])) {
                $detalle = Detalle::findOrFail($det['id']);
            } else {
                $detalle = new Detalle();
            }

            $det['id_venta'] = $venta->id;
            $detalle->fill($det);
            $detalle->save();

            // Procesar paquete si existe
            if (isset($det['id_paquete'])) {
                $this->procesarPaquete($det['id_paquete'], $venta, $detalle);
            }

            // Procesar cita si existe
            if (isset($det['id_cita'])) {
                $this->procesarCita($det['id_cita'], $venta);
            }

            // Procesar composiciones si existen
            if (isset($det['composiciones'])) {
                $this->procesarComposiciones($detalle, $det['composiciones']);
            }
        }
    }

    /**
     * Procesar paquete asociado a un detalle
     *
     * @param int $idPaquete
     * @param Venta $venta
     * @param Detalle $detalle
     * @return void
     */
    protected function procesarPaquete(int $idPaquete, Venta $venta, Detalle $detalle): void
    {
        $paquete = Paquete::find($idPaquete);
        if ($paquete) {
            $paquete->estado = ($venta->estado == 'Pagada') ? 'Facturado' : 'Pendiente';
            $paquete->fecha = $venta->fecha;
            $paquete->id_venta = $venta->id;
            $paquete->id_venta_detalle = $detalle->id;
            $paquete->save();
        }
    }

    /**
     * Procesar cita asociada a un detalle
     *
     * @param int $idCita
     * @param Venta $venta
     * @return void
     */
    protected function procesarCita(int $idCita, Venta $venta): void
    {
        $evento = Evento::findOrfail($idCita);
        if ($venta->estado == 'Pagada') {
            $evento->estado = 'Pagado';
            $evento->estadoVerificarFrecuencia('Pagado');
        } else {
            $evento->estado = 'Pendiente';
            $evento->save();
        }
    }

    /**
     * Procesar composiciones de un detalle
     *
     * @param Detalle $detalle
     * @param array $composiciones
     * @return void
     */
    protected function procesarComposiciones(Detalle $detalle, array $composiciones): void
    {
        foreach ($composiciones as $item) {
            $cd = new DetalleCompuesto();
            $cd->id_producto = $item['id_compuesto'];
            $cd->cantidad = $item['cantidad'];
            $cd->id_detalle = $detalle->id;
            $cd->save();
        }
    }

    /**
     * Guardar impuestos de la venta
     *
     * @param Venta $venta
     * @param array|null $impuestos
     * @return void
     */
    public function guardarImpuestos(Venta $venta, ?array $impuestos): void
    {
        if (!$impuestos) {
            return;
        }

        foreach ($impuestos as $impuesto) {
            $venta_impuesto = new Impuesto();
            $venta_impuesto->id_impuesto = $impuesto['id'];
            $venta_impuesto->monto = $impuesto['monto'];
            $venta_impuesto->id_venta = $venta->id;
            $venta_impuesto->save();
        }
    }

    /**
     * Guardar métodos de pago de la venta
     *
     * @param Venta $venta
     * @param array|null $metodosDePago
     * @return void
     */
    public function guardarMetodosDePago(Venta $venta, ?array $metodosDePago): void
    {
        if (!isset($metodosDePago)) {
            return;
        }

        foreach ($metodosDePago as $metodo) {
            $metodo_pago = new MetodoDePago();
            $metodo_pago->id_venta = $venta->id;
            $metodo_pago->nombre = $metodo['nombre'];
            $metodo_pago->total = $metodo['total'];
            $metodo_pago->save();
        }
    }

    /**
     * Procesar evento asociado a la venta
     *
     * @param int|null $idEvento
     * @param Venta $venta
     * @return void
     */
    public function procesarEvento(?int $idEvento, Venta $venta): void
    {
        if (!$idEvento) {
            return;
        }

        $evento = Evento::findOrfail($idEvento);
        if ($venta->estado == 'Pagada') {
            $evento->estado = 'Pagado';
            $evento->estadoVerificarFrecuencia('Pagado');
        } else {
            $evento->estado = 'Pendiente';
            $evento->save();
        }
    }

    /**
     * Procesar proyecto asociado a la venta
     *
     * @param int|null $idProyecto
     * @param Venta $venta
     * @return void
     */
    public function procesarProyecto(?int $idProyecto, Venta $venta): void
    {
        if (!$idProyecto) {
            return;
        }

        $proyecto = Proyecto::find($idProyecto);
        if ($proyecto) {
            $proyecto->estado = ($venta->estado == 'Pagada') ? 'Facturado' : 'Pendiente';
            $proyecto->save();
        }
    }

    /**
     * Eliminar una venta y sus detalles
     *
     * @param int $id
     * @return Venta
     */
    public function eliminarVenta(int $id): Venta
    {
        $venta = Venta::findOrFail($id);

        foreach ($venta->detalles as $detalle) {
            $detalle->delete();
        }

        $venta->delete();

        return $venta;
    }
}


