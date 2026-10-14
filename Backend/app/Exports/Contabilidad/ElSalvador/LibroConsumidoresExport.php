<?php

namespace App\Exports\Contabilidad\ElSalvador;

use App\Services\Contabilidad\LibroConsumidoresFilas;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeSheet;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

    class LibroConsumidoresExport implements FromCollection, WithMapping, WithHeadings, WithEvents
    {
        public $request;
        private $index = 1;

    public function filter(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Verifica si la empresa tiene facturación electrónica habilitada
     */
    private function tieneFacturacionElectronica(): bool
    {
        $empresa = Auth::user()->empresa()->first();
        return $empresa && $empresa->facturacion_electronica === true;
    }

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event) {
                $event->sheet->insertNewRowBefore(1, 4);

                $event->sheet->setCellValue('A1', 'LIBRO DE VENTAS A CONSUMIDORES ');
                $event->sheet->setCellValue('A2', Auth::user()->empresa()->pluck('nombre')->first());
                $event->sheet->setCellValue('A3', 'NRC: ' . Auth::user()->empresa()->pluck('ncr')->first());
                $event->sheet->setCellValue('E3', 'Folio N°:');
                $event->sheet->setCellValue('A4', 'Mes: ' . ucfirst(Carbon::parse($this->request->inicio)->translatedFormat('F')));
                $event->sheet->setCellValue('E4', 'Año: ' . Carbon::parse($this->request->inicio)->format('Y'));

            },
        ];
    }

    public function headings():array{
        return[
            'N°',
            'Fecha',
            'Correlativo Inicial',
            'Correlativo Final',
            'VENTAS EXENTAS',
            'VENTAS INTERNAS GRAVADAS',
            'EXPORTACIONES',
            'TOTAL DE VENTAS DIARIAS PROPIAS',
            'VENTAS A CUENTA DE TERCEROS',
        ];
    }

    public function collection()
    {
        $facturacionElectronica = $this->tieneFacturacionElectronica();
        $ventas = LibroConsumidoresFilas::consulta($this->request, $facturacionElectronica)->get();
        $filas = collect(LibroConsumidoresFilas::filas($ventas, $facturacionElectronica));

        return $filas->push([
            'es_total' => true,
            'ventas_exentas' => $filas->sum('ventas_exentas'),
            'ventas_internas_gravadas' => $filas->sum('ventas_internas_gravadas'),
            'exportaciones' => $filas->sum('exportaciones'),
            'total_ventas_diarias_propias' => $filas->sum('total_ventas_diarias_propias'),
            'ventas_a_cuenta_de_terceros' => $filas->sum('ventas_a_cuenta_de_terceros'),
        ]);
    }

    public function map($fila): array{
        $fila = is_array($fila) ? $fila : (array) $fila;

        if (!empty($fila['es_total'])) {
            return [
                '',
                'Totales',
                '',
                '',
                $fila['ventas_exentas'],
                $fila['ventas_internas_gravadas'],
                $fila['exportaciones'],
                $fila['total_ventas_diarias_propias'],
                $fila['ventas_a_cuenta_de_terceros'],
            ];
        }

        return [
            $this->index++,
            Carbon::parse($fila['fecha'])->format('d/m/Y'),
            $fila['correlativo_inicial'],
            $fila['correlativo_final'],
            $fila['ventas_exentas'],
            $fila['ventas_internas_gravadas'],
            $fila['exportaciones'],
            $fila['total_ventas_diarias_propias'],
            $fila['ventas_a_cuenta_de_terceros'],
        ];
    }
}
