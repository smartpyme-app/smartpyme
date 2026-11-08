<?php

namespace App\Imports;

use App\Services\Contabilidad\ActivoImportacionMasivaService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ActivosMasivoImport implements ToCollection, WithHeadingRow
{
    /** @var array{creados: int, omitidos: int, errores: array<int, array{fila: int, mensaje: string}>} */
    public array $resultado = ['creados' => 0, 'omitidos' => 0, 'errores' => []];

    public function __construct(
        private int $idEmpresa,
        private int $idUsuario,
    ) {}

    public function collection(Collection $rows): void
    {
        $filas = $rows->map(fn ($fila) => $fila instanceof Collection ? $fila->toArray() : (array) $fila)->all();

        $this->resultado = (new ActivoImportacionMasivaService())->procesar(
            $filas,
            $this->idEmpresa,
            $this->idUsuario
        );
    }
}
