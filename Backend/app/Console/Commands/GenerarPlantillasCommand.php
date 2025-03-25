<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class GenerarPlantillasCommand extends Command
{
    
    protected $signature = 'ventas:generar-plantillas';


    protected $description = 'Genera las plantillas Excel para importación de ventas';


    public function __construct()
    {
        parent::__construct();
    }

   
    public function handle()
    {
        $this->info('Generando plantillas Excel para importación de ventas...');
        
 
        $this->generarPlantillaCreditoFiscal();
        $this->info('✓ Plantilla para Crédito Fiscal generada.');
        

        $this->generarPlantillaConsumidorFinal();
        $this->info('✓ Plantilla para Consumidor Final generada.');
        
        $this->info('Plantillas generadas correctamente en la carpeta public/docs/');
        
        return 0;
    }
    

    protected function generarPlantillaCreditoFiscal()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        
        $encabezados = [
            'nombre_comercial', 'nombre', 'NIT', 'NRC', 'cod_giro', 
            'cod_departamento', 'cod_municipio', 'direccion', 'telefono', 
            'correo', 'fecha', 'descripcion', 'tipo_item', 'forma_pago', 
            'no_sujeta', 'exenta', 'gravada', 'subtotal', 'iva', 
            'iva_retenido', 'total', 'condicion', 'fecha_pago'
        ];
        

        $this->escribirEncabezados($sheet, $encabezados);
        
      
        $ejemplos = [
            [
                'Empresa ABC', 'Corporación ABC S.A. de C.V.', '0614-010190-101-0', '12345-6', '12345',
                '1', '1', '1a Calle Poniente #123, Colonia Centro', '2222-3333',
                'info@empresa-abc.com', '2025-03-25', 'Servicio de mantenimiento', 'Servicio', 'Tarjeta de crédito/débito',
                '0', '0', '100.00', '100.00', '13.00',
                '0', '113.00', 'Contado', '2025-03-25'
            ]
        ];
        
        $this->escribirEjemplos($sheet, $ejemplos, 2);
        
       
        $this->agregarHojaInstrucciones($spreadsheet, 'credito_fiscal');
        
       
        $writer = new Xlsx($spreadsheet);
        $writer->save(public_path('docs/ventas-credito-fiscal-format.xlsx'));
    }
    
    /**
     * Generar plantilla para Consumidor Final
     */
    protected function generarPlantillaConsumidorFinal()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
       
        $encabezados = [
            'nombre', 'tipo_documento', 'num_documento', 'cod_departamento', 'cod_municipio',
            'direccion', 'telefono', 'correo', 'fecha', 'descripcion',
            'tipo_item', 'forma_pago', 'exenta', 'gravada', 'subtotal',
            'iva', 'iva_retenido', 'total', 'condicion', 'fecha_pago'
        ];
        
       
        $this->escribirEncabezados($sheet, $encabezados);
        
       
        $ejemplos = [
            [
                'Juan Pérez', 'DUI', '01234567-8', '1', '1',
                'Residencial Las Flores, Casa #10', '7777-8888', 'juan.perez@email.com', '2025-03-25', 'Reparación de laptop',
                'Servicio', 'Tarjeta de crédito/débito', '0', '50.00', '50.00',
                '6.50', '0', '56.50', 'Contado', '2025-03-25'
            ],
        ];
        
        $this->escribirEjemplos($sheet, $ejemplos, 2);
        
      
        $this->agregarHojaInstrucciones($spreadsheet, 'consumidor_final');
        
       
        $writer = new Xlsx($spreadsheet);
        $writer->save(public_path('docs/ventas-consumidor-final-format.xlsx'));
    }
    
    
    protected function escribirEncabezados($sheet, $encabezados)
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
    
   
    protected function escribirEjemplos($sheet, $ejemplos, $filaInicio)
    {
        foreach ($ejemplos as $indexFila => $ejemplo) {
            foreach ($ejemplo as $indexColumna => $valor) {
                $columna = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($indexColumna + 1);
                $sheet->setCellValue($columna . ($filaInicio + $indexFila), $valor);
            }
        }
    }
    
   
    protected function agregarHojaInstrucciones($spreadsheet, $tipo)
    {
        $instrucciones = $spreadsheet->createSheet();
        $instrucciones->setTitle('Instrucciones');
        
        // Escribir instrucciones según el tipo
        $titulo = ($tipo == 'credito_fiscal') ? 'INSTRUCCIONES PARA IMPORTAR VENTAS (CRÉDITO FISCAL)' : 'INSTRUCCIONES PARA IMPORTAR VENTAS (CONSUMIDOR FINAL)';
        $instrucciones->setCellValue('A1', $titulo);
        $instrucciones->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        
        $filas = [
            ['A3', '1. FORMATO DE LA PLANTILLA:'],
            ['A4', '- No modifique los encabezados de la primera fila.'],
            ['A5', '- Cada fila representa un producto/servicio vendido.'],
            ['A6', '- Las filas se agruparán en ventas según el cliente y la fecha.'],
            ['A8', '2. CAMPOS OBLIGATORIOS:'],
        ];
        
        // Campos obligatorios específicos para cada tipo
        if ($tipo == 'credito_fiscal') {
            $filas[] = ['A9', '- nombre_comercial: Nombre comercial del cliente.'];
            $filas[] = ['A10', '- nombre: Nombre legal del cliente.'];
            $filas[] = ['A11', '- NIT: NIT del cliente (formato correcto).'];
            $filas[] = ['A12', '- fecha: Fecha de la venta en formato YYYY-MM-DD.'];
            $filas[] = ['A13', '- descripcion: Descripción del producto o servicio.'];
            $filas[] = ['A14', '- total: Monto total de la venta.'];
        } else {
            $filas[] = ['A9', '- nombre: Nombre del cliente (o "Consumidor Final").'];
            $filas[] = ['A10', '- fecha: Fecha de la venta en formato YYYY-MM-DD.'];
            $filas[] = ['A11', '- descripcion: Descripción del producto o servicio.'];
            $filas[] = ['A12', '- total: Monto total de la venta.'];
        }
        
       
        $filas[] = ['A16', '3. CÓDIGOS DE DEPARTAMENTOS Y MUNICIPIOS:'];
        $filas[] = ['A17', '- Los códigos de departamento deben corresponder a los registrados en el sistema (ej. 01 para San Salvador).'];
        $filas[] = ['A18', '- Los códigos de municipio deben corresponder a los registrados en el sistema (ej. 0101 para San Salvador).'];
        
        $filas[] = ['A20', '4. TIPOS DE ÍTEM:'];
        $filas[] = ['A21', '- Producto: Para artículos físicos.'];
        $filas[] = ['A22', '- Servicio: Para servicios prestados.'];
        
        $filas[] = ['A24', '5. FORMAS DE PAGO:'];
        $filas[] = ['A25', '- Efectivo, Tarjeta, Cheque, Transferencia, Crédito, etc.'];
        
        $filas[] = ['A27', '6. CONDICIÓN:'];
        $filas[] = ['A28', '- Contado: Pago inmediato.'];
        $filas[] = ['A29', '- Crédito: Pago diferido (requiere fecha_pago).'];
        
        foreach ($filas as $fila) {
            $instrucciones->setCellValue($fila[0], $fila[1]);
            if (strpos($fila[0], 'A') === 0 && substr($fila[1], -1) === ':') {
                $instrucciones->getStyle($fila[0])->getFont()->setBold(true);
            }
        }
        
        
        $instrucciones->getColumnDimension('A')->setWidth(5);
        $instrucciones->getColumnDimension('B')->setWidth(60);
        
        $spreadsheet->setActiveSheetIndex(0);
    }
}