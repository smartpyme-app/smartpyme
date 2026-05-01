<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\NamedRange;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RefrescarPlantillasImportacionClientesCommand extends Command
{
    protected $signature = 'clientes:refrescar-plantillas-importacion';

    protected $description = 'Plantilla empresas: una fila de ejemplo genérica, hoja Instrucciones; personas: columna código de cliente';

    /** Filas de datos en la hoja «clientes» (fila 1 = encabezados; datos desde fila 2). */
    private const FILAS_DATOS_EMPRESAS = 1000;

    private const FILAS_DATOS_PERSONAS_SV = 1000;

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

    private function establecerFormulasCodigoEmpresaEnFila(Worksheet $sheet, int $row): void
    {
        $sheet->setCellValue(
            'D' . $row,
            '=IF(C' . $row . '<>"",INDEX(Actividades!A:A,MATCH(C' . $row . ',Actividades!B:B,0)),"")'
        );
        $sheet->setCellValue(
            'J' . $row,
            '=IF(I' . $row . '<>"",INDEX(Departamentos!A:A,MATCH(I' . $row . ',Departamentos!B:B,0)),"")'
        );
        $sheet->setCellValue(
            'L' . $row,
            '=IF(K' . $row . '<>"",INDEX(Distritos!A:A,MATCH(K' . $row . ',Distritos!B:B,0)),"")'
        );
        $sheet->setCellValue(
            'N' . $row,
            '=IF(M' . $row . '<>"",INDEX(Municipios!A:A,MATCH(M' . $row . ',Municipios!B:B,0)),"")'
        );
    }

    /**
     * Quita validaciones previas (p. ej. listas en fila 1 o #REF! en filas de datos).
     */
    private function limpiarValidacionesDatos(Worksheet $sheet): void
    {
        foreach (array_keys($sheet->getDataValidationCollection()) as $coord) {
            $sheet->setDataValidation($coord, null);
        }
    }

    private function nuevaValidacionLista(string $formula1): DataValidation
    {
        $dv = new DataValidation();
        $dv->setType(DataValidation::TYPE_LIST);
        $dv->setErrorStyle(DataValidation::STYLE_INFORMATION);
        $dv->setAllowBlank(true);
        $dv->setShowDropDown(true);
        $dv->setFormula1($formula1);

        return $dv;
    }

    /**
     * Referencia A1 para lista de validación (nombre de hoja entre comillas simples si hace falta).
     */
    private function rangoColumnaCatalogo(?Worksheet $catalogo, string $columna, int $filaInicio = 2): string
    {
        if ($catalogo === null) {
            return '';
        }

        $ultima = max((int) $catalogo->getHighestRow(), $filaInicio);
        $titulo = str_replace('\'', '\'\'', $catalogo->getTitle());

        return sprintf('\'%s\'!$%s$%d:$%s$%d', $titulo, $columna, $filaInicio, $columna, $ultima);
    }

    /**
     * Listas planas (sin INDIRECT): el usuario elige entre todo el catálogo como en departamentos.
     */
    private function aplicarValidacionesHojaClientesEmpresas(Spreadsheet $spreadsheet, Worksheet $sheet, int $ultimaFila): void
    {
        $this->limpiarValidacionesDatos($sheet);
        if ($ultimaFila < 2) {
            return;
        }

        $rangoDistritos = $this->rangoColumnaCatalogo($spreadsheet->getSheetByName('Distritos'), 'B', 2);
        $rangoMunicipios = $this->rangoColumnaCatalogo($spreadsheet->getSheetByName('Municipios'), 'B', 2);
        if ($rangoDistritos === '' || $rangoMunicipios === '') {
            $this->warn('No se encontraron hojas Distritos/Municipios; listas de distrito/municipio omitidas.');
        }

        $r2 = 2;
        $sheet->setDataValidation('C' . $r2 . ':C' . $ultimaFila, $this->nuevaValidacionLista('Actividades!$B$2:$B$774'));
        $sheet->setDataValidation('E' . $r2 . ':E' . $ultimaFila, $this->nuevaValidacionLista('tipo_contribuyente!$A$1:$A$4'));
        $sheet->setDataValidation('I' . $r2 . ':I' . $ultimaFila, $this->nuevaValidacionLista('Departamentos!$B$2:$B$16'));
        if ($rangoDistritos !== '') {
            $sheet->setDataValidation('K' . $r2 . ':K' . $ultimaFila, $this->nuevaValidacionLista($rangoDistritos));
        }
        if ($rangoMunicipios !== '') {
            $sheet->setDataValidation('M' . $r2 . ':M' . $ultimaFila, $this->nuevaValidacionLista($rangoMunicipios));
        }
    }

    private function aplicarValidacionesHojaClientesPersonasSv(Spreadsheet $spreadsheet, Worksheet $sheet, int $ultimaFila): void
    {
        $this->limpiarValidacionesDatos($sheet);
        if ($ultimaFila < 2) {
            return;
        }

        $rangoDistritos = $this->rangoColumnaCatalogo($spreadsheet->getSheetByName('Distritos'), 'B', 2);
        $rangoMunicipios = $this->rangoColumnaCatalogo($spreadsheet->getSheetByName('Municipios'), 'B', 2);
        if ($rangoDistritos === '' || $rangoMunicipios === '') {
            $this->warn('No se encontraron hojas Distritos/Municipios en plantilla personas; listas distrito/municipio omitidas.');
        }

        $r2 = 2;
        $sheet->setDataValidation('G' . $r2 . ':G' . $ultimaFila, $this->nuevaValidacionLista('Departamentos!$B$2:$B$16'));
        if ($rangoDistritos !== '') {
            $sheet->setDataValidation('I' . $r2 . ':I' . $ultimaFila, $this->nuevaValidacionLista($rangoDistritos));
        }
        if ($rangoMunicipios !== '') {
            $sheet->setDataValidation('K' . $r2 . ':K' . $ultimaFila, $this->nuevaValidacionLista($rangoMunicipios));
        }
    }

    /**
     * El libro trae Municipios_7 y Municipios_8 corruptos (comillas en el nombre de hoja); INDIRECT falla para esos deptos.
     */
    private function repararRangosNombreMunicipiosRotos(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->getSheetByName('Municipios');
        if ($sheet === null) {
            return;
        }

        $rangos = [
            'Municipios_7' => '$B$27:$B$28',
            'Municipios_8' => '$B$29:$B$31',
        ];

        foreach ($rangos as $nombre => $rango) {
            $spreadsheet->removeNamedRange($nombre);
            $spreadsheet->addNamedRange(new NamedRange($nombre, $sheet, $rango));
        }
    }

    private function actualizarPlantillaEmpresas(): void
    {
        $path = public_path('docs/clientes-empresas-format.xlsx');
        $spreadsheet = IOFactory::load($path);
        $this->repararRangosNombreMunicipiosRotos($spreadsheet);
        $existente = $spreadsheet->getSheetByName('Instrucciones');
        if ($existente !== null) {
            $spreadsheet->removeSheetByIndex($spreadsheet->getIndex($existente));
        }

        $clientes = $spreadsheet->getSheetByName('clientes');
        if ($clientes === null) {
            $this->warn('No se encontró hoja clientes en clientes-empresas-format.xlsx');

            return;
        }

        $fr = self::FILAS_DATOS_EMPRESAS;
        $highest = (int) $clientes->getHighestRow();
        if ($highest > $fr) {
            $clientes->removeRow($fr + 1, $highest - $fr);
        }

        for ($r = 3; $r <= $fr; $r++) {
            foreach (['A', 'B', 'C', 'E', 'F', 'G', 'H', 'I', 'K', 'M', 'O', 'P'] as $col) {
                $clientes->setCellValue($col . $r, null);
            }
            $this->establecerFormulasCodigoEmpresaEnFila($clientes, $r);
        }

        $this->rellenarFilaEjemploEmpresasSv($clientes);
        $this->aplicarValidacionesHojaClientesEmpresas($spreadsheet, $clientes, $fr);

        $instr = new Worksheet($spreadsheet, 'Instrucciones');
        $lineas = [
            'Importación de clientes tipo empresa (El Salvador)',
            '',
            '1. Complete únicamente la hoja «clientes». Las demás son catálogos para las fórmulas de códigos (cod_giro, cod_departamento, etc.).',
            '2. nombre_empresa y NCR son obligatorios.',
            '3. Distrito (K) y municipio (M) tienen listas con todo el catálogo (como departamento): puede buscar y elegir cualquier fila. Conviene que coincida con el departamento (I) para que los códigos J, L y N sean coherentes.',
            '4. La fila 2 es un ejemplo; desde la fila 3 tiene las mismas fórmulas y listas para agregar más clientes.',
            '5. No elimine ni mueva las pestañas de catálogo (Actividades, Departamentos, Distritos, Municipios) si usa listas o fórmulas.',
            '6. Solo se importa la primera hoja del archivo; debe llamarse «clientes» y ser la primera pestaña (izquierda).',
            '7. nit y teléfono: use formato texto en Excel cuando haga falta para no perder ceros.',
            '8. correo es opcional; «-» o vacío se aceptan.',
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
        $this->repararRangosNombreMunicipiosRotos($spreadsheet);
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

        $ultima = max((int) $sheet->getHighestRow(), self::FILAS_DATOS_PERSONAS_SV);
        $this->aplicarValidacionesHojaClientesPersonasSv($spreadsheet, $sheet, $ultima);

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
