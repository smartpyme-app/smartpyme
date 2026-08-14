<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->string('dui', 20)->change();
            $table->unsignedTinyInteger('id_type')->nullable()->after('dui');
            $table->decimal('horas_jornada', 5, 2)->nullable()->after('tipo_jornada');
            $table->string('categoria_ocupacional', 50)->nullable()->after('horas_jornada');
            $table->unsignedTinyInteger('tipo_salario')->nullable()->default(1)->after('salario_base');
            $table->boolean('tiene_conyuge_dependiente')->default(false)->after('tipo_salario');
            $table->unsignedTinyInteger('cantidad_hijos_dependientes')->default(0)->after('tiene_conyuge_dependiente');
        });
    }

    public function down(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->dropColumn([
                'id_type',
                'horas_jornada',
                'categoria_ocupacional',
                'tipo_salario',
                'tiene_conyuge_dependiente',
                'cantidad_hijos_dependientes',
            ]);
            $table->string('dui', 10)->change();
        });
    }
};
