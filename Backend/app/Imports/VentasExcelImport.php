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
use App\Models\Ventas\Impuesto as ImpuestoVenta;
use App\Models\Admin\Impuesto;
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

    public function collection(Collection $rows)
    {
        DB::beginTransaction();

        try {
            $validRows = 0;
            $ventasExitosas = 0;
            $ventasFallidas = 0;

            if (count($rows) > 0) {
                $primeraFila = $rows[0];
                $this->tipo_documento = $this->determinarTipoDocumento($primeraFila);
                Log::info("Tipo de documento determinado: " . $this->tipo_documento);
            }

            $ventasAgrupadas = [];

            foreach ($rows as $index => $row) {
                if ($this->esFilaVacia($row)) {
                    Log::info("Fila {$index} ignorada por estar vacía");
                    continue;
                }

                if (!$this->validarFilaRequeridos($row)) {
                    Log::info("Fila {$index} ignorada por faltar datos requeridos");
                    continue;
                }

                $validRows++;
                Log::info("Fila {$index} válida");

                $clienteKey = $this->generarClienteKey($row);
                Log::info("Cliente key: " . $clienteKey);

                if (!isset($ventasAgrupadas[$clienteKey])) {
                    $id_cliente = $this->buscarOCrearCliente($row);
                    $id_documento = $this->buscarDocumento($this->tipo_documento);

                    if (!$id_cliente || !$id_documento) {
                        Log::error("Error: ID de cliente o documento no válido. Cliente: {$id_cliente}, Documento: {$id_documento}");
                        continue;
                    }

                    $ventasAgrupadas[$clienteKey] = [
                        'cabecera' => $this->obtenerDatosCabecera($row, $id_cliente, $id_documento),
                        'detalles' => []
                    ];

                    Log::info("Nueva venta agrupada creada para: " . $clienteKey);
                }

                $ventasAgrupadas[$clienteKey]['detalles'][] = $this->obtenerDatosDetalle($row);
                Log::info("Detalle agregado a venta: " . $clienteKey);
            }

            Log::info("Total de filas válidas: " . $validRows);
            Log::info("Total de ventas agrupadas: " . count($ventasAgrupadas));

            foreach ($ventasAgrupadas as $clienteKey => $datos) {
                Log::info("Procesando venta para: " . $clienteKey);
                Log::info("Datos de cabecera: " . json_encode($datos['cabecera']));
                Log::info("Total de detalles: " . count($datos['detalles']));

                if ($this->procesarVenta($datos['cabecera'], $datos['detalles'])) {
                    $this->contador++;
                    $ventasExitosas++;
                    Log::info("Venta procesada exitosamente: " . $clienteKey);
                } else {
                    $ventasFallidas++;
                    Log::warning("No se pudo procesar la venta para: " . $clienteKey);
                }
            }

            Log::info("Total de ventas procesadas exitosamente: " . $ventasExitosas);
            Log::info("Total de ventas que no pudieron procesarse: " . $ventasFallidas);

            if (count($this->errores) > 0 && $ventasExitosas == 0) {
                Log::error("Errores encontrados y ninguna venta procesada: " . count($this->errores));
                Log::error(implode("\n", $this->errores));
                DB::rollback();
                throw new \Exception(implode("\n", $this->errores));
            }

            if ($this->contador == 0) {
                Log::warning("No se procesó ninguna venta");
                DB::rollback();
                throw new \Exception('No se encontraron ventas válidas para importar.');
            }

            DB::commit();
            Log::info("Importación completada: " . $this->contador . " ventas procesadas");
            return $this->contador;
        } catch (\Exception $e) {
            Log::error("Error en importación: " . $e->getMessage());
            Log::error($e->getTraceAsString());
            DB::rollback();
            throw $e;
        }
    }

    protected function esFilaVacia($row)
    {
        Log::info('Verificando fila: ' . json_encode($row));

        if (empty($row) || count($row) == 0) {
            Log::info('Fila vacía: array vacío');
            return true;
        }

        $camposRequeridos = ['nombre', 'descripcion', 'fecha'];
        foreach ($camposRequeridos as $campo) {
            if (!isset($row[$campo]) || (is_string($row[$campo]) && trim($row[$campo]) === '') || $row[$campo] === null) {
                Log::info("Fila vacía: sin $campo");
                return true;
            }
        }

        $todoVacio = true;
        foreach ($row as $campo => $valor) {
            if (!empty($valor) && $valor !== null && $valor !== '') {
                $todoVacio = false;
                break;
            }
        }

        if ($todoVacio) {
            Log::info('Fila vacía: todos los campos son vacíos');
            return true;
        }

        Log::info('Fila válida');
        return false;
    }

    protected function determinarTipoDocumento($fila)
    {
        $camposCredito = ['nit', 'nrc', 'cod_giro', 'nombre_comercial'];
        foreach ($camposCredito as $campo) {
            if (isset($fila[$campo])) {
                return 'credito_fiscal';
            }
        }
        return 'consumidor_final';
    }

    protected function validarFilaRequeridos($fila)
    {
        if ($this->esFilaVacia($fila)) {
            return false;
        }

        $requeridos = ['nombre', 'fecha', 'descripcion', 'total'];
        $requeridos = array_merge($requeridos, $this->tipo_documento == 'credito_fiscal' ? ['nit'] : []);

        if (!empty($fila['num_documento']) && $this->tipo_documento != 'credito_fiscal') {
            $requeridos[] = 'tipo_documento';
        }

        $faltantes = array_filter($requeridos, function($campo) use ($fila) {
            return !isset($fila[$campo]) || (is_string($fila[$campo]) && trim($fila[$campo]) === '') || $fila[$campo] === null;
        });

        if (!empty($faltantes)) {
            $this->errores[] = "Error: Faltan los campos obligatorios '" . implode("', '", $faltantes) . "' en una de las filas.";
            Log::warning("Fila inválida - Faltan campos: " . implode(", ", $faltantes) . " - Datos: " . json_encode($fila));
            return false;
        }

        return true;
    }

    protected function buscarOCrearCliente($fila)
    {
        try {
            $cliente = $this->buscarCliente($fila);

            if (!$cliente) {
                $cliente = $this->crearCliente($fila);
            }

            return $cliente->id;
        } catch (\Exception $e) {
            $clienteDefault = Cliente::where('nombre_completo', 'Consumidor Final')->first();
            return $clienteDefault ? $clienteDefault->id : null;
        }
    }

    protected function buscarCliente($fila)
    {
        if ($this->tipo_documento == 'credito_fiscal') {
            return Cliente::where('nit', $fila['nit'])->first();
        }

        $query = Cliente::query();
        if (!empty($fila['num_documento'])) {
            $query->where('dui', $fila['num_documento']);
        }
        return $query->first();
    }

    protected function crearCliente($fila)
    {
        $cliente = new Cliente();
        $departamento = $this->buscarDepartamento($fila['cod_departamento'] ?? null);
        $municipio = $this->buscarMunicipio($fila['cod_municipio'] ?? null);
        $distrito = $this->buscarDistrito($fila['cod_departamento'] ?? null, $fila['cod_municipio'] ?? null);

        $datosCliente = [
            'nombre' => $fila['nombre'] ?? 'Consumidor Final',
            'apellido' => $fila['apellido'] ?? '',
            'telefono' => $fila['telefono'] ?? '',
            'correo' => $fila['correo'] ?? '',
            'direccion' => $fila['direccion'] ?? '',
            'cod_departamento' => $departamento->cod ?? null,
            'departamento' => $departamento->nombre ?? null,
            'cod_municipio' => $municipio->cod ?? null,
            'municipio' => $municipio->nombre ?? null,
            'cod_distrito' => $distrito->cod ?? null,
            'distrito' => $distrito->nombre ?? null,
            'id_usuario' => Auth::id(),
            'tipo' => $this->tipo_documento == 'credito_fiscal' ? 'Empresa' : 'Persona',
            'id_empresa' => Auth::user()->id_empresa
        ];

        if ($this->tipo_documento == 'credito_fiscal') {
            $datosCliente = array_merge($datosCliente, [
                'nombre_empresa' => $fila['nombre_comercial'] ?? $fila['nombre'],
                'nit' => $fila['nit'],
                'ncr' => $fila['nrc'] ?? '',
                'giro' => $fila['cod_giro'] ?? '',
                'tipo_contribuyente' => 'Otro',
                'dui' => $fila['num_documento'] ?? ''
            ]);
        } else {
            $datosCliente = array_merge($datosCliente, [
                'tipo_documento' => $fila['tipo_documento'] ?? 'DUI',
                'dui' => $fila['num_documento'] ?? ''
            ]);
        }

        $cliente->fill($datosCliente);
        $cliente->save();

        return $cliente;
    }

    protected function buscarDepartamento($codigo)
    {
        return $codigo ? Departamento::where('id', $codigo)->first() : null;
    }

    protected function buscarMunicipio($codigo)
    {
        return $codigo ? Municipio::where('id', $codigo)->first() : null;
    }

    protected function buscarDistrito($departamento, $municipio)
    {
        return ($departamento && $municipio) ? Distrito::where('cod_departamento', $departamento)
            ->where('cod_municipio', $municipio)
            ->first() : null;
    }

    protected function buscarDocumento($tipo)
    {
        $documento = Documento::where('nombre', $tipo == 'credito_fiscal' ? 'Crédito Fiscal' : 'Factura')
            ->where('id_sucursal', Auth::user()->id_sucursal)
            ->first();
        return $documento ? $documento->id : null;
    }

    protected function generarClienteKey($row)
    {
        return $this->tipo_documento == 'credito_fiscal' ? 
            ($row['nit'] ?? '') . '-' . ($row['fecha'] ?? '') :
            ($row['nombre'] ?? '') . '-' . ($row['fecha'] ?? '');
    }

    protected function obtenerDatosCabecera($fila, $id_cliente, $id_documento)
    {
        $canal = Canal::where('id_empresa', Auth::user()->id_empresa)->first();
        $fecha = $this->convertirFechaExcel($fila['fecha']);

        $gravada = $this->extraerValorOCalcular($fila, 'gravada', 0);
        $subtotal = $this->extraerValorOCalcular($fila, 'subtotal', $gravada);
        $iva = $this->extraerValorOCalcular($fila, 'iva', $gravada * 0.13);
        $total = $this->extraerValorOCalcular($fila, 'total', $subtotal + $iva);

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
            'exenta' => $this->extraerValorOCalcular($fila, 'exenta', 0),
            'no_sujeta' => $this->extraerValorOCalcular($fila, 'no_sujeta', 0),
            'gravada' => $gravada,
            'cuenta_a_terceros' => 0,
            'iva' => $iva,
            'iva_retenido' => $this->extraerValorOCalcular($fila, 'iva_retenido', 0),
            'iva_percibido' => 0,
            'sub_total' => $subtotal,
            'total' => $total,
            'cobrar_impuestos' => $iva > 0 ? 1 : 0,
        ];

        $cabecera['fecha_pago'] = isset($fila['fecha_pago']) && !empty($fila['fecha_pago']) ?
            $this->convertirFechaExcel($fila['fecha_pago']) :
            ($cabecera['credito'] ? Carbon::parse($fecha)->addMonth()->format('Y-m-d') : null);

        $documento = Documento::find($id_documento);
        if ($documento) {
            $ultimoCorrelativo = Venta::where('id_documento', $id_documento)->max('correlativo');
            $cabecera['correlativo'] = $ultimoCorrelativo ? $ultimoCorrelativo + 1 : $documento->correlativo;
        }

        return $cabecera;
    }

    protected function convertirFechaExcel($fecha)
    {
        if (is_numeric($fecha)) {
            try {
                return Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($fecha))->format('Y-m-d');
            } catch (\Exception $e) {
                return date('Y-m-d');
            }
        }

        if (is_string($fecha) && preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $fecha, $matches)) {
            return "{$matches[3]}-{$matches[2]}-{$matches[1]}";
        }

        try {
            return Carbon::parse($fecha)->format('Y-m-d');
        } catch (\Exception $e) {
            return date('Y-m-d');
        }
    }

    protected function buscarOCrearProducto($fila)
    {
        $descripcion = $fila['descripcion'] ?? 'Sin descripción';
        Log::info('Buscando producto: ' . $descripcion);

        $producto = Producto::where('nombre', $descripcion)
            ->orWhere('descripcion', 'like', '%' . $descripcion . '%')
            ->first();

        if ($producto) {
            Log::info('Producto encontrado: ID ' . $producto->id);
            return $producto->id;
        }

        Log::warning('Producto no encontrado en el sistema: ' . $descripcion);
        return 0;
    }

    protected function actualizarInventario($venta, $detalle)
    {
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

    public function getErrores()
    {
        return $this->errores;
    }

    protected function obtenerDatosDetalle($fila)
    {
        Log::info('Procesando fila: ' . json_encode($fila));

        $idProducto = $this->buscarOCrearProducto($fila);

        $gravada = $this->extraerValorOCalcular($fila, 'gravada');
        $subtotal = $this->extraerValorOCalcular($fila, 'subtotal', $gravada);
        $iva = $this->extraerValorOCalcular($fila, 'iva', $subtotal * 0.13);
        $total = $this->extraerValorOCalcular($fila, 'total', $subtotal + $iva);

        $cantidad = 1;
        $precio = $gravada;

        if (isset($fila['precio']) && is_numeric($fila['precio']) && $fila['precio'] > 0) {
            $precio = $fila['precio'];
            if (isset($fila['cantidad']) && is_numeric($fila['cantidad']) && $fila['cantidad'] > 0) {
                $cantidad = $fila['cantidad'];
            } else if ($precio > 0) {
                $cantidad = $gravada / $precio;
            }
        }

        $producto = Producto::find($idProducto);
        $costo = $producto ? $producto->costo : 0;
        $total_costo = $cantidad * $costo;

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
            'gravada' => $gravada,
            'cuenta_a_terceros' => 0,
            'iva' => $iva,
            'id_vendedor' => Auth::id(),
            'tipo_item' => $fila['tipo_item'] ?? 'Producto',
        ];
    }

    protected function extraerValorOCalcular($fila, $campo, $valorPredeterminado = 0)
    {
        if (!isset($fila[$campo]) || empty($fila[$campo])) {
            return $valorPredeterminado;
        }

        $valor = $fila[$campo];

        if (is_numeric($valor)) {
            return (float)$valor;
        }

        if (is_string($valor) && substr($valor, 0, 1) === '=') {
            $formulas = [
                '/=N(\d+)/' => $valorPredeterminado,
                '/=N(\d+)\*13%/' => $valorPredeterminado,
                '/=N(\d+)\+P(\d+)/' => $valorPredeterminado,
                '/=I(\d+)/' => date('Y-m-d')
            ];

            foreach ($formulas as $patron => $resultado) {
                if (preg_match($patron, $valor)) {
                    return $resultado;
                }
            }
        }

        return is_numeric($valor) ? (float)$valor : $valorPredeterminado;
    }

    protected function procesarVenta($cabecera, $detalles)
    {
        try {
            $detallesValidos = [];
            $detallesInvalidos = [];

            foreach ($detalles as $detalle_data) {
                if (empty($detalle_data['id_producto'])) {
                    $detallesInvalidos[] = "Producto no encontrado: " . ($detalle_data['descripcion'] ?? 'Sin descripción');
                    continue;
                }
                $detallesValidos[] = $detalle_data;
            }

            if (empty($detallesValidos)) {
                $mensajeError = "Error: No se pudo procesar la venta porque no se encontraron productos válidos. ";
                $mensajeError .= "Productos no encontrados: " . implode(", ", $detallesInvalidos);
                $this->errores[] = $mensajeError;
                Log::warning($mensajeError);
                return false;
            }

            if (!empty($detallesInvalidos)) {
                Log::warning("Advertencia: Algunos productos no fueron encontrados y se omitieron: " . implode(", ", $detallesInvalidos));
            }

            $venta = new Venta();
            $venta->fill($cabecera);
            $venta->save();

            Log::info('Venta creada: ' . $venta->id);

            $this->procesarImpuestos($venta, $cabecera);

            foreach ($detallesValidos as $detalle_data) {
                $detalle = new Detalle();
                $detalle_data['id_venta'] = $venta->id;

                $producto = Producto::find($detalle_data['id_producto']);
                if ($producto) {
                    $detalle_data['costo'] = $producto->costo;
                    $detalle_data['total_costo'] = $detalle_data['costo'] * $detalle_data['cantidad'];
                }

                $detalle->fill($detalle_data);
                $detalle->save();

                if ($venta->cotizacion == 0) {
                    $this->actualizarInventario($venta, $detalle);
                }
            }

            $documento = Documento::find($venta->id_documento);
            if ($documento) {
                $documento->increment('correlativo');
            }

            return true;
        } catch (\Exception $e) {
            $this->errores[] = "Error al procesar venta: " . $e->getMessage();
            Log::error('Error al procesar venta: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return false;
        }
    }

    protected function procesarImpuestos($venta, $cabecera)
    {
        if (isset($cabecera['iva']) && is_numeric($cabecera['iva']) && $cabecera['iva'] > 0) {
            try {
                $iva = Impuesto::where('nombre', 'IVA')
                    ->where('id_empresa', Auth::user()->id_empresa)
                    ->first() ?? Impuesto::where('id_empresa', Auth::user()->id_empresa)->first();

                if ($iva) {
                    $impuesto = new ImpuestoVenta([
                        'id_impuesto' => $iva->id,
                        'id_venta' => $venta->id,
                        'monto' => $cabecera['iva']
                    ]);
                    $impuesto->save();

                    Log::info('Impuesto IVA agregado: ' . $cabecera['iva']);
                } else {
                    Log::warning('No se encontró configuración de impuestos para la empresa');
                }
            } catch (\Exception $e) {
                Log::error('Error al procesar impuestos: ' . $e->getMessage());
            }
        }
    }
}
