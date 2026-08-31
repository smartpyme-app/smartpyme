<?php

namespace App\Services\Inventario;

use Carbon\Carbon;

class HistorialPrecioCostoService
{
    public function construir(
        array $producto,
        string $inicio,
        array $ventas,
        array $compras,
        ?float $precioApertura = null,
        ?float $costoApertura = null
    ): array {
        $eventosPrecio = $this->eventosPrecio($ventas, $precioApertura);
        $eventosCosto = $this->eventosCosto($compras, $costoApertura);
        $serie = $this->armarSeries($inicio, $eventosPrecio, $eventosCosto, $precioApertura, $costoApertura);

        $ultimoCostoCompras = $this->ultimoCostoNeto($compras);
        $ultimoCosto = $ultimoCostoCompras ?? $costoApertura ?? (isset($producto['costo']) ? (float) $producto['costo'] : null);
        $ultimoPrecioEvento = $this->ultimoValor($eventosPrecio);
        $ultimoPrecio = $ultimoPrecioEvento ?? $precioApertura ?? (isset($producto['precio']) ? (float) $producto['precio'] : null);
        $valoresPrecio = $this->valoresTendencia($precioApertura, $eventosPrecio, $this->ultimoPrecioPeriodo($ventas));
        $valoresCosto = $this->valoresTendencia($costoApertura, $eventosCosto, $ultimoCostoCompras);

        $eventos = array_merge($eventosPrecio, $eventosCosto);
        usort($eventos, [$this, 'compararEventos']);

        $eventosPublicos = array_map(function (array $evento) {
            unset($evento['id_detalle']);
            return $evento;
        }, $eventos);

        return [
            'producto' => [
                'id'     => $producto['id'] ?? null,
                'nombre' => $producto['nombre'] ?? null,
                'codigo' => $producto['codigo'] ?? null,
                'precio' => $ultimoPrecio !== null ? round((float) $ultimoPrecio, 4) : (float) ($producto['precio'] ?? 0),
                'costo'  => $ultimoCosto !== null ? round((float) $ultimoCosto, 4) : (float) ($producto['costo'] ?? 0),
            ],
            'labels'           => $serie['labels'],
            'precios'          => $serie['precios'],
            'costos'           => $serie['costos'],
            'tendencia_precio' => $this->calcularTendenciaSerie($valoresPrecio),
            'tendencia_costo'  => $this->calcularTendenciaSerie($valoresCosto),
            'variacion_precio' => $this->calcularVariacionPorcentual($valoresPrecio),
            'variacion_costo'  => $this->calcularVariacionPorcentual($valoresCosto),
            'eventos'          => array_values($eventosPublicos),
            'total_ventas'     => count($ventas),
            'total_compras'    => count($compras),
        ];
    }

    public function costoUnitarioNeto(array $compra): float
    {
        return round(ConversionInventarioService::calcularCostoUnitarioNetoBase(
            $compra['cantidad'] ?? 1,
            $compra['costo'] ?? 0,
            $compra['descuento'] ?? 0,
            $compra['factor_conversion'] ?? 1
        ), 4);
    }

    private function eventosPrecio(array $ventas, ?float $precioApertura): array
    {
        $eventos = [];
        $ultimo = $precioApertura !== null ? round($precioApertura, 4) : null;

        foreach ($ventas as $venta) {
            $precio = round((float) ($venta['precio_sin_iva'] ?? $venta['precio']), 4);
            if ($ultimo !== null && $precio == $ultimo) {
                continue;
            }
            $eventos[] = [
                'fecha'        => $this->normalizarFecha($venta['fecha'] ?? null),
                'valor'        => $precio,
                'tipo'         => 'precio',
                'referencia'   => 'Venta #' . ($venta['correlativo'] ?? $venta['id_documento'] ?? $venta['id'] ?? ''),
                'id_documento' => $venta['id_documento'] ?? null,
                'usuario'      => $venta['usuario'] ?? null,
                'id_detalle'   => $venta['id'] ?? 0,
            ];
            $ultimo = $precio;
        }

        return $eventos;
    }

    private function eventosCosto(array $compras, ?float $costoApertura): array
    {
        $eventos = [];
        $ultimo = $costoApertura !== null ? round($costoApertura, 4) : null;

        foreach ($compras as $compra) {
            $costo = $this->costoUnitarioNeto($compra);
            if ($ultimo !== null && $costo == $ultimo) {
                continue;
            }
            $ref = $compra['referencia'] ?? null;
            $eventos[] = [
                'fecha'        => $this->normalizarFecha($compra['fecha'] ?? null),
                'valor'        => $costo,
                'tipo'         => 'costo',
                'referencia'   => $ref ?: ('Compra #' . ($compra['id_documento'] ?? $compra['id'] ?? '')),
                'id_documento' => $compra['id_documento'] ?? null,
                'usuario'      => $compra['usuario'] ?? null,
                'id_detalle'   => $compra['id'] ?? 0,
            ];
            $ultimo = $costo;
        }

        return $eventos;
    }

