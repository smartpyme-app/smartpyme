<?php

namespace App\Exports\Contabilidad\Honduras;

use App\Models\Ventas\Venta;
use App\Models\Ventas\Devoluciones\Devolucion as DevolucionVenta;
use App\Services\Contabilidad\LibroIvaMontosHelper;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeSheet;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Libro de ventas a contribuyentes - Formato Honduras (SAR).
 * Columnas según formato oficial.
 */
class LibroContribuyentesExport implements FromCollection, WithMapping, WithHeadings, WithEvents
{
    public $request;

    private int $index = 1;

    /** @var list<string> */
    public const TIPOS_CONTRIBUYENTE = ['Crédito fiscal'];

    /** @var list<string> */
    private const CLAVES_FILA = [
        'no',
        'fecha',
        'correlativo',
        'nrc',
        'nombre',
        'exentas',
        'no_sujetas',
        'gravadas_locales',
        'debito_fiscal',
        'cta_terceros',
        'debito_cta_terceros',
        'iva_percibido',
        'iva_retenido',
        'total',
    ];

    /** @var list<string> */
    private const CLAVES_RESUMEN = [
        'gravadas',
        'exportaciones',
        'debito_fiscal',
        'iva_percibido',
        'iva_retenido',
    ];

    public function filter(Request $request)
    {
        $this->request = $request;
    }

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event) {
                $event->sheet->insertNewRowBefore(1, 4);
                $event->sheet->setCellValue('A1', 'LIBRO DE VENTAS A CONTRIBUYENTES');
                $event->sheet->setCellValue('A2', Auth::user()->empresa()->pluck('nombre')->first());
                $event->sheet->setCellValue('A4', 'Mes: ' . ucfirst(Carbon::parse($this->request->inicio)->translatedFormat('F')) . ' - Año: ' . Carbon::parse($this->request->inicio)->format('Y'));
            },
        ];
    }

    public function headings(): array
    {
        return [
            'N°',
            'Fecha',
            'N° Correlativo',
            'NRC',
            'Nombre del cliente',
            'Ventas Exentas',
            'Ventas No Sujetas',
            'Ventas Gravadas Locales',
            'Débito Fiscal',
            'Ventas a Cuenta de Terceros',
            'Débito Fiscal Cta. Terceros',
            'IVA Percibido',
            'IVA Retenido',
            'Total',
        ];
    }

    public function collection()
    {
        return $this->registros();
    }

    /**
     * @return array{filas: array<int, array<string, mixed>>, resumen_operaciones: array<string, array<string, float>>}
     */
    public function rowsForApi(): array
    {
        $filas = $this->registros()->values()->map(function ($item, $index) {
            return $this->mapVenta($item->registro, $index + 1, (int) $item->mult);
        })->all();

        return [
            'filas' => $filas,
            'resumen_operaciones' => $this->resumenOperaciones($filas),
        ];
    }

    private function registros(): Collection
    {
        $request = $this->request;

        $ventas = Venta::with(['cliente', 'documento'])
            ->where('estado', '!=', 'Anulada')
            ->where('cotizacion', 0)
            ->whereHas('documento', fn ($q) => $q->whereIn('nombre', self::TIPOS_CONTRIBUYENTE))
            ->when($request->id_sucursal, fn ($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->get()
            ->map(fn ($v) => (object) ['registro' => $v, 'mult' => 1]);

        $devoluciones = DevolucionVenta::with(['cliente', 'documento', 'venta.documento'])
            ->where('enable', true)
            ->whereHas('venta.documento', fn ($q) => $q->whereIn('nombre', self::TIPOS_CONTRIBUYENTE))
            ->when($request->id_sucursal, fn ($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->get()
            ->map(fn ($d) => (object) ['registro' => $d, 'mult' => -1]);

        return $ventas->merge($devoluciones)
            ->sortBy(fn ($x) => [(string) $x->registro->fecha, (string) ($x->registro->correlativo ?? '')])
            ->values();
    }

    private function mapVenta(object $venta, int $no, int $mult = 1): array
    {
        $cliente = $venta->cliente ?? null;

        return [
            'no' => $no,
            'fecha' => (string) $venta->fecha,
            'correlativo' => trim((string) ($venta->correlativo ?? '')),
            'nrc' => (string) ($cliente?->ncr ?? ''),
            'nombre' => (string) ($venta->nombre_cliente ?? $cliente?->nombre ?? ''),
            'exentas' => round(LibroIvaMontosHelper::ventasExentas($venta) * $mult, 2),
            'no_sujetas' => round(LibroIvaMontosHelper::ventasNoSujetas($venta) * $mult, 2),
            'gravadas_locales' => round(LibroIvaMontosHelper::ventasGravadas($venta) * $mult, 2),
            'debito_fiscal' => round((float) ($venta->iva ?? 0) * $mult, 2),
            'cta_terceros' => round((float) ($venta->cuenta_a_terceros ?? 0) * $mult, 2),
            // ponytail: no hay fuente de débito fiscal por cuenta de terceros (techo: persistir campo si el SAR lo exige separado).
            'debito_cta_terceros' => 0.0,
            'iva_percibido' => round((float) ($venta->iva_percibido ?? 0) * $mult, 2),
            'iva_retenido' => round((float) ($venta->iva_retenido ?? 0) * $mult, 2),
            'total' => round((float) ($venta->total ?? 0) * $mult, 2),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $filas
     * @return array{
     *     totales_detalle: array<string, float>,
     *     consumidor_final: array<string, float>,
     *     contribuyentes: array<string, float>,
     *     cta_terceros: array<string, float>
     * }
     */
    private function resumenOperaciones(array $filas): array
    {
        $vacio = array_fill_keys(self::CLAVES_RESUMEN, 0.0);
        $totalesDetalle = $vacio;
        $contribuyentes = $vacio;
        $ctaTerceros = $vacio;

        foreach ($filas as $fila) {
            $gravadas = (float) ($fila['gravadas_locales'] ?? 0);
            $debito = (float) ($fila['debito_fiscal'] ?? 0);
            $percibido = (float) ($fila['iva_percibido'] ?? 0);
            $retenido = (float) ($fila['iva_retenido'] ?? 0);

            $totalesDetalle['gravadas'] += $gravadas;
            $totalesDetalle['debito_fiscal'] += $debito;
            $totalesDetalle['iva_percibido'] += $percibido;
            $totalesDetalle['iva_retenido'] += $retenido;

            $contribuyentes['gravadas'] += $gravadas;
            $contribuyentes['debito_fiscal'] += $debito;
            $contribuyentes['iva_percibido'] += $percibido;
            $contribuyentes['iva_retenido'] += $retenido;

            $ctaTerceros['gravadas'] += (float) ($fila['cta_terceros'] ?? 0);
            $ctaTerceros['debito_fiscal'] += (float) ($fila['debito_cta_terceros'] ?? 0);
        }

        // exportaciones / consumidor_final: sin fuente en este libro (solo Crédito fiscal).
        return [
            'totales_detalle' => $this->redondearResumen($totalesDetalle),
            'consumidor_final' => $vacio,
            'contribuyentes' => $this->redondearResumen($contribuyentes),
            'cta_terceros' => $this->redondearResumen($ctaTerceros),
        ];
    }

    /**
     * @param  array<string, float>  $bloque
     * @return array<string, float>
     */
    private function redondearResumen(array $bloque): array
    {
        foreach ($bloque as $clave => $valor) {
            $bloque[$clave] = round($valor, 2);
        }

        return $bloque;
    }

    public function map($item): array
    {
        $row = $this->mapVenta($item->registro, $this->index++, (int) $item->mult);
        $valores = [];
        foreach (self::CLAVES_FILA as $clave) {
            $valores[] = $row[$clave];
        }

        return $valores;
    }
}
