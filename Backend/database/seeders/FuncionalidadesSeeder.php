<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Admin\Funcionalidad;
use Illuminate\Support\Facades\Log;

class FuncionalidadesSeeder extends Seeder
{

    public function run()
    {
        $this->command->info('Iniciando seeder de funcionalidades...');

        $funcionalidades = [
            [
                'nombre' => 'Chat Asistente IA',
                'slug' => 'chat-asistente-ia',
                'descripcion' => 'Acceso al asistente virtual con Inteligencia Artificial',
                'orden' => 1
            ],
            [
                'nombre' => 'Chatbot WhatsApp',
                'slug' => 'chatbot-whatsapp',
                'descripcion' => 'Acceso al chatbot de WhatsApp',
                'orden' => 2
            ],
            [
                'nombre' => 'Cobro de Propina',
                'slug' => 'cobro-propina',
                'descripcion' => 'Permite cobrar propina en las ventas del módulo de facturación',
                'orden' => 3
            ],
            [
                'nombre' => 'Contabilidad',
                'slug' => 'contabilidad',
                'descripcion' => 'Acceso al módulo de contabilidad',
                'orden' => 4
            ],
            [
                'nombre' => 'Fidelización de Clientes',
                'slug' => 'fidelizacion-clientes',
                'descripcion' => 'Sistema de acumulación y canje de puntos para fidelizar clientes',
                'orden' => 5
            ],
            [
                'nombre' => 'Inteligencia de negocios V2',
                'slug' => 'inteligencia-negocios-v2',
                'descripcion' => 'Acceso al dashboard de inteligencia de negocios (versión 2)',
                'orden' => 6
            ],
            [
                'nombre' => 'Importación masiva de compras (JSON DTE)',
                'slug' => 'importacion-masiva-compras-json',
                'descripcion' => 'Permite importar varias compras desde archivos JSON de DTE en el listado de compras',
                'orden' => 7
            ],
            [
                'nombre' => 'Importación masiva de gastos (JSON DTE)',
                'slug' => 'importacion-masiva-gastos-json',
                'descripcion' => 'Permite importar varios gastos desde archivos JSON de DTE en el listado de gastos',
                'orden' => 8
            ],
            [

                'nombre' => 'Transformación de productos',
                'slug' => 'transformacion-productos',
                'descripcion' => 'Permite convertir un producto en otros (entradas y salidas de stock) desde el módulo de inventario',
                'orden' => 9
            ],
            [
                'nombre' => 'Descarga automatizada de DTEs',
                'slug' => 'descarga-automatizada-dtes',
                'descripcion' => 'Conectar cuentas de correo (Gmail/IMAP) y descargar, validar y procesar DTEs recibidos',
                'orden' => 10
            ],
            [
                'nombre' => 'Módulo de Presentaciones',
                'slug' => 'modulo-presentaciones-productos',
                'descripcion' => 'Permite gestionar presentaciones de productos',
                'orden' => 11
            ],
            [
                'nombre' => 'Integración BoxFul',
                'slug' => 'integracion-boxful',
                'descripcion' => 'Permite integrar con el servicio de envíos BoxFul',
                'orden' => 12
            ],
            [
                'nombre' => 'Multimoneda',
                'slug' => 'multimoneda',
                'descripcion' => 'Permite registrar documentos en distintas monedas, guardando el tipo de cambio y el valor de conversión en cada transacción',
                'orden' => 13,
            ],
            [
                'nombre' => 'Comisiones de Vendedores',
                'slug' => 'comisiones-vendedores',
                'descripcion' => 'Comisiones por categoría/subcategoría de producto con ledger y liquidación',
                'orden' => 20
            ],
            [
                'nombre' => 'Bonos de Vendedores',
                'slug' => 'bonos-vendedores',
                'descripcion' => 'Motor de bonos por reglas (independiente de comisiones)',
                'orden' => 21
            ],
            [
                'nombre' => 'Gift Cards',
                'slug' => 'gift-cards',
                'descripcion' => 'Emisión y redención de gift cards con saldo parcial',
                'orden' => 22
            ],
            [
                'nombre' => 'Créditos a clientes',
                'slug' => 'creditos-clientes',
                'descripcion' => 'Contratos de crédito a clientes con cuotas y facturación programada',
                'orden' => 23
            ],
            //Se pueden agregar mas funcionalidades con el mismo formato
        ];

        $contador = 0;

        foreach ($funcionalidades as $funcionalidad) {
            try {
                Funcionalidad::updateOrCreate(
                    ['slug' => $funcionalidad['slug']],
                    $funcionalidad
                );
                $contador++;
            } catch (\Exception $e) {
                Log::error("Error al crear/actualizar funcionalidad {$funcionalidad['nombre']}: " . $e->getMessage());
                $this->command->error("Error al procesar la funcionalidad {$funcionalidad['nombre']}: " . $e->getMessage());
            }
        }

        $this->command->info("Seeder completado: {$contador} funcionalidades procesadas correctamente");
    }
}
