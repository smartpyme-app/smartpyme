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
     * Si no hay mapeo (p. ej. categoría o tipo custom), clasifica como OTROS_GASTOS.
     */
    public static function resolver(int $idEmpresa, ?string $tipo, ?int $idCategoria = null): ?self
    {
        $tipo = trim((string) $tipo);
        if ($tipo !== '' || $idCategoria) {
            $filas = static::with('categoria')
                ->whereIn('id_empresa', [0, $idEmpresa])
                ->where(function ($q) use ($tipo, $idCategoria) {
                    if ($tipo !== '') {
                        $q->where(fn ($t) => $t->where('origen', 'tipo')->where('clave', $tipo));
                    }
                    if ($idCategoria) {
                        $method = $tipo !== '' ? 'orWhere' : 'where';
                        $q->$method(fn ($c) => $c->where('origen', 'categoria')->where('clave', (string) $idCategoria));
                    }
                })
                ->get();

            $resuelto = $filas->sortByDesc(
                fn (self $f) => $f->id_empresa === 0 ? 1 : ($f->origen === 'categoria' ? 3 : 2)
            )->first();

            if ($resuelto) {
                return $resuelto;
            }
        }

        $otros = NifCategoria::where('codigo', 'OTROS_GASTOS')->first();
        if (! $otros) {
            return null;
        }

        $fallback = new static([
            'id_empresa' => 0,
            'origen' => $idCategoria ? 'categoria' : 'tipo',
            'clave' => $idCategoria ? (string) $idCategoria : $tipo,
            'id_nif_categoria' => $otros->id,
            'revisar' => false,
        ]);
        $fallback->setRelation('categoria', $otros);

        return $fallback;
    }
}
