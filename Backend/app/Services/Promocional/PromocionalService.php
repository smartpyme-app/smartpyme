<?php

namespace App\Services\Promocional;

use App\Models\Admin\Empresa;
use App\Models\Plan;
use App\Models\Promocional;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PromocionalService
{
    /**
     * Obtiene la configuración de un código promocional desde la base de datos
     *
     * @param string $codigo
     * @param string|null $tipoPlan Tipo de plan para validar planes permitidos
     * @return Promocional|null
     */
    public function obtenerConfiguracionCodigoPromocional(?string $codigo, ?string $tipoPlan = null): ?Promocional
    {
        if (empty($codigo)) {
            return null;
        }

        $codigoUpper = strtoupper(trim($codigo));

        // Buscar el código promocional en la base de datos
        $promocional = Promocional::where('codigo', $codigoUpper)
            ->where('activo', true)
            ->first();

        if (!$promocional) {
            return null;
        }

        // Validar fechas de expiración si están definidas
        $opciones = $promocional->opciones ?? [];
        if (isset($opciones['fecha_expiracion'])) {
            $fechaExpiracion = Carbon::parse($opciones['fecha_expiracion']);
            if (now()->gt($fechaExpiracion)) {
                return null; // Código expirado
            }
        }

        if (isset($opciones['fecha_inicio'])) {
            $fechaInicio = Carbon::parse($opciones['fecha_inicio']);
            if (now()->lt($fechaInicio)) {
                return null; // Código aún no válido
            }
        }

        // Validar planes permitidos si están definidos
        if ($tipoPlan && !empty($promocional->planes_permitidos)) {
            $planesPermitidos = array_map('strtolower', $promocional->planes_permitidos);
            $tipoPlanLower = strtolower($tipoPlan);
            if (!in_array($tipoPlanLower, $planesPermitidos)) {
                return null; // Plan no permitido para este código
            }
        }

        return $promocional;
    }

    /**
     * Aplica las reglas de códigos promocionales a la empresa
     *
     * @param Empresa $empresa
     * @param string|null $codigoPromocional
     * @param float $totalOriginal
     * @param string|null $tipoPlan Tipo de plan para validar planes permitidos
     * @return array ['total' => float, 'campania' => string|null]
     */
    public function aplicarCodigoPromocional(Empresa $empresa, ?string $codigoPromocional, float $totalOriginal, ?string $tipoPlan = null): array
    {
        $total = $totalOriginal;
        $campania = null;

        if (!empty($codigoPromocional)) {
            // Guardar código promocional en la empresa
            $empresa->codigo_promocional = $codigoPromocional;

            // Obtener configuración del código promocional desde la base de datos
            $promocional = $this->obtenerConfiguracionCodigoPromocional($codigoPromocional, $tipoPlan);

            if ($promocional) {
                // Aplicar descuento según el tipo
                if ($promocional->tipo === 'porcentaje') {
                    // Descuento por porcentaje (descuento está en formato decimal, ej: 50.00 = 50%)
                    $descuentoDecimal = $promocional->descuento / 100;
                    $total = $totalOriginal * (1 - $descuentoDecimal);
                } elseif ($promocional->tipo === 'monto_fijo') {
                    // Descuento por monto fijo
                    $total = max(0, $totalOriginal - $promocional->descuento);
                }

                // Establecer campaña si está definida
                if ($promocional->campania) {
                    $empresa->campania = $promocional->campania;
                    $campania = $promocional->campania;
                }
            }
        }

        return [
            'total' => $total,
            'campania' => $campania
        ];
    }

    /**
     * Calcula el total original basándose en el plan y tipo de plan
     *
     * @param Plan $plan
     * @param string $tipoPlan
     * @return float
     */
    public function calcularTotalOriginal(Plan $plan, string $tipoPlan): float
    {
        if (!$plan) {
            return 0;
        }

        // Si el plan tiene precio definido, usarlo
        if ($plan->precio) {
            return floatval($plan->precio);
        }

        // Si no tiene precio, calcular basándose en el nombre del plan y tipo
        $planNombre = strtolower($plan->nombre ?? '');
        $esMensual = strtolower($tipoPlan) === 'mensual';

        // Precios por plan (Mensual / Anual)
        $precios = [
            'emprendedor' => ['mensual' => 16.95, 'anual' => 203.4],
            'estándar' => ['mensual' => 28.25, 'anual' => 339],
            'estandar' => ['mensual' => 28.25, 'anual' => 339],
            'avanzado' => ['mensual' => 56.5, 'anual' => 678],
            'pro' => ['mensual' => 113, 'anual' => 1220]
        ];

        foreach ($precios as $nombre => $precio) {
            if (strpos($planNombre, $nombre) !== false) {
                return $esMensual ? $precio['mensual'] : $precio['anual'];
            }
        }

        // Si no se encuentra, usar el precio del plan si existe
        return $plan->precio ?? 0;
    }

    /**
     * Calcula el monto mensual con descuentos aplicados del código promocional
     *
     * @param float $precioMensual
     * @param string|null $codigoPromocional
     * @return float
     */
    public function calcularMontoMensual(float $precioMensual, ?string $codigoPromocional = null): float
    {
        $montoMensual = $precioMensual;

        if (empty($codigoPromocional)) {
            return $montoMensual;
        }

        $promocional = $this->obtenerConfiguracionCodigoPromocional($codigoPromocional, 'Mensual');

        if (!$promocional) {
            return $montoMensual;
        }

        if ($promocional->tipo === 'porcentaje') {
            $descuentoDecimal = $promocional->descuento / 100;
            $montoMensual = $precioMensual * (1 - $descuentoDecimal);
        } elseif ($promocional->tipo === 'monto_fijo') {
            $montoMensual = max(0, $precioMensual - $promocional->descuento);
        }

        return $montoMensual;
    }

    /**
     * Calcula el monto anual con descuentos aplicados (20% de descuento anual + código promocional si aplica)
     *
     * @param float $precioMensual
     * @param string|null $codigoPromocional
     * @return float
     */
    public function calcularMontoAnual(float $precioMensual, ?string $codigoPromocional = null): float
    {
        // Calcular precio anual con 20% de descuento
        $precioAnualSinDescuento = $precioMensual * 12;
        $precioAnualConDescuento20 = $precioAnualSinDescuento * 0.8; // 20% de descuento anual
        $montoAnual = $precioAnualConDescuento20;

        if (empty($codigoPromocional)) {
            return $montoAnual;
        }

        $promocional = $this->obtenerConfiguracionCodigoPromocional($codigoPromocional, 'Anual');

        if (!$promocional) {
            return $montoAnual;
        }

        if ($promocional->tipo === 'porcentaje') {
            $descuentoDecimal = $promocional->descuento / 100;
            $montoAnual = $precioAnualConDescuento20 * (1 - $descuentoDecimal);
        } elseif ($promocional->tipo === 'monto_fijo') {
            $montoAnual = max(0, $precioAnualConDescuento20 - $promocional->descuento);
        }

        return $montoAnual;
    }

    /**
     * Calcula la fecha de fin del período de prueba
     * Si hay código promocional válido, retorna la fecha actual (sin período de prueba)
     * Si no hay código promocional, retorna la fecha actual más los días de duración del plan
     *
     * @param string|null $codigoPromocional
     * @param Plan $plan
     * @param string|null $tipoPlan
     * @return Carbon
     */
    public function calcularFinPeriodoPrueba(?string $codigoPromocional, Plan $plan, ?string $tipoPlan = null): Carbon
    {
        $tieneCodigoPromocional = !empty($codigoPromocional) &&
            $this->obtenerConfiguracionCodigoPromocional($codigoPromocional, $tipoPlan) !== null;

        return $tieneCodigoPromocional
            ? now()
            : now()->addDays($plan->duracion_dias ?? 0);
    }

    /**
     * Calcula la fecha del próximo pago según la frecuencia de pago
     *
     * @param string $frecuenciaPago
     * @return Carbon
     */
    public function calcularFechaProximoPago(string $frecuenciaPago): Carbon
    {
        switch ($frecuenciaPago) {
            case config('constants.FRECUENCIA_PAGO_TRIMESTRAL'):
                return now()->addMonths(3);
            case config('constants.FRECUENCIA_PAGO_ANUAL'):
                return now()->addMonths(12);
            case config('constants.FRECUENCIA_PAGO_MENSUAL'):
            default:
                return now()->addMonth();
        }
    }
}
