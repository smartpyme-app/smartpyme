<?php

namespace App\Imports;

use App\Models\Planilla\Empleado;
use App\Models\Planilla\DepartamentoEmpresa;
use App\Models\Planilla\CargoEmpresa;
use App\Constants\PlanillaConstants;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmpleadosImport implements ToCollection, WithHeadingRow
{
    protected $data;
    protected $empleadosCreados = 0;
    protected $empleadosActualizados = 0;
    protected $errores = [];

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function collection(Collection $rows)
    {
        try {
            DB::beginTransaction();

            foreach ($rows as $row) {
                // Saltar fila si está vacía
                if ($this->isEmptyRow($row)) {
                    continue;
                }

                try {
                    $this->procesarEmpleado($row);
                } catch (\Exception $e) {
                    $nombreCompleto = $this->obtenerNombreCompleto($row);
                    $this->errores[] = [
                        'nombre' => $nombreCompleto,
                        'error' => $e->getMessage()
                    ];
                    Log::error('Error procesando empleado: ' . $e->getMessage(), [
                        'row' => $row->toArray()
                    ]);
                }
            }

            DB::commit();

            Log::info('Importación de empleados completada', [
                'creados' => $this->empleadosCreados,
                'actualizados' => $this->empleadosActualizados,
                'errores' => count($this->errores)
            ]);

            return true;
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    protected function procesarEmpleado($row)
    {
        // Obtener nombre completo y código
        // Compatible con plantilla de planillas que usa 'codigo' y 'nombres_y_apellidos'
        $nombreCompleto = $this->obtenerNombreCompleto($row);
        $codigoEmpleado = $this->obtenerValorColumna($row, ['codigo', 'codigo_empleado', 'codigo empleado'], null);

        // Buscar empleado existente
        $empleado = $this->buscarEmpleado($nombreCompleto, $codigoEmpleado);

        if ($empleado) {
            // Actualizar empleado existente
            $this->actualizarEmpleado($empleado, $row);
            $this->empleadosActualizados++;
        } else {
            // Crear nuevo empleado
            $this->crearEmpleadoDesdeExcel($row, $nombreCompleto, $codigoEmpleado);
            $this->empleadosCreados++;
        }
    }

    protected function buscarEmpleado($nombreCompleto, $codigoEmpleado = null)
    {
        // Normalizar nombre completo
        $nombreCompleto = preg_replace('/\s+/', ' ', trim($nombreCompleto));

        // Si hay código, buscar SOLO por código
        if (!empty($codigoEmpleado)) {
            $codigoEmpleado = trim($codigoEmpleado);
            $empleado = Empleado::where('codigo', $codigoEmpleado)
                ->where('id_empresa', $this->data['empresa_id'])
                ->first();

            if ($empleado) {
                return $empleado;
            }
            return null;
        }

        // Si no hay código, buscar por nombre de manera estricta
        if (empty($nombreCompleto) || strlen($nombreCompleto) < 3) {
            return null;
        }

        $partes = array_filter(explode(' ', $nombreCompleto), function($parte) {
            return strlen(trim($parte)) > 0;
        });
        $partes = array_values($partes);

        $query = Empleado::where('id_empresa', $this->data['empresa_id']);

        $empleado = $query->where(function ($q) use ($partes, $nombreCompleto) {
            // Búsqueda exacta
            $q->where(DB::raw("CONCAT(TRIM(nombres), ' ', TRIM(apellidos))"), '=', $nombreCompleto);
            $q->orWhere(DB::raw("CONCAT(TRIM(apellidos), ' ', TRIM(nombres))"), '=', $nombreCompleto);

            // Búsqueda por nombres y apellidos separados
            if (count($partes) >= 2) {
                $posibleApellido = end($partes);
                $posiblesNombres = implode(' ', array_slice($partes, 0, -1));

                $q->orWhere(function($subQ) use ($posiblesNombres, $posibleApellido) {
                    $subQ->where('nombres', '=', $posiblesNombres)
                         ->where('apellidos', '=', $posibleApellido);
                });
            }
        })->first();

        return $empleado;
    }

    protected function crearEmpleadoDesdeExcel($row, $nombreCompleto, $codigoEmpleado = null)
    {
        // Extraer nombres y apellidos
        $nombres = trim($this->obtenerValorColumna($row, ['nombres']) ?? '');
        $apellidos = trim($this->obtenerValorColumna($row, ['apellidos']) ?? '');

        if (empty($nombres) || empty($apellidos)) {
            $partes = array_filter(explode(' ', trim($nombreCompleto)), function($parte) {
                return strlen(trim($parte)) > 0;
            });
            $partes = array_values($partes);

            if (count($partes) >= 2) {
                $nombres = $partes[0];
                $apellidos = implode(' ', array_slice($partes, 1));
            } else {
                $nombres = $nombreCompleto;
                $apellidos = $nombreCompleto;
            }
        }

        // Obtener datos del Excel
        $dui = $this->obtenerValorColumna($row, ['documento_de_identidad', 'documento identidad', 'dui'], null);
        $nit = $this->obtenerValorColumna($row, ['nit'], null);
        $email = $this->obtenerValorColumna($row, ['correo', 'email'], null);
        $telefono = $this->obtenerValorColumna($row, ['telefono', 'teléfono'], null);
        $direccion = $this->obtenerValorColumna($row, ['direccion', 'dirección'], null);
        $fechaNacimiento = $this->obtenerValorColumna($row, ['fecha_nacimiento', 'fecha nacimiento'], null);
        $fechaInicio = $this->obtenerValorColumna($row, ['fecha_inicio', 'fecha inicio'], null);
        $salarioBase = $this->limpiarMonto($this->obtenerValorColumna($row, ['salario_base', 'salario base'], 0));
        $tipoJornada = $this->obtenerValorColumna($row, ['tipo_jornada', 'tipo jornada'], null);
        $tipoContrato = $this->obtenerValorColumna($row, ['tipo_contrato', 'tipo contrato'], null);
        $estadoEmpleado = $this->obtenerValorColumna($row, ['estado_empleado', 'estado'], null);
        $idDepartamento = $this->obtenerValorColumna($row, ['id_departamento', 'departamento'], null);
        $idCargo = $this->obtenerValorColumna($row, ['id_cargo', 'cargo'], null);

        // Generar código si no existe
        if (empty($codigoEmpleado)) {
            $codigoEmpleado = $this->generarCodigoEmpleado($nombres, $apellidos);
        }

        // Obtener o crear departamento
        $departamento = $idDepartamento 
            ? DepartamentoEmpresa::find($idDepartamento)
            : $this->obtenerOCrearDepartamento();

        // Obtener o crear cargo
        $cargo = $idCargo 
            ? CargoEmpresa::find($idCargo)
            : $this->obtenerOCrearCargo($departamento->id);

        // Convertir tipo jornada y contrato
        $tipoJornadaId = $this->convertirTipoJornada($tipoJornada);
        $tipoContratoId = $this->convertirTipoContrato($tipoContrato);
        $estadoId = $this->convertirEstadoEmpleado($estadoEmpleado);

        // Procesar fechas
        $fechaNacimientoFormateada = $this->procesarFecha($fechaNacimiento);
        $fechaInicioFormateada = $this->procesarFecha($fechaInicio) ?? Carbon::now()->format('Y-m-d');

        // Generar email si no existe
        if (empty($email)) {
            $email = $this->generarEmail($nombres, $apellidos, $codigoEmpleado);
        }

        // Verificar que el email sea único
        $email = $this->hacerEmailUnico($email);

        // Verificar que el DUI sea único o generar uno temporal
        if (!empty($dui)) {
            $dui = $this->hacerDuiUnico($dui);
        } else {
            $dui = $this->generarDuiTemporal($codigoEmpleado);
        }

        // Crear empleado
        $empleado = Empleado::create([
            'codigo' => $codigoEmpleado,
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'dui' => $dui,
            'nit' => $nit ? $this->hacerNitUnico($nit) : null,
            'isss' => $this->obtenerValorColumna($row, ['isss'], null),
            'afp' => $this->obtenerValorColumna($row, ['afp'], null),
            'fecha_nacimiento' => $fechaNacimientoFormateada ?? Carbon::now()->subYears(25)->format('Y-m-d'),
            'direccion' => $direccion ?? 'Sin dirección',
            'telefono' => $telefono ?? '00000000',
            'email' => $email,
            'salario_base' => $salarioBase > 0 ? $salarioBase : 0,
            'tipo_contrato' => $tipoContratoId,
            'tipo_jornada' => $tipoJornadaId,
            'fecha_ingreso' => $fechaInicioFormateada,
            'estado' => $estadoId,
            'id_departamento' => $departamento->id,
            'id_cargo' => $cargo->id,
            'id_sucursal' => $this->data['sucursal_id'],
            'id_empresa' => $this->data['empresa_id'],
        ]);

        Log::info('Empleado creado desde importación', [
            'id' => $empleado->id,
            'codigo' => $empleado->codigo,
            'nombre_completo' => $empleado->nombre_completo
        ]);

        return $empleado;
    }

    protected function actualizarEmpleado($empleado, $row)
    {
        // Obtener datos del Excel
        $nombres = trim($this->obtenerValorColumna($row, ['nombres']) ?? '');
        $apellidos = trim($this->obtenerValorColumna($row, ['apellidos']) ?? '');
        $dui = $this->obtenerValorColumna($row, ['documento_de_identidad', 'documento identidad', 'dui'], null);
        $nit = $this->obtenerValorColumna($row, ['nit'], null);
        $email = $this->obtenerValorColumna($row, ['correo', 'email'], null);
        $telefono = $this->obtenerValorColumna($row, ['telefono', 'teléfono'], null);
        $direccion = $this->obtenerValorColumna($row, ['direccion', 'dirección'], null);
        $fechaNacimiento = $this->obtenerValorColumna($row, ['fecha_nacimiento', 'fecha nacimiento'], null);
        $fechaInicio = $this->obtenerValorColumna($row, ['fecha_inicio', 'fecha inicio'], null);
        $salarioBase = $this->limpiarMonto($this->obtenerValorColumna($row, ['salario_base', 'salario base'], null));
        $tipoJornada = $this->obtenerValorColumna($row, ['tipo_jornada', 'tipo jornada'], null);
        $tipoContrato = $this->obtenerValorColumna($row, ['tipo_contrato', 'tipo contrato'], null);
        $estadoEmpleado = $this->obtenerValorColumna($row, ['estado_empleado', 'estado'], null);
        $idDepartamento = $this->obtenerValorColumna($row, ['id_departamento', 'departamento'], null);
        $idCargo = $this->obtenerValorColumna($row, ['id_cargo', 'cargo'], null);

        // Preparar datos para actualizar
        $datosActualizar = [];

        if (!empty($nombres)) $datosActualizar['nombres'] = $nombres;
        if (!empty($apellidos)) $datosActualizar['apellidos'] = $apellidos;
        if (!empty($dui)) $datosActualizar['dui'] = $this->hacerDuiUnico($dui, $empleado->id);
        if (!empty($nit)) $datosActualizar['nit'] = $this->hacerNitUnico($nit, $empleado->id);
        if (!empty($email)) $datosActualizar['email'] = $this->hacerEmailUnico($email, $empleado->id);
        if (!empty($telefono)) $datosActualizar['telefono'] = $telefono;
        if (!empty($direccion)) $datosActualizar['direccion'] = $direccion;
        if (!empty($fechaNacimiento)) {
            $fechaNac = $this->procesarFecha($fechaNacimiento);
            if ($fechaNac) $datosActualizar['fecha_nacimiento'] = $fechaNac;
        }
        if (!empty($fechaInicio)) {
            $fechaIng = $this->procesarFecha($fechaInicio);
            if ($fechaIng) $datosActualizar['fecha_ingreso'] = $fechaIng;
        }
        if ($salarioBase !== null) $datosActualizar['salario_base'] = $salarioBase;
        if (!empty($tipoJornada)) $datosActualizar['tipo_jornada'] = $this->convertirTipoJornada($tipoJornada);
        if (!empty($tipoContrato)) $datosActualizar['tipo_contrato'] = $this->convertirTipoContrato($tipoContrato);
        if (!empty($estadoEmpleado)) $datosActualizar['estado'] = $this->convertirEstadoEmpleado($estadoEmpleado);
        if (!empty($idDepartamento)) {
            $departamento = DepartamentoEmpresa::find($idDepartamento);
            if ($departamento) $datosActualizar['id_departamento'] = $departamento->id;
        }
        if (!empty($idCargo)) {
            $cargo = CargoEmpresa::find($idCargo);
            if ($cargo) $datosActualizar['id_cargo'] = $cargo->id;
        }

        $isss = $this->obtenerValorColumna($row, ['isss'], null);
        $afp = $this->obtenerValorColumna($row, ['afp'], null);
        if ($isss !== null) $datosActualizar['isss'] = $isss;
        if ($afp !== null) $datosActualizar['afp'] = $afp;

        // Actualizar empleado
        $empleado->update($datosActualizar);

        Log::info('Empleado actualizado desde importación', [
            'id' => $empleado->id,
            'codigo' => $empleado->codigo,
            'nombre_completo' => $empleado->nombre_completo
        ]);

        return $empleado;
    }

    // Funciones auxiliares reutilizadas de PlanillasImport
    // Compatible con plantilla de planillas que usa 'nombres_y_apellidos' o 'nombres y apellidos'
    protected function obtenerNombreCompleto($row)
    {
        // Buscar nombres_y_apellidos (con guión bajo) - formato de plantilla de planillas
        $nombreCompleto = $this->obtenerValorColumna($row, ['nombres_y_apellidos', 'nombres y apellidos'], null);
        if (!empty($nombreCompleto)) {
            return trim($nombreCompleto);
        }

        // Si no existe, buscar nombres y apellidos separados
        $nombres = trim($this->obtenerValorColumna($row, ['nombres']) ?? '');
        $apellidos = trim($this->obtenerValorColumna($row, ['apellidos']) ?? '');

        if (empty($nombres) && empty($apellidos)) {
            return '';
        }

        return trim($nombres . ' ' . $apellidos);
    }

    protected function obtenerValorColumna($row, array $nombresPosibles, $default = null)
    {
        $arrayRow = $row->toArray();

        foreach ($nombresPosibles as $nombre) {
            if (isset($arrayRow[$nombre]) && $arrayRow[$nombre] !== null && $arrayRow[$nombre] !== '') {
                return $arrayRow[$nombre];
            }

            $nombreConEspacio = str_replace('_', ' ', $nombre);
            if (isset($arrayRow[$nombreConEspacio]) && $arrayRow[$nombreConEspacio] !== null && $arrayRow[$nombreConEspacio] !== '') {
                return $arrayRow[$nombreConEspacio];
            }

            $nombreLower = strtolower($nombre);
            if (isset($arrayRow[$nombreLower]) && $arrayRow[$nombreLower] !== null && $arrayRow[$nombreLower] !== '') {
                return $arrayRow[$nombreLower];
            }

            $nombreConEspacioLower = strtolower($nombreConEspacio);
            if (isset($arrayRow[$nombreConEspacioLower]) && $arrayRow[$nombreConEspacioLower] !== null && $arrayRow[$nombreConEspacioLower] !== '') {
                return $arrayRow[$nombreConEspacioLower];
            }
        }

        // Búsqueda case-insensitive
        $clavesArray = array_keys($arrayRow);
        foreach ($nombresPosibles as $nombre) {
            $nombreNormalizado = $this->normalizarTextoParaBusqueda($nombre);

            foreach ($clavesArray as $clave) {
                $claveNormalizada = $this->normalizarTextoParaBusqueda($clave);

                if ($nombreNormalizado === $claveNormalizada) {
                    $valor = $arrayRow[$clave];
                    if ($valor !== null && $valor !== '') {
                        return $valor;
                    }
                }
            }
        }

        return $default;
    }

    protected function normalizarTextoParaBusqueda($texto)
    {
        $texto = mb_strtolower(trim($texto), 'UTF-8');
        $texto = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'ü'],
            ['a', 'e', 'i', 'o', 'u', 'n', 'u'],
            $texto
        );
        $texto = preg_replace('/[\s_]+/', ' ', $texto);
        $texto = trim($texto);
        return $texto;
    }

    protected function isEmptyRow($row)
    {
        $array = $row->toArray();
        $filtered = array_filter($array, function($value) {
            return !empty(trim((string)$value));
        });
        return empty($filtered);
    }

    protected function limpiarMonto($monto)
    {
        if (empty($monto)) return 0;
        return (float) preg_replace('/[^0-9.]/', '', $monto);
    }

    protected function generarCodigoEmpleado($nombres, $apellidos)
    {
        $inicialNombre = strtoupper(substr($nombres, 0, 1));
        $inicialApellido = strtoupper(substr($apellidos, 0, 1));

        $partesApellido = explode(' ', $apellidos);
        $segundaInicial = '';
        if (count($partesApellido) > 1) {
            $segundaInicial = strtoupper(substr($partesApellido[1], 0, 1));
        }

        $baseCodigo = $inicialNombre . $inicialApellido . $segundaInicial;

        $contador = 1;
        $codigo = $baseCodigo . str_pad($contador, 2, '0', STR_PAD_LEFT);

        while (Empleado::where('codigo', $codigo)
            ->where('id_empresa', $this->data['empresa_id'])
            ->exists()) {
            $contador++;
            $codigo = $baseCodigo . str_pad($contador, 2, '0', STR_PAD_LEFT);

            if ($contador > 99) {
                $codigo = $baseCodigo . time();
                break;
            }
        }

        return $codigo;
    }

    protected function obtenerOCrearDepartamento()
    {
        $departamento = DepartamentoEmpresa::where('id_empresa', $this->data['empresa_id'])
            ->where('id_sucursal', $this->data['sucursal_id'])
            ->where(function($q) {
                $q->where('nombre', 'LIKE', '%General%')
                  ->orWhere('nombre', 'LIKE', '%Sin asignar%')
                  ->orWhere('nombre', 'LIKE', '%Default%');
            })
            ->first();

        if (!$departamento) {
            $departamento = DepartamentoEmpresa::create([
                'nombre' => 'General',
                'descripcion' => 'Departamento creado automáticamente para importación',
                'activo' => true,
                'estado' => 1,
                'id_sucursal' => $this->data['sucursal_id'],
                'id_empresa' => $this->data['empresa_id'],
            ]);
        }

        return $departamento;
    }

    protected function obtenerOCrearCargo($departamentoId)
    {
        $cargo = CargoEmpresa::where('id_empresa', $this->data['empresa_id'])
            ->where('id_sucursal', $this->data['sucursal_id'])
            ->where('id_departamento', $departamentoId)
            ->where(function($q) {
                $q->where('nombre', 'LIKE', '%General%')
                  ->orWhere('nombre', 'LIKE', '%Sin asignar%')
                  ->orWhere('nombre', 'LIKE', '%Default%');
            })
            ->first();

        if (!$cargo) {
            $cargo = CargoEmpresa::create([
                'nombre' => 'General',
                'descripcion' => 'Cargo creado automáticamente para importación',
                'salario_base' => 0,
                'activo' => true,
                'estado' => 1,
                'id_departamento' => $departamentoId,
                'id_sucursal' => $this->data['sucursal_id'],
                'id_empresa' => $this->data['empresa_id'],
            ]);
        }

        return $cargo;
    }

    protected function convertirTipoJornada($tipoJornada)
    {
        if (empty($tipoJornada)) {
            return PlanillaConstants::TIPO_JORNADA_TIEMPO_COMPLETO;
        }

        $tipoJornada = strtolower(trim($tipoJornada));

        if (strpos($tipoJornada, 'tiempo completo') !== false || 
            strpos($tipoJornada, 'completo') !== false ||
            $tipoJornada == '1') {
            return PlanillaConstants::TIPO_JORNADA_TIEMPO_COMPLETO;
        }

        if (strpos($tipoJornada, 'medio tiempo') !== false || 
            strpos($tipoJornada, 'medio') !== false ||
            $tipoJornada == '2') {
            return PlanillaConstants::TIPO_JORNADA_MEDIO_TIEMPO;
        }

        return PlanillaConstants::TIPO_JORNADA_TIEMPO_COMPLETO;
    }

    protected function convertirTipoContrato($tipoContrato)
    {
        if (empty($tipoContrato)) {
            return PlanillaConstants::TIPO_CONTRATO_PERMANENTE;
        }

        $tipoContrato = strtolower(trim($tipoContrato));

        if (strpos($tipoContrato, 'permanente') !== false || $tipoContrato == '1') {
            return PlanillaConstants::TIPO_CONTRATO_PERMANENTE;
        }

        if (strpos($tipoContrato, 'temporal') !== false || $tipoContrato == '2') {
            return PlanillaConstants::TIPO_CONTRATO_TEMPORAL;
        }

        if (strpos($tipoContrato, 'obra') !== false || $tipoContrato == '3') {
            return PlanillaConstants::TIPO_CONTRATO_POR_OBRA;
        }

        if (strpos($tipoContrato, 'servicios profesionales') !== false || 
            strpos($tipoContrato, 'servicios') !== false ||
            $tipoContrato == '4') {
            return 4; // Servicios Profesionales
        }

        return PlanillaConstants::TIPO_CONTRATO_PERMANENTE;
    }

    protected function convertirEstadoEmpleado($estado)
    {
        if (empty($estado)) {
            return PlanillaConstants::ESTADO_EMPLEADO_ACTIVO;
        }

        $estado = strtolower(trim($estado));

        if (strpos($estado, 'activo') !== false || $estado == '1') {
            return PlanillaConstants::ESTADO_EMPLEADO_ACTIVO;
        }

        if (strpos($estado, 'inactivo') !== false || $estado == '0' || $estado == '2') {
            return PlanillaConstants::ESTADO_EMPLEADO_INACTIVO;
        }

        return PlanillaConstants::ESTADO_EMPLEADO_ACTIVO;
    }

    protected function procesarFecha($fecha)
    {
        if (empty($fecha)) {
            return null;
        }

        try {
            // Si es un número (fecha de Excel), convertir
            // Las fechas de Excel son números que representan días desde el 1 de enero de 1900
            if (is_numeric($fecha) && $fecha > 0) {
                // Excel cuenta desde el 1 de enero de 1900, pero tiene un bug que cuenta 1900 como año bisiesto
                // Por eso restamos 2 días
                $timestamp = ($fecha - 25569) * 86400; // 25569 es el número de días entre 1900-01-01 y 1970-01-01
                $fechaCarbon = Carbon::createFromTimestamp($timestamp);
                return $fechaCarbon->format('Y-m-d');
            }

            $formatos = ['d/m/Y', 'Y-m-d', 'd-m-Y', 'm/d/Y', 'Y/m/d'];

            foreach ($formatos as $formato) {
                try {
                    $fechaCarbon = Carbon::createFromFormat($formato, trim($fecha));
                    return $fechaCarbon->format('Y-m-d');
                } catch (\Exception $e) {
                    continue;
                }
            }

            $fechaCarbon = Carbon::parse($fecha);
            return $fechaCarbon->format('Y-m-d');
        } catch (\Exception $e) {
            Log::warning('Error procesando fecha', [
                'fecha_original' => $fecha,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    protected function generarEmail($nombres, $apellidos, $codigo)
    {
        $nombreLimpio = strtolower(preg_replace('/[^a-z0-9]/', '', $this->normalizarTexto($nombres)));
        $apellidoLimpio = strtolower(preg_replace('/[^a-z0-9]/', '', $this->normalizarTexto($apellidos)));
        $codigoLimpio = strtolower(preg_replace('/[^a-z0-9]/', '', $codigo));

        $email = $nombreLimpio . '.' . $apellidoLimpio . '.' . $codigoLimpio . '@empresa.local';

        return $email;
    }

    protected function normalizarTexto($texto)
    {
        $texto = mb_strtolower($texto, 'UTF-8');
        $texto = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ'],
            ['a', 'e', 'i', 'o', 'u', 'n', 'a', 'e', 'i', 'o', 'u', 'n'],
            $texto
        );
        return $texto;
    }

    protected function hacerEmailUnico($email, $excludeId = null)
    {
        $emailBase = $email;
        $contador = 1;

        while (Empleado::where('email', $email)
            ->where('id_empresa', $this->data['empresa_id'])
            ->when($excludeId, function($q) use ($excludeId) {
                $q->where('id', '!=', $excludeId);
            })
            ->exists()) {
            $partes = explode('@', $emailBase);
            $email = $partes[0] . $contador . '@' . ($partes[1] ?? 'empresa.local');
            $contador++;
        }

        return $email;
    }

    protected function hacerDuiUnico($dui, $excludeId = null)
    {
        $duiLimpio = preg_replace('/[^0-9-]/', '', $dui);

        $duiBase = $duiLimpio;
        $duiFinal = $duiLimpio;
        $contador = 1;

        while (Empleado::where('dui', $duiFinal)
            ->where('id_empresa', $this->data['empresa_id'])
            ->when($excludeId, function($q) use ($excludeId) {
                $q->where('id', '!=', $excludeId);
            })
            ->exists()) {
            $duiFinal = $duiBase . '-' . $contador;
            $contador++;

            if ($contador > 10) {
                $duiFinal = $this->generarDuiTemporal($duiBase);
                break;
            }
        }

        return $duiFinal;
    }

    protected function generarDuiTemporal($codigo)
    {
        $timestamp = substr(time(), -6);
        $codigoLimpio = preg_replace('/[^0-9]/', '', $codigo);
        $codigoLimpio = substr($codigoLimpio, 0, 3);

        $dui = str_pad($codigoLimpio . $timestamp, 9, '0', STR_PAD_LEFT);
        $dui = substr($dui, 0, 8) . '-' . substr($dui, -1);

        return $this->hacerDuiUnico($dui);
    }

    protected function hacerNitUnico($nit, $excludeId = null)
    {
        $nit = preg_replace('/[^0-9-]/', '', $nit);

        $nitBase = $nit;
        $contador = 1;

        while (Empleado::where('nit', $nit)
            ->where('id_empresa', $this->data['empresa_id'])
            ->when($excludeId, function($q) use ($excludeId) {
                $q->where('id', '!=', $excludeId);
            })
            ->exists()) {
            $nit = $nitBase . '-' . $contador;
            $contador++;

            if ($contador > 10) {
                return null;
            }
        }

        return $nit;
    }

    public function getEmpleadosCreados()
    {
        return $this->empleadosCreados;
    }

    public function getEmpleadosActualizados()
    {
        return $this->empleadosActualizados;
    }

    public function getErrores()
    {
        return $this->errores;
    }
}

