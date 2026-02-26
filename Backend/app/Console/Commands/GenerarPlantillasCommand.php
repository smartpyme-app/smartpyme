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
            'correlativo', 'estado_factura', 'tipo_documento_venta',
            'nombre_comercial', 'nombre', 'nit', 'nrc', 'cod_giro',
            'cod_departamento', 'cod_municipio', 'direccion', 'telefono',
            'correo', 'fecha', 'descripcion', 'tipo_item', 'forma_pago',
            'no_sujeta', 'exenta', 'gravada', 'subtotal', 'iva',
            'iva_retenido', 'total', 'condicion', 'fecha_pago'
        ];
        

        $this->escribirEncabezados($sheet, $encabezados);
        
      
        $ejemplos = [
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
            'correlativo', 'estado_factura', 'tipo_documento_venta',
            'nombre', 'tipo_documento', 'num_documento',
            'direccion', 'telefono', 'correo', 'fecha', 'descripcion',
            'tipo_item', 'forma_pago', 'exenta', 'gravada', 'subtotal',
            'iva', 'iva_retenido', 'total', 'condicion', 'fecha_pago'
        ];
        
        
        $this->escribirEncabezados($sheet, $encabezados);
        
        // Datos de prueba (sin cod_departamento ni cod_municipio); Ticket = Factura consumidor final
        $ejemplos = [
            [100, 'Pagada', 'Factura', 'Juan Perez', 'DUI', '05027470-7', 'Av. Principal 123, San Salvador', '2222-3333', 'ventas@esperanza.com', '2025-02-03', 'Producto A - Venta al por mayor', 'Producto', 'Tarjeta de crédito/débito', 0, 100, 100, 13, 0, 113, 'Contado', '2025-03-15'],
            [1, 'Pagada', 'Ticket', 'Jose Perez', 'NIT', '05027470-8', 'Av. Principal 456, San Salvador', '2222-3334', 'ventas@esperanza.com', '2025-02-03', 'Producto B - Accesorio', 'Producto', 'Tarjeta de crédito/débito', 0, 100, 100, 13, 0, 113, 'Contado', '2025-03-15'],
            [101, 'Pendiente', 'Factura', 'Rafael Perez', 'DUI', '05027470-9', 'Av. Principal 789, San Salvador', '2222-3335', 'ventas@esperanza.com', '2025-02-03', 'Servicio de entrega', 'Servicio', 'Tarjeta de crédito/débito', 0, 100, 100, 13, 0, 113, 'Contado', '2025-03-15'],
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
        $estadoFacturaIndex = array_search('estado_factura', $encabezados);
        $tipoDocumentoVentaIndex = array_search('tipo_documento_venta', $encabezados);
        $tipoDocumentoIndex = array_search('tipo_documento', $encabezados);
        $tipoItemIndex = array_search('tipo_item', $encabezados);
        $formaPagoIndex = array_search('forma_pago', $encabezados);
        $condicionIndex = array_search('condicion', $encabezados);
        
        // Configurar la validación para 100 filas (ajustar según necesidad)
        $numFilas = 100;
        
        // Agregar lista desplegable para estado_factura si existe
        if ($estadoFacturaIndex !== false) {
            $columna = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($estadoFacturaIndex + 1);
            $this->agregarListaDesplegable(
                $sheet,
                $columna . '2:' . $columna . ($numFilas + 1),
                '"Pagada,Pendiente,Anulada"'
            );
        }

        // Agregar lista desplegable para tipo_documento_venta si existe
        if ($tipoDocumentoVentaIndex !== false) {
            $columna = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($tipoDocumentoVentaIndex + 1);
            $this->agregarListaDesplegable(
                $sheet,
                $columna . '2:' . $columna . ($numFilas + 1),
                '"Factura,Ticket,Crédito Fiscal,Factura de exportación"'
            );
        }

        // Agregar lista desplegable para tipo_documento (cliente) si existe
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
                '"Servicio"'
            );
        }
        
        // Agregar lista desplegable para forma_pago si existe
        if ($formaPagoIndex !== false) {
            $columna = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($formaPagoIndex + 1);
            $this->agregarListaDesplegable(
                $sheet, 
                $columna . '2:' . $columna . ($numFilas + 1), 
                '"Tarjeta de crédito/débito"'
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
        $filas[] = ['A10', '- correlativo: Número de factura (opcional; si se omite se asigna automáticamente).'];
        $filas[] = ['A11', '- estado_factura: Pagada, Pendiente o Anulada.'];
        $filas[] = ['A12', '- tipo_documento_venta: Factura, Ticket (consumidor final) o Crédito Fiscal.'];
        // Campos obligatorios específicos para cada tipo
        if ($tipo == 'credito_fiscal') {
            $filas[] = ['A13', '- nombre_comercial: Nombre comercial del cliente.'];
            $filas[] = ['A14', '- nombre: Nombre legal del cliente.'];
            $filas[] = ['A15', '- nit: NIT del cliente (formato correcto).'];
            $filas[] = ['A16', '- fecha: Fecha de la venta en formato YYYY-MM-DD.'];
            $filas[] = ['A17', '- descripcion: Descripción del producto o servicio.'];
            $filas[] = ['A18', '- total: Monto total de la venta.'];
        } else {
            $filas[] = ['A13', '- nombre: Nombre del cliente (o "Consumidor Final").'];
            $filas[] = ['A14', '- tipo_documento: Seleccione de la lista desplegable (DUI, NIT, Pasaporte, etc.)'];
            $filas[] = ['A15', '- fecha: Fecha de la venta en formato YYYY-MM-DD.'];
            $filas[] = ['A16', '- descripcion: Descripción del producto o servicio.'];
            $filas[] = ['A17', '- total: Monto total de la venta.'];
        }
        
       
        if ($tipo == 'credito_fiscal') {
            $filas[] = ['A20', '3. CÓDIGOS DE DEPARTAMENTOS Y MUNICIPIOS (opcionales):'];
            $filas[] = ['A21', '- Los códigos de departamento y municipio deben corresponder a los registrados en el sistema.'];
        } else {
            $filas[] = ['A20', '3. UBICACIÓN:'];
            $filas[] = ['A21', '- No es necesario cod_departamento ni cod_municipio en esta plantilla.'];
        }

        $filas[] = ['A24', '4. TIPOS DE ÍTEM:'];
        $filas[] = ['A25', '- Producto: Para artículos físicos.'];
        $filas[] = ['A26', '- Servicio: Para servicios prestados.'];

        $filas[] = ['A28', '5. FORMAS DE PAGO:'];
        $filas[] = ['A29', '- Efectivo, Tarjeta de crédito/débito'];

        $filas[] = ['A31', '6. CONDICIÓN:'];
        $filas[] = ['A32', '- Contado: Pago inmediato.'];
        $filas[] = ['A33', '- Crédito: Pago diferido (requiere fecha_pago).'];
        
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