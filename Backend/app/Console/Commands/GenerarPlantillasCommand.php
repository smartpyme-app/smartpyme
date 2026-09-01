<?php

namespace App\Console\Commands;

use App\Models\MH\ActividadEconomica;
use App\Models\MH\Departamento;
use App\Models\MH\Distrito;
use App\Models\MH\Municipio;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class GenerarPlantillasCommand extends Command
{
    protected $signature = 'ventas:generar-plantillas';

    protected $description = 'Genera la plantilla Excel unificada para importación de ventas históricas';

    public function handle()
    {
        $this->info('Generando plantilla unificada de importación de ventas...');
        $this->generarPlantillaUnificada();
        $this->borrarPlantillasViejas();
        $this->info('Plantilla generada en public/docs/ventas-format.xlsx');

        return 0;
    }

    protected function generarPlantillaUnificada(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Plantilla ventas');

        $encabezados = [
            'tipo_cliente', 'tipo_documento_venta', 'correlativo', 'estado_factura',
            'nombre', 'tipo_documento', 'num_documento',
            'nombre_comercial', 'nit', 'nrc', 'giro',
            'departamento', 'municipio', 'distrito', 'direccion', 'telefono', 'correo',
            'fecha', 'descripcion', 'tipo_item', 'forma_pago',
            'no_sujeta', 'exenta', 'gravada', 'subtotal', 'iva', 'iva_retenido', 'total',
            'condicion', 'fecha_pago',
        ];

        $this->escribirEncabezados($sheet, $encabezados);
        $this->agregarHojaValores($spreadsheet);
        $this->agregarValidaciones($sheet, $encabezados);
        $this->agregarHojaInstrucciones($spreadsheet);

        $dir = public_path('docs');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($dir . '/ventas-format.xlsx');
    }

    protected function borrarPlantillasViejas(): void
    {
        foreach (['ventas-credito-fiscal-format.xlsx', 'ventas-consumidor-final-format.xlsx'] as $vieja) {
            $path = public_path('docs/' . $vieja);
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    protected function escribirEncabezados($sheet, array $encabezados): void
    {
        foreach ($encabezados as $index => $encabezado) {
            $columna = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue($columna . '1', $encabezado);
            $sheet->getStyle($columna . '1')->getFont()->setBold(true);
            $sheet->getStyle($columna . '1')->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('DDEBF7');
            $sheet->getColumnDimension($columna)->setAutoSize(true);
        }
        $sheet->freezePane('A2');
    }

    protected function agregarValidaciones($sheet, array $encabezados): void
    {
        $numFilas = 100;
        $listas = [
            'tipo_cliente' => '"Persona,Empresa"',
            'tipo_documento_venta' => '"Factura,Ticket,Crédito fiscal,Factura de exportación"',
            'tipo_documento' => '"DUI,NIT,Pasaporte,Carnet de residente,Otro"',
            'estado_factura' => '"Pagada,Pendiente,Anulada"',
            'tipo_item' => '"Servicio"',
            'forma_pago' => '"Efectivo,Tarjeta de crédito/débito,Cheque,Transferencia,Vales,Chivo Wallet,Bitcoin"',
            'condicion' => '"Contado,Crédito"',
        ];

        foreach ($listas as $campo => $opciones) {
            $idx = array_search($campo, $encabezados, true);
            if ($idx === false) {
                continue;
            }
            $columna = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($idx + 1);
            $this->agregarListaDesplegable($sheet, $columna . '2:' . $columna . ($numFilas + 1), $opciones);
        }
    }

    protected function agregarListaDesplegable($sheet, string $rango, string $opciones): void
    {
        $validation = $sheet->getCell(explode(':', $rango)[0])->getDataValidation();
        $validation->setType(DataValidation::TYPE_LIST)
            ->setErrorStyle(DataValidation::STYLE_INFORMATION)
            ->setAllowBlank(true)
            ->setShowInputMessage(true)
            ->setShowErrorMessage(true)
            ->setShowDropDown(true)
            ->setFormula1($opciones);
        $sheet->setDataValidation($rango, $validation);
    }

    protected function agregarHojaValores(Spreadsheet $spreadsheet): void
    {
        $valores = $spreadsheet->createSheet();
        $valores->setTitle('Valores');
        $valores->setCellValue('A1', 'departamento');
        $valores->setCellValue('B1', 'municipio');
        $valores->setCellValue('C1', 'distrito');
        $valores->setCellValue('D1', 'giro');
        $valores->getStyle('A1:D1')->getFont()->setBold(true);

        try {
            $deps = Departamento::orderBy('nombre')->pluck('nombre')->all();
            $muns = Municipio::orderBy('nombre')->pluck('nombre')->all();
            $dists = Distrito::orderBy('nombre')->pluck('nombre')->all();
            $giros = ActividadEconomica::orderBy('nombre')->pluck('nombre')->all();
        } catch (\Throwable $e) {
            $deps = $muns = $dists = $giros = [];
        }

        foreach ($deps as $i => $nombre) {
            $valores->setCellValue('A' . ($i + 2), $nombre);
        }
        foreach ($muns as $i => $nombre) {
            $valores->setCellValue('B' . ($i + 2), $nombre);
        }
        foreach ($dists as $i => $nombre) {
            $valores->setCellValue('C' . ($i + 2), $nombre);
        }
        foreach ($giros as $i => $nombre) {
            $valores->setCellValue('D' . ($i + 2), $nombre);
        }
    }

    protected function agregarHojaInstrucciones(Spreadsheet $spreadsheet): void
    {
        $instrucciones = $spreadsheet->createSheet();
        $instrucciones->setTitle('Instrucciones');
        $instrucciones->setCellValue('A1', 'INSTRUCCIONES PARA IMPORTAR VENTAS HISTÓRICAS');
        $instrucciones->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $filas = [
            ['A3', '1. FORMATO:'],
            ['A4', 'No modifique los encabezados de la primera fila. Cada fila es un ítem de una venta.'],
            ['A5', 'Varias filas con el mismo correlativo + mismo cliente + mismo tipo de documento = una venta con varios detalles.'],
            ['A7', '2. OBLIGATORIOS SIEMPRE:'],
            ['A8', 'tipo_cliente, tipo_documento_venta, correlativo, nombre, fecha, descripcion, total, forma_pago.'],
            ['A9', 'El correlativo es el número histórico de la factura; no se asigna automáticamente.'],
            ['A11', '3. TIPO DE CLIENTE Y DOCUMENTO:'],
            ['A12', 'tipo_cliente: Persona o Empresa. Una Persona también puede recibir Crédito fiscal.'],
            ['A13', 'Si tipo_documento_venta es Crédito fiscal, nit y nrc son obligatorios (Persona o Empresa).'],
            ['A14', 'tipo_documento_venta: Factura, Ticket, Crédito fiscal, Factura de exportación.'],
            ['A16', '4. FORMAS DE PAGO:'],
            ['A17', 'Efectivo, Tarjeta de crédito/débito, Cheque, Transferencia, Vales, Chivo Wallet, Bitcoin.'],
            ['A19', '5. DETALLE:'],
            ['A20', 'Son ventas históricas: el ítem se guarda siempre como Servicio. No se busca en inventario.'],
            ['A22', '6. CONDICIÓN:'],
            ['A23', 'Contado o Crédito. Si es Crédito, fecha_pago es obligatorio.'],
            ['A25', '7. ERRORES:'],
            ['A26', 'Si hay un error no se importa nada. El sistema indica fila, columna y motivo.'],
        ];

        foreach ($filas as $fila) {
            $instrucciones->setCellValue($fila[0], $fila[1]);
            if (str_ends_with($fila[1], ':')) {
                $instrucciones->getStyle($fila[0])->getFont()->setBold(true);
            }
        }
        $instrucciones->getColumnDimension('A')->setWidth(120);
        $spreadsheet->setActiveSheetIndex(0);
    }
}
