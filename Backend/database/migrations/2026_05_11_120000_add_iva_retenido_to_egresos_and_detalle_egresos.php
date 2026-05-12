<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIvaRetenidoToEgresosAndDetalleEgresos extends Migration
{
    public function up()
    {
        if (Schema::hasTable('egresos') && !Schema::hasColumn('egresos', 'iva_retenido')) {
            Schema::table('egresos', function (Blueprint $table) {
                $table->decimal('iva_retenido', 12, 2)->default(0);
            });
        }

        if (Schema::hasTable('detalle_egresos')) {
            if (!Schema::hasColumn('detalle_egresos', 'iva_retenido')) {
                Schema::table('detalle_egresos', function (Blueprint $table) {
                    $table->decimal('iva_retenido', 12, 2)->default(0);
                });
            }
            if (!Schema::hasColumn('detalle_egresos', 'aplica_retencion_iva')) {
                Schema::table('detalle_egresos', function (Blueprint $table) {
                    $table->boolean('aplica_retencion_iva')->default(false);
                });
            }
        }
    }

    public function down()
    {
        if (Schema::hasTable('egresos') && Schema::hasColumn('egresos', 'iva_retenido')) {
            Schema::table('egresos', function (Blueprint $table) {
                $table->dropColumn('iva_retenido');
            });
        }

        if (Schema::hasTable('detalle_egresos')) {
            if (Schema::hasColumn('detalle_egresos', 'aplica_retencion_iva')) {
                Schema::table('detalle_egresos', function (Blueprint $table) {
                    $table->dropColumn('aplica_retencion_iva');
                });
            }
            if (Schema::hasColumn('detalle_egresos', 'iva_retenido')) {
                Schema::table('detalle_egresos', function (Blueprint $table) {
                    $table->dropColumn('iva_retenido');
                });
            }
        }
    }
}
