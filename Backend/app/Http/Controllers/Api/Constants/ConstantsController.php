<?php

namespace App\Http\Controllers\Api\Constants;

use App\Constants\PlanillaConstants;
use App\Constants\ClienteNotasConstants;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use ReflectionClass;

class ConstantsController extends Controller
{

    public function getAppConstants() {


        $appConstants = [
            'planilla' => $this->getPlanillaConstants(),
            'cliente_notas' => $this->getClienteNotasConstants(),
            // 'ventas' => $this->getVentasConstants(), ejmplo
        ];

        return response()->json($appConstants);

    }

    private function getPlanillaConstants()
    {
        $reflection = new ReflectionClass(PlanillaConstants::class);
        $constants = $reflection->getConstants();

        // Organizamos las constantes por categorías ACTUALIZADAS
        $organizedConstants = [
            'original' => [
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
                    'MINIMA' => $constants['RENTA_MINIMA'], // Ahora 550.00
                    'MAXIMA_PRIMER_TRAMO' => $constants['RENTA_MAXIMA_PRIMER_TRAMO'],
                    'MAXIMA_SEGUNDO_TRAMO' => $constants['RENTA_MAXIMA_SEGUNDO_TRAMO'],
                    'PORCENTAJE_PRIMER_TRAMO' => $constants['PORCENTAJE_PRIMER_TRAMO'],
                    'PORCENTAJE_SEGUNDO_TRAMO' => $constants['PORCENTAJE_SEGUNDO_TRAMO'],
                    'PORCENTAJE_TERCER_TRAMO' => $constants['PORCENTAJE_TERCER_TRAMO'],
                ],
                // NUEVAS TABLAS 2025
                'RENTA_TABLAS' => [
                    'MENSUAL' => [
                        'TRAMO_1' => [
                            'DESDE' => $constants['RENTA_MENSUAL_TRAMO_1_DESDE'],
                            'HASTA' => $constants['RENTA_MENSUAL_TRAMO_1_HASTA'],
                            'PORCENTAJE' => $constants['RENTA_MENSUAL_TRAMO_1_PORCENTAJE'],
                            'SOBRE_EXCESO' => $constants['RENTA_MENSUAL_TRAMO_1_SOBRE_EXCESO'],
                            'CUOTA_FIJA' => $constants['RENTA_MENSUAL_TRAMO_1_CUOTA_FIJA']
                        ],
                        'TRAMO_2' => [
                            'DESDE' => $constants['RENTA_MENSUAL_TRAMO_2_DESDE'],
                            'HASTA' => $constants['RENTA_MENSUAL_TRAMO_2_HASTA'],
                            'PORCENTAJE' => $constants['RENTA_MENSUAL_TRAMO_2_PORCENTAJE'],
                            'SOBRE_EXCESO' => $constants['RENTA_MENSUAL_TRAMO_2_SOBRE_EXCESO'],
                            'CUOTA_FIJA' => $constants['RENTA_MENSUAL_TRAMO_2_CUOTA_FIJA']
                        ],
                        'TRAMO_3' => [
                            'DESDE' => $constants['RENTA_MENSUAL_TRAMO_3_DESDE'],
                            'HASTA' => $constants['RENTA_MENSUAL_TRAMO_3_HASTA'],
                            'PORCENTAJE' => $constants['RENTA_MENSUAL_TRAMO_3_PORCENTAJE'],
                            'SOBRE_EXCESO' => $constants['RENTA_MENSUAL_TRAMO_3_SOBRE_EXCESO'],
                            'CUOTA_FIJA' => $constants['RENTA_MENSUAL_TRAMO_3_CUOTA_FIJA']
                        ],
                        'TRAMO_4' => [
                            'DESDE' => $constants['RENTA_MENSUAL_TRAMO_4_DESDE'],
                            'HASTA' => $constants['RENTA_MENSUAL_TRAMO_4_HASTA'],
                            'PORCENTAJE' => $constants['RENTA_MENSUAL_TRAMO_4_PORCENTAJE'],
                            'SOBRE_EXCESO' => $constants['RENTA_MENSUAL_TRAMO_4_SOBRE_EXCESO'],
                            'CUOTA_FIJA' => $constants['RENTA_MENSUAL_TRAMO_4_CUOTA_FIJA']
                        ]
                    ],
                    'QUINCENAL' => [
                        'TRAMO_1' => [
                            'DESDE' => $constants['RENTA_QUINCENAL_TRAMO_1_DESDE'],
                            'HASTA' => $constants['RENTA_QUINCENAL_TRAMO_1_HASTA'],
                            'PORCENTAJE' => $constants['RENTA_QUINCENAL_TRAMO_1_PORCENTAJE'],
                            'SOBRE_EXCESO' => $constants['RENTA_QUINCENAL_TRAMO_1_SOBRE_EXCESO'],
                            'CUOTA_FIJA' => $constants['RENTA_QUINCENAL_TRAMO_1_CUOTA_FIJA']
                        ],
                        'TRAMO_2' => [
                            'DESDE' => $constants['RENTA_QUINCENAL_TRAMO_2_DESDE'],
                            'HASTA' => $constants['RENTA_QUINCENAL_TRAMO_2_HASTA'],
                            'PORCENTAJE' => $constants['RENTA_QUINCENAL_TRAMO_2_PORCENTAJE'],
                            'SOBRE_EXCESO' => $constants['RENTA_QUINCENAL_TRAMO_2_SOBRE_EXCESO'],
                            'CUOTA_FIJA' => $constants['RENTA_QUINCENAL_TRAMO_2_CUOTA_FIJA']
                        ],
                        'TRAMO_3' => [
                            'DESDE' => $constants['RENTA_QUINCENAL_TRAMO_3_DESDE'],
                            'HASTA' => $constants['RENTA_QUINCENAL_TRAMO_3_HASTA'],
                            'PORCENTAJE' => $constants['RENTA_QUINCENAL_TRAMO_3_PORCENTAJE'],
                            'SOBRE_EXCESO' => $constants['RENTA_QUINCENAL_TRAMO_3_SOBRE_EXCESO'],
                            'CUOTA_FIJA' => $constants['RENTA_QUINCENAL_TRAMO_3_CUOTA_FIJA']
                        ],
                        'TRAMO_4' => [
                            'DESDE' => $constants['RENTA_QUINCENAL_TRAMO_4_DESDE'],
                            'HASTA' => $constants['RENTA_QUINCENAL_TRAMO_4_HASTA'],
                            'PORCENTAJE' => $constants['RENTA_QUINCENAL_TRAMO_4_PORCENTAJE'],
                            'SOBRE_EXCESO' => $constants['RENTA_QUINCENAL_TRAMO_4_SOBRE_EXCESO'],
                            'CUOTA_FIJA' => $constants['RENTA_QUINCENAL_TRAMO_4_CUOTA_FIJA']
                        ]
                    ],
                    'SEMANAL' => [
                        'TRAMO_1' => [
                            'DESDE' => $constants['RENTA_SEMANAL_TRAMO_1_DESDE'],
                            'HASTA' => $constants['RENTA_SEMANAL_TRAMO_1_HASTA'],
                            'PORCENTAJE' => $constants['RENTA_SEMANAL_TRAMO_1_PORCENTAJE'],
                            'SOBRE_EXCESO' => $constants['RENTA_SEMANAL_TRAMO_1_SOBRE_EXCESO'],
                            'CUOTA_FIJA' => $constants['RENTA_SEMANAL_TRAMO_1_CUOTA_FIJA']
                        ],
                        'TRAMO_2' => [
                            'DESDE' => $constants['RENTA_SEMANAL_TRAMO_2_DESDE'],
                            'HASTA' => $constants['RENTA_SEMANAL_TRAMO_2_HASTA'],
                            'PORCENTAJE' => $constants['RENTA_SEMANAL_TRAMO_2_PORCENTAJE'],
                            'SOBRE_EXCESO' => $constants['RENTA_SEMANAL_TRAMO_2_SOBRE_EXCESO'],
                            'CUOTA_FIJA' => $constants['RENTA_SEMANAL_TRAMO_2_CUOTA_FIJA']
                        ],
                        'TRAMO_3' => [
                            'DESDE' => $constants['RENTA_SEMANAL_TRAMO_3_DESDE'],
                            'HASTA' => $constants['RENTA_SEMANAL_TRAMO_3_HASTA'],
                            'PORCENTAJE' => $constants['RENTA_SEMANAL_TRAMO_3_PORCENTAJE'],
                            'SOBRE_EXCESO' => $constants['RENTA_SEMANAL_TRAMO_3_SOBRE_EXCESO'],
                            'CUOTA_FIJA' => $constants['RENTA_SEMANAL_TRAMO_3_CUOTA_FIJA']
                        ],
                        'TRAMO_4' => [
                            'DESDE' => $constants['RENTA_SEMANAL_TRAMO_4_DESDE'],
                            'HASTA' => $constants['RENTA_SEMANAL_TRAMO_4_HASTA'],
                            'PORCENTAJE' => $constants['RENTA_SEMANAL_TRAMO_4_PORCENTAJE'],
                            'SOBRE_EXCESO' => $constants['RENTA_SEMANAL_TRAMO_4_SOBRE_EXCESO'],
                            'CUOTA_FIJA' => $constants['RENTA_SEMANAL_TRAMO_4_CUOTA_FIJA']
                        ]
                    ]
                ],
                'RECALCULOS' => [
                    'JUNIO' => [
                        'TRAMO_1' => [
                            'DESDE' => $constants['RENTA_RECALCULO_JUNIO_TRAMO_1_DESDE'],
                            'HASTA' => $constants['RENTA_RECALCULO_JUNIO_TRAMO_1_HASTA'],
                            'PORCENTAJE' => $constants['RENTA_RECALCULO_JUNIO_TRAMO_1_PORCENTAJE'],
                            'CUOTA_FIJA' => $constants['RENTA_RECALCULO_JUNIO_TRAMO_1_CUOTA_FIJA']
                        ],
                        'TRAMO_2' => [
                            'DESDE' => $constants['RENTA_RECALCULO_JUNIO_TRAMO_2_DESDE'],
                            'HASTA' => $constants['RENTA_RECALCULO_JUNIO_TRAMO_2_HASTA'],
                            'PORCENTAJE' => $constants['RENTA_RECALCULO_JUNIO_TRAMO_2_PORCENTAJE'],
                            'SOBRE_EXCESO' => $constants['RENTA_RECALCULO_JUNIO_TRAMO_2_SOBRE_EXCESO'],
                            'CUOTA_FIJA' => $constants['RENTA_RECALCULO_JUNIO_TRAMO_2_CUOTA_FIJA']
                        ],
                        'TRAMO_3' => [
                            'DESDE' => $constants['RENTA_RECALCULO_JUNIO_TRAMO_3_DESDE'],
                            'HASTA' => $constants['RENTA_RECALCULO_JUNIO_TRAMO_3_HASTA'],
                            'PORCENTAJE' => $constants['RENTA_RECALCULO_JUNIO_TRAMO_3_PORCENTAJE'],
                            'SOBRE_EXCESO' => $constants['RENTA_RECALCULO_JUNIO_TRAMO_3_SOBRE_EXCESO'],
                            'CUOTA_FIJA' => $constants['RENTA_RECALCULO_JUNIO_TRAMO_3_CUOTA_FIJA']
                        ],
                        'TRAMO_4' => [
                            'DESDE' => $constants['RENTA_RECALCULO_JUNIO_TRAMO_4_DESDE'],
                            'HASTA' => $constants['RENTA_RECALCULO_JUNIO_TRAMO_4_HASTA'],
                            'PORCENTAJE' => $constants['RENTA_RECALCULO_JUNIO_TRAMO_4_PORCENTAJE'],
                            'SOBRE_EXCESO' => $constants['RENTA_RECALCULO_JUNIO_TRAMO_4_SOBRE_EXCESO'],
                            'CUOTA_FIJA' => $constants['RENTA_RECALCULO_JUNIO_TRAMO_4_CUOTA_FIJA']
                        ]
                    ],
                    'DICIEMBRE' => [
                        'TRAMO_1' => [
                            'DESDE' => $constants['RENTA_RECALCULO_DICIEMBRE_TRAMO_1_DESDE'],
                            'HASTA' => $constants['RENTA_RECALCULO_DICIEMBRE_TRAMO_1_HASTA'],
                            'PORCENTAJE' => $constants['RENTA_RECALCULO_DICIEMBRE_TRAMO_1_PORCENTAJE'],
                            'CUOTA_FIJA' => $constants['RENTA_RECALCULO_DICIEMBRE_TRAMO_1_CUOTA_FIJA']
                        ],
                        'TRAMO_2' => [
                            'DESDE' => $constants['RENTA_RECALCULO_DICIEMBRE_TRAMO_2_DESDE'],
                            'HASTA' => $constants['RENTA_RECALCULO_DICIEMBRE_TRAMO_2_HASTA'],
                            'PORCENTAJE' => $constants['RENTA_RECALCULO_DICIEMBRE_TRAMO_2_PORCENTAJE'],
                            'SOBRE_EXCESO' => $constants['RENTA_RECALCULO_DICIEMBRE_TRAMO_2_SOBRE_EXCESO'],
                            'CUOTA_FIJA' => $constants['RENTA_RECALCULO_DICIEMBRE_TRAMO_2_CUOTA_FIJA']
                        ],
                        'TRAMO_3' => [
                            'DESDE' => $constants['RENTA_RECALCULO_DICIEMBRE_TRAMO_3_DESDE'],
                            'HASTA' => $constants['RENTA_RECALCULO_DICIEMBRE_TRAMO_3_HASTA'],
                            'PORCENTAJE' => $constants['RENTA_RECALCULO_DICIEMBRE_TRAMO_3_PORCENTAJE'],
                            'SOBRE_EXCESO' => $constants['RENTA_RECALCULO_DICIEMBRE_TRAMO_3_SOBRE_EXCESO'],
                            'CUOTA_FIJA' => $constants['RENTA_RECALCULO_DICIEMBRE_TRAMO_3_CUOTA_FIJA']
                        ],
                        'TRAMO_4' => [
                            'DESDE' => $constants['RENTA_RECALCULO_DICIEMBRE_TRAMO_4_DESDE'],
                            'HASTA' => $constants['RENTA_RECALCULO_DICIEMBRE_TRAMO_4_HASTA'],
                            'PORCENTAJE' => $constants['RENTA_RECALCULO_DICIEMBRE_TRAMO_4_PORCENTAJE'],
                            'SOBRE_EXCESO' => $constants['RENTA_RECALCULO_DICIEMBRE_TRAMO_4_SOBRE_EXCESO'],
                            'CUOTA_FIJA' => $constants['RENTA_RECALCULO_DICIEMBRE_TRAMO_4_CUOTA_FIJA']
                        ]
                    ]
                ],
                'DEDUCCION_EMPLEADOS_ASALARIADOS' => $constants['DEDUCCION_EMPLEADOS_ASALARIADOS'],
                'AGUINALDO' => [
                    'EXENTO_DECRETO_2023' => $constants['AGUINALDO_EXENTO_DECRETO_2023'],
                    'ESTADOS' => [
                        'BORRADOR' => $constants['AGUINALDO_BORRADOR'],
                        'PAGADO' => $constants['AGUINALDO_PAGADO'],
                    ]
                ],
                'LISTAS' => [
                    'TIPOS_CONTRATO' => PlanillaConstants::getTiposContrato(),
                    'TIPOS_JORNADA' => PlanillaConstants::getTiposJornada(),
                    'ESTADOS_EMPLEADO' => PlanillaConstants::getEstadosEmpleado(),
                    'TIPOS_DOCUMENTO' => PlanillaConstants::getTiposDocumento(),
                    'TIPOS_BAJA' => PlanillaConstants::getTiposBaja(),
                ]
            ]
        ];

