<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AsignarPaisesEmpresasSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        echo "🌍 Asignando países a empresas existentes...\n";

        // Obtener todas las empresas sin cod_pais asignado
        $empresas = DB::table('empresas')
            ->select('id', 'nombre', 'pais', 'cod_pais')
            ->whereNull('cod_pais')
            ->get();

        // Obtener todos los países de la tabla paises para hacer el mapeo
        $paises = DB::table('paises')
            ->select('cod', 'nombre')
            ->get()
            ->keyBy('nombre'); // Indexar por nombre para búsqueda rápida

        $actualizado = 0;
        $noEncontrado = 0;

        foreach ($empresas as $empresa) {
            $codPais = $this->buscarCodigoPais($empresa->pais, $paises);
            
            if ($codPais) {
                DB::table('empresas')
                    ->where('id', $empresa->id)
                    ->update(['cod_pais' => $codPais]);
                
                echo "✅ {$empresa->nombre} → '{$empresa->pais}' → {$codPais}\n";
                $actualizado++;
            } else {
                echo "⚠️  {$empresa->nombre} → No se encontró país: '{$empresa->pais}'\n";
                $noEncontrado++;
            }
        }

        echo "\n📊 Resumen:\n";
        echo "   ✅ Empresas actualizadas: {$actualizado}\n";
        echo "   ⚠️  Empresas sin país encontrado: {$noEncontrado}\n";
        
        if ($noEncontrado > 0) {
            echo "\n💡 Para empresas sin país encontrado:\n";
            echo "   1. Verificar que el país existe en la tabla 'paises'\n";
            echo "   2. Actualizar manualmente o agregar país faltante\n";
            echo "   3. Revisar variaciones de nombres (tildes, espacios, etc.)\n";
        }

        // Mostrar países disponibles en la tabla
        echo "\n📋 Países disponibles en la tabla 'paises':\n";
        $paisesDisponibles = DB::table('paises')->select('cod', 'nombre')->orderBy('nombre')->get();
        foreach ($paisesDisponibles as $pais) {
            echo "   {$pais->cod} → {$pais->nombre}\n";
        }
    }

    /**
     * Buscar código de país en la tabla paises basado en el nombre
     */
    private function buscarCodigoPais($nombrePais, $paises)
    {
        if (empty($nombrePais)) {
            return null;
        }

        // Normalizar el nombre del país (mayúsculas, sin espacios extra)
        $nombreNormalizado = strtoupper(trim($nombrePais));

        // 1. Búsqueda exacta (convertir nombre de BD a mayúsculas)
        foreach ($paises as $nombre => $pais) {
            if (strtoupper(trim($nombre)) === $nombreNormalizado) {
                return $pais->cod;
            }
        }

        // 2. Búsqueda parcial (contiene el texto)
        foreach ($paises as $nombre => $pais) {
            $nombrePaisBD = strtoupper(trim($nombre));
            
            // Si el nombre de empresa contiene el nombre del país de BD
            if (strpos($nombreNormalizado, $nombrePaisBD) !== false) {
                return $pais->cod;
            }
            
            // Si el nombre del país de BD contiene el nombre de empresa
            if (strpos($nombrePaisBD, $nombreNormalizado) !== false) {
                return $pais->cod;
            }
        }

        // 3. Búsqueda sin tildes ni caracteres especiales
        $nombreSinTildes = $this->quitarTildes($nombreNormalizado);
        
        foreach ($paises as $nombre => $pais) {
            $nombrePaisBDSinTildes = $this->quitarTildes(strtoupper(trim($nombre)));
            
            if ($nombreSinTildes === $nombrePaisBDSinTildes) {
                return $pais->cod;
            }
            
            if (strpos($nombreSinTildes, $nombrePaisBDSinTildes) !== false || 
                strpos($nombrePaisBDSinTildes, $nombreSinTildes) !== false) {
                return $pais->cod;
            }
        }

        return null; // No se encontró
    }

    /**
     * Quitar tildes y caracteres especiales para comparación
     */
    private function quitarTildes($texto)
    {
        $tildes = ['Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ', 'Ü'];
        $sinTildes = ['A', 'E', 'I', 'O', 'U', 'N', 'U'];
        
        return str_replace($tildes, $sinTildes, $texto);
    }
}