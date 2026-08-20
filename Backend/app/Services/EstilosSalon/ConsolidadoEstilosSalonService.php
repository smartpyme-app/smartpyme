<?php

namespace App\Services\EstilosSalon;

use App\Exports\ReportesAutomaticos\VentasPorCategoriaPorSucursal\VentasPorCategoriaSucursalMultiExport;
use App\Models\Admin\Empresa;
use App\Support\EstilosSalon\EstilosSalonPeriodo;
use Illuminate\Support\Facades\DB;

class ConsolidadoEstilosSalonService
{
    private const CATEGORIAS = [
        ['nombre' => 'Productos', 'porcentaje' => 100],
        ['nombre' => 'Servicios', 'porcentaje' => 90],
    ];

    /**
     * @return array<int, array{id: int, nombre: string, configuracion: object}>
     */
    public function empresasParaExport(): array
    {
        $empresas = [];

        foreach (EstilosSalonPeriodo::EMPRESAS_IDS as $idEmpresa) {
            $empresa = Empresa::find($idEmpresa);

            if (! $empresa) {
                continue;
            }

            $configuracion = $this->buildConfiguracion($idEmpresa);

            if ($configuracion === null) {
                continue;
            }

            $empresas[] = [
                'id' => $idEmpresa,
                'nombre' => $empresa->nombre,
                'configuracion' => $configuracion,
            ];
        }

        return $empresas;
    }

    /**
     * @param  array<int, array{id: int, nombre: string, configuracion: object}>  $empresas
     */
    public function makeExport(string $fechaInicio, string $fechaFin, array $empresas): VentasPorCategoriaSucursalMultiExport
    {
        return new VentasPorCategoriaSucursalMultiExport($fechaInicio, $fechaFin, $empresas);
    }

    private function buildConfiguracion(int $idEmpresa): ?object
    {
        $configuracion = [];

        foreach (self::CATEGORIAS as $cat) {
            $categoria = DB::table('categorias')
                ->where('id_empresa', $idEmpresa)
                ->whereRaw('LOWER(TRIM(nombre)) = ?', [mb_strtolower(trim($cat['nombre']))])
                ->first();

            if (! $categoria) {
                continue;
            }

            $configuracion[] = [
                'id' => $categoria->id,
                'nombre' => $categoria->nombre,
                'porcentaje' => $cat['porcentaje'],
            ];
        }

        if (count($configuracion) !== count(self::CATEGORIAS)) {
            return null;
        }

        $sucursales = DB::table('sucursales')
            ->where('id_empresa', $idEmpresa)
            ->orderBy('nombre')
            ->pluck('id')
            ->toArray();

        return (object) [
            'configuracion' => $configuracion,
            'sucursales' => $sucursales,
        ];
    }
}
