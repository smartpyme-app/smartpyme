<?php

namespace App\Console\Commands;

use App\Models\MH\Pais;
use App\Models\MH\EstadoPais;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ImportarEstadosPaises extends Command
{
    protected $signature = 'importar:estados {json_path?}';
    protected $description = 'Importa estados/provincias desde un archivo JSON';

    // Datos de códigos postales para El Salvador
    protected $codigosPostalesElSalvador = [
        'AH' => '2101', // Ahuachapán
        'SA' => '2201', // Santa Ana
        'SO' => '2301', // Sonsonate
        'CH' => '1312', // Chalatenango
        'LI' => '1501', // La Libertad
        'SS' => '1101', // San Salvador
        'CU' => '1401', // Cuscatlán
        'PA' => '1601', // La Paz
        'CA' => '1201', // Cabañas
        'SV' => '1701', // San Vicente
        'US' => '3401', // Usulután
        'SM' => '3301', // San Miguel
        'MO' => '3211', // Morazán
        'UN' => '3101'  // La Unión
    ];

    public function handle()
    {
        // Obtener la ruta del archivo JSON (valor por defecto o proporcionado)
        $jsonPath = $this->argument('json_path') ?: storage_path('app/data/states.json');
        
        // Verificar que el archivo existe
        if (!File::exists($jsonPath)) {
            $this->error("El archivo JSON no existe en la ruta: $jsonPath");
            return 1;
        }
        
        // Leer el contenido del archivo JSON
        $jsonData = File::get($jsonPath);
        
        // Decodificar el JSON a un array de PHP
        $states = json_decode($jsonData, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error("Error al decodificar JSON: " . json_last_error_msg());
            return 1;
        }
        
        // Obtener todos los países para mapear country_code a pais_id
        $paises = Pais::all()->keyBy('cod')->toArray();
        
        $this->info("Iniciando importación de estados...");
        $bar = $this->output->createProgressBar(count($states));
        
        $estadosCreados = 0;
        $estadosOmitidos = 0;
        $paisesNoEncontrados = [];
        
        foreach ($states as $state) {
            // Buscar el país correspondiente por código
            if (isset($paises[$state['country_code']])) {
                $paisId = $paises[$state['country_code']]['id'];
                
                // Verificar si ya existe un estado con ese nombre para ese país
                $existeEstado = EstadoPais::where('nombre', $state['name'])
                    ->where('pais_id', $paisId)
                    ->exists();
                
                // Determinar el código postal si es de El Salvador
                $codigoPostal = null;
                if ($state['country_code'] === 'SV' && isset($this->codigosPostalesElSalvador[$state['state_code']])) {
                    $codigoPostal = $this->codigosPostalesElSalvador[$state['state_code']];
                }
                
                if (!$existeEstado) {
                    EstadoPais::create([
                        'nombre' => $state['name'],
                        'codigo' => $state['state_code'],
                        'pais_id' => $paisId,
                        'type' => $state['type'] ?? null,
                        'latitude' => $state['latitude'] ?? null,
                        'longitude' => $state['longitude'] ?? null,
                        'codigo_postal' => $codigoPostal
                    ]);
                    
                    $estadosCreados++;
                } else {
                    // Si ya existe pero queremos actualizar el código postal
                    if ($state['country_code'] === 'SV' && $codigoPostal) {
                        EstadoPais::where('nombre', $state['name'])
                            ->where('pais_id', $paisId)
                            ->update(['codigo_postal' => $codigoPostal]);
                            
                        $this->info("Actualizado código postal para {$state['name']}");
                    }
                    $estadosOmitidos++;
                }
            } else {
                if (!in_array($state['country_code'], $paisesNoEncontrados)) {
                    $paisesNoEncontrados[] = $state['country_code'];
                }
                $estadosOmitidos++;
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine(2);
        
        $this->info("Importación completada:");
        $this->info("- Estados creados: $estadosCreados");
        $this->info("- Estados omitidos: $estadosOmitidos");
        
        if (count($paisesNoEncontrados) > 0) {
            $this->warn("- Países no encontrados en la base de datos: " . implode(', ', $paisesNoEncontrados));
            $this->info("  Asegúrate de que estos códigos de país existan en la tabla 'paises'.");
        }
        
        return 0;
    }
}