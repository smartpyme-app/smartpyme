<?php

namespace App\Services\Planilla;

use App\Models\Admin\Empresa;
use App\Models\Admin\Sucursal;
use App\Models\Planilla\PlanillaDetalle;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV para "Crear Planilla por Archivo" del SPU (SSF El Salvador, manual enero 2024).
 */
class SpuPlanillaService
{
    public const TIPOS_DOCUMENTO = ['01', '02', '03', '04'];
    public const INSTITUCIONES = ['COF', 'MAX', 'ISP', 'ISS'];

    // ponytail: el manual no define estos valores (sin encabezado, UTF-8 sin BOM); confirmar con una carga de prueba.
    // Alternativas si el SPU rechaza el archivo: separador ',', cero '0.00' / '00', mb_convert_encoding a Windows-1252.
    private const SEPARADOR = ';';
    private const FIN_LINEA = "\r\n";
    private const CERO_MONTO = '0';
    private const CERO_DIAS = '0';

    private const SIN_INSTITUCION = ['13', '15', '23'];
    private const CON_PAGO_ADICIONAL = ['02', '10', '21'];
    private const CON_VACACION = ['09', '10', '20', '21'];
    private const SOLO_NATURAL = ['15', '23'];
    private const SOLO_JURIDICA = ['16', '17'];
    private const REGIMEN_POR_CODIGO = ['15' => 'domestico', '16' => 'marino_mercante', '17' => 'regidores'];

    private const PLANILLA_APROBADA = 2;
    private const PLANILLA_PAGADA = 3;
    private const DETALLE_RETIRADO = 0;

    private const NOMBRES = [
        'primer_nombre' => 'Primer nombre',
        'segundo_nombre' => 'Segundo nombre',
        'primer_apellido' => 'Primer apellido',
        'segundo_apellido' => 'Segundo apellido',
        'apellido_casada' => 'Apellido de casada',
    ];

    public function empleador(Empresa $empresa, Sucursal $sucursal, int $anio, int $mes): array
    {
        return [
            'nit_dui' => self::digitos($empresa->nit),
            'numero_patronal' => self::digitos($empresa->numero_patronal_isss),
            'periodo' => sprintf('%02d%04d', $mes, $anio),
            'centro_trabajo' => self::digitos($sucursal->correlativo_centro_trabajo_isss),
            'tipo_persona' => $empresa->tipo_persona,
            'regimen_especial' => $empresa->regimen_especial_isss,
        ];
    }

    /** Una fila por empleado sumando las planillas aprobadas o pagadas del mes y sucursal (dos quincenas = una fila). */
    public function filas(int $idEmpresa, int $idSucursal, int $anio, int $mes): array
    {
        return PlanillaDetalle::query()
            ->whereHas('planilla', fn ($q) => $q->where('id_empresa', $idEmpresa)
                ->where('id_sucursal', $idSucursal)
                ->where('anio', $anio)
                ->where('mes', $mes)
                ->whereIn('estado', [self::PLANILLA_APROBADA, self::PLANILLA_PAGADA]))
            ->where('estado', '!=', self::DETALLE_RETIRADO)
            ->with(['empleado' => fn ($q) => $q->withTrashed()])
            ->get()
            ->filter(fn ($detalle) => $detalle->empleado)
            ->groupBy('id_empleado')
            ->map(fn ($detalles) => self::construirFila($detalles->first()->empleado->toArray(), [
                'salario' => $detalles->sum(fn ($d) => (float) $d->salario_devengado),
                'pago_adicional' => $detalles->sum(fn ($d) => (float) $d->monto_horas_extra + (float) $d->comisiones
                    + (float) $d->bonificaciones + (float) $d->otros_ingresos),
                'dias' => $detalles->sum(fn ($d) => (float) $d->dias_laborados),
            ], $anio, $mes))
            ->sortBy('empleado')
            ->values()
            ->all();
    }

