<?php

namespace App\Imports;

use App\Services\Paquetes\PaqueteExternalImportService;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use JWTAuth;

class Paquetes implements ToModel, WithHeadingRow, WithValidation
{
    private $numRows = 0;

    public function model(array $row)
    {
        $usuario = JWTAuth::parseToken()->authenticate();
        $service = app(PaqueteExternalImportService::class);
        $result = $service->importRow(
            (int) $usuario->id_empresa,
            (int) $usuario->id,
            (int) $usuario->id_sucursal,
            $row
        );

        if ($result['status'] === 'created') {
            ++$this->numRows;

            return $result['paquete'];
        }

        return null;
    }

    public function rules(): array
    {
        return PaqueteExternalImportService::rowValidationRules();
    }


    public function getRowCount(): int
    {
        return $this->numRows;
    }
}