    private function armarSeries(
        string $inicio,
        array $eventosPrecio,
        array $eventosCosto,
        ?float $precioApertura,
        ?float $costoApertura
    ): array {
        $labels = [];
        $precios = [];
        $costos = [];
        $precioAcum = $precioApertura !== null ? round($precioApertura, 4) : null;
        $costoAcum = $costoApertura !== null ? round($costoApertura, 4) : null;

        if ($precioApertura !== null || $costoApertura !== null) {
            $labels[] = Carbon::parse($inicio)->format('d/m/Y');
            $precios[] = $precioAcum;
            $costos[] = $costoAcum;
        }

        $eventos = array_merge($eventosPrecio, $eventosCosto);
        usort($eventos, [$this, 'compararEventos']);

        foreach ($eventos as $evento) {
            if ($evento['tipo'] === 'precio') {
                $precioAcum = $evento['valor'];
            } else {
                $costoAcum = $evento['valor'];
            }

            $n = count($labels);
            if ($n > 0 && $precios[$n - 1] === $precioAcum && $costos[$n - 1] === $costoAcum) {
                continue;
            }

            $labels[] = Carbon::parse($evento['fecha'])->format('d/m/Y');
            $precios[] = $precioAcum;
            $costos[] = $costoAcum;
        }

        return [
            'labels'  => $labels,
            'precios' => $precios,
            'costos'  => $costos,
        ];
    }

    private function valoresTendencia(?float $apertura, array $eventos, ?float $ultimoDelPeriodo): array
    {
        $valores = [];
        if ($apertura !== null) {
            $valores[] = round($apertura, 4);
        }
        foreach ($eventos as $evento) {
            $valores[] = $evento['valor'];
        }
        if ($apertura !== null && empty($eventos) && $ultimoDelPeriodo !== null) {
            $valores[] = round($ultimoDelPeriodo, 4);
        }

        return $valores;
    }

    private function ultimoCostoNeto(array $compras): ?float
    {
        if (empty($compras)) {
            return null;
        }

        $ultima = $compras[array_key_last($compras)];

        return $this->costoUnitarioNeto($ultima);
    }

    private function ultimoPrecioPeriodo(array $ventas): ?float
    {
        if (empty($ventas)) {
            return null;
        }

        $ultima = $ventas[array_key_last($ventas)];

        return round((float) ($ultima['precio_sin_iva'] ?? $ultima['precio']), 4);
    }

    private function ultimoValor(array $eventos): ?float
    {
        if (empty($eventos)) {
            return null;
        }

        return (float) $eventos[array_key_last($eventos)]['valor'];
    }

    private function compararEventos(array $a, array $b): int
    {
        $cmp = strcmp((string) $a['fecha'], (string) $b['fecha']);
        if ($cmp !== 0) {
            return $cmp;
        }

        return ($a['id_detalle'] ?? 0) <=> ($b['id_detalle'] ?? 0);
    }

    private function normalizarFecha($fecha): string
    {
        if ($fecha === null || $fecha === '') {
            return '';
        }

        return Carbon::parse($fecha)->format('Y-m-d');
    }

    private function calcularTendenciaSerie(array $valores): string
    {
        $numericos = array_values(array_filter($valores, function ($v) {
            return $v !== null;
        }));

        if (count($numericos) < 2) {
            return count($numericos) === 1 ? 'estable' : 'sin_datos';
        }

        $primero = (float) $numericos[0];
        $ultimo = (float) $numericos[count($numericos) - 1];

        if ($primero == 0.0) {
            return $ultimo > 0 ? 'subiendo' : 'estable';
        }

        $variacion = (($ultimo - $primero) / abs($primero)) * 100;
        if (abs($variacion) < 0.5) {
            return 'estable';
        }

        return $ultimo > $primero ? 'subiendo' : 'bajando';
    }

    private function calcularVariacionPorcentual(array $valores): ?float
    {
        $numericos = array_values(array_filter($valores, function ($v) {
            return $v !== null;
        }));

        if (count($numericos) < 2) {
            return null;
        }

        $primero = (float) $numericos[0];
        $ultimo = (float) $numericos[count($numericos) - 1];

        if ($primero == 0.0) {
            return null;
        }

        return round((($ultimo - $primero) / abs($primero)) * 100, 2);
    }
}
