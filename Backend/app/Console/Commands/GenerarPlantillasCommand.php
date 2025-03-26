<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

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

        // Añadir validaciones para listas desplegables
        $this->agregarValidaciones($sheet, $encabezados);
       
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
        
        // Añadir validaciones para listas desplegables
        $this->agregarValidaciones($sheet, $encabezados);
      
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
    
    /**
     * Agregar validaciones de datos (listas desplegables)
     */
    protected function agregarValidaciones($sheet, $encabezados)
    {
        // Encontrar índice de las columnas que necesitan validación
        $tipoDocumentoIndex = array_search('tipo_documento', $encabezados);
        $tipoItemIndex = array_search('tipo_item', $encabezados);
        $formaPagoIndex = array_search('forma_pago', $encabezados);
        $condicionIndex = array_search('condicion', $encabezados);
        
        // Configurar la validación para 100 filas (ajustar según necesidad)
        $numFilas = 100;
        
        // Agregar lista desplegable para tipo_documento si existe
        if ($tipoDocumentoIndex !== false) {
            $columna = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($tipoDocumentoIndex + 1);
            $this->agregarListaDesplegable(
                $sheet, 
                $columna . '2:' . $columna . ($numFilas + 1), 
                '"DUI,NIT,Pasaporte,Carnet de residente,Otro"'
            );
        }
        
        // Agregar lista desplegable para tipo_item si existe
        if ($tipoItemIndex !== false) {
            $columna = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($tipoItemIndex + 1);
            $this->agregarListaDesplegable(
                $sheet, 
                $columna . '2:' . $columna . ($numFilas + 1), 
                '"Producto,Servicio"'
            );
        }
        
        // Agregar lista desplegable para forma_pago si existe
        if ($formaPagoIndex !== false) {
            $columna = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($formaPagoIndex + 1);
            $this->agregarListaDesplegable(
                $sheet, 
                $columna . '2:' . $columna . ($numFilas + 1), 
                '"Efectivo,Tarjeta de crédito/débito,Cheque,Transferencia,CARGO AUTOMATICO"'
            );
        }
        
        // Agregar lista desplegable para condicion si existe
        if ($condicionIndex !== false) {
            $columna = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($condicionIndex + 1);
            $this->agregarListaDesplegable(
                $sheet, 
                $columna . '2:' . $columna . ($numFilas + 1), 
                '"Contado,Crédito"'
            );
        }
    }
    
    /**
     * Agregar una lista desplegable a un rango de celdas
     */
    protected function agregarListaDesplegable($sheet, $rango, $opciones)
    {
        // Crear la validación
        $validation = $sheet->getCell(explode(':', $rango)[0])->getDataValidation();
        $validation->setType(DataValidation::TYPE_LIST)
            ->setErrorStyle(DataValidation::STYLE_INFORMATION)
            ->setAllowBlank(true)
            ->setShowInputMessage(true)
            ->setShowErrorMessage(true)
            ->setShowDropDown(true)
            ->setFormula1($opciones);
        
        // Aplicar la validación al rango completo
        $sheet->setDataValidation($rango, $validation);
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
            ['A7', '- Utilice las listas desplegables para seleccionar valores en tipo de documento, tipo de ítem, forma de pago y condición.'],
            ['A9', '2. CAMPOS OBLIGATORIOS:'],
        ];
        
        // Campos obligatorios específicos para cada tipo
        if ($tipo == 'credito_fiscal') {
            $filas[] = ['A10', '- nombre_comercial: Nombre comercial del cliente.'];
            $filas[] = ['A11', '- nombre: Nombre legal del cliente.'];
            $filas[] = ['A12', '- NIT: NIT del cliente (formato correcto).'];
            $filas[] = ['A13', '- fecha: Fecha de la venta en formato YYYY-MM-DD.'];
            $filas[] = ['A14', '- descripcion: Descripción del producto o servicio.'];
            $filas[] = ['A15', '- total: Monto total de la venta.'];
        } else {
            $filas[] = ['A10', '- nombre: Nombre del cliente (o "Consumidor Final").'];
            $filas[] = ['A11', '- tipo_documento: Seleccione de la lista desplegable (DUI, NIT, Pasaporte, etc.)'];
            $filas[] = ['A12', '- fecha: Fecha de la venta en formato YYYY-MM-DD.'];
            $filas[] = ['A13', '- descripcion: Descripción del producto o servicio.'];
            $filas[] = ['A14', '- total: Monto total de la venta.'];
        }
        
       
        $filas[] = ['A17', '3. CÓDIGOS DE DEPARTAMENTOS Y MUNICIPIOS:'];
        $filas[] = ['A18', '- Los códigos de departamento deben corresponder a los registrados en el sistema (ej. 01 para San Salvador).'];
        $filas[] = ['A19', '- Los códigos de municipio deben corresponder a los registrados en el sistema (ej. 0101 para San Salvador).'];
        
        $filas[] = ['A21', '4. TIPOS DE ÍTEM:'];
        $filas[] = ['A22', '- Producto: Para artículos físicos.'];
        $filas[] = ['A23', '- Servicio: Para servicios prestados.'];
        
        $filas[] = ['A25', '5. FORMAS DE PAGO:'];
        $filas[] = ['A26', '- Efectivo, Tarjeta de crédito/débito, Cheque, Transferencia, CARGO AUTOMATICO.'];
        
        $filas[] = ['A28', '6. CONDICIÓN:'];
        $filas[] = ['A29', '- Contado: Pago inmediato.'];
        $filas[] = ['A30', '- Crédito: Pago diferido (requiere fecha_pago).'];
        
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