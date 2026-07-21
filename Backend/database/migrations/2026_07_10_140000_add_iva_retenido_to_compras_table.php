<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIvaRetenidoToComprasTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('compras') && !Schema::hasColumn('compras', 'iva_retenido')) {
            Schema::table('compras', function (Blueprint $table) {
                $table->decimal('iva_retenido', 12, 2)->default(0)->after('renta_retenida');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('compras') && Schema::hasColumn('compras', 'iva_retenido')) {
            Schema::table('compras', function (Blueprint $table) {
                $table->dropColumn('iva_retenido');
            });
        }
    }
}
