<?php

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$origen = __DIR__ . '/../public/docs/ventas-format.xlsx';
$destino = dirname(__DIR__, 2) . '/ventas-format-ejemplo.xlsx';

$spreadsheet = IOFactory::load($origen);
$sheet = $spreadsheet->getSheetByName('Plantilla ventas');

$filas = [
    // 1. Persona + Factura + Efectivo
    ['Persona', 'Factura', 1001, 'Pagada', 'Juan Perez', 'DUI', '01234567-8', '', '', '', '', 'San Salvador', 'SAN SALVADOR CENTRO', 'SAN SALVADOR', 'Col. Escalón, Av. Las Magnolias 12', '2222-1001', 'juan.perez@example.com', '2025-06-02', 'Consulta médica', 'Servicio', 'Efectivo', 0, 0, 100, 100, 13, 0, 113, 'Contado', ''],
    // 2. Persona + Ticket + Tarjeta
    ['Persona', 'Ticket', 1002, 'Pagada', 'Maria Lopez', 'DUI', '02345678-9', '', '', '', '', 'San Salvador', 'SAN SALVADOR CENTRO', 'MEJICANOS', 'Mejicanos, Calle Principal 45', '2222-1002', 'maria.lopez@example.com', '2025-06-03', 'Venta mostrador', 'Servicio', 'Tarjeta de crédito/débito', 0, 0, 50, 50, 6.5, 0, 56.5, 'Contado', ''],
    // 3. Persona + CCF (nit y nrc) + Transferencia
    ['Persona', 'Crédito fiscal', 2001, 'Pagada', 'Carlos Rivera', 'NIT', '06142203851012', 'Consultorio Rivera', '0614-220385-101-2', '123456-7', 'Actividades de atención de la salud humana', 'San Salvador', 'SAN SALVADOR CENTRO', 'SAN SALVADOR', 'Col. Médica, Calle Arce 88', '2222-1003', 'carlos.rivera@example.com', '2025-06-04', 'Honorarios profesionales', 'Servicio', 'Transferencia', 0, 0, 200, 200, 26, 0, 226, 'Contado', ''],
    // 4. Empresa + CCF + Cheque
    ['Empresa', 'Crédito fiscal', 2002, 'Pagada', 'DISTRIBUIDORA ESPERANZA, S.A. DE C.V.', '', '', 'Distribuidora Esperanza', '0614-010190-001-1', '234567-8', 'Actividades de apoyo empresariales ncp', 'La Libertad', 'LA LIBERTAD CENTRO', 'SANTA TECLA', 'Santa Tecla, Blvd. Orden de Malta', '2222-1004', 'ventas@esperanza.com', '2025-06-05', 'Suministros de oficina', 'Servicio', 'Cheque', 0, 0, 500, 500, 65, 0, 565, 'Contado', ''],
    // 5-6. Una venta con dos ítems (mismo correlativo)
    ['Persona', 'Factura', 1003, 'Pagada', 'Ana Martinez', 'DUI', '03456789-0', '', '', '', '', 'San Salvador', 'SAN SALVADOR CENTRO', 'SAN SALVADOR', 'Col. San Benito 3', '2222-1005', 'ana.martinez@example.com', '2025-06-06', 'Servicio de diseño', 'Servicio', 'Efectivo', 0, 0, 80, 80, 10.4, 0, 90.4, 'Contado', ''],
    ['Persona', 'Factura', 1003, 'Pagada', 'Ana Martinez', 'DUI', '03456789-0', '', '', '', '', 'San Salvador', 'SAN SALVADOR CENTRO', 'SAN SALVADOR', 'Col. San Benito 3', '2222-1005', 'ana.martinez@example.com', '2025-06-06', 'Impresión de material', 'Servicio', 'Efectivo', 0, 0, 20, 20, 2.6, 0, 22.6, 'Contado', ''],
    // 7. Persona + Factura a crédito
    ['Persona', 'Factura', 1004, 'Pendiente', 'Rafael Gomez', 'DUI', '04567890-1', '', '', '', '', 'Santa Ana', 'SANTA ANA CENTRO', 'SANTA ANA', 'Santa Ana, 10a Av. Norte', '2440-1006', 'rafael.gomez@example.com', '2025-06-07', 'Reparación de equipo', 'Servicio', 'Transferencia', 0, 0, 150, 150, 19.5, 0, 169.5, 'Crédito', '2025-07-07'],
    // 8. Empresa + Factura (no CCF)
    ['Empresa', 'Factura', 1005, 'Pagada', 'MINI SUPER LA ESQUINA', '', '', 'Mini Super La Esquina', '', '', '', 'San Salvador', 'SAN SALVADOR CENTRO', 'SOYAPANGO', 'Soyapango, Blvd. del Ejército', '2222-1007', 'super@esquina.com', '2025-06-08', 'Entrega a domicilio', 'Servicio', 'Vales', 0, 0, 30, 30, 3.9, 0, 33.9, 'Contado', ''],
    // 9. Empresa + CCF + Chivo Wallet
    ['Empresa', 'Crédito fiscal', 2003, 'Pagada', 'TECNOSV, S.A. DE C.V.', '', '', 'TecnoSV', '0614-150280-102-3', '345678-9', 'Actividades de diseño especializado', 'San Salvador', 'SAN SALVADOR CENTRO', 'SAN SALVADOR', 'San Salvador, Alameda Roosevelt', '2222-1008', 'facturacion@tecnosv.com', '2025-06-09', 'Mantenimiento de software', 'Servicio', 'Chivo Wallet', 0, 0, 300, 300, 39, 0, 339, 'Contado', ''],
    // 10. Consumidor Final + Ticket + Bitcoin
    ['Persona', 'Ticket', 1006, 'Pagada', 'Consumidor Final', '', '', '', '', '', '', 'San Salvador', 'SAN SALVADOR CENTRO', 'SAN SALVADOR', 'San Salvador', '', '', '2025-06-10', 'Venta de contado CF', 'Servicio', 'Bitcoin', 0, 0, 25, 25, 3.25, 0, 28.25, 'Contado', ''],
    // 11. Persona + Factura de exportación
    ['Persona', 'Factura de exportación', 3001, 'Pagada', 'Luis Hernandez', 'Pasaporte', 'A1234567', '', '', '', '', 'San Salvador', 'SAN SALVADOR CENTRO', 'SAN SALVADOR', 'Col. Escalón 20', '2222-1009', 'luis.hernandez@example.com', '2025-06-11', 'Servicio de consultoría al exterior', 'Servicio', 'Transferencia', 0, 0, 400, 400, 0, 0, 400, 'Contado', ''],
];

foreach ($filas as $i => $fila) {
    $n = $i + 2;
    foreach ($fila as $j => $valor) {
        $sheet->setCellValueByColumnAndRow($j + 1, $n, $valor);
    }
}

$writer = new Xlsx($spreadsheet);
$writer->save($destino);

$copiaDocs = __DIR__ . '/../public/docs/ventas-format-ejemplo.xlsx';
$writer->save($copiaDocs);

echo "Ejemplo: {$destino}\n";
echo "Copia: {$copiaDocs}\n";
echo 'Ventas: 10 (11 filas; correlativo 1003 tiene 2 ítems)' . PHP_EOL;
