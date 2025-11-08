<?php

namespace App\Imports;

use App\Models\Contabilidad\Catalogo\Cuenta;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Auth;
use Exception;

class CatalogoImport implements ToCollection, WithHeadingRow, WithValidation
{
    private $numRows = 0;
    private $errores = [];
    private $empresa_id;
    private $cuentasProcesadas = []; // Mapa de código => id de cuentas ya procesadas

    public function __construct()
    {
        $this->empresa_id = Auth::user()->id_empresa;
    }

    /**
     * Procesar toda la colección en una transacción atómica
     */
    public function collection(Collection $rows)
    {
        DB::beginTransaction();

        try {
            Log::info("Iniciando import de catálogo", [
                'empresa_id' => $this->empresa_id,
                'total_filas' => $rows->count()
            ]);

            // 1. Validar que no haya códigos duplicados en el archivo
            $this->validarCodigosDuplicadosEnArchivo($rows);

            // 2. Validar que no existan códigos en la BD para esta empresa
            $this->validarCodigosExistentesEnBD($rows);

            // 3. Ordenar filas por nivel (padres primero)
            $rowsOrdenadas = $this->ordenarPorJerarquia($rows);

            // 4. Procesar cada fila
            foreach ($rowsOrdenadas as $index => $row) {
                $this->procesarFila($row, $index);
                $this->numRows++;
            }

            DB::commit();

            Log::info("Import completado exitosamente", [
                'empresa_id' => $this->empresa_id,
                'filas_procesadas' => $this->numRows
            ]);

        } catch (Exception $e) {
            DB::rollback();

            Log::error("Error en import de catálogo", [
                'empresa_id' => $this->empresa_id,
                'error' => $e->getMessage(),
                'errores_acumulados' => $this->errores
            ]);

            throw new Exception("Error en importación: " . $e->getMessage() .
                               (count($this->errores) > 0 ? "\nErrores adicionales: " . implode(", ", $this->errores) : ""));
        }
    }

    /**
     * Validar que no haya códigos duplicados en el archivo Excel
     */
    private function validarCodigosDuplicadosEnArchivo(Collection $rows)
    {
        $codigos = $rows->pluck('codigo');
        $duplicados = $codigos->duplicates();

        if ($duplicados->count() > 0) {
            throw new Exception("Códigos duplicados en el archivo: " . $duplicados->implode(', '));
        }
    }

    /**
     * Validar que no existan códigos en la BD para esta empresa
     */
    private function validarCodigosExistentesEnBD(Collection $rows)
    {
        $codigosArchivo = $rows->pluck('codigo')->toArray();

        $codigosExistentes = Cuenta::where('id_empresa', $this->empresa_id)
            ->whereIn('codigo', $codigosArchivo)
            ->pluck('codigo')
            ->toArray();

        if (count($codigosExistentes) > 0) {
            throw new Exception("Los siguientes códigos ya existen en la empresa: " . implode(', ', $codigosExistentes));
        }
    }

    /**
     * Ordenar filas por jerarquía (nivel ascendente, luego por código)
     */
    private function ordenarPorJerarquia(Collection $rows)
    {
        return $rows->sortBy(function ($row) {
            return [$row['nivel'], $row['codigo']];
        });
    }

