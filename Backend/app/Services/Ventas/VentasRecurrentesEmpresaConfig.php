<?php

namespace App\Services\Ventas;

use App\Models\Admin\Empresa;
use App\Services\Admin\EmpresaConfiguracionService;

/** Preferencias de ventas recurrentes automáticas (Mi cuenta → Preferencias). */
final class VentasRecurrentesEmpresaConfig
{
    public const KEY_ACTIVO = 'ventas_recurrentes_automaticas_activo';

    public const KEY_CORREO = 'ventas_recurrentes_correo_resumen';

    public const KEY_PAUSADA = 'ventas_recurrentes_generacion_pausada';

    public static function activo(Empresa $empresa): bool
    {
        return (bool) $empresa->getCustomConfigValue('configuraciones', self::KEY_ACTIVO, false);
    }

    public static function generacionPausada(Empresa $empresa): bool
    {
        return (bool) $empresa->getCustomConfigValue('configuraciones', self::KEY_PAUSADA, false);
    }

    public static function correoResumen(Empresa $empresa): string
    {
        $correo = trim((string) $empresa->getCustomConfigValue('configuraciones', self::KEY_CORREO, ''));

        return $correo !== '' ? $correo : trim((string) $empresa->correo);
    }

    /** @return array{activo: bool, correo_resumen: string, generacion_pausada: bool} */
    public static function preferencias(Empresa $empresa): array
    {
        return [
            'activo' => self::activo($empresa),
            'correo_resumen' => trim((string) $empresa->getCustomConfigValue('configuraciones', self::KEY_CORREO, ''))
                ?: trim((string) $empresa->correo),
            'generacion_pausada' => self::generacionPausada($empresa),
        ];
    }

    public static function guardarPreferencias(Empresa $empresa, bool $activo, string $correoResumen, bool $generacionPausada): void
    {
        $service = app(EmpresaConfiguracionService::class);
        $pais = strtoupper($empresa->cod_pais ?: 'SV');
        $current = $service->get((int) $empresa->id, 'configuraciones', $pais);

        if ($current === null) {
            $legacy = $empresa->custom_config['configuraciones'] ?? [];
            $current = is_array($legacy) ? $legacy : [];
        }

        $current[self::KEY_ACTIVO] = $activo;
        $current[self::KEY_CORREO] = trim($correoResumen);
        $current[self::KEY_PAUSADA] = $generacionPausada;
        $service->set((int) $empresa->id, 'configuraciones', $current, $pais);
    }
}
