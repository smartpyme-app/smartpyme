<?php



namespace App\Services\Contabilidad;



use App\Models\Contabilidad\Activo;

use App\Models\Contabilidad\ActivoDepreciacion;

use App\Models\Contabilidad\ActivoMovimiento;

use Illuminate\Support\Collection;

use InvalidArgumentException;



class ActivosReportesService

{

    public const TIPOS = [

        'libro-activos',

        'depreciacion-periodo',

        'estado-activos',

        'bajas-periodo',

    ];



    public function generar(string $tipo, int $empresaId, array $filtros): array

    {

        if (! in_array($tipo, self::TIPOS, true)) {

            throw new InvalidArgumentException('Tipo de reporte no válido.');

        }



        return match ($tipo) {

            'libro-activos' => $this->libroActivos($empresaId, $filtros),

            'depreciacion-periodo' => $this->depreciacionPeriodo($empresaId, $filtros),

            'estado-activos' => $this->estadoActivos($empresaId, $filtros),

            'bajas-periodo' => $this->bajasPeriodo($empresaId, $filtros),

        };

    }



    private function libroActivos(int $empresaId, array $filtros): array

    {

        $activos = $this->queryActivos($empresaId, $filtros)

            ->when($filtros['inicio'] ?? null, fn ($q) => $q->where('fecha_compra', '>=', $filtros['inicio']))

            ->when($filtros['fin'] ?? null, fn ($q) => $q->where('fecha_compra', '<=', $filtros['fin']))

            ->orderBy('fecha_compra')

            ->orderBy('nombre')

            ->get();



        $columnas = ['Nombre', 'Categoría', 'Fecha compra', 'Sucursal', 'Estado', 'Valor compra', 'Valor en libros'];

        $lineas = $activos->map(fn (Activo $a) => [

            $a->nombre,

            $a->categoria?->nombre ?? '',

            $a->fecha_compra?->format('Y-m-d') ?? $a->fecha_compra,

            $a->sucursal?->nombre ?? '',

            $a->estado,

            (float) $a->valor_compra,

            (float) ($a->valor_en_libros ?? 0),

        ])->all();



        return $this->payload('libro-activos', 'Libro de activos', $columnas, $lineas, $filtros, [

            'valor_compra' => round($activos->sum('valor_compra'), 2),

            'valor_en_libros' => round($activos->sum(fn ($a) => (float) ($a->valor_en_libros ?? 0)), 2),

        ]);

    }



    private function depreciacionPeriodo(int $empresaId, array $filtros): array

    {

        $periodo = $filtros['periodo'] ?? now()->format('Y-m');



        $lineasQuery = ActivoDepreciacion::withoutGlobalScopes()

            ->with(['activo.categoria', 'activo.sucursal'])

            ->where('id_empresa', $empresaId)

            ->where('periodo', $periodo)

            ->when($filtros['id_sucursal'] ?? null, function ($q) use ($filtros) {

                $q->whereHas('activo', fn ($a) => $a->where('id_sucursal', $filtros['id_sucursal']));

            })

            ->orderBy('id_activo')

            ->get();



        $columnas = ['Período', 'Activo', 'Categoría', 'Estado', 'Monto', 'Acumulada', 'Valor en libros'];

        $lineas = $lineasQuery->map(fn (ActivoDepreciacion $d) => [

            $d->periodo,

            $d->activo?->nombre ?? '',

            $d->activo?->categoria?->nombre ?? '',

            $d->estado,

            (float) $d->monto,

            (float) $d->depreciacion_acumulada,

            (float) $d->valor_en_libros,

        ])->all();



        return $this->payload('depreciacion-periodo', 'Depreciación del período', $columnas, $lineas, $filtros, [

            'monto' => round($lineasQuery->sum('monto'), 2),

        ]);

    }



    private function estadoActivos(int $empresaId, array $filtros): array

    {

        $corte = $filtros['fecha_corte'] ?? now()->toDateString();



        $activos = $this->queryActivos($empresaId, $filtros)

            ->where('fecha_compra', '<=', $corte)

            ->where(function ($q) use ($corte) {

                $q->whereNull('fecha_retiro')

                    ->orWhere('fecha_retiro', '>', $corte);

            })

            ->orderBy('nombre')

            ->get();



        $columnas = ['Nombre', 'Categoría', 'Fecha compra', 'Sucursal', 'Estado', 'Valor compra', 'Dep. acumulada', 'Valor en libros'];

        $lineas = $activos->map(fn (Activo $a) => [

            $a->nombre,

            $a->categoria?->nombre ?? '',

            $a->fecha_compra?->format('Y-m-d') ?? $a->fecha_compra,

            $a->sucursal?->nombre ?? '',

            $a->estado,

            (float) $a->valor_compra,

            (float) ($a->depreciacion_acumulada ?? 0),

            (float) ($a->valor_en_libros ?? 0),

        ])->all();



        return $this->payload('estado-activos', 'Estado de activos', $columnas, $lineas, $filtros, [

            'valor_en_libros' => round($activos->sum(fn ($a) => (float) ($a->valor_en_libros ?? 0)), 2),

        ]);

    }



    private function bajasPeriodo(int $empresaId, array $filtros): array

    {

        $movimientos = ActivoMovimiento::withoutGlobalScopes()

            ->with(['activo.sucursal'])

            ->where('id_empresa', $empresaId)

            ->whereIn('tipo', ['baja', 'venta'])

            ->when($filtros['inicio'] ?? null, fn ($q) => $q->where('fecha', '>=', $filtros['inicio']))

            ->when($filtros['fin'] ?? null, fn ($q) => $q->where('fecha', '<=', $filtros['fin']))

            ->when($filtros['id_sucursal'] ?? null, function ($q) use ($filtros) {

                $q->whereHas('activo', fn ($a) => $a->where('id_sucursal', $filtros['id_sucursal']));

            })

            ->orderByDesc('fecha')

            ->get();



        $columnas = ['Fecha', 'Activo', 'Tipo', 'Motivo', 'Sucursal', 'Monto venta'];

        $lineas = $movimientos->map(fn (ActivoMovimiento $m) => [

            $m->fecha?->format('Y-m-d') ?? $m->fecha,

            $m->activo?->nombre ?? '',

            $m->tipo,

            $m->descripcion ?? '',

            $m->activo?->sucursal?->nombre ?? '',

            $m->monto !== null ? (float) $m->monto : '',

        ])->all();



        return $this->payload('bajas-periodo', 'Bajas del período', $columnas, $lineas, $filtros, [

            'cantidad' => $movimientos->count(),

        ]);

    }



    private function queryActivos(int $empresaId, array $filtros)

    {

        return Activo::withoutGlobalScopes()

            ->with(['categoria', 'sucursal'])

            ->where('id_empresa', $empresaId)

            ->when($filtros['id_sucursal'] ?? null, fn ($q) => $q->where('id_sucursal', $filtros['id_sucursal']))

            ->when($filtros['id_categoria'] ?? null, fn ($q) => $q->where('id_categoria', $filtros['id_categoria']));

    }



    private function payload(string $tipo, string $titulo, array $columnas, array $lineas, array $filtros, array $totales): array

    {

        return [

            'tipo' => $tipo,

            'titulo' => $titulo,

            'filtros' => $filtros,

            'columnas' => $columnas,

            'lineas' => $lineas,

            'totales' => $totales,

            'cantidad' => count($lineas),

        ];

    }

}

