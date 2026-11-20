<?php

use Illuminate\Database\Migrations\Migration;

/**
 * NEUTRALIZADA (2026-11): no hace nada a propósito.
 *
 * La versión original fusionaba y BORRABA filas duplicadas de `inventario`
 * (producto + bodega) y luego creaba un índice único. Ejecutada por accidente
 * en producción con `php artisan migrate` eliminaba stock de clientes sin
 * respaldo, y el índice único hacía fallar cualquier inserción que hoy
 * genere duplicados.
 *
 * Se deja vacía para que `migrate` la marque como ejecutada y no bloquee
 * las migraciones siguientes. Si algún día se quieren eliminar duplicados,
 * hacerlo con un script SQL revisado (respaldo + verificación + rollback),
 * no con una migración. El código original está en el historial de git.
 */
class AddUniqueInventarioProductoBodegaAndRemoveDuplicates extends Migration
{
    public function up()
    {
        // Intencionalmente vacío. Ver comentario de la clase.
    }

    public function down()
    {
        // Intencionalmente vacío.
    }
}