    public static function construirFila(array $e, array $suma, int $anio, int $mes): array
    {
        [$primerNombre, $segundoNombre] = self::partir($e['nombres'] ?? '');
        [$primerApellido, $segundoApellido] = self::partir($e['apellidos'] ?? '');
        $inicio = Carbon::create($anio, $mes, 1)->startOfDay();
        $fin = $inicio->copy()->endOfMonth();
        $enMes = fn ($fecha) => $fecha && Carbon::parse($fecha)->between($inicio, $fin);
        $pagoAdicional = round((float) ($suma['pago_adicional'] ?? 0), 2);
        $dias = min(30, (int) round((float) ($suma['dias'] ?? 0)));

        $codigos = [];
        if ($enMes($e['fecha_ingreso'] ?? null)) {
            $codigos[] = '08';
        }
        if ($enMes($e['fecha_baja'] ?? null)) {
            $codigos[] = '07';
        }
        if (!empty($e['es_pensionado'])) {
            $codigos[] = '04';
        }
        if ($pagoAdicional > 0) {
            $codigos[] = '02';
        }

        $advertencias = [];
        if (count($codigos) > 2) {
            $advertencias[] = 'Se detectaron los códigos ' . implode(', ', $codigos) . '; el SPU admite dos. Revise cuáles declarar.';
        }
        if ($dias < 30 && !array_intersect($codigos, ['07', '08'])) {
            $advertencias[] = "Tiene {$dias} días: si fue licencia (05) o incapacidad (06), agregue el código.";
        }

        return [
            'id_empleado' => $e['id'] ?? null,
            'empleado' => trim(($e['nombres'] ?? '') . ' ' . ($e['apellidos'] ?? '')),
            'numero_documento' => self::documento($e['dui'] ?? ''),
            'tipo_documento' => '01',
            'afiliacion_isss' => self::digitos($e['isss'] ?? ''),
            'institucion_previsional' => $e['institucion_previsional'] ?? '',
            'primer_nombre' => $primerNombre,
            'segundo_nombre' => $segundoNombre,
            'primer_apellido' => $primerApellido,
            'segundo_apellido' => $segundoApellido,
            'apellido_casada' => $e['apellido_casada'] ?? '',
            'salario' => round((float) ($suma['salario'] ?? 0), 2),
            'pago_adicional' => $pagoAdicional,
            'monto_vacacion' => 0,
            'dias' => $dias,
            'horas' => (int) round((float) ($e['horas_jornada'] ?? 0) ?: 8),
            'dias_vacacion' => 0,
            'codigo_1' => $codigos[0] ?? '00',
            'codigo_2' => $codigos[1] ?? '00',
            'advertencias' => $advertencias,
        ];
    }

