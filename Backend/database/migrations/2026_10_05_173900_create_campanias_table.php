<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateCampaniasTable extends Migration
{
    public function up()
    {
        Schema::create('campanias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        $vistos = [];
        $ahora = now();

        foreach ($this->nombresExistentes() as $nombre) {
            $nombre = trim((string) $nombre);
            if ($nombre === '' || mb_strlen($nombre) > 255) {
                continue;
            }
            $clave = mb_strtolower($nombre);
            if (isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;
            DB::table('campanias')->insert([
                'nombre' => $nombre,
                'activo' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }

    public function down()
    {
        Schema::dropIfExists('campanias');
    }

    private function nombresExistentes(): array
    {
        $nombres = [];

        foreach (['empresas', 'promocionales'] as $tabla) {
            if (!Schema::hasTable($tabla) || !Schema::hasColumn($tabla, 'campania')) {
                continue;
            }
            $nombres = array_merge(
                $nombres,
                DB::table($tabla)
                    ->whereNotNull('campania')
                    ->where('campania', '!=', '')
                    ->distinct()
                    ->pluck('campania')
                    ->all()
            );
        }

        return $nombres;
    }
}
