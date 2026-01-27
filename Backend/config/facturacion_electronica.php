<?php

/**
 * Configuración de Facturación Electrónica por País
 * 
 * Este archivo contiene la configuración de URLs, formatos y otros parámetros
 * específicos para cada país que soporta facturación electrónica.
 * 
 * Para agregar un nuevo país:
 * 1. Agregar entrada en el array 'paises'
 * 2. Incluir URLs de prueba y producción
 * 3. Especificar formato de documento (JSON/XML)
 * 4. Agregar cualquier otra configuración específica
 */

return [
    'paises' => [
        'SV' => [
            'nombre' => 'El Salvador',
            'urls' => [
                'prueba' => [
                    'base' => 'https://apitest.dtes.mh.gob.sv',
                    'auth' => 'https://apitest.dtes.mh.gob.sv/seguridad/auth',
                    'recepcion' => 'https://apitest.dtes.mh.gob.sv/fesv/recepciondte',
                    'anulacion' => 'https://apitest.dtes.mh.gob.sv/fesv/anulardte',
                    'contingencia' => 'https://apitest.dtes.mh.gob.sv/fesv/contingencia',
                    'consulta' => 'https://apitest.dtes.mh.gob.sv/fesv/recepcion/consultadte',
                ],
                'produccion' => [
                    'base' => 'https://api.dtes.mh.gob.sv',
                    'auth' => 'https://api.dtes.mh.gob.sv/seguridad/auth',
                    'recepcion' => 'https://api.dtes.mh.gob.sv/fesv/recepciondte',
                    'anulacion' => 'https://api.dtes.mh.gob.sv/fesv/anulardte',
                    'contingencia' => 'https://api.dtes.mh.gob.sv/fesv/contingencia',
                    'consulta' => 'https://api.dtes.mh.gob.sv/fesv/recepcion/consultadte',
                ],
            ],
            'firmador' => [
                'url' => 'https://firmador.smartpyme.site:8443/firmardocumento/',
                'alternativa' => 'https://facturadtesv.com:8443/firmardocumento/',
            ],
            'formato_documento' => 'JSON',
            'version' => 1,
            'tipos_documento' => [
                '01' => 'Factura',
                '03' => 'Comprobante de Crédito Fiscal',
                '04' => 'Nota de Remisión',
                '05' => 'Nota de Crédito',
                '06' => 'Nota de Débito',
                '07' => 'Comprobante de Retención',
                '08' => 'Comprobante de Liquidación',
                '09' => 'Documento Contable de Liquidación',
                '11' => 'Factura de Exportación',
                '14' => 'Factura de Sujeto Excluido',
                '15' => 'Comprobante de Donación',
            ],
            'consulta_publica' => 'https://admin.factura.gob.sv/consultaPublica',
        ],
        
        'CR' => [
            'nombre' => 'Costa Rica',
            'urls' => [
                'prueba' => [
                    'base' => 'https://api-sandbox.hacienda.go.cr',
                    'auth' => 'https://api-sandbox.hacienda.go.cr/auth/token',
                    'recepcion' => 'https://api-sandbox.hacienda.go.cr/fe/ae',
                    'anulacion' => 'https://api-sandbox.hacienda.go.cr/fe/anulacion',
                    'consulta' => 'https://api-sandbox.hacienda.go.cr/fe/consulta',
                ],
                'produccion' => [
                    'base' => 'https://api.hacienda.go.cr',
                    'auth' => 'https://api.hacienda.go.cr/auth/token',
                    'recepcion' => 'https://api.hacienda.go.cr/fe/ae',
                    'anulacion' => 'https://api.hacienda.go.cr/fe/anulacion',
                    'consulta' => 'https://api.hacienda.go.cr/fe/consulta',
                ],
            ],
            'firmador' => null, // Se maneja diferente en CR (probablemente con certificado local)
            'formato_documento' => 'XML', // Por confirmar
            'version' => 1,
            'tipos_documento' => [
                '01' => 'Factura Electrónica',
                '02' => 'Nota de Débito',
                '03' => 'Nota de Crédito',
                // Por confirmar según documentación oficial
            ],
            'consulta_publica' => null, // Por confirmar
        ],
    ],
    
    /**
     * Configuración global
     */
    'default' => [
        'timeout' => 30, // Timeout en segundos para peticiones HTTP
        'retry_attempts' => 3, // Intentos de reintento en caso de error
        'log_requests' => true, // Loggear todas las peticiones
    ],
];
