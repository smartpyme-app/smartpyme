<?php

namespace App\Imports;

use App\Support\Inventario\ActualizacionMasivaProductos;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ActualizacionMasivaProductosImport implements ToCollection, WithHeadingRow
{
    /** @var array{actualizados: int, errores: array<int, array{fila: int, mensaje: string}>} */
    public array $resultado = ['actualizados' => 0, 'errores' => []];

    public function __construct(private int $idEmpresa, private int $idUsuario)
    {
    }

    public function collection(Collection $rows)
    {
        $this->resultado = (new ActualizacionMasivaProductos())->procesar(
            $rows->map(fn ($fila) => $fila instanceof Collection ? $fila->toArray() : (array) $fila)->all(),
            $this->idEmpresa,
            $this->idUsuario
        );
    }
}
