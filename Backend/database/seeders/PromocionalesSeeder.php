<?php

namespace Database\Seeders;

use App\Models\Promocional;
use Illuminate\Database\Seeder;

class PromocionalesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $codigosPromocionales = [
            'SMARTPYME2025' => [
                'descuento' => 50.00, // 50% de descuento
                'campania' => 'Boxful',
                'descripcion' => '50% de descuento para campaña Boxful',
                'tipo' => 'porcentaje',
                'activo' => true,
                'planes_permitidos' => ['Mensual', 'Trimestral'],
                'opciones' => [
                    'uso_maximo' => null, // sin límite
                    'uso_por_usuario' => 1,
                    'fecha_inicio' => '2025-01-01',
                    'fecha_expiracion' => '2025-12-31',
                    'monto_minimo' => null,
                    'monto_maximo' => null,
                    'combinable' => false
                ]
            ]
            //Agregar más códigos promocionales aquí con la misma estructura
        ];

        foreach ($codigosPromocionales as $codigo => $datos) {
            Promocional::updateOrCreate(
                ['codigo' => $codigo],
                [
                    'descuento' => $datos['descuento'],
                    'campania' => $datos['campania'],
                    'descripcion' => $datos['descripcion'],
                    'tipo' => $datos['tipo'],
                    'activo' => $datos['activo'],
                    'planes_permitidos' => $datos['planes_permitidos'],
                    'opciones' => $datos['opciones']
                ]
            );
        }
    }
}
