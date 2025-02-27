<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExtractProductAttributes extends Command
{
    protected $signature = 'attributes:extract-from-products';
    protected $description = 'Extract and create attributes from existing products';

    public function handle()
    {
        $this->info('Comenzando extracción de atributos...');

      
        $productos = DB::table('productos')
            ->select('id_empresa', 'talla', 'color', 'material')
            ->whereNotNull('id_empresa')
            ->get()
            ->groupBy('id_empresa');

        foreach ($productos as $id_empresa => $productosEmpresa) {
            $this->info("\nProcesando empresa ID: {$id_empresa}");

            
            $tallas = $productosEmpresa->pluck('talla')->unique()->filter();
            $colores = $productosEmpresa->pluck('color')->unique()->filter();
            $materiales = $productosEmpresa->pluck('material')->unique()->filter();

        
            $this->info("Tallas encontradas: " . $tallas->count());
            $this->info("Colores encontrados: " . $colores->count());
            $this->info("Materiales encontrados: " . $materiales->count());

            
            foreach ($tallas as $talla) {
                $exists = DB::table('producto_atributo_valores')
                    ->where('tipo', 'talla')
                    ->where('valor', $talla)
                    ->where('id_empresa', $id_empresa)
                    ->exists();

                if (!$exists && !empty($talla)) {
                    DB::table('producto_atributo_valores')->insert([
                        'tipo' => 'talla',
                        'valor' => $talla,
                        'id_empresa' => $id_empresa,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    $this->info("Talla agregada: {$talla}");
                }
            }


            foreach ($colores as $color) {
                $exists = DB::table('producto_atributo_valores')
                    ->where('tipo', 'color')
                    ->where('valor', $color)
                    ->where('id_empresa', $id_empresa)
                    ->exists();

                if (!$exists && !empty($color)) {
                    DB::table('producto_atributo_valores')->insert([
                        'tipo' => 'color',
                        'valor' => $color,
                        'id_empresa' => $id_empresa,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    $this->info("Color agregado: {$color}");
                }
            }

            
            foreach ($materiales as $material) {
                $exists = DB::table('producto_atributo_valores')
                    ->where('tipo', 'material')
                    ->where('valor', $material)
                    ->where('id_empresa', $id_empresa)
                    ->exists();

                if (!$exists && !empty($material)) {
                    DB::table('producto_atributo_valores')->insert([
                        'tipo' => 'material',
                        'valor' => $material,
                        'id_empresa' => $id_empresa,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    $this->info("Material agregado: {$material}");
                }
            }
        }

        $this->info('\n¡Proceso completado!');
    }
}