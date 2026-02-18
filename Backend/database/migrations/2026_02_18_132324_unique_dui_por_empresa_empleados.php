<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        $indexName = $this->getUniqueIndexNameOnColumn('empleados', 'dui');
        if ($indexName !== null) {
            Schema::table('empleados', function (Blueprint $table) use ($indexName) {
                $table->dropIndex($indexName);
            });
        }

        if ($this->compositeUniqueExists('empleados', ['dui', 'id_empresa']) === false) {
            Schema::table('empleados', function (Blueprint $table) {
                $table->unique(['dui', 'id_empresa']);
            });
        }
    }

    public function down()
    {
        $compositeName = $this->getUniqueIndexNameOnColumns('empleados', ['dui', 'id_empresa']);
        if ($compositeName !== null) {
            Schema::table('empleados', function (Blueprint $table) use ($compositeName) {
                $table->dropIndex($compositeName);
            });
        }
        Schema::table('empleados', function (Blueprint $table) {
            $table->unique('dui');
        });
    }

    private function getUniqueIndexNameOnColumn(string $table, string $column): ?string
    {
        $db = Schema::getConnection()->getDatabaseName();
        $row = DB::selectOne(
            "SELECT INDEX_NAME FROM information_schema.STATISTICS 
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? 
             AND NON_UNIQUE = 0 AND INDEX_NAME != 'PRIMARY'
             LIMIT 1",
            [$db, $table, $column]
        );
        return $row->INDEX_NAME ?? null;
    }

    private function getUniqueIndexNameOnColumns(string $table, array $columns): ?string
    {
        $db = Schema::getConnection()->getDatabaseName();
        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        $params = array_merge([$db, $table], $columns);
        $row = DB::selectOne(
            "SELECT INDEX_NAME FROM information_schema.STATISTICS 
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME IN ($placeholders) 
             AND NON_UNIQUE = 0 AND INDEX_NAME != 'PRIMARY'
             GROUP BY INDEX_NAME HAVING COUNT(*) = ?",
            array_merge($params, [count($columns)])
        );
        return $row->INDEX_NAME ?? null;
    }

    private function compositeUniqueExists(string $table, array $columns): bool
    {
        return $this->getUniqueIndexNameOnColumns($table, $columns) !== null;
    }
};
