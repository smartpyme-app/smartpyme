<?php

namespace App\Exports\Contabilidad\Honduras;

use App\Models\Compras\Compra;
use App\Models\Compras\Gastos\Gasto;
use App\Models\Compras\Devoluciones\Devolucion as DevolucionCompra;
use App\Services\Contabilidad\LibroIvaMontosHelper;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeSheet;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Constants\DocumentoConstants;

/**
 * Libro de compras - Formato Honduras (SAR).
 * Columnas según formato oficial.
 */
class LibroComprasExport implements FromCollection, WithMapping, WithHeadings, WithEvents
{
    public $request;

    private int $index = 1;

    /** @var list<string> */
    private const CLAVES_FILA = [
        'no',
        'fecha_emision',
        'numero_documento',
        'nrc',
        'nit_o_dui',
        'nombre_proveedor',
        'exentas_internas',
        'exentas_internaciones',
        'exentas_importaciones',
        'gravadas_internas',
        'gravadas_internaciones',
        'gravadas_importaciones',
        'credito_fiscal',
        'fovial',
        'cotrans',
        'cesc',
        'anticipo_iva_percibido',
        'total',
        'retencion_terceros',
        'compras_sujetos_excluidos',
    ];

    /** @var list<string> */
    private const CLAVES_MONETARIAS = [
        'exentas_internas',
        'exentas_internaciones',
        'exentas_importaciones',
        'gravadas_internas',
        'gravadas_internaciones',
        'gravadas_importaciones',
        'credito_fiscal',
        'fovial',
        'cotrans',
        'cesc',
        'anticipo_iva_percibido',
        'total',
        'retencion_terceros',
        'compras_sujetos_excluidos',
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
                $event->sheet->setCellValue('A1', 'LIBRO DE COMPRAS');
                $event->sheet->setCellValue('A2', Auth::user()->empresa()->pluck('nombre')->first());
                $event->sheet->setCellValue('A4', 'Mes: ' . ucfirst(Carbon::parse($this->request->inicio)->translatedFormat('F')) . ' - Año: ' . Carbon::parse($this->request->inicio)->format('Y'));
            },
        ];
    }

    public function headings(): array
    {
        return [
            'N°',
            'Fecha de emisión',
            'Número de documento',
            'NRC',
            'NIT o DUI',
            'Nombre del proveedor',
            'Compras exentas internas',
            'Compras exentas internaciones',
            'Compras exentas importaciones',
            'Compras gravadas internas',
            'Compras gravadas internaciones',
            'Compras gravadas importaciones',
            'Crédito fiscal',
            'FOVIAL',
            'COTRANS',
            'CESC',
            'Anticipo a cuenta IVA percibido',
            'Total',
            'Retención a terceros',
            'Compras a sujetos excluidos',
        ];
    }

    public function collection()
    {
        $request = $this->request;

        $compras = Compra::with(['proveedor'])
            ->where('estado', '!=', 'Anulada')
            ->where('cotizacion', 0)
            ->whereNotIn('tipo_documento', DocumentoConstants::TIPOS_COMPRA_SIN_IVA_FISCAL)
            ->when($request->id_sucursal, fn($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->get()
            ->map(fn($c) => (object) ['registro' => $c, 'mult' => 1]);

        $gastos = Gasto::with(['proveedor'])
            ->where('estado', '!=', 'Cancelado')
            ->where('estado', '!=', 'Anulada')
            ->whereNotIn('tipo_documento', DocumentoConstants::TIPOS_COMPRA_SIN_IVA_FISCAL)
            ->when($request->id_sucursal, fn($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->get()
            ->map(fn($g) => (object) ['registro' => $g, 'mult' => 1]);

        $devoluciones = DevolucionCompra::with(['proveedor'])
            ->where('enable', true)
            ->whereNotIn('tipo_documento', DocumentoConstants::TIPOS_COMPRA_SIN_IVA_FISCAL)
            ->when($request->id_sucursal, fn($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->get()
            ->map(fn($d) => (object) ['registro' => $d, 'mult' => -1]);

        return $compras->merge($gastos)->merge($devoluciones)->sortBy(fn($x) => $x->registro->fecha)->values();
    }

    /**
     * @return array{filas: array<int, array<string, mixed>>, totales: array<string, float>}
     */
    public function rowsForApi(): array
    {
        $filas = $this->collection()->values()->map(function ($item, $index) {
            return $this->mapItemToAssoc($item, $index + 1);
        })->all();

        $totales = array_fill_keys(self::CLAVES_MONETARIAS, 0.0);
        foreach ($filas as $fila) {
            foreach (self::CLAVES_MONETARIAS as $clave) {
                $totales[$clave] += (float) ($fila[$clave] ?? 0);
            }
        }

        return [
            'filas' => $filas,
            'totales' => $totales,
        ];
    }

    private function mapItemToAssoc(object $item, int $no): array
    {
        $r = $item->registro;
        $m = (int) $item->mult;
        $proveedor = $r->proveedor ?? (method_exists($r, 'proveedor') ? $r->proveedor()->first() : null);
        $esSujetoExcluido = (string) ($r->tipo_documento ?? '') === 'Sujeto excluido';
        $columnas = LibroIvaMontosHelper::columnasCompra($r, $m);
        if ($esSujetoExcluido) {
            $columnas = array_fill_keys(array_keys($columnas), 0.0);
        }

        return [
            'no' => $no,
            'fecha_emision' => (string) $r->fecha,
            'numero_documento' => (string) ($r->referencia ?? ''),
            'nrc' => (string) ($proveedor?->ncr ?? ''),
            'nit_o_dui' => (string) ($proveedor?->nit ?? $proveedor?->dui ?? ''),
            'nombre_proveedor' => (string) ($r->nombre_proveedor ?? ''),
            'exentas_internas' => (float) $columnas['compras_exentas'],
            'exentas_internaciones' => 0.0,
            'exentas_importaciones' => (float) $columnas['importaciones_exentas'],
            'gravadas_internas' => (float) $columnas['compras_gravadas'],
            'gravadas_internaciones' => 0.0,
            'gravadas_importaciones' => (float) $columnas['importaciones_gravadas'],
            'credito_fiscal' => (float) $columnas['credito_fiscal'],
            'fovial' => 0.0,
            'cotrans' => 0.0,
            'cesc' => 0.0,
            'anticipo_iva_percibido' => (float) ($r->percepcion ?? 0) * $m,
            'total' => (float) ($r->total ?? 0) * $m,
            'retencion_terceros' => (float) ($r->iva_retenido ?? 0) * $m,
            'compras_sujetos_excluidos' => $esSujetoExcluido ? (float) $r->total * $m : 0.0,
        ];
    }

    public function map($item): array
    {
        $row = $this->mapItemToAssoc($item, $this->index++);
        $valores = [];
        foreach (self::CLAVES_FILA as $clave) {
            $valores[] = $row[$clave];
        }

        return $valores;
    }
}
