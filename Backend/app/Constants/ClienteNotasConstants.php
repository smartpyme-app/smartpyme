<?php

namespace App\Constants;

class ClienteNotasConstants
{
    // ESTADOS GENERALES
    const ESTADO_ACTIVO = 1;
    const ESTADO_INACTIVO = 0;

    // TIPOS DE INTERACCIÓN
    const TIPO_VISITA_PRESENCIAL = 'visita';
    const TIPO_LLAMADA_TELEFONICA = 'llamada';
    const TIPO_WHATSAPP = 'whatsapp';
    const TIPO_EMAIL = 'email';
    const TIPO_NOTA_INTERNA = 'comentarios';
    const TIPO_PREFERENCIAS = 'preferencias';
    const TIPO_QUEJAS = 'quejas';

    // TIPOS DE VISITA
    const VISITA_PRESENCIAL = 'presencial';
    const VISITA_VIRTUAL = 'virtual';

    // PRIORIDADES
    const PRIORIDAD_BAJA = 'low';
    const PRIORIDAD_MEDIA = 'medium';
    const PRIORIDAD_ALTA = 'high';
    const PRIORIDAD_URGENTE = 'urgent';

    // ESTADOS DE NOTA
    const ESTADO_NOTA_ACTIVO = 'activo';
    const ESTADO_NOTA_PENDIENTE = 'pendiente';
    const ESTADO_NOTA_EN_PROCESO = 'en_proceso';
    const ESTADO_NOTA_RESUELTO = 'resuelto';
    const ESTADO_NOTA_ARCHIVADO = 'archivado';

    // ICONOS POR TIPO DE INTERACCIÓN
    const ICONOS = [
        self::TIPO_VISITA_PRESENCIAL => '👤',
        self::TIPO_LLAMADA_TELEFONICA => '📞',
        self::TIPO_WHATSAPP => '💬',
        self::TIPO_EMAIL => '📧',
        self::TIPO_NOTA_INTERNA => '📝',
        self::TIPO_PREFERENCIAS => '⭐',
        self::TIPO_QUEJAS => '⚠️'
    ];

    // COLORES POR PRIORIDAD
    const COLORES_PRIORIDAD = [
        self::PRIORIDAD_BAJA => '#10b981',      // Verde
        self::PRIORIDAD_MEDIA => '#f59e0b',     // Amarillo
        self::PRIORIDAD_ALTA => '#ef4444',       // Rojo
        self::PRIORIDAD_URGENTE => '#dc2626'     // Rojo oscuro
    ];

    // COLORES POR ESTADO
    const COLORES_ESTADO = [
        self::ESTADO_NOTA_ACTIVO => '#10b981',      // Verde
        self::ESTADO_NOTA_PENDIENTE => '#f59e0b',   // Amarillo
        self::ESTADO_NOTA_EN_PROCESO => '#3b82f6',  // Azul
        self::ESTADO_NOTA_RESUELTO => '#059669',    // Verde oscuro
        self::ESTADO_NOTA_ARCHIVADO => '#6b7280'    // Gris
    ];

    // TEXTOS FORMATEADOS POR PRIORIDAD
    const TEXTOS_PRIORIDAD = [
        self::PRIORIDAD_BAJA => 'Baja',
        self::PRIORIDAD_MEDIA => 'Media',
        self::PRIORIDAD_ALTA => 'Alta',
        self::PRIORIDAD_URGENTE => 'Urgente'
    ];

    // TEXTOS FORMATEADOS POR ESTADO
    const TEXTOS_ESTADO = [
        self::ESTADO_NOTA_ACTIVO => 'Activo',
        self::ESTADO_NOTA_PENDIENTE => 'Pendiente',
        self::ESTADO_NOTA_EN_PROCESO => 'En Proceso',
        self::ESTADO_NOTA_RESUELTO => 'Resuelto',
        self::ESTADO_NOTA_ARCHIVADO => 'Archivado'
    ];

    // TEXTOS FORMATEADOS POR TIPO DE INTERACCIÓN
    const TEXTOS_TIPO = [
        self::TIPO_VISITA_PRESENCIAL => 'Visita Presencial',
        self::TIPO_LLAMADA_TELEFONICA => 'Llamada Telefónica',
        self::TIPO_WHATSAPP => 'WhatsApp',
        self::TIPO_EMAIL => 'Email',
        self::TIPO_NOTA_INTERNA => 'Nota Interna',
        self::TIPO_PREFERENCIAS => 'Preferencias',
        self::TIPO_QUEJAS => 'Quejas'
    ];

    // TEXTOS FORMATEADOS POR TIPO DE VISITA
    const TEXTOS_TIPO_VISITA = [
        self::VISITA_PRESENCIAL => 'Visita Presencial',
        self::VISITA_VIRTUAL => 'Visita Virtual'
    ];

    // RESPONSABLES PREDETERMINADOS
    const RESPONSABLES = [
        'jorge' => 'Jorge Martínez (Ventas)',
        'jennifer' => 'Jennifer Díaz (Customer Success)',
        'carla' => 'Carla Rodríguez (Soporte)'
    ];

    // CATEGORÍAS DE NOTAS
    const CATEGORIAS = [
        'seguimiento' => 'Seguimiento',
        'ventas' => 'Ventas',
        'soporte' => 'Soporte',
        'fidelizacion' => 'Fidelización',
        'quejas' => 'Quejas',
        'sugerencias' => 'Sugerencias',
        'general' => 'General'
    ];

    // CONFIGURACIÓN DE TIEMPO
    const TIEMPO_SEGUIMIENTO_DEFAULT = 7; // días
    const TIEMPO_RECORDATORIO = 3; // días antes del seguimiento

    // LÍMITES
    const LIMITE_CARACTERES_TITULO = 100;
    const LIMITE_CARACTERES_CONTENIDO = 1000;
    const LIMITE_CARACTERES_RESOLUCION = 500;

    // PAGINACIÓN
    const NOTAS_POR_PAGINA = 20;
    const VISITAS_POR_PAGINA = 20;

    // FILTROS POR DEFECTO
    const FILTRO_FECHA_DESDE_DEFAULT = 30; // días atrás
    const FILTRO_ESTADO_DEFAULT = self::ESTADO_NOTA_ACTIVO;
    const FILTRO_PRIORIDAD_DEFAULT = self::PRIORIDAD_MEDIA;

    // ESTADÍSTICAS
    const ESTADISTICAS_PERIODO_DEFAULT = 30; // días
    const ESTADISTICAS_TOP_LIMIT = 10;

    // NOTIFICACIONES
    const NOTIFICACION_SEGUIMIENTO_PENDIENTE = 'seguimiento_pendiente';
    const NOTIFICACION_NOTA_ALTA_PRIORIDAD = 'nota_alta_prioridad';
    const NOTIFICACION_NOTA_VENCIDA = 'nota_vencida';

    // EXPORTACIÓN
    const FORMATO_EXPORTACION_PDF = 'pdf';
    const FORMATO_EXPORTACION_EXCEL = 'excel';
    const FORMATO_EXPORTACION_CSV = 'csv';

    // VALIDACIONES
    const TITULO_MIN_LENGTH = 5;
    const CONTENIDO_MIN_LENGTH = 10;
    const FECHA_FUTURA_MAX_DAYS = 365;

    // MÉTRICAS DE RENDIMIENTO
    const METRICA_TIEMPO_RESPUESTA_OBJETIVO = 24; // horas
    const METRICA_SATISFACCION_OBJETIVO = 4.5; // escala 1-5
    const METRICA_RESOLUCION_OBJETIVO = 95; // porcentaje
}