    /**
     * Procesar una fila individual
     */
    private function procesarFila($row, $index)
    {
        try {
            $cuenta = new Cuenta();

            // Procesar acepta_datos
            $acepta_datos = strtoupper($row['acepta_datos'] ?? 'NO') == 'SI' ? 1 : 0;

            // Datos básicos
            $cuenta->codigo = $row['codigo'];
            $cuenta->nombre = $row['nombre'];
            $cuenta->naturaleza = $row['naturaleza'];
            $cuenta->rubro = ucfirst(strtolower($row['rubro']));
            $cuenta->nivel = $row['nivel'];
            $cuenta->id_empresa = $this->empresa_id; // ✅ SEGURO: Siempre la empresa actual
            $cuenta->acepta_datos = $acepta_datos;
            $cuenta->abono = isset($row['abono']) ? $row['abono'] : 0;
            $cuenta->cargo = isset($row['cargo']) ? $row['cargo'] : 0;
            $cuenta->saldo = isset($row['saldo']) ? $row['saldo'] : 0;
            $cuenta->saldo_inicial = isset($row['saldo']) ? $row['saldo'] : 0;

            // ✅ SEGURO: Buscar cuenta padre primero en las cuentas ya procesadas, luego en BD
            if (!empty($row['id_cuenta_padre'])) {
                // Normalizar código padre (convertir a string para consistencia)
                $codigoPadre = (string)$row['id_cuenta_padre'];
                $idPadre = null;

                // 1. Buscar primero en las cuentas ya procesadas del archivo
                if (isset($this->cuentasProcesadas[$codigoPadre])) {
                    $idPadre = $this->cuentasProcesadas[$codigoPadre];
                } else {
                    // 2. Si no está en las procesadas, buscar en la base de datos
                    // Buscar tanto como string como número para mayor compatibilidad
                    $cuentaPadre = Cuenta::where('id_empresa', $this->empresa_id)
                        ->where(function($query) use ($codigoPadre) {
                            $query->where('codigo', $codigoPadre)
                                  ->orWhere('codigo', (int)$codigoPadre);
                        })
                        ->first();

                    if ($cuentaPadre) {
                        $idPadre = $cuentaPadre->id;
                    }
                }

                if (!$idPadre) {
                    // Log adicional para debugging
                    Log::warning("Cuenta padre no encontrada", [
                        'codigo_padre' => $codigoPadre,
                        'codigo_cuenta_actual' => $cuenta->codigo,
                        'fila' => $index + 1,
                        'cuentas_procesadas' => array_keys($this->cuentasProcesadas)
                    ]);
                    throw new Exception("Cuenta padre '{$codigoPadre}' no encontrada para la fila " . ($index + 1));
                }

                $cuenta->id_cuenta_padre = $idPadre;
            } else {
                $cuenta->id_cuenta_padre = null;
            }

            $cuenta->save();

            // Guardar la cuenta procesada en el mapa para futuras referencias
            // Normalizar código a string para consistencia en las búsquedas
            $codigoNormalizado = (string)$cuenta->codigo;
            $this->cuentasProcesadas[$codigoNormalizado] = $cuenta->id;

        } catch (Exception $e) {
            $this->errores[] = "Fila " . ($index + 1) . ": " . $e->getMessage();
            throw $e;
        }
    }

    /**
     * Validaciones a nivel de fila
     */
    public function rules(): array
    {
        return [
            'codigo' => 'required|int',
            'nombre' => 'required|string',
            'naturaleza' => 'required|string|in:Deudor,Acreedor',
            'rubro' => 'required|string',
            'nivel' => 'required|integer|min:0|max:10',
            'saldo' => 'nullable|numeric',
            'abono' => 'nullable|numeric',
            'cargo' => 'nullable|numeric',
            'acepta_datos' => 'nullable|string|in:SI,NO,si,no,Si,No'
        ];
    }

    /**
     * Mensajes de validación personalizados
     */
    public function customValidationMessages()
    {
        return [
            'codigo.required' => 'El código es obligatorio',
            'codigo.max' => 'El código no puede exceder 50 caracteres',
            'nombre.required' => 'El nombre es obligatorio',
            'naturaleza.required' => 'La naturaleza es obligatoria',
            'naturaleza.in' => 'La naturaleza debe ser "Deudor" o "Acreedor"',
            'rubro.required' => 'El rubro es obligatorio',
            'nivel.required' => 'El nivel es obligatorio',
            'nivel.integer' => 'El nivel debe ser un número entero',
            'nivel.min' => 'El nivel no puede ser menor a 0',
            'nivel.max' => 'El nivel no puede ser mayor a 10',
        ];
    }

    public function getRowCount(): int
    {
        return $this->numRows;
    }

    public function getErrores(): array
    {
        return $this->errores;
    }
}
