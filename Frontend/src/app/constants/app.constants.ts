export const AppConstants = {

    /** Días tras el vencimiento con acceso (1..N); suspensión desde |días| > N (coincidir con Backend config/constants.php). */
    DIAS_PRORROGA_SUSCRIPCION: 3,

    /** Coincidir con Backend config/constants.php — próximo cobro al registrar «Pago recibido». */
    DIAS_PAGO_RECIBIDO_PROXIMO_CICLO: 30,

    /** Días que suma «Conceder acceso temporal» (sin mover fecha de próximo pago). */
    DIAS_ACCESO_TEMPORAL_ADMIN: 2,

    ESTADOS_SUSCRIPCION: {
        ACTIVO: 'activo',
        INACTIVO: 'inactivo',
        CANCELADO: 'cancelado',
        PENDIENTE: 'pendiente',
        RENOVADO: 'renovado',
        EN_PRUEBA: 'en prueba'
    },

    PLANES: {
        EMPRENDEDOR: {
            NOMBRE: 'Emprendedor',
            PRECIO: 16.95,
            DURACION_DIAS: 30,
            DIAS_PERIODO_PRUEBA: 3,
            CARACTERISTICAS: [
                'Funciones básicas',
                'Soporte por correo', 
                'Límite de usuarios básico'
            ]
        },
        ESTANDAR: {
            NOMBRE: 'Estándar',
            PRECIO: 28.25,
            DURACION_DIAS: 30,
            DIAS_PERIODO_PRUEBA: 3,
            CARACTERISTICAS: [
                'Todas las funciones básicas',
                'Soporte prioritario',
                'Más usuarios permitidos',
                'Reportes avanzados'
            ]
        },
        AVANZADO: {
            NOMBRE: 'Avanzado',
            PRECIO: 56.50,
            DURACION_DIAS: 30,
            DIAS_PERIODO_PRUEBA: 3,
            CARACTERISTICAS: [
                'Todas las funciones estándar',
                'Acceso a API',
                'Soporte 24/7'
            ]
        },
        PRO: {
            NOMBRE: 'Pro',
            PRECIO: 113.00,
            DURACION_DIAS: 30,
            DIAS_PERIODO_PRUEBA: 3,
            CARACTERISTICAS: [
                'Todas las funciones avanzadas',
                'Acceso a API',
                'Soporte 24/7'
            ]
        }
    },

    PLANID: {
        EMPRENDEDOR: 1,
        ESTANDAR: 2,
        AVANZADO: 3,
        PRO: 4
    }
};