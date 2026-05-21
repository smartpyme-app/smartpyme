<?php

namespace App\Services\Contabilidad;

use App\Models\Admin\FormaDePago;
use App\Models\Contabilidad\Configuracion;
use App\Models\Contabilidad\Partidas\Partida;
use App\Models\Contabilidad\Partidas\Detalle;
use App\Models\Contabilidad\Catalogo\Cuenta;
use App\Models\Compras\Gastos\Gasto;
use App\Models\Compras\Gastos\Abono as AbonoGasto;
use Illuminate\Support\Facades\DB;
use Exception;

class GastosService
{
    public function crearPartida($gasto)
    {
        if (!$gasto || !isset($gasto->id)) {
            throw new Exception('El gasto proporcionado no es válido', 400);
        }

        $configuracion = Configuracion::first();
        if (!$configuracion) {
            throw new Exception('No se encontró la configuración contable', 400);
        }

        $gastoCompleto = Gasto::with('categoria')->find($gasto->id);
        if (!$gastoCompleto) {
            throw new Exception('No se encontró el gasto con ID: ' . $gasto->id, 400);
        }

        $this->validarGastoParaPartida($gastoCompleto);

        Partida::assertNoExisteParaOrigen('Gasto', $gastoCompleto->id, 'Ya existen partidas contables generadas para este gasto.');

        DB::beginTransaction();

        try {
            $partida = Partida::create([
                'fecha'         => $gastoCompleto->fecha,
                'tipo'          => $gastoCompleto->estado == 'Pendiente' ? 'CxP' : 'Egreso',
                'concepto'      => $this->conceptoGasto($gastoCompleto),
                'estado'        => 'Pendiente',
                'referencia'    => 'Gasto',
                'id_referencia' => $gastoCompleto->id,
                'id_usuario'    => $gastoCompleto->id_usuario,
                'id_empresa'    => $gastoCompleto->id_empresa,
            ]);

            $detalles = $gastoCompleto->estado == 'Pendiente'
                ? $this->detallesGastoCxP($gastoCompleto, $configuracion)
                : $this->detallesGastoEgreso($gastoCompleto, $configuracion);

            foreach ($detalles as $det) {
                Detalle::create(array_merge($det, ['id_partida' => $partida->id]));
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Partida contable de gasto creada exitosamente',
                'partida_id' => $partida->id,
            ];

        } catch (Exception $e) {
            DB::rollback();
            throw new Exception('Error al crear la partida de gasto: ' . $e->getMessage(), 400);
        } catch (\Throwable $e) {
            DB::rollback();
            throw new Exception('Error inesperado al crear la partida de gasto: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Detalles para gasto pendiente (reconocimiento en CxP).
     *
     * @return array<int, array<string, mixed>>
     */
    public function detallesGastoCxP(Gasto $gastoCompleto, Configuracion $configuracion): array
    {
        $this->validarGastoParaPartida($gastoCompleto);

        if (!$configuracion->id_cuenta_cxp) {
            throw new Exception('No se ha configurado la cuenta de cuentas por pagar', 400);
        }

        $cuenta_cxp = Cuenta::find($configuracion->id_cuenta_cxp);
        if (!$cuenta_cxp) {
            throw new Exception('No se encontró la cuenta contable de cuentas por pagar', 400);
        }

        return $this->armarDetallesGasto($gastoCompleto, $configuracion, $cuenta_cxp);
    }

    /**
     * Detalles para gasto confirmado o pagado (egreso de caja/banco).
     *
     * @return array<int, array<string, mixed>>
     */
    public function detallesGastoEgreso(Gasto $gastoCompleto, Configuracion $configuracion): array
    {
        $this->validarGastoParaPartida($gastoCompleto);

        if (!$gastoCompleto->forma_pago) {
            throw new Exception('El gasto no tiene forma de pago asignada', 400);
        }

        $cuenta_haber = $this->cuentaFormaPago($gastoCompleto->forma_pago);

        return $this->armarDetallesGasto($gastoCompleto, $configuracion, $cuenta_haber);
    }

    /**
     * Detalles para abono a un gasto pendiente (pago de CxP).
     *
     * @return array<int, array<string, mixed>>
     */
    public function detallesAbonoGasto(AbonoGasto $abono, Gasto $gasto, Configuracion $configuracion): array
    {
        if (!$abono->total || $abono->total <= 0) {
            throw new Exception('El abono no tiene un monto válido', 400);
        }

        if (!$abono->forma_pago) {
            throw new Exception('El abono no tiene forma de pago asignada', 400);
        }

        if (!$configuracion->id_cuenta_cxp) {
            throw new Exception('No se ha configurado la cuenta de cuentas por pagar', 400);
        }

        $cuenta_cxp = Cuenta::find($configuracion->id_cuenta_cxp);
        if (!$cuenta_cxp) {
            throw new Exception('No se encontró la cuenta contable de cuentas por pagar', 400);
        }

        $cuenta_forma_pago = $this->cuentaFormaPago($abono->forma_pago);
        $refTxt = ($gasto->tipo_documento ?? 'Documento') . ' #' . ($gasto->referencia ?? $gasto->id);
        $concepto = 'Abono a cuenta por pagar gasto ' . $refTxt;

        return [
            [
                'id_cuenta' => $cuenta_cxp->id,
                'codigo' => $cuenta_cxp->codigo,
                'nombre_cuenta' => $cuenta_cxp->nombre,
                'concepto' => $concepto,
                'debe' => $abono->total,
                'haber' => null,
                'saldo' => 0,
            ],
            [
                'id_cuenta' => $cuenta_forma_pago->id,
                'codigo' => $cuenta_forma_pago->codigo,
                'nombre_cuenta' => $cuenta_forma_pago->nombre,
                'concepto' => $concepto,
                'debe' => null,
                'haber' => $abono->total,
                'saldo' => 0,
            ],
        ];
    }

    private function validarGastoParaPartida(Gasto $gastoCompleto): void
    {
        if (!$gastoCompleto->fecha) {
            throw new Exception('El gasto no tiene fecha asignada', 400);
        }

        if (!$gastoCompleto->total || $gastoCompleto->total <= 0) {
            throw new Exception('El gasto no tiene un monto válido', 400);
        }

        if (!$gastoCompleto->categoria || !$gastoCompleto->categoria->id_cuenta_contable) {
            throw new Exception('La categoría del gasto no tiene una cuenta contable configurada', 400);
        }
    }

    private function conceptoGasto(Gasto $gastoCompleto): string
    {
        return 'Gasto de ' . $gastoCompleto->categoria->nombre . '. '
            . ($gastoCompleto->tipo_documento ?? 'Documento') . ' #'
            . ($gastoCompleto->referencia ?? 'Sin referencia');
    }

    private function cuentaFormaPago(string $formaPago): Cuenta
    {
        $formapago = FormaDePago::with('banco')->where('nombre', $formaPago)->first();

        if (!$formapago) {
            throw new Exception('No se encontró la forma de pago: ' . $formaPago, 400);
        }

        if (!$formapago->banco || !$formapago->banco->id_cuenta_contable) {
            throw new Exception('La forma de pago no tiene un banco o cuenta contable configurada', 400);
        }

        $cuenta = Cuenta::find($formapago->banco->id_cuenta_contable);
        if (!$cuenta) {
            throw new Exception('No se encontró la cuenta contable del banco asociado a la forma de pago', 400);
        }

        return $cuenta;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function armarDetallesGasto(Gasto $gastoCompleto, Configuracion $configuracion, Cuenta $cuenta_haber): array
    {
        $nombreCategoria = $gastoCompleto->categoria->nombre;
        $concepto = 'Gasto de ' . $nombreCategoria;
        $refTxt = ($gastoCompleto->tipo_documento ?? 'Documento') . ' #' . ($gastoCompleto->referencia ?? $gastoCompleto->id);

        $cuenta_categoria = Cuenta::find($gastoCompleto->categoria->id_cuenta_contable);
        if (!$cuenta_categoria) {
            throw new Exception('No se encontró la cuenta contable de la categoría del gasto', 400);
        }

        $detalles = [
            [
                'id_cuenta' => $cuenta_haber->id,
                'codigo' => $cuenta_haber->codigo,
                'nombre_cuenta' => $cuenta_haber->nombre,
                'concepto' => $concepto . ' ' . $refTxt,
                'debe' => null,
                'haber' => $gastoCompleto->total,
                'saldo' => 0,
            ],
            [
                'id_cuenta' => $cuenta_categoria->id,
                'codigo' => $cuenta_categoria->codigo,
                'nombre_cuenta' => $cuenta_categoria->nombre,
                'concepto' => $concepto . ' ' . $refTxt,
                'debe' => $gastoCompleto->sub_total,
                'haber' => null,
                'saldo' => 0,
            ],
        ];

        if ($gastoCompleto->iva > 0) {
            $cuenta_iva = $this->cuentaConfigurada($configuracion->id_cuenta_iva_compras, 'IVA compras');
            $detalles[] = [
                'id_cuenta' => $cuenta_iva->id,
                'codigo' => $cuenta_iva->codigo,
                'nombre_cuenta' => $cuenta_iva->nombre,
                'concepto' => $concepto . ' ' . $refTxt,
                'debe' => $gastoCompleto->iva,
                'haber' => null,
                'saldo' => 0,
            ];
        }

        if ($gastoCompleto->iva_percibido > 0) {
            $cuenta_iva_percibido = $this->cuentaConfigurada(
                $configuracion->id_cuenta_iva_retenido_compras,
                'IVA retenido compras'
            );
            $detalles[] = [
                'id_cuenta' => $cuenta_iva_percibido->id,
                'codigo' => $cuenta_iva_percibido->codigo,
                'nombre_cuenta' => $cuenta_iva_percibido->nombre,
                'concepto' => $concepto . ' ' . $refTxt,
                'debe' => $gastoCompleto->iva_percibido,
                'haber' => null,
                'saldo' => 0,
            ];
        }

        if ($gastoCompleto->renta_retenida > 0) {
            $cuenta_renta = $this->cuentaConfigurada(
                $configuracion->id_cuenta_renta_retenida_compras,
                'renta retenida compras'
            );
            $detalles[] = [
                'id_cuenta' => $cuenta_renta->id,
                'codigo' => $cuenta_renta->codigo,
                'nombre_cuenta' => $cuenta_renta->nombre,
                'concepto' => $concepto . ' ' . $refTxt,
                'debe' => null,
                'haber' => $gastoCompleto->renta_retenida,
                'saldo' => 0,
            ];
        }

        return $detalles;
    }

    private function cuentaConfigurada(?int $idCuenta, string $nombreCuenta): Cuenta
    {
        if (!$idCuenta) {
            throw new Exception('No se ha configurado la cuenta de ' . $nombreCuenta, 400);
        }

        $cuenta = Cuenta::find($idCuenta);
        if (!$cuenta) {
            throw new Exception('No se encontró la cuenta contable de ' . $nombreCuenta, 400);
        }

        return $cuenta;
    }
}
