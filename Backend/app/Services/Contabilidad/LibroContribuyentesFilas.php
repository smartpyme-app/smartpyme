<?php

namespace App\Services\Contabilidad;

use App\Exports\Contabilidad\Honduras\LibroContribuyentesExport;
use App\Models\Ventas\Devoluciones\Devolucion as DevolucionVenta;
use App\Models\Ventas\Venta;
use App\Support\NombreComercial;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Filas del libro de ventas a contribuyentes (SV y consulta base HN).
 * ponytail: join + select acotado; no cargar columna dte en listados.
 */
class LibroContribuyentesFilas
{
    /** @var list<string> */
    private const VENTA_SELECT = [
        'ventas.id',
        'ventas.fecha',
        'ventas.correlativo',
        'ventas.codigo_generacion',
        'ventas.numero_control',
        'ventas.sello_mh',
        'ventas.exenta',
        'ventas.gravada',
        'ventas.sub_total',
        'ventas.no_sujeta',
        'ventas.iva',
        'ventas.cuenta_a_terceros',
        'ventas.iva_retenido',
        'ventas.iva_percibido',
        'ventas.total',
        'ventas.id_cliente',
    ];

    /** @return list<string> */
    private static function devolucionSelect(): array
    {
        // ponytail: gravada/iva_percibido no existen en todas las BD; montos salen de sub_total/iva vía LibroIvaMontosHelper.
        $cols = [
            'devoluciones_venta.id',
            'devoluciones_venta.fecha',
            'devoluciones_venta.correlativo',
            'devoluciones_venta.codigo_generacion',
            'devoluciones_venta.numero_control',
            'devoluciones_venta.sello_mh',
            'devoluciones_venta.exenta',
            'devoluciones_venta.sub_total',
            'devoluciones_venta.no_sujeta',
            'devoluciones_venta.iva',
            'devoluciones_venta.cuenta_a_terceros',
            'devoluciones_venta.iva_retenido',
            'devoluciones_venta.total',
            'devoluciones_venta.id_cliente',
        ];
        if (Schema::hasColumn('devoluciones_venta', 'gravada')) {
            $cols[] = 'devoluciones_venta.gravada';
        }
        if (Schema::hasColumn('devoluciones_venta', 'iva_percibido')) {
            $cols[] = 'devoluciones_venta.iva_percibido';
        }

        return $cols;
    }

