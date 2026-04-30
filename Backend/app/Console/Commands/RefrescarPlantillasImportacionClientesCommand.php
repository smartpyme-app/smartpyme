<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RefrescarPlantillasImportacionClientesCommand extends Command
{
    protected $signature = 'clientes:refrescar-plantillas-importacion';

    protected $description = 'Plantilla empresas: una fila de ejemplo genérica, hoja Instrucciones; personas: columna código de cliente';

    public function handle(): int
    {
        $this->actualizarPlantillaEmpresas();
        $this->actualizarPlantillaPersonasSv();
        $this->actualizarPlantillaPersonasGeneral();

        $this->info('Plantillas actualizadas en public/docs/');

        return 0;
    }

    /**
     * Datos de muestra genéricos (como en clientes-personas): texto de catálogo válido para que
     * las fórmulas de códigos sigan funcionando; sin nombres, correos ni direcciones que parezcan reales.
     */
    private function rellenarFilaEjemploEmpresasSv(Worksheet $sheet): void
    {
        $row = 2;
        $sheet->setCellValue('A' . $row, 'Empresa de ejemplo S.A. de C.V.');
        $sheet->setCellValue('B' . $row, '12345678901234');
        // Debe coincidir exactamente con una fila del catálogo Actividades (columna B).
        $sheet->setCellValue('C' . $row, 'Acabado de productos textiles');
        $sheet->setCellValue(
            'D' . $row,
            '=IF(C' . $row . '<>"",INDEX(Actividades!A:A,MATCH(C' . $row . ',Actividades!B:B,0)),"")'
        );
        $sheet->setCellValue('E' . $row, 'Otro');
        $sheet->setCellValue('F' . $row, '');
        $sheet->setCellValue('G' . $row, '06140101000000');
        $sheet->setCellValue('H' . $row, 'Colonia Ejemplo, Calle Principal #123, San Salvador');
        $sheet->setCellValue('I' . $row, 'San Salvador');
        $sheet->setCellValue(
            'J' . $row,
            '=IF(I' . $row . '<>"",INDEX(Departamentos!A:A,MATCH(I' . $row . ',Departamentos!B:B,0)),"")'
        );
        $sheet->setCellValue('K' . $row, 'SAN SALVADOR');
        $sheet->setCellValue(
            'L' . $row,
            '=IF(K' . $row . '<>"",INDEX(Distritos!A:A,MATCH(K' . $row . ',Distritos!B:B,0)),"")'
        );
        $sheet->setCellValue('M' . $row, 'SAN SALVADOR CENTRO');
        $sheet->setCellValue(
            'N' . $row,
            '=IF(M' . $row . '<>"",INDEX(Municipios!A:A,MATCH(M' . $row . ',Municipios!B:B,0)),"")'
        );
        $sheet->setCellValue('O' . $row, '22223333');
        $sheet->setCellValue('P' . $row, 'correoejemplo@ejemplo.com');
    }

    private function actualizarPlantillaEmpresas(): void
    {
        $path = public_path('docs/clientes-empresas-format.xlsx');
        $spreadsheet = IOFactory::load($path);
        $existente = $spreadsheet->getSheetByName('Instrucciones');
        if ($existente !== null) {
            $spreadsheet->removeSheetByIndex($spreadsheet->getIndex($existente));
        }

        $clientes = $spreadsheet->getSheetByName('clientes');
        if ($clientes === null) {
            $this->warn('No se encontró hoja clientes en clientes-empresas-format.xlsx');

            return;
        }

        $highest = (int) $clientes->getHighestRow();
        for ($r = $highest; $r >= 3; $r--) {
            $clientes->removeRow($r);
        }

        $this->rellenarFilaEjemploEmpresasSv($clientes);

        $instr = new Worksheet($spreadsheet, 'Instrucciones');
        $lineas = [
            'Importación de clientes tipo empresa (El Salvador)',
            '',
            '1. Complete únicamente la hoja «clientes». Las demás son catálogos para las fórmulas de códigos (cod_giro, cod_departamento, etc.).',
            '2. nombre_empresa y NCR son obligatorios.',
            '3. Use textos exactos del catálogo para giro, departamento, distrito y municipio; si la plantilla tiene fórmulas, los códigos se calculan solos.',
            '4. No elimine ni mueva las pestañas de catálogo si necesita las fórmulas.',
            '5. Solo se importa la primera hoja del archivo; debe llamarse «clientes» y ser la primera pestaña (izquierda).',
            '6. nit y teléfono: use formato texto en Excel cuando haga falta para no perder ceros.',
            '7. correo es opcional; «-» o vacío se aceptan.',
        ];
        foreach ($lineas as $i => $texto) {
            $instr->setCellValue('A' . ($i + 1), $texto);
        }
        $instr->getColumnDimension('A')->setWidth(95);
        $spreadsheet->addSheet($instr, 1);

        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);
        $this->line('✓ clientes-empresas-format.xlsx');
    }

    private function actualizarPlantillaPersonasSv(): void
    {
        $path = public_path('docs/clientes-personas-format.xlsx');
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheetByName('clientes');
        if ($sheet === null) {
            $this->warn('No sheet clientes in personas SV');

            return;
        }

        $h1 = $sheet->getCell('C1')->getValue();
        if ($h1 === 'codigo de cliente' || $h1 === 'Codigo de cliente') {
            $this->line('○ clientes-personas-format.xlsx ya tenía columna código de cliente');
        } else {
            $sheet->insertNewColumnBefore('C', 1);
            $sheet->setCellValue('C1', 'codigo de cliente');
        }

        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);
        $this->line('✓ clientes-personas-format.xlsx');
    }

    private function actualizarPlantillaPersonasGeneral(): void
    {
        $path = public_path('docs/clientes-personas-format-general.xlsx');
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $h1 = $sheet->getCell('B1')->getValue();
        $h2 = $sheet->getCell('C1')->getValue();
        // Apellido en B, documento en C típicamente
        if (stripos((string) $h2, 'codigo') !== false && stripos((string) $h2, 'cliente') !== false) {
            $this->line('○ clientes-personas-format-general.xlsx ya tenía columna código');

            return;
        }
        if ((string) $h1 === 'Apellido' || (string) $h1 === 'apellido') {
            $sheet->insertNewColumnBefore('C', 1);
            $sheet->setCellValue('C1', 'Codigo de cliente');
        } else {
            $sheet->insertNewColumnBefore('B', 1);
            $sheet->setCellValue('B1', 'Codigo de cliente');
        }

        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);
        $this->line('✓ clientes-personas-format-general.xlsx');
    }
}
