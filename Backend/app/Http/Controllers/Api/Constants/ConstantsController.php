<?php

namespace App\Http\Controllers\Api\Constants;

use App\Constants\PlanillaConstants;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use ReflectionClass;

class ConstantsController extends Controller
{

    public function getAppConstants() {


        $appConstants = [
            'planilla' => $this->getPlanillaConstants(),
            // 'ventas' => $this->getVentasConstants(), ejmplo
        ];

        return response()->json($appConstants);

    }

    private function getPlanillaConstants()
    {
        $reflection = new ReflectionClass(PlanillaConstants::class);
        $constants = $reflection->getConstants();

        // Organizamos las constantes por categorías
        $organizedConstants = [
            'ESTADOS' => [
                'ACTIVO' => $constants['ESTADO_ACTIVO'],
                'INACTIVO' => $constants['ESTADO_INACTIVO'],
            ],
            'TIPOS_CONTRATO' => [
                'PERMANENTE' => $constants['TIPO_CONTRATO_PERMANENTE'],
                'TEMPORAL' => $constants['TIPO_CONTRATO_TEMPORAL'],
                'POR_OBRA' => $constants['TIPO_CONTRATO_POR_OBRA'],
            ],
            'TIPOS_JORNADA' => [
                'TIEMPO_COMPLETO' => $constants['TIPO_JORNADA_TIEMPO_COMPLETO'],
                'MEDIO_TIEMPO' => $constants['TIPO_JORNADA_MEDIO_TIEMPO'],
            ],
            'ESTADOS_EMPLEADO' => [
                'INACTIVO' => $constants['ESTADO_EMPLEADO_INACTIVO'],
                'ACTIVO' => $constants['ESTADO_EMPLEADO_ACTIVO'],
                'VACACIONES' => $constants['ESTADO_EMPLEADO_VACACIONES'],
                'INCAPACIDAD' => $constants['ESTADO_EMPLEADO_INCAPACIDAD'],
                'SUSPENDIDO' => $constants['ESTADO_EMPLEADO_SUSPENDIDO'],
            ],
            'ESTADOS_PLANILLA' => [
                'INACTIVA' => $constants['PLANILLA_INACTIVA'],
                'ACTIVA' => $constants['PLANILLA_ACTIVA'],
                'BORRADOR' => $constants['PLANILLA_BORRADOR'],
                'PENDIENTE' => $constants['PLANILLA_PENDIENTE'],
                'APROBADA' => $constants['PLANILLA_APROBADA'],
                'PAGADA' => $constants['PLANILLA_PAGADA'],
                'ANULADA' => $constants['PLANILLA_ANULADA'],
            ],
            'DESCUENTOS' => [
                'ISSS_EMPLEADO' => $constants['DESCUENTO_ISSS_EMPLEADO'],
                'ISSS_PATRONO' => $constants['DESCUENTO_ISSS_PATRONO'],
                'AFP_EMPLEADO' => $constants['DESCUENTO_AFP_EMPLEADO'],
                'AFP_PATRONO' => $constants['DESCUENTO_AFP_PATRONO'],
            ],
            'RENTA' => [
                'MINIMA' => $constants['RENTA_MINIMA'],
                'MAXIMA_PRIMER_TRAMO' => $constants['RENTA_MAXIMA_PRIMER_TRAMO'],
                'MAXIMA_SEGUNDO_TRAMO' => $constants['RENTA_MAXIMA_SEGUNDO_TRAMO'],
                'PORCENTAJE_PRIMER_TRAMO' => $constants['PORCENTAJE_PRIMER_TRAMO'],
                'PORCENTAJE_SEGUNDO_TRAMO' => $constants['PORCENTAJE_SEGUNDO_TRAMO'],
                'PORCENTAJE_TERCER_TRAMO' => $constants['PORCENTAJE_TERCER_TRAMO'],
            ],
            'LISTAS' => [
                'TIPOS_CONTRATO' => PlanillaConstants::getTiposContrato(),
                'TIPOS_JORNADA' => PlanillaConstants::getTiposJornada(),
                'ESTADOS_EMPLEADO' => PlanillaConstants::getEstadosEmpleado(),
                'TIPOS_DOCUMENTO' => PlanillaConstants::getTiposDocumento(),
                'TIPOS_BAJA' => PlanillaConstants::getTiposBaja(),
            ]
        ];

        return response()->json($organizedConstants);
    }
}