    /** @return list<string> */
    private static function clienteJoinSelect(): array
    {
        $cols = [
            'clientes.ncr as cliente_ncr',
            'clientes.nit as cliente_nit',
            'clientes.nombre as cliente_nombre',
            'clientes.apellido as cliente_apellido',
            'clientes.nombre_empresa as cliente_nombre_empresa',
            'clientes.tipo as cliente_tipo',
        ];
        if (Schema::hasColumn('clientes', 'nombre_comercial')) {
            $cols[] = 'clientes.nombre_comercial as cliente_nombre_comercial';
        }

        return $cols;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function filasElSalvador(
        object $request,
        bool $facturacionElectronica,
        FacturacionElectronicaHelperService $feHelper
    ): array {
        $ventas = self::queryVentasSv($request, $facturacionElectronica, ['Crédito fiscal'])->get();
        $devoluciones = self::queryDevolucionesSv($request, ['Crédito fiscal'])->get();

        $filas = $ventas
            ->map(fn ($row) => self::mapFilaVentaSv($row, 1, $feHelper))
            ->concat($devoluciones->map(fn ($row) => self::mapFilaDevolucionSv($row, $feHelper)))
            ->sortBy(fn ($item) => [(string) $item['fecha'], (string) ($item['correlativo'] ?? '')])
            ->values()
            ->all();

        return $filas;
    }

    public static function registrosHonduras(object $request): Collection
    {
        $tipos = LibroContribuyentesExport::TIPOS_CONTRIBUYENTE;

        $ventasQuery = self::queryVentasSv($request, false, $tipos);
        if (Schema::hasColumn('documentos', 'numero_emision')) {
            $ventasQuery->addSelect('documentos.numero_emision as documento_numero_emision');
        }
        $ventas = $ventasQuery->get()
            ->map(function ($v) {
                $v->numero_emision = $v->documento_numero_emision ?? null;

                return (object) ['registro' => self::ventaDesdeFila($v), 'mult' => 1];
            });

        $devolucionesQuery = self::queryDevolucionesSv($request, $tipos);
        if (Schema::hasColumn('documentos', 'numero_emision')) {
            $devolucionesQuery->addSelect('documentos.numero_emision as documento_numero_emision');
        }
        $devoluciones = $devolucionesQuery->get()
            ->map(function ($d) {
                $d->numero_emision = $d->documento_numero_emision ?? null;

                return (object) ['registro' => self::ventaDesdeFila($d), 'mult' => -1];
            });

        return collect()
            ->concat($ventas)
            ->concat($devoluciones)
            ->sortBy(fn ($x) => [(string) $x->registro->fecha, (string) ($x->registro->correlativo ?? '')])
            ->values();
    }

    /**
     * @param  list<string>  $tiposDocumento
     */
    private static function queryVentasSv(object $request, bool $facturacionElectronica, array $tiposDocumento): EloquentBuilder
    {
        return Venta::query()
            ->join('documentos', 'documentos.id', '=', 'ventas.id_documento')
            ->leftJoin('clientes', 'clientes.id', '=', 'ventas.id_cliente')
            ->where('ventas.estado', '!=', 'Anulada')
            ->where('ventas.cotizacion', 0)
            ->whereIn('documentos.nombre', $tiposDocumento)
            ->when($request->id_sucursal, fn ($q) => $q->where('ventas.id_sucursal', $request->id_sucursal))
            ->whereBetween('ventas.fecha', [$request->inicio, $request->fin])
            ->when($facturacionElectronica, function ($query) {
                $query->whereNotNull('ventas.sello_mh')
                    ->where('ventas.sello_mh', '!=', '');
            })
            ->select(array_merge(self::VENTA_SELECT, self::clienteJoinSelect()))
            ->orderBy('ventas.fecha')
            ->orderBy('ventas.correlativo');
    }

    /**
     * @param  list<string>  $tiposDocumento
     */
    private static function queryDevolucionesSv(object $request, array $tiposDocumento): EloquentBuilder
    {
        return DevolucionVenta::query()
            ->join('ventas', 'ventas.id', '=', 'devoluciones_venta.id_venta')
            ->join('documentos', 'documentos.id', '=', 'ventas.id_documento')
            ->leftJoin('clientes', 'clientes.id', '=', 'devoluciones_venta.id_cliente')
            ->where('devoluciones_venta.enable', true)
            ->where('ventas.estado', '!=', 'Anulada')
            ->whereIn('documentos.nombre', $tiposDocumento)
            ->when($request->id_sucursal, fn ($q) => $q->where('devoluciones_venta.id_sucursal', $request->id_sucursal))
            ->whereBetween('devoluciones_venta.fecha', [$request->inicio, $request->fin])
            ->select(array_merge(self::devolucionSelect(), self::clienteJoinSelect()))
            ->orderBy('devoluciones_venta.fecha')
            ->orderBy('devoluciones_venta.correlativo');
    }

    /**
     * @return array<string, mixed>
     */
    private static function mapFilaVentaSv(object $row, int $mult, FacturacionElectronicaHelperService $feHelper): array
    {
        $venta = self::ventaDesdeFila($row);

        return self::filaSvDesdeMontos(
            $row,
            $venta->fecha,
            $feHelper->obtenerCodigoGeneracion($venta),
            $feHelper->obtenerNumeroControl($venta),
            $feHelper->obtenerSello($venta),
            trim((string) $venta->correlativo),
            $venta,
            $mult
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function mapFilaDevolucionSv(object $row, FacturacionElectronicaHelperService $feHelper): array
    {
        $devolucion = self::devolucionDesdeFila($row);
        $mult = -1;

        return self::filaSvDesdeMontos(
            $row,
            $devolucion->fecha,
            $feHelper->obtenerCodigoGeneracionDevolucion($devolucion),
            $feHelper->obtenerNumeroControlDevolucion($devolucion),
            $feHelper->obtenerSelloDevolucion($devolucion),
            trim((string) $devolucion->correlativo),
            $devolucion,
            $mult
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function filaSvDesdeMontos(
        object $row,
        $fecha,
        string $codigoGeneracion,
        string $numeroControl,
        string $sello,
        string $correlativo,
        object $montos,
        int $mult
    ): array {
        return [
            'fecha' => $fecha,
            'codigo_generacion' => $codigoGeneracion,
            'numero_control' => $numeroControl,
            'sello' => $sello,
            'correlativo' => $correlativo,
            'nombre_cliente' => self::nombreClienteDesdeFila($row),
            'nrc_cliente' => (string) ($row->cliente_ncr ?? $row->cliente_nit ?? ''),
            'ventas_exentas' => LibroIvaMontosHelper::ventasExentas($montos) * $mult,
            'ventas_internas_gravadas' => LibroIvaMontosHelper::ventasGravadas($montos) * $mult,
            'debito_fiscal' => (float) ($montos->iva ?? 0) * $mult,
            'ventas_exentas_a_cuenta_de_terceros' => 0.0,
            'ventas_internas_gravadas_a_cuenta_de_terceros' => (float) ($montos->cuenta_a_terceros ?? 0) * $mult,
            'debito_fiscal_por_cuenta_de_terceros' => 0.0,
            'iva_retenido' => self::montoConSigno((float) ($montos->iva_retenido ?? 0), $mult),
            'iva_percibido' => self::montoConSigno((float) ($montos->iva_percibido ?? 0), $mult),
            'total' => self::montoConSigno((float) ($montos->total ?? 0), $mult),
        ];
    }

    private static function devolucionDesdeFila(object $row): DevolucionVenta
    {
        $devolucion = new DevolucionVenta();
        $devolucion->forceFill([
            'id' => $row->id ?? null,
            'fecha' => $row->fecha ?? null,
            'correlativo' => $row->correlativo ?? null,
            'codigo_generacion' => $row->codigo_generacion ?? null,
            'numero_control' => $row->numero_control ?? null,
            'sello_mh' => $row->sello_mh ?? null,
            'exenta' => $row->exenta ?? 0,
            'gravada' => $row->gravada ?? 0,
            'sub_total' => $row->sub_total ?? 0,
            'no_sujeta' => $row->no_sujeta ?? 0,
            'iva' => $row->iva ?? 0,
            'cuenta_a_terceros' => $row->cuenta_a_terceros ?? 0,
            'iva_retenido' => $row->iva_retenido ?? 0,
            'iva_percibido' => $row->iva_percibido ?? 0,
            'total' => $row->total ?? 0,
        ]);
        $devolucion->exists = true;

        return $devolucion;
    }

    private static function ventaDesdeFila(object $row): Venta
    {
        $venta = new Venta();
        $venta->forceFill([
            'id' => $row->id ?? null,
            'fecha' => $row->fecha ?? null,
            'correlativo' => $row->correlativo ?? null,
            'codigo_generacion' => $row->codigo_generacion ?? null,
            'numero_control' => $row->numero_control ?? null,
            'sello_mh' => $row->sello_mh ?? null,
            'exenta' => $row->exenta ?? 0,
            'gravada' => $row->gravada ?? 0,
            'sub_total' => $row->sub_total ?? 0,
            'no_sujeta' => $row->no_sujeta ?? 0,
            'iva' => $row->iva ?? 0,
            'cuenta_a_terceros' => $row->cuenta_a_terceros ?? 0,
            'iva_retenido' => $row->iva_retenido ?? 0,
            'iva_percibido' => $row->iva_percibido ?? 0,
            'total' => $row->total ?? 0,
            'id_cliente' => $row->id_cliente ?? null,
        ]);
        $venta->exists = true;

        if (isset($row->numero_emision)) {
            $venta->setRelation('documento', (object) [
                'numero_emision' => $row->numero_emision,
            ]);
        }

        if (! empty($row->id_cliente)) {
            $venta->setRelation('cliente', (object) [
                'ncr' => $row->cliente_ncr ?? null,
                'nit' => $row->cliente_nit ?? null,
                'nombre' => $row->cliente_nombre ?? null,
                'apellido' => $row->cliente_apellido ?? null,
                'nombre_empresa' => $row->cliente_nombre_empresa ?? null,
                'nombre_comercial' => $row->cliente_nombre_comercial ?? null,
                'tipo' => $row->cliente_tipo ?? null,
            ]);
        }

        return $venta;
    }

    private static function nombreClienteDesdeFila(object $row): string
    {
        if (empty($row->id_cliente)) {
            return 'Consumidor Final';
        }

        $legal = ($row->cliente_tipo ?? '') === 'Empresa'
            ? (string) ($row->cliente_nombre_empresa ?? '')
            : trim(((string) ($row->cliente_nombre ?? '')) . ' ' . ((string) ($row->cliente_apellido ?? '')));

        return NombreComercial::anexar($legal, (string) ($row->cliente_nombre_comercial ?? ''));
    }

    private static function montoConSigno(float $valor, int $mult): float
    {
        if ($mult >= 0) {
            return $valor;
        }

        return $valor > 0 ? $valor * -1 : $valor;
    }
}