    public function validar(array $filas, array $empleador, bool $complementaria): array
    {
        $filas = array_values($filas);
        $errores = [];
        $error = function (?int $i, string $columna, string $mensaje) use (&$errores, $filas) {
            $errores[] = [
                'fila' => $i === null ? null : $i + 1,
                'empleado' => $i === null ? null : ($filas[$i]['empleado'] ?? null),
                'columna' => $columna,
                'mensaje' => $mensaje,
            ];
        };

        $persona = $empleador['tipo_persona'] ?? null;
        if (!in_array($persona, ['natural', 'juridica'], true)) {
            $error(null, 'Tipo de persona', 'Configure si el empleador es persona natural o jurídica.');
        }
        if (!in_array(strlen($empleador['nit_dui'] ?? ''), $persona === 'juridica' ? [14] : [9, 14], true)) {
            $error(null, 'NIT/DUI empleador', $persona === 'juridica' ? 'El NIT de la empresa debe tener 14 dígitos.' : 'Debe ser un DUI de 9 dígitos o un NIT de 14.');
        }
        if (!preg_match('/^\d{1,9}$/', $empleador['numero_patronal'] ?? '')) {
            $error(null, 'Número patronal', 'Configure el número patronal ISSS (solo números, máximo 9).');
        }
        if (!preg_match('/^(0[1-9]|1[0-2])\d{4}$/', $empleador['periodo'] ?? '')) {
            $error(null, 'Período', 'El período debe tener formato MMAAAA.');
        }
        if (!preg_match('/^\d{1,3}$/', $empleador['centro_trabajo'] ?? '')) {
            $error(null, 'Centro de trabajo', 'Configure el correlativo ISSS del centro de trabajo de la sucursal (máximo 3 dígitos).');
        }
        if (!$filas) {
            $error(null, 'Filas', 'No hay empleados en planillas aprobadas o pagadas para ese mes y sucursal.');
        }

        foreach ($filas as $i => $f) {
            $codigos = [(string) ($f['codigo_1'] ?? ''), (string) ($f['codigo_2'] ?? '')];
            $tiene = fn (array $lista) => (bool) array_intersect($codigos, $lista);

            foreach ($codigos as $n => $codigo) {
                if (!preg_match('/^(0\d|1\d|2[0-3])$/', $codigo)) {
                    $error($i, 'Código de observación ' . ($n + 1), 'Debe ser un código del catálogo (00 a 23); use 00 si no aplica.');
                }
            }

            $tipo = (string) ($f['tipo_documento'] ?? '');
            $documento = self::documento($f['numero_documento'] ?? '');
            if (!in_array($tipo, self::TIPOS_DOCUMENTO, true)) {
                $error($i, 'Tipo de documento', 'Use 01 DUI, 02 Pasaporte, 03 Carné de minoridad o 04 Carné de residente.');
            } elseif ($tipo === '01' ? !preg_match('/^\d{9}$/', $documento) : !preg_match('/^[A-Z0-9]{1,20}$/', $documento)) {
                $error($i, 'Número de documento', $tipo === '01' ? 'El DUI debe tener 9 dígitos.' : 'Obligatorio, máximo 20 caracteres.');
            }
            if (!preg_match('/^\d{1,9}$/', self::digitos($f['afiliacion_isss'] ?? ''))) {
                $error($i, 'Afiliación ISSS', 'Obligatorio, solo números, máximo 9.');
            }
            if (!$tiene(self::SIN_INSTITUCION) && !in_array($f['institucion_previsional'] ?? '', self::INSTITUCIONES, true)) {
                $error($i, 'Institución previsional', 'Seleccione COF, MAX, ISP o ISS (solo va vacía con códigos 13, 15 o 23).');
            }

            foreach (['primer_nombre' => 'Primer nombre', 'primer_apellido' => 'Primer apellido'] as $campo => $columna) {
                if (trim((string) ($f[$campo] ?? '')) === '') {
                    $error($i, $columna, 'Obligatorio.');
                }
            }
            foreach (self::NOMBRES as $campo => $columna) {
                if (mb_strlen(trim((string) ($f[$campo] ?? ''))) > 40) {
                    $error($i, $columna, 'Máximo 40 caracteres.');
                }
            }

            foreach (['salario' => 'Salario', 'pago_adicional' => 'Pago adicional', 'monto_vacacion' => 'Monto de vacación'] as $campo => $columna) {
                $valor = $f[$campo] ?? 0;
                if (!is_numeric($valor) || $valor < 0 || strlen(self::monto($valor)) > 9) {
                    $error($i, $columna, 'Monto inválido (de 0 a 999999.99).');
                }
            }
            if (is_numeric($f['salario'] ?? null) && (float) $f['salario'] <= 0) {
                $error($i, 'Salario', 'Debe ser mayor a 0.');
            }

            $pago = (float) ($f['pago_adicional'] ?? 0);
            if ($tiene(self::CON_PAGO_ADICIONAL) && $pago <= 0) {
                $error($i, 'Pago adicional', 'Los códigos 02, 10 y 21 requieren un pago adicional mayor a 0.');
            }
            if (!$tiene(self::CON_PAGO_ADICIONAL) && $pago != 0) {
                $error($i, 'Pago adicional', 'Solo se permite con código 02, 10 o 21.');
            }

            $montoVacacion = (float) ($f['monto_vacacion'] ?? 0);
            $diasVacacion = $f['dias_vacacion'] ?? 0;
            if ($tiene(self::CON_VACACION)) {
                if ($montoVacacion <= 0) {
                    $error($i, 'Monto de vacación', 'Los códigos 09, 10, 20 y 21 requieren monto de vacación.');
                }
                if (!self::entero($diasVacacion, 1, 99)) {
                    $error($i, 'Días de vacación', 'Los códigos 09, 10, 20 y 21 requieren días de vacación (1 a 99).');
                }
            } else {
                if ($montoVacacion != 0) {
                    $error($i, 'Monto de vacación', 'Solo se permite con código 09, 10, 20 o 21.');
                }
                if ((int) $diasVacacion !== 0) {
                    $error($i, 'Días de vacación', 'Solo se permite con código 09, 10, 20 o 21.');
                }
            }

            if (!self::entero($f['dias'] ?? null, 1, 31)) {
                $error($i, 'Días', 'Entero de 1 a 31.');
            }
            if (!self::entero($f['horas'] ?? null, 1, 24)) {
                $error($i, 'Horas', 'Entero de 1 a 24.');
            }

            if (in_array('12', $codigos, true)) {
                $anterior = $filas[$i - 1] ?? null;
                $codigosAnterior = [(string) ($anterior['codigo_1'] ?? ''), (string) ($anterior['codigo_2'] ?? '')];
                if (!$anterior || ($anterior['id_empleado'] ?? null) != ($f['id_empleado'] ?? null) || !in_array('06', $codigosAnterior, true)) {
                    $error($i, 'Códigos de observación', 'El código 12 requiere, justo antes, una fila del mismo trabajador con código 06.');
                }
            }
            if (in_array('18', $codigos, true) && !$complementaria) {
                $error($i, 'Códigos de observación', 'El código 18 solo se permite en planillas complementarias.');
            }
            if ($tiene(self::SOLO_NATURAL) && $persona !== 'natural') {
                $error($i, 'Códigos de observación', 'Los códigos 15 y 23 son solo para empleadores persona natural.');
            }
            if ($tiene(self::SOLO_JURIDICA) && $persona !== 'juridica') {
                $error($i, 'Códigos de observación', 'Los códigos 16 y 17 son solo para empleadores persona jurídica.');
            }
            foreach (self::REGIMEN_POR_CODIGO as $codigo => $regimen) {
                if (in_array((string) $codigo, $codigos, true) && ($empleador['regimen_especial'] ?? null) !== $regimen) {
                    $error($i, 'Códigos de observación', "El código {$codigo} requiere que el empleador esté registrado en el régimen especial {$regimen}.");
                }
            }
        }

        return $errores;
    }

