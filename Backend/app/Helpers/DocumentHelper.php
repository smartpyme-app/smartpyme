<?php
namespace App\Helpers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DocumentHelper
{
    public static function saveEmployeeDocument($file, $empresaId, $empleadoId, $disk = 'documentos')
    {
        try {
            $rutaCarpeta = 'empresa-' . $empresaId . '/empleado-' . $empleadoId;
            
            if (!Storage::disk($disk)->exists($rutaCarpeta)) {
                Storage::disk($disk)->makeDirectory($rutaCarpeta, 0755, true);
            }

            $nombreArchivo = time() . '_' . md5(uniqid() . $file->getClientOriginalName()) . '.' . $file->getClientOriginalExtension();
            $rutaCompleta = $rutaCarpeta . '/' . $nombreArchivo;

            if (Storage::disk($disk)->putFileAs($rutaCarpeta, $file, $nombreArchivo)) {
                return [
                    'success' => true,
                    'ruta' => $rutaCompleta,
                    'nombre' => $nombreArchivo
                ];
            }

            return [
                'success' => false,
                'error' => 'Error al guardar el archivo'
            ];
        } catch (\Exception $e) {
            Log::error('Error guardando documento: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}