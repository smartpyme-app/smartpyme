<?php

namespace App\Services\MH;

class MHPruebasMasivasOutcome
{
    public static function normalizarGiro(?string $texto): string
    {
        $texto = trim((string) $texto);

        return preg_replace('/\s+/u', ' ', $texto) ?? $texto;
    }

    public static function giroCoincideConCatalogo(?string $giro, ?string $nombreCatalogo): bool
    {
        if ($giro === null || $nombreCatalogo === null || $giro === '' || $nombreCatalogo === '') {
            return false;
        }

        return self::normalizarGiro($giro) === self::normalizarGiro($nombreCatalogo);
    }

    public static function mensajeGiroInvalido(?string $codActividad, ?string $giro, ?string $nombreCatalogo): string
    {
        if (!$codActividad) {
            return 'La empresa no tiene código de actividad económica. Seleccione la actividad en Empresa y guarde antes de ejecutar las pruebas masivas.';
        }

        if (!$giro) {
            return 'La empresa no tiene giro (descActividad). Vuelva a seleccionar la actividad económica en Empresa y guarde.';
        }

        if (!$nombreCatalogo) {
            return "El código de actividad económica {$codActividad} no existe en el catálogo. Seleccione una actividad válida en Empresa.";
        }

        return 'El giro de la empresa no coincide con el catálogo de Hacienda (descActividad). '
            . "Código {$codActividad}. Giro actual: \"{$giro}\". Valor de catálogo: \"{$nombreCatalogo}\". "
            . 'Vuelva a seleccionar la actividad económica en Empresa, guarde y emita una factura de prueba antes de relanzar las pruebas masivas.';
    }

    public static function esFalloTotal(int $exitosos, int $fallidos): bool
    {
        return $exitosos <= 0;
    }

    public static function esExitoCompleto(int $exitosos, int $fallidos): bool
    {
        return $exitosos > 0 && $fallidos === 0;
    }

    public static function esRechazoEmisorIrrecuperable(?string $mensaje): bool
    {
        if (!$mensaje) {
            return false;
        }

        return mb_stripos($mensaje, 'descActividad') !== false
            || mb_stripos($mensaje, '#/emisor/') !== false
            || mb_stripos($mensaje, 'codActividad') !== false;
    }

    public static function asuntoCorreo(string $tipoTexto, int $exitosos, int $fallidos): string
    {
        if (self::esFalloTotal($exitosos, $fallidos)) {
            return 'Error en Pruebas Masivas MH: ' . $tipoTexto;
        }

        if ($fallidos > 0) {
            return 'Pruebas Masivas MH finalizadas con errores: ' . $tipoTexto;
        }

        return 'Pruebas Masivas MH Completadas: ' . $tipoTexto;
    }

    public static function resumenFallo(array $resultados, int $cantidadSolicitada): string
    {
        $exitosos = $resultados['exitosos'] ?? 0;
        $fallidos = $resultados['fallidos'] ?? 0;
        $primero = '';

        foreach ($resultados['detalles'] ?? [] as $detalle) {
            if (($detalle['status'] ?? '') === 'Error') {
                $primero = (string) ($detalle['message'] ?? '');
                break;
            }
        }

        $resumen = "Hacienda no aceptó las pruebas. Emitidos: {$exitosos}, rechazados: {$fallidos}, solicitados: {$cantidadSolicitada}.";

        if ($primero !== '') {
            $resumen .= ' Detalle: ' . $primero;
        }

        if (self::esRechazoEmisorIrrecuperable($primero) || !empty($resultados['detenido_por_emisor'])) {
            $resumen .= ' Revise el giro y la actividad económica de la empresa (deben coincidir con el catálogo de Hacienda) y vuelva a guardar antes de reintentar.';
        }

        if (!empty($resultados['detenido_por_emisor'])) {
            $resumen .= ' Se detuvo el resto del lote para no enviar documentos que Hacienda rechazaría por el mismo motivo.';
        }

        return $resumen;
    }
}
