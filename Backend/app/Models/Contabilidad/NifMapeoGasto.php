<?php

namespace App\Models\Contabilidad;

use Illuminate\Database\Eloquent\Model;

class NifMapeoGasto extends Model
{
    protected $table = 'nif_mapeo_gastos';

    protected $fillable = ['id_empresa', 'origen', 'clave', 'id_nif_categoria', 'revisar'];

    protected $casts = ['revisar' => 'boolean'];

    public function categoria()
    {
        return $this->belongsTo(NifCategoria::class, 'id_nif_categoria');
    }

    /**
     * Categoría NIIF de un gasto. Gana lo más específico de la empresa:
     * su categoría de gasto, luego su tipo, luego el mapeo base de SmartPyme.
     * Sin fila devuelve null: no se adivina, el gasto queda «sin clasificar».
     */
    public static function resolver(int $idEmpresa, ?string $tipo, ?int $idCategoria = null): ?self
    {
        $tipo = trim((string) $tipo);
        $filas = static::with('categoria')
            ->whereIn('id_empresa', [0, $idEmpresa])
            ->where(function ($q) use ($tipo, $idCategoria) {
                $q->where(fn ($t) => $t->where('origen', 'tipo')->where('clave', $tipo));
                if ($idCategoria) {
                    $q->orWhere(fn ($c) => $c->where('origen', 'categoria')->where('clave', (string) $idCategoria));
                }
            })
            ->get();

        return $filas->sortByDesc(
            fn (self $f) => $f->id_empresa === 0 ? 1 : ($f->origen === 'categoria' ? 3 : 2)
        )->first();
    }
}
