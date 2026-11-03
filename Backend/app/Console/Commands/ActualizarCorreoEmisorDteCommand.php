<?php

namespace App\Console\Commands;

use App\Models\Compras\Compra;
use App\Models\Ventas\Venta;
use App\Support\FacturacionElectronica\CostaRicaFeDteDocumento;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ActualizarCorreoEmisorDteCommand extends Command
{
    protected $signature = 'dte:actualizar-correo-emisor
                            {--id-empresa= : ID de la empresa (obligatorio)}
                            {--correo= : Correo correcto del emisor en el JSON (obligatorio)}
                            {--dry-run : Solo mostrar qué se actualizaría, sin escribir}
                            {--table=ventas : ventas, compras o both}
                            {--mes= : Filtrar por mes de la columna fecha (YYYY-MM, ej. 2025-06)}
                            {--desde= : Inicio de rango fecha (Y-m-d); usar junto con --hasta}
                            {--hasta= : Fin de rango fecha (Y-m-d); usar junto con --hasta}
                            {--skip-invalidacion : No revisar dte_invalidacion}
                            {--limit= : Máximo de filas a revisar por tabla}';

    protected $description = 'Corrige emisor.correo en DTE (columna local y/o objeto S3) si no coincide con el correo indicado';

    public function handle(): int
    {
        $idEmpresa = $this->option('id-empresa');
        $correoOpt = $this->option('correo');
        if ($idEmpresa === null || $idEmpresa === '' || $correoOpt === null || trim((string) $correoOpt) === '') {
            $this->error('Indica --id-empresa= y --correo= (correo destino del emisor).');

            return 1;
        }

        $idEmpresa = (int) $idEmpresa;
        $correoObjetivo = self::normalizarCorreo((string) $correoOpt);
        if ($correoObjetivo === '') {
            $this->error('El correo indicado no es válido.');

            return 1;
        }

        $empresa = DB::table('empresas')->where('id', $idEmpresa)->first();
        if (!$empresa) {
            $this->error("No se encontró la empresa con id={$idEmpresa}.");

            return 1;
        }

        $table = strtolower((string) $this->option('table'));
        if (!in_array($table, ['ventas', 'compras', 'both'], true)) {
            $this->error('Opción --table debe ser ventas, compras o both.');

            return 1;
        }

        $rangoFechas = $this->resolveRangoFechasFiltro();
        if ($rangoFechas === false) {
            return 1;
        }

        $dryRun = (bool) $this->option('dry-run');
        $skipInv = (bool) $this->option('skip-invalidacion');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $disk = config('dte.disk', 's3');

        if ($dryRun) {
            $this->warn('Modo dry-run: no se aplicarán cambios.');
        }

        $this->info("Empresa: {$empresa->nombre} (id={$idEmpresa})");
        $this->info("Correo objetivo (emisor): {$correoObjetivo}");
        if ($rangoFechas !== null) {
            $this->info("Rango fecha: {$rangoFechas[0]} … {$rangoFechas[1]}");
        }

        $errores = 0;
        if ($table === 'ventas' || $table === 'both') {
            $errores += $this->processTable(Venta::class, 'ventas', $idEmpresa, $correoObjetivo, $disk, $dryRun, $skipInv, $rangoFechas, $limit);
        }
        if ($table === 'compras' || $table === 'both') {
            $errores += $this->processTable(Compra::class, 'compras', $idEmpresa, $correoObjetivo, $disk, $dryRun, $skipInv, $rangoFechas, $limit);
        }

        return $errores > 0 ? 1 : 0;
    }

    /**
     * @param  class-string<Venta|Compra>  $modelClass
     * @param  array{0: string, 1: string}|null  $rangoFechas
     */
    protected function processTable(
        string $modelClass,
        string $label,
        int $idEmpresa,
        string $correoObjetivo,
        string $disk,
        bool $dryRun,
        bool $skipInv,
        ?array $rangoFechas,
        ?int $limit
    ): int {
        $stats = [
            'revisados' => 0,
            'actualizados' => 0,
            'ya_correctos' => 0,
            'sin_dte' => 0,
            'omitidos_xml' => 0,
            'errores' => 0,
        ];

        $q = $modelClass::query()
            ->withoutGlobalScopes()
            ->where('id_empresa', $idEmpresa)
            ->where(function ($w) use ($skipInv) {
                $w->where(function ($inner) {
                    $inner->whereNotNull('dte')->where('dte', '!=', '')
                        ->orWhereNotNull('dte_s3_key');
                });
                if (!$skipInv) {
                    $w->orWhere(function ($inner) {
                        $inner->whereNotNull('dte_invalidacion')->where('dte_invalidacion', '!=', '')
                            ->orWhereNotNull('dte_invalidacion_s3_key');
                    });
                }
            })
            ->orderBy('id');

        if ($rangoFechas !== null) {
            $q->whereBetween('fecha', [$rangoFechas[0], $rangoFechas[1]]);
        }

        $total = (clone $q)->count();
        $this->newLine();
        $this->info("Tabla {$label}: {$total} registro(s) a revisar");

        $rowsProcessed = 0;
        $q->chunkById(50, function ($rows) use (
            &$stats,
            &$rowsProcessed,
            $correoObjetivo,
            $disk,
            $dryRun,
            $skipInv,
            $label,
            $limit
        ) {
            foreach ($rows as $row) {
                if ($limit !== null && $limit > 0 && $rowsProcessed >= $limit) {
                    return false;
                }
                $rowsProcessed++;
                $stats['revisados']++;

                $cols = [['json' => 'dte', 'key' => 'dte_s3_key', 'name' => 'dte']];
                if (!$skipInv) {
                    $cols[] = ['json' => 'dte_invalidacion', 'key' => 'dte_invalidacion_s3_key', 'name' => 'dte_invalidacion'];
                }

                foreach ($cols as $col) {
                    $result = $this->processColumn($row, $col['json'], $col['key'], $disk, $correoObjetivo, $dryRun);
                    if ($result === 'updated') {
                        $stats['actualizados']++;
                        $this->line("Actualizado {$label}#{$row->id} [{$col['name']}]");
                    } elseif ($result === 'ok') {
                        $stats['ya_correctos']++;
                    } elseif ($result === 'absent') {
                        $stats['sin_dte']++;
                    } elseif ($result === 'xml') {
                        $stats['omitidos_xml']++;
                    } else {
                        $stats['errores']++;
                    }
                }
            }
        });

        $this->info("Resumen {$label}:");
        $this->line("  Registros revisados: {$stats['revisados']}");
        $this->line("  Documentos actualizados: {$stats['actualizados']}");
        $this->line("  Ya tenían el correo correcto: {$stats['ya_correctos']}");
        $this->line("  Sin DTE en esa columna: {$stats['sin_dte']}");
        $this->line("  Omitidos (XML Costa Rica): {$stats['omitidos_xml']}");
        $this->line("  Errores: {$stats['errores']}");

        return $stats['errores'];
    }

    /**
     * @param  Venta|Compra  $row
     * @return 'updated'|'ok'|'absent'|'xml'|'error'
     */
    protected function processColumn(
        Model $row,
        string $jsonCol,
        string $keyCol,
        string $disk,
        string $correoObjetivo,
        bool $dryRun
    ): string {
        $raw = $row->getRawOriginal($jsonCol);
        $s3Key = $row->getRawOriginal($keyCol);

        $hasDb = $raw !== null && $raw !== '';
        $hasS3 = !empty($s3Key);
        if (!$hasDb && !$hasS3) {
            return 'absent';
        }

        $updated = false;
        $hadOk = false;
        $hadXml = false;
        $hadError = false;

        if ($hasDb) {
            $outcome = $this->applyToPayload($raw, $correoObjetivo);
            if ($outcome === 'xml') {
                $hadXml = true;
            } elseif ($outcome === 'error') {
                $hadError = true;
            } elseif ($outcome['changed']) {
                if ($dryRun) {
                    $this->line("[dry-run] {$jsonCol} en BD {$row->getTable()}#{$row->id}: -> {$correoObjetivo}");
                    $updated = true;
                } else {
                    try {
                        DB::table($row->getTable())->where('id', $row->id)->update([
                            $jsonCol => $outcome['encoded'],
                        ]);
                        $updated = true;
                    } catch (\Throwable $e) {
                        $this->reportError($row, $jsonCol, 'db', $e);
                        $hadError = true;
                    }
                }
            } else {
                $hadOk = true;
            }
        }

        if ($hasS3) {
            $bytes = $this->readObject($disk, (string) $s3Key);
            if ($bytes === null) {
                $this->error("No se pudo leer S3 {$row->getTable()}#{$row->id} key={$s3Key}");
                $hadError = true;
            } else {
                $outcome = $this->applyToPayload($bytes, $correoObjetivo);
                if ($outcome === 'xml') {
                    $hadXml = true;
                } elseif ($outcome === 'error') {
                    $hadError = true;
                } elseif ($outcome['changed']) {
                    if ($dryRun) {
                        $this->line("[dry-run] S3 {$row->getTable()}#{$row->id} key={$s3Key}: -> {$correoObjetivo}");
                        $updated = true;
                    } else {
                        try {
                            $format = $this->s3ObjectFormat($outcome['encoded']);
                            Storage::disk($disk)->put($s3Key, $outcome['encoded'], [
                                'visibility' => 'private',
                                'ContentType' => $format['contentType'],
                            ]);
                            $updated = true;
                        } catch (\Throwable $e) {
                            $this->reportError($row, $jsonCol, 's3', $e, $s3Key);
                            $hadError = true;
                        }
                    }
                } else {
                    $hadOk = true;
                }
            }
        }

        if ($hadError) {
            return 'error';
        }
        if ($updated) {
            return 'updated';
        }
        if ($hadXml && !$hadOk) {
            return 'xml';
        }

        return 'ok';
    }

    /**
     * @return array{changed: bool, encoded: string}|'xml'|'error'
     */
    protected function applyToPayload(mixed $raw, string $correoObjetivo)
    {
        if (is_string($raw) && CostaRicaFeDteDocumento::esXmlComprobante($raw)) {
            return 'xml';
        }

        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($decoded)) {
            return 'error';
        }

        $patch = self::parchearCorreoEmisor($decoded, $correoObjetivo);
        if (!$patch['changed']) {
            return ['changed' => false, 'encoded' => ''];
        }

        $encoded = json_encode($patch['data'], JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            return 'error';
        }

        return ['changed' => true, 'encoded' => $encoded];
    }

    /**
     * @return array{changed: bool, data: array<string, mixed>}
     */
    public static function parchearCorreoEmisor(array $dte, string $correoObjetivo): array
    {
        $objetivo = self::normalizarCorreo($correoObjetivo);
        $actual = self::normalizarCorreo((string) ($dte['emisor']['correo'] ?? ''));

        if ($actual === $objetivo) {
            return ['changed' => false, 'data' => $dte];
        }

        if (!isset($dte['emisor']) || !is_array($dte['emisor'])) {
            $dte['emisor'] = [];
        }
        $dte['emisor']['correo'] = $objetivo;

        return ['changed' => true, 'data' => $dte];
    }

    public static function normalizarCorreo(string $correo): string
    {
        return strtolower(trim($correo));
    }

    protected function readObject(string $disk, string $key): ?string
    {
        try {
            if (!Storage::disk($disk)->exists($key)) {
                return null;
            }

            return Storage::disk($disk)->get($key);
        } catch (\Throwable $e) {
            Log::error('dte:actualizar-correo-emisor lectura S3', [
                'key' => $key,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array{contentType: string}
     */
    protected function s3ObjectFormat(string $bytes): array
    {
        if (CostaRicaFeDteDocumento::esXmlComprobante($bytes)) {
            return ['contentType' => 'application/xml'];
        }

        return ['contentType' => 'application/json'];
    }

    protected function reportError(Model $row, string $col, string $source, \Throwable $e, ?string $s3Key = null): void
    {
        Log::error('dte:actualizar-correo-emisor falló', [
            'table' => $row->getTable(),
            'id' => $row->id,
            'column' => $col,
            'source' => $source,
            's3_key' => $s3Key,
            'message' => $e->getMessage(),
        ]);
        $this->error("Error {$row->getTable()}#{$row->id} [{$col}/{$source}]: " . $e->getMessage());
    }

    /**
     * @return array{0: string, 1: string}|null|false
     */
    protected function resolveRangoFechasFiltro()
    {
        $mesOpt = $this->option('mes');
        $desdeOpt = $this->option('desde');
        $hastaOpt = $this->option('hasta');

        if ($mesOpt) {
            if ($desdeOpt || $hastaOpt) {
                $this->error('No combines --mes con --desde/--hasta. Usa --mes=YYYY-MM o el par --desde/--hasta.');

                return false;
            }
            try {
                $start = Carbon::createFromFormat('Y-m', (string) $mesOpt)->startOfMonth();

                return [$start->format('Y-m-d'), $start->copy()->endOfMonth()->format('Y-m-d')];
            } catch (\Throwable $e) {
                $this->error('--mes debe ser YYYY-MM (ejemplo: 2025-03).');

                return false;
            }
        }

        if ($desdeOpt || $hastaOpt) {
            if (!$desdeOpt || !$hastaOpt) {
                $this->error('Para rango manual debes indicar --desde=Y-m-d y --hasta=Y-m-d.');

                return false;
            }
            try {
                $desde = Carbon::parse((string) $desdeOpt)->format('Y-m-d');
                $hasta = Carbon::parse((string) $hastaOpt)->format('Y-m-d');
            } catch (\Throwable $e) {
                $this->error('Fechas inválidas en --desde o --hasta.');

                return false;
            }
            if ($desde > $hasta) {
                $this->error('--desde no puede ser posterior a --hasta.');

                return false;
            }

            return [$desde, $hasta];
        }

        return null;
    }
}
