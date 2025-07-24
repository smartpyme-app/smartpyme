<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DepartamentosAreasSeeder extends Seeder
{
    public function run()
    {
        $empresaId = 45;
        
        // Obtener todas las sucursales de la empresa
        $sucursales = DB::table('sucursales')
            ->where('id_empresa', $empresaId)
            ->where('activo', 1)
            ->pluck('id');
            
        if ($sucursales->isEmpty()) {
            echo "No se encontraron sucursales activas para la empresa {$empresaId}\n";
            return;
        }
        
        $departamentosAreas = [
            'RRHH' => [
                'ATENCIÓN PERSONAL',
                'SERVICIOS PROFESIONALES',
                'INSUMOS ADMINISTRATIVOS',
                'UNIFORMES',
                'PLANILLA PERMANENTE CAVALIER',
                'PLANILLA TEMPORAL CAVALIER',
                'PLANILLA PERMANENTE KINARA',
                'PLANILLA TEMPORAL KINARA',
                'PASANTÍAS',
                'SELECCIÓN DE PERSONAL',
                'CAPACITACIONES'
            ],
            'COMPRAS' => [
                'PROMOCIONALES LOCAL',
                'PROMOCIONALES EXTERIOR',
                'MATERIA PRIMA DE PRODUCCIÓN',
                'INSUMOS DE PRODUCIÓN',
                'PRODUCTOS DE ESCRITURA',
                'INSUMOS ADMINISTRATIVOS',
                'GASTOS ADMINISTRATIVOS',
                'ATENCIÓN PROVEEDORES'
            ],
            'IMEX' => [
                'GASTOS DE IMPORTACIÓN',
                'IMPORTACIÓN RETAIL',
                'GASTOS DE EXPORTACIÓN',
                'EXPORTACIÓN RETAIL'
            ],
            'COMERCIAL' => [
                'VIÁTICOS RETAIL',
                'VIATICOS CORPORATIVO',
                'MUESTRAS RETAIL',
                'ATENCIÓN A CLIENTES',
                'MUESTRAS',
                'DISEÑO DE PRODUCTOS',
                'INNOVACIÓN Y DESARROLLO'
            ],
            'CONTABILIDAD' => [
                'TRÁMITES',
                'VIÁTICOS CONTABILIDAD',
                'SERVICIOS TERCERIZADOS',
                'IMPUESTOS'
            ],
            'MERCADEO' => [
                'ADS',
                'DESARROLLOS',
                'AGENCIA MKT',
                'CAMPAÑAS',
                'APOYO COLECCIÓN'
            ],
            'GERENCIA' => [
                'COMBUSTIBLE G.',
                'ALIMENTACIÓN G.',
                'FAMILIARES',
                'DONACIÓN',
                'CEO',
                'MEMBRESIAS',
                'LEGAL'
            ],
            'OPERACIONES' => [
                'G. ADMINISTRATIVOS',
                'SERVICIOS BÁSICOS',
                'REPARACIONES O MANTENIMIENTOS',
                'SEGURIDAD OCUPACIONAL',
                'AVERIAS DE PRODUCTOS'
            ],
            'PRODUCCIÓN' => [
                'MAQUINARIA',
                'INSUMOS PRODUCCIÓN',
                'PERSONAL PRODUCCIÓN',
                'PRODUCCIÓN EXTERNA',
                'TEMPORADA AGENDAS',
                'MARROQUINERIA',
                'INSUMOS O MATERIA PRIMA'
            ],
            'BODEGA' => [
                'PERSONAL BODEGA',
                'INSUMOS BODEGA',
                'MANTENIMIENTOS',
                'DESPACHO DE PRODUCTOS',
                'TERCERIZACIÓN DE SERVICIOS'
            ],
            'IT' => [
                'LICENCIAS Y PROGRAMAS',
                'COMPRA O MTTO DE EQUIPOS',
                'PERIFERICOS Y CONSUMIBLES',
                'IMPLEMENTACIONES',
                'MEJORAS DE IT',
                'OTROS PAGOS',
                'VIÁTICOS PERSONAL'
            ],
            'KINARA' => [
                'TRÁMITES O MUESTRAS',
                'PRODUCTOS KINARA',
                'INSUMOS PARA SALON',
                'GASTOS ADM KINARA',
                'MANTENIMIENTOS KINARA',
                'MARKETING KINARA',
                'ADECUACIONES KINARA'
            ],
            'LIFE' => [
                'INSUMOS',
                'ADECUACIONES LIFE',
                'COMPRA DE EQUIPOS',
                'MANTENIMIENTOS LIFE',
                'TRÁMITES Y SERVICIOS',
                'OTROS PAGOS',
                'SERVICIOS BÁSICOS',
                'MARKETING LIFE'
            ]
        ];

        foreach ($sucursales as $sucursalId) {
            foreach ($departamentosAreas as $nombreDepartamento => $areas) {
                // Crear departamento para cada sucursal
                $departamentoId = DB::table('departamentos_empresa')->insertGetId([
                    'nombre' => $nombreDepartamento,
                    'descripcion' => null,
                    'activo' => true,
                    'estado' => 1,
                    'id_sucursal' => $sucursalId,
                    'id_empresa' => $empresaId,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                // Crear áreas para este departamento
                foreach ($areas as $nombreArea) {
                    DB::table('areas_empresa')->insert([
                        'nombre' => $nombreArea,
                        'descripcion' => null,
                        'activo' => true,
                        'estado' => 1,
                        'id_departamento' => $departamentoId,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }
        }
    }
}