<?php

namespace App\Exports\ReportesAutomaticos\VentasPorCategoriaPorSucursal;

use App\Models\Admin\Sucursal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class VentasPorCategoriaSucursalExport implements FromCollection, WithHeadings, WithMapping, WithTitle, ShouldAutoSize, WithStyles
{
    public $fechaInicio;
    public $fechaFin;
    public $id_empresa;
    public $configuracion;
    public $sucursalesData;
    public $sucursales;
    public $nombreEmpresa;

    public function __construct(
        $fechaInicio = null,
        $fechaFin = null,
        $id_empresa = null,
        $configuracion = null,
        $nombreEmpresa = null
    ) {
        $this->fechaInicio = $fechaInicio;
        $this->fechaFin = $fechaFin;
        $this->id_empresa = $id_empresa;
        $this->configuracion = $configuracion;
        $this->nombreEmpresa = $nombreEmpresa;

        if ($configuracion && isset($configuracion->sucursales) && ! empty($configuracion->sucursales)) {
            $this->sucursalesData = Sucursal::withoutGlobalScopes()
                ->whereIn('id', $configuracion->sucursales)
                ->orderBy('nombre')
                ->get()
                ->keyBy('id');
            $this->sucursales = $configuracion->sucursales;
        } else {
            $this->sucursalesData = Sucursal::withoutGlobalScopes()
                ->where('id_empresa', $id_empresa)
                ->orderBy('nombre')
                ->get()
                ->keyBy('id');
            $this->sucursales = $this->sucursalesData->pluck('id')->toArray();
        }
    }

    public function title(): string
    {
        $nombre = $this->nombreEmpresa ?: "Empresa {$this->id_empresa}";

        return mb_substr("{$nombre} ({$this->fechaInicio} al {$this->fechaFin})", 0, 31);
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'EEEEEE']]],
        ];
    }

    public function collection()
    {
        try {
            $categoriasIds = [];
            if ($this->configuracion && isset($this->configuracion->configuracion) && ! empty($this->configuracion->configuracion)) {
                $categoriasIds = collect($this->configuracion->configuracion)->pluck('id')->toArray();
            }

            $query = DB::table('detalles_venta as dv')
                ->join('productos as pro', 'dv.id_producto', '=', 'pro.id')
                ->join('categorias as cat', 'pro.id_categoria', '=', 'cat.id')
                ->join('ventas as vv', 'dv.id_venta', '=', 'vv.id')
                ->join('sucursales as suc', 'vv.id_sucursal', '=', 'suc.id')
                ->where('vv.estado', '!=', 'Anulada')
                ->where('vv.id_empresa', $this->id_empresa)
                ->whereBetween('vv.fecha', [$this->fechaInicio, $this->fechaFin]);

            if (! empty($categoriasIds)) {
                $query->whereIn('cat.id', $categoriasIds);
            }

            if (! empty($this->sucursales)) {
                $query->whereIn('vv.id_sucursal', $this->sucursales);
            }

            $ventasData = $query->select(
                'cat.id as id_categoria',
                'cat.nombre as nombre_categoria',
                'suc.nombre as nombre_sucursal',
                'suc.id as id_sucursal',
                DB::raw('SUM(dv.total) as total_ventas')
            )
                ->groupBy('cat.id', 'cat.nombre', 'suc.nombre', 'suc.id')
                ->orderBy('suc.nombre')
                ->get();

            $categorias = $this->obtenerCategorias($categoriasIds);
            $porcentajesCategorias = $this->obtenerPorcentajesCategorias();

            $resultadoFormateado = [];

            foreach ($this->sucursalesData as $id => $sucursal) {
                $resultadoFormateado[$id] = [
                    'id_sucursal' => $id,
                    'Sucursal' => $sucursal->nombre,
                ];

                foreach ($categorias as $idCat => $nombreCat) {
                    $porcentaje = isset($porcentajesCategorias[$idCat]) ? $porcentajesCategorias[$idCat]['porcentaje'] : 1;
                    $nombreConPorcentaje = $nombreCat.' ('.($porcentaje * 100).'%)';
                    $resultadoFormateado[$id][$nombreConPorcentaje] = 0;
                }

                $resultadoFormateado[$id]['TOTAL'] = 0;
            }

            $totales = [
                'id_sucursal' => null,
                'Sucursal' => 'TOTAL',
            ];

            foreach ($categorias as $idCat => $nombreCat) {
                $porcentaje = isset($porcentajesCategorias[$idCat]) ? $porcentajesCategorias[$idCat]['porcentaje'] : 1;
                $nombreConPorcentaje = $nombreCat.' ('.($porcentaje * 100).'%)';
                $totales[$nombreConPorcentaje] = 0;
            }

            $totales['TOTAL'] = 0;

            foreach ($ventasData as $venta) {
                $idSucursal = $venta->id_sucursal;
                $idCategoria = $venta->id_categoria;
                $nombreCategoria = $venta->nombre_categoria;
                $total = $venta->total_ventas;

                if (isset($porcentajesCategorias[$idCategoria])) {
                    $total = $total * $porcentajesCategorias[$idCategoria]['porcentaje'];
                }

                $porcentaje = isset($porcentajesCategorias[$idCategoria]) ? $porcentajesCategorias[$idCategoria]['porcentaje'] : 1;
                $nombreConPorcentaje = $nombreCategoria.' ('.($porcentaje * 100).'%)';

                if (! isset($resultadoFormateado[$idSucursal])) {
                    $resultadoFormateado[$idSucursal] = [
                        'id_sucursal' => $idSucursal,
                        'Sucursal' => $venta->nombre_sucursal,
                    ];
                    foreach ($categorias as $idCat => $nombreCat) {
                        $pct = isset($porcentajesCategorias[$idCat]) ? $porcentajesCategorias[$idCat]['porcentaje'] : 1;
                        $resultadoFormateado[$idSucursal][$nombreCat.' ('.($pct * 100).'%)'] = 0;
                    }
                    $resultadoFormateado[$idSucursal]['TOTAL'] = 0;
                }

                if (! isset($totales[$nombreConPorcentaje])) {
                    $totales[$nombreConPorcentaje] = 0;
                }

                $resultadoFormateado[$idSucursal][$nombreConPorcentaje] += $total;
                $resultadoFormateado[$idSucursal]['TOTAL'] += $total;
                $totales[$nombreConPorcentaje] += $total;
                $totales['TOTAL'] += $total;
            }

            $resultado = collect(array_values($resultadoFormateado));
            $resultado->push($totales);

            return $resultado;
        } catch (\Exception $e) {
            Log::error('Error en collection de VentasPorCategoriaSucursalExport', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'id_empresa' => $this->id_empresa,
            ]);

            return collect([
                [
                    'Sucursal' => 'Error al generar el reporte',
                    'TOTAL' => 0,
                ],
            ]);
        }
    }

    public function headings(): array
    {
        try {
            $categoriasIds = [];
            if ($this->configuracion && isset($this->configuracion->configuracion) && ! empty($this->configuracion->configuracion)) {
                $categoriasIds = collect($this->configuracion->configuracion)->pluck('id')->toArray();
            }

            $categorias = $this->obtenerCategorias($categoriasIds);
            $porcentajesCategorias = $this->obtenerPorcentajesCategorias();

            $encabezados = ['Sucursal'];

            foreach ($categorias as $id => $nombre) {
                $porcentaje = isset($porcentajesCategorias[$id]) ? $porcentajesCategorias[$id]['porcentaje'] * 100 : 100;
                $encabezados[] = $nombre.' ('.$porcentaje.'%)';
            }

            $encabezados[] = 'TOTAL';

            return $encabezados;
        } catch (\Exception $e) {
            Log::error('Error generando encabezados VentasPorCategoriaSucursalExport', [
                'error' => $e->getMessage(),
                'id_empresa' => $this->id_empresa,
            ]);

            return ['Sucursal', 'TOTAL'];
        }
    }

    public function map($fila): array
    {
        try {
            $sucursal = $fila['Sucursal'] ?? 'Sin sucursal';
            $resultado = [$sucursal];
            $encabezados = $this->headings();

            for ($i = 1; $i < count($encabezados); $i++) {
                $columna = $encabezados[$i];
                $valor = $fila[$columna] ?? 0;
                $resultado[] = is_numeric($valor) ? number_format(round($valor, 2), 2) : $valor;
            }

            return $resultado;
        } catch (\Exception $e) {
            Log::error('Error en map de VentasPorCategoriaSucursalExport', [
                'error' => $e->getMessage(),
                'fila' => $fila,
            ]);

            $resultado = ['Error al formatear'];
            $encabezados = $this->headings();

            for ($i = 1; $i < count($encabezados); $i++) {
                $resultado[] = '0.00';
            }

            return $resultado;
        }
    }

    private function obtenerCategorias(array $categoriasIds)
    {
        if ($this->configuracion && isset($this->configuracion->configuracion) && ! empty($this->configuracion->configuracion)) {
            return collect($this->configuracion->configuracion)
                ->mapWithKeys(fn ($config) => [$config['id'] => $config['nombre']]);
        }

        $query = DB::table('categorias')->where('id_empresa', $this->id_empresa);

        if (! empty($categoriasIds)) {
            $query->whereIn('id', $categoriasIds);
        }

        return $query->orderBy('nombre')->pluck('nombre', 'id');
    }

    private function obtenerPorcentajesCategorias(): array
    {
        $porcentajesCategorias = [];

        if ($this->configuracion && isset($this->configuracion->configuracion) && ! empty($this->configuracion->configuracion)) {
            foreach ($this->configuracion->configuracion as $config) {
                if (isset($config['id'], $config['nombre'], $config['porcentaje'])) {
                    $porcentajesCategorias[$config['id']] = [
                        'nombre' => $config['nombre'],
                        'porcentaje' => $config['porcentaje'] / 100,
                    ];
                }
            }
        }

        return $porcentajesCategorias;
    }
}
