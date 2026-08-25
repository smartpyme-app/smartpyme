<?php

namespace App\Imports;

use App\Models\Admin\Canal;
use App\Models\Admin\Documento;
use App\Models\Inventario\Producto;
use App\Models\MH\ActividadEconomica;
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
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeSheet;
use Maatwebsite\Excel\Events\AfterSheet;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Importación de ventas desde Excel.
 *
 * Dos plantillas con columnas distintas:
 *
 * — CONSUMIDOR FINAL: nombre, tipo_documento, num_documento, departamento, municipio, distrito,
 *   direccion, telefono, correo, fecha, tipo_documento_venta, correlativo, descripcion, forma_pago,
 *   exenta, gravada, subtotal, iva, iva_retenido, total, condicion, fecha_pago.
 *   tipo_documento: DUI | NIT | Pasaporte | Carnet de residente | Otro.
 *   tipo_documento_venta: Factura | Ticket | Crédito fiscal | Factura de exportación.
 *   condicion: Contado | Crédito.
 *
 * — CRÉDITO FISCAL: nombre_comercial, nombre, NIT, NRC, giro, departamento, municipio, distrito,
 *   direccion, telefono, correo, fecha, descripcion, tipo_item, forma_pago, no_sujeta, exenta, gravada,
 *   subtotal, iva, iva_retenido, total, condicion, fecha_pago.
 *   tipo_documento_venta y condicion con las mismas opciones que arriba.
 *
 * Departamento, municipio y distrito se resuelven por nombre (LOWER) a códigos MH.
 * El tipo de plantilla se detecta por fila: si tiene NIT con valor → crédito fiscal; si no → consumidor final.
 */
class VentasExcelImport implements ToCollection, WithHeadingRow, WithEvents
{
    protected $tipo_documento;
    protected $contador = 0;
    protected $errores = [];
    protected $productos_faltantes = [];
    protected $importar_hoja = true; 
    protected $primera_hoja_procesada = false; 

    public function registerEvents(): array
    {
        return [
            // Antes de procesar cada hoja, verificar si es la primera
            BeforeSheet::class => function (BeforeSheet $event) {
                if ($this->primera_hoja_procesada) {
                    // Si ya procesamos la primera hoja, ignorar esta
                    $this->importar_hoja = false;
                    Log::info('Ignorando hoja: ' . $event->getSheet()->getTitle());
                } else {
                    // Esta es la primera hoja, procesarla
                    $this->importar_hoja = true;
                    Log::info('Procesando primera hoja: ' . $event->getSheet()->getTitle());
                }
            },

            // Después de procesar cada hoja, marcar que ya procesamos la primera
            AfterSheet::class => function (AfterSheet $event) {
                if ($this->importar_hoja) {
                    $this->primera_hoja_procesada = true;
                    Log::info('Primera hoja procesada correctamente');
                }
            },
        ];
    }

