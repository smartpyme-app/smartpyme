<?php

namespace App\Imports;

use App\Models\Admin\Canal;
use App\Models\Admin\Documento;
use App\Models\Inventario\Categorias\Categoria;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Producto;
use App\Models\MH\Departamento;
use App\Models\MH\Distrito;
use App\Models\MH\Municipio;
use App\Models\Ventas\Clientes\Cliente;
use App\Models\Ventas\Detalle;
use App\Models\Ventas\Venta;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class VentasExcelImport implements ToCollection, WithHeadingRow
{
    protected $tipo_documento;
    protected $contador = 0;
    protected $errores = [];

    /**
     * @param Collection $rows
     */
    public function collection(Collection $rows)
    {
        DB::beginTransaction();

        //   try {
        // Determinar el tipo de documento por los encabezados
        if (count($rows) > 0) {
            $primeraFila = $rows[0];
            $this->tipo_documento = $this->determinarTipoDocumento($primeraFila);
        }



        // Agrupar filas por cliente y fecha
        $ventasAgrupadas = [];

        foreach ($rows as $index => $row) {
            // Validar datos mínimos requeridos
            if (!$this->validarFilaRequeridos($row)) {
                continue;
            }

            // Crear una clave única para agrupar filas de la misma venta
            // En este caso, agrupamos por cliente (identificado por nombre/NIT) y fecha
            $clienteKey = '';
            if ($this->tipo_documento == 'credito_fiscal') {
                $clienteKey = ($row['nit'] ?? '') . '-' . ($row['fecha'] ?? '');
            } else {
                $clienteKey = ($row['nombre'] ?? '') . '-' . ($row['fecha'] ?? '');
            }

            if (!isset($ventasAgrupadas[$clienteKey])) {
                // Buscar o crear cliente
                $id_cliente = $this->buscarOCrearCliente($row);

                // Buscar documento adecuado
                $id_documento = $this->buscarDocumento($this->tipo_documento);

                // Crear nueva entrada para esta venta
                $ventasAgrupadas[$clienteKey] = [
                    'cabecera' => $this->obtenerDatosCabecera($row, $id_cliente, $id_documento),
                    'detalles' => []
                ];
            }

            $ventasAgrupadas[$clienteKey]['detalles'][] = $this->obtenerDatosDetalle($row);
        }

        // Procesar cada venta agrupada
        foreach ($ventasAgrupadas as $clienteKey => $datos) {
            if ($this->procesarVenta($datos['cabecera'], $datos['detalles'])) {
                $this->contador++;
            }
        }

        // Si hay errores, hacer rollback
        // if (count($this->errores) > 0) {
        //     DB::rollback();
        //     throw new \Exception(implode("\n", $this->errores));
        // }

        DB::commit();
        return $this->contador;
        // } catch (\Exception $e) {
        //     DB::rollback();
        //     throw $e;
        // }
    }

    /**
     * Determinar el tipo de documento basado en los encabezados
     */
    protected function determinarTipoDocumento($fila)
    {
        // Si tiene campos específicos de crédito fiscal
        if (isset($fila['nit']) || isset($fila['nrc']) || isset($fila['cod_giro']) || isset($fila['nombre_comercial'])) {
            return 'credito_fiscal';
        }

        // Por defecto, consideramos que es consumidor final
        return 'consumidor_final';
    }

    /**
     * Validar que los campos requeridos existan
     */
    protected function validarFilaRequeridos($fila)
    {
        // Campos requeridos para ambos tipos
        $requeridos = ['nombre', 'fecha', 'descripcion', 'total'];

        // Campos adicionales según el tipo
        if ($this->tipo_documento == 'credito_fiscal') {
            $requeridos[] = 'nit';
        } else {
            // Para consumidor final, el tipo_documento no es obligatorio si es anónimo
            if (!empty($fila['num_documento'])) {
                $requeridos[] = 'tipo_documento';
            }
        }

        foreach ($requeridos as $campo) {
            if (!isset($fila[$campo]) || (is_string($fila[$campo]) && trim($fila[$campo]) === '')) {
                $this->errores[] = "Error: Falta el campo obligatorio '$campo' en una de las filas.";
                return false;
            }
        }

        return true;
    }

    /**
     * Buscar o crear cliente según los datos del Excel
     */
    protected function buscarOCrearCliente($fila)
    {
        //   try {
        // Intentar buscar al cliente primero
        $cliente = null;

        if ($this->tipo_documento == 'credito_fiscal') {
            // Buscar por NIT para crédito fiscal
            $cliente = Cliente::where('nit', $fila['nit'])->first();
        } else {
            // Para consumidor final, buscar por nombre y documento si existe
            $query = Cliente::query();

            // if (!empty($fila['nombre'])) {
            //     $query->where('nombre_completo', $fila['nombre']);
            // }

            if (!empty($fila['num_documento'])) {
                $query->where('dui', $fila['num_documento']);
            }

            $cliente = $query->first();
        }

        // Si no se encuentra, crear nuevo cliente
        if (!$cliente) {
            $cliente = new Cliente();
            $departamento = $this->buscarDepartamento($fila['cod_departamento'] ?? null);
            $municipio = $this->buscarMunicipio($fila['cod_municipio'] ?? null);
            $distrito = $this->buscarDistrito($fila['cod_departamento'], $fila['cod_municipio']);

            $cliente->nombre = $fila['nombre'] ?? 'Consumidor Final';
            $cliente->apellido = $fila['apellido'] ?? '';
            $cliente->telefono = $fila['telefono'] ?? '';
            $cliente->correo = $fila['correo'] ?? '';
            $cliente->direccion = $fila['direccion'] ?? '';
            $cliente->cod_departamento =   $departamento->cod ?? null;
            $cliente->departamento =  $departamento->nombre ?? null;
            $cliente->cod_municipio =  $municipio->cod ?? null;
            $cliente->municipio =  $municipio->nombre ?? null;
            $cliente->cod_distrito = $distrito->cod ?? null;
            $cliente->distrito =  $distrito->nombre ?? null;

            $cliente->id_usuario = Auth::id();
            $cliente->tipo = 'Persona';
            $cliente->id_empresa = Auth::user()->id_empresa;

            // Datos específicos según tipo
            if ($this->tipo_documento == 'credito_fiscal') {
                $cliente->tipo = 'Empresa';
                $cliente->nombre_empresa = $fila['nombre_comercial'] ?? $fila['nombre'];
                $cliente->nit = $fila['nit'];
                $cliente->ncr = $fila['nrc'] ?? '';
                $cliente->giro = $fila['cod_giro'] ?? '';
                $cliente->tipo_contribuyente = 'Otro';
                $cliente->dui = $fila['num_documento'] ?? '';
            } else {
                $cliente->tipo_documento = $fila['tipo_documento'] ?? 'DUI';
                $cliente->dui = $fila['num_documento'] ?? '';
            }

            $cliente->save();
        }

        return $cliente->id;

        // } catch (\Exception $e) {
        //     // Si hay error, usar cliente por defecto (consumidor final)
        //     $clienteDefault = Cliente::where('nombre_completo', 'Consumidor Final')->first();
        //     return $clienteDefault ? $clienteDefault->id : null;
        // }
    }

    /**
     * Buscar departamento por código
     */
    protected function buscarDepartamento($codigo)
    {
        if (!$codigo) return null;

        $departamento = Departamento::where('id', $codigo)->first();
        return $departamento ?? null;
    }

    /**
     * Buscar municipio por código
     */
    protected function buscarMunicipio($codigo)
    {
        if (!$codigo) return null;

        $municipio = Municipio::where('id', $codigo)->first();
        return $municipio ?? null;
    }

    /**
     * Buscar distrito por código
     */
    protected function buscarDistrito($departamento, $municipio)
    {
        if (!$departamento || !$municipio) return null;

        $distrito = Distrito::where('cod_departamento', $departamento)
            ->where('cod_municipio', $municipio)
            ->first();
        return $distrito ?? null;
    }

    /**
     * Buscar el documento adecuado según el tipo
     */
    protected function buscarDocumento($tipo)
    {
        $nombreDocumento = ($tipo == 'credito_fiscal') ? 'Crédito Fiscal' : 'Factura';

        $documento = Documento::where('nombre', $nombreDocumento)
            ->where('id_sucursal', Auth::user()->id_sucursal)
            ->first();

        return $documento ? $documento->id : null;
    }




    protected function obtenerDatosCabecera($fila, $id_cliente, $id_documento)
    {
        $canal = Canal::where('id_empresa', Auth::user()->id_empresa)->first();

        $fecha = $this->convertirFechaExcel($fila['fecha']);

        $cabecera = [
            'fecha' => $fecha,
            'estado' => 'Pagada',
            'forma_pago' => $fila['forma_pago'] ?? 'Tarjeta de crédito/débito',
            'condicion' => $fila['condicion'] ?? 'Contado',
            'credito' => (strtolower($fila['condicion'] ?? 'Contado') == 'crédito' || strtolower($fila['condicion'] ?? 'Contado') == 'credito') ? 1 : 0,
            'id_cliente' => $id_cliente,
            'id_documento' => $id_documento,
            'id_canal' => $canal->id,
            'id_sucursal' => Auth::user()->id_sucursal,
            'id_bodega' => Auth::user()->id_bodega,
            'id_vendedor' => Auth::id(),
            'id_usuario' => Auth::id(),
            'id_empresa' => Auth::user()->id_empresa,
            'observaciones' => '',
            'cotizacion' => 0,
            'exenta' => $fila['exenta'] ?? 0,
            'no_sujeta' => $fila['no_sujeta'] ?? 0,
            'gravada' => $fila['gravada'] ?? 0,
            'cuenta_a_terceros' => 0,
            'iva' => $fila['iva'] ?? 0,
            'iva_retenido' => $fila['iva_retenido'] ?? 0,
            'iva_percibido' => 0,
            'sub_total' => $fila['subtotal'] ?? 0,
            'total' => $fila['total'] ?? 0,
            'cobrar_impuestos' => isset($fila['iva']) && $fila['iva'] > 0 ? 1 : 0,
        ];

        // Procesar fecha de pago
        if (isset($fila['fecha_pago']) && !empty($fila['fecha_pago'])) {
            $cabecera['fecha_pago'] = $this->convertirFechaExcel($fila['fecha_pago']);
        } else if ($cabecera['credito']) {
            $cabecera['fecha_pago'] = Carbon::parse($fecha)->addMonth()->format('Y-m-d');
        }

        // Manejo de correlativo (este bloque ya lo tienes correcto)
        $documento = Documento::find($id_documento);
        if ($documento) {
            $ultimoCorrelativo = Venta::where('id_documento', $id_documento)
                ->max('correlativo');
            $cabecera['correlativo'] = $ultimoCorrelativo ? $ultimoCorrelativo + 1 : $documento->correlativo;
        }

        return $cabecera;
    }


    protected function convertirFechaExcel($fecha)
    {
        // Si es un número (Excel almacena fechas como números), convertirlo
        if (is_numeric($fecha)) {
            // Excel almacena fechas como días desde el 1/1/1900 (o 1/1/1904 en Mac)
            // PHP puede convertir esto usando DateTime
            try {

                $dateTime = Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($fecha));
                return $dateTime->format('Y-m-d');
            } catch (\Exception $e) {
                // Si falla, intentar parsear directamente
                return date('Y-m-d');
            }
        }

        // Si es una cadena con formato dd/mm/yyyy
        if (is_string($fecha) && preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $fecha, $matches)) {
            return "{$matches[3]}-{$matches[2]}-{$matches[1]}"; // Convertir a Y-m-d
        }

        // Intentar con Carbon para otros formatos
        try {
            return Carbon::parse($fecha)->format('Y-m-d');
        } catch (\Exception $e) {
            // Si todo falla, devolver la fecha actual
            return date('Y-m-d');
        }
    }


    protected function formatearFecha($fecha)
    {
        return $this->convertirFechaExcel($fecha);
    }

    /**
     * Extraer datos de detalle de una fila
     */
    protected function obtenerDatosDetalle($fila)
    {

        $idProducto = $this->buscarOCrearProducto($fila);


        $cantidad = 1; // Por defecto
        $precio = $fila['subtotal'] ?? 0; // Si no hay desglose, usar el total
        $total = $fila['total'] ?? 0;

        // Si hay información de precio unitario, calcular
        if (isset($fila['precio']) && is_numeric($fila['precio']) && $fila['precio'] > 0) {
            $precio = $fila['precio'];
            // Si hay información de cantidad, usarla
            if (isset($fila['cantidad']) && is_numeric($fila['cantidad']) && $fila['cantidad'] > 0) {
                $cantidad = $fila['cantidad'];
                $total = $cantidad * $precio;
            } else {
                // Calcular cantidad basada en total y precio
                if ($precio > 0) {
                    $cantidad = $total / $precio;
                }
            }
        }

        $producto = Producto::find($idProducto);
        if ($producto) {
            $costo = $producto->costo;
            $total_costo = $cantidad * $costo;
        } else {
            $costo = 0;
            $total_costo = 0;
        }

        return [
            'id_producto' => $idProducto,
            'descripcion' => $fila['descripcion'] ?? '',
            'cantidad' => $cantidad,
            'precio' => $precio,
            'costo' => $costo,
            'descuento' => 0,
            'total' => $total,
            'total_costo' => $total_costo,
            'exenta' => $fila['exenta'] ?? 0,
            'no_sujeta' => $fila['no_sujeta'] ?? 0,
            'gravada' => $fila['gravada'] ?? $total,
            'cuenta_a_terceros' => 0,
            'iva' => $fila['iva'] ?? 0,
            'id_vendedor' => Auth::id(),
            'tipo_item' => $fila['tipo_item'] ?? 'Producto',
        ];
    }

    /**
     * Buscar o crear producto según la descripción
     */
    protected function buscarOCrearProducto($fila)
    {
        // Log::info('Buscando producto: ' . $fila['descripcion']);
        //imprimir todo el array
        // Log::info($fila);
        $producto = Producto::where('nombre', $fila['descripcion'])
            ->orWhere('descripcion', 'like', '%' . $fila['descripcion'] . '%')
            ->first();

        if ($producto) {
            return $producto->id;
        }else{
            return 0;
        }

        //crear categoria
        $categoria = new Categoria();
        $categoria->nombre = $fila['tipo_item'];
        $categoria->descripcion = $fila['tipo_item'];
        $categoria->enable = '1';
        $categoria->id_empresa = Auth::user()->id_empresa;
        $categoria->save();

        $producto = new Producto();
        $producto->nombre = $fila['descripcion'];
        $producto->costo = $fila['gravada'] ?? 0;
        $producto->precio = $fila['total'] ?? 0;
        $producto->descripcion = $fila['descripcion'];
        $producto->id_categoria = $categoria->id;
        $producto->tipo = $fila['tipo_item'] ?? 'Producto';
        $producto->id_empresa = Auth::user()->id_empresa;
        $producto->enable = '1';

        $producto->save();

        return $producto->id;
    }

    /**
     * Procesar una venta completa
     */
    protected function procesarVenta($cabecera, $detalles)
    {
        // try {
        // Verificar si ya existe esta venta
        // if (isset($cabecera['correlativo'])) {
        //     $existe = Venta::where('correlativo', $cabecera['correlativo'])
        //         ->where('id_sucursal', $cabecera['id_sucursal'])
        //         ->where('id_documento', $cabecera['id_documento'])
        //         ->exists();

        //     if ($existe) {
        //         $this->errores[] = "Error: Ya existe una venta con el correlativo {$cabecera['correlativo']} en la sucursal y documento seleccionados.";
        //         return false;
        //     }
        // }

        if (isset($cabecera['correlativo'])) {
            $correlativoOriginal = $cabecera['correlativo'];
            $correlativoNuevo = $correlativoOriginal;
            $contador = 0;

            // Intentar hasta 10 veces encontrar un correlativo único
            while ($contador < 10) {
                $existe = Venta::where('correlativo', $correlativoNuevo)
                    ->where('id_sucursal', $cabecera['id_sucursal'])
                    ->where('id_documento', $cabecera['id_documento'])
                    ->exists();

                if (!$existe) {
                    // ¡Encontramos un correlativo único!
                    $cabecera['correlativo'] = $correlativoNuevo;
                    break;
                }

                // Incrementar el correlativo y seguir intentando
                $correlativoNuevo++;
                $contador++;
            }

            // Si después de 10 intentos seguimos sin éxito, generar uno completamente nuevo
            if ($contador >= 10) {
                // Obtener el máximo correlativo y sumar uno
                $maxCorrelativo = Venta::where('id_documento', $cabecera['id_documento'])
                    ->where('id_sucursal', $cabecera['id_sucursal'])
                    ->max('correlativo');

                $cabecera['correlativo'] = $maxCorrelativo ? $maxCorrelativo + 1 : 1;
            }
        }

        // Crear la venta
        $venta = new Venta();
        $venta->fill($cabecera);
        $venta->save();

        // Crear los detalles
        foreach ($detalles as $detalle_data) {
            // Si no hay ID de producto, continuar con el siguiente
            // if (empty($detalle_data['id_producto'])) {
            //     Log::info('No se encontró el producto: ' . $detalle_data['descripcion']);
            //     Log::info($detalle_data);
            //     continue;
            // }

            $detalle = new Detalle();
            $detalle_data['id_venta'] = $venta->id;

            // Obtener costo del producto
            $producto = Producto::find($detalle_data['id_producto']);
            if ($producto) {
                $detalle_data['costo'] = $producto->costo;
                $detalle_data['total_costo'] = $detalle_data['costo'] * $detalle_data['cantidad'];
            }

            $detalle->fill($detalle_data);
            $detalle->save();

            // Actualizar inventario si no es cotización
            if ($venta->cotizacion == 0) {
                $this->actualizarInventario($venta, $detalle);
            }
        }

        // Incrementar el correlativo del documento
        $documento = Documento::find($venta->id_documento);
        if ($documento) {
            $documento->increment('correlativo');
        }

        return true;
        // } catch (\Exception $e) {
        //     $this->errores[] = "Error al procesar venta: " . $e->getMessage();
        //     return false;
        // }
    }


    protected function actualizarInventario($venta, $detalle)
    {
        //solamente su es producto si es servicio no se actualiza
        if ($detalle->tipo_item == 'Producto') {
            $inventario = Inventario::where('id_producto', $detalle->id_producto)
                ->where('id_bodega', $venta->id_bodega)
                ->first();

            if ($inventario) {
                $inventario->stock -= $detalle->cantidad;
                $inventario->save();
                $inventario->kardex($venta, $detalle->cantidad, $detalle->precio);
            }
        }
    }


    public function getContador()
    {
        return $this->contador;
    }

    /**
     * Obtener errores
     */
    public function getErrores()
    {
        return $this->errores;
    }
}
