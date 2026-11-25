<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class HomeBanner extends Model
{
    public const PAISES_PERMITIDOS = ['SV', 'HN', 'GT', 'CR'];

    protected $table = 'home_banners';

    protected $fillable = [
        'nombre',
        'imagen_desktop',
        'imagen_mobile',
        'paises',
        'url_destino',
        'abrir_nueva_pestana',
        'segundos_visualizacion',
        'orden',
        'activo',
        'publicado_desde',
        'publicado_hasta',
    ];

    protected $casts = [
        'paises' => 'array',
        'abrir_nueva_pestana' => 'boolean',
        'activo' => 'boolean',
        'publicado_desde' => 'datetime',
        'publicado_hasta' => 'datetime',
    ];

    public function scopeVisiblesParaPais(Builder $query, ?string $codPais, ?Carbon $en = null): Builder
    {
        $codPais = strtoupper(trim((string) ($codPais ?: 'SV')));
        $en ??= now();

        return $query
            ->where('activo', true)
            ->where(function (Builder $q) use ($en) {
                $q->whereNull('publicado_desde')->orWhere('publicado_desde', '<=', $en);
            })
            ->where(function (Builder $q) use ($en) {
                $q->whereNull('publicado_hasta')->orWhere('publicado_hasta', '>=', $en);
            })
            ->where(function (Builder $q) use ($codPais) {
                $q->whereJsonLength('paises', 0)
                    ->orWhereJsonContains('paises', $codPais);
            })
            ->orderBy('orden')
            ->orderBy('id');
    }

    /**
     * @param  array<int, string>|null  $paises
     * @return array<int, string>
     */
    public static function normalizarPaises(?array $paises): array
    {
        if ($paises === null || $paises === []) {
            return [];
        }

        $unicos = [];
        foreach ($paises as $codigo) {
            $codigo = strtoupper(trim((string) $codigo));
            if ($codigo !== '' && in_array($codigo, self::PAISES_PERMITIDOS, true)) {
                $unicos[$codigo] = $codigo;
            }
        }

        return array_values($unicos);
    }
}