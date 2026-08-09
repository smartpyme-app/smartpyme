<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Segunda línea de defensa: una sola sesión activa (abierta|pre_cuenta) por mesa.
 *
 * Solución FINAL validada en MariaDB 10.11.18 (Docker, 2026-08-09):
 *   mesa_sesion_activa_id GENERATED ALWAYS AS (...) STORED + UNIQUE
 *
 * El functional unique index de MySQL 8+/9:
 *   CREATE UNIQUE INDEX ... ((CASE WHEN ...))
 * NO es sintaxis válida en MariaDB 10.11 (ERROR 1064).
 *
 * En MySQL 9.x local, GENERATED STORED sobre columna con FK puede fallar (1215);
 * por eso hay fallback a functional index SOLO si el motor no es MariaDB.
 */
return new class extends Migration
{
    private const INDEX = 'uq_restaurante_mesa_sesion_activa';

    private const COLUMN = 'mesa_sesion_activa_id';

    public function up(): void
    {
        if (! Schema::hasTable('restaurante_sesiones_mesa')) {
            return;
        }

        $dups = DB::select('
            SELECT mesa_id, COUNT(*) AS n
            FROM restaurante_sesiones_mesa
            WHERE estado IN (\'abierta\', \'pre_cuenta\')
            GROUP BY mesa_id
            HAVING n > 1
        ');

        if (count($dups) > 0) {
            $ids = collect($dups)->pluck('mesa_id')->implode(', ');
            throw new \RuntimeException(
                'No se puede crear ' . self::INDEX . ": hay sesiones activas duplicadas para mesa_id in ({$ids}). Resolver antes de migrar."
            );
        }

        if ($this->isMariaDb()) {
            $this->upMariaDbGenerated();
        } else {
            $this->upMysqlFunctionalIndex();
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('restaurante_sesiones_mesa')) {
            return;
        }

        if ($this->hasIndex(self::INDEX)) {
            DB::statement('DROP INDEX ' . self::INDEX . ' ON restaurante_sesiones_mesa');
        }

        if (Schema::hasColumn('restaurante_sesiones_mesa', self::COLUMN)) {
            DB::statement('ALTER TABLE restaurante_sesiones_mesa DROP COLUMN ' . self::COLUMN);
        }
    }

    private function upMariaDbGenerated(): void
    {
        if (! Schema::hasColumn('restaurante_sesiones_mesa', self::COLUMN)) {
            DB::statement('
                ALTER TABLE restaurante_sesiones_mesa
                ADD COLUMN ' . self::COLUMN . ' BIGINT UNSIGNED
                    GENERATED ALWAYS AS (
                        IF(estado IN (\'abierta\', \'pre_cuenta\'), mesa_id, NULL)
                    ) STORED
            ');
        }

        if (! $this->hasIndex(self::INDEX)) {
            DB::statement('
                CREATE UNIQUE INDEX ' . self::INDEX . '
                ON restaurante_sesiones_mesa (' . self::COLUMN . ')
            ');
        }
    }

    /**
     * Fallback solo para entornos de desarrollo MySQL 8+/9 donde GENERATED+FK falla (1215).
     * Producción Smartpyme = MariaDB 10.11 → usa upMariaDbGenerated().
     */
    private function upMysqlFunctionalIndex(): void
    {
        // Si quedó un intento de columna generada fallido/parcial, limpiar.
        if (Schema::hasColumn('restaurante_sesiones_mesa', self::COLUMN)) {
            DB::statement('ALTER TABLE restaurante_sesiones_mesa DROP COLUMN ' . self::COLUMN);
        }

        if ($this->hasIndex(self::INDEX)) {
            return;
        }

        DB::statement('
            CREATE UNIQUE INDEX ' . self::INDEX . '
            ON restaurante_sesiones_mesa (
                (CASE WHEN estado IN (\'abierta\', \'pre_cuenta\') THEN mesa_id ELSE NULL END)
            )
        ');
    }

    private function isMariaDb(): bool
    {
        $v = (string) (DB::selectOne('SELECT VERSION() AS v')->v ?? '');

        return stripos($v, 'mariadb') !== false;
    }

    private function hasIndex(string $name): bool
    {
        return collect(DB::select('SHOW INDEX FROM restaurante_sesiones_mesa'))
            ->contains(fn ($i) => $i->Key_name === $name);
    }
};
