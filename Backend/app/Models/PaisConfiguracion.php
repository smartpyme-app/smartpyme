<?php

namespace App\Models;

use App\Models\Admin\Empresa;
use App\Services\FacturacionElectronica\FacturacionElectronicaCountryResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class PaisConfiguracion extends Model
{
    protected $table = 'pais_configuracion';

    public const MODULO_DOCUMENTOS = 'documentos';

    public const MODULO_PLANILLAS = 'planillas';

    public const MODULO_IMPUESTOS = 'impuestos';

    public const MODULO_MONEDA = 'moneda';

    public const MODULO_IDENTIFICACION = 'identificacion';

    public const MODULO_FACTURACION_ELECTRONICA = 'facturacion_electronica';

    protected $fillable = [
        'pais',
        'modulo',
        'configuracion',
    ];

    protected $casts = [
        'configuracion' => 'array',
    ];

    public function scopeModulo($query, string $modulo)
    {
        return $query->where('modulo', $modulo);
    }

    public function scopePais($query, string $pais)
    {
        return $query->where('pais', strtoupper($pais));
    }

    public static function urlFirmadorParaEmpresa(?Empresa $empresa): string
    {
        return self::urlFirmador(FacturacionElectronicaCountryResolver::resolveCodigoPaisFe($empresa));
    }

    public static function urlFirmador(string $pais): string
    {
        $pais = strtoupper(trim($pais));
        if ($pais === '') {
            $pais = FacturacionElectronicaCountryResolver::CODIGO_EL_SALVADOR;
        }

        if (! Schema::hasTable('pais_configuracion')) {
            throw new \RuntimeException('Falta la tabla pais_configuracion.');
        }

        $row = self::query()
            ->pais($pais)
            ->modulo(self::MODULO_FACTURACION_ELECTRONICA)
            ->first();

        $url = is_array($row?->configuracion) ? ($row->configuracion['url_firmador'] ?? '') : '';
        if (! is_string($url) || trim($url) === '') {
            throw new \RuntimeException('Configure url_firmador para '.$pais.' en configuración de país.');
        }

        return rtrim(trim($url), '/').'/';
    }
}
