<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Illuminate\Http\Request;
use App\Models\Admin\Empresa;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class SuscripcionesExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    private $request;

    public function filter(Request $request)
    {
        Log::info($request);
        $this->request = $request;
    }

    public function headings(): array
    {
        return [
            'Empresa',
            'Propietario',
            'Plan',
            'Precio Plan',
            'Tipo Plan',
            'Estado Suscripción',
            'Fecha Suscripción',
            'Fecha Último Pago',
            'Método de Pago',
            'Fecha Vencimiento',
            'Días Restantes',
            'Duración Plan (días)',
        ];
    }

    public function collection()
    {
        $request = $this->request;

        $query = Empresa::with(['suscripcion', 'suscripcion.plan' => function ($query) {
            $query->select('id', 'nombre', 'precio', 'slug', 'duracion_dias');
        }]);

        // Filtro por estado de suscripción
        if ($request->estado) {
            if ($request->estado === 'sin_suscripcion') {
                $query->doesntHave('suscripcion');
            } else {
                $query->whereHas('suscripcion', function ($q) use ($request) {
                    $q->where('estado', $request->estado);
                });
            }
        }

        // Aplicar todos los filtros del método index
        $query->when($request->buscador, function ($q) use ($request) {
            return $q->where(function ($query) use ($request) {
                $query->where('nombre', 'like', '%' . $request->buscador . '%')
                    ->orWhere('nombre_propietario', 'like', '%' . $request->buscador . '%');
            });
        })
            ->when($request->suscripcion_inicio, function ($q) use ($request) {
                return $q->whereHas('suscripcion', function ($query) use ($request) {
                    $query->whereDate('created_at', '>=', $request->suscripcion_inicio);
                });
            })
            ->when($request->suscripcion_fin, function ($q) use ($request) {
                return $q->whereHas('suscripcion', function ($query) use ($request) {
                    $query->whereDate('created_at', '<=', $request->suscripcion_fin);
                });
            })
            ->when($request->pago_inicio, function ($q) use ($request) {
                return $q->whereHas('suscripcion', function ($query) use ($request) {
                    $query->whereDate('fecha_ultimo_pago', '>=', $request->pago_inicio);
                });
            })
            ->when($request->pago_fin, function ($q) use ($request) {
                return $q->whereHas('suscripcion', function ($query) use ($request) {
                    $query->whereDate('fecha_ultimo_pago', '<=', $request->pago_fin);
                });
            })
            ->when($request->plan, function ($q) use ($request) {
                return $q->whereHas('suscripcion.plan', function ($query) use ($request) {
                    $query->where('nombre', $request->plan);
                });
            })
            ->when($request->forma_pago, function ($q) use ($request) {
                return $q->where('metodo_pago', $request->forma_pago);
            });

        $orden = $request->orden ?? 'created_at';
        $direccion = $request->direccion ?? 'desc';

        $query->orderBy($orden, $direccion);

        return $query->get();
    }

    public function map($empresa): array
    {
        $suscripcion = $empresa->suscripcion;
        $plan = $suscripcion->plan ?? null;

        // Calcular fecha de vencimiento y días restantes
        $diasRestantes = null;
        $fechaVencimiento = null;
        if ($suscripcion && $suscripcion->fecha_ultimo_pago && $plan) {
            $fechaVencimiento = \Carbon\Carbon::parse($suscripcion->fecha_ultimo_pago)
                ->addDays($plan->duracion_dias);
            $diasRestantes = now()->diffInDays($fechaVencimiento, false);
            $fechaVencimiento = $fechaVencimiento->format('Y-m-d');
        }

        $tipoPlan = $plan ? $plan->tipo_plan : 'N/A';

        return [
            $empresa->nombre ?? '',
            $empresa->nombre_propietario ?? '',
            $plan->nombre ?? 'Sin plan',
            $plan ? number_format($plan->precio, 2) : '0.00',
            $tipoPlan,
            $suscripcion->estado ?? 'Sin suscripción',
            $suscripcion ? optional($suscripcion->created_at)->format('Y-m-d') : '',
            $suscripcion && $suscripcion->fecha_ultimo_pago ?
                \Carbon\Carbon::parse($suscripcion->fecha_ultimo_pago)->format('Y-m-d') : '',
            $empresa->metodo_pago ?? '',
            $fechaVencimiento ?? '',
            $diasRestantes !== null ? $diasRestantes : '',
            $plan->duracion_dias ?? '',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Estilo a la fila de encabezados
            1 => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFE5E5E5'], // gris claro
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                ],
            ],
        ];
    }
}
