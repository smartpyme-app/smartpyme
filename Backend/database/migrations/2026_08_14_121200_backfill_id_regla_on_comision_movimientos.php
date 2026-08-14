<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $porEmpresa = DB::table('comision_reglas')
            ->select('id_empresa', DB::raw('MIN(id) as id_regla'))
            ->where('tipo_calculo', 'por_categoria')
            ->where('alcance', 'global')
            ->groupBy('id_empresa')
            ->get();

        foreach ($porEmpresa as $row) {
            DB::table('comision_movimientos')
                ->where('id_empresa', (int) $row->id_empresa)
                ->whereNull('id_regla')
                ->update(['id_regla' => (int) $row->id_regla]);
        }
    }

    public function down(): void
    {
        $porEmpresa = DB::table('comision_reglas')
            ->select('id_empresa', DB::raw('MIN(id) as id_regla'))
            ->where('tipo_calculo', 'por_categoria')
            ->where('alcance', 'global')
            ->groupBy('id_empresa')
            ->get();

        foreach ($porEmpresa as $row) {
            DB::table('comision_movimientos')
                ->where('id_empresa', (int) $row->id_empresa)
                ->where('id_regla', (int) $row->id_regla)
                ->update(['id_regla' => null]);
        }
    }
};
