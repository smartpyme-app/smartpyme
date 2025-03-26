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

    /**
     * @param Collection $rows
     */
    public function collection(Collection $rows)
    {
        DB::beginTransaction();

        try {
            // Inicializar contador para el log
            $validRows = 0;
            $ventasExitosas = 0;
            $ventasFallidas = 0;

            // Determinar el tipo de documento por los encabezados
            if (count($rows) > 0) {
                $primeraFila = $rows[0];
                $this->tipo_documento = $this->determinarTipoDocumento($primeraFila);
                Log::info("Tipo de documento determinado: " . $this->tipo_documento);
            }

            // Agrupar filas por cliente y fecha
            $ventasAgrupadas = [];

            foreach ($rows as $index => $row) {
                // AÑADIDO: Saltar filas vacías
                if ($this->esFilaVacia($row)) {
                    Log::info("Fila {$index} ignorada por estar vacía");
                    continue;
                }

                // Validar datos mínimos requeridos
                if (!$this->validarFilaRequeridos($row)) {
                    Log::info("Fila {$index} ignorada por faltar datos requeridos");
                    continue;
                }

                $validRows++;
                Log::info("Fila {$index} válida");

                // Crear una clave única para agrupar filas de la misma venta
                $clienteKey = '';
                if ($this->tipo_documento == 'credito_fiscal') {
                    $clienteKey = ($row['nit'] ?? '') . '-' . ($row['fecha'] ?? '');
                } else {
                    $clienteKey = ($row['nombre'] ?? '') . '-' . ($row['fecha'] ?? '');
                }

                Log::info("Cliente key: " . $clienteKey);

                if (!isset($ventasAgrupadas[$clienteKey])) {
                    // Buscar o crear cliente
                    $id_cliente = $this->buscarOCrearCliente($row);
                    Log::info("Cliente ID: " . $id_cliente);

                    // Buscar documento adecuado
                    $id_documento = $this->buscarDocumento($this->tipo_documento);
                    Log::info("Documento ID: " . $id_documento);

                    if (!$id_cliente || !$id_documento) {
                        Log::error("Error: ID de cliente o documento no válido. Cliente: {$id_cliente}, Documento: {$id_documento}");
                        continue;
                    }

                    // Crear nueva entrada para esta venta
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

            // Procesar cada venta agrupada
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
                    // Continuamos con la siguiente venta en lugar de detener el proceso
                }
            }

            Log::info("Total de ventas procesadas exitosamente: " . $ventasExitosas);
            Log::info("Total de ventas que no pudieron procesarse: " . $ventasFallidas);

            // Si hay errores pero se procesaron algunas ventas, NO hacer rollback
            if (count($this->errores) > 0 && $ventasExitosas == 0) {
                Log::error("Errores encontrados y ninguna venta procesada: " . count($this->errores));
                Log::error(implode("\n", $this->errores));
                DB::rollback();
                throw new \Exception(implode("\n", $this->errores));
            } else if (count($this->errores) > 0) {
                // Hay errores pero algunas ventas se procesaron correctamente
                Log::warning("Se encontraron " . count($this->errores) . " errores, pero se procesaron " . $ventasExitosas . " ventas correctamente.");
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
        // Log para depuración
        Log::info('Verificando fila: ' . json_encode($row));

        // Si la fila está completamente vacía (es un array vacío)
        if (empty($row) || count($row) == 0) {
            Log::info('Fila vacía: array vacío');
            return true;
        }

        // Si 'nombre' no existe o está vacío, considerar la fila como vacía
        if (!isset($row['nombre']) || (is_string($row['nombre']) && trim($row['nombre']) === '') || $row['nombre'] === null) {
            Log::info('Fila vacía: sin nombre');
            return true;
        }

        // Si 'descripcion' no existe o está vacío, considerar la fila como vacía
        if (!isset($row['descripcion']) || (is_string($row['descripcion']) && trim($row['descripcion']) === '') || $row['descripcion'] === null) {
            Log::info('Fila vacía: sin descripción');
            return true;
        }

        // Si 'fecha' no existe o está vacío, considerar la fila como vacía
        if (!isset($row['fecha']) || empty($row['fecha'])) {
            Log::info('Fila vacía: sin fecha');
            return true;
        }

        // Verificar si todos los valores están vacíos o son nulos
        $todoVacio = true;
        foreach ($row as $campo => $valor) {
            // Si al menos un campo tiene un valor no vacío, la fila no está vacía
            if (!empty($valor) && $valor !== null && $valor !== '') {
                $todoVacio = false;
                break;
            }
        }

        if ($todoVacio) {
            Log::info('Fila vacía: todos los campos son vacíos');
            return true;
        }

        // Si llegamos aquí, la fila tiene datos válidos
        Log::info('Fila válida');
        return false;
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
        // Comprobación más estricta para evitar procesar filas vacías
        if ($this->esFilaVacia($fila)) {
            // No agregamos error porque ya sabemos que es una fila vacía
            return false;
        }

        // Campos requeridos para ambos tipos
        $requeridos = ['nombre', 'fecha', 'descripcion', 'total'];
        $faltantes = [];

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
            if (!isset($fila[$campo]) || (is_string($fila[$campo]) && trim($fila[$campo]) === '') || $fila[$campo] === null) {
                $faltantes[] = $campo;
            }
        }

        if (!empty($faltantes)) {
            $this->errores[] = "Error: Faltan los campos obligatorios '" . implode("', '", $faltantes) . "' en una de las filas.";
            Log::warning("Fila inválida - Faltan campos: " . implode(", ", $faltantes) . " - Datos: " . json_encode($fila));
            return false;
        }

        return true;
    }

    /**
     * Buscar o crear cliente según los datos del Excel
     */
    protected function buscarOCrearCliente($fila)
    {
        try {
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
        } catch (\Exception $e) {
            // Si hay error, usar cliente por defecto (consumidor final)
            $clienteDefault = Cliente::where('nombre_completo', 'Consumidor Final')->first();
            return $clienteDefault ? $clienteDefault->id : null;
        }
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

        // Extraer valores numéricos o calcular si son fórmulas
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

        // Procesar fecha de pago
        if (isset($fila['fecha_pago']) && !empty($fila['fecha_pago'])) {
            $cabecera['fecha_pago'] = $this->convertirFechaExcel($fila['fecha_pago']);
        } else if ($cabecera['credito']) {
            $cabecera['fecha_pago'] = Carbon::parse($fecha)->addMonth()->format('Y-m-d');
        }

        // Manejo de correlativo
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
        return 0; // Retornar 0 indica que no se encontró el producto
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


    /**
     * Extraer datos de detalle de una fila, manejando fórmulas de Excel
     */
    protected function obtenerDatosDetalle($fila)
    {
        Log::info('Procesando fila: ' . json_encode($fila));

        $idProducto = $this->buscarOCrearProducto($fila);

        // Manejar las fórmulas de Excel para gravada, subtotal, iva y total
        $gravada = $this->extraerValorOCalcular($fila, 'gravada');
        $subtotal = $this->extraerValorOCalcular($fila, 'subtotal', $gravada); // Si subtotal es una fórmula, usar gravada como valor base
        $iva = $this->extraerValorOCalcular($fila, 'iva', $subtotal * 0.13); // Si iva es una fórmula, calcularlo como subtotal * 0.13
        $total = $this->extraerValorOCalcular($fila, 'total', $subtotal + $iva); // Si total es una fórmula, calcularlo como subtotal + iva

        $cantidad = 1; // Por defecto
        $precio = $gravada; // Usamos el valor de gravada como precio base

        // Si hay información de precio unitario, calcular
        if (isset($fila['precio']) && is_numeric($fila['precio']) && $fila['precio'] > 0) {
            $precio = $fila['precio'];
            // Si hay información de cantidad, usarla
            if (isset($fila['cantidad']) && is_numeric($fila['cantidad']) && $fila['cantidad'] > 0) {
                $cantidad = $fila['cantidad'];
            } else {
                // Calcular cantidad basada en gravada y precio
                if ($precio > 0) {
                    $cantidad = $gravada / $precio;
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
            'gravada' => $gravada,
            'cuenta_a_terceros' => 0,
            'iva' => $iva,
            'id_vendedor' => Auth::id(),
            'tipo_item' => $fila['tipo_item'] ?? 'Producto',
        ];
    }

    /**
     * Extraer valor numérico de un campo, o calcular si es una fórmula
     */
    protected function extraerValorOCalcular($fila, $campo, $valorPredeterminado = 0)
    {
        // Si el campo no existe o está vacío
        if (!isset($fila[$campo]) || empty($fila[$campo])) {
            return $valorPredeterminado;
        }

        $valor = $fila[$campo];

        // Si ya es un número, devolverlo directamente
        if (is_numeric($valor)) {
            return (float)$valor;
        }

        // Si es una cadena que parece una fórmula
        if (is_string($valor) && substr($valor, 0, 1) === '=') {
            // Las fórmulas más comunes
            if (preg_match('/=N(\d+)/', $valor, $matches)) {
                // Fórmula tipo "=N2" (referencia a subtotal)
                // Usar el valor predeterminado proporcionado
                return $valorPredeterminado;
            }

            if (preg_match('/=N(\d+)\*13%/', $valor, $matches)) {
                // Fórmula tipo "=N2*13%" (cálculo de IVA)
                // Usar el valor predeterminado que debería ser subtotal * 0.13
                return $valorPredeterminado;
            }

            if (preg_match('/=N(\d+)\+P(\d+)/', $valor, $matches)) {
                // Fórmula tipo "=N2+P2" (suma subtotal + iva)
                // Usar el valor predeterminado que debería ser subtotal + iva
                return $valorPredeterminado;
            }

            if (preg_match('/=I(\d+)/', $valor, $matches)) {
                // Fórmula tipo "=I2" (referencia a fecha)
                // Duplicar la fecha actual
                return date('Y-m-d');
            }
        }

        // Para cualquier otro caso, intentar convertir a número o devolver 0
        return is_numeric($valor) ? (float)$valor : $valorPredeterminado;
    }

    protected function procesarVenta($cabecera, $detalles)
    {
        try {
            // Verificar si hay detalles válidos
            $detallesValidos = [];
            $detallesInvalidos = [];

            foreach ($detalles as $detalle_data) {
                // Si no hay ID de producto, registrar como inválido
                if (empty($detalle_data['id_producto'])) {
                    $detallesInvalidos[] = "Producto no encontrado: " . ($detalle_data['descripcion'] ?? 'Sin descripción');
                    continue;
                }

                // Este detalle es válido
                $detallesValidos[] = $detalle_data;
            }

            // Si no hay detalles válidos, no procesar la venta
            if (empty($detallesValidos)) {
                $mensajeError = "Error: No se pudo procesar la venta porque no se encontraron productos válidos. ";
                $mensajeError .= "Productos no encontrados: " . implode(", ", $detallesInvalidos);
                $this->errores[] = $mensajeError;
                Log::warning($mensajeError);
                return false;
            }

            // Si hay algunos detalles inválidos, registrar advertencia pero continuar con los válidos
            if (!empty($detallesInvalidos)) {
                Log::warning("Advertencia: Algunos productos no fueron encontrados y se omitieron: " . implode(", ", $detallesInvalidos));
            }

            // Continuar con el resto del código para crear la venta con los detalles válidos
            // Manejo de correlativos duplicados...

            // Crear la venta
            $venta = new Venta();
            $venta->fill($cabecera);
            $venta->save();

            Log::info('Venta creada: ' . $venta->id);

            // Manejar impuestos
            $this->procesarImpuestos($venta, $cabecera);

            // Crear los detalles (solo los válidos)
            foreach ($detallesValidos as $detalle_data) {
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
        } catch (\Exception $e) {
            $this->errores[] = "Error al procesar venta: " . $e->getMessage();
            Log::error('Error al procesar venta: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return false;
        }
    }

    /**
     * Procesar impuestos de la venta
     */
    protected function procesarImpuestos($venta, $cabecera)
    {
        // Verificar si hay valor de IVA
        if (isset($cabecera['iva']) && is_numeric($cabecera['iva']) && $cabecera['iva'] > 0) {
            try {
                // Buscar impuesto de IVA en el sistema
                $iva = Impuesto::where('nombre', 'IVA')
                    ->where('id_empresa', Auth::user()->id_empresa)
                    ->first();

                // Si no encuentra IVA por nombre, buscar el primer impuesto
                if (!$iva) {
                    $iva = Impuesto::where('id_empresa', Auth::user()->id_empresa)->first();
                }

                // Si existe un impuesto configurado, crear registro de impuesto
                if ($iva) {
                    $impuesto = new ImpuestoVenta();
                    $impuesto->id_impuesto = $iva->id;
                    $impuesto->id_venta = $venta->id;
                    $impuesto->monto = $cabecera['iva'];
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
