<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ReporteConfiguracion extends Model
{
    use HasFactory;

    protected $table = 'reporte_configuraciones';

    protected $fillable = [
        'tipo_reporte',
        'activo',
        'frecuencia',
        'dias_semana',
        'dia_mes',
        'envio_matutino',
        'hora_matutino',
        'envio_mediodia',
        'hora_mediodia',
        'envio_nocturno',
        'hora_nocturno',
        'destinatarios',
        'asunto_correo',
        'id_empresa',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'envio_matutino' => 'boolean',
        'envio_mediodia' => 'boolean',
        'envio_nocturno' => 'boolean',
        'destinatarios' => 'array',
        'dias_semana' => 'array',
    ];

  
    public function debeEnviarseHoy()
    {
        $hoy = Carbon::today();
        
        switch ($this->frecuencia) {
            case 'diario':
                return true;
                
            case 'semanal':
                $diaSemana = $hoy->dayOfWeekIso;
                return in_array($diaSemana, $this->dias_semana ?? []);
                
            case 'mensual':
                $diaMes = $hoy->day;
                return $diaMes == $this->dia_mes;
                
            default:
                return false;
        }
    }

    /**
     * Determina si es hora de enviar el reporte según el horario específico
     */
    public function esHoraDeEnvio($horario)
    {
        if (!$this->$horario) {
            return false;
        }
        
        $horaAtributo = 'hora_' . substr($horario, 6); // Extrae 'matutino', 'mediodia' o 'nocturno'
        $horaConfiguracion = $this->$horaAtributo;
        
        if (!$horaConfiguracion) {
            return false;
        }
        
        $now = Carbon::now();
        $horaEnvio = Carbon::createFromFormat('H:i', $horaConfiguracion);
        
        // Comparamos solo hora y minuto
        return $now->format('H:i') === $horaEnvio->format('H:i');
    }

    /**
     * Relación con la empresa
     */
    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }
}