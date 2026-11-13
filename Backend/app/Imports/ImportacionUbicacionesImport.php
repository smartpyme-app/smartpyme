<?php

namespace App\Imports;

use App\Support\Inventario\ImportacionUbicaciones;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ImportacionUbicacionesImport implements ToCollection, WithHeadingRow
{
    /** @var array{agregados: int, actualizados: int, errores: array<int, array{fila: int, mensaje: string}>} */
    public array $resultado = ['agregados' => 0, 'actualizados' => 0, 'errores' => []];

    public function __construct(private int $idEmpresa)
    {
    }

    public function collection(Collection $rows)
    {
        $this->resultado = (new ImportacionUbicaciones())->procesar(
            $rows->map(fn ($fila) => $fila instanceof Collection ? $fila->toArray() : (array) $fila)->all(),
            $this->idEmpresa
        );
    }
}
