<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Unique de redención debe colisionar también con id_regla NULL (MySQL/MariaDB: NULL ≠ NULL).
 *
 * MySQL 8+: functional unique (IFNULL(id_regla, 0)).
 * MariaDB 10.11: esa sintaxis es ERROR 1064 — columna generada STORED + unique.
 */
return new class extends Migration
{
    private const INDEX = 'comision_mov_unique_redencion_ifnull';

    private const COLUMN = 'id_regla_ifnull';

    public function up(): void
    {
        $version = $this->databaseVersion();
        $mariaDb = $this->isMariaDb($version);
        if (! $mariaDb && version_compare($this->mysqlVersion($version), '8.0.13', '<')) {
            throw new RuntimeException(
                "La migración requiere MySQL 8.0.13+ para índices funcionales; versión detectada: {$version}."
            );
        }

        Schema::table('comision_movimientos', function (Blueprint $table) {
            $table->dropUnique('comision_mov_unique_redencion_regla');
        });

        if ($mariaDb) {
            $this->upMariaDbGenerated();

            return;
        }

        DB::statement('
            ALTER TABLE comision_movimientos
            ADD UNIQUE INDEX '.self::INDEX.' (id_empresa, id_gift_card_redencion, (IFNULL(id_regla, 0)))
        ');
    }

    public function down(): void
    {
        if ($this->hasIndex(self::INDEX)) {
            DB::statement('DROP INDEX '.self::INDEX.' ON comision_movimientos');
        }

        if (Schema::hasColumn('comision_movimientos', self::COLUMN)) {
            Schema::table('comision_movimientos', function (Blueprint $table) {
                $table->dropColumn(self::COLUMN);
            });
        }

        Schema::table('comision_movimientos', function (Blueprint $table) {
            $table->unique(
                ['id_empresa', 'id_gift_card_redencion', 'id_regla'],
                'comision_mov_unique_redencion_regla'
            );
        });
    }

    private function upMariaDbGenerated(): void
    {
        if (! Schema::hasColumn('comision_movimientos', self::COLUMN)) {
            DB::statement('
                ALTER TABLE comision_movimientos
                ADD COLUMN '.self::COLUMN.' BIGINT UNSIGNED
                    GENERATED ALWAYS AS (IFNULL(id_regla, 0)) STORED
            ');
        }

        DB::statement('
            CREATE UNIQUE INDEX '.self::INDEX.'
            ON comision_movimientos (id_empresa, id_gift_card_redencion, '.self::COLUMN.')
        ');
    }

    private function databaseVersion(): string
    {
        return (string) (DB::selectOne('SELECT VERSION() AS v')->v ?? '');
    }

    private function isMariaDb(string $version): bool
    {
        return stripos($version, 'mariadb') !== false;
    }

    private function mysqlVersion(string $version): string
    {
        preg_match('/\d+\.\d+\.\d+/', $version, $matches);

        return $matches[0] ?? '0.0.0';
    }

    private function hasIndex(string $name): bool
    {
        return collect(DB::select('SHOW INDEX FROM comision_movimientos'))
            ->contains(fn ($i) => $i->Key_name === $name);
    }
};
