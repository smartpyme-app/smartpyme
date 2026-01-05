<?php

namespace App\Services\Compras\Gastos;

use App\Models\Compras\Gastos\Gasto;
use App\Models\Compras\Proveedores\Proveedor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class GastoImportService
{
    /**
     * Importa un gasto desde JSON DTE
     *
     * @param array $jsonData Datos del JSON DTE
     * @return Gasto Gasto mapeado (sin guardar)
     */
    public function importarDesdeJson(array $jsonData): Gasto
    {
        // Inicializar el gasto con datos predeterminados
        $gasto = new Gasto();
        $gasto->forma_pago = 'Efectivo';
        $gasto->estado = 'Confirmado';
        $gasto->tipo_documento = 'Factura';
        $gasto->tipo = 'Gastos varios';
        $gasto->fecha = date('Y-m-d');
        $gasto->id_empresa = Auth::user()->id_empresa;
        $gasto->id_sucursal = Auth::user()->id_sucursal;
        $gasto->id_usuario = Auth::user()->id;

        // Mapear datos de identificación
        $this->mapearIdentificacion($gasto, $jsonData);

        // Mapear datos del proveedor
        if (isset($jsonData['emisor'])) {
            $proveedor = $this->buscarOCrearProveedor($jsonData['emisor']);
            if ($proveedor) {
                $gasto->id_proveedor = $proveedor->id;
            }
        }

        // Mapear conceptos e ítems
        if (isset($jsonData['cuerpoDocumento']) && !empty($jsonData['cuerpoDocumento'])) {
            $this->mapearConceptos($gasto, $jsonData['cuerpoDocumento']);
        }

        // Mapear totales financieros
        if (isset($jsonData['resumen'])) {
            $this->mapearResumen($gasto, $jsonData['resumen']);
        }

        // Guardar DTE completo para referencia
        $gasto->dte = $jsonData;

        return $gasto;
    }

    /**
     * Mapea los datos de identificación del DTE
     *
     * @param Gasto $gasto
     * @param array $jsonData
     * @return void
     */
    protected function mapearIdentificacion(Gasto $gasto, array $jsonData): void
    {
        if (!isset($jsonData['identificacion'])) {
            return;
        }

        $identificacion = $jsonData['identificacion'];

        if (isset($identificacion['fecEmi'])) {
            $gasto->fecha = $identificacion['fecEmi'];
        }

        if (isset($identificacion['numeroControl'])) {
            $gasto->referencia = substr($identificacion['numeroControl'], -10);
        }

        if (isset($identificacion['tipoDte'])) {
            $tiposDte = [
                '01' => 'Factura',
                '03' => 'Crédito fiscal',
                '05' => 'Nota de débito',
                '06' => 'Nota de crédito',
                '07' => 'Comprobante de retención',
                '11' => 'Factura de exportación',
                '14' => 'Sujeto excluido'
            ];

            $gasto->tipo_documento = $tiposDte[$identificacion['tipoDte']] ?? 'Factura';
        }

        $gasto->codigo_generacion = $identificacion['codigoGeneracion'] ?? null;
        $gasto->numero_control = $identificacion['numeroControl'] ?? null;
    }

    /**
     * Mapea los conceptos e ítems del DTE
     *
     * @param Gasto $gasto
     * @param array $cuerpoDocumento
     * @return void
     */
    protected function mapearConceptos(Gasto $gasto, array $cuerpoDocumento): void
    {
        // Usar la primera descripción como concepto principal
        $gasto->concepto = $cuerpoDocumento[0]['descripcion'];

        // Si hay más de un ítem, añadirlos como nota
        if (count($cuerpoDocumento) > 1) {
            $itemsAdicionales = [];
            for ($i = 1; $i < count($cuerpoDocumento); $i++) {
                $item = $cuerpoDocumento[$i];
                $itemsAdicionales[] = ($i + 1) . ". " . $item['descripcion'] .
                    " (" . $item['cantidad'] . " x $" . $item['precioUni'] . ")";
            }

            $gasto->nota = "Detalle adicional:\n" . implode("\n", $itemsAdicionales);
        }

        // Intentar determinar categoría basada en las descripciones
        $gasto->tipo = $this->determinarCategoria($cuerpoDocumento);
    }

    /**
     * Mapea el resumen financiero del DTE
     *
     * @param Gasto $gasto
     * @param array $resumen
     * @return void
     */
    protected function mapearResumen(Gasto $gasto, array $resumen): void
    {
        // Montos base
        if (isset($resumen['subTotal'])) {
            $gasto->sub_total = $resumen['subTotal'];
        } elseif (isset($resumen['totalGravada'])) {
            $gasto->sub_total = $resumen['totalGravada'];
        }

        // IVA
        if (isset($resumen['tributos']) && !empty($resumen['tributos'])) {
            foreach ($resumen['tributos'] as $tributo) {
                if ($tributo['codigo'] === '20') { // Código para IVA
                    $gasto->iva = $tributo['valor'];
                    break;
                }
            }
        }

        // Retención de renta
        if (isset($resumen['reteRenta']) && $resumen['reteRenta'] > 0) {
            $gasto->renta_retenida = $resumen['reteRenta'];
        }

        // Percepción
        if (isset($resumen['ivaPerci1']) && $resumen['ivaPerci1'] > 0) {
            $gasto->iva_percibido = $resumen['ivaPerci1'];
        }

        // Total
        if (isset($resumen['totalPagar'])) {
            $gasto->total = $resumen['totalPagar'];
        } elseif (isset($resumen['montoTotalOperacion'])) {
            $gasto->total = $resumen['montoTotalOperacion'];
        }

        // Forma de pago
        if (isset($resumen['pagos']) && !empty($resumen['pagos'])) {
            $this->mapearFormaPago($gasto, $resumen['pagos']);
        }

        // Condición de operación
        if (isset($resumen['condicionOperacion'])) {
            if ($resumen['condicionOperacion'] == 1) {
                $gasto->condicion = 'Contado';
                $gasto->estado = 'Confirmado';
            } elseif ($resumen['condicionOperacion'] == 2) {
                $gasto->condicion = 'Crédito';
                $gasto->estado = 'Pendiente';
            }
        }
    }

    /**
     * Mapea la forma de pago desde los datos del DTE
     *
     * @param Gasto $gasto
     * @param array $pagos
     * @return void
     */
    protected function mapearFormaPago(Gasto $gasto, array $pagos): void
    {
        $formaPagoCodigos = [
            '01' => 'Efectivo',
            '02' => 'Tarjeta de Crédito',
            '03' => 'Tarjeta de Débito',
            '04' => 'Cheque',
            '05' => 'Transferencia',
            '06' => 'Crédito',
            '07' => 'Tarjeta de regalo',
            '08' => 'Dinero electrónico',
            '99' => 'Otros'
        ];

        $pago = $pagos[0];
        $gasto->forma_pago = $formaPagoCodigos[$pago['codigo']] ?? 'Efectivo';

        // Manejo de crédito
        if ($pago['codigo'] === '06') {
            $gasto->estado = 'Pendiente';

            // Si hay plazo, calcular fecha de pago
            if (isset($pago['plazo'])) {
                $fechaPago = date('Y-m-d', strtotime($gasto->fecha . ' + ' . $pago['plazo'] . ' days'));
                $gasto->fecha_pago = $fechaPago;
            }
        }
    }

    /**
     * Busca o crea un proveedor basado en los datos del emisor del DTE
     *
     * @param array $emisorData
     * @return Proveedor|null
     */
    public function buscarOCrearProveedor(array $emisorData): ?Proveedor
    {
        if (!isset($emisorData['nit'])) {
            return null;
        }

        // Buscar por NIT
        $proveedor = Proveedor::where('nit', $emisorData['nit'])
            ->where('id_empresa', Auth::user()->id_empresa)
            ->first();

        if ($proveedor) {
            return $proveedor;
        }

        // Crear nuevo proveedor
        $proveedor = new Proveedor();
        $proveedor->tipo = 'Empresa';
        $proveedor->nombre_empresa = $emisorData['nombre'];
        $proveedor->nit = $emisorData['nit'];
        $proveedor->ncr = $emisorData['nrc'] ?? '';
        $proveedor->telefono = $emisorData['telefono'] ?? '';
        $proveedor->email = $emisorData['correo'] ?? '';

        // Manejar dirección
        if (isset($emisorData['direccion']) && isset($emisorData['direccion']['complemento'])) {
            $proveedor->direccion = $emisorData['direccion']['complemento'];
        } else {
            $proveedor->direccion = 'No especificada';
        }

        // Añadir ID de empresa y usuario actual
        $proveedor->id_empresa = Auth::user()->id_empresa;
        $proveedor->id_usuario = Auth::user()->id;

        $proveedor->save();

        Log::info("Proveedor creado desde importación DTE", [
            'proveedor_id' => $proveedor->id,
            'nit' => $proveedor->nit
        ]);

        return $proveedor;
    }

    /**
     * Determina la categoría del gasto basándose en las descripciones de los ítems
     *
     * @param array $items
     * @return string
     */
    public function determinarCategoria(array $items): string
    {
        // Palabras clave para cada categoría
        $categoriasKeywords = [
            'Alquiler' => ['alquiler', 'renta', 'arrendamiento', 'local'],
            'Combustible' => ['combustible', 'gasolina', 'diesel', 'gas'],
            'Costo de venta' => ['costo', 'venta', 'producto'],
            'Insumos' => ['insumos', 'suministros', 'papelería', 'oficina'],
            'Impuestos' => ['impuesto', 'iva', 'renta', 'fiscal', 'tributario'],
            'Gastos Administrativos' => ['administrativo', 'gestión', 'admin'],
            'Mantenimiento' => ['mantenimiento', 'reparación', 'arreglo'],
            'Marketing' => ['marketing', 'publicidad', 'promoción'],
            'Materia Prima' => ['materia prima', 'material', 'insumo'],
            'Servicios' => ['servicio', 'suscripción', 'internet', 'teléfono', 'electricidad', 'agua'],
            'Planilla' => ['planilla', 'salario', 'sueldo', 'nómina'],
            'Préstamos' => ['préstamo', 'crédito', 'financiamiento']
        ];

        // Concatenar todas las descripciones
        $descripcionCompleta = '';
        foreach ($items as $item) {
            if (isset($item['descripcion'])) {
                $descripcionCompleta .= ' ' . strtolower($item['descripcion']);
            }
        }

        // Buscar coincidencias con palabras clave
        foreach ($categoriasKeywords as $categoria => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($descripcionCompleta, strtolower($keyword)) !== false) {
                    return $categoria;
                }
            }
        }

        // Categoría predeterminada
        return 'Gastos varios';
    }
}

