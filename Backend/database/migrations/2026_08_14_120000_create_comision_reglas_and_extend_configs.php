<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comision_reglas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa');
            $table->string('nombre');
            $table->string('tipo_calculo', 32);
            $table->string('alcance', 32)->default('global');
            $table->json('id_vendedores')->nullable();
            $table->string('momento_devengo', 32)->default('al_pagar');
            $table->boolean('reemplaza_global')->default(false);
            $table->json('config')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->index(['id_empresa', 'activo']);
        });

        Schema::table('comision_categoria_config', function (Blueprint $table) {
            $table->unsignedBigInteger('id_regla')->nullable()->after('id_empresa');
        });

        Schema::table('comision_subcategoria_config', function (Blueprint $table) {
            $table->unsignedBigInteger('id_regla')->nullable()->after('id_empresa');
        });

        Schema::table('comision_movimientos', function (Blueprint $table) {
            $table->unsignedBigInteger('id_regla')->nullable()->after('id_periodo');
        });

        Schema::table('comision_liquidaciones', function (Blueprint $table) {
            $table->decimal('salario_base', 14, 4)->default(0)->after('total_comision');
            $table->decimal('ajuste_salario_minimo', 14, 4)->default(0)->after('salario_base');
            $table->decimal('salario_minimo_aplicado', 14, 4)->nullable()->after('ajuste_salario_minimo');
            $table->decimal('total_a_pagar', 14, 4)->default(0)->after('salario_minimo_aplicado');
        });

        $empresaIds = collect()
            ->merge(DB::table('comision_categoria_config')->distinct()->pluck('id_empresa'))
            ->merge(DB::table('comision_subcategoria_config')->distinct()->pluck('id_empresa'))
            ->merge(
                DB::table('empresa_funcionalidades as ef')
                    ->join('funcionalidades as f', 'f.id', '=', 'ef.id_funcionalidad')
                    ->where('f.slug', 'comisiones-vendedores')
                    ->where('ef.activo', true)
                    ->pluck('ef.id_empresa')
            )
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $now = now();
        foreach ($empresaIds as $idEmpresa) {
            $idRegla = DB::table('comision_reglas')->insertGetId([
                'id_empresa' => $idEmpresa,
                'nombre' => 'Por categoría',
                'tipo_calculo' => 'por_categoria',
                'alcance' => 'global',
                'id_vendedores' => null,
                'momento_devengo' => 'al_pagar',
                'reemplaza_global' => false,
                'config' => json_encode(new stdClass()),
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('comision_categoria_config')->where('id_empresa', $idEmpresa)->update(['id_regla' => $idRegla]);
            DB::table('comision_subcategoria_config')->where('id_empresa', $idEmpresa)->update(['id_regla' => $idRegla]);
        }

        Schema::table('comision_categoria_config', function (Blueprint $table) {
            $table->dropUnique(['id_empresa', 'id_categoria']);
            $table->unique(['id_regla', 'id_categoria']);
        });

        Schema::table('comision_subcategoria_config', function (Blueprint $table) {
            $table->dropUnique(['id_empresa', 'id_subcategoria']);
            $table->unique(['id_regla', 'id_subcategoria']);
        });

        DB::table('comision_liquidaciones')->update([
            'total_a_pagar' => DB::raw('total_comision'),
        ]);
    }

    public function down(): void
    {
        $categoriaIdsConservar = DB::table('comision_categoria_config')
            ->selectRaw('MIN(id) as id')
            ->groupBy('id_empresa', 'id_categoria')
            ->pluck('id')
            ->all();

        if ($categoriaIdsConservar !== []) {
            DB::table('comision_categoria_config')
                ->whereNotIn('id', $categoriaIdsConservar)
                ->delete();
        }

        $subcategoriaIdsConservar = DB::table('comision_subcategoria_config')
            ->selectRaw('MIN(id) as id')
            ->groupBy('id_empresa', 'id_subcategoria')
            ->pluck('id')
            ->all();

        if ($subcategoriaIdsConservar !== []) {
            DB::table('comision_subcategoria_config')
                ->whereNotIn('id', $subcategoriaIdsConservar)
                ->delete();
        }

        Schema::table('comision_categoria_config', function (Blueprint $table) {
            $table->dropUnique(['id_regla', 'id_categoria']);
            $table->unique(['id_empresa', 'id_categoria']);
            $table->dropColumn('id_regla');
        });
        Schema::table('comision_subcategoria_config', function (Blueprint $table) {
            $table->dropUnique(['id_regla', 'id_subcategoria']);
            $table->unique(['id_empresa', 'id_subcategoria']);
            $table->dropColumn('id_regla');
        });
        Schema::table('comision_movimientos', function (Blueprint $table) {
            $table->dropColumn('id_regla');
        });
        Schema::table('comision_liquidaciones', function (Blueprint $table) {
            $table->dropColumn(['salario_base', 'ajuste_salario_minimo', 'salario_minimo_aplicado', 'total_a_pagar']);
        });
        Schema::dropIfExists('comision_reglas');
    }
};