    public function contenidoCsv(array $filas, array $empleador): string
    {
        $csv = '';
        foreach ($filas as $fila) {
            $campos = array_map(fn ($v) => self::campoCsv((string) $v), $this->lineaCsv($fila, $empleador));
            $csv .= implode(self::SEPARADOR, $campos) . self::FIN_LINEA;
        }

        return $csv;
    }

    public function descargar(array $filas, array $empleador): StreamedResponse
    {
        // ponytail: arma el archivo en memoria; miles de filas pesan pocos cientos de KB. Si crece, escribir por fila a php://output.
        $csv = $this->contenidoCsv($filas, $empleador);

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, "planilla-unica-{$empleador['periodo']}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function lineaCsv(array $f, array $empleador): array
    {
        $codigos = [(string) $f['codigo_1'], (string) $f['codigo_2']];
        $opcional = fn ($valor) => (float) $valor == 0 ? self::CERO_MONTO : self::monto($valor);
        $diasVacacion = (int) ($f['dias_vacacion'] ?? 0);

        return [
            $empleador['nit_dui'],
            $empleador['numero_patronal'],
            $empleador['periodo'],
            str_pad($empleador['centro_trabajo'], 3, '0', STR_PAD_LEFT),
            self::documento($f['numero_documento'] ?? ''),
            $f['tipo_documento'],
            self::digitos($f['afiliacion_isss'] ?? ''),
            array_intersect($codigos, self::SIN_INSTITUCION) ? '' : ($f['institucion_previsional'] ?? ''),
            trim((string) ($f['primer_nombre'] ?? '')),
            trim((string) ($f['segundo_nombre'] ?? '')),
            trim((string) ($f['primer_apellido'] ?? '')),
            trim((string) ($f['segundo_apellido'] ?? '')),
            trim((string) ($f['apellido_casada'] ?? '')),
            self::monto($f['salario']),
            $opcional($f['pago_adicional'] ?? 0),
            $opcional($f['monto_vacacion'] ?? 0),
            self::dos($f['dias']),
            self::dos($f['horas']),
            $diasVacacion === 0 ? self::CERO_DIAS : self::dos($diasVacacion),
            $codigos[0],
            $codigos[1],
        ];
    }

    private static function partir(?string $texto): array
    {
        // ponytail: parte por el primer espacio; "De la Cruz" queda mal y se corrige en pantalla. Upgrade: columnas separadas de nombre en empleados.
        $partes = explode(' ', trim(preg_replace('/\s+/', ' ', (string) $texto)), 2);

        return [$partes[0] ?? '', $partes[1] ?? ''];
    }

    private static function campoCsv(string $valor): string
    {
        return str_contains($valor, self::SEPARADOR) || str_contains($valor, '"') || preg_match('/[\r\n]/', $valor)
            ? '"' . str_replace('"', '""', $valor) . '"'
            : $valor;
    }

    private static function entero(mixed $valor, int $min, int $max): bool
    {
        $texto = (string) $valor;

        return ctype_digit($texto) && (int) $texto >= $min && (int) $texto <= $max;
    }

    private static function digitos(?string $valor): string
    {
        return preg_replace('/\D/', '', (string) $valor);
    }

    private static function documento(?string $valor): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $valor));
    }

    private static function dos(mixed $valor): string
    {
        return str_pad((string) (int) $valor, 2, '0', STR_PAD_LEFT);
    }

    private static function monto(mixed $valor): string
    {
        return number_format((float) $valor, 2, '.', '');
    }
}
