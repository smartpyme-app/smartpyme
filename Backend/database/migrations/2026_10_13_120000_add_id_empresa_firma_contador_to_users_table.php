<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('id_empresa_firma_contador')->nullable()->after('id_empresa');
            $table->foreign('id_empresa_firma_contador')->references('id')->on('empresas')->nullOnDelete();
        });

        // ponytail: firma = id_empresa actual si no es una PYME de la cartera del contador
        if (!Schema::hasTable('contador_empresa_accesos')) {
            return;
        }

        $contadorIds = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', config('constants.ROL_ADMIN_CONTADOR', 'admin_contador'))
            ->where('model_has_roles.model_type', 'App\Models\User')
            ->pluck('model_has_roles.model_id');

        foreach ($contadorIds as $userId) {
            $idEmpresa = (int) DB::table('users')->where('id', $userId)->value('id_empresa');
            if (!$idEmpresa) {
                continue;
            }
            $enCartera = DB::table('contador_empresa_accesos')
                ->where('id_usuario_contador', $userId)
                ->where('estado', 'activo')
                ->where('id_empresa', $idEmpresa)
                ->exists();
            if (!$enCartera) {
                DB::table('users')->where('id', $userId)->update(['id_empresa_firma_contador' => $idEmpresa]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['id_empresa_firma_contador']);
            $table->dropColumn('id_empresa_firma_contador');
        });
    }
};
