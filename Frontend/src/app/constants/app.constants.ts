export const AppConstants = {

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
            PRECIO: 19.99,
            CARACTERISTICAS: [
                'Funciones básicas',
                'Soporte por correo', 
                'Límite de usuarios básico'
            ]
        },
        ESTANDAR: {
            NOMBRE: 'Estándar',
            PRECIO: 28.25,
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
            CARACTERISTICAS: [
                'Todas las funciones estándar',
                'Acceso a API',
                'Soporte 24/7'
            ]
        }
    }
};