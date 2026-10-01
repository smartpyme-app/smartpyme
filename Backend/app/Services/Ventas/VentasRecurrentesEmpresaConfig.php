<?php

namespace App\Services\Ventas;

use App\Models\Admin\Empresa;
use App\Services\Admin\EmpresaConfiguracionService;

/** Preferencias de ventas recurrentes automáticas (Mi cuenta → Preferencias). */
final class VentasRecurrentesEmpresaConfig
{
    public const KEY_ACTIVO = 'ventas_recurrentes_automaticas_activo';

    public const KEY_CORREO = 'ventas_recurrentes_correo_resumen';

    public const KEY_GENERACION_ACTIVA = 'ventas_recurrentes_generacion_activa';

    /** @deprecated lectura legacy; al guardar se migra a KEY_GENERACION_ACTIVA */
    private const KEY_PAUSADA_LEGACY = 'ventas_recurrentes_generacion_pausada';

    public static function activo(Empresa $empresa): bool
    {
        return (bool) $empresa->getCustomConfigValue('configuraciones', self::KEY_ACTIVO, false);
    }

    public static function generacionActiva(Empresa $empresa): bool
    {
        $config = $empresa->getCustomConfigValue('configuraciones', null, []);
        if (!is_array($config)) {
            return true;
        }
        if (array_key_exists(self::KEY_GENERACION_ACTIVA, $config)) {
            return (bool) $config[self::KEY_GENERACION_ACTIVA];
        }
        if (array_key_exists(self::KEY_PAUSADA_LEGACY, $config)) {
            return !(bool) $config[self::KEY_PAUSADA_LEGACY];
        }

        return true;
    }

    public static function correoResumen(Empresa $empresa): string
    {
        $correo = trim((string) $empresa->getCustomConfigValue('configuraciones', self::KEY_CORREO, ''));

        return $correo !== '' ? $correo : trim((string) $empresa->correo);
    }

    /** @return array{activo: bool, correo_resumen: string, generacion_activa: bool} */
    public static function preferencias(Empresa $empresa): array
    {
        return [
            'activo' => self::activo($empresa),
            'correo_resumen' => trim((string) $empresa->getCustomConfigValue('configuraciones', self::KEY_CORREO, ''))
                ?: trim((string) $empresa->correo),
            'generacion_activa' => self::generacionActiva($empresa),
        ];
    }

    public static function guardarPreferencias(Empresa $empresa, bool $activo, string $correoResumen, bool $generacionActiva): void
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
        $current[self::KEY_GENERACION_ACTIVA] = $generacionActiva;
        unset($current[self::KEY_PAUSADA_LEGACY]);
        $service->set((int) $empresa->id, 'configuraciones', $current, $pais);
    }
}
