<?php

namespace App\Console\Commands;

use App\Models\Compras\Compra;
use App\Models\Ventas\Venta;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MigrateDteToS3Command extends Command
{
    /**
     * @var array<int, string>
     */
    protected static $empresaPathSegmentCache = [];

    protected $signature = 'dte:migrate-to-s3
                            {--dry-run : No escribe en S3 ni actualiza la base de datos}
                            {--limit= : Máximo de filas (registros) a revisar por tabla}
                            {--table=both : ventas, compras o both}
                            {--skip-invalidacion : No migrar columnas dte_invalidacion}
                            {--mes= : Filtrar por mes de la columna fecha (formato YYYY-MM, ej. 2025-06)}
                            {--desde= : Inicio de rango fecha (Y-m-d); usar junto con --hasta}
                            {--hasta= : Fin de rango fecha (Y-m-d); usar junto con --desde}';

    protected $description = 'Sube JSON de DTE desde compras/ventas a S3 y vacía la columna local.';

    public function handle(): int
    {
        $disk = config('dte.disk', 's3');
        $dry = (bool) $this->option('dry-run');
        $bucket = config('filesystems.disks.' . $disk . '.bucket');
        if (empty($bucket) && !$dry) {
            $this->error('El bucket S3 no está configurado (variable AWS_BUCKET / disco ' . $disk . ').');

            return 1;
        }

        $table = strtolower((string) $this->option('table'));
        if (!in_array($table, ['both', 'ventas', 'compras'], true)) {
            $this->error('Opción --table debe ser ventas, compras o both.');

            return 1;
        }

        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $skipInv = (bool) $this->option('skip-invalidacion');

        $rangoFechas = $this->resolveRangoFechasFiltro();
        if ($rangoFechas === false) {
            return 1;
        }

        self::$empresaPathSegmentCache = [];

        $total = 0;
        if ($table === 'both' || $table === 'ventas') {
            $total += $this->processModel(Venta::class, 'ventas', $disk, $dry, $limit, $skipInv, $rangoFechas);
        }
        if ($table === 'both' || $table === 'compras') {
            $total += $this->processModel(Compra::class, 'compras', $disk, $dry, $limit, $skipInv, $rangoFechas);
        }

        $this->info('Proceso finalizado. Registros actualizados (aprox.): ' . $total);

        return 0;
    }

    /**
     * @param  class-string<Venta|Compra>  $class
     * @param  array{0: string, 1: string}|null  $rangoFechas [desde, hasta] inclusive en columna fecha
     */
    protected function processModel(string $class, string $table, string $disk, bool $dry, ?int $limit, bool $skipInv, ?array $rangoFechas): int
    {
        $q = $class::query()->withoutGlobalScopes();
        $updated = 0;

        $q->where(function ($w) {
            $w->whereNotNull('dte')->where('dte', '!=', '')
                ->orWhere(function ($w2) {
                    $w2->whereNotNull('dte_invalidacion')->where('dte_invalidacion', '!=', '');
                });
        });

        if ($rangoFechas !== null) {
            $q->whereBetween('fecha', [$rangoFechas[0], $rangoFechas[1]]);
            $this->line("Filtro fecha: {$rangoFechas[0]} … {$rangoFechas[1]}");
        }

        $q->orderBy('id');

        $this->info('Procesando tabla: ' . $table);

        $rowsProcessed = 0;
        $q->chunkById(50, function ($rows) use (&$updated, &$rowsProcessed, $disk, $dry, $skipInv, $table, $limit) {
            foreach ($rows as $row) {
                if ($limit !== null && $limit > 0 && $rowsProcessed >= $limit) {
                    return false;
                }
                $rowsProcessed++;

                $main = $this->migrateColumn(
                    $row,
                    'dte',
                    'dte_s3_key',
                    'dte_migrated_at',
                    $disk,
                    $dry,
                    $table
                );
                $updated += $main ? 1 : 0;

                if (!$skipInv) {
                    $inv = $this->migrateColumn(
                        $row,
                        'dte_invalidacion',
                        'dte_invalidacion_s3_key',
                        'dte_invalidacion_migrated_at',
                        $disk,
                        $dry,
                        $table
                    );
                    $updated += $inv ? 1 : 0;
                }
            }
        });

        return $updated;
    }

    /**
     * @param  Venta|Compra  $row
     */
    protected function migrateColumn($row, string $jsonCol, string $keyCol, string $migratedCol, string $disk, bool $dry, string $table): bool
    {
        $raw = $row->getRawOriginal($jsonCol);
        if ($raw === null || $raw === '') {
            return false;
        }
        if (!empty($row->getRawOriginal($keyCol))) {
            return false;
        }

        $path = $this->buildObjectKey($table, $row, $jsonCol);

        if ($dry) {
            $this->line("[dry-run] {$table}#{$row->id} -> {$path}");

            return false;
        }

        $bytes = is_string($raw) ? $raw : json_encode($raw);
        if ($bytes === false || $bytes === '') {
            return false;
        }

        try {
            Storage::disk($disk)->put($path, $bytes, [
                'visibility' => 'private',
                'ContentType' => 'application/json',
            ]);
        } catch (\Throwable $e) {
            Log::error('dte:migrate-to-s3 put falló', [
                'table' => $table,
                'id' => $row->id,
                'column' => $jsonCol,
                'message' => $e->getMessage(),
            ]);
            $this->warn("Fallo S3 {$table} id={$row->id} col={$jsonCol}: " . $e->getMessage());

            return false;
        }

        $migratedAt = now();
        DB::table($table)->where('id', $row->id)->update([
            $keyCol => $path,
            $migratedCol => $migratedAt,
            $jsonCol => null,
        ]);

        $attrs = $row->getAttributes();
        $attrs[$keyCol] = $path;
        $attrs[$migratedCol] = $migratedAt;
        $attrs[$jsonCol] = null;
        $row->setRawAttributes($attrs);
        $row->syncOriginal();

        $this->line("Migrado {$table}#{$row->id} {$jsonCol} -> {$path}");

        return true;
    }

    /**
     * Ruta en S3 alineada al bucket: ventas/ o compras/, empresa, año/mes, archivo.
     * Ej.: ventas/12-mi-empresa-slug/2026/05/registro-88421-documento.json
     *
     * @param  Venta|Compra  $row
     */
    protected function buildObjectKey(string $table, $row, string $column): string
    {
        $id = (int) $row->id;
        $idEmpresa = (int) ($row->getAttribute('id_empresa') ?? 0);
        $empresaSegment = $this->empresaPathSegment($idEmpresa);

        $fecha = $row->getAttribute('fecha');
        try {
            $c = $fecha ? Carbon::parse($fecha) : Carbon::now();
        } catch (\Throwable $e) {
            $c = Carbon::now();
        }
        $year = $c->format('Y');
        $month = $c->format('m');

        $tipoArchivo = $column === 'dte_invalidacion' ? 'invalidacion' : 'documento';
        $prefijoRaiz = $table === 'ventas' ? 'ventas' : 'compras';

        return $prefijoRaiz . '/' . $empresaSegment . '/' . $year . '/' . $month . '/registro-' . $id . '-' . $tipoArchivo . '.json';
    }

    protected function empresaPathSegment(int $idEmpresa): string
    {
        if ($idEmpresa <= 0) {
            return '0_sin-empresa';
        }
        if (isset(self::$empresaPathSegmentCache[$idEmpresa])) {
            return self::$empresaPathSegmentCache[$idEmpresa];
        }

        $nombre = '';
        try {
            $row = DB::table('empresas')->where('id', $idEmpresa)->value('nombre');
            $nombre = is_string($row) ? $row : '';
        } catch (\Throwable $e) {
            Log::warning('dte:migrate-to-s3 no se pudo leer nombre de empresa', [
                'id_empresa' => $idEmpresa,
                'message' => $e->getMessage(),
            ]);
        }

        $slug = Str::slug($nombre !== '' ? $nombre : 'sin-nombre', '-');
        if ($slug === '') {
            $slug = 'sin-nombre';
        }
        if (strlen($slug) > 72) {
            $slug = substr($slug, 0, 72);
        }

        $segment = $idEmpresa . '-' . $slug;
        self::$empresaPathSegmentCache[$idEmpresa] = $segment;

        return $segment;
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
                $this->error('No combines --mes con --desde/--hasta. Usa solo --mes=YYYY-MM o el par --desde/--hasta.');

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
