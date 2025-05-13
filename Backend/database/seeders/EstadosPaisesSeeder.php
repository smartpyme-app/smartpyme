<?php

namespace Database\Seeders;

use App\Models\MH\EstadoPais;
use App\Models\MH\Pais;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class EstadosPaisesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Ruta al archivo JSON
        $jsonPath = storage_path('app/data/states.json');
        
        // Verificar que el archivo existe
        if (!File::exists($jsonPath)) {
            $this->command->error("El archivo JSON no existe en la ruta: $jsonPath");
            return;
        }
        
        // Leer el contenido del archivo JSON
        $jsonData = File::get($jsonPath);
        
        // Decodificar el JSON a un array de PHP
        $states = json_decode($jsonData, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->command->error("Error al decodificar JSON: " . json_last_error_msg());
            return;
        }
        
        // Obtener todos los países para mapear country_code a pais_id
        $paises = Pais::all()->keyBy('cod')->toArray();
        
        $estadosCreados = 0;
        $estadosOmitidos = 0;
        
        foreach ($states as $state) {
            // Buscar el país correspondiente por código
            if (isset($paises[$state['country_code']])) {
                $paisId = $paises[$state['country_code']]['id'];
                
                // Verificar si ya existe un estado con ese nombre para ese país
                $existeEstado = EstadoPais::where('nombre', $state['name'])
                    ->where('pais_id', $paisId)
                    ->exists();
                
                if (!$existeEstado) {
                    EstadoPais::create([
                        'nombre' => $state['name'],
                        'codigo' => $state['state_code'],
                        'pais_id' => $paisId,
                        'type' => $state['type'] ?? null,
                    ]);
                    
                    $estadosCreados++;
                } else {
                    $estadosOmitidos++;
                }
            } else {
                $this->command->info("País no encontrado con código: {$state['country_code']}");
                $estadosOmitidos++;
            }
        }
        
        $this->command->info("Importación completada. Estados creados: $estadosCreados, Omitidos: $estadosOmitidos");
    }
}