    public function collection(Collection $rows)
    {
        // Si no es la primera hoja, simplemente retornar sin procesar
        if (!$this->importar_hoja) {
            Log::info('Omitiendo proceso de hoja secundaria');
            return;
        }

        DB::beginTransaction();

        try {
            $validRows = 0;
            $ventasExitosas = 0;
            $ventasFallidas = 0;

            // Si no hay filas, lanzar excepción
            if (count($rows) == 0) {
                throw new \Exception('El archivo no contiene datos para importar.');
            }

            $ventasAgrupadas = [];

            foreach ($rows as $index => $row) {
                if ($this->esFilaVacia($row)) {
                    Log::info("Fila {$index} ignorada por estar vacía");
                    continue;
                }

                $this->tipo_documento = $this->determinarTipoDocumento($row);

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
                    $id_documento = $this->buscarDocumentoPorNombre($row['tipo_documento_venta'] ?? '')
                        ?: $this->buscarDocumentoPorTipoImportacion();

                    if (!$id_cliente || !$id_documento) {
                        $detalleError = "Fila ignorada: ";
                        if (!$id_cliente) {
                            $detalleError .= "No se pudo obtener o crear el cliente ('" . ($row['nombre'] ?? 'sin nombre') . "'). ";
                        }
                        if (!$id_documento) {
                            $detalleError .= "No se encontró tipo de documento de venta válido en el sistema. ";
                        }
                        $this->errores[] = $detalleError;
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

            // Devolver información completa para mostrar en frontend
            return [
                'message' => $this->contador . ' ventas procesadas correctamente',
                'ventas_procesadas' => $this->contador,
                'ventas_fallidas' => $ventasFallidas,
                'productos_faltantes' => array_unique($this->productos_faltantes)
            ];
        } catch (\Exception $e) {
            Log::error("Error en importación: " . $e->getMessage());
            Log::error($e->getTraceAsString());
            DB::rollback();
            throw $e;
        }
    }

    protected function normalizarTextoNumero($valor)
    {
        if ($valor === null || $valor === '') {
            return '';
        }
        if (is_numeric($valor)) {
            return sprintf('%.0f', (float) $valor);
        }
        $str = trim((string) $valor);
        if (preg_match('/^[0-9]+(\.[0-9]+)?[eE][+-]?[0-9]+$/', $str)) {
            return sprintf('%.0f', (float) $str);
        }
        return $str;
    }

    protected function esFilaVacia($row)
    {
        Log::info('Verificando fila: ' . json_encode($row));

        if (empty($row) || count($row) == 0) {
            Log::info('Fila vacía: array vacío');
            return true;
        }

        foreach ($row as $valor) {
            if ($valor !== null && $valor !== '' && !(is_string($valor) && trim($valor) === '')) {
                Log::info('Fila válida');
                return false;
            }
        }

        Log::info('Fila vacía: todos los campos son vacíos');
        return true;
    }

    /**
     * Determina si el documento es crédito_fiscal o consumidor_final facilmente si viene o no NIT.
     */
    protected function determinarTipoDocumento($fila)
    {
        $nit = isset($fila['nit']) ? trim((string) $fila['nit']) : '';
        if ($nit !== '') {
            return 'credito_fiscal';
        }
        return 'consumidor_final';
    }

    protected function validarFilaRequeridos($fila)
    {
        if ($this->esFilaVacia($fila)) {
            return false;
        }

        $tipoDoc = $this->determinarTipoDocumento($fila);
        $requeridos = ['nombre', 'fecha', 'descripcion', 'total'];
        $requeridos = array_merge($requeridos, $tipoDoc == 'credito_fiscal' ? ['nit'] : []);

        if (!empty($fila['num_documento']) && $tipoDoc != 'credito_fiscal') {
            $requeridos[] = 'tipo_documento';
        }

        $faltantes = array_filter($requeridos, function ($campo) use ($fila) {
            return !isset($fila[$campo]) || (is_string($fila[$campo]) && trim($fila[$campo]) === '') || $fila[$campo] === null;
        });

        if (!empty($faltantes)) {
            $this->errores[] = "Error: Faltan los campos obligatorios '" . implode("', '", $faltantes) . "' en la fila de '" . ($fila['nombre'] ?? 'registro sin nombre') . "'.";
            Log::warning("Fila inválida - Faltan campos: " . implode(", ", $faltantes) . " - Datos: " . json_encode($fila));
            return false;
        }

        return true;
    }

    protected function resolverGiroYCodGiro($fila)
    {
        $raw = trim((string) ($fila['giro'] ?? $fila['cod_giro'] ?? ''));
        if ($raw === '') {
            return ['giro' => null, 'cod_giro' => null];
        }

        $actividad = ActividadEconomica::where('cod', $raw)
            ->orWhere('nombre', $raw)
            ->orWhereRaw('LOWER(TRIM(nombre)) = ?', [strtolower($raw)])
            ->first();

        if ($actividad) {
            return [
                'giro' => $actividad->nombre,
                'cod_giro' => $actividad->cod,
            ];
        }

        if (preg_match('/^\d{4,6}$/', $raw)) {
            return [
                'giro' => $raw,
                'cod_giro' => $raw,
            ];
        }

        return [
            'giro' => mb_substr($raw, 0, 255),
            'cod_giro' => null,
        ];
    }

    protected function buscarOCrearCliente($fila)
    {
        try {
            $cliente = $this->buscarCliente($fila);

            if (!$cliente) {
                $cliente = $this->crearCliente($fila);
            }

            return $cliente ? $cliente->id : null;
        } catch (\Exception $e) {
            Log::error("Error al buscar/crear cliente: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            $clienteDefault = Cliente::where('nombre', 'Consumidor Final')
                ->where('id_empresa', Auth::user()->id_empresa)
                ->first();
            if ($clienteDefault) {
                return $clienteDefault->id;
            }
            $this->errores[] = "Error al crear cliente (" . ($fila['nombre'] ?? '') . "): " . $e->getMessage();
            return null;
        }
    }

    protected function buscarCliente($fila)
    {
        if ($this->tipo_documento == 'credito_fiscal') {
            $nit = $this->normalizarTextoNumero($fila['nit'] ?? '');
            $ncr = $this->normalizarTextoNumero($fila['nrc'] ?? $fila['ncr'] ?? '');

            $cliente = Cliente::where(function ($q) use ($nit, $ncr, $fila) {
                $hasCond = false;
                if ($nit !== '') {
                    $q->where('nit', $nit)->orWhere('nit', (string) ($fila['nit'] ?? ''));
                    $hasCond = true;
                }
                if ($ncr !== '') {
                    if ($hasCond) {
                        $q->orWhere('ncr', $ncr)->orWhere('ncr', (string) ($fila['nrc'] ?? $fila['ncr'] ?? ''));
                    } else {
                        $q->where('ncr', $ncr)->orWhere('ncr', (string) ($fila['nrc'] ?? $fila['ncr'] ?? ''));
                        $hasCond = true;
                    }
                }
            })->first();

            if ($cliente) {
                $this->actualizarCliente($cliente, $fila);
            }
            return $cliente;
        }

        $numDoc = $this->normalizarTextoNumero($fila['num_documento'] ?? '');
        if ($numDoc !== '') {
            $cliente = Cliente::where('dui', $numDoc)
                ->orWhere('dui', (string) ($fila['num_documento'] ?? ''))
                ->first();
            if ($cliente) {
                $this->actualizarCliente($cliente, $fila);
            }
            return $cliente;
        }

        return null;
    }

    protected function actualizarCliente($cliente, $fila)
    {
        $ubicacion = $this->resolverUbicacionDesdeFila($fila);
        $dep = $ubicacion['departamento'];
        $mun = $ubicacion['municipio'];
        $dis = $ubicacion['distrito'];
        $giroInfo = $this->resolverGiroYCodGiro($fila);

        $datosCliente = [
            'nombre' => $fila['nombre'] ?? 'Consumidor Final',
            'apellido' => $fila['apellido'] ?? '',
            'telefono' => $this->normalizarTextoNumero($fila['telefono'] ?? ''),
            'correo' => $fila['correo'] ?? '',
            'direccion' => $fila['direccion'] ?? '',
            'cod_departamento' => $dep ? $dep->cod : null,
            'cod_municipio' => $mun ? $mun->cod : null,
            'cod_distrito' => $dis ? $dis->cod : null,
            'tipo_contribuyente' => $fila['tipo_contribuyente'] ?? 'Otro',
            'dui' => $this->normalizarTextoNumero($fila['num_documento'] ?? '')
        ];

        if ($this->tipo_documento == 'credito_fiscal') {
            $datosCliente = array_merge($datosCliente, [
                'nombre_empresa' => $fila['nombre_comercial'] ?? $fila['nombre'],
                'nit' => $this->normalizarTextoNumero($fila['nit'] ?? ''),
                'ncr' => $this->normalizarTextoNumero($fila['nrc'] ?? $fila['ncr'] ?? ''),
                'giro' => $giroInfo['giro'],
                'cod_giro' => $giroInfo['cod_giro'],
                'tipo_contribuyente' => 'Otro',
                'dui' => $this->normalizarTextoNumero($fila['num_documento'] ?? '')
            ]);
        } else {
            // Consumidor final: solo tipo_documento y num_documento (no hay NIT, NRC, cod_giro, nombre_comercial)
            $datosCliente = array_merge($datosCliente, [
                'nombre_empresa' => $fila['nombre'] ?? '',
                'tipo_documento' => $fila['tipo_documento'] ?? 'DUI',
                'dui' => $this->normalizarTextoNumero($fila['num_documento'] ?? '')
            ]);
        }
        $cliente->fill($datosCliente);
        $cliente->save();
        return $cliente;
    }

    protected function crearCliente($fila)
    {
        $cliente = new Cliente();
        $ubicacion = $this->resolverUbicacionDesdeFila($fila);
        $departamento = $ubicacion['departamento'];
        $municipio = $ubicacion['municipio'];
        $distrito = $ubicacion['distrito'];
        $giroInfo = $this->resolverGiroYCodGiro($fila);

        $datosCliente = [
            'nombre' => $fila['nombre'] ?? 'Consumidor Final',
            'apellido' => $fila['apellido'] ?? '',
            'telefono' => $this->normalizarTextoNumero($fila['telefono'] ?? ''),
            'correo' => $fila['correo'] ?? '',
            'direccion' => $fila['direccion'] ?? '',
            'cod_departamento' => $departamento ? $departamento->cod : null,
            'departamento' => $departamento ? $departamento->nombre : ($fila['departamento'] ?? null),
            'cod_municipio' => $municipio ? $municipio->cod : null,
            'municipio' => $municipio ? $municipio->nombre : ($fila['municipio'] ?? null),
            'cod_distrito' => $distrito ? $distrito->cod : null,
            'distrito' => $distrito ? $distrito->nombre : ($fila['distrito'] ?? null),
            'id_usuario' => Auth::id(),
            'tipo' => $this->tipo_documento == 'credito_fiscal' ? 'Empresa' : 'Persona',
            'id_empresa' => Auth::user()->id_empresa
        ];

        if ($this->tipo_documento == 'credito_fiscal') {
            $datosCliente = array_merge($datosCliente, [
                'nombre_empresa' => $fila['nombre_comercial'] ?? $fila['nombre'],
                'nit' => $this->normalizarTextoNumero($fila['nit'] ?? ''),
                'ncr' => $this->normalizarTextoNumero($fila['nrc'] ?? $fila['ncr'] ?? ''),
                'giro' => $giroInfo['giro'],
                'cod_giro' => $giroInfo['cod_giro'],
                'tipo_contribuyente' => 'Otro',
                'dui' => $this->normalizarTextoNumero($fila['num_documento'] ?? '')
            ]);
        } else {
            $datosCliente = array_merge($datosCliente, [
                'tipo_documento' => $fila['tipo_documento'] ?? 'DUI',
                'dui' => $this->normalizarTextoNumero($fila['num_documento'] ?? '')
            ]);
        }

        $cliente->fill($datosCliente);
        $cliente->save();

        return $cliente;
    }

    /**
     * Busca departamento por código (id). Para resolver por nombre del Excel usar buscarDepartamentoPorNombre.
     */
    protected function buscarDepartamento($codigo)
    {
        return $codigo ? Departamento::where('id', $codigo)->first() : null;
    }

    /**
     * Busca municipio por código (id). Para resolver por nombre del Excel usar buscarMunicipioPorNombre.
     */
    protected function buscarMunicipio($codigo)
    {
        return $codigo ? Municipio::where('id', $codigo)->first() : null;
    }

    /**
     * Busca distrito por códigos de departamento y municipio.
     * Para resolver por nombre del Excel usar buscarDistritoPorNombre.
     */
    protected function buscarDistrito($departamento, $municipio)
    {
        return ($departamento && $municipio) ? Distrito::where('cod_departamento', $departamento)
            ->where('cod_municipio', $municipio)
            ->first() : null;
    }

    /**
     * Busca departamento por nombre (comparación case-insensitive con LOWER).
     * El Excel trae la columna "departamento" con el nombre; se devuelve el registro para obtener ->cod.
     */
    protected function buscarDepartamentoPorNombre($nombre)
    {
        if ($nombre === null || (is_string($nombre) && trim($nombre) === '')) {
            return null;
        }
        $nombre = is_string($nombre) ? trim($nombre) : (string) $nombre;
        return Departamento::whereRaw('LOWER(TRIM(nombre)) = ?', [strtolower($nombre)])->first();
    }

    /**
     * Busca municipio por nombre (comparación case-insensitive con LOWER).
     * Si se pasa cod_departamento se filtra por él para desambiguar.
     */
    protected function buscarMunicipioPorNombre($nombre, $cod_departamento = null)
    {
        if ($nombre === null || (is_string($nombre) && trim($nombre) === '')) {
            return null;
        }
        $nombre = is_string($nombre) ? trim($nombre) : (string) $nombre;
        $query = Municipio::whereRaw('LOWER(TRIM(nombre)) = ?', [strtolower($nombre)]);
        if ($cod_departamento !== null && $cod_departamento !== '') {
            $query->where('cod_departamento', $cod_departamento);
        }
        return $query->first();
    }

    /**
     * Busca distrito por nombre (comparación case-insensitive con LOWER).
     * Si se pasan cod_departamento y cod_municipio se filtran para desambiguar.
     */
    protected function buscarDistritoPorNombre($nombre, $cod_departamento = null, $cod_municipio = null)
    {
        if ($nombre === null || (is_string($nombre) && trim($nombre) === '')) {
            return null;
        }
        $nombre = is_string($nombre) ? trim($nombre) : (string) $nombre;
        $query = Distrito::whereRaw('LOWER(TRIM(nombre)) = ?', [strtolower($nombre)]);
        if ($cod_departamento !== null && $cod_departamento !== '') {
            $query->where('cod_departamento', $cod_departamento);
        }
        if ($cod_municipio !== null && $cod_municipio !== '') {
            $query->where('cod_municipio', $cod_municipio);
        }
        return $query->first();
    }

    /**
     * Resuelve departamento, municipio y distrito desde la fila del Excel (columnas por nombre).
     * Devuelve ['departamento' => modelo|null, 'municipio' => modelo|null, 'distrito' => modelo|null]
     * para usar ->cod en el registro de venta/cliente.
     * Acepta array o objeto (p. ej. Collection) porque Maatwebsite/Excel puede devolver filas como objeto.
     */
    protected function resolverUbicacionDesdeFila($fila)
    {
        $fila = is_array($fila) ? $fila : (method_exists($fila, 'toArray') ? $fila->toArray() : (array) $fila);
        $dep = $this->buscarDepartamentoPorNombre($fila['departamento'] ?? null);
        $codDep = $dep ? $dep->cod : null;
        $mun = $this->buscarMunicipioPorNombre($fila['municipio'] ?? null, $codDep);
        $codMun = $mun ? $mun->cod : null;
        $dis = $this->buscarDistritoPorNombre($fila['distrito'] ?? null, $codDep, $codMun);
        return [
            'departamento' => $dep,
            'municipio' => $mun,
            'distrito' => $dis,
        ];
    }

    /**
     * Busca el documento por el valor de tipo_documento_venta del Excel.
     * Valores válidos: Factura, Ticket, Crédito fiscal, Factura de exportación.
     */
    protected function buscarDocumentoPorNombre($nombreExcel)
    {
        $nombre = is_string($nombreExcel) ? trim($nombreExcel) : '';
        if ($nombre === '') {
            return null;
        }

        $sucursalId = Auth::user()->id_sucursal ?? null;
        if ($sucursalId) {
            $documento = Documento::where('id_sucursal', $sucursalId)
                ->whereRaw('LOWER(TRIM(nombre)) = ?', [strtolower($nombre)])
                ->first();
            if ($documento) {
                return $documento->id;
            }
        }

        $documento = Documento::whereRaw('LOWER(TRIM(nombre)) = ?', [strtolower($nombre)])->first();
        return $documento ? $documento->id : null;
    }

    /**
     * Fallback: obtiene id_documento según tipo de importación cuando tipo_documento_venta viene vacío.
     * Crédito fiscal → "Crédito fiscal"; Consumidor final → "Ticket" o "Factura".
     */
    protected function buscarDocumentoPorTipoImportacion()
    {
        $id_sucursal = Auth::user()->id_sucursal ?? null;

        if ($this->tipo_documento === 'credito_fiscal') {
            $posiblesNombres = [
                config('constants.TIPO_DOCUMENTO_CREDITO_FISCAL', 'Crédito fiscal'),
                'Crédito fiscal',
                'Credito fiscal',
                'Comprobante de Crédito Fiscal',
                'Comprobante de Credito Fiscal',
                'CCF'
            ];
            foreach ($posiblesNombres as $nom) {
                $id = $this->buscarDocumentoPorNombre($nom);
                if ($id) {
                    return $id;
                }
            }
        } else {
            $posiblesNombres = [
                config('constants.TIPO_DOCUMENTO_TICKET', 'Ticket'),
                'Ticket',
                config('constants.TIPO_DOCUMENTO_FACTURA', 'Factura'),
                'Factura',
                'Factura de Consumidor Final',
                'Factura de consumidor final',
                'FCF'
            ];
            foreach ($posiblesNombres as $nom) {
                $id = $this->buscarDocumentoPorNombre($nom);
                if ($id) {
                    return $id;
                }
            }
        }

        if ($id_sucursal) {
            $doc = Documento::where('id_sucursal', $id_sucursal)->where('activo', 1)->first()
                ?: Documento::where('id_sucursal', $id_sucursal)->first();
            if ($doc) {
                return $doc->id;
            }
        }

        $cualquiera = Documento::where('activo', 1)->first() ?: Documento::first();
        return $cualquiera ? $cualquiera->id : null;
    }

    protected function generarClienteKey($row)
    {
        $nitNorm = $this->normalizarTextoNumero($row['nit'] ?? '');
        $base = $this->tipo_documento == 'credito_fiscal'
            ? ($nitNorm ?: ($row['nombre'] ?? '')) . '-' . ($row['fecha'] ?? '')
            : ($row['nombre'] ?? '') . '-' . ($row['fecha'] ?? '');
        $correlativo = isset($row['correlativo']) && $row['correlativo'] !== '' && $row['correlativo'] !== null
            ? $row['correlativo']
            : 'auto';
        return $base . '-' . $correlativo;
    }

    protected function obtenerDatosCabecera($fila, $id_cliente, $id_documento)
    {
        $canal = Canal::where('id_empresa', Auth::user()->id_empresa)->first()
            ?: Canal::firstOrCreate(['id_empresa' => Auth::user()->id_empresa], ['nombre' => 'Directo', 'enable' => '1']);
        $fecha = $this->convertirFechaExcel($fila['fecha']);

        $gravada = $this->extraerValorOCalcular($fila, 'gravada', 0);
        $exenta = $this->extraerValorOCalcular($fila, 'exenta', 0);
        $noSujeta = $this->extraerValorOCalcular($fila, 'no_sujeta', 0);
        $subtotal = $this->extraerValorOCalcular($fila, 'subtotal', $gravada + $exenta + $noSujeta);
        $iva = $this->extraerValorOCalcular($fila, 'iva', $gravada * 0.13);
        $ivaRetenido = $this->extraerValorOCalcular($fila, 'iva_retenido', 0);
        $total = $this->extraerValorOCalcular($fila, 'total', $subtotal + $iva - $ivaRetenido);

        $estado = $this->obtenerEstadoFactura($fila);

        $cabecera = [
            'fecha' => $fecha,
            'estado' => $estado,
            'forma_pago' => $fila['forma_pago'] ?? 'Tarjeta de crédito/débito',
            'condicion' => $fila['condicion'] ?? 'Contado',
            'credito' => (strtolower($fila['condicion'] ?? 'Contado') == 'crédito' || strtolower($fila['condicion'] ?? 'Contado') == 'credito') ? 1 : 0,
            'id_cliente' => $id_cliente,
            'id_documento' => $id_documento,
            'id_canal' => $canal ? $canal->id : 1,
            'id_sucursal' => Auth::user()->id_sucursal ?? 1,
            'id_bodega' => Auth::user()->id_bodega ?? 1,
            'id_vendedor' => Auth::id(),
            'id_usuario' => Auth::id(),
            'id_empresa' => Auth::user()->id_empresa,
            'observaciones' => '',
            'cotizacion' => 0,
            'exenta' => $exenta,
            'no_sujeta' => $noSujeta,
            'gravada' => $gravada,
            'cuenta_a_terceros' => 0,
            'iva' => $iva,
            'iva_retenido' => $ivaRetenido,
            'iva_percibido' => 0,
            'sub_total' => $subtotal,
            'total' => $total,
            'cobrar_impuestos' => $iva > 0 ? 1 : 0,
            'importado' => true,
        ];

        $cabecera['fecha_pago'] = isset($fila['fecha_pago']) && !empty($fila['fecha_pago']) ?
            $this->convertirFechaExcel($fila['fecha_pago']) : ($cabecera['credito'] ? Carbon::parse($fecha)->addMonth()->format('Y-m-d') : null);

        $documento = Documento::find($id_documento);
        if ($documento) {
            $correlativoImportado = $this->extraerCorrelativoFila($fila);
            if ($correlativoImportado !== null) {
                $cabecera['correlativo'] = (int) $correlativoImportado;
            } else {
                $ultimoCorrelativo = Venta::where('id_documento', $id_documento)->max('correlativo');
                $cabecera['correlativo'] = $ultimoCorrelativo ? $ultimoCorrelativo + 1 : $documento->correlativo;
            }
        }

        return $cabecera;
    }

    protected function obtenerEstadoFactura($fila)
    {
        $estado = $fila['estado_factura'] ?? $fila['estado'] ?? 'Pagada';
        if (is_string($estado)) {
            $estado = trim($estado);
        }
        $validos = ['Pagada', 'Pendiente', 'Anulada'];
        return in_array($estado, $validos) ? $estado : 'Pagada';
    }

    protected function extraerCorrelativoFila($fila)
    {
        $valor = $fila['correlativo'] ?? null;
        if ($valor === null || $valor === '') {
            return null;
        }
        if (is_numeric($valor)) {
            return (int) $valor;
        }
        if (is_string($valor) && is_numeric(trim($valor))) {
            return (int) trim($valor);
        }
        return null;
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

        if (is_string($fecha) && preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/', trim($fecha), $matches)) {
            $y = strlen($matches[3]) === 2 ? '20' . $matches[3] : $matches[3];
            return "{$y}-{$matches[2]}-{$matches[1]}";
        }

        try {
            return Carbon::parse($fecha)->format('Y-m-d');
        } catch (\Exception $e) {
            return date('Y-m-d');
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

    public function getProductosFaltantes()
    {
        return array_unique($this->productos_faltantes);
    }

    protected function obtenerDatosDetalle($fila)
    {
        Log::info('Procesando fila: ' . json_encode($fila));

        // No se busca ni crea producto: se agrega el detalle con la descripción del Excel (id_producto = 0).
        $idProducto = 0;

        $gravada = $this->extraerValorOCalcular($fila, 'gravada', 0);
        $exenta = $this->extraerValorOCalcular($fila, 'exenta', 0);
        $noSujeta = $this->extraerValorOCalcular($fila, 'no_sujeta', 0);
        $subtotal = $this->extraerValorOCalcular($fila, 'subtotal', $gravada + $exenta + $noSujeta);
        $iva = $this->extraerValorOCalcular($fila, 'iva', $gravada * 0.13);
        $total = $this->extraerValorOCalcular($fila, 'total', $subtotal + $iva);

        $cantidad = 1;
        $precio = $gravada > 0 ? $gravada : ($subtotal > 0 ? $subtotal : $total);

        if (isset($fila['precio']) && is_numeric($fila['precio']) && (float) $fila['precio'] > 0) {
            $precio = (float) $fila['precio'];
            if (isset($fila['cantidad']) && is_numeric($fila['cantidad']) && (float) $fila['cantidad'] > 0) {
                $cantidad = (float) $fila['cantidad'];
            } else if ($precio > 0 && $gravada > 0) {
                $cantidad = $gravada / $precio;
            }
        } elseif (isset($fila['cantidad']) && is_numeric($fila['cantidad']) && (float) $fila['cantidad'] > 0) {
            $cantidad = (float) $fila['cantidad'];
            if ($cantidad > 0 && $precio > 0) {
                $precio = $precio / $cantidad;
            }
        }

        $costo = 0;
        $total_costo = 0;

        return [
            'id_producto' => $idProducto,
            'descripcion' => $this->descripcionDetalle($fila),
            'cantidad' => $cantidad,
            'precio' => $precio,
            'costo' => $costo,
            'descuento' => 0,
            'sub_total' => $subtotal,
            'total' => $total,
            'total_costo' => $total_costo,
            'exenta' => $exenta,
            'no_sujeta' => $noSujeta,
            'gravada' => $gravada,
            'cuenta_a_terceros' => 0,
            'iva' => $iva,
            'id_vendedor' => Auth::id(),
            'tipo_item' => $fila['tipo_item'] ?? 'Servicio',
            'tipo_gravado' => $gravada > 0 ? 'gravada' : ($exenta > 0 ? 'exenta' : ($noSujeta > 0 ? 'no_sujeta' : 'gravada')),
        ];
    }

    protected function descripcionDetalle($fila)
    {
        $descripcion = $fila['descripcion'] ?? '';
        if (is_string($descripcion)) {
            $descripcion = trim($descripcion);
        }

        return $descripcion !== '' && $descripcion !== null ? $descripcion : 'Sin descripción';
    }

    protected function extraerValorOCalcular($fila, $campo, $valorPredeterminado = 0)
    {
        if (!isset($fila[$campo]) || $fila[$campo] === '' || $fila[$campo] === null) {
            return (float) $valorPredeterminado;
        }

        $valor = $fila[$campo];

        if (is_numeric($valor)) {
            return (float) $valor;
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
                    return (float) $resultado;
                }
            }
        }

        return is_numeric($valor) ? (float) $valor : (float) $valorPredeterminado;
    }

    protected function procesarVenta($cabecera, $detalles)
    {
        try {
            $detallesValidos = [];
            $detallesInvalidos = [];

            foreach ($detalles as $detalle_data) {
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
            }

            $documento = Documento::find($venta->id_documento);
            if ($documento) {
                $documento->correlativo = max($documento->correlativo, $venta->correlativo + 1);
                $documento->save();
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
        if (isset($cabecera['iva']) && is_numeric($cabecera['iva']) && (float) $cabecera['iva'] > 0) {
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