        return $organizedConstants;
    }

    private function getClienteNotasConstants()
    {
        $reflection = new ReflectionClass(ClienteNotasConstants::class);
        $constants = $reflection->getConstants();

        // Organizamos las constantes por categorías
        $organizedConstants = [
            'original' => [
                'ESTADOS' => [
                    'ACTIVO' => $constants['ESTADO_ACTIVO'],
                    'INACTIVO' => $constants['ESTADO_INACTIVO'],
                ],
                'TIPOS_INTERACCION' => [
                    'VISITA_PRESENCIAL' => $constants['TIPO_VISITA_PRESENCIAL'],
                    'LLAMADA_TELEFONICA' => $constants['TIPO_LLAMADA_TELEFONICA'],
                    'WHATSAPP' => $constants['TIPO_WHATSAPP'],
                    'EMAIL' => $constants['TIPO_EMAIL'],
                    'NOTA_INTERNA' => $constants['TIPO_NOTA_INTERNA'],
                    'PREFERENCIAS' => $constants['TIPO_PREFERENCIAS'],
                    'QUEJAS' => $constants['TIPO_QUEJAS'],
                ],
                'TIPOS_VISITA' => [
                    'PRESENCIAL' => $constants['VISITA_PRESENCIAL'],
                    'VIRTUAL' => $constants['VISITA_VIRTUAL'],
                ],
                'PRIORIDADES' => [
                    'BAJA' => $constants['PRIORIDAD_BAJA'],
                    'MEDIA' => $constants['PRIORIDAD_MEDIA'],
                    'ALTA' => $constants['PRIORIDAD_ALTA'],
                    'URGENTE' => $constants['PRIORIDAD_URGENTE'],
                ],
                'ESTADOS_NOTA' => [
                    'ACTIVO' => $constants['ESTADO_NOTA_ACTIVO'],
                    'PENDIENTE' => $constants['ESTADO_NOTA_PENDIENTE'],
                    'EN_PROCESO' => $constants['ESTADO_NOTA_EN_PROCESO'],
                    'RESUELTO' => $constants['ESTADO_NOTA_RESUELTO'],
                    'ARCHIVADO' => $constants['ESTADO_NOTA_ARCHIVADO'],
                ],
                'ICONOS' => $constants['ICONOS'],
                'COLORES_PRIORIDAD' => $constants['COLORES_PRIORIDAD'],
                'COLORES_ESTADO' => $constants['COLORES_ESTADO'],
                'TEXTOS_PRIORIDAD' => $constants['TEXTOS_PRIORIDAD'],
                'TEXTOS_ESTADO' => $constants['TEXTOS_ESTADO'],
                'TEXTOS_TIPO' => $constants['TEXTOS_TIPO'],
                'TEXTOS_TIPO_VISITA' => $constants['TEXTOS_TIPO_VISITA'],
                'RESPONSABLES' => $constants['RESPONSABLES'],
                'CATEGORIAS' => $constants['CATEGORIAS'],
                'CONFIGURACION' => [
                    'TIEMPO_SEGUIMIENTO_DEFAULT' => $constants['TIEMPO_SEGUIMIENTO_DEFAULT'],
                    'TIEMPO_RECORDATORIO' => $constants['TIEMPO_RECORDATORIO'],
                    'LIMITE_CARACTERES_TITULO' => $constants['LIMITE_CARACTERES_TITULO'],
                    'LIMITE_CARACTERES_CONTENIDO' => $constants['LIMITE_CARACTERES_CONTENIDO'],
                    'LIMITE_CARACTERES_RESOLUCION' => $constants['LIMITE_CARACTERES_RESOLUCION'],
                    'NOTAS_POR_PAGINA' => $constants['NOTAS_POR_PAGINA'],
                    'VISITAS_POR_PAGINA' => $constants['VISITAS_POR_PAGINA'],
                    'FILTRO_FECHA_DESDE_DEFAULT' => $constants['FILTRO_FECHA_DESDE_DEFAULT'],
                    'FILTRO_ESTADO_DEFAULT' => $constants['FILTRO_ESTADO_DEFAULT'],
                    'FILTRO_PRIORIDAD_DEFAULT' => $constants['FILTRO_PRIORIDAD_DEFAULT'],
                    'ESTADISTICAS_PERIODO_DEFAULT' => $constants['ESTADISTICAS_PERIODO_DEFAULT'],
                    'ESTADISTICAS_TOP_LIMIT' => $constants['ESTADISTICAS_TOP_LIMIT'],
                    'TITULO_MIN_LENGTH' => $constants['TITULO_MIN_LENGTH'],
                    'CONTENIDO_MIN_LENGTH' => $constants['CONTENIDO_MIN_LENGTH'],
                    'FECHA_FUTURA_MAX_DAYS' => $constants['FECHA_FUTURA_MAX_DAYS'],
                ],
                'NOTIFICACIONES' => [
                    'SEGUIMIENTO_PENDIENTE' => $constants['NOTIFICACION_SEGUIMIENTO_PENDIENTE'],
                    'NOTA_ALTA_PRIORIDAD' => $constants['NOTIFICACION_NOTA_ALTA_PRIORIDAD'],
                    'NOTA_VENCIDA' => $constants['NOTIFICACION_NOTA_VENCIDA'],
                ],
                'EXPORTACION' => [
                    'PDF' => $constants['FORMATO_EXPORTACION_PDF'],
                    'EXCEL' => $constants['FORMATO_EXPORTACION_EXCEL'],
                    'CSV' => $constants['FORMATO_EXPORTACION_CSV'],
                ],
                'METRICAS' => [
                    'TIEMPO_RESPUESTA_OBJETIVO' => $constants['METRICA_TIEMPO_RESPUESTA_OBJETIVO'],
                    'SATISFACCION_OBJETIVO' => $constants['METRICA_SATISFACCION_OBJETIVO'],
                    'RESOLUCION_OBJETIVO' => $constants['METRICA_RESOLUCION_OBJETIVO'],
                ]
            ]
        ];

        return $organizedConstants;
    }
}
