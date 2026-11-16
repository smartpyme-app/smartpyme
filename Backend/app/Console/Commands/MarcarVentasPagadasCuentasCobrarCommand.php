<?php

namespace App\Console\Commands;

use App\Models\Ventas\Venta;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MarcarVentasPagadasCuentasCobrarCommand extends Command
{
    /** @var array<int, string> */
    private const MESES_HOJA = [
        1 => 'ENE',
        2 => 'FEB',
        3 => 'MAR',
        4 => 'ABR',
        5 => 'MAY',
        6 => 'JUN',
        7 => 'JUL',
        8 => 'AGO',
        9 => 'SEP',
        10 => 'OCT',
        11 => 'NOV',
        12 => 'DIC',
    ];

    protected $signature = 'ventas:marcar-pagadas-cuentas-cobrar
                            {--empresa=799 : ID de empresa (VALDES, SUAREZ & VELASCO)}
                            {--mes= : Mes 1-12 (obligatorio)}
                            {--anio= : Año (obligatorio)}
                            {--excel= : Ruta al Excel de cuentas por cobrar}
                            {--hoja= : Nombre de hoja del Excel (si no, busca MES.AÑO o la primera)}
                            {--detalle-excel : Lista filas del Excel sin venta en el sistema}
                            {--ejecutar : Persiste cambios; sin esto solo muestra el plan}';

    protected $description = 'Marca ventas Pendientes como Pagadas por mes, excepto las listadas en el Excel de cuentas por cobrar';

    public function handle(): int
    {
        $empresaId = (int) $this->option('empresa');
        $mes = (int) $this->option('mes');
        $anio = (int) $this->option('anio');

        if ($empresaId <= 0 || $mes < 1 || $mes > 12 || $anio < 2000) {
            $this->error('Indique --empresa, --mes (1-12) y --anio válidos.');

            return self::FAILURE;
        }

        $excelPath = (string) ($this->option('excel') ?: $this->defaultExcelPath());
        if (! is_readable($excelPath)) {
            $this->error('No se puede leer el Excel: '.$excelPath);

            return self::FAILURE;
        }

        $ejecutar = (bool) $this->option('ejecutar');
        if (! $ejecutar) {
            $this->warn('Dry-run: no se aplicarán cambios. Use --ejecutar para persistir.');
        }

        $reader = IOFactory::createReaderForFile($excelPath);
        $reader->setReadDataOnly(true);
        $workbook = $reader->load($excelPath);

        try {
            $hojaUsada = $this->resolverHojaExcel($workbook, $mes, $anio);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $sheet = $workbook->getSheetByName($hojaUsada);

        $this->line(sprintf(
            'Cuentas por cobrar: hoja del libro «%s». Filas con FECHA en %s/%d (no importa que la hoja diga ENE u otro mes).',
            $hojaUsada,
            str_pad((string) $mes, 2, '0', STR_PAD_LEFT),
            $anio
        ));

        $filasExcel = $this->leerFilasPendientesExcel($sheet, $mes, $anio);

        $inicio = Carbon::create($anio, $mes, 1)->startOfDay();
        $fin = (clone $inicio)->endOfMonth();

        /** @var Collection<int, Venta> $pendientes */
        $pendientes = Venta::query()
            ->with('cliente')
            ->where('id_empresa', $empresaId)
            ->where('estado', 'Pendiente')
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->get();

        $exclusionIds = [];
        $filasSinMatch = [];
        $paresExcel = [];
        $ambiguo = false;

        foreach ($filasExcel as $fila) {
            $coincidencias = $this->ventasQueCoincidenConFila($pendientes, $fila);
            if ($coincidencias->isEmpty()) {
                if ($this->filaTieneCandidatosAmbiguos($pendientes, $fila)) {
                    $this->warn(sprintf(
                        'Excel sin match único (%s / %s / %s): hay varias ventas con ese cliente y total; no se excluye ninguna.',
                        $fila['numero_doc'],
                        $fila['cliente'],
                        $fila['total']
                    ));
                } else {
                    $filasSinMatch[] = $fila;
                }

                continue;
            }

            if ($coincidencias->count() > 1) {
                $this->warn(sprintf(
                    'Excel %s: %d ventas pendientes coinciden (ids: %s).',
                    $fila['numero_doc'],
                    $coincidencias->count(),
                    $coincidencias->pluck('id')->join(', ')
                ));
                $ambiguo = true;
            }

            foreach ($coincidencias as $venta) {
                $exclusionIds[$venta->id] = $venta->id;
                $paresExcel[] = [
                    $venta->id,
                    $fila['fecha'],
                    $fila['numero_doc'],
                    $fila['cliente'],
                    $fila['total'],
                    $venta->fecha,
                ];
            }
        }

        if ($ambiguo) {
            return self::FAILURE;
        }

        $aPagar = $pendientes->reject(fn (Venta $v) => isset($exclusionIds[$v->id]))->values();
        $excluidas = $pendientes->filter(fn (Venta $v) => isset($exclusionIds[$v->id]))->values();

        $this->info(sprintf(
            'Empresa %d | Mes %02d/%d | Filas Excel en ese mes: %d | Ventas pendientes en BD: %d | Quedan Pendiente: %d | Pasan a Pagada: %d',
            $empresaId,
            $mes,
            $anio,
            count($filasExcel),
            $pendientes->count(),
            $excluidas->count(),
            $aPagar->count()
        ));

        if ($paresExcel !== []) {
            $this->table(
                ['ID venta', 'Fecha Excel', 'N° DOC', 'Cliente', 'Total', 'Fecha venta BD'],
                $paresExcel
            );
        }

        if ($excluidas->isNotEmpty()) {
            $this->table(
                ['ID', 'N° control', 'Cliente', 'Total', 'Fecha'],
                $excluidas->map(fn (Venta $v) => [
                    $v->id,
                    $v->numero_control,
                    $v->nombre_cliente,
                    $v->total,
                    $v->fecha,
                ])->all()
            );
        }

        if ($aPagar->isNotEmpty()) {
            $this->newLine();
            $this->info('Ventas que pasarían a Pagada:');
            $this->table(
                ['ID', 'N° control', 'Cliente', 'Total', 'Fecha'],
                $aPagar->map(fn (Venta $v) => [
                    $v->id,
                    $v->numero_control,
                    $v->nombre_cliente,
                    $v->total,
                    $v->fecha,
                ])->all()
            );
        }

        if ($filasSinMatch !== []) {
            $this->line(sprintf(
                'Filas del Excel sin venta en el sistema (mismo mes): %d — no impiden la ejecución.',
                count($filasSinMatch)
            ));
            if ($this->option('detalle-excel')) {
                $this->table(
                    ['N° DOC', 'Cliente', 'Total'],
                    array_map(fn (array $f) => [$f['numero_doc'], $f['cliente'], $f['total']], $filasSinMatch)
                );
            }
        }

        if (! $ejecutar) {
            return self::SUCCESS;
        }

        $actualizadas = 0;
        foreach ($aPagar as $venta) {
            $venta->estado = 'Pagada';
            $venta->save();
            $actualizadas++;
        }

        $this->info(sprintf('Ventas actualizadas a Pagada: %d', $actualizadas));

        return self::SUCCESS;
    }

    public static function nombreHojaMes(int $mes, int $anio): string
    {
        $prefijo = self::MESES_HOJA[$mes] ?? strtoupper(Carbon::create($anio, $mes, 1)->locale('es')->isoFormat('MMM'));

        return sprintf('%s.%d', $prefijo, $anio);
    }

    public static function normalizarNombre(?string $nombre): string
    {
        $nombre = preg_replace('/\s+/u', ' ', trim((string) $nombre));

        return mb_strtoupper($nombre ?? '', 'UTF-8');
    }

    public static function totalesCoinciden(float $a, float $b, float $tolerancia = 0.02): bool
    {
        return abs($a - $b) <= $tolerancia;
    }

    public static function parsearFechaExcel(mixed $valorFecha): ?Carbon
    {
        if ($valorFecha === null || $valorFecha === '') {
            return null;
        }

        try {
            if (is_numeric($valorFecha)) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $valorFecha))->startOfDay();
            }

            return Carbon::parse((string) $valorFecha)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    public static function filaFechaEnMes(mixed $valorFecha, int $mes, int $anio): bool
    {
        $fecha = self::parsearFechaExcel($valorFecha);
        if ($fecha === null) {
            return false;
        }

        return (int) $fecha->month === $mes && (int) $fecha->year === $anio;
    }

    public static function correlativoCoincideConDoc(?int $correlativo, string $numeroDocExcel): bool
    {
        if ($correlativo === null || $correlativo <= 0) {
            return false;
        }

        return self::sufijoNumericoDocumento(strtoupper(trim($numeroDocExcel))) === (string) $correlativo;
    }

    public static function numeroControlCoincide(?string $numeroControlDb, string $numeroDocExcel): bool
    {
        $excel = strtoupper(trim($numeroDocExcel));
        $db = strtoupper(trim((string) $numeroControlDb));
        if ($excel === '' || $db === '') {
            return false;
        }
        if ($db === $excel) {
            return true;
        }

        $sufijoExcel = self::sufijoNumericoDocumento($excel);
        $sufijoDb = self::sufijoNumericoDocumento($db);
        if ($sufijoExcel !== '' && $sufijoExcel === $sufijoDb) {
            return true;
        }

        // ponytail: atajo — sufijo corto del Excel al final del número de control en BD.
        return $sufijoExcel !== '' && str_ends_with(preg_replace('/\D/', '', $db) ?? '', $sufijoExcel);
    }

    private static function sufijoNumericoDocumento(string $numero): string
    {
        if (! preg_match('/-([^-]+)$/', $numero, $m)) {
            return ltrim(preg_replace('/\D/', '', $numero) ?? '', '0') ?: '0';
        }

        return ltrim(preg_replace('/\D/', '', $m[1]) ?? '', '0') ?: '0';
    }

    private function defaultExcelPath(): string
    {
        return dirname(base_path()).'/Cuentas por Cobrar 2026 . SISTEMA.xlsx';
    }

    private function resolverHojaExcel(Spreadsheet $workbook, int $mes, int $anio): string
    {
        $hojaForzada = trim((string) $this->option('hoja'));
        if ($hojaForzada !== '') {
            if (! $workbook->sheetNameExists($hojaForzada)) {
                throw new \InvalidArgumentException(sprintf(
                    'La hoja "%s" no existe. Disponibles: %s',
                    $hojaForzada,
                    implode(', ', $workbook->getSheetNames())
                ));
            }

            return $hojaForzada;
        }

        $nombreHojaPreferida = self::nombreHojaMes($mes, $anio);
        if ($workbook->sheetNameExists($nombreHojaPreferida)) {
            return $nombreHojaPreferida;
        }

        return $workbook->getSheet(0)->getTitle();
    }

    /**
     * @return list<array{numero_doc: string, cliente: string, total: float, fecha: string}>
     */
    private function leerFilasPendientesExcel(Worksheet $sheet, int $mes, int $anio): array
    {
        $filas = [];
        $maxRow = (int) $sheet->getHighestDataRow();

        for ($row = 4; $row <= $maxRow; $row++) {
            $numeroDoc = trim((string) $sheet->getCellByColumnAndRow(3, $row)->getCalculatedValue());
            if ($numeroDoc === '') {
                continue;
            }

            $valorFecha = $sheet->getCellByColumnAndRow(10, $row)->getCalculatedValue();
            if (! self::filaFechaEnMes($valorFecha, $mes, $anio)) {
                continue;
            }

            $cliente = trim((string) $sheet->getCellByColumnAndRow(5, $row)->getCalculatedValue());
            $total = (float) $sheet->getCellByColumnAndRow(6, $row)->getCalculatedValue();
            $fecha = self::parsearFechaExcel($valorFecha);

            $filas[] = [
                'numero_doc' => $numeroDoc,
                'cliente' => $cliente,
                'total' => round($total, 2),
                'fecha' => $fecha?->toDateString() ?? '',
            ];
        }

        return $filas;
    }

    /**
     * @param  Collection<int, Venta>  $pendientes
     * @param  array{numero_doc: string, cliente: string, total: float}  $fila
     */
    private function candidatosPorClienteYTotal(Collection $pendientes, array $fila): Collection
    {
        $nombreExcel = self::normalizarNombre($fila['cliente']);

        return $pendientes->filter(function (Venta $venta) use ($fila, $nombreExcel) {
            if (self::normalizarNombre($venta->nombre_cliente) !== $nombreExcel) {
                return false;
            }

            return self::totalesCoinciden((float) $venta->total, $fila['total']);
        })->values();
    }

    /**
     * @param  Collection<int, Venta>  $pendientes
     * @param  array{numero_doc: string, cliente: string, total: float}  $fila
     */
    private function filaTieneCandidatosAmbiguos(Collection $pendientes, array $fila): bool
    {
        return $this->candidatosPorClienteYTotal($pendientes, $fila)->count() > 1;
    }

    /**
     * Coincide por ID de venta (implícito), nombre de cliente y total; N° DOC si está en BD.
     *
     * @param  Collection<int, Venta>  $pendientes
     * @param  array{numero_doc: string, cliente: string, total: float}  $fila
     * @return Collection<int, Venta>
     */
    private function ventasQueCoincidenConFila(Collection $pendientes, array $fila): Collection
    {
        $candidatos = $this->candidatosPorClienteYTotal($pendientes, $fila);
        if ($candidatos->isEmpty()) {
            return $candidatos;
        }

        $conDocumento = $candidatos->filter(function (Venta $venta) use ($fila) {
            return self::numeroControlCoincide($venta->numero_control, $fila['numero_doc'])
                || self::correlativoCoincideConDoc((int) $venta->correlativo, $fila['numero_doc']);
        });

        if ($conDocumento->isNotEmpty()) {
            return $conDocumento->values();
        }

        if ($candidatos->count() === 1) {
            return $candidatos;
        }

        return collect();
    }
}
